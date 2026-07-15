/**
 * Push notification module
 */

function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const raw = atob(base64);
    return Uint8Array.from([...raw].map((c) => c.charCodeAt(0)));
}

async function getToken() {
    if (window._sfApiToken) return window._sfApiToken;
    try {
        const res = await fetch('/api/token', {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            },
        });
        if (!res.ok) return null;
        const data = await res.json();
        window._sfApiToken = data.token;
        return data.token;
    } catch { return null; }
}

async function sendSubscriptionToServer(subscription, token) {
    const key  = subscription.getKey('p256dh');
    const auth = subscription.getKey('auth');

    await fetch('/api/push/subscribe', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'Authorization': `Bearer ${token}`,
        },
        body: JSON.stringify({
            endpoint: subscription.endpoint,
            p256dh:   key  ? btoa(String.fromCharCode(...new Uint8Array(key)))  : null,
            auth:     auth ? btoa(String.fromCharCode(...new Uint8Array(auth))) : null,
        }),
    });
}

export async function initPushNotifications() {
    const vapidKey = document.querySelector('meta[name="vapid-public-key"]')?.content;
    if (!vapidKey || !('serviceWorker' in navigator) || !('PushManager' in window)) return;

    try {
        const registration = await navigator.serviceWorker.register('/sw.js');
        const permission = await Notification.requestPermission();
        if (permission !== 'granted') return;

        const token = await getToken();
        if (!token) return;

        const existing = await registration.pushManager.getSubscription();
        if (existing) {
            await sendSubscriptionToServer(existing, token);
            return;
        }

        const subscription = await registration.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: urlBase64ToUint8Array(vapidKey),
        });

        await sendSubscriptionToServer(subscription, token);
    } catch {
        // Push not supported or denied — fail silently
    }
}
