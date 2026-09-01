<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\WebPushService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\SerializesModels;

class SendPushNotification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 2;
    public int $backoff = 15;

    /** @param array<string, scalar|null> $data */
    public function __construct(
        public int $userId,
        public string $title,
        public string $body,
        public array $data = [],
    ) {
        // Keep network-bound push work out of the interactive request path.
        $this->onQueue('notifications')->afterCommit();
    }

    public function handle(WebPushService $push): void
    {
        $user = User::find($this->userId);

        if ($user) {
            $push->send($user, $this->title, $this->body, $this->data);
        }
    }
}
