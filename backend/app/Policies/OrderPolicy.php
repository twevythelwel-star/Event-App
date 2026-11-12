<?php

namespace App\Policies;

use App\Models\EventWorkerAssignment;
use App\Models\Order;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class OrderPolicy
{
    use HandlesAuthorization;

    /**
     * Determine if the user can view the order.
     */
    public function view(User $user, Order $order, ?EventWorkerAssignment $assignment = null): bool
    {
        // System administrators can view any order
        if ($user->hasRole(\App\Enums\AccountType::SYSTEM_ADMIN)) {
            return true;
        }

        // Delegated administrators can view within their scope
        if ($user->hasRole(\App\Enums\AccountType::ADMINISTRATOR)) {
            return \App\Support\AdminAuthorization::isActiveAdmin($user)
                && \App\Support\AdminAuthorization::organizerInScope($user, $order->organizer_id)
                && \Illuminate\Support\Facades\Gate::forUser($user)->allows(\App\Support\AdminAuthorization::ORDER_VIEW, $order);
        }

        // Event hosts can view orders for their own events
        if ($user->hasRole(\App\Enums\AccountType::EVENT_HOST)) {
            return $order->event->organizer_id === $user->organizer_id;
        }

        // Attendees can view their own orders
        if ($user->hasRole(\App\Enums\AccountType::ATTENDEE)) {
            return $order->user_id === $user->id;
        }

        // Event workers based on role and assignment
        if ($assignment && $assignment->isCurrentlyActive()) {
            $role = $assignment->worker_role;

            // Payment clerks can view relevant orders
            if ($role === \App\Enums\EventWorkerRole::PAYMENT_CLERK) {
                return $assignment->event_id === $order->event_id;
            }

            // Event supervisors can view relevant orders
            if ($role === \App\Enums\EventWorkerRole::EVENT_SUPERVISOR) {
                return $assignment->event_id === $order->event_id;
            }

            // Custom restricted workers need explicit permission
            if ($role === \App\Enums\EventWorkerRole::CUSTOM_RESTRICTED_WORKER) {
                return $assignment->canPerform('view_relevant_orders') && $assignment->event_id === $order->event_id;
            }

            // Ticket checkers and food station workers cannot view full order details
            return false;
        }

        return false;
    }

    /**
     * Determine if the user can record cash payment.
     */
    public function recordCashPayment(User $user, Order $order, ?EventWorkerAssignment $assignment = null): bool
    {
        if ($this->runsOrdersEvent($user, $order)) {
            return true;
        }
        if ($assignment && $assignment->exists && $assignment->isCurrentlyActive()) {
            return $assignment->canPerform('record_cash_received') && $assignment->event_id === $order->event_id;
        }

        return false;
    }

    /**
     * Determine if the user can verify a payment.
     */
    public function verifyPayment(User $user, Order $order, ?EventWorkerAssignment $assignment = null): bool
    {
        if ($this->runsOrdersEvent($user, $order)) {
            return true;
        }
        if ($assignment && $assignment->exists && $assignment->isCurrentlyActive()) {
            return $assignment->canPerform('verify_payments') && $assignment->event_id === $order->event_id;
        }

        return false;
    }

    /**
     * Whether the user runs the order's event: a system administrator, or a manager of the event's organizer
     * (EventWorkerService::actsWithoutAssignment). They act on the event's money without a staff assignment.
     */
    private function runsOrdersEvent(User $user, Order $order): bool
    {
        $event = \App\Models\Event::withoutGlobalScopes()->find($order->event_id);

        return $event !== null && \App\Services\EventWorkerService::actsWithoutAssignment($user, $event);
    }

    /**
     * Determine if the user can issue a refund.
     */
    public function issueRefund(User $user, Order $order, ?EventWorkerAssignment $assignment = null): bool
    {
        // Only event hosts and administrators can issue refunds
        if ($user->hasRole(\App\Enums\AccountType::EVENT_HOST)) {
            return $order->event->organizer_id === $user->organizer_id;
        }

        if ($user->hasRole(\App\Enums\AccountType::SYSTEM_ADMIN)) {
            return true;
        }

        if ($user->hasRole(\App\Enums\AccountType::ADMINISTRATOR)) {
            // Delegated admins have no refund capability flag; scope or payment-review permission alone
            // must not grant a financial mutation.
            return false;
        }

        // Workers cannot issue refunds (prohibited)
        return false;
    }

    /**
     * Determine if the user can modify order items.
     */
    public function modifyItems(User $user, Order $order, ?EventWorkerAssignment $assignment = null): bool
    {
        // Event hosts can modify their own event orders
        if ($user->hasRole(\App\Enums\AccountType::EVENT_HOST)) {
            return $order->event->organizer_id === $user->organizer_id;
        }

        // Workers cannot modify order items (prohibited)
        return false;
    }
}
