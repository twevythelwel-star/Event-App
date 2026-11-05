<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\PaymentWebhookEvent;
use App\Models\SystemPlatformSetting;
use App\Payments\PaymentMethods;
use App\Services\AuditLogService;
use App\Support\AdminAuthorization as Admin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Payment processors for the whole platform. Credentials are never stored in the database: they come from the
 * environment (config/payments.php). Administrators only approve or withdraw a configured processor, recorded on
 * the platform settings row (payment_processors) and in the audit log.
 */
class PaymentConfigurationController extends Controller
{
    public function index(): JsonResponse
    {
        Gate::authorize(Admin::SETTINGS_MANAGE);
        $approvals = PaymentMethods::approvals();

        return response()->json([
            'processors' => collect(PaymentMethods::APPROVALS)->map(fn (string $label, string $key) => [
                'key' => $key,
                'label' => $label,
                'configured' => PaymentMethods::processorConfigured($key === 'stripe_cash_app_pay' ? 'stripe' : $key),
                'approved' => $approvals[$key],
            ])->values(),
            'credentials' => 'Set in the server environment (STRIPE_* and PAYPAL_* variables), never through this screen.',
        ]);
    }

    public function approveProcessor(Request $request, string $processor): JsonResponse
    {
        return $this->setApproval($request, $processor, true);
    }

    public function disableProcessor(Request $request, string $processor): JsonResponse
    {
        return $this->setApproval($request, $processor, false);
    }

    /** Recent processor webhooks, per provider: what arrived and what the platform did with it. */
    public function webhookHealth(): JsonResponse
    {
        Gate::authorize(Admin::SETTINGS_MANAGE);

        $health = [];
        foreach (['stripe', 'paypal'] as $provider) {
            $recent = PaymentWebhookEvent::where('provider', $provider)->latest('processed_at')->take(20)->get();
            $health[$provider] = [
                'configured' => PaymentMethods::processorConfigured($provider),
                'last_received_at' => optional($recent->first()?->processed_at)->toIso8601String(),
                'recent' => $recent->countBy('result'),
            ];
        }

        return response()->json(['webhook_health' => $health]);
    }

    /** The latest processor payments (cards, PayPal, Cash App Pay), from the payments ledger. */
    public function transactionLogs(): JsonResponse
    {
        Gate::authorize(Admin::SETTINGS_MANAGE);

        $payments = Payment::query()->latest()->take(50)
            ->get(['id', 'order_id', 'provider', 'method', 'status', 'amount_minor', 'currency', 'provider_reference', 'created_at']);

        return response()->json(['transactions' => $payments]);
    }

    private function setApproval(Request $request, string $processor, bool $approved): JsonResponse
    {
        Gate::authorize(Admin::SETTINGS_MANAGE);
        abort_unless(array_key_exists($processor, PaymentMethods::APPROVALS), 404, 'Unknown payment processor.');

        $settings = SystemPlatformSetting::firstOrCreate([]);
        $stored = (array) ($settings->payment_processors ?? []);
        $before = (bool) ($stored[$processor] ?? false);
        $stored[$processor] = $approved;
        $settings->payment_processors = $stored;
        $settings->save();

        if ($before !== $approved) {
            AuditLogService::log(
                action: $approved ? 'payment_processor_approved' : 'payment_processor_withdrawn',
                targetEntity: 'system_platform_settings',
                targetId: (string) $settings->id,
                details: PaymentMethods::APPROVALS[$processor] . ($approved ? ' approved' : ' withdrawn') . " by {$request->user()->email}.",
                actor: $request->user(),
            );
        }

        return response()->json([
            'success' => true,
            'processor' => $processor,
            'approved' => $approved,
            'configured' => PaymentMethods::processorConfigured($processor === 'stripe_cash_app_pay' ? 'stripe' : $processor),
        ]);
    }
}
