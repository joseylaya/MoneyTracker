<?php

namespace App\Services;

use App\Models\User;
use App\Models\WebPushSubscription;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Throwable;

class WebPushService
{
    public function __construct(private FirebasePush $firebasePush)
    {
    }

    /** @param array<string, scalar|null> $data */
    public function send(User $user, string $title, string $body, array $data = []): void
    {
        // Existing Firebase registrations remain valid while devices upgrade to Web Push.
        $this->firebasePush->send($user, $title, $body, $data);

        $publicKey = config('services.web_push.public_key');
        $privateKey = config('services.web_push.private_key');

        if (! $publicKey || ! $privateKey) {
            Log::warning('Web Push delivery skipped because VAPID is not configured.', ['user_id' => $user->id]);
            return;
        }

        try {
            $webPush = new WebPush(['VAPID' => [
                'subject' => config('services.web_push.subject'),
                'publicKey' => $publicKey,
                'privateKey' => $privateKey,
            ]]);
            $subscriptions = WebPushSubscription::where('user_id', $user->id)->get();
            $payload = json_encode([
                'title' => $title,
                'body' => $body,
                'url' => $data['url'] ?? config('app.url'),
                'badge_count' => (int) ($data['badge_count'] ?? 0),
            ], JSON_THROW_ON_ERROR);

            foreach ($subscriptions as $subscription) {
                $webPush->queueNotification(Subscription::create([
                    'endpoint' => $subscription->endpoint,
                    'publicKey' => $subscription->public_key,
                    'authToken' => $subscription->auth_token,
                ]), $payload);
            }

            foreach ($webPush->flush() as $report) {
                if ($report->isSuccess()) {
                    continue;
                }

                $endpoint = (string) $report->getRequest()->getUri();
                if ($report->isSubscriptionExpired()) {
                    WebPushSubscription::where('endpoint', $endpoint)->delete();
                }

                Log::warning('Web Push delivery failed.', [
                    'user_id' => $user->id,
                    'endpoint' => $endpoint,
                    'reason' => $report->getReason(),
                ]);
            }
        } catch (Throwable $exception) {
            Log::warning('Web Push delivery skipped.', [
                'user_id' => $user->id,
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}
