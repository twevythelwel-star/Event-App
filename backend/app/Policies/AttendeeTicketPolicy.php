<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AttendeeTicket;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderOperationsService;
use App\Services\OrderPlacementService;

/** Who may act on one guest's ticket. */
class AttendeeTicketPolicy
{
    /** The order's holder, or staff who may verify this order's payments (event owners, payment verifiers, system admins). */
    public function reissue(User $user, AttendeeTicket $ticket): bool
    {
        $order = Order::withoutGlobalScopes()->find($ticket->order_id);
        if (!$order) {
            return false;
        }

        return app(OrderPlacementService::class)->isUserAuthorizedForOrder($order, $user)
            || (app(OrderOperationsService::class)->capabilities($user, $order)['verify'] ?? false);
    }
}
