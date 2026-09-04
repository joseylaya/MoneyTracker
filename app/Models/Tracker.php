<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tracker extends Model
{
    use HasUuids, SoftDeletes;

    protected $guarded = [];
    protected $hidden = ['share_token'];

    public function members(): HasMany { return $this->hasMany(TrackerMember::class); }
    public function expenses(): HasMany { return $this->hasMany(Expense::class); }
    public function settlements(): HasMany { return $this->hasMany(Settlement::class); }
    public function invitations(): HasMany { return $this->hasMany(TrackerInvitation::class); }
    public function messages(): HasMany { return $this->hasMany(TrackerMessage::class); }
    public function settlementRequests(): HasMany { return $this->hasMany(TrackerSettlementRequest::class); }
    public function notifications(): HasMany { return $this->hasMany(TrackerNotification::class); }
    public function itineraryDays(): HasMany { return $this->hasMany(ItineraryDay::class)->orderBy('date')->orderBy('sort_order'); }
}
