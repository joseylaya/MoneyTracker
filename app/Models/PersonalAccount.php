<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class PersonalAccount extends Model { protected $guarded=[]; protected function casts(): array { return ['last_reconciled_at'=>'datetime']; } public function transactions(): HasMany { return $this->hasMany(PersonalTransaction::class); } }
