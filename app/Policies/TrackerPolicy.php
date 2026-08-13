<?php

namespace App\Policies;

use App\Models\Tracker;
use App\Models\User;

class TrackerPolicy
{
    public function view(User $user, Tracker $tracker): bool { return $this->role($user, $tracker) !== null; }
    public function update(User $user, Tracker $tracker): bool { return in_array($this->role($user, $tracker), ['owner', 'editor'], true); }
    public function manageMembers(User $user, Tracker $tracker): bool { return $this->role($user, $tracker) === 'owner'; }
    public function settle(User $user, Tracker $tracker): bool { return $this->role($user, $tracker) !== null; }
    public function comment(User $user, Tracker $tracker): bool { return in_array($this->role($user, $tracker), ['owner', 'editor', 'commenter'], true); }
    public function chat(User $user, Tracker $tracker): bool { return in_array($this->role($user, $tracker), ['owner', 'editor', 'commenter'], true); }

    private function role(User $user, Tracker $tracker): ?string
    {
        return $tracker->members()->where('user_id', $user->id)->where('status', 'active')->value('role');
    }
}
