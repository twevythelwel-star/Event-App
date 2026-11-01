<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Services\LocationAddressNormalizer;

class StoreEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|min:3|max:255',
            'slug' => 'nullable|string|max:128|regex:/^[a-z0-9-]+$/',
            'tagline' => 'nullable|string|max:255',
            'date_str' => 'required|string|max:128',
            'time_str' => 'required|string|max:128',
            'timezone' => ['nullable', Rule::in(LocationAddressNormalizer::supportedTimezones())],
            'venue_name' => 'required|string|max:255',
            'venue_address' => 'nullable|string|max:500',
            'street_address' => 'nullable|string|max:255',
            'venue_building_name' => 'nullable|string|max:180',
            'city' => 'nullable|string|max:120',
            'state_code' => ['nullable', 'string', 'size:2', Rule::exists('us_states', 'code')->where('is_enabled', true)],
            'target_market_id' => ['nullable', 'uuid', Rule::exists('target_markets', 'id')->where('is_enabled', true)],
            'zip_code' => ['nullable', 'regex:/^\d{5}(?:-\d{4})?$/'],
            'country_code' => ['nullable', Rule::in(['US'])],
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
            'coordinates' => 'nullable|string|max:64',
            'description' => 'nullable|string',
            'currency' => ['nullable', \Illuminate\Validation\Rule::in(\App\Support\Currency::supportedCodes())],
            'organizer_name' => 'required|string|max:255',
            'organizer_phone' => 'required|string|min:7|max:32',
            'organizer_whatsapp' => 'nullable|string|max:32',
            'organizer_email' => 'nullable|email|max:255',
            'advance_order_deadline' => 'required|date',
            'is_ordering_open' => 'boolean',
            'status' => 'nullable|in:draft,published,unpublished,cancelled',
            'pickup_instructions' => 'nullable|string',
            'packaging_fee_usd' => 'nullable|numeric|min:0',
            'packaging_fee_jmd' => 'nullable|integer|min:0',
            'event_date_iso' => 'nullable|date_format:Y-m-d',
            // The traditions and cuisines the host says the event celebrates (config/traditions.php keys).
            'traditions' => 'nullable|array|max:4',
            'traditions.*' => ['string', Rule::in(array_keys((array) config('traditions', [])))],
            // Same image rules as the dedicated flyer upload: the create and edit forms may attach one too.
            'flyer' => 'nullable|file|mimes:jpeg,jpg,png,webp|max:5120|dimensions:min_width=300,min_height=200,max_width=4500,max_height=4500',
            // Ticket types from the event form (one row each); converted to the stored tier format by the controller.
            'ticket_tiers' => 'nullable|array|max:20',
            'ticket_tiers.*.id' => 'nullable|string|max:64',
            'ticket_tiers.*.name' => 'required|string|min:2|max:120',
            'ticket_tiers.*.description' => 'nullable|string|max:300',
            'ticket_tiers.*.price' => 'required|numeric|min:0|max:100000',
            'ticket_tiers.*.available_stock' => 'required|integer|min:1|max:100000',
            'ticket_tiers.*.max_per_customer' => 'required|integer|min:1|max:100|lte:ticket_tiers.*.available_stock',
            'ticket_tiers.*.sale_start' => 'nullable|date',
            'ticket_tiers.*.sale_end' => 'nullable|date|after_or_equal:ticket_tiers.*.sale_start',
        ];
    }

    public function attributes(): array
    {
        return [
            'event_date_iso' => 'event date',
            'traditions' => 'traditions and cuisines',
            'traditions.*' => 'tradition',
            'ticket_tiers.*.name' => 'ticket name',
            'ticket_tiers.*.price' => 'ticket price',
            'ticket_tiers.*.available_stock' => 'tickets available',
            'ticket_tiers.*.max_per_customer' => 'limit per customer',
            'ticket_tiers.*.sale_start' => 'sales start',
            'ticket_tiers.*.sale_end' => 'sales end',
        ];
    }
}
