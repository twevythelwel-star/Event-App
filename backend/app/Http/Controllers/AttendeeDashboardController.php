<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\User;
use App\Services\AccountSettingsService;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AttendeeDashboardController extends Controller
{
    /**
     * Attendee Dashboard Overview: Orders, Digital Tickets, and Receipts.
     * Invariant: Attendees can only access orders matching their own verified email or phone.
     */
    public function index(Request $request): JsonResponse|View
    {
        $user = $request->user();

        // Scope orders strictly to this attendee: their account, or their verified email / phone.
        // The user_id condition always applies, so the filter can never be empty.
        $orders = Order::withoutGlobalScopes()
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id);
                if (!empty($user->email)) {
                    $q->orWhere('email', $user->email);
                }
                if (!empty($user->phone)) {
                    $q->orWhere('customer_phone', $user->phone);
                }
            })
            ->with(['items', 'event' => fn ($q) => $q->withoutGlobalScopes()->with(['mediaItems', 'menuItems'])])
            ->orderByDesc('created_at')
            ->get();

        $displayCurrency = $user->preferred_display_currency ?? 'USD';

        $ordersWithEstimates = $orders->map(function ($order) use ($displayCurrency) {
            $estimate = AccountSettingsService::formatDisplayEstimate($order, $displayCurrency);
            return [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'ticket_code' => $order->ticket_code,
                'status' => $order->status,
                'fulfillment_status' => $order->fulfillment_status,
                'currency' => $order->currency,
                'total_amount' => $order->total_amount,
                'display_estimate' => $estimate['display_formatted_estimate'],
                'created_at' => $order->created_at,
                'all_handed_over' => $order->all_handed_over,
                'cancellation' => app(\App\Services\CancellationService::class)->statusFor($order),
                'items' => $order->items ?? [],
            ];
        });

        $activeClaimCode = \App\Models\StaffClaimCode::where('user_id', $user->id)
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        $pendingAssignments = \App\Models\EventWorkerAssignment::with(['event', 'assignedBy'])
            ->where('user_id', $user->id)
            ->where('status', 'pending_acceptance')
            ->latest()
            ->get();

        $myStaffAssignments = \App\Models\EventWorkerAssignment::with(['event', 'assignedBy'])
            ->where('user_id', $user->id)
            ->whereIn('status', ['active', 'scheduled', 'expired', 'revoked'])
            ->latest()
            ->get();

        $hostApplication = \App\Models\HostApplication::where('user_id', $user->id)
            ->latest()
            ->first();

        // Upcoming events from the attendee's active orders, plus featured upcoming published events
        $activeOrders = $orders->reject(fn (Order $o) => in_array($o->status, ['refunded', 'cancelled', 'rejected', 'expired'], true));
        $upcomingBookedEvents = $activeOrders
            ->map(fn (Order $o) => $o->event)
            ->filter()
            ->unique('id')
            ->values();

        // Discovery follows the same rules as public event browsing: published, public, not ended,
        // soonest first — never drafts, private or past events.
        $upcomingDiscoverEvents = \App\Models\Event::allTenants()
            ->where('status', 'published')
            ->where('visibility', 'public')
            ->where(fn ($q) => $q->whereNull('event_date_iso')->orWhereDate('event_date_iso', '>=', now()->toDateString()))
            ->whereNotIn('id', $upcomingBookedEvents->pluck('id')->all())
            ->orderByRaw('event_date_iso IS NULL')->orderBy('event_date_iso')->orderBy('start_time')
            ->take(8)
            ->get()
            ->reject(fn (\App\Models\Event $event) => \App\Support\EventListing::hasEnded($event))
            ->values();

        // Quick discovery links into the public catalog, one per event kind group.
        $discoverGroups = collect((array) config('photo_library.groups', []))
            ->map(fn ($label, $key) => ['key' => (string) $key, 'label' => (string) $label])
            ->values();

        // Advance food orders across all attendee orders (excluding ticket lines and package grouping lines)
        $foodOrderLines = collect();
        foreach ($orders as $order) {
            foreach (($order->items ?? []) as $item) {
                if ($item->category !== 'Tickets' && $item->line_type !== \App\Models\OrderItem::PACKAGE) {
                    $foodOrderLines->push([
                        'order' => $order,
                        'item' => $item,
                        'event_title' => $order->event?->title ?? 'Event',
                        'pickup_number' => $order->pickup_number,
                    ]);
                }
            }
        }

        // Recent notifications for this attendee's orders or recipient address
        $orderIds = $orders->pluck('id')->all();
        $notifications = \App\Models\NotificationLog::withoutGlobalScopes()
            ->where(function ($q) use ($user, $orderIds) {
                if ($orderIds !== []) {
                    $q->whereIn('order_id', $orderIds);
                }
                if (!empty($user->email)) {
                    $q->orWhere('recipient', $user->email);
                }
                if (!empty($user->phone)) {
                    $q->orWhere('recipient', $user->phone);
                }
            })
            ->latest('created_at')
            ->take(8)
            ->get();

        // Profile completion checklist
        $profileChecks = [
            [
                'label' => 'Full name on pass',
                'done' => !empty(trim((string) ($user->name ?? ''))),
                'detail' => $user->name ?: 'Add your name in Account Settings',
                'href' => route('settings.show'),
            ],
            [
                'label' => 'Email address verified',
                'done' => !empty($user->email_verified_at) || !empty($user->email),
                'detail' => $user->email ?: 'Verify your email address',
                'href' => route('settings.show'),
            ],
            [
                'label' => 'Mobile phone for SMS alerts',
                'done' => !empty(trim((string) ($user->phone ?? ''))),
                'detail' => $user->phone ?: 'Add a mobile number for gate & food alerts',
                'href' => route('settings.show'),
            ],
            [
                'label' => 'Preferred display currency',
                'done' => !empty($user->preferred_display_currency),
                'detail' => $displayCurrency,
                'href' => route('settings.show') . '#currency',
            ],
            [
                'label' => 'Platform terms acknowledged',
                'done' => !empty($user->agreed_to_terms_at) || $user->status === 'active',
                'detail' => 'Active & compliant',
                'href' => route('legal.index'),
            ],
        ];
        $completedProfileCount = collect($profileChecks)->where('done', true)->count();
        $profileCompletionPercent = (int) round(($completedProfileCount / count($profileChecks)) * 100);

        $data = [
            'dashboard' => 'Attendee Digital Ticket Wallet',
            'attendee_name' => $user->name,
            'preferred_display_currency' => $displayCurrency,
            'total_orders_count' => $orders->count(),
            'orders' => $orders,
            'ordersWithEstimates' => $ordersWithEstimates,
            'activeClaimCode' => $activeClaimCode,
            'pendingAssignments' => $pendingAssignments,
            'myStaffAssignments' => $myStaffAssignments,
            'hostApplication' => $hostApplication,
            'upcomingBookedEvents' => $upcomingBookedEvents,
            'upcomingDiscoverEvents' => $upcomingDiscoverEvents,
            'discoverGroups' => $discoverGroups,
            'foodOrderLines' => $foodOrderLines,
            'notifications' => $notifications,
            'profileChecks' => $profileChecks,
            'profileCompletionPercent' => $profileCompletionPercent,
            'user' => $user,
        ];

        if ($request->wantsJson()) {
            return response()->json($data);
        }

        return view('dashboards.attendee', $data);
    }

    /**
     * Retrieve digital ticket using order number and secret ticket code.
     * Enforces IDOR protection: Attendees can only retrieve tickets they own.
     */
    public function retrieveTicket(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_number' => ['required', 'string'],
            'ticket_code' => ['required', 'string'],
        ]);

        $order = Order::where('order_number', $validated['order_number'])
            ->where('ticket_code', $validated['ticket_code'])
            ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid ticket credentials. Please verify your order number and ticket code.',
            ], 404);
        }

        $user = $request->user();
        if ($user) {
            $isOwner = ((int) $order->user_id === (int) $user->id)
                || (strtolower((string) $order->email) === strtolower((string) $user->email));

            if (!$isOwner && !$user->hasRole(\App\Enums\AccountType::SYSTEM_ADMIN)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Access Denied: You do not have permission to view or retrieve this ticket.',
                ], 403);
            }
        }

        return response()->json([
            'success' => true,
            'ticket' => [
                'order_number' => $order->order_number,
                'ticket_code' => $order->ticket_code,
                'customer_name' => $order->customer_name,
                'currency' => $order->currency,
                'total_amount' => $order->total_amount,
                'status' => $order->status,
                'all_handed_over' => $order->all_handed_over,
            ],
        ]);
    }

    public function requestCancellation(Request $request, string $orderId): \Illuminate\Http\RedirectResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);
        $order = Order::withoutGlobalScopes()->whereKey($orderId)->where('user_id', $request->user()->id)->firstOrFail();
        try {
            app(\App\Services\CancellationService::class)->request($request->user(), $order, $data['reason'] ?? null);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['cancellation' => $e->getMessage()]);
        }
        return back()->with('success', 'Your cancellation request has been sent to the event organizer.');
    }
}
