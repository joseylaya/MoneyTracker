<?php

namespace App\Services;

use App\Models\PersonalTransaction;
use App\Models\TrackerNotification;
use App\Models\User;

class SmartLifestyleAlerts
{
    private const REMINDER_TIMES = ['11:00', '15:00', '19:00', '22:00'];
    private const TIMEZONE = 'Asia/Manila';

    public function __construct(
        private PersonalFinance $finance,
        private TrackerNotifier $notifier,
    ) {}

    public function afterLifestyleExpense(User $user, PersonalTransaction $expense): void
    {
        if ($expense->category !== 'Lifestyle' || ! $expense->occurred_on->isToday()) return;

        $summary = $this->finance->summary($user);
        $budget = $summary['lifestyleBudget'];
        if ($budget <= 0) return;

        $usedPercent = (int) floor(($summary['lifestyle'] / $budget) * 100);
        $isLargeExpense = $expense->amount_minor >= max(50000, (int) ceil($budget * 0.20));
        $hasRecentLifestyleExpense = PersonalTransaction::where('user_id', $user->id)
            ->where('type', 'expense')->where('category', 'Lifestyle')->whereKeyNot($expense->id)
            ->where('created_at', '>=', now()->subHours(3))->exists();
        $isFastSpending = $hasRecentLifestyleExpense && $expense->amount_minor >= max(20000, (int) ceil($budget * 0.10));

        if ($usedPercent < 70 || (! $isLargeExpense && ! $isFastSpending) || $this->sentToday($user, 'personal.lifestyle.spending-alert')) return;

        $this->send($user, 'personal.lifestyle.spending-alert', 'Lifestyle spending check', $summary, "You are {$usedPercent}% through your lifestyle budget after a recent spend.");
    }

    public function sendScheduledReminders(): int
    {
        $now = now(self::TIMEZONE);
        if (! in_array($now->format('H:i'), self::REMINDER_TIMES, true)) return 0;

        $slot = $now->format('H');
        $type = "personal.lifestyle.reminder.{$slot}";
        $sent = 0;
        User::query()->whereIn('id', function ($query) {
            $query->select('user_id')->from('personal_finance_settings');
        })->each(function (User $user) use ($type, &$sent) {
            if ($this->sentToday($user, $type)) return;
            $summary = $this->finance->summary($user);
            if ($summary['lifestyleBudget'] <= 0) return;
            $this->send($user, $type, 'Lifestyle check-in', $summary, 'A quick look before your next spend.');
            $sent++;
        });

        return $sent;
    }

    private function sentToday(User $user, string $type): bool
    {
        return TrackerNotification::where('user_id', $user->id)->where('type', $type)
            ->whereDate('created_at', now(self::TIMEZONE)->toDateString())->exists();
    }

    /** @param array<string, mixed> $summary */
    private function send(User $user, string $type, string $title, array $summary, string $context): void
    {
        $remaining = max(0, $summary['lifestyleBudget'] - $summary['lifestyle']);
        $usedPercent = (int) floor(($summary['lifestyle'] / $summary['lifestyleBudget']) * 100);
        $currency = $summary['settings']->currency_code;
        $body = 'Lifestyle left: '.$this->money($remaining, $currency)." · {$usedPercent}% used. {$context}";

        $this->notifier->user($user->id, null, null, $type, $title, $body, route('home'), [
            'personal_notification' => 'lifestyle',
            'lifestyle_remaining_minor' => $remaining,
            'lifestyle_used_percent' => $usedPercent,
        ]);
    }

    private function money(int $minor, string $currency): string
    {
        return $currency === 'PHP' ? '₱'.number_format($minor / 100, 2) : $currency.' '.number_format($minor / 100, 2);
    }
}
