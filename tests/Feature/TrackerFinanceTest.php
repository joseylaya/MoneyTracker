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

    public function test_settlement_breakdown_preserves_each_obligation_to_the_actual_spender(): void
    {
        $alice = User::factory()->create(['name' => 'Alice']);
        $bob = User::factory()->create(['name' => 'Bob']);
        $cara = User::factory()->create(['name' => 'Cara']);
        $tracker = Tracker::create(['name' => 'Trip', 'currency_code' => 'PHP', 'currency_exponent' => 2, 'owner_user_id' => $alice->id, 'created_by' => $alice->id]);
        foreach ([$alice, $bob, $cara] as $user) TrackerMember::create(['tracker_id' => $tracker->id, 'user_id' => $user->id, 'role' => $user->id === $alice->id ? 'owner' : 'editor', 'status' => 'active', 'joined_at' => now(), 'created_by' => $alice->id]);

        $aliceExpense = Expense::create(['tracker_id' => $tracker->id, 'description' => 'Alice paid for Bob', 'amount_minor' => 10000, 'paid_by_user_id' => $alice->id, 'expense_date' => today(), 'created_by' => $alice->id]);
        ExpenseSplit::create(['expense_id' => $aliceExpense->id, 'user_id' => $bob->id, 'amount_minor' => 10000]);
        $bobExpense = Expense::create(['tracker_id' => $tracker->id, 'description' => 'Bob paid for Cara', 'amount_minor' => 10000, 'paid_by_user_id' => $bob->id, 'expense_date' => today(), 'created_by' => $bob->id]);
        ExpenseSplit::create(['expense_id' => $bobExpense->id, 'user_id' => $cara->id, 'amount_minor' => 10000]);

        $breakdown = collect(app(TrackerFinance::class)->settlementBreakdown($tracker))->keyBy('user_id');
        $this->assertSame(10000, collect($breakdown[$bob->id]['counterparties'])->firstWhere('user_id', $alice->id)['net_minor']);
        $this->assertSame(10000, collect($breakdown[$cara->id]['counterparties'])->firstWhere('user_id', $bob->id)['net_minor']);
        $this->assertSame([
            ['from_user_id' => $bob->id, 'to_user_id' => $alice->id, 'amount_minor' => 10000],
            ['from_user_id' => $cara->id, 'to_user_id' => $bob->id, 'amount_minor' => 10000],
        ], app(TrackerFinance::class)->spenderObligations($tracker));
    }
}
