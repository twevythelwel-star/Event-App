<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** One item on the event's schedule: what happens, when, where, and who is on. Times are stored in UTC. */
class EventActivity extends Model
{
    use HasUuids;

    public const KINDS = [
        'performance' => 'Performance', 'dj_set' => 'DJ set', 'talk' => 'Talk / speaker', 'food_service' => 'Food service',
        'activity' => 'Activity', 'ceremony' => 'Ceremony', 'break' => 'Break',
    ];

    protected $fillable = ['event_id', 'stage_id', 'title', 'kind', 'description', 'starts_at', 'ends_at', 'is_public'];

    protected $casts = ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'is_public' => 'boolean'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(EventStage::class, 'stage_id');
    }

    public function performers(): BelongsToMany
    {
        return $this->belongsToMany(EventPerformer::class, 'event_activity_performer', 'activity_id', 'performer_id')->orderBy('sort_order');
    }

    public function kindLabel(): string
    {
        return self::KINDS[$this->kind] ?? ucfirst((string) $this->kind);
    }
}
