<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Enums\EventWorkerRole;
use App\Models\Event;
use App\Models\EventWorkerAssignment;
use App\Models\User;
use App\Services\EventWorkerService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class EventWorkerManager extends Component
{
    public string $eventId;
    public ?Event $event = null;

    // Assignment Form state
    public ?int $selectedUserId = null;
    public string $workerRole = 'ticket_checker';
    public string $stationAssignment = 'all';
    public array $customPermissions = [];

    // Success / error flash
    public ?string $feedbackMessage = null;
    public ?string $errorMessage = null;

    protected array $rules = [
        'selectedUserId' => 'required|integer',
        'workerRole' => 'required|string',
        'stationAssignment' => 'required|string|max:50',
        'customPermissions' => 'nullable|array',
    ];

    public function mount(string $eventId): void
    {
        $this->eventId = $eventId;
        $this->event = Event::find($eventId);
    }

    public function assignWorker(): void
    {
        $this->resetMessages();
        $this->validate();

        $currentUser = Auth::user();
        if (!$currentUser) {
            $this->errorMessage = 'You must be logged in to manage workers.';
            return;
        }

        $targetUser = User::find($this->selectedUserId);
        if (!$targetUser) {
            $this->errorMessage = 'Selected user was not found.';
            return;
        }

        if (!$this->event) {
            $this->errorMessage = 'Event context not found.';
            return;
        }

        try {
            $roleEnum = EventWorkerRole::from($this->workerRole);
            EventWorkerService::assignWorker(
                assigner: $currentUser,
                event: $this->event,
                targetUser: $targetUser,
                role: $roleEnum,
                stationAssignment: $this->stationAssignment,
                customPermissions: $this->customPermissions
            );

            $this->feedbackMessage = "Worker {$targetUser->name} assigned as {$roleEnum->label()} successfully.";
            $this->reset(['selectedUserId', 'customPermissions']);
            $this->workerRole = 'ticket_checker';
            $this->stationAssignment = 'all';
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function toggleWorkerStatus(int $assignmentId): void
    {
        $this->resetMessages();
        $assignment = EventWorkerAssignment::find($assignmentId);
        if ($assignment) {
            $assignment->is_active = !$assignment->is_active;
            $assignment->save();
            $statusLabel = $assignment->is_active ? 'activated' : 'deactivated';
            $this->feedbackMessage = "Worker assignment has been {$statusLabel}.";
        }
    }

    public function resetMessages(): void
    {
        $this->feedbackMessage = null;
        $this->errorMessage = null;
    }

    public function render(): View
    {
        $assignments = EventWorkerAssignment::with(['user', 'assignedBy'])
            ->where('event_id', $this->eventId)
            ->latest()
            ->get();

        $availableUsers = User::where('status', 'active')->orderBy('name')->get();

        return view('livewire.event-worker-manager', [
            'assignments' => $assignments,
            'availableUsers' => $availableUsers,
            'workerRoles' => EventWorkerRole::cases(),
        ]);
    }
}
