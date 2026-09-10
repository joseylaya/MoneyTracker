<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

#[Fillable(['name', 'username', 'email', 'password', 'is_placeholder', 'placeholder_tracker_id'])]
#[Hidden(['password', 'remember_token', 'avatar_path'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $appends = ['avatar_url'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_placeholder' => 'boolean',
        ];
    }

    public function getAvatarUrlAttribute(): ?string
    {
        if (! $this->avatar_path) {
            return null;
        }

        $diskName = config('filesystems.profile_photos_disk');
        $disk = Storage::disk($diskName);

        return $diskName === 'supabase'
            ? $disk->temporaryUrl($this->avatar_path, now()->addHour())
            : $disk->url($this->avatar_path);
    }

    public function trackerMemberships(): HasMany
    {
        return $this->hasMany(TrackerMember::class);
    }

    public function trackerNotifications(): HasMany
    {
        return $this->hasMany(TrackerNotification::class);
    }

    public function personalAccounts(): HasMany
    {
        return $this->hasMany(PersonalAccount::class);
    }
}
