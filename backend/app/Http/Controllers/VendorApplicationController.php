<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\VendorApplication;
use App\Models\VendorOffer;
use App\Services\Events\PartnersService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Businesses applying to an event as a vendor, exhibitor or sponsor, and following their own applications. */
class VendorApplicationController extends Controller
{
    public function __construct(private readonly PartnersService $partners) {}

    public function create(Request $request, string $id): View
    {
        $event = Event::allTenants()->where('status', 'published')->findOrFail($id);

        return view('vendors.apply', [
            'event' => $event,
            'state' => $this->partners->applicationsState($event),
            'offers' => VendorOffer::where('event_id', $event->id)->where('is_active', true)->orderBy('sort_order')->get(),
            'existing' => VendorApplication::where('event_id', $event->id)->where('user_id', $request->user()->id)->whereIn('status', ['pending', 'approved'])->first(),
            'currency' => $event->currency ?: 'USD',
        ]);
    }

    public function store(Request $request, string $id): RedirectResponse
    {
        $event = Event::allTenants()->where('status', 'published')->findOrFail($id);
        $data = $request->validate([
            'category' => ['required', Rule::in(array_keys(VendorApplication::CATEGORIES))],
            'offer_id' => ['nullable', 'string', 'max:64'],
            'business_name' => ['required', 'string', 'max:150'],
            'contact_name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['required', 'string', 'min:7', 'max:40'],
            'description' => ['required', 'string', 'min:10', 'max:3000'],
            'website' => ['nullable', 'string', 'max:300'],
            'needs' => ['nullable', 'string', 'max:1000'],
            'agree' => $event->vendor_terms ? ['accepted'] : ['nullable'],
        ], ['agree.accepted' => "Agree to the host's vendor terms to apply."]);
        $data['show_publicly'] = $request->boolean('show_publicly');

        try {
            $this->partners->apply($request->user(), $event, $data);
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['application' => $e->getMessage()]);
        }

        return redirect()->route('vendors.mine')->with('status', "Application sent to {$event->organizer_name}. You will see their decision here.");
    }

    public function mine(Request $request): View
    {
        return view('vendors.mine', [
            'applications' => VendorApplication::with(['event', 'offer'])->where('user_id', $request->user()->id)->latest()->get(),
        ]);
    }

    public function withdraw(Request $request, string $applicationId): RedirectResponse
    {
        try {
            $this->partners->withdraw($request->user(), $applicationId);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Application withdrawn.');
    }
}
