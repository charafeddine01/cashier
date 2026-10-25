/**
 * OmniPOS Pro - Service Worker
 * PWA Offline Cache Management
 *
 * STRICT RULES:
 * 1. NEVER intercept non-GET requests (100% POST protocol API requests must go directly to server).
 * 2. NEVER intercept requests containing /api/.
 * 3. During local development (localhost / 127.0.0.1), completely bypass caching.
 */

const CACHE_NAME = 'omnipos-cache-v3';

self.addEventListener('install', (event) => {
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  // Purge any older cached versions
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.map((key) => caches.delete(key)))
    ).then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  // 1. Strictly ignore non-GET requests (All OmniPOS API calls are HTTP POST)
  if (event.request.method !== 'GET') {
    return;
  }

  // 2. Strictly bypass all API endpoints
  if (event.request.url.includes('/api/')) {
    return;
  }

  // 3. Bypass caching on localhost / 127.0.0.1 for seamless dev & testing
  try {
    const url = new URL(event.request.url);
    if (
      url.hostname === 'localhost' ||
      url.hostname === '127.0.0.1' ||
      url.hostname === '0.0.0.0' ||
      url.hostname === '[::1]'
    ) {
      return;
    }
  } catch {
    // If URL parsing fails, proceed
  }

  // 4. Production network-first with cache fallback
  event.respondWith(
    fetch(event.request)
      .then((networkResponse) => {
        if (networkResponse && networkResponse.status === 200 && networkResponse.type === 'basic') {
          const responseToCache = networkResponse.clone();
          caches.open(CACHE_NAME).then((cache) => {
            cache.put(event.request, responseToCache);
          });
        }
        return networkResponse;
      })
      .catch(() => {
        return caches.match(event.request).then((cachedResponse) => {
          if (cachedResponse) {
            return cachedResponse;
          }
          if (event.request.mode === 'navigate') {
            return caches.match('/index.html');
          }
        });
      })
  );
});
