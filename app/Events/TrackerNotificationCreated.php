<?php

namespace App\Events;

use App\Models\TrackerNotification;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TrackerNotificationCreated implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

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
