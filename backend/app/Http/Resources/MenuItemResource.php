<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serializes a menu item in the camelCase shape of the SPA's MenuItem type.
 */
class MenuItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return array_filter([
            'id' => $this->id,
            'organizerId' => $this->organizer_id,
            'name' => $this->name,
            'category' => $this->category,
            'cuisine' => $this->cuisine,
            'cuisineLabel' => $this->cuisine ? config("traditions.{$this->cuisine}.label") : null,
            'countryOfOrigin' => $this->country_of_origin,
            'description' => $this->description ?? '',
            'dietaryTags' => $this->dietary_tags,
            'dietaryClassification' => $this->dietary_tags
                ? \Illuminate\Support\Str::camel((string) $this->dietary_tags[0])
                : 'none',
            'allergens' => $this->allergens,
            'spiceLevel' => $this->spice_level,
            'portionSizes' => $this->portion_sizes,
            'pricingType' => $this->pricing_type,
            'priceUSD' => (float) $this->price_usd,
            'priceJMD' => (int) $this->price_jmd,
            'priceMinor' => $this->price_minor !== null ? (int) $this->price_minor : null,
            'currency' => $this->currency,
            'imageUrl' => $this->display_image_url ?? '',
            'imageIsUploaded' => \App\Services\MediaStorageService::isStoredPath($this->image_path),
            'customOptionGroups' => $this->custom_option_groups,
            'customAmountConfig' => $this->custom_amount_config,
            'allowSpecialInstructions' => $this->allow_special_instructions,
            'ingredients' => $this->ingredients,
            'guestInstructions' => $this->guest_instructions,
            'prepTimeMinutes' => $this->prep_minutes,
            'availabilityStartIso' => $this->available_from?->toIso8601String(),
            'availabilityEndIso' => $this->available_until?->toIso8601String(),
            'maxQtyPerOrder' => $this->max_per_order,
            'isFeatured' => $this->is_featured,
            'isAvailable' => $this->is_active,
            'imageIsIllustrative' => $this->image_is_illustrative,
            'imagePosition' => $this->image_position,
            'itemType' => $this->item_type,
            'availableStock' => $this->available_stock,
            'vendorAssignment' => $this->vendor_assignment,
            'preparationNotes' => $this->preparation_notes,
        ], fn ($value) => $value !== null);
    }
}
