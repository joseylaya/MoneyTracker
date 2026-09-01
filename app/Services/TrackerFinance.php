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
            if ($expense->expense_type === 'sponsored') continue;
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

    /** Detailed expense and payment trail behind each member's net balance. */
    public function settlementBreakdown(Tracker $tracker): array
    {
        $members = $tracker->members()->where('status', 'active')->with('user:id,name')->get();
        $rows = $members->mapWithKeys(fn ($member) => [$member->user_id => [
            'user_id' => (int) $member->user_id, 'name' => $member->user?->name, 'counterparties' => [],
        ]])->all();

        $counterparty = function (int $userId, int $otherId) use (&$rows): array {
            return $rows[$userId]['counterparties'][$otherId] ?? [
                'user_id' => $otherId, 'name' => $rows[$otherId]['name'] ?? 'Unknown member',
                'you_owe_expenses_minor' => 0, 'they_owe_expenses_minor' => 0,
                'you_paid_minor' => 0, 'they_paid_minor' => 0, 'expenses' => [],
            ];
        };

        foreach (Expense::with('splits')->where('tracker_id', $tracker->id)->whereNull('deleted_at')->get() as $expense) {
            if ($expense->expense_type === 'sponsored') continue;
            $payerId = (int) $expense->paid_by_user_id;
            foreach ($expense->splits as $split) {
                $debtorId = (int) $split->user_id;
                $amount = (int) $split->amount_minor;
                if ($debtorId === $payerId || $amount === 0 || ! isset($rows[$debtorId], $rows[$payerId])) continue;
                $debtor = $counterparty($debtorId, $payerId);
                $debtor['you_owe_expenses_minor'] += $amount;
                $debtor['expenses'][] = ['expense_id' => $expense->id, 'description' => $expense->description, 'expense_date' => $expense->expense_date?->format('Y-m-d'), 'direction' => 'you_owe', 'amount_minor' => $amount];
                $rows[$debtorId]['counterparties'][$payerId] = $debtor;
                $payer = $counterparty($payerId, $debtorId);
                $payer['they_owe_expenses_minor'] += $amount;
                $payer['expenses'][] = ['expense_id' => $expense->id, 'description' => $expense->description, 'expense_date' => $expense->expense_date?->format('Y-m-d'), 'direction' => 'owed_to_you', 'amount_minor' => $amount];
                $rows[$payerId]['counterparties'][$debtorId] = $payer;
            }
        }

        foreach (Settlement::where('tracker_id', $tracker->id)->whereNull('deleted_at')->get() as $settlement) {
            $from = (int) $settlement->from_user_id; $to = (int) $settlement->to_user_id; $amount = (int) $settlement->amount_minor;
            if ($from === $to || ! isset($rows[$from], $rows[$to])) continue;
            $payer = $counterparty($from, $to); $payer['you_paid_minor'] += $amount; $rows[$from]['counterparties'][$to] = $payer;
            $receiver = $counterparty($to, $from); $receiver['they_paid_minor'] += $amount; $rows[$to]['counterparties'][$from] = $receiver;
        }

        $balances = $this->memberBalances($tracker);
        return collect($rows)->map(function (array $row) use ($balances) {
            $row['counterparties'] = collect($row['counterparties'])->map(function (array $other) {
                $other['net_minor'] = $other['you_owe_expenses_minor'] - $other['they_owe_expenses_minor'] - $other['you_paid_minor'] + $other['they_paid_minor'];
                return $other;
            })->sortBy('name')->values()->all();
            $row['you_owe_expenses_minor'] = collect($row['counterparties'])->sum('you_owe_expenses_minor');
            $row['owed_to_you_expenses_minor'] = collect($row['counterparties'])->sum('they_owe_expenses_minor');
            $row['settlements_paid_minor'] = collect($row['counterparties'])->sum('you_paid_minor');
            $row['settlements_received_minor'] = collect($row['counterparties'])->sum('they_paid_minor');
            $row['balance_minor'] = (int) ($balances[$row['user_id']] ?? 0);
            return $row;
        })->sortBy('name')->values()->all();
    }

    /** Outstanding pairwise obligations, kept with the members who actually spent. */
    public function spenderObligations(Tracker $tracker): array
    {
        return collect($this->settlementBreakdown($tracker))->flatMap(function (array $member) {
            return collect($member['counterparties'])
                ->filter(fn (array $other) => $other['net_minor'] > 0)
                ->map(fn (array $other) => [
                    'from_user_id' => (int) $member['user_id'],
                    'to_user_id' => (int) $other['user_id'],
                    'amount_minor' => (int) $other['net_minor'],
                ]);
        })->values()->all();
    }

    /** @return array<int, array{from_user_id:int,to_user_id:int,amount_minor:int}> */
    private function calculateDirectDebts(Tracker $tracker): array
    {
        // Settle net positions, not each expense independently. The latter can
        // leave circular or redundant debts even though the balances are right.
        $balances = $this->calculateMemberBalances($tracker);
        ksort($balances);

        $debtors = collect($balances)
            ->filter(fn (int $balance) => $balance < 0)
            ->map(fn (int $balance, int $userId) => ['user_id' => $userId, 'amount_minor' => -$balance])
            ->values()
            ->all();
        $creditors = collect($balances)
            ->filter(fn (int $balance) => $balance > 0)
            ->map(fn (int $balance, int $userId) => ['user_id' => $userId, 'amount_minor' => $balance])
            ->values()
            ->all();

        $debts = [];
        $debtorIndex = 0;
        $creditorIndex = 0;
        while (isset($debtors[$debtorIndex], $creditors[$creditorIndex])) {
            $amount = min($debtors[$debtorIndex]['amount_minor'], $creditors[$creditorIndex]['amount_minor']);
            if ($amount > 0 && $debtors[$debtorIndex]['user_id'] !== $creditors[$creditorIndex]['user_id']) {
                $debts[] = [
                    'from_user_id' => $debtors[$debtorIndex]['user_id'],
                    'to_user_id' => $creditors[$creditorIndex]['user_id'],
                    'amount_minor' => $amount,
                ];
            }

            $debtors[$debtorIndex]['amount_minor'] -= $amount;
            $creditors[$creditorIndex]['amount_minor'] -= $amount;
            if ($debtors[$debtorIndex]['amount_minor'] === 0) $debtorIndex++;
            if ($creditors[$creditorIndex]['amount_minor'] === 0) $creditorIndex++;
        }

        return $debts;
    }

    private function balancesKey(Tracker $tracker): string { return "tracker:{$tracker->id}:balances:v1"; }
    private function debtsKey(Tracker $tracker): string { return "tracker:{$tracker->id}:direct-debts:v2"; }
}
