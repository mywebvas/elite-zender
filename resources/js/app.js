import './bootstrap';

import intersect from '@alpinejs/intersect';
import Alpine from 'alpinejs';
import Quill from 'quill';
import 'quill/dist/quill.snow.css';

// Turbo is bundled, not pulled from a CDN at runtime.
//
// The layout used to load it from cdn.jsdelivr.net with a <script type=module>
// tag. Two problems: the Content-Security-Policy only allows `script-src
// 'self'` plus a nonce, so the browser blocked it outright and every
// `turbo:load` listener below was dead code; and a third-party CDN in the
// critical path is an availability and supply-chain dependency we do not need.
import * as Turbo from '@hotwired/turbo';

Alpine.plugin(intersect);

window.Quill = Quill;
window.Turbo = Turbo;

// ---------------------------------------------------------------------------
// Dark mode — the inline <head> script already applied the class before first
// paint; this keeps the module in sync for anything that reads it later.
// ---------------------------------------------------------------------------
const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
const storedTheme = localStorage.getItem('theme');
if (storedTheme === 'dark' || (!storedTheme && prefersDark)) {
    document.documentElement.classList.add('dark');
}

// ---------------------------------------------------------------------------
// $toast — global toast manager (docs/07-PWA-SPEC.md §4.1)
// ---------------------------------------------------------------------------
const toastQueue = Alpine.reactive({ items: [] });

const dismissToast = (id) => {
    toastQueue.items = toastQueue.items.filter((t) => t.id !== id);
};

window.$toast = function (message, type = 'success', duration = 3000) {
    const id = `${Date.now()}-${Math.random()}`;
    toastQueue.items.push({ id, message, type, duration });

    // Errors persist until dismissed; everything else auto-expires. Previously
    // only `success` was auto-dismissed, so informational toasts piled up on
    // screen forever.
    if (type !== 'error' && duration > 0) {
        setTimeout(() => dismissToast(id), duration);
    }

    // Mirror the message into the live region so screen readers announce it.
    const announcer = document.getElementById('sr-announce');
    if (announcer) {
        announcer.textContent = message;
    }

    return id;
};

window.$dismissToast = dismissToast;

Alpine.magic('toast', () => window.$toast);
Alpine.store('toastQueue', toastQueue);

// ---------------------------------------------------------------------------
// x-transition preset — 150ms ease-out per spec (docs/07-PWA-SPEC.md §3)
// Honours prefers-reduced-motion: an animation the user asked us not to play
// is an accessibility failure, not a flourish.
// ---------------------------------------------------------------------------
document.addEventListener('alpine:init', () => {
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    Alpine.data('transitionPreset', () => (reduced
        ? {
            enter: '', enterStart: '', enterEnd: '',
            leave: '', leaveStart: '', leaveEnd: '',
        }
        : {
            enter: 'transition ease-out duration-150',
            enterStart: 'opacity-0 translate-y-1',
            enterEnd: 'opacity-100 translate-y-0',
            leave: 'transition ease-in duration-100',
            leaveStart: 'opacity-100 translate-y-0',
            leaveEnd: 'opacity-0 translate-y-1',
        }));
});

// ---------------------------------------------------------------------------
// Service worker registration (docs/07-PWA-SPEC.md §5.2)
// ---------------------------------------------------------------------------
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker
            .register('/sw.js', { scope: '/' })
            .then((registration) => {
                registration.addEventListener('updatefound', () => {
                    const newWorker = registration.installing;

                    newWorker?.addEventListener('statechange', () => {
                        if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                            window.$toast('Update available — refresh to apply.', 'info', 0);
                        }
                    });
                });
            })
            .catch(() => {
                // Registration failure is non-fatal; the app still works online.
            });
    });
}

window.Alpine = Alpine;
Alpine.start();

// Turbo swaps <body> without a full page load, so Alpine has to re-scan the
// new DOM. This listener only works now that Turbo is actually loaded.
document.addEventListener('turbo:load', () => {
    window.Alpine?.initTree(document.body);
});
