<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('tracker.{trackerId}', function ($user, $trackerId) {
    return \App\Models\TrackerMember::where('tracker_id', $trackerId)->where('user_id', $user->id)->where('status', 'active')->exists();
});
