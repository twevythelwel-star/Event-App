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
        $menuItems = $this->resource->relationLoaded('menuItems')
            ? collect($this->resource->menuItems)
            : collect();
        $eventMedia = $this->resource->relationLoaded('mediaItems')
            ? collect($this->resource->mediaItems)
            : $this->resource->mediaItems()->get();
        // Images an administrator hid are listed only to the host's own event management, which can see them.
        if (!$request->routeIs('api.host.*')) {
            $eventMedia = $eventMedia->reject(fn ($media) => $media->isHidden())->values();
        }
        $currency = strtoupper((string) ($this->currency ?: 'USD'));
        $foodServiceFee = \App\Support\Money::ofMinor((int) $this->food_service_fee_minor, $currency)->toMajorNumber();
        $packagePrice = fn (int $minor) => \App\Support\Money::ofMinor($minor, $currency)->toMajorNumber();
        $packages = $menuItems->filter(fn ($item) => $item->isPackage())->map(function ($package) use ($menuItems, $currency, $packagePrice) {
            $config = (array) ($package->package_config ?? []);
            $itemName = fn ($id) => $menuItems->firstWhere('id', $id)?->name ?? '';
            $pricePair = fn (int $minor) => $currency === 'JMD'
                ? ['upgradePriceUSD' => 0, 'upgradePriceJMD' => $packagePrice($minor)]
                : ['upgradePriceUSD' => $packagePrice($minor), 'upgradePriceJMD' => 0];

            return [
                'id' => $package->id,
                'name' => $package->name,
                'description' => $package->description ?? '',
                'imageUrl' => $package->display_image_url ?? '',
                'imageIsUploaded' => \App\Services\MediaStorageService::isStoredPath($package->image_path),
                'basePriceUSD' => (float) $package->price_usd,
                'basePriceJMD' => (int) $package->price_jmd,
                'components' => collect($config['components'] ?? [])->map(function ($component) use ($itemName, $pricePair) {
                    $substitutes = collect($component['substitutes'] ?? []);
                    $upgrade = $pricePair((int) ($substitutes->first()['extra_minor'] ?? 0));

                    return [
                        'menuItemId' => (string) ($component['menu_item_id'] ?? ''),
                        'menuItemName' => $itemName($component['menu_item_id'] ?? ''),
                        'quantity' => (int) ($component['quantity'] ?? 1),
                        'isRequired' => true,
                        'isSubstitutable' => $substitutes->isNotEmpty(),
                        'substituteMenuItemIds' => $substitutes->pluck('menu_item_id')->all(),
                        ...$upgrade,
                    ];
                })->values()->all(),
                'choiceGroups' => collect($config['choice_groups'] ?? [])->map(function ($group) use ($itemName, $pricePair) {
                    return [
                        'id' => (string) ($group['id'] ?? ''),
                        'title' => (string) ($group['title'] ?? ''),
                        'min' => (int) ($group['min'] ?? 0),
                        'max' => (int) ($group['max'] ?? 1),
                        'options' => collect($group['options'] ?? [])->map(fn ($option) => [
                            'menuItemId' => (string) ($option['menu_item_id'] ?? ''),
                            'menuItemName' => $itemName($option['menu_item_id'] ?? ''),
                            ...$pricePair((int) ($option['extra_minor'] ?? 0)),
                        ])->values()->all(),
                    ];
                })->values()->all(),
                'optionalAdditions' => collect($config['add_ons'] ?? [])->map(function ($addOn) use ($itemName, $packagePrice, $currency) {
                    $minor = (int) ($addOn['price_minor'] ?? 0);

                    return [
                        'menuItemId' => (string) ($addOn['menu_item_id'] ?? ''),
                        'menuItemName' => $itemName($addOn['menu_item_id'] ?? ''),
                        'priceUSD' => $currency === 'USD' ? $packagePrice($minor) : 0,
                        'priceJMD' => $currency === 'JMD' ? $packagePrice($minor) : 0,
                    ];
                })->values()->all(),
                'maxQty' => $package->available_stock,
                'maxQtyPerOrder' => $package->max_per_order,
                'isAvailable' => $package->is_active,
                'isFeatured' => $package->is_featured,
            ];
        })->values()->all();

        return [
            'id' => $this->id,
            'organizerId' => $this->organizer_id,
            'slug' => $this->slug,
            'title' => $this->title,
            'tagline' => $this->tagline ?? '',
            'category' => $this->category ?? 'Community',
            'summary' => $this->summary ?? '',
            'eventTypes' => $this->event_types ?? [],
            'eventFormat' => $this->event_format ?? 'in_person',
            'expectedAttendance' => $this->expected_attendance,
            'ageRestriction' => $this->age_restriction ?? '',
            'dressCode' => $this->dress_code ?? '',
            'accessibilityInformation' => $this->accessibility_information ?? '',
            'languages' => $this->languages ?? [],
            'visibility' => $this->visibility ?? 'public',
            'isFeatured' => (bool) $this->is_featured,
            'virtualUrl' => $this->virtual_url ?? '',
            'virtualAccessInstructions' => $this->virtual_access_instructions ?? '',
            'culturalCategoryIds' => $this->culturalCategories()->pluck('cultural_categories.id')->all(),
            'countryIds' => $this->countries()->pluck('countries.id')->all(),
            'selectedDishes' => $this->dishes->map(fn ($dish) => [
                'id' => $dish->id,
                'name' => $dish->name,
                'alternativeSpellings' => $dish->pivot->alternative_spellings
                    ? json_decode($dish->pivot->alternative_spellings, true)
                    : [],
                'isFusion' => (bool) $dish->pivot->is_fusion,
                'dietaryType' => $dish->pivot->dietary_type,
            ])->values(),
            'customDishes' => $this->customDishes->map(fn ($dish) => [
                'name' => $dish->custom_name,
                'countryId' => $dish->country_id,
                'alternativeSpellings' => $dish->alternative_spellings ?? [],
                'isFusion' => (bool) $dish->is_fusion,
                'dietaryType' => $dish->dietary_type,
            ])->values(),
            'dateStr' => $this->date_str,
            'eventDateIso' => $this->event_date_iso,
            'endDateIso' => $this->end_date_iso,
            'timeStr' => $this->time_str,
            'startTime' => $this->start_time,
            'endTime' => $this->end_time,
            'doorsOpenTime' => $this->doors_open_time,
            'setupTime' => $this->setup_time,
            'foodServiceStartTime' => $this->food_service_start_time,
            'foodServiceEndTime' => $this->food_service_end_time,
            'ticketSalesStartIso' => $this->ticket_sales_start_at?->setTimezone($timezone)->format('Y-m-d\TH:i'),
            'ticketSalesEndIso' => $this->ticket_sales_end_at?->setTimezone($timezone)->format('Y-m-d\TH:i'),
            'earlyBirdSaleStartIso' => $this->early_bird_starts_at?->setTimezone($timezone)->format('Y-m-d\TH:i'),
            'earlyBirdSaleEndIso' => $this->early_bird_ends_at?->setTimezone($timezone)->format('Y-m-d\TH:i'),
            'timezone' => $timezone,
            'venueName' => $this->venue_name,
            'venueType' => $this->venue_type,
            'venueInstructions' => $this->venue_instructions ?? '',
            'parkingInformation' => $this->parking_information ?? '',
            'venueBuildingName' => $this->venue_building_name ?? '',
            'streetAddress' => $this->street_address ?? '',
            'city' => $this->city ?? '',
            'stateCode' => $this->state_code ?? '',
            'stateName' => $this->state?->name ?? '',
            'zipCode' => $this->zip_code ?? '',
            'countryCode' => $this->country_code ?? '',
            'latitude' => $this->location_verified_at && $this->latitude !== null ? (float) $this->latitude : null,
            'longitude' => $this->location_verified_at && $this->longitude !== null ? (float) $this->longitude : null,
            'locationVerified' => (bool) $this->location_verified_at,
            'locationSource' => $this->location_verified_at ? $this->location_source : null,
            'locationPreviewUrl' => $this->location_verified_at && $this->latitude !== null && $this->longitude !== null
                ? app(\App\Services\Events\MapLocationService::class)->previewUrl((float) $this->latitude, (float) $this->longitude)
                : null,
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
            'refundPolicyDeadlineHours' => $this->refund_policy_deadline_hours,
            'refundPolicyNotes' => $this->refund_policy_notes,
            'refundPolicyMode' => $this->refund_policy_mode ?? 'manual_full_and_partial',
            'hostCancellationPolicy' => $this->host_cancellation_policy ?? 'contact_organizer',
            'postponementPolicy' => $this->postponement_policy ?? 'tickets_valid_for_new_date',
            'weatherCancellationPolicy' => $this->weather_cancellation_policy ?? 'contact_organizer',
            'foodOrderCancellationDeadlineHours' => $this->food_order_cancellation_deadline_hours ?? 24,
            'foodOrderModificationDeadlineHours' => $this->food_order_modification_deadline_hours ?? 24,
            'nonRefundableTicketCategories' => $this->non_refundable_ticket_categories ?? [],
            'isOrderingOpen' => $this->is_ordering_open,
            'status' => $this->status,
            'pickupInstructions' => $this->pickup_instructions ?? '',
            'packagingFeeUSD' => (float) $this->packaging_fee_usd,
            'packagingFeeJMD' => (int) $this->packaging_fee_jmd,
            'packagingFeeMinor' => (int) $this->packaging_fee_minor,
            'supportsFoodOrdering' => (bool) ($this->supports_food_ordering ?? true),
            'foodServiceConfig' => [
                'enabled' => (bool) ($this->supports_food_ordering ?? true),
                'serviceMode' => !$this->supports_food_ordering
                    ? 'disabled'
                    : ['event_day' => 'eventDay', 'both' => 'both'][$this->food_ordering_mode] ?? 'preorder',
                'fulfillmentMode' => $this->food_fulfillment ?: 'pickup',
                'deliveryInstructions' => $this->food_delivery_instructions,
                'foodServiceStartTime' => $this->food_service_start_time,
                'foodServiceEndTime' => $this->food_service_end_time,
                'orderingDeadlineIso' => $this->advance_order_deadline?->setTimezone($timezone)->format('Y-m-d\TH:i'),
                'packagingFeeUSD' => (float) $this->packaging_fee_usd,
                'packagingFeeJMD' => (int) $this->packaging_fee_jmd,
                'serviceFeeUSD' => $currency === 'USD' ? $foodServiceFee : 0,
                'serviceFeeJMD' => $currency === 'JMD' ? $foodServiceFee : 0,
                'maxOrdersPerEvent' => $this->food_max_orders,
                'pickupWindows' => array_map(fn ($window, $index) => [
                    'id' => \App\Services\Events\EventSchedule::windowKey($window),
                    'label' => $window['start'] . '–' . $window['end'],
                    'startTime' => $window['start'],
                    'endTime' => $window['end'],
                    'maxOrders' => $window['max_orders'] ?? null,
                ], $this->pickupWindows(), array_keys($this->pickupWindows())),
            ],
            'packages' => $packages,
            'ticketTiers' => $request->routeIs('api.host.events*')
                ? ($this->ticket_tiers ?? [])
                : \App\Services\Checkout\TicketTierCatalog::sellable($this->resource),
            // Uploaded flyers go through the media route, which enforces who may see unpublished events
            'flyerUrl' => MediaStorageService::isStoredPath($this->flyer_path)
                ? "/api/v1/media/events/{$this->id}/flyer?v=" . substr(sha1($this->flyer_path), 0, 10)
                : $this->flyer_path,
            'flyerThumbnailUrl' => MediaStorageService::isStoredPath($this->flyer_thumbnail_path)
                ? "/api/v1/media/events/{$this->id}/flyer?variant=thumbnail&v=" . substr(sha1($this->flyer_thumbnail_path), 0, 10)
                : ($this->flyer_path ?? null),
            'flyerSmallUrl' => $this->variantUrl("/api/v1/media/events/{$this->id}/flyer", $this->flyer_variants, 'small'),
            'flyerMediumUrl' => $this->variantUrl("/api/v1/media/events/{$this->id}/flyer", $this->flyer_variants, 'medium'),
            'flyerLargeUrl' => $this->variantUrl("/api/v1/media/events/{$this->id}/flyer", $this->flyer_variants, 'large'),
            'flyerAvifUrl' => $this->variantUrl("/api/v1/media/events/{$this->id}/flyer", $this->flyer_variants, 'large', 'avif'),
            'flyerAltText' => $this->flyer_alt_text ?? ($this->title ? $this->title . ' event flyer' : 'Event flyer'),
            'flyerPositionX' => (int) ($this->flyer_position_x ?? 50),
            'flyerPositionY' => (int) ($this->flyer_position_y ?? 50),
            'eventMedia' => $eventMedia->map(fn ($media) => [
                'id' => $media->id,
                'mediaType' => $media->media_type,
                'imageUrl' => "/api/v1/media/event-media/{$media->id}?v=" . substr(sha1($media->original_path), 0, 10),
                'thumbnailUrl' => $media->thumbnail_path ? "/api/v1/media/event-media/{$media->id}?variant=thumbnail&v=" . substr(sha1($media->thumbnail_path), 0, 10) : null,
                'smallThumbnailUrl' => $media->small_thumbnail_path ? "/api/v1/media/event-media/{$media->id}?variant=small&v=" . substr(sha1($media->small_thumbnail_path), 0, 10) : null,
                'mediumThumbnailUrl' => $media->medium_thumbnail_path ? "/api/v1/media/event-media/{$media->id}?variant=medium&v=" . substr(sha1($media->medium_thumbnail_path), 0, 10) : null,
                'largeThumbnailUrl' => $media->large_thumbnail_path ? "/api/v1/media/event-media/{$media->id}?variant=large&v=" . substr(sha1($media->large_thumbnail_path), 0, 10) : null,
                'avifUrl' => $this->variantUrl("/api/v1/media/event-media/{$media->id}", $media->variants, 'large', 'avif'),
                'moderationStatus' => $media->moderation_status ?? 'approved',
                'altText' => $media->alt_text,
                'width' => (int) $media->width,
                'height' => (int) $media->height,
                'positionX' => (int) $media->position_x,
                'positionY' => (int) $media->position_y,
                'cropX' => $media->crop_x,
                'cropY' => $media->crop_y,
                'cropWidth' => $media->crop_width,
                'cropHeight' => $media->crop_height,
            ])->values(),
            'flyerMimeType' => $this->flyer_mime_type,
            'flyerSizeBytes' => $this->flyer_size_bytes,
            'flyerDimensions' => $this->flyer_width && $this->flyer_height
                ? ['width' => $this->flyer_width, 'height' => $this->flyer_height]
                : null,
            'isFlyerPublic' => $this->is_flyer_public,
            'menu' => MenuItemResource::collection($menuItems->reject(fn ($item) => $item->isPackage())->values()),
        ];
    }

    /** A versioned link to one stored variant (size and format) of an image, or null when it was not made. */
    private function variantUrl(string $base, ?array $variants, string $size, string $format = 'webp'): ?string
    {
        $path = $variants[$size][$format] ?? null;

        return $path ? "{$base}?variant={$size}&format={$format}&v=" . substr(sha1($path), 0, 10) : null;
    }
}
