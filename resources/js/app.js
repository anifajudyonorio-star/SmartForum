import './bootstrap';
import './chat';
import './notifications';
import { initOfflineSync, queueAction } from './offline';
import { initPushNotifications } from './push';

window.queueAction = queueAction;
initOfflineSync();
initPushNotifications();

// Intercept topic creation form when offline
(function () {
    const form = document.getElementById('topicCreateForm');
    if (!form) return;
    form.addEventListener('submit', function (e) {
        const isOffline = window._networkForced === false || !navigator.onLine;
        if (!isOffline) return;
        e.preventDefault();
        const groupId = form.dataset.groupId;
        const title = form.querySelector('input[name="Title"]')?.value.trim();
        const description = form.querySelector('textarea[name="Topic_Description"]')?.value.trim();
        if (!title) return;
        queueAction('create_topic', { group_id: groupId, title, description });
        alert('You\'re offline. Your topic will be created when you reconnect.');
    });
})();

// Intercept quiz form when offline
(function () {
    const form = document.getElementById('quizForm');
    if (!form) return;
    form.addEventListener('submit', function (e) {
        const isOffline = window._networkForced === false || !navigator.onLine;
        if (!isOffline) return;
        e.preventDefault();
        if (form.dataset.offlineQueued === 'true') return;
        const action = form.getAttribute('action');
        const quizId = action?.match(/\/quizzes\/(\d+)\//)?.[1];
        const attemptId = form.querySelector('input[name="attempt_id"]')?.value;
        if (!quizId || !attemptId) return;
        const answers = {};
        form.querySelectorAll('input[type="radio"]:checked').forEach((input) => {
            const match = input.name.match(/answers\[(\d+)\]/);
            if (match) answers[match[1]] = input.value;
        });
        queueAction('submit_quiz', {
            quiz_id: Number(quizId),
            attempt_id: Number(attemptId),
            answers,
        });
        form.dataset.offlineQueued = 'true';
        form.querySelectorAll('button, input').forEach((element) => {
            if (element.type !== 'hidden') element.disabled = true;
        });
        alert(
            'Your quiz submission is queued once. The server deadline is authoritative; '
            + 'if synchronization occurs after it, queued answer changes are ignored and the attempt times out.',
        );
    });
})();

// Mobile sidebar toggle
(function () {
    const toggle = document.getElementById('sidebarToggle');
    const mobile = document.getElementById('mobileSidebar');
    const backdrop = document.getElementById('mobileSidebarBackdrop');
    const closeBtn = document.getElementById('mobileSidebarClose');

    function setOpenState(open) {
        if (mobile) mobile.classList.toggle('open', !!open);
        if (backdrop) backdrop.classList.toggle('open', !!open);
        document.body.style.overflow = open ? 'hidden' : '';
    }

    function closeSidebar() { setOpenState(false); }

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeSidebar();
    });

    if (toggle) {
        toggle.addEventListener('click', () => {
            const isOpen = mobile && mobile.classList.contains('open');
            setOpenState(!isOpen);
        });
    }

    if (backdrop) backdrop.addEventListener('click', closeSidebar);
    if (closeBtn) closeBtn.addEventListener('click', closeSidebar);

    // Close mobile sidebar when a nav link is clicked
    document.querySelectorAll('#mobileSidebar .sidebar-nav-link').forEach((link) => {
        link.addEventListener('click', closeSidebar);
    });
})();

// Fly-in animations for dynamically loaded content
(function () {
    const observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('fly-in-visible');
                    observer.unobserve(entry.target);
                }
            });
        },
        { threshold: 0.1, rootMargin: '0px 0px -40px 0px' }
    );

    document.querySelectorAll('[data-fly-in]').forEach((el) => observer.observe(el));
})();
