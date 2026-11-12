<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventQrCode;
use App\Services\EventManagementService;
use App\Services\EventQr\EventQrService;
use App\Services\EventQr\QrDesignCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Event QR codes: the public short link they encode, and the host's preview, download and redesign actions.
 * Host actions only reach events the user may manage (EventManagementService::findEvent); any other event
 * behaves as not found.
 */
class EventQrController extends Controller
{
    public function __construct(
        protected EventQrService $qr,
        protected EventManagementService $events
    ) {
    }

    /**
     * Where a scanned event QR code lands. Only published events resolve; a draft, unpublished or cancelled
     * event does not become reachable because a code for it exists. The code never admits anyone.
     *
     * IMPORTANT: This is a PROMOTIONAL QR code for sharing. It is NOT an admission ticket. Admission uses
     * each ticket's own secure token (orders.secure_ticket_token). Never use this QR code to authenticate
     * admission or fulfill food orders.
     */
    public function resolve(string $code): Response|RedirectResponse
    {
        $qr = EventQrCode::where('public_code', strtoupper($code))->first();
        $event = $qr ? Event::withoutGlobalScopes()->whereNull('deleted_at')->find($qr->event_id) : null;

        abort_if(!$event, 404);

        // A draft was never public: keep it indistinguishable from a missing code.
        abort_if($event->status === 'draft', 404);

        if ($event->status === 'published' && $event->visibility !== 'private') {
            return redirect()->route('events.show', $event->id);
        }

        // Cancelled, unpublished or private events get an honest status page — the printed
        // code keeps working, it just tells the truth about the event's current state.
        // Titles are shown only for events that were once public (cancelled/unpublished);
        // private events reveal nothing beyond their status.
        $statusPage = match (true) {
            $event->status === 'cancelled' => ['title' => 'This event was cancelled', 'message' => 'The organizer has cancelled this event. If you purchased tickets or placed food orders, watch your email and My tickets for refund and cancellation updates.', 'badge' => 'Cancelled'],
            $event->status === 'unpublished' => ['title' => 'This event is no longer listed', 'message' => 'This event is not currently open for discovery. It may return later — check back or browse other events.', 'badge' => 'Unavailable'],
            default => ['title' => 'This event is not available', 'message' => 'This event is private or not open to the public. If you were invited, use the link from your invitation.', 'badge' => 'Private'],
        };

        return response()->view('event-qr.status', [
            'eventTitle' => in_array($event->status, ['cancelled', 'unpublished'], true) ? $event->title : null,
            'badge' => $statusPage['badge'],
            'title' => $statusPage['title'],
            'message' => $statusPage['message'],
        ], 200);
    }

    /** Public artwork for a published event, e.g. for sharing. */
    public function publicArtwork(string $code): Response
    {
        $qr = EventQrCode::where('public_code', strtoupper($code))->first();
        $event = $qr ? Event::withoutGlobalScopes()->whereNull('deleted_at')->find($qr->event_id) : null;
        abort_if(!$event || $event->status !== 'published' || !$qr->isReady(), 404);

        return response($this->qr->svg($qr), 200, [
            'Content-Type' => 'image/svg+xml',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'",
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'public, max-age=300',
            'X-EventBite-QR-Type' => 'promotional', // Distinguish from ticket QR codes
        ]);
    }

    /** Preview (inline) or download of the host's artwork: svg, png (print resolution) or poster (svg). */
    public function download(Request $request, string $id, string $format): Response
    {
        $event = $this->events->findEvent($request->user(), $id);
        $qr = $this->qr->ensureFor($event);
        abort_unless($qr->isReady(), 409, 'The QR artwork is not ready. Try generating it again.');

        $base = Str::slug((string) $event->title) ?: 'event';
        $status = $event->status === 'published' ? '' : '-draft';
        [$body, $type, $name] = match ($format) {
            'svg' => [$this->qr->svg($qr), 'image/svg+xml', "{$base}{$status}-qr.svg"],
            'png' => [$this->qr->png($qr, 24), 'image/png', "{$base}{$status}-qr.png"],
            'poster' => [$this->qr->poster($qr, $event), 'image/svg+xml', "{$base}{$status}-qr-poster.svg"],
            default => abort(404),
        };

        $disposition = $request->boolean('inline') ? 'inline' : 'attachment';

        return response($body, 200, [
            'Content-Type' => $type,
            'Content-Disposition' => "{$disposition}; filename=\"{$name}\"",
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'",
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
            'X-EventBite-QR-Type' => 'promotional', // Distinguish from ticket QR codes
            'X-Event-Publication-Status' => $event->status, // Indicate draft/published state
        ]);
    }

    /** Preview QR artwork for draft events (authorized hosts only). */
    public function previewArtwork(Request $request, string $id): Response
    {
        $event = $this->events->findEvent($request->user(), $id);
        $qr = $this->qr->ensureFor($event);
        abort_unless($qr->isReady(), 409, 'The QR artwork is not ready. Try generating it again.');

        return response($this->qr->svg($qr), 200, [
            'Content-Type' => 'image/svg+xml',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'",
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
            'X-EventBite-QR-Type' => 'promotional',
            'X-Event-Publication-Status' => $event->status,
        ]);
    }

    /** A new look from an approved preset. The code the artwork encodes does not change. */
    public function redesign(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate([
            'preset' => ['required', 'string', 'in:random,' . implode(',', array_keys(QrDesignCatalog::PRESETS))],
        ]);

        $event = $this->events->findEvent($request->user(), $id);
        $qr = $this->qr->redesign($event, $validated['preset']);

        $message = match (true) {
            !$qr->isReady() => 'The QR design could not be generated. The previous artwork is unchanged; please try again.',
            isset($qr->design['fallback_reason']) => 'That style could not guarantee a reliable scan, so a classic design was used instead (' . $qr->design['fallback_reason'] . ').',
            default => 'New QR design: ' . QrDesignCatalog::label((string) $qr->preset) . '. The code still points to the same event page.',
        };

        return back()->with('status', $message);
    }

    /** Retry artwork generation after a failure. Keeps the same code and design. */
    public function retry(Request $request, string $id): RedirectResponse
    {
        $event = $this->events->findEvent($request->user(), $id);
        $qr = $this->qr->ensureFor($event);

        return back()->with('status', $qr->isReady() ? 'Event QR code is ready.' : 'The QR artwork still could not be generated. Please try again later.');
    }
}
