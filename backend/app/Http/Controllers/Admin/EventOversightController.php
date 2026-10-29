<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventWorkerAssignment;
use App\Models\Order;
use App\Services\AuditLogService;
use App\Support\AdminAuthorization as Admin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EventOversightController extends Controller
{
    private const STATUSES = ['draft', 'published', 'unpublished', 'cancelled'];

    /**
     * Moderation actions an administrator may take, the state each needs and what it changes.
     * They mirror the host's own publish/unpublish/ordering switches (EventController).
     */
    private const ACTIONS = [
        'unpublish' => ['from' => ['published'], 'label' => 'Unpublish'],
        'republish' => ['from' => ['unpublished'], 'label' => 'Republish'],
        'close_ordering' => ['from' => ['published', 'unpublished', 'draft'], 'label' => 'Close ordering'],
        'open_ordering' => ['from' => ['published'], 'label' => 'Reopen ordering'],
    ];

    public function index(Request $request): View
    {
        Gate::authorize(Admin::EVENTS_VIEW);
        $admin = $request->user();

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(self::STATUSES)],
            'ordering' => ['nullable', Rule::in(['open', 'closed'])],
        ]);

        $query = Admin::applyOrganizerScope(Event::allTenants(), $admin)
            ->with('organizer:id,name')
            ->withCount([
                'orders as orders_total' => fn ($q) => $q->allTenants(),
                'orders as orders_verified' => fn ($q) => $q->allTenants()->whereIn('status', ['paid', 'partially_paid']),
                'orders as orders_awaiting_review' => fn ($q) => $q->allTenants()->whereIn('status', ['payment_submitted', 'under_verification']),
            ]);

        if ($term = trim((string) ($filters['q'] ?? ''))) {
            $query->where(fn ($q) => $q->where('title', 'like', "%{$term}%")->orWhere('organizer_name', 'like', "%{$term}%"));
        }
        if ($status = $filters['status'] ?? null) {
            $query->where('status', $status);
        }
        if ($ordering = $filters['ordering'] ?? null) {
            $query->where('is_ordering_open', $ordering === 'open');
        }

        return view('admin.events.index', [
            'events' => $query->latest()->paginate(20)->withQueryString(),
            'filters' => $filters,
            'statuses' => self::STATUSES,
        ]);
    }

    public function show(Request $request, string $event): View
    {
        $record = $this->findEvent($event);
        Gate::authorize(Admin::EVENT_VIEW, $record);

        $orders = Order::allTenants()->where('event_id', $record->id);
        $statusCounts = (clone $orders)->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        $canModerate = Gate::allows(Admin::EVENT_MODERATE, $record);

        return view('admin.events.show', [
            'event' => $record->loadMissing('organizer:id,name'),
            'statusCounts' => $statusCounts,
            'ordersTotal' => (clone $orders)->count(),
            'staffCount' => EventWorkerAssignment::where('event_id', $record->id)->where('is_active', true)->count(),
            'canSeePayments' => Gate::allows(Admin::PAYMENTS_VIEW),
            'canModerate' => $canModerate,
            'actions' => $canModerate ? $this->availableActions($record) : [],
        ]);
    }

    public function moderate(Request $request, string $event): RedirectResponse
    {
        $record = $this->findEvent($event);
        Gate::authorize(Admin::EVENT_MODERATE, $record);

        $data = $request->validate([
            'action' => ['required', Rule::in(array_keys(self::ACTIONS))],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
            'confirm' => ['accepted'],
        ], [
            'reason.required' => 'Give a reason. The host and the audit log will see it.',
            'confirm.accepted' => 'Tick the box to confirm this change.',
        ]);

        $action = $data['action'];
        if (!array_key_exists($action, $this->availableActions($record))) {
            return back()->with('error', self::ACTIONS[$action]['label'] . ' is not possible for an event that is ' . $record->status
                . ($record->is_ordering_open ? ' with ordering open.' : ' with ordering closed.'));
        }

        $before = "status={$record->status}, ordering=" . ($record->is_ordering_open ? 'open' : 'closed');
        match ($action) {
            'unpublish' => $record->forceFill(['status' => 'unpublished', 'is_ordering_open' => false]),
            'republish' => $record->forceFill(['status' => 'published']),
            'close_ordering' => $record->forceFill(['is_ordering_open' => false]),
            'open_ordering' => $record->forceFill(['is_ordering_open' => true]),
        };
        $record->save();
        $after = "status={$record->status}, ordering=" . ($record->is_ordering_open ? 'open' : 'closed');

        AuditLogService::log(
            action: 'event_moderated',
            targetEntity: 'events',
            targetId: (string) $record->id,
            details: "{$record->title}: {$action} ({$before} -> {$after}). Reason: {$data['reason']}",
            actor: $request->user(),
            organizerId: (string) $record->organizer_id,
            eventId: (string) $record->id,
        );

        return back()->with('success', self::ACTIONS[$action]['label'] . ' done for "' . $record->title . '".');
    }

    /** @return array<string, string> */
    private function availableActions(Event $event): array
    {
        $available = [];
        foreach (self::ACTIONS as $key => $action) {
            if (!in_array($event->status, $action['from'], true)) {
                continue;
            }
            if ($key === 'close_ordering' && !$event->is_ordering_open) {
                continue;
            }
            if ($key === 'open_ordering' && $event->is_ordering_open) {
                continue;
            }
            $available[$key] = $action['label'];
        }

        return $available;
    }

    private function findEvent(string $id): Event
    {
        return Event::allTenants()->whereKey($id)->firstOrFail();
    }
}
