<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class PersonalTransaction extends Model { protected $guarded=[]; protected function casts(): array { return ['occurred_on'=>'date']; } public function account(): BelongsTo { return $this->belongsTo(PersonalAccount::class,'personal_account_id'); } public function commitment(): BelongsTo { return $this->belongsTo(PersonalCommitment::class,'personal_commitment_id'); } }
