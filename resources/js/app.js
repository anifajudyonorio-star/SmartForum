import './bootstrap';
import './chat';
import './notifications';

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
