<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Compliance\ComplianceSafetyReport;
use App\Models\Event;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Anyone signed in can report a safety issue about an event (food safety, allergens, alcohol, venue safety,
 * harassment). Reports go to EventBite's compliance administrators, not to the host.
 */
class SafetyReportController extends Controller
{
    public function store(\App\Http\Requests\Compliance\StoreSafetyReportRequest $request, string $event): RedirectResponse
    {
        $record = Event::allTenants()->where('status', '!=', 'draft')->findOrFail($event);
        $data = $request->validated();

        ComplianceSafetyReport::create([
            'event_id' => $record->id,
            'reported_by' => $request->user()->id,
            'category' => $data['category'],
            'description' => $data['description'],
            'occurred_on' => $data['occurred_on'] ?? null,
            'may_contact' => (bool) ($data['may_contact'] ?? false),
            // Food poisoning, allergic reactions and underage drinking are looked at first.
            'priority' => in_array($data['category'], ['food_safety', 'allergen', 'alcohol'], true) ? 'urgent' : 'normal',
            'status' => 'open',
        ]);

        return back()->with('success', 'Thank you. Your report went to EventBite\'s compliance team, not to the host. If someone is in danger, call 911.');
    }
}
