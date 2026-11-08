<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Compliance\CompliancePublicationOverride;
use App\Models\Event;
use App\Services\AuditLogService;
use App\Services\Compliance\EventPublicationGates;
use App\Support\AdminAuthorization as Admin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * A compliance administrator overrides one requirement item blocking an event's publication: with explicit
 * permission (admin.compliance.override), a documented reason, a review date at most 90 days away and an audit
 * entry. It can be revoked at any time. Event hosts have no route here.
 */
class CompliancePublicationOverrideController extends Controller
{
    public const MAX_DAYS = 90;

    public function store(Request $request, string $event, EventPublicationGates $gates): RedirectResponse
    {
        Gate::authorize(Admin::COMPLIANCE_OVERRIDE);
        $record = Event::withoutGlobalScopes()->findOrFail($event);
        $data = $request->validate([
            'gate_code' => ['required', 'string', 'max:200'],
            'reason' => ['required', 'string', 'min:20', 'max:2000'],
            'review_by' => ['required', 'date', 'after:today', 'before_or_equal:' . now()->addDays(self::MAX_DAYS)->toDateString()],
            'confirm' => ['accepted'],
        ], [
            'reason.min' => 'Document the reason in at least 20 characters: what was checked and why the event may proceed.',
            'review_by.before_or_equal' => 'An override lasts at most ' . self::MAX_DAYS . ' days; set an earlier review date.',
            'confirm.accepted' => 'Confirm that you have authority to override this requirement.',
        ]);

        $gate = collect($gates->check($record))->first(fn ($g) => $g['code'] === $data['gate_code']);
        if (!$gate) {
            return back()->withErrors(['gate_code' => 'That requirement is no longer blocking this event.']);
        }
        if (!$gate['overridable'] || !str_starts_with($gate['code'], EventPublicationGates::OVERRIDABLE_PREFIX)) {
            return back()->withErrors(['gate_code' => 'This gate cannot be overridden: identity verification, host acknowledgements, the event\'s own acknowledgement and its food and alcohol declarations must come from the host.']);
        }
        if ($gate['override']) {
            return back()->withErrors(['gate_code' => 'This requirement already has an active override.']);
        }

        $override = CompliancePublicationOverride::create([
            'event_id' => $record->id, 'gate_code' => $gate['code'], 'gate_message' => $gate['message'], 'reason' => $data['reason'],
            'granted_by' => $request->user()->id, 'granted_at' => now(), 'review_by' => $data['review_by'],
        ]);
        AuditLogService::log(
            action: 'compliance_override_granted', targetEntity: 'events', targetId: (string) $record->id,
            details: "Override #{$override->id} of \"{$gate['message']}\" until {$data['review_by']}. Reason: {$data['reason']}",
            actor: $request->user(), organizerId: (string) $record->organizer_id, eventId: (string) $record->id,
        );

        return back()->with('success', 'Override recorded until ' . $override->review_by->format('j M Y') . '.');
    }

    public function revoke(Request $request, CompliancePublicationOverride $override): RedirectResponse
    {
        Gate::authorize(Admin::COMPLIANCE_OVERRIDE);
        $data = $request->validate(['revoke_reason' => ['required', 'string', 'min:5', 'max:2000']]);
        if ($override->revoked_at !== null) {
            return back()->withErrors(['revoke_reason' => 'This override was already revoked.']);
        }
        $override->forceFill(['revoked_by' => $request->user()->id, 'revoked_at' => now(), 'revoke_reason' => $data['revoke_reason']])->save();
        AuditLogService::log(
            action: 'compliance_override_revoked', targetEntity: 'events', targetId: (string) $override->event_id,
            details: "Override #{$override->id} revoked. Reason: {$data['revoke_reason']}",
            actor: $request->user(), eventId: (string) $override->event_id,
        );

        return back()->with('success', 'Override revoked. The requirement blocks publishing again.');
    }
}
