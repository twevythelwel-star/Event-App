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

        if (Gate::allows(Admin::USERS_VIEW)) {
            $users = Admin::applyUserScope(User::query(), $admin);
            $metrics[] = [
                'label' => 'Registered users',
                'value' => number_format((clone $users)->count()),
                'note' => number_format((clone $users)->where('created_at', '>=', $since)->count()) . ' joined in the last ' . self::PERIOD_DAYS . ' days',
                'definition' => 'All user accounts that are not deleted' . (Admin::organizerScope($admin) === null ? '.' : ', limited to people linked to your organizers.'),
            ];
        }

        if (Gate::allows(Admin::EVENTS_VIEW)) {
            $events = Admin::applyOrganizerScope(Event::allTenants(), $admin);
            $metrics[] = [
                'label' => 'Published events',
                'value' => number_format((clone $events)->where('status', 'published')->count()),
                'note' => number_format((clone $events)->where('status', 'published')->where('is_ordering_open', true)->count()) . ' taking orders now',
                'definition' => 'Events with status "published". "Taking orders" means ordering is switched on.',
            ];
        }

        $paymentsVisible = Gate::allows(Admin::PAYMENTS_VIEW);
        if ($paymentsVisible) {
            $orders = Admin::applyOrganizerScope(Order::allTenants(), $admin);
            $metrics[] = [
                'label' => 'Verified orders',
                'value' => number_format((clone $orders)->whereIn('status', ['paid', 'partially_paid'])->where('created_at', '>=', $since)->count()),
                'note' => 'Last ' . self::PERIOD_DAYS . ' days',
                'definition' => 'Orders placed in the last ' . self::PERIOD_DAYS . ' days whose payment is verified (paid or partially paid). Each order carries one ticket code.',
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
        }

        $recentActivity = collect();
        if (Gate::allows(Admin::AUDIT_VIEW)) {
            $recentActivity = Admin::applyAuditScope(SecurityAuditLog::query(), $admin)->with('user:id,name')->latest('created_at')->take(8)->get();
        }

        return view('admin.overview', [
            'metrics' => $metrics,
            'revenue' => $revenue ?? collect(),
            'periodDays' => self::PERIOD_DAYS,
            'recentActivity' => $recentActivity,
            'canSeeAudit' => Gate::allows(Admin::AUDIT_VIEW),
            'hasAnyCapability' => $metrics !== [] || Gate::allows(Admin::AUDIT_VIEW) || Gate::allows(Admin::REPORTS_VIEW),
        ]);
    }
}
