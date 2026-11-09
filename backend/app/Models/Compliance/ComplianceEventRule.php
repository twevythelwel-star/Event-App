<?php

declare(strict_types=1);

namespace App\Models\Compliance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An administrator's rule for event checklists in one jurisdiction: a requirement that applies (effect
 * "required"), an exception (effect "not_required"), or an additional local requirement (topic "local").
 * Conditions narrow it to some events; an empty condition matches every event. Every rule cites its source.
 */
class ComplianceEventRule extends Model
{
    public const EFFECTS = ['required' => 'Required', 'not_required' => 'Not required (exception)'];

    public const LOCATIONS = ['event' => 'Event location', 'food_preparation' => 'Food preparation location', 'alcohol_service' => 'Alcohol service location'];

    public const FOOD_MODES = ['none' => 'No food', 'sold' => 'Food sold', 'complimentary' => 'Food complimentary', 'both' => 'Food sold and complimentary'];

    public const ALCOHOL_MODES = ['none' => 'No alcohol', 'sold' => 'Alcohol sold', 'included' => 'Alcohol included in the ticket',
        'complimentary' => 'Alcohol complimentary', 'byob' => 'Bring your own (BYOB)'];

    protected $fillable = ['jurisdiction_id', 'include_descendants', 'topic', 'title', 'description', 'effect', 'location', 'conditions',
        'requirement_id', 'source_url', 'source_title', 'is_active', 'created_by', 'updated_by', 'approved_by', 'approved_at'];

    protected $casts = ['conditions' => 'array', 'include_descendants' => 'boolean', 'is_active' => 'boolean', 'approved_at' => 'datetime'];

    /** A new rule waits for a second administrator's approval before it affects any event. */
    public function awaitingApproval(): bool
    {
        return $this->approved_at === null && !$this->is_active;
    }

    public function jurisdiction(): BelongsTo
    {
        return $this->belongsTo(ComplianceJurisdiction::class, 'jurisdiction_id');
    }

    public function requirement(): BelongsTo
    {
        return $this->belongsTo(ComplianceRequirement::class, 'requirement_id');
    }

    public function label(): string
    {
        return $this->topic === 'local' ? (string) $this->title : (ComplianceRequirement::TOPICS[$this->topic] ?? $this->topic);
    }

    /**
     * Whether the rule applies to an event's arrangements.
     *
     * @param array{event_type: ?string, food_mode: string, alcohol_mode: string, caterer: bool, alcohol_vendor: bool} $facts
     */
    public function matches(array $facts): bool
    {
        $c = $this->conditions ?? [];
        $in = fn (string $key, $value) => empty($c[$key]) || in_array($value, (array) $c[$key], true);
        $flag = fn (string $key, bool $value) => !array_key_exists($key, $c) || $c[$key] === null || (bool) $c[$key] === $value;

        return $in('event_types', $facts['event_type']) && $in('food_modes', $facts['food_mode']) && $in('alcohol_modes', $facts['alcohol_mode'])
            && $flag('caterer', $facts['caterer']) && $flag('alcohol_vendor', $facts['alcohol_vendor']);
    }

    /** A short description of the conditions, for administrators and hosts. */
    public function conditionSummary(): string
    {
        $c = $this->conditions ?? [];
        $parts = [];
        if (!empty($c['event_types'])) {
            $parts[] = 'event type ' . implode(' or ', $c['event_types']);
        }
        if (!empty($c['food_modes'])) {
            $parts[] = implode(' or ', array_map(fn ($m) => strtolower(self::FOOD_MODES[$m] ?? $m), $c['food_modes']));
        }
        if (!empty($c['alcohol_modes'])) {
            $parts[] = implode(' or ', array_map(fn ($m) => strtolower(self::ALCOHOL_MODES[$m] ?? $m), $c['alcohol_modes']));
        }
        if (isset($c['caterer'])) {
            $parts[] = $c['caterer'] ? 'a licensed caterer is involved' : 'no licensed caterer';
        }
        if (isset($c['alcohol_vendor'])) {
            $parts[] = $c['alcohol_vendor'] ? 'a licensed alcohol vendor is involved' : 'no licensed alcohol vendor';
        }

        return $parts === [] ? 'every event' : implode('; ', $parts);
    }
}
