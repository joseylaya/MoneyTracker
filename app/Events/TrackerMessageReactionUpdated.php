<?php

namespace App\Events;

use App\Models\TrackerMessage;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TrackerMessageReactionUpdated implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(public TrackerMessage $message) {}

    public function broadcastOn(): array { return [new PrivateChannel('tracker.'.$this->message->tracker_id)]; }
    public function broadcastAs(): string { return 'tracker.message.reactions.updated'; }
    public function broadcastWith(): array
    {
        return ['message_id' => $this->message->id, 'reactions' => $this->summary()];
    }

    private function summary(): array
    {
        return $this->message->reactions()->selectRaw('emoji, count(*) as count')->groupBy('emoji')->get()->map(fn ($reaction) => ['emoji' => $reaction->emoji, 'count' => (int) $reaction->count])->values()->all();
    }
}
