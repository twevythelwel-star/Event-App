<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\HandoverAuditLog;
use App\Models\Order;
use App\Models\SecurityAuditLog;
use App\Models\User;
use App\Services\AuditLogService;
use App\Support\AdminAuthorization as Admin;
use App\Support\PaymentStatusPresenter as Status;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    private const PRESETS = [7, 30, 90];

    public function index(Request $request): View
    {
        Gate::authorize(Admin::REPORTS_VIEW);
        $admin = $request->user();
        [$from, $to, $filters] = $this->period($request);

        $orders = $this->ordersInPeriod($admin, $from, $to, $filters['event'] ?? null);
        $financial = Gate::allows(Admin::REPORTS_FINANCIAL);

        $byGroup = collect(Status::GROUPS)->map(function (array $group) use ($orders, $financial) {
            $rows = (clone $orders)->whereIn('status', $group['statuses']);

            return [
                'label' => $group['label'],
                'count' => (clone $rows)->count(),
                'amounts' => $financial
                    ? (clone $rows)->selectRaw('currency, SUM(total_minor) as total, SUM(amount_paid_minor) as paid')->groupBy('currency')->get()
                    : collect(),
            ];
        });

        $eventScope = Admin::applyOrganizerScope(Event::allTenants(), $admin);

        return view('admin.reports.index', [
            'filters' => $filters,
            'from' => $from,
            'to' => $to,
            'presets' => self::PRESETS,
            'events' => (clone $eventScope)->orderBy('title')->get(['id', 'title']),
            'eventStatus' => (clone $eventScope)->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status'),
            'ordersTotal' => (clone $orders)->count(),
            'byGroup' => $byGroup,
            'financial' => $financial,
            'refunds' => $financial
                ? (clone $orders)->where('refunded_minor', '>', 0)->selectRaw('currency, COUNT(*) as orders, SUM(refunded_minor) as total')->groupBy('currency')->get()
                : collect(),
            'redemptions' => $this->redemptions($admin, $from, $to, $filters['event'] ?? null),
            'staffActivity' => $this->staffActivity($admin, $from, $to, $filters['event'] ?? null),
            'canExport' => Gate::allows(Admin::REPORTS_EXPORT),
        ]);
    }

    /**
     * Per-event aggregate export. No customer names, phone numbers or emails leave the platform this way.
     */
    public function export(Request $request): StreamedResponse
    {
        Gate::authorize(Admin::REPORTS_EXPORT);
        $admin = $request->user();
        [$from, $to, $filters] = $this->period($request);

        $orders = $this->ordersInPeriod($admin, $from, $to, $filters['event'] ?? null);
        $rows = (clone $orders)
            ->selectRaw("event_id, currency, COUNT(*) as orders,
                SUM(CASE WHEN status IN ('paid','partially_paid') THEN 1 ELSE 0 END) as verified,
                SUM(CASE WHEN status IN ('payment_submitted','under_verification') THEN 1 ELSE 0 END) as submitted,
                SUM(CASE WHEN status = 'awaiting_payment' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
                SUM(CASE WHEN status = 'refunded' THEN 1 ELSE 0 END) as refunded,
                SUM(CASE WHEN status IN ('paid','partially_paid') THEN amount_paid_minor ELSE 0 END) as collected,
                SUM(refunded_minor) as refunded_money")
            ->groupBy('event_id', 'currency')
            ->get();

        $titles = Event::allTenants()->whereIn('id', $rows->pluck('event_id')->filter())->pluck('title', 'id');

        AuditLogService::log(
            action: 'report_exported',
            targetEntity: 'reports',
            targetId: null,
            details: "Per-event order summary exported for {$from->toDateString()} to {$to->toDateString()}" . (isset($filters['event']) ? " (event {$filters['event']})" : '') . ", {$rows->count()} rows.",
            actor: $admin,
        );

        $filename = 'event-order-summary-' . $from->toDateString() . '-to-' . $to->toDateString() . '.csv';

        return response()->streamDownload(function () use ($rows, $titles) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Event', 'Currency', 'Orders placed', 'Verified', 'Confirmation submitted', 'Pending', 'Rejected', 'Refunded', 'Collected (verified orders)', 'Refunded amount']);
            foreach ($rows as $row) {
                fputcsv($out, [
                    $this->csvSafe($titles[$row->event_id] ?? 'No event'),
                    $row->currency,
                    $row->orders,
                    $row->verified,
                    $row->submitted,
                    $row->pending,
                    $row->rejected,
                    $row->refunded,
                    // Exact decimal text from integer minor units, one currency per row; never summed across currencies.
                    \App\Support\Money::ofMinor((int) $row->collected, $row->currency)->toDecimalString(),
                    \App\Support\Money::ofMinor((int) $row->refunded_money, $row->currency)->toDecimalString(),
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store']);
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: array<string, mixed>}
     */
    private function period(Request $request): array
    {
        $filters = $request->validate([
            'days' => ['nullable', 'integer', Rule::in(self::PRESETS)],
            'from' => ['nullable', 'date', 'required_with:to'],
            'to' => ['nullable', 'date', 'after_or_equal:from', 'required_with:from'],
            'event' => ['nullable', 'string', 'max:36'],
        ]);

        if (!empty($filters['from'])) {
            $from = CarbonImmutable::parse($filters['from'])->startOfDay();
            $to = CarbonImmutable::parse($filters['to'])->endOfDay();
        } else {
            $days = (int) ($filters['days'] ?? 30);
            $filters['days'] = $days;
            $to = CarbonImmutable::now()->endOfDay();
            $from = $to->subDays($days - 1)->startOfDay();
        }

        if (!empty($filters['event'])) {
            $event = Event::allTenants()->whereKey($filters['event'])->first();
            abort_unless($event && Admin::organizerInScope($request->user(), $event->organizer_id), 403);
        }

        return [$from, $to, $filters];
    }

    private function ordersInPeriod(User $admin, CarbonImmutable $from, CarbonImmutable $to, ?string $eventId): Builder
    {
        return Admin::applyOrganizerScope(Order::allTenants(), $admin)
            ->whereBetween('created_at', [$from, $to])
            ->when($eventId, fn ($q) => $q->where('event_id', $eventId));
    }

    /** @return array{scans: int, collections: int} */
    private function redemptions(User $admin, CarbonImmutable $from, CarbonImmutable $to, ?string $eventId): array
    {
        $scans = Admin::applyAuditScope(SecurityAuditLog::query(), $admin)
            ->where('action', 'ticket_redemption_verified')
            ->whereBetween('created_at', [$from, $to])
            ->when($eventId, fn ($q) => $q->whereIn('target_id', Order::allTenants()->where('event_id', $eventId)->select('id')));

        $collections = Admin::applyOrganizerScope(HandoverAuditLog::allTenants(), $admin)
            ->where('action_type', 'collection')
            ->whereBetween('timestamp', [$from, $to])
            ->when($eventId, fn ($q) => $q->whereIn('order_id', Order::allTenants()->where('event_id', $eventId)->select('id')));

        return ['scans' => $scans->count(), 'collections' => (int) $collections->sum('quantity_collected')];
    }

    private function staffActivity(User $admin, CarbonImmutable $from, CarbonImmutable $to, ?string $eventId)
    {
        return Admin::applyOrganizerScope(HandoverAuditLog::allTenants(), $admin)
            ->whereBetween('timestamp', [$from, $to])
            ->when($eventId, fn ($q) => $q->whereIn('order_id', Order::allTenants()->where('event_id', $eventId)->select('id')))
            ->selectRaw('staff_name, station, COUNT(*) as handovers, SUM(quantity_collected) as portions')
            ->groupBy('staff_name', 'station')
            ->orderByDesc('handovers')
            ->take(20)
            ->get();
    }

    /** Stop spreadsheet formula injection through event titles. */
    private function csvSafe(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/', $value) ? "'" . $value : $value;
    }
}
