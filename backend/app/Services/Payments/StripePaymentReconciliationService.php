<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\OrderAdjustment;
use App\Models\Payment;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/** Pulls the provider's authoritative PaymentIntent state for attempts whose webhook may be delayed/lost. */
final class StripePaymentReconciliationService
{
    /** @return array{checked:int, applied:int, mismatches:int, errors:int, skipped:int} */
    public function reconcile(int $limit = 100, ?int $staleMinutes = null): array
    {
        $result = ['checked' => 0, 'applied' => 0, 'mismatches' => 0, 'errors' => 0, 'skipped' => 0];
        $secret = (string) config('payments.stripe.secret_key', '');
        if (!config('payments.stripe.reconciliation.enabled', true) || $secret === '') {
            Log::notice('payments.reconciliation.skipped', ['provider' => 'stripe', 'reason' => 'not_configured']);
            $result['skipped']++;
            return $result;
        }

        $staleMinutes = max(1, $staleMinutes ?? (int) config('payments.stripe.reconciliation.stale_minutes', 5));
        $payments = Payment::query()->where('provider', 'stripe')->whereNotNull('provider_reference')
            ->where(function ($query) use ($staleMinutes) {
                $query->whereIn('status', [Payment::PENDING, Payment::PROCESSING])
                    ->orWhere('last_reconciled_at', '<=', now()->subMinutes($staleMinutes))
                    ->orWhereNull('last_reconciled_at');
            })->orderBy('updated_at')->limit(max(1, min(500, $limit)))->get();

        foreach ($payments as $payment) {
            $result['checked']++;
            try {
                $intent = $this->retrieveIntent($payment, $secret);
                $outcome = $this->applyProviderSnapshot($payment, $intent);
                $result[$outcome]++;
            } catch (\Throwable $exception) {
                $payment->forceFill(['reconciliation_status' => 'error', 'last_reconciled_at' => now(),
                    'reconciliation_note' => 'Provider query failed: ' . substr($exception::class, 0, 120)])->save();
                Log::error('payments.reconciliation.provider_error', ['payment_id' => $payment->id,
                    'provider_reference' => $payment->provider_reference, 'exception' => $exception::class]);
                $result['errors']++;
            }
        }

        return $result;
    }

    private function retrieveIntent(Payment $payment, string $secret): array
    {
        $base = rtrim((string) config('payments.stripe.api_base', 'https://api.stripe.com'), '/');
        $response = Http::withToken($secret)->acceptJson()->timeout(12)->retry(2, 250)
            ->get($base . '/v1/payment_intents/' . rawurlencode((string) $payment->provider_reference), [
                'expand' => ['latest_charge'],
            ]);
        if (!$response->successful()) {
            throw new RuntimeException('Stripe retrieve request returned HTTP ' . $response->status());
        }
        $body = $response->json();
        if (!is_array($body) || !isset($body['id'], $body['status'])) {
            throw new RuntimeException('Stripe returned an invalid PaymentIntent response.');
        }
        return $body;
    }

    /** @return 'applied'|'mismatches' */
    private function applyProviderSnapshot(Payment $snapshot, array $intent): string
    {
        $payment = Payment::query()->with('order')->findOrFail($snapshot->id);
        $order = $payment->order;
        $currency = strtolower((string) ($intent['currency'] ?? ''));
        $providerOrderId = (string) ($intent['metadata']['eventbite_order_id'] ?? '');
        $received = (int) ($intent['amount_received'] ?? 0);
        $amount = (int) ($intent['amount'] ?? -1);
        $intentId = (string) $intent['id'];
        $status = (string) $intent['status'];

        $mismatches = [];
        if (!hash_equals((string) $payment->provider_reference, $intentId)) $mismatches[] = 'provider_reference';
        if ($amount !== (int) $payment->amount_minor) $mismatches[] = 'amount';
        if ($currency !== strtolower((string) $payment->currency)) $mismatches[] = 'currency';
        if ($providerOrderId === '' || !hash_equals((string) $order->id, $providerOrderId)) $mismatches[] = 'order_identity';
        if (!in_array($status, ['requires_payment_method', 'requires_confirmation', 'requires_action', 'processing', 'requires_capture', 'canceled', 'succeeded'], true)) {
            $mismatches[] = 'unknown_provider_status';
        }
        if ($status === 'succeeded' && $received !== (int) $payment->amount_minor) $mismatches[] = 'amount_received';

        $charge = $intent['latest_charge'] ?? null;
        $providerRefunded = is_array($charge) ? (int) ($charge['amount_refunded'] ?? 0) : null;
        if ($status === 'succeeded' && !is_array($charge)) $mismatches[] = 'charge_snapshot_unavailable';
        $internalRefunded = (int) OrderAdjustment::query()->where('order_id', $order->id)
            ->where('type', OrderAdjustment::TYPE_REFUND)->sum('amount_minor');
        if ($providerRefunded !== null && $providerRefunded !== $internalRefunded) $mismatches[] = 'refund_total';

        $providerCaptureId = is_array($charge) ? (string) ($charge['id'] ?? '') : null;

        return DB::transaction(function () use ($payment, $order, $status, $received, $mismatches, $intentId, $providerRefunded, $providerCaptureId): string {
            $lockedOrder = Order::withoutGlobalScopes()->lockForUpdate()->findOrFail($order->id);
            $lockedPayment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            $note = $mismatches === [] ? null : 'Mismatch: ' . implode(',', $mismatches);
            $fields = ['last_reconciled_at' => now(), 'reconciliation_status' => $mismatches === [] ? 'matched' : 'mismatch',
                'reconciliation_note' => $note];

            if ($status === 'succeeded') {
                $fields += ['status' => Payment::SUCCEEDED, 'amount_received_minor' => $received,
                    'provider_capture_id' => $providerCaptureId ?: $lockedPayment->provider_capture_id,
                    'succeeded_at' => $lockedPayment->succeeded_at ?? now()];
            } elseif ($status === 'processing' || $status === 'requires_capture') {
                $fields['status'] = Payment::PROCESSING;
            } elseif ($status === 'canceled') {
                $fields['status'] = Payment::CANCELLED;
            } elseif ($status === 'requires_payment_method') {
                $fields['status'] = Payment::FAILED;
                $fields['failure_code'] = 'provider_requires_new_payment_method';
                $fields['failure_message'] = 'Provider reconciliation found an unsuccessful payment attempt.';
            } elseif (in_array($status, ['requires_confirmation', 'requires_action'], true)) {
                $fields['status'] = Payment::PROCESSING;
            }
            $lockedPayment->forceFill($fields)->save();

            if ($mismatches === [] && $status === 'succeeded') {
                $successfulTotal = (int) Payment::query()->where('order_id', $lockedOrder->id)
                    ->where('status', Payment::SUCCEEDED)->sum('amount_received_minor');
                if ($successfulTotal > (int) $lockedOrder->total_minor) {
                    $lockedPayment->forceFill(['reconciliation_status' => 'mismatch',
                        'reconciliation_note' => 'Mismatch: total_successful_payments_exceed_order'])->save();
                    $mismatches[] = 'total_successful_payments_exceed_order';
                } else {
                    $lockedOrder->amount_paid_minor = $successfulTotal;
                    $lockedOrder->status = $successfulTotal >= (int) $lockedOrder->total_minor ? 'paid' : 'partially_paid';
                    $lockedOrder->verified_at = now();
                    $lockedOrder->verified_by = 'stripe_reconciliation';
                    $lockedOrder->save();
                }
            }

            AuditLogService::log(action: 'payment_reconciliation_' . ($mismatches === [] ? 'matched' : 'mismatch'),
                targetEntity: 'payments', targetId: (string) $lockedPayment->id,
                details: $mismatches === [] ? "Stripe payment {$intentId} reconciled with the internal order." : 'Stripe reconciliation mismatch: ' . implode(',', $mismatches),
                organizerId: $lockedPayment->organizer_id, eventId: $lockedOrder->event_id);

            if ($mismatches !== []) {
                Log::critical('payments.reconciliation.mismatch', ['payment_id' => $lockedPayment->id,
                    'order_id' => $lockedOrder->id, 'provider_reference' => $intentId, 'mismatches' => $mismatches,
                    'provider_refunded_minor' => $providerRefunded]);
                if (app()->environment('production')) {
                    Log::channel('slack')->critical('payments.reconciliation.mismatch', [
                        'payment_id' => $lockedPayment->id, 'order_id' => $lockedOrder->id, 'mismatches' => $mismatches]);
                }
                return 'mismatches';
            }
            return 'applied';
        }, 3);
    }
}
