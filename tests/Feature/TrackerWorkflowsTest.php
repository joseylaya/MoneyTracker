<?php

namespace Tests\Feature;

use App\Models\Tracker;
use App\Models\TrackerMember;
use App\Models\Expense;
use App\Models\ExpenseSplit;
use App\Models\Settlement;
use App\Models\User;
use App\Services\TrackerFinance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TrackerWorkflowsTest extends TestCase
{
    use RefreshDatabase;

    private function tracker(User $owner): Tracker
    {
        $tracker = Tracker::create(['name' => 'Weekend trip', 'currency_code' => 'PHP', 'currency_exponent' => 2, 'owner_user_id' => $owner->id, 'created_by' => $owner->id]);
        TrackerMember::create(['tracker_id' => $tracker->id, 'user_id' => $owner->id, 'role' => 'owner', 'status' => 'active', 'joined_at' => now(), 'created_by' => $owner->id]);
        return $tracker;
    }

    public function test_viewer_cannot_create_an_expense(): void
    {
        $owner = User::factory()->create(); $viewer = User::factory()->create(); $tracker = $this->tracker($owner);
        TrackerMember::create(['tracker_id' => $tracker->id, 'user_id' => $viewer->id, 'role' => 'viewer', 'status' => 'active', 'joined_at' => now(), 'created_by' => $owner->id]);
        $this->actingAs($viewer)->post(route('trackers.expenses.store', $tracker), ['description' => 'Dinner', 'amount' => '100.00', 'paid_by_user_id' => $viewer->id, 'expense_date' => today()->toDateString(), 'participants' => [$owner->id, $viewer->id]])->assertForbidden();
    }

    public function test_owner_can_open_the_add_expense_screen(): void
    {
        $owner = User::factory()->create(); $tracker = $this->tracker($owner);
        $this->actingAs($owner)->get(route('trackers.expenses.create', $tracker))->assertOk();
    }

    public function test_owner_can_archive_restore_and_soft_delete_a_tracker(): void
    {
        $owner = User::factory()->create(); $tracker = $this->tracker($owner);

        $this->actingAs($owner)->patch(route('trackers.archive', $tracker))->assertRedirect(route('trackers.index'));
        $this->assertDatabaseHas('trackers', ['id' => $tracker->id, 'status' => 'archived']);
        $this->actingAs($owner)->get(route('trackers.index'))->assertOk()->assertInertia(fn ($page) => $page->where('trackers', [])->has('archivedTrackers', 1));
        $this->actingAs($owner)->get(route('trackers.expenses.create', $tracker))->assertForbidden();

        $this->actingAs($owner)->patch(route('trackers.restore', $tracker))->assertRedirect();
        $this->assertDatabaseHas('trackers', ['id' => $tracker->id, 'status' => 'active']);
        $this->actingAs($owner)->delete(route('trackers.destroy', $tracker))->assertRedirect(route('trackers.index'));
        $this->assertSoftDeleted('trackers', ['id' => $tracker->id, 'deleted_by' => $owner->id]);
    }

    public function test_non_owner_cannot_archive_or_delete_a_tracker(): void
    {
        $owner = User::factory()->create(); $editor = User::factory()->create(); $tracker = $this->tracker($owner);
        TrackerMember::create(['tracker_id' => $tracker->id, 'user_id' => $editor->id, 'role' => 'editor', 'status' => 'active', 'joined_at' => now(), 'created_by' => $owner->id]);

        $this->actingAs($editor)->patch(route('trackers.archive', $tracker))->assertForbidden();
        $this->actingAs($editor)->delete(route('trackers.destroy', $tracker))->assertForbidden();
    }

    public function test_tracker_detail_renders_after_an_expense_is_created(): void
    {
        $owner = User::factory()->create(); $tracker = $this->tracker($owner);
        $this->actingAs($owner)->post(route('trackers.expenses.store', $tracker), ['description' => 'Fuel', 'amount' => '300.00', 'paid_by_user_id' => $owner->id, 'expense_date' => today()->toDateString(), 'participants' => [$owner->id]])->assertRedirect();
        $this->actingAs($owner)->get(route('trackers.show', $tracker))->assertOk();
    }

    public function test_editor_can_correct_an_expense_and_an_overpayment_becomes_money_owed_back(): void
    {
        $owner = User::factory()->create(); $editor = User::factory()->create(); $tracker = $this->tracker($owner);
        TrackerMember::create(['tracker_id' => $tracker->id, 'user_id' => $editor->id, 'role' => 'editor', 'status' => 'active', 'joined_at' => now(), 'created_by' => $owner->id]);
        $expense = Expense::create(['tracker_id' => $tracker->id, 'description' => 'Dinner', 'amount_minor' => 200000, 'paid_by_user_id' => $owner->id, 'expense_date' => today(), 'created_by' => $owner->id]);
        foreach ([$owner, $editor] as $member) ExpenseSplit::create(['expense_id' => $expense->id, 'user_id' => $member->id, 'amount_minor' => 100000]);
        Settlement::create(['tracker_id' => $tracker->id, 'from_user_id' => $editor->id, 'to_user_id' => $owner->id, 'amount_minor' => 100000, 'settlement_date' => today(), 'created_by' => $editor->id]);

        $finance = app(TrackerFinance::class);
        $this->assertSame([], $finance->directDebts($tracker));

        $this->actingAs($editor)->patch(route('trackers.expenses.update', [$tracker, $expense]), [
            'description' => 'Dinner (corrected)', 'amount' => '1000.00', 'paid_by_user_id' => $owner->id,
            'expense_date' => today()->toDateString(), 'participants' => [$owner->id, $editor->id],
        ])->assertRedirect(route('trackers.expenses.show', [$tracker, $expense]));

        $this->assertDatabaseHas('expenses', ['id' => $expense->id, 'description' => 'Dinner (corrected)', 'amount_minor' => 100000, 'updated_by' => $editor->id]);
        $this->assertDatabaseCount('expense_splits', 2);
        $this->assertSame([['from_user_id' => $owner->id, 'to_user_id' => $editor->id, 'amount_minor' => 50000]], $finance->directDebts($tracker));
        $this->assertDatabaseHas('activity_logs', ['tracker_id' => $tracker->id, 'action' => 'expense.updated', 'subject_id' => $expense->id]);
    }

    public function test_owner_can_create_pending_invitation_for_unregistered_email(): void
    {
        $owner = User::factory()->create(); $tracker = $this->tracker($owner);
        $this->actingAs($owner)->post(route('trackers.members.store', $tracker), ['email' => 'future@example.com', 'role' => 'viewer'])->assertRedirect();
        $this->assertDatabaseHas('tracker_invitations', ['tracker_id' => $tracker->id, 'email' => 'future@example.com', 'role' => 'viewer', 'status' => 'pending']);
    }

    public function test_member_suggestions_match_registered_users_and_exclude_existing_members(): void
    {
        $owner = User::factory()->create();
        $candidate = User::factory()->create(['name' => 'Maria Cruz', 'email' => 'maria@example.com']);
        $existing = User::factory()->create(['name' => 'Maria Existing', 'email' => 'existing@example.com']);
        $tracker = $this->tracker($owner);
        TrackerMember::create(['tracker_id' => $tracker->id, 'user_id' => $existing->id, 'role' => 'viewer', 'status' => 'active', 'joined_at' => now(), 'created_by' => $owner->id]);

        $this->actingAs($owner)->getJson(route('trackers.members.suggestions', ['tracker' => $tracker, 'query' => 'maria']))
            ->assertOk()->assertJsonPath('suggestions.0.email', $candidate->email)->assertJsonCount(1, 'suggestions');
    }

    public function test_settlement_cannot_exceed_current_direct_debt(): void
    {
        $owner = User::factory()->create(); $member = User::factory()->create(); $tracker = $this->tracker($owner);
        TrackerMember::create(['tracker_id' => $tracker->id, 'user_id' => $member->id, 'role' => 'editor', 'status' => 'active', 'joined_at' => now(), 'created_by' => $owner->id]);
        $this->actingAs($owner)->post(route('trackers.expenses.store', $tracker), ['description' => 'Dinner', 'amount' => '100.00', 'paid_by_user_id' => $owner->id, 'expense_date' => today()->toDateString(), 'participants' => [$owner->id, $member->id]])->assertRedirect();
        $this->actingAs($member)->post(route('trackers.settlements.store', $tracker), ['from_user_id' => $member->id, 'to_user_id' => $owner->id, 'amount' => '50.01', 'settlement_date' => today()->toDateString()])->assertSessionHasErrors('amount');
    }

    public function test_commenter_can_add_an_expense_comment_but_viewer_cannot(): void
    {
        $owner = User::factory()->create(); $commenter = User::factory()->create(); $viewer = User::factory()->create(); $tracker = $this->tracker($owner);
        foreach ([[$commenter, 'commenter'], [$viewer, 'viewer']] as [$user, $role]) {
            TrackerMember::create(['tracker_id' => $tracker->id, 'user_id' => $user->id, 'role' => $role, 'status' => 'active', 'joined_at' => now(), 'created_by' => $owner->id]);
        }
        $this->actingAs($owner)->post(route('trackers.expenses.store', $tracker), ['description' => 'Dinner', 'amount' => '120.00', 'paid_by_user_id' => $owner->id, 'expense_date' => today()->toDateString(), 'participants' => [$owner->id, $commenter->id, $viewer->id]]);
        $expense = $tracker->expenses()->firstOrFail();
        $this->actingAs($commenter)->post(route('trackers.expenses.comments.store', [$tracker, $expense]), ['body' => 'This includes parking.'])->assertRedirect();
        $this->assertDatabaseHas('expense_comments', ['expense_id' => $expense->id, 'user_id' => $commenter->id, 'body' => 'This includes parking.']);
        $this->actingAs($viewer)->post(route('trackers.expenses.comments.store', [$tracker, $expense]), ['body' => 'Not allowed'])->assertForbidden();
    }

    public function test_tracker_conversation_persists_emoji_messages_and_respects_roles(): void
    {
        $owner = User::factory()->create(); $commenter = User::factory()->create(); $viewer = User::factory()->create(); $tracker = $this->tracker($owner);
        foreach ([[$commenter, 'commenter'], [$viewer, 'viewer']] as [$user, $role]) {
            TrackerMember::create(['tracker_id' => $tracker->id, 'user_id' => $user->id, 'role' => $role, 'status' => 'active', 'joined_at' => now(), 'created_by' => $owner->id]);
        }
        $this->actingAs($commenter)->get(route('trackers.conversation.index', $tracker))->assertOk();
        $this->actingAs($commenter)->post(route('trackers.conversation.store', $tracker), ['body' => 'Dinner is at 7:00 PM 🎉'])->assertRedirect();
        $this->assertDatabaseHas('tracker_messages', ['tracker_id' => $tracker->id, 'user_id' => $commenter->id, 'body' => 'Dinner is at 7:00 PM 🎉']);
        $message = $tracker->messages()->firstOrFail();
        $this->actingAs($commenter)->post(route('trackers.conversation.reactions.store', [$tracker, $message]), ['emoji' => '❤️'])->assertRedirect();
        $this->assertDatabaseHas('tracker_message_reactions', ['tracker_message_id' => $message->id, 'user_id' => $commenter->id, 'emoji' => '❤️']);
        $this->actingAs($commenter)->post(route('trackers.conversation.reactions.store', [$tracker, $message]), ['emoji' => '❤️'])->assertRedirect();
        $this->assertDatabaseMissing('tracker_message_reactions', ['tracker_message_id' => $message->id, 'user_id' => $commenter->id]);
        $this->actingAs($viewer)->post(route('trackers.conversation.store', $tracker), ['body' => 'I cannot send this'])->assertForbidden();
        $this->actingAs($viewer)->post(route('trackers.conversation.reactions.store', [$tracker, $message]), ['emoji' => '👍'])->assertForbidden();
    }

    public function test_chat_send_returns_the_saved_message_for_the_optimistic_client_bubble(): void
    {
        $owner = User::factory()->create();
        $tracker = $this->tracker($owner);
        $clientMessageId = '0751e359-6b71-4a87-9b3b-0ed5c5d7d461';

        $response = $this->actingAs($owner)
            ->withHeaders(['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'])
            ->post(route('trackers.conversation.store', $tracker), ['body' => 'Saved without a duplicate.', 'client_message_id' => $clientMessageId]);

        $response->assertCreated()
            ->assertJsonPath('client_message_id', $clientMessageId)
            ->assertJsonPath('message.body', 'Saved without a duplicate.')
            ->assertJsonPath('message.author.id', $owner->id);
    }

    public function test_opening_a_conversation_marks_messages_as_read_for_that_member(): void
    {
        $owner = User::factory()->create(); $member = User::factory()->create(); $tracker = $this->tracker($owner);
        $membership = TrackerMember::create(['tracker_id' => $tracker->id, 'user_id' => $member->id, 'role' => 'commenter', 'status' => 'active', 'joined_at' => now(), 'created_by' => $owner->id]);
        $this->actingAs($owner)->post(route('trackers.conversation.store', $tracker), ['body' => 'Welcome to the trip chat 👋'])->assertRedirect();
        $this->assertNull($membership->fresh()->last_read_chat_at);
        $this->actingAs($member)->get(route('trackers.conversation.index', $tracker))->assertOk();
        $this->assertNotNull($membership->fresh()->last_read_chat_at);
    }

    public function test_conversation_includes_the_current_user_name_for_realtime_typing_presence(): void
    {
        $owner = User::factory()->create(['name' => 'Jose']);
        $tracker = $this->tracker($owner);

        $this->actingAs($owner)->get(route('trackers.conversation.index', $tracker))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Trackers/Conversation')
            ->where('currentUserId', $owner->id)
            ->where('currentUserName', 'Jose'));
    }

    public function test_chat_settlement_only_changes_balances_after_the_recipient_approves(): void
    {
        $owner = User::factory()->create(); $member = User::factory()->create(); $tracker = $this->tracker($owner);
        TrackerMember::create(['tracker_id' => $tracker->id, 'user_id' => $member->id, 'role' => 'editor', 'status' => 'active', 'joined_at' => now(), 'created_by' => $owner->id]);
        $this->actingAs($owner)->post(route('trackers.expenses.store', $tracker), ['description' => 'Dinner', 'amount' => '100.00', 'paid_by_user_id' => $owner->id, 'expense_date' => today()->toDateString(), 'participants' => [$owner->id, $member->id]]);
        $this->actingAs($member)->post(route('trackers.conversation.settlements.store', $tracker), ['to_user_id' => $owner->id, 'amount' => '50.00', 'settlement_date' => today()->toDateString()])->assertRedirect();
        $request = $tracker->settlementRequests()->firstOrFail();
        $this->assertSame('pending', $request->status); $this->assertDatabaseCount('settlements', 0);
        $this->actingAs($owner)->post(route('trackers.conversation.settlements.response', [$tracker, $request]), ['decision' => 'approved'])->assertRedirect();
        $this->assertSame('approved', $request->fresh()->status); $this->assertDatabaseCount('settlements', 1);
    }

    public function test_settle_up_page_notifies_recipient_and_waits_for_approval(): void
    {
        $owner = User::factory()->create(); $member = User::factory()->create(); $tracker = $this->tracker($owner);
        TrackerMember::create(['tracker_id' => $tracker->id, 'user_id' => $member->id, 'role' => 'editor', 'status' => 'active', 'joined_at' => now(), 'created_by' => $owner->id]);
        $this->actingAs($owner)->post(route('trackers.expenses.store', $tracker), ['description' => 'Hotel', 'amount' => '200.00', 'paid_by_user_id' => $owner->id, 'expense_date' => today()->toDateString(), 'participants' => [$owner->id, $member->id]]);

        $this->actingAs($member)->post(route('trackers.settlements.store', $tracker), [
            'from_user_id' => $member->id, 'to_user_id' => $owner->id, 'amount' => '100.00',
            'settlement_date' => today()->toDateString(), 'note' => 'Bank transfer',
        ])->assertRedirect(route('trackers.show', $tracker))->assertSessionHas('success');

        $settlementRequest = $tracker->settlementRequests()->firstOrFail();
        $this->assertSame('pending', $settlementRequest->status);
        $this->assertDatabaseCount('settlements', 0);
        $this->assertDatabaseHas('tracker_messages', ['tracker_id' => $tracker->id, 'settlement_request_id' => $settlementRequest->id, 'type' => 'settlement_request']);
        $this->assertDatabaseHas('tracker_notifications', ['tracker_id' => $tracker->id, 'user_id' => $owner->id, 'type' => 'settlement.requested']);

        $this->actingAs($owner)->post(route('trackers.conversation.settlements.response', [$tracker, $settlementRequest]), ['decision' => 'approved'])->assertRedirect();
        $this->assertSame('approved', $settlementRequest->fresh()->status);
        $this->assertDatabaseHas('settlements', ['tracker_id' => $tracker->id, 'from_user_id' => $member->id, 'to_user_id' => $owner->id, 'amount_minor' => 10000]);
    }

    public function test_personal_activity_includes_historical_direct_settlements(): void
    {
        $owner = User::factory()->create(); $member = User::factory()->create(); $tracker = $this->tracker($owner);
        TrackerMember::create(['tracker_id' => $tracker->id, 'user_id' => $member->id, 'role' => 'editor', 'status' => 'active', 'joined_at' => now(), 'created_by' => $owner->id]);
        Settlement::create(['tracker_id' => $tracker->id, 'from_user_id' => $member->id, 'to_user_id' => $owner->id, 'amount_minor' => 2500, 'settlement_date' => today(), 'created_by' => $member->id]);

        $this->actingAs($member)->get(route('trackers.show', $tracker))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Trackers/Show')
            ->has('personalTransactions', 1)
            ->where('personalTransactions.0.type', 'settlement')
            ->where('personalTransactions.0.direction', 'out')
            ->where('personalTransactions.0.status', 'completed')
            ->where('personalTransactions.0.amount_minor', 2500));
    }
}
