<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Order;
use App\Models\SecurityAuditLog;
use App\Models\User;
use App\Support\AdminAuthorization as Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class OverviewController extends Controller
{
    /** Reporting window for the "recent" figures on the overview. */
    private const PERIOD_DAYS = 30;

    public function index(Request $request): View
    {
        $admin = $request->user();
        $since = now()->subDays(self::PERIOD_DAYS)->startOfDay();
        $metrics = [];

        $canSeeUsers = Gate::allows(Admin::USERS_VIEW);
        $canSeeEvents = Gate::allows(Admin::EVENTS_VIEW);
        $canSeePayments = Gate::allows(Admin::PAYMENTS_VIEW);
        $canSeeAudit = Gate::allows(Admin::AUDIT_VIEW);
        $canSeeReports = Gate::allows(Admin::REPORTS_VIEW);
        $canManageSettings = Gate::allows(Admin::SETTINGS_MANAGE);

        $recentUsers = collect();
        if ($canSeeUsers) {
            $users = Admin::applyUserScope(User::query(), $admin);
            $metrics[] = [
                'label' => 'Registered users',
                'value' => number_format((clone $users)->count()),
                'note' => number_format((clone $users)->where('created_at', '>=', $since)->count()) . ' joined in the last ' . self::PERIOD_DAYS . ' days',
                'definition' => 'All user accounts that are not deleted' . (Admin::organizerScope($admin) === null ? '.' : ', limited to people linked to your organizers.'),
                'href' => route('admin.console.users.index'),
            ];
            $recentUsers = (clone $users)->latest('created_at')->take(5)->get();
        }

        $recentEvents = collect();
        if ($canSeeEvents) {
            $events = Admin::applyOrganizerScope(Event::allTenants(), $admin);
            $metrics[] = [
                'label' => 'Published events',
                'value' => number_format((clone $events)->where('status', 'published')->count()),
                'note' => number_format((clone $events)->where('status', 'published')->where('is_ordering_open', true)->count()) . ' taking orders now',
                'definition' => 'Events with status "published". "Taking orders" means ordering is switched on.',
                'href' => route('admin.console.events.index'),
            ];
            $recentEvents = (clone $events)->latest('created_at')->take(5)->get();
        }

        $pendingOrders = collect();
        if ($canSeePayments) {
            $orders = Admin::applyOrganizerScope(Order::allTenants(), $admin);
            $metrics[] = [
                'label' => 'Verified orders',
                'value' => number_format((clone $orders)->whereIn('status', ['paid', 'partially_paid'])->where('created_at', '>=', $since)->count()),
                'note' => 'Last ' . self::PERIOD_DAYS . ' days',
                'definition' => 'Orders placed in the last ' . self::PERIOD_DAYS . ' days whose payment is verified (paid or partially paid). Each order carries one ticket code.',
                'href' => route('admin.console.orders.index'),
            ];
            $metrics[] = [
                'label' => 'Payments to review',
                'value' => number_format((clone $orders)->whereIn('status', ['payment_submitted', 'under_verification'])->count()),
                'note' => 'Confirmation submitted, not yet verified',
                'definition' => 'Orders in "payment submitted" or "under verification", at any date.',
                'href' => route('admin.console.orders.index', ['status' => 'submitted']),
            ];

            $revenue = (clone $orders)
                ->whereIn('status', ['paid', 'partially_paid'])
                ->where('created_at', '>=', $since)
                ->selectRaw('currency, SUM(amount_paid_minor) as collected')
                ->groupBy('currency')
                ->pluck('collected', 'currency');

            $pendingOrders = (clone $orders)
                ->whereIn('status', ['payment_submitted', 'under_verification'])
                ->latest('created_at')
                ->take(5)
                ->get();
        }

        $recentActivity = collect();
        if ($canSeeAudit) {
            $recentActivity = Admin::applyAuditScope(SecurityAuditLog::query(), $admin)->with('user:id,name')->latest('created_at')->take(8)->get();
        }

        return view('admin.overview', [
            'metrics' => $metrics,
            'revenue' => $revenue ?? collect(),
            'periodDays' => self::PERIOD_DAYS,
            'recentUsers' => $recentUsers,
            'recentEvents' => $recentEvents,
            'pendingOrders' => $pendingOrders,
            'recentActivity' => $recentActivity,
            'canSeeUsers' => $canSeeUsers,
            'canSeeEvents' => $canSeeEvents,
            'canSeePayments' => $canSeePayments,
            'canSeeAudit' => $canSeeAudit,
            'canSeeReports' => $canSeeReports,
            'canManageSettings' => $canManageSettings,
            'isSystemAdmin' => Admin::isSystemAdmin($admin),
            'hasAnyCapability' => $metrics !== [] || $canSeeAudit || $canSeeReports,
        ]);
    }
}
