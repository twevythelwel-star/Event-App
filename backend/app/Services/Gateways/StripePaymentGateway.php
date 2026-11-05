<?php

declare(strict_types=1);

namespace App\Services\Gateways;

use App\Contracts\PaymentGatewayInterface;
use App\Models\Order;
use LogicException;

/**
 * Deliberately unavailable until checkout is connected to Stripe's API and signed webhooks.
 * This adapter used to fabricate PaymentIntent and refund identifiers and mark orders paid
 * from caller supplied values, which must never be used for real transactions.
 */
final class StripePaymentGateway implements PaymentGatewayInterface
{
    public function getIdentifier(): string
    {
        return 'stripe_us';
    }

    public function isAvailable(string $currency, string $country = 'US'): bool
    {
        return false;
    }

    public function initiatePayment(Order $order, array $paymentData): array
    {
        throw new LogicException('Stripe checkout is unavailable until verified provider integration is configured.');
    }

    public function verifyPayment(Order $order, array $verificationData): array
    {
        throw new LogicException('Stripe payment status can only be updated by a verified Stripe webhook.');
    }

    public function processRefund(Order $order, int $refundAmountMinor, string $reason, array $refundData = []): array
    {
        throw new LogicException('Stripe refunds are unavailable until verified provider integration is configured.');
    }
}
