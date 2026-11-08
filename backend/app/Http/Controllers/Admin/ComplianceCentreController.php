<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\HostVerificationStatus;
use App\Http\Controllers\Controller;
use App\Models\AdminPermission;
use App\Models\Compliance\ComplianceJurisdiction;
use App\Models\Compliance\ComplianceSafetyReport;
use App\Models\Compliance\HostPublishingSuspension;
use App\Models\Event;
use App\Models\EventComplianceAcknowledgement;
use App\Models\EventComplianceEvidence;
use App\Models\HostIdentityVerification;
use App\Models\PolicyAcceptance;
use App\Models\SecurityAuditLog;
use App\Models\User;
use App\Events\Compliance\ComplianceActionRecorded;
use App\Events\Compliance\PublishingSuspensionChanged;
use App\Services\Compliance\EventComplianceService;
use App\Services\Compliance\EventPublicationGates;
use App\Services\HostIdentityVerificationService;
use App\Support\AdminAuthorization as Admin;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The compliance management dashboard: one place for compliance administrators to review identity submissions,
 * permits, licences and event declarations, filter by jurisdiction, watch expiring documents, handle safety reports,
 * suspend hosts and events, and read consent and audit records.
 *
 * Every action is authorized through AdminAuthorization's Gates and the compliance Policies, never role names:
 *   - compliance management (Admin::COMPLIANCE_MANAGE): system administrators and administrators granted it;
 *   - identity documents (HostIdentityVerificationPolicy): only administrators specifically granted access;
 *   - consent and audit records (Admin::COMPLIANCE_RECORDS): compliance managers who may read audit logs.
 * Delegated administrators only see the events and hosts of the organizers they are assigned.
 */
class ComplianceCentreController extends Controller
{
    public const EXPIRY_WINDOW_DAYS = 60;

    /** Audit actions that belong to the compliance trail. */
    public const AUDIT_ACTIONS = \App\Models\ComplianceAuditLog::ACTIONS;

    public function dashboard(Request $request): View
    {
        Gate::authorize(Admin::COMPLIANCE_MANAGE);
        $admin = $request->user();
        $state = $this->stateFilter($request);
        $events = $this->scopedEvents($admin, $state);

        $evidence = EventComplianceEvidence::whereIn('event_id', (clone $events)->select('id'));
        $open = fn ($kind) => ComplianceRulesController::filterKind((clone $evidence)->whereIn('status', ['submitted', 'pending_verification']), $kind)->count();
        $canIdentity = Gate::allows('viewAny', HostIdentityVerification::class);

        return view('admin.compliance-centre.dashboard', $this->shared($request, [
            'counts' => [
                'identity' => $canIdentity ? HostIdentityVerification::whereIn('status', ['pending_review', 'under_review'])->count() : null,
                'food' => $open('food'),
                'alcohol' => $open('alcohol'),
                'declarations' => $this->declarationRows($admin, $state)->whereIn('state', ['missing', 'outdated'])->count(),
                'expiring' => count($this->expiringRows($admin, $state)),
                'safety' => $this->safetyQuery($admin, $state)->whereIn('status', ['open', 'investigating'])->count(),
                'urgent' => $this->safetyQuery($admin, $state)->whereIn('status', ['open', 'investigating'])->where('priority', 'urgent')->count(),
                'host_suspensions' => HostPublishingSuspension::active()->count(),
                'event_suspensions' => (clone $events)->whereNotNull('compliance_suspended_at')->count(),
            ],
            'expiring' => array_slice($this->expiringRows($admin, $state), 0, 8),
            'safety' => $this->safetyQuery($admin, $state)->whereIn('status', ['open', 'investigating'])->orderByRaw("priority = 'urgent' desc")->latest()->take(6)->get(),
        ]));
    }

    // ----- Identity -------------------------------------------------------------------------------------------

    public function identity(Request $request): View
    {
        Gate::authorize('viewAny', HostIdentityVerification::class);
        $status = (string) $request->query('status', 'open');
        $search = trim((string) $request->query('q', ''));

        $items = HostIdentityVerification::with(['user', 'reviewer'])
            ->when($status === 'open', fn ($q) => $q->whereIn('status', ['pending_review', 'under_review', 'additional_information_required']))
            ->when($status !== 'open' && $status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($search !== '', fn ($q) => $q->whereIn('user_id', User::where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")->select('id')))
            ->latest('submitted_at_utc')->paginate(25)->withQueryString();

        return view('admin.compliance-centre.identity', $this->shared($request, compact('items', 'status', 'search')));
    }

    /** Issue a short-lived link to one ID document (audited as PII access) and open it. */
    public function identityDocument(Request $request, HostIdentityVerification $verification): RedirectResponse
    {
        Gate::authorize('viewDocument', $verification);
        try {
            return redirect()->away(HostIdentityVerificationService::generateTemporaryDocumentUrl($request->user(), $verification));
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    // ----- Event declarations ---------------------------------------------------------------------------------

    public function declarations(Request $request): View
    {
        Gate::authorize(Admin::COMPLIANCE_MANAGE);
        $state = $this->stateFilter($request);
        $show = (string) $request->query('show', 'attention');
        $rows = $this->declarationRows($request->user(), $state, trim((string) $request->query('county', '')));
        if ($show === 'attention') {
            $rows = $rows->whereIn('state', ['missing', 'outdated']);
        } elseif ($show === 'suspended') {
            $rows = $rows->filter(fn ($row) => $row['event']->compliance_suspended_at !== null);
        }

        return view('admin.compliance-centre.declarations', $this->shared($request, ['rows' => $rows->values(), 'show' => $show, 'county' => (string) $request->query('county', '')]));
    }

    public function declaration(Request $request, string $event): View
    {
        $record = Event::allTenants()->findOrFail($event);
        Gate::authorize(Admin::COMPLIANCE_SUSPEND_EVENT, $record);
        $history = EventComplianceAcknowledgement::where('event_id', $record->id)->with('user')->latest('accepted_at_utc')->get();

        return view('admin.compliance-centre.declaration', $this->shared($request, [
            'event' => $record,
            'history' => $history,
            'status' => app(EventComplianceService::class)->status($record)['state'],
            'declarations' => EventComplianceService::declarations(),
            'gates' => app(EventPublicationGates::class)->check($record),
        ]));
    }

    public function suspendEvent(\App\Http\Requests\Compliance\SuspendEventRequest $request, string $event): RedirectResponse
    {
        $record = Event::allTenants()->findOrFail($event);
        Gate::authorize(Admin::COMPLIANCE_SUSPEND_EVENT, $record);
        $data = $request->validated();
        if ($record->compliance_suspended_at) {
            return back()->with('error', 'This event is already suspended.');
        }
        $before = $record->status;
        DB::transaction(function () use ($record, $data, $request, $before) {
            $record->forceFill(['compliance_suspended_at' => now(), 'compliance_suspended_by' => $request->user()->id, 'compliance_suspension_reason' => $data['reason'],
                'status' => $record->status === 'published' ? 'unpublished' : $record->status, 'is_ordering_open' => false])->save();
            PublishingSuspensionChanged::dispatch(true, $data['reason'], $request->user(), $record, null,
                "{$record->title} suspended (was {$before}; unpublished and ordering closed). Reason: {$data['reason']}");
        });

        return back()->with('success', "\"{$record->title}\" is suspended: unpublished, ordering closed, and it cannot be published until reinstated.");
    }
    public function reinstateEvent(\App\Http\Requests\Compliance\ComplianceReasonRequest $request, string $event): RedirectResponse
    {
        $record = Event::allTenants()->findOrFail($event);
        Gate::authorize(Admin::COMPLIANCE_SUSPEND_EVENT, $record);
        $data = $request->validated();
        if (!$record->compliance_suspended_at) {
            return back()->with('error', 'This event is not suspended.');
        }
        DB::transaction(function () use ($record, $data, $request) {
            $record->forceFill(['compliance_suspended_at' => null, 'compliance_suspended_by' => null, 'compliance_suspension_reason' => null])->save();
            PublishingSuspensionChanged::dispatch(false, $data['reason'], $request->user(), $record, null,
                "{$record->title} reinstated. It stays unpublished until the host publishes it through the compliance gates. Reason: {$data['reason']}");
        });

        return back()->with('success', "\"{$record->title}\" is reinstated. It stays unpublished until the host publishes it, which runs every compliance gate again.");
    }
    // ----- Expiring documents ---------------------------------------------------------------------------------

    public function expiring(Request $request): View
    {
        Gate::authorize(Admin::COMPLIANCE_MANAGE);

        return view('admin.compliance-centre.expiring', $this->shared($request, ['rows' => $this->expiringRows($request->user(), $this->stateFilter($request))]));
    }

    // ----- Safety reports -------------------------------------------------------------------------------------

    public function safety(Request $request): View
    {
        Gate::authorize('viewAny', ComplianceSafetyReport::class);
        $status = (string) $request->query('status', 'open');
        $items = $this->safetyQuery($request->user(), $this->stateFilter($request))
            ->with(['event', 'reporter', 'assignee', 'resolver'])
            ->when($status === 'open', fn ($q) => $q->whereIn('status', ['open', 'investigating']))
            ->when(array_key_exists($status, ComplianceSafetyReport::STATUSES), fn ($q) => $q->where('status', $status))
            ->orderByRaw("priority = 'urgent' desc")->latest()->paginate(25)->withQueryString();
        $managers = User::whereIn('id', fn ($q) => $q->select('user_id')->from('user_roles')->whereIn('role', ['admin', 'system_admin'])->where('is_active', true))
            ->orWhere('is_system_admin', true)->orderBy('name')->get()->filter(fn (User $u) => Admin::isComplianceManager($u))->values();

        return view('admin.compliance-centre.safety', $this->shared($request, compact('items', 'status', 'managers')));
    }

    public function updateSafety(\App\Http\Requests\Compliance\UpdateSafetyReportRequest $request, ComplianceSafetyReport $report): RedirectResponse
    {
        $data = $request->validated();
        $closing = in_array($data['status'], ['resolved', 'dismissed'], true);
        DB::transaction(function () use ($report, $data, $closing, $request) {
            $report->forceFill($data + [
                'resolved_by' => $closing ? $request->user()->id : null,
                'resolved_at' => $closing ? ($report->resolved_at ?? now()) : null,
            ])->save();
            ComplianceActionRecorded::dispatch('compliance_safety_report_updated', 'compliance_safety_reports', (string) $report->id,
                "Safety report #{$report->id}: {$report->status}, {$report->priority}" . ($report->resolution ? ". {$report->resolution}" : ''),
                $request->user(), $report->event?->organizer_id, $report->event_id);
        });

        return back()->with('success', "Safety report #{$report->id} updated.");
    }
    // ----- Hosts ----------------------------------------------------------------------------------------------

    public function hosts(Request $request): View
    {
        Gate::authorize(Admin::COMPLIANCE_MANAGE);
        $admin = $request->user();
        $search = trim((string) $request->query('q', ''));
        $show = (string) $request->query('show', 'all');
        $query = User::whereIn('id', fn ($q) => $q->select('user_id')->from('user_roles')->where('role', 'event_host')->where('is_active', true))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")))
            ->when($show === 'suspended', fn ($q) => $q->whereIn('id', HostPublishingSuspension::active()->select('user_id')));
        $scope = Admin::organizerScope($admin);
        if ($scope !== null) {
            $query->whereIn('id', fn ($q) => $q->select('user_id')->from('organizer_users')->whereIn('organizer_id', $scope ?: ['__none__']));
        }
        $hosts = $query->orderBy('name')->paginate(25)->withQueryString();
        $rows = $hosts->getCollection()->map(fn (User $host) => [
            'host' => $host,
            'identity' => EventPublicationGates::identityVerified($host),
            'missing_policies' => EventPublicationGates::missingHostPolicies($host),
            'suspension' => HostPublishingSuspension::activeFor((int) $host->id),
        ]);

        return view('admin.compliance-centre.hosts', $this->shared($request, compact('hosts', 'rows', 'search', 'show')));
    }

    public function suspendHost(\App\Http\Requests\Compliance\SuspendHostRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();
        if (HostPublishingSuspension::activeFor((int) $user->id)) {
            return back()->with('error', "{$user->name}'s publishing privileges are already suspended.");
        }

        // The suspension, any unpublishing and the audit entry succeed or fail together.
        $unpublished = DB::transaction(function () use ($request, $user, $data) {
            HostPublishingSuspension::create(['user_id' => $user->id, 'reason' => $data['reason'], 'suspended_by' => $request->user()->id,
                'suspended_at' => now(), 'review_by' => $data['review_by'] ?? null]);
            $unpublished = 0;
            if (!empty($data['unpublish'])) {
                $organizers = \App\Models\OrganizerUser::where('user_id', $user->id)->where('is_active', true)->pluck('organizer_id');
                $unpublished = Event::allTenants()->whereIn('organizer_id', $organizers)->where('status', 'published')
                    ->update(['status' => 'unpublished', 'is_ordering_open' => false]);
            }
            PublishingSuspensionChanged::dispatch(true, $data['reason'], $request->user(), null, $user,
                "Publishing privileges suspended for {$user->name}" . ($unpublished ? "; {$unpublished} published events unpublished" : '') . ". Reason: {$data['reason']}");

            return $unpublished;
        });

        return back()->with('success', "{$user->name} can no longer publish events" . ($unpublished ? " and {$unpublished} published " . str('event')->plural($unpublished) . ' were unpublished' : '') . '.');
    }
    public function reinstateHost(\App\Http\Requests\Compliance\ComplianceReasonRequest $request, HostPublishingSuspension $suspension): RedirectResponse
    {
        Gate::authorize(Admin::COMPLIANCE_SUSPEND_HOST, $suspension->user);
        $data = $request->validated();
        if ($suspension->lifted_at) {
            return back()->with('error', 'This suspension has already been lifted.');
        }
        DB::transaction(function () use ($suspension, $data, $request) {
            $suspension->forceFill(['lifted_at' => now(), 'lifted_by' => $request->user()->id, 'lift_reason' => $data['reason']])->save();
            PublishingSuspensionChanged::dispatch(false, $data['reason'], $request->user(), null, $suspension->user,
                "Publishing privileges restored for {$suspension->user?->name}. Reason: {$data['reason']}");
        });

        return back()->with('success', "{$suspension->user?->name} can publish again (every compliance gate still applies).");
    }
    // ----- Consent and audit records --------------------------------------------------------------------------

    public function records(Request $request): View
    {
        Gate::authorize(Admin::COMPLIANCE_RECORDS);
        $search = trim((string) $request->query('q', ''));
        $policy = (string) $request->query('policy', '');
        $users = $search !== '' ? User::where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")->limit(200)->pluck('id')->all() : null;

        $consents = PolicyAcceptance::with('user')
            ->when($users !== null, fn ($q) => $q->whereIn('user_id', $users))
            ->when($policy !== '', fn ($q) => $q->where('policy_name', $policy))
            ->latest('accepted_at_utc')->paginate(25, ['*'], 'consents')->withQueryString();
        $audit = Admin::applyAuditScope(SecurityAuditLog::query(), $request->user())
            ->whereIn('action', self::AUDIT_ACTIONS)
            ->when($users !== null, fn ($q) => $q->where(fn ($w) => $w->whereIn('user_id', $users)->orWhereIn('target_id', array_map('strval', $users))))
            ->latest('created_at')->paginate(25, ['*'], 'audit')->withQueryString();
        $policies = app(\App\Services\Compliance\PolicyCatalog::class)->all();

        return view('admin.compliance-centre.records', $this->shared($request, compact('consents', 'audit', 'search', 'policy', 'policies')));
    }

    // ----- Identity document access ---------------------------------------------------------------------------

    public function access(Request $request): View
    {
        Gate::authorize(Admin::COMPLIANCE_MANAGE);
        $admins = User::whereIn('id', fn ($q) => $q->select('user_id')->from('user_roles')->whereIn('role', ['admin', 'system_admin'])->where('is_active', true))
            ->orWhere('is_system_admin', true)->with('adminPermissions')->orderBy('name')->get();

        return view('admin.compliance-centre.access', $this->shared($request, ['admins' => $admins]));
    }

    public function updateAccess(\App\Http\Requests\Compliance\UpdateComplianceAccessRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();
        DB::transaction(function () use ($user, $data, $request) {
            $permissions = AdminPermission::firstOrNew(['user_id' => $user->id], ['scope' => 'all']);
            $describe = fn () => sprintf('identity documents=%s, compliance management=%s',
                $permissions->can_access_identity_documents ? 'yes' : 'no', $permissions->can_manage_compliance ? 'yes' : 'no');
            $before = $describe();
            $permissions->can_access_identity_documents = (bool) $data['grant'];
            $permissions->identity_access_granted_by = $data['grant'] ? $request->user()->id : null;
            $permissions->identity_access_granted_at = $data['grant'] ? now() : null;
            if (array_key_exists('manage', $data) && $data['manage'] !== null) {
                $permissions->can_manage_compliance = (bool) $data['manage'];
            }
            $permissions->save();
            ComplianceActionRecorded::dispatch('compliance_identity_access_changed', 'users', (string) $user->id,
                "Compliance access for {$user->name}: {$before} -> {$describe()}. Reason: {$data['reason']}", $request->user());
        });

        return back()->with('success', "Compliance access updated for {$user->name}.");
    }
    // ----- Helpers --------------------------------------------------------------------------------------------

    /** The tab counts and filters every page shares. */
    private function shared(Request $request, array $data): array
    {
        return $data + [
            'stateFilter' => strtoupper($this->stateFilter($request)),
            'states' => ComplianceJurisdiction::where('type', 'state')->orderBy('name')->get(['code', 'name']),
            'canIdentity' => Gate::allows('viewAny', HostIdentityVerification::class),
            'canRecords' => Gate::allows(Admin::COMPLIANCE_RECORDS),
        ];
    }

    private function stateFilter(Request $request): string
    {
        return strtolower(substr(preg_replace('/[^A-Za-z]/', '', (string) $request->query('state', '')), 0, 2));
    }

    private function scopedEvents(User $admin, string $state = ''): Builder
    {
        return Admin::applyOrganizerScope(Event::allTenants(), $admin)
            ->when($state !== '', fn ($q) => $q->whereRaw('lower(state_code) = ?', [$state]));
    }

    private function safetyQuery(User $admin, string $state = ''): Builder
    {
        $scope = Admin::organizerScope($admin);
        $query = ComplianceSafetyReport::query();
        if ($scope !== null || $state !== '') {
            $query->whereIn('event_id', $this->scopedEvents($admin, $state)->select('id'));
        }

        return $query;
    }

    /** Current and upcoming events with their Event Compliance acknowledgement status. */
    private function declarationRows(User $admin, string $state = '', string $county = '')
    {
        $service = app(EventComplianceService::class);

        return $this->scopedEvents($admin, $state)
            ->where('status', '!=', 'cancelled')
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '>=', now()->subDay()))
            ->when($county !== '', fn ($q) => $q->where('compliance_jurisdiction_county', 'like', "%{$county}%"))
            ->orderBy('starts_at')->limit(300)->get()
            ->map(function (Event $event) use ($service) {
                $status = $service->status($event);

                return ['event' => $event, 'state' => $status['state'], 'acknowledgement' => $status['acknowledgement']];
            });
    }

    /**
     * Verified permits and licences, and (for administrators with identity access) approved host IDs, that have
     * expired or expire within the window, soonest first.
     */
    private function expiringRows(User $admin, string $state = ''): array
    {
        $limit = now()->addDays(self::EXPIRY_WINDOW_DAYS)->endOfDay();
        $rows = [];
        $evidence = EventComplianceEvidence::with('event')->where('status', 'verified')->whereNotNull('expires_on')->where('expires_on', '<=', $limit)
            ->whereIn('event_id', $this->scopedEvents($admin, $state)->select('id'))->get();
        foreach ($evidence as $item) {
            $rows[] = ['kind' => 'Permit or licence', 'title' => $item->item_title, 'subject' => $item->event?->title ?? $item->event_id,
                'date' => $item->expires_on, 'url' => route('admin.console.compliance-centre.declaration', $item->event_id)];
        }
        if (Gate::forUser($admin)->allows('viewAny', HostIdentityVerification::class) && $state === '') {
            $ids = HostIdentityVerification::with('user')->where('status', HostVerificationStatus::APPROVED->value)
                ->whereNotNull('document_expires_at')->where('document_expires_at', '<=', $limit)->get();
            foreach ($ids as $verification) {
                $rows[] = ['kind' => 'Host government ID', 'title' => $verification->documentTypeLabel(), 'subject' => $verification->user?->name ?? 'Former user',
                    'date' => $verification->document_expires_at, 'url' => route('admin.console.compliance-centre.identity', ['status' => 'all', 'q' => $verification->user?->email])];
            }
        }
        foreach ($rows as &$row) {
            $row['days'] = (int) now()->startOfDay()->diffInDays($row['date']->copy()->startOfDay(), false);
        }
        usort($rows, fn ($a, $b) => $a['date'] <=> $b['date']);

        return $rows;
    }
}
