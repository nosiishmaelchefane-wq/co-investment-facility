{{--
    CIF Sidebar — template only (no routes, no permissions).
    To wire up later:
      - replace 'url' => '#' with route('...')
      - replace 'active' => false with request()->routeIs('...')
    Wired so far: Dashboard, Transaction EOI (expression-of-interest.*)
      - wrap items/sections with @can / @canany as needed
--}}
@php
    $menu = [
        [
            'section' => null,
            'items' => [
                ['label' => 'Dashboard', 'icon' => 'bi-speedometer2',
                 'url'    => Route::has('dashboard') ? route('dashboard') : '#',
                 'active' => request()->routeIs('dashboard')],
            ],
        ],
        [
            'section' => 'Transactions',
            'items' => [
                ['label' => 'Transaction Expression of Interest (EOI)', 'icon' => 'bi-file-earmark-text-fill',
                 'url'    => route('expression-of-interest.index'),
                 'active' => request()->routeIs('expression-of-interest.*')],
                ['label' => 'Matchmaking',       'icon' => 'bi-arrow-left-right',       'url' => '#', 'active' => false],
            ],
        ],
        [
            'section' => 'Decision Gates',
            'items' => [
                ['label' => 'Eligibility Screening',  'icon' => 'bi-funnel-fill',           'url' => '#', 'active' => false, 'badge' => 'G1'],
                ['label' => 'Due Diligence & ESGC',   'icon' => 'bi-search',                'url' => '#', 'active' => false, 'badge' => 'G2'],
                ['label' => 'Committee Evaluation',   'icon' => 'bi-clipboard2-data-fill',  'url' => '#', 'active' => false, 'badge' => 'G3'],
                ['label' => 'Final Authorisation',    'icon' => 'bi-patch-check-fill',      'url' => '#', 'active' => false, 'badge' => 'G4'],
                ['label' => 'Conditions Precedent',   'icon' => 'bi-list-check',            'url' => '#', 'active' => false, 'badge' => 'G5'],
            ],
        ],
        [
            'section' => 'Funding',
            'items' => [
                ['label' => 'Capital Verification', 'icon' => 'bi-shield-fill-check', 'url' => '#', 'active' => false],
                ['label' => 'Disbursements',        'icon' => 'bi-cash-stack',        'url' => '#', 'active' => false],
            ],
        ],
        [
            'section' => 'Portfolio',
            'items' => [
                ['label' => 'Portfolio Monitoring',  'icon' => 'bi-graph-up-arrow', 'url' => '#', 'active' => false],
                ['label' => 'Milestones & Reports',  'icon' => 'bi-flag-fill',      'url' => '#', 'active' => false],
            ],
        ],
        [
            'section' => 'Insights',
            'items' => [
                ['label' => 'Reports & Analytics', 'icon' => 'bi-bar-chart-line-fill', 'url' => '#', 'active' => false],
                ['label' => 'Documents',           'icon' => 'bi-folder2-open',        'url' => '#', 'active' => false],
            ],
        ],
        [
            'section' => 'Communication',
            'items' => [
                ['label' => 'Compose Email', 'icon' => 'bi-pencil-square', 'url' => '#', 'active' => false],
            ],
        ],
        [
            'section' => 'Administration',
            'items' => [
                ['label' => 'All Users',             'icon' => 'bi-person-lines-fill', 'url' => '#', 'active' => false],
                ['label' => 'Roles & Permissions',   'icon' => 'bi-shield-lock-fill',  'url' => '#', 'active' => false, 'badge' => 'Admin'],
                ['label' => 'Configuration',         'icon' => 'bi-sliders',           'url' => '#', 'active' => false],
                ['label' => 'Audit Trail',           'icon' => 'bi-clock-history',     'url' => '#', 'active' => false],
            ],
        ],
    ];
@endphp

<div class="sidebar-custom d-flex flex-column flex-shrink-0 p-3" id="sidebar">

    <!-- Toggle Button -->
    <button type="button" class="btn btn-sm border-0 shadow-sm mb-3 align-self-end" id="sidebarToggle" title="Toggle Sidebar">
        <i class="bi bi-chevron-left fs-5" id="toggleIcon"></i>
    </button>

    <!-- Brand -->
    <a href="#" class="d-flex align-items-center mb-3 text-decoration-none sidebar-brand">
        <img src="{{ asset('/images/logo.jpg') }}"
             alt="LEHSFF Co-Investment Facility"
             class="img-fluid brand-logo"
             style="max-height: 45px; width: auto; object-fit: contain;">
        <span class="brand-mark">CI</span>
    </a>

    <div class="sidebar-text brand-caption mb-1">Co-Investment Facility</div>

    <hr class="my-2 sidebar-hr">

    <!-- Navigation Menu - Scrollable Area -->
    <div class="nav-scrollable-container">
        <ul class="nav nav-pills flex-column gap-1" id="sidebarNav">

            @foreach ($menu as $group)
                @if ($group['section'])
                    <li class="nav-item mt-2">
                        <small class="section-label px-3 sidebar-text">{{ $group['section'] }}</small>
                        <hr class="section-divider my-1">
                    </li>
                @endif

                @foreach ($group['items'] as $item)
                    <li class="nav-item">
                        <a href="{{ $item['url'] }}"
                           class="nav-link d-flex align-items-center py-2 px-3 rounded-4 {{ $item['active'] ? 'active-nav' : 'hover-nav' }}"
                           data-tooltip="{{ $item['label'] }}"
                           @if ($item['active']) aria-current="page" @endif>

                            <i class="bi {{ $item['icon'] }} fs-5 nav-icon flex-shrink-0"></i>

                            <span class="fw-medium sidebar-text ms-3 me-auto">{{ $item['label'] }}</span>

                            @isset($item['badge'])
                                <span class="badge ms-2 sidebar-badge flex-shrink-0">{{ $item['badge'] }}</span>
                            @endisset
                        </a>
                    </li>
                @endforeach
            @endforeach

        </ul>
    </div>

    <!-- Bottom section -->
    <div class="mt-auto pt-2 sidebar-bottom">
        <hr class="my-2 sidebar-hr">

        <!-- User Profile -->
        <div class="d-flex align-items-center p-2 rounded-3 user-profile-toggle">
            <div class="position-relative flex-shrink-0">
                <div class="rounded-circle border border-3 d-flex align-items-center justify-content-center profile-avatar">
                    <i class="bi bi-person-fill"></i>
                </div>
                <span class="position-absolute bottom-0 end-0 rounded-circle status-dot"></span>
            </div>
            <div class="d-flex flex-column ms-3 sidebar-text overflow-hidden">
                <strong class="text-truncate profile-name">{{ auth()->user()->name ?? 'User' }}</strong>
                <small class="text-muted text-capitalize text-truncate">{{ auth()->user()->email ?? 'Role' }}</small>
            </div>
        </div>

        <!-- Logout -->
        <div class="mt-2">
            <button type="button" class="btn btn-sm w-100 d-flex align-items-center justify-content-center btn-logout"
                    onclick="document.getElementById('logout-form').submit();">
                <i class="bi bi-box-arrow-right fs-5 flex-shrink-0"></i>
                <span class="ms-2 sidebar-text">Logout</span>
            </button>
        </div>

        <form id="logout-form" action="#" method="POST" class="d-none">
            @csrf
        </form>
    </div>
</div>

<style>
/* ── CIF palette ── */
.sidebar-custom {
    --sb-primary:     #29317D;   /* text, icons, labels */
    --sb-accent:      #089E49;   /* active + hover accent */
    --sb-navy:        #0d1a3a;   /* tooltips */
    --sb-hover-bg:    rgba(8, 158, 73, .10);
    --sb-soft-bg:     #f0f2fa;

    width: 280px;
    height: 100vh;
    position: sticky;
    top: 0;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    background: linear-gradient(135deg, #f8f9fc 0%, #ffffff 100%);
    border-right: 1px solid #e2e8f0;
    box-shadow: 2px 0 10px rgba(41, 49, 125, .08);
    transition: width .3s ease;
}

/* Brand */
.sidebar-custom .brand-mark {
    display: none;
    width: 38px; height: 38px; border-radius: 10px;
    background: var(--sb-accent); color: #fff; font-weight: 800;
    align-items: center; justify-content: center;
}
.sidebar-custom .brand-caption {
    font-size: .72rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase;
    color: var(--sb-accent);
}
.sidebar-custom .sidebar-hr { border-color: #dee2e6; opacity: .5; }

/* Scrollable nav */
.nav-scrollable-container {
    flex: 1 1 auto;
    min-height: 0;
    position: relative;
    overflow-y: auto;
    overflow-x: hidden;
    scrollbar-width: thin;
    scrollbar-color: var(--sb-primary) var(--sb-soft-bg);
    margin-right: -4px;
    padding-right: 4px;
    width: calc(100% + 4px);
}
.nav-scrollable-container::-webkit-scrollbar { width: 4px; }
.nav-scrollable-container::-webkit-scrollbar-track { background: var(--sb-soft-bg); }
.nav-scrollable-container::-webkit-scrollbar-thumb { background: var(--sb-primary); border-radius: 4px; }
.nav-scrollable-container::-webkit-scrollbar-thumb:hover { background: var(--sb-accent); }

#sidebarNav { margin: 0; padding: 0; list-style: none; }

/* Links */
.sidebar-custom .nav-link { border: none !important; color: var(--sb-primary); }
.sidebar-custom .nav-link .nav-icon,
.sidebar-custom .nav-link .sidebar-text { color: var(--sb-primary); }
.sidebar-custom .nav-link .sidebar-text { line-height: 1.25; min-width: 0; }
.sidebar-custom .rounded-4 { border-radius: 12px !important; }

.hover-nav { transition: all .3s ease; background-color: transparent; }
.hover-nav:hover { background-color: var(--sb-hover-bg) !important; transform: translateX(4px); }
.hover-nav:hover .nav-icon,
.hover-nav:hover .sidebar-text { color: var(--sb-accent) !important; }

.active-nav {
    background-color: var(--sb-accent) !important;
    box-shadow: 0 4px 8px rgba(8, 158, 73, .25) !important;
}
.active-nav .nav-icon,
.active-nav .sidebar-text { color: #fff !important; }

/* Section labels */
.section-label {
    display: block;
    margin-bottom: 2px;
    font-size: .68rem;
    font-weight: 700;
    letter-spacing: .09em;
    text-transform: uppercase;
    color: var(--sb-primary);
    opacity: .75;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.section-divider { display: none; }

/* Badges */
.sidebar-badge {
    font-size: .65rem;
    padding: .25rem .5rem;
    border-radius: 20px;
    font-weight: 600;
    background-color: var(--sb-primary);
    color: #fff;
}
.active-nav .sidebar-badge { background-color: #fff; color: var(--sb-accent); }

/* Toggle button */
#sidebarToggle {
    width: 32px; height: 32px; padding: 0;
    display: flex; align-items: center; justify-content: center;
    border-radius: 50%;
    background-color: var(--sb-soft-bg);
    color: var(--sb-primary);
    transition: background .2s ease;
}
#sidebarToggle i { color: var(--sb-primary); }
#sidebarToggle:hover { background-color: var(--sb-hover-bg) !important; }
#sidebarToggle:hover i { color: var(--sb-accent) !important; }
#toggleIcon { transition: transform .3s ease; }

/* Profile */
.sidebar-bottom { flex-shrink: 0; }
.user-profile-toggle { background-color: var(--sb-soft-bg); transition: all .3s ease; }
.user-profile-toggle:hover { background-color: var(--sb-hover-bg) !important; }
.user-profile-toggle:hover .profile-name { color: var(--sb-accent) !important; }
.profile-avatar { width: 40px; height: 40px; border-color: var(--sb-primary) !important; }
.profile-avatar i { font-size: 20px; color: var(--sb-primary); }
.profile-name { color: var(--sb-primary); }
.status-dot { width: 12px; height: 12px; background-color: var(--sb-accent); border: 2px solid #fff; }

/* Logout */
.btn-logout { background: transparent; color: var(--sb-primary); border: 1px solid var(--sb-primary); transition: all .3s ease; }
.btn-logout i { color: var(--sb-primary); }
.btn-logout:hover { background-color: var(--sb-hover-bg) !important; border-color: var(--sb-accent) !important; color: var(--sb-accent) !important; }
.btn-logout:hover i { color: var(--sb-accent) !important; }

/* ── Collapsed state ── */
.sidebar-custom.collapsed { width: 72px; }
.sidebar-custom.collapsed .sidebar-text,
.sidebar-custom.collapsed .sidebar-badge,
.sidebar-custom.collapsed .brand-logo { display: none !important; }
.sidebar-custom.collapsed .brand-mark { display: inline-flex; }
.sidebar-custom.collapsed .sidebar-brand { justify-content: center; }
.sidebar-custom.collapsed .section-divider { display: block !important; }
.sidebar-custom.collapsed .nav-scrollable-container { width: 100%; margin-right: 0; padding-right: 0; }
.sidebar-custom.collapsed .nav-link { justify-content: center; padding-left: 0 !important; padding-right: 0 !important; position: relative; }
.sidebar-custom.collapsed .nav-icon { margin: 0 !important; }
.sidebar-custom.collapsed .user-profile-toggle { justify-content: center; }
.sidebar-custom.collapsed #toggleIcon { transform: rotate(180deg); }

/* Tooltips when collapsed */
.sidebar-custom.collapsed .nav-link::after {
    content: attr(data-tooltip);
    position: absolute;
    left: calc(100% + 10px);
    top: 50%;
    transform: translateY(-50%);
    background: var(--sb-navy);
    color: #fff;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: .78rem;
    white-space: nowrap;
    opacity: 0;
    pointer-events: none;
    transition: opacity .2s ease;
    z-index: 9999;
}
.sidebar-custom.collapsed .nav-link:hover::after { opacity: 1; }
</style>

<script>
(function () {
    var sidebar   = document.getElementById('sidebar');
    var toggleBtn = document.getElementById('sidebarToggle');
    if (!sidebar || !toggleBtn) return;

    // Remember collapsed state between page loads
    try {
        if (localStorage.getItem('cif-sidebar-collapsed') === '1') sidebar.classList.add('collapsed');
    } catch (e) {}

    toggleBtn.addEventListener('click', function () {
        sidebar.classList.toggle('collapsed');
        try {
            localStorage.setItem('cif-sidebar-collapsed', sidebar.classList.contains('collapsed') ? '1' : '0');
        } catch (e) {}
        window.dispatchEvent(new Event('sidebarToggle'));
    });
})();
</script>