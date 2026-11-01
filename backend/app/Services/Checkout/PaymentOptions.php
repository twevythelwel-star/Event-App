<?php

declare(strict_types=1);

namespace App\Services\Checkout;

use App\Models\Event;
use App\Models\HostPaymentPolicy;
use App\Models\SystemPlatformSetting;

/**
 * Payment methods a customer may choose for an event: those the organizer has set up
 * (HostPaymentPolicy) and the platform allows (system_platform_settings.supported_payment_methods).
 * A transfer method is only offered when the organizer has written instructions for it.
 */
final class PaymentOptions
{
    /**
     * @return list<array{value: string, label: string, description: string, icon: string, instructions: ?string}>
     */
    public static function forEvent(Event $event): array
    {
        $policy = HostPaymentPolicy::where('organizer_id', $event->organizer_id)->first();
        $allowed = SystemPlatformSetting::first()?->supported_payment_methods;

        $candidates = [
            'bank' => ['Bank transfer', 'Transfer to the organizer, then send your reference.', 'payment', $policy?->bank_transfer_instructions],
            'zelle' => ['Zelle', 'Send with Zelle, then send your confirmation number.', 'payment', $policy?->zelle_instructions],
            'cashapp' => ['Cash App', 'Send with Cash App, then send your confirmation.', 'payment', $policy?->cash_app_instructions],
            'cash' => ['Cash at pickup', 'Pay staff when you collect. Your order is held as awaiting payment.', 'credit-card', null],
        ];

        $options = [];
        foreach ($candidates as $value => [$label, $description, $icon, $instructions]) {
            $enabledByHost = $value === 'cash'
                ? ($policy === null ? true : (bool) $policy->enable_cash) // database default: cash on
                : trim((string) $instructions) !== '';
            $allowedByPlatform = !is_array($allowed) || in_array($value, $allowed, true);

            if ($enabledByHost && $allowedByPlatform) {
                $options[] = [
                    'value' => $value,
                    'label' => $label,
                    'description' => $description,
                    'icon' => $icon,
                    'instructions' => $value === 'cash' ? null : trim((string) $instructions),
                ];
            }
        }

        return $options;
    }

    public static function instructionsFor(Event $event, string $method): ?string
    {
        foreach (self::forEvent($event) as $option) {
            if ($option['value'] === $method) {
                return $option['instructions'];
            }
        }

        return null;
    }
}
