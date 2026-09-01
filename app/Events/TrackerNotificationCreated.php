<?php

namespace App\Events;

use App\Models\TrackerNotification;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TrackerNotificationCreated implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    /** Do not let a temporary realtime outage turn a saved action into an HTTP 500. */
    public string $queue = 'realtime';

    public function __construct(
        public TrackerNotification $notification,
        public int $badgeCount,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('App.Models.User.'.$this->notification->user_id)];
    }

    public function broadcastAs(): string
    {
        return 'tracker.notification.created';
    }

    public function broadcastWith(): array
    {
        return [
            'notification_id' => $this->notification->id,
            'badge_count' => $this->badgeCount,
        ];
    }
}
