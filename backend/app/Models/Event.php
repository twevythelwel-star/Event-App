<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Event extends Model
{
    use HasFactory, HasUuids, SoftDeletes, BelongsToTenant;

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
        'supports_food_ordering',
        'ticket_tiers',
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
        'supports_food_ordering' => 'boolean',
        'ticket_tiers' => 'array',
        'flyer_size_bytes' => 'integer',
        'flyer_width' => 'integer',
        'flyer_height' => 'integer',
    ];

    public function menuItems(): HasMany
    {
        return $this->hasMany(MenuItem::class, 'event_id');
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
     * seeded events point at a public /images path; anything else falls back to a stock photo.
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

        return '/images/island_festival_sunset.jpg';
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
            'og_title' => "{$this->title} | Island Events",
            'og_description' => $this->tagline ?: ($this->description ? substr($this->description, 0, 150) . '...' : ''),
            'og_image' => $this->flyer_path ? "https://islandevents.app/storage/{$this->flyer_path}" : "https://islandevents.app/images/default_caribbean_feast.jpg",
            'og_url' => $this->shareable_url,
        ];
    }
}
