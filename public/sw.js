'use strict';
const OFFLINE_CACHE = 'motel-offline-v1';
const OFFLINE_PAGE = '/offline.html';
const PUBLIC_FILES = [OFFLINE_PAGE, '/icons/motel-192.png', '/icons/motel-512.png'];

self.addEventListener('install', event => {
  event.waitUntil(caches.open(OFFLINE_CACHE).then(cache => cache.addAll(
    PUBLIC_FILES.map(path => new Request(path, { credentials: 'omit', cache: 'reload' }))
  )).then(() => self.skipWaiting()));
});

self.addEventListener('activate', event => {
  event.waitUntil(caches.keys().then(keys => Promise.all(
    keys.filter(key => key.startsWith('motel-offline-') && key !== OFFLINE_CACHE).map(key => caches.delete(key))
  )).then(() => self.clients.claim()));
});

// Business pages and submissions always use the network. Never store private responses.
self.addEventListener('fetch', event => {
  if (event.request.method !== 'GET' || event.request.mode !== 'navigate' ||
      new URL(event.request.url).origin !== self.location.origin) return;
  event.respondWith(fetch(event.request).catch(async () => {
    const cache = await caches.open(OFFLINE_CACHE);
    return await cache.match(OFFLINE_PAGE) || Response.error();
  }));
});
