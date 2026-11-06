<?php

namespace App\Models;

use App\Services\MediaStorageService;
use App\Support\ResponsiveImage;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A sponsor of an event: who they are, their level, logo, and where they are credited. */
class EventSponsor extends Model
{
    use HasUuids;

    /** Levels, highest first. */
    public const LEVELS = ['gold' => 'Gold', 'silver' => 'Silver', 'bronze' => 'Bronze', 'partner' => 'Partner'];

    protected $fillable = ['event_id', 'package_id', 'application_id', 'name', 'level', 'description', 'website', 'logo_path', 'logo_variants',
        'logo_alt_text', 'show_on_page', 'show_on_flyer', 'show_on_ticket', 'sort_order'];

    protected $casts = ['logo_variants' => 'array', 'show_on_page' => 'boolean', 'show_on_flyer' => 'boolean', 'show_on_ticket' => 'boolean', 'sort_order' => 'integer'];

    public function package(): BelongsTo
    {
        return $this->belongsTo(SponsorshipPackage::class, 'package_id');
    }

    public function levelLabel(): string
    {
        return self::LEVELS[$this->level] ?? ucfirst((string) $this->level);
    }

    /** The logo the host uploaded, or null (pages then show the sponsor's name as text, never someone else's logo). */
    public function logo(): ?ResponsiveImage
    {
        return MediaStorageService::isStoredPath($this->logo_path) ? ResponsiveImage::forSponsor($this) : null;
    }

    /** The sponsor's website when it is a full https address. */
    public function safeWebsite(): ?string
    {
        return str_starts_with((string) $this->website, 'https://') ? $this->website : null;
    }
}
