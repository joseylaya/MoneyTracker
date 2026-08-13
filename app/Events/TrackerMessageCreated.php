<?php

namespace App\Events;

use App\Models\TrackerMessage;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TrackerMessageCreated implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(public TrackerMessage $message) {}

    public function broadcastOn(): array { return [new PrivateChannel('tracker.'.$this->message->tracker_id)]; }
    public function broadcastAs(): string { return 'tracker.message.created'; }
    public function broadcastWith(): array
    {
        return ['message' => [
            'id' => $this->message->id,
            'body' => $this->message->body,
            'created_at' => $this->message->created_at?->toIso8601String(),
            'author' => ['id' => $this->message->author->id, 'name' => $this->message->author->name],
            'reactions' => [],
            'type' => $this->message->type,
            'attachments' => $this->message->attachments->map(fn ($attachment) => ['id' => $attachment->id, 'name' => $attachment->original_name, 'mime_type' => $attachment->mime_type, 'size_bytes' => $attachment->size_bytes, 'url' => route('trackers.conversation.attachments.show', [$this->message->tracker_id, $this->message->id, $attachment->id])])->values(),
            'settlement_request' => $this->message->settlementRequest ? ['id' => $this->message->settlementRequest->id, 'from_name' => $this->message->settlementRequest->fromUser->name, 'to_name' => $this->message->settlementRequest->toUser->name, 'from_user_id' => $this->message->settlementRequest->from_user_id, 'to_user_id' => $this->message->settlementRequest->to_user_id, 'amount_minor' => $this->message->settlementRequest->amount_minor, 'settlement_date' => $this->message->settlementRequest->settlement_date, 'note' => $this->message->settlementRequest->note, 'status' => $this->message->settlementRequest->status] : null,
        ]];
    }
}
