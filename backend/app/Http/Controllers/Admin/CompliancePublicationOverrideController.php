<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Compliance\CompliancePublicationOverride;
use App\Models\Event;
use App\Events\Compliance\ComplianceActionRecorded;
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

    public function store(\App\Http\Requests\Compliance\StorePublicationOverrideRequest $request, string $event, EventPublicationGates $gates): RedirectResponse
    {
        $record = Event::withoutGlobalScopes()->findOrFail($event);
        $data = $request->validated();

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

        $override = \Illuminate\Support\Facades\DB::transaction(function () use ($record, $gate, $data, $request) {
            $override = CompliancePublicationOverride::create([
                'event_id' => $record->id, 'gate_code' => $gate['code'], 'gate_message' => $gate['message'], 'reason' => $data['reason'],
                'granted_by' => $request->user()->id, 'granted_at' => now(), 'review_by' => $data['review_by'],
            ]);
            ComplianceActionRecorded::dispatch('compliance_override_granted', 'events', (string) $record->id,
                "Override #{$override->id} of \"{$gate['message']}\" until {$data['review_by']}. Reason: {$data['reason']}",
                $request->user(), (string) $record->organizer_id, (string) $record->id);

            return $override;
        });

        return back()->with('success', 'Override recorded until ' . $override->review_by->format('j M Y') . '.');
    }

    public function revoke(\App\Http\Requests\Compliance\RevokePublicationOverrideRequest $request, CompliancePublicationOverride $override): RedirectResponse
    {
        $data = $request->validated();
        if ($override->revoked_at !== null) {
            return back()->withErrors(['revoke_reason' => 'This override was already revoked.']);
        }
        \Illuminate\Support\Facades\DB::transaction(function () use ($override, $data, $request) {
            $override->forceFill(['revoked_by' => $request->user()->id, 'revoked_at' => now(), 'revoke_reason' => $data['revoke_reason']])->save();
            ComplianceActionRecorded::dispatch('compliance_override_revoked', 'events', (string) $override->event_id,
                "Override #{$override->id} revoked. Reason: {$data['revoke_reason']}", $request->user(), null, (string) $override->event_id);
        });

        return back()->with('success', 'Override revoked. The requirement blocks publishing again.');
    }
}
