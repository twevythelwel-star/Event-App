{{-- Event QR: the event's promotional code. It opens the public event page and never admits anyone. --}}
@php
    $presets = \App\Services\EventQr\QrDesignCatalog::PRESETS;
    $design = is_array($qr->design) ? $qr->design : [];
    $ready = $qr->isReady();
    $presetLabel = \App\Services\EventQr\QrDesignCatalog::label((string) $qr->preset);
@endphp
<section id="event-qr" class="card eb-event-qr" aria-labelledby="event-qr-heading">
    <div class="card-header">
        <h2 id="event-qr-heading" class="card-title">Event QR code</h2>
        <p class="card-subtitle">For flyers, posters and social posts. Scanning it opens this event's public page. It is not a ticket and does not admit anyone; each ticket has its own code.</p>
    </div>

    @if (session('status') && str_contains((string) session('status'), 'QR'))
        <p role="status" class="eb-event-qr-flash">{{ session('status') }}</p>
    @endif

    <div class="eb-event-qr-grid">
        <div class="eb-event-qr-preview">
            @if ($ready)
                <img src="{{ route('events.qr.download', [$event->id, 'svg']) }}?inline=1&amp;v={{ $qr->design_version }}"
                     alt="Event QR code for {{ $event->title }}, {{ $presetLabel }} design" width="260" height="260">
            @else
                <p>The QR artwork could not be generated{{ $qr->failure_reason ? ': ' . $qr->failure_reason : '' }}.</p>
            @endif
        </div>

        <div class="eb-event-qr-details">
            <dl class="eb-event-qr-facts">
                <div><dt>Event</dt><dd>{{ $event->title }}</dd></div>
                <div>
                    <dt>Event status</dt>
                    <dd>{{ ucfirst((string) $event->status) }}@if ($event->status !== 'published') <span class="eb-event-qr-note">The link works once the event is published.</span>@endif</dd>
                </div>
                <div>
                    <dt>Design</dt>
                    <dd>{{ $presetLabel }} <span class="eb-event-qr-muted">(version {{ $qr->design_version }})</span>
                        @if (isset($design['fallback_reason']))<span class="eb-event-qr-note">Classic design used: {{ $design['fallback_reason'] }}.</span>@endif
                    </dd>
                </div>
                <div>
                    <dt>Status</dt>
                    <dd><span class="badge {{ $ready ? 'badge-verified' : 'badge-failed' }}">{{ $ready ? 'Ready — checked with a QR reader' : 'Not generated' }}</span></dd>
                </div>
            </dl>

            <div class="form-group">
                <label for="qr-destination">Destination</label>
                <input id="qr-destination" class="eb-input" type="text" readonly value="{{ $qrDestination }}">
            </div>

            @if ($ready)
                <div class="eb-event-qr-actions" role="group" aria-label="Download the event QR code">
                    <a class="btn btn-secondary btn-sm" href="{{ route('events.qr.download', [$event->id, 'svg']) }}">Download SVG</a>
                    <a class="btn btn-secondary btn-sm" href="{{ route('events.qr.download', [$event->id, 'png']) }}">Download PNG (print size)</a>
                    <a class="btn btn-secondary btn-sm" href="{{ route('events.qr.download', [$event->id, 'poster']) }}">Download poster (SVG)</a>
                </div>
            @else
                <form action="{{ route('events.qr.retry', $event->id) }}" method="POST">
                    @csrf
                    <button type="submit" class="btn btn-primary btn-sm">Try generating again</button>
                </form>
            @endif

            <form action="{{ route('events.qr.redesign', $event->id) }}" method="POST" class="eb-event-qr-redesign">
                @csrf
                <div class="form-group">
                    <label for="qr-preset">New design</label>
                    <select id="qr-preset" name="preset" class="eb-select">
                        <option value="random">Surprise me (random)</option>
                        @foreach ($presets as $value => $label)
                            @continue($value === 'custom_theme' && !$event->flyer_path)
                            <option value="{{ $value }}">{{ $label }}{{ $value === 'custom_theme' ? ' (colours from your flyer)' : '' }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="btn btn-outline btn-sm">Generate new design</button>
                <p class="eb-event-qr-muted">A new design changes only the look. The code still opens the same event page, so printed flyers keep working.</p>
            </form>
        </div>
    </div>
</section>
