<?php

namespace App\Http\Resources;

use App\Services\MediaStorageService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serializes a public event (with its active menu) in the shape of the SPA's EventDetails type.
 */
class EventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $timezone = $this->timezone ?: config('events.market.default_timezone', 'America/New_York');

        return [
            'id' => $this->id,
            'organizerId' => $this->organizer_id,
            'slug' => $this->slug,
            'title' => $this->title,
            'tagline' => $this->tagline ?? '',
            'category' => $this->category ?? 'Community',
            'dateStr' => $this->date_str,
            'eventDateIso' => $this->event_date_iso,
            'timeStr' => $this->time_str,
            'startTime' => $this->start_time,
            'endTime' => $this->end_time,
            'timezone' => $timezone,
            'venueName' => $this->venue_name,
            'venueBuildingName' => $this->venue_building_name ?? '',
            'streetAddress' => $this->street_address ?? '',
            'city' => $this->city ?? '',
            'stateCode' => $this->state_code ?? '',
            'stateName' => $this->state?->name ?? '',
            'zipCode' => $this->zip_code ?? '',
            'countryCode' => $this->country_code ?? '',
            'latitude' => $this->latitude === null ? null : (float) $this->latitude,
            'longitude' => $this->longitude === null ? null : (float) $this->longitude,
            'targetMarketId' => $this->target_market_id,
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
            'packagingFeeMinor' => (int) $this->packaging_fee_minor,
            'supportsFoodOrdering' => (bool) ($this->supports_food_ordering ?? true),
            'ticketTiers' => $this->ticket_tiers ?? [],
            // Uploaded flyers go through the media route, which enforces who may see unpublished events
            'flyerUrl' => MediaStorageService::isStoredPath($this->flyer_path)
                ? "/api/v1/media/events/{$this->id}/flyer?v=" . substr(sha1($this->flyer_path), 0, 10)
                : $this->flyer_path,
            'flyerMimeType' => $this->flyer_mime_type,
            'flyerSizeBytes' => $this->flyer_size_bytes,
            'flyerDimensions' => $this->flyer_width && $this->flyer_height
                ? ['width' => $this->flyer_width, 'height' => $this->flyer_height]
                : null,
            'isFlyerPublic' => $this->is_flyer_public,
            'menu' => MenuItemResource::collection($this->whenLoaded('menuItems')),
        ];
    }
}
