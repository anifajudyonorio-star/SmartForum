import './bootstrap';
import './chat';
import './notifications';

// Global back button — return to previous page, or dashboard if no history
(function () {
    const backBtn = document.getElementById('appBackBtn');
    if (!backBtn) return;

    backBtn.addEventListener('click', () => {
        const fallback = backBtn.dataset.fallback || '/dashboard';

        // history.length > 1 usually means there is somewhere to go back to.
        // Also guard against leaving the site when opened in a new tab.
        if (window.history.length > 1 && document.referrer) {
            const sameOrigin = (() => {
                try {
                    return new URL(document.referrer).origin === window.location.origin;
                } catch {
                    return false;
                }
            })();

            if (sameOrigin) {
                window.history.back();
                return;
            }
        }

        if (window.history.length > 1) {
            window.history.back();
            return;
        }

        window.location.href = fallback;
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
