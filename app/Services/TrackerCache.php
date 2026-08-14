<?php

namespace App\Services;

use App\Models\Tracker;
use Illuminate\Support\Facades\Cache;

class TrackerCache
{
    private const MEMBERS_TTL_SECONDS = 300;

    /** @return array<int, array{id:int,membership_id:int,name:string,email:string,role:string}> */
    public function activeMembers(Tracker $tracker): array
    {
        return Cache::remember($this->membersKey($tracker), self::MEMBERS_TTL_SECONDS, fn () => $tracker->members()
            ->where('status', 'active')
            ->with('user:id,name,email')
            ->get()
            ->map(fn ($member) => [
                'id' => $member->user_id,
                'membership_id' => $member->id,
                'name' => $member->user->name,
                'email' => $member->user->email,
                'role' => $member->role,
            ])
            ->values()
            ->all());
    }

    public function forget(Tracker $tracker): void
    {
        Cache::forget($this->membersKey($tracker));
    }

    private function membersKey(Tracker $tracker): string
    {
        return "tracker:{$tracker->id}:active-members:v1";
    }
}
