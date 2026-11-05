<?php

declare(strict_types=1);

namespace App\Payments;

use App\Models\Event;
use App\Models\HostPaymentPolicy;
use App\Models\SystemPlatformSetting;

/**
 * Every payment method EventBite knows, and whether it can be offered for one event and amount.
 *
 * A method is offered only when all of these hold:
 *  1. the platform allows the method (system_platform_settings.supported_payment_methods);
 *  2. for processor methods, a production checkout and webhook integration must be implemented;
 *  3. the organizer has switched it on (and, for transfers, written the instructions customers follow);
 *  4. it works in the event's currency, and the amount is one the processor accepts.
 *
 * Kinds: "processor" (the processor confirms the payment), "manual" (a transfer the organizer verifies),
 * "cash" (paid to staff at the event).
 */
final class PaymentMethods
{
    public const PROCESSOR = 'processor';
    public const MANUAL = 'manual';
    public const CASH = 'cash';

    /** @var array<string, array{kind: string, provider: ?string, label: string, description: string, icon: string, currencies: ?list<string>, host_flag: string, instructions: ?string}> */
    private const DEFINITIONS = [
        'card' => [
            'kind' => self::PROCESSOR, 'provider' => 'stripe', 'label' => 'Card',
            'description' => 'Pay by debit or credit card.',
            'icon' => 'credit-card', 'currencies' => null, 'host_flag' => 'enable_card', 'instructions' => null,
        ],
        'zelle' => [
            'kind' => self::MANUAL, 'provider' => null, 'label' => 'Zelle',
            'description' => 'Send with Zelle from your bank app, then send us the confirmation number. The organizer checks it before your ticket is marked paid.',
            'icon' => 'payment', 'currencies' => ['USD'], 'host_flag' => 'enable_zelle', 'instructions' => 'zelle_instructions',
        ],
        'cashapp' => [
            'kind' => self::MANUAL, 'provider' => null, 'label' => 'Cash App transfer',
            'description' => 'Send to the organizer\'s $Cashtag, then send us the confirmation. The organizer checks it before your ticket is marked paid.',
            'icon' => 'payment', 'currencies' => ['USD'], 'host_flag' => 'enable_cashapp', 'instructions' => 'cash_app_instructions',
        ],
        'bank' => [
            'kind' => self::MANUAL, 'provider' => null, 'label' => 'Bank transfer',
            'description' => 'Transfer to the organizer, then send us your reference. The organizer checks it before your ticket is marked paid.',
            'icon' => 'payment', 'currencies' => null, 'host_flag' => 'enable_bank', 'instructions' => 'bank_transfer_instructions',
        ],
        'cash' => [
            'kind' => self::CASH, 'provider' => null, 'label' => 'Cash at pickup',
            'description' => 'Pay staff when you collect. Your order is held as awaiting payment.',
            'icon' => 'credit-card', 'currencies' => null, 'host_flag' => 'enable_cash', 'instructions' => null,
        ],
    ];

    /** Platform approval keys for processors and processor-specific methods. */
    public const APPROVALS = [
        'stripe' => 'Stripe (cards, Apple Pay, Google Pay)',
        'stripe_cash_app_pay' => 'Cash App Pay through Stripe',
        'paypal' => 'PayPal',
    ];

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::DEFINITIONS);
    }

    /** @return array<string, string> */
    public static function labels(): array
    {
        return array_map(fn (array $d) => $d['label'], self::DEFINITIONS);
    }

    public static function exists(string $method): bool
    {
        return isset(self::DEFINITIONS[$method]);
    }

    public static function kind(string $method): ?string
    {
        return self::DEFINITIONS[$method]['kind'] ?? null;
    }

    public static function provider(string $method): ?string
    {
        return self::DEFINITIONS[$method]['provider'] ?? null;
    }

    public static function isProcessor(?string $method): bool
    {
        return self::kind((string) $method) === self::PROCESSOR;
    }

    public static function label(?string $method): string
    {
        return self::DEFINITIONS[(string) $method]['label'] ?? ucfirst((string) $method);
    }

    public static function hostFlag(string $method): ?string
    {
        return self::DEFINITIONS[$method]['host_flag'] ?? null;
    }

    public static function instructionsField(string $method): ?string
    {
        return self::DEFINITIONS[$method]['instructions'] ?? null;
    }

    /** Whether a processor has all the credentials it needs. Never reveals the credentials themselves. */
    public static function processorConfigured(string $provider): bool
    {
        // Configuration alone does not make a processor safe to offer. No provider has a
        // production intent/capture/webhook implementation wired into checkout yet.
        return false;
    }

    /** @return array<string, bool> approval key => approved */
    public static function approvals(?SystemPlatformSetting $settings = null): array
    {
        $settings ??= SystemPlatformSetting::first();
        $stored = (array) ($settings?->payment_processors ?? []);

        $approvals = [];
        foreach (array_keys(self::APPROVALS) as $key) {
            $approvals[$key] = (bool) ($stored[$key] ?? false);
        }

        return $approvals;
    }

    /**
     * Every method with whether it can be offered and, if not, why (for the organizer's settings page).
     *
     * @return array<string, array{method: string, kind: string, label: string, description: string, icon: string, instructions: ?string, available: bool, reason: ?string}>
     */
    public static function evaluate(Event $event, ?int $amountMinor = null, ?HostPaymentPolicy $policy = null, ?SystemPlatformSetting $settings = null): array
    {
        $policy ??= HostPaymentPolicy::where('organizer_id', $event->organizer_id)->first();
        $settings ??= SystemPlatformSetting::first();
        $allowed = $settings?->supported_payment_methods;
        $approvals = self::approvals($settings);
        $currency = strtoupper((string) ($event->currency ?: \App\Support\Currency::defaultCode()));

        $result = [];
        foreach (self::DEFINITIONS as $method => $d) {
            $instructions = $d['instructions'] ? trim((string) ($policy?->{$d['instructions']} ?? '')) : null;
            $reason = self::unavailableReason($method, $d, $policy, $allowed, $approvals, $currency, $amountMinor, $instructions);
            $result[$method] = [
                'method' => $method,
                'kind' => $d['kind'],
                'label' => $d['label'],
                'description' => $d['description'],
                'icon' => $d['icon'],
                'instructions' => $instructions !== '' ? $instructions : null,
                'available' => $reason === null,
                'reason' => $reason,
            ];
        }

        return $result;
    }

    /**
     * The methods a customer may choose for this event (and amount, when known).
     *
     * @return list<array{value: string, kind: string, label: string, description: string, icon: string, instructions: ?string}>
     */
    public static function availableFor(Event $event, ?int $amountMinor = null): array
    {
        $options = [];
        foreach (self::evaluate($event, $amountMinor) as $method => $m) {
            if ($m['available']) {
                $options[] = ['value' => $method] + array_intersect_key($m, array_flip(['kind', 'label', 'description', 'icon', 'instructions']));
            }
        }

        return $options;
    }

    /** @param array<string, bool> $approvals */
    private static function unavailableReason(string $method, array $d, ?HostPaymentPolicy $policy, ?array $allowed, array $approvals,
        string $currency, ?int $amountMinor, ?string $instructions): ?string
    {
        if (is_array($allowed) && !in_array($method, $allowed, true)) {
            return 'Not offered on this platform.';
        }

        if ($d['kind'] === self::PROCESSOR) {
            return 'Online card processing is not available yet.';
        }

        if ($d['currencies'] !== null && !in_array($currency, $d['currencies'], true)) {
            return "Not available for events priced in {$currency}.";
        }

        // Organiser switches: only cash is enabled by default. Online processors require a
        // reviewed implementation and an explicit opt-in after that work is complete.
        $flag = $d['host_flag'];
        $enabled = $policy !== null ? (bool) $policy->{$flag} : $flag === 'enable_cash';
        if (!$enabled) {
            return 'Switched off by the organizer.';
        }
        if ($d['kind'] === self::MANUAL && ($instructions === null || $instructions === '')) {
            return 'The organizer has not added payment instructions.';
        }

        return null;
    }
}
