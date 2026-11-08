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

    public function submit(Request $request, string $id): RedirectResponse
    {
        $event = $this->events->findEvent($request->user(), $id);
        $data = $request->validate([
            'item_key' => ['required', 'string', 'max:160'],
            'reference_number' => ['required', 'string', 'max:120'],
            'issuing_authority' => ['required', 'string', 'max:255'],
            'expires_on' => ['nullable', 'date', 'after:today'],
            'note' => ['nullable', 'string', 'max:2000'],
            'document' => ['nullable', 'file', 'max:5120', 'mimes:pdf,jpg,jpeg,png', 'mimetypes:application/pdf,image/jpeg,image/png'],
        ]);
        // Only an item on this event's checklist that can take evidence.
        $item = collect($this->checklist->build(EventRequirementChecklist::factsFromEvent($event), $event)['sections'])
            ->pluck('items')->flatten(1)->firstWhere('key', $data['item_key']);
        if (!$item || !$item['can_submit']) {
            return back()->withErrors(['item_key' => 'That requirement is not on this event\'s checklist.']);
        }
        if ($item['evidence'] && $item['evidence']->status === 'pending_verification') {
            return back()->withErrors(['item_key' => 'This is being verified now. Wait for the result before submitting again.']);
        }

        $document = $request->file('document');
        $path = $document?->storeAs('compliance-evidence/' . $event->id, Str::uuid() . '.' . $document->guessExtension(), self::DOCUMENT_DISK);

        EventComplianceEvidence::create([
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
        ]);

        return redirect()->to(route('events.requirements', $event->id) . '#item-' . md5($item['key']))
            ->with('status', 'Submitted. An EventBite administrator will verify it; until then it shows as Submitted.');
    }

    /** The private document, to the event's managers and administrators who review evidence. */
    public function document(Request $request, EventComplianceEvidence $evidence): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user && $evidence->document_path, 404);
        if (!Gate::forUser($user)->allows(Admin::COMPLIANCE_EDIT)) {
            $this->events->findEvent($user, $evidence->event_id); // 404 unless the user manages this event
        }

        return Storage::disk(self::DOCUMENT_DISK)->download($evidence->document_path, $evidence->document_name ?: 'document', [
            'Content-Type' => $evidence->document_mime ?: 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function assertHost(Request $request): void
    {
        $user = $request->user();
        abort_unless($user && ($user->hasRole(\App\Enums\AccountType::EVENT_HOST) || $user->hasRole(\App\Enums\AccountType::SYSTEM_ADMIN)), 403);
    }
}
