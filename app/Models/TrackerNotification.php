<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrackerNotification extends Model
{
    use HasUuids;
    protected $guarded = [];
    protected function casts(): array { return ['data' => 'array', 'read_at' => 'datetime', 'dismissed_at' => 'datetime']; }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function tracker(): BelongsTo { return $this->belongsTo(Tracker::class); }
    public function actor(): BelongsTo { return $this->belongsTo(User::class, 'actor_user_id'); }
}
