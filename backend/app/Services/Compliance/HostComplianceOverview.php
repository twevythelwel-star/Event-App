<?php

declare(strict_types=1);

namespace App\Services\Compliance;

use App\Enums\HostVerificationStatus;
use App\Models\Compliance\CompliancePublicationOverride;
use App\Models\Event;
use App\Models\EventComplianceEvidence;
use App\Models\HostApplication;
use App\Models\HostIdentityVerification;
use App\Models\OrganizerUser;
use App\Models\PolicyAcceptance;
use App\Models\User;
use App\Services\LegalComplianceService;
use Illuminate\Support\Collection;

/**
 * Everything an event host needs to see about their own compliance, in one place (the "Compliance and
 * verification" page and the summary on the host dashboard).
 *
 * It reads the same sources the publication gates use (EventPublicationGates), so what it reports as blocking is
 * exactly what blocks publishing. It never marks anything compliant itself: identity, permits and licences only
 * count once an EventBite administrator has verified them.
 *
 * Every status carries a tone (success, warning, error, info, neutral), a text label (colour is never the only
 * signal) and, where the host has something to do, the action in plain words and where to do it.
 */
final class HostComplianceOverview
{
    /** Documents expiring within this many days are listed as upcoming expirations. */
    public const EXPIRY_WINDOW_DAYS = 60;

    /** Gate codes that belong to the host's account rather than to one event. */
    private const ACCOUNT_GATES = ['identity', 'host_policies', 'host_suspended'];

    public function __construct(private readonly EventPublicationGates $gates)
    {
    }

    public function for(User $host): array
    {
        $events = $this->events($host);
        $identity = $this->identity($host);
        $policies = $this->policies($host);
        $eventRows = $events->map(fn (Event $event) => $this->event($event, $host))->values();
        $documents = $this->documents($host, $events);
        $expiring = $this->expiring($identity, $documents);
        $acknowledgements = $this->acknowledgements($host, $eventRows);

        $overview = [
            'identity' => $identity,
            'policies' => $policies,
            'events' => $eventRows->all(),
            'events_needing_information' => $eventRows->filter(fn ($row) => $row['needs_host'])->values()->all(),
            'documents' => $documents,
            'expiring' => $expiring,
            'acknowledgements' => $acknowledgements,
            'messages' => $this->messages($host, $events),
            'suspension' => \App\Models\Compliance\HostPublishingSuspension::activeFor((int) $host->id),
        ];
        $overview['account'] = $this->account($overview);

        return $overview;
    }

    /** @return Collection<int, Event> the host's current events (not cancelled, not long past) */
    public function events(User $host): Collection
    {
        $organizerIds = OrganizerUser::where('user_id', $host->id)->where('is_active', true)->pluck('organizer_id');

        return Event::withoutGlobalScopes()->whereIn('organizer_id', $organizerIds)
            ->where('status', '!=', 'cancelled')
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '>=', now()->subDay()))
            ->orderByRaw('starts_at is null')->orderBy('starts_at')->get();
    }

    private function identity(User $host): array
    {
        $verification = HostIdentityVerification::where('user_id', $host->id)->latest()->first();
        $verified = EventPublicationGates::identityVerified($host);
        $update = route('host.compliance') . '#identity';

        if (!$verification) {
            return $this->status($verified ? 'verified_by_application' : 'not_submitted', $verified ? 'success' : 'error',
                $verified ? 'Verified' : 'Not submitted',
                $verified ? 'Your identity was verified with your host application.' : 'EventBite has no government ID verification for you.',
                $verified ? null : 'Upload a government-issued photo ID. Your events cannot be published until EventBite approves it.',
                $verified ? null : $update) + ['record' => null, 'can_upload' => !$verified];
        }

        $status = $verification->statusEnum();
        $expired = $verification->isDocumentExpired();
        [$tone, $explain, $action] = match (true) {
            $status === HostVerificationStatus::APPROVED && $expired => ['error', 'Your approved ID expired on ' . $verification->document_expires_at->format('j M Y') . '.', 'Upload your renewed ID. Your events cannot be published until EventBite approves it.'],
            $status === HostVerificationStatus::APPROVED => ['success', 'EventBite has approved your government ID.', null],
            in_array($status, [HostVerificationStatus::PENDING_REVIEW, HostVerificationStatus::UNDER_REVIEW], true) => ['info', 'EventBite is reviewing your ID. You will see the result here.', null],
            $status === HostVerificationStatus::ADDITIONAL_INFORMATION_REQUIRED => ['warning', 'EventBite needs more information about your ID.', 'Read the reviewer\'s message below and upload a clearer or different document.'],
            $status === HostVerificationStatus::REJECTED => ['error', 'EventBite could not accept your ID.', 'Read the reviewer\'s message below and upload a valid government-issued photo ID.'],
            $status === HostVerificationStatus::EXPIRED, $status === HostVerificationStatus::REVERIFICATION_REQUIRED => ['error', 'Your identity needs to be verified again.', 'Upload a current government-issued photo ID.'],
            $status === HostVerificationStatus::SUSPENDED => ['error', 'Your identity verification is suspended.', 'Contact EventBite support. You cannot publish events until it is resolved.'],
            default => ['error', 'EventBite has no government ID verification for you.', 'Upload a government-issued photo ID.'],
        };
        $canUpload = $status->allowsDocumentReplacement() || ($status === HostVerificationStatus::APPROVED && $expired);

        return $this->status($expired && $status === HostVerificationStatus::APPROVED ? 'expired' : $status->value, $tone,
            $expired && $status === HostVerificationStatus::APPROVED ? 'Expired' : $verification->statusLabel(),
            $explain, $action, $action && $canUpload ? $update : null)
            + ['record' => $verification, 'can_upload' => $canUpload];
    }

    /**
     * Every mandatory policy a host must have accepted at its current version: the account policies and the host
     * responsibility acknowledgements. A policy accepted only at an older version is a policy update.
     */
    private function policies(User $host): array
    {
        $accepted = PolicyAcceptance::where('user_id', $host->id)->where('acceptance_status', 'accepted')
            ->orderByDesc('accepted_at_utc')->get(['policy_name', 'policy_version', 'accepted_at_utc'])->groupBy('policy_name');

        $rows = [];
        $catalog = app(PolicyCatalog::class);
        // Policies in force for this host (role and jurisdiction); optional ones are listed but never required.
        $specs = array_filter($catalog->all(), fn ($spec) => $catalog->appliesTo($host, $spec, ignoreRole: $spec['group'] === 'host'));
        foreach ($specs as $spec) {
            $history = $accepted->get($spec['policy_name'], collect());
            $current = $history->firstWhere('policy_version', $spec['version']);
            $previous = $history->first(fn ($row) => $row->policy_version !== $spec['version']);
            $state = $catalog->state($host, $spec['policy_name']);
            if (!$spec['mandatory'] && $state !== 'accepted') {
                $state = 'optional';
            }
            $rows[] = [
                'policy_name' => $spec['policy_name'],
                'title' => $spec['title'],
                'group' => $spec['group'] === 'host' ? 'Host responsibility' : 'Account policy',
                'description' => $spec['description'],
                'version' => $spec['version'],
                'state' => $state,
                'accepted_at' => ($current ?? $history->first())?->accepted_at_utc,
                'previous_version' => $previous?->policy_version,
                'mandatory' => $spec['mandatory'],
                'change_summary' => $spec['change_summary'],
                'tone' => match ($state) { 'accepted' => 'success', 'updated' => 'warning', 'optional' => 'info', default => 'error' },
                'label' => match ($state) { 'accepted' => 'Accepted', 'updated' => 'Updated: accept again', 'optional' => 'For information', default => 'Not accepted' },
            ];
        }

        return $rows;
    }

    private function event(Event $event, User $host): array
    {
        $gates = collect($this->gates->check($event, $host));
        $own = $gates->reject(fn ($g) => in_array($g['code'], self::ACCOUNT_GATES, true));
        $blocking = $own->filter(fn ($g) => $g['override'] === null)->values();
        $overridden = $own->filter(fn ($g) => $g['override'] !== null)->values();
        $accountBlocked = $gates->contains(fn ($g) => in_array($g['code'], self::ACCOUNT_GATES, true));
        $waitingOnly = $blocking->isNotEmpty() && $blocking->every(fn ($g) => str_contains($g['message'], 'waiting for an EventBite administrator'));
        $published = $event->status === 'published';

        [$state, $tone, $label] = match (true) {
            $event->compliance_suspended_at !== null => ['suspended', 'error', 'Suspended by EventBite'],
            $blocking->isEmpty() && !$accountBlocked => ['ready', 'success', $published ? 'Compliant' : 'Ready to publish'],
            $blocking->isEmpty() => ['account', 'warning', 'Blocked by your account'],
            $waitingOnly => ['waiting', 'info', 'Waiting for EventBite review'],
            default => ['action', $published ? 'error' : 'warning', $published ? 'Published: information missing' : 'Information required'],
        };

        return [
            'event' => $event,
            'state' => $state,
            'tone' => $tone,
            'label' => $label,
            'published' => $published,
            'needs_host' => $state === 'action',
            'account_blocked' => $accountBlocked,
            'issues' => $blocking->map(fn ($g) => [
                'message' => $g['message'],
                'waiting' => str_contains($g['message'], 'waiting for an EventBite administrator'),
                'url' => $g['section'] === 'requirements' ? route('events.requirements', $event->id) : route('events.edit', $event->id) . '#studio-' . $g['section'],
                'link' => $g['section'] === 'requirements' ? 'Open the requirements checklist' : 'Open Event Compliance',
            ])->all(),
            'overridden' => $overridden->map(fn ($g) => ['message' => $g['message'], 'until' => $g['override']->review_by])->all(),
        ];
    }

    /** Permits and licences the host submitted for their events, with EventBite's review status. */
    private function documents(User $host, Collection $events): array
    {
        return EventComplianceEvidence::with('event')->whereIn('event_id', $events->pluck('id'))
            ->orderByRaw('expires_on is null')->orderBy('expires_on')->get()
            ->map(function (EventComplianceEvidence $evidence) {
                $status = $evidence->checklistStatus();

                return [
                    'evidence' => $evidence,
                    'status' => $status,
                    'tone' => match ($status) { 'verified' => 'success', 'rejected', 'expired' => 'error', default => 'info' },
                    'label' => $status === 'expired' ? 'Expired' : (EventComplianceEvidence::STATUSES[$status] ?? ucfirst($status)),
                    'action' => match ($status) {
                        'rejected' => 'Submit a corrected permit or licence.',
                        'expired' => 'Submit the renewed permit or licence.',
                        'submitted', 'pending_verification' => null,
                        default => null,
                    },
                    'url' => route('events.requirements', $evidence->event_id),
                ];
            })->all();
    }

    /** Documents that have expired or expire within the window, soonest first. */
    private function expiring(array $identity, array $documents): array
    {
        $rows = [];
        $limit = now()->addDays(self::EXPIRY_WINDOW_DAYS)->endOfDay();
        $add = function (string $title, string $what, $date, string $url) use (&$rows, $limit) {
            if ($date === null || $date->gt($limit)) {
                return;
            }
            $days = (int) now()->startOfDay()->diffInDays($date->copy()->startOfDay(), false);
            $rows[] = [
                'title' => $title,
                'what' => $what,
                'date' => $date,
                'days' => $days,
                'tone' => $days < 0 ? 'error' : ($days <= 14 ? 'warning' : 'info'),
                'label' => $days < 0 ? 'Expired ' . abs($days) . ' ' . str('day')->plural(abs($days)) . ' ago' : ($days === 0 ? 'Expires today' : 'Expires in ' . $days . ' ' . str('day')->plural($days)),
                'action' => $days < 0 ? 'Upload the renewed document now.' : 'Renew it and upload the new document before it expires.',
                'url' => $url,
            ];
        };

        $record = $identity['record'];
        if ($record && $record->status === HostVerificationStatus::APPROVED->value) {
            $add('Government ID', 'Identity verification', $record->document_expires_at, route('host.compliance') . '#identity');
        }
        foreach ($documents as $doc) {
            if ($doc['evidence']->status === 'verified') {
                $add($doc['evidence']->item_title, $doc['evidence']->event?->title ?? 'Event', $doc['evidence']->expires_on, $doc['url']);
            }
        }
        usort($rows, fn ($a, $b) => $a['date'] <=> $b['date']);

        return $rows;
    }

    /** Acknowledgements the host still owes: host responsibility acknowledgements and each event's declarations. */
    private function acknowledgements(User $host, Collection $eventRows): array
    {
        $rows = [];
        foreach (EventPublicationGates::missingHostPolicies($host) as $title) {
            $rows[] = ['title' => $title, 'scope' => 'Host responsibility acknowledgement', 'tone' => 'error', 'label' => 'Not acknowledged',
                'action' => 'Read and accept it under Policy updates below.', 'url' => route('host.compliance') . '#policies'];
        }
        foreach ($eventRows as $row) {
            $status = app(EventComplianceService::class)->status($row['event'])['state'];
            if ($status !== 'current') {
                $rows[] = ['title' => 'Event Compliance declarations', 'scope' => $row['event']->title,
                    'tone' => $status === 'missing' ? 'error' : 'warning',
                    'label' => $status === 'missing' ? 'Not confirmed' : 'Out of date',
                    'action' => $status === 'missing'
                        ? 'Give the event\'s jurisdiction and confirm the 15 declarations.'
                        : 'The location, food or alcohol arrangements changed. Review and confirm the declarations again.',
                    'url' => route('events.edit', $row['event']->id) . '#studio-event-compliance'];
            }
        }

        return $rows;
    }

    /** Review messages from EventBite: ID review notes, host application decisions, permit reviews and overrides. */
    private function messages(User $host, Collection $events): array
    {
        $messages = [];
        foreach (HostIdentityVerification::where('user_id', $host->id)->latest()->take(5)->get() as $verification) {
            $text = trim((string) ($verification->rejection_or_info_reason ?: $verification->review_notes));
            if ($text !== '' && $verification->reviewed_at_utc) {
                $messages[] = ['from' => 'Identity verification', 'subject' => 'Government ID: ' . $verification->statusLabel(),
                    'body' => $text, 'at' => $verification->reviewed_at_utc,
                    'tone' => $verification->status === HostVerificationStatus::APPROVED->value ? 'success' : 'warning'];
            }
        }
        $application = HostApplication::where('user_id', $host->id)->latest()->first();
        if ($application && $application->reviewed_at && trim((string) ($application->rejection_reason ?: $application->review_notes)) !== '') {
            $messages[] = ['from' => 'Host application', 'subject' => 'Host application: ' . ucfirst(str_replace('_', ' ', (string) $application->status)),
                'body' => (string) ($application->rejection_reason ?: $application->review_notes), 'at' => $application->reviewed_at,
                'tone' => $application->rejection_reason ? 'error' : 'info'];
        }
        foreach (EventComplianceEvidence::with('event')->whereIn('event_id', $events->pluck('id'))->whereNotNull('reviewed_at')->get() as $evidence) {
            $messages[] = ['from' => $evidence->event?->title ?? 'Event', 'subject' => $evidence->item_title . ': ' . (EventComplianceEvidence::STATUSES[$evidence->status] ?? $evidence->status),
                'body' => $evidence->review_comment ?: ($evidence->status === 'verified' ? 'EventBite verified this permit or licence.' : 'EventBite reviewed this permit or licence.'),
                'at' => $evidence->reviewed_at, 'tone' => $evidence->status === 'verified' ? 'success' : ($evidence->status === 'rejected' ? 'error' : 'info')];
        }
        foreach (CompliancePublicationOverride::whereIn('event_id', $events->pluck('id'))->get() as $override) {
            $active = $override->isActive();
            $messages[] = ['from' => $events->firstWhere('id', $override->event_id)?->title ?? 'Event',
                'subject' => $active ? 'Requirement temporarily waived until ' . $override->review_by->format('j M Y') : 'Requirement waiver ended',
                'body' => $active
                    ? 'An EventBite compliance administrator allowed this event to proceed without one requirement until the date shown. The requirement applies again after that date.'
                    : 'A temporary waiver for one requirement has ended. The requirement applies again.',
                'at' => $override->revoked_at ?? ($active ? $override->granted_at : $override->review_by), 'tone' => $active ? 'info' : 'warning'];
        }
        foreach (\App\Models\Compliance\HostPublishingSuspension::where('user_id', $host->id)->latest('suspended_at')->take(5)->get() as $suspension) {
            $messages[] = ['from' => 'EventBite compliance', 'subject' => $suspension->lifted_at ? 'Publishing privileges restored' : 'Publishing privileges suspended',
                'body' => $suspension->lifted_at ? ($suspension->lift_reason ?: 'Your publishing privileges were restored.') : $suspension->reason,
                'at' => $suspension->lifted_at ?? $suspension->suspended_at, 'tone' => $suspension->lifted_at ? 'success' : 'error'];
        }
        foreach ($events->whereNotNull('compliance_suspended_at') as $event) {
            $messages[] = ['from' => $event->title, 'subject' => 'Event suspended by EventBite',
                'body' => (string) ($event->compliance_suspension_reason ?: 'This event was suspended on compliance grounds.'),
                'at' => $event->compliance_suspended_at, 'tone' => 'error'];
        }
        usort($messages, fn ($a, $b) => $b['at'] <=> $a['at']);

        return array_slice($messages, 0, 30);
    }

    /** The host's overall standing, worst first. */
    private function account(array $o): array
    {
        $policyGaps = array_filter($o['policies'], fn ($p) => !in_array($p['state'], ['accepted', 'optional'], true));
        $hostAcks = array_filter($o['policies'], fn ($p) => $p['group'] === 'Host responsibility' && !in_array($p['state'], ['accepted', 'optional'], true));
        $todo = [];
        if ($o['identity']['action']) {
            $todo[] = ['text' => $o['identity']['action'], 'url' => $o['identity']['url'] ?? route('host.compliance') . '#identity'];
        }
        if ($policyGaps !== []) {
            $todo[] = ['text' => 'Accept ' . count($policyGaps) . ' ' . str('policy')->plural(count($policyGaps)) . ' at their current version.', 'url' => route('host.compliance') . '#policies'];
        }
        $needs = count($o['events_needing_information']);
        if ($needs > 0) {
            $todo[] = ['text' => "Give the missing information for {$needs} " . str('event')->plural($needs) . '.', 'url' => route('host.compliance') . '#events'];
        }
        $expired = array_filter($o['expiring'], fn ($row) => $row['days'] < 0);
        if ($expired !== []) {
            $todo[] = ['text' => 'Upload renewed copies of ' . count($expired) . ' expired ' . str('document')->plural(count($expired)) . '.', 'url' => route('host.compliance') . '#expiring'];
        }

        $identityOk = $o['identity']['tone'] === 'success';
        $identityWaiting = $o['identity']['tone'] === 'info';
        if ($o['suspension']) {
            array_unshift($todo, ['text' => 'Your publishing privileges are suspended: ' . $o['suspension']->reason . ' Contact EventBite compliance to resolve it.', 'url' => route('host.compliance') . '#messages']);
        }
        [$state, $tone, $label, $summary] = match (true) {
            $o['suspension'] !== null => ['suspended', 'error', 'Publishing suspended', 'EventBite has suspended your publishing privileges. Your events cannot be published until the suspension is lifted.'],
            !$identityOk && !$identityWaiting, $hostAcks !== [] => ['restricted', 'error', 'Action required', 'You cannot publish events until the items below are resolved.'],
            $identityWaiting => ['under_review', 'info', 'Under review', 'EventBite is reviewing your identity. You cannot publish events until it is approved.'],
            $todo !== [] => ['attention', 'warning', 'Attention needed', 'Your account is in good standing, but some items need your attention.'],
            default => ['compliant', 'success', 'In good standing', 'Your identity is verified and every required policy is accepted.'],
        };

        return compact('state', 'tone', 'label', 'summary', 'todo');
    }

    private function status(string $state, string $tone, string $label, string $explain, ?string $action, ?string $url): array
    {
        return compact('state', 'tone', 'label', 'explain', 'action', 'url');
    }
}
