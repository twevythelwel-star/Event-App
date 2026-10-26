<?php

namespace App\Services;

/**
 * Raised inside the order placement transaction to abort it (rolling back stock reservations)
 * with a customer-facing message.
 */
class OrderPlacementException extends \RuntimeException
{
}
