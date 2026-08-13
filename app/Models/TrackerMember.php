<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrackerMember extends Model
{
    use HasUuids;
    protected $guarded = [];
    protected function casts(): array { return ['joined_at' => 'datetime', 'removed_at' => 'datetime', 'last_read_chat_at' => 'datetime']; }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function tracker(): BelongsTo { return $this->belongsTo(Tracker::class); }
}
