<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Compliance\PolicyCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Where people acknowledge updated and new policies (RequirePolicyAcknowledgement sends them here). Each policy is
 * accepted with its own checkbox and recorded as its own append-only record, linked to the exact version.
 */
class PolicyAcknowledgementController extends Controller
{
    public function show(Request $request, PolicyCatalog $catalog): View
    {
        return view('policies.acknowledge', ['outstanding' => $catalog->outstanding($request->user())]);
    }

    public function store(\App\Http\Requests\Compliance\AcknowledgePoliciesRequest $request, PolicyCatalog $catalog): RedirectResponse
    {
        $data = $request->validated();
        $outstanding = $catalog->outstanding($request->user());
        if (array_diff($data['policies'], array_keys($outstanding)) !== []) {
            throw ValidationException::withMessages(['policies' => 'One of those policies does not need your acceptance. Reload the page and try again.']);
        }
        $catalog->record($request->user(), $data['policies'], 'policy_update', $request);

        $remaining = $catalog->outstanding($request->user());
        if ($remaining !== []) {
            return redirect()->route('policies.acknowledge')->with('success', 'Recorded. ' . count($remaining) . ' still to accept.');
        }

        return redirect()->intended(route('home'))->with('success', 'Thank you. Your acceptance is recorded.');
    }
}
