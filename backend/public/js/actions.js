/*
 * Page behaviour wired by data attributes instead of inline on*="" handlers, so the Content-Security-Policy can
 * forbid inline event handlers (script-src without 'unsafe-inline').
 *
 *   data-action-click / -change / -input / -keyup / -submit / -load / -error="name"
 *       Calls a built-in action below, or else a global page function window[name].
 *       data-args='[...]' (JSON) supplies its arguments. Built-ins get (element, event, ...args).
 *   data-prevent-default      With data-action-submit: stop the normal form submission first.
 *   data-confirm="question"   On a <form>: asked before it submits. On any other element: asked on click,
 *                             before its click action runs; "Cancel" stops both.
 */
(function () {
    'use strict';

    var builtins = {
        // Submits the element's own form, or the form with the given id.
        'submit-form': function (el, event, formId) {
            var form = formId ? document.getElementById(formId) : el.form;
            if (form) form.submit();
        },
        'select': function (el) { el.select(); },
        'print': function () { window.print(); },
        'reload': function () { window.location.reload(); },
        // Copies text, then shows a short confirmation in place of the button's label.
        'copy': function (el, event, text, copiedLabel) {
            if (navigator.clipboard) navigator.clipboard.writeText(String(text));
            el.textContent = copiedLabel || 'Copied';
        },
        'hide': function (el, event, id) {
            var target = document.getElementById(id);
            if (target) target.style.display = 'none';
        },
        // A submit button that shows its saving state only when the form is valid (it will really submit).
        'mark-saving': function (el) {
            if (el.form && el.form.checkValidity()) {
                el.setAttribute('data-saving', '');
                el.setAttribute('aria-busy', 'true');
            }
        },
        'remove-closest': function (el, event, selector) {
            var target = el.closest(selector);
            if (target) target.remove();
        },
        // A broken image falls back once to another URL (and drops its <source> alternatives).
        'image-fallback': function (el, event, url) {
            if (el.dataset.fallbackUsed) return;
            el.dataset.fallbackUsed = '1';
            if (el.parentElement) el.parentElement.querySelectorAll('source').forEach(function (s) { s.remove(); });
            el.removeAttribute('srcset');
            el.src = url;
        },
        // Food card photos: drop the loading shimmer when the photo arrives, or show the placeholder if it can't.
        'food-media-loaded': function (el) {
            var media = el.closest('.eb-food-card-media');
            if (media) media.classList.remove('eb-skeleton');
        },
        'food-media-failed': function (el) {
            var media = el.closest('.eb-food-card-media');
            if (media) {
                media.classList.remove('eb-skeleton');
                media.classList.add('eb-food-card-placeholder');
            }
            el.remove();
        },
        // Scrolls to the element named by data-scroll-target (so it can follow another action's own arguments).
        'scroll-to': function (el) {
            var target = document.getElementById(el.getAttribute('data-scroll-target'));
            if (target) target.scrollIntoView({ behavior: 'smooth' });
        }
    };

    function args(el) {
        var raw = el.getAttribute('data-args');
        if (!raw) return [];
        try {
            var parsed = JSON.parse(raw);
            return Array.isArray(parsed) ? parsed : [parsed];
        } catch (e) {
            return [];
        }
    }

    // Runs the actions named in data-action-<type> (several may be given, separated by spaces).
    function run(el, type, event) {
        var names = (el.getAttribute('data-action-' + type) || '').split(/\s+/).filter(Boolean);
        var a = args(el);
        names.forEach(function (name) {
            if (builtins[name]) {
                builtins[name].apply(null, [el, event].concat(a));
            } else if (typeof window[name] === 'function') {
                window[name].apply(el, a);
            } else if (window.console) {
                console.warn('No page action named ' + name);
            }
        });
    }

    document.addEventListener('click', function (event) {
        var confirmEl = event.target.closest('[data-confirm]:not(form)');
        if (confirmEl && !window.confirm(confirmEl.getAttribute('data-confirm'))) {
            event.preventDefault();
            event.stopImmediatePropagation();
            return;
        }
        var el = event.target.closest('[data-action-click]');
        if (el) run(el, 'click', event);
    });

    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (form.matches('[data-confirm]') && !window.confirm(form.getAttribute('data-confirm'))) {
            event.preventDefault();
            return;
        }
        if (form.matches('[data-action-submit]')) {
            if (form.hasAttribute('data-prevent-default')) event.preventDefault();
            run(form, 'submit', event);
        }
    });

    ['change', 'input', 'keyup'].forEach(function (type) {
        document.addEventListener(type, function (event) {
            var el = event.target.closest && event.target.closest('[data-action-' + type + ']');
            if (el) run(el, type, event);
        });
    });

    // load and error don't bubble: listen in the capture phase.
    ['load', 'error'].forEach(function (type) {
        document.addEventListener(type, function (event) {
            var el = event.target;
            if (el && el.nodeType === 1 && el.hasAttribute('data-action-' + type)) run(el, type, event);
        }, true);
    });

    // Images that finished (or failed) before this script ran.
    function settleImages() {
        document.querySelectorAll('img[data-action-error], img[data-action-load]').forEach(function (img) {
            if (!img.complete || img.dataset.actionSettled) return;
            img.dataset.actionSettled = '1';
            if (img.naturalWidth === 0 && img.hasAttribute('data-action-error')) run(img, 'error', null);
            else if (img.naturalWidth > 0 && img.hasAttribute('data-action-load')) run(img, 'load', null);
        });
    }
    settleImages();
    document.addEventListener('DOMContentLoaded', settleImages);
})();
