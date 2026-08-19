<?php

namespace App\Console\Commands;

use App\Services\SmartLifestyleAlerts;
use Illuminate\Console\Command;

class SendLifestyleReminders extends Command
{
    protected $signature = 'personal-finance:send-lifestyle-reminders';
    protected $description = 'Send scheduled lifestyle spending reminders.';

    public function handle(SmartLifestyleAlerts $alerts): int
    {
        $sent = $alerts->sendScheduledReminders();
        $this->info("Sent {$sent} lifestyle reminder(s).");

        return self::SUCCESS;
    }
}
