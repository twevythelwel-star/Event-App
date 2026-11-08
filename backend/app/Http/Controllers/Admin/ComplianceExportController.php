<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Compliance\ComplianceSafetyReport;
use App\Models\Compliance\HostPublishingSuspension;
use App\Models\Event;
use App\Models\EventComplianceEvidence;
use App\Models\HostIdentityVerification;
use App\Models\PolicyAcceptance;
use App\Models\SecurityAuditLog;
use App\Services\AuditLogService;
use App\Services\Compliance\EventComplianceService;
use App\Support\AdminAuthorization as Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSV compliance reports. Each export is authorized for what it contains, limited to the administrator's organizers,
 * and recorded in the audit log. Exports never include identity documents, document numbers, storage paths or hashes.
 */
class ComplianceExportController extends Controller
{
    public const REPORTS = [
        'permits' => 'Permits and licences',
        'declarations' => 'Event compliance declarations',
        'safety' => 'Safety reports',
        'suspensions' => 'Host publishing suspensions',
        'identity' => 'Host identity verification status',
        'consent' => 'Consent records (policy acceptances)',
        'audit' => 'Compliance audit trail',
    ];

    public function export(Request $request, string $report): StreamedResponse
    {
        abort_unless(array_key_exists($report, self::REPORTS), 404);
        Gate::authorize(Admin::COMPLIANCE_EXPORT);
        $admin = $request->user();
        match ($report) {
            'identity' => Gate::authorize('viewAny', HostIdentityVerification::class),
            'consent', 'audit' => Gate::authorize(Admin::COMPLIANCE_RECORDS),
            'suspensions' => abort_unless(Admin::organizerScope($admin) === null, 403, 'Host suspensions are exported by platform-wide administrators.'),
            default => null,
        };
        $state = strtolower(substr(preg_replace('/[^A-Za-z]/', '', (string) $request->query('state', '')), 0, 2));
        $events = Admin::applyOrganizerScope(Event::allTenants(), $admin)->when($state !== '', fn ($q) => $q->whereRaw('lower(state_code) = ?', [$state]));

        [$header, $rows] = $this->build($report, $events, $admin);

        AuditLogService::log(action: 'compliance_report_exported', targetEntity: 'compliance_reports', targetId: $report,
            details: self::REPORTS[$report] . ' exported' . ($state !== '' ? ' for ' . strtoupper($state) : '') . ', ' . count($rows) . ' rows.', actor: $admin);

        $filename = 'compliance-' . $report . ($state !== '' ? '-' . $state : '') . '-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($header, $rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $header);
            foreach ($rows as $row) {
                fputcsv($out, array_map([self::class, 'safe'], $row));
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store']);
    }

    private function build(string $report, $events, $admin): array
    {
        $date = fn ($d) => $d?->format('Y-m-d') ?? '';
        $time = fn ($d) => $d?->format('Y-m-d H:i') ?? '';

        return match ($report) {
            'permits' => [
                ['Event', 'State', 'Requirement', 'Reference', 'Issuing authority', 'Expires', 'Status', 'Submitted', 'Reviewed', 'Review comment'],
                EventComplianceEvidence::with('event')->whereIn('event_id', (clone $events)->select('id'))->orderBy('event_id')->get()
                    ->map(fn ($e) => [$e->event?->title, $e->event?->state_code, $e->item_title, $e->reference_number, $e->issuing_authority, $date($e->expires_on),
                        $e->checklistStatus(), $time($e->submitted_at), $time($e->reviewed_at), $e->review_comment])->all(),
            ],
            'declarations' => [
                ['Event', 'Event status', 'State', 'County', 'Municipality', 'Declarations', 'Confirmed at (UTC)', 'Confirmed by'],
                (clone $events)->where('status', '!=', 'cancelled')->orderBy('starts_at')->get()->map(function ($event) {
                    $status = app(EventComplianceService::class)->status($event);
                    $ack = $status['acknowledgement'];

                    return [$event->title, $event->status, $event->state_code, $ack?->jurisdiction_county ?? $event->compliance_jurisdiction_county,
                        $ack?->jurisdiction_municipality, ['current' => 'Current', 'outdated' => 'Out of date', 'missing' => 'Missing'][$status['state']],
                        $ack?->accepted_at_utc?->format('Y-m-d H:i'), $ack?->user?->email];
                })->all(),
            ],
            'safety' => [
                ['Report', 'Event', 'Category', 'Priority', 'Status', 'Reported', 'Occurred', 'Description', 'Resolution', 'Resolved'],
                ComplianceSafetyReport::with('event')->whereIn('event_id', (clone $events)->select('id'))->latest()->get()
                    ->map(fn ($r) => [$r->id, $r->event?->title, ComplianceSafetyReport::CATEGORIES[$r->category] ?? $r->category, $r->priority, $r->status,
                        $time($r->created_at), $date($r->occurred_on), $r->description, $r->resolution, $time($r->resolved_at)])->all(),
            ],
            'suspensions' => [
                ['Host', 'Email', 'Suspended', 'Reason', 'Review by', 'Lifted', 'Lift reason'],
                HostPublishingSuspension::with('user')->latest('suspended_at')->get()
                    ->map(fn ($s) => [$s->user?->name, $s->user?->email, $time($s->suspended_at), $s->reason, $date($s->review_by), $time($s->lifted_at), $s->lift_reason])->all(),
            ],
            'identity' => [
                ['Host', 'Email', 'Status', 'Document type', 'Document expires', 'Submitted', 'Reviewed', 'Reviewer'],
                HostIdentityVerification::with(['user', 'reviewer'])->latest()->get()
                    ->map(fn ($v) => [$v->user?->name, $v->user?->email, $v->statusLabel(), $v->documentTypeLabel(), $date($v->document_expires_at),
                        $time($v->submitted_at_utc), $time($v->reviewed_at_utc), $v->reviewer?->name])->all(),
            ],
            'consent' => [
                ['User', 'Email', 'Policy', 'Version', 'Status', 'Accepted at (UTC)', 'Context', 'IP address', 'Browser'],
                PolicyAcceptance::with('user')->latest('accepted_at_utc')->limit(20000)->get()
                    ->map(fn ($p) => [$p->user?->name, $p->user?->email, $p->policy_name, $p->policy_version, $p->acceptance_status,
                        $time($p->accepted_at_utc), $p->context, $p->ip_address, $p->user_agent])->all(),
            ],
            'audit' => [
                ['When (UTC)', 'Actor', 'Role', 'Action', 'Target', 'Target id', 'Details', 'Outcome'],
                Admin::applyAuditScope(SecurityAuditLog::query(), $admin)->whereIn('action', ComplianceCentreController::AUDIT_ACTIONS)
                    ->with('user')->latest('created_at')->limit(20000)->get()
                    ->map(fn ($l) => [$time($l->created_at), $l->user?->email ?? 'system', $l->actor_role, $l->action, $l->target_entity, $l->target_id, $l->details, $l->outcome])->all(),
            ],
        };
    }

    /** Stops spreadsheet formula injection from user-entered text. */
    public static function safe($value): string
    {
        $value = (string) ($value ?? '');

        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'" . $value : $value;
    }
}
