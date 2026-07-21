const CACHE_NAME = 'hoki-dimsum-shell-v1';
const APP_SHELL = [
  './assets/css/style.css',
  './assets/2.png',
  './assets/icons/icon-192.png',
  './assets/icons/icon-512.png',
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => cache.addAll(APP_SHELL))
  );
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.filter((k) => k !== CACHE_NAME).map((k) => caches.delete(k)))
    )
  );
  self.clients.claim();
});

self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (req.method !== 'GET') return;

  if (req.mode === 'navigate') {
    // Halaman dinamis (order, status, dll): selalu utamakan versi terbaru dari server.
    event.respondWith(fetch(req).catch(() => caches.match(req)));
    return;
  }

  // Aset statis (CSS/JS/gambar): pakai cache dulu, baru fetch ke server kalau belum ada.
  event.respondWith(
    caches.match(req).then((cached) => cached || fetch(req))
  );
});
