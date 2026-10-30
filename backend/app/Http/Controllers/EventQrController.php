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
     */
    public function resolve(string $code): RedirectResponse
    {
        $qr = EventQrCode::where('public_code', strtoupper($code))->first();
        $event = $qr ? Event::withoutGlobalScopes()->whereNull('deleted_at')->find($qr->event_id) : null;

        abort_if(!$event || $event->status !== 'published', 404);

        return redirect()->route('events.show', $event->id);
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
        ]);
    }

    /** Preview (inline) or download of the host's artwork: svg, png (print resolution) or poster (svg). */
    public function download(Request $request, string $id, string $format): Response
    {
        $event = $this->events->findEvent($request->user(), $id);
        $qr = $this->qr->ensureFor($event);
        abort_unless($qr->isReady(), 409, 'The QR artwork is not ready. Try generating it again.');

        $base = Str::slug((string) $event->title) ?: 'event';
        [$body, $type, $name] = match ($format) {
            'svg' => [$this->qr->svg($qr), 'image/svg+xml', "{$base}-qr.svg"],
            'png' => [$this->qr->png($qr, 24), 'image/png', "{$base}-qr.png"],
            'poster' => [$this->qr->poster($qr, $event), 'image/svg+xml', "{$base}-qr-poster.svg"],
            default => abort(404),
        };

        $disposition = $request->boolean('inline') ? 'inline' : 'attachment';

        return response($body, 200, [
            'Content-Type' => $type,
            'Content-Disposition' => "{$disposition}; filename=\"{$name}\"",
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'",
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
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
