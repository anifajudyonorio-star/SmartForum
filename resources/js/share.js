export function buildShareText({ authorName, topicTitle, content, shareUrl }) {
    const excerpt = (content || '').trim().slice(0, 180);
    return `${authorName} in "${topicTitle}": ${excerpt}${excerpt.length < (content || '').length ? '…' : ''}\n${shareUrl}`;
}

export function shareTargets({ shareUrl, text }) {
    const encodedUrl = encodeURIComponent(shareUrl);
    const encodedText = encodeURIComponent(text);

    return [
        {
            id: 'whatsapp',
            label: 'WhatsApp',
            icon: 'bi-whatsapp',
            url: `https://wa.me/?text=${encodedText}`,
        },
        {
            id: 'facebook',
            label: 'Facebook',
            icon: 'bi-facebook',
            url: `https://www.facebook.com/sharer/sharer.php?u=${encodedUrl}&quote=${encodedText}`,
        },
        {
            id: 'twitter',
            label: 'X / Twitter',
            icon: 'bi-twitter-x',
            url: `https://twitter.com/intent/tweet?text=${encodedText}`,
        },
        {
            id: 'linkedin',
            label: 'LinkedIn',
            icon: 'bi-linkedin',
            url: `https://www.linkedin.com/sharing/share-offsite/?url=${encodedUrl}`,
        },
        {
            id: 'telegram',
            label: 'Telegram',
            icon: 'bi-telegram',
            url: `https://t.me/share/url?url=${encodedUrl}&text=${encodedText}`,
        },
    ];
}

export function openShareWindow(url) {
    window.open(url, '_blank', 'noopener,noreferrer,width=640,height=560');
}

export function copyShareText(text) {
    if (navigator.clipboard?.writeText) {
        return navigator.clipboard.writeText(text);
    }

    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.setAttribute('readonly', '');
    textarea.style.position = 'absolute';
    textarea.style.left = '-9999px';
    document.body.appendChild(textarea);
    textarea.select();
    document.execCommand('copy');
    document.body.removeChild(textarea);

    return Promise.resolve();
}

export function showShareMenu(anchor, { authorName, topicTitle, content, shareUrl }) {
    document.querySelectorAll('.wa-share-menu').forEach((menu) => menu.remove());

    const text = buildShareText({ authorName, topicTitle, content, shareUrl });
    const menu = document.createElement('div');
    menu.className = 'wa-share-menu';
    menu.innerHTML = `
        <div class="wa-share-menu-title">Share message</div>
        ${shareTargets({ shareUrl, text }).map((target) => `
            <button type="button" class="wa-share-option" data-share-url="${target.url}">
                <i class="bi ${target.icon}"></i>
                <span>${target.label}</span>
            </button>
        `).join('')}
        <button type="button" class="wa-share-option" data-copy-share="1">
            <i class="bi bi-link-45deg"></i>
            <span>Copy link</span>
        </button>
    `;

    anchor.closest('.wa-bubble-wrap')?.appendChild(menu);

    menu.querySelectorAll('[data-share-url]').forEach((button) => {
        button.addEventListener('click', () => {
            openShareWindow(button.dataset.shareUrl);
            menu.remove();
        });
    });

    menu.querySelector('[data-copy-share]')?.addEventListener('click', async () => {
        await copyShareText(text);
        menu.remove();
    });

    const closeOnOutsideClick = (event) => {
        if (!menu.contains(event.target) && event.target !== anchor) {
            menu.remove();
            document.removeEventListener('click', closeOnOutsideClick);
        }
    };

    setTimeout(() => document.addEventListener('click', closeOnOutsideClick), 0);
}

export function bindShareButtons(scope, topicTitle, topicUrl) {
    scope.querySelectorAll('.share-btn').forEach((button) => {
        if (button.dataset.shareBound === '1') {
            return;
        }
        button.dataset.shareBound = '1';

        button.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();

            const msg = button.closest('.wa-msg');
            const content = msg?.querySelector('.wa-bubble-text')?.textContent?.trim() || '';
            const authorName = msg?.querySelector('.wa-bubble-name')?.textContent?.trim()
                || (msg?.classList.contains('mine') ? 'You' : 'Member');
            const postId = msg?.dataset.msgId || button.dataset.post;
            const shareUrl = `${topicUrl}#msg-${postId}`;

            showShareMenu(button, { authorName, topicTitle, content, shareUrl });
        });
    });
}
