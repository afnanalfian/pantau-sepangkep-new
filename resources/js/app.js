/**
 * PANTAU SEPANGKEP – JS
 * Livewire 4 sudah membawa Alpine.js, jadi di sini hanya:
 *  - registrasi Service Worker (PWA)
 *  - Web Push (berlangganan notifikasi HP)
 *  - toast global
 */

const VAPID = document.querySelector('meta[name="vapid-key"]')?.content;
const CSRF = () => document.querySelector('meta[name="csrf-token"]')?.content;

function urlB64ToUint8Array(base64) {
    const padding = '='.repeat((4 - (base64.length % 4)) % 4);
    const raw = atob((base64 + padding).replace(/-/g, '+').replace(/_/g, '/'));
    return Uint8Array.from([...raw].map((c) => c.charCodeAt(0)));
}

async function post(url, body) {
    const res = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF(), Accept: 'application/json' },
        body: JSON.stringify(body || {}),
        credentials: 'same-origin',
    });
    return res.json().catch(() => ({}));
}

const Push = {
    supported() {
        return 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window && !!VAPID;
    },
    isIos() {
        return /iphone|ipad|ipod/i.test(navigator.userAgent);
    },
    isStandalone() {
        return window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
    },
    async registration() {
        return navigator.serviceWorker.ready;
    },
    async status() {
        if (!this.supported()) return this.isIos() && !this.isStandalone() ? 'ios-install' : 'unsupported';
        if (Notification.permission === 'denied') return 'denied';
        const reg = await this.registration();
        const sub = await reg.pushManager.getSubscription();
        return sub ? 'subscribed' : 'default';
    },
    async subscribe() {
        if (!this.supported()) throw new Error('Browser ini belum mendukung notifikasi push.');
        const perm = await Notification.requestPermission();
        if (perm !== 'granted') throw new Error('Izin notifikasi ditolak. Aktifkan lewat pengaturan situs di browser.');
        const reg = await this.registration();
        let sub = await reg.pushManager.getSubscription();
        if (!sub) {
            sub = await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: urlB64ToUint8Array(VAPID) });
        }
        const json = sub.toJSON();
        const encoding = (PushManager.supportedContentEncodings || ['aesgcm']).includes('aes128gcm') ? 'aes128gcm' : 'aesgcm';
        await post('/push/subscribe', { ...json, contentEncoding: encoding });
        localStorage.setItem('pantau-push', '1');
        return true;
    },
    async unsubscribe() {
        const reg = await this.registration();
        const sub = await reg.pushManager.getSubscription();
        if (sub) {
            await post('/push/unsubscribe', { endpoint: sub.endpoint });
            await sub.unsubscribe();
        }
        localStorage.removeItem('pantau-push');
    },
    async test() {
        return post('/push/test');
    },
    async endpoint() {
        try {
            const reg = await this.registration();
            return (await reg.pushManager.getSubscription())?.endpoint || '';
        } catch (e) {
            return '';
        }
    },
};
window.PantauPush = Push;

if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').then(async () => {
            // Perbarui langganan diam-diam bila sebelumnya sudah diizinkan
            if (Push.supported() && Notification.permission === 'granted' && document.body.dataset.auth === '1') {
                Push.subscribe().catch(() => {});
            }
        });
        navigator.serviceWorker.addEventListener('message', (e) => {
            if (e.data?.type === 'pantau-push') {
                window.Livewire?.dispatch('notifications-updated');
            }
            if (e.data?.type === 'pantau-resubscribe') {
                Push.subscribe().catch(() => {});
            }
        });
    });
}

// Saat logout, kirim endpoint push agar perangkat ini berhenti menerima notifikasi
document.addEventListener('submit', async (e) => {
    const form = e.target;
    if (form.matches('[data-logout]') && !form.dataset.ready) {
        e.preventDefault();
        const ep = await Push.endpoint();
        if (ep) {
            const input = document.createElement('input');
            input.type = 'hidden'; input.name = 'endpoint'; input.value = ep;
            form.appendChild(input);
            try { await (await navigator.serviceWorker.ready).pushManager.getSubscription().then((s) => s?.unsubscribe()); } catch (err) {}
        }
        form.dataset.ready = '1';
        form.submit();
    }
});
