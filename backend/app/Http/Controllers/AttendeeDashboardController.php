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
            ->with(['items', 'event' => fn ($q) => $q->withoutGlobalScopes()])
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
}
