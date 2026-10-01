<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Co-Investment Facility | {{ config('app.name', 'LEHSFF') }}</title>

    {{-- Fonts --}}
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />

    {{-- Bootstrap 5 + Bootstrap Icons --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <style>
        :root {
            --cif-primary:       #29317D;
            --cif-primary-dark:  #1f2663;
            --cif-accent:        #089E49;
            --cif-accent-dark:   #07863e;
            --cif-navy:          #0d1a3a;
        }

        body { font-family: 'Figtree', system-ui, sans-serif; color: #334155; background: #f8fafc; }

        /* ---------- Helpers ---------- */
        .text-cif-primary { color: var(--cif-primary) !important; }
        .text-cif-accent  { color: var(--cif-accent) !important; }
        .text-cif-navy    { color: var(--cif-navy) !important; }
        .bg-cif-primary   { background: var(--cif-primary) !important; }
        .bg-cif-navy      { background: var(--cif-navy) !important; }
        .text-white-60    { color: rgba(255,255,255,.6) !important; }
        .text-white-75    { color: rgba(255,255,255,.75) !important; }
        .eyebrow { font-size: .8rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--cif-accent); }

        /* ---------- Buttons ---------- */
        .btn-cif-primary { background: var(--cif-primary); border-color: var(--cif-primary); color: #fff; font-weight: 600; }
        .btn-cif-primary:hover, .btn-cif-primary:focus { background: var(--cif-primary-dark); border-color: var(--cif-primary-dark); color: #fff; }
        .btn-cif-accent  { background: var(--cif-accent); border-color: var(--cif-accent); color: #fff; font-weight: 600; }
        .btn-cif-accent:hover, .btn-cif-accent:focus { background: var(--cif-accent-dark); border-color: var(--cif-accent-dark); color: #fff; }
        .btn-outline-light-soft { border: 1px solid rgba(255,255,255,.3); color: #fff; font-weight: 600; }
        .btn-outline-light-soft:hover { background: rgba(255,255,255,.1); color: #fff; }
        .btn-link-cif { background: none; border: 0; padding: 0; font-weight: 600; color: var(--cif-primary); text-decoration: none; }
        .btn-link-cif:hover { text-decoration: underline; }
        .btn-link-cif.accent { color: var(--cif-accent); }

        /* ---------- Navbar ---------- */
        .cif-nav { position: absolute; inset: 0 0 auto 0; z-index: 30; }
        .cif-nav .nav-link { color: rgba(255,255,255,.75); font-weight: 500; }
        .cif-nav .nav-link:hover { color: #fff; }
        .brand-mark { width: 40px; height: 40px; border-radius: 12px; background: var(--cif-accent); color: #fff;
                      display: inline-flex; align-items: center; justify-content: center; font-weight: 800; }

        /* ---------- Hero ---------- */
        .hero { position: relative; overflow: hidden; background: var(--cif-navy);
                background-image:
                    linear-gradient(rgba(255,255,255,.04) 1px, transparent 1px),
                    linear-gradient(90deg, rgba(255,255,255,.04) 1px, transparent 1px);
                background-size: 48px 48px; padding: 9rem 0 6rem; }
        .hero .glow { position: absolute; border-radius: 50%; filter: blur(80px); pointer-events: none; }
        .hero .glow-1 { width: 480px; height: 480px; left: -160px; top: -160px; background: var(--cif-primary); opacity: .6; }
        .hero .glow-2 { width: 420px; height: 420px; right: 0; bottom: -190px; background: var(--cif-accent); opacity: .2; }
        .hero h1 { font-size: clamp(2.2rem, 4.5vw, 3.6rem); font-weight: 800; line-height: 1.1; color: #fff; }
        .hero-badge { display: inline-flex; align-items: center; gap: .5rem; padding: .4rem 1rem; border-radius: 999px;
                      border: 1px solid rgba(8,158,73,.4); background: rgba(8,158,73,.1); color: #6ee7a8; font-size: .875rem; font-weight: 500; }
        .hero-badge .dot { width: 8px; height: 8px; border-radius: 50%; background: var(--cif-accent); }
        .hero-stats { border-top: 1px solid rgba(255,255,255,.1); }
        .hero-stats dt { font-size: .72rem; letter-spacing: .08em; text-transform: uppercase; color: rgba(255,255,255,.5); font-weight: 500; }
        .hero-stats dd { font-size: 2rem; font-weight: 700; color: #fff; margin: 0; }

        /* ---------- Auth card ---------- */
        .auth-card { background: #fff; border-radius: 1rem; padding: 2.25rem; box-shadow: 0 25px 50px -12px rgba(0,0,0,.35);
                     max-width: 460px; margin: 0 auto; }
        .auth-tabs { display: grid; grid-template-columns: 1fr 1fr; background: #f1f5f9; border-radius: .75rem; padding: 4px; }
        .auth-tab { border: 0; background: transparent; border-radius: .6rem; padding: .6rem; font-weight: 600; font-size: .9rem; color: #64748b; transition: all .2s; }
        .auth-tab.is-active { background: #fff; color: var(--cif-primary); box-shadow: 0 1px 3px rgba(13,26,58,.12); }
        .auth-panel[hidden] { display: none !important; }
        .auth-panel.is-entering { animation: authIn .28s ease-out; }
        @keyframes authIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: none; } }

        .auth-card .form-label { font-size: .875rem; font-weight: 500; color: #334155; margin-bottom: .35rem; }
        .auth-card .form-control { padding: .7rem .9rem; font-size: .9rem; border-color: #cbd5e1; border-radius: .5rem; }
        .auth-card .form-control:focus { border-color: var(--cif-primary); box-shadow: 0 0 0 .2rem rgba(41,49,125,.15); }
        .auth-card .input-group-text { background: #fff; border-color: #cbd5e1; color: #94a3b8; }
        .auth-card .input-group > .input-group-text + .form-control { border-left: 0; }
        .auth-card .input-group > .form-control.has-toggle { border-right: 0; }
        .auth-card .input-group .btn-toggle { background: #fff; border: 1px solid #cbd5e1; border-left: 0; color: #94a3b8; }
        .auth-card .input-group .btn-toggle:hover { color: var(--cif-primary); }
        .auth-card .input-group:focus-within .input-group-text,
        .auth-card .input-group:focus-within .btn-toggle,
        .auth-card .input-group:focus-within .form-control { border-color: var(--cif-primary); box-shadow: none; }
        .auth-card .input-group:focus-within { box-shadow: 0 0 0 .2rem rgba(41,49,125,.15); border-radius: .5rem; }
        .auth-card .form-check-input:checked { background-color: var(--cif-primary); border-color: var(--cif-primary); }
        .auth-card .btn-lg { font-size: .95rem; padding: .8rem; border-radius: .5rem; }

        /* ---------- Sections ---------- */
        .section { padding: 5rem 0; }
        .section-title { font-weight: 700; color: var(--cif-navy); font-size: clamp(1.8rem, 3vw, 2.4rem); }

        .pathway-card { position: relative; overflow: hidden; background: #fff; border: 1px solid #e2e8f0; border-radius: 1rem;
                        padding: 2rem; height: 100%; transition: transform .2s, box-shadow .2s; }
        .pathway-card:hover { transform: translateY(-4px); box-shadow: 0 12px 30px rgba(13,26,58,.1); }
        .pathway-card::before { content: ''; position: absolute; inset: 0 0 auto 0; height: 4px; background: var(--bar); }
        .icon-box { width: 48px; height: 48px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.35rem; }
        .icon-box.primary { background: rgba(41,49,125,.1); color: var(--cif-primary); }
        .icon-box.accent  { background: rgba(8,158,73,.1);  color: var(--cif-accent); }
        .arrow-link { font-weight: 600; text-decoration: none; }
        .arrow-link:hover { text-decoration: underline; }

        .gates { position: relative; }
        .gates::before { content: ''; position: absolute; left: 0; right: 0; top: 28px; height: 2px;
                         background: linear-gradient(90deg, var(--cif-primary), var(--cif-accent)); }
        .gate-no { position: relative; z-index: 1; width: 56px; height: 56px; border-radius: 50%; background: var(--cif-primary);
                   color: #fff; font-weight: 700; font-size: 1.1rem; display: flex; align-items: center; justify-content: center;
                   border: 4px solid #f8fafc; box-shadow: 0 4px 10px rgba(13,26,58,.15); }
        @media (max-width: 991.98px) { .gates::before { display: none; } }

        .info-tile { background: #fff; border: 1px solid #e2e8f0; border-radius: .75rem; padding: 1.5rem; height: 100%; }

        .cta-box { position: relative; overflow: hidden; background: var(--cif-primary); border-radius: 1.5rem; padding: 3.5rem; }
        .cta-box .glow { position: absolute; right: -80px; top: -80px; width: 290px; height: 290px; border-radius: 50%;
                         background: var(--cif-accent); opacity: .3; filter: blur(70px); }
        .check-item { background: rgba(255,255,255,.1); border-radius: .5rem; padding: .8rem 1rem; color: #fff; font-size: .9rem; }
        .check-item i { color: #34d27b; }

        @media (max-width: 575.98px) { .auth-card { padding: 1.5rem; } .cta-box { padding: 2rem; } }
    </style>
</head>

<body>

{{-- ============================ NAVBAR ============================ --}}
<nav class="cif-nav">
    <div class="container py-3 d-flex align-items-center justify-content-between">
        <a href="#" class="d-flex align-items-center gap-3 text-decoration-none">
            <span class="brand-mark">CI</span>
            <span class="lh-sm text-white">
                <span class="d-block fw-bold">Co-Investment Facility</span>
                <span class="d-block small text-white-60">LEHSFF · CAFI</span>
            </span>
        </a>

        <ul class="nav d-none d-md-flex gap-2">
            <li class="nav-item"><a class="nav-link" href="#how-it-works">How it works</a></li>
            <li class="nav-item"><a class="nav-link" href="#pathways">Pathways</a></li>
            <li class="nav-item"><a class="nav-link" href="#eligibility">Eligibility</a></li>
            <li class="nav-item"><a class="nav-link" href="#">Contact</a></li>
        </ul>

        <a href="#auth" data-auth-show="register" class="btn btn-sm btn-outline-light-soft px-3 py-2">Submit EOI</a>
    </div>
</nav>

{{-- ============================ HERO + AUTH ============================ --}}
<section class="hero">
    <div class="glow glow-1"></div>
    <div class="glow glow-2"></div>

    <div class="container position-relative">
        <div class="row align-items-center g-5">

            {{-- Left: Messaging --}}
            <div class="col-lg-7">
                <span class="hero-badge mb-4"><span class="dot"></span> Now accepting Transaction EOIs</span>

                <h1 class="mb-4">
                    Unlocking capital for
                    <span class="text-cif-accent">Basotho enterprises</span>
                    through co-investment.
                </h1>

                <p class="fs-5 text-white-75 mb-0" style="max-width: 560px;">
                    The LEHSFF Co-Investment Facility partners with private investors to fund
                    high-potential enterprises. Apply with your investor, or let us match you with one.
                </p>

                <dl class="hero-stats row mt-5 pt-4 mb-0" style="max-width: 560px;">
                    <div class="col-4"><dt>Decision gates</dt><dd>5</dd></div>
                    <div class="col-4"><dt>Pathways</dt><dd>2</dd></div>
                    <div class="col-4"><dt>Fully digital</dt><dd class="text-cif-accent">100%</dd></div>
                </dl>
            </div>

            {{-- Right: Auth card --}}
            <div class="col-lg-5" id="auth" style="scroll-margin-top: 6rem;">
                <div class="auth-card">

                    {{-- Tabs --}}
                    <div class="auth-tabs mb-4" role="tablist">
                        <button type="button" role="tab" class="auth-tab is-active" data-auth-show="login">Sign in</button>
                        <button type="button" role="tab" class="auth-tab" data-auth-show="register">Register</button>
                    </div>

                    @if (session('status'))
                        <div class="alert alert-success py-2 small">{{ session('status') }}</div>
                    @endif

                    {{-- ======================= LOGIN PANEL ======================= --}}
                    <div class="auth-panel" data-auth-panel="login">
                        <livewire:auth.login />
                    </div>

                    {{-- ======================= REGISTER PANEL ======================= --}}
                    <div class="auth-panel" data-auth-panel="register" hidden>
                        <livewire:auth.register />
                    </div>

                </div>
            </div>
        </div>
    </div>
</section>

{{-- ============================ PATHWAYS ============================ --}}
<section id="pathways" class="section bg-white">
    <div class="container">
        <div class="text-center mx-auto mb-5" style="max-width: 640px;">
            <div class="eyebrow">Two ways in</div>
            <h2 class="section-title mt-2">Choose your pathway</h2>
            <p class="text-secondary mt-3">
                Whether you already have an investor or need one, every transaction follows the same transparent review process.
            </p>
        </div>

        <div class="row g-4">
            <div class="col-md-6">
                <div class="pathway-card" style="--bar: var(--cif-primary);">
                    <div class="icon-box primary mb-4"><i class="bi bi-file-earmark-text"></i></div>
                    <div class="small fw-semibold text-uppercase text-muted">Pathway 1</div>
                    <h3 class="h5 fw-bold text-cif-navy mt-1">Direct Transaction EOI</h3>
                    <p class="text-secondary small mt-3">
                        You already have a committed investor. Submit one Expression of Interest with your enterprise,
                        investor, transaction structure and intended use of funds.
                    </p>
                    <a href="#auth" data-auth-show="register" class="arrow-link text-cif-primary">Start an EOI →</a>
                </div>
            </div>
            <div class="col-md-6">
                <div class="pathway-card" style="--bar: var(--cif-accent);">
                    <div class="icon-box accent mb-4"><i class="bi bi-arrow-left-right"></i></div>
                    <div class="small fw-semibold text-uppercase text-muted">Pathway 2</div>
                    <h3 class="h5 fw-bold text-cif-navy mt-1">LEHSFF Facilitated Matchmaking</h3>
                    <p class="text-secondary small mt-3">
                        No investor yet? Build your enterprise profile and we'll match you with investors by sector,
                        ticket size and investment preferences, then facilitate the introduction.
                    </p>
                    <a href="#auth" data-auth-show="register" class="arrow-link text-cif-accent">Find an investor →</a>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ============================ HOW IT WORKS ============================ --}}
@php
    $gates = [
        ['no' => 1, 'title' => 'Eligibility Screening',  'text' => 'Completeness, enterprise and investor eligibility, and alignment with Facility parameters.'],
        ['no' => 2, 'title' => 'Due Diligence & ESGC',   'text' => 'Business, financial and compliance review with an ESGC assessment and risk rating.'],
        ['no' => 3, 'title' => 'Investment Committee',   'text' => 'Independent scoring by a three-member committee against approved criteria.'],
        ['no' => 4, 'title' => 'Final Authorisation',    'text' => 'Sequential approval by the CAFI Managing Director and the MTIBD Principal Secretary.'],
        ['no' => 5, 'title' => 'Conditions & Agreement', 'text' => 'Conditions precedent fulfilled, Grant Agreement signed and investor capital verified.'],
    ];
@endphp

<section id="how-it-works" class="section">
    <div class="container">
        <div class="text-center mx-auto mb-5" style="max-width: 640px;">
            <div class="eyebrow">The process</div>
            <h2 class="section-title mt-2">Five gates from application to funding</h2>
            <p class="text-secondary mt-3">Track your transaction at every stage. You'll be notified when action is needed.</p>
        </div>

        <div class="gates row row-cols-1 row-cols-sm-2 row-cols-lg-5 g-4">
            @foreach ($gates as $gate)
                <div class="col">
                    <div class="gate-no mb-3">{{ $gate['no'] }}</div>
                    <h3 class="h6 fw-bold text-cif-navy">{{ $gate['title'] }}</h3>
                    <p class="small text-secondary mb-0">{{ $gate['text'] }}</p>
                </div>
            @endforeach
        </div>

        <div class="row g-3 mt-5">
            <div class="col-md-6">
                <div class="info-tile d-flex gap-3">
                    <div class="icon-box accent flex-shrink-0" style="width:40px;height:40px;font-size:1.1rem;"><i class="bi bi-cash-stack"></i></div>
                    <div>
                        <h4 class="h6 fw-bold text-cif-navy mb-1">Disbursement in tranches</h4>
                        <p class="small text-secondary mb-0">Each tranche is released only after the investor's matching capital for that tranche is verified.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="info-tile d-flex gap-3">
                    <div class="icon-box primary flex-shrink-0" style="width:40px;height:40px;font-size:1.1rem;"><i class="bi bi-bar-chart-line"></i></div>
                    <div>
                        <h4 class="h6 fw-bold text-cif-navy mb-1">Portfolio support</h4>
                        <p class="small text-secondary mb-0">Funded enterprises join the portfolio with milestone tracking, reporting and ongoing support.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ============================ ELIGIBILITY CTA ============================ --}}
<section id="eligibility" class="section bg-white">
    <div class="container">
        <div class="cta-box">
            <div class="glow"></div>
            <div class="row align-items-center g-5 position-relative">
                <div class="col-lg-6">
                    <h2 class="fw-bold text-white" style="font-size: clamp(1.8rem, 3vw, 2.4rem);">Is your enterprise ready?</h2>
                    <p class="text-white-75 mt-3">Have these ready before you submit your Transaction EOI.</p>
                    <div class="d-flex flex-wrap gap-3 mt-4">
                        <a href="#auth" data-auth-show="register" class="btn btn-cif-accent px-4 py-2">Register your enterprise</a>
                        <a href="#" class="btn btn-outline-light-soft px-4 py-2">Download guidelines</a>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="d-grid gap-2">
                        @foreach ([
                            'A registered enterprise operating in Lesotho',
                            'A committed investor, or readiness for matchmaking',
                            'Business registration and ownership documents',
                            'Financial statements and tax/statutory compliance',
                            'A clear investment requirement and use of funds',
                        ] as $item)
                            <div class="check-item d-flex gap-2 align-items-start">
                                <i class="bi bi-check-circle-fill"></i> <span>{{ $item }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ============================ FOOTER ============================ --}}
<footer class="bg-cif-navy text-white-60">
    <div class="container py-4 d-flex flex-column flex-md-row align-items-center justify-content-between gap-3 small">
        <div class="d-flex align-items-center gap-2">
            <span class="brand-mark" style="width:32px;height:32px;border-radius:8px;font-size:.75rem;">CI</span>
            <span>&copy; {{ date('Y') }} LEHSFF Co-Investment Facility · CAFI. All rights reserved.</span>
        </div>
        <div class="d-flex gap-4">
            <a href="#" class="text-white-60 text-decoration-none">Privacy</a>
            <a href="#" class="text-white-60 text-decoration-none">Terms</a>
            <a href="#" class="text-white-60 text-decoration-none">Support</a>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var panels = document.querySelectorAll('[data-auth-panel]');
    var tabs   = document.querySelectorAll('.auth-tab');
    var card   = document.getElementById('auth');

    function showAuth(view, scroll) {
        panels.forEach(function (panel) {
            var active = panel.getAttribute('data-auth-panel') === view;
            panel.hidden = !active;                       // native attribute: works without any CSS framework
            panel.classList.remove('is-entering');
            if (active) {
                void panel.offsetWidth;                   // restart fade-in
                panel.classList.add('is-entering');
                var first = panel.querySelector('input:not([type=hidden])');
                if (first) first.focus({ preventScroll: true });
            }
        });
        tabs.forEach(function (tab) {
            var active = tab.getAttribute('data-auth-show') === view;
            tab.classList.toggle('is-active', active);
            tab.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        if (scroll && card) card.scrollIntoView({ behavior: 'smooth', block: 'center' });
        if (history.replaceState) history.replaceState(null, '', '#' + view);
    }

    // Anything with data-auth-show switches the card (tabs, inline links, page CTAs)
    document.addEventListener('click', function (e) {
        var trigger = e.target.closest('[data-auth-show]');
        if (!trigger) return;
        e.preventDefault();
        showAuth(trigger.getAttribute('data-auth-show'), !card.contains(trigger));
    });

    // Password show / hide (delegated, so it keeps working after Livewire re-renders)
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-toggle-password]');
        if (!btn) return;
        var input = document.getElementById(btn.getAttribute('data-toggle-password'));
        if (!input) return;
        var show  = input.type === 'password';
        input.type = show ? 'text' : 'password';
        var icon = btn.querySelector('i');
        if (icon) icon.className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
        btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    });

    // /#register opens the register form directly
    if (location.hash === '#register') showAuth('register', false);
});
</script>

</body>
</html>