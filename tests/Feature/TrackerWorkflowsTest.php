<?php

namespace Tests\Feature;

use App\Models\Tracker;
use App\Models\TrackerMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_tracker_detail_renders_after_an_expense_is_created(): void
    {
        $owner = User::factory()->create(); $tracker = $this->tracker($owner);
        $this->actingAs($owner)->post(route('trackers.expenses.store', $tracker), ['description' => 'Fuel', 'amount' => '300.00', 'paid_by_user_id' => $owner->id, 'expense_date' => today()->toDateString(), 'participants' => [$owner->id]])->assertRedirect();
        $this->actingAs($owner)->get(route('trackers.show', $tracker))->assertOk();
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
}
