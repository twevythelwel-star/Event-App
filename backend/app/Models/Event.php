<?php

namespace App\Models;

use App\Models\Concerns\SyncsMinorUnits;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Event extends Model
{
    use HasFactory, HasUuids, SoftDeletes, BelongsToTenant, SyncsMinorUnits;

    /**
     * New events get UUID keys, but the seeded demo catalogue uses readable keys such as
     * "evt-kingston-harbour-fry". Accept both so route model binding (worker terminal, ordering,
     * host staff pages) can find demo events. The lookup itself is unchanged: exact key match,
     * with the normal scopes applied.
     */
    protected function isValidUniqueId($value): bool
    {
        return Str::isUuid($value)
            || (is_string($value) && preg_match('/^evt-[a-z0-9]+(?:-[a-z0-9]+)*$/', $value) === 1 && strlen($value) <= 64);
    }

    protected $fillable = [
        'organizer_id',
        'slug',
        'title',
        'tagline',
        'category',
        'summary',
        'event_types',
        'event_format',
        'expected_attendance',
        'age_restriction',
        'dress_code',
        'accessibility_information',
        'languages',
        'visibility',
        'is_featured',
        'virtual_url',
        'virtual_access_instructions',
        'date_str',
        'event_date_iso',
        'time_str',
        'start_time',
        'end_time',
        'timezone',
        'venue_name',
        'venue_building_name',
        'street_address',
        'city',
        'state_code',
        'target_market_id',
        'zip_code',
        'country_code',
        'latitude',
        'longitude',
        'venue_address',
        'coordinates',
        'description',
        'currency',
        'payment_methods',
        'organizer_name',
        'organizer_phone',
        'organizer_whatsapp',
        'organizer_email',
        'advance_order_deadline',
        'is_ordering_open',
        'status',
        'pickup_instructions',
        'packaging_fee_usd',
        'packaging_fee_jmd',
        'packaging_fee_minor',
        'supports_food_ordering',
        'ticket_tiers',
        'traditions',
        'flyer_path',
        'flyer_thumbnail_path',
        'flyer_variants',
        'flyer_moderation_status',
        'flyer_alt_text',
        'flyer_position_x',
        'flyer_position_y',
        'flyer_mime_type',
        'flyer_size_bytes',
        'flyer_width',
        'flyer_height',
        'is_flyer_public',
        'refund_policy_deadline_hours',
        'refund_policy_notes',
        'refund_policy_mode',
        'host_cancellation_policy',
        'postponement_policy',
        'weather_cancellation_policy',
        'food_order_cancellation_deadline_hours',
        'food_order_modification_deadline_hours',
        'non_refundable_ticket_categories',
        'end_date_iso',
        'starts_at',
        'ends_at',
        'doors_open_time',
        'setup_time',
        'food_service_start_time',
        'food_service_end_time',
        'ticket_sales_start_at',
        'ticket_sales_end_at',
        'early_bird_starts_at',
        'early_bird_ends_at',
        'venue_type',
        'venue_instructions',
        'parking_information',
        'location_source',
        'location_place_id',
        'location_verified_at',
        'food_ordering_mode',
        'food_fulfillment',
        'food_delivery_instructions',
        'food_service_fee_minor',
        'food_pickup_windows',
        'food_max_orders',
        'creation_key',
        'serves_alcohol',
        'alcohol_age_verification_required',
        'food_permit_status',
        'food_permit_number',
        'alcohol_permit_status',
        'alcohol_permit_number',
        'allergen_disclosure_confirmed',
        'temperature_and_sanitation_confirmed',
        'compliance_verified_at',
        'compliance_jurisdiction_county',
        'compliance_jurisdiction_municipality',
        'compliance_acknowledgement_id',
        'compliance_reacknowledgement_required',
        'compliance_reacknowledgement_reason',
        'compliance_arrangement_fingerprint',
        // How food and alcohol are provided, and where (EventRequirementChecklist).
        'food_service_mode',
        'alcohol_service_mode',
        'uses_licensed_caterer',
        'uses_licensed_alcohol_vendor',
        'food_prepared_elsewhere',
        'food_prep_state_code',
        'food_prep_county',
        'food_prep_city',
        'alcohol_served_elsewhere',
        'alcohol_service_state_code',
        'alcohol_service_county',
        'alcohol_service_city',
    ];

    /** How guests may order food: before the event, on the day during food service, or both. */
    public const FOOD_ORDERING_MODES = ['preorder' => 'Preorders only', 'event_day' => 'Event-day purchases only', 'both' => 'Preorders and event-day purchases'];

    /** How food reaches guests. Delivery means to a table or a designated area at the venue. */
    public const FOOD_FULFILLMENT = ['pickup' => 'Pickup only', 'pickup_and_delivery' => 'Pickup, or delivery to a table or area'];

    public const FOOD_PERMIT_STATUSES = [
        'not_applicable' => 'No food prepared or served',
        'licensed_caterer_or_venue' => 'Licensed restaurant, caterer, or commercial kitchen',
        'temporary_food_permit_obtained' => 'Temporary / special-event food permit obtained',
        'permit_pending_inspection' => 'Permit application submitted — pending municipal inspection',
        'cottage_or_exempt_disclosed' => 'Exempt community / cottage food with required state disclosure',
    ];

    public const ALCOHOL_PERMIT_STATUSES = [
        'no_alcohol' => 'No alcohol sold, supplied, or served',
        'licensed_venue_or_bar' => 'Served under venue or licensed bar liquor licence',
        'special_event_permit_obtained' => 'Special-event / temporary alcohol permit obtained',
        'licensed_caterer_bartender' => 'Served by licensed alcohol caterer with certified bartenders',
    ];

    public const VENUE_TYPES = ['park' => 'Park or outdoor space', 'hall' => 'Hall or community center', 'restaurant' => 'Restaurant or bar',
        'church' => 'Church or place of worship', 'school' => 'School or campus', 'stadium' => 'Stadium or arena', 'beach' => 'Beach or waterfront',
        'private' => 'Private residence', 'street' => 'Street or block party', 'other' => 'Other'];

    protected $casts = [
        'advance_order_deadline' => 'datetime',
        'is_ordering_open' => 'boolean',
        'is_flyer_public' => 'boolean',
        'packaging_fee_usd' => 'decimal:2',
        'packaging_fee_jmd' => 'integer',
        'packaging_fee_minor' => 'integer',
        'supports_food_ordering' => 'boolean',
        'ticket_tiers' => 'array',
        'traditions' => 'array',
        'event_types' => 'array',
        'languages' => 'array',
        'is_featured' => 'boolean',
        'expected_attendance' => 'integer',
        'payment_methods' => 'array',
        'flyer_size_bytes' => 'integer',
        'flyer_variants' => 'array',
        'vendor_applications_open' => 'boolean',
        'allow_ticket_transfers' => 'boolean',
        'waitlist_enabled' => 'boolean',
        'vendor_applications_close_at' => 'datetime',
        'flyer_width' => 'integer',
        'flyer_height' => 'integer',
        'flyer_position_x' => 'integer',
        'flyer_position_y' => 'integer',
        'refund_policy_deadline_hours' => 'integer',
        'food_order_cancellation_deadline_hours' => 'integer',
        'food_order_modification_deadline_hours' => 'integer',
        'non_refundable_ticket_categories' => 'array',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'ticket_sales_start_at' => 'datetime',
        'ticket_sales_end_at' => 'datetime',
        'early_bird_starts_at' => 'datetime',
        'early_bird_ends_at' => 'datetime',
        'location_verified_at' => 'datetime',
        'food_service_fee_minor' => 'integer',
        'food_pickup_windows' => 'array',
        'food_max_orders' => 'integer',
        'serves_alcohol' => 'boolean',
        'alcohol_age_verification_required' => 'boolean',
        'allergen_disclosure_confirmed' => 'boolean',
        'temperature_and_sanitation_confirmed' => 'boolean',
        'compliance_verified_at' => 'datetime',
        'compliance_reacknowledgement_required' => 'boolean',
        'uses_licensed_caterer' => 'boolean',
        'uses_licensed_alcohol_vendor' => 'boolean',
        'food_prepared_elsewhere' => 'boolean',
        'alcohol_served_elsewhere' => 'boolean',
    ];

    /**
     * Whether a guest may order food at this moment, and if not, why. Preorders close at the ordering deadline;
     * event-day purchases are open during food service hours on the event's days.
     *
     * @return array{open: bool, reason: ?string}
     */
    public function foodOrderingState(?\DateTimeInterface $now = null): array
    {
        $now ??= now();
        $mode = $this->food_ordering_mode ?: 'preorder';
        $deadline = $this->advance_order_deadline;
        $deadlineTs = $deadline instanceof \DateTimeInterface ? $deadline->getTimestamp() : (trim((string) $deadline) !== '' ? strtotime((string) $deadline) : null);
        $preorderOpen = $deadlineTs === null || $deadlineTs === false || $now->getTimestamp() <= $deadlineTs;
        $serviceOpen = $this->isDuringFoodService($now);

        if ($mode === 'preorder') {
            return $preorderOpen ? ['open' => true, 'reason' => null] : ['open' => false, 'reason' => 'Food preorders for this event have closed.'];
        }
        if ($mode === 'event_day') {
            return $serviceOpen ? ['open' => true, 'reason' => null] : ['open' => false, 'reason' => 'Food can be bought only during food service hours at the event.'];
        }

        return $preorderOpen || $serviceOpen ? ['open' => true, 'reason' => null] : ['open' => false, 'reason' => 'Food preorders have closed and food service is not open right now.'];
    }

    /** Whether food service is running now, on any day of the event. */
    public function isDuringFoodService(\DateTimeInterface $now): bool
    {
        if (!$this->food_service_start_time || !$this->food_service_end_time || !$this->event_date_iso) {
            return false;
        }
        // Plain PHP dates, so this also runs where Carbon is not loaded.
        $tz = new \DateTimeZone($this->timezone ?: config('events.market.default_timezone', 'America/New_York'));
        $first = new \DateTimeImmutable(substr((string) $this->event_date_iso, 0, 10), $tz);
        $last = $this->end_date_iso ? new \DateTimeImmutable(substr((string) $this->end_date_iso, 0, 10), $tz) : $first;
        $nowTs = $now->getTimestamp();
        for ($day = $first; $day <= $last; $day = $day->modify('+1 day')) {
            $open = \DateTimeImmutable::createFromFormat('Y-m-d H:i', $day->format('Y-m-d') . ' ' . $this->food_service_start_time, $tz);
            $close = \DateTimeImmutable::createFromFormat('Y-m-d H:i', $day->format('Y-m-d') . ' ' . $this->food_service_end_time, $tz);
            if (!$open || !$close) {
                return false;
            }
            if ($close <= $open) {
                $close = $close->modify('+1 day');
            }
            if ($nowTs >= $open->getTimestamp() && $nowTs < $close->getTimestamp()) {
                return true;
            }
        }

        return false;
    }

    /** @return list<array{start: string, end: string, max_orders: ?int}> */
    public function pickupWindows(): array
    {
        return array_values(array_filter((array) ($this->food_pickup_windows ?? []), fn ($w) => is_array($w) && !empty($w['start']) && !empty($w['end'])));
    }

    public function menuItems(): HasMany
    {
        return $this->hasMany(MenuItem::class, 'event_id');
    }

    public function culturalCategories(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(CulturalCategory::class, 'event_cultural_categories');
    }

    public function countries(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Country::class, 'event_countries');
    }

    public function dishes(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Dish::class, 'event_dishes')->withPivot([
            'id', 'alternative_spellings', 'is_fusion', 'dietary_type',
        ])->withTimestamps();
    }

    public function customDishes(): HasMany
    {
        return $this->hasMany(EventDish::class)->whereNull('dish_id');
    }

    public function state(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(UsState::class, 'state_code', 'code');
    }

    public function targetMarket(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(TargetMarket::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'event_id');
    }

    /**
     * Determine if advance ordering is currently allowed for this event.
     */
    public function isOrderingAllowed(): bool
    {
        if (!$this->is_ordering_open || $this->status !== 'published') {
            return false;
        }

        if ($this->advance_order_deadline && now()->greaterThan($this->advance_order_deadline)) {
            return false;
        }

        return true;
    }

    /**
     * Check if this event can be publicly viewed by anonymous visitors.
     */
    public function isPubliclyViewable(): bool
    {
        return $this->status === 'published' && $this->visibility !== 'private';
    }

    /**
     * Image to show for this event. Uploaded flyers go through the access-checked media route;
     * seeded events point at a public /images path. Without a flyer the event gets a typographic flyer of its
     * own name, date and place (App\Support\GeneratedFlyer), never a photo of one culture standing in for it.
     */
    public function getDisplayImageUrlAttribute(): string
    {
        $path = (string) ($this->flyer_path ?? '');
        if (\App\Services\MediaStorageService::isStoredPath($path) && !$this->flyerIsHidden()) {
            // Versioned by the stored file, so a replaced flyer is fetched afresh.
            return route('api.media.flyer', ['eventId' => $this->id, 'v' => substr(sha1($path), 0, 10)]);
        }
        // A host's linked artwork is theirs; a bundled stock photo is never an event's cover (ImageSubjects), so an
        // event without its own flyer is shown by artwork made from its own details instead.
        if (str_starts_with($path, 'https://')) {
            return $path;
        }

        return $this->id ? route('events.generated-flyer', $this->id) : '/images/event-placeholder.svg';
    }

    public function getDisplayThumbnailUrlAttribute(): string
    {
        if (\App\Services\MediaStorageService::isStoredPath($this->flyer_thumbnail_path)) {
            return "/api/v1/media/events/{$this->id}/flyer?variant=thumbnail";
        }

        return $this->display_image_url;
    }

    /** Whether the host uploaded artwork (or the event points at a real image), rather than using the generated flyer. */
    public function hasFlyer(): bool
    {
        $path = (string) ($this->flyer_path ?? '');
        if (\App\Services\MediaStorageService::isStoredPath($path)) {
            return !$this->flyerIsHidden();
        }

        // Only the host's own artwork counts as a flyer; a bundled photo of some other event never does.
        return str_starts_with($path, 'https://');
    }

    /** Whether an administrator hid the uploaded flyer (moderation); the generated flyer shows in its place. */
    public function flyerIsHidden(): bool
    {
        return ($this->flyer_moderation_status ?? 'approved') === 'hidden';
    }

    /** The event's cover (its flyer, or the generated artwork) as a responsive image. */
    public function coverImage(): \App\Support\ResponsiveImage
    {
        return \App\Support\ResponsiveImage::forEventCover($this);
    }

    /**
     * The event's gallery photos people may see: approved ones, in the host's order.
     *
     * @return \Illuminate\Support\Collection<int, EventMedia>
     */
    public function visibleGallery(): \Illuminate\Support\Collection
    {
        $media = $this->relationLoaded('mediaItems') ? $this->mediaItems : $this->mediaItems()->get();

        return $media->where('media_type', 'gallery')->where('moderation_status', '!=', 'hidden')->values();
    }

    /** The schedule: activities in time order. */
    public function activities(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(EventActivity::class)->orderBy('starts_at');
    }

    /** The stages and places activities happen at. */
    public function stages(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(EventStage::class)->orderBy('sort_order');
    }

    /** The DJs, performers, bands, speakers and hosts on the bill. */
    public function performers(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(EventPerformer::class)->orderBy('sort_order');
    }

    public function mediaItems(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(EventMedia::class)->orderBy('sort_order')->orderBy('created_at');
    }

    /**
     * The traditions and cuisines this event celebrates, as config/traditions.php keys: the host's own choice,
     * or, until the host chooses, a suggestion from the event's title, description and active menu.
     *
     * @return list<string>
     */
    public function traditionKeys(): array
    {
        $catalog = (array) config('traditions', []);
        $chosen = is_array($this->traditions) ? array_values(array_filter($this->traditions, fn ($key) => isset($catalog[$key]))) : [];
        if ($this->traditions !== null) {
            return $chosen;
        }

        $menu = $this->relationLoaded('menuItems')
            ? $this->menuItems->where('is_active', true)->map(fn ($item) => $item->name . ' ' . $item->description)->implode(' ')
            : '';
        $text = mb_strtolower(implode(' ', [$this->title, $this->tagline, $this->description, $this->category, $menu]));
        $found = [];
        foreach ($catalog as $key => $tradition) {
            foreach ($tradition['words'] ?? [] as $word) {
                if (preg_match('/\b' . preg_quote($word, '/') . '/u', $text) === 1) {
                    $found[] = $key;
                    break;
                }
            }
        }

        return $found;
    }

    /** @return list<string> human labels for traditionKeys() */
    public function traditionLabels(): array
    {
        return array_map(fn ($key) => (string) config("traditions.{$key}.label", $key), $this->traditionKeys());
    }

    /**
     * Generate the unique, shareable public URL for this event.
     */
    public function getShareableUrlAttribute(): string
    {
        $identifier = $this->slug ?: $this->id;
        return "https://islandevents.app/e/{$identifier}";
    }

    /**
     * Generate social sharing metadata for OpenGraph and Twitter cards.
     */
    public function getSocialMetadataAttribute(): array
    {
        return [
            'og_title' => "{$this->title} | EventBite",
            'og_description' => $this->tagline ?: ($this->description ? substr($this->description, 0, 150) . '...' : ''),
            'og_image' => $this->flyer_path ? "https://islandevents.app/storage/{$this->flyer_path}" : "https://islandevents.app/images/default_caribbean_feast.jpg",
            'og_url' => $this->shareable_url,
        ];
    }


    /** @return array<string, string> */
    protected function minorUnitPairs(): array
    {
        return [strtoupper($this->minorUnitCurrency()) === 'JMD' ? 'packaging_fee_jmd' : 'packaging_fee_usd' => 'packaging_fee_minor'];
    }

    protected function minorUnitCurrency(): string
    {
        return (string) ($this->currency ?: \App\Support\Currency::defaultCode());
    }

    /**
     * Check if a specific payment method is enabled for this event.
     */
    public function hasPaymentMethod(string $method): bool
    {
        if (empty($this->payment_methods) || !is_array($this->payment_methods)) {
            // Default: all methods enabled if not configured
            return true;
        }

        return in_array($method, $this->payment_methods, true);
    }

    /**
     * Get enabled payment methods for this event.
     */
    public function getEnabledPaymentMethods(): array
    {
        if (empty($this->payment_methods) || !is_array($this->payment_methods)) {
            // Default payment methods
            return ['bank', 'zelle', 'cashapp', 'cash', 'card'];
        }

        return $this->payment_methods;
    }

    /**
     * Set enabled payment methods for this event.
     */
    public function setPaymentMethods(array $methods): void
    {
        $validMethods = ['bank', 'zelle', 'cashapp', 'cash', 'card'];
        $this->payment_methods = array_intersect($methods, $validMethods);
    }

    public function complianceAcknowledgements(): HasMany
    {
        return $this->hasMany(EventComplianceAcknowledgement::class, 'event_id', 'id')
            ->orderByDesc('accepted_at_utc');
    }

    public function activeComplianceAcknowledgement(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(EventComplianceAcknowledgement::class, 'compliance_acknowledgement_id', 'id');
    }
}
