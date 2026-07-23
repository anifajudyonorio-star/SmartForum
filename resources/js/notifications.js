// Live notification popups + notifications page (desktop-style)
(function () {
    const pollUrl = document.querySelector('meta[name="notifications-poll-url"]')?.content;
    if (!pollUrl) return;

    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    let lastId = parseInt(document.querySelector('meta[name="notifications-last-id"]')?.content || '0', 10);
    let stack = document.getElementById('notifToastStack');
    const notificationsList = document.getElementById('notificationsList');
    const notificationsPageUrl = window.notificationsPageUrl || null;
    const onNotificationsPage = Boolean(notificationsList && notificationsPageUrl);

    if (!stack) {
        stack = document.createElement('div');
        stack.id = 'notifToastStack';
        stack.className = 'wa-notif-stack';
        stack.setAttribute('aria-live', 'polite');
        document.body.appendChild(stack);
    }

    function updateBadges(count) {
        document.querySelectorAll('[data-notif-badge]').forEach((badge) => {
            if (count > 0) {
                badge.textContent = count > 99 ? '99+' : count;
                badge.classList.remove('d-none');
            } else {
                badge.textContent = '';
                badge.classList.add('d-none');
            }
        });

        document.querySelectorAll('[data-notif-page-badge]').forEach((badge) => {
            if (count > 0) {
                badge.textContent = `${count} unread`;
                badge.classList.remove('d-none');
            } else {
                badge.textContent = '';
                badge.classList.add('d-none');
            }
        });

        const unreadMeta = document.querySelector('meta[name="notifications-unread"]');
        if (unreadMeta) {
            unreadMeta.content = String(count);
        }
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text ?? '';
        return div.innerHTML;
    }

    function iconForType(type) {
        if (type === 'Quiz') return 'bi-patch-question-fill';
        if (type === 'warning') return 'bi-exclamation-triangle-fill';
        if (type === 'PostCreated') return 'bi-chat-left-text-fill';
        if (type === 'reply') return 'bi-reply-fill';
        return 'bi-bell-fill';
    }

    function dismissToast(toast) {
        toast.classList.add('wa-notif-toast-hide');
        setTimeout(() => toast.remove(), 280);
    }

    async function markNotificationRead(id) {
        const res = await fetch(`/notifications/${id}/read`, {
            method: 'PATCH',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!res.ok) {
            throw new Error('Could not mark notification as read.');
        }

        return res.json();
    }

    function markRowAsRead(row) {
        row.classList.remove('unread');
        row.querySelector('.notif-unread-dot')?.remove();
    }

    function renderNotificationItem(notification) {
        const unread = !notification.is_read;
        const unreadDot = unread ? '<span class="notif-unread-dot" aria-hidden="true">●</span>' : '';

        return `
            <a href="${escapeHtml(notification.url)}"
               class="notif-item ${unread ? 'unread' : ''} fly-in fly-in-visible"
               data-notif-item
               data-notif-id="${notification.id}"
               data-notif-url="${escapeHtml(notification.url)}">
                <div class="notif-icon">
                    <i class="bi ${iconForType(notification.type)}"></i>
                </div>
                <div class="notif-body">
                    <div class="notif-item-top">
                        ${unreadDot}
                        <p class="notif-title">${escapeHtml(notification.title)}</p>
                        <span class="notif-time">${escapeHtml(notification.time)}</span>
                    </div>
                    <p class="notif-message">${escapeHtml(notification.message)}</p>
                </div>
            </a>
        `;
    }

    function bindNotificationItems(scope = document) {
        scope.querySelectorAll('[data-notif-item]').forEach((row) => {
            if (row.dataset.notifBound === 'true') {
                return;
            }
            row.dataset.notifBound = 'true';

            row.addEventListener('click', async (event) => {
                event.preventDefault();

                const id = row.dataset.notifId;
                const url = row.dataset.notifUrl;
                const isUnread = row.classList.contains('unread');

                try {
                    if (isUnread && id) {
                        const data = await markNotificationRead(id);
                        if (typeof data.unread_count === 'number') {
                            updateBadges(data.unread_count);
                        }
                        markRowAsRead(row);
                        window.location.href = data.url || url;
                        return;
                    }
                } catch {
                    // still navigate even if mark-read fails
                }

                window.location.href = url;
            });
        });
    }

    async function refreshNotificationsPage() {
        if (!onNotificationsPage) {
            return;
        }

        try {
            const res = await fetch(notificationsPageUrl, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!res.ok) {
                return;
            }

            const data = await res.json();

            if (typeof data.unread_count === 'number') {
                updateBadges(data.unread_count);
            }

            const items = data.notifications || [];
            if (items.length === 0) {
                notificationsList.innerHTML = `
                    <div class="groups-empty-state fly-in fly-in-visible" data-notif-empty>
                        <div class="groups-empty-icon"><i class="bi bi-bell-slash"></i></div>
                        <p class="text-muted mb-0">No notifications yet.</p>
                    </div>
                `;
                return;
            }

            notificationsList.innerHTML = items.map(renderNotificationItem).join('');
            bindNotificationItems(notificationsList);
        } catch {
            // ignore refresh errors
        }
    }

    function showToast(notification) {
        const toast = document.createElement('div');
        toast.className = 'wa-notif-toast';
        toast.innerHTML = `
            <div class="wa-notif-toast-icon">
                <i class="bi ${iconForType(notification.type)}"></i>
            </div>
            <div class="wa-notif-toast-body">
                <strong>${escapeHtml(notification.title)}</strong>
                <p>${escapeHtml(notification.message)}</p>
                <small>${escapeHtml(notification.time)}</small>
            </div>
            <button type="button" class="wa-notif-toast-close" aria-label="Dismiss">
                <i class="bi bi-x"></i>
            </button>
        `;

        const openNotification = async () => {
            try {
                const data = await markNotificationRead(notification.id);
                if (typeof data.unread_count === 'number') {
                    updateBadges(data.unread_count);
                }
                window.location.href = data.url || notification.url;
            } catch {
                window.location.href = notification.url;
            }
        };

        toast.addEventListener('click', (e) => {
            if (e.target.closest('.wa-notif-toast-close')) return;
            openNotification();
        });

        toast.querySelector('.wa-notif-toast-close')?.addEventListener('click', (e) => {
            e.stopPropagation();
            dismissToast(toast);
        });

        stack.prepend(toast);
        requestAnimationFrame(() => toast.classList.add('wa-notif-toast-show'));

        setTimeout(() => {
            if (toast.isConnected) dismissToast(toast);
        }, 6000);
    }

    async function poll() {
        try {
            const res = await fetch(`${pollUrl}?after=${lastId}`, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!res.ok) return;

            const data = await res.json();

            if (typeof data.unread_count === 'number') {
                updateBadges(data.unread_count);
            }

            if (data.latest_id && data.latest_id > lastId) {
                lastId = data.latest_id;
            }

            (data.notifications || []).slice().reverse().forEach((notification) => {
                if (notification.id > 0) {
                    showToast(notification);
                }
            });

            if (onNotificationsPage) {
                await refreshNotificationsPage();
            }
        } catch {
            // ignore network errors during polling
        }
    }

    updateBadges(parseInt(document.querySelector('meta[name="notifications-unread"]')?.content || '0', 10));
    bindNotificationItems();

    poll();
    setInterval(poll, 12000);
})();
