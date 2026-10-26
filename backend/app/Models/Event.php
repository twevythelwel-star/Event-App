<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Event extends Model
{
    use HasFactory, HasUuids, SoftDeletes, BelongsToTenant;

    protected $fillable = [
        'organizer_id',
        'slug',
        'title',
        'tagline',
        'date_str',
        'time_str',
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
