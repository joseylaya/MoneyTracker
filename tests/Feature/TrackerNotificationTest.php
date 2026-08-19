<?php

namespace Tests\Feature;

use App\Events\TrackerNotificationCreated;
use App\Jobs\SendPushNotification;
use App\Models\Tracker;
use App\Models\TrackerMember;
use App\Models\TrackerNotification;
use App\Models\User;
use App\Services\TrackerNotifier;
use App\Services\ConversationPresence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TrackerNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_tracker_member_receives_a_persistent_notification_but_actor_does_not(): void
    {
        Queue::fake();
        Event::fake([TrackerNotificationCreated::class]);
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $tracker = Tracker::create(['name' => 'Trip', 'currency_code' => 'PHP', 'currency_exponent' => 2, 'owner_user_id' => $owner->id, 'created_by' => $owner->id]);
        foreach ([$owner, $member] as $user) {
            TrackerMember::create(['tracker_id' => $tracker->id, 'user_id' => $user->id, 'role' => $user->id === $owner->id ? 'owner' : 'editor', 'status' => 'active', 'joined_at' => now(), 'created_by' => $owner->id]);
        }

        app(TrackerNotifier::class)->members($tracker, $owner, 'expense.created', 'New expense', 'Dinner was added.', route('trackers.show', $tracker));

        $this->assertDatabaseHas('tracker_notifications', ['user_id' => $member->id, 'tracker_id' => $tracker->id, 'type' => 'expense.created']);
        $this->assertDatabaseMissing('tracker_notifications', ['user_id' => $owner->id, 'tracker_id' => $tracker->id]);
        Queue::assertPushed(SendPushNotification::class, fn ($job) => $job->userId === $member->id && $job->data['tracker_id'] === $tracker->id);
        Event::assertDispatched(TrackerNotificationCreated::class, fn ($event) => $event->notification->user_id === $member->id && $event->badgeCount === 1);
    }

    public function test_only_the_owner_can_read_or_dismiss_a_notification(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $notification = $owner->trackerNotifications()->create(['type' => 'test', 'title' => 'Test', 'body' => 'Body']);

        $this->actingAs($other)->patch(route('notifications.read', $notification))->assertForbidden();
        $this->actingAs($owner)->patch(route('notifications.read', $notification))->assertRedirect();
        $this->assertNotNull($notification->fresh()->read_at);
        $this->actingAs($owner)->delete(route('notifications.dismiss', $notification))->assertRedirect();
        $this->assertNotNull($notification->fresh()->dismissed_at);
    }

    public function test_an_open_conversation_does_not_receive_a_duplicate_device_push_for_its_messages(): void
    {
        Queue::fake();
        Event::fake([TrackerNotificationCreated::class]);
        $sender = User::factory()->create();
        $viewer = User::factory()->create();
        $tracker = Tracker::create(['name' => 'Trip', 'currency_code' => 'PHP', 'currency_exponent' => 2, 'owner_user_id' => $sender->id, 'created_by' => $sender->id]);

        app(ConversationPresence::class)->mark($viewer->id, $tracker);
        app(TrackerNotifier::class)->user($viewer->id, $tracker, $sender->id, 'conversation.message', 'New message', 'Hi', route('trackers.conversation.index', $tracker));

        $this->assertDatabaseHas('tracker_notifications', ['user_id' => $viewer->id, 'tracker_id' => $tracker->id, 'type' => 'conversation.message']);
        Queue::assertNotPushed(SendPushNotification::class);
    }

    public function test_alerts_group_repeated_updates_by_tracker_and_action(): void
    {
        $user = User::factory()->create();
        $tracker = Tracker::create(['name' => 'Trip', 'currency_code' => 'PHP', 'currency_exponent' => 2, 'owner_user_id' => $user->id, 'created_by' => $user->id]);
        $first = TrackerNotification::create(['user_id' => $user->id, 'tracker_id' => $tracker->id, 'type' => 'message.created', 'title' => 'New message', 'body' => 'First message', 'created_at' => now()->subMinutes(2), 'updated_at' => now()->subMinutes(2)]);
        $other = TrackerNotification::create(['user_id' => $user->id, 'tracker_id' => $tracker->id, 'type' => 'expense.created', 'title' => 'New expense', 'body' => 'Dinner', 'created_at' => now()->subMinute(), 'updated_at' => now()->subMinute()]);
        $latest = TrackerNotification::create(['user_id' => $user->id, 'tracker_id' => $tracker->id, 'type' => 'message.created', 'title' => 'New message', 'body' => 'Second message']);

        $this->actingAs($user)->get(route('notifications.index'))->assertInertia(fn (Assert $page) => $page
            ->component('Notifications/Index')
            ->has('notifications', 2)
            ->where('notifications.0.count', 2)
            ->where('notifications.0.unread_count', 2));

        $this->patch(route('notifications.group.read', $latest))->assertRedirect();
        $this->assertNotNull($first->fresh()->read_at);
        $this->assertNotNull($latest->fresh()->read_at);
        $this->assertNull($other->fresh()->read_at);

        $this->delete(route('notifications.group.dismiss', $latest))->assertRedirect();
        $this->assertNotNull($first->fresh()->dismissed_at);
        $this->assertNotNull($latest->fresh()->dismissed_at);
        $this->assertNull($other->fresh()->dismissed_at);
    }
}
