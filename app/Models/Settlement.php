<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Settlement extends Model
{
    use HasUuids, SoftDeletes;
    protected $guarded = [];
    protected function casts(): array { return ['settlement_date' => 'date']; }
    public function sender(): BelongsTo { return $this->belongsTo(User::class, 'from_user_id'); }
    public function recipient(): BelongsTo { return $this->belongsTo(User::class, 'to_user_id'); }
}
