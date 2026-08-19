<?php

namespace App\Services;

use App\Models\Tracker;
use Illuminate\Support\Facades\Cache;

class ConversationPresence
{
    /** A short-lived marker prevents pushes only while the conversation is visibly open. */
    public function mark(int $userId, Tracker $tracker): void
    {
        Cache::put($this->key($userId, $tracker->id), true, now()->addSeconds(75));
    }

    public function forget(int $userId, Tracker $tracker): void
    {
        Cache::forget($this->key($userId, $tracker->id));
    }

    public function isViewing(int $userId, Tracker $tracker): bool
    {
        return Cache::has($this->key($userId, $tracker->id));
    }

    private function key(int $userId, string $trackerId): string
    {
        return "tracker-conversation-presence:{$userId}:{$trackerId}";
    }
}
