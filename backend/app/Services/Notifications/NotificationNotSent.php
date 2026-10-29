<?php

namespace App\Services\Notifications;

/**
 * A notification that cannot be sent and should not be retried (no provider configured, invalid
 * recipient, provider rejected it permanently). Temporary failures throw ordinary exceptions instead,
 * so the queue retries them.
 */
class NotificationNotSent extends \RuntimeException
{
}
