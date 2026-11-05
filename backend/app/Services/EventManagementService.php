<?php

namespace App\Services;

use App\Enums\AccountType;
use App\Enums\TenantRole;
use App\Exceptions\SecurityException;
use App\Models\Event;
use App\Models\MenuItem;
use App\Models\OrganizerUser;
use App\Models\SecurityAuditLog;
use App\Models\User;
use App\Services\LocationAddressNormalizer;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Event Host editing of their own events and menus.
 *
 * Only organizer members whose role can manage event configuration (event owners, platform admins) and
 * System Administrators may edit; payment verifiers, collectors and assigned event staff may not.
 * Every lookup is scoped to the user's organizers, so another organizer's event behaves as not found.
 */
class EventManagementService
{
    public const STATUSES = ['draft', 'published', 'unpublished', 'cancelled'];

    public function __construct(
        protected MediaStorageService $media
    ) {
    }

    /**
     * Organizer ids whose events this user may edit, or null for every organizer (System Administrators).
     */
    public function manageableOrganizerIds(User $user): ?Collection
    {
        if ($user->hasRole(AccountType::SYSTEM_ADMIN)) {
            return null;
        }

        return OrganizerUser::where('user_id', $user->id)
            ->where('is_active', true)
            ->get()
            ->filter(function (OrganizerUser $membership) {
                $role = $membership->role instanceof TenantRole ? $membership->role : TenantRole::tryFrom((string) $membership->role);

                return $role?->canManageEventConfiguration() ?? false;
            })
            ->pluck('organizer_id')
            ->values();
    }

    public function eventsQuery(User $user): Builder
    {
        $query = Event::withoutGlobalScopes()
            ->with(['menuItems' => fn($q) => $q->withoutGlobalScopes()->whereNull('deleted_at')->orderBy('sort_order')])
            ->orderBy('advance_order_deadline');

        $organizers = $this->manageableOrganizerIds($user);

        return $organizers === null ? $query : $query->whereIn('organizer_id', $organizers);
    }

    public function findEvent(User $user, string $eventId): Event
    {
        $event = $this->eventsQuery($user)->whereKey($eventId)->first();
        if (!$event) {
            throw (new ModelNotFoundException('Event not found.'))->setModel(Event::class);
        }

        return $event;
    }

    /**
     * Create a new event (defaults to draft status unless explicitly published after passing readiness checks).
     *
     * @param array<string, mixed> $data Validated event fields (snake_case)
     */
    public function createEvent(User $user, array $data): Event
    {
        $organizers = $this->manageableOrganizerIds($user);
        if ($organizers !== null && $organizers->isEmpty()) {
            throw new SecurityException('You are not authorized to create events.');
        }

        $requestedOrgId = $data['organizer_id'] ?? null;
        if ($organizers !== null) {
            if ($requestedOrgId !== null && !$organizers->contains($requestedOrgId)) {
                throw new SecurityException('You may only create events for organizers you manage.');
            }
            $organizerId = $requestedOrgId ?: (string) $organizers->first();
        } else {
            $organizerId = $requestedOrgId ?: 'org-kingston-youth';
        }

        // Every new event starts as a draft, which may leave the address unfinished; publishing checks it.
        $data = app(LocationAddressNormalizer::class)->normalize($data + ['event_status' => 'draft']);
        $data = \App\Services\Events\EventDetails::prepare($data);
        $data['organizer_id'] = $organizerId;
        $this->assertCulturalSelectionValid(null, $data);

        $timezone = $data['timezone'] ?? config('events.market.default_timezone', 'America/New_York');
        $this->assertValidTimezone($timezone);

        if (isset($data['ticket_tiers']) && is_array($data['ticket_tiers'])) {
            $data['ticket_tiers'] = $this->setTicketCurrency($data['ticket_tiers'], (string) ($data['currency'] ?? config('events.market.default_currency', 'USD')));
            $this->assertTicketTiersValid($data['ticket_tiers'], (string) ($data['currency'] ?? config('events.market.default_currency', 'USD')));
        }
        $menuItems = is_array($data['menu_items'] ?? null) ? $data['menu_items'] : [];
        unset($data['menu_items']);

        $deadlineRaw = $data['advance_order_deadline'] ?? now($timezone)->addDays(14)->toIso8601String();
        $deadlineUtc = Carbon::parse($deadlineRaw, $timezone)->utc();
        $organizerPolicy = \App\Models\HostPaymentPolicy::where('organizer_id', $organizerId)->first();

        $title = trim((string) ($data['title'] ?? 'Untitled Community Event'));
        $slug = !empty($data['slug'])
            ? $data['slug']
            : Str::slug($title) . '-' . strtolower(Str::random(6));

        $event = Event::withoutGlobalScopes()->create([
            'id' => 'evt-' . strtolower(Str::random(10)),
            'organizer_id' => $organizerId,
            'slug' => $slug,
            'title' => $title,
            'tagline' => $data['tagline'] ?? '',
            'category' => $data['category'] ?? 'Community',
            'summary' => $data['summary'] ?? null,
            'event_types' => $data['event_types'] ?? [],
            'event_format' => $data['event_format'] ?? 'in_person',
            'expected_attendance' => $data['expected_attendance'] ?? null,
            'age_restriction' => $data['age_restriction'] ?? null,
            'dress_code' => $data['dress_code'] ?? null,
            'accessibility_information' => $data['accessibility_information'] ?? null,
            'languages' => $data['languages'] ?? [],
            'visibility' => $data['visibility'] ?? 'public',
            'virtual_url' => $data['virtual_url'] ?? null,
            'virtual_access_instructions' => $data['virtual_access_instructions'] ?? null,
            'is_featured' => $user->hasRole(\App\Enums\AccountType::SYSTEM_ADMIN) && (bool) ($data['is_featured'] ?? false),
            'date_str' => $data['date_str'] ?? '',
            'event_date_iso' => $data['event_date_iso'] ?? null,
            'time_str' => $data['time_str'] ?? '',
            'start_time' => $data['start_time'] ?? null,
            'end_time' => $data['end_time'] ?? null,
            'timezone' => $timezone,
            'venue_name' => $data['venue_name'] ?? '',
            'venue_building_name' => $data['venue_building_name'] ?? null,
            'street_address' => $data['street_address'] ?? null,
            'city' => $data['city'] ?? null,
            'state_code' => $data['state_code'] ?? null,
            'target_market_id' => $data['target_market_id'] ?? null,
            'zip_code' => $data['zip_code'] ?? null,
            'country_code' => $data['country_code'] ?? 'US',
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'venue_address' => $data['venue_address'] ?? '',
            'coordinates' => $data['coordinates'] ?? '',
            'description' => $data['description'] ?? '',
            'traditions' => $data['traditions'] ?? null,
            'currency' => $data['currency'] ?? config('events.market.default_currency', 'USD'),
            'organizer_name' => $data['organizer_name'] ?? $user->name,
            'organizer_phone' => $data['organizer_phone'] ?? ($user->phone ?? '+1 (305) 555-0100'),
            'organizer_whatsapp' => $data['organizer_whatsapp'] ?? '',
            'organizer_email' => $data['organizer_email'] ?? $user->email,
            'advance_order_deadline' => $deadlineUtc,
            'is_ordering_open' => false,
            'status' => 'draft',
            'pickup_instructions' => $data['pickup_instructions'] ?? '',
            'packaging_fee_usd' => $data['packaging_fee_usd'] ?? 0,
            'packaging_fee_jmd' => $data['packaging_fee_jmd'] ?? 0,
            'supports_food_ordering' => $data['supports_food_ordering'] ?? true,
            'refund_policy_deadline_hours' => $data['refund_policy_deadline_hours'] ?? $organizerPolicy?->refund_policy_deadline_hours ?? 48,
            'refund_policy_notes' => $data['refund_policy_notes'] ?? $organizerPolicy?->refund_policy_notes,
            'refund_policy_mode' => $data['refund_policy_mode'] ?? $organizerPolicy?->refund_policy_mode ?? 'manual_full_and_partial',
            'host_cancellation_policy' => $data['host_cancellation_policy'] ?? $organizerPolicy?->host_cancellation_policy ?? 'contact_organizer',
            'postponement_policy' => $data['postponement_policy'] ?? $organizerPolicy?->postponement_policy ?? 'tickets_valid_for_new_date',
            'weather_cancellation_policy' => $data['weather_cancellation_policy'] ?? $organizerPolicy?->weather_cancellation_policy ?? 'contact_organizer',
            'food_order_cancellation_deadline_hours' => $data['food_order_cancellation_deadline_hours'] ?? $organizerPolicy?->food_order_cancellation_deadline_hours ?? 24,
            'food_order_modification_deadline_hours' => $data['food_order_modification_deadline_hours'] ?? $organizerPolicy?->food_order_modification_deadline_hours ?? 24,
            'non_refundable_ticket_categories' => $data['non_refundable_ticket_categories'] ?? $organizerPolicy?->non_refundable_ticket_categories ?? [],
            'ticket_tiers' => $data['ticket_tiers'] ?? [],
            'flyer_alt_text' => $data['flyer_alt_text'] ?? ($title . ' event flyer'),
            'flyer_position_x' => $data['flyer_position_x'] ?? 50,
            'flyer_position_y' => $data['flyer_position_y'] ?? 50,
            'is_flyer_public' => false,
            // The create form's key: a retried submission finds this event instead of creating another.
            'creation_key' => isset($data['creation_key']) && Str::isUuid((string) $data['creation_key']) ? (string) $data['creation_key'] : null,
            // Timetable, venue details, verified map pin and food plan (prepared by EventDetails).
            ...\Illuminate\Support\Arr::only($data, ['end_date_iso', 'starts_at', 'ends_at', 'doors_open_time', 'setup_time',
                'food_service_start_time', 'food_service_end_time', 'ticket_sales_start_at', 'ticket_sales_end_at', 'early_bird_starts_at', 'early_bird_ends_at',
                'venue_type', 'venue_instructions', 'parking_information', 'location_source', 'location_place_id', 'location_verified_at',
                'food_ordering_mode', 'food_fulfillment', 'food_delivery_instructions', 'food_service_fee_minor', 'food_pickup_windows', 'food_max_orders']),
        ]);
        $this->syncCulturalCatalog($event, $data);

        // Event creation via the dashboard also supports starter menu items in the same request.
        if ($menuItems !== []) {
            foreach ($menuItems as $index => $item) {
                if (!is_array($item) || !filled($item['name'] ?? null)) continue;
                $this->createMenuItem($user, $event->id, array_merge($item, [
                    'sort_order' => $item['sort_order'] ?? (int) $index,
                    'pricing_type' => $item['pricing_type'] ?? 'fixed',
                ]));
            }
        }

        $this->audit($user, 'event_created', 'events', $event->id, "Draft event '{$event->title}' created via Event Wizard.");

        // The event's promotional QR identity and artwork; artwork failure never fails event creation.
        app(\App\Services\EventQr\EventQrService::class)->ensureFor($event);

        return $this->findEvent($user, $event->id);
    }

    /**
     * @param array<string, mixed> $data Validated event fields (snake_case)
     */
    public function updateEvent(User $user, string $eventId, array $data): Event
    {
        $event = $this->findEvent($user, $eventId);
        $this->assertCulturalSelectionValid($event, $data);
        $menuItems = is_array($data['menu_items'] ?? null) ? $data['menu_items'] : [];
        $this->assertMenuItemsConsistent($menuItems);
        unset($data['menu_items']);
        $data = app(LocationAddressNormalizer::class)->normalize(array_merge([
            'street_address' => $event->street_address,
            'venue_building_name' => $event->venue_building_name,
            'city' => $event->city,
            'state_code' => $event->state_code,
            'zip_code' => $event->zip_code,
            'country_code' => $event->country_code ?? 'US',
            'latitude' => $event->latitude,
            'longitude' => $event->longitude,
            'venue_address' => $event->venue_address,
            'timezone' => $event->timezone,
            'event_format' => $event->event_format,
            'virtual_url' => $event->virtual_url,
            'event_status' => $event->status,
        ], $data));
        unset($data['event_status']);

        if (array_key_exists('timezone', $data) && $data['timezone'] !== null) {
            $this->assertValidTimezone((string) $data['timezone']);
        }

        if (array_key_exists('ticket_tiers', $data) && is_array($data['ticket_tiers'])) {
            $data['ticket_tiers'] = $this->setTicketCurrency($data['ticket_tiers'], (string) ($data['currency'] ?? $event->currency ?? config('events.market.default_currency', 'USD')));
            $this->assertTicketTiersValid($data['ticket_tiers'], (string) ($data['currency'] ?? $event->currency ?? config('events.market.default_currency', 'USD')));
        }

        if ($menuItems !== []) {
            foreach ($menuItems as $index => $item) {
                if (!is_array($item) || !filled($item['name'] ?? null)) continue;
                $this->createMenuItem($user, $eventId, array_merge($item, ['sort_order' => $item['sort_order'] ?? (int) $index, 'pricing_type' => $item['pricing_type'] ?? 'fixed']));
            }
        }

        $effectiveTimezone = $data['timezone'] ?? $event->timezone ?: config('events.market.default_timezone', 'America/New_York');

        if (array_key_exists('advance_order_deadline', $data)) {
            // Deadlines are entered in the event's own timezone
            $data['advance_order_deadline'] = Carbon::parse($data['advance_order_deadline'], $effectiveTimezone)->utc();
        }
        if (($data['is_ordering_open'] ?? false) === true && $event->status !== 'published') {
            throw new \InvalidArgumentException('Publish the event before opening it for orders.');
        }

        // The timetable, map pin and food plan, checked together with what the event already has.
        $data = \App\Services\Events\EventDetails::prepare($data, $event);

        $before = $event->only(\App\Services\Notifications\NotificationService::EVENT_NOTICE_FIELDS);
        $event->fill($data)->save();
        $this->syncCulturalCatalog($event, $data);
        \App\Services\Notifications\NotificationService::afterEventSaved($event, $before);
        $this->audit($user, 'event_updated', 'events', $event->id, "Event '{$event->title}' updated: " . implode(', ', array_keys($data)) . '.');

        return $this->findEvent($user, $eventId);
    }

    private function syncCulturalCatalog(Event $event, array $data): void
    {
        if (array_key_exists('cultural_category_ids', $data)) {
            $event->culturalCategories()->sync($data['cultural_category_ids'] ?? []);
        }
        if (array_key_exists('country_ids', $data)) {
            $event->countries()->sync($data['country_ids'] ?? []);
        }
        if (array_key_exists('dish_ids', $data)) {
            $selectedDishIds = $data['dish_ids'] ?? [];
            \App\Models\EventDish::where('event_id', $event->id)
                ->whereNotNull('dish_id')
                ->whereNotIn('dish_id', $selectedDishIds ?: [''])
                ->delete();
            foreach ($selectedDishIds as $dishId) {
                \App\Models\EventDish::updateOrCreate(
                    ['event_id' => $event->id, 'dish_id' => $dishId],
                    ['custom_name' => null, 'alternative_spellings' => [], 'is_fusion' => false, 'dietary_type' => 'any']
                );
            }
        }
        if (array_key_exists('custom_dishes', $data)) {
            $event->customDishes()->delete();
            foreach ($data['custom_dishes'] ?? [] as $dish) {
                $event->customDishes()->create([
                    'country_id' => $dish['country_id'],
                    'dish_id' => null,
                    'custom_name' => $dish['name'],
                    'alternative_spellings' => $dish['alternative_spellings'] ?? [],
                    'is_fusion' => $dish['is_fusion'] ?? false,
                    'dietary_type' => $dish['dietary_type'] ?? 'any',
                ]);
            }
        }
    }

    private function assertCulturalSelectionValid(?Event $event, array $data): void
    {
        $countryIds = array_values($data['country_ids'] ?? $event?->countries()->pluck('countries.id')->all() ?? []);
        $categoryIds = array_values($data['cultural_category_ids'] ?? $event?->culturalCategories()->pluck('cultural_categories.id')->all() ?? []);
        if ($countryIds !== [] && $categoryIds !== []) {
            $matchingCountries = \App\Models\Country::query()
                ->whereIn('countries.id', $countryIds)
                ->join('cultural_region_countries', 'cultural_region_countries.country_id', '=', 'countries.id')
                ->join('cultural_regions', 'cultural_regions.id', '=', 'cultural_region_countries.cultural_region_id')
                ->whereIn('cultural_regions.cultural_category_id', $categoryIds)
                ->distinct()
                ->pluck('countries.id');
            if ($matchingCountries->count() !== count(array_unique($countryIds))) {
                throw new \InvalidArgumentException('Each selected country must belong to one of the selected cultural identities.');
            }
        }
        foreach ($data['custom_dishes'] ?? [] as $dish) {
            if (!in_array($dish['country_id'], $countryIds, true)) {
                throw new \InvalidArgumentException('Custom dishes must belong to a country selected for this event.');
            }
        }
        if (array_key_exists('dish_ids', $data)) {
            $dishCountries = \App\Models\Dish::query()
                ->whereIn('dishes.id', $data['dish_ids'] ?? [])
                ->join('cuisines', 'cuisines.id', '=', 'dishes.cuisine_id')
                ->pluck('cuisines.country_id');
            if ($dishCountries->diff($countryIds)->isNotEmpty()) {
                throw new \InvalidArgumentException('Selected dishes must belong to a country selected for this event.');
            }
        }
    }

    public function setStatus(User $user, string $eventId, string $status): Event
    {
        $event = $this->findEvent($user, $eventId);

        if (!in_array($status, self::STATUSES, true)) {
            throw new \InvalidArgumentException("Unsupported event status '{$status}'.");
        }
        if ($event->status === 'cancelled' && $status !== 'cancelled') {
            throw new \InvalidArgumentException('A cancelled event cannot be reopened; create a new event instead.');
        }

        if ($status === 'published') {
            // The full readiness check: nothing goes on sale while something critical is missing.
            $blocking = array_values(array_filter($this->publicationReadiness($event, $user), fn ($check) => $check['critical']));
            if ($blocking !== []) {
                throw new \InvalidArgumentException('Cannot publish yet: ' . implode(' ', array_column($blocking, 'message')));
            }
            if (is_array($event->ticket_tiers) && !empty($event->ticket_tiers)) {
                $this->assertTicketTiersValid($event->ticket_tiers, (string) ($event->currency ?: config('events.market.default_currency', 'USD')));
            }
        }

        $previous = $event->status;
        $event->status = $status;
        $event->is_flyer_public = $status === 'published';
        $event->is_ordering_open = $status === 'published';
        $event->save();

        $this->audit($user, 'event_status_changed', 'events', $event->id, "Event '{$event->title}' status {$previous} -> {$status}.");

        return $this->findEvent($user, $eventId);
    }

    /** The event form's sections, for linking a readiness problem to where it is fixed. */
    public const READINESS_SECTIONS = [
        'overview' => 'Basics', 'location' => 'When and where', 'contact' => 'Contact', 'menu' => 'Food ordering',
        'tickets' => 'Tickets', 'policy' => 'Cancellations and refunds', 'branding' => 'Flyer and images', 'publishing' => 'Publishing',
    ];

    /**
     * Everything checked before an event may be published, as a list of problems. A critical problem blocks
     * publication; the rest are recommendations shown on the review screen. An empty list means ready.
     *
     * @return list<array{section: string, message: string, critical: bool}>
     */
    public function publicationReadiness(Event $event, ?User $user = null): array
    {
        $issues = [];
        $add = function (string $section, string $message, bool $critical = true) use (&$issues) {
            $issues[] = ['section' => $section, 'message' => $message, 'critical' => $critical];
        };
        $blank = fn ($value) => trim((string) $value) === '';

        // Basics
        if (mb_strlen(trim((string) $event->title)) < 3 || $event->title === 'Untitled event draft') {
            $add('overview', 'Give the event a name.');
        }
        if (mb_strlen(trim(strip_tags((string) $event->description))) < 20) {
            $add('overview', 'Write a description of at least a sentence or two, so guests know what to expect.');
        }

        // Date and time
        if ($blank($event->event_date_iso)) {
            $add('location', 'Set the event date.');
        } elseif (substr((string) $event->event_date_iso, 0, 10) < now($event->timezone ?: 'UTC')->toDateString() && $blank($event->end_date_iso)) {
            $add('location', 'The event date has passed. Choose a future date.');
        }
        if (!preg_match(\App\Services\Events\EventSchedule::TIME, (string) $event->start_time) && $blank($event->time_str)) {
            $add('location', 'Set the start time.');
        }
        if ($blank($event->end_time)) {
            $add('location', 'Add an end time so guests can plan their evening.', false);
        }

        // Location (or the online link for a virtual event)
        if ($event->event_format === 'virtual') {
            if ($blank($event->virtual_url)) {
                $add('location', 'Add the link guests use to join the online event.');
            }
        } else {
            if ($blank($event->venue_name)) {
                $add('location', 'Name the venue.');
            }
            $usAddress = strtoupper((string) ($event->country_code ?: 'US')) === 'US';
            if ($blank($event->street_address) || $blank($event->city) || $blank($event->zip_code) || ($usAddress && $blank($event->state_code))) {
                $add('location', 'Complete the venue address: street, city, ' . ($usAddress ? 'state and ZIP code.' : 'and postal code.'));
            }
            if (!$event->location_verified_at && app(\App\Services\Events\MapLocationService::class)->enabled()) {
                $add('location', 'Look the address up on the map so guests get a pin and directions.', false);
            }
        }

        // Host contact
        if ($blank($event->organizer_name)) {
            $add('contact', 'Add the host or organization name.');
        }
        if (strlen(preg_replace('/\D/', '', (string) $event->organizer_phone)) < 7 || preg_replace('/\D/', '', (string) $event->organizer_phone) === '0000000') {
            $add('contact', 'Add a phone number guests can call.');
        }
        if ($blank($event->organizer_email)) {
            $add('contact', 'Add a support email for questions about orders.', false);
        }

        // Tickets and their inventory
        $currency = (string) ($event->currency ?: config('events.market.default_currency', 'USD'));
        $tiers = array_values(array_filter(is_array($event->ticket_tiers) ? $event->ticket_tiers : [],
            fn ($tier) => is_array($tier) && ($tier['is_active'] ?? $tier['isActive'] ?? true) !== false));
        $hasFood = $event->supports_food_ordering !== false
            && MenuItem::withoutGlobalScopes()->where('event_id', $event->id)->where('is_active', true)->whereNull('deleted_at')->exists();
        if ($tiers === [] && !$hasFood) {
            $add('tickets', 'Add at least one ticket type (free events need one at $0), or a food menu to sell.');
        }
        foreach ($tiers as $tier) {
            $name = (string) ($tier['name'] ?? 'A ticket type');
            if (\App\Services\Checkout\TicketTierCatalog::priceMinorFor($tier, $currency) === null) {
                $add('tickets', "{$name} has no price.");
            }
            $available = \App\Services\Checkout\TicketTierCatalog::available($tier);
            if ($available === null) {
                $add('tickets', "{$name} has no ticket quantity. Set how many are available.");
            } elseif ($available <= 0) {
                $add('tickets', "{$name} has no tickets left to sell.", false);
            }
        }
        if ($tiers !== [] && collect($tiers)->every(fn ($tier) => (\App\Services\Checkout\TicketTierCatalog::available($tier) ?? 1) <= 0)) {
            $add('tickets', 'Every ticket type is sold out or has no tickets. Add tickets before publishing.');
        }

        // Food, when the host takes food orders
        if ($event->supports_food_ordering !== false) {
            $menu = MenuItem::withoutGlobalScopes()->where('event_id', $event->id)->whereNull('deleted_at')->get();
            $active = $menu->where('is_active', true);
            if ($active->isEmpty()) {
                $add('menu', 'Food ordering is on but the menu is empty. Add menu items, or turn food ordering off.');
            }
            if (in_array($event->food_ordering_mode, ['event_day', 'both'], true) && ($blank($event->food_service_start_time) || $blank($event->food_service_end_time))) {
                $add('menu', 'Event-day food orders need food service hours.');
            }
            foreach ($active->filter(fn (MenuItem $item) => $item->isPackage()) as $package) {
                $ids = collect((array) ($package->package_config['components'] ?? []))->pluck('menu_item_id')
                    ->merge(collect((array) ($package->package_config['choice_groups'] ?? []))->flatMap(fn ($g) => array_column((array) ($g['options'] ?? []), 'menu_item_id')));
                if ($ids->contains(fn ($id) => !$active->contains('id', $id))) {
                    $add('menu', "The '{$package->name}' package includes an item that is hidden or removed, so it cannot be ordered.");
                }
            }
            if ($active->isNotEmpty() && $active->every(fn (MenuItem $item) => blank($item->display_image_url))) {
                $add('menu', 'Add photos of your food. Guests order more when they can see it.', false);
            }
        }

        // Images
        if (!$event->flyer_path) {
            $add('branding', 'Upload a flyer or cover image. Until then the event uses a generated title card.', false);
        }

        // Refunds: the event's own policy or the organizer's
        if ($event->refund_policy_deadline_hours === null && $blank($event->refund_policy_notes)) {
            $policy = \App\Models\HostPaymentPolicy::where('organizer_id', $event->organizer_id)->first();
            if (!$policy || ($policy->refund_policy_deadline_hours === null && $blank($policy->refund_policy_notes))) {
                $add('policy', 'Set a refund policy: when guests can ask for a refund, and on what terms.');
            }
        }

        // Permission to publish
        if ($user && !$user->hasRole(AccountType::EVENT_HOST) && !$user->hasRole(AccountType::SYSTEM_ADMIN)) {
            $add('publishing', 'Only an approved event host can publish events.');
        }
        if ($event->status === 'cancelled') {
            $add('publishing', 'A cancelled event cannot be published again; create a new event instead.');
        }

        return $issues;
    }

    /**
     * @param array<string, mixed> $data Validated menu item fields (snake_case)
     */
    public function createMenuItem(User $user, string $eventId, array $data): Event
    {
        $event = $this->findEvent($user, $eventId);
        $data = $this->normalizeMenuAvailability($data, $event->timezone);
        $this->assertMenuItemConsistent($data);
        $data = $this->withPackageConfig($event, $data);

        // A caller may choose the new item's id (a UUID), to attach a photo to it straight after.
        $item = new MenuItem(array_merge($data, [
            'organizer_id' => $event->organizer_id,
            'event_id' => $event->id,
            'is_active' => $data['is_active'] ?? true,
            'sort_order' => $data['sort_order'] ?? ((int) $event->menuItems->max('sort_order') + 1),
        ]));
        if (isset($data['id']) && \Illuminate\Support\Str::isUuid((string) $data['id'])) {
            $item->id = (string) $data['id'];
        }
        $item->save();

        $this->audit($user, 'menu_item_created', 'menu_items', $event->id, "Menu item '{$data['name']}' added to '{$event->title}'.");

        return $this->findEvent($user, $eventId);
    }

    /**
     * @param array<string, mixed> $data Validated menu item fields (snake_case)
     */
    public function updateMenuItem(User $user, string $eventId, string $itemId, array $data): Event
    {
        $event = $this->findEvent($user, $eventId);
        $item = $this->findMenuItem($event, $itemId);
        $data = $this->normalizeMenuAvailability($data, $event->timezone);
        $this->assertMenuItemConsistent(array_merge($item->only(['pricing_type', 'custom_option_groups', 'custom_amount_config']), $data));
        if ($item->isPackage() || ($data['item_type'] ?? null) === 'package') {
            $data = $this->withPackageConfig($event, array_merge(['item_type' => 'package', 'package_config' => $item->package_config ?? []], $data), $item->id);
        }

        // Replacing an uploaded photo with a link (or nothing) removes the stored file
        if (array_key_exists('image_url', $data) && $data['image_url'] !== $item->image_url && $item->image_path) {
            $this->media->delete($item->image_path);
            $data['image_path'] = null;
        }

        $item->fill($data)->save();
        $this->audit($user, 'menu_item_updated', 'menu_items', $item->id, "Menu item '{$item->name}' updated on '{$event->title}'.");

        return $this->findEvent($user, $eventId);
    }

    /**
     * The host editor submits datetime-local values in the event's timezone; store them canonically in UTC.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalizeMenuAvailability(array $data, ?string $timezone): array
    {
        foreach (['available_from', 'available_until'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] !== null && $data[$field] !== '') {
                $data[$field] = Carbon::parse((string) $data[$field], $timezone ?: config('app.timezone'))->utc();
            }
        }

        return $data;
    }

    /**
     * Soft delete: past orders keep their frozen item snapshots.
     */
    public function deleteMenuItem(User $user, string $eventId, string $itemId): Event
    {
        $event = $this->findEvent($user, $eventId);
        $item = $this->findMenuItem($event, $itemId);

        if ($event->status === 'published' && $event->menuItems->count() <= 1) {
            throw new \InvalidArgumentException('A published event needs at least one menu item. Unpublish it first.');
        }
        // A package is built from the event's items; removing one it uses would leave the package unorderable.
        $usedBy = MenuItem::withoutGlobalScopes()->where('event_id', $event->id)->where('item_type', 'package')->whereNull('deleted_at')
            ->get()->first(fn (MenuItem $package) => str_contains((string) json_encode($package->package_config), '"' . $item->id . '"'));
        if ($usedBy && $usedBy->id !== $item->id) {
            throw new \InvalidArgumentException("'{$item->name}' is part of the '{$usedBy->name}' package. Remove it from the package first.");
        }

        $item->delete();
        $this->audit($user, 'menu_item_deleted', 'menu_items', $item->id, "Menu item '{$item->name}' removed from '{$event->title}'.");

        return $this->findEvent($user, $eventId);
    }

    public function uploadFlyer(User $user, string $eventId, UploadedFile $file, ?string $altText = null, int $positionX = 50, int $positionY = 50, ?array $crop = null): Event
    {
        $event = $this->findEvent($user, $eventId);
        $stored = $this->media->storeImage($file, $event->organizer_id, 'flyers');

        // Generate responsive derivatives for flyer (with optional crop)
        $derivatives = $this->media->createResponsiveDerivatives($stored['path'], $event->organizer_id, 'flyers', $crop);

        $previous = $event->flyer_path;
        $previousThumbnail = $event->flyer_thumbnail_path;

        $event->fill([
            'flyer_path' => $stored['path'],
            'flyer_thumbnail_path' => $derivatives['large']['path'],
            'flyer_mime_type' => $stored['mime'],
            'flyer_size_bytes' => $stored['size'],
            'flyer_width' => $stored['width'],
            'flyer_height' => $stored['height'],
            'flyer_alt_text' => $altText ?: ($event->title . ' event flyer'),
            'flyer_position_x' => max(0, min(100, $positionX)),
            'flyer_position_y' => max(0, min(100, $positionY)),
            'is_flyer_public' => $event->status === 'published',
        ])->save();

        // Clean up previous files
        $this->media->delete($previous);
        $this->media->delete($previousThumbnail);

        $this->audit($user, 'event_flyer_uploaded', 'events', $event->id, "Flyer uploaded for '{$event->title}' ({$stored['width']}x{$stored['height']}, {$stored['size']} bytes).");

        return $this->findEvent($user, $eventId);
    }

    public function removeFlyer(User $user, string $eventId): Event
    {
        $event = $this->findEvent($user, $eventId);
        $previous = $event->flyer_path;
        $previousThumbnail = $event->flyer_thumbnail_path;

        $event->fill([
            'flyer_path' => null,
            'flyer_thumbnail_path' => null,
            'flyer_mime_type' => null,
            'flyer_size_bytes' => null,
            'flyer_width' => null,
            'flyer_height' => null,
            'is_flyer_public' => false,
        ])->save();
        $this->media->delete($previous);
        $this->media->delete($previousThumbnail);

        $this->audit($user, 'event_flyer_removed', 'events', $event->id, "Flyer removed from '{$event->title}'.");

        return $this->findEvent($user, $eventId);
    }

    public function uploadEventMedia(
        User $user,
        string $eventId,
        UploadedFile $file,
        string $mediaType,
        string $altText,
        int $positionX = 50,
        int $positionY = 50,
        ?array $crop = null
    ): Event {
        $event = $this->findEvent($user, $eventId);
        if (!in_array($mediaType, \App\Models\EventMedia::TYPES, true)) {
            throw new \InvalidArgumentException('Unsupported event media type.');
        }
        if ($event->mediaItems()->where('media_type', $mediaType)->count() >= ($mediaType === 'gallery' ? 12 : 8)) {
            throw new \InvalidArgumentException('This event has reached its image limit for that category.');
        }
        $stored = $this->media->storeImage($file, $event->organizer_id, 'event-media');

        // Generate responsive derivatives
        $derivatives = $this->media->createResponsiveDerivatives($stored['path'], $event->organizer_id, 'event-media', $crop);

        $media = $event->mediaItems()->create([
            'media_type' => $mediaType,
            'original_path' => $stored['path'],
            'thumbnail_path' => $derivatives['large']['path'],
            'small_thumbnail_path' => $derivatives['small']['path'],
            'medium_thumbnail_path' => $derivatives['medium']['path'],
            'large_thumbnail_path' => $derivatives['large']['path'],
            'mime_type' => $stored['mime'],
            'size_bytes' => $stored['size'],
            'width' => $stored['width'],
            'height' => $stored['height'],
            'alt_text' => trim($altText),
            'position_x' => $positionX,
            'position_y' => $positionY,
            'sort_order' => (int) $event->mediaItems()->where('media_type', $mediaType)->max('sort_order') + 1,
            'crop_x' => $crop['x'] ?? null,
            'crop_y' => $crop['y'] ?? null,
            'crop_width' => $crop['width'] ?? null,
            'crop_height' => $crop['height'] ?? null,
        ]);
        $this->audit($user, 'event_media_uploaded', 'event_media', $media->id, "{$mediaType} image uploaded for '{$event->title}' ({$stored['width']}x{$stored['height']}).");

        return $this->findEvent($user, $eventId);
    }

    public function removeEventMedia(User $user, string $eventId, string $mediaId): Event
    {
        $event = $this->findEvent($user, $eventId);
        $media = $event->mediaItems()->whereKey($mediaId)->firstOrFail();
        $this->media->delete($media->original_path);
        $this->media->delete($media->thumbnail_path);
        $this->media->delete($media->small_thumbnail_path);
        $this->media->delete($media->medium_thumbnail_path);
        $this->media->delete($media->large_thumbnail_path);
        $media->delete();
        $this->audit($user, 'event_media_removed', 'event_media', $mediaId, "{$media->media_type} image removed from '{$event->title}'.");

        return $this->findEvent($user, $eventId);
    }

    public function uploadMenuPhoto(User $user, string $eventId, string $itemId, UploadedFile $file): Event
    {
        $event = $this->findEvent($user, $eventId);
        $item = $this->findMenuItem($event, $itemId);
        $stored = $this->media->storeImage($file, $event->organizer_id, 'menu');
        $previous = $item->image_path;

        $item->image_path = $stored['path'];
        // Version the URL so browsers fetch the new photo instead of a cached one
        $item->image_url = "/api/v1/media/menu/{$item->id}/photo?v=" . substr(sha1($stored['path']), 0, 10);
        $item->save();
        $this->media->delete($previous);

        $this->audit($user, 'menu_photo_uploaded', 'menu_items', $item->id, "Photo uploaded for '{$item->name}' on '{$event->title}'.");

        return $this->findEvent($user, $eventId);
    }

    /**
     * Puts the event's menu in the given order (menu item ids, first to last). Ids not on this event are ignored;
     * items left out keep their place after the listed ones.
     */
    public function reorderMenu(User $user, string $eventId, array $orderedIds): Event
    {
        $event = $this->findEvent($user, $eventId);
        $items = MenuItem::withoutGlobalScopes()->where('event_id', $event->id)->whereNull('deleted_at')
            ->orderBy('sort_order')->orderBy('name')->get()->keyBy('id');
        $ordered = collect($orderedIds)->map(fn ($id) => (string) $id)->filter(fn ($id) => $items->has($id))->unique()->values();
        $ordered = $ordered->merge($items->keys()->diff($ordered))->values();

        \Illuminate\Support\Facades\DB::transaction(function () use ($ordered, $items) {
            foreach ($ordered as $position => $id) {
                if ((int) $items[$id]->sort_order !== $position) {
                    $items[$id]->forceFill(['sort_order' => $position])->save();
                }
            }
        });
        $this->audit($user, 'menu_reordered', 'events', $event->id, "Menu order changed on '{$event->title}'.");

        return $this->findEvent($user, $eventId);
    }

    /** Removes an item's uploaded photo (and the stored file); the card then shows no photo. */
    public function removeMenuPhoto(User $user, string $eventId, string $itemId): Event
    {
        $event = $this->findEvent($user, $eventId);
        $item = $this->findMenuItem($event, $itemId);
        $previous = $item->image_path;
        $item->fill(['image_path' => null, 'image_url' => null, 'image_is_illustrative' => false, 'image_position' => null])->save();
        $this->media->delete($previous);
        $this->audit($user, 'menu_photo_removed', 'menu_items', $item->id, "Photo removed from '{$item->name}' on '{$event->title}'.");

        return $this->findEvent($user, $eventId);
    }

    /**
     * A package's definition, checked against the event's own items (MealPackage::normalizeConfig). A package is
     * sold at its fixed price; a package cannot contain another package or itself.
     */
    private function withPackageConfig(Event $event, array $data, ?string $selfId = null): array
    {
        if (($data['item_type'] ?? 'item') !== 'package') {
            unset($data['package_config']);

            return $data;
        }
        $config = \App\Services\Menu\MealPackage::normalizeConfig((array) ($data['package_config'] ?? []), $event);
        if ($selfId !== null && str_contains((string) json_encode($config), '"' . $selfId . '"')) {
            throw new \InvalidArgumentException('A package cannot contain itself.');
        }
        $data['package_config'] = $config;
        $data['pricing_type'] = 'fixed';
        $data['custom_option_groups'] = null;

        return $data;
    }

    private function findMenuItem(Event $event, string $itemId): MenuItem
    {
        $item = MenuItem::withoutGlobalScopes()->where('event_id', $event->id)->whereKey($itemId)->first();
        if (!$item) {
            throw (new ModelNotFoundException('Menu item not found.'))->setModel(MenuItem::class);
        }

        return $item;
    }

    private function assertValidTimezone(string $timezone): void
    {
        if (!in_array($timezone, timezone_identifiers_list(), true)) {
            throw new \InvalidArgumentException("Invalid IANA timezone '{$timezone}'.");
        }
    }

    /**
     * Validates ticket tiers pricing, stock availability, and sale windows on the server.
     *
     * @param array<int, array<string, mixed>> $tiers
     */
    private function assertTicketTiersValid(array $tiers, string $eventCurrency): void
    {
        foreach ($tiers as $idx => $tier) {
            if (!is_array($tier)) {
                throw new \InvalidArgumentException("Ticket tier #{$idx} must be an object.");
            }
            $name = trim((string) ($tier['name'] ?? ''));
            if (strlen($name) < 2) {
                throw new \InvalidArgumentException('Each ticket tier requires a name of at least 2 characters.');
            }
            $priceUsd = (float) ($tier['priceUSD'] ?? $tier['price_usd'] ?? -1);
            $priceJmd = (float) ($tier['priceJMD'] ?? $tier['price_jmd'] ?? -1);
            if ($priceUsd < 0 || $priceJmd < 0) {
                throw new \InvalidArgumentException("Ticket tier '{$name}' prices cannot be negative.");
            }
            $stock = (int) ($tier['availableStock'] ?? $tier['available_stock'] ?? 0);
            if ($stock <= 0) {
                throw new \InvalidArgumentException("Ticket tier '{$name}' must have a positive quantity limit.");
            }
            $maxPerCustomer = (int) ($tier['maxPerCustomer'] ?? $tier['max_per_customer'] ?? 1);
            if ($maxPerCustomer < 1 || $maxPerCustomer > $stock) {
                throw new \InvalidArgumentException("Ticket tier '{$name}' maxPerCustomer must be between 1 and availableStock ({$stock}).");
            }
            $minPerOrder = (int) ($tier['minPerOrder'] ?? $tier['min_per_order'] ?? 1);
            if ($minPerOrder < 1 || $minPerOrder > $maxPerCustomer || $minPerOrder > $stock) {
                throw new \InvalidArgumentException("Ticket tier '{$name}' minimum per order must be between 1 and its maximum per order.");
            }
            $admissionCount = (int) ($tier['admissionCount'] ?? $tier['admission_count'] ?? 1);
            if ($admissionCount < 1 || $admissionCount > 100) {
                throw new \InvalidArgumentException("Ticket tier '{$name}' admissions per ticket must be between 1 and 100.");
            }
            if (isset($tier['category']) && (!is_string($tier['category']) || strlen($tier['category']) > 40)) {
                throw new \InvalidArgumentException("Ticket tier '{$name}' has an invalid category.");
            }
            if (isset($tier['visibility']) && !in_array($tier['visibility'], ['public', 'hidden'], true)) {
                throw new \InvalidArgumentException("Ticket tier '{$name}' visibility must be public or hidden.");
            }
            if (isset($tier['designStyle']) && !in_array($tier['designStyle'], ['premium_minimal', 'vip_dark', 'festival_color', 'modern_digital', 'elegant_gold', 'concert_pass'], true)) {
                throw new \InvalidArgumentException("Ticket tier '{$name}' has an unsupported design style.");
            }
            if (isset($tier['accentColor']) && !in_array($tier['accentColor'], ['indigo', 'emerald', 'coral', 'cyan', 'violet', 'gold'], true)) {
                throw new \InvalidArgumentException("Ticket tier '{$name}' has an unsupported accent color.");
            }
            if (isset($tier['backgroundStyle']) && !in_array($tier['backgroundStyle'], ['solid', 'gradient', 'glow'], true)) {
                throw new \InvalidArgumentException("Ticket tier '{$name}' has an unsupported background style.");
            }
            if (isset($tier['benefits']) && (!is_array($tier['benefits']) || count($tier['benefits']) > 20)) {
                throw new \InvalidArgumentException("Ticket tier '{$name}' may have at most 20 benefits.");
            }
            $saleStart = $tier['saleStartIso'] ?? $tier['sale_start_iso'] ?? null;
            $saleEnd = $tier['saleEndIso'] ?? $tier['sale_end_iso'] ?? null;
            if (!empty($saleStart) && !empty($saleEnd)) {
                $startTs = strtotime((string) $saleStart);
                $endTs = strtotime((string) $saleEnd);
                if ($startTs === false || $endTs === false || $startTs > $endTs) {
                    throw new \InvalidArgumentException("Ticket tier '{$name}' sale start date must be on or before sale end date.");
                }
            }
        }
    }

    /**
     * The checkout has one authoritative currency per event; persist that currency on each tier for display.
     *
     * @param array<int, mixed> $tiers
     * @return array<int, mixed>
     */
    private function setTicketCurrency(array $tiers, string $currency): array
    {
        foreach ($tiers as &$tier) {
            if (is_array($tier)) {
                $tier['currency'] = strtoupper($currency);
            }
        }
        unset($tier);

        return $tiers;
    }

    /**
     * Customizable items need at least one option group; custom-amount items need a sane min/max range.
     *
     * @param array<string, mixed> $data
     */
    private function assertMenuItemConsistent(array $data): void
    {
        $type = $data['pricing_type'] ?? 'fixed';

        if ($type === 'customizable' && empty($data['custom_option_groups'])) {
            throw new \InvalidArgumentException('Customizable items need at least one option group.');
        }

        if ($type === 'custom_amount') {
            $config = $data['custom_amount_config'] ?? null;
            if (!is_array($config)) {
                throw new \InvalidArgumentException('Custom-amount items need minimum and maximum amounts.');
            }
            foreach (['USD', 'JMD'] as $currency) {
                $min = (float) ($config["min{$currency}"] ?? 0);
                $max = (float) ($config["max{$currency}"] ?? 0);
                if ($min <= 0 || $max < $min) {
                    throw new \InvalidArgumentException("Custom-amount range in {$currency} must have a positive minimum no larger than the maximum.");
                }
            }
        }
    }

    /** @param array<int, array<string, mixed>> $items */
    private function assertMenuItemsConsistent(array $items): void
    {
        foreach ($items as $item) {
            if (is_array($item) && filled($item['name'] ?? null)) {
                $this->assertMenuItemConsistent($item);
            }
        }
    }

    private function audit(User $user, string $action, string $entity, string $targetId, string $details): void
    {
        SecurityAuditLog::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'actor_role' => $user->getActiveRole()->value,
            'action' => $action,
            'target_entity' => $entity,
            'target_id' => $targetId,
            'ip_address' => request()->ip() ?? '127.0.0.1',
            'user_agent' => request()->userAgent() ?? 'api',
            'details' => $details,
            'is_pii_access' => false,
            'created_at' => now(),
        ]);
    }
}
