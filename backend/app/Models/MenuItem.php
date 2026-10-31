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
        'description',
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
        'is_active',
    ];

    protected $casts = [
        'price_usd' => 'decimal:2',
        'price_jmd' => 'integer',
        'price_minor' => 'integer',
        'custom_option_groups' => 'array',
        'custom_amount_config' => 'array',
        'allow_special_instructions' => 'boolean',
        'available_stock' => 'integer',
        'is_active' => 'boolean',
    ];

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

    /** The dish's base price as Money (in its event's currency). */
    public function priceMoney(): \App\Support\Money
    {
        return $this->money('price_minor');
    }
}
