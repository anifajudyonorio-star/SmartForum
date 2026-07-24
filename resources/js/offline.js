/**
 * Offline sync module
 */

const QUEUE_KEY = 'sf_offline_queue';
const DEVICE_KEY = 'sf_device_id';

const FAILURES_FOR_OFFLINE = 3;
const ONLINE_PROBE_MS = 10000;
const RECONNECT_PROBE_MS = 2000;

window._sfStableOnline = true;
let consecutiveFailures = 0;
let connectivityProbeTimer = null;

export function isStableOnline() {
    if (window._networkForced === false) return false;
    return window._sfStableOnline !== false;
}

async function probeServerReachable() {
    if (!navigator.onLine) return false;
    try {
        const res = await fetch('/up', {
            method: 'HEAD',
            cache: 'no-store',
            credentials: 'same-origin',
            signal: AbortSignal.timeout(5000),
        });
        return res.ok;
    } catch {
        return false;
    }
}

function applyStableConnectivity(reachable) {
    const previous = window._sfStableOnline;

    if (reachable) {
        consecutiveFailures = 0;
        if (!window._sfStableOnline) {
            window._sfStableOnline = true;
        }
    } else {
        consecutiveFailures += 1;
        if (window._sfStableOnline && consecutiveFailures >= FAILURES_FOR_OFFLINE) {
            window._sfStableOnline = false;
        }
    }

    if (previous !== window._sfStableOnline) {
        updateSyncStatus();
        restartConnectivityProbe();
        if (window._sfStableOnline) {
            showBanner('Reconnected. Syncing…', 'info');
            registerDevice().then(() => flushQueue()).catch(() => {});
        } else {
            showBanner("You're offline. Actions will be saved and synced when you reconnect.");
        }
    }
}

async function onNetworkMaybeBack() {
    if (window._networkForced === false) return;
    const reachable = await probeServerReachable();
    const wasOffline = !window._sfStableOnline;
    applyStableConnectivity(reachable);
    if (reachable && getQueue().length > 0 && !wasOffline) {
        await registerDevice();
        await flushQueue();
    }
}

async function runConnectivityProbe() {
    if (window._networkForced === false) return;
    applyStableConnectivity(await probeServerReachable());
}

function restartConnectivityProbe() {
    if (connectivityProbeTimer) {
        window.clearInterval(connectivityProbeTimer);
        connectivityProbeTimer = null;
    }
    const interval = window._sfStableOnline ? ONLINE_PROBE_MS : RECONNECT_PROBE_MS;
    connectivityProbeTimer = window.setInterval(runConnectivityProbe, interval);
}

function startConnectivityProbe() {
    if (connectivityProbeTimer) return;
    runConnectivityProbe();
    restartConnectivityProbe();
}

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
    try {
        const parsed = JSON.parse(localStorage.getItem(QUEUE_KEY) || '[]');
        if (!Array.isArray(parsed)) return [];

        let upgraded = false;
        const queue = parsed.map((action) => {
            if (action.action_uuid) return action;
            upgraded = true;
            return {
                ...action,
                action_uuid: crypto.randomUUID(),
                status: action.status ?? 'pending',
                last_error: action.last_error ?? null,
            };
        });
        if (upgraded) localStorage.setItem(QUEUE_KEY, JSON.stringify(queue));
        return queue;
    } catch {
        return [];
    }
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
    const queue = getQueue();
    const existing = actionType === 'submit_quiz'
        ? queue.find((action) =>
            action.action_type === 'submit_quiz'
            && String(action.payload?.attempt_id) === String(payload?.attempt_id)
        )
        : null;

    if (existing) {
        showBanner('This quiz submission is already queued. It will not be queued twice.');
        return existing.action_uuid;
    }

    const actionUuid = crypto.randomUUID();
    const pendingId = 'p-' + Date.now() + '-' + Math.random().toString(36).slice(2, 7);
    queue.push({
        action_uuid: actionUuid,
        action_type: actionType,
        payload,
        queued_at: Date.now(),
        pendingId,
        status: 'pending',
        last_error: null,
    });
    saveQueue(queue);
    if (pendingEl) pendingEl.dataset.pendingId = pendingId;
    const count = queue.length;
    showBanner(`You're offline. ${count} action${count > 1 ? 's' : ''} queued — will sync when reconnected.`);
    return actionUuid;
}

function mergeActionResults(queue, results) {
    const byUuid = new Map((results ?? []).map((result) => [result.action_uuid, result]));

    return queue
        .filter((action) => {
            const result = byUuid.get(action.action_uuid);
            return !result || !['succeeded', 'duplicate'].includes(result.status);
        })
        .map((action) => {
            const result = byUuid.get(action.action_uuid);
            if (!result) return action;

            return {
                ...action,
                status: result.status === 'failed' ? 'failed' : 'pending',
                last_error: result.reason ?? null,
            };
        });
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
    let queue = getQueue();
    if (!queue.length) return;
    if (window._networkForced === false) return; // forced offline
    if (!isStableOnline()) return;
    if (!await ensureToken()) return;

    try {
        const uploadRes = await fetch('/api/sync/upload', {
            method: 'POST',
            headers: authHeaders(),
            body: JSON.stringify({ actions: queue }),
        });

        const uploadData = await uploadRes.json().catch(() => ({}));
        if (!uploadRes.ok) {
            const message = uploadData.message ?? 'Offline actions were rejected by the server.';
            showBanner(`${message} Nothing was removed from your offline queue.`, 'danger');
            return;
        }

        const originalQueue = queue;
        queue = mergeActionResults(queue, uploadData.actions);
        saveQueue(queue);

        if (!queue.length) {
            updateAcknowledgedMessages(originalQueue, queue);
            updateSyncStatus();
            localStorage.setItem('last_sync_time', new Date());
            showBanner('Previously synchronized actions were acknowledged.', 'success');
            return;
        }

        const syncRes = await fetch('/api/sync', {
            method: 'POST',
            headers: authHeaders(),
            body: JSON.stringify({ device_id: getOrCreateDeviceId() }),
        });

        if (!syncRes.ok) {
            showBanner('Sync failed. Will retry when connection is stable.', 'danger');
            return;
        }

        const data = await syncRes.json();
        const beforeSync = queue;
        queue = mergeActionResults(queue, data.actions);
        saveQueue(queue);
        updateSyncStatus();
        localStorage.setItem('last_sync_time', new Date());
        const conflicts = data.conflicts ?? [];
        const errors    = data.errors    ?? [];
        updateAcknowledgedMessages(originalQueue, queue);

        // If on a topic page — reload the chat from server (includes the synced message)
        const chat = document.getElementById('waChat');
        const acknowledgedAny = beforeSync.length !== queue.length;
        if (chat && acknowledgedAny) {
            const topicId = chat.dataset.topicId;
            const exportArea = document.getElementById('chatExportArea');
            const messagesEl = document.getElementById('chatMessages');
            if (topicId && exportArea) {
                const postsRes = await fetch(`/topics/${topicId}/posts-fragment`, {
                    headers: { 'Accept': 'text/html', 'X-Requested-With': 'XMLHttpRequest' }
                }).catch(() => null);

                if (postsRes && postsRes.ok) {
                    const stillPending = [...exportArea.querySelectorAll('[data-pending-id]')];
                    exportArea.innerHTML = await postsRes.text();
                    stillPending.forEach((el) => exportArea.appendChild(el));
                    if (messagesEl) messagesEl.scrollTop = messagesEl.scrollHeight;
                }
            }
        }

        // Refresh latest posts on dashboard
        const latestPostsCard = document.getElementById('latest-posts-list');
        if (latestPostsCard) {
            const dpRes = await fetch('/dashboard/latest-posts', {
                headers: { 'Accept': 'text/html', 'X-Requested-With': 'XMLHttpRequest' }
            }).catch(() => null);
            if (dpRes && dpRes.ok) latestPostsCard.innerHTML = await dpRes.text();
        }

        const failed = queue.filter((action) => action.status === 'failed');
        if (failed.length || conflicts.length) {
            const reasons = failed
                .map((action) => action.last_error)
                .filter(Boolean)
                .join(' | ');
            showBanner(
                `Some actions need attention and remain queued${reasons ? `: ${reasons}` : '.'}`,
                'warning',
            );
        } else if (errors.length || queue.length) {
            showBanner('Some actions were not acknowledged and remain queued for retry.', 'danger');
        } else {
            showBanner('Back online — offline actions synced!', 'success');
            setTimeout(hideBanner, 3000);
        }

    } catch {
        showBanner('Sync failed. Will retry when connection is stable.', 'danger');
    }
}

function updateAcknowledgedMessages(previousQueue, remainingQueue) {
    const remainingUuids = new Set(remainingQueue.map((action) => action.action_uuid));
    const acknowledgedPendingIds = previousQueue
        .filter((action) => !remainingUuids.has(action.action_uuid))
        .map((action) => action.pendingId)
        .filter(Boolean);

    document.querySelectorAll('[data-pending-id]').forEach((message) => {
        if (!acknowledgedPendingIds.includes(message.dataset.pendingId)) return;

        const tick = message.querySelector('.msg-tick--pending');
        if (tick) {
            tick.classList.replace('msg-tick--pending', 'msg-tick--sent');
            tick.title = 'Sent';
            tick.innerHTML = '&#10003;&#10003;';
        }
        message.removeAttribute('data-pending-id');
    });
}

window.addEventListener('offline', () => {
    onNetworkMaybeBack();
});

window.addEventListener('online', () => {
    onNetworkMaybeBack();
});

export async function initOfflineSync() {
    if (!document.querySelector('meta[name="notifications-poll-url"]')) return;

    const stored = localStorage.getItem('sf_network_forced');
    window._networkForced = stored === 'false' ? false : null;

    await registerDevice();
    startConnectivityProbe();

    if (window._networkForced !== false && isStableOnline() && getQueue().length) {
        await flushQueue();
    }

    if (!isStableOnline()) {
        showBanner("You're offline. Actions will be saved and synced when you reconnect.");
    }

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
            window._sfStableOnline = true;
            consecutiveFailures = 0;
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

    const isOnline = isStableOnline();

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