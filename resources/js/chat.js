// WhatsApp-style chat interactions

import { isStableOnline, notifyOfflineActionBlocked } from './offline';
import { bindShareButtons } from './share';

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

export function buildMessageHtml(post) {
    const chatRoot = document.getElementById('waChat');
    const postsBase = (chatRoot?.dataset.postsBaseUrl || '/posts').replace(/\/$/, '');
    const editUrl = post.edit_url || `${postsBase}/${post.id}/edit`;
    const destroyUrl = post.destroy_url || `${postsBase}/${post.id}`;
    const mine = post.is_mine ? 'mine' : 'theirs';
    const nameBlock = post.is_mine ? '' : `<div class="wa-bubble-name">${escapeHtml(post.user_name)}</div>`;
    let quoteBlock = '';
    if (post.parent) {
        quoteBlock = `
            <div class="wa-quote reply-quote" data-scroll-to="msg-${post.parent.id}">
                <div class="wa-quote-author">${escapeHtml(post.parent.user_name)}</div>
                <p class="wa-quote-text">${escapeHtml(post.parent.content.substring(0, 120))}</p>
            </div>`;
    }
    const actions = post.is_mine
        ? `<a href="${escapeHtml(editUrl)}" class="wa-action-btn" title="Edit"><i class="bi bi-pencil-fill"></i></a>
           <form action="${escapeHtml(destroyUrl)}" method="POST" class="d-inline" data-post-delete="${post.id}" onsubmit="return confirm('Delete this message?')">
               <input type="hidden" name="_token" value="${document.querySelector('meta[name="csrf-token"]')?.content || ''}">
               <input type="hidden" name="_method" value="DELETE">
               <button type="submit" class="wa-action-btn" title="Delete"><i class="bi bi-trash-fill"></i></button>
           </form>`
        : `<button type="button" class="wa-action-btn report-btn" data-post="${post.id}" title="Report as irrelevant">
               <i class="bi bi-flag-fill"></i>
           </button>`;
    const hiddenBadge = post.is_mine && post.hidden_count > 0
        ? `<span class="wa-hidden-badge" title="Hidden from ${post.hidden_count} member(s)"><i class="bi bi-eye-slash"></i> ${post.hidden_count}</span>`
        : '';
    return `
        <div class="wa-msg ${mine}" id="msg-${post.id}" data-msg-id="${post.id}">
            <div class="wa-bubble-wrap">
                <div class="wa-bubble">
                    <div class="wa-bubble-actions">
                        <button type="button" class="wa-action-btn copy-btn" title="Copy">
                            <i class="bi bi-clipboard"></i>
                        </button>
                        <button type="button" class="wa-action-btn reply-btn"
                                data-post="${post.id}"
                                data-user="${escapeHtml(post.user_name)}"
                                data-content="${escapeHtml(post.content.substring(0, 80))}"
                                title="Reply">
                            <i class="bi bi-reply-fill"></i>
                        </button>
                        <button type="button" class="wa-action-btn share-btn" data-post="${post.id}" title="Share">
                            <i class="bi bi-share-fill"></i>
                        </button>
                        ${actions}
                    </div>
                    ${nameBlock}
                    ${quoteBlock}
                    <p class="wa-bubble-text">${escapeHtml(post.content)}</p>
                    <div class="wa-bubble-meta">
                        ${hiddenBadge}
                        <span class="wa-bubble-time">${escapeHtml(post.created_at)}</span>
                        <span class="msg-tick msg-tick--sent" title="Sent">&#10003;&#10003;</span>
                    </div>
                </div>
            </div>
        </div>`;
}

(function () {
    const chat = document.getElementById('waChat');
    if (!chat) return;

    const messagesEl = document.getElementById('chatMessages');
    const form = document.getElementById('chatForm');
    const input = document.getElementById('messageInput');
    const sendBtn = document.getElementById('sendBtn');
    const replyPreview = document.getElementById('replyPreview');
    const replyUser = document.getElementById('replyUser');
    const replyText = document.getElementById('replyText');
    const parentInput = document.getElementById('Parent_Post_ID');
    const cancelReply = document.getElementById('cancelReply');
    const excludeToggle = document.getElementById('excludeToggle');
    const excludePanel = document.getElementById('excludePanel');
    const storeUrl = chat.dataset.storeUrl;
    const postsFragmentUrl = chat.dataset.postsFragmentUrl;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const topicTitle = chat.dataset.topicTitle || 'Discussion';
    const topicUrl = chat.dataset.topicUrl || window.location.href.split('#')[0];
    let messageRefreshSeq = 0;

    function scrollToBottom() {
        if (messagesEl) messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    function autoGrow(el) {
        el.style.height = 'auto';
        el.style.height = Math.min(el.scrollHeight, 120) + 'px';
    }

    function clearReply() {
        if (replyPreview) replyPreview.classList.add('d-none');
        if (parentInput) parentInput.value = '';
    }

    function setReply(postId, user, content) {
        if (replyPreview) replyPreview.classList.remove('d-none');
        if (replyUser) replyUser.textContent = user;
        if (replyText) replyText.textContent = content;
        if (parentInput) parentInput.value = postId;
        if (input) input.focus();
    }

    function clearExcludeSelections() {
        form?.querySelectorAll('.exclude-user-checkbox:checked').forEach((cb) => { cb.checked = false; });
        if (excludePanel) excludePanel.classList.add('d-none');
        if (excludeToggle) excludeToggle.classList.remove('active');
    }

    function collectExcludedUsers() {
        if (!form) return [];
        return [...form.querySelectorAll('.exclude-user-checkbox:checked')]
            .map((cb) => Number(cb.value))
            .filter((id) => Number.isInteger(id) && id > 0);
    }

    function copyTextSync(text) {
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.setAttribute('readonly', '');
        textarea.style.position = 'fixed';
        textarea.style.top = '0';
        textarea.style.left = '0';
        textarea.style.opacity = '0';
        textarea.style.pointerEvents = 'none';
        document.body.appendChild(textarea);
        textarea.focus();
        textarea.select();
        textarea.setSelectionRange(0, text.length);

        let copied = false;
        try {
            copied = document.execCommand('copy');
        } catch {
            copied = false;
        }

        document.body.removeChild(textarea);
        return copied;
    }

    function copyText(text, btn) {
        if (!text) return;

        const done = () => {
            const original = btn.title;
            btn.title = 'Copied!';
            setTimeout(() => { btn.title = original; }, 1500);
        };

        if (copyTextSync(text)) {
            done();
            return;
        }

        if (navigator.clipboard?.writeText) {
            navigator.clipboard.writeText(text).then(done).catch(() => copyTextSync(text) && done());
        }
    }

    function bindCopyButtons() {
        const area = document.getElementById('chatExportArea');
        if (!area || area.dataset.copyBound) return;
        area.dataset.copyBound = '1';

        area.addEventListener('mousedown', (event) => {
            const btn = event.target.closest('.copy-btn');
            if (!btn) return;

            event.preventDefault();
            event.stopPropagation();

            const bubble = btn.closest('.wa-bubble');
            const textEl = bubble?.querySelector('.wa-bubble-text');
            const text = (textEl?.innerText || textEl?.textContent || '').trim();
            copyText(text, btn);
        }, true);
    }

    function bindReplyButtons(scope) {
        (scope || document).querySelectorAll('.reply-btn').forEach((btn) => {
            if (btn.dataset.bound) return;
            btn.dataset.bound = '1';
            btn.addEventListener('click', function () {
                setReply(this.dataset.post, this.dataset.user, this.dataset.content);
            });
        });
    }

    async function reportPost(postId) {
        const reportBaseUrl = chat.dataset.reportUrl;
        if (!reportBaseUrl || !postId) return;

        const reason = window.prompt(
            'Why is this message irrelevant? (optional)',
            ''
        );
        if (reason === null) return;

        try {
            const response = await fetch(`${reportBaseUrl}/${postId}/report`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf || '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ reason }),
            });
            const data = await response.json().catch(() => ({}));

            if (!response.ok) {
                const message = data.message
                    || data.errors?.post?.[0]
                    || 'Could not report this message.';
                window.alert(message);
                return;
            }

            const messageRow = document.getElementById(`msg-${postId}`);
            messageRow?.remove();

            const exportArea = document.getElementById('chatExportArea');
            if (exportArea && !exportArea.querySelector('.wa-msg')) {
                exportArea.innerHTML = `
                    <div class="wa-empty" id="chatEmpty">
                        <i class="bi bi-chat-dots"></i>
                        <p class="mb-0">No messages yet. Start the conversation below.</p>
                    </div>`;
            }

            window.alert(data.message || 'Message reported. A group admin will review it shortly.');
        } catch {
            window.alert('Could not report this message. Check your connection and try again.');
        }
    }

    function bindReportButtons() {
        const area = document.getElementById('chatExportArea');
        if (!area || area.dataset.reportBound) return;
        area.dataset.reportBound = '1';

        area.addEventListener('click', (event) => {
            const btn = event.target.closest('.report-btn');
            if (!btn) return;

            event.preventDefault();
            event.stopPropagation();
            reportPost(btn.dataset.post);
        });
    }

    function bindQuoteScroll(scope) {
        (scope || document).querySelectorAll('.reply-quote').forEach((quote) => {
            if (quote.dataset.bound) return;
            quote.dataset.bound = '1';
            quote.addEventListener('click', function () {
                const target = document.getElementById(this.dataset.scrollTo);
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    target.style.transition = 'background 0.3s';
                    target.style.background = 'rgba(22, 163, 74, 0.12)';
                    setTimeout(() => { target.style.background = ''; }, 1200);
                }
            });
        });
    }

    function getMessageFingerprint(scope) {
        return [...scope.querySelectorAll('.wa-msg[data-msg-id]')]
            .map((el) => {
                const id = el.dataset.msgId;
                const text = el.querySelector('.wa-bubble-text')?.textContent?.trim() ?? '';
                return `${id}:${text}`;
            })
            .join('|');
    }

    function updateMessageCount(count) {
        const subtitle = chat.querySelector('.wa-chat-subtitle');
        if (!subtitle) return;
        const parts = subtitle.textContent.split('•');
        if (parts.length < 2) return;
        subtitle.textContent = `${parts[0].trim()} • ${count} messages`;
    }

    async function refreshMessagesFromServer() {
        if (!isStableOnline()) return;

        const exportArea = document.getElementById('chatExportArea');
        const messagesEl = document.getElementById('chatMessages');
        const fragmentUrl = postsFragmentUrl || null;
        if (!fragmentUrl || !exportArea) return;

        const previousFingerprint = getMessageFingerprint(exportArea);
        const previousCount = exportArea.querySelectorAll('.wa-msg[data-msg-id]').length;
        const seq = ++messageRefreshSeq;

        try {
            const postsRes = await fetch(fragmentUrl, {
                headers: {
                    'Accept': 'text/html',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                cache: 'no-store',
            });

            if (!postsRes.ok || seq !== messageRefreshSeq) return;

            const html = await postsRes.text();
            // Wrong host/path often returns a full HTML document (login page, other app).
            if (/<!DOCTYPE|<html[\s>]/i.test(html)) return;

            const temp = document.createElement('div');
            temp.innerHTML = html;
            const newMsgs = [...temp.querySelectorAll('.wa-msg[data-msg-id]')];
            const newIds = newMsgs.map((el) => el.dataset.msgId);
            const newFingerprint = newMsgs
                .map((el) => `${el.dataset.msgId}:${el.querySelector('.wa-bubble-text')?.textContent?.trim() ?? ''}`)
                .join('|');
            if (newFingerprint === previousFingerprint) return;

            // Do not wipe an existing conversation with an empty/foreign payload.
            if (newIds.length === 0 && previousCount > 0 && html.trim() !== '') return;

            const scrollNearBottom = messagesEl
                && (messagesEl.scrollHeight - messagesEl.scrollTop - messagesEl.clientHeight < 80);

            if (newIds.length === 0) {
                exportArea.innerHTML = `
                    <div class="wa-empty" id="chatEmpty">
                        <i class="bi bi-chat-dots"></i>
                        <p class="mb-0">No messages yet. Start the conversation below.</p>
                    </div>`;
            } else {
                exportArea.innerHTML = html;
                bindReplyButtons(exportArea);
                bindShareButtons(exportArea, topicTitle, topicUrl);
                bindQuoteScroll(exportArea);
                bindDeleteForms(exportArea);
            }

            updateMessageCount(newIds.length);

            if (scrollNearBottom) scrollToBottom();
        } catch {
            // ignore transient network errors during polling
        }
    }

    function bindLongPressActions() {
        const area = document.getElementById('chatExportArea');
        if (!area || area.dataset.longPressBound) return;
        area.dataset.longPressBound = '1';

        let pressTimer = null;

        const clearPress = () => {
            if (pressTimer) {
                window.clearTimeout(pressTimer);
                pressTimer = null;
            }
        };

        const closeAllActions = (except = null) => {
            area.querySelectorAll('.wa-msg.wa-actions-open').forEach((el) => {
                if (el !== except) el.classList.remove('wa-actions-open');
            });
        };

        area.addEventListener('touchstart', (event) => {
            if (event.target.closest('.wa-bubble-actions')) return;
            const msg = event.target.closest('.wa-msg');
            if (!msg) return;

            clearPress();
            pressTimer = window.setTimeout(() => {
                closeAllActions(msg);
                msg.classList.add('wa-actions-open');
                if (navigator.vibrate) {
                    try { navigator.vibrate(12); } catch { /* ignore */ }
                }
            }, 450);
        }, { passive: true });

        area.addEventListener('touchend', clearPress, { passive: true });
        area.addEventListener('touchmove', clearPress, { passive: true });
        area.addEventListener('touchcancel', clearPress, { passive: true });

        area.addEventListener('click', (event) => {
            if (event.target.closest('.wa-bubble-actions')) return;
            closeAllActions();
        });

        document.addEventListener('click', (event) => {
            if (event.target.closest('#chatExportArea')) return;
            closeAllActions();
        });
    }

    let messagePollTimer = null;

    function startMessagePolling() {
        if (messagePollTimer) return;
        messagePollTimer = window.setInterval(refreshMessagesFromServer, 5000);
    }

    function stopMessagePolling() {
        if (!messagePollTimer) return;
        window.clearInterval(messagePollTimer);
        messagePollTimer = null;
    }

    startMessagePolling();
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            stopMessagePolling();
            return;
        }
        refreshMessagesFromServer();
        startMessagePolling();
    });
    window.addEventListener('focus', refreshMessagesFromServer);

    if (input) {
        input.addEventListener('input', () => autoGrow(input));
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                form?.requestSubmit();
            }
        });
        autoGrow(input);
    }

    if (cancelReply) cancelReply.addEventListener('click', clearReply);

    if (excludeToggle && excludePanel) {
        excludeToggle.addEventListener('click', () => {
            excludePanel.classList.toggle('d-none');
            excludeToggle.classList.toggle('active');
        });
    }

    bindReplyButtons();
    bindReportButtons();
    bindCopyButtons();
    bindShareButtons(chat, topicTitle, topicUrl);
    bindDeleteForms(chat);
    bindLongPressActions();
    bindQuoteScroll();
    scrollToBottom();

    const hashTarget = window.location.hash?.replace('#', '');
    if (hashTarget && hashTarget.startsWith('msg-')) {
        const target = document.getElementById(hashTarget);
        if (target) {
            setTimeout(() => {
                target.scrollIntoView({ behavior: 'smooth', block: 'center' });
                target.style.transition = 'background 0.3s';
                target.style.background = 'rgba(22, 163, 74, 0.12)';
                setTimeout(() => { target.style.background = ''; }, 1200);
            }, 150);
        }
    }

    function bindDeleteForms(scope) {
        scope.querySelectorAll('form[data-post-delete]').forEach((form) => {
            if (form.dataset.deleteBound === '1') return;
            form.dataset.deleteBound = '1';
            form.addEventListener('submit', (event) => {
                if (isStableOnline()) return;
                event.preventDefault();
                notifyOfflineActionBlocked();
            });
        });
    }

    if (form) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            if (!isStableOnline()) {
                notifyOfflineActionBlocked();
                return;
            }

            const content = input?.value?.trim();
            if (!content) return;

            sendBtn.disabled = true;
            const formData = new FormData(form);

            try {
                const res = await fetch(storeUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: formData,
                });

                if (!res.ok) {
                    window.alert('Could not send your message. Check your connection and try again.');
                    return;
                }

                const data = await res.json();
                const exportArea = document.getElementById('chatExportArea');
                const empty = document.getElementById('chatEmpty');
                if (empty) empty.remove();

                const wrapper = document.createElement('div');
                wrapper.innerHTML = buildMessageHtml(data.post);
                const msgEl = wrapper.firstElementChild;
                exportArea?.appendChild(msgEl);

                bindReplyButtons(msgEl);
                bindShareButtons(msgEl, topicTitle, topicUrl);
                bindQuoteScroll(msgEl);
                bindDeleteForms(msgEl);

                input.value = '';
                autoGrow(input);
                clearReply();
                clearExcludeSelections();
                scrollToBottom();

                // Keep the optimistic message; next poll uses the prefixed fragment URL.
                refreshMessagesFromServer();
            } catch {
                window.alert('Could not send your message. Check your connection and try again.');
            } finally {
                sendBtn.disabled = false;
            }
        });
    }

    const exportButton = document.getElementById('exportPdf');
    if (exportButton && typeof html2pdf !== 'undefined') {
        exportButton.addEventListener('click', function () {
            const topicTitle = chat.querySelector('.wa-chat-title')?.textContent || 'discussion';
            const groupName = chat.querySelector('.wa-chat-subtitle')?.textContent || '';
            const wrapper = document.createElement('div');
            wrapper.style.padding = '16px';
            wrapper.innerHTML = `
                <h2 style="margin:0 0 4px;font-size:18px;">${topicTitle}</h2>
                <p style="margin:0 0 16px;color:#666;font-size:13px;">${groupName} — Exported ${new Date().toLocaleString()}</p>
            `;
            const clone = document.getElementById('chatExportArea')?.cloneNode(true);
            if (clone) {
                clone.querySelectorAll('.wa-bubble-actions').forEach((el) => el.remove());
                wrapper.appendChild(clone);
            }
            html2pdf().set({
                margin: 10,
                filename: `discussion-${topicTitle.replace(/[^a-z0-9]/gi, '-').toLowerCase()}.pdf`,
                image: { type: 'jpeg', quality: 0.95 },
                html2canvas: { scale: 2, useCORS: true },
                jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
            }).from(wrapper).save();
        });
    }
})();
