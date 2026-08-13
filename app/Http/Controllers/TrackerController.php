<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Expense;
use App\Actions\CreateExpense;
use App\Models\ExpenseSplit;
use App\Models\Settlement;
use App\Models\Tracker;
use App\Models\TrackerMember;
use App\Models\User;
use App\Models\TrackerInvitation;
use App\Services\TrackerFinance;
use App\Services\FirebasePush;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TrackerController extends Controller
{
    public function index(Request $request, TrackerFinance $finance): Response
    {
        $trackers = Tracker::whereHas('members', fn ($q) => $q->where('user_id', $request->user()->id)->where('status', 'active'))
            ->withCount(['members' => fn ($q) => $q->where('status', 'active')])->latest()->get()
            ->map(function (Tracker $tracker) use ($finance, $request) {
                $tracker->current_user_balance_minor = $finance->memberBalances($tracker)[$request->user()->id] ?? 0;
                return $tracker;
            });
        return Inertia::render('Trackers/Index', ['trackers' => $trackers]);
    }

    public function create(): Response { return Inertia::render('Trackers/Create'); }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:150'], 'description' => ['nullable', 'string', 'max:2000'], 'currency_code' => ['required', 'in:PHP,USD,EUR']]);
        $user = $request->user();
        $tracker = DB::transaction(function () use ($data, $user) {
            $tracker = Tracker::create([...$data, 'currency_exponent' => 2, 'owner_user_id' => $user->id, 'created_by' => $user->id]);
            TrackerMember::create(['tracker_id' => $tracker->id, 'user_id' => $user->id, 'role' => 'owner', 'status' => 'active', 'joined_at' => now(), 'created_by' => $user->id]);
            $this->activity($tracker, $user->id, 'tracker.created', 'tracker', $tracker->id, ['name' => $tracker->name]);
            return $tracker;
        });
        return to_route('trackers.show', $tracker)->with('success', 'Tracker created.');
    }

    public function show(Request $request, Tracker $tracker, TrackerFinance $finance): Response
    {
        $this->authorize('view', $tracker);
        $membership = $this->membership($tracker, $request->user()->id);
        $members = $tracker->members()->where('status', 'active')->with('user:id,name,email')->get()->map(fn ($member) => ['id' => $member->user_id, 'name' => $member->user->name, 'email' => $member->user->email, 'role' => $member->role]);
        $balances = $finance->memberBalances($tracker);
        $memberData = $members->map(fn ($member) => [...$member, 'balance_minor' => $balances[$member['id']] ?? 0]);
        $expenses = $tracker->expenses()->with(['payer:id,name', 'splits.user:id,name'])->withCount('comments')->orderByDesc('expense_date')->orderByDesc('created_at')->get();
        $unreadMessagesCount = $tracker->messages()
            ->where('user_id', '!=', $request->user()->id)
            ->when($membership->last_read_chat_at, fn ($query, $lastReadAt) => $query->where('created_at', '>', $lastReadAt))
            ->count();
        return Inertia::render('Trackers/Show', [
            'tracker' => $tracker,
            'membership' => ['role' => $membership->role, 'can_manage_members' => $request->user()->can('manageMembers', $tracker), 'can_manage_finances' => $request->user()->can('update', $tracker)],
            'members' => $memberData,
            'expenses' => $expenses,
            'debts' => $finance->directDebts($tracker),
            'currentUserId' => $request->user()->id,
            'unreadMessagesCount' => $unreadMessagesCount,
        ]);
    }

    public function members(Request $request, Tracker $tracker): Response
    {
        $this->authorize('view', $tracker);
        return Inertia::render('Trackers/Members', [
            'tracker' => $tracker,
            'members' => $tracker->members()->where('status', 'active')->with('user:id,name,email')->get()->map(fn ($member) => ['id' => $member->user_id, 'membership_id' => $member->id, 'name' => $member->user->name, 'email' => $member->user->email, 'role' => $member->role]),
            'invitations' => $tracker->invitations()->where('status', 'pending')->latest()->get(['id', 'email', 'role', 'created_at']),
            'canManage' => $request->user()->can('manageMembers', $tracker),
        ]);
    }

    public function expenseCreate(Request $request, Tracker $tracker): Response
    {
        $this->authorize('update', $tracker);
        return Inertia::render('Expenses/Create', ['tracker' => $tracker, 'members' => $tracker->members()->where('status', 'active')->with('user:id,name,email')->get()->map(fn ($member) => ['id' => $member->user_id, 'name' => $member->user->name, 'role' => $member->role])]);
    }

    public function expenseShow(Request $request, Tracker $tracker, Expense $expense): Response
    {
        $this->authorize('view', $tracker);
        abort_unless($expense->tracker_id === $tracker->id, 404);
        $expense->load(['payer:id,name,email', 'splits.user:id,name,email']);
        $comments = $expense->comments()->with('author:id,name')->latest('created_at')->limit(11)->get();
        $hasMoreComments = $comments->count() > 10;
        $expense->setRelation('comments', $comments->take(10)->sortBy('created_at')->values());
        return Inertia::render('Expenses/Show', ['tracker' => $tracker, 'expense' => $expense, 'hasMoreComments' => $hasMoreComments, 'canManage' => $request->user()->can('update', $tracker), 'canComment' => $request->user()->can('comment', $tracker), 'currentUserId' => $request->user()->id]);
    }

    public function addMember(Request $request, Tracker $tracker): RedirectResponse
    {
        $this->authorize('manageMembers', $tracker);
        $data = $request->validate(['email' => ['required', 'email'], 'role' => ['required', 'in:editor,commenter,viewer']]);
        $user = User::whereRaw('lower(email) = ?', [strtolower($data['email'])])->first();
        if (! $user) {
            TrackerInvitation::updateOrCreate(['tracker_id' => $tracker->id, 'email' => strtolower($data['email']), 'status' => 'pending'], ['role' => $data['role'], 'invited_by' => $request->user()->id, 'expires_at' => now()->addDays(14)]);
            $this->activity($tracker, $request->user()->id, 'invitation.sent', 'invitation', '', ['email' => strtolower($data['email']), 'role' => $data['role']]);
            return back()->with('success', 'Invitation is pending until this email registers.');
        }
        if ($this->membership($tracker, $user->id)) return back()->withErrors(['email' => 'This user is already a tracker member.']);
        TrackerMember::create(['tracker_id' => $tracker->id, 'user_id' => $user->id, 'role' => $data['role'], 'status' => 'active', 'joined_at' => now(), 'created_by' => $request->user()->id]);
        app(FirebasePush::class)->send($user, 'You joined '.$tracker->name, $request->user()->name.' added you as a '.$data['role'].'.', ['tracker_id' => $tracker->id]);
        $this->activity($tracker, $request->user()->id, 'member.joined', 'member', (string) $user->id, ['name' => $user->name, 'role' => $data['role']]);
        return back()->with('success', "$user->name was added to the tracker.");
    }

    public function changeMemberRole(Request $request, Tracker $tracker, TrackerMember $member): RedirectResponse
    {
        $this->authorize('manageMembers', $tracker);
        abort_unless($member->tracker_id === $tracker->id && $member->role !== 'owner', 422);
        $data = $request->validate(['role' => ['required', 'in:editor,commenter,viewer']]);
        $oldRole = $member->role; $member->update(['role' => $data['role'], 'updated_by' => $request->user()->id]);
        $this->activity($tracker, $request->user()->id, 'member.role_changed', 'member', $member->id, ['old_role' => $oldRole, 'new_role' => $data['role']]);
        return back()->with('success', 'Member role updated.');
    }

    public function storeExpense(Request $request, Tracker $tracker): RedirectResponse
    {
        $this->authorize('update', $tracker);
        $data = $request->validate(['description' => ['required', 'string', 'max:255'], 'amount' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'], 'paid_by_user_id' => ['required', 'integer'], 'expense_date' => ['required', 'date'], 'note' => ['nullable', 'string', 'max:2000'], 'participants' => ['required', 'array', 'min:1'], 'participants.*' => ['integer']]);
        $memberIds = $tracker->members()->where('status', 'active')->pluck('user_id')->all();
        abort_unless(in_array((int) $data['paid_by_user_id'], $memberIds, true) && empty(array_diff($data['participants'], $memberIds)), 422);
        $amount = $this->minor($data['amount']);
        $participants = array_values(array_unique(array_map('intval', $data['participants'])));
        $expense = app(CreateExpense::class)->handle($tracker, $request->user(), [...$data, 'note' => $data['note'] ?? null, 'amount_minor' => $amount, 'participants' => $participants]);
        return to_route('trackers.show', $tracker)->with('success', "{$expense->description} was added.");
    }

    public function settlementCreate(Request $request, Tracker $tracker, TrackerFinance $finance): Response
    {
        $this->authorize('settle', $tracker);
        $members = $tracker->members()->where('status', 'active')->with('user:id,name,email')->get()->map(fn ($member) => ['id' => $member->user_id, 'name' => $member->user->name]);
        return Inertia::render('Settlements/Create', ['tracker' => $tracker, 'members' => $members, 'debts' => $finance->directDebts($tracker), 'currentUserId' => $request->user()->id]);
    }

    public function storeSettlement(Request $request, Tracker $tracker, TrackerFinance $finance): RedirectResponse
    {
        $this->authorize('settle', $tracker);
        $data = $request->validate(['from_user_id' => ['required', 'integer'], 'to_user_id' => ['required', 'integer', 'different:from_user_id'], 'amount' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'], 'settlement_date' => ['required', 'date'], 'note' => ['nullable', 'string', 'max:2000']]);
        $amount = $this->minor($data['amount']);
        $debt = collect($finance->directDebts($tracker))->first(fn ($debt) => $debt['from_user_id'] === (int) $data['from_user_id'] && $debt['to_user_id'] === (int) $data['to_user_id']);
        if (! $debt || $amount > $debt['amount_minor']) return back()->withErrors(['amount' => 'The settlement cannot be greater than the current direct debt.']);
        DB::transaction(function () use ($tracker, $request, $data, $amount) {
            $settlement = Settlement::create(['tracker_id' => $tracker->id, 'from_user_id' => $data['from_user_id'], 'to_user_id' => $data['to_user_id'], 'amount_minor' => $amount, 'settlement_date' => $data['settlement_date'], 'note' => $data['note'], 'created_by' => $request->user()->id]);
            $this->activity($tracker, $request->user()->id, 'settlement.created', 'settlement', $settlement->id, ['amount_minor' => $amount, 'from_user_id' => $settlement->from_user_id, 'to_user_id' => $settlement->to_user_id]);
        });
        return to_route('trackers.show', $tracker)->with('success', 'Settlement recorded and balances updated.');
    }

    private function membership(Tracker $tracker, int $userId): ?TrackerMember { return $tracker->members()->where('user_id', $userId)->where('status', 'active')->first(); }
    private function minor(string $amount): int { [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, ''); return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0'); }
    private function activity(Tracker $tracker, int $actorId, string $action, string $type, string $id, array $metadata): void { ActivityLog::create(['tracker_id' => $tracker->id, 'actor_user_id' => $actorId, 'action' => $action, 'subject_type' => $type, 'subject_id' => $id, 'metadata' => $metadata, 'created_at' => now()]); }
}
