<?php

namespace App\Http\Controllers;

use App\Models\ItineraryDay;
use App\Models\Tracker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class ItineraryRouteController extends Controller
{
    public function __invoke(Request $request, Tracker $tracker, ItineraryDay $day): JsonResponse
    {
        $this->authorize('view', $tracker);
        abort_unless($day->tracker_id === $tracker->id, 404);
        $coordinates = $day->items()->whereNotNull('latitude')->whereNotNull('longitude')->get(['longitude', 'latitude'])
            ->map(fn ($item) => $item->longitude.','.$item->latitude)->values();
        if ($request->filled(['origin_latitude', 'origin_longitude'])) {
            $origin = $request->validate([
                'origin_latitude' => ['required', 'numeric', 'between:-90,90'],
                'origin_longitude' => ['required', 'numeric', 'between:-180,180'],
            ]);
            $coordinates->prepend($origin['origin_longitude'].','.$origin['origin_latitude']);
        }
        abort_if($coordinates->count() < 2, 422, 'At least two mapped stops are required.');
        $mode = $day->route_mode === 'walking' ? 'walking' : 'driving';
        $baseUrl = rtrim(config('services.itinerary_routing.'.$mode.'_url'), '/');
        $cacheKey = 'itinerary-route:v2:'.sha1($mode.'|'.$coordinates->join(';'));

        try {
            $route = Cache::remember($cacheKey, now()->addHours(12), function () use ($baseUrl, $coordinates) {
                $response = Http::acceptJson()->timeout(8)->get($baseUrl.'/'.$coordinates->join(';'), [
                    'overview' => 'full', 'geometries' => 'geojson', 'steps' => 'false',
                ])->throw()->json('routes.0');
                abort_unless($response, 503, 'No route was found for these stops.');
                return [
                    'geometry' => $response['geometry'],
                    'distance_meters' => (float) $response['distance'],
                    'duration_seconds' => (float) $response['duration'],
                    'legs' => collect($response['legs'] ?? [])->map(fn ($leg) => [
                        'distance_meters' => (float) $leg['distance'],
                        'duration_seconds' => (float) $leg['duration'],
                    ])->values()->all(),
                ];
            });
        } catch (\Throwable $error) {
            report($error);
            abort(503, 'The routing service is temporarily unavailable.');
        }
        return response()->json($route);
    }
}
