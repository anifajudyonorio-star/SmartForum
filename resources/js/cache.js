/**
 * Offline read cache module
 */

const DB_NAME = 'sf-cache';
const DB_VER  = 1;
const STORE   = 'topics';

function openDb() {
    return new Promise((resolve, reject) => {
        const req = indexedDB.open(DB_NAME, DB_VER);
        req.onupgradeneeded = (e) => e.target.result.createObjectStore(STORE, { keyPath: 'id' });
        req.onsuccess  = (e) => resolve(e.target.result);
        req.onerror    = ()  => reject(req.error);
    });
}

async function dbPut(data) {
    const db = await openDb();
    return new Promise((resolve, reject) => {
        const tx = db.transaction(STORE, 'readwrite');
        tx.objectStore(STORE).put(data);
        tx.oncomplete = resolve;
        tx.onerror    = () => reject(tx.error);
    });
}

async function dbGet(id) {
    const db = await openDb();
    return new Promise((resolve, reject) => {
        const tx  = db.transaction(STORE, 'readonly');
        const req = tx.objectStore(STORE).get(id);
        req.onsuccess = () => resolve(req.result ?? null);
        req.onerror   = () => reject(req.error);
    });
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

function escHtml(str) {
    const d = document.createElement('div');
    d.textContent = str;
    return d.innerHTML;
}

function renderCachedPosts(cached) {
    const container = document.getElementById('chatMessages');
    if (!container) return;

    const composer = document.querySelector('.wa-composer');
    if (composer) composer.style.display = 'none';

    const exportArea = document.getElementById('chatExportArea') ?? container;

    if (!cached.posts.length) {
        exportArea.innerHTML = `<div class="wa-empty"><i class="bi bi-chat-dots"></i><p class="mb-0">No messages cached.</p></div>`;
        return;
    }

    const cachedAt = new Date(cached.cached_at).toLocaleString();

    exportArea.innerHTML = cached.posts.map((post) => {
        const mine   = post.is_mine ? 'mine' : 'theirs';
        const parent = post.parent
            ? `<div class="wa-reply-quote"><strong>${escHtml(post.parent.user_name)}</strong><span>${escHtml(post.parent.content)}</span></div>`
            : '';
        return `
            <div class="wa-message ${mine}">
                <div class="wa-bubble">
                    ${parent}
                    <p class="wa-bubble-text">${escHtml(post.content)}</p>
                    <span class="wa-bubble-time">${escHtml(post.created_at)}</span>
                </div>
                ${!post.is_mine ? `<span class="wa-sender">${escHtml(post.user_name)}</span>` : ''}
            </div>`;
    }).join('');

    exportArea.insertAdjacentHTML('beforeend',
        `<div class="chat-date-divider"><span>📦 Cached · ${escHtml(cachedAt)}</span></div>`
    );

    container.scrollTop = container.scrollHeight;
}

export async function initReadCache() {
    const chat = document.getElementById('waChat');
    if (!chat) return;

    const apiUrl  = chat.dataset.topicApiUrl;
    const topicId = parseInt(chat.dataset.topicId, 10);
    if (!apiUrl || !topicId) return;

    if (navigator.onLine) {
        const token = await getToken();
        if (!token) return;

        try {
            const res = await fetch(apiUrl, {
                headers: {
                    'Accept': 'application/json',
                    'Authorization': `Bearer ${token}`,
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            if (res.ok) {
                const data = await res.json();
                await dbPut({ id: topicId, ...data });
            }
        } catch { /* non-critical */ }
    } else {
        const cached = await dbGet(topicId).catch(() => null);
        if (cached) {
            renderCachedPosts(cached);
        } else {
            const container = document.getElementById('chatMessages');
            if (container) {
                container.innerHTML = `<div class="wa-empty"><i class="bi bi-wifi-off"></i><p class="mb-0">You're offline and this topic hasn't been cached yet. Visit it while online first.</p></div>`;
            }
            const composer = document.querySelector('.wa-composer');
            if (composer) composer.style.display = 'none';
        }
    }
}
