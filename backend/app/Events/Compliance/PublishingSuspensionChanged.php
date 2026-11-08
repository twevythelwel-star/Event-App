<?php

declare(strict_types=1);

namespace App\Events\Compliance;

use App\Models\Event;
use App\Models\User;

/**
 * An event, or a host's publishing privileges, was suspended or reinstated on compliance grounds. Audited; the
 * hosts concerned are told.
 */
final class PublishingSuspensionChanged extends ComplianceActionRecorded
{
    public function __construct(
        public readonly bool $suspended,
        public readonly string $reason,
        User $actor,
        public readonly ?Event $event = null,
        public readonly ?User $host = null,
        string $details = '',
    ) {
        $subject = $event ? 'event' : 'host';
        parent::__construct(
            action: 'compliance_' . $subject . '_' . ($suspended ? 'suspended' : 'reinstated'),
            targetEntity: $event ? 'events' : 'users',
            targetId: (string) ($event?->id ?? $host?->id),
            details: $details,
            actor: $actor,
            organizerId: $event?->organizer_id,
            eventId: $event?->id,
        );
    }
}
