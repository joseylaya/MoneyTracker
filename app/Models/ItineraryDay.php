<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ItineraryDay extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['date' => 'date:Y-m-d'];
    }

    public function tracker(): BelongsTo { return $this->belongsTo(Tracker::class); }
    public function items(): HasMany { return $this->hasMany(ItineraryItem::class)->orderBy('sort_order')->orderBy('start_time'); }
}
