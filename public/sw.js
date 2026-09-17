/**
 * EliteSender Service Worker — stub for PWA installability.
 *
 * Strategy (docs/07-PWA-SPEC.md §5.2):
 *   - Built CSS/JS/fonts : cache-first (Workbox precache — M8 Polish)
 *   - Blade pages / API  : network-first, 3s timeout → cache
 *   - Dashboard stats    : stale-while-revalidate
 *   - Tracking pixels    : network-only (never cache)
 *   - Offline fallback   : /offline page
 *
 * TODO (M8 Polish): replace this stub with Workbox via vite-plugin-pwa.
 * The install/activate/fetch hooks below are structural placeholders
 * that keep the browser from erroring while allowing future strategy injection.
 */

const CACHE_NAME = 'elitesender-v1';
const OFFLINE_URL = '/offline';

// ---------------------------------------------------------------------------
// Install — pre-cache the offline fallback only
// ---------------------------------------------------------------------------
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => cache.add(OFFLINE_URL))
    );
    self.skipWaiting();
});

// ---------------------------------------------------------------------------
// Activate — claim clients immediately; prune old caches
// ---------------------------------------------------------------------------
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(
                keys
                    .filter((key) => key !== CACHE_NAME)
                    .map((key) => caches.delete(key))
            )
        )
    );
    self.clients.claim();
});

// ---------------------------------------------------------------------------
// Fetch — network-first; fall back to /offline for navigation requests
// ---------------------------------------------------------------------------
self.addEventListener('fetch', (event) => {
    // Never intercept non-GET, tracking pixels, or cross-origin requests
    if (
        event.request.method !== 'GET' ||
        event.request.url.includes('/t/') ||
        !event.request.url.startsWith(self.location.origin)
    ) {
        return;
    }

    if (event.request.mode === 'navigate') {
        event.respondWith(
            fetch(event.request).catch(() =>
                caches.match(OFFLINE_URL)
            )
        );
        return;
    }

    // All other GET requests: network-first passthrough (Workbox takes over in M8)
    event.respondWith(fetch(event.request));
});
