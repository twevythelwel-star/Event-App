{{--
    The host's event photo gallery: add photos (with alt text and a focal point), see each one as guests will, change
    its details, or remove it. Photos an administrator hid stay listed here, marked, so the host knows why guests do
    not see them. Each form is separate from the main event form.
--}}
@php
    $gallery = $event->mediaItems()->where('media_type', 'gallery')->orderBy('sort_order')->get();
    $limit = 12;
@endphp
<section class="eb-panel card" style="margin: 0;" id="event-photos" aria-labelledby="event-photos-heading">
    <div class="eb-card-header card-header"><div>
        <h2 id="event-photos-heading" class="eb-card-title card-title">Event photos ({{ $gallery->count() }} of {{ $limit }})</h2>
        <p class="eb-card-subtitle card-subtitle">Real photos of this event, its venue or past editions. Guests see them on the event page. Your original files are kept; the site shows resized copies.</p>
    </div></div>

    @if($gallery->isNotEmpty())
        <ul class="eb-photo-manager">
            @foreach($gallery as $media)
                <li class="eb-photo-manager-item">
                    <div class="eb-photo-manager-frame">
                        <x-responsive-image :image="$media->responsiveImage()" :alt="$media->alt_text" sizes="220px" />
                        @if($media->isHidden())
                            <span class="eb-badge eb-badge-error eb-photo-manager-flag">Hidden by an administrator</span>
                        @endif
                    </div>
                    @if($media->isHidden() && $media->moderation_note)
                        <p class="eb-help-text" style="margin: 0;">Reason: {{ $media->moderation_note }}</p>
                    @endif
                    <form method="POST" action="{{ route('events.photos.update', [$event->id, $media->id]) }}" class="eb-stack" style="gap: var(--space-2);">
                        @csrf
                        @method('PATCH')
                        <label class="eb-label" for="photo-alt-{{ $media->id }}">Alt text</label>
                        <input id="photo-alt-{{ $media->id }}" name="alt_text" class="eb-input" value="{{ $media->alt_text }}" required minlength="2" maxlength="250">
                        <div class="eb-grid-2">
                            <label class="eb-help-text">Focus left–right <input type="range" name="position_x" min="0" max="100" value="{{ (int) $media->position_x }}" aria-label="Focus left to right, percent" data-photo-focus="x"></label>
                            <label class="eb-help-text">Focus top–bottom <input type="range" name="position_y" min="0" max="100" value="{{ (int) $media->position_y }}" aria-label="Focus top to bottom, percent" data-photo-focus="y"></label>
                        </div>
                        <div class="eb-cluster">
                            <button type="submit" class="eb-btn eb-btn-secondary eb-btn-sm">Save details</button>
                            <button type="submit" form="remove-photo-{{ $media->id }}" class="eb-btn eb-btn-ghost eb-btn-sm">Remove</button>
                        </div>
                    </form>
                    <form id="remove-photo-{{ $media->id }}" method="POST" action="{{ route('events.photos.destroy', [$event->id, $media->id]) }}"
                          onsubmit="return confirm('Remove this photo? The original and every resized copy are deleted.');" hidden>
                        @csrf
                        @method('DELETE')
                    </form>
                </li>
            @endforeach
        </ul>
    @else
        <x-empty-state title="No photos yet" description="Add photos of the venue, the food or past editions so guests know what to expect." illustration="events" />
    @endif

    @if($gallery->count() < $limit)
        <form method="POST" action="{{ route('events.photos.store', $event->id) }}" enctype="multipart/form-data" class="eb-stack eb-photo-upload" style="gap: var(--space-3);" data-photo-upload>
            @csrf
            <h3 class="eb-text-label" style="margin: 0;">Add a photo</h3>
            <div class="eb-grid-2" style="align-items: start;">
                <div class="eb-stack" style="gap: var(--space-2);">
                    <label for="gallery-photo" class="eb-label">Photo</label>
                    <input type="file" id="gallery-photo" name="photo" accept="image/jpeg,image/png,image/webp" class="eb-input" required>
                    @error('photo')<div class="eb-field-error" role="alert"><span>{{ $message }}</span></div>@enderror
                    <p class="eb-help-text" data-photo-check role="status" aria-live="polite">JPG, PNG or WebP, up to 5 MB, at least 200 × 200 pixels.</p>
                    <label for="gallery-alt" class="eb-label">Alt text</label>
                    <input type="text" id="gallery-alt" name="alt_text" class="eb-input" required minlength="2" maxlength="250" value="{{ old('alt_text') }}" placeholder="e.g. Guests at the long tables by the harbour">
                    @error('alt_text')<div class="eb-field-error" role="alert"><span>{{ $message }}</span></div>@enderror
                    <input type="hidden" name="position_x" value="50" data-photo-focus-x>
                    <input type="hidden" name="position_y" value="50" data-photo-focus-y>
                </div>
                <div>
                    <div class="eb-focal-frame" style="aspect-ratio: 4 / 3;" hidden data-photo-preview-frame>
                        <img alt="Preview of the photo to add" data-photo-preview style="cursor: crosshair;">
                    </div>
                    <p class="eb-help-text" hidden data-photo-preview-help>Click the photo to choose what stays in view when it is cropped.</p>
                </div>
            </div>
            <div><button type="submit" class="eb-btn eb-btn-primary" data-prevent-double-submit="true">Add photo</button></div>
        </form>
    @endif
</section>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Existing photos: the focus sliders move the photo in its frame as you drag.
        document.querySelectorAll('.eb-photo-manager-item').forEach(function (item) {
            const img = item.querySelector('.eb-photo-manager-frame img');
            const x = item.querySelector('[data-photo-focus="x"]');
            const y = item.querySelector('[data-photo-focus="y"]');
            const move = () => { if (img) img.style.objectPosition = x.value + '% ' + y.value + '%'; };
            x.addEventListener('input', move);
            y.addEventListener('input', move);
        });

        // A new photo: preview it, check its size, and click to set its focal point (the server checks again).
        const form = document.querySelector('[data-photo-upload]');
        if (!form) return;
        const file = form.querySelector('#gallery-photo');
        const frame = form.querySelector('[data-photo-preview-frame]');
        const preview = form.querySelector('[data-photo-preview]');
        const help = form.querySelector('[data-photo-preview-help]');
        const check = form.querySelector('[data-photo-check]');
        const fx = form.querySelector('[data-photo-focus-x]');
        const fy = form.querySelector('[data-photo-focus-y]');
        file.addEventListener('change', function () {
            const chosen = file.files && file.files[0];
            if (!chosen) return;
            if (!['image/jpeg', 'image/png', 'image/webp'].includes(chosen.type)) { check.textContent = 'That file is not a JPG, PNG or WebP image.'; file.value = ''; return; }
            if (chosen.size > 5 * 1024 * 1024) { check.textContent = 'That photo is larger than 5 MB.'; file.value = ''; return; }
            preview.onload = function () {
                const ok = preview.naturalWidth >= 200 && preview.naturalHeight >= 200 && preview.naturalWidth <= 4500 && preview.naturalHeight <= 4500;
                check.textContent = ok ? chosen.name + ' (' + preview.naturalWidth + ' × ' + preview.naturalHeight + ') is ready to add.'
                    : 'This photo is ' + preview.naturalWidth + ' × ' + preview.naturalHeight + ' pixels; it must be between 200 × 200 and 4500 × 4500.';
            };
            preview.src = URL.createObjectURL(chosen);
            frame.hidden = false;
            help.hidden = false;
        });
        preview.addEventListener('click', function (event) {
            const rect = preview.getBoundingClientRect();
            fx.value = String(Math.round(Math.max(0, Math.min(1, (event.clientX - rect.left) / rect.width)) * 100));
            fy.value = String(Math.round(Math.max(0, Math.min(1, (event.clientY - rect.top) / rect.height)) * 100));
            preview.style.objectPosition = fx.value + '% ' + fy.value + '%';
        });
    });
</script>
