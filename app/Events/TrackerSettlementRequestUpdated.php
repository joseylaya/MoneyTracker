<?php

namespace App\Events;

use App\Models\TrackerSettlementRequest;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TrackerSettlementRequestUpdated implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;
    public function __construct(public TrackerSettlementRequest $settlementRequest) {}
    public function broadcastOn(): array { return [new PrivateChannel('tracker.'.$this->settlementRequest->tracker_id)]; }
    public function broadcastAs(): string { return 'tracker.settlement-request.updated'; }
    public function broadcastWith(): array { return ['settlement_request_id' => $this->settlementRequest->id, 'status' => $this->settlementRequest->status]; }
}
