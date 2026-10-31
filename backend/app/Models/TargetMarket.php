<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TargetMarket extends Model
{
    use HasUuids;

    protected $fillable = [
        'state_code', 'city', 'slug', 'default_timezone', 'zip_codes', 'metadata',
        'discovery_priority', 'is_featured', 'is_enabled',
    ];

    protected $casts = [
        'zip_codes' => 'array',
        'metadata' => 'array',
        'discovery_priority' => 'integer',
        'is_featured' => 'boolean',
        'is_enabled' => 'boolean',
    ];

    public function state(): BelongsTo
    {
        return $this->belongsTo(UsState::class, 'state_code', 'code');
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class, 'target_market_id');
    }
}
