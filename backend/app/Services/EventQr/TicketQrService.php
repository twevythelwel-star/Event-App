<?php

declare(strict_types=1);

namespace App\Services\EventQr;

use App\Models\AttendeeTicket;
use App\Models\Event;
use App\Models\Order;
use App\Services\Tickets\AttendeeTicketService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Draws each attendee ticket's QR code in its event's design, after checking it reads back.
 *
 * Rendering is separate from issuing: the ticket and its credential already exist (AttendeeTicketService), so a
 * failed render only leaves qr_status "failed" with the reason, and render() can simply run again. Nothing here
 * creates tickets or credentials.
 *
 * - Each event design is certified once (decoded large, small and after JPEG compression, with a credential of
 *   the real length) and the result cached by the design's fingerprint.
 * - Each ticket's own artwork is then decoded once more with its real credential before it is marked ready.
 * - A design that does not read back is replaced by the conventional design; if even that fails, the ticket is
 *   marked failed and the ticket page offers a retry.
 */
class TicketQrService
{
    public function __construct(
        private readonly TicketQrDesignSystem $designs,
        private readonly QrArtworkRenderer $renderer,
        private readonly AttendeeTicketService $tickets,
    ) {
    }

    /**
     * Issues any missing attendee tickets for the order, then renders those not yet ready (or whose event design
     * changed). Safe to call on every visit to the ticket page.
     *
     * @return Collection<int, AttendeeTicket>
     */
    public function prepare(Order $order): Collection
    {
        $tickets = $this->tickets->issueFor($order);
        $event = $order->relationLoaded('event') && $order->event
            ? $order->event
            : Event::withoutGlobalScopes()->find($order->event_id);
        if (!$event) {
            return $tickets;
        }

        $design = $this->designs->design($event);
        $fingerprint = TicketQrDesignSystem::fingerprint($design);

        return $tickets->map(function (AttendeeTicket $ticket) use ($event, $design, $fingerprint) {
            $current = $ticket->qr_status === AttendeeTicket::QR_READY && $ticket->qr_design_hash === $fingerprint;

            return $current || $ticket->checked_in_at !== null && $ticket->qr_status === AttendeeTicket::QR_READY
                ? $ticket
                : $this->render($ticket, $event, $design);
        });
    }

    /**
     * Renders (or re-renders) one ticket's artwork. Never throws: failures are recorded on the ticket.
     *
     * @param array<string, mixed>|null $design the event's design, when the caller already has it
     */
    public function render(AttendeeTicket $ticket, Event $event, ?array $design = null): AttendeeTicket
    {
        $design ??= $this->designs->design($event);
        $credential = $this->tickets->credential($ticket);
        $ticket->qr_attempts = (int) $ticket->qr_attempts + 1;

        try {
            $chosen = null;
            $candidates = [$design, TicketQrDesignSystem::fallback('the event design did not decode reliably', (string) ($design['preset'] ?? ''))];
            foreach ($candidates as $candidate) {
                if ($this->certified($candidate) && $this->designs->decodesOnce($credential, $candidate)) {
                    $chosen = $candidate;
                    break;
                }
            }
            if ($chosen === null) {
                throw new \RuntimeException('Neither the event design nor the conventional design decoded.');
            }

            $ticket->forceFill([
                'qr_status' => AttendeeTicket::QR_READY,
                'qr_design' => $chosen,
                // The event's fingerprint, even when the fallback was drawn, so an unchanged event is not retried each visit.
                'qr_design_hash' => TicketQrDesignSystem::fingerprint($design),
                'qr_failure_reason' => null,
                'qr_generated_at' => now(),
            ])->save();
        } catch (\Throwable $e) {
            Log::warning('Ticket QR artwork generation failed', ['attendee_ticket_id' => $ticket->id, 'error' => $e->getMessage()]);
            $ticket->forceFill([
                'qr_status' => AttendeeTicket::QR_FAILED,
                'qr_failure_reason' => mb_substr($e->getMessage(), 0, 255),
            ])->save();
        }

        return $ticket;
    }

    /** Inline SVG for the ticket page, or null when the ticket has no ready artwork. */
    public function svg(AttendeeTicket $ticket, string $label): ?string
    {
        if ($ticket->qr_status !== AttendeeTicket::QR_READY || !is_array($ticket->qr_design)) {
            return null;
        }
        $design = QrDesignCatalog::safe($ticket->qr_design);
        $svg = $this->renderer->svg($this->tickets->credential($ticket), $design);
        $svg = (string) preg_replace('/^<\?xml[^>]*\?>\s*/', '', $svg);

        // Accessible name; the size comes from the stylesheet so the code scales cleanly.
        return (string) preg_replace('/^<svg /', '<svg role="img" aria-label="' . e($label) . '" class="eb-ticket-qr-art" ', $svg, 1);
    }

    /** A preview of a design with a sample credential (host and admin screens; never a real credential). @param array<string, mixed> $design */
    public function previewSvg(array $design): string
    {
        $svg = $this->renderer->svg(TicketQrDesignSystem::SAMPLE_CREDENTIAL, QrDesignCatalog::safe($design));

        return (string) preg_replace('/^<\?xml[^>]*\?>\s*/', '', $svg);
    }

    /** @param array<string, mixed> $design */
    private function certified(array $design): bool
    {
        if (QrDesignCatalog::problemWith($design) !== null) {
            return false;
        }
        $key = 'ticket-qr-certified:' . TicketQrDesignSystem::fingerprint($design);
        if (Cache::get($key) === true) {
            return true;
        }
        $ok = $this->designs->decodesReliably(TicketQrDesignSystem::SAMPLE_CREDENTIAL, $design);
        // Only a pass is remembered: a failure may be transient (and the render is retried), so it is checked again.
        if ($ok) {
            Cache::put($key, true, now()->addDays(30));
        }

        return $ok;
    }
}
