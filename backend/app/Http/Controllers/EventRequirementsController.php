<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Compliance\ComplianceEventRule;
use App\Models\Event;
use App\Models\EventComplianceEvidence;
use App\Services\Compliance\EventRequirementChecklist;
use App\Services\EventManagementService;
use App\Support\AdminAuthorization as Admin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The host's view of an event's jurisdiction-specific requirements (EventRequirementChecklist): a live
 * preview while the event form is filled in, the full checklist, and evidence (permit or licence details and
 * a document) for an administrator to verify. A host never marks an item verified.
 */
class EventRequirementsController extends Controller
{
    public const DOCUMENT_DISK = 'local';

    public function __construct(private readonly EventRequirementChecklist $checklist, private readonly EventManagementService $events)
    {
    }

    /** The checklist for what the form says right now (create and edit pages). */
    public function preview(Request $request): View
    {
        $this->assertHost($request);
        $input = $request->validate([
            'event_id' => ['nullable', 'string', 'max:64'],
            'category' => ['nullable', 'string', 'max:128'],
            'state_code' => ['nullable', 'string', 'max:2'],
            'city' => ['nullable', 'string', 'max:120'],
            'compliance_jurisdiction_county' => ['nullable', 'string', 'max:120'],
            'compliance_jurisdiction_municipality' => ['nullable', 'string', 'max:120'],
            'supports_food_ordering' => ['nullable', 'string', 'max:5'],
            'serves_alcohol' => ['nullable', 'string', 'max:5'],
            'food_service_mode' => ['nullable', Rule::in(array_keys(ComplianceEventRule::FOOD_MODES))],
            'alcohol_service_mode' => ['nullable', Rule::in(array_keys(ComplianceEventRule::ALCOHOL_MODES))],
            'uses_licensed_caterer' => ['nullable', 'string', 'max:5'],
            'uses_licensed_alcohol_vendor' => ['nullable', 'string', 'max:5'],
            'food_prepared_elsewhere' => ['nullable', 'string', 'max:5'],
            'food_prep_state_code' => ['nullable', 'string', 'max:2'],
            'food_prep_county' => ['nullable', 'string', 'max:120'],
            'food_prep_city' => ['nullable', 'string', 'max:120'],
            'alcohol_served_elsewhere' => ['nullable', 'string', 'max:5'],
            'alcohol_service_state_code' => ['nullable', 'string', 'max:2'],
            'alcohol_service_county' => ['nullable', 'string', 'max:120'],
            'alcohol_service_city' => ['nullable', 'string', 'max:120'],
        ]);
        $event = null;
        if (!empty($input['event_id'])) {
            try {
                $event = $this->events->findEvent($request->user(), $input['event_id']);
            } catch (\Throwable) {
                $event = null; // not this host's event: show the checklist without its evidence
            }
        }

        return view('events.partials.requirements-checklist', [
            'checklist' => $this->checklist->build(EventRequirementChecklist::facts($input), $event),
            'event' => $event,
            'interactive' => false,
        ]);
    }

    public function show(Request $request, string $id): View
    {
        $event = $this->events->findEvent($request->user(), $id);

        return view('events.requirements', [
            'event' => $event,
            'checklist' => $this->checklist->build(EventRequirementChecklist::factsFromEvent($event), $event),
        ]);
    }

    public function submit(\App\Http\Requests\Compliance\SubmitComplianceEvidenceRequest $request, string $id): RedirectResponse
    {
        $event = $this->events->findEvent($request->user(), $id);
        $data = $request->validated();
        // Only an item on this event's checklist that can take evidence.
        $item = collect($this->checklist->build(EventRequirementChecklist::factsFromEvent($event), $event)['sections'])
            ->pluck('items')->flatten(1)->firstWhere('key', $data['item_key']);
        if (!$item || !$item['can_submit']) {
            return back()->withErrors(['item_key' => 'That requirement is not on this event\'s checklist.']);
        }
        if ($item['evidence'] && $item['evidence']->status === 'pending_verification') {
            return back()->withErrors(['item_key' => 'This is being verified now. Wait for the result before submitting again.']);
        }

        // The file goes to private storage first; if recording the submission fails, the file is removed again.
        $document = $request->file('document');
        $path = $document?->storeAs('compliance-evidence/' . $event->id, Str::uuid() . '.' . $document->guessExtension(), self::DOCUMENT_DISK);

        try {
            $evidence = \Illuminate\Support\Facades\DB::transaction(fn () => EventComplianceEvidence::create([
            'event_id' => $event->id,
            'item_key' => $item['key'],
            'item_title' => $item['title'] . ' · ' . $item['level']['name'],
            'status' => 'submitted',
            'reference_number' => $data['reference_number'],
            'issuing_authority' => $data['issuing_authority'],
            'expires_on' => $data['expires_on'] ?? null,
            'note' => $data['note'] ?? null,
            'document_path' => $path ?: null,
            'document_name' => $document ? mb_substr($document->getClientOriginalName(), 0, 255) : null,
            'document_mime' => $document?->getMimeType(),
            'submitted_by' => $request->user()->id,
            'submitted_at' => now(),
        ]));
        } catch (\Throwable $e) {
            if ($path) {
                Storage::disk(self::DOCUMENT_DISK)->delete($path);
            }
            throw $e;
        }
        \App\Events\Compliance\ComplianceActionRecorded::dispatch('compliance_evidence_submitted', 'event_compliance_evidence', (string) $evidence->id,
            "{$evidence->item_title} submitted for event {$event->id}" . ($path ? ' with a document' : ''), $request->user(), (string) $event->organizer_id, (string) $event->id);

        return redirect()->to(route('events.requirements', $event->id) . '#item-' . md5($item['key']))
            ->with('status', 'Submitted. An EventBite administrator will verify it; until then it shows as Submitted.');
    }

    /**
     * The private document (EventComplianceEvidencePolicy::viewDocument): the event's hosts and compliance managers
     * whose scope covers the event. Every download is audited.
     */
    public function document(Request $request, EventComplianceEvidence $evidence): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user && $evidence->document_path, 404);
        abort_unless(Gate::forUser($user)->allows('viewDocument', $evidence), 404);
        abort_unless(Storage::disk(self::DOCUMENT_DISK)->exists($evidence->document_path), 404);
        \App\Events\Compliance\ComplianceActionRecorded::dispatch('compliance_document_downloaded', 'event_compliance_evidence', (string) $evidence->id,
            "Downloaded the document for {$evidence->item_title} (event {$evidence->event_id}).", $user, $evidence->event?->organizer_id, (string) $evidence->event_id);

        return Storage::disk(self::DOCUMENT_DISK)->download($evidence->document_path, $evidence->document_name ?: 'document', [
            'Content-Type' => $evidence->document_mime ?: 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /** A host withdraws a submission before review starts. The record is kept (soft deleted) for the audit trail. */
    public function withdraw(Request $request, EventComplianceEvidence $evidence): RedirectResponse
    {
        Gate::authorize('withdraw', $evidence);
        \Illuminate\Support\Facades\DB::transaction(fn () => $evidence->delete());
        \App\Events\Compliance\ComplianceActionRecorded::dispatch('compliance_evidence_withdrawn', 'event_compliance_evidence', (string) $evidence->id,
            "{$evidence->item_title} withdrawn by the host before review.", $request->user(), $evidence->event?->organizer_id, (string) $evidence->event_id);

        return redirect()->route('events.requirements', $evidence->event_id)->with('status', 'Submission withdrawn. You can submit it again.');
    }

    private function assertHost(Request $request): void
    {
        $user = $request->user();
        abort_unless($user && ($user->hasRole(\App\Enums\AccountType::EVENT_HOST) || $user->hasRole(\App\Enums\AccountType::SYSTEM_ADMIN)), 403);
    }
}
