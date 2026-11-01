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
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HostDashboardController extends Controller
{
    /**
     * Event Host Dashboard Overview: Ticketing, Kitchen Operations & Worker Oversight.
     */
    public function index(Request $request): JsonResponse|View|RedirectResponse
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

        // Kitchen portion forecasting: food portions on paid orders (ticket lines are not food), per menu item.
        $paidOrders = $orders->where('status', 'paid');
        $bufferPercent = $opsRule->food_prep_buffer_percent ?? 10;
        $eventTitles = [];
        foreach ($events as $event) {
            $eventTitles[$event->id] = $event->title;
        }
        $settledOrderEvents = [];
        foreach ($orders as $order) {
            if (in_array($order->status, ['paid', 'partially_refunded'], true)) {
                $settledOrderEvents[$order->id] = $order->event_id;
            }
        }
        $prep = [];
        if ($settledOrderEvents !== []) {
            $foodLines = \App\Models\OrderItem::whereIn('order_id', array_keys($settledOrderEvents))->get();
            foreach ($foodLines as $line) {
                if ($line->ticket_tier_id !== null || $line->category === 'Tickets') {
                    continue;
                }
                $eventId = $settledOrderEvents[$line->order_id] ?? '';
                $key = $eventId . '|' . $line->name;
                $prep[$key] ??= ['event' => $eventTitles[$eventId] ?? 'Event', 'item' => $line->name, 'category' => $line->category, 'sold' => 0, 'collected' => 0];
                $prep[$key]['sold'] += (int) $line->quantity;
                $prep[$key]['collected'] += (int) $line->collected_quantity;
            }
        }
        ksort($prep);
        $totalSoldPortions = 0;
        $remainingPortions = 0;
        foreach ($prep as $key => $row) {
            $remaining = max(0, $row['sold'] - $row['collected']);
            $prep[$key]['remaining'] = $remaining;
            $prep[$key]['prep_target'] = (int) ceil(($remaining * (100 + $bufferPercent)) / 100);
            $totalSoldPortions += $row['sold'];
            $remainingPortions += $remaining;
        }
        $prepItems = array_values($prep);

        // Manual payments waiting for the host's decision, oldest first.
        $reviewOrders = [];
        foreach ($orders as $order) {
            if (in_array($order->status, ['payment_submitted', 'under_verification'], true)) {
                $reviewOrders[] = $order;
            }
        }
        usort($reviewOrders, fn ($a, $b) => strcmp((string) $a->created_at, (string) $b->created_at));
        $prepWithBuffer = $opsRule
            ? $opsRule->calculatePrepForecast($remainingPortions)
            : (int) ceil(($remainingPortions * (100 + $bufferPercent)) / 100);

        // Money per currency from the integer *_minor columns; currencies are never added together.
        $financials = \App\Services\Pricing\RevenueSummary::byCurrency($orders, $cashRecords);

        // Legacy JSON keys (read by the React client): JMD figures in major units, computed exactly as before.
        $jmd = $orders->where('currency', 'JMD');
        $jmdPaid = $paidOrders->where('currency', 'JMD');
        $major = fn (int $minor) => \App\Support\Money::ofMinor($minor, 'JMD')->toMajorNumber();
        $grossVolumeJMD = $major((int) $jmd->sum('total_minor'));
        $verifiedPaymentsJMD = $major((int) $jmdPaid->sum('total_minor'));
        $confirmedPaymentsJMD = $major((int) $jmdPaid->sum('total_minor') + (int) $jmd->where('status', 'partially_paid')->sum('amount_paid_minor'));
        $pendingPaymentsJMD = $major(
            (int) $jmd->whereIn('status', ['payment_submitted', 'under_verification', 'pending_verification', 'awaiting_payment'])->sum('total_minor')
            + (int) $jmd->where('status', 'partially_paid')->sum(fn ($o) => max(0, (int) $o->total_minor - (int) $o->amount_paid_minor))
        );
        $forecastRevenueJMD = $confirmedPaymentsJMD + $pendingPaymentsJMD;
        $refundsTotalJMD = $major((int) $jmd->where('status', 'refunded')->sum('total_minor'));
        $cashReceivedJMD = $major((int) $cashRecords->where('currency', 'JMD')->sum('amount_minor'));
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
            'financials' => $financials,
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
            'prep_items' => $prepItems,
            'active_workers_count' => $workers->where('is_active', true)->count(),
            'events' => $events,
            'recent_orders' => $orders->take(10),
            'review_orders' => $reviewOrders,
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
            'currency' => ['required', \Illuminate\Validation\Rule::in(\App\Support\Currency::supportedCodes())],
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
