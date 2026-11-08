<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Compliance\ComplianceAuthority;
use App\Models\Compliance\ComplianceJurisdiction;
use App\Models\Compliance\ComplianceRecordReview;
use App\Models\Compliance\ComplianceRequirement;
use App\Services\Compliance\ComplianceReviewWorkflow;
use App\Support\AdminAuthorization as Admin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The compliance administrator's console for the US state and local compliance database: the jurisdiction
 * hierarchy, each jurisdiction's authorities and requirement records, and the review workflow
 * (ComplianceReviewWorkflow) that turns researched records into verified ones.
 */
class ComplianceDatabaseController extends Controller
{
    public function __construct(private readonly ComplianceReviewWorkflow $workflow)
    {
    }

    public function index(Request $request): View
    {
        Gate::authorize(Admin::COMPLIANCE_VIEW);
        $search = trim((string) $request->query('q', ''));

        $count = fn (string $class) => $class::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $states = ComplianceJurisdiction::where('type', 'state')->orderBy('name')
            ->withCount([
                'children as counties_count' => fn ($q) => $q->where('type', 'county'),
                'authorities',
                'requirements as verified_requirements_count' => fn ($q) => $q->where('status', 'verified'),
                'requirements as open_requirements_count' => fn ($q) => $q->whereIn('status', ['verification_required', 'draft', 'changes_requested', 'review_due']),
            ])->get();

        return view('admin.compliance.index', [
            'country' => ComplianceJurisdiction::where('type', 'country')->where('code', 'US')->first(),
            'states' => $states,
            'authorityCounts' => $count(ComplianceAuthority::class),
            'requirementCounts' => $count(ComplianceRequirement::class),
            'pending' => $this->queue('pending_review'),
            'due' => $this->queue('review_due'),
            'search' => $search,
            'results' => $search === '' ? collect() : ComplianceJurisdiction::with('parent')
                ->where('name', 'like', '%' . addcslashes($search, '%_') . '%')->orderBy('name')->limit(40)->get(),
            'totals' => ComplianceJurisdiction::query()->selectRaw('type, count(*) as total')->groupBy('type')->pluck('total', 'type'),
        ]);
    }

    public function show(Request $request, ComplianceJurisdiction $jurisdiction): View
    {
        Gate::authorize(Admin::COMPLIANCE_VIEW);
        $jurisdiction->load(['authorities' => fn ($q) => $q->orderBy('area')->orderBy('name'), 'requirements.authority']);
        $childSearch = trim((string) $request->query('q', ''));
        $children = $jurisdiction->children()
            ->when($childSearch !== '', fn ($q) => $q->where('name', 'like', '%' . addcslashes($childSearch, '%_') . '%'))
            ->withCount(['requirements as verified_requirements_count' => fn ($q) => $q->where('status', 'verified')])
            ->orderBy('type')->orderBy('name')->paginate(50)->withQueryString();
        // Authorities a requirement may cite: this jurisdiction's and those above it.
        $lineageIds = collect($jurisdiction->lineage())->pluck('id');

        return view('admin.compliance.jurisdiction', [
            'jurisdiction' => $jurisdiction,
            'lineage' => $jurisdiction->lineage(),
            'children' => $children,
            'childSearch' => $childSearch,
            'requirements' => $jurisdiction->requirements->keyBy('topic'),
            'citableAuthorities' => ComplianceAuthority::whereIn('jurisdiction_id', $lineageIds)->orderBy('name')->get(),
            'history' => $this->history($jurisdiction),
            'rules' => \App\Models\Compliance\ComplianceEventRule::where('jurisdiction_id', $jurisdiction->id)->orderByDesc('is_active')->latest()->get(),
            // The event form's categories, which rules can be limited to.
            'eventTypes' => ['Community', 'Food', 'Music', 'Culture', 'Sports', 'Fundraiser', 'Market', 'Workshop', 'Other'],
            'canEdit' => Gate::allows(Admin::COMPLIANCE_EDIT),
            'canApprove' => Gate::allows(Admin::COMPLIANCE_APPROVE),
        ]);
    }

    /** Opens research on a jurisdiction: one "Verification Required" record per requirement topic. */
    public function startResearch(Request $request, ComplianceJurisdiction $jurisdiction): RedirectResponse
    {
        Gate::authorize(Admin::COMPLIANCE_EDIT);
        $created = 0;
        foreach (array_keys(ComplianceRequirement::TOPICS) as $topic) {
            $record = ComplianceRequirement::firstOrCreate(['jurisdiction_id' => $jurisdiction->id, 'topic' => $topic], ['status' => 'verification_required']);
            if ($record->wasRecentlyCreated) {
                $created++;
                ComplianceRecordReview::create(['record_type' => 'requirement', 'record_id' => $record->id, 'action' => 'created',
                    'to_status' => 'verification_required', 'user_id' => $request->user()->id, 'comment' => 'Research opened.']);
            }
        }

        return back()->with('success', $created ? "Research opened: {$created} requirement records to verify." : 'Every requirement topic already has a record.');
    }

    public function storeAuthority(Request $request, ComplianceJurisdiction $jurisdiction): RedirectResponse
    {
        Gate::authorize(Admin::COMPLIANCE_EDIT);
        $data = $request->validate($this->rules('authority', $jurisdiction));
        $authority = ComplianceAuthority::create(['jurisdiction_id' => $jurisdiction->id, 'status' => 'draft'] + $this->normalise($request, $data));
        ComplianceRecordReview::create(['record_type' => 'authority', 'record_id' => $authority->id, 'action' => 'created',
            'to_status' => 'draft', 'user_id' => $request->user()->id, 'snapshot' => $authority->only($authority->getFillable())]);

        return back()->with('success', "{$authority->name} added as a draft.");
    }

    public function update(Request $request, string $type, int $id): RedirectResponse
    {
        Gate::authorize(Admin::COMPLIANCE_EDIT);
        $record = $this->record($type, $id);
        $data = $request->validate($this->rules($type, $record->jurisdiction));
        $this->workflow->edit($record, $this->normalise($request, $data), $request->user());

        return back()->with('success', 'Saved. ' . ($record->status === 'draft' ? 'Submit it for review when the source is confirmed.' : ''));
    }

    public function transition(Request $request, string $type, int $id, string $action): RedirectResponse
    {
        $record = $this->record($type, $id);
        Gate::authorize($action === 'approve' ? Admin::COMPLIANCE_APPROVE : Admin::COMPLIANCE_EDIT);
        $request->validate(['comment' => ['nullable', 'string', 'max:2000']]);
        $comment = (string) $request->input('comment', '');
        try {
            match ($action) {
                'submit' => $this->workflow->submit($record, $request->user(), $comment),
                'approve' => $this->workflow->approve($record, $request->user(), $comment),
                'request-changes' => $this->workflow->requestChanges($record, $request->user(), $comment),
                'reset' => $this->workflow->markVerificationRequired($record, $request->user(), $comment),
            };
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(["record-{$type}-{$id}" => $e->getMessage()])->withInput();
        }

        return back()->with('success', match ($action) {
            'submit' => 'Submitted for review.',
            'approve' => 'Approved: the record is verified.',
            'request-changes' => 'Sent back with your comments.',
            'reset' => 'Marked "Verification Required".',
        });
    }

    private function record(string $type, int $id): ComplianceAuthority|ComplianceRequirement
    {
        return match ($type) {
            'authority' => ComplianceAuthority::findOrFail($id),
            'requirement' => ComplianceRequirement::findOrFail($id),
            default => abort(404),
        };
    }

    private function rules(string $type, ComplianceJurisdiction $jurisdiction): array
    {
        $url = ['nullable', 'url:https,http', 'max:500'];
        $common = [
            'source_url' => $url, 'source_title' => ['nullable', 'string', 'max:255'], 'source_confirmed_official' => ['nullable', 'boolean'],
            'effective_date' => ['nullable', 'date'], 'last_verified_date' => ['nullable', 'date', 'before_or_equal:today'],
            'next_review_date' => ['nullable', 'date', 'after:today'],
            'research_notes' => ['nullable', 'string', 'max:5000'], 'admin_notes' => ['nullable', 'string', 'max:5000'],
        ];

        return $common + ($type === 'authority' ? [
            'area' => ['required', Rule::in(array_keys(ComplianceAuthority::AREAS))],
            'name' => ['required', 'string', 'max:255'], 'role' => ['required', 'string', 'max:255'],
            'website_url' => $url, 'legislation_url' => $url, 'legislation_title' => ['nullable', 'string', 'max:255'],
        ] : [
            'authority_id' => ['nullable', Rule::exists('compliance_authorities', 'id')->whereIn('jurisdiction_id', collect($jurisdiction->lineage())->pluck('id')->all())],
            'summary' => ['nullable', 'string', 'max:5000'], 'value' => ['nullable', 'string', 'max:120'],
            'conditions' => ['nullable', 'string', 'max:5000'], 'required_documents' => ['nullable', 'string', 'max:5000'],
            'legislation_citation' => ['nullable', 'string', 'max:255'],
        ]);
    }

    private function normalise(Request $request, array $data): array
    {
        if ($request->has('source_confirmed_official_submitted')) {
            $data['source_confirmed_official'] = $request->boolean('source_confirmed_official');
        }

        return $data;
    }

    private function queue(string $status)
    {
        $load = fn (string $class) => $class::with('jurisdiction')->where('status', $status)->latest('updated_at')->limit(50)->get();

        return $load(ComplianceAuthority::class)->concat($load(ComplianceRequirement::class))->sortByDesc('updated_at')->values();
    }

    private function history(ComplianceJurisdiction $jurisdiction)
    {
        $ids = fn (string $type) => ($type === 'authority' ? $jurisdiction->authorities : $jurisdiction->requirements)->pluck('id');

        return ComplianceRecordReview::with('user')
            ->where(fn ($q) => $q->where(fn ($q) => $q->where('record_type', 'authority')->whereIn('record_id', $ids('authority')))
                ->orWhere(fn ($q) => $q->where('record_type', 'requirement')->whereIn('record_id', $ids('requirement'))))
            ->latest('id')->limit(30)->get();
    }
}
