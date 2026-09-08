<?php

namespace App\Http\Controllers;

use App\Models\WebPushSubscription;
use App\Models\PushDevice;
use Illuminate\Http\Request;

class PushDeviceController extends Controller
{
    public function store(Request $request)
    {
        if ($request->filled('token')) {
            $data = $request->validate(['token' => ['required', 'string', 'max:512']]);

            PushDevice::updateOrCreate(
                ['token' => $data['token']],
                ['user_id' => $request->user()->id, 'platform' => 'web', 'last_seen_at' => now()],
            );

            return response()->noContent();
        }

        $data = $request->validate([
            'subscription.endpoint' => ['required', 'string', 'max:4096'],
            'subscription.keys.p256dh' => ['required', 'string', 'max:1024'],
            'subscription.keys.auth' => ['required', 'string', 'max:1024'],
        ]);

        $subscription = $data['subscription'];

        WebPushSubscription::updateOrCreate(
            ['endpoint' => $subscription['endpoint']],
            [
                'user_id' => $request->user()->id,
                'public_key' => $subscription['keys']['p256dh'],
                'auth_token' => $subscription['keys']['auth'],
                'last_seen_at' => now(),
            ],
        );

        return response()->noContent();
    }

    public function destroy(Request $request)
    {
        $data = $request->validate([
            'token' => ['nullable', 'string', 'max:512', 'required_without:endpoint'],
            'endpoint' => ['nullable', 'string', 'max:4096', 'required_without:token'],
        ]);

        if (! empty($data['token'])) {
            PushDevice::where('user_id', $request->user()->id)->where('token', $data['token'])->delete();
        }

        if (! empty($data['endpoint'])) {
            WebPushSubscription::where('user_id', $request->user()->id)->where('endpoint', $data['endpoint'])->delete();
        }

        return response()->noContent();
    }
}
