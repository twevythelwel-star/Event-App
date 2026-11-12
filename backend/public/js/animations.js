/**
 * EventBite Motion & Interactive Graphics Utilities
 * Laravel-compatible progressive enhancement (Vanilla JS, zero framework overhead)
 *
 * Guarantees:
 * - Respects prefers-reduced-motion (including live OS setting changes)
 * - Every form, link, drawer, and button works 100% if JS or CSS animations are disabled
 * - Dark-mode 3D Soft Realism toast notifications & flash alert dismissal
 */

const reducedMotionQuery = window.matchMedia
    ? window.matchMedia('(prefers-reduced-motion: reduce)')
    : { matches: false, addEventListener: null };

function prefersReducedMotion() {
    return Boolean(reducedMotionQuery.matches);
}

class EventBiteAnimations {
    /**
     * Trigger a one-shot animation class on an element
     */
    static animate(element, animationClass, duration = 360) {
        if (!element || prefersReducedMotion()) {
            return Promise.resolve();
        }

        return new Promise((resolve) => {
            element.classList.remove(animationClass);
            // Force reflow only when triggering a one-shot state feedback class
            void element.offsetWidth;
            element.classList.add(animationClass);

            window.setTimeout(() => {
                element.classList.remove(animationClass);
                resolve();
            }, duration);
        });
    }

    /**
     * Show a loading skeleton inside a container
     */
    static showSkeleton(container, type = 'card', count = 3) {
        if (!container) return;

        const safeCount = Math.max(1, Math.min(12, Number(count) || 1));
        const items = [];

        for (let i = 0; i < safeCount; i++) {
            if (type === 'ticket') {
                items.push(`
                    <div class="eb-skeleton-ticket">
                        <div class="eb-skeleton-ticket__main">
                            <div class="eb-skeleton eb-skeleton-kicker"></div>
                            <div class="eb-skeleton eb-skeleton-title"></div>
                            <div class="eb-skeleton eb-skeleton-line eb-skeleton-line--short"></div>
                        </div>
                        <div class="eb-skeleton eb-skeleton-ticket__qr"></div>
                    </div>
                `);
            } else if (type === 'metric') {
                items.push(`
                    <div class="eb-skeleton-metric">
                        <div class="eb-skeleton eb-skeleton-kicker"></div>
                        <div class="eb-skeleton eb-skeleton-metric__value"></div>
                        <div class="eb-skeleton eb-skeleton-line eb-skeleton-line--short"></div>
                    </div>
                `);
            } else if (type === 'text') {
                items.push(`<div class="eb-skeleton eb-skeleton-line"></div>`);
            } else {
                items.push(`
                    <div class="eb-skeleton-card">
                        <div class="eb-skeleton eb-skeleton-media"></div>
                        <div class="eb-skeleton-body">
                            <div class="eb-skeleton eb-skeleton-kicker"></div>
                            <div class="eb-skeleton eb-skeleton-title"></div>
                            <div class="eb-skeleton eb-skeleton-line"></div>
                        </div>
                    </div>
                `);
            }
        }

        container.innerHTML = `<div class="eb-skeleton-group" role="status" aria-busy="true" aria-label="Loading content">${items.join('')}</div>`;
    }

    /**
     * Replace skeleton with content
     */
    static hideSkeleton(container, content) {
        if (!container) return;
        container.innerHTML = content;
        if (!prefersReducedMotion()) {
            container.classList.add('fade-in');
        }
    }

    /**
     * Show or clear inline button loading state without breaking native form submit
     */
    static setButtonLoading(button, isLoading = true, loadingText = null) {
        if (!button) return;

        if (isLoading) {
            if (button.dataset.loading === 'true') return;
            button.dataset.loading = 'true';
            button.setAttribute('aria-busy', 'true');
            button.dataset.originalHtml = button.innerHTML;

            const label = loadingText || button.dataset.loadingText || button.textContent.trim() || 'Processing...';
            button.innerHTML = `<span class="eb-btn-spinner" aria-hidden="true"></span><span>${label}</span>`;
        } else {
            button.dataset.loading = 'false';
            button.removeAttribute('aria-busy');
            if (button.dataset.originalHtml) {
                button.innerHTML = button.dataset.originalHtml;
                delete button.dataset.originalHtml;
            }
        }
    }

    /**
     * Dark-mode 3D elevated toast notification
     */
    static showToast(message, type = 'info', duration = 3500) {
        if (!message) return;

        const palette = {
            success: {
                bg: 'rgba(6, 78, 59, 0.94)',
                border: 'rgba(16, 185, 129, 0.45)',
                text: '#ecfdf5',
                accent: '#34d399',
            },
            error: {
                bg: 'rgba(127, 29, 29, 0.94)',
                border: 'rgba(248, 113, 113, 0.45)',
                text: '#fef2f2',
                accent: '#f87171',
            },
            warning: {
                bg: 'rgba(120, 53, 15, 0.94)',
                border: 'rgba(251, 191, 36, 0.45)',
                text: '#fffbeb',
                accent: '#fbbf24',
            },
            info: {
                bg: 'rgba(15, 23, 42, 0.95)',
                border: 'rgba(129, 140, 248, 0.42)',
                text: '#f8fafc',
                accent: '#818cf8',
            },
        };

        const tone = palette[type] || palette.info;
        const toast = document.createElement('div');
        toast.className = `eb-toast eb-toast--${type}`;
        toast.setAttribute('role', type === 'error' ? 'alert' : 'status');
        toast.setAttribute('aria-live', type === 'error' ? 'assertive' : 'polite');

        Object.assign(toast.style, {
            position: 'fixed',
            bottom: '1.25rem',
            right: '1.25rem',
            padding: '0.85rem 1.1rem',
            borderRadius: '0.75rem',
            backgroundColor: tone.bg,
            color: tone.text,
            border: `1px solid ${tone.border}`,
            boxShadow: '0 14px 28px -8px rgba(0, 0, 0, 0.6), inset 0 1px 0 rgba(255, 255, 255, 0.10)',
            zIndex: '9999',
            maxWidth: '24rem',
            fontSize: '0.875rem',
            fontWeight: '600',
            display: 'flex',
            alignItems: 'center',
            gap: '0.65rem',
        });

        const dot = document.createElement('span');
        Object.assign(dot.style, {
            width: '0.5rem',
            height: '0.5rem',
            borderRadius: '9999px',
            backgroundColor: tone.accent,
            flexShrink: '0',
        });

        const textSpan = document.createElement('span');
        textSpan.textContent = message;

        toast.appendChild(dot);
        toast.appendChild(textSpan);
        document.body.appendChild(toast);

        window.setTimeout(() => {
            toast.classList.add('is-dismissing');
            window.setTimeout(() => toast.remove(), prefersReducedMotion() ? 10 : 200);
        }, duration);
    }

    /**
     * Pulse a live price counter when options or quantities change
     */
    static pulsePrice(element) {
        if (!element || prefersReducedMotion()) return;
        this.animate(element, 'eb-price-updated', 320);
    }

    /**
     * Shake an element on validation error
     */
    static shakeError(element) {
        if (!element) return;
        this.animate(element, 'shake', 380);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    // 1. Progressive image loading: remove skeleton once image finishes loading
    const images = document.querySelectorAll('img[loading="lazy"], .eb-event-card__media img, .eb-food-card__media img');
    images.forEach((img) => {
        if (img.complete) {
            img.classList.remove('eb-skeleton', 'skeleton');
        } else {
            img.addEventListener('load', () => {
                img.classList.remove('eb-skeleton', 'skeleton');
            }, { once: true });
            img.addEventListener('error', () => {
                img.classList.remove('eb-skeleton', 'skeleton');
            }, { once: true });
        }
    });

    // 2. Opt-in button loading feedback on forms with [data-loading-form] or buttons with [data-loading-text]
    document.querySelectorAll('form').forEach((form) => {
        form.addEventListener('submit', () => {
            const submitter = form.querySelector('button[type="submit"][data-loading-text], button[type="submit"].eb-btn-primary');
            if (submitter && !submitter.hasAttribute('data-no-loading')) {
                EventBiteAnimations.setButtonLoading(submitter, true);
            }
        });
    });

    // 3. Accessible flash alert dismiss support
    document.querySelectorAll('.flash-alert [data-dismiss-alert], .ac-alert [data-dismiss-alert]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const alertBox = btn.closest('.flash-alert, .ac-alert');
            if (!alertBox) return;
            alertBox.classList.add('is-dismissing');
            window.setTimeout(() => alertBox.remove(), prefersReducedMotion() ? 10 : 180);
        });
    });
});

window.EventBiteAnimations = EventBiteAnimations;
