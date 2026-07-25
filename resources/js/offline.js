/**
 * Web connectivity monitoring only.
 * Req 8: offline saved data and sync are desktop-only — the web app requires a live connection.
 */

const FAILURES_FOR_OFFLINE = 3;
const ONLINE_PROBE_MS = 10000;
const RECONNECT_PROBE_MS = 2000;

export const OFFLINE_WEB_MESSAGE =
    'You are offline. The web app requires an internet connection. '
    + 'Use the SmartForum desktop app to access saved data and sync when you reconnect.';

window._sfStableOnline = true;
let consecutiveFailures = 0;
let connectivityProbeTimer = null;

export function isStableOnline() {
    return window._sfStableOnline !== false && navigator.onLine;
}

export function showOfflineBanner(message = OFFLINE_WEB_MESSAGE) {
    const banner = document.getElementById('offlineBanner');
    if (!banner) return;
    banner.textContent = message;
    banner.className = 'offline-banner offline-banner-warning show';
}

export function hideOfflineBanner() {
    const banner = document.getElementById('offlineBanner');
    if (banner) banner.classList.remove('show');
}

export function notifyOfflineActionBlocked() {
    showOfflineBanner();
    window.alert(OFFLINE_WEB_MESSAGE);
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
        window._sfStableOnline = true;
    } else {
        consecutiveFailures += 1;
        if (window._sfStableOnline && consecutiveFailures >= FAILURES_FOR_OFFLINE) {
            window._sfStableOnline = false;
        }
    }

    if (previous !== window._sfStableOnline) {
        if (window._sfStableOnline) {
            hideOfflineBanner();
        } else {
            showOfflineBanner();
        }
        restartConnectivityProbe();
    }
}

async function onNetworkChange() {
    applyStableConnectivity(await probeServerReachable());
}

function restartConnectivityProbe() {
    if (connectivityProbeTimer) {
        window.clearInterval(connectivityProbeTimer);
        connectivityProbeTimer = null;
    }

    const interval = window._sfStableOnline ? ONLINE_PROBE_MS : RECONNECT_PROBE_MS;
    connectivityProbeTimer = window.setInterval(async () => {
        applyStableConnectivity(await probeServerReachable());
    }, interval);
}

export function initWebConnectivity() {
    if (!document.querySelector('meta[name="notifications-poll-url"]')) return;

    window.addEventListener('offline', onNetworkChange);
    window.addEventListener('online', onNetworkChange);

    onNetworkChange();
    restartConnectivityProbe();
}
