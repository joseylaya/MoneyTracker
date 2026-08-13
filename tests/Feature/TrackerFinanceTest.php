<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\ExpenseSplit;
use App\Models\Tracker;
use App\Models\TrackerMember;
use App\Models\User;
use App\Services\TrackerFinance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrackerFinanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_equal_splits_keep_member_balances_zero_sum_and_preserve_direct_debt(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $tracker = Tracker::create(['name' => 'Trip', 'currency_code' => 'PHP', 'currency_exponent' => 2, 'owner_user_id' => $owner->id, 'created_by' => $owner->id]);
        foreach ([$owner, $member] as $user) TrackerMember::create(['tracker_id' => $tracker->id, 'user_id' => $user->id, 'role' => $user->id === $owner->id ? 'owner' : 'editor', 'status' => 'active', 'joined_at' => now(), 'created_by' => $owner->id]);
        $expense = Expense::create(['tracker_id' => $tracker->id, 'description' => 'Dinner', 'amount_minor' => 1001, 'paid_by_user_id' => $owner->id, 'expense_date' => today(), 'created_by' => $owner->id]);
        ExpenseSplit::create(['expense_id' => $expense->id, 'user_id' => $owner->id, 'amount_minor' => 501]);
        ExpenseSplit::create(['expense_id' => $expense->id, 'user_id' => $member->id, 'amount_minor' => 500]);

        $finance = app(TrackerFinance::class);
        $balances = $finance->memberBalances($tracker);

        $this->assertSame(0, array_sum($balances));
        $this->assertSame(500, $balances[$owner->id]);
        $this->assertSame(-500, $balances[$member->id]);
        $this->assertSame([['from_user_id' => $member->id, 'to_user_id' => $owner->id, 'amount_minor' => 500]], $finance->directDebts($tracker));
    }
}
