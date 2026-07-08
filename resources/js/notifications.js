// WhatsApp-style live notification popups (polling)
(function () {
    const pollUrl = document.querySelector('meta[name="notifications-poll-url"]')?.content;
    if (!pollUrl) return;

    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    let lastId = parseInt(document.querySelector('meta[name="notifications-last-id"]')?.content || '0', 10);
    let stack = document.getElementById('notifToastStack');

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
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function iconForType(type) {
        if (type === 'reply') return 'bi-reply-fill';
        if (type === 'PostCreated') return 'bi-chat-left-text-fill';
        return 'bi-bell-fill';
    }

    function dismissToast(toast) {
        toast.classList.add('wa-notif-toast-hide');
        setTimeout(() => toast.remove(), 280);
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
                await fetch(`/notifications/${notification.id}/read`, {
                    method: 'PATCH',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
            } catch {
                // still navigate
            }
            window.location.href = notification.url;
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
        } catch {
            // ignore network errors during polling
        }
    }

    updateBadges(parseInt(document.querySelector('meta[name="notifications-unread"]')?.content || '0', 10));

    poll();
    setInterval(poll, 12000);
})();
