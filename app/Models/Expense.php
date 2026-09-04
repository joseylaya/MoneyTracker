<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends Model
{
    use HasUuids, SoftDeletes;
    protected $guarded = [];
    protected function casts(): array { return ['expense_date' => 'date']; }
    public function payer(): BelongsTo { return $this->belongsTo(User::class, 'paid_by_user_id'); }
    public function tracker(): BelongsTo { return $this->belongsTo(Tracker::class); }
    public function itineraryItem(): BelongsTo { return $this->belongsTo(ItineraryItem::class); }
    public function splits(): HasMany { return $this->hasMany(ExpenseSplit::class); }
    public function comments(): HasMany { return $this->hasMany(ExpenseComment::class); }
}
