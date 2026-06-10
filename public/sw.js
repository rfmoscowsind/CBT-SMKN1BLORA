const CACHE = 'cbt-shell-v8';
const SHELL = ['/offline.html', '/manifest.json', '/icons/cbt.svg'];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE)
            .then((cache) => cache.addAll(SHELL))
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((key) => key !== CACHE).map((key) => caches.delete(key))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    if (event.request.method !== 'GET') {
        return;
    }

    const url = new URL(event.request.url);

    if (event.request.mode === 'navigate') {
        if (url.pathname.includes('/logout')) {
            return;
        }
        event.respondWith(fetch(event.request).catch(() => caches.match('/offline.html')));
        return;
    }

    if (url.origin !== self.location.origin) {
        return;
    }

    // Only cache static assets and exclude dynamic routes, APIs, and sw.js itself
    const isStaticAsset = /\.(js|css|png|jpg|jpeg|gif|svg|ico|woff|woff2|ttf|eot|json)$/i.test(url.pathname);
    const isDynamicRoute = url.pathname.startsWith('/api/') || 
                           url.pathname.startsWith('/kelola/') || 
                           url.pathname.startsWith('/monitoring/') || 
                           url.pathname.startsWith('/ujian/') ||
                           url.pathname.startsWith('/dashboard') ||
                           url.pathname.endsWith('sw.js');

    if (!isStaticAsset || isDynamicRoute) {
        return;
    }

    event.respondWith(
        caches.match(event.request).then((cached) => cached || fetch(event.request).then((response) => {
            if (response.ok && response.type === 'basic') {
                const clone = response.clone();
                caches.open(CACHE).then((cache) => cache.put(event.request, clone));
            }

            return response;
        })),
    );
});
