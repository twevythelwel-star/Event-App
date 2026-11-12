<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A photo or logo a host uploaded for an event. The row holds references to stored files (the sanitized original
 * and its responsive variants), never the image bytes.
 */
class EventMedia extends Model
{
    use HasUuids;

    public const TYPES = ['cover', 'gallery', 'venue', 'organization_logo', 'sponsor_logo'];

    /** The kinds of photo a host adds on the event page: name, and how many the event may have. */
    public const HOST_PHOTO_KINDS = [
        'gallery' => ['label' => 'Gallery photo', 'limit' => 12],
        'cover' => ['label' => 'Main photo (shown when there is no flyer)', 'limit' => 1],
        'venue' => ['label' => 'Venue photo', 'limit' => 8],
    ];

    /** approved: shown; hidden: an administrator took it down (kept, so the decision can be reversed). */
    public const MODERATION_STATUSES = ['approved', 'hidden'];

    protected $fillable = [
        'event_id', 'media_type', 'original_path', 'thumbnail_path', 'small_thumbnail_path', 'medium_thumbnail_path',
        'large_thumbnail_path', 'variants', 'mime_type', 'size_bytes', 'width', 'height', 'alt_text', 'position_x',
        'position_y', 'sort_order', 'crop_x', 'crop_y', 'crop_width', 'crop_height', 'uploaded_by',
        'moderation_status', 'moderated_by', 'moderated_at', 'moderation_note',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'position_x' => 'integer',
        'position_y' => 'integer',
        'sort_order' => 'integer',
        'crop_x' => 'integer',
        'crop_y' => 'integer',
        'crop_width' => 'integer',
        'crop_height' => 'integer',
        'variants' => 'array',
        'moderated_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function isHidden(): bool
    {
        return ($this->moderation_status ?? 'approved') === 'hidden';
    }

    public function responsiveImage(): \App\Support\ResponsiveImage
    {
        return \App\Support\ResponsiveImage::forEventMedia($this);
    }
}
