<?php

namespace Tests\Feature;

use App\Models\PersonalAccount;
use App\Models\PersonalBucket;
use App\Models\PersonalCommitment;
use App\Models\PersonalFinanceSetting;
use App\Models\PersonalTransaction;
use App\Models\User;
use App\Services\PersonalFinance;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use App\Events\TrackerNotificationCreated;
use App\Jobs\SendPushNotification;
use Tests\TestCase;

class PersonalFinanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_signed_in_users_open_personal_finance_home_from_the_root_and_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/')->assertRedirect(route('home'));
        $this->get(route('dashboard'))->assertRedirect(route('home'));
    }

    public function test_safe_to_spend_excludes_protected_money_without_treating_an_unfunded_bill_as_spent(): void
    {
        $user = User::factory()->create();
        PersonalAccount::create(['user_id' => $user->id, 'name' => 'Cash', 'type' => 'cash', 'balance_minor' => 300000]);
        PersonalCommitment::create(['user_id' => $user->id, 'name' => 'Rent', 'amount_minor' => 100000, 'due_day' => now()->addDay()->day]);
        PersonalBucket::create(['user_id' => $user->id, 'type' => 'savings', 'reserved_minor' => 50000]);
        PersonalBucket::create(['user_id' => $user->id, 'type' => 'emergency', 'reserved_minor' => 25000]);

        $summary = app(PersonalFinance::class)->summary($user);

        $this->assertSame(225000, $summary['safe']);
        $this->actingAs($user)->get(route('home'))->assertOk();
    }

    public function test_user_can_edit_and_delete_personal_finance_entries(): void
    {
        $user = User::factory()->create();
        $account = PersonalAccount::create(['user_id' => $user->id, 'name' => 'Old bank', 'type' => 'bank', 'balance_minor' => 10000]);
        $bucket = PersonalBucket::create(['user_id' => $user->id, 'type' => 'savings', 'target_minor' => 20000, 'reserved_minor' => 10000]);
        $commitment = PersonalCommitment::create(['user_id' => $user->id, 'name' => 'Old bill', 'amount_minor' => 5000, 'due_day' => 15]);

        $this->actingAs($user)->patch(route('personal.accounts.update', $account), ['name' => 'New bank', 'type' => 'e_wallet'])->assertRedirect();
        $this->assertDatabaseHas('personal_accounts', ['id' => $account->id, 'name' => 'New bank', 'type' => 'e_wallet']);

        $this->patch(route('personal.commitments.update', $commitment), ['name' => 'New bill', 'amount' => '75.50', 'due_day' => 20, 'category' => 'Utilities'])->assertRedirect();
        $this->assertDatabaseHas('personal_commitments', ['id' => $commitment->id, 'name' => 'New bill', 'amount_minor' => 7550, 'due_day' => 20]);

        $this->delete(route('personal.accounts.destroy', $account))->assertRedirect();
        $this->delete(route('personal.commitments.destroy', $commitment))->assertRedirect();
        $this->delete(route('personal.buckets.destroy', $bucket))->assertRedirect();

        $this->assertDatabaseMissing('personal_accounts', ['id' => $account->id]);
        $this->assertDatabaseMissing('personal_commitments', ['id' => $commitment->id]);
        $this->assertDatabaseMissing('personal_buckets', ['id' => $bucket->id]);
    }

    public function test_income_can_be_split_between_accounts(): void
    {
        $user = User::factory()->create();
        $gcash = PersonalAccount::create(['user_id' => $user->id, 'name' => 'GCash', 'type' => 'e_wallet']);
        $cash = PersonalAccount::create(['user_id' => $user->id, 'name' => 'Cash', 'type' => 'cash']);

        $this->actingAs($user)->post(route('personal.income.store'), [
            'income_type' => 'salary',
            'salary_period' => 'first',
            'description' => 'Bike sale',
            'date' => now()->toDateString(),
            'entries' => [
                ['account_id' => $gcash->id, 'amount' => '1000.00'],
                ['account_id' => $cash->id, 'amount' => '1000.00'],
            ],
        ])->assertRedirect();

        $this->assertDatabaseHas('personal_accounts', ['id' => $gcash->id, 'balance_minor' => 100000]);
        $this->assertDatabaseHas('personal_accounts', ['id' => $cash->id, 'balance_minor' => 100000]);
        $this->assertDatabaseCount('personal_transactions', 2);
        $this->assertDatabaseHas('personal_transactions', ['personal_account_id' => $gcash->id, 'category' => 'Salary · 15th']);
    }

    public function test_opening_account_balance_is_allocated_like_income(): void
    {
        $user = User::factory()->create();
        PersonalFinanceSetting::create(['user_id' => $user->id, 'lifestyle_percent' => 20, 'savings_percent' => 10, 'emergency_percent' => 5]);

        $this->actingAs($user)->post(route('personal.accounts.store'), ['name' => 'GCash', 'type' => 'e_wallet', 'opening_balance' => '1000.00'])->assertRedirect();

        $summary = app(PersonalFinance::class)->summary($user);
        $this->assertSame(100000, $summary['income']);
        $this->assertSame(20000, $summary['lifestyleBudget']);
        $this->assertSame(20000, $summary['lifestyleAvailable']);
        $this->assertSame(10000, $summary['savings']);
        $this->assertSame(5000, $summary['emergency']);
        $this->assertDatabaseHas('personal_transactions', ['user_id' => $user->id, 'type' => 'income', 'category' => 'Starting balance', 'amount_minor' => 100000]);
    }

    public function test_summary_creates_a_default_cash_account(): void
    {
        $user = User::factory()->create();

        app(PersonalFinance::class)->summary($user);

        $this->assertDatabaseHas('personal_accounts', ['user_id' => $user->id, 'name' => 'Cash', 'type' => 'cash', 'balance_minor' => 0]);
    }

    public function test_cash_balance_can_be_updated_and_duplicate_account_is_rejected(): void
    {
        $user = User::factory()->create();
        $cash = PersonalAccount::create(['user_id' => $user->id, 'name' => 'Cash', 'type' => 'cash']);
        PersonalAccount::create(['user_id' => $user->id, 'name' => 'Mariabank', 'type' => 'e_wallet']);

        $this->actingAs($user)->patch(route('personal.accounts.update', $cash), ['name' => 'Cash', 'type' => 'cash', 'cash_balance' => '250.00'])->assertRedirect();
        $this->assertDatabaseHas('personal_accounts', ['id' => $cash->id, 'balance_minor' => 25000]);
        $this->assertDatabaseHas('personal_transactions', ['personal_account_id' => $cash->id, 'type' => 'reconciliation', 'amount_minor' => 25000]);

        $this->post(route('personal.accounts.store'), ['name' => 'Mariabank', 'type' => 'e_wallet', 'opening_balance' => '0'])->assertSessionHasErrors('name');
    }

    public function test_bill_payment_deducts_the_selected_account_and_marks_the_bill_paid(): void
    {
        $user = User::factory()->create();
        $account = PersonalAccount::create(['user_id' => $user->id, 'name' => 'GCash', 'type' => 'e_wallet', 'balance_minor' => 100000]);
        $bank = PersonalAccount::create(['user_id' => $user->id, 'name' => 'MariBank', 'type' => 'bank', 'balance_minor' => 200000]);
        $bill = PersonalCommitment::create(['user_id' => $user->id, 'name' => 'Internet', 'amount_minor' => 200000, 'due_day' => now()->day]);

        $this->actingAs($user)->post(route('personal.commitments.pay', $bill), ['date' => now()->toDateString(), 'entries' => [['account_id' => $account->id, 'amount' => '1000.00'], ['account_id' => $bank->id, 'amount' => '1000.00']]])->assertRedirect();

        $this->assertDatabaseHas('personal_accounts', ['id' => $account->id, 'balance_minor' => 0]);
        $this->assertDatabaseHas('personal_accounts', ['id' => $bank->id, 'balance_minor' => 100000]);
        $this->assertDatabaseHas('personal_transactions', ['personal_account_id' => $account->id, 'personal_commitment_id' => $bill->id, 'type' => 'expense', 'amount_minor' => 100000]);
        $this->assertDatabaseHas('personal_transactions', ['personal_account_id' => $bank->id, 'personal_commitment_id' => $bill->id, 'type' => 'expense', 'amount_minor' => 100000]);
        $this->assertSame('Paid', app(PersonalFinance::class)->summary($user)['commitments']->first()->status);
    }

    public function test_paid_recurring_bill_never_counts_as_lifestyle_spending_and_lifestyle_left_cannot_exceed_safe_money(): void
    {
        $user = User::factory()->create();
        PersonalFinanceSetting::create(['user_id' => $user->id, 'lifestyle_percent' => 20]);
        $account = PersonalAccount::create(['user_id' => $user->id, 'name' => 'Cash', 'type' => 'cash', 'balance_minor' => 100000]);
        PersonalTransaction::create(['user_id' => $user->id, 'personal_account_id' => $account->id, 'type' => 'income', 'amount_minor' => 100000, 'category' => 'Salary', 'occurred_on' => now()]);
        $bill = PersonalCommitment::create(['user_id' => $user->id, 'name' => 'Allowance', 'amount_minor' => 100000, 'due_day' => now()->day, 'category' => 'Lifestyle']);

        $this->actingAs($user)->post(route('personal.commitments.pay', $bill), ['date' => now()->toDateString(), 'entries' => [['account_id' => $account->id, 'amount' => '1000.00']]])->assertRedirect();

        $summary = app(PersonalFinance::class)->summary($user);
        $this->assertSame(0, $summary['lifestyle']);
        $this->assertSame(0, $summary['safe']);
        $this->assertSame(0, $summary['lifestyleAvailable']);
    }

    public function test_salary_funds_its_bill_cycle_but_sale_income_remains_available_for_planning(): void
    {
        Carbon::setTestNow('2026-08-19 12:00:00');
        try {
            $user = User::factory()->create();
            PersonalFinanceSetting::create(['user_id' => $user->id, 'lifestyle_percent' => 20, 'savings_percent' => 10, 'emergency_percent' => 5]);
            $account = PersonalAccount::create(['user_id' => $user->id, 'name' => 'GCash', 'type' => 'e_wallet']);
            PersonalCommitment::create(['user_id' => $user->id, 'name' => 'Month-end bill', 'amount_minor' => 900000, 'due_day' => 30]);

            $this->actingAs($user)->post(route('personal.income.store'), ['income_type' => 'salary', 'salary_period' => 'first', 'date' => '2026-08-15', 'entries' => [['account_id' => $account->id, 'amount' => '10000.00']]])->assertRedirect();
            $this->assertDatabaseHas('personal_transactions', ['personal_account_id' => $account->id, 'bill_reserved_minor' => 900000]);
            $this->post(route('personal.income.store'), ['income_type' => 'sale', 'date' => '2026-08-19', 'entries' => [['account_id' => $account->id, 'amount' => '1000.00']]])->assertRedirect();

            $summary = app(PersonalFinance::class)->summary($user);
            $this->assertSame(900000, $summary['reservedBills']);
            $this->assertSame(0, $summary['billShortfall']);
            $this->assertSame(40000, $summary['lifestyleBudget']);
            $this->assertSame(170000, $summary['safe']);
            $this->assertSame(40000, $summary['lifestyleAvailable']);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_quick_expense_deducts_the_selected_account(): void
    {
        $user = User::factory()->create();
        $account = PersonalAccount::create(['user_id' => $user->id, 'name' => 'GCash', 'type' => 'e_wallet', 'balance_minor' => 50000]);

        $this->actingAs($user)->post(route('personal.expenses.store'), ['account_id' => $account->id, 'amount' => '120.50', 'category' => 'Lifestyle', 'description' => 'Coffee', 'date' => now()->toDateString()])->assertRedirect();

        $this->assertDatabaseHas('personal_accounts', ['id' => $account->id, 'balance_minor' => 37950]);
        $this->assertDatabaseHas('personal_transactions', ['personal_account_id' => $account->id, 'type' => 'expense', 'amount_minor' => 12050, 'category' => 'Lifestyle', 'description' => 'Coffee']);
    }

    public function test_large_fast_lifestyle_spending_sends_one_lifestyle_left_push(): void
    {
        Queue::fake();
        Event::fake([TrackerNotificationCreated::class]);
        $user = User::factory()->create();
        $account = PersonalAccount::create(['user_id' => $user->id, 'name' => 'GCash', 'type' => 'e_wallet', 'balance_minor' => 100000]);
        PersonalFinanceSetting::create(['user_id' => $user->id, 'lifestyle_percent' => 100]);
        PersonalTransaction::create(['user_id' => $user->id, 'personal_account_id' => $account->id, 'type' => 'income', 'amount_minor' => 100000, 'category' => 'Salary', 'occurred_on' => now()]);
        PersonalTransaction::create(['user_id' => $user->id, 'personal_account_id' => $account->id, 'type' => 'expense', 'amount_minor' => 60000, 'category' => 'Lifestyle', 'occurred_on' => now()]);

        $this->actingAs($user)->post(route('personal.expenses.store'), ['account_id' => $account->id, 'amount' => '200.00', 'category' => 'Lifestyle', 'date' => now()->toDateString()])->assertRedirect();

        $this->assertDatabaseHas('tracker_notifications', ['user_id' => $user->id, 'type' => 'personal.lifestyle.spending-alert']);
        Queue::assertPushed(SendPushNotification::class, fn ($job) => $job->userId === $user->id && $job->data['personal_notification'] === 'lifestyle');
    }

    public function test_lifestyle_reminders_are_sent_at_the_default_philippine_time_slots_once_each_day(): void
    {
        Queue::fake();
        Event::fake([TrackerNotificationCreated::class]);
        Carbon::setTestNow(Carbon::parse('2026-08-19 11:00:00', 'Asia/Manila'));
        try {
            $user = User::factory()->create();
            PersonalFinanceSetting::create(['user_id' => $user->id, 'lifestyle_percent' => 20]);
            PersonalTransaction::create(['user_id' => $user->id, 'type' => 'income', 'amount_minor' => 100000, 'category' => 'Salary', 'occurred_on' => now()]);

            $this->artisan('personal-finance:send-lifestyle-reminders')->assertSuccessful();
            $this->assertDatabaseHas('tracker_notifications', ['user_id' => $user->id, 'type' => 'personal.lifestyle.reminder.11']);
            Queue::assertPushed(SendPushNotification::class, fn ($job) => $job->userId === $user->id && $job->data['personal_notification'] === 'lifestyle');

            $this->artisan('personal-finance:send-lifestyle-reminders')->assertSuccessful();
            $this->assertDatabaseCount('tracker_notifications', 1);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_upcoming_bills_show_current_half_month_and_overdue_bills_first(): void
    {
        Carbon::setTestNow('2026-08-05 12:00:00');
        try {
            $user = User::factory()->create();
            PersonalCommitment::create(['user_id' => $user->id, 'name' => 'Overdue', 'amount_minor' => 10000, 'due_day' => 3]);
            PersonalCommitment::create(['user_id' => $user->id, 'name' => 'First half', 'amount_minor' => 10000, 'due_day' => 10]);
            PersonalCommitment::create(['user_id' => $user->id, 'name' => 'Second half', 'amount_minor' => 10000, 'due_day' => 20]);

            $summary = app(PersonalFinance::class)->summary($user);
            $this->assertSame(['Overdue', 'First half'], $summary['upcomingCommitments']->pluck('name')->all());

            Carbon::setTestNow('2026-08-16 12:00:00');
            $summary = app(PersonalFinance::class)->summary($user);
            $this->assertSame(['Overdue', 'First half', 'Second half'], $summary['upcomingCommitments']->pluck('name')->all());
        } finally {
            Carbon::setTestNow();
        }
    }
}
