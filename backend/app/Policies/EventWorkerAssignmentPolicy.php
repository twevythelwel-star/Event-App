<?php

namespace App\Policies;

use App\Models\EventWorkerAssignment;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class EventWorkerAssignmentPolicy
{
    use HandlesAuthorization;

    /**
     * Determine if the user can view the assignment.
     */
    public function view(User $user, EventWorkerAssignment $assignment): bool
    {
        // System administrators can view any assignment
        if ($user->hasRole(\App\Enums\AccountType::SYSTEM_ADMIN)) {
            return true;
        }

        // Delegated administrators can view within scope
        if ($user->hasRole(\App\Enums\AccountType::ADMINISTRATOR)) {
            return true;
        }

        // Event hosts can view assignments for their events
        if ($user->hasRole(\App\Enums\AccountType::EVENT_HOST)) {
            return $assignment->organizer_id === $user->organizer_id;
        }

        // Workers can view their own assignments
        if ($assignment->user_id === $user->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine if the user can create an assignment.
     */
    public function create(User $user, ?EventWorkerAssignment $assignment = null): bool
    {
        // Only event hosts and administrators can create assignments
        if ($user->hasRole(\App\Enums\AccountType::SYSTEM_ADMIN)) {
            return true;
        }

        if ($user->hasRole(\App\Enums\AccountType::ADMINISTRATOR)) {
            return true;
        }

        if ($user->hasRole(\App\Enums\AccountType::EVENT_HOST)) {
            return true;
        }

        return false;
    }

    /**
     * Determine if the user can update the assignment.
     */
    public function update(User $user, EventWorkerAssignment $assignment): bool
    {
        // System administrators can update any assignment
        if ($user->hasRole(\App\Enums\AccountType::SYSTEM_ADMIN)) {
            return true;
        }

        // Delegated administrators can update within scope
        if ($user->hasRole(\App\Enums\AccountType::ADMINISTRATOR)) {
            return true;
        }

        // Event hosts can update assignments for their events
        if ($user->hasRole(\App\Enums\AccountType::EVENT_HOST)) {
            return $assignment->organizer_id === $user->organizer_id;
        }

        // Workers can accept their own pending assignments
        if ($assignment->user_id === $user->id && in_array($assignment->status, ['pending_acceptance', 'pending_invite'])) {
            return true;
        }

        return false;
    }

    /**
     * Determine if the user can delete the assignment.
     */
    public function delete(User $user, EventWorkerAssignment $assignment): bool
    {
        // Only system administrators can delete assignments
        // Hosts should revoke instead of delete to maintain audit trail
        return $user->hasRole(\App\Enums\AccountType::SYSTEM_ADMIN);
    }

    /**
     * Determine if the user can revoke the assignment.
     */
    public function revoke(User $user, EventWorkerAssignment $assignment): bool
    {
        if ($user->hasRole(\App\Enums\AccountType::SYSTEM_ADMIN)) {
            return true;
        }

        if ($user->hasRole(\App\Enums\AccountType::ADMINISTRATOR)) {
            return true;
        }

        if ($user->hasRole(\App\Enums\AccountType::EVENT_HOST)) {
            return $assignment->organizer_id === $user->organizer_id;
        }

        return false;
    }

    /**
     * Determine if the user can manage worker assignments for the event.
     */
    public function manageForEvent(User $user, string $eventId): bool
    {
        if ($user->hasRole(\App\Enums\AccountType::SYSTEM_ADMIN)) {
            return true;
        }

        if ($user->hasRole(\App\Enums\AccountType::ADMINISTRATOR)) {
            return true;
        }

        if ($user->hasRole(\App\Enums\AccountType::EVENT_HOST)) {
            $event = \App\Models\Event::find($eventId);
            return $event && $event->organizer_id === $user->organizer_id;
        }

        return false;
    }
}
