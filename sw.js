/**
 * Service worker mínimo para instalación PWA (Android/Chrome).
 * Estrategia: red directa. Sin caché de assets, APIs ni partidas.
 */
self.addEventListener('install', function (event) {
  self.skipWaiting();
});

self.addEventListener('activate', function (event) {
  event.waitUntil(self.clients.claim());
});

self.addEventListener('fetch', function (event) {
  if (event.request.method !== 'GET') {
    return;
  }
  var url = new URL(event.request.url);
  var path = url.pathname;
  if (path.indexOf('/api/') !== -1 || path.endsWith('/api/index.php') || path.indexOf('/data/') !== -1) {
    return;
  }
  event.respondWith(fetch(event.request));
});
