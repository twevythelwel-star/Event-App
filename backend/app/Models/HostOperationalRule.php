<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HostOperationalRule extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'host_operational_rules';

    protected $fillable = [
        'id',
        'organizer_id',
        'stations',
        'max_event_capacity',
        'max_per_item_portions',
        'enforce_anti_duplicate_redemption',
        'allow_partial_collection',
        'food_prep_buffer_percent',
    ];

    protected $casts = [
        'stations' => 'array',
        'max_event_capacity' => 'integer',
        'max_per_item_portions' => 'integer',
        'enforce_anti_duplicate_redemption' => 'boolean',
        'allow_partial_collection' => 'boolean',
        'food_prep_buffer_percent' => 'integer',
    ];

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(Organizer::class, 'organizer_id');
    }

    /**
     * Compute safe target prep portions using integer math (avoids IEEE-754 float drift).
     */
    public function calculatePrepForecast(int $soldPortions): int
    {
        $buffer = $this->food_prep_buffer_percent ?: 10;
        return (int) ceil(($soldPortions * (100 + $buffer)) / 100);
    }
}
