<?php

namespace App\Http\Controllers;

use App\Actions\CreateExpense;
use App\Actions\UpdateExpense;
use App\Events\ExpenseCommentCreated;
use App\Models\ActivityLog;
use App\Models\Expense;
use App\Models\ExpenseComment;
use App\Models\Settlement;
use App\Models\Tracker;
use App\Services\TrackerFinance;
use App\Services\TrackerNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Syncs only the small, financial core that can safely be queued on a device.
 * Each operation UUID is recorded, so reconnect retries cannot create duplicates.
 */
class OfflineSyncController extends Controller
{
    public function diagnostic(Request $request): JsonResponse
    {
        $data = $request->validate(['stage' => ['required', 'string', 'max:80'], 'message' => ['required', 'string', 'max:1000'], 'name' => ['nullable', 'string', 'max:160'], 'online' => ['required', 'boolean'], 'user_agent' => ['nullable', 'string', 'max:1000']]);
        Log::warning('SplitShare offline cache diagnostic', [...$data, 'user_id' => $request->user()->id]);
        return response()->json(['recorded' => true]);
    }

    public function bootstrap(Request $request, TrackerFinance $finance): JsonResponse
    {
        $user = $request->user();
        $trackers = Tracker::query()->where('status', 'active')
            ->whereHas('members', fn ($q) => $q->where('user_id', $user->id)->where('status', 'active'))
            ->with(['members.user:id,name,email', 'expenses.payer:id,name', 'expenses.splits.user:id,name', 'expenses.comments.author:id,name'])
            ->latest()->get();

        return response()->json([
            'user' => $user->only(['id', 'name', 'email']),
            'saved_at' => now()->toIso8601String(),
            'trackers' => $trackers->map(function (Tracker $tracker) use ($finance) {
                return [
                    'id' => $tracker->id, 'name' => $tracker->name, 'description' => $tracker->description,
                    'currency_code' => $tracker->currency_code, 'updated_at' => $tracker->updated_at?->toIso8601String(),
                    'members' => $tracker->members->where('status', 'active')->map(fn ($member) => [
                        'id' => $member->user_id, 'name' => $member->user?->name, 'role' => $member->role,
                    ])->values(),
                    'expenses' => $tracker->expenses->map(fn (Expense $expense) => [
                        'id' => $expense->id, 'description' => $expense->description, 'amount_minor' => $expense->amount_minor,
                        'expense_date' => $expense->expense_date?->format('Y-m-d'), 'paid_by_user_id' => $expense->paid_by_user_id,
                        'note' => $expense->note, 'version' => $expense->version, 'payer' => $expense->payer?->only(['id', 'name']),
                        'splits' => $expense->splits->map(fn ($split) => ['user_id' => $split->user_id, 'amount_minor' => $split->amount_minor, 'user' => $split->user?->only(['id', 'name'])]),
                        'comments' => $expense->comments->map(fn ($comment) => ['id' => $comment->id, 'body' => $comment->body, 'user_id' => $comment->user_id, 'created_at' => $comment->created_at?->toIso8601String(), 'author' => $comment->author?->only(['id', 'name'])]),
                    ])->values(),
                    'settlements' => Settlement::query()->where('tracker_id', $tracker->id)->latest('settlement_date')->get()->map(fn ($settlement) => $settlement->only(['id', 'from_user_id', 'to_user_id', 'amount_minor', 'settlement_date', 'note', 'version'])),
                    'debts' => $finance->directDebts($tracker),
                ];
            })->values(),
        ]);
    }

    public function batch(Request $request, TrackerFinance $finance, TrackerNotifier $notifier, CreateExpense $creator, UpdateExpense $updater): JsonResponse
    {
        $data = $request->validate(['operations' => ['required', 'array', 'max:50'], 'operations.*.id' => ['required', 'uuid'], 'operations.*.type' => ['required', 'string'], 'operations.*.tracker_id' => ['required', 'uuid'], 'operations.*.payload' => ['required', 'array']]);
        $results = [];
        foreach ($data['operations'] as $operation) {
            $existing = DB::table('offline_sync_operations')->where('id', $operation['id'])->first();
            if ($existing) { $results[] = ['id' => $operation['id'], 'status' => 'applied', 'result' => json_decode($existing->result, true)]; continue; }
            try {
                $result = $this->apply($request, $operation, $finance, $notifier, $creator, $updater);
                DB::table('offline_sync_operations')->insert(['id' => $operation['id'], 'user_id' => $request->user()->id, 'operation_type' => $operation['type'], 'result' => json_encode($result), 'applied_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
                $results[] = ['id' => $operation['id'], 'status' => 'applied', 'result' => $result];
            } catch (\Throwable $e) {
                report($e);
                $results[] = ['id' => $operation['id'], 'status' => 'rejected', 'message' => $e instanceof ValidationException ? $e->getMessage() : 'This change could not be synced.'];
            }
        }
        return response()->json(['results' => $results]);
    }

    private function apply(Request $request, array $operation, TrackerFinance $finance, TrackerNotifier $notifier, CreateExpense $creator, UpdateExpense $updater): array
    {
        $tracker = Tracker::findOrFail($operation['tracker_id']);
        $actor = $request->user(); $payload = $operation['payload'];
        if ($operation['type'] === 'expense.create') {
            $this->authorize('update', $tracker);
            $validated = validator($payload, ['description' => ['required','string','max:255'], 'amount' => ['required','regex:/^\d+(\.\d{1,2})?$/'], 'paid_by_user_id' => ['required','integer'], 'expense_date' => ['required','date'], 'note' => ['nullable','string','max:2000'], 'participants' => ['required','array','min:1'], 'participants.*' => ['integer']])->validate();
            $this->assertMembers($tracker, $validated);
            $expense = $creator->handle($tracker, $actor, [...$validated, 'amount_minor' => $this->minor($validated['amount']), 'participants' => array_values(array_unique($validated['participants']))]);
            $notifier->members($tracker, $actor, 'expense.created', 'New expense in '.$tracker->name, $actor->name.' added '.$expense->description.'.', route('trackers.expenses.show', [$tracker, $expense]), ['expense_id' => $expense->id]);
            return ['resource_id' => $expense->id, 'version' => $expense->version];
        }
        if ($operation['type'] === 'expense.update') {
            $this->authorize('update', $tracker); $expense = Expense::where('tracker_id', $tracker->id)->findOrFail($payload['id'] ?? '');
            $validated = validator($payload, ['id' => ['required','uuid'], 'base_version' => ['nullable','integer'], 'description' => ['required','string','max:255'], 'amount' => ['required','regex:/^\d+(\.\d{1,2})?$/'], 'paid_by_user_id' => ['required','integer'], 'expense_date' => ['required','date'], 'note' => ['nullable','string','max:2000'], 'participants' => ['required','array','min:1'], 'participants.*' => ['integer']])->validate();
            $this->assertMembers($tracker, $validated);
            // Last edit wins: a later reconnect intentionally overwrites an older server version.
            $expense = $updater->handle($expense, $actor, [...$validated, 'amount_minor' => $this->minor($validated['amount']), 'participants' => array_values(array_unique($validated['participants']))]);
            return ['resource_id' => $expense->id, 'version' => $expense->version];
        }
        if ($operation['type'] === 'settlement.create') {
            $this->authorize('settle', $tracker);
            $validated = validator($payload, ['from_user_id' => ['required','integer'], 'to_user_id' => ['required','integer','different:from_user_id'], 'amount' => ['required','regex:/^\d+(\.\d{1,2})?$/'], 'settlement_date' => ['required','date'], 'note' => ['nullable','string','max:2000']])->validate();
            $amount = $this->minor($validated['amount']);
            $debt = collect($finance->directDebts($tracker, false))->first(fn ($debt) => $debt['from_user_id'] === (int) $validated['from_user_id'] && $debt['to_user_id'] === (int) $validated['to_user_id']);
            if (! $debt || $amount > $debt['amount_minor']) throw ValidationException::withMessages(['amount' => 'The settlement is no longer valid. Refresh and try again.']);
            $settlement = Settlement::create([...$validated, 'tracker_id' => $tracker->id, 'amount_minor' => $amount, 'created_by' => $actor->id]); $finance->forget($tracker);
            return ['resource_id' => $settlement->id, 'version' => $settlement->version];
        }
        if ($operation['type'] === 'expense-comment.create') {
            $expense = Expense::where('tracker_id', $tracker->id)->findOrFail($payload['expense_id'] ?? ''); abort_unless($actor->can('comment', $tracker), 403);
            $body = trim(validator($payload, ['expense_id' => ['required','uuid'], 'body' => ['required','string','max:2000']])->validate()['body']);
            $comment = ExpenseComment::create(['expense_id' => $expense->id, 'user_id' => $actor->id, 'body' => $body]);
            ActivityLog::create(['tracker_id' => $tracker->id, 'actor_user_id' => $actor->id, 'action' => 'expense.commented', 'subject_type' => 'expense_comment', 'subject_id' => $comment->id, 'metadata' => ['expense_id' => $expense->id, 'description' => $expense->description], 'created_at' => now()]);
            ExpenseCommentCreated::dispatch($comment->load(['author:id,name', 'expense:id,tracker_id']));
            return ['resource_id' => $comment->id];
        }
        throw ValidationException::withMessages(['type' => 'Unsupported offline operation.']);
    }

    private function assertMembers(Tracker $tracker, array $data): void { $ids = $tracker->members()->where('status', 'active')->pluck('user_id')->map(fn ($id) => (int) $id)->all(); if (!in_array((int) $data['paid_by_user_id'], $ids, true) || array_diff($data['participants'], $ids)) throw ValidationException::withMessages(['participants' => 'Only active tracker members can be included.']); }
    private function minor(string $amount): int { [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, ''); return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0'); }
}
