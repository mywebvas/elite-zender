import './bootstrap';

import Alpine from 'alpinejs';
import intersect from '@alpinejs/intersect';
Alpine.plugin(intersect);
import Quill from 'quill';
import 'quill/dist/quill.snow.css';

window.Quill = Quill;

// ---------------------------------------------------------------------------
// Dark-mode: read persisted preference, apply before paint to avoid flash
// ---------------------------------------------------------------------------
const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
const storedTheme = localStorage.getItem('theme');
if (storedTheme === 'dark' || (!storedTheme && prefersDark)) {
    document.documentElement.classList.add('dark');
}

// ---------------------------------------------------------------------------
// $toast — global toast manager (docs/07-PWA-SPEC.md §4.1)
// Used by x-toast component and window.$toast()
// ---------------------------------------------------------------------------
const toastQueue = Alpine.reactive({ items: [] });

window.$toast = function (message, type = 'success', duration = 3000) {
    const id = Date.now() + Math.random();
    toastQueue.items.push({ id, message, type, duration });

    if (type === 'success' && duration > 0) {
        setTimeout(() => {
            toastQueue.items = toastQueue.items.filter((t) => t.id !== id);
        }, duration);
    }
};

Alpine.magic('toast', () => window.$toast);

// Expose queue so x-toast component can bind to it
Alpine.store('toastQueue', toastQueue);

// ---------------------------------------------------------------------------
// x-transition preset — 150ms ease-out per spec (docs/07-PWA-SPEC.md §3)
// ---------------------------------------------------------------------------
document.addEventListener('alpine:init', () => {
    Alpine.data('transitionPreset', () => ({
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
                // Detect SW update — toast the user (non-intrusive)
                registration.addEventListener('updatefound', () => {
                    const newWorker = registration.installing;
                    newWorker?.addEventListener('statechange', () => {
                        if (
                            newWorker.state === 'installed' &&
                            navigator.serviceWorker.controller
                        ) {
                            window.$toast(
                                'Update available — refresh to apply.',
                                'info',
                                0  // persist until user acts
                            );
                        }
                    });
                });
            })
            .catch(() => {
                // SW registration failure is non-fatal — app still works
            });
    });
}

window.Alpine = Alpine;
Alpine.start();


document.addEventListener('turbo:load', () => { if (window.Alpine) { window.Alpine.initTree(document.body); } });
