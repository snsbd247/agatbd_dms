/*
 * DMS service worker: only what offline order taking needs.
 *  - Built files (/build/…) and icons: from the cache once loaded.
 *  - Pages: always from the network; when there is no network, the last
 *    copy of the Offline Orders page (or of the page asked for) is shown.
 * Nothing is posted from here; POSTs always go to the network.
 */
const CACHE = 'dms-v1';
// Relative to where the app lives (domain root on the server, /dms/ locally).
const OFFLINE_PAGE = new URL('sales/offline-orders', self.registration.scope).pathname;
const BUILD = new URL('build/', self.registration.scope).pathname;

self.addEventListener('install', () => self.skipWaiting());

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const req = event.request;
    if (req.method !== 'GET') return;
    const url = new URL(req.url);
    if (url.origin !== self.location.origin) return;

    // Scripts (our build and Livewire/Alpine), styles, fonts, icons: cache first.
    if (url.pathname.startsWith(BUILD) || ['script', 'style', 'font', 'image'].includes(req.destination) || /\.(png|ico|svg|woff2?)$/.test(url.pathname)) {
        event.respondWith(
            caches.open(CACHE).then(async (cache) => {
                const hit = await cache.match(req);
                if (hit) return hit;
                const res = await fetch(req);
                if (res.ok) cache.put(req, res.clone());
                return res;
            })
        );
        return;
    }

    // Pages: network first; keep a copy of the offline order page.
    if (req.mode === 'navigate') {
        event.respondWith(
            fetch(req)
                .then((res) => {
                    if (res.ok && url.pathname === OFFLINE_PAGE) {
                        const copy = res.clone();
                        caches.open(CACHE).then((cache) => cache.put(OFFLINE_PAGE, copy));
                    }
                    return res;
                })
                .catch(async () => {
                    const cache = await caches.open(CACHE);
                    return (await cache.match(OFFLINE_PAGE))
                        || new Response('<meta name="viewport" content="width=device-width"><p style="font-family:sans-serif;padding:2rem">No internet. Open “Offline Orders” once while online to use it without internet. / ইন্টারনেট নেই। অফলাইনে ব্যবহার করতে একবার অনলাইনে “Offline Orders” খুলুন।</p>',
                            { headers: { 'Content-Type': 'text/html; charset=utf-8' } });
                })
        );
    }
});
