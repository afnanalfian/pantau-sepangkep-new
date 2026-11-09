/* PANTAU SEPANGKEP – Service Worker
 * - Menerima Web Push & menampilkan notifikasi sistem (HP / desktop)
 * - Membuka halaman terkait saat notifikasi diklik
 * - Halaman offline sederhana
 */
const CACHE = 'pantau-v1';
const OFFLINE_URL = '/offline.html';

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE).then((c) => c.addAll([OFFLINE_URL, '/icons/icon-192.png', '/images/logo-bps-256.png']))
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    if (event.request.mode === 'navigate') {
        event.respondWith(fetch(event.request).catch(() => caches.match(OFFLINE_URL)));
    }
});

self.addEventListener('push', (event) => {
    let data = {};
    try { data = event.data ? event.data.json() : {}; } catch (e) { data = { title: 'PANTAU SEPANGKEP', body: event.data ? event.data.text() : '' }; }

    const title = data.title || 'PANTAU SEPANGKEP';
    const options = {
        body: data.body || '',
        icon: data.icon || '/icons/icon-192.png',
        badge: data.badge || '/icons/badge-72.png',
        tag: data.tag || undefined,
        renotify: !!data.tag,
        vibrate: [120, 60, 120],
        data: { url: data.url || '/' },
    };

    event.waitUntil(
        self.registration.showNotification(title, options).then(() =>
            self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clients) =>
                clients.forEach((c) => c.postMessage({ type: 'pantau-push', payload: data }))
            )
        )
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const target = new URL(event.notification.data?.url || '/', self.location.origin).href;

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clients) => {
            for (const client of clients) {
                if (client.url.startsWith(self.location.origin) && 'focus' in client) {
                    client.navigate(target);
                    return client.focus();
                }
            }
            return self.clients.openWindow(target);
        })
    );
});

self.addEventListener('pushsubscriptionchange', (event) => {
    // Browser memperbarui langganan: minta halaman mendaftar ulang saat dibuka berikutnya.
    event.waitUntil(self.clients.matchAll().then((cs) => cs.forEach((c) => c.postMessage({ type: 'pantau-resubscribe' }))));
});
