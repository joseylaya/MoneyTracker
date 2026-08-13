<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\Relations\BelongsTo;
class PushDevice extends Model { protected $guarded = []; protected function casts(): array { return ['last_seen_at' => 'datetime']; } public function user(): BelongsTo { return $this->belongsTo(User::class); } }
