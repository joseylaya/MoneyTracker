<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrackerInvitation extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['accepted_at' => 'datetime', 'declined_at' => 'datetime', 'revoked_at' => 'datetime', 'expires_at' => 'datetime'];
    }

    public function tracker(): BelongsTo { return $this->belongsTo(Tracker::class); }
    public function inviter(): BelongsTo { return $this->belongsTo(User::class, 'invited_by'); }
}
