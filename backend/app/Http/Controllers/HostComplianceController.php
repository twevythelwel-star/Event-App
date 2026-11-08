<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\AccountType;
use App\Models\PolicyAcceptance;
use App\Models\User;
use App\Services\Compliance\HostComplianceOverview;
use App\Services\HostIdentityVerificationService;
use App\Services\LegalComplianceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * The event host's "Compliance and verification" page: identity, account standing, permits and licences,
 * expirations, events needing information, outstanding acknowledgements, policy updates and review messages.
 */
class HostComplianceController extends Controller
{
    public function __construct(private readonly HostComplianceOverview $overview)
    {
    }

    public function index(Request $request): View|JsonResponse|RedirectResponse
    {
        $user = $request->user();
        if (!$this->isHost($user)) {
            return $request->wantsJson()
                ? response()->json(['message' => 'Only event hosts have a compliance area.'], 403)
                : redirect()->route('host.apply')->with('error', 'Apply to become an event host first.');
        }

        $overview = $this->overview->for($user);
        if ($request->wantsJson()) {
            return response()->json($this->forJson($overview));
        }

        return view('host.compliance', $overview + [
            'documentTypes' => HostIdentityVerificationService::supportedDocumentTypes(),
        ]);
    }

    /**
     * Accept updated or outstanding policies. Each policy is accepted only when its own box is ticked, and each is
     * recorded separately at its current version with the time, IP address and browser.
     */
    public function acceptPolicies(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->isHost($user), 403);

        $specs = collect(app(\App\Services\Compliance\PolicyCatalog::class)->all());
        $data = $request->validate(['policies' => ['required', 'array', 'min:1'], 'policies.*' => ['string']], [
            'policies.required' => 'Tick each policy you have read and accept.',
        ]);
        $unknown = array_diff($data['policies'], $specs->keys()->all());
        if ($unknown !== []) {
            throw ValidationException::withMessages(['policies' => 'One of the policies is not a current EventBite policy. Reload the page and try again.']);
        }

        // One append-only record per policy, linked to the exact version accepted (PolicyCatalog::record).
        $recorded = app(\App\Services\Compliance\PolicyCatalog::class)->record($user, $data['policies'], 'policy_update', $request);

        return redirect()->to(route('host.compliance') . '#policies')
            ->with('success', $recorded === 0 ? 'Those policies were already accepted.' : "Recorded your acceptance of {$recorded} " . str('policy')->plural($recorded) . '.');
    }

    private function isHost(User $user): bool
    {
        return $user->hasRole(AccountType::EVENT_HOST);
    }

    /** The overview without models' internal fields, for the JSON API. */
    private function forJson(array $o): array
    {
        $record = $o['identity']['record'];
        $o['identity']['record'] = $record ? [
            'status' => $record->status,
            'document_type' => $record->documentTypeLabel(),
            'document_expires_at' => $record->document_expires_at?->toDateString(),
            'submitted_at' => $record->submitted_at_utc?->toIso8601String(),
        ] : null;
        $event = fn ($row) => ['id' => $row['event']->id, 'title' => $row['event']->title, 'status' => $row['event']->status] + array_diff_key($row, ['event' => 1]);
        $o['events'] = array_map($event, $o['events']);
        $o['events_needing_information'] = array_map($event, $o['events_needing_information']);
        $o['documents'] = array_map(fn ($d) => [
            'event_id' => $d['evidence']->event_id,
            'title' => $d['evidence']->item_title,
            'reference_number' => $d['evidence']->reference_number,
            'issuing_authority' => $d['evidence']->issuing_authority,
            'expires_on' => $d['evidence']->expires_on?->toDateString(),
        ] + array_diff_key($d, ['evidence' => 1]), $o['documents']);

        return $o;
    }
}
