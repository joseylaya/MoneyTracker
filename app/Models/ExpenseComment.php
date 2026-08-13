<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExpenseComment extends Model
{
    use HasUuids, SoftDeletes;

    protected $guarded = [];

    public function expense(): BelongsTo { return $this->belongsTo(Expense::class); }
    public function author(): BelongsTo { return $this->belongsTo(User::class, 'user_id'); }
}
