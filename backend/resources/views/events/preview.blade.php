@extends('events.show')

@section('title', 'Preview: ' . ($event->title ?? 'Event') . ' — EventBite')
@section('page-title', 'Preview: ' . ($event->title ?? 'Event'))

@push('styles')
<style>
    /* Preview Mode Banner */
    .preview-banner {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        background: linear-gradient(135deg, var(--color-warning) 0%, var(--color-warning) 100%);
        color: var(--color-ink);
        padding: 12px 24px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        z-index: 10000;
        box-shadow: 0 4px 12px color-mix(in srgb, var(--color-shade) 30%, transparent);
        font-weight: 700;
        font-size: 14px;
    }

    .preview-banner .preview-info {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .preview-banner .preview-icon {
        font-size: 20px;
    }

    .preview-banner .preview-actions {
        display: flex;
        gap: 12px;
        align-items: center;
    }

    .preview-banner .preview-device-toggle {
        display: flex;
        background: color-mix(in srgb, var(--color-ink) 20%, transparent);
        border-radius: 8px;
        padding: 4px;
        gap: 4px;
    }

    .preview-banner .preview-device-btn {
        background: transparent;
        border: none;
        color: var(--color-ink);
        padding: 6px 12px;
        border-radius: 6px;
        font-size: 12px;
        font-weight: 700;
        cursor: pointer;
        transition: color 0.2s ease, background-color 0.2s ease, border-color 0.2s ease, transform 0.2s ease, opacity 0.2s ease;
    }

    .preview-banner .preview-device-btn.active {
        background: var(--color-ink);
        color: var(--color-warning);
    }

    .preview-banner .preview-device-btn:hover:not(.active) {
        background: color-mix(in srgb, var(--color-ink) 30%, transparent);
    }

    .preview-banner .preview-close-btn {
        background: color-mix(in srgb, var(--color-ink) 20%, transparent);
        border: none;
        color: var(--color-ink);
        padding: 8px 16px;
        border-radius: 6px;
        font-size: 13px;
        font-weight: 700;
        cursor: pointer;
        transition: color 0.2s ease, background-color 0.2s ease, border-color 0.2s ease, transform 0.2s ease, opacity 0.2s ease;
    }

    .preview-banner .preview-close-btn:hover {
        background: color-mix(in srgb, var(--color-ink) 30%, transparent);
    }

    /* Preview Mode Container */
    .preview-container {
        margin-top: 60px; /* Space for banner */
    }

    /* Desktop Preview */
    .preview-desktop {
        width: 100%;
    }

    /* Tablet Preview */
    .preview-tablet {
        width: 768px;
        margin: 0 auto;
        border-left: 1px solid color-mix(in srgb, var(--color-ink) 10%, transparent);
        border-right: 1px solid color-mix(in srgb, var(--color-ink) 10%, transparent);
    }

    /* Mobile Preview */
    .preview-mobile {
        width: 375px;
        margin: 0 auto;
        border-left: 1px solid color-mix(in srgb, var(--color-ink) 10%, transparent);
        border-right: 1px solid color-mix(in srgb, var(--color-ink) 10%, transparent);
    }

    /* Disable purchase buttons in preview */
    .preview-mode .btn-gradient-purchase,
    .preview-mode .eb-buy-bar .eb-btn {
        pointer-events: none;
        opacity: 0.6;
        cursor: not-allowed;
    }

    .preview-mode .btn-gradient-purchase::after,
    .preview-mode .eb-buy-bar .eb-btn::after {
        content: ' — Preview Mode';
        font-size: 11px;
        font-weight: 600;
    }

    /* Adjust hero for preview modes */
    .preview-tablet .detail-hero,
    .preview-mobile .detail-hero {
        height: 280px;
    }

    .preview-mobile .detail-main-layout {
        grid-template-columns: minmax(0, 1fr);
    }

    .preview-mobile .detail-main-layout > div:last-child {
        display: none; /* Hide sticky panel on mobile preview */
    }

    /* Preview badge on hero */
    .preview-badge {
        position: absolute;
        top: 70px;
        right: 24px;
        background: color-mix(in srgb, var(--color-warning) 95%, transparent);
        color: var(--color-ink);
        padding: 6px 14px;
        border-radius: 9999px;
        font-size: 11px;
        font-weight: 800;
        z-index: 15;
        backdrop-filter: blur(8px);
    }

    /* Adjust body padding for banner */
    body {
        padding-top: 0 !important;
    }
</style>
@endpush

@section('content')
<!-- Preview Mode Banner -->
<div class="preview-banner">
    <div class="preview-info">
        <span class="preview-icon" aria-hidden="true"><x-icon name="search" size="sm" /></span>
        <span>Preview Mode — This is how your event will appear to the public</span>
    </div>
    <div class="preview-actions">
        <div class="preview-device-toggle">
            <button type="button" class="preview-device-btn active" data-device="desktop" data-action-click="setPreviewDevice" data-args='["desktop"]'>
                Desktop
            </button>
            <button type="button" class="preview-device-btn" data-device="tablet" data-action-click="setPreviewDevice" data-args='["tablet"]'>
                Tablet
            </button>
            <button type="button" class="preview-device-btn" data-device="mobile" data-action-click="setPreviewDevice" data-args='["mobile"]'>
                Mobile
            </button>
        </div>
        <a href="{{ route('events.edit', $event->id) }}" class="preview-close-btn">
            Edit Event
        </a>
        <a href="{{ route('events.publish', $event->id) }}" class="preview-close-btn">
            Publish
        </a>
    </div>
</div>

<!-- Preview Container -->
<div class="preview-container preview-mode" id="preview-container">
    <!-- Preview Badge on Hero -->
    <div class="preview-badge">PREVIEW</div>

    <!-- Override the back button to return to edit -->
    <script @cspNonce>
        document.addEventListener('DOMContentLoaded', function() {
            const backBtn = document.getElementById('btn-back-to-discovery');
            if (backBtn) {
                backBtn.href = '{{ route('events.edit', $event->id) }}';
                backBtn.innerHTML = '<span>&larr;</span><span>Back to Edit</span>';
            }
        });
    </script>

    @parent
</div>

<script @cspNonce>
    function setPreviewDevice(device) {
        const container = document.getElementById('preview-container');
        const buttons = document.querySelectorAll('.preview-device-btn');

        // Update button states
        buttons.forEach(btn => btn.classList.remove('active'));
        document.querySelector(`[data-device="${device}"]`).classList.add('active');

        // Update container class
        container.classList.remove('preview-desktop', 'preview-tablet', 'preview-mobile');
        container.classList.add('preview-' + device);

        // Update layout for mobile
        const mainLayout = document.querySelector('.detail-main-layout');
        if (mainLayout) {
            if (device === 'mobile') {
                mainLayout.style.gridTemplateColumns = 'minmax(0, 1fr)';
            } else {
                mainLayout.style.gridTemplateColumns = 'minmax(0, 1fr) 380px';
            }
        }

        // Update hero height
        const hero = document.querySelector('.detail-hero');
        if (hero) {
            hero.style.height = device === 'mobile' ? '280px' : '380px';
        }
    }

    // Disable all purchase-related actions in preview
    document.addEventListener('DOMContentLoaded', function() {
        // Disable tab switching that might trigger purchase flows
        const purchaseBtns = document.querySelectorAll('.btn-gradient-purchase, .eb-buy-bar .eb-btn');
        purchaseBtns.forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                alert('Purchase is disabled in preview mode. Publish your event to enable purchases.');
            });
        });

        // Disable share button
        const shareBtn = document.querySelector('[onclick="shareCurrentEvent()"]');
        if (shareBtn) {
            shareBtn.onclick = function() {
                alert('Share is disabled in preview mode.');
            };
        }
    });
</script>
@endsection
