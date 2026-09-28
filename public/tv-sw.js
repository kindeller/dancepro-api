// Cache only the public player shell. Private images and credentials stay in the player's IndexedDB.
const VERSION = 'dancepro-tv-shell-v7';
const SHELL = ['/tv', '/tv-assets/player.js', '/tv-assets/cache.js', '/tv-assets/player.css'];
self.addEventListener('install', event => { event.waitUntil(caches.open(VERSION).then(cache => cache.addAll(SHELL)).then(() => self.skipWaiting())); });
self.addEventListener('activate', event => { event.waitUntil(caches.keys().then(keys => Promise.all(keys.filter(k => k.startsWith('dancepro-tv-shell-') && k !== VERSION).map(k => caches.delete(k)))).then(() => self.clients.claim())); });
self.addEventListener('fetch', event => {
    const url = new URL(event.request.url);
    if (event.request.method !== 'GET' || url.origin !== self.location.origin || !SHELL.includes(url.pathname)) return;
    event.respondWith(fetch(event.request).catch(() => caches.match(url.pathname)));
});
