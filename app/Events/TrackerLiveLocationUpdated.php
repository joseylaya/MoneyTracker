<?php

namespace App\Events;

use App\Models\Tracker;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TrackerLiveLocationUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Tracker $tracker,
        public User $user,
        public string $dayId,
        public ?array $location,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('tracker.'.$this->tracker->id)];
    }

    public function broadcastAs(): string
    {
        return 'tracker.live-location.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'day_id' => $this->dayId,
            'user' => ['id' => $this->user->id, 'name' => $this->user->name],
            'location' => $this->location,
        ];
    }
}
