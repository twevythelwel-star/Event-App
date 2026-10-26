<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\Order;

/**
 * PaymentGatewayInterface
 *
 * Modular contract for payment providers. Allows seamlessly swapping between
 * manual US payment workflows (Cash, Domestic ACH, Wire, Zelle) and automated
 * US card/ACH gateways (e.g. Stripe, Square, Authorize.net).
 */
interface PaymentGatewayInterface
{
    /**
     * Get the provider's unique machine identifier.
     */
    public function getIdentifier(): string;

    /**
     * Determine if this gateway is available for the given currency and country.
     */
    public function isAvailable(string $currency, string $country = 'US'): bool;

    /**
     * Process an initial payment or claim.
     * Note: For manual workflows, this submits the claim for review and NEVER marks paid automatically.
     */
    public function initiatePayment(Order $order, array $paymentData): array;

    /**
     * Verify payment funds received via official provider or manual reconciler.
     */
    public function verifyPayment(Order $order, array $verificationData): array;

    /**
     * Process an authorized refund.
     */
    public function processRefund(Order $order, int $refundAmountMinor, string $reason, array $refundData = []): array;
}
