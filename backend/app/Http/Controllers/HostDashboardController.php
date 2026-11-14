<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\AccountType;
use App\Enums\TenantRole;
use App\Models\CashPayment;
use App\Models\CancellationRequest;
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
                // Kitchen prep counts what is cooked: package components, not the package lines that group them.
                if ($line->ticket_tier_id !== null || $line->category === 'Tickets' || $line->line_type === \App\Models\OrderItem::PACKAGE) {
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

        // Event performance & ticket sales chart data (strictly from real orders and order items)
        $allOrderIds = $orders->pluck('id')->all();
        $allOrderItems = $allOrderIds !== []
            ? \App\Models\OrderItem::whereIn('order_id', $allOrderIds)->get()->groupBy('order_id')
            : collect();

        $eventPerformance = [];
        $ticketSalesByTier = [];
        $totalTicketsSold = 0;

        foreach ($events as $ev) {
            $evOrders = $orders->where('event_id', $ev->id);
            $evSettledOrders = $evOrders->whereIn('status', ['paid', 'partially_refunded']);
            $evTicketsSold = 0;
            $evFoodPortions = 0;

            foreach ($evSettledOrders as $sOrder) {
                foreach (($allOrderItems->get($sOrder->id) ?? []) as $line) {
                    if ($line->ticket_tier_id !== null || $line->category === 'Tickets') {
                        $evTicketsSold += (int) $line->quantity;
                        $tierKey = $ev->id . '|' . ($line->ticket_tier_id ?: $line->name);
                        $ticketSalesByTier[$tierKey] ??= [
                            'event_title' => $ev->title,
                            'tier_name' => $line->name,
                            'sold' => 0,
                        ];
                        $ticketSalesByTier[$tierKey]['sold'] += (int) $line->quantity;
                    } elseif ($line->line_type !== \App\Models\OrderItem::PACKAGE) {
                        $evFoodPortions += (int) $line->quantity;
                    }
                }
            }

            $totalTicketsSold += $evTicketsSold;
            $evCurrency = $ev->currency ?: 'USD';
            $evConfirmedMinor = (int) $evSettledOrders->where('currency', $evCurrency)->sum('total_minor')
                + (int) $evOrders->where('currency', $evCurrency)->where('status', 'partially_paid')->sum('amount_paid_minor');

            $eventPerformance[$ev->id] = [
                'orders_count' => $evOrders->count(),
                'paid_orders_count' => $evSettledOrders->count(),
                'tickets_sold' => $evTicketsSold,
                'food_portions' => $evFoodPortions,
                'confirmed_revenue_formatted' => \App\Support\Money::formatMinor($evConfirmedMinor, $evCurrency),
            ];
        }

        // Order analytics: status breakdown & payment method distribution
        $totalOrdersCount = max(1, $orders->count());
        $orderStatusBreakdown = [
            ['label' => 'Verified / Paid', 'count' => $orders->whereIn('status', ['paid', 'partially_refunded'])->count(), 'tone' => 'emerald'],
            ['label' => 'Awaiting verification', 'count' => $orders->whereIn('status', ['payment_submitted', 'under_verification'])->count(), 'tone' => 'amber'],
            ['label' => 'Pending payment / Partial', 'count' => $orders->whereIn('status', ['pending', 'awaiting_payment', 'partially_paid'])->count(), 'tone' => 'sky'],
            ['label' => 'Refunded / Cancelled / Rejected', 'count' => $orders->whereIn('status', ['refunded', 'cancelled', 'rejected', 'expired'])->count(), 'tone' => 'neutral'],
        ];
        foreach ($orderStatusBreakdown as &$sRow) {
            $sRow['percent'] = $orders->isEmpty() ? 0 : (int) round(($sRow['count'] / $totalOrdersCount) * 100);
        }
        unset($sRow);

        $paymentMethodBreakdown = $orders
            ->groupBy(fn ($o) => (string) ($o->method ?: 'other'))
            ->map(fn ($group, $method) => [
                'method' => $method,
                'label' => \App\Support\PaymentStatusPresenter::methodLabel($method),
                'count' => $group->count(),
                'percent' => (int) round(($group->count() / $totalOrdersCount) * 100),
            ])
            ->values()
            ->all();

        // USD financial metrics for U.S. operations
        $usd = $orders->where('currency', 'USD');
        $usdPaid = $paidOrders->where('currency', 'USD');
        $usdMajor = fn (int $minor) => \App\Support\Money::ofMinor($minor, 'USD')->toMajorNumber();

        // Gross sales (total orders regardless of status)
        $grossVolumeUSD = $usdMajor((int) $usd->sum('total_minor'));

        // Verified/paid orders (status = paid or confirmed)
        $verifiedPaymentsUSD = $usdMajor((int) $usdPaid->sum('total_minor'));

        // Confirmed payments including partially paid
        $confirmedPaymentsUSD = $usdMajor(
            (int) $usdPaid->sum('total_minor') +
            (int) $usd->where('status', 'partially_paid')->sum('amount_paid_minor')
        );

        // Pending payments (awaiting payment, payment_submitted, under_verification)
        // Do NOT count pending manual-transfer orders as paid revenue
        $pendingPaymentsUSD = $usdMajor(
            (int) $usd->whereIn('status', ['payment_submitted', 'under_verification', 'pending_verification', 'awaiting_payment'])->sum('total_minor') +
            (int) $usd->where('status', 'partially_paid')->sum(fn ($o) => max(0, (int) $o->total_minor - (int) $o->amount_paid_minor))
        );

        // Forecast revenue (confirmed + pending)
        $forecastRevenueUSD = $confirmedPaymentsUSD + $pendingPaymentsUSD;

        // Refunds
        $refundsTotalUSD = $usdMajor((int) $usd->where('status', 'refunded')->sum('total_minor'));

        // Cash received
        $cashReceivedUSD = $usdMajor((int) $cashRecords->where('currency', 'USD')->sum('amount_minor'));

        // Settled funds (confirmed - refunds)
        $settledFundsUSD = max(0, $confirmedPaymentsUSD - $refundsTotalUSD);

        // Fees and taxes (from order_charges; its kinds are platform_fee, host_fee, tax and discount)
        $usdOrderIds = $usd->pluck('id');
        $feesUSD = $usdMajor((int) \App\Models\OrderCharge::whereIn('order_id', $usdOrderIds)
            ->where('currency', 'USD')
            ->whereIn('kind', ['platform_fee', 'host_fee'])
            ->sum('amount_minor'));

        $taxesUSD = $usdMajor((int) \App\Models\OrderCharge::whereIn('order_id', $usdOrderIds)
            ->where('currency', 'USD')
            ->where('kind', 'tax')
            ->sum('amount_minor'));

        // Net amount (gross - fees - taxes - refunds)
        $netUSD = max(0, $grossVolumeUSD - $feesUSD - $taxesUSD - $refundsTotalUSD);

        // Pending manual-transfer count
        $pendingTransfersCount = $orders->whereIn('status', ['payment_submitted', 'under_verification'])->count();

        // Legacy JMD support
        $jmd = $orders->where('currency', 'JMD');
        $jmdPaid = $paidOrders->where('currency', 'JMD');
        $jmdMajor = fn (int $minor) => \App\Support\Money::ofMinor($minor, 'JMD')->toMajorNumber();
        $grossVolumeJMD = $jmdMajor((int) $jmd->sum('total_minor'));
        $verifiedPaymentsJMD = $jmdMajor((int) $jmdPaid->sum('total_minor'));
        $confirmedPaymentsJMD = $jmdMajor((int) $jmdPaid->sum('total_minor') + (int) $jmd->where('status', 'partially_paid')->sum('amount_paid_minor'));
        $pendingPaymentsJMD = $jmdMajor(
            (int) $jmd->whereIn('status', ['payment_submitted', 'under_verification', 'pending_verification', 'awaiting_payment'])->sum('total_minor')
            + (int) $jmd->where('status', 'partially_paid')->sum(fn ($o) => max(0, (int) $o->total_minor - (int) $o->amount_paid_minor))
        );
        $forecastRevenueJMD = $confirmedPaymentsJMD + $pendingPaymentsJMD;
        $refundsTotalJMD = $jmdMajor((int) $jmd->where('status', 'refunded')->sum('total_minor'));
        $cashReceivedJMD = $jmdMajor((int) $cashRecords->where('currency', 'JMD')->sum('amount_minor'));
        $settledFundsJMD = max(0, $confirmedPaymentsJMD - $refundsTotalJMD);

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
            // USD Financial Metrics
            'gross_volume_usd' => $grossVolumeUSD,
            'verified_payments_usd' => $verifiedPaymentsUSD,
            'confirmed_payments_usd' => $confirmedPaymentsUSD,
            'pending_payments_usd' => $pendingPaymentsUSD,
            'forecast_revenue_usd' => $forecastRevenueUSD,
            'refunds_total_usd' => $refundsTotalUSD,
            'cash_received_usd' => $cashReceivedUSD,
            'settled_funds_usd' => $settledFundsUSD,
            'fees_usd' => $feesUSD,
            'taxes_usd' => $taxesUSD,
            'net_usd' => $netUSD,
            // Legacy JMD Financial Metrics
            'cash_receipts_total' => $cashReceivedJMD,
            'gross_volume_jmd' => $grossVolumeJMD,
            'verified_payments_jmd' => $verifiedPaymentsJMD,
            'confirmed_payments_jmd' => $confirmedPaymentsJMD,
            'pending_payments_jmd' => $pendingPaymentsJMD,
            'forecast_revenue_jmd' => $forecastRevenueJMD,
            'refunds_total_jmd' => $refundsTotalJMD,
            'settled_funds_jmd' => $settledFundsJMD,
            // Food and Staff
            'sold_portions' => $totalSoldPortions,
            'prep_with_buffer' => $prepWithBuffer,
            'buffer_percent' => $bufferPercent,
            'prep_items' => $prepItems,
            'active_workers_count' => $workers->where('is_active', true)->count(),
            'events' => $events,
            'recent_orders' => $orders->take(10),
            'review_orders' => $reviewOrders,
            'cancellation_requests' => CancellationRequest::with(['order.event'])->whereIn('order_id', $orders->pluck('id'))
                ->whereIn('status', ['pending', 'approved', 'partially_refunded'])->latest('requested_at')->limit(100)->get(),
            'workers' => $workers,
            'total_tickets_sold' => $totalTicketsSold,
            'event_performance' => $eventPerformance,
            'ticket_sales_by_tier' => array_values($ticketSalesByTier),
            'order_status_breakdown' => $orderStatusBreakdown,
            'payment_method_breakdown' => $paymentMethodBreakdown,
        ];

        // Compliance and verification summary for the host's own account (the full page is host.compliance).
        $data['compliance'] = $user->hasRole(AccountType::EVENT_HOST)
            ? app(\App\Services\Compliance\HostComplianceOverview::class)->for($user)
            : null;

        if ($request->wantsJson()) {
            return response()->json(array_diff_key($data, ['compliance' => 1]) + ['compliance' => $data['compliance'] ? [
                'account' => $data['compliance']['account'],
                'identity_status' => $data['compliance']['identity']['state'],
                'events_needing_information' => count($data['compliance']['events_needing_information']),
                'outstanding_acknowledgements' => count($data['compliance']['acknowledgements']),
                'upcoming_expirations' => count($data['compliance']['expiring']),
            ] : null]);
        }

        return view('dashboards.host', $data);
    }

    /**
     * Record in-person cash receipt.
     */
    public function reviewCancellationRequest(Request $request, string $cancellationId): RedirectResponse
    {
        $data = $request->validate(['decision' => ['required', 'in:approved,rejected'], 'reason' => ['required', 'string', 'max:500']]);
        try {
            app(\App\Services\CancellationService::class)->review($request->user(), CancellationRequest::findOrFail($cancellationId), $data['decision'], $data['reason']);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['cancellation' => $e->getMessage()]);
        }
        return back()->with('success', 'Cancellation request reviewed.');
    }

    public function recordCancellationRefund(Request $request, string $cancellationId): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'], 'reason' => ['required', 'string', 'max:500'],
            'refund_reference' => ['required', 'string', 'max:128'],
        ]);
        $cancellation = CancellationRequest::findOrFail($cancellationId);
        abort_unless(in_array($cancellation->status, ['approved', 'partially_refunded'], true), 422);
        try {
            app(\App\Services\CashLedgerService::class)->refundOrder($request->user(), $cancellation->order_id,
                (string) $data['amount'], $data['reason'], $data['refund_reference']);
        } catch (\InvalidArgumentException|\App\Exceptions\SecurityException $e) {
            return back()->withErrors(['refund' => $e->getMessage()]);
        }
        return back()->with('success', 'Completed manual refund recorded.');
    }

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
