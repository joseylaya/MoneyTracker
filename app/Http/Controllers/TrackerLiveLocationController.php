<?php

namespace App\Http\Controllers;

use App\Events\TrackerLiveLocationUpdated;
use App\Models\ItineraryDay;
use App\Models\Tracker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class TrackerLiveLocationController extends Controller
{
    private const ACTIVE_SECONDS = 30;

    private const CACHE_SECONDS = 120;

    public function index(Request $request, Tracker $tracker, ItineraryDay $day): JsonResponse
    {
        $this->authorize('view', $tracker);
        $this->ensureDay($tracker, $day);

        $locations = collect(Cache::get($this->indexKey($tracker, $day), []))
            ->map(fn ($userId) => Cache::get($this->locationKey($tracker, $day, (int) $userId)))
            ->filter(fn ($location) => $location && now()->diffInSeconds($location['updated_at'], true) <= self::ACTIVE_SECONDS)
            ->values();

        return response()->json(['locations' => $locations, 'expires_after_seconds' => self::ACTIVE_SECONDS]);
    }

    public function update(Request $request, Tracker $tracker, ItineraryDay $day): JsonResponse
    {
        $this->authorize('view', $tracker);
        $this->ensureDay($tracker, $day);
        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['required', 'numeric', 'min:0', 'max:10000'],
            'heading' => ['nullable', 'numeric', 'between:0,360'],
            'speed' => ['nullable', 'numeric', 'min:0', 'max:200'],
        ]);
        $location = [
            'user' => ['id' => $request->user()->id, 'name' => $request->user()->name],
            'latitude' => (float) $data['latitude'],
            'longitude' => (float) $data['longitude'],
            'accuracy' => (float) $data['accuracy'],
            'heading' => isset($data['heading']) ? (float) $data['heading'] : null,
            'speed' => isset($data['speed']) ? (float) $data['speed'] : null,
            'updated_at' => now()->toIso8601String(),
        ];
        Cache::put($this->locationKey($tracker, $day, $request->user()->id), $location, self::CACHE_SECONDS);
        $members = collect(Cache::get($this->indexKey($tracker, $day), []))->push($request->user()->id)->unique()->values()->all();
        Cache::put($this->indexKey($tracker, $day), $members, self::CACHE_SECONDS);
        broadcast(new TrackerLiveLocationUpdated($tracker, $request->user(), $day->id, $location))->toOthers();

        return response()->json(['location' => $location]);
    }

    public function destroy(Request $request, Tracker $tracker, ItineraryDay $day): JsonResponse
    {
        $this->authorize('view', $tracker);
        $this->ensureDay($tracker, $day);
        Cache::forget($this->locationKey($tracker, $day, $request->user()->id));
        broadcast(new TrackerLiveLocationUpdated($tracker, $request->user(), $day->id, null))->toOthers();

        return response()->json([], 204);
    }

    private function ensureDay(Tracker $tracker, ItineraryDay $day): void
    {
        abort_unless($day->tracker_id === $tracker->id, 404);
    }

    private function indexKey(Tracker $tracker, ItineraryDay $day): string
    {
        return "tracker:{$tracker->id}:itinerary:{$day->id}:live-location-users";
    }

    private function locationKey(Tracker $tracker, ItineraryDay $day, int $userId): string
    {
        return "tracker:{$tracker->id}:itinerary:{$day->id}:live-location:{$userId}";
    }
}
