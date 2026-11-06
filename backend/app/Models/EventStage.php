<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A place at the event where activities happen: a stage, a tent, the food court. */
class EventStage extends Model
{
    use HasUuids;

    protected $fillable = ['event_id', 'name', 'description', 'sort_order'];

    protected $casts = ['sort_order' => 'integer'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(EventActivity::class, 'stage_id')->orderBy('starts_at');
    }
}
