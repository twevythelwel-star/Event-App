<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Compliance\ComplianceJurisdiction;
use App\Models\Compliance\CompliancePolicyVersion;
use App\Services\Compliance\EventComplianceService;
use App\Services\Compliance\PolicyCatalog;
use App\Support\LegalPages;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The public legal and policy pages (LegalPages). Each shows the version of its policy in force, its effective and
 * publication dates, and its earlier versions, which stay readable. Every page says it is general information, not
 * legal advice, and while the wording awaits legal review, that it is draft wording.
 */
class LegalPageController extends Controller
{
    public function index(): View
    {
        return view('legal.index', ['pages' => LegalPages::PAGES]);
    }

    public function show(Request $request, string $slug, PolicyCatalog $catalog): View
    {
        abort_unless(isset(LegalPages::PAGES[$slug]) && $slug !== 'jurisdictions', 404);
        $page = LegalPages::PAGES[$slug];
        $history = CompliancePolicyVersion::where('policy_name', $page['policy'])->whereIn('status', ['published', 'retired'])
            ->orderByDesc('published_at')->orderByDesc('id')->get();
        $current = $catalog->current($page['policy']);
        // An earlier version stays readable at ?version=.
        $shown = $request->filled('version') ? $history->firstWhere('version', (string) $request->query('version')) : $current;
        abort_if($shown === null, 404);

        $related = [];
        if (!empty($page['related_host_acknowledgements'])) {
            $related['Host responsibility acknowledgements'] = collect($catalog->group('host'))->filter(fn ($s) => $s['mandatory'])
                ->map(fn ($s) => ['title' => $s['title'], 'text' => $s['description'], 'version' => $s['version']])->values()->all();
            $related['Event Compliance declarations (confirmed for each event)'] = collect(EventComplianceService::POLICY_NAMES)
                ->map(fn ($name) => $catalog->current($name))->filter()
                ->map(fn ($v) => ['title' => $v->title, 'text' => $v->body, 'version' => $v->version])->values()->all();
        }

        return view('legal.show', [
            'slug' => $slug,
            'page' => $page,
            'version' => $shown,
            'isCurrent' => $current && $shown->id === $current->id,
            'current' => $current,
            'upcoming' => $history->first(fn ($v) => $v->status === 'published' && $v->effective_at && $v->effective_at->isFuture()),
            'history' => $history->filter(fn ($v) => $v->isInForce()),
            'blocks' => LegalPages::blocks((string) $shown->body),
            'related' => $related,
        ]);
    }

    public function jurisdictions(): View
    {
        return view('legal.jurisdictions', [
            'page' => LegalPages::PAGES['jurisdictions'],
            'states' => ComplianceJurisdiction::where('type', 'state')->orderBy('name')->get(['id', 'code', 'name']),
        ]);
    }

    public function jurisdiction(string $state): View
    {
        $record = ComplianceJurisdiction::where('type', 'state')->where('code', strtoupper($state))->firstOrFail();

        return view('legal.jurisdiction', [
            'page' => LegalPages::PAGES['jurisdictions'],
            'state' => $record,
            'federal' => ComplianceJurisdiction::where('type', 'country')->with(['authorities', 'requirements'])->first(),
            'authorities' => $record->authorities()->orderBy('area')->get(),
            'requirements' => $record->requirements()->with('authority')->orderBy('topic')->get(),
        ]);
    }
}
