<?php

namespace App\Services;

use App\Events\TrackerNotificationCreated;
use App\Jobs\SendPushNotification;
use App\Models\Tracker;
use App\Models\TrackerNotification;
use App\Models\User;

class TrackerNotifier
{
    public function __construct(private ConversationPresence $conversationPresence) {}

    /** @param array<string, scalar|null> $data */
    public function members(Tracker $tracker, User $actor, string $type, string $title, string $body, string $url, array $data = []): void
    {
        $tracker->members()->where('status', 'active')->where('user_id', '!=', $actor->id)->pluck('user_id')
            ->each(fn (int $userId) => $this->user($userId, $tracker, $actor->id, $type, $title, $body, $url, $data));
    }

    /** @param array<string, scalar|null> $data */
    public function user(int $userId, ?Tracker $tracker, ?int $actorId, string $type, string $title, string $body, string $url, array $data = []): TrackerNotification
    {
        $notification = TrackerNotification::create([
            'user_id' => $userId, 'tracker_id' => $tracker?->id, 'actor_user_id' => $actorId,
            'type' => $type, 'title' => $title, 'body' => $body, 'url' => $url, 'data' => $data,
        ]);
        $badgeCount = TrackerNotification::where('user_id', $userId)->whereNull('dismissed_at')->whereNull('read_at')->count();
        broadcast(new TrackerNotificationCreated($notification, $badgeCount));
        $isViewingThisConversation = $type === 'conversation.message' && $tracker && $this->conversationPresence->isViewing($userId, $tracker);
        if (! $isViewingThisConversation) {
            SendPushNotification::dispatch($userId, $title, $body, [...$data, 'notification_id' => $notification->id, 'url' => $url, 'tracker_id' => $tracker?->id, 'badge_count' => $badgeCount]);
        }
        return $notification;
    }
}
