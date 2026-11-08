<?php

declare(strict_types=1);

namespace App\Services\Compliance;

use App\Models\Compliance\ComplianceEventRule;
use App\Models\Compliance\ComplianceJurisdiction;
use App\Models\Compliance\ComplianceRequirement;
use App\Models\Event;
use App\Models\EventComplianceEvidence;
use Illuminate\Support\Collection;

/**
 * The requirements checklist for one event, worked out from where it happens and how it provides food and
 * alcohol. Each item is a requirement topic (or an additional local requirement) at one level of a
 * location's jurisdiction chain.
 *
 * What decides an item's status:
 * - Only an administrator's rule (ComplianceEventRule, which cites an official source) makes an item "Required"
 *   or, as an exception, "Not Required". With no rule the honest answer is "Verification Required".
 * - What the host submits moves a Required or Verification Required item to "Submitted"; an administrator then
 *   moves it to "Pending Verification", "Verified" or "Rejected", and a verified item past its expiry date is
 *   "Expired". Ticking a box never makes a host compliant.
 */
final class EventRequirementChecklist
{
    public const STATUSES = [
        'required' => 'Required',
        'not_required' => 'Not Required',
        'submitted' => 'Submitted',
        'pending_verification' => 'Pending Verification',
        'verified' => 'Verified',
        'rejected' => 'Rejected',
        'expired' => 'Expired',
        'verification_required' => 'Verification Required',
    ];

    /** Which requirement topics each location brings, given what the event provides. */
    private const TOPICS = [
        'event' => ['special_event_permit', 'restrictions', 'required_documentation'],
        'event_food' => ['food_permit', 'temporary_food_event'],
        'food_preparation' => ['food_establishment', 'food_handler_training'],
        'alcohol_service' => ['alcohol_licensing', 'minimum_drinking_age'],
    ];

    public function __construct(private readonly JurisdictionResolver $resolver)
    {
    }

    /** The arrangements and locations of a saved event. */
    public static function factsFromEvent(Event $event): array
    {
        return self::facts([
            'category' => $event->category,
            'food_service_mode' => $event->food_service_mode,
            'alcohol_service_mode' => $event->alcohol_service_mode,
            'serves_alcohol' => $event->serves_alcohol,
            'supports_food_ordering' => $event->supports_food_ordering,
            'uses_licensed_caterer' => $event->uses_licensed_caterer,
            'uses_licensed_alcohol_vendor' => $event->uses_licensed_alcohol_vendor,
            'state_code' => $event->state_code,
            'compliance_jurisdiction_county' => $event->compliance_jurisdiction_county,
            'compliance_jurisdiction_municipality' => $event->compliance_jurisdiction_municipality,
            'city' => $event->city,
            ...$event->only(['food_prepared_elsewhere', 'food_prep_state_code', 'food_prep_county', 'food_prep_city',
                'alcohol_served_elsewhere', 'alcohol_service_state_code', 'alcohol_service_county', 'alcohol_service_city']),
        ]);
    }

    /**
     * The arrangements and locations from form input (the live preview) or an event's values.
     *
     * @return array{event_type: ?string, food_mode: string, alcohol_mode: string, caterer: bool, alcohol_vendor: bool, locations: array<string, array{0: ?string, 1: ?string, 2: ?string}>}
     */
    public static function facts(array $in): array
    {
        $food = $in['food_service_mode'] ?? null;
        $food = array_key_exists((string) $food, ComplianceEventRule::FOOD_MODES) ? $food : ((($in['supports_food_ordering'] ?? false) && $in['supports_food_ordering'] !== '0') ? 'sold' : 'none');
        $alcohol = $in['alcohol_service_mode'] ?? null;
        $alcohol = array_key_exists((string) $alcohol, ComplianceEventRule::ALCOHOL_MODES) ? $alcohol : ((($in['serves_alcohol'] ?? false) && $in['serves_alcohol'] !== '0') ? 'sold' : 'none');
        $flag = fn ($v) => $v === true || $v === 1 || $v === '1' || $v === 'true';
        $event = [$in['state_code'] ?? null, $in['compliance_jurisdiction_county'] ?? null,
            ($in['compliance_jurisdiction_municipality'] ?? null) ?: ($in['city'] ?? null)];
        $elsewhere = fn (string $flagKey, string $prefix) => $flag($in[$flagKey] ?? false)
            ? [($in["{$prefix}_state_code"] ?? null) ?: $event[0], $in["{$prefix}_county"] ?? null, $in["{$prefix}_city"] ?? null]
            : $event;

        return [
            'event_type' => isset($in['category']) && $in['category'] !== '' ? (string) $in['category'] : null,
            'food_mode' => $food,
            'alcohol_mode' => $alcohol,
            'caterer' => $flag($in['uses_licensed_caterer'] ?? false),
            'alcohol_vendor' => $flag($in['uses_licensed_alcohol_vendor'] ?? false),
            'locations' => [
                'event' => $event,
                'food_preparation' => $elsewhere('food_prepared_elsewhere', 'food_prep'),
                'alcohol_service' => $elsewhere('alcohol_served_elsewhere', 'alcohol_service'),
            ],
        ];
    }

    /**
     * @return array{sections: list<array>, counts: array<string, int>, required: int, verified: int}
     */
    public function build(array $facts, ?Event $event = null): array
    {
        $evidence = $event
            ? EventComplianceEvidence::where('event_id', $event->id)->latest('id')->get()->unique('item_key')->keyBy('item_key')
            : collect();
        $sections = [];
        $locations = ['event' => 'Event location'];
        if ($facts['food_mode'] !== 'none') {
            $locations['food_preparation'] = 'Food preparation location';
        }
        if ($facts['alcohol_mode'] !== 'none') {
            $locations['alcohol_service'] = 'Alcohol service location';
        }

        foreach ($locations as $location => $label) {
            [$state, $county, $city] = $facts['locations'][$location];
            $resolved = $this->resolver->resolve($state, $county, $city);
            $topics = self::TOPICS[$location] ?? [];
            if ($location === 'event' && $facts['food_mode'] !== 'none') {
                $topics = [...self::TOPICS['event_food'], ...$topics];
            }
            $levels = $this->levels($resolved);
            $rules = $this->rules($resolved['chain'], $location)->filter(fn (ComplianceEventRule $rule) => $rule->matches($facts));
            $requirements = ComplianceRequirement::whereIn('jurisdiction_id', collect($resolved['chain'])->pluck('id'))->get()
                ->groupBy('jurisdiction_id');

            $items = [];
            foreach ($levels as $level) {
                foreach ($topics as $topic) {
                    $applying = $this->applying($rules, $level, $topic);
                    // The federal level only shows where a rule applies: federal requirements are in addition to, not instead of, local ones.
                    if ($level['type'] === 'country' && $applying->isEmpty()) {
                        continue;
                    }
                    $items[] = $this->item($location, $level, $topic, ComplianceRequirement::TOPICS[$topic], $applying,
                        $level['node'] ? ($requirements->get($level['node']->id)?->firstWhere('topic', $topic)) : null, $evidence);
                }
                // Additional local requirements an administrator configured for this level.
                foreach ($this->applying($rules, $level, 'local')->where('effect', 'required') as $rule) {
                    $items[] = $this->item($location, $level, 'local-' . $rule->id, $rule->label(), collect([$rule]), $rule->requirement, $evidence);
                }
            }

            $sections[] = ['location' => $location, 'label' => $label, 'jurisdiction' => $resolved, 'items' => $items,
                'same_as_event' => $location !== 'event' && $facts['locations'][$location] === $facts['locations']['event']];
        }

        $all = collect($sections)->pluck('items')->flatten(1);
        $counts = $all->countBy('status')->all();
        $needed = $all->whereNotIn('status', ['not_required']);

        return [
            'sections' => $sections,
            'counts' => $counts,
            'required' => $needed->count(),
            'verified' => $needed->where('status', 'verified')->count(),
        ];
    }

    /** @return list<array{type: string, name: string, key: string, node: ?ComplianceJurisdiction}> */
    private function levels(array $resolved): array
    {
        $levels = array_map(fn ($node) => ['type' => $node->type, 'name' => $node->name, 'key' => JurisdictionResolver::keyFor($node), 'node' => $node], $resolved['chain']);
        foreach ($resolved['missing'] as $missing) {
            $levels[] = $missing + ['node' => null];
        }

        return $levels;
    }

    /** Active rules for this location in the chain: a jurisdiction's own, and those set to cover places inside another. */
    private function rules(array $chain, string $location): Collection
    {
        $ids = collect($chain)->pluck('id');

        return ComplianceEventRule::with('requirement')->where('is_active', true)->where('location', $location)->whereIn('jurisdiction_id', $ids)->get();
    }

    private function applying(Collection $rules, array $level, string $topic): Collection
    {
        if ($level['node'] === null) {
            return collect();
        }
        $ancestorIds = collect($level['node']->lineage())->pluck('id');

        return $rules->filter(fn (ComplianceEventRule $rule) => $rule->topic === $topic && (
            $rule->jurisdiction_id === $level['node']->id
            || ($rule->include_descendants && $ancestorIds->contains($rule->jurisdiction_id) && $rule->jurisdiction_id !== $level['node']->id)
        ))->values();
    }

    private function item(string $location, array $level, string $topic, string $title, Collection $rules, ?ComplianceRequirement $requirement, Collection $evidence): array
    {
        // An exception wins over a requirement where both match.
        $exception = $rules->firstWhere('effect', 'not_required');
        $rule = $exception ?? $rules->firstWhere('effect', 'required');
        $base = $rule ? $rule->effect : 'verification_required';
        $key = "{$location}:{$topic}:{$level['key']}";
        $submitted = $base === 'not_required' ? null : $evidence->get($key);

        return [
            'key' => $key,
            'title' => $title,
            'level' => $level,
            'base' => $base,
            'status' => $submitted ? $submitted->checklistStatus() : $base,
            'rule' => $rule,
            'requirement' => $requirement,
            'evidence' => $submitted,
            'can_submit' => $base !== 'not_required',
        ];
    }
}
