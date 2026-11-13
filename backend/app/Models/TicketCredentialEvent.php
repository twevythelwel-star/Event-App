<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** One step in a ticket credential's history (see the migration). Append-only. */
class TicketCredentialEvent extends Model
{
    use HasUuids;

    public const ISSUED = 'issued';
    public const REISSUED = 'reissued';
    public const REVOKED = 'revoked';
    public const CHARGEBACK = 'chargeback';

    protected $fillable = ['order_id', 'attendee_ticket_id', 'action', 'token_version', 'reason', 'actor_user_id', 'actor_name', 'occurred_at'];

    protected $casts = ['token_version' => 'integer', 'occurred_at' => 'datetime'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Ticket credential history is append-only.'));
        static::deleting(fn () => throw new \LogicException('Ticket credential history cannot be deleted.'));
    }

    /** Records a step; the actor is the signed-in user who did it, or null for the system. */
    public static function record(string $orderId, ?AttendeeTicket $ticket, string $action, ?User $actor, ?string $reason): self
    {
        return self::create([
            'order_id' => $orderId,
            'attendee_ticket_id' => $ticket?->id,
            'action' => $action,
            'token_version' => $ticket?->token_version,
            'reason' => $reason !== null ? mb_substr(Order::redactTokenForLog($reason), 0, 255) : null,
            'actor_user_id' => $actor?->id,
            'actor_name' => $actor ? mb_substr((string) $actor->name, 0, 160) : 'system',
            'occurred_at' => now(),
        ]);
    }
}
