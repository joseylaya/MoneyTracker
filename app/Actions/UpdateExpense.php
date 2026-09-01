<?php

namespace App\Actions;

use App\Models\ActivityLog;
use App\Models\Expense;
use App\Models\ExpenseSplit;
use App\Models\User;
use App\Services\TrackerFinance;
use App\Services\ExpenseSplitter;
use Illuminate\Support\Facades\DB;

class UpdateExpense
{
    public function __construct(private TrackerFinance $finance, private ExpenseSplitter $splitter) {}

    /** @param array{description:string,amount_minor:int,paid_by_user_id:int,expense_date:string,note:?string,participants:array<int,int>} $data */
    public function handle(Expense $expense, User $actor, array $data): Expense
    {
        $before = ['description' => $expense->description, 'amount_minor' => $expense->amount_minor, 'paid_by_user_id' => $expense->paid_by_user_id];

        DB::transaction(function () use ($expense, $actor, $data, $before) {
            $expense->update([
                'description' => $data['description'], 'amount_minor' => $data['amount_minor'],
                'paid_by_user_id' => $data['paid_by_user_id'], 'expense_date' => $data['expense_date'],
                'note' => $data['note'], 'updated_by' => $actor->id, 'version' => $expense->version + 1,
                'expense_type' => $data['expense_type'] ?? 'split', 'split_method' => $data['split_method'] ?? 'equal',
                'unit_price_minor' => $data['unit_price_minor'] ?? null,
            ]);
            ExpenseSplit::where('expense_id', $expense->id)->delete();
            foreach ($this->splitter->split($data) as $split) {
                ExpenseSplit::create(['expense_id' => $expense->id, ...$split]);
            }
            ActivityLog::create([
                'tracker_id' => $expense->tracker_id, 'actor_user_id' => $actor->id, 'action' => 'expense.updated',
                'subject_type' => 'expense', 'subject_id' => $expense->id,
                'metadata' => ['description' => $expense->description, 'amount_minor' => $expense->amount_minor, 'previous' => $before], 'created_at' => now(),
            ]);
        });

        $this->finance->forget($expense->tracker);

        return $expense->fresh(['payer:id,name', 'splits.user:id,name']);
    }
}
