<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SecurityAuditLog;
use App\Support\AdminAuthorization as Admin;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Scanner & gate fraud monitoring: aggregates the append-only security audit trail into
 * investigable signals. Read-only — it never accuses; it shows counts and context so
 * authorized staff can decide. Sensitive audit detail stays behind AUDIT_VIEW.
 */
class FraudMonitorController extends Controller
{
    private const FRAUD_ACTIONS = [
        'ticket_redemption_rejected',
        'ticket_redemption_verified',
        'ticket_token_reissued',
        'ticket_token_revoked',
        'food_handover_rejected',
    ];

    public function index(): View
    {
        Gate::authorize(Admin::AUDIT_VIEW);

        $since24h = now()->subDay();
        $since7d = now()->subDays(7);

        $base = SecurityAuditLog::whereIn('action', self::FRAUD_ACTIONS);

        // Rejection signals grouped by actor + event: repeated invalid/revoked/wrong-event scans
        // and over-quantity food attempts all land here.
        $actors24h = (clone $base)->where('created_at', '>=', $since24h)
            ->whereIn('action', ['ticket_redemption_rejected', 'food_handover_rejected'])
            ->selectRaw('user_id, event_id, ip_address, count(*) as rejections')
            ->groupBy('user_id', 'event_id', 'ip_address')
            ->orderByDesc('rejections')
            ->limit(25)
            ->get();

        // Action mix over the last 7 days so spikes are visible at a glance.
        $actionMix = (clone $base)->where('created_at', '>=', $since7d)
            ->selectRaw('action, outcome, count(*) as total')
            ->groupBy('action', 'outcome')
            ->orderByDesc('total')
            ->get();

        // Latest rejection records with full context for drill-down.
        $recentRejections = (clone $base)->where('created_at', '>=', $since7d)
            ->whereIn('action', ['ticket_redemption_rejected', 'food_handover_rejected'])
            ->with('user:id,name,email')
            ->latest('created_at')
            ->limit(50)
            ->get();

        $suspiciousActors = $actors24h->filter(fn ($r) => (int) $r->rejections >= 5)->values();

        return view('admin.fraud-monitor.index', [
            'actors24h' => $actors24h,
            'actionMix' => $actionMix,
            'recentRejections' => $recentRejections,
            'suspiciousActors' => $suspiciousActors,
        ]);
    }
}
