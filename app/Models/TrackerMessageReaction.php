<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrackerMessageReaction extends Model
{
    use HasUuids;

    protected $guarded = [];

    public function message(): BelongsTo { return $this->belongsTo(TrackerMessage::class, 'tracker_message_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
