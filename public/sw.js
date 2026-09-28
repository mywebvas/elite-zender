/**
 * EliteSender Service Worker
 * Fully Optimized Premium PWA Implementation
 */

const CACHE_NAME = 'elitesender-premium-v2';
const OFFLINE_URL = '/offline';
const CACHE_EXTENSIONS = ['.css', '.js', '.woff2', '.png', '.svg', '.webp', '.jpg'];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => cache.add(OFFLINE_URL))
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(
                keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))
            )
        )
    );
    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);

    // Skip non-GET, external domains, and tracking endpoints
    if (event.request.method !== 'GET' || !url.origin.includes(self.location.origin) || url.pathname.includes('/api/track')) {
        return;
    }

    // Cache-First strategy for static assets (CSS, JS, Fonts, Images)
    if (CACHE_EXTENSIONS.some(ext => url.pathname.endsWith(ext)) || url.pathname.includes('/build/')) {
        event.respondWith(
            caches.match(event.request).then((cachedResponse) => {
                if (cachedResponse) {
                    return cachedResponse; // Return instantly from cache
                }
                return fetch(event.request).then((networkResponse) => {
                    if (networkResponse && networkResponse.status === 200 && networkResponse.type === 'basic') {
                        const responseToCache = networkResponse.clone();
                        caches.open(CACHE_NAME).then((cache) => {
                            cache.put(event.request, responseToCache);
                        });
                    }
                    return networkResponse;
                });
            })
        );
        return;
    }

    // Network-First strategy for HTML/Navigation (SPA morphing)
    if (event.request.mode === 'navigate' || event.request.headers.get('accept').includes('text/html')) {
        event.respondWith(
            fetch(event.request).catch(() => caches.match(OFFLINE_URL))
        );
        return;
    }

    // Network-only for everything else
    event.respondWith(fetch(event.request));
});
