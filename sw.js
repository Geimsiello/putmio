/* PutMio — service worker (cache asset statici, niente HTML/API autenticate) */
const CACHE = 'putmio-static-v2';
/*
 * CSS e JS NON sono precache-ati: gli URL contengono la versione nel nome
 * (es. app.v1718900000.css), quindi a ogni aggiornamento cambia l'URL e la
 * cache-first qui sotto scarica automaticamente l'ultima versione. Restano
 * in precache solo gli asset con nome stabile (icone, favicon, placeholder).
 */
const ASSETS = [
  'public/assets/favicon.svg',
  'public/assets/no-poster.svg',
  'public/assets/icons/icon-192.png',
  'public/assets/icons/icon-512.png',
];

self.addEventListener('install', function (event) {
  event.waitUntil(
    caches.open(CACHE).then(function (cache) {
      return cache.addAll(ASSETS);
    })
  );
  self.skipWaiting();
});

self.addEventListener('activate', function (event) {
  event.waitUntil(
    caches.keys().then(function (keys) {
      return Promise.all(
        keys.filter(function (key) { return key !== CACHE; }).map(function (key) {
          return caches.delete(key);
        })
      );
    })
  );
  self.clients.claim();
});

self.addEventListener('fetch', function (event) {
  if (event.request.method !== 'GET') {
    return;
  }

  var url = new URL(event.request.url);
  if (url.origin !== self.location.origin) {
    return;
  }

  if (!url.pathname.includes('/public/assets/')) {
    return;
  }

  event.respondWith(
    caches.match(event.request).then(function (cached) {
      if (cached) {
        return cached;
      }
      return fetch(event.request).then(function (response) {
        if (!response || response.status !== 200 || response.type === 'opaque') {
          return response;
        }
        var copy = response.clone();
        caches.open(CACHE).then(function (cache) {
          cache.put(event.request, copy);
        });
        return response;
      });
    })
  );
});
