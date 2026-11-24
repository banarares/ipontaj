const CACHE = 'ipontaj-public-v2';
const PUBLIC = [
    '/offline.html',
    '/icons/icon-192.png',
    '/icons/icon-512.png',
    '/favicon.svg',
    '/favicon.ico',
    '/apple-touch-icon.png',
];
self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(CACHE).then((cache) => cache.addAll(PUBLIC)));
    self.skipWaiting();
});
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) =>
                Promise.all(
                    keys
                        .filter(
                            (k) =>
                                k.startsWith('ipontaj-public-') && k !== CACHE,
                        )
                        .map((k) => caches.delete(k)),
                ),
            )
            .then(() => self.clients.claim()),
    );
});
self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);
    if (url.origin !== self.location.origin || event.request.method !== 'GET')
        return;
    if (event.request.mode === 'navigate') {
        // Never cache HTML, Inertia data, authenticated responses or exports.
        event.respondWith(
            fetch(event.request).catch(() => caches.match('/offline.html')),
        );
    } else if (PUBLIC.includes(url.pathname) && !url.search) {
        event.respondWith(
            caches
                .match(event.request)
                .then((cached) => cached || fetch(event.request)),
        );
    }
});
