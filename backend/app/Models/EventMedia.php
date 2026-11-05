<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventMedia extends Model
{
    use HasUuids;

    public const TYPES = ['cover', 'gallery', 'organization_logo', 'sponsor_logo'];

    protected $fillable = [
        'event_id', 'media_type', 'original_path', 'thumbnail_path', 'mime_type', 'size_bytes',
        'width', 'height', 'alt_text', 'position_x', 'position_y', 'sort_order',
        'crop_x', 'crop_y', 'crop_width', 'crop_height',
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
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}