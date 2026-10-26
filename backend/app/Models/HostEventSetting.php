<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HostEventSetting extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'host_event_settings';

    protected $fillable = [
        'id',
        'organizer_id',
        'default_currency',
        'default_timezone',
        'default_location',
        'default_order_deadline_hours',
        'default_pickup_instructions',
        'default_flyer_aspect_ratio',
        'default_brand_theme',
    ];

    protected $casts = [
        'default_order_deadline_hours' => 'integer',
    ];

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(Organizer::class, 'organizer_id');
    }
}
