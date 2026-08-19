<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class PersonalCommitment extends Model { protected $guarded=[]; protected function casts(): array { return ['active'=>'boolean']; } public function transactions(): HasMany { return $this->hasMany(PersonalTransaction::class); } }
