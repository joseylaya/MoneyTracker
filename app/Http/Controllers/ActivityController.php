<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ActivityController extends Controller
{
    public function index(Request $request): Response
    {
        $activities = ActivityLog::query()
            ->where(function ($query) use ($request) {
                $query->whereNull('tracker_id')->orWhereIn('tracker_id', $request->user()->trackerMemberships()->where('status', 'active')->pluck('tracker_id'));
            })
            ->with(['actor:id,name', 'tracker:id,name'])
            ->latest('created_at')->limit(60)->get()
            ->map(fn ($activity) => ['id' => $activity->id, 'action' => $activity->action, 'metadata' => $activity->metadata, 'created_at' => $activity->created_at, 'actor' => $activity->actor ? ['name' => $activity->actor->name] : null, 'tracker' => $activity->tracker ? ['id' => $activity->tracker->id, 'name' => $activity->tracker->name] : null]);
        return Inertia::render('Activity/Index', ['activities' => $activities]);
    }
}
