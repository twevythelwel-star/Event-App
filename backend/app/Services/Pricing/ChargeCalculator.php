<?php

declare(strict_types=1);

namespace App\Services\Pricing;

use App\Models\ChargeRule;
use App\Models\Event;
use App\Support\Money;

/**
 * Works out the fee, tax and discount lines for an order from configuration. It never assumes a rate:
 * the only charges are the event's own packaging fee (a host fee on food orders, as before) and the
 * active ChargeRule rows that apply to this event: platform-wide, the event's organizer, the event
 * itself, optionally limited to one US state.
 *
 * Order of application, all in integer minor units:
 *   1. discounts, each capped at what it applies to, reduce the amounts that fees and taxes are based on;
 *   2. platform and host fees, based on the discounted item amounts;
 *   3. taxes, based on the discounted item amounts in their scope. Fees are not taxed by default, because
 *      whether a fee is taxable depends on the jurisdiction; configure a separate tax rule if it is.
 *
 * Fixed-amount rules in a different currency from the order are skipped rather than converted.
 */
final class ChargeCalculator
{
    /**
     * @param list<array{kind: string, line_total_minor: int, quantity: int}> $lines kind is 'ticket' or 'food'
     * @return list<array{kind: string, label: string, charge_rule_id: ?string, method: string, rate_bp: ?int, basis_minor: int, amount_minor: int, currency: string}>
     */
    public function charges(Event $event, array $lines, string $currency): array
    {
        $scopes = [
            'tickets' => $this->sum($lines, 'ticket'),
            'food' => $this->sum($lines, 'food'),
        ];
        $quantities = [
            'tickets' => $this->count($lines, 'ticket'),
            'food' => $this->count($lines, 'food'),
        ];

        $rules = $this->rulesFor($event);
        $charges = [];

        // 1. Discounts reduce the amounts later charges are based on, proportionally per scope.
        foreach ($this->ofKind($rules, ['discount']) as $rule) {
            $basis = $this->basis($rule->applies_to, $scopes);
            $amount = $this->amountFor($rule, $basis, $this->quantity($rule->applies_to, $quantities), $currency);
            if ($amount === null || $amount <= 0) {
                continue;
            }
            $amount = min($amount, $basis);
            $charges[] = $this->line($rule, $basis, $amount, $currency);
            $this->reduce($scopes, $rule->applies_to, $amount);
        }

        // 2. Fees: the event's packaging fee first (host fee on food), then configured rules.
        $packaging = (int) ($event->packaging_fee_minor ?? 0);
        if ($packaging > 0 && $quantities['food'] > 0 && strtoupper((string) ($event->currency ?: $currency)) === $currency) {
            $charges[] = [
                'kind' => 'host_fee', 'label' => 'Packaging fee', 'charge_rule_id' => null, 'method' => 'fixed_per_order',
                'rate_bp' => null, 'basis_minor' => $scopes['food'], 'amount_minor' => $packaging, 'currency' => $currency,
            ];
        }
        foreach ($this->ofKind($rules, ['platform_fee', 'host_fee']) as $rule) {
            $basis = $this->basis($rule->applies_to, $scopes);
            $amount = $this->amountFor($rule, $basis, $this->quantity($rule->applies_to, $quantities), $currency);
            if ($amount !== null && $amount > 0) {
                $charges[] = $this->line($rule, $basis, $amount, $currency);
            }
        }

        // 3. Taxes on the discounted item amounts in their scope.
        foreach ($this->ofKind($rules, ['tax']) as $rule) {
            $basis = $this->basis($rule->applies_to, $scopes);
            $amount = $this->amountFor($rule, $basis, $this->quantity($rule->applies_to, $quantities), $currency);
            if ($amount !== null && $amount > 0) {
                $charges[] = $this->line($rule, $basis, $amount, $currency);
            }
        }

        return $charges;
    }

    /** @return list<ChargeRule> */
    /**
     * The active rules an order at this event would be charged, for showing customers before they buy. Fixed
     * amounts in another currency are left out, exactly as charges() leaves them out.
     *
     * @return list<ChargeRule>
     */
    public function applicableRules(Event $event): array
    {
        $currency = strtoupper((string) ($event->currency ?: \App\Support\Currency::defaultCode()));

        return array_values(array_filter($this->rulesFor($event),
            fn (ChargeRule $rule) => $rule->method === 'percent' || strtoupper((string) $rule->currency) === $currency));
    }

    private function rulesFor(Event $event): array
    {
        if (!function_exists('app')) {
            return []; // standalone scripts: no database, no configured rules
        }

        return ChargeRule::query()
            ->where('is_active', true)
            ->where(function ($q) use ($event) {
                $q->where(fn ($p) => $p->whereNull('organizer_id')->whereNull('event_id'))
                    ->orWhere(fn ($o) => $o->where('organizer_id', $event->organizer_id)->whereNull('event_id'))
                    ->orWhere('event_id', $event->id);
            })
            ->where(fn ($q) => $q->whereNull('state_code')->orWhere('state_code', $event->state_code))
            ->orderBy('sort_order')
            ->orderBy('created_at')
            ->get()
            ->all();
    }

    /**
     * @param list<ChargeRule> $rules
     * @param list<string> $kinds
     * @return list<ChargeRule>
     */
    private function ofKind(array $rules, array $kinds): array
    {
        return array_values(array_filter($rules, fn (ChargeRule $rule) => in_array($rule->kind, $kinds, true)));
    }

    private function amountFor(ChargeRule $rule, int $basis, int $quantity, string $currency): ?int
    {
        if ($basis <= 0) {
            return null; // nothing in this rule's scope
        }

        return match ($rule->method) {
            'percent' => $rule->rate_bp === null ? null : Money::ofMinor($basis, $currency)->percentage((int) $rule->rate_bp)->minor(),
            'fixed_per_order' => $this->fixed($rule, $currency),
            'fixed_per_item' => ($fixed = $this->fixed($rule, $currency)) === null ? null : $fixed * $quantity,
            default => null,
        };
    }

    private function fixed(ChargeRule $rule, string $currency): ?int
    {
        if ($rule->amount_minor === null || strtoupper((string) $rule->currency) !== $currency) {
            return null;
        }

        return (int) $rule->amount_minor;
    }

    private function line(ChargeRule $rule, int $basis, int $amount, string $currency): array
    {
        return [
            'kind' => $rule->kind,
            'label' => $rule->label,
            'charge_rule_id' => $rule->id,
            'method' => $rule->method,
            'rate_bp' => $rule->rate_bp,
            'basis_minor' => $basis,
            'amount_minor' => $amount,
            'currency' => $currency,
        ];
    }

    /** @param array{tickets: int, food: int} $scopes */
    private function basis(string $appliesTo, array $scopes): int
    {
        return match ($appliesTo) {
            'tickets' => $scopes['tickets'],
            'food' => $scopes['food'],
            default => $scopes['tickets'] + $scopes['food'],
        };
    }

    /** @param array{tickets: int, food: int} $quantities */
    private function quantity(string $appliesTo, array $quantities): int
    {
        return match ($appliesTo) {
            'tickets' => $quantities['tickets'],
            'food' => $quantities['food'],
            default => $quantities['tickets'] + $quantities['food'],
        };
    }

    /** Takes a discount out of the scope(s) it applied to; an "all" discount is split pro rata. */
    private function reduce(array &$scopes, string $appliesTo, int $amount): void
    {
        if ($appliesTo === 'tickets' || $appliesTo === 'food') {
            $scopes[$appliesTo] -= $amount;

            return;
        }
        $total = $scopes['tickets'] + $scopes['food'];
        $fromTickets = $total > 0 ? intdiv($amount * $scopes['tickets'], $total) : 0;
        $scopes['tickets'] -= $fromTickets;
        $scopes['food'] -= $amount - $fromTickets;
    }

    private function sum(array $lines, string $kind): int
    {
        return array_sum(array_map(fn ($l) => $l['kind'] === $kind ? (int) $l['line_total_minor'] : 0, $lines));
    }

    private function count(array $lines, string $kind): int
    {
        return array_sum(array_map(fn ($l) => $l['kind'] === $kind ? (int) $l['quantity'] : 0, $lines));
    }
}
