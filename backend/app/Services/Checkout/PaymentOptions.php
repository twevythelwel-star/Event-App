<?php

declare(strict_types=1);

namespace App\Services\Checkout;

use App\Models\Event;
use App\Models\HostPaymentPolicy;
use App\Payments\PaymentMethods;

/**
 * Payment methods a customer may choose for an event. The rules (platform, processor configuration and
 * approval, organiser switches, currency and amount) live in App\Payments\PaymentMethods.
 */
final class PaymentOptions
{
    /**
     * @return list<array{value: string, kind: string, label: string, description: string, icon: string, instructions: ?string, handle: ?string, handle_label: ?string}>
     */
    public static function forEvent(Event $event, ?int $amountMinor = null): array
    {
        return PaymentMethods::availableFor($event, $amountMinor);
    }

    /**
     * The organiser's transfer instructions for an order's method. Shown for an order already placed even if
     * the organiser has since switched the method off, so the customer can still finish paying.
     */
    public static function instructionsFor(Event $event, string $method): ?string
    {
        $field = PaymentMethods::instructionsField($method);
        if ($field === null) {
            return null;
        }
        $text = trim((string) (HostPaymentPolicy::where('organizer_id', $event->organizer_id)->value($field) ?? ''));

        return $text !== '' ? $text : null;
    }

    /**
     * Where the customer sends an order's payment (the organiser's $Cashtag or Zelle email/phone). Like the
     * instructions, shown for an order already placed even if the method has since been switched off.
     */
    public static function handleFor(Event $event, string $method): ?string
    {
        $field = PaymentMethods::handleField($method);
        if ($field === null) {
            return null;
        }
        $handle = trim((string) (HostPaymentPolicy::where('organizer_id', $event->organizer_id)->value($field) ?? ''));

        return $handle !== '' ? $handle : null;
    }
}
