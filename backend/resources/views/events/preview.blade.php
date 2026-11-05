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
        background: linear-gradient(135deg, #F59E0B 0%, #D97706 100%);
        color: #FFFFFF;
        padding: 12px 24px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        z-index: 10000;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
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
        background: rgba(255, 255, 255, 0.2);
        border-radius: 8px;
        padding: 4px;
        gap: 4px;
    }

    .preview-banner .preview-device-btn {
        background: transparent;
        border: none;
        color: #FFFFFF;
        padding: 6px 12px;
        border-radius: 6px;
        font-size: 12px;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .preview-banner .preview-device-btn.active {
        background: #FFFFFF;
        color: #D97706;
    }

    .preview-banner .preview-device-btn:hover:not(.active) {
        background: rgba(255, 255, 255, 0.3);
    }

    .preview-banner .preview-close-btn {
        background: rgba(255, 255, 255, 0.2);
        border: none;
        color: #FFFFFF;
        padding: 8px 16px;
        border-radius: 6px;
        font-size: 13px;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .preview-banner .preview-close-btn:hover {
        background: rgba(255, 255, 255, 0.3);
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
        border-left: 1px solid rgba(255, 255, 255, 0.1);
        border-right: 1px solid rgba(255, 255, 255, 0.1);
    }

    /* Mobile Preview */
    .preview-mobile {
        width: 375px;
        margin: 0 auto;
        border-left: 1px solid rgba(255, 255, 255, 0.1);
        border-right: 1px solid rgba(255, 255, 255, 0.1);
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
        background: rgba(245, 158, 11, 0.95);
        color: #FFFFFF;
        padding: 6px 14px;
        border-radius: 9999px;
        font-size: 11px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.05em;
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
        <span class="preview-icon">👁️</span>
        <span>Preview Mode — This is how your event will appear to the public</span>
    </div>
    <div class="preview-actions">
        <div class="preview-device-toggle">
            <button type="button" class="preview-device-btn active" data-device="desktop" onclick="setPreviewDevice('desktop')">
                <span>🖥️</span> Desktop
            </button>
            <button type="button" class="preview-device-btn" data-device="tablet" onclick="setPreviewDevice('tablet')">
                <span>📱</span> Tablet
            </button>
            <button type="button" class="preview-device-btn" data-device="mobile" onclick="setPreviewDevice('mobile')">
                <span>📲</span> Mobile
            </button>
        </div>
        <a href="{{ route('events.edit', $event->id) }}" class="preview-close-btn">
            <span>✏️</span> Edit Event
        </a>
        <a href="{{ route('events.publish', $event->id) }}" class="preview-close-btn">
            <span>🚀</span> Publish
        </a>
    </div>
</div>

<!-- Preview Container -->
<div class="preview-container preview-mode" id="preview-container">
    <!-- Preview Badge on Hero -->
    <div class="preview-badge">PREVIEW</div>

    <!-- Override the back button to return to edit -->
    <script>
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

<script>
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
