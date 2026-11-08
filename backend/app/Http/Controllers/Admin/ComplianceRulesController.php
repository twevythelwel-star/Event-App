<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Compliance\ComplianceEventRule;
use App\Models\Compliance\ComplianceJurisdiction;
use App\Models\Compliance\ComplianceRecordReview;
use App\Models\Compliance\ComplianceRequirement;
use App\Models\EventComplianceEvidence;
use App\Services\Compliance\ComplianceReviewWorkflow;
use App\Support\AdminAuthorization as Admin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Administrators configure event-checklist rules per jurisdiction (requirements, exceptions and additional
 * local requirements) and review what hosts submit for checklist items.
 */
class ComplianceRulesController extends Controller
{
    public function store(Request $request, ComplianceJurisdiction $jurisdiction): RedirectResponse
    {
        Gate::authorize(Admin::COMPLIANCE_EDIT);
        $data = $request->validate([
            'topic' => ['required', Rule::in([...array_keys(ComplianceRequirement::TOPICS), 'local'])],
            'title' => ['required_if:topic,local', 'nullable', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'effect' => ['required', Rule::in(array_keys(ComplianceEventRule::EFFECTS))],
            'location' => ['required', Rule::in(array_keys(ComplianceEventRule::LOCATIONS))],
            'include_descendants' => ['nullable', 'boolean'],
            'event_types' => ['nullable', 'array'], 'event_types.*' => ['string', 'max:128'],
            'food_modes' => ['nullable', 'array'], 'food_modes.*' => [Rule::in(array_keys(ComplianceEventRule::FOOD_MODES))],
            'alcohol_modes' => ['nullable', 'array'], 'alcohol_modes.*' => [Rule::in(array_keys(ComplianceEventRule::ALCOHOL_MODES))],
            'caterer' => ['nullable', Rule::in(['any', 'yes', 'no'])],
            'alcohol_vendor' => ['nullable', Rule::in(['any', 'yes', 'no'])],
            'requirement_id' => ['nullable', Rule::exists('compliance_requirements', 'id')->where('jurisdiction_id', $jurisdiction->id)],
            'source_url' => ['required', 'url:https', 'max:500'],
            'source_title' => ['nullable', 'string', 'max:255'],
            'source_confirmed_official' => ['nullable', 'boolean'],
        ]);
        if ($data['effect'] === 'not_required' && $data['topic'] === 'local') {
            return back()->withErrors(['effect' => 'An additional local requirement is always required; add an exception to a standard topic instead.'])->withInput();
        }
        if (!ComplianceReviewWorkflow::isOfficialUrl($data['source_url']) && !$request->boolean('source_confirmed_official')) {
            return back()->withErrors(['source_url' => 'Cite an official government source (.gov, .mil or .us), or confirm it is the agency\'s own official site.'])->withInput();
        }
        $tri = fn (?string $v) => match ($v) { 'yes' => true, 'no' => false, default => null };
        $conditions = array_filter([
            'event_types' => array_values(array_filter($data['event_types'] ?? [])),
            'food_modes' => array_values($data['food_modes'] ?? []),
            'alcohol_modes' => array_values($data['alcohol_modes'] ?? []),
        ]) + array_filter(['caterer' => $tri($data['caterer'] ?? null), 'alcohol_vendor' => $tri($data['alcohol_vendor'] ?? null)], fn ($v) => $v !== null);

        $rule = ComplianceEventRule::create([
            'jurisdiction_id' => $jurisdiction->id,
            'include_descendants' => $request->boolean('include_descendants'),
            'topic' => $data['topic'],
            'title' => $data['topic'] === 'local' ? $data['title'] : null,
            'description' => $data['description'] ?? null,
            'effect' => $data['effect'],
            'location' => $data['location'],
            'conditions' => $conditions ?: null,
            'requirement_id' => $data['requirement_id'] ?? null,
            'source_url' => $data['source_url'],
            'source_title' => $data['source_title'] ?? null,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);
        $this->log($rule, 'created', $request, 'Rule added: ' . $rule->label() . ' (' . strtolower(ComplianceEventRule::EFFECTS[$rule->effect]) . ') for ' . $rule->conditionSummary() . '.');

        return redirect()->to(route('admin.console.compliance.jurisdictions.show', $jurisdiction) . '#event-rules')->with('success', 'Rule added.');
    }

    public function toggle(Request $request, ComplianceEventRule $rule): RedirectResponse
    {
        Gate::authorize(Admin::COMPLIANCE_EDIT);
        $rule->forceFill(['is_active' => !$rule->is_active, 'updated_by' => $request->user()->id])->save();
        $this->log($rule, $rule->is_active ? 'reactivated' : 'deactivated', $request, null);

        return redirect()->to(route('admin.console.compliance.jurisdictions.show', $rule->jurisdiction_id) . '#event-rules')
            ->with('success', $rule->is_active ? 'Rule turned on.' : 'Rule turned off.');
    }

    public function evidence(Request $request): View
    {
        Gate::authorize('viewAny', EventComplianceEvidence::class);
        $status = (string) $request->query('status', 'open');
        $kind = (string) $request->query('kind', 'all');
        $state = strtolower(substr(preg_replace('/[^A-Za-z]/', '', (string) $request->query('state', '')), 0, 2));

        $query = EventComplianceEvidence::with(['event', 'submitter', 'reviewer'])
            ->whereIn('event_id', Admin::applyOrganizerScope(\App\Models\Event::allTenants(), $request->user())->select('id'))
            ->when($status === 'open', fn ($q) => $q->whereIn('status', ['submitted', 'pending_verification']))
            ->when($status !== 'open' && array_key_exists($status, EventComplianceEvidence::STATUSES), fn ($q) => $q->where('status', $status));
        self::filterKind($query, $kind);
        if ($state !== '') {
            // Checklist keys end in "{type}:{state}/{place}", e.g. "event:food_permit:county:fl/miami-dade".
            $query->where('item_key', 'like', "%:{$state}/%");
        }

        return view('admin.compliance.evidence', [
            'status' => $status,
            'kind' => $kind,
            'state' => strtoupper($state),
            'stateFilter' => strtoupper($state),
            'canIdentity' => Gate::allows('viewAny', \App\Models\HostIdentityVerification::class),
            'canRecords' => Gate::allows(Admin::COMPLIANCE_RECORDS),
            'states' => \App\Models\Compliance\ComplianceJurisdiction::where('type', 'state')->orderBy('name')->get(['code', 'name']),
            'items' => $query->latest('submitted_at')->paginate(30)->withQueryString(),
        ]);
    }

    /** Food permits or alcohol licences, by the checklist topic and location in the item key. */
    public static function filterKind($query, string $kind)
    {
        if ($kind === 'food') {
            $query->where(fn ($q) => $q->where('item_key', 'like', '%:food\_%')->orWhere('item_key', 'like', '%:temporary_food_event:%')
                ->orWhere('item_key', 'like', 'event_food:%')->orWhere('item_key', 'like', 'food_preparation:%'));
        } elseif ($kind === 'alcohol') {
            $query->where(fn ($q) => $q->where('item_key', 'like', 'alcohol_service:%')->orWhere('item_key', 'like', '%:alcohol_licensing:%')
                ->orWhere('item_key', 'like', '%:minimum_drinking_age:%'));
        }

        return $query;
    }

    public function review(\App\Http\Requests\Compliance\ReviewComplianceEvidenceRequest $request, EventComplianceEvidence $evidence, string $action): RedirectResponse
    {
        abort_unless(Admin::organizerInScope($request->user(), $evidence->event?->organizer_id), 403);
        $data = $request->validated();
        $allowed = ['start' => ['submitted'], 'verify' => ['submitted', 'pending_verification'], 'reject' => ['submitted', 'pending_verification']];
        if (!in_array($evidence->status, $allowed[$action], true)) {
            return back()->withErrors(['evidence' => 'This submission has already been reviewed.']);
        }
        if ($action !== 'start' && $evidence->submitted_by === $request->user()->id) {
            return back()->withErrors(['evidence' => 'You submitted this, so another administrator must review it.']);
        }
        // The decision and its review record are saved together; the audit entry and the host's email follow the commit.
        \Illuminate\Support\Facades\DB::transaction(function () use ($evidence, $action, $data, $request) {
            $evidence->forceFill([
                'status' => ['start' => 'pending_verification', 'verify' => 'verified', 'reject' => 'rejected'][$action],
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'review_comment' => $data['comment'] ?? $evidence->review_comment,
            ])->save();
            ComplianceRecordReview::create(['record_type' => 'evidence', 'record_id' => $evidence->id, 'action' => $action,
                'to_status' => $evidence->status, 'user_id' => $request->user()->id, 'comment' => $data['comment'] ?? null,
                'snapshot' => $evidence->only(['event_id', 'item_key', 'reference_number', 'issuing_authority', 'expires_on'])]);
            \App\Events\Compliance\ComplianceEvidenceReviewed::dispatch($evidence, $request->user(), $action, $data['comment'] ?? null);
        });
        return back()->with('success', ['start' => 'Marked Pending Verification.', 'verify' => 'Verified.', 'reject' => 'Rejected; the host will see your comment.'][$action]);
    }

    private function log(ComplianceEventRule $rule, string $action, Request $request, ?string $comment): void
    {
        ComplianceRecordReview::create(['record_type' => 'rule', 'record_id' => $rule->id, 'action' => $action,
            'user_id' => $request->user()->id, 'comment' => $comment, 'snapshot' => $rule->only($rule->getFillable())]);
    }
}
