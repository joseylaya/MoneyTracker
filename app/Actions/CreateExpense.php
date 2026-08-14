<?php

namespace App\Actions;

use App\Models\ActivityLog;
use App\Models\Expense;
use App\Models\ExpenseSplit;
use App\Models\Tracker;
use App\Models\User;
use App\Services\TrackerFinance;
use Illuminate\Support\Facades\DB;

class CreateExpense
{
    public function __construct(private TrackerFinance $finance) {}

    /** @param array{description:string,amount_minor:int,paid_by_user_id:int,expense_date:string,note:?string,participants:array<int,int>} $data */
    public function handle(Tracker $tracker, User $actor, array $data): Expense
    {
        $expense = DB::transaction(function () use ($tracker, $actor, $data) {
            $expense = Expense::create([
                'tracker_id' => $tracker->id, 'description' => $data['description'], 'amount_minor' => $data['amount_minor'],
                'paid_by_user_id' => $data['paid_by_user_id'], 'expense_date' => $data['expense_date'], 'note' => $data['note'],
                'created_by' => $actor->id,
            ]);
            $share = intdiv($data['amount_minor'], count($data['participants']));
            $remainder = $data['amount_minor'] % count($data['participants']);
            foreach ($data['participants'] as $index => $memberId) {
                ExpenseSplit::create(['expense_id' => $expense->id, 'user_id' => $memberId, 'amount_minor' => $share + ($index < $remainder ? 1 : 0)]);
            }
            ActivityLog::create(['tracker_id' => $tracker->id, 'actor_user_id' => $actor->id, 'action' => 'expense.created', 'subject_type' => 'expense', 'subject_id' => $expense->id, 'metadata' => ['description' => $expense->description, 'amount_minor' => $expense->amount_minor], 'created_at' => now()]);
            return $expense;
        });

        $this->finance->forget($tracker);

        return $expense;
    }
}
