<?php

namespace Tests\Feature;

use App\Models\Tracker;
use App\Models\TrackerMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrackerShareLinkTest extends TestCase
{
    use RefreshDatabase;

    private function tracker(User $owner): Tracker
    {
        $tracker = Tracker::create(['name' => 'Laag', 'currency_code' => 'PHP', 'currency_exponent' => 2, 'owner_user_id' => $owner->id, 'created_by' => $owner->id]);
        TrackerMember::create(['tracker_id' => $tracker->id, 'user_id' => $owner->id, 'role' => 'owner', 'status' => 'active', 'joined_at' => now(), 'created_by' => $owner->id]);
        return $tracker;
    }

    public function test_owner_can_generate_a_stable_share_link(): void
    {
        $owner = User::factory()->create();
        $tracker = $this->tracker($owner);

        $first = $this->actingAs($owner)->postJson(route('trackers.share-link.store', $tracker))->assertOk()->json('url');
        $second = $this->actingAs($owner)->postJson(route('trackers.share-link.store', $tracker))->assertOk()->json('url');

        $this->assertSame($first, $second);
        $this->assertNotNull($tracker->fresh()->share_token);
    }

    public function test_regular_member_cannot_generate_a_share_link(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $tracker = $this->tracker($owner);
        TrackerMember::create(['tracker_id' => $tracker->id, 'user_id' => $member->id, 'role' => 'viewer', 'status' => 'active', 'joined_at' => now(), 'created_by' => $owner->id]);

        $this->actingAs($member)->postJson(route('trackers.share-link.store', $tracker))->assertForbidden();
        $this->assertNull($tracker->fresh()->share_token);
    }

    public function test_guest_is_sent_to_registration_then_joins_after_registering(): void
    {
        $owner = User::factory()->create();
        $tracker = $this->tracker($owner);
        $this->actingAs($owner)->postJson(route('trackers.share-link.store', $tracker));
        $this->post(route('logout'));
        $joinUrl = route('trackers.share.join', $tracker->fresh()->share_token);

        $this->get($joinUrl)->assertStatus(303)->assertRedirect(route('register'));
        $this->post(route('register'), ['username' => 'new_friend', 'email' => 'friend@example.com', 'password' => 'password', 'password_confirmation' => 'password'])->assertRedirect($joinUrl);
        $this->get($joinUrl)->assertStatus(303)->assertRedirect(route('trackers.show', $tracker));

        $friend = User::where('email', 'friend@example.com')->firstOrFail();
        $this->assertDatabaseHas('tracker_members', ['tracker_id' => $tracker->id, 'user_id' => $friend->id, 'role' => 'editor', 'status' => 'active']);
    }

    public function test_logged_in_user_joins_immediately_and_duplicate_opens_are_safe(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $tracker = $this->tracker($owner);
        $this->actingAs($owner)->postJson(route('trackers.share-link.store', $tracker));
        $url = route('trackers.share.join', $tracker->fresh()->share_token);

        $this->actingAs($member)->get($url)->assertRedirect(route('trackers.show', $tracker));
        $this->actingAs($member)->get($url)->assertRedirect(route('trackers.show', $tracker));

        $this->assertSame(1, TrackerMember::where('tracker_id', $tracker->id)->where('user_id', $member->id)->count());
    }

    public function test_mobile_share_text_appended_to_token_is_safely_accepted(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $tracker = $this->tracker($owner);
        $this->actingAs($owner)->postJson(route('trackers.share-link.store', $tracker));
        $token = $tracker->fresh()->share_token;

        $this->actingAs($member)->get('/join/'.$token.'%20Join%20my%20Laag%20tracker%20on%20SplitShare')
            ->assertRedirect(route('trackers.show', $tracker));

        $this->assertDatabaseHas('tracker_members', ['tracker_id' => $tracker->id, 'user_id' => $member->id, 'status' => 'active']);
    }

    public function test_invalid_share_token_returns_not_found_instead_of_database_error(): void
    {
        $this->get('/join/not-a-uuid')->assertNotFound();
    }
}
