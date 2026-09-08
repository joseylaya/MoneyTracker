<?php

namespace App\Http\Controllers;

use App\Models\Tracker;
use App\Models\TrackerPlannedExpense;
use App\Models\TrackerTask;
use App\Services\TrackerCache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TrackerPlanningController extends Controller
{
    public function index(Request $request, Tracker $tracker, TrackerCache $cache): Response
    {
        $this->authorize('view', $tracker);

        return Inertia::render('Trackers/Planning', [
            'tracker' => $tracker,
            'members' => collect($cache->activeMembers($tracker))->map(fn ($member) => collect($member)->only(['id', 'name', 'role'])->all())->values(),
            'tasks' => $tracker->tasks()->with(['assignee:id,name', 'completedBy:id,name'])->orderByRaw('completed_at is not null')->orderBy('created_at')->get(),
            'plannedExpenses' => $tracker->plannedExpenses()->with('assignee:id,name')->orderBy('expected_date')->orderBy('created_at')->get(),
            'canManage' => $request->user()->can('update', $tracker),
        ]);
    }

    public function storeTask(Request $request, Tracker $tracker): RedirectResponse
    {
        $this->authorize('update', $tracker);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'assigned_to_user_id' => ['nullable', 'integer'],
        ]);
        $this->ensureActiveMember($tracker, $data['assigned_to_user_id'] ?? null);
        $tracker->tasks()->create([...$data, 'created_by' => $request->user()->id]);

        return back()->with('success', 'Task added.');
    }

    public function toggleTask(Request $request, Tracker $tracker, TrackerTask $task): RedirectResponse
    {
        $this->authorize('view', $tracker);
        abort_unless($tracker->status === 'active' && $task->tracker_id === $tracker->id, 404);
        $complete = ! $task->completed_at;
        $task->update([
            'completed_at' => $complete ? now() : null,
            'completed_by_user_id' => $complete ? $request->user()->id : null,
        ]);

        return back();
    }

    public function destroyTask(Request $request, Tracker $tracker, TrackerTask $task): RedirectResponse
    {
        $this->authorize('update', $tracker);
        abort_unless($task->tracker_id === $tracker->id, 404);
        $task->delete();

        return back()->with('success', 'Task removed.');
    }

    public function storePlannedExpense(Request $request, Tracker $tracker): RedirectResponse
    {
        $this->authorize('update', $tracker);
        $data = $request->validate([
            'description' => ['required', 'string', 'max:180'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999.99'],
            'assigned_to_user_id' => ['nullable', 'integer'],
            'expected_date' => ['nullable', 'date'],
        ]);
        $this->ensureActiveMember($tracker, $data['assigned_to_user_id'] ?? null);
        $tracker->plannedExpenses()->create([
            'description' => $data['description'],
            'estimated_amount_minor' => (int) round(((float) $data['amount']) * (10 ** $tracker->currency_exponent)),
            'assigned_to_user_id' => $data['assigned_to_user_id'] ?? null,
            'expected_date' => $data['expected_date'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Estimate added.');
    }

    public function destroyPlannedExpense(Request $request, Tracker $tracker, TrackerPlannedExpense $plannedExpense): RedirectResponse
    {
        $this->authorize('update', $tracker);
        abort_unless($plannedExpense->tracker_id === $tracker->id, 404);
        $plannedExpense->delete();

        return back()->with('success', 'Estimate removed.');
    }

    private function ensureActiveMember(Tracker $tracker, ?int $userId): void
    {
        if ($userId === null) return;
        abort_unless($tracker->members()->where('user_id', $userId)->where('status', 'active')->exists(), 422);
    }
}
