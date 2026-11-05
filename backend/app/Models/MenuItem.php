<?php

namespace App\Models;

use App\Models\Concerns\SyncsMinorUnits;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MenuItem extends Model
{
    use HasFactory, HasUuids, SoftDeletes, BelongsToTenant, SyncsMinorUnits;

    protected $fillable = [
        'organizer_id',
        'event_id',
        'name',
        'category',
        'cuisine',
        'country_of_origin',
        'description',
        'dietary_tags',
        'allergens',
        'spice_level',
        'portion_sizes',
        'pricing_type',
        'price_usd',
        'price_jmd',
        'price_minor',
        'currency',
        'image_url',
        'image_path',
        'custom_option_groups',
        'custom_amount_config',
        'allow_special_instructions',
        'available_stock',
        'sort_order',
        'vendor_assignment',
        'preparation_notes',
        'is_active',
        'item_type',
        'ingredients',
        'guest_instructions',
        'max_per_order',
        'prep_minutes',
        'available_from',
        'available_until',
        'is_featured',
        'image_is_illustrative',
        'image_position',
        'package_config',
    ];

    /** Suggested menu sections. A section is also the food station that hands the item over. */
    public const CATEGORIES = ['Main Dishes', 'Seafood', 'Poultry', 'Meat', 'Vegetarian', 'Vegan', 'Rice and Grains', 'Sides',
        'Desserts', 'Drinks', 'Snacks', 'Sauces and Condiments', 'Extras'];

    public const DIETARY_TAGS = ['vegetarian' => 'Vegetarian', 'vegan' => 'Vegan', 'gluten_free' => 'Gluten-free', 'dairy_free' => 'Dairy-free',
        'halal' => 'Halal', 'kosher' => 'Kosher', 'nut_free' => 'Nut-free', 'pescatarian' => 'Pescatarian'];

    /** The major allergens U.S. labeling law names. */
    public const ALLERGENS = ['milk' => 'Milk', 'eggs' => 'Eggs', 'fish' => 'Fish', 'shellfish' => 'Shellfish', 'tree_nuts' => 'Tree nuts',
        'peanuts' => 'Peanuts', 'wheat' => 'Wheat', 'soy' => 'Soy', 'sesame' => 'Sesame'];

    protected $casts = [
        'price_usd' => 'decimal:2',
        'price_jmd' => 'integer',
        'price_minor' => 'integer',
        'dietary_tags' => 'array',
        'allergens' => 'array',
        'portion_sizes' => 'array',
        'custom_option_groups' => 'array',
        'custom_amount_config' => 'array',
        'allow_special_instructions' => 'boolean',
        'available_stock' => 'integer',
        'is_active' => 'boolean',
        'max_per_order' => 'integer',
        'prep_minutes' => 'integer',
        'available_from' => 'datetime',
        'available_until' => 'datetime',
        'is_featured' => 'boolean',
        'image_is_illustrative' => 'boolean',
        'package_config' => 'array',
    ];

    /**
     * Whether the photo shown is a sample rather than this dish: the host said so, or it is one of the images
     * bundled with the app (public /images), which are never a host's own food.
     */
    public function photoIsIllustrative(): bool
    {
        return (bool) $this->image_is_illustrative
            || (!\App\Services\MediaStorageService::isStoredPath($this->image_path ?? null) && str_starts_with((string) $this->image_url, '/images/'));
    }

    public function isPackage(): bool
    {
        return $this->item_type === 'package';
    }

    /** On the menu and inside its availability window. */
    public function isAvailableAt(?\DateTimeInterface $now = null): bool
    {
        $now ??= now();

        return (bool) $this->is_active
            && ($this->available_from === null || $now >= $this->available_from)
            && ($this->available_until === null || $now < $this->available_until);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    /**
     * Check if a specific portion quantity is available in inventory.
     */
    public function hasAvailableStock(int $requestedQty): bool
    {
        if ($this->available_stock === null) {
            return true; // Uncapped item
        }

        return $this->available_stock >= $requestedQty;
    }

    /**
     * Photo to show for this dish: an uploaded photo via the access-checked media route,
     * otherwise a public image URL, otherwise null (the card shows no photo).
     */
    public function getDisplayImageUrlAttribute(): ?string
    {
        if (\App\Services\MediaStorageService::isStoredPath($this->image_path ?? null)) {
            return route('api.media.menu-photo', $this->id);
        }
        $url = (string) ($this->image_url ?? '');

        return (str_starts_with($url, '/') || str_starts_with($url, 'https://')) ? $url : null;
    }


    protected static function booted(): void
    {
        // A dish is priced in its event's currency.
        static::saving(function (MenuItem $item) {
            if (!$item->currency) {
                $item->currency = $item->event_id
                    ? (Event::withoutGlobalScopes()->whereKey($item->event_id)->value('currency') ?: \App\Support\Currency::defaultCode())
                    : \App\Support\Currency::defaultCode();
            }
        });
    }

    /** @return array<string, string> */
    protected function minorUnitPairs(): array
    {
        // The legacy column for this dish's currency mirrors price_minor.
        return [strtoupper((string) $this->minorUnitCurrency()) === 'JMD' ? 'price_jmd' : 'price_usd' => 'price_minor'];
    }

    protected function minorUnitCurrency(): string
    {
        if (!$this->currency && $this->event_id) {
            $this->currency = Event::withoutGlobalScopes()->whereKey($this->event_id)->value('currency') ?: null;
        }

        return (string) ($this->currency ?: \App\Support\Currency::defaultCode());
    }

    /**
     * The base price in minor units of the given (order) currency, read the way checkout reads it: price_minor when
     * the item is priced in that currency, otherwise the host's price column for that currency.
     */
    public function priceMinorIn(string $currency): int
    {
        $currency = strtoupper($currency);
        if ($this->price_minor !== null && strtoupper((string) ($this->currency ?: $currency)) === $currency) {
            return (int) $this->price_minor;
        }

        return \App\Support\Money::parse((string) (($currency === 'JMD' ? $this->price_jmd : $this->price_usd) ?? 0), $currency)->minor();
    }

    /** The dish's base price as Money (in its event's currency). */
    public function priceMoney(): \App\Support\Money
    {
        return $this->money('price_minor');
    }

    /**
     * Get available portion sizes with their prices.
     * Returns array of portion sizes if configured, otherwise single default portion.
     */
    public function getAvailablePortions(): array
    {
        if ($this->portion_sizes && is_array($this->portion_sizes)) {
            return $this->portion_sizes;
        }

        // Default single portion at base price
        return [
            [
                'name' => 'standard',
                'label' => 'Standard',
                'price_minor' => $this->price_minor,
            ]
        ];
    }

    /**
     * Get price for a specific portion size.
     */
    public function getPriceForPortion(string $portionName): int
    {
        $portions = $this->getAvailablePortions();

        foreach ($portions as $portion) {
            if (($portion['name'] ?? $portion['label'] ?? '') === $portionName) {
                return (int) ($portion['price_minor'] ?? $this->price_minor);
            }
        }

        return (int) $this->price_minor;
    }

    /**
     * Check if item has a specific dietary tag.
     */
    public function hasDietaryTag(string $tag): bool
    {
        if (!$this->dietary_tags || !is_array($this->dietary_tags)) {
            return false;
        }

        return in_array(strtolower($tag), array_map('strtolower', $this->dietary_tags));
    }

    /**
     * Check if item contains a specific allergen.
     */
    public function hasAllergen(string $allergen): bool
    {
        if (!$this->allergens || !is_array($this->allergens)) {
            return false;
        }

        return in_array(strtolower($allergen), array_map('strtolower', $this->allergens));
    }

    /**
     * Validate spice level value.
     */
    public static function isValidSpiceLevel(?string $level): bool
    {
        if ($level === null) {
            return true;
        }

        return in_array($level, ['mild', 'medium', 'hot', 'extra_hot']);
    }
}
