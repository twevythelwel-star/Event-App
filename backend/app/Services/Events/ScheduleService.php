<?php

namespace App\Services\Events;

use App\Models\Event;
use App\Models\EventActivity;
use App\Models\EventPerformer;
use App\Models\EventStage;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\EventManagementService;
use App\Services\MediaStorageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The host's event itinerary: stages, the people on the bill, and the activities that make up the schedule.
 *
 * Every change goes through EventManagementService::findEvent, so only accounts that manage the event can make it.
 * The rules live here, not in the forms: an activity ends after it starts and inside the event's own times, two
 * activities never overlap on the same stage, and stages and performers must belong to the same event.
 */
class ScheduleService
{
    public function __construct(
        private readonly EventManagementService $events,
        private readonly MediaStorageService $media,
    ) {}

    // ---- Stages ------------------------------------------------------------------------------------------------

    public function saveStage(User $user, string $eventId, ?string $stageId, array $data): EventStage
    {
        $event = $this->events->findEvent($user, $eventId);
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('Give the stage or place a name.');
        }
        $duplicate = EventStage::where('event_id', $event->id)->where('name', $name)->when($stageId, fn ($q) => $q->whereKeyNot($stageId))->exists();
        if ($duplicate) {
            throw new \InvalidArgumentException("This event already has a place called '{$name}'.");
        }
        $stage = $stageId ? EventStage::where('event_id', $event->id)->findOrFail($stageId) : new EventStage([
            'event_id' => $event->id,
            'sort_order' => (int) EventStage::where('event_id', $event->id)->max('sort_order') + 1,
        ]);
        $stage->fill(['name' => mb_substr($name, 0, 80), 'description' => $this->text($data['description'] ?? null, 300)])->save();
        $this->audit($user, $event, $stageId ? 'schedule_stage_updated' : 'schedule_stage_added', 'event_stages', $stage->id, "Place '{$stage->name}'");

        return $stage;
    }

    /** Removing a stage keeps its activities; they become "no set place". */
    public function deleteStage(User $user, string $eventId, string $stageId): void
    {
        $event = $this->events->findEvent($user, $eventId);
        $stage = EventStage::where('event_id', $event->id)->findOrFail($stageId);
        EventActivity::where('stage_id', $stage->id)->update(['stage_id' => null]);
        $stage->delete();
        $this->audit($user, $event, 'schedule_stage_removed', 'event_stages', $stageId, "Place '{$stage->name}' removed");
    }

    // ---- Performers, DJs, speakers -----------------------------------------------------------------------------

    public function savePerformer(User $user, string $eventId, ?string $performerId, array $data, ?UploadedFile $photo = null, bool $removePhoto = false): EventPerformer
    {
        $event = $this->events->findEvent($user, $eventId);
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('Give the performer or speaker a name.');
        }
        if (!array_key_exists((string) ($data['role'] ?? ''), EventPerformer::ROLES)) {
            throw new \InvalidArgumentException('Choose what they do: DJ, performer, band, guest speaker or host.');
        }
        $links = [];
        foreach ((array) ($data['links'] ?? []) as $link) {
            $url = trim((string) ($link['url'] ?? ''));
            if ($url === '') {
                continue;
            }
            if (!preg_match('#^https://[^\s"\'<>]+$#i', $url)) {
                throw new \InvalidArgumentException('Links must be full https:// addresses.');
            }
            $links[] = ['label' => mb_substr(trim((string) ($link['label'] ?? '')) ?: (string) parse_url($url, PHP_URL_HOST), 0, 40), 'url' => mb_substr($url, 0, 300)];
        }

        $performer = $performerId ? EventPerformer::where('event_id', $event->id)->findOrFail($performerId) : new EventPerformer([
            'event_id' => $event->id,
            'sort_order' => (int) EventPerformer::where('event_id', $event->id)->max('sort_order') + 1,
        ]);
        $performer->fill([
            'name' => mb_substr($name, 0, 120),
            'role' => $data['role'],
            'headline' => $this->text($data['headline'] ?? null, 160),
            'bio' => $this->text($data['bio'] ?? null, 2000),
            'links' => array_slice($links, 0, 5),
            'photo_alt_text' => $this->text($data['photo_alt_text'] ?? null, 250),
        ]);

        $oldPath = null;
        $oldVariants = null;
        if ($photo) {
            $stored = $this->media->storeImage($photo, (string) $event->organizer_id, 'performers');
            try {
                $variants = $this->media->createVariants($stored['path'], (string) $event->organizer_id, 'performers');
            } catch (\Throwable $e) {
                $this->media->delete($stored['path']);
                throw $e;
            }
            [$oldPath, $oldVariants] = [$performer->photo_path, $performer->photo_variants];
            $performer->photo_path = $stored['path'];
            $performer->photo_variants = $variants;
        } elseif ($removePhoto && $performer->photo_path) {
            [$oldPath, $oldVariants] = [$performer->photo_path, $performer->photo_variants];
            $performer->photo_path = null;
            $performer->photo_variants = null;
        }
        $performer->save();
        $this->media->delete($oldPath);
        $this->media->deleteVariants($oldVariants);
        $this->audit($user, $event, $performerId ? 'schedule_performer_updated' : 'schedule_performer_added', 'event_performers', $performer->id, "{$performer->roleLabel()} '{$performer->name}'");

        return $performer;
    }

    public function deletePerformer(User $user, string $eventId, string $performerId): void
    {
        $event = $this->events->findEvent($user, $eventId);
        $performer = EventPerformer::where('event_id', $event->id)->findOrFail($performerId);
        $this->media->delete($performer->photo_path);
        $this->media->deleteVariants($performer->photo_variants);
        $performer->delete();
        $this->audit($user, $event, 'schedule_performer_removed', 'event_performers', $performerId, "'{$performer->name}' removed");
    }

    // ---- Activities ---------------------------------------------------------------------------------------------

    /**
     * @param array{title?: string, kind?: string, description?: ?string, stage_id?: ?string, date?: string,
     *              start_time?: string, end_time?: string, is_public?: bool, performer_ids?: list<string>} $data
     *        times are typed in the event's own timezone; an end before the start means it runs past midnight
     */
    public function saveActivity(User $user, string $eventId, ?string $activityId, array $data): EventActivity
    {
        $event = $this->events->findEvent($user, $eventId);
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            throw new \InvalidArgumentException('Give the activity a title.');
        }
        if (!array_key_exists((string) ($data['kind'] ?? ''), EventActivity::KINDS)) {
            throw new \InvalidArgumentException('Choose what kind of activity this is.');
        }
        [$startsAt, $endsAt] = $this->times($event, (string) ($data['date'] ?? ''), (string) ($data['start_time'] ?? ''), (string) ($data['end_time'] ?? ''));

        $stageId = filled($data['stage_id'] ?? null) ? (string) $data['stage_id'] : null;
        if ($stageId && !EventStage::where('event_id', $event->id)->whereKey($stageId)->exists()) {
            throw new \InvalidArgumentException('That stage is not part of this event.');
        }
        $performerIds = array_values(array_unique(array_filter((array) ($data['performer_ids'] ?? []), 'is_string')));
        if (count($performerIds) !== EventPerformer::where('event_id', $event->id)->whereIn('id', $performerIds)->count()) {
            throw new \InvalidArgumentException('Only people on this event can be added to its activities.');
        }

        if ($stageId) {
            $clash = EventActivity::where('event_id', $event->id)->where('stage_id', $stageId)
                ->when($activityId, fn ($q) => $q->whereKeyNot($activityId))
                ->where('starts_at', '<', $endsAt)->where('ends_at', '>', $startsAt)->first();
            if ($clash) {
                $tz = $event->timezone ?: config('app.timezone');
                throw new \InvalidArgumentException("This overlaps '{$clash->title}' on the same stage ("
                    . $clash->starts_at->copy()->setTimezone($tz)->format('g:i A') . '–' . $clash->ends_at->copy()->setTimezone($tz)->format('g:i A') . ').');
            }
        }

        return DB::transaction(function () use ($user, $event, $activityId, $data, $title, $startsAt, $endsAt, $stageId, $performerIds) {
            $activity = $activityId ? EventActivity::where('event_id', $event->id)->findOrFail($activityId) : new EventActivity(['event_id' => $event->id]);
            $activity->fill([
                'title' => mb_substr($title, 0, 150),
                'kind' => $data['kind'],
                'description' => $this->text($data['description'] ?? null, 2000),
                'stage_id' => $stageId,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'is_public' => (bool) ($data['is_public'] ?? true),
            ])->save();
            $activity->performers()->sync($performerIds);
            $this->audit($user, $event, $activityId ? 'schedule_activity_updated' : 'schedule_activity_added', 'event_activities', $activity->id, "'{$activity->title}'");

            return $activity;
        });
    }

    public function deleteActivity(User $user, string $eventId, string $activityId): void
    {
        $event = $this->events->findEvent($user, $eventId);
        $activity = EventActivity::where('event_id', $event->id)->findOrFail($activityId);
        $activity->delete();
        $this->audit($user, $event, 'schedule_activity_removed', 'event_activities', $activityId, "'{$activity->title}' removed");
    }

    // ---- What attendees see -------------------------------------------------------------------------------------

    /**
     * The public schedule, grouped by the event's local date. Food service hours set on the event appear as their
     * own entry unless the host already scheduled food service, so the schedule never contradicts the food times.
     *
     * @return Collection<string, Collection<int, array{title: string, kind: string, kind_label: string, description: ?string,
     *         stage: ?string, starts: Carbon, ends: Carbon, performers: Collection, auto: bool}>>
     */
    public function publicSchedule(Event $event): Collection
    {
        $tz = $event->timezone ?: config('app.timezone');
        $activities = EventActivity::with(['stage', 'performers'])->where('event_id', $event->id)->where('is_public', true)->orderBy('starts_at')->get();
        $rows = $activities->map(fn (EventActivity $a) => [
            'title' => $a->title, 'kind' => $a->kind, 'kind_label' => $a->kindLabel(), 'description' => $a->description,
            'stage' => $a->stage?->name, 'starts' => $a->starts_at->copy()->setTimezone($tz), 'ends' => $a->ends_at->copy()->setTimezone($tz),
            'performers' => $a->performers, 'auto' => false,
        ]);

        $food = $this->foodServiceWindow($event);
        if ($food && !$activities->contains(fn ($a) => $a->kind === 'food_service')) {
            $rows->push(['title' => 'Food service', 'kind' => 'food_service', 'kind_label' => EventActivity::KINDS['food_service'],
                'description' => 'Food orders are served during these hours.', 'stage' => null,
                'starts' => $food[0]->copy()->setTimezone($tz), 'ends' => $food[1]->copy()->setTimezone($tz), 'performers' => collect(), 'auto' => true]);
        }

        return $rows->sortBy(fn ($row) => $row['starts']->getTimestamp())->values()->groupBy(fn ($row) => $row['starts']->format('Y-m-d'));
    }

    /** @return array{0: Carbon, 1: Carbon}|null the event's food service hours in UTC, when both are set */
    private function foodServiceWindow(Event $event): ?array
    {
        $tz = $event->timezone ?: config('app.timezone');
        $date = $event->starts_at?->copy()->setTimezone($tz)->format('Y-m-d') ?? $event->event_date_iso;
        if (!$date || !$event->food_service_start_time || !$event->food_service_end_time) {
            return null;
        }
        $start = Carbon::parse("{$date} {$event->food_service_start_time}", $tz);
        $end = Carbon::parse("{$date} {$event->food_service_end_time}", $tz);
        if ($end->lessThanOrEqualTo($start)) {
            $end->addDay();
        }

        return [$start->utc(), $end->utc()];
    }

    /** @return array{0: Carbon, 1: Carbon} UTC start and end, checked against the event's own times */
    private function times(Event $event, string $date, string $start, string $end): array
    {
        $tz = $event->timezone ?: config('app.timezone');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !preg_match('/^\d{2}:\d{2}$/', $start) || !preg_match('/^\d{2}:\d{2}$/', $end)) {
            throw new \InvalidArgumentException('Give the activity a date, a start time and an end time.');
        }
        $startsAt = Carbon::parse("{$date} {$start}", $tz);
        $endsAt = Carbon::parse("{$date} {$end}", $tz);
        if ($endsAt->lessThanOrEqualTo($startsAt)) {
            // An end time earlier than the start means the activity runs past midnight.
            $endsAt->addDay();
        }
        if ($startsAt->diffInHours($endsAt) > 24) {
            throw new \InvalidArgumentException('An activity can last at most 24 hours.');
        }
        $startsAt->utc();
        $endsAt->utc();
        if ($event->starts_at && $event->ends_at && ($startsAt->lt($event->starts_at) || $endsAt->gt($event->ends_at))) {
            throw new \InvalidArgumentException('Activities must take place during the event ('
                . $event->starts_at->copy()->setTimezone($tz)->format('M j, g:i A') . ' to ' . $event->ends_at->copy()->setTimezone($tz)->format('M j, g:i A') . ').');
        }

        return [$startsAt, $endsAt];
    }

    private function text(mixed $value, int $max): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, $max);
    }

    private function audit(User $user, Event $event, string $action, string $entity, string $id, string $details): void
    {
        AuditLogService::log(action: $action, targetEntity: $entity, targetId: $id, details: "{$event->title}: {$details}.",
            actor: $user, organizerId: (string) $event->organizer_id, eventId: (string) $event->id);
    }
}
