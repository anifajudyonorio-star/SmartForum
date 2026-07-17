// WhatsApp-style chat interactions

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

export function buildPendingBubble(content, pendingId) {
    const now = new Date().toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
    const div = document.createElement('div');
    div.innerHTML = `
        <div class="wa-msg mine" data-pending-id="${pendingId}">
            <div class="wa-bubble-wrap">
                <div class="wa-bubble">
                    <p class="wa-bubble-text">${content.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')}</p>
                    <div class="wa-bubble-meta">
                        <span class="wa-bubble-time">${now}</span>
                        <span class="msg-tick msg-tick--pending" title="Pending">&#10003;</span>
                    </div>
                </div>
            </div>
        </div>`;
    return div.firstElementChild;
}

export function buildMessageHtml(post) {
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
        ? `<a href="/posts/${post.id}/edit" class="wa-action-btn" title="Edit"><i class="bi bi-pencil-fill"></i></a>
           <form action="/posts/${post.id}" method="POST" class="d-inline" onsubmit="return confirm('Delete this message?')">
               <input type="hidden" name="_token" value="${document.querySelector('meta[name="csrf-token"]')?.content || ''}">
               <input type="hidden" name="_method" value="DELETE">
               <button type="submit" class="wa-action-btn" title="Delete"><i class="bi bi-trash-fill"></i></button>
           </form>`
        : '';
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
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;

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
    bindCopyButtons();
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

    if (form) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const isOffline = window._networkForced === false || !navigator.onLine;

            if (isOffline) {
                const content = input?.value?.trim();
                if (!content) return;
                const topicId = chat.dataset.topicId;
                const parentId = parentInput?.value || null;

                const exportArea = document.getElementById('chatExportArea');
                const empty = document.getElementById('chatEmpty');
                if (empty) empty.remove();

                const bubble = buildPendingBubble(content, 'tmp');
                exportArea?.appendChild(bubble);
                scrollToBottom();

                const pendingId = window.queueAction('create_post', { topic_id: topicId, content, parent_post_id: parentId }, bubble);
                bubble.dataset.pendingId = pendingId;

                input.value = '';
                autoGrow(input);
                clearReply();
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

                if (!res.ok) throw new Error('Send failed');

                const data = await res.json();
                const exportArea = document.getElementById('chatExportArea');
                const empty = document.getElementById('chatEmpty');
                if (empty) empty.remove();

                const wrapper = document.createElement('div');
                wrapper.innerHTML = buildMessageHtml(data.post);
                const msgEl = wrapper.firstElementChild;
                exportArea?.appendChild(msgEl);

                bindReplyButtons(msgEl);
                bindQuoteScroll(msgEl);

                input.value = '';
                autoGrow(input);
                clearReply();
                clearExcludeSelections();
                scrollToBottom();
            } catch {
                // fetch failed — do nothing
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
