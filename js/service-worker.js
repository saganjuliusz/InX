const CACHE_NAME = 'inx-cache-v0.0.0.1';
const urlsToCache = [
  '/InX/',
  '/InX/index.php',
  '/InX/styles.css',
  '/InX/script.js',
  '/InX/manifest.json',
  '/InX/icon-512x512.png',
  '/InX/icon-192x192.png'
];
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => cache.addAll(urlsToCache))
  );
});
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames.map((cacheName) => {
          if (cacheName !== CACHE_NAME) {
            return caches.delete(cacheName);
          }
        })
      );
    })
  );
});
self.addEventListener('fetch', (event) => {
  event.respondWith(
    caches.match(event.request)
      .then((response) => {
        if (response) {
          return response;
        }
        return fetch(event.request).then(
          (response) => {
            if(!response || response.status !== 200 || response.type !== 'basic') {
              return response;
            }
            const responseToCache = response.clone();
            caches.open(CACHE_NAME)
              .then((cache) => {
                cache.put(event.request, responseToCache);
              });
            return response;
          }
        );
      })
  );
});
self.addEventListener('push', function(event) {
  const options = {
    body: event.data.text(),
    icon: 'icon-192x192.png',
    badge: 'icon-192x192.png'
  };
  event.waitUntil(
    self.registration.showNotification('InX Powiadomienie', options)
  );
});