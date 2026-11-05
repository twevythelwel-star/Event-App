<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class EventPolicy
{
    use HandlesAuthorization;

    /**
     * Determine if the user can view the event.
     */
    public function view(User $user, Event $event): bool
    {
        // Published events are viewable by all authenticated users
        if ($event->is_published) {
            return true;
        }

        // Unpublished events require host or admin access
        if ($user->hasRole(\App\Enums\AccountType::SYSTEM_ADMIN)) {
            return true;
        }

        if ($user->hasRole(\App\Enums\AccountType::ADMINISTRATOR)) {
            return \App\Support\AdminAuthorization::isActiveAdmin($user)
                && \Illuminate\Support\Facades\Gate::forUser($user)->allows(\App\Support\AdminAuthorization::EVENT_VIEW, $event);
        }

        if ($user->hasRole(\App\Enums\AccountType::EVENT_HOST)) {
            return $event->organizer_id === $user->organizer_id;
        }

        return false;
    }

    /**
     * Determine if the user can update the event.
     */
    public function update(User $user, Event $event): bool
    {
        if ($user->hasRole(\App\Enums\AccountType::SYSTEM_ADMIN)) {
            return true;
        }

        if ($user->hasRole(\App\Enums\AccountType::ADMINISTRATOR)) {
            return \App\Support\AdminAuthorization::isActiveAdmin($user)
                && (bool) ($user->adminPermissions?->can_manage_events ?? false)
                && \App\Support\AdminAuthorization::organizerInScope($user, $event->organizer_id);
        }

        if ($user->hasRole(\App\Enums\AccountType::EVENT_HOST)) {
            return $event->organizer_id === $user->organizer_id;
        }

        return false;
    }

    /**
     * Determine if the user can delete the event.
     */
    public function delete(User $user, Event $event): bool
    {
        // Only system administrators can delete events
        return $user->hasRole(\App\Enums\AccountType::SYSTEM_ADMIN);
    }

    /**
     * Determine if the user can manage event staff.
     */
    public function manageStaff(User $user, Event $event): bool
    {
        if ($user->hasRole(\App\Enums\AccountType::SYSTEM_ADMIN)) {
            return true;
        }

        if ($user->hasRole(\App\Enums\AccountType::ADMINISTRATOR)) {
            return \App\Support\AdminAuthorization::isActiveAdmin($user)
                && (bool) ($user->adminPermissions?->can_manage_events ?? false)
                && \App\Support\AdminAuthorization::organizerInScope($user, $event->organizer_id);
        }

        if ($user->hasRole(\App\Enums\AccountType::EVENT_HOST)) {
            return $event->organizer_id === $user->organizer_id;
        }

        return false;
    }

    /**
     * Determine if the user can view event reports.
     */
    public function viewReports(User $user, Event $event): bool
    {
        if ($user->hasRole(\App\Enums\AccountType::SYSTEM_ADMIN)) {
            return true;
        }

        if ($user->hasRole(\App\Enums\AccountType::ADMINISTRATOR)) {
            return \App\Support\AdminAuthorization::isActiveAdmin($user)
                && \App\Support\AdminAuthorization::organizerInScope($user, $event->organizer_id)
                && \Illuminate\Support\Facades\Gate::forUser($user)->allows(\App\Support\AdminAuthorization::REPORTS_VIEW);
        }

        if ($user->hasRole(\App\Enums\AccountType::EVENT_HOST)) {
            return $event->organizer_id === $user->organizer_id;
        }

        return false;
    }

    /**
     * Determine if the user can change event prices.
     */
    public function changePrices(User $user, Event $event): bool
    {
        // Only event hosts and administrators can change prices
        if ($user->hasRole(\App\Enums\AccountType::SYSTEM_ADMIN)) {
            return true;
        }

        if ($user->hasRole(\App\Enums\AccountType::ADMINISTRATOR)) {
            return \App\Support\AdminAuthorization::isActiveAdmin($user)
                && (bool) ($user->adminPermissions?->can_manage_events ?? false)
                && \App\Support\AdminAuthorization::organizerInScope($user, $event->organizer_id);
        }

        if ($user->hasRole(\App\Enums\AccountType::EVENT_HOST)) {
            return $event->organizer_id === $user->organizer_id;
        }

        // Workers cannot change prices (prohibited)
        return false;
    }
}
