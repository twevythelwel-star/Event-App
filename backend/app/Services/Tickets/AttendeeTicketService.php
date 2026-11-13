<?php

declare(strict_types=1);

namespace App\Services\Tickets;

use App\Models\AttendeeTicket;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Issues each attendee their own admission credential: an order for three tickets gets three attendee tickets,
 * each with its own QR code.
 *
 * Issuing happens only once an order exists. OrderPlacementService calls issueFor() inside the same transaction
 * that creates the order and its lines, so either the order and all its credentials exist, or neither does:
 * opening the checkout page or a failed order never creates a credential.
 *
 * issueFor() is idempotent. It locks the order row, creates only the seats that are missing, and the
 * unique(order_id, sequence) index stops any concurrent second issue, so a double click, a retried request or a
 * retry after a failure never adds a ticket.
 *
 * Credentials: "tkt_" + base64url(HMAC-SHA256(app key, "attendee-ticket|id|version|nonce")), 256 bits. Only its
 * SHA-256 hash is stored. The server can show the same credential on every visit without keeping it anywhere,
 * a database copy alone cannot produce one, and rotate() (a new nonce and version) makes every earlier copy,
 * screenshots included, stop working.
 */
class AttendeeTicketService
{
    public const PREFIX = 'tkt_';

    /** Whether a scanned code has the shape of an attendee credential. */
    public static function looksLikeCredential(string $code): bool
    {
        return preg_match('/^tkt_[A-Za-z0-9_-]{43}$/', trim($code)) === 1;
    }

    public static function hash(string $credential): string
    {
        return hash('sha256', trim($credential));
    }

    /**
     * The order's ticket lines, in a stable order, each with how many people it admits.
     *
     * @return list<array{item: OrderItem, quantity: int}>
     */
    public function seatsFor(Order $order): array
    {
        $items = OrderItem::where('order_id', $order->id)->orderBy('created_at')->orderBy('id')->get();

        return $items
            ->filter(fn (OrderItem $item) => self::isTicketLine($item))
            ->map(fn (OrderItem $item) => ['item' => $item, 'quantity' => max(0, (int) $item->quantity)])
            ->filter(fn (array $seat) => $seat['quantity'] > 0)
            ->values()
            ->all();
    }

    public static function isTicketLine(OrderItem $item): bool
    {
        return $item->ticket_tier_id !== null
            || $item->line_type === OrderItem::TICKET
            || $item->category === 'Tickets';
    }

    /**
     * Creates the attendee tickets the order should have and does not have yet; returns all of them.
     * Safe to call any number of times, inside or outside a transaction.
     *
     * @return Collection<int, AttendeeTicket>
     */
    public function issueFor(Order $order): Collection
    {
        $issue = function () use ($order): void {
            // Serialises issuing for this order: a second caller waits here, then finds the seats already made.
            Order::withoutGlobalScopes()->whereKey($order->id)->lockForUpdate()->first();

            $existing = AttendeeTicket::where('order_id', $order->id)->pluck('sequence')->all();
            $existing = array_flip($existing);

            $sequence = 0;
            foreach ($this->seatsFor($order) as $seat) {
                for ($i = 0; $i < $seat['quantity']; $i++) {
                    $sequence++;
                    if (isset($existing[$sequence])) {
                        continue;
                    }
                    $this->createSeat($order, $seat['item'], $sequence);
                }
            }
        };

        try {
            DB::transaction($issue);
        } catch (UniqueConstraintViolationException) {
            // Another request issued the same seat between our read and our insert: theirs stands.
        }

        return $this->ticketsFor($order);
    }

    /** @return Collection<int, AttendeeTicket> */
    public function ticketsFor(Order $order): Collection
    {
        return AttendeeTicket::where('order_id', $order->id)->orderBy('sequence')->get();
    }

    /** The credential the attendee's QR code encodes. Derived on demand; never stored. */
    public function credential(AttendeeTicket $ticket): string
    {
        $mac = hash_hmac('sha256', "attendee-ticket|{$ticket->id}|{$ticket->token_version}|{$ticket->credential_nonce}", $this->key(), true);

        return self::PREFIX . rtrim(strtr(base64_encode($mac), '+/', '-_'), '=');
    }

    /** The current attendee ticket a scanned credential belongs to, or null (unknown, replaced or for another event). */
    public function findByCredential(string $credential, ?string $eventId = null): ?AttendeeTicket
    {
        if (!self::looksLikeCredential($credential)) {
            return null;
        }

        return AttendeeTicket::where('token_hash', self::hash($credential))
            ->when($eventId !== null, fn ($q) => $q->where('event_id', $eventId))
            ->first();
    }

    /**
     * Replaces the ticket's credential: every earlier copy of its QR code stops working at once. Admitted
     * tickets keep theirs (the gate already rejects them, and their record of admission must not change).
     */
    public function rotate(AttendeeTicket $ticket): AttendeeTicket
    {
        if ($ticket->checked_in_at !== null) {
            return $ticket;
        }
        $nonce = bin2hex(random_bytes(32));
        $ticket->forceFill([
            'credential_nonce' => $nonce,
            'token_version' => (int) $ticket->token_version + 1,
            'token_rotated_at' => now(),
            // The look stays; the artwork is drawn again for the new credential.
            'qr_status' => AttendeeTicket::QR_PENDING,
        ]);
        $ticket->token_hash = self::hash($this->credential($ticket));
        $ticket->save();

        return $ticket;
    }

    /** Rotates every not-yet-admitted ticket on the order (QR replacement, ticket transfer). */
    public function rotateAllFor(Order $order): void
    {
        DB::transaction(function () use ($order) {
            AttendeeTicket::where('order_id', $order->id)->whereNull('checked_in_at')->lockForUpdate()->get()
                ->each(fn (AttendeeTicket $ticket) => $this->rotate($ticket));
        });
    }

    private function createSeat(Order $order, OrderItem $item, int $sequence): AttendeeTicket
    {
        $ticket = new AttendeeTicket([
            'order_id' => $order->id,
            'event_id' => $order->event_id,
            'organizer_id' => $order->organizer_id,
            'order_item_id' => $item->id,
            'ticket_tier_id' => $item->ticket_tier_id,
            'sequence' => $sequence,
            'ticket_number' => $this->ticketNumber($order, $sequence),
            'credential_nonce' => bin2hex(random_bytes(32)),
            'token_version' => 1,
            'qr_status' => AttendeeTicket::QR_PENDING,
        ]);
        $ticket->id = (string) Str::uuid();
        $ticket->token_hash = self::hash($this->credential($ticket));
        $ticket->save();

        return $ticket;
    }

    /** The order's ticket code with the seat number, e.g. SFF-7KQ2MX-02. Unique because ticket codes are. */
    private function ticketNumber(Order $order, int $sequence): string
    {
        $base = (string) ($order->ticket_code ?: strtoupper(substr(str_replace('-', '', (string) $order->id), 0, 10)));

        return mb_substr($base, 0, 33) . '-' . str_pad((string) $sequence, 2, '0', STR_PAD_LEFT);
    }

    private function key(): string
    {
        $key = (string) config('app.key');
        if (str_starts_with($key, 'base64:')) {
            $decoded = base64_decode(substr($key, 7), true);
            $key = $decoded === false ? $key : $decoded;
        }
        if ($key === '') {
            throw new \RuntimeException('APP_KEY is not set: attendee ticket credentials cannot be issued.');
        }

        return $key;
    }
}
