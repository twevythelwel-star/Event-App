<?php

namespace App\Services\Events;

use App\Models\Event;
use App\Models\EventSponsor;
use App\Models\SponsorshipPackage;
use App\Models\User;
use App\Models\VendorApplication;
use App\Models\VendorOffer;
use App\Models\VenueArea;
use App\Services\AuditLogService;
use App\Services\EventManagementService;
use App\Services\MediaStorageService;
use App\Support\Money;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Vendors, exhibitors and sponsors.
 *
 * Hosts open applications, sell booth types, decide on applications (booth, fee, reason) and record whether the fee
 * is paid. Businesses apply with their own account and follow their status. Hosts add sponsors at a level, with a
 * logo and where they are credited (event page, generated flyer, tickets). Host actions go through
 * EventManagementService::findEvent, so only the event's managers can take them; applicants only ever see their own.
 */
class PartnersService
{
    public function __construct(
        private readonly EventManagementService $events,
        private readonly MediaStorageService $media,
    ) {}

    // ---- Settings and booth offers (host) ----------------------------------------------------------------------

    public function saveSettings(User $user, string $eventId, bool $open, ?string $closesLocal, ?string $terms): Event
    {
        $event = $this->events->findEvent($user, $eventId);
        $closes = filled($closesLocal) ? Carbon::parse((string) $closesLocal, $event->timezone ?: config('app.timezone'))->utc() : null;
        $event->forceFill([
            'vendor_applications_open' => $open,
            'vendor_applications_close_at' => $closes,
            'vendor_terms' => filled($terms) ? mb_substr(trim((string) $terms), 0, 5000) : null,
        ])->save();
        $this->audit($user, $event, 'vendor_settings_saved', 'events', (string) $event->id, 'vendor applications ' . ($open ? 'open' : 'closed'));

        return $event;
    }

    public function saveOffer(User $user, string $eventId, ?string $offerId, array $data): VendorOffer
    {
        $event = $this->events->findEvent($user, $eventId);
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('Give the booth type a name.');
        }
        $category = filled($data['category'] ?? null) ? (string) $data['category'] : null;
        if ($category !== null && !array_key_exists($category, VendorApplication::CATEGORIES)) {
            throw new \InvalidArgumentException('Unknown vendor type.');
        }
        $areaId = $this->boothArea($event, $data['venue_area_id'] ?? null);
        $offer = $offerId ? VendorOffer::where('event_id', $event->id)->findOrFail($offerId) : new VendorOffer([
            'event_id' => $event->id, 'sort_order' => (int) VendorOffer::where('event_id', $event->id)->max('sort_order') + 1,
        ]);
        $quantity = filled($data['quantity'] ?? null) ? max(1, min(5000, (int) $data['quantity'])) : null;
        if ($offerId && $quantity !== null && $quantity < $offer->taken()) {
            throw new \InvalidArgumentException("{$offer->taken()} booths of this type are already given out; the number cannot go below that.");
        }
        $offer->fill([
            'name' => mb_substr($name, 0, 120), 'category' => $category, 'description' => $this->text($data['description'] ?? null, 2000),
            'price_minor' => Money::parse($data['price'] ?? 0, $event->currency ?: 'USD')->minor(), 'quantity' => $quantity,
            'venue_area_id' => $areaId, 'is_active' => (bool) ($data['is_active'] ?? true),
        ])->save();
        $this->audit($user, $event, $offerId ? 'vendor_offer_updated' : 'vendor_offer_added', 'vendor_offers', $offer->id, "booth type '{$offer->name}'");

        return $offer;
    }

    public function deleteOffer(User $user, string $eventId, string $offerId): void
    {
        $event = $this->events->findEvent($user, $eventId);
        $offer = VendorOffer::where('event_id', $event->id)->findOrFail($offerId);
        if ($offer->taken() > 0) {
            throw new \InvalidArgumentException('Approved vendors hold booths of this type. Turn it off instead of removing it.');
        }
        $offer->delete();
        $this->audit($user, $event, 'vendor_offer_removed', 'vendor_offers', $offerId, "booth type '{$offer->name}' removed");
    }

    // ---- Applications -------------------------------------------------------------------------------------------

    /** Whether businesses can apply to this event right now, and if not, why. */
    public function applicationsState(Event $event): array
    {
        if ($event->status !== 'published') {
            return ['open' => false, 'reason' => 'This event is not published.'];
        }
        if (!$event->vendor_applications_open) {
            return ['open' => false, 'reason' => 'This event is not taking vendor applications.'];
        }
        if ($event->vendor_applications_close_at && $event->vendor_applications_close_at->isPast()) {
            return ['open' => false, 'reason' => 'Vendor applications for this event have closed.'];
        }

        return ['open' => true, 'reason' => null];
    }

    public function apply(User $applicant, Event $event, array $data): VendorApplication
    {
        $state = $this->applicationsState($event);
        if (!$state['open']) {
            throw new \InvalidArgumentException($state['reason']);
        }
        $category = (string) ($data['category'] ?? '');
        if (!array_key_exists($category, VendorApplication::CATEGORIES)) {
            throw new \InvalidArgumentException('Choose what kind of vendor you are.');
        }
        if (VendorApplication::where('event_id', $event->id)->where('user_id', $applicant->id)->whereIn('status', ['pending', 'approved'])->exists()) {
            throw new \InvalidArgumentException('You already have an application for this event. You can follow it under My vendor applications.');
        }
        $offerId = filled($data['offer_id'] ?? null) ? (string) $data['offer_id'] : null;
        if ($offerId) {
            $offer = VendorOffer::where('event_id', $event->id)->where('is_active', true)->find($offerId);
            if (!$offer || ($offer->category !== null && $offer->category !== $category)) {
                throw new \InvalidArgumentException('That booth type is not available for your kind of business.');
            }
            if ($offer->remaining() === 0) {
                throw new \InvalidArgumentException("'{$offer->name}' is fully booked. Choose another booth type.");
            }
        }
        $website = trim((string) ($data['website'] ?? ''));
        if ($website !== '' && !preg_match('#^https://[^\s"\'<>]+$#i', $website)) {
            throw new \InvalidArgumentException('Your website must be a full https:// address.');
        }
        foreach (['business_name' => 'your business name', 'contact_name' => 'a contact name', 'email' => 'an email address', 'phone' => 'a phone number', 'description' => 'what you will sell or show'] as $key => $what) {
            if (trim((string) ($data[$key] ?? '')) === '') {
                throw new \InvalidArgumentException("Give {$what}.");
            }
        }

        $application = VendorApplication::create([
            'event_id' => $event->id, 'user_id' => $applicant->id, 'offer_id' => $offerId, 'category' => $category,
            'business_name' => mb_substr(trim((string) $data['business_name']), 0, 150), 'contact_name' => mb_substr(trim((string) $data['contact_name']), 0, 120),
            'email' => mb_substr(trim((string) $data['email']), 0, 190), 'phone' => mb_substr(trim((string) $data['phone']), 0, 40),
            'description' => mb_substr(trim((string) $data['description']), 0, 3000), 'website' => $website ?: null,
            'needs' => $this->text($data['needs'] ?? null, 1000), 'show_publicly' => (bool) ($data['show_publicly'] ?? false), 'status' => 'pending',
        ]);
        AuditLogService::log(action: 'vendor_application_submitted', targetEntity: 'vendor_applications', targetId: (string) $application->id,
            details: "{$event->title}: {$application->categoryLabel()} application from '{$application->business_name}'.", actor: $applicant,
            organizerId: (string) $event->organizer_id, eventId: (string) $event->id);

        return $application;
    }

    public function withdraw(User $applicant, string $applicationId): VendorApplication
    {
        $application = VendorApplication::where('user_id', $applicant->id)->findOrFail($applicationId);
        if (!in_array($application->status, ['pending', 'approved'], true)) {
            throw new \InvalidArgumentException('This application can no longer be withdrawn.');
        }
        if ($application->status === 'approved' && $application->fee_status === 'paid') {
            throw new \InvalidArgumentException('Your booth fee is paid. Contact the host to withdraw, so they can arrange a refund.');
        }
        $application->forceFill(['status' => 'withdrawn'])->save();

        return $application;
    }

    /**
     * Approves (with a booth type, booth and fee) or declines (with an optional reason) an application.
     *
     * @param array{decision: string, offer_id?: ?string, booth_label?: ?string, venue_area_id?: ?string, fee?: ?string, note?: ?string} $data
     */
    public function decide(User $user, string $eventId, string $applicationId, array $data): VendorApplication
    {
        $event = $this->events->findEvent($user, $eventId);

        return DB::transaction(function () use ($user, $event, $applicationId, $data) {
            $application = VendorApplication::where('event_id', $event->id)->lockForUpdate()->findOrFail($applicationId);
            if ($application->status === 'withdrawn') {
                throw new \InvalidArgumentException('The applicant withdrew this application.');
            }
            $note = $this->text($data['note'] ?? null, 2000);
            if (($data['decision'] ?? '') === 'decline') {
                $application->forceFill(['status' => 'declined', 'decision_note' => $note, 'decided_by' => $user->id, 'decided_at' => now(),
                    'fee_minor' => null, 'fee_status' => 'not_due'])->save();
                $this->audit($user, $event, 'vendor_application_declined', 'vendor_applications', $application->id, "'{$application->business_name}' declined");

                return $application;
            }
            if (($data['decision'] ?? '') !== 'approve') {
                throw new \InvalidArgumentException('Choose to approve or decline.');
            }
            $offerId = filled($data['offer_id'] ?? null) ? (string) $data['offer_id'] : $application->offer_id;
            $offer = $offerId ? VendorOffer::where('event_id', $event->id)->lockForUpdate()->find($offerId) : null;
            if ($offerId && !$offer) {
                throw new \InvalidArgumentException('That booth type is not part of this event.');
            }
            if ($offer && $application->status !== 'approved' && $offer->remaining() === 0) {
                throw new \InvalidArgumentException("'{$offer->name}' is fully booked.");
            }
            $fee = filled($data['fee'] ?? null) ? Money::parse($data['fee'], $event->currency ?: 'USD')->minor() : (int) ($offer?->price_minor ?? 0);
            $application->forceFill([
                'status' => 'approved', 'offer_id' => $offer?->id, 'decision_note' => $note, 'decided_by' => $user->id, 'decided_at' => now(),
                'booth_label' => $this->text($data['booth_label'] ?? null, 60),
                'venue_area_id' => $this->boothArea($event, $data['venue_area_id'] ?? null) ?? $offer?->venue_area_id,
                'fee_minor' => $fee, 'fee_status' => $fee > 0 ? ($application->fee_status === 'paid' ? 'paid' : 'unpaid') : 'not_due',
            ])->save();
            $this->audit($user, $event, 'vendor_application_approved', 'vendor_applications', $application->id,
                "'{$application->business_name}' approved, fee " . Money::formatMinor($fee, $event->currency ?: 'USD'));

            return $application;
        });
    }

    /** Records that the booth fee was paid (or waived, or is still due). The money itself is handled outside EventBite. */
    public function setFeeStatus(User $user, string $eventId, string $applicationId, string $status): VendorApplication
    {
        $event = $this->events->findEvent($user, $eventId);
        $application = VendorApplication::where('event_id', $event->id)->where('status', 'approved')->findOrFail($applicationId);
        if (!in_array($status, ['unpaid', 'paid', 'waived'], true) || (int) $application->fee_minor === 0) {
            throw new \InvalidArgumentException('There is no booth fee to record for this vendor.');
        }
        $application->forceFill(['fee_status' => $status])->save();
        $this->audit($user, $event, 'vendor_fee_recorded', 'vendor_applications', $application->id, "booth fee for '{$application->business_name}' marked {$status}");

        return $application;
    }

    /** @return Collection<int, VendorApplication> approved vendors who agreed to be listed on the event page */
    public function publicVendors(Event $event): Collection
    {
        return VendorApplication::where('event_id', $event->id)->where('status', 'approved')->where('show_publicly', true)
            ->where('category', '!=', 'sponsor')->orderBy('category')->orderBy('business_name')->get();
    }

    // ---- Sponsors -----------------------------------------------------------------------------------------------

    public function savePackage(User $user, string $eventId, ?string $packageId, array $data): SponsorshipPackage
    {
        $event = $this->events->findEvent($user, $eventId);
        if (!array_key_exists((string) ($data['level'] ?? ''), EventSponsor::LEVELS) || trim((string) ($data['name'] ?? '')) === '') {
            throw new \InvalidArgumentException('Give the package a name and a level.');
        }
        $package = $packageId ? SponsorshipPackage::where('event_id', $event->id)->findOrFail($packageId) : new SponsorshipPackage(['event_id' => $event->id]);
        $package->fill([
            'level' => $data['level'], 'name' => mb_substr(trim((string) $data['name']), 0, 120), 'benefits' => $this->text($data['benefits'] ?? null, 2000),
            'price_minor' => Money::parse($data['price'] ?? 0, $event->currency ?: 'USD')->minor(),
            'quantity' => filled($data['quantity'] ?? null) ? max(1, min(500, (int) $data['quantity'])) : null,
        ])->save();
        $this->audit($user, $event, 'sponsorship_package_saved', 'sponsorship_packages', $package->id, "package '{$package->name}'");

        return $package;
    }

    public function deletePackage(User $user, string $eventId, string $packageId): void
    {
        $event = $this->events->findEvent($user, $eventId);
        $package = SponsorshipPackage::where('event_id', $event->id)->findOrFail($packageId);
        $package->delete();
        $this->audit($user, $event, 'sponsorship_package_removed', 'sponsorship_packages', $packageId, "package '{$package->name}' removed");
    }

    public function saveSponsor(User $user, string $eventId, ?string $sponsorId, array $data, ?UploadedFile $logo = null, bool $removeLogo = false): EventSponsor
    {
        $event = $this->events->findEvent($user, $eventId);
        $name = trim((string) ($data['name'] ?? ''));
        $level = (string) ($data['level'] ?? '');
        if ($name === '' || !array_key_exists($level, EventSponsor::LEVELS)) {
            throw new \InvalidArgumentException('Give the sponsor a name and a level.');
        }
        $website = trim((string) ($data['website'] ?? ''));
        if ($website !== '' && !preg_match('#^https://[^\s"\'<>]+$#i', $website)) {
            throw new \InvalidArgumentException('The sponsor website must be a full https:// address.');
        }
        $packageId = filled($data['package_id'] ?? null) ? (string) $data['package_id'] : null;
        if ($packageId) {
            $package = SponsorshipPackage::where('event_id', $event->id)->find($packageId);
            if (!$package) {
                throw new \InvalidArgumentException('That package is not part of this event.');
            }
            $level = $package->level;
            $count = EventSponsor::where('package_id', $package->id)->when($sponsorId, fn ($q) => $q->whereKeyNot($sponsorId))->count();
            if ($package->quantity !== null && $count >= $package->quantity) {
                throw new \InvalidArgumentException("The '{$package->name}' package is fully taken.");
            }
        }

        $sponsor = $sponsorId ? EventSponsor::where('event_id', $event->id)->findOrFail($sponsorId) : new EventSponsor([
            'event_id' => $event->id, 'sort_order' => (int) EventSponsor::where('event_id', $event->id)->max('sort_order') + 1,
        ]);
        $sponsor->fill([
            'name' => mb_substr($name, 0, 150), 'level' => $level, 'package_id' => $packageId, 'description' => $this->text($data['description'] ?? null, 1000),
            'website' => $website ?: null, 'logo_alt_text' => $this->text($data['logo_alt_text'] ?? null, 250),
            'show_on_page' => (bool) ($data['show_on_page'] ?? false), 'show_on_flyer' => (bool) ($data['show_on_flyer'] ?? false),
            'show_on_ticket' => (bool) ($data['show_on_ticket'] ?? false),
        ]);
        $old = null;
        if ($logo) {
            $stored = $this->media->storeImage($logo, (string) $event->organizer_id, 'sponsors');
            try {
                $variants = $this->media->createVariants($stored['path'], (string) $event->organizer_id, 'sponsors');
            } catch (\Throwable $e) {
                $this->media->delete($stored['path']);
                throw $e;
            }
            $old = [$sponsor->logo_path, $sponsor->logo_variants];
            $sponsor->logo_path = $stored['path'];
            $sponsor->logo_variants = $variants;
        } elseif ($removeLogo && $sponsor->logo_path) {
            $old = [$sponsor->logo_path, $sponsor->logo_variants];
            $sponsor->logo_path = null;
            $sponsor->logo_variants = null;
        }
        $sponsor->save();
        if ($old) {
            $this->media->delete($old[0]);
            $this->media->deleteVariants($old[1]);
        }
        $this->audit($user, $event, $sponsorId ? 'sponsor_updated' : 'sponsor_added', 'event_sponsors', $sponsor->id, "{$sponsor->levelLabel()} sponsor '{$sponsor->name}'");

        return $sponsor;
    }

    public function deleteSponsor(User $user, string $eventId, string $sponsorId): void
    {
        $event = $this->events->findEvent($user, $eventId);
        $sponsor = EventSponsor::where('event_id', $event->id)->findOrFail($sponsorId);
        $this->media->delete($sponsor->logo_path);
        $this->media->deleteVariants($sponsor->logo_variants);
        $sponsor->delete();
        $this->audit($user, $event, 'sponsor_removed', 'event_sponsors', $sponsorId, "sponsor '{$sponsor->name}' removed");
    }

    /** Turns an approved sponsor application into a sponsor (no logo yet; the host adds it with the sponsor's files). */
    public function sponsorFromApplication(User $user, string $eventId, string $applicationId, string $level): EventSponsor
    {
        $event = $this->events->findEvent($user, $eventId);
        $application = VendorApplication::where('event_id', $event->id)->where('status', 'approved')->where('category', 'sponsor')->findOrFail($applicationId);
        if (EventSponsor::where('application_id', $application->id)->exists()) {
            throw new \InvalidArgumentException("'{$application->business_name}' is already a sponsor.");
        }
        $sponsor = $this->saveSponsor($user, $eventId, null, ['name' => $application->business_name, 'level' => $level,
            'description' => mb_substr($application->description, 0, 1000), 'website' => $application->website, 'show_on_page' => $application->show_publicly]);
        $sponsor->forceFill(['application_id' => $application->id])->save();

        return $sponsor;
    }

    /**
     * Sponsors for one placement, highest level first.
     *
     * @param 'page'|'flyer'|'ticket' $placement
     * @return Collection<int, EventSponsor>
     */
    public function sponsorsFor(Event $event, string $placement): Collection
    {
        $order = array_flip(array_keys(EventSponsor::LEVELS));

        return EventSponsor::where('event_id', $event->id)->where('show_on_' . $placement, true)->get()
            ->sortBy(fn (EventSponsor $s) => [$order[$s->level] ?? 9, $s->sort_order])->values();
    }

    // ---- Helpers ------------------------------------------------------------------------------------------------

    private function boothArea(Event $event, mixed $areaId): ?string
    {
        if (!filled($areaId)) {
            return null;
        }
        $area = VenueArea::where('event_id', $event->id)->where('kind', 'vendor_booth')->find((string) $areaId);
        if (!$area) {
            throw new \InvalidArgumentException('Choose a vendor booth from this event\'s venue plan.');
        }

        return $area->id;
    }

    private function text(mixed $value, int $max): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, $max);
    }

    private function audit(User $user, Event $event, string $action, string $entity, string $id, string $details): void
    {
        AuditLogService::log(action: $action, targetEntity: $entity, targetId: $id, details: "{$event->title}: {$details}.",
            actor: $user, organizerId: (string) $event->organizer_id, eventId: (string) $event->id);
    }
}
