<?php

namespace Tests\Feature;

use App\Models\ItineraryDay;
use App\Models\ItineraryItem;
use App\Models\Tracker;
use App\Models\TrackerMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ItineraryTest extends TestCase
{
    use RefreshDatabase;

    private function tracker(User $owner): Tracker
    {
        $tracker = Tracker::create(['name' => 'Japan 2027', 'currency_code' => 'PHP', 'currency_exponent' => 2, 'owner_user_id' => $owner->id, 'created_by' => $owner->id]);
        TrackerMember::create(['tracker_id' => $tracker->id, 'user_id' => $owner->id, 'role' => 'owner', 'status' => 'active', 'joined_at' => now(), 'created_by' => $owner->id]);
        return $tracker;
    }

    public function test_member_can_view_an_itinerary_and_owner_can_build_it(): void
    {
        $owner = User::factory()->create();
        $tracker = $this->tracker($owner);

        $this->actingAs($owner)->post(route('trackers.itinerary.days.store', $tracker), [
            'date' => '2027-04-10', 'title' => 'Arrival', 'route_mode' => 'driving',
        ])->assertRedirect();

        $day = $tracker->itineraryDays()->firstOrFail();
        $this->actingAs($owner)->post(route('trackers.itinerary.items.store', [$tracker, $day]), [
            'title' => 'Narita Airport', 'type' => 'transport', 'start_time' => '09:30',
            'location_name' => 'Narita International Airport', 'latitude' => 35.7720, 'longitude' => 140.3929,
        ])->assertRedirect();

        $this->assertDatabaseHas('itinerary_days', ['tracker_id' => $tracker->id, 'date' => '2027-04-10', 'title' => 'Arrival']);
        $this->assertDatabaseHas('itinerary_items', ['itinerary_day_id' => $day->id, 'title' => 'Narita Airport', 'type' => 'transport']);
        $this->actingAs($owner)->get(route('trackers.itinerary.index', $tracker))->assertOk()->assertInertia(fn ($page) => $page
            ->component('Itinerary/Index')->has('days', 1)->where('days.0.date', '2027-04-10')->has('days.0.items', 1)->where('canManage', true));
    }

    public function test_viewer_can_read_but_cannot_change_an_itinerary(): void
    {
        $owner = User::factory()->create(); $viewer = User::factory()->create(); $tracker = $this->tracker($owner);
        TrackerMember::create(['tracker_id' => $tracker->id, 'user_id' => $viewer->id, 'role' => 'viewer', 'status' => 'active', 'joined_at' => now(), 'created_by' => $owner->id]);

        $this->actingAs($viewer)->get(route('trackers.itinerary.index', $tracker))->assertOk();
        $this->actingAs($viewer)->post(route('trackers.itinerary.days.store', $tracker), ['date' => '2027-04-10', 'route_mode' => 'walking'])->assertForbidden();
    }

    public function test_reorder_requires_and_updates_the_complete_day(): void
    {
        $owner = User::factory()->create(); $tracker = $this->tracker($owner);
        $day = ItineraryDay::create(['tracker_id' => $tracker->id, 'date' => '2027-04-10', 'route_mode' => 'walking', 'created_by' => $owner->id]);
        $first = ItineraryItem::create(['itinerary_day_id' => $day->id, 'title' => 'Temple', 'type' => 'place', 'sort_order' => 0, 'created_by' => $owner->id]);
        $second = ItineraryItem::create(['itinerary_day_id' => $day->id, 'title' => 'Lunch', 'type' => 'food', 'sort_order' => 1, 'created_by' => $owner->id]);

        $this->actingAs($owner)->patch(route('trackers.itinerary.reorder', $tracker), ['day_id' => $day->id, 'item_ids' => [$second->id, $first->id]])->assertRedirect();
        $this->assertSame(0, $second->fresh()->sort_order);
        $this->assertSame(1, $first->fresh()->sort_order);
        $this->actingAs($owner)->patch(route('trackers.itinerary.reorder', $tracker), ['day_id' => $day->id, 'item_ids' => [$first->id]])->assertStatus(422);
    }

    public function test_item_from_another_tracker_cannot_be_changed(): void
    {
        $owner = User::factory()->create(); $otherOwner = User::factory()->create();
        $tracker = $this->tracker($owner); $other = $this->tracker($otherOwner);
        $day = ItineraryDay::create(['tracker_id' => $other->id, 'date' => '2027-05-01', 'route_mode' => 'driving', 'created_by' => $otherOwner->id]);
        $item = ItineraryItem::create(['itinerary_day_id' => $day->id, 'title' => 'Hotel', 'type' => 'accommodation', 'created_by' => $otherOwner->id]);

        $this->actingAs($owner)->delete(route('trackers.itinerary.items.destroy', [$tracker, $item]))->assertNotFound();
    }

    public function test_owner_can_complete_and_reopen_an_itinerary_stop(): void
    {
        $owner = User::factory()->create(); $tracker = $this->tracker($owner);
        $day = ItineraryDay::create(['tracker_id' => $tracker->id, 'date' => '2027-04-10', 'route_mode' => 'driving', 'created_by' => $owner->id]);
        $item = ItineraryItem::create(['itinerary_day_id' => $day->id, 'title' => 'Garden', 'type' => 'place', 'created_by' => $owner->id]);

        $this->actingAs($owner)->patchJson(route('trackers.itinerary.items.completion', [$tracker, $item]), ['completed' => true])
            ->assertOk()->assertJsonPath('id', $item->id);
        $this->assertNotNull($item->fresh()->completed_at);
        $this->assertSame($owner->id, $item->fresh()->completed_by);

        $this->actingAs($owner)->patchJson(route('trackers.itinerary.items.completion', [$tracker, $item]), ['completed' => false])->assertOk();
        $this->assertNull($item->fresh()->completed_at);
    }

    public function test_route_is_derived_from_ordered_stops(): void
    {
        Http::fake(['router.project-osrm.org/*' => Http::response(['routes' => [[
            'geometry' => ['type' => 'LineString', 'coordinates' => [[140.39, 35.77], [139.70, 35.69]]],
            'distance' => 81200.5, 'duration' => 4800.2, 'legs' => [['distance' => 81200.5, 'duration' => 4800.2]],
        ]]])]);
        $owner = User::factory()->create(); $tracker = $this->tracker($owner);
        $day = ItineraryDay::create(['tracker_id' => $tracker->id, 'date' => '2027-04-10', 'route_mode' => 'driving', 'created_by' => $owner->id]);
        foreach ([['Airport', 35.77, 140.39], ['Hotel', 35.69, 139.70]] as $index => [$title, $latitude, $longitude]) {
            ItineraryItem::create(['itinerary_day_id' => $day->id, 'title' => $title, 'type' => 'place', 'latitude' => $latitude, 'longitude' => $longitude, 'sort_order' => $index, 'created_by' => $owner->id]);
        }
        $this->actingAs($owner)->getJson(route('trackers.itinerary.route', [$tracker, $day]))
            ->assertOk()->assertJsonPath('geometry.type', 'LineString')->assertJsonPath('distance_meters', 81200.5)
            ->assertJsonPath('legs.0.duration_seconds', 4800.2);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/route/v1/driving/140.39,35.77;139.7,35.69'));
    }

    public function test_expense_can_be_linked_only_to_an_item_in_the_same_tracker(): void
    {
        $owner = User::factory()->create(); $tracker = $this->tracker($owner);
        $day = ItineraryDay::create(['tracker_id' => $tracker->id, 'date' => '2027-04-10', 'route_mode' => 'walking', 'created_by' => $owner->id]);
        $item = ItineraryItem::create(['itinerary_day_id' => $day->id, 'title' => 'Dinner', 'type' => 'food', 'created_by' => $owner->id]);
        $payload = ['description' => 'Ramen', 'amount' => '500.00', 'paid_by_user_id' => $owner->id, 'expense_date' => '2027-04-10', 'participants' => [$owner->id], 'itinerary_item_id' => $item->id];
        $this->actingAs($owner)->post(route('trackers.expenses.store', $tracker), $payload)->assertRedirect();
        $this->assertDatabaseHas('expenses', ['tracker_id' => $tracker->id, 'itinerary_item_id' => $item->id, 'description' => 'Ramen']);

        $other = $this->tracker(User::factory()->create());
        $this->actingAs($owner)->post(route('trackers.expenses.store', $tracker), [...$payload, 'itinerary_item_id' => ItineraryItem::create(['itinerary_day_id' => ItineraryDay::create(['tracker_id' => $other->id, 'date' => '2027-05-10', 'route_mode' => 'driving', 'created_by' => $other->owner_user_id])->id, 'title' => 'Other', 'type' => 'place', 'created_by' => $other->owner_user_id])->id])->assertStatus(422);
    }
}
