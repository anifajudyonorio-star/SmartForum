/**
 * Offline sync module
 */

const QUEUE_KEY = 'sf_offline_queue';
const DEVICE_KEY = 'sf_device_id';

async function ensureToken() {
    if (window._sfApiToken) return true;
    try {
        const res = await fetch('/api/token', {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            },
        });
        if (!res.ok) return false;
        const data = await res.json();
        window._sfApiToken = data.token;
        return true;
    } catch { return false; }
}

function getQueue() {
    try { return JSON.parse(localStorage.getItem(QUEUE_KEY) || '[]'); } catch { return []; }
}

function saveQueue(queue) {
    localStorage.setItem(QUEUE_KEY, JSON.stringify(queue));
}

function getOrCreateDeviceId() {
    let id = localStorage.getItem(DEVICE_KEY);
    if (!id) {
        id = 'browser-' + crypto.randomUUID();
        localStorage.setItem(DEVICE_KEY, id);
    }
    return id;
}

function authHeaders() {
    const token = window._sfApiToken;
    return {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        ...(token ? { 'Authorization': `Bearer ${token}` } : {}),
    };
}

function showBanner(message, type = 'warning') {
    let banner = document.getElementById('offlineBanner');
    if (!banner) return;
    banner.textContent = message;
    banner.className = `offline-banner offline-banner-${type} show`;
}

function hideBanner() {
    const banner = document.getElementById('offlineBanner');
    if (banner) banner.classList.remove('show');
}

export function queueAction(actionType, payload) {
    const queue = getQueue();
    queue.push({ action_type: actionType, payload, queued_at: Date.now() });
    saveQueue(queue);
    const count = queue.length;
    showBanner(`You're offline. ${count} action${count > 1 ? 's' : ''} queued — will sync when reconnected.`);
}

async function registerDevice() {
    if (!await ensureToken()) return;
    await fetch('/api/sync/device', {
        method: 'POST',
        headers: authHeaders(),
        body: JSON.stringify({
            device_id: getOrCreateDeviceId(),
            device_name: navigator.userAgent.substring(0, 100),
            device_type: 'browser',
        }),
    }).catch(() => {});
}

async function flushQueue() {
    const queue = getQueue();
    if (!queue.length) return;
    if (!await ensureToken()) return;

    try {
        const uploadRes = await fetch('/api/sync/upload', {
            method: 'POST',
            headers: authHeaders(),
            body: JSON.stringify({ actions: queue }),
        });

        if (!uploadRes.ok) return;

        saveQueue([]);

        const syncRes = await fetch('/api/sync', {
            method: 'POST',
            headers: authHeaders(),
            body: JSON.stringify({ device_id: getOrCreateDeviceId() }),
        });

        if (syncRes.ok) {
            const data = await syncRes.json();
            const conflicts = data.conflicts ?? [];
            const errors    = data.errors    ?? [];

            if (conflicts.length) {
                const reasons = conflicts.map(c => c.reason).join(' | ');
                showBanner(`Synced with conflicts: ${reasons}`, 'warning');
                setTimeout(hideBanner, 8000);
            } else if (errors.length) {
                showBanner(`Sync completed with ${errors.length} error(s). Some actions may not have saved.`, 'danger');
                setTimeout(hideBanner, 6000);
            } else {
                showBanner('Back online — offline actions synced!', 'success');
                setTimeout(hideBanner, 3000);
            }
        }

        setTimeout(() => window.location.reload(), 3500);

    } catch {
        showBanner('Sync failed. Will retry when connection is stable.', 'danger');
    }
}

window.addEventListener('offline', () => {
    showBanner("You're offline. Actions will be saved and synced when you reconnect.");
});

window.addEventListener('online', async () => {
    showBanner('Reconnected. Syncing…', 'info');
    await registerDevice();
    await flushQueue();
});

export async function initOfflineSync() {
    if (!document.querySelector('meta[name="notifications-poll-url"]')) return;

    await registerDevice();

    if (navigator.onLine && getQueue().length) {
        await flushQueue();
    }

    if (!navigator.onLine) {
        showBanner("You're offline. Actions will be saved and synced when you reconnect.");
    }
}
