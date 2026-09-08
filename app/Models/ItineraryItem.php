<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ItineraryItem extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['latitude' => 'float', 'longitude' => 'float', 'completed_at' => 'datetime'];
    }

    public function day(): BelongsTo { return $this->belongsTo(ItineraryDay::class, 'itinerary_day_id'); }
    public function expenses(): HasMany { return $this->hasMany(Expense::class); }
}
