<?php

namespace Tests\Feature;

use App\Models\Tracker;
use App\Models\TrackerMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TrackerPlanningTest extends TestCase
{
    use RefreshDatabase;

    private function tracker(User $owner): Tracker
    {
        $tracker = Tracker::create(['name' => 'Island trip', 'currency_code' => 'PHP', 'currency_exponent' => 2, 'owner_user_id' => $owner->id, 'created_by' => $owner->id]);
        TrackerMember::create(['tracker_id' => $tracker->id, 'user_id' => $owner->id, 'role' => 'owner', 'status' => 'active', 'joined_at' => now(), 'created_by' => $owner->id]);
        return $tracker;
    }

    public function test_editor_can_add_assigned_tasks_and_future_expenses(): void
    {
        $owner = User::factory()->create();
        $editor = User::factory()->create();
        $tracker = $this->tracker($owner);
        TrackerMember::create(['tracker_id' => $tracker->id, 'user_id' => $editor->id, 'role' => 'editor', 'status' => 'active', 'joined_at' => now(), 'created_by' => $owner->id]);

        $this->actingAs($editor)->post(route('trackers.tasks.store', $tracker), [
            'title' => 'Bring the kaldero', 'assigned_to_user_id' => $owner->id,
        ])->assertRedirect();
        $this->actingAs($editor)->post(route('trackers.planned-expenses.store', $tracker), [
            'description' => 'Boat rental', 'amount' => '2500.50', 'assigned_to_user_id' => $editor->id, 'expected_date' => '2026-10-11',
        ])->assertRedirect();

        $this->assertDatabaseHas('tracker_tasks', ['tracker_id' => $tracker->id, 'title' => 'Bring the kaldero', 'assigned_to_user_id' => $owner->id]);
        $this->assertDatabaseHas('tracker_planned_expenses', ['tracker_id' => $tracker->id, 'description' => 'Boat rental', 'estimated_amount_minor' => 250050]);
        $this->actingAs($owner)->get(route('trackers.show', $tracker))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('tasks', 1)->where('tasks.0.assignee.name', $owner->name)
            ->has('plannedExpenses', 1)->where('plannedExpenses.0.estimated_amount_minor', 250050));
        $this->actingAs($owner)->get(route('trackers.planning.index', $tracker))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Trackers/Planning')->has('tasks', 1)->has('plannedExpenses', 1)->where('canManage', true));
    }

    public function test_any_active_member_can_toggle_a_task_but_viewer_cannot_create_one(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $tracker = $this->tracker($owner);
        TrackerMember::create(['tracker_id' => $tracker->id, 'user_id' => $viewer->id, 'role' => 'viewer', 'status' => 'active', 'joined_at' => now(), 'created_by' => $owner->id]);
        $task = $tracker->tasks()->create(['title' => 'Pack water', 'assigned_to_user_id' => $viewer->id, 'created_by' => $owner->id]);

        $this->actingAs($viewer)->patch(route('trackers.tasks.toggle', [$tracker, $task]))->assertRedirect();
        $this->assertNotNull($task->fresh()->completed_at);
        $this->assertSame($viewer->id, $task->fresh()->completed_by_user_id);
        $this->actingAs($viewer)->patch(route('trackers.tasks.toggle', [$tracker, $task]))->assertRedirect();
        $this->assertNull($task->fresh()->completed_at);
        $this->actingAs($viewer)->post(route('trackers.tasks.store', $tracker), ['title' => 'Not allowed'])->assertForbidden();
    }

    public function test_assignee_must_be_an_active_tracker_member(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $tracker = $this->tracker($owner);

        $this->actingAs($owner)->post(route('trackers.tasks.store', $tracker), [
            'title' => 'Invalid assignment', 'assigned_to_user_id' => $outsider->id,
        ])->assertStatus(422);
        $this->assertDatabaseCount('tracker_tasks', 0);
    }
}
