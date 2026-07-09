<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SmartForum — Academic Discussion Platform</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/sass/app.scss', 'resources/js/app.js'])
    @endif
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', system-ui, sans-serif;
            background: #0a0f1e;
            color: #e2e8f0;
            min-height: 100vh;
            -webkit-font-smoothing: antialiased;
        }

        /* ── Nav ── */
        nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.1rem 2.5rem;
            position: fixed;
            top: 0; width: 100%;
            z-index: 100;
            background: rgba(10,15,30,0.8);
            backdrop-filter: blur(16px);
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }

        .nav-brand {
            font-size: 1.2rem;
            font-weight: 800;
            color: #fff;
            letter-spacing: -0.5px;
        }

        .nav-brand span { color: #6366f1; }

        .nav-links { display: flex; gap: 0.75rem; align-items: center; }

        .btn-nav {
            padding: 0.45rem 1.1rem;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.2s;
            letter-spacing: -0.1px;
        }

        .btn-outline {
            border: 1px solid rgba(255,255,255,0.12);
            color: #cbd5e1;
        }
        .btn-outline:hover { border-color: rgba(255,255,255,0.3); color: #fff; }

        .btn-primary-nav {
            background: #6366f1;
            color: #fff;
            border: 1px solid #6366f1;
            box-shadow: 0 2px 12px rgba(99,102,241,0.3);
        }
        .btn-primary-nav:hover { background: #4f46e5; box-shadow: 0 4px 20px rgba(99,102,241,0.45); }

        /* ── Hero ── */
        .hero {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 7rem 1.5rem 4rem;
            position: relative;
            overflow: hidden;
        }

        .hero::before {
            content: '';
            position: absolute;
            inset: 0;
            background:
                radial-gradient(ellipse 70% 50% at 50% -10%, rgba(99,102,241,0.2) 0%, transparent 65%),
                radial-gradient(ellipse 40% 30% at 80% 80%, rgba(139,92,246,0.1) 0%, transparent 60%);
            pointer-events: none;
        }

        .hero-inner { position: relative; max-width: 720px; }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            background: rgba(99,102,241,0.12);
            border: 1px solid rgba(99,102,241,0.25);
            color: #a5b4fc;
            padding: 0.4rem 1rem;
            border-radius: 999px;
            font-size: 0.78rem;
            font-weight: 600;
            margin-bottom: 2rem;
            letter-spacing: 0.3px;
            text-transform: uppercase;
        }

        .hero h1 {
            font-size: clamp(2.6rem, 7vw, 4.5rem);
            font-weight: 900;
            line-height: 1.08;
            letter-spacing: -2px;
            margin-bottom: 1.5rem;
            color: #fff;
        }

        .hero h1 .accent { color: #6366f1; }
        .hero h1 .accent-2 { color: #a78bfa; }

        .hero p {
            font-size: 1.1rem;
            color: #94a3b8;
            max-width: 480px;
            margin: 0 auto 2.5rem;
            line-height: 1.75;
            font-weight: 400;
        }

        .hero-actions {
            display: flex;
            gap: 0.875rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn-hero {
            padding: 0.875rem 2rem;
            border-radius: 10px;
            font-size: 0.95rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            letter-spacing: -0.2px;
        }

        .btn-hero-primary {
            background: #6366f1;
            color: #fff;
            box-shadow: 0 4px 24px rgba(99,102,241,0.4);
        }
        .btn-hero-primary:hover {
            background: #4f46e5;
            transform: translateY(-2px);
            box-shadow: 0 8px 36px rgba(99,102,241,0.5);
            color: #fff;
        }

        .btn-hero-ghost {
            border: 1px solid rgba(255,255,255,0.12);
            color: #cbd5e1;
            background: rgba(255,255,255,0.03);
        }
        .btn-hero-ghost:hover {
            border-color: rgba(255,255,255,0.25);
            color: #fff;
            background: rgba(255,255,255,0.06);
        }

        /* ── Stats strip ── */
        .stats-strip {
            display: flex;
            justify-content: center;
            gap: 3rem;
            flex-wrap: wrap;
            padding: 2.5rem 1.5rem;
            border-top: 1px solid rgba(255,255,255,0.05);
            border-bottom: 1px solid rgba(255,255,255,0.05);
            background: rgba(255,255,255,0.02);
        }

        .stat-item { text-align: center; }

        .stat-item .num {
            font-size: 1.75rem;
            font-weight: 800;
            color: #fff;
            letter-spacing: -1px;
            display: block;
        }

        .stat-item .lbl {
            font-size: 0.8rem;
            color: #64748b;
            font-weight: 500;
            margin-top: 0.2rem;
        }

        /* ── Features ── */
        .features {
            padding: 6rem 1.5rem;
            max-width: 1080px;
            margin: 0 auto;
        }

        .section-eyebrow {
            text-align: center;
            color: #6366f1;
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            margin-bottom: 0.75rem;
        }

        .section-title {
            text-align: center;
            font-size: clamp(1.6rem, 3.5vw, 2.4rem);
            font-weight: 800;
            letter-spacing: -1px;
            color: #fff;
            margin-bottom: 0.75rem;
        }

        .section-sub {
            text-align: center;
            color: #64748b;
            font-size: 1rem;
            max-width: 460px;
            margin: 0 auto 3.5rem;
            line-height: 1.7;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.25rem;
        }

        .feature-card {
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.07);
            border-radius: 16px;
            padding: 1.75rem;
            transition: all 0.25s;
        }

        .feature-card:hover {
            background: rgba(99,102,241,0.07);
            border-color: rgba(99,102,241,0.2);
            transform: translateY(-3px);
            box-shadow: 0 12px 40px rgba(0,0,0,0.3);
        }

        .feature-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: rgba(99,102,241,0.15);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            margin-bottom: 1.1rem;
        }

        .feature-card h3 {
            font-size: 0.95rem;
            font-weight: 700;
            color: #f1f5f9;
            margin-bottom: 0.5rem;
            letter-spacing: -0.2px;
        }

        .feature-card p {
            font-size: 0.85rem;
            color: #64748b;
            line-height: 1.65;
        }

        /* ── CTA ── */
        .cta {
            padding: 5rem 1.5rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .cta::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(ellipse 60% 80% at 50% 50%, rgba(99,102,241,0.12) 0%, transparent 70%);
            pointer-events: none;
        }

        .cta-inner {
            position: relative;
            max-width: 560px;
            margin: 0 auto;
        }

        .cta h2 {
            font-size: clamp(1.8rem, 4vw, 2.8rem);
            font-weight: 900;
            letter-spacing: -1.5px;
            color: #fff;
            margin-bottom: 1rem;
        }

        .cta p {
            color: #64748b;
            font-size: 1rem;
            margin-bottom: 2rem;
            line-height: 1.7;
        }

        /* ── Footer ── */
        footer {
            text-align: center;
            padding: 1.75rem;
            color: #334155;
            font-size: 0.82rem;
            border-top: 1px solid rgba(255,255,255,0.05);
            letter-spacing: 0.1px;
        }

        /* ── Responsive ── */
        @media (max-width: 600px) {
            nav { padding: 1rem 1.25rem; }
            .hero h1 { letter-spacing: -1px; }
            .stats-strip { gap: 1.75rem; }
        }
    </style>
</head>
<body>

    <nav>
        <div class="nav-brand">Smart<span>Forum</span></div>
        <div class="nav-links">
            <a href="{{ route('login') }}" class="btn-nav btn-outline">Log in</a>
            <a href="{{ route('register') }}" class="btn-nav btn-primary-nav">Sign up free</a>
        </div>
    </nav>

    <section class="hero">
        <div class="hero-inner">
            <div class="hero-badge">🎓 Academic Discussion Platform</div>
            <h1>
                Where <span class="accent">Learning</span><br>
                Meets <span class="accent-2">Discussion</span>
            </h1>
            <p>Participate in assigned groups, explore topics, and engage in meaningful academic conversations with lecturers and peers.</p>
            <div class="hero-actions">
                <a href="{{ route('register') }}" class="btn-hero btn-hero-primary">
                    Get Started Free →
                </a>
                <a href="{{ route('login') }}" class="btn-hero btn-hero-ghost">
                    Sign in
                </a>
            </div>
        </div>
    </section>

    <div class="stats-strip">
        <div class="stat-item">
            <span class="num">100%</span>
            <span class="lbl">Free to use</span>
        </div>
        <div class="stat-item">
            <span class="num">3</span>
            <span class="lbl">User roles</span>
        </div>
        <div class="stat-item">
            <span class="num">∞</span>
            <span class="lbl">Discussions</span>
        </div>
        <div class="stat-item">
            <span class="num">24/7</span>
            <span class="lbl">Always available</span>
        </div>
    </div>

    <section class="features">
        <p class="section-eyebrow">Features</p>
        <h2 class="section-title">Everything you need to learn together</h2>
        <p class="section-sub">A complete platform for academic collaboration between students, lecturers, and admins.</p>
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon">📚</div>
                <h3>Group Discussions</h3>
                <p>Admins create groups and assign members. Lecturers create topics. Students and lecturers participate in structured academic conversations.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">🧠</div>
                <h3>Quizzes</h3>
                <p>Test your knowledge with quizzes created by lecturers. Get instant feedback and track your progress.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">🔔</div>
                <h3>Notifications</h3>
                <p>Stay updated with real-time notifications on new posts, replies, and group activity.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">🛡️</div>
                <h3>Moderated Platform</h3>
                <p>Admins keep the community healthy with tools to ensure respectful and productive discussions.</p>
            </div>
        </div>
    </section>

    <section class="cta">
        <div class="cta-inner">
            <h2>Ready to get started?</h2>
            <p>Create your free account in seconds and start learning together.</p>
            <a href="{{ route('register') }}" class="btn-hero btn-hero-primary">
                Sign up for free →
            </a>
        </div>
    </section>

    <footer>
        &copy; {{ date('Y') }} SmartForum. Built for academic excellence.
    </footer>

</body>
</html>
