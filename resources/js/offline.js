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

export function queueAction(actionType, payload, pendingEl) {
    const pendingId = 'p-' + Date.now() + '-' + Math.random().toString(36).slice(2, 7);
    const queue = getQueue();
    queue.push({ action_type: actionType, payload, queued_at: Date.now(), pendingId });
    saveQueue(queue);
    if (pendingEl) pendingEl.dataset.pendingId = pendingId;
    const count = queue.length;
    showBanner(`You're offline. ${count} action${count > 1 ? 's' : ''} queued — will sync when reconnected.`);
    return pendingId;
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
    if (window._networkForced === false) return; // forced offline
    if (!await ensureToken()) return;

    try {
        const uploadRes = await fetch('/api/sync/upload', {
            method: 'POST',
            headers: authHeaders(),
            body: JSON.stringify({ actions: queue }),
        });

        if (!uploadRes.ok) return;

        const syncRes = await fetch('/api/sync', {
            method: 'POST',
            headers: authHeaders(),
            body: JSON.stringify({ device_id: getOrCreateDeviceId() }),
        });

        if (!syncRes.ok) {
            showBanner('Sync failed. Will retry when connection is stable.', 'danger');
            return;
        }

        saveQueue([]);
        updateSyncStatus();
        localStorage.setItem("last_sync_time", new Date());
        const data = await syncRes.json();
        const conflicts = data.conflicts ?? [];
        const errors    = data.errors    ?? [];

        // If on a topic page — reload the chat from server (includes the synced message)
        const chat = document.getElementById('waChat');
        if (chat) {
            const topicId = chat.dataset.topicId;
            const exportArea = document.getElementById('chatExportArea');
            const messagesEl = document.getElementById('chatMessages');
            if (topicId && exportArea) {
                const postsRes = await fetch(`/topics/${topicId}/posts-fragment`, {
                    headers: { 'Accept': 'text/html', 'X-Requested-With': 'XMLHttpRequest' }
                }).catch(() => null);

                if (postsRes && postsRes.ok) {
                    exportArea.innerHTML = await postsRes.text();
                    if (messagesEl) messagesEl.scrollTop = messagesEl.scrollHeight;
                } else {
                    // Fragment failed — just upgrade the ticks so message stays visible
                    document.querySelectorAll('[data-pending-id]').forEach((msg) => {
                        const tick = msg.querySelector('.msg-tick--pending');
                        if (tick) {
                            tick.classList.replace('msg-tick--pending', 'msg-tick--sent');
                            tick.title = 'Sent';
                            tick.innerHTML = '&#10003;&#10003;';
                        }
                        msg.removeAttribute('data-pending-id');
                    });
                }
            }
        } else {
            // Not on chat page — just upgrade ticks
            document.querySelectorAll('[data-pending-id]').forEach((msg) => {
                const tick = msg.querySelector('.msg-tick--pending');
                if (tick) {
                    tick.classList.replace('msg-tick--pending', 'msg-tick--sent');
                    tick.title = 'Sent';
                    tick.innerHTML = '&#10003;&#10003;';
                }
                msg.removeAttribute('data-pending-id');
            });
        }

        // Refresh latest posts on dashboard
        const latestPostsCard = document.getElementById('latest-posts-list');
        if (latestPostsCard) {
            const dpRes = await fetch('/dashboard/latest-posts', {
                headers: { 'Accept': 'text/html', 'X-Requested-With': 'XMLHttpRequest' }
            }).catch(() => null);
            if (dpRes && dpRes.ok) latestPostsCard.innerHTML = await dpRes.text();
        }

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

    } catch {
        showBanner('Sync failed. Will retry when connection is stable.', 'danger');
    }
}

window.addEventListener('offline', () => {
    showBanner("You're offline. Actions will be saved and synced when you reconnect.");
    localStorage.setItem("last_sync_time", new Date());
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

    // Init network toggle button
    const stored = localStorage.getItem('sf_network_forced');
    window._networkForced = stored === 'false' ? false : null;

    // Apply stored state to button on page load
    if (window._networkForced === false) {
        const btn = document.getElementById('networkToggleBtn');
        const icon = document.getElementById('networkToggleIcon');
        const text = document.getElementById('networkToggleText');
        if (btn) {
            btn.classList.replace('btn-outline-success', 'btn-outline-danger');
            btn.classList.add('active');
        }
        if (icon) icon.className = 'bi bi-wifi-off';
        if (text) text.textContent = 'Offline';
        showBanner("You're offline. Actions will be saved and synced when you reconnect.");
    }

    window._toggleNetwork = function () {
        const btn = document.getElementById('networkToggleBtn');
        const icon = document.getElementById('networkToggleIcon');
        const text = document.getElementById('networkToggleText');

        if (window._networkForced !== false) {
            // Go offline
            window._networkForced = false;
            localStorage.setItem('sf_network_forced', 'false');
            btn.classList.replace('btn-outline-success', 'btn-outline-danger');
            btn.classList.add('active');
            icon.className = 'bi bi-wifi-off';
            if (text) text.textContent = 'Offline';
            showBanner("You're offline. Actions will be saved and synced when you reconnect.");
            updateSyncStatus();
        } else {
            // Go online
            window._networkForced = null;
            localStorage.removeItem('sf_network_forced');
            btn.classList.replace('btn-outline-danger', 'btn-outline-success');
            btn.classList.remove('active');
            icon.className = 'bi bi-wifi';
            if (text) text.textContent = 'Online';
            showBanner('Reconnected. Syncing…', 'info');
            updateSyncStatus();
            registerDevice().then(() => flushQueue());
        }
    };
}
// ------------------------------
// Dashboard Sync Card
// ------------------------------

function updateSyncStatus() {
    const status = document.getElementById('sync-status');
    const pending = document.getElementById('pending-count');
    const lastSync = document.getElementById('last-sync');

    const isOnline = window._networkForced !== false && navigator.onLine;

    if (status) {
        status.innerHTML = isOnline ? "🟢 Online" : "🔴 Offline";
        status.className = isOnline ? "badge bg-success" : "badge bg-danger";
    }

    if (pending) {
        pending.innerHTML = getQueue().length;
    }

    if (lastSync) {
        const time = localStorage.getItem("last_sync_time");
        if (time) lastSync.innerHTML = new Date(time).toLocaleString();
    }
}

window.addEventListener("online", updateSyncStatus);

window.addEventListener("offline", updateSyncStatus);

document.addEventListener("DOMContentLoaded", () => {

    updateSyncStatus();

    const button = document.getElementById("sync-now-btn");

    if (!button) return;

    button.addEventListener("click", async () => {

        button.disabled = true;
        button.innerHTML = "Synchronizing...";

        await registerDevice();
        await flushQueue();

        localStorage.setItem("last_sync_time", new Date());
        updateSyncStatus();

        button.disabled = false;
        button.innerHTML = "✓ Sync Complete";

        setTimeout(() => {
            button.innerHTML = "Sync Now";
        }, 2500);

    });

});