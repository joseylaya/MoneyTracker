<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrackerSettlementRequest extends Model
{
    use HasUuids;
    protected $guarded = [];
    protected function casts(): array { return ['settlement_date' => 'date', 'responded_at' => 'datetime']; }
    public function fromUser(): BelongsTo { return $this->belongsTo(User::class, 'from_user_id'); }
    public function toUser(): BelongsTo { return $this->belongsTo(User::class, 'to_user_id'); }
    public function approvedSettlement(): BelongsTo { return $this->belongsTo(Settlement::class, 'approved_settlement_id'); }
}
