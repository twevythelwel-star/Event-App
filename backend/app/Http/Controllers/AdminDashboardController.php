<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\AccountType;
use App\Models\Event;
use App\Models\Order;
use App\Models\SecurityAuditLog;
use App\Services\AuditLogService;
use App\Services\CredentialMaskingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    /**
     * Delegated Administrator Dashboard Overview.
     */
    public function index(Request $request): JsonResponse|View
    {
        $user = $request->user();

        // Enforce role authorization: must be Administrator or System Admin
        if (!$user->hasRole(AccountType::ADMINISTRATOR) && !$user->hasRole(AccountType::SYSTEM_ADMIN)) {
            if ($request->wantsJson()) {
                return response()->json([
                    'error' => 'Forbidden',
                    'message' => 'Unauthorized: Administrator privileges required to access this operations desk.',
                ], 403);
            }
            return redirect()->route('attendee.dashboard')->with('error', 'Unauthorized access: Administrator privileges required.');
        }

        $permissions = $user->adminPermissions;

        // Scoped event access query
        $eventsQuery = Event::query();
        if ($permissions && $permissions->scope !== 'all') {
            $assigned = $permissions->assigned_organizer_ids ?? [];
            $eventsQuery->whereIn('organizer_id', $assigned);
        }

        $events = $eventsQuery->orderByDesc('created_at')->get();
        $eventsCount = $events->count();
        $pendingModeration = (clone $eventsQuery)->where('is_ordering_open', false)->count();

        // Customer support orders and disputes
        $supportOrdersQuery = Order::whereIn('status', ['payment_submitted', 'under_verification']);
        if ($permissions && $permissions->scope !== 'all') {
            $assigned = $permissions->assigned_organizer_ids ?? [];
            $supportOrdersQuery->whereIn('organizer_id', $assigned);
        }
        $supportOrders = $supportOrdersQuery->orderByDesc('created_at')->take(10)->get();

        $data = [
            'dashboard' => 'Delegated Administrator Desk',
            'administrator_name' => $user->name,
            'scope' => $permissions->scope ?? 'assigned_events',
            'eventsCount' => $eventsCount,
            'pendingModeration' => $pendingModeration,
            'pendingVerifications' => $supportOrders->count(),
            'events' => $events,
            'supportOrders' => $supportOrders,
            'user' => $user,
            'permissions' => [
                'can_manage_events' => $permissions->can_manage_events ?? false,
                'can_moderate_content' => $permissions->can_moderate_content ?? false,
                'can_support_orders' => $permissions->can_support_orders ?? true,
                'can_view_payments' => $permissions->can_view_payments ?? false,
                'can_view_reports' => $permissions->can_view_reports ?? false,
                'can_access_audit_logs' => $permissions->can_access_audit_logs ?? false,
            ],
        ];

        if ($request->wantsJson()) {
            return response()->json($data);
        }

        return view('dashboards.admin', $data);
    }

    /**
     * Review order dispute or ticket retrieval issue with auditable PII tracking.
     */
    public function reviewOrderSupport(Request $request, string $orderId): JsonResponse
    {
        $user = $request->user();
        $order = Order::findOrFail($orderId);

        AuditLogService::log(
            action: 'order_support_review',
            targetEntity: 'orders',
            targetId: (string) $order->id,
            details: "Administrator {$user->name} reviewed order dispute for Order #{$order->order_number}.",
            actor: $user,
            isPiiAccess: true
        );

        return response()->json([
            'success' => true,
            'order' => $order,
        ]);
    }
}
