{{--
    A sample ticket in one of the host's ticket designs, for the ticket designer. It shows no real QR code: the
    square is a labelled placeholder, because only an issued ticket has one.
    Props: design, accent, background, mode ('phone' | 'print'), name, price, event, when.
--}}
@props([
    'design' => 'premium_minimal',
    'accent' => 'indigo',
    'background' => 'solid',
    'mode' => 'phone',
    'name' => 'General Admission',
    'price' => '',
    'event' => 'Your event',
    'when' => '',
])
<div {{ $attributes->merge(['class' => 'eb-ticket-skin']) }} data-design="{{ $design }}" data-accent="{{ $accent }}" data-bg="{{ $background }}" data-mode="{{ $mode }}" role="img"
     aria-label="Sample {{ str_replace('_', ' ', $design) }} ticket">
    <div class="eb-ticket-skin-main">
        <span class="eb-ticket-skin-kicker">EventBite · Admission</span>
        <strong class="eb-ticket-skin-event" data-mock-event>{{ $event }}</strong>
        <span class="eb-ticket-skin-when" data-mock-when>{{ $when }}</span>
        <div class="eb-ticket-skin-tier">
            <span data-mock-name>{{ $name }}</span>
            <span class="eb-ticket-skin-price" data-mock-price>{{ $price }}</span>
        </div>
    </div>
    <div class="eb-ticket-skin-tear" aria-hidden="true"></div>
    <div class="eb-ticket-skin-stub">
        <span class="eb-ticket-skin-qr" aria-hidden="true"></span>
        <span class="eb-ticket-skin-note">Sample. The QR code appears on each guest's ticket.</span>
    </div>
</div>
