/**
 * EventBite Animation Utilities
 * Provides accessible, performance-optimized animations with reduced motion support
 */

class EventBiteAnimations {
    constructor() {
        this.respectsReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        this.toastContainer = null;
        this.init();
    }

    init() {
        this.createToastContainer();
        this.setupGlobalListeners();
    }

    /**
     * Create a container for toast notifications
     */
    createToastContainer() {
        this.toastContainer = document.createElement('div');
        this.toastContainer.id = 'toast-container';
        this.toastContainer.style.cssText = `
            position: fixed;
            top: 24px;
            right: 24px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 12px;
            pointer-events: none;
        `;
        document.body.appendChild(this.toastContainer);
    }

    /**
     * Setup global event listeners
     */
    setupGlobalListeners() {
        // Listen for save events
        document.addEventListener('save-success', (e) => this.onSaveSuccess(e.detail));
        document.addEventListener('save-error', (e) => this.onSaveError(e.detail));
        
        // Listen for validation events
        document.addEventListener('validation-error', (e) => this.onValidationError(e.detail));
        
        // Listen for loading events
        document.addEventListener('loading-start', (e) => this.onLoadingStart(e.detail));
        document.addEventListener('loading-end', (e) => this.onLoadingEnd(e.detail));
    }

    /**
     * Show a toast notification
     */
    toast(message, type = 'info', duration = 3000) {
        if (!this.toastContainer) return;

        const toast = document.createElement('div');
        const bgColors = {
            success: '#dcfce7',
            error: '#fee2e2',
            info: '#dbeafe',
            warning: '#fef3c7'
        };
        const textColors = {
            success: '#166534',
            error: '#991b1b',
            info: '#1e40af',
            warning: '#92400e'
        };

        toast.style.cssText = `
            background: ${bgColors[type] || bgColors.info};
            color: ${textColors[type] || textColors.info};
            padding: 12px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            pointer-events: auto;
            ${this.respectsReducedMotion ? '' : 'animation: toastEnter 0.3s ease-out;'}
        `;
        toast.textContent = message;

        this.toastContainer.appendChild(toast);

        setTimeout(() => {
            toast.style.animation = 'toastExit 0.3s ease-in forwards';
            setTimeout(() => toast.remove(), 300);
        }, duration);
    }

    /**
     * Show save confirmation animation
     */
    onSaveSuccess(detail) {
        const message = detail.message || 'Changes saved successfully';
        this.toast(message, 'success');
        
        // Add visual confirmation to save button if provided
        if (detail.buttonId) {
            const button = document.getElementById(detail.buttonId);
            if (button) {
                button.classList.add('success-checkmark');
                setTimeout(() => button.classList.remove('success-checkmark'), 500);
            }
        }
    }

    /**
     * Show save error animation
     */
    onSaveError(detail) {
        const message = detail.message || 'Failed to save changes';
        this.toast(message, 'error');
        
        if (detail.buttonId) {
            const button = document.getElementById(detail.buttonId);
            if (button) {
                button.classList.add('error-shake');
                setTimeout(() => button.classList.remove('error-shake'), 400);
            }
        }
    }

    /**
     * Show validation error with animation
     */
    onValidationError(detail) {
        const field = detail.fieldId ? document.getElementById(detail.fieldId) : null;
        if (field) {
            field.classList.add('error-shake');
            setTimeout(() => field.classList.remove('error-shake'), 400);
            
            // Focus the field after animation
            field.focus();
        }
        
        this.toast(detail.message || 'Please check the highlighted fields', 'error');
    }

    /**
     * Show loading indicator
     */
    onLoadingStart(detail) {
        const target = detail.targetId ? document.getElementById(detail.targetId) : null;
        if (target) {
            const spinner = document.createElement('span');
            spinner.className = 'spinner';
            spinner.id = 'loading-spinner-' + Date.now();
            target.appendChild(spinner);
            target.dataset.loadingSpinner = spinner.id;
        }
    }

    /**
     * Hide loading indicator
     */
    onLoadingEnd(detail) {
        const target = detail.targetId ? document.getElementById(detail.targetId) : null;
        if (target && target.dataset.loadingSpinner) {
            const spinner = document.getElementById(target.dataset.loadingSpinner);
            if (spinner) spinner.remove();
            delete target.dataset.loadingSpinner;
        }
    }

    /**
     * Animate progress bar
     */
    updateProgress(progressId, percent) {
        const progress = document.getElementById(progressId);
        if (progress) {
            const fill = progress.querySelector('.progress-fill');
            if (fill) {
                fill.style.setProperty('--progress', percent + '%');
                fill.style.width = percent + '%';
            }
        }
    }

    /**
     * Show skeleton loading state
     */
    showSkeleton(containerId, type = 'text') {
        const container = document.getElementById(containerId);
        if (!container) return;

        const skeleton = document.createElement('div');
        skeleton.className = 'skeleton';
        
        if (type === 'text') {
            skeleton.classList.add('skeleton-text');
        } else if (type === 'avatar') {
            skeleton.classList.add('skeleton-avatar');
        } else if (type === 'image') {
            skeleton.classList.add('skeleton-image');
        }

        container.appendChild(skeleton);
        return skeleton;
    }

    /**
     * Hide skeleton and show content
     */
    hideSkeleton(skeleton, content) {
        if (skeleton && content) {
            skeleton.style.display = 'none';
            content.classList.add('fade-in');
        }
    }

    /**
     * Animate image loading
     */
    animateImageLoad(imgElement) {
        if (!this.respectsReducedMotion) {
            imgElement.classList.add('image-loading');
            
            imgElement.onload = () => {
                imgElement.classList.remove('image-loading');
                imgElement.classList.add('image-loaded');
            };
        }
    }

    /**
     * Add hover effects to cards
     */
    addCardHoverEffect(cardId, effect = 'lift') {
        const card = document.getElementById(cardId);
        if (!card) return;

        const effects = {
            lift: 'hover-lift',
            glow: 'hover-glow',
            scale: 'hover-scale'
        };

        card.classList.add(effects[effect] || effects.lift);
    }

    /**
     * Smooth tab transition
     */
    switchTab(tabId) {
        const tabs = document.querySelectorAll('[data-tab]');
        const contents = document.querySelectorAll('.tab-content');

        tabs.forEach(tab => {
            const isActive = tab.dataset.tab === tabId;
            tab.classList.toggle('is-active', isActive);
            tab.setAttribute('aria-selected', isActive);
        });

        contents.forEach(content => {
            const isActive = content.id === tabId;
            content.classList.toggle('is-hidden', !isActive);
            content.classList.toggle('is-visible', isActive);
        });
    }

    /**
     * Animate section completion
     */
    markSectionComplete(sectionId, complete = true) {
        const status = document.getElementById(sectionId + '-status');
        if (status) {
            status.className = 'section-status ' + (complete ? 'complete' : 'incomplete');
        }
    }

    /**
     * Collapse/expand card with animation
     */
    toggleCardExpand(cardId, expanded) {
        const content = document.getElementById(cardId + '-content');
        if (content) {
            content.classList.toggle('is-expanded', expanded);
        }
    }

    /**
     * Disable animations for a specific element
     */
    disableAnimations(elementId) {
        const element = document.getElementById(elementId);
        if (element) {
            element.style.animation = 'none';
            element.style.transition = 'none';
        }
    }
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.EventBiteAnimations = new EventBiteAnimations();
    });
} else {
    window.EventBiteAnimations = new EventBiteAnimations();
}

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = EventBiteAnimations;
}
