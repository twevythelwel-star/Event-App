<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serializes a public event (with its active menu) in the shape of the SPA's EventDetails type.
 */
class EventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $timezone = $this->timezone ?: 'America/Jamaica';

        return [
            'id' => $this->id,
            'organizerId' => $this->organizer_id,
            'slug' => $this->slug,
            'title' => $this->title,
            'tagline' => $this->tagline ?? '',
            'dateStr' => $this->date_str,
            'timeStr' => $this->time_str,
            'timezone' => $timezone,
            'venueName' => $this->venue_name,
            'venueAddress' => $this->venue_address,
            'coordinates' => $this->coordinates ?? '',
            'description' => $this->description ?? '',
            'currency' => $this->currency,
            'organizerName' => $this->organizer_name,
            'organizerPhone' => $this->organizer_phone,
            'organizerWhatsApp' => $this->organizer_whatsapp ?? '',
            'organizerEmail' => $this->organizer_email ?? '',
            'advanceOrderDeadline' => $this->advance_order_deadline?->setTimezone($timezone)->format('l, M j, Y \a\t g:i A') ?? '',
            'advanceOrderDeadlineIso' => $this->advance_order_deadline?->toIso8601String(),
            'isOrderingOpen' => $this->is_ordering_open,
            'status' => $this->status,
            'pickupInstructions' => $this->pickup_instructions ?? '',
            'packagingFeeUSD' => (float) $this->packaging_fee_usd,
            'packagingFeeJMD' => (int) $this->packaging_fee_jmd,
            'flyerUrl' => $this->is_flyer_public ? $this->flyer_path : null,
            'isFlyerPublic' => $this->is_flyer_public,
            'menu' => MenuItemResource::collection($this->whenLoaded('menuItems')),
        ];
    }
}
