<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Compliance\ComplianceJurisdiction;
use App\Models\Compliance\CompliancePolicyVersion;
use App\Models\PolicyAcceptance;
use App\Services\AuditLogService;
use App\Services\Compliance\PolicyCatalog;
use App\Support\AdminAuthorization as Admin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Version-controlled policies (CompliancePolicyVersionPolicy).
 *
 * A compliance manager drafts a version: a new version of an existing policy, or a new policy with its own unique
 * identifier. A system administrator publishes it. Publishing never overwrites anything: earlier versions stay,
 * marked retired, and every acknowledgement record keeps pointing at the version it accepted. When the new version
 * is mandatory and requires re-acknowledgement (a material change), the people it applies to are identified and
 * must acknowledge it at their next interaction; restricted actions wait until they do (RequirePolicyAcknowledgement).
 */
class CompliancePolicyController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', CompliancePolicyVersion::class);
        $catalog = app(PolicyCatalog::class);
        $acceptance = PolicyAcceptance::where('acceptance_status', 'accepted')
            ->selectRaw('policy_name, policy_version, count(distinct user_id) as people')->groupBy('policy_name', 'policy_version')->get()
            ->groupBy('policy_name');

        return view('admin.compliance-centre.policies', [
            'current' => $catalog->published(),
            'required' => collect($catalog->published())->map(fn ($v, $name) => $catalog->required($name)),
            'acceptance' => $acceptance,
            'history' => CompliancePolicyVersion::with(['drafter', 'publisher'])->orderBy('policy_name')->orderByDesc('id')->get()->groupBy('policy_name'),
            'drafts' => CompliancePolicyVersion::with('drafter')->where('status', 'draft')->latest()->get(),
            'upcoming' => $catalog->upcoming(),
            'affected' => ['account' => PolicyCatalog::affectedCount('account'), 'host' => PolicyCatalog::affectedCount('host')],
            'stateFilter' => '',
            'states' => ComplianceJurisdiction::where('type', 'state')->orderBy('name')->get(['code', 'name']),
            'canIdentity' => Gate::allows('viewAny', \App\Models\HostIdentityVerification::class),
            'canRecords' => Gate::allows(Admin::COMPLIANCE_RECORDS),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', CompliancePolicyVersion::class);
        $current = app(PolicyCatalog::class)->published();
        $data = $request->validate([
            'policy_name' => ['nullable', 'string', 'max:120'],
            'identifier' => ['nullable', 'string', 'max:80', 'regex:/^[a-z][a-z0-9_]*$/'],
            'version' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9.\-]+$/'],
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::in(array_keys(CompliancePolicyVersion::CATEGORIES))],
            'body' => ['required', 'string', 'min:20', 'max:50000'],
            'change_summary' => ['required', 'string', 'min:10', 'max:2000'],
            'effective_at' => ['nullable', 'date', 'after_or_equal:today'],
            'applicable_roles' => ['required', 'array', 'min:1'],
            'applicable_roles.*' => [Rule::in(array_keys(CompliancePolicyVersion::ROLES))],
            'applicable_jurisdictions' => ['nullable', 'array'],
            'applicable_jurisdictions.*' => ['string', 'regex:/^[A-Za-z]{2}$/'],
            'acknowledgement_mandatory' => ['nullable', 'boolean'],
            'requires_reacknowledgement' => ['nullable', 'boolean'],
        ], [
            'change_summary.required' => 'Say what changed, so the people accepting it know.',
            'applicable_roles.required' => 'Choose who the policy applies to.',
            'identifier.regex' => 'Use lower-case letters, numbers and underscores, starting with a letter.',
        ]);

        // A new version of an existing policy, or a new policy with its own unique identifier.
        $existing = $data['policy_name'] ?? null;
        if ($existing && !CompliancePolicyVersion::where('policy_name', $existing)->exists()) {
            return back()->withInput()->withErrors(['policy_name' => 'Choose a policy from the list.']);
        }
        $name = $existing ?: (($data['identifier'] ?? null) ?: 'policy_' . Str::slug($data['title'], '_'));
        if (!$existing && CompliancePolicyVersion::where('policy_name', $name)->exists()) {
            return back()->withInput()->withErrors(['identifier' => "The identifier \"{$name}\" is already used by another policy. Choose that policy to add a version, or give a new identifier."]);
        }
        $wasPerEvent = $existing && ($current[$existing] ?? null)?->category === PolicyCatalog::PER_EVENT_CATEGORY;
        if (($data['category'] === PolicyCatalog::PER_EVENT_CATEGORY) !== $wasPerEvent) {
            return back()->withInput()->withErrors(['category' => 'Event compliance declarations keep their category, and no other policy can use it.']);
        }
        if (CompliancePolicyVersion::where('policy_name', $name)->where('version', $data['version'])->exists()) {
            return back()->withInput()->withErrors(['version' => "Version {$data['version']} already exists for this policy. Versions are never overwritten: give the new version a new number."]);
        }
        $roles = array_values(array_unique($data['applicable_roles']));

        $version = CompliancePolicyVersion::create([
            'policy_name' => $name,
            'policy_group' => in_array('event_host', $roles, true) && !in_array('all', $roles, true) ? 'host' : 'account',
            'version' => $data['version'],
            'title' => $data['title'],
            'category' => $data['category'],
            'body' => $data['body'],
            'change_summary' => $data['change_summary'],
            'effective_at' => $data['effective_at'] ?? null,
            'applicable_roles' => $roles,
            'applicable_jurisdictions' => array_values(array_unique(array_map('strtoupper', $data['applicable_jurisdictions'] ?? []))),
            'acknowledgement_mandatory' => (bool) ($data['acknowledgement_mandatory'] ?? false),
            'requires_reacknowledgement' => (bool) ($data['requires_reacknowledgement'] ?? false),
            'status' => 'draft',
            'source' => 'admin',
            'drafted_by' => $request->user()->id,
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
            // Earlier versions are kept, marked retired; one with a later effective date leaves them in force until then.
            CompliancePolicyVersion::where('policy_name', $version->policy_name)->where('status', 'published')->get()
                ->each(fn (CompliancePolicyVersion $old) => $old->update(['status' => 'retired']));
            $version->forceFill(['status' => 'published', 'published_by' => $request->user()->id, 'published_at' => now(),
                'effective_at' => $version->effective_at ?? now()])->save();
        });

        app()->forgetScopedInstances();
        $catalog = app(PolicyCatalog::class);
        $mustAccept = $version->acknowledgement_mandatory && $version->requires_reacknowledgement;
        $affected = $mustAccept ? $catalog->affectedUsers($version)->count() : 0;
        $when = $version->effective_at->isFuture() ? 'from ' . $version->effective_at->format('j M Y') : 'now';
        AuditLogService::log(action: 'compliance_policy_published', targetEntity: 'compliance_policy_versions', targetId: (string) $version->id,
            details: "Published {$version->policy_name} version {$version->version} ({$version->title}), in force {$when}; "
                . ($mustAccept ? "{$affected} people must acknowledge it" : 'no re-acknowledgement required') . ". Changes: {$version->change_summary}",
            actor: $request->user());

        return back()->with('success', "Published {$version->title} version {$version->version}, in force {$when}. "
            . ($mustAccept ? "{$affected} " . Str::plural('person', $affected) . ' must acknowledge it at their next visit; restricted actions wait until they do.'
                : 'Earlier acknowledgements still count, so nobody has to accept it again.'));
    }

    /** CSV of the people a version applies to who have not acknowledged it. */
    public function affected(Request $request, CompliancePolicyVersion $version): StreamedResponse
    {
        Gate::authorize('viewAny', CompliancePolicyVersion::class);
        Gate::authorize(Admin::COMPLIANCE_EXPORT);
        $catalog = app(PolicyCatalog::class);
        $users = $catalog->affectedUsers($version);
        AuditLogService::log(action: 'compliance_report_exported', targetEntity: 'compliance_policy_versions', targetId: (string) $version->id,
            details: "Exported people who must acknowledge {$version->policy_name} version {$version->version}.", actor: $request->user());

        return response()->streamDownload(function () use ($users, $version, $catalog) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Name', 'Email', 'Policy', 'Version', 'Status']);
            foreach ($users as $user) {
                fputcsv($out, array_map([ComplianceExportController::class, 'safe'], [$user->name, $user->email, $version->policy_name, $version->version,
                    $catalog->state($user, $version->policy_name) === 'updated' ? 'Accepted an earlier version' : 'Not accepted']));
            }
            fclose($out);
        }, "policy-{$version->policy_name}-{$version->version}-to-acknowledge.csv", ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store']);
    }

    public function destroy(Request $request, CompliancePolicyVersion $version): RedirectResponse
    {
        Gate::authorize('delete', $version);
        $version->delete();

        return back()->with('success', 'Draft deleted.');
    }
}
