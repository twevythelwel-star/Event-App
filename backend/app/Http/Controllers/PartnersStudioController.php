<?php

namespace App\Http\Controllers;

use App\Models\EventSponsor;
use App\Models\SponsorshipPackage;
use App\Models\VendorApplication;
use App\Models\VendorOffer;
use App\Models\VenueArea;
use App\Services\EventManagementService;
use App\Services\Events\PartnersService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The host's vendors, exhibitors and sponsors: applications and decisions, booth types and prices, sponsorship
 * packages and sponsors. Authorization and every rule live in PartnersService.
 */
class PartnersStudioController extends Controller
{
    private const TABS = ['applications', 'booths', 'sponsors', 'settings'];

    public function __construct(
        private readonly EventManagementService $events,
        private readonly PartnersService $partners,
    ) {}

    public function show(Request $request, string $id): View
    {
        $event = $this->events->findEvent($request->user(), $id);
        $status = in_array($request->query('status'), array_keys(VendorApplication::STATUSES), true) ? $request->query('status') : null;

        return view('events.partners-studio', [
            'event' => $event,
            'tab' => in_array($request->query('tab'), self::TABS, true) ? $request->query('tab') : 'applications',
            'status' => $status,
            'applications' => VendorApplication::with('offer')->where('event_id', $event->id)->when($status, fn ($q) => $q->where('status', $status))
                ->orderByRaw("CASE status WHEN 'pending' THEN 0 WHEN 'approved' THEN 1 ELSE 2 END")->latest()->get(),
            'counts' => VendorApplication::where('event_id', $event->id)->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status'),
            'offers' => VendorOffer::where('event_id', $event->id)->orderBy('sort_order')->get(),
            'booths' => VenueArea::where('event_id', $event->id)->where('kind', 'vendor_booth')->orderBy('label')->get(),
            'packages' => SponsorshipPackage::where('event_id', $event->id)->orderByRaw("CASE level WHEN 'gold' THEN 0 WHEN 'silver' THEN 1 WHEN 'bronze' THEN 2 ELSE 3 END")->get(),
            'sponsors' => EventSponsor::where('event_id', $event->id)->orderByRaw("CASE level WHEN 'gold' THEN 0 WHEN 'silver' THEN 1 WHEN 'bronze' THEN 2 ELSE 3 END")->orderBy('sort_order')->get(),
            'currency' => $event->currency ?: 'USD',
            'tz' => $event->timezone ?: config('app.timezone'),
        ]);
    }

    public function saveSettings(Request $request, string $id): RedirectResponse
    {
        $data = $request->validate(['closes_at' => ['nullable', 'date'], 'terms' => ['nullable', 'string', 'max:5000']]);

        return $this->run($id, 'settings', fn () => $this->partners->saveSettings($request->user(), $id, $request->boolean('open'), $data['closes_at'] ?? null, $data['terms'] ?? null), 'Vendor application settings saved.');
    }

    public function saveOffer(Request $request, string $id, ?string $offerId = null): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'], 'category' => ['nullable', Rule::in(array_keys(VendorApplication::CATEGORIES))],
            'description' => ['nullable', 'string', 'max:2000'], 'price' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:5000'], 'venue_area_id' => ['nullable', 'string', 'max:64'],
        ]);
        $data['is_active'] = !$request->has('is_active_submitted') || $request->boolean('is_active');

        return $this->run($id, 'booths', fn () => $this->partners->saveOffer($request->user(), $id, $offerId, $data), $offerId ? 'Booth type saved.' : 'Booth type added.');
    }

    public function deleteOffer(Request $request, string $id, string $offerId): RedirectResponse
    {
        return $this->run($id, 'booths', fn () => $this->partners->deleteOffer($request->user(), $id, $offerId), 'Booth type removed.');
    }

    public function decide(Request $request, string $id, string $applicationId): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'decline'])], 'offer_id' => ['nullable', 'string', 'max:64'],
            'booth_label' => ['nullable', 'string', 'max:60'], 'venue_area_id' => ['nullable', 'string', 'max:64'],
            'fee' => ['nullable', 'numeric', 'min:0', 'max:1000000'], 'note' => ['nullable', 'string', 'max:2000'],
        ]);

        return $this->run($id, 'applications', fn () => $this->partners->decide($request->user(), $id, $applicationId, $data),
            $data['decision'] === 'approve' ? 'Application approved.' : 'Application declined.');
    }

    public function feeStatus(Request $request, string $id, string $applicationId): RedirectResponse
    {
        $data = $request->validate(['fee_status' => ['required', Rule::in(['unpaid', 'paid', 'waived'])]]);

        return $this->run($id, 'applications', fn () => $this->partners->setFeeStatus($request->user(), $id, $applicationId, $data['fee_status']), 'Booth fee recorded.');
    }

    public function sponsorFromApplication(Request $request, string $id, string $applicationId): RedirectResponse
    {
        $data = $request->validate(['level' => ['required', Rule::in(array_keys(EventSponsor::LEVELS))]]);

        return $this->run($id, 'sponsors', fn () => $this->partners->sponsorFromApplication($request->user(), $id, $applicationId, $data['level']), 'Added as a sponsor. Add their logo below.');
    }

    public function savePackage(Request $request, string $id, ?string $packageId = null): RedirectResponse
    {
        $data = $request->validate([
            'level' => ['required', Rule::in(array_keys(EventSponsor::LEVELS))], 'name' => ['required', 'string', 'max:120'],
            'benefits' => ['nullable', 'string', 'max:2000'], 'price' => ['required', 'numeric', 'min:0', 'max:10000000'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        return $this->run($id, 'sponsors', fn () => $this->partners->savePackage($request->user(), $id, $packageId, $data), 'Sponsorship package saved.');
    }

    public function deletePackage(Request $request, string $id, string $packageId): RedirectResponse
    {
        return $this->run($id, 'sponsors', fn () => $this->partners->deletePackage($request->user(), $id, $packageId), 'Package removed.');
    }

    public function saveSponsor(Request $request, string $id, ?string $sponsorId = null): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'], 'level' => ['required', Rule::in(array_keys(EventSponsor::LEVELS))],
            'package_id' => ['nullable', 'string', 'max:64'], 'description' => ['nullable', 'string', 'max:1000'],
            'website' => ['nullable', 'string', 'max:300'], 'logo' => ['nullable', 'file', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'logo_alt_text' => ['nullable', 'string', 'max:250'],
        ], ['logo.mimes' => 'Upload the logo as PNG, JPG or WebP.']);
        foreach (['show_on_page', 'show_on_flyer', 'show_on_ticket'] as $flag) {
            $data[$flag] = $request->boolean($flag);
        }

        return $this->run($id, 'sponsors', fn () => $this->partners->saveSponsor($request->user(), $id, $sponsorId, $data, $request->file('logo'), $request->boolean('remove_logo')),
            $sponsorId ? 'Sponsor saved.' : 'Sponsor added.');
    }

    public function deleteSponsor(Request $request, string $id, string $sponsorId): RedirectResponse
    {
        return $this->run($id, 'sponsors', fn () => $this->partners->deleteSponsor($request->user(), $id, $sponsorId), 'Sponsor removed.');
    }

    private function run(string $id, string $tab, callable $change, string $message): RedirectResponse
    {
        $back = redirect()->route('events.partners', ['id' => $id, 'tab' => $tab]);
        try {
            $change();
        } catch (\InvalidArgumentException $e) {
            return $back->withErrors(['partners' => $e->getMessage()])->withInput();
        }

        return $back->with('status', $message);
    }
}
