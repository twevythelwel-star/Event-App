{{--
    Dialog shell. Content goes in the slot. Open it with any element carrying
    data-modal-open="{id}"; close with the ✕ button, Escape, or data-modal-close.
    Props: id, title, open (start open, e.g. to show validation errors after a failed submit).
    A dialog is presentation only: anything it confirms is still checked on the server.
--}}
@props([
    'id',
    'title',
    'open' => false,
])

<div id="{{ $id }}" class="eb-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="{{ $id }}-title"
     data-modal @unless($open) style="display: none;" @endunless>
    <div {{ $attributes->merge(['class' => 'eb-modal']) }}>
        <div class="eb-modal-head">
            <h2 id="{{ $id }}-title" class="eb-h3" style="margin: 0;">{{ $title }}</h2>
            <button type="button" class="eb-modal-close" data-modal-close aria-label="Close">✕</button>
        </div>
        {{ $slot }}
    </div>
</div>

@once
    <script>
        (function () {
            var lastTrigger = null;
            function show(modal, trigger) {
                lastTrigger = trigger || null;
                modal.style.display = '';
                var focusable = modal.querySelector('input, select, textarea, button:not([data-modal-close]), a[href]');
                (focusable || modal.querySelector('[data-modal-close]')).focus();
            }
            function hide(modal) {
                modal.style.display = 'none';
                if (lastTrigger) { lastTrigger.focus(); }
            }
            document.addEventListener('click', function (e) {
                var opener = e.target.closest('[data-modal-open]');
                if (opener) {
                    var modal = document.getElementById(opener.dataset.modalOpen);
                    if (modal) { e.preventDefault(); show(modal, opener); }
                    return;
                }
                var closer = e.target.closest('[data-modal-close]');
                if (closer) { hide(closer.closest('[data-modal]')); return; }
                if (e.target.matches('[data-modal]')) { hide(e.target); }
            });
            document.addEventListener('keydown', function (e) {
                if (e.key !== 'Escape') { return; }
                document.querySelectorAll('[data-modal]').forEach(function (m) {
                    if (m.style.display !== 'none') { hide(m); }
                });
            });
        })();
    </script>
@endonce
