<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class ExpenseSplitter
{
    /** @return array<int, array{user_id:int,amount_minor:int,quantity:?int}> */
    public function split(array $data): array
    {
        if (($data['expense_type'] ?? 'split') === 'sponsored') {
            return [];
        }

        $participants = array_values(array_unique(array_map('intval', $data['participants'] ?? [])));
        if ($participants === []) {
            throw ValidationException::withMessages(['participants' => 'Select at least one member.']);
        }

        if (($data['split_method'] ?? 'equal') !== 'quantity') {
            $share = intdiv($data['amount_minor'], count($participants));
            $remainder = $data['amount_minor'] % count($participants);

            return array_map(fn (int $userId, int $index) => [
                'user_id' => $userId,
                'amount_minor' => $share + ($index < $remainder ? 1 : 0),
                'quantity' => null,
            ], $participants, array_keys($participants));
        }

        $unitPrice = (int) ($data['unit_price_minor'] ?? 0);
        if ($unitPrice <= 0) {
            throw ValidationException::withMessages(['unit_price' => 'Enter a price per item greater than zero.']);
        }

        $quantities = $data['quantities'] ?? [];
        $splits = [];
        $allocated = 0;
        foreach ($participants as $userId) {
            $quantity = filter_var($quantities[$userId] ?? $quantities[(string) $userId] ?? null, FILTER_VALIDATE_INT);
            if ($quantity === false || $quantity < 0) {
                throw ValidationException::withMessages(["quantities.{$userId}" => 'Quantity must be zero or more.']);
            }
            $amount = $unitPrice * $quantity;
            $allocated += $amount;
            $splits[] = ['user_id' => $userId, 'amount_minor' => $amount, 'quantity' => $quantity];
        }

        if ($allocated !== (int) $data['amount_minor']) {
            throw ValidationException::withMessages(['quantities' => 'The allocated total must equal the expense total.']);
        }
        if ($allocated === 0) {
            throw ValidationException::withMessages(['quantities' => 'Enter a quantity for at least one member.']);
        }

        return $splits;
    }
}
