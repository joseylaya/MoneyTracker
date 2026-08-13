<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TrackerMessage extends Model
{
    use HasUuids, SoftDeletes;

    protected $guarded = [];

    public function tracker(): BelongsTo { return $this->belongsTo(Tracker::class); }
    public function author(): BelongsTo { return $this->belongsTo(User::class, 'user_id'); }
    public function reactions(): HasMany { return $this->hasMany(TrackerMessageReaction::class); }
    public function attachments(): HasMany { return $this->hasMany(TrackerMessageAttachment::class); }
    public function settlementRequest(): BelongsTo { return $this->belongsTo(TrackerSettlementRequest::class); }
}
