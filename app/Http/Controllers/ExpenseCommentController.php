<?php

namespace App\Http\Controllers;

use App\Events\ExpenseCommentCreated;
use App\Models\ActivityLog;
use App\Models\Expense;
use App\Models\ExpenseComment;
use App\Models\Tracker;
use App\Services\TrackerNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ExpenseCommentController extends Controller
{
    public function older(Request $request, Tracker $tracker, Expense $expense): JsonResponse
    {
        abort_unless($expense->tracker_id === $tracker->id && $request->user()->can('view', $tracker), 403);
        $data = $request->validate(['before' => ['required', 'date']]);
        $comments = $expense->comments()->with('author:id,name')->where('created_at', '<', $data['before'])->latest('created_at')->limit(11)->get();
        return response()->json(['comments' => $comments->take(10)->sortBy('created_at')->values(), 'has_more' => $comments->count() > 10]);
    }
    public function store(Request $request, Tracker $tracker, Expense $expense, TrackerNotifier $notifier): RedirectResponse
    {
        abort_unless($expense->tracker_id === $tracker->id && $request->user()->can('comment', $tracker), 403);
        $data = $request->validate(['body' => ['required', 'string', 'max:2000']]);
        $comment = ExpenseComment::create(['expense_id' => $expense->id, 'user_id' => $request->user()->id, 'body' => trim($data['body'])])->load(['author:id,name', 'expense:id,tracker_id']);
        ActivityLog::create(['tracker_id' => $tracker->id, 'actor_user_id' => $request->user()->id, 'action' => 'expense.commented', 'subject_type' => 'expense_comment', 'subject_id' => $comment->id, 'metadata' => ['expense_id' => $expense->id, 'description' => $expense->description], 'created_at' => now()]);
        ExpenseCommentCreated::dispatch($comment);
        $notifier->members($tracker, $request->user(), 'expense.comment', 'New comment in '.$tracker->name, $request->user()->name.' commented on '.$expense->description.'.', route('trackers.expenses.show', [$tracker, $expense]), ['expense_id' => $expense->id]);
        return back()->with('success', 'Comment added.');
    }
}
