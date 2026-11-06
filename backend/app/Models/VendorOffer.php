<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A booth type the host sells to vendors and exhibitors, e.g. "10×10 food booth with power". */
class VendorOffer extends Model
{
    use HasUuids;

    protected $fillable = ['event_id', 'name', 'category', 'description', 'price_minor', 'quantity', 'venue_area_id', 'is_active', 'sort_order'];

    protected $casts = ['price_minor' => 'integer', 'quantity' => 'integer', 'is_active' => 'boolean', 'sort_order' => 'integer'];

    public function applications(): HasMany
    {
        return $this->hasMany(VendorApplication::class, 'offer_id');
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(VenueArea::class, 'venue_area_id');
    }

    /** Booths of this type already given to approved applications. */
    public function taken(): int
    {
        return $this->applications()->where('status', 'approved')->count();
    }

    public function remaining(): ?int
    {
        return $this->quantity === null ? null : max(0, $this->quantity - $this->taken());
    }
}
