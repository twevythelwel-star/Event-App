<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Services\LocationAddressNormalizer;

class StoreEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Ownership before validation: someone who does not manage the event gets "not found", never a list of
        // the fields it needs.
        if (($id = $this->route('id')) !== null && $this->user()) {
            app(\App\Services\EventManagementService::class)->findEvent($this->user(), (string) $id);
        }

        return true;
    }

    /**
     * A draft may be saved with sections still incomplete; what publishing needs is checked by the readiness check
     * (EventManagementService::publicationReadiness). Publishing, and editing a published event, need it all.
     */
    private function savingDraft(): bool
    {
        $id = $this->route('id');
        if ($id === null) {
            // A new event is always saved; whether it may also be published is the readiness check's decision,
            // so a host who presses Publish too early keeps a draft instead of losing the form.
            return true;
        }
        if ($this->input('status') === 'published') {
            return false;
        }
        $status = \App\Models\Event::withoutGlobalScopes()->whereKey($id)->value('status');

        return $status !== 'published';
    }

    public function rules(): array
    {
        $needed = $this->savingDraft() ? 'nullable' : 'required';

        return [
            'creation_key' => 'nullable|uuid',
            // Only read for system administrators; everyone else's events belong to their own organizer.
            'organizer_id' => ['nullable', 'string', Rule::exists('organizers', 'id')],
            'title' => 'required|string|min:3|max:255',
            'slug' => 'nullable|string|max:128|regex:/^[a-z0-9-]+$/',
            'tagline' => 'nullable|string|max:255',
            // The kind of event (photo library subject), chosen by the host; never inferred from the title.
            'photo_subject' => ['nullable', \Illuminate\Validation\Rule::in(array_keys(\App\Services\PhotoLibrary::subjects()))],
            // Written from the schedule when left empty (EventDetails).
            'date_str' => 'nullable|string|max:128',
            'time_str' => 'nullable|string|max:128',
            'timezone' => ['nullable', Rule::in(LocationAddressNormalizer::supportedTimezones())],
            'venue_name' => "{$needed}|string|max:255",
            'venue_address' => 'nullable|string|max:500',
            'street_address' => 'nullable|string|max:255',
            'venue_building_name' => 'nullable|string|max:180',
            'city' => 'nullable|string|max:120',
            'state_code' => strtoupper((string) $this->input('country_code', 'US')) === 'US'
                ? ['nullable', 'string', 'size:2', Rule::exists('us_states', 'code')->where('is_enabled', true)]
                : ['nullable', 'string', 'max:80'],
            'target_market_id' => ['nullable', 'uuid', Rule::exists('target_markets', 'id')->where('is_enabled', true)],
            // U.S. ZIP format for U.S. events; other enabled countries use their own postal formats.
            'zip_code' => strtoupper((string) $this->input('country_code', 'US')) === 'US' ? ['nullable', 'regex:/^\d{5}(?:-\d{4})?$/'] : ['nullable', 'string', 'max:32'],
            'country_code' => ['nullable', Rule::in((array) config('events.market.allowed_countries', ['US']))],
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
            'coordinates' => 'nullable|string|max:64',
            'description' => 'nullable|string',
            'currency' => ['nullable', \Illuminate\Validation\Rule::in(\App\Support\Currency::supportedCodes())],
            'organizer_name' => "{$needed}|string|max:255",
            'organizer_phone' => "{$needed}|string|min:7|max:32",
            'organizer_whatsapp' => 'nullable|string|max:32',
            'organizer_email' => 'nullable|email|max:255',
            'advance_order_deadline' => "{$needed}|date",
            'refund_policy_deadline_hours' => 'nullable|integer|min:0|max:720',
            'refund_policy_notes' => 'nullable|string|max:2000',
            'refund_policy_mode' => ['nullable', 'in:manual_full_only,manual_full_and_partial,non_refundable'],
            'host_cancellation_policy' => ['nullable', 'in:contact_organizer,manual_refund_if_supported'],
            'postponement_policy' => ['nullable', 'in:tickets_valid_for_new_date,contact_organizer'],
            'weather_cancellation_policy' => ['nullable', 'in:contact_organizer,reschedule_tickets_valid,manual_refund_if_supported'],
            'food_order_cancellation_deadline_hours' => ['nullable', 'integer', 'min:0', 'max:720'],
            'food_order_modification_deadline_hours' => ['nullable', 'integer', 'min:0', 'max:720'],
            'non_refundable_ticket_categories' => ['nullable', 'array', 'max:20'],
            'non_refundable_ticket_categories.*' => ['string', 'max:80', 'distinct'],
            'is_ordering_open' => 'boolean',
            'status' => 'nullable|in:draft,published,unpublished,cancelled',
            'pickup_instructions' => 'nullable|string',
            'packaging_fee_usd' => 'nullable|numeric|min:0',
            'packaging_fee_jmd' => 'nullable|integer|min:0',
            'event_date_iso' => 'nullable|date_format:Y-m-d',
            // The timetable, in the event's time zone. EventSchedule checks the order of everything and works out
            // events that run past midnight or over several days.
            'end_date_iso' => 'nullable|date_format:Y-m-d',
            'start_time' => ['nullable', 'regex:' . \App\Services\Events\EventSchedule::TIME],
            'end_time' => ['nullable', 'regex:' . \App\Services\Events\EventSchedule::TIME],
            'doors_open_time' => ['nullable', 'regex:' . \App\Services\Events\EventSchedule::TIME],
            'setup_time' => ['nullable', 'regex:' . \App\Services\Events\EventSchedule::TIME],
            'food_service_start_time' => ['nullable', 'regex:' . \App\Services\Events\EventSchedule::TIME],
            'food_service_end_time' => ['nullable', 'regex:' . \App\Services\Events\EventSchedule::TIME],
            'ticket_sales_start_at' => 'nullable|date',
            'ticket_sales_end_at' => 'nullable|date',
            'early_bird_starts_at' => 'nullable|date',
            'early_bird_ends_at' => 'nullable|date',
            // Venue details shown to guests.
            'venue_type' => ['nullable', Rule::in(array_keys(\App\Models\Event::VENUE_TYPES))],
            'venue_instructions' => 'nullable|string|max:2000',
            'parking_information' => 'nullable|string|max:2000',
            'accessibility_information' => 'nullable|string|max:2000',
            // A map pin is kept only when it came from the configured mapping service (see MapLocationService).
            'location_token' => 'nullable|string|max:2000',
            'clear_location_pin' => 'nullable|boolean',
            // The food service plan.
            'food_ordering_mode' => ['nullable', Rule::in(array_keys(\App\Models\Event::FOOD_ORDERING_MODES))],
            'food_fulfillment' => ['nullable', Rule::in(array_keys(\App\Models\Event::FOOD_FULFILLMENT))],
            'food_delivery_instructions' => 'nullable|string|max:1000',
            'food_service_fee' => 'nullable|numeric|min:0|max:1000',
            'food_max_orders' => 'nullable|integer|min:1|max:100000',
            'supports_food_ordering' => 'nullable|boolean',
            'food_pickup_windows' => 'nullable|array|max:24',
            'food_pickup_windows_submitted' => 'nullable|boolean',
            'food_pickup_windows.*.start' => 'nullable|string|max:5',
            'food_pickup_windows.*.end' => 'nullable|string|max:5',
            'food_pickup_windows.*.max_orders' => 'nullable|integer|min:1|max:10000',
            // Food safety, allergen, alcohol, and permit compliance disclosures.
            'serves_alcohol' => 'nullable|boolean',
            'alcohol_age_verification_required' => 'nullable|boolean',
            'food_permit_status' => ['nullable', Rule::in(array_keys(\App\Models\Event::FOOD_PERMIT_STATUSES))],
            'food_permit_number' => 'nullable|string|max:120',
            'alcohol_permit_status' => ['nullable', Rule::in(array_keys(\App\Models\Event::ALCOHOL_PERMIT_STATUSES))],
            'alcohol_permit_number' => 'nullable|string|max:120',
            'allergen_disclosure_confirmed' => 'nullable|boolean',
            'temperature_and_sanitation_confirmed' => 'nullable|boolean',
            'compliance_jurisdiction_county' => 'nullable|string|max:120',
            'compliance_jurisdiction_municipality' => 'nullable|string|max:120',
            'food_service_mode' => ['nullable', Rule::in(array_keys(\App\Models\Compliance\ComplianceEventRule::FOOD_MODES))],
            'alcohol_service_mode' => ['nullable', Rule::in(array_keys(\App\Models\Compliance\ComplianceEventRule::ALCOHOL_MODES))],
            'uses_licensed_caterer' => 'nullable|boolean',
            'uses_licensed_alcohol_vendor' => 'nullable|boolean',
            'food_prepared_elsewhere' => 'nullable|boolean',
            'food_prep_state_code' => ['nullable', 'string', 'size:2', Rule::exists('us_states', 'code')],
            'food_prep_county' => 'nullable|string|max:120',
            'food_prep_city' => 'nullable|string|max:120',
            'alcohol_served_elsewhere' => 'nullable|boolean',
            'alcohol_service_state_code' => ['nullable', 'string', 'size:2', Rule::exists('us_states', 'code')],
            'alcohol_service_county' => 'nullable|string|max:120',
            'alcohol_service_city' => 'nullable|string|max:120',
            'enforce_event_compliance_declarations' => 'nullable|boolean',
            'decl_food_safety_laws' => 'nullable|boolean',
            'decl_food_permits_inspections' => 'nullable|boolean',
            'decl_food_handler_certification' => 'nullable|boolean',
            'decl_food_allergen_accuracy' => 'nullable|boolean',
            'decl_food_contamination_prevention' => 'nullable|boolean',
            'decl_alcohol_regulated' => 'nullable|boolean',
            'decl_alcohol_authorized_only' => 'nullable|boolean',
            'decl_alcohol_licences_approvals' => 'nullable|boolean',
            'decl_alcohol_age_verification' => 'nullable|boolean',
            'decl_alcohol_responsible_service' => 'nullable|boolean',
            'decl_alcohol_event_restrictions' => 'nullable|boolean',
            'decl_general_accurate_info' => 'nullable|boolean',
            'decl_general_jurisdiction_compliance' => 'nullable|boolean',
            'decl_general_insurance_permits' => 'nullable|boolean',
            'decl_general_platform_enforcement' => 'nullable|boolean',
            // The traditions and cuisines the host says the event celebrates (config/traditions.php keys).
            // Countries from the cultural catalog that the event features (the country picker).
            'country_ids' => 'nullable|array|max:8',
            'country_ids.*' => ['string', Rule::exists('countries', 'id')->where('is_active', true)],
            'country_ids_submitted' => 'nullable|boolean',
            'traditions' => 'nullable|array|max:4',
            'traditions.*' => ['string', Rule::in(array_keys((array) config('traditions', [])))],
            // Same image rules as the dedicated flyer upload: the create and edit forms may attach one too.
            'flyer' => 'nullable|file|mimes:jpeg,jpg,png,webp|max:5120|dimensions:min_width=300,min_height=200,max_width=4500,max_height=4500',
            // Ticket types from the event form (one row each); converted to the stored tier format by the controller.
            'ticket_tiers' => 'nullable|array|max:20',
            'ticket_tiers.*.id' => 'nullable|string|max:64',
            'ticket_tiers.*.name' => 'required|string|min:2|max:120',
            'ticket_tiers.*.description' => 'nullable|string|max:300',
            'ticket_tiers.*.category' => 'nullable|string|max:40',
            'ticket_tiers.*.price' => 'required|numeric|min:0|max:100000',
            'ticket_tiers.*.price_jmd' => 'nullable|numeric|min:0|max:10000000',
            // Charged instead of the regular price until the event's early-bird period ends.
            'ticket_tiers.*.early_bird_price' => 'nullable|numeric|min:0|lt:ticket_tiers.*.price',
            'ticket_tiers.*.available_stock' => 'required|integer|min:1|max:100000',
            'ticket_tiers.*.max_per_customer' => 'required|integer|min:1|max:100|lte:ticket_tiers.*.available_stock',
            'ticket_tiers.*.min_per_order' => 'nullable|integer|min:1|max:100|lte:ticket_tiers.*.max_per_customer|lte:ticket_tiers.*.available_stock',
            'ticket_tiers.*.admission_count' => 'nullable|integer|min:1|max:100',
            'ticket_tiers.*.age_restriction' => 'nullable|string|max:100',
            'ticket_tiers.*.admission_privileges' => 'nullable|string|max:500',
            'ticket_tiers.*.included_food_and_drinks' => 'nullable|string|max:500',
            'ticket_tiers.*.benefits' => 'nullable|string|max:1000',
            'ticket_tiers.*.refund_eligible' => 'nullable|boolean',
            'ticket_tiers.*.visibility' => 'nullable|in:public,hidden',
            'ticket_tiers.*.design_style' => 'nullable|in:premium_minimal,vip_dark,festival_color,modern_digital,elegant_gold,concert_pass',
            'ticket_tiers.*.accent_color' => 'nullable|in:indigo,emerald,coral,cyan,violet,gold',
            'ticket_tiers.*.background_style' => 'nullable|in:solid,gradient,glow',
            'ticket_tiers.*.is_active' => 'nullable|boolean',
            'ticket_tiers.*.sale_start' => 'nullable|date',
            'ticket_tiers.*.sale_end' => 'nullable|date|after_or_equal:ticket_tiers.*.sale_start',
            // Menu items are created in the same event submission so hosts can publish a complete experience.
            'menu_items' => 'nullable|array|max:50',
            'menu_items.*.name' => 'required|string|min:2|max:150',
            'menu_items.*.category' => 'required|string|max:64',
            'menu_items.*.cuisine' => ['nullable', 'string', Rule::in(array_keys((array) config('traditions', [])))],
            'menu_items.*.description' => 'nullable|string|max:1000',
            'menu_items.*.price_usd' => 'required|numeric|min:0|max:10000',
            'menu_items.*.price_jmd' => 'required|integer|min:0|max:1000000',
            'menu_items.*.pricing_type' => 'required|in:fixed,customizable,custom_amount',
            'menu_items.*.custom_option_groups' => 'nullable|array|max:10',
            'menu_items.*.custom_option_groups.*.id' => 'required_with:menu_items.*.custom_option_groups|string|max:64',
            'menu_items.*.custom_option_groups.*.title' => 'required_with:menu_items.*.custom_option_groups|string|max:100',
            'menu_items.*.custom_option_groups.*.required' => 'required_with:menu_items.*.custom_option_groups|boolean',
            'menu_items.*.custom_option_groups.*.choices' => 'required_with:menu_items.*.custom_option_groups|array|min:1|max:20',
            'menu_items.*.custom_option_groups.*.choices.*.label' => 'required|string|max:150',
            'menu_items.*.custom_option_groups.*.choices.*.extraPriceUSD' => 'required|numeric|min:0',
            'menu_items.*.custom_option_groups.*.choices.*.extraPriceJMD' => 'required|numeric|min:0',
            'menu_items.*.custom_amount_config' => 'nullable|array',
            'menu_items.*.custom_amount_config.minUSD' => 'nullable|numeric|min:0',
            'menu_items.*.custom_amount_config.maxUSD' => 'nullable|numeric|min:0',
            'menu_items.*.custom_amount_config.minJMD' => 'nullable|numeric|min:0',
            'menu_items.*.custom_amount_config.maxJMD' => 'nullable|numeric|min:0',
            'menu_items.*.available_stock' => 'nullable|integer|min:0|max:100000',
            'menu_items.*.allow_special_instructions' => 'nullable|boolean',
            'menu_items.*.sort_order' => 'nullable|integer|min:0|max:1000',
        ];
    }

    public function attributes(): array
    {
        return [
            'event_date_iso' => 'event date',
            'end_date_iso' => 'end date',
            'doors_open_time' => 'doors-open time',
            'food_service_start_time' => 'food service start',
            'food_service_end_time' => 'food service end',
            'ticket_sales_start_at' => 'ticket sales start',
            'ticket_sales_end_at' => 'ticket sales end',
            'early_bird_ends_at' => 'early-bird end',
            'ticket_tiers.*.early_bird_price' => 'early-bird price',
            'traditions' => 'traditions and cuisines',
            'traditions.*' => 'tradition',
            'ticket_tiers.*.name' => 'ticket name',
            'ticket_tiers.*.price' => 'ticket price',
            'ticket_tiers.*.price_jmd' => 'ticket price (JMD)',
            'ticket_tiers.*.available_stock' => 'tickets available',
            'ticket_tiers.*.max_per_customer' => 'limit per customer',
            'ticket_tiers.*.sale_start' => 'sales start',
            'ticket_tiers.*.sale_end' => 'sales end',
        ];
    }
}
