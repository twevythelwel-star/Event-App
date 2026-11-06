<?php

namespace App\Http\Controllers;

use App\Models\EventActivity;
use App\Models\EventPerformer;
use App\Models\EventStage;
use App\Services\EventManagementService;
use App\Services\Events\ScheduleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The schedule studio: the host builds the event's programme (activities), the places they happen (stages) and the
 * people on the bill (DJs, performers, speakers), and previews the schedule as attendees will see it.
 * Authorization and every rule live in ScheduleService.
 */
class ScheduleStudioController extends Controller
{
    private const TABS = ['programme', 'people', 'stages', 'preview'];

    public function __construct(
        private readonly EventManagementService $events,
        private readonly ScheduleService $schedule,
    ) {}

    public function show(Request $request, string $id): View
    {
        $event = $this->events->findEvent($request->user(), $id);
        $tab = in_array($request->query('tab'), self::TABS, true) ? $request->query('tab') : 'programme';

        return view('events.schedule-studio', [
            'event' => $event,
            'tab' => $tab,
            'tz' => $event->timezone ?: config('app.timezone'),
            'stages' => EventStage::where('event_id', $event->id)->orderBy('sort_order')->get(),
            'performers' => EventPerformer::where('event_id', $event->id)->orderBy('sort_order')->get(),
            'activities' => EventActivity::with(['stage', 'performers'])->where('event_id', $event->id)->orderBy('starts_at')->get(),
            'publicSchedule' => $this->schedule->publicSchedule($event),
        ]);
    }

    public function saveStage(Request $request, string $id, ?string $stageId = null): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:80'], 'description' => ['nullable', 'string', 'max:300']]);

        return $this->run($id, 'stages', fn () => $this->schedule->saveStage($request->user(), $id, $stageId, $data), $stageId ? 'Place saved.' : 'Place added.');
    }

    public function deleteStage(Request $request, string $id, string $stageId): RedirectResponse
    {
        return $this->run($id, 'stages', fn () => $this->schedule->deleteStage($request->user(), $id, $stageId), 'Place removed. Its activities are kept without a set place.');
    }

    public function savePerformer(Request $request, string $id, ?string $performerId = null): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'role' => ['required', Rule::in(array_keys(EventPerformer::ROLES))],
            'headline' => ['nullable', 'string', 'max:160'],
            'bio' => ['nullable', 'string', 'max:2000'],
            'links' => ['nullable', 'array', 'max:5'],
            'links.*.label' => ['nullable', 'string', 'max:40'],
            'links.*.url' => ['nullable', 'string', 'max:300'],
            'photo' => ['nullable', 'file', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'photo_alt_text' => ['nullable', 'string', 'max:250'],
            'remove_photo' => ['nullable', 'boolean'],
        ], ['photo.mimes' => 'Upload a JPG, PNG or WebP photo.', 'photo.max' => 'Photos must be 5 MB or smaller.']);

        return $this->run($id, 'people', fn () => $this->schedule->savePerformer($request->user(), $id, $performerId, $data,
            $request->file('photo'), $request->boolean('remove_photo')), $performerId ? 'Profile saved.' : 'Added to the line-up.');
    }

    public function deletePerformer(Request $request, string $id, string $performerId): RedirectResponse
    {
        return $this->run($id, 'people', fn () => $this->schedule->deletePerformer($request->user(), $id, $performerId), 'Removed from the line-up.');
    }

    public function saveActivity(Request $request, string $id, ?string $activityId = null): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'kind' => ['required', Rule::in(array_keys(EventActivity::KINDS))],
            'description' => ['nullable', 'string', 'max:2000'],
            'stage_id' => ['nullable', 'string', 'max:64'],
            'date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'performer_ids' => ['nullable', 'array', 'max:20'],
            'performer_ids.*' => ['string', 'max:64'],
        ]);
        $data['is_public'] = !$request->boolean('hidden');

        return $this->run($id, 'programme', fn () => $this->schedule->saveActivity($request->user(), $id, $activityId, $data), $activityId ? 'Activity saved.' : 'Activity added to the schedule.');
    }

    public function deleteActivity(Request $request, string $id, string $activityId): RedirectResponse
    {
        return $this->run($id, 'programme', fn () => $this->schedule->deleteActivity($request->user(), $id, $activityId), 'Activity removed.');
    }

    private function run(string $id, string $tab, callable $change, string $message): RedirectResponse
    {
        $back = redirect()->route('events.schedule-studio', ['id' => $id, 'tab' => $tab]);
        try {
            $change();
        } catch (\InvalidArgumentException $e) {
            return $back->withErrors(['schedule' => $e->getMessage()])->withInput();
        }

        return $back->with('status', $message);
    }
}
