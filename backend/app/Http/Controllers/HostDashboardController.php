<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\AccountType;
use App\Enums\TenantRole;
use App\Models\CashPayment;
use App\Models\Event;
use App\Models\EventWorkerAssignment;
use App\Models\HostOperationalRule;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Organizer;
use App\Models\OrganizerUser;
use App\Services\AuditLogService;
use App\Services\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HostDashboardController extends Controller
{
    /**
     * Event Host Dashboard Overview: Ticketing, Kitchen Operations & Worker Oversight.
     */
    public function index(Request $request): JsonResponse|View
    {
        $user = $request->user();

        // Enforce role authorization: must be approved Event Host or System Admin
        if (!$user->hasRole(AccountType::EVENT_HOST) && !$user->hasRole(AccountType::SYSTEM_ADMIN)) {
            if ($request->wantsJson()) {
                return response()->json([
                    'error' => 'Forbidden',
                    'message' => 'You must be an approved Event Host to access the Host Command Center.',
                ], 403);
            }
            return redirect()->route('attendee.dashboard')->with('error', 'You must apply and be approved as an Event Host to access the Host Dashboard.');
        }

        $tenantId = TenantContext::id();

        // Enforce ownership: user must have membership on this organizer or be system admin
        $membership = null;
        if ($tenantId) {
            $membership = OrganizerUser::where('organizer_id', $tenantId)
                ->where('user_id', $user->id)
                ->where('is_active', true)
                ->first();
        }

        if ($tenantId && !$membership && !$user->hasRole(AccountType::SYSTEM_ADMIN)) {
            if ($request->wantsJson()) {
                return response()->json([
                    'error' => 'Forbidden',
                    'message' => 'You are not authorized to access this event organizer workspace.',
                ], 403);
            }
            abort(403, 'You are not authorized to access this event organizer workspace.');
        }

        // Scope every query to organizers this user belongs to. Without a tenant context, a host sees all of
        // their own organizers, never the whole platform; only a system administrator sees everything.
        if ($tenantId) {
            $organizerIds = [$tenantId];
        } elseif ($user->hasRole(AccountType::SYSTEM_ADMIN)) {
            $organizerIds = null;
        } else {
            $organizerIds = OrganizerUser::where('user_id', $user->id)
                ->where('is_active', true)
                ->pluck('organizer_id')
                ->toArray();
            if (count($organizerIds) === 1) {
                $tenantId = $organizerIds[0];
                $membership = OrganizerUser::where('organizer_id', $tenantId)->where('user_id', $user->id)->where('is_active', true)->first();
            }
        }

        $eventsQuery = Event::withoutGlobalScopes()->whereNull('deleted_at');
        $ordersQuery = Order::withoutGlobalScopes()->whereNull('deleted_at');
        $cashQuery = CashPayment::withoutGlobalScopes();
        $workersQuery = EventWorkerAssignment::withoutGlobalScopes();

        if ($organizerIds !== null) {
            $eventsQuery->whereIn('organizer_id', $organizerIds);
            $ordersQuery->whereIn('organizer_id', $organizerIds);
            $cashQuery->whereIn('organizer_id', $organizerIds);
            $workersQuery->whereIn('organizer_id', $organizerIds);
        }

        $events = $eventsQuery->orderByDesc('created_at')->get();
        $orders = $ordersQuery->orderByDesc('created_at')->get();
        $cashRecords = $cashQuery->orderByDesc('created_at')->get();
        $workers = $workersQuery->with('user')->get();
        $opsRule = $tenantId ? HostOperationalRule::where('organizer_id', $tenantId)->first() : null;

        // Kitchen portion forecasting
        $paidOrders = $orders->where('status', 'paid');
        $totalSoldPortions = $paidOrders->sum(function ($order) {
            return count($order->items ?? []);
        });

        $bufferPercent = $opsRule->food_prep_buffer_percent ?? 10;
        $prepWithBuffer = $opsRule 
            ? $opsRule->calculatePrepForecast($totalSoldPortions) 
            : (int) ceil(($totalSoldPortions * (100 + $bufferPercent)) / 100);

        // Financial aggregates (strictly distinguishing confirmed, pending, forecast, refunds, and settled funds)
        $grossVolumeJMD = $orders->where('currency', 'JMD')->sum('total_amount');
        $verifiedPaymentsJMD = $paidOrders->where('currency', 'JMD')->sum('total_amount');
        $partiallyPaidConfirmedJMD = $orders->where('currency', 'JMD')->where('status', 'partially_paid')->sum('amount_paid');
        $confirmedPaymentsJMD = $verifiedPaymentsJMD + $partiallyPaidConfirmedJMD;

        $pendingSubmittedJMD = $orders->where('currency', 'JMD')->whereIn('status', ['payment_submitted', 'under_verification', 'pending_verification', 'awaiting_payment'])->sum('total_amount');
        $partiallyPaidRemainingJMD = $orders->where('currency', 'JMD')->where('status', 'partially_paid')->sum(function ($o) {
            return max(0, (float) $o->total_amount - (float) ($o->amount_paid ?? 0));
        });
        $pendingPaymentsJMD = $pendingSubmittedJMD + $partiallyPaidRemainingJMD;

        $forecastRevenueJMD = $confirmedPaymentsJMD + $pendingPaymentsJMD;
        $refundsTotalJMD = $orders->where('currency', 'JMD')->where('status', 'refunded')->sum('total_amount');

        $cashReceivedJMD = $cashRecords->where('currency', 'JMD')->sum('amount');
        $settledFundsJMD = max(0, $confirmedPaymentsJMD - $refundsTotalJMD);
        $pendingTransfersCount = $orders->whereIn('status', ['payment_submitted', 'under_verification'])->count();

        $data = [
            'dashboard' => 'Event Host Command Center',
            'host_name' => $user->name,
            'organizer_id' => $tenantId,
            'role_on_event' => $membership->role->value ?? 'owner',
            'total_events' => $events->count(),
            'total_orders' => $orders->count(),
            'paid_orders_count' => $paidOrders->count(),
            'pending_verification_count' => $pendingTransfersCount,
            'cash_receipts_total' => $cashReceivedJMD,
            'gross_volume_jmd' => $grossVolumeJMD,
            'verified_payments_jmd' => $verifiedPaymentsJMD,
            'confirmed_payments_jmd' => $confirmedPaymentsJMD,
            'pending_payments_jmd' => $pendingPaymentsJMD,
            'forecast_revenue_jmd' => $forecastRevenueJMD,
            'refunds_total_jmd' => $refundsTotalJMD,
            'settled_funds_jmd' => $settledFundsJMD,
            'sold_portions' => $totalSoldPortions,
            'prep_with_buffer' => $prepWithBuffer,
            'buffer_percent' => $bufferPercent,
            'active_workers_count' => $workers->where('is_active', true)->count(),
            'events' => $events,
            'recent_orders' => $orders->take(10),
            'workers' => $workers,
        ];

        if ($request->wantsJson()) {
            return response()->json($data);
        }

        return view('dashboards.host', $data);
    }

    /**
     * Record in-person cash receipt.
     */
    public function recordCashReceipt(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenantId = TenantContext::id();

        $validated = $request->validate([
            'order_id' => ['required', 'exists:orders,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['required', 'in:JMD,USD'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $order = Order::where('organizer_id', $tenantId)->findOrFail($validated['order_id']);

        $receiptNumber = 'CSH-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));
        $cashRecord = CashPayment::create([
            'organizer_id' => $tenantId,
            'order_id' => $order->id,
            'receipt_number' => $receiptNumber,
            'customer_name' => $order->customer_name,
            'customer_phone' => $order->customer_phone,
            'amount' => $validated['amount'],
            'currency' => $validated['currency'],
            'received_by' => $user->name,
            'received_at' => now(),
            'notes' => $validated['notes'] ?? 'Cash payment received in full.',
        ]);

        $order->status = 'paid';
        $order->amount_paid = $validated['amount'];
        $order->verified_at = now();
        $order->verified_by = $user->name;
        $order->save();

        AuditLogService::log(
            action: 'cash_receipt_recorded',
            targetEntity: 'cash_payments',
            targetId: (string) $cashRecord->id,
            details: "Cash receipt {$receiptNumber} of {$validated['currency']} {$validated['amount']} recorded by {$user->name}.",
            actor: $user
        );

        return response()->json([
            'success' => true,
            'receipt_number' => $receiptNumber,
            'order_status' => 'paid',
        ]);
    }
}
