<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Settlement;
use App\Models\Tracker;
use Illuminate\Support\Facades\Cache;

class TrackerFinance
{
    /** @return array<int, int> user id => balance in minor units */
    public function memberBalances(Tracker $tracker): array
    {
        return Cache::remember($this->balancesKey($tracker), 60, fn () => $this->calculateMemberBalances($tracker));
    }

    /** @return array<int, int> user id => balance in minor units */
    private function calculateMemberBalances(Tracker $tracker): array
    {
        $balances = $tracker->members()->where('status', 'active')->pluck('user_id')->mapWithKeys(fn ($id) => [$id => 0])->all();
        $expenses = Expense::with('splits')->where('tracker_id', $tracker->id)->whereNull('deleted_at')->get();
        foreach ($expenses as $expense) {
            $balances[$expense->paid_by_user_id] = ($balances[$expense->paid_by_user_id] ?? 0) + $expense->amount_minor;
            foreach ($expense->splits as $split) {
                $balances[$split->user_id] = ($balances[$split->user_id] ?? 0) - $split->amount_minor;
            }
        }
        foreach (Settlement::where('tracker_id', $tracker->id)->whereNull('deleted_at')->get() as $settlement) {
            $balances[$settlement->from_user_id] = ($balances[$settlement->from_user_id] ?? 0) + $settlement->amount_minor;
            $balances[$settlement->to_user_id] = ($balances[$settlement->to_user_id] ?? 0) - $settlement->amount_minor;
        }
        return $balances;
    }

    /** @return array<int, array{from_user_id:int,to_user_id:int,amount_minor:int}> */
    public function directDebts(Tracker $tracker, bool $useCache = true): array
    {
        if (! $useCache) {
            return $this->calculateDirectDebts($tracker);
        }

        return Cache::remember($this->debtsKey($tracker), 30, fn () => $this->calculateDirectDebts($tracker));
    }

    public function forget(Tracker $tracker): void
    {
        Cache::forget($this->balancesKey($tracker));
        Cache::forget($this->debtsKey($tracker));
    }

    /** @return array<int, array{from_user_id:int,to_user_id:int,amount_minor:int}> */
    private function calculateDirectDebts(Tracker $tracker): array
    {
        $debts = [];
        $add = function (int $from, int $to, int $amount) use (&$debts): void {
            if ($from === $to || $amount === 0) return;
            $forward = "$from:$to";
            $reverse = "$to:$from";
            $opposite = $debts[$reverse] ?? 0;
            if ($opposite >= $amount) { $debts[$reverse] = $opposite - $amount; return; }
            unset($debts[$reverse]);
            $debts[$forward] = ($debts[$forward] ?? 0) + $amount - $opposite;
        };
        foreach (Expense::with('splits')->where('tracker_id', $tracker->id)->whereNull('deleted_at')->get() as $expense) {
            foreach ($expense->splits as $split) $add($split->user_id, $expense->paid_by_user_id, $split->amount_minor);
        }
        foreach (Settlement::where('tracker_id', $tracker->id)->whereNull('deleted_at')->get() as $settlement) $add($settlement->to_user_id, $settlement->from_user_id, $settlement->amount_minor);
        return collect($debts)->filter()->map(function ($amount, $key) {
            [$from, $to] = explode(':', $key);
            return ['from_user_id' => (int) $from, 'to_user_id' => (int) $to, 'amount_minor' => $amount];
        })->values()->all();
    }

    private function balancesKey(Tracker $tracker): string { return "tracker:{$tracker->id}:balances:v1"; }
    private function debtsKey(Tracker $tracker): string { return "tracker:{$tracker->id}:direct-debts:v1"; }
}
