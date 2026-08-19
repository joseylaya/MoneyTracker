<?php

namespace App\Http\Controllers;

use App\Models\TrackerNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TrackerNotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $notifications = TrackerNotification::where('user_id', $request->user()->id)->whereNull('dismissed_at')
            ->with('tracker:id,name')->latest()->limit(100)->get()
            ->groupBy(fn (TrackerNotification $item) => ($item->tracker_id ?? 'global').':'.$item->type)
            ->map(function ($items) {
                $latest = $items->first();
                return [
                    'id' => $latest->id, 'type' => $latest->type, 'title' => $latest->title, 'body' => $latest->body,
                    'url' => $latest->url, 'read_at' => $items->contains(fn ($item) => is_null($item->read_at)) ? null : $latest->read_at,
                    'created_at' => $latest->created_at, 'count' => $items->count(),
                    'unread_count' => $items->filter(fn ($item) => is_null($item->read_at))->count(),
                    'tracker' => $latest->tracker ? ['id' => $latest->tracker->id, 'name' => $latest->tracker->name] : null,
                ];
            })->values();
        return Inertia::render('Notifications/Index', ['notifications' => $notifications]);
    }

    public function read(Request $request, TrackerNotification $notification): RedirectResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 403);
        $notification->update(['read_at' => $notification->read_at ?? now()]);
        return $notification->url ? redirect($notification->url) : back();
    }

    public function readGroup(Request $request, TrackerNotification $notification): RedirectResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 403);
        $this->group($request, $notification)->whereNull('read_at')->update(['read_at' => now()]);
        return $notification->url ? redirect($notification->url) : back();
    }

    public function dismiss(Request $request, TrackerNotification $notification): RedirectResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 403);
        $notification->update(['dismissed_at' => now()]);
        return back();
    }

    public function dismissGroup(Request $request, TrackerNotification $notification): RedirectResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 403);
        $this->group($request, $notification)->update(['dismissed_at' => now()]);
        return back();
    }

    public function readAll(Request $request): RedirectResponse
    {
        TrackerNotification::where('user_id', $request->user()->id)->whereNull('dismissed_at')->whereNull('read_at')->update(['read_at' => now()]);
        return back();
    }

    private function group(Request $request, TrackerNotification $notification)
    {
        $query = TrackerNotification::where('user_id', $request->user()->id)->whereNull('dismissed_at')->where('type', $notification->type);
        return $notification->tracker_id ? $query->where('tracker_id', $notification->tracker_id) : $query->whereNull('tracker_id');
    }
}
