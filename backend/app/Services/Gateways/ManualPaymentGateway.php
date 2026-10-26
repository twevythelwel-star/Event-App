<?php

declare(strict_types=1);

namespace App\Services\Gateways;

use App\Contracts\PaymentGatewayInterface;
use App\Models\Order;
use App\Services\PriceCalculationService;
use InvalidArgumentException;
use RuntimeException;

/**
 * ManualPaymentGateway
 *
 * Implements manual US cash, ACH transfer, wire, and Zelle payment workflows.
 * Enforces the strict rule: Uploaded screenshots or attendee payment claims
 * are NEVER treated as proof of funds. Payments must be verified through
 * an authorized, auditable reconciliation workflow before being marked paid.
 */
class ManualPaymentGateway implements PaymentGatewayInterface
{
    public function getIdentifier(): string
    {
        return 'manual_us';
    }

    public function isAvailable(string $currency, string $country = 'US'): bool
    {
        return strtoupper($currency) === 'USD' && strtoupper($country) === 'US';
    }

    /**
     * Attendee submits transfer reference or cash payment intent.
     * Status is set strictly to 'payment_submitted' or 'under_verification'.
     * NEVER marked 'paid' until verified.
     */
    public function initiatePayment(Order $order, array $paymentData): array
    {
        $reference = trim((string) ($paymentData['reference'] ?? ''));
        $senderName = trim((string) ($paymentData['sender_name'] ?? ''));
        $method = trim((string) ($paymentData['method'] ?? 'bank_transfer'));
        $receiptPath = $paymentData['receipt_path'] ?? null;

        $order->payment_reference = $reference ?: null;
        $order->sender_name = $senderName ?: null;
        $order->method = $method;
        $order->status = 'payment_submitted'; // Strictly awaiting verification

        $order->verification_notes = "Payment claim submitted by attendee. Awaiting bank/cash reconciliation. Screenshot: " . ($receiptPath ? 'attached' : 'none');
        $order->save();

        return [
            'success' => true,
            'status' => 'payment_submitted',
            'is_paid' => false,
            'message' => 'Payment details submitted. Your order will be confirmed once funds are verified by event operations.',
            'order_id' => $order->id,
            'reference' => $order->payment_reference,
        ];
    }

    /**
     * Authorized Event Host or Administrator verifies that funds were received.
     */
    public function verifyPayment(Order $order, array $verificationData): array
    {
        $verifierId = (string) ($verificationData['verifier_id'] ?? '');
        $verifierName = (string) ($verificationData['verifier_name'] ?? 'Authorized Staff');
        $amountVerifiedMinor = (int) ($verificationData['amount_verified_minor'] ?? 0);
        $notes = trim((string) ($verificationData['notes'] ?? 'Verified via bank/cash records'));

        if ($amountVerifiedMinor <= 0) {
            throw new InvalidArgumentException("Verified amount must be greater than zero.");
        }

        $totalOrderMinor = PriceCalculationService::toMinorUnits((float) $order->total_amount, $order->currency ?: 'USD');
        $currentPaidMinor = PriceCalculationService::toMinorUnits((float) ($order->amount_paid ?? 0), $order->currency ?: 'USD');
        $newTotalPaidMinor = $currentPaidMinor + $amountVerifiedMinor;

        $financials = PriceCalculationService::calculateOrderFinancials(
            PriceCalculationService::toMinorUnits((float) $order->subtotal, $order->currency ?: 'USD'),
            PriceCalculationService::toMinorUnits((float) $order->packaging_fee, $order->currency ?: 'USD'),
            $newTotalPaidMinor,
            0,
            $order->currency ?: 'USD'
        );

        $order->amount_paid = PriceCalculationService::fromMinorUnits($newTotalPaidMinor, $order->currency ?: 'USD');
        $order->status = $financials['is_fully_paid'] ? 'paid' : 'partially_paid';
        $order->verified_at = now();
        $order->verified_by = $verifierId ?: null;
        $order->verification_notes = "{$notes} [Verified by {$verifierName} on " . date('Y-m-d H:i:s') . ']';
        $order->save();

        return [
            'success' => true,
            'status' => $order->status,
            'is_paid' => $financials['is_fully_paid'],
            'amount_verified_minor' => $amountVerifiedMinor,
            'total_amount_minor' => $financials['total_amount_minor'],
            'amount_received_minor' => $financials['amount_received_minor'],
            'outstanding_balance_minor' => $financials['outstanding_balance_minor'],
            'order_id' => $order->id,
        ];
    }

    /**
     * Process an authorized refund with audit trail.
     */
    public function processRefund(Order $order, int $refundAmountMinor, string $reason, array $refundData = []): array
    {
        $currentPaidMinor = PriceCalculationService::toMinorUnits((float) ($order->amount_paid ?? 0), $order->currency ?: 'USD');
        if ($refundAmountMinor > $currentPaidMinor) {
            throw new InvalidArgumentException("Refund amount ({$refundAmountMinor} cents) cannot exceed verified amount received ({$currentPaidMinor} cents).");
        }

        $refundDetails = (array) ($order->refund_details ?? []);
        $authorizer = $refundData['authorizer_name'] ?? 'Authorized Administrator';

        $refundRecord = [
            'amount_minor' => $refundAmountMinor,
            'formatted_amount' => PriceCalculationService::formatMinorUnits($refundAmountMinor, $order->currency ?: 'USD'),
            'reason' => $reason,
            'authorized_by' => $authorizer,
            'timestamp' => date('Y-m-d H:i:s'),
        ];
        $refundDetails[] = $refundRecord;

        $totalRefundedMinor = array_sum(array_column($refundDetails, 'amount_minor'));
        $netReceiptsMinor = $currentPaidMinor - $totalRefundedMinor;

        $order->refund_details = $refundDetails;
        if ($netReceiptsMinor <= 0) {
            $order->status = 'refunded';
        }

        $order->save();

        return [
            'success' => true,
            'status' => $order->status,
            'refunded_amount_minor' => $refundAmountMinor,
            'total_refunded_minor' => $totalRefundedMinor,
            'net_receipts_minor' => $netReceiptsMinor,
            'order_id' => $order->id,
        ];
    }
}
