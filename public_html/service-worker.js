const CACHE_NAME = 'srp-dashboard-v5';
const CORE_ASSETS = [
  '/',
  '/login',
  '/offline.html',
  '/landing.php',
  '/manifest.json',
  '/assets/style.css',
  '/assets/icons/icon.svg',
  '/assets/icons/icon-maskable.svg',
  '/assets/icons/fox-head.png',
];

function getRequestUrl(request)
{
    try {
        return new URL(request.url);
    } catch (error) {
        return null;
    }
}

function isCacheableRequest(request)
{
    if (!(request instanceof Request) || request.method !== 'GET') {
        return false;
    }

    const url = getRequestUrl(request);
    if (url === null) {
        return false;
    }

    if (url.protocol !== 'http:' && url.protocol !== 'https:') {
        return false;
    }

    return url.origin === self.location.origin;
}

function putInCache(cacheName, request, response)
{
    if (!isCacheableRequest(request)) {
        return Promise.resolve();
    }

    if (!(response instanceof Response) || !response.ok || response.type === 'opaque') {
        return Promise.resolve();
    }

    return caches.open(cacheName)
    .then(function (cache) {
        return cache.put(request, response.clone());
    })
    .catch(function () {
        return undefined;
    });
}

self.addEventListener('install', function (event) {
    event.waitUntil(
        caches.open(CACHE_NAME).then(function (cache) {
            return Promise.all(CORE_ASSETS.map(function (url) {
                return cache.add(url).catch(function () {
                    return undefined;
                });
            }));
        })
    );
    self.skipWaiting();
});

self.addEventListener('activate', function (event) {
    event.waitUntil(
        caches.keys().then(function (keys) {
            return Promise.all(keys.map(function (key) {
                if (key === CACHE_NAME) {
                    return null;
                }

                return caches.delete(key);
            }));
        })
    );
    self.clients.claim();
});

self.addEventListener('fetch', function (event) {
    const request = event.request;
    if (!isCacheableRequest(request)) {
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request)
            .then(function (response) {
                void putInCache(CACHE_NAME, request, response);
                return response;
            })
            .catch(async function () {
                const cache = await caches.open(CACHE_NAME);
                const cached = await cache.match(request);
                if (cached) {
                    return cached;
                }
                const offline = await cache.match('/offline.html');
                return offline || new Response('Offline', { status: 503, headers: { 'Content-Type': 'text/plain' } });
            })
        );
        return;
    }

    event.respondWith(
        caches.match(request).then(function (cached) {
            const networkFetch = fetch(request)
            .then(function (response) {
                void putInCache(CACHE_NAME, request, response);
                return response;
            })
            .catch(function () {
                return cached || new Response('', { status: 408 });
            });

            return cached || networkFetch;
        })
    );
});
