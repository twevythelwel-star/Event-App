<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEventRequest;
use App\Http\Requests\UploadFlyerRequest;
use App\Models\Event;
use App\Models\TargetMarket;
use App\Models\UsState;
use App\Services\LocationAddressNormalizer;
use App\Services\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EventController extends Controller
{
    /**
     * Display a listing of events for the active organizer.
     * Enforces configurable browsing policy (require_login_for_browsing).
     */
    public function index(Request $request): mixed
    {
        if (config('events.require_login_for_browsing', false) && !auth()->check()) {
            if ($request->wantsJson()) {
                return response()->json(['error' => 'Authentication required: Please sign in to browse events.'], 401);
            }
            return redirect()->guest(route('login'))->with('error', 'Please sign in to browse events.');
        }

        // Soonest first; events without a machine-readable date go last.
        // Public discovery: every organizer's published events (the signed-in user's own organizer scope must not
        // narrow it), and never drafts, unpublished or cancelled events. Soonest first; undated events last.
        $query = Event::allTenants()->with(['menuItems' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])
            ->where('status', 'published')
            ->where('visibility', 'public')
            ->where(fn ($q) => $q->whereNull('event_date_iso')->orWhereDate('event_date_iso', '>=', now()->toDateString()));
        if ($request->filled('q')) {
            $term = '%'.mb_strtolower(trim((string) $request->query('q'))).'%';
            $query->where(fn ($q) => $q->whereRaw('LOWER(title) LIKE ?', [$term])
                ->orWhereRaw('LOWER(COALESCE(category, \'\')) LIKE ?', [$term])
                ->orWhereRaw('LOWER(COALESCE(city, \'\')) LIKE ?', [$term]));
        }
        $perPage = min(48, max(12, (int) $request->query('per_page', 24)));
        $events = $query->orderByRaw('event_date_iso IS NULL')->orderBy('event_date_iso')->orderBy('start_time')
            ->paginate($perPage)->withQueryString();
        $events->setCollection($events->getCollection()
            ->reject(fn (Event $event) => \App\Support\EventListing::hasEnded($event))->values());

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'data' => $events->items(),
                'links' => ['first' => $events->url(1), 'last' => $events->url($events->lastPage()),
                    'prev' => $events->previousPageUrl(), 'next' => $events->nextPageUrl()],
                'meta' => ['current_page' => $events->currentPage(), 'last_page' => $events->lastPage(),
                    'per_page' => $events->perPage(), 'total' => $events->total()],
            ]);
        }

        return view('events.index', [
            'events' => $events,
            'states' => UsState::where('is_enabled', true)->orderBy('name')->get(),
            'markets' => TargetMarket::where('is_enabled', true)->orderBy('city')->get(),
        ]);
    }

    /**
     * The typographic flyer shown for an event without uploaded artwork. Only published events are drawn;
     * anything else gets the plain placeholder, so a draft's name is never exposed through this URL.
     */
    public function generatedFlyer(string $id): \Illuminate\Http\Response|\Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $event = Event::allTenants()->whereKey($id)->where('status', 'published')->where('visibility', '!=', 'private')->first();
        if (!$event) {
            return response()->file(public_path('images/event-placeholder.svg'), ['Content-Type' => 'image/svg+xml']);
        }

        return response(\App\Support\GeneratedFlyer::svg($event), 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'public, max-age=600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Preview the event as it will appear to the public.
     * Only accessible to event managers. Reuses the actual show view with preview mode flag.
     */
    public function preview(Request $request, string $id): mixed
    {
        $event = $this->managedEvent($request, $id);

        // Everything below is read from the event's own configuration; ordering itself happens in CheckoutController.
        $currency = $event->currency ?: \App\Support\Currency::defaultCode();
        $tiers = \App\Services\Checkout\TicketTierCatalog::sellable($event);
        $menu = $event->menuItems->where('is_active', true)->sortBy('sort_order')->values();
        $availability = \App\Support\EventListing::availability($event);
        $fromPriceText = \App\Support\EventListing::fromPriceText($event);
        $canOrder = in_array($availability['key'], ['on_sale', 'food'], true)
            || ($event->isOrderingAllowed() && $menu->isNotEmpty() && $event->supports_food_ordering);

        // Accepted payment methods: names only. Account details are shown on the customer's own order.
        $paymentMethods = \App\Services\Checkout\PaymentOptions::forEvent($event);
        $chargeRules = (new \App\Services\Pricing\ChargeCalculator())->applicableRules($event);
        $packagingFeeMinor = $menu->isNotEmpty() ? (int) $event->packaging_fee_minor : 0;
        $policy = \App\Models\HostPaymentPolicy::where('organizer_id', $event->organizer_id)->first();

        $qr = \App\Models\EventQrCode::where('event_id', $event->id)->first();
        $eventQr = $qr && $qr->isReady()
            ? ['image' => route('event-qr.public', $qr->public_code), 'link' => route('event-qr.resolve', $qr->public_code)]
            : null;

        return view('events.preview', compact('event', 'tiers', 'menu', 'currency', 'availability', 'fromPriceText', 'canOrder',
            'paymentMethods', 'chargeRules', 'packagingFeeMinor', 'policy', 'eventQr'));
    }

    /**
     * Display the specified event.
     * Enforces configurable browsing policy (require_login_for_browsing).
     */
    public function show(Request $request, string $id): mixed
    {
        if (config('events.require_login_for_browsing', false) && !auth()->check()) {
            if ($request->wantsJson()) {
                return response()->json(['error' => 'Authentication required: Please sign in to view this event.'], 401);
            }
            return redirect()->guest(route('login'))->with('error', 'Please sign in to view this event.');
        }

        $event = Event::with(['menuItems', 'mediaItems'])->findOrFail($id);
        // A promoter's link (?ref=CODE): remember them for this buyer's session, so their sale is credited.
        if ($request->filled('ref')) {
            $affiliate = \App\Models\Affiliate::where('event_id', $event->id)->where('is_active', true)
                ->where('code', \App\Services\Checkout\DiscountService::normalize((string) $request->query('ref')))->first();
            if ($affiliate) {
                $request->session()->put("affiliate.{$event->id}", $affiliate->id);
            }
        }

        // Only published events are public. Draft, unpublished and cancelled events are visible to the people
        // who manage them, and behave as not found for everyone else (including via an event QR short link).
        if (($event->status !== 'published' || $event->visibility === 'private') && !$this->canManage($request, $event)) {
            abort(404);
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'data' => [
                    'event' => $event,
                    'is_ordering_allowed' => $event->isOrderingAllowed(),
                ],
            ]);
        }

        // Everything below is read from the event's own configuration; ordering itself happens in CheckoutController.
        $currency = $event->currency ?: \App\Support\Currency::defaultCode();
        $tiers = \App\Services\Checkout\TicketTierCatalog::sellable($event);
        $menu = $event->menuItems->where('is_active', true)->sortBy('sort_order')->values();
        $availability = \App\Support\EventListing::availability($event);
        $fromPriceText = \App\Support\EventListing::fromPriceText($event);
        $canOrder = in_array($availability['key'], ['on_sale', 'food'], true)
            || ($event->isOrderingAllowed() && $menu->isNotEmpty() && $event->supports_food_ordering);

        // Accepted payment methods: names only. Account details are shown on the customer's own order.
        $paymentMethods = \App\Services\Checkout\PaymentOptions::forEvent($event);
        $chargeRules = (new \App\Services\Pricing\ChargeCalculator())->applicableRules($event);
        $packagingFeeMinor = $menu->isNotEmpty() ? (int) $event->packaging_fee_minor : 0;
        $policy = \App\Models\HostPaymentPolicy::where('organizer_id', $event->organizer_id)->first();

        $qr = \App\Models\EventQrCode::where('event_id', $event->id)->first();
        $eventQr = $qr && $qr->isReady() && $event->status === 'published'
            ? ['image' => route('event-qr.public', $qr->public_code), 'link' => route('event-qr.resolve', $qr->public_code)]
            : null;

        return view('events.show', compact('event', 'tiers', 'menu', 'currency', 'availability', 'fromPriceText', 'canOrder',
            'paymentMethods', 'chargeRules', 'packagingFeeMinor', 'policy', 'eventQr'));
    }

    private function canManage(Request $request, Event $event): bool
    {
        if (!$request->user()) {
            return false;
        }

        try {
            $this->managedEvent($request, (string) $event->id);

            return true;
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return false;
        }
    }

    /**
     * The event, if the signed-in user may manage it (their organizer's event, or any event for a System
     * Administrator). Anyone else gets "not found", the same as for an event that does not exist.
     */
    private function managedEvent(Request $request, string $id): Event
    {
        $user = $request->user();
        if (!$user) {
            abort(404);
        }

        return app(\App\Services\EventManagementService::class)->findEvent($user, $id);
    }

    /**
     * The organizer a new event belongs to: the active organizer when the user may manage it, otherwise the
     * user's only manageable organizer. Null when that is not clear (no organizer, or several and none chosen).
     */
    private function organizerForNewEvent(Request $request): ?string
    {
        $manageable = app(\App\Services\EventManagementService::class)->manageableOrganizerIds($request->user());
        $active = TenantContext::id();

        if ($manageable === null) {
            // System Administrators act for the organizer they have switched into, or the one they choose on the
            // create form; the choice must be an existing organizer.
            $chosen = (string) $request->input('organizer_id', '');

            return $active ?? ($chosen !== '' && \App\Models\Organizer::whereKey($chosen)->exists() ? $chosen : null);
        }
        if ($active !== null && $manageable->contains($active)) {
            return $active;
        }

        return $manageable->count() === 1 ? (string) $manageable->first() : null;
    }

    /**
     * Show the form for creating a new event.
     * Requires active Event Host privileges.
     */
    public function create(Request $request): mixed
    {
        $user = $request->user();
        if ($user && !$user->hasRole(\App\Enums\AccountType::EVENT_HOST) && !$user->hasRole(\App\Enums\AccountType::SYSTEM_ADMIN)) {
            if ($request->wantsJson()) {
                return response()->json(['error' => 'Forbidden: You must be an approved Event Host to create events.'], 403);
            }
            return redirect()->route('host.apply')->with('error', 'You must apply and be approved as an Event Host to create events.');
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Event creation form metadata',
            ]);
        }

        if ($request->filled('draft')) {
            $draft = app(\App\Services\EventManagementService::class)->findEvent($user, (string) $request->query('draft'));
            abort_unless($draft->status === 'draft', 404);
            $draft->load(['menuItems' => fn ($query) => $query->withoutGlobalScopes()->orderBy('sort_order')]);
        }

        return view('events.create', [
            'states' => UsState::where('is_enabled', true)->orderBy('name')->get(),
            'markets' => TargetMarket::where('is_enabled', true)->orderByDesc('is_featured')->orderBy('discovery_priority')->get(),
            'timezones' => LocationAddressNormalizer::supportedTimezones(),
            'traditions' => (array) config('traditions', []),
            'draft' => isset($draft) ? $draft : null,
        ]);
    }

    /** Save a server-confirmed draft snapshot during the creation workflow. */
    public function autosave(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && ($user->hasRole(\App\Enums\AccountType::EVENT_HOST) || $user->hasRole(\App\Enums\AccountType::SYSTEM_ADMIN)), 403);
        $data = $request->validate([
            'ticket_tiers_submitted' => ['sometimes', 'boolean'],
            'title' => ['nullable', 'string', 'max:255'],
            'tagline' => ['nullable', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:128', 'regex:/^[a-z0-9-]+$/'],
            'description' => ['nullable', 'string', 'max:5000'],
            'category' => ['nullable', 'string', 'max:128'],
            'traditions' => ['nullable', 'array', 'max:4'],
            'traditions.*' => ['string', \Illuminate\Validation\Rule::in(array_keys((array) config('traditions', [])))],
            'event_date_iso' => ['nullable', 'date_format:Y-m-d'],
            'date_str' => ['nullable', 'string', 'max:128'],
            'time_str' => ['nullable', 'string', 'max:128'],
            'timezone' => ['nullable', \Illuminate\Validation\Rule::in(LocationAddressNormalizer::supportedTimezones())],
            'venue_name' => ['nullable', 'string', 'max:255'], 'street_address' => ['nullable', 'string', 'max:255'],
            'venue_building_name' => ['nullable', 'string', 'max:180'], 'city' => ['nullable', 'string', 'max:120'],
            'state_code' => ['nullable', 'string', 'size:2', \Illuminate\Validation\Rule::exists('us_states', 'code')->where('is_enabled', true)], 'zip_code' => ['nullable', 'regex:/^\d{5}(?:-\d{4})?$/'],
            'organizer_name' => ['nullable', 'string', 'max:255'], 'organizer_phone' => ['nullable', 'string', 'max:32'],
            'organizer_whatsapp' => ['nullable', 'string', 'max:32'], 'organizer_email' => ['nullable', 'email', 'max:255'],
            'advance_order_deadline' => ['nullable', 'date'], 'packaging_fee_usd' => ['nullable', 'numeric', 'min:0'],
            'pickup_instructions' => ['nullable', 'string', 'max:1000'], 'refund_policy_deadline_hours' => ['nullable', 'integer', 'min:0', 'max:720'],
            'refund_policy_notes' => ['nullable', 'string', 'max:2000'],
            'ticket_tiers' => ['nullable', 'array', 'max:20'], 'ticket_tiers.*.name' => ['nullable', 'string', 'max:120'],
            'ticket_tiers.*.price' => ['nullable', 'numeric', 'min:0'], 'ticket_tiers.*.available_stock' => ['nullable', 'integer', 'min:1'],
            'ticket_tiers.*.max_per_customer' => ['nullable', 'integer', 'min:1', 'max:100'],
            'ticket_tiers.*.description' => ['nullable', 'string', 'max:300'], 'ticket_tiers.*.sale_start' => ['nullable', 'date'],
            'ticket_tiers.*.sale_end' => ['nullable', 'date'],
        ]);

        $organizerId = $this->organizerForNewEvent($request);
        if ($organizerId === null) {
            return response()->json(['message' => 'Choose an organizer context before saving this draft.'], 422);
        }
        if (!filled($eventData['slug'] ?? null) && filled($eventData['title'] ?? null)) {
            $eventData['slug'] = Str::slug($eventData['title']) . '-' . Str::lower(Str::random(6));
        }

        $title = trim((string) ($data['title'] ?? ''));
        $eventData = array_merge($data, [
            'title' => mb_strlen($title) >= 3 ? $title : 'Untitled event draft',
            'date_str' => $data['date_str'] ?? '', 'time_str' => $data['time_str'] ?? '',
            'venue_name' => $data['venue_name'] ?? '', 'organizer_name' => $data['organizer_name'] ?? $user->name,
            'organizer_phone' => $data['organizer_phone'] ?? $user->phone ?? '0000000',
            'advance_order_deadline' => $data['advance_order_deadline'] ?? now()->addDays(14)->toIso8601String(),
            'country_code' => 'US', 'currency' => 'USD', 'organizer_id' => $organizerId, 'status' => 'draft',
            'ticket_tiers' => collect($data['ticket_tiers'] ?? [])->filter(fn ($row) => filled($row['name'] ?? null) && is_numeric($row['price'] ?? null) && is_numeric($row['available_stock'] ?? null))
                ->map(fn ($row) => ['name' => $row['name'], 'price' => $row['price'], 'available_stock' => $row['available_stock'], 'max_per_customer' => min((int) ($row['max_per_customer'] ?? 1), (int) $row['available_stock']), 'description' => $row['description'] ?? '', 'sale_start' => $row['sale_start'] ?? null, 'sale_end' => $row['sale_end'] ?? null])->values()->all(),
        ]);
        if (filled($eventData['street_address'] ?? null) && filled($eventData['city'] ?? null) && filled($eventData['state_code'] ?? null) && filled($eventData['zip_code'] ?? null)) {
            $eventData = app(LocationAddressNormalizer::class)->normalize($eventData);
        } else {
            $eventData['timezone'] = ($eventData['timezone'] ?? null) ?: config('events.market.default_timezone', 'America/New_York');
        }
        $key = $request->input('creation_key');
        $event = $this->eventForCreationKey($organizerId, is_string($key) ? $key : null)
            ?? app(\App\Services\EventManagementService::class)->createEvent($user, $eventData + ['creation_key' => is_string($key) ? $key : null]);

        return response()->json(['saved' => true, 'event_id' => $event->id, 'resume_url' => route('events.create', ['draft' => $event->id]), 'edit_url' => route('events.edit', $event->id), 'saved_at' => now()->toIso8601String()], 201);
    }

    /**
     * Show the form for editing an existing event.
     */
    public function edit(Request $request, string $id): mixed
    {
        $event = $this->managedEvent($request, $id);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'data' => $event,
            ]);
        }

        // Only managers reach this screen (managedEvent). ensureFor() also creates the QR identity for events
        // made before QR codes existed.
        $qrService = app(\App\Services\EventQr\EventQrService::class);
        $qr = $qrService->ensureFor($event);
        $qrDestination = $qrService->destinationUrl($qr);

        return view('events.edit', [
            'event' => $event,
            'qr' => $qr,
            'qrDestination' => $qrDestination,
            'states' => UsState::where('is_enabled', true)->orderBy('name')->get(),
            'markets' => TargetMarket::where('is_enabled', true)->orderByDesc('is_featured')->orderBy('discovery_priority')->get(),
            'timezones' => LocationAddressNormalizer::supportedTimezones(),
        ]);
    }

    /**
     * Store a newly created event for the active organizer.
     */
    public function store(StoreEventRequest $request): mixed
    {
        $user = $request->user() ?? (function_exists('auth') ? auth()->user() : null);
        if ($user && !$user->hasRole(\App\Enums\AccountType::EVENT_HOST) && !$user->hasRole(\App\Enums\AccountType::SYSTEM_ADMIN)) {
            if ($request->wantsJson()) {
                return response()->json(['error' => 'Forbidden: You must be an approved Event Host to create events.'], 403);
            }
            return redirect()->route('host.apply')->with('error', 'You must apply and be approved as an Event Host to create events.');
        }

        // A draft may leave the address unfinished; the readiness check asks for it before publishing.
        $validated = app(LocationAddressNormalizer::class)->normalize($request->safe()->except(['flyer', 'ticket_tiers', 'menu_items'])
            + ['event_status' => 'draft']);
        $menuItems = (array) $request->validated('menu_items', []);
        if ($menuItems !== []) {
            foreach ($menuItems as $index => &$item) {
                $item['currency'] = $validated['currency'] ?? 'USD';
                $item['available_stock'] = ($item['available_stock'] ?? '') !== '' ? (int) $item['available_stock'] : null;
                $item['allow_special_instructions'] = (bool) ($item['allow_special_instructions'] ?? false);
                $item['sort_order'] = (int) $index;
            }
            unset($item);
            $validated['menu_items'] = $menuItems;
        }
        if ($request->boolean('traditions_submitted')) {
            $validated['traditions'] = array_values(array_unique((array) $request->validated('traditions', [])));
        }
        // The country picker: an empty choice is a choice too. Synced by EventManagementService on create.
        unset($validated['country_ids_submitted']);
        if ($request->boolean('country_ids_submitted')) {
            $validated['country_ids'] = array_values(array_unique((array) $request->validated('country_ids', [])));
        }
        if ($request->boolean('ticket_tiers_submitted') || $request->has('ticket_tiers')) {
            $validated['ticket_tiers'] = self::tiersFromForm((array) $request->validated('ticket_tiers', []), [], $validated['timezone'] ?? null);
        }
        $requestedStatus = $validated['status'] ?? 'draft';
        unset($validated['status']);
        $organizerId = $user ? $this->organizerForNewEvent($request) : null;
        if ($organizerId === null) {
            $message = 'Choose the organizer this event belongs to. Your account is not linked to exactly one organizer you can manage.';
            if ($request->wantsJson()) {
                return response()->json(['error' => $message], 403);
            }
            abort(403, $message);
        }
        $validated['organizer_id'] = $organizerId;

        // A refresh or retry of the same form (same creation key) returns the event it already created.
        if (($existing = $this->eventForCreationKey($organizerId, $validated['creation_key'] ?? null)) !== null) {
            return $request->wantsJson()
                ? response()->json(['success' => true, 'message' => 'This event was already created.', 'data' => $existing], 200)
                : redirect()->route('events.edit', $existing->id)->with('status', "'{$existing->title}' was already saved. You're editing it now.");
        }

        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['title']) . '-' . Str::random(6);
        }

        // The event, its ticket types, starter menu items and catalog links are created together or not at all.
        try {
            $event = DB::transaction(fn () => app(\App\Services\EventManagementService::class)->createEvent($user, $validated));
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            // Two submissions of the same form raced; the other one created the event.
            $existing = $this->eventForCreationKey($organizerId, $validated['creation_key'] ?? null);
            if ($existing === null) {
                throw $e;
            }
            return redirect()->route('events.edit', $existing->id)->with('status', "'{$existing->title}' was already saved. You're editing it now.");
        }

        // The flyer first, so the readiness check below sees it. A flyer that cannot be used never loses the event.
        $flyerProblem = $this->storeFlyerUpload($request, $event);
        $event->refresh();

        // Publishing runs the readiness check. If something is missing the event stays a saved draft and the host
        // sees the review screen: the form is not sent back, so trying again cannot create a second event.
        $publishProblem = null;
        if ($requestedStatus === 'published') {
            try {
                $event = app(\App\Services\EventManagementService::class)->setStatus($user, $event->id, 'published');
            } catch (\InvalidArgumentException $exception) {
                $publishProblem = $exception->getMessage();
            }
        }
        if ($flyerProblem !== null && $publishProblem === null && !$request->wantsJson()) {
            return redirect()->route('events.edit', $event->id)
                ->with('error', "'{$event->title}' is saved, but the flyer was not: {$flyerProblem}");
        }

        // Every new event gets its promotional QR identity and artwork. Failure here never fails the event:
        // the identity is kept as "failed" and the host can retry from the event screen.
        app(\App\Services\EventQr\EventQrService::class)->ensureFor($event);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $publishProblem ? 'Event saved as a draft. ' . $publishProblem : 'Event created successfully',
                'data' => $event,
            ], 201);
        }

        if ($publishProblem !== null) {
            return redirect()->route('events.review', $event->id)
                ->with('error', "'{$event->title}' is saved as a draft. Finish the items below, then publish.");
        }

        return redirect()->route('events.edit', $event->id)->with('status', $event->status === 'published'
            ? "'{$event->title}' is published. Guests can now find it and buy tickets."
            : "'{$event->title}' is saved as a draft. Nothing is on sale until you publish it.");
    }

    /** The event a create form with this key already made for the organizer, if any. */
    private function eventForCreationKey(?string $organizerId, ?string $key): ?Event
    {
        if (!$organizerId || !$key || !Str::isUuid($key)) {
            return null;
        }

        return Event::withoutGlobalScopes()->where('organizer_id', $organizerId)->where('creation_key', $key)->first();
    }

    /**
     * The final review before publishing: every readiness check, with links to the section that fixes it.
     */
    public function review(Request $request, string $id): mixed
    {
        $event = $this->managedEvent($request, $id);
        $issues = app(\App\Services\EventManagementService::class)->publicationReadiness($event, $request->user());
        $menu = \App\Models\MenuItem::withoutGlobalScopes()->where('event_id', $event->id)->whereNull('deleted_at')->where('is_active', true)->get();

        return view('events.review', [
            'event' => $event,
            'issues' => $issues,
            'blocking' => array_values(array_filter($issues, fn ($i) => $i['critical'])),
            'suggestions' => array_values(array_filter($issues, fn ($i) => !$i['critical'])),
            'sections' => \App\Services\EventManagementService::READINESS_SECTIONS,
            'tiers' => \App\Services\Checkout\TicketTierCatalog::sellable($event),
            'itemCount' => $menu->reject(fn ($m) => $m->isPackage())->count(),
            'packageCount' => $menu->filter(fn ($m) => $m->isPackage())->count(),
        ]);
    }

    /**
     * Update the specified event.
     */
    public function update(StoreEventRequest $request, string $id): mixed
    {
        $event = $this->managedEvent($request, $id);
        $before = $event->only(\App\Services\Notifications\NotificationService::EVENT_NOTICE_FIELDS);
        $location = [
            'street_address' => $event->street_address,
            'venue_building_name' => $event->venue_building_name,
            'city' => $event->city,
            'state_code' => $event->state_code,
            'zip_code' => $event->zip_code,
            'country_code' => $event->country_code,
            'latitude' => $event->latitude,
            'longitude' => $event->longitude,
            'timezone' => $event->timezone,
        ];
        // A draft may keep an unfinished address (the readiness check asks for it before publishing).
        $changes = app(LocationAddressNormalizer::class)->normalize(array_merge($location, $request->safe()->except(['flyer', 'ticket_tiers', 'menu_items']),
            ['event_status' => $event->status === 'published' ? 'published' : 'draft']));
        unset($changes['event_status']);
        if ($request->boolean('traditions_submitted')) {
            // Unticking every box is a choice too: the event then shows no traditions.
            $changes['traditions'] = array_values(array_unique((array) $request->validated('traditions', [])));
        }
        $menuItems = (array) $request->validated('menu_items', []);
        if ($menuItems !== []) {
            foreach ($menuItems as $index => &$item) {
                $item['currency'] = $changes['currency'] ?? $event->currency ?? 'USD';
                $item['available_stock'] = ($item['available_stock'] ?? '') !== '' ? (int) $item['available_stock'] : null;
                $item['allow_special_instructions'] = (bool) ($item['allow_special_instructions'] ?? false);
                $item['sort_order'] = (int) $index;
            }
            unset($item);
            $changes['menu_items'] = $menuItems;
        }
        if ($request->boolean('ticket_tiers_submitted')) {
            // Through the management service, which checks the tiers and audits the change.
            try {
                app(\App\Services\EventManagementService::class)->updateEvent($request->user(), $event->id, [
                    'ticket_tiers' => self::tiersFromForm((array) $request->validated('ticket_tiers', []), (array) ($event->ticket_tiers ?? []), $event->timezone),
                    'currency' => $changes['currency'] ?? $event->currency,
                ]);
            } catch (\InvalidArgumentException $e) {
                throw \Illuminate\Validation\ValidationException::withMessages(['ticket_tiers' => $e->getMessage()]);
            }
            $event->refresh();
        }
        // The timetable, map pin and food plan, checked together with what the event already has.
        $changes = \App\Services\Events\EventDetails::prepare($changes, $event);
        if (array_key_exists('advance_order_deadline', $changes) && filled($changes['advance_order_deadline'])) {
            // Typed in the event's own time zone, like every other time on the form.
            $changes['advance_order_deadline'] = \Illuminate\Support\Carbon::parse($changes['advance_order_deadline'], $changes['timezone'] ?? $event->timezone)->utc();
        }
        // Status and ordering are never set by saving the form: publishing goes through the readiness check
        // (setStatus) and ordering through toggleOrdering, so a crafted form cannot put a draft on sale.
        $requestedStatus = $changes['status'] ?? null;
        unset($changes['status'], $changes['is_ordering_open'], $changes['organizer_id'], $changes['creation_key']);
        // Countries come from the picker and live in their own table, not on the event row.
        $countryIds = $request->boolean('country_ids_submitted') ? array_values(array_unique((array) $request->validated('country_ids', []))) : null;
        unset($changes['country_ids'], $changes['country_ids_submitted']);
        $event->update($changes);
        if ($countryIds !== null) {
            $event->countries()->sync($countryIds);
        }
        // Ticket holders hear when the date, time or place changes.
        \App\Services\Notifications\NotificationService::afterEventSaved($event, $before);

        $flyerProblem = $this->storeFlyerUpload($request, $event);
        $event->refresh();
        if ($flyerProblem !== null && !$request->wantsJson()) {
            return redirect()->route('events.edit', $event->id)->with('error', "Your changes are saved, but the flyer was not: {$flyerProblem}");
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Event updated successfully',
                'data' => $event,
            ]);
        }

        // Asked to publish with the save: only through the readiness check.
        if ($requestedStatus === 'published' && $event->status !== 'published') {
            try {
                app(\App\Services\EventManagementService::class)->setStatus($request->user(), $event->id, 'published');
            } catch (\InvalidArgumentException $e) {
                return redirect()->route('events.review', $event->id)->with('error', "Your changes are saved, but '{$event->title}' is not ready to publish yet.");
            }

            return redirect()->route('events.edit', $event->id)->with('status', "'{$event->title}' is saved and published.");
        }

        // Back to the form, so the host sees what was saved.
        return redirect()->route('events.edit', $event->id)->with('status', "Event '{$event->title}' saved.");
    }

    /**
     * Toggle advance ordering status.
     */
    public function toggleOrdering(Request $request, string $id): mixed
    {
        $event = $this->managedEvent($request, $id);
        // Only a published event can take orders; a draft is opened by publishing it, after the readiness check.
        if (!$event->is_ordering_open && $event->status !== 'published') {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'error' => 'Publish the event before opening it for orders.'], 422);
            }

            return back()->with('error', 'Publish the event before opening it for orders.');
        }
        $event->is_ordering_open = !$event->is_ordering_open;
        $event->save();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $event->is_ordering_open ? 'Ordering opened' : 'Ordering closed',
                'is_ordering_open' => $event->is_ordering_open,
            ]);
        }

        return back()->with('status', $event->is_ordering_open ? 'Advance ordering opened.' : 'Advance ordering closed.');
    }

    /**
     * Upload an event flyer with tenant-scoped isolation.
     */
    public function uploadFlyer(UploadFlyerRequest $request, string $id): mixed
    {
        $event = $this->managedEvent($request, $id);
        $problem = $this->storeFlyerUpload($request, $event);
        $event->refresh();

        if ($request->wantsJson()) {
            return $problem !== null
                ? response()->json(['success' => false, 'error' => $problem], 422)
                : response()->json([
                    'success' => true,
                    'message' => 'Flyer uploaded successfully',
                    'flyer_path' => $event->flyer_path,
                    'flyer_url' => $event->display_image_url,
                    'event' => $event,
                ]);
        }

        return $problem !== null ? back()->with('error', $problem) : back()->with('status', 'Flyer uploaded successfully.');
    }

    /**
     * Stores the request's flyer through the media service: checked, re-encoded (dropping EXIF and anything
     * appended), kept as the untouched original plus responsive variants, on the private disk under the event's
     * organizer. The host's alt text, focal point and optional crop (percent) come from the same form.
     *
     * @return string|null why the flyer could not be used, or null when it was stored (or none was sent)
     */
    private function storeFlyerUpload(Request $request, Event $event): ?string
    {
        if (!$request->hasFile('flyer')) {
            return null;
        }
        $crop = null;
        $cropFields = ['x' => 'flyer_crop_x', 'y' => 'flyer_crop_y', 'width' => 'flyer_crop_width', 'height' => 'flyer_crop_height'];
        if (collect($cropFields)->every(fn ($field) => is_numeric($request->input($field)))) {
            $crop = array_map(fn ($field) => max(0, min(100, (int) $request->input($field))), $cropFields);
            if ($crop['width'] < 10 || $crop['height'] < 10 || ($crop['x'] === 0 && $crop['y'] === 0 && $crop['width'] === 100 && $crop['height'] === 100)) {
                $crop = null;
            }
        }

        try {
            app(\App\Services\EventManagementService::class)->uploadFlyer(
                $request->user(), $event->id, $request->file('flyer'),
                $request->input('flyer_alt_text') ?: $event->flyer_alt_text,
                (int) ($request->input('flyer_position_x', $event->flyer_position_x ?? 50)),
                (int) ($request->input('flyer_position_y', $event->flyer_position_y ?? 50)),
                $crop,
            );
        } catch (\InvalidArgumentException $e) {
            return $e->getMessage();
        }

        return null;
    }

    /**
     * Remove the promotional flyer from the event.
     */
    public function removeFlyer(Request $request, string $id): mixed
    {
        $event = $this->managedEvent($request, $id);
        // The same removal as the API: the original and every variant are deleted, and the change is audited.
        $event = app(\App\Services\EventManagementService::class)->removeFlyer($request->user(), $event->id);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Flyer removed successfully',
                'event' => $event,
            ]);
        }

        return back()->with('status', 'Flyer removed successfully.');
    }

    /**
     * Publish the event, making it publicly discoverable.
     */
    public function publish(Request $request, string $id): mixed
    {
        $event = $this->managedEvent($request, $id);
        try {
            $event = app(\App\Services\EventManagementService::class)->setStatus($request->user(), $event->id, 'published');
        } catch (\InvalidArgumentException $e) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'error' => $e->getMessage(),
                    'issues' => app(\App\Services\EventManagementService::class)->publicationReadiness($event, $request->user())], 422);
            }

            return redirect()->route('events.review', $event->id)->with('error', 'This event is not ready to publish yet. Finish the items below.');
        }
        if (!$event->flyer_path) {
            $event->is_flyer_public = false;
            $event->save();
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Event published successfully. Now visible to the public.',
                'event' => $event,
                'shareable_url' => $event->shareable_url,
                'social_meta' => $event->social_metadata,
            ]);
        }

        return back()->with('status', "Event '{$event->title}' published successfully.");
    }

    /**
     * Unpublish the event, temporarily withdrawing it from public view.
     */
    public function unpublish(Request $request, string $id): mixed
    {
        $event = $this->managedEvent($request, $id);
        $event->status = 'unpublished';
        $event->is_ordering_open = false;
        $event->is_flyer_public = false;
        $event->save();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Event unpublished. Hidden from anonymous visitors.',
                'event' => $event,
            ]);
        }

        return back()->with('status', "Event '{$event->title}' unpublished.");
    }

    /**
     * Cancel the event.
     */
    public function cancel(Request $request, string $id): JsonResponse
    {
        $event = $this->managedEvent($request, $id);
        $event->status = 'cancelled';
        $event->is_ordering_open = false;
        $event->save();

        return response()->json([
            'success' => true,
            'message' => 'Event marked as cancelled.',
            'event' => $event,
        ]);
    }

    /**
     * Public-facing event page view (No authentication required).
     */
    public function publicShow(string $slugOrId): JsonResponse
    {
        $event = Event::withoutGlobalScopes()
            ->where('status', 'published')
            ->where(function ($q) use ($slugOrId) {
                $q->where('slug', $slugOrId)->orWhere('id', $slugOrId);
            })
            ->with(['menuItems' => function ($q) {
                $q->where('is_active', true)->orderBy('sort_order');
            }])
            ->first();

        if (!$event) {
            return response()->json([
                'success' => false,
                'message' => 'Event not found or not currently published.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'event' => $event,
                'is_ordering_allowed' => $event->isOrderingAllowed(),
                'shareable_url' => $event->shareable_url,
                'social_meta' => $event->social_metadata,
            ],
        ]);
    }

    /**
     * Ticket types from the event form, in the stored tier format. A row with the id of an existing tier keeps
     * that tier's other keys (so nothing the form does not show is lost); new rows get a new id. Removed rows
     * are dropped.
     *
     * @param array<int, array<string, mixed>> $rows
     * @param array<int, array<string, mixed>> $existing
     * @return list<array<string, mixed>>
     */
    private static function tiersFromForm(array $rows, array $existing, ?string $timezone): array
    {
        // Sale times are typed in the event's own time zone and stored with their offset.
        $tz = $timezone ?: config('events.market.default_timezone', 'America/New_York');
        $byId = collect($existing)->filter(fn ($tier) => is_array($tier) && isset($tier['id']))->keyBy('id');

        return collect($rows)->values()->map(function (array $row) use ($byId, $tz) {
            $id = (string) ($row['id'] ?? '');
            $tier = $id !== '' && $byId->has($id) ? $byId->get($id) : ['id' => 'tier-' . Str::lower(Str::random(8)), 'priceJMD' => 0];
            $price = round((float) $row['price'], 2);

            $tier['name'] = trim((string) $row['name']);
            $tier['description'] = trim((string) ($row['description'] ?? ''));
            if (array_key_exists('category', $row)) {
                $tier['category'] = trim((string) $row['category']);
            }
            $tier['priceUSD'] = $price;
            if (array_key_exists('price_usd', $tier)) {
                $tier['price_usd'] = $price; // the catalog reads this key first when present
            }
            if (array_key_exists('price_minor', $tier)) {
                $tier['price_minor'] = (int) round($price * 100);
                $tier['currency'] = 'USD';
            }
            if (array_key_exists('price_jmd', $row)) {
                $tier['priceJMD'] = round((float) ($row['price_jmd'] ?: 0), 2);
            } else {
                $tier['priceJMD'] = $tier['priceJMD'] ?? 0;
            }
            // Charged instead of the regular price until the event's early-bird period ends (TicketTierCatalog).
            $tier['earlyBirdPriceUSD'] = ($row['early_bird_price'] ?? '') !== '' && $row['early_bird_price'] !== null
                ? round((float) $row['early_bird_price'], 2) : null;
            $tier['availableStock'] = (int) $row['available_stock'];
            $tier['maxPerCustomer'] = (int) $row['max_per_customer'];
            if (array_key_exists('min_per_order', $row)) {
                $tier['minPerOrder'] = (int) $row['min_per_order'];
            }
            if (array_key_exists('admission_count', $row)) {
                $tier['admissionCount'] = (int) $row['admission_count'];
            }
            foreach ([
                'age_restriction' => 'ageRestriction',
                'admission_privileges' => 'admissionPrivileges',
                'included_food_and_drinks' => 'includedFoodAndDrinks',
            ] as $formKey => $tierKey) {
                if (array_key_exists($formKey, $row)) {
                    $tier[$tierKey] = trim((string) $row[$formKey]);
                }
            }
            if (array_key_exists('benefits', $row)) {
                $tier['benefits'] = collect(preg_split('/\R/', (string) $row['benefits']) ?: [])
                    ->map(fn ($benefit) => trim($benefit))
                    ->filter()
                    ->take(20)
                    ->values()
                    ->all();
            }
            foreach ([
                'refund_eligible' => ['refundEligible', fn ($value) => filter_var($value, FILTER_VALIDATE_BOOLEAN)],
                'visibility' => ['visibility', fn ($value) => (string) $value],
                'design_style' => ['designStyle', fn ($value) => (string) $value],
                'accent_color' => ['accentColor', fn ($value) => (string) $value],
                'background_style' => ['backgroundStyle', fn ($value) => (string) $value],
                'is_active' => ['isActive', fn ($value) => filter_var($value, FILTER_VALIDATE_BOOLEAN)],
            ] as $formKey => [$tierKey, $transform]) {
                if (array_key_exists($formKey, $row)) {
                    $tier[$tierKey] = $transform($row[$formKey]);
                }
            }
            $tier['saleStartIso'] = !empty($row['sale_start']) ? \Illuminate\Support\Carbon::parse($row['sale_start'], $tz)->toIso8601String() : null;
            $tier['saleEndIso'] = !empty($row['sale_end']) ? \Illuminate\Support\Carbon::parse($row['sale_end'], $tz)->toIso8601String() : null;

            return $tier;
        })->all();
    }
}
