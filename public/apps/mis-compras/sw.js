const CACHE_NAME = 'mis-compras-pwa-v17';
const PRECACHE_URLS = [
  '/apps/mis-compras/',
  '/apps/mis-compras/index.html',
  '/apps/mis-compras/style.css',
  '/apps/mis-compras/app.js',
  '/apps/mis-compras/manifest.json',
  '/apps/mis-compras/icon-192.png',
  '/apps/mis-compras/icon-512.png',
  '/apps/mis-compras/apple-touch-icon.png',
  '/apps/mis-compras/favicon.ico',
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => cache.addAll(PRECACHE_URLS))
  );
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.map((key) => (key === CACHE_NAME ? null : caches.delete(key))))
    )
  );
  self.clients.claim();
});

self.addEventListener('fetch', (event) => {
  const { request } = event;
  if (request.method !== 'GET') return;

  const url = new URL(request.url);
  if (url.origin !== self.location.origin) return;
  if (url.pathname.includes('/api/')) return;

  event.respondWith(
    caches.match(request).then((cached) => {
      if (cached) return cached;
      return fetch(request).then((response) => {
        const responseClone = response.clone();
        caches.open(CACHE_NAME).then((cache) => cache.put(request, responseClone));
        return response;
      }).catch(() => caches.match('/apps/mis-compras/index.html'));
    })
  );
});
