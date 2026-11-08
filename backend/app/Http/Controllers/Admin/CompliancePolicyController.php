<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Compliance\CompliancePolicyVersion;
use App\Models\PolicyAcceptance;
use App\Services\AuditLogService;
use App\Services\Compliance\PolicyCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Policy versions and new acknowledgements (CompliancePolicyVersionPolicy).
 *
 * A compliance manager drafts a new version of a policy, or a new acknowledgement. A system administrator publishes
 * it: it becomes the current version (PolicyCatalog), the version it replaces is retired, and everyone it applies to
 * must accept it. For a host acknowledgement, a host who has not accepted it cannot publish events.
 */
class CompliancePolicyController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', CompliancePolicyVersion::class);
        $catalog = app(PolicyCatalog::class);
        $current = $catalog->all();
        $acceptance = PolicyAcceptance::where('acceptance_status', 'accepted')
            ->selectRaw('policy_name, policy_version, count(distinct user_id) as people')->groupBy('policy_name', 'policy_version')->get()
            ->groupBy('policy_name');

        return view('admin.compliance-centre.policies', [
            'current' => $current,
            'acceptance' => $acceptance,
            'versions' => CompliancePolicyVersion::with(['drafter', 'publisher'])->latest()->get(),
            'affected' => ['account' => PolicyCatalog::affectedCount('account'), 'host' => PolicyCatalog::affectedCount('host')],
            'stateFilter' => '',
            'states' => collect(),
            'canIdentity' => Gate::allows('viewAny', \App\Models\HostIdentityVerification::class),
            'canRecords' => Gate::allows(\App\Support\AdminAuthorization::COMPLIANCE_RECORDS),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', CompliancePolicyVersion::class);
        $current = app(PolicyCatalog::class)->all();
        $data = $request->validate([
            'policy_name' => ['nullable', 'string', 'max:120'],
            'policy_group' => ['required', Rule::in(array_keys(CompliancePolicyVersion::GROUPS))],
            'version' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9.\-]+$/'],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'min:20', 'max:20000'],
            'change_summary' => ['required', 'string', 'min:10', 'max:2000'],
        ], ['change_summary.required' => 'Say what changed, so the people accepting it know.']);

        // An existing policy, or a new acknowledgement named from its title.
        $name = ($data['policy_name'] ?? null) ?: 'policy_' . Str::slug($data['title'], '_');
        if (isset($current[$name])) {
            if ($current[$name]['group'] !== $data['policy_group']) {
                return back()->withInput()->withErrors(['policy_group' => 'This policy belongs to the ' . $current[$name]['group'] . ' group.']);
            }
            if ($current[$name]['version'] === $data['version']) {
                return back()->withInput()->withErrors(['version' => "Version {$data['version']} is already the current version. Give the new version a new number."]);
            }
        }
        if (CompliancePolicyVersion::where('policy_name', $name)->where('version', $data['version'])->exists()) {
            return back()->withInput()->withErrors(['version' => 'That version already exists for this policy.']);
        }

        $version = CompliancePolicyVersion::create([
            'policy_name' => $name, 'policy_group' => $data['policy_group'], 'version' => $data['version'], 'title' => $data['title'],
            'body' => $data['body'], 'change_summary' => $data['change_summary'], 'status' => 'draft', 'drafted_by' => $request->user()->id,
        ]);
        AuditLogService::log(action: 'compliance_policy_drafted', targetEntity: 'compliance_policy_versions', targetId: (string) $version->id,
            details: "Drafted {$name} version {$version->version}: {$version->title}", actor: $request->user());

        return redirect()->route('admin.console.compliance-centre.policies')->with('success', "Draft saved: {$version->title} version {$version->version}. Nothing changes until a system administrator publishes it.");
    }

    public function publish(Request $request, CompliancePolicyVersion $version): RedirectResponse
    {
        Gate::authorize('publish', $version);
        $request->validate(['confirm' => ['accepted']], ['confirm.accepted' => 'Tick the box to confirm you understand who must accept it.']);

        DB::transaction(function () use ($version, $request) {
            CompliancePolicyVersion::where('policy_name', $version->policy_name)->where('status', 'published')->update(['status' => 'retired']);
            $version->forceFill(['status' => 'published', 'published_by' => $request->user()->id, 'published_at' => now()])->save();
        });
        $affected = PolicyCatalog::affectedCount($version->policy_group);
        AuditLogService::log(action: 'compliance_policy_published', targetEntity: 'compliance_policy_versions', targetId: (string) $version->id,
            details: "Published {$version->policy_name} version {$version->version} ({$version->title}); {$affected} people must accept it. Changes: {$version->change_summary}",
            actor: $request->user());

        return back()->with('success', "Published {$version->title} version {$version->version}. "
            . ($version->policy_group === 'host' ? "{$affected} hosts must accept it before they can publish events again." : 'Every user must accept it.'));
    }

    public function destroy(Request $request, CompliancePolicyVersion $version): RedirectResponse
    {
        Gate::authorize('delete', $version);
        $version->delete();

        return back()->with('success', 'Draft deleted.');
    }
}
