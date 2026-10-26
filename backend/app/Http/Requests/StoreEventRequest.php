<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
            'timezone' => 'nullable|string|max:64',
            'venue_name' => 'required|string|max:255',
            'venue_address' => 'required|string',
            'coordinates' => 'nullable|string|max:64',
            'description' => 'nullable|string',
            'currency' => 'nullable|in:JMD,USD',
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
        ];
    }
}
