<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\SecurityException;
use App\Http\Controllers\Controller;
use App\Http\Resources\EventResource;
use App\Models\Event;
use App\Services\EventManagementService;
use App\Services\LocationAddressNormalizer;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Event Host editing and creation of their own events and menus for the single-page app.
 */
class HostEventsController extends Controller
{
    public function __construct(
        protected EventManagementService $events
    ) {}

    public function index(Request $request): JsonResponse
    {
        $rows = $this->events->eventsQuery($request->user())->get();

        return response()->json(['data' => EventResource::collection($rows)->resolve($request)]);
    }

    public function store(Request $request): JsonResponse
    {
        if ($request->exists('is_featured') && !$request->user()->hasRole(\App\Enums\AccountType::SYSTEM_ADMIN)) {
            return response()->json(['error' => 'Only System Administrators may feature events.'], 403);
        }
        $physical = !in_array($request->input('event_format', 'in_person'), ['virtual'], true);
        $data = $request->validate([
            'organizer_id' => ['sometimes', 'nullable', 'string', 'max:128'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:128', 'regex:/^[a-z0-9-]+$/'],
            'title' => ['required', 'string', 'min:3', 'max:200'],
            ...$this->eventIdentityRules($request),
            'tagline' => ['sometimes', 'nullable', 'string', 'max:255'],
            'category' => ['sometimes', 'nullable', 'string', 'max:128'],
            'date_str' => ['required', 'string', 'min:3', 'max:100'],
            'event_date_iso' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'end_date_iso' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'time_str' => ['required', 'string', 'max:100'],
            'start_time' => ['sometimes', 'nullable', 'regex:' . \App\Services\Events\EventSchedule::TIME],
            'end_time' => ['sometimes', 'nullable', 'regex:' . \App\Services\Events\EventSchedule::TIME],
            'doors_open_time' => ['sometimes', 'nullable', 'regex:' . \App\Services\Events\EventSchedule::TIME],
            'setup_time' => ['sometimes', 'nullable', 'regex:' . \App\Services\Events\EventSchedule::TIME],
            'ticket_sales_start_at' => ['sometimes', 'nullable', 'date'],
            'ticket_sales_end_at' => ['sometimes', 'nullable', 'date'],
            'early_bird_starts_at' => ['sometimes', 'nullable', 'date'],
            'early_bird_ends_at' => ['sometimes', 'nullable', 'date'],
            'timezone' => ['sometimes', 'nullable', Rule::in(LocationAddressNormalizer::supportedTimezones())],
            'venue_name' => [Rule::requiredIf($physical), 'nullable', 'string', 'min:2', 'max:200'],
            'venue_address' => ['sometimes', 'nullable', 'string', 'max:500'],
            'street_address' => [$physical ? 'required' : 'nullable', 'string', 'max:255'],
            'venue_building_name' => ['sometimes', 'nullable', 'string', 'max:180'],
            'city' => [$physical ? 'required' : 'nullable', 'string', 'max:120'],
            'state_code' => $this->stateCodeRules($request, $physical, true),
            'target_market_id' => ['sometimes', 'nullable', 'uuid', Rule::exists('target_markets', 'id')->where('is_enabled', true)],
            'zip_code' => $this->postalCodeRules($request, $physical, true),
            'country_code' => ['sometimes', Rule::in((array) config('events.market.allowed_countries', ['US']))],
            'venue_type' => ['sometimes', 'nullable', Rule::in(array_keys(Event::VENUE_TYPES))],
            'venue_instructions' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'parking_information' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'location_token' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'clear_location_pin' => ['sometimes', 'boolean'],
            'latitude' => ['sometimes', 'nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
            'coordinates' => ['sometimes', 'nullable', 'string', 'max:100'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'currency' => ['sometimes', 'nullable', Rule::in(\App\Support\Currency::supportedCodes())],
            'pickup_instructions' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'organizer_name' => ['sometimes', 'nullable', 'string', 'max:200'],
            'organizer_phone' => ['required', 'string', 'min:7', 'max:40'],
            'organizer_whatsapp' => ['sometimes', 'nullable', 'string', 'max:40'],
            'organizer_email' => ['sometimes', 'nullable', 'email', 'max:150'],
            'packaging_fee_usd' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'packaging_fee_jmd' => ['sometimes', 'integer', 'min:0', 'max:20000'],
            'advance_order_deadline' => ['required', 'date'],
            'supports_food_ordering' => ['sometimes', 'boolean'],
            'food_ordering_mode' => ['sometimes', Rule::in(array_keys(Event::FOOD_ORDERING_MODES))],
            'food_fulfillment' => ['sometimes', Rule::in(array_keys(Event::FOOD_FULFILLMENT))],
            'food_delivery_instructions' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'food_service_start_time' => ['sometimes', 'nullable', 'regex:' . \App\Services\Events\EventSchedule::TIME],
            'food_service_end_time' => ['sometimes', 'nullable', 'regex:' . \App\Services\Events\EventSchedule::TIME],
            'food_service_fee' => ['sometimes', 'numeric', 'min:0', 'max:1000'],
            'food_max_orders' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100000'],
            'food_pickup_windows' => ['sometimes', 'nullable', 'array', 'max:24'],
            'food_pickup_windows.*.start' => ['required_with:food_pickup_windows', 'string', 'max:5'],
            'food_pickup_windows.*.end' => ['required_with:food_pickup_windows', 'string', 'max:5'],
            'food_pickup_windows.*.max_orders' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'payment_methods' => ['sometimes', 'nullable', 'array'],
            'payment_methods.*' => ['string', 'in:bank,zelle,cashapp,cash,card'],
            'ticket_tiers' => ['sometimes', 'nullable', 'array', 'max:20'],
            'refund_policy_deadline_hours' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:720'],
            'refund_policy_notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        return $this->run(fn () => $this->present($request, $this->events->createEvent($request->user(), $data)), 201);
    }

    public function update(Request $request, string $eventId): JsonResponse
    {
        if ($request->exists('is_featured') && !$request->user()->hasRole(\App\Enums\AccountType::SYSTEM_ADMIN)) {
            return response()->json(['error' => 'Only System Administrators may feature events.'], 403);
        }
        $data = $request->validate([
            ...$this->eventIdentityRules($request, $eventId),
            'title' => ['sometimes', 'string', 'min:3', 'max:200'],
            'tagline' => ['sometimes', 'nullable', 'string', 'max:255'],
            'category' => ['sometimes', 'nullable', 'string', 'max:128'],
            'date_str' => ['sometimes', 'string', 'min:3', 'max:100'],
            'event_date_iso' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'end_date_iso' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'time_str' => ['sometimes', 'string', 'max:100'],
            'start_time' => ['sometimes', 'nullable', 'regex:' . \App\Services\Events\EventSchedule::TIME],
            'end_time' => ['sometimes', 'nullable', 'regex:' . \App\Services\Events\EventSchedule::TIME],
            'doors_open_time' => ['sometimes', 'nullable', 'regex:' . \App\Services\Events\EventSchedule::TIME],
            'setup_time' => ['sometimes', 'nullable', 'regex:' . \App\Services\Events\EventSchedule::TIME],
            'ticket_sales_start_at' => ['sometimes', 'nullable', 'date'],
            'ticket_sales_end_at' => ['sometimes', 'nullable', 'date'],
            'early_bird_starts_at' => ['sometimes', 'nullable', 'date'],
            'early_bird_ends_at' => ['sometimes', 'nullable', 'date'],
            'timezone' => ['sometimes', 'nullable', Rule::in(LocationAddressNormalizer::supportedTimezones())],
            'venue_name' => ['sometimes', 'nullable', 'string', 'min:2', 'max:200'],
            'venue_address' => ['sometimes', 'string', 'max:500'],
            'street_address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'venue_building_name' => ['sometimes', 'nullable', 'string', 'max:180'],
            'city' => ['sometimes', 'nullable', 'string', 'max:120'],
            'state_code' => $this->stateCodeRules($request, false, false),
            'target_market_id' => ['sometimes', 'nullable', 'uuid', Rule::exists('target_markets', 'id')->where('is_enabled', true)],
            'zip_code' => $this->postalCodeRules($request, false, false),
            'country_code' => ['sometimes', Rule::in((array) config('events.market.allowed_countries', ['US']))],
            'venue_type' => ['sometimes', 'nullable', Rule::in(array_keys(Event::VENUE_TYPES))],
            'venue_instructions' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'parking_information' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'location_token' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'clear_location_pin' => ['sometimes', 'boolean'],
            'latitude' => ['sometimes', 'nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
            'coordinates' => ['sometimes', 'nullable', 'string', 'max:100'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'currency' => ['sometimes', 'nullable', Rule::in(\App\Support\Currency::supportedCodes())],
            'pickup_instructions' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'organizer_name' => ['sometimes', 'nullable', 'string', 'max:200'],
            'organizer_phone' => ['sometimes', 'string', 'max:40'],
            'organizer_whatsapp' => ['sometimes', 'nullable', 'string', 'max:40'],
            'organizer_email' => ['sometimes', 'nullable', 'email', 'max:150'],
            'packaging_fee_usd' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'packaging_fee_jmd' => ['sometimes', 'integer', 'min:0', 'max:20000'],
            'advance_order_deadline' => ['sometimes', 'date'],
            'is_ordering_open' => ['sometimes', 'boolean'],
            'supports_food_ordering' => ['sometimes', 'boolean'],
            'food_ordering_mode' => ['sometimes', Rule::in(array_keys(Event::FOOD_ORDERING_MODES))],
            'food_fulfillment' => ['sometimes', Rule::in(array_keys(Event::FOOD_FULFILLMENT))],
            'food_delivery_instructions' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'food_service_start_time' => ['sometimes', 'nullable', 'regex:' . \App\Services\Events\EventSchedule::TIME],
            'food_service_end_time' => ['sometimes', 'nullable', 'regex:' . \App\Services\Events\EventSchedule::TIME],
            'food_service_fee' => ['sometimes', 'numeric', 'min:0', 'max:1000'],
            'food_max_orders' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100000'],
            'food_pickup_windows' => ['sometimes', 'nullable', 'array', 'max:24'],
            'food_pickup_windows.*.start' => ['required_with:food_pickup_windows', 'string', 'max:5'],
            'food_pickup_windows.*.end' => ['required_with:food_pickup_windows', 'string', 'max:5'],
            'food_pickup_windows.*.max_orders' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'payment_methods' => ['sometimes', 'nullable', 'array'],
            'payment_methods.*' => ['string', 'in:bank,zelle,cashapp,cash,card'],
            'ticket_tiers' => ['sometimes', 'nullable', 'array', 'max:20'],
            'refund_policy_deadline_hours' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:720'],
            'refund_policy_notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        if ($data === []) {
            return response()->json(['error' => 'Nothing to update.'], 422);
        }

        return $this->run(fn () => $this->present($request, $this->events->updateEvent($request->user(), $eventId, $data)));
    }

    public function setStatus(Request $request, string $eventId): JsonResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(EventManagementService::STATUSES)]]);

        return $this->run(fn () => $this->present($request, $this->events->setStatus($request->user(), $eventId, $data['status'])));
    }

    public function storeMenuItem(Request $request, string $eventId): JsonResponse
    {
        $data = $request->validate($this->menuRules(creating: true));

        return $this->run(fn () => $this->present($request, $this->events->createMenuItem($request->user(), $eventId, $data)), 201);
    }

    public function updateMenuItem(Request $request, string $eventId, string $itemId): JsonResponse
    {
        $data = $request->validate($this->menuRules(creating: false));

        return $this->run(fn () => $this->present($request, $this->events->updateMenuItem($request->user(), $eventId, $itemId, $data)));
    }

    public function destroyMenuItem(Request $request, string $eventId, string $itemId): JsonResponse
    {
        return $this->run(fn () => $this->present($request, $this->events->deleteMenuItem($request->user(), $eventId, $itemId)));
    }

    public function uploadFlyer(Request $request, string $eventId): JsonResponse
    {
        $data = $request->validate([
            'flyer' => ['required', 'file'],
            'alt_text' => ['sometimes', 'nullable', 'string', 'max:250'],
            'position_x' => ['sometimes', 'integer', 'between:0,100'],
            'position_y' => ['sometimes', 'integer', 'between:0,100'],
            'crop_x' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100'],
            'crop_y' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100'],
            'crop_width' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
            'crop_height' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $crop = null;
        if (isset($data['crop_x'], $data['crop_y'], $data['crop_width'], $data['crop_height'])) {
            $crop = [
                'x' => (int) $data['crop_x'],
                'y' => (int) $data['crop_y'],
                'width' => (int) $data['crop_width'],
                'height' => (int) $data['crop_height'],
            ];
        }

        return $this->run(fn () => $this->present($request, $this->events->uploadFlyer(
            $request->user(), $eventId, $request->file('flyer'), $data['alt_text'] ?? null,
            (int) ($data['position_x'] ?? 50), (int) ($data['position_y'] ?? 50),
            $crop,
        )));
    }

    public function removeFlyer(Request $request, string $eventId): JsonResponse
    {
        return $this->run(fn () => $this->present($request, $this->events->removeFlyer($request->user(), $eventId)));
    }

    public function uploadEventMedia(Request $request, string $eventId): JsonResponse
    {
        $data = $request->validate([
            'photo' => ['required', 'file'],
            'media_type' => ['required', Rule::in(\App\Models\EventMedia::TYPES)],
            'alt_text' => ['required', 'string', 'min:2', 'max:250'],
            'position_x' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'position_y' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'crop_x' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100'],
            'crop_y' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100'],
            'crop_width' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
            'crop_height' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $crop = null;
        if (isset($data['crop_x'], $data['crop_y'], $data['crop_width'], $data['crop_height'])) {
            $crop = [
                'x' => (int) $data['crop_x'],
                'y' => (int) $data['crop_y'],
                'width' => (int) $data['crop_width'],
                'height' => (int) $data['crop_height'],
            ];
        }

        return $this->run(fn () => $this->present($request, $this->events->uploadEventMedia(
            $request->user(), $eventId, $request->file('photo'), $data['media_type'], $data['alt_text'],
            (int) ($data['position_x'] ?? 50), (int) ($data['position_y'] ?? 50),
            $crop,
        )), 201);
    }

    public function removeEventMedia(Request $request, string $eventId, string $mediaId): JsonResponse
    {
        return $this->run(fn () => $this->present($request, $this->events->removeEventMedia($request->user(), $eventId, $mediaId)));
    }

    public function uploadMenuPhoto(Request $request, string $eventId, string $itemId): JsonResponse
    {
        $request->validate(['photo' => ['required', 'file']]);

        return $this->run(fn () => $this->present($request, $this->events->uploadMenuPhoto($request->user(), $eventId, $itemId, $request->file('photo'))));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function menuRules(bool $creating): array
    {
        $required = $creating ? 'required' : 'sometimes';

        return [
            'name' => [$required, 'string', 'min:2', 'max:150'],
            'category' => [$required, 'string', 'max:64'],
            'cuisine' => ['sometimes', 'nullable', 'string', \Illuminate\Validation\Rule::in(array_keys((array) config('traditions', [])))],
            'country_of_origin' => ['sometimes', 'nullable', 'string', 'max:100'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'ingredients' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'dietary_tags' => ['sometimes', 'nullable', 'array'],
            'dietary_tags.*' => ['string', Rule::in(array_keys(\App\Models\MenuItem::DIETARY_TAGS))],
            'allergens' => ['sometimes', 'nullable', 'array'],
            'allergens.*' => ['string', Rule::in(array_keys(\App\Models\MenuItem::ALLERGENS))],
            'spice_level' => ['sometimes', 'nullable', 'in:mild,medium,hot,extra_hot'],
            'portion_sizes' => ['sometimes', 'nullable', 'array'],
            'portion_sizes.*.name' => ['required_with:portion_sizes', 'string', 'max:50'],
            'portion_sizes.*.label' => ['required_with:portion_sizes', 'string', 'max:50'],
            'portion_sizes.*.price_minor' => ['required_with:portion_sizes', 'integer', 'min:0'],
            'pricing_type' => [$required, Rule::in(['fixed', 'customizable', 'custom_amount'])],
            'price_usd' => [$required, 'numeric', 'min:0', 'max:10000'],
            'price_jmd' => [$required, 'integer', 'min:0', 'max:1000000'],
            // Links only: absolute http(s) URLs or site paths; uploads go through the photo endpoint
            'image_url' => ['sometimes', 'nullable', 'string', 'max:500', 'regex:/^(https?:\/\/|\/)[^\s"\'<>]*$/i'],
            'custom_option_groups' => ['sometimes', 'nullable', 'array', 'max:10'],
            'custom_option_groups.*.id' => ['required', 'string', 'max:64'],
            'custom_option_groups.*.title' => ['required', 'string', 'max:100'],
            'custom_option_groups.*.required' => ['required', 'boolean'],
            'custom_option_groups.*.choices' => ['required', 'array', 'min:1', 'max:20'],
            'custom_option_groups.*.choices.*.label' => ['required', 'string', 'max:150'],
            'custom_option_groups.*.choices.*.extraPriceUSD' => ['required', 'numeric', 'min:0'],
            'custom_option_groups.*.choices.*.extraPriceJMD' => ['required', 'numeric', 'min:0'],
            'custom_amount_config' => ['sometimes', 'nullable', 'array'],
            'allow_special_instructions' => ['sometimes', 'boolean'],
            'guest_instructions' => ['sometimes', 'nullable', 'string', 'max:500'],
            'available_stock' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100000'],
            'max_per_order' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
            'prep_minutes' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:600'],
            'available_from' => ['sometimes', 'nullable', 'date'],
            'available_until' => ['sometimes', 'nullable', 'date', 'after:available_from'],
            'is_featured' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'item_type' => ['sometimes', Rule::in(['item', 'package'])],
            'package_config' => ['sometimes', 'nullable', 'array'],
            'package_config.components' => ['sometimes', 'array', 'max:30'],
            'package_config.choice_groups' => ['sometimes', 'array', 'max:20'],
            'package_config.add_ons' => ['sometimes', 'array', 'max:30'],
            'image_is_illustrative' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'vendor_assignment' => ['sometimes', 'nullable', 'string', 'max:100'],
            'preparation_notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }

    private function present(Request $request, Event $event): array
    {
        return ['data' => (new EventResource($event))->resolve($request)];
    }

    private function eventIdentityRules(Request $request, ?string $eventId = null): array
    {
        $types = [
            'Cultural Festival', 'Food Festival', 'Concert', 'Live Music', 'Community Gathering',
            'Networking', 'Fundraiser', 'Religious or Spiritual Gathering', 'Private Celebration',
            'Conference', 'Food Pop-Up', 'Market or Vendor Event', 'Educational Event', 'Family Event', 'Other',
        ];

        return [
            'slug' => ['sometimes', 'nullable', 'string', 'max:128', 'regex:/^[a-z0-9-]+$/', Rule::unique('events', 'slug')->ignore($eventId)],
            'summary' => ['sometimes', 'nullable', 'string', 'max:500'],
            'event_types' => ['sometimes', 'array', 'max:15'],
            'event_types.*' => ['string', 'distinct', Rule::in($types)],
            'event_format' => ['sometimes', Rule::in(['in_person', 'virtual', 'hybrid'])],
            'expected_attendance' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:10000000'],
            'age_restriction' => ['sometimes', 'nullable', 'string', 'max:120'],
            'dress_code' => ['sometimes', 'nullable', 'string', 'max:160'],
            'accessibility_information' => ['sometimes', 'nullable', 'string', 'max:3000'],
            'languages' => ['sometimes', 'array', 'max:30'],
            'languages.*' => ['string', 'max:64', 'distinct'],
            'visibility' => ['sometimes', Rule::in(['public', 'unlisted', 'private'])],
            'virtual_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'virtual_access_instructions' => ['sometimes', 'nullable', 'string', 'max:3000'],
            'flyer_alt_text' => ['sometimes', 'nullable', 'string', 'max:250'],
            'flyer_position_x' => ['sometimes', 'integer', 'between:0,100'],
            'flyer_position_y' => ['sometimes', 'integer', 'between:0,100'],
            'refund_policy_deadline_hours' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:720'],
            'refund_policy_notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'refund_policy_mode' => ['sometimes', Rule::in(['manual_full_only', 'manual_full_and_partial', 'non_refundable'])],
            'host_cancellation_policy' => ['sometimes', Rule::in(['contact_organizer', 'manual_refund_if_supported'])],
            'postponement_policy' => ['sometimes', Rule::in(['tickets_valid_for_new_date', 'contact_organizer'])],
            'weather_cancellation_policy' => ['sometimes', Rule::in(['contact_organizer', 'reschedule_tickets_valid', 'manual_refund_if_supported'])],
            'food_order_cancellation_deadline_hours' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:720'],
            'food_order_modification_deadline_hours' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:720'],
            'non_refundable_ticket_categories' => ['sometimes', 'array', 'max:20'],
            'non_refundable_ticket_categories.*' => ['string', 'max:80', 'distinct'],
            'is_featured' => ['sometimes', 'boolean'],
            'cultural_category_ids' => ['sometimes', 'array', 'max:20'],
            'cultural_category_ids.*' => ['string', 'distinct', 'exists:cultural_categories,id'],
            'country_ids' => ['sometimes', 'array', 'max:50'],
            'country_ids.*' => ['string', 'size:2', 'distinct', 'exists:countries,id'],
            'dish_ids' => ['sometimes', 'array', 'max:150'],
            'dish_ids.*' => ['uuid', 'distinct', 'exists:dishes,id'],
            'custom_dishes' => ['sometimes', 'array', 'max:150'],
            'custom_dishes.*.name' => ['required', 'string', 'min:2', 'max:160'],
            'custom_dishes.*.country_id' => ['required', 'string', 'size:2', 'exists:countries,id'],
            'custom_dishes.*.alternative_spellings' => ['sometimes', 'array', 'max:10'],
            'custom_dishes.*.alternative_spellings.*' => ['string', 'max:160'],
            'custom_dishes.*.is_fusion' => ['sometimes', 'boolean'],
            'custom_dishes.*.dietary_type' => ['sometimes', Rule::in(['any', 'vegetarian', 'vegan'])],
            'serves_alcohol' => ['sometimes', 'boolean'],
            'alcohol_age_verification_required' => ['sometimes', 'boolean'],
            'food_permit_status' => ['sometimes', 'nullable', Rule::in(array_keys(Event::FOOD_PERMIT_STATUSES))],
            'food_permit_number' => ['sometimes', 'nullable', 'string', 'max:120'],
            'alcohol_permit_status' => ['sometimes', 'nullable', Rule::in(array_keys(Event::ALCOHOL_PERMIT_STATUSES))],
            'alcohol_permit_number' => ['sometimes', 'nullable', 'string', 'max:120'],
            'allergen_disclosure_confirmed' => ['sometimes', 'boolean'],
            'temperature_and_sanitation_confirmed' => ['sometimes', 'boolean'],
            'compliance_jurisdiction_county' => ['sometimes', 'nullable', 'string', 'max:120'],
            'compliance_jurisdiction_municipality' => ['sometimes', 'nullable', 'string', 'max:120'],
            'food_service_mode' => ['sometimes', 'nullable', Rule::in(array_keys(\App\Models\Compliance\ComplianceEventRule::FOOD_MODES))],
            'alcohol_service_mode' => ['sometimes', 'nullable', Rule::in(array_keys(\App\Models\Compliance\ComplianceEventRule::ALCOHOL_MODES))],
            'uses_licensed_caterer' => ['sometimes', 'nullable', 'boolean'],
            'uses_licensed_alcohol_vendor' => ['sometimes', 'nullable', 'boolean'],
            'food_prepared_elsewhere' => ['sometimes', 'nullable', 'boolean'],
            'food_prep_state_code' => ['sometimes', 'nullable', 'string', 'size:2', Rule::exists('us_states', 'code')],
            'food_prep_county' => ['sometimes', 'nullable', 'string', 'max:120'],
            'food_prep_city' => ['sometimes', 'nullable', 'string', 'max:120'],
            'alcohol_served_elsewhere' => ['sometimes', 'nullable', 'boolean'],
            'alcohol_service_state_code' => ['sometimes', 'nullable', 'string', 'size:2', Rule::exists('us_states', 'code')],
            'alcohol_service_county' => ['sometimes', 'nullable', 'string', 'max:120'],
            'alcohol_service_city' => ['sometimes', 'nullable', 'string', 'max:120'],
            'enforce_event_compliance_declarations' => ['sometimes', 'boolean'],
            'decl_food_safety_laws' => ['sometimes', 'boolean'],
            'decl_food_permits_inspections' => ['sometimes', 'boolean'],
            'decl_food_handler_certification' => ['sometimes', 'boolean'],
            'decl_food_allergen_accuracy' => ['sometimes', 'boolean'],
            'decl_food_contamination_prevention' => ['sometimes', 'boolean'],
            'decl_alcohol_regulated' => ['sometimes', 'boolean'],
            'decl_alcohol_authorized_only' => ['sometimes', 'boolean'],
            'decl_alcohol_licences_approvals' => ['sometimes', 'boolean'],
            'decl_alcohol_age_verification' => ['sometimes', 'boolean'],
            'decl_alcohol_responsible_service' => ['sometimes', 'boolean'],
            'decl_alcohol_event_restrictions' => ['sometimes', 'boolean'],
            'decl_general_accurate_info' => ['sometimes', 'boolean'],
            'decl_general_jurisdiction_compliance' => ['sometimes', 'boolean'],
            'decl_general_insurance_permits' => ['sometimes', 'boolean'],
            'decl_general_platform_enforcement' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * The US launch requires its state/territory catalog; other enabled countries keep their own region format.
     *
     * @return array<int, mixed>
     */
    private function stateCodeRules(Request $request, bool $physical, bool $creating): array
    {
        if (!$creating && !$request->exists('country_code')) {
            return ['sometimes', 'nullable', 'string', 'max:80'];
        }
        $country = strtoupper((string) $request->input('country_code', 'US'));
        if ($country !== 'US') {
            return [$creating && $physical ? 'nullable' : 'sometimes', 'nullable', 'string', 'max:80'];
        }

        return [
            $creating && $physical ? 'required' : 'sometimes',
            'nullable',
            'string',
            'size:2',
            Rule::exists('us_states', 'code')->where('is_enabled', true),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    private function postalCodeRules(Request $request, bool $physical, bool $creating): array
    {
        if (!$creating && !$request->exists('country_code')) {
            return ['sometimes', 'nullable', 'string', 'max:32'];
        }
        $country = strtoupper((string) $request->input('country_code', 'US'));
        if ($country !== 'US') {
            return [$creating && $physical ? 'nullable' : 'sometimes', 'nullable', 'string', 'max:32'];
        }

        return [
            $creating && $physical ? 'required' : 'sometimes',
            'nullable',
            'regex:/^\d{5}(?:-\d{4})?$/',
        ];
    }

    private function run(callable $action, int $status = 200): JsonResponse
    {
        try {
            return response()->json($action(), $status);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => $e->getMessage() ?: 'Not found.'], 404);
        } catch (SecurityException $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }
}
