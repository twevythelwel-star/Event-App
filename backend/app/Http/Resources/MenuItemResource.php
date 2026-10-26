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
            'description' => $this->description ?? '',
            'pricingType' => $this->pricing_type,
            'priceUSD' => (float) $this->price_usd,
            'priceJMD' => (int) $this->price_jmd,
            'imageUrl' => $this->image_url ?? '',
            'customOptionGroups' => $this->custom_option_groups,
            'customAmountConfig' => $this->custom_amount_config,
            'allowSpecialInstructions' => $this->allow_special_instructions,
            'availableStock' => $this->available_stock,
        ], fn ($value) => $value !== null);
    }
}
