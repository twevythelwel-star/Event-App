<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Order;
use App\Services\EventQr\TicketQrService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Draws an order's attendee QR artwork in the background once the order is committed, so the ticket page opens
 * with its codes ready. It only renders: tickets and credentials already exist, and TicketQrService::prepare()
 * never creates duplicates, so a retried or repeated job is harmless. If the queue is down, the ticket page draws
 * the codes itself on first view.
 */
class RenderAttendeeTicketQrCodes implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var list<int> seconds between attempts */
    public array $backoff = [10, 60];

    public function __construct(public readonly string $orderId)
    {
        $this->afterCommit();
    }

    public function uniqueId(): string
    {
        return $this->orderId;
    }

    public function handle(TicketQrService $qr): void
    {
        $order = Order::withoutGlobalScopes()->find($this->orderId);
        if ($order) {
            $qr->prepare($order);
        }
    }

    /** Queue the render when pre-rendering is on (config tickets.prerender_qr). */
    public static function dispatchFor(Order $order): void
    {
        if (config('tickets.prerender_qr', true)) {
            self::dispatch((string) $order->id);
        }
    }
}
