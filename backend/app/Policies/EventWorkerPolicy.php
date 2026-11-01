<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\AccountType;
use App\Enums\EventWorkerRole;
use App\Models\Event;
use App\Models\EventWorkerAssignment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;

class EventWorkerPolicy
{
    /**
     * Platform administrator bypass for emergency operational oversight.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole(AccountType::SYSTEM_ADMIN)) {
            return true;
        }
        return null;
    }

    /**
     * Determine whether the user can manage event-worker assignments.
     * Rule: Event Hosts (owners) and Event Supervisors can manage worker assignments for their assigned event.
     * All other worker roles cannot manage worker assignments.
     */
    public function manageWorkerAssignments(User $user, Event $event): bool
    {
        // This event's own host, or a supervisor whose assignment on it is current
        return \App\Services\EventWorkerService::staffManagerRole($user, $event) !== null;
    }

    /**
     * Determine whether the worker can record cash received or verify payments.
     * Rule: Payment Clerk and Event Supervisor can record cash and verify payments.
     * Ticket Checker, Food Station Worker cannot record payments.
     * Altering financial records without an audit trail is strictly prohibited.
     */
    public function recordPayment(User $user, Order $order): bool
    {
        $assignment = $this->getActiveAssignment($user, $order->event_id);

        if (!$assignment) {
            return $user->hasRole(AccountType::EVENT_HOST);
        }

        return $assignment->canPerform('record_cash_received') || $assignment->canPerform('verify_payments');
    }

    /**
     * Determine whether the worker can scan QR tickets and validate ticket status.
     * Rule: Ticket Checker and Event Supervisor can scan and record entry.
     * Ticket Checker must not be able to view unnecessary payment or personal information, issue refunds, or change prices.
     */
    public function scanTicket(User $user, Order $order): bool
    {
        $assignment = $this->getActiveAssignment($user, $order->event_id);

        if (!$assignment) {
            return $user->hasRole(AccountType::EVENT_HOST);
        }

        return $assignment->canPerform('scan_qr_tickets') || $assignment->canPerform('validate_ticket_status');
    }

    /**
     * Determine whether the worker can mark food quantities collected.
     * Rule: Food Station Worker and Event Supervisor can collect food.
     * Must not view unrelated customer info, change menu prices, or access financial reports.
     */
    public function collectFoodItem(User $user, OrderItem $item, Order $order): bool
    {
        $assignment = $this->getActiveAssignment($user, $order->event_id);

        if (!$assignment) {
            return $user->hasRole(AccountType::EVENT_HOST);
        }

        if (!$assignment->canPerform('mark_quantities_collected')) {
            return false;
        }

        // Station filtering: If worker is assigned to specific station, item must match
        if ($assignment->station_assignment !== 'all') {
            $itemStation = $item->menuItem?->station ?? 'all';
            if ($itemStation !== 'all' && $itemStation !== $assignment->station_assignment) {
                return false;
            }
        }

        return true;
    }

    /**
     * Determine whether the worker can view operational reports.
     * Rule: Event Supervisor can view reports for assigned event.
     * Payment Clerk, Ticket Checker, Food Station Worker cannot view financial reports.
     * Supervisor must not access other hosts' events or platform-wide administrator settings.
     */
    public function viewReports(User $user, Event $event): bool
    {
        // Event Host can view reports for their own event
        if ($user->hasRole(AccountType::EVENT_HOST)) {
            return true;
        }

        $assignment = $this->getActiveAssignment($user, $event->id);

        if (!$assignment) {
            return false;
        }

        // Supervisor can view relevant reports for assigned event
        if ($assignment->worker_role === EventWorkerRole::EVENT_SUPERVISOR) {
            return true;
        }

        return $assignment->canPerform('view_relevant_reports');
    }

    /**
     * Retrieve the active worker assignment for a user on a specific event.
     * Enforces active status and time-bound boundaries in real-time.
     */
    protected function getActiveAssignment(User $user, string $eventId): ?EventWorkerAssignment
    {
        $assignment = EventWorkerAssignment::where('user_id', $user->id)
            ->where(function ($query) use ($eventId) {
                $query->where('event_id', $eventId)
                    ->orWhereNull('event_id');
            })
            ->where('is_active', true)
            ->first();

        if ($assignment && $assignment->isCurrentlyActive()) {
            return $assignment;
        }

        return null;
    }
}
