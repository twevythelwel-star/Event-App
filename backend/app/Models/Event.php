<?php

namespace App\Models;

use App\Models\Concerns\SyncsMinorUnits;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Event extends Model
{
    use HasFactory, HasUuids, SoftDeletes, BelongsToTenant, SyncsMinorUnits;

    /**
     * New events get UUID keys, but the seeded demo catalogue uses readable keys such as
     * "evt-kingston-harbour-fry". Accept both so route model binding (worker terminal, ordering,
     * host staff pages) can find demo events. The lookup itself is unchanged: exact key match,
     * with the normal scopes applied.
     */
    protected function isValidUniqueId($value): bool
    {
        return Str::isUuid($value)
            || (is_string($value) && preg_match('/^evt-[a-z0-9]+(?:-[a-z0-9]+)*$/', $value) === 1 && strlen($value) <= 64);
    }

    protected $fillable = [
        'organizer_id',
        'slug',
        'title',
        'tagline',
        'category',
        'date_str',
        'event_date_iso',
        'time_str',
        'start_time',
        'end_time',
        'timezone',
        'venue_name',
        'venue_building_name',
        'street_address',
        'city',
        'state_code',
        'target_market_id',
        'zip_code',
        'country_code',
        'latitude',
        'longitude',
        'venue_address',
        'coordinates',
        'description',
        'currency',
        'organizer_name',
        'organizer_phone',
        'organizer_whatsapp',
        'organizer_email',
        'advance_order_deadline',
        'is_ordering_open',
        'status',
        'pickup_instructions',
        'packaging_fee_usd',
        'packaging_fee_jmd',
        'packaging_fee_minor',
        'supports_food_ordering',
        'ticket_tiers',
        'traditions',
        'flyer_path',
        'flyer_thumbnail_path',
        'flyer_mime_type',
        'flyer_size_bytes',
        'flyer_width',
        'flyer_height',
        'is_flyer_public',
    ];

    protected $casts = [
        'advance_order_deadline' => 'datetime',
        'is_ordering_open' => 'boolean',
        'is_flyer_public' => 'boolean',
        'packaging_fee_usd' => 'decimal:2',
        'packaging_fee_jmd' => 'integer',
        'packaging_fee_minor' => 'integer',
        'supports_food_ordering' => 'boolean',
        'ticket_tiers' => 'array',
        'traditions' => 'array',
        'flyer_size_bytes' => 'integer',
        'flyer_width' => 'integer',
        'flyer_height' => 'integer',
    ];

    public function menuItems(): HasMany
    {
        return $this->hasMany(MenuItem::class, 'event_id');
    }

    public function state(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(UsState::class, 'state_code', 'code');
    }

    public function targetMarket(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(TargetMarket::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'event_id');
    }

    /**
     * Determine if advance ordering is currently allowed for this event.
     */
    public function isOrderingAllowed(): bool
    {
        if (!$this->is_ordering_open || $this->status !== 'published') {
            return false;
        }

        if ($this->advance_order_deadline && now()->greaterThan($this->advance_order_deadline)) {
            return false;
        }

        return true;
    }

    /**
     * Check if this event can be publicly viewed by anonymous visitors.
     */
    public function isPubliclyViewable(): bool
    {
        return $this->status === 'published';
    }

    /**
     * Image to show for this event. Uploaded flyers go through the access-checked media route;
     * seeded events point at a public /images path. Without a flyer the event gets a typographic flyer of its
     * own name, date and place (App\Support\GeneratedFlyer), never a photo of one culture standing in for it.
     */
    public function getDisplayImageUrlAttribute(): string
    {
        $path = (string) ($this->flyer_path ?? '');
        if (\App\Services\MediaStorageService::isStoredPath($path)) {
            return route('api.media.flyer', $this->id);
        }
        if (str_starts_with($path, '/images/') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return $this->id ? route('events.generated-flyer', $this->id) : '/images/event-placeholder.svg';
    }

    /** Whether the host uploaded artwork (or the event points at a real image), rather than using the generated flyer. */
    public function hasFlyer(): bool
    {
        $path = (string) ($this->flyer_path ?? '');

        return $path !== '' && (\App\Services\MediaStorageService::isStoredPath($path) || str_starts_with($path, '/images/') || str_starts_with($path, 'https://'));
    }

    /**
     * The traditions and cuisines this event celebrates, as config/traditions.php keys: the host's own choice,
     * or, until the host chooses, a suggestion from the event's title, description and active menu.
     *
     * @return list<string>
     */
    public function traditionKeys(): array
    {
        $catalog = (array) config('traditions', []);
        $chosen = is_array($this->traditions) ? array_values(array_filter($this->traditions, fn ($key) => isset($catalog[$key]))) : [];
        if ($this->traditions !== null) {
            return $chosen;
        }

        $menu = $this->relationLoaded('menuItems')
            ? $this->menuItems->where('is_active', true)->map(fn ($item) => $item->name . ' ' . $item->description)->implode(' ')
            : '';
        $text = mb_strtolower(implode(' ', [$this->title, $this->tagline, $this->description, $this->category, $menu]));
        $found = [];
        foreach ($catalog as $key => $tradition) {
            foreach ($tradition['words'] ?? [] as $word) {
                if (preg_match('/\b' . preg_quote($word, '/') . '/u', $text) === 1) {
                    $found[] = $key;
                    break;
                }
            }
        }

        return $found;
    }

    /** @return list<string> human labels for traditionKeys() */
    public function traditionLabels(): array
    {
        return array_map(fn ($key) => (string) config("traditions.{$key}.label", $key), $this->traditionKeys());
    }

    /**
     * Generate the unique, shareable public URL for this event.
     */
    public function getShareableUrlAttribute(): string
    {
        $identifier = $this->slug ?: $this->id;
        return "https://islandevents.app/e/{$identifier}";
    }

    /**
     * Generate social sharing metadata for OpenGraph and Twitter cards.
     */
    public function getSocialMetadataAttribute(): array
    {
        return [
            'og_title' => "{$this->title} | EventBite",
            'og_description' => $this->tagline ?: ($this->description ? substr($this->description, 0, 150) . '...' : ''),
            'og_image' => $this->flyer_path ? "https://islandevents.app/storage/{$this->flyer_path}" : "https://islandevents.app/images/default_caribbean_feast.jpg",
            'og_url' => $this->shareable_url,
        ];
    }


    /** @return array<string, string> */
    protected function minorUnitPairs(): array
    {
        return [strtoupper($this->minorUnitCurrency()) === 'JMD' ? 'packaging_fee_jmd' : 'packaging_fee_usd' => 'packaging_fee_minor'];
    }

    protected function minorUnitCurrency(): string
    {
        return (string) ($this->currency ?: \App\Support\Currency::defaultCode());
    }
}
