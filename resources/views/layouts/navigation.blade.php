{{--
    CIF Top Navigation — template only (no routes, no permissions).
    Dropdowns: tiny vanilla script at the bottom (data-dropdown) — no Bootstrap JS, Popper or Alpine needed.
    Page title / breadcrumb come from the page:
        @section('page-title', 'Transaction EOIs')
        @section('breadcrumb')
            <li class="breadcrumb-item"><a href="#">Transactions</a></li>
            <li class="breadcrumb-item active">EOIs</li>
        @endsection
--}}
@php
    $user     = auth()->user();
    $name     = $user->name  ?? 'User';
    $email    = $user->email ?? 'user@example.com';
    $initials = collect(explode(' ', trim($name)))->filter()->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('') ?: 'U';


@endphp

<nav class="cif-topnav" id="cifTopnav">
    <div class="topnav-inner">

        {{-- Mobile: open sidebar --}}
        <button type="button" class="topnav-icon-btn d-lg-none" data-sidebar-mobile-toggle aria-label="Open menu">
            <i class="bi bi-list fs-5"></i>
        </button>

        {{-- Title + breadcrumb --}}
        <div class="topnav-title me-auto">
            <h1 class="page-title mb-0">@yield('page-title', '')</h1>
            @hasSection('breadcrumb')
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="#"><i class="bi bi-house-door"></i></a></li>
                        @yield('breadcrumb')
                    </ol>
                </nav>
            @else
                <div class="page-subtitle">LEHSFF Co-Investment Facility</div>
            @endif
        </div>



        {{-- User --}}
        <div class="dropdown" data-dropdown>
            <button class="topnav-user" type="button" data-dropdown-toggle aria-expanded="false">
                <span class="user-avatar">{{ $initials }}</span>
                <span class="d-none d-md-block text-start lh-sm overflow-hidden">
                    <span class="d-block user-name text-truncate">{{ $name }}</span>
                    <span class="d-block user-role text-truncate">{{ $email }}</span>
                </span>
                <i class="bi bi-chevron-down small d-none d-md-block"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end topnav-menu" data-bs-popper="static" style="min-width: 240px;">
                <li class="px-3 py-2 border-bottom mb-1">
                    <div class="d-flex align-items-center gap-2">
                        <span class="user-avatar">{{ $initials }}</span>
                        <span class="overflow-hidden">
                            <span class="d-block user-name text-truncate">{{ $name }}</span>
                            <span class="d-block user-role text-truncate">{{ $email }}</span>
                        </span>
                    </div>
                </li>
                @can('eoi.submit')
                    <li><a class="dropdown-item" href="{{ Route::has('profile.index') ? route('profile.index') : '#' }}"><i class="bi bi-building"></i> My enterprise profile</a></li>
                @endcan
                <li>
                    <a class="dropdown-item" href="{{ route('account.settings', ['id' => auth()->id()]) }}">
                        <i class="bi bi-gear"></i> Account settings
                    </a>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <form method="POST" action="{{ Route::has('logout') ? route('logout') : '#' }}">
                        @csrf
                        <button type="submit" class="dropdown-item text-danger"><i class="bi bi-box-arrow-right"></i> Log out</button>
                    </form>
                </li>
            </ul>
        </div>
    </div>

    {{-- Mobile search --}}
    <form class="topnav-search mobile d-md-none" action="#" method="GET" role="search">
        <i class="bi bi-search"></i>
        <input type="search" name="q" class="form-control" placeholder="Search…" aria-label="Search">
    </form>
</nav>

<div class="sidebar-mobile-backdrop" data-sidebar-mobile-close></div>

<style>
.cif-topnav {
    --tn-primary:   #29317D;
    --tn-accent:    #089E49;
    --tn-navy:      #0d1a3a;
    --tn-soft:      #f0f2fa;
    --tn-border:    #e2e8f0;

    position: sticky; top: 0; z-index: 1030;
    background: rgba(255,255,255,.92);
    backdrop-filter: saturate(180%) blur(8px);
    border-bottom: 1px solid var(--tn-border);
}
.cif-topnav .topnav-inner { display: flex; align-items: center; gap: .75rem; min-height: 68px; padding: .5rem 1.5rem; }

/* Title */
.cif-topnav .page-title   { font-size: 1.1rem; font-weight: 700; color: var(--tn-navy); line-height: 1.2; }
.cif-topnav .page-subtitle{ font-size: .75rem; color: #94a3b8; }
.cif-topnav .breadcrumb   { font-size: .75rem; }
.cif-topnav .breadcrumb a { color: #64748b; text-decoration: none; }
.cif-topnav .breadcrumb a:hover { color: var(--tn-accent); }
.cif-topnav .breadcrumb-item.active { color: var(--tn-primary); font-weight: 600; }

/* Search */
.topnav-search { position: relative; width: 340px; max-width: 100%; }
.topnav-search > i { position: absolute; left: .85rem; top: 50%; transform: translateY(-50%); color: #94a3b8; pointer-events: none; }
.topnav-search .form-control { background: var(--tn-soft); border: 1px solid transparent; border-radius: 10px;
                               padding: .55rem 2.5rem .55rem 2.3rem; font-size: .85rem; }
.topnav-search .form-control:focus { background: #fff; border-color: var(--tn-primary); box-shadow: 0 0 0 .2rem rgba(41,49,125,.12); }
.topnav-search.mobile { width: auto; margin: 0 1rem .75rem; }

/* Buttons */
.topnav-icon-btn { position: relative; width: 40px; height: 40px; border-radius: 10px; border: 1px solid var(--tn-border);
                   background: #fff; color: var(--tn-primary); display: inline-flex; align-items: center; justify-content: center;
                   transition: all .2s; }
.topnav-icon-btn:hover, .topnav-icon-btn[aria-expanded="true"] { border-color: var(--tn-accent); color: var(--tn-accent); background: rgba(8,158,73,.06); }
.notif-count { position: absolute; top: -6px; right: -6px; min-width: 18px; height: 18px; padding: 0 5px; border-radius: 9px;
               background: var(--tn-accent); color: #fff; font-size: .65rem; font-weight: 700; display: flex; align-items: center;
               justify-content: center; border: 2px solid #fff; }

.topnav-user { display: flex; align-items: center; gap: .6rem; padding: .25rem .5rem .25rem .25rem; border-radius: 12px;
               border: 1px solid transparent; background: transparent; color: #64748b; max-width: 240px; transition: all .2s; }
.topnav-user:hover, .topnav-user[aria-expanded="true"] { background: var(--tn-soft); border-color: var(--tn-border); }
.user-avatar { width: 38px; height: 38px; flex-shrink: 0; border-radius: 50%; background: var(--tn-primary); color: #fff;
               font-weight: 700; font-size: .8rem; display: inline-flex; align-items: center; justify-content: center;
               box-shadow: 0 0 0 2px #fff, 0 0 0 4px rgba(8,158,73,.35); }
.user-name { font-weight: 600; font-size: .85rem; color: var(--tn-navy); }
.user-role { font-size: .72rem; color: #94a3b8; }

/* Dropdowns (script toggles .show; data-bs-popper="static" lets Bootstrap CSS position them without Popper) */
.cif-topnav .dropdown { position: relative; }
.topnav-menu { border: 1px solid var(--tn-border); border-radius: 12px; box-shadow: 0 12px 32px rgba(13,26,58,.12); padding: .4rem; margin-top: .5rem !important; z-index: 1035; }
.topnav-menu.show { animation: topnavMenuIn .15s ease-out; }
@keyframes topnavMenuIn { from { opacity: 0; transform: translateY(-4px); } to { opacity: 1; transform: none; } }
.topnav-menu .dropdown-item { display: flex; align-items: center; gap: .6rem; border-radius: 8px; font-size: .85rem; padding: .5rem .75rem; color: #334155; }
.topnav-menu .dropdown-item i { color: var(--tn-primary); width: 18px; text-align: center; }
.topnav-menu .dropdown-item:hover { background: rgba(8,158,73,.08); color: var(--tn-accent); }
.topnav-menu .dropdown-item:hover i { color: var(--tn-accent); }
.topnav-menu .dropdown-item.text-danger i { color: #dc3545; }
.topnav-menu .dropdown-item.text-danger:hover { background: #fee2e2; color: #b91c1c; }
.menu-heading { color: var(--tn-navy); font-size: .9rem; }
.link-cif { color: var(--tn-primary); }
.link-cif:hover { color: var(--tn-accent); }

/* Notifications */
.notif-menu { width: 340px; max-width: calc(100vw - 2rem); padding: 0 !important; }
.notif-list { max-height: 340px; overflow-y: auto; }
.notif-item { display: flex; gap: .75rem; padding: .8rem 1rem; text-decoration: none; border-bottom: 1px solid #f1f5f9; transition: background .15s; }
.notif-item:hover { background: var(--tn-soft); }
.notif-icon { width: 36px; height: 36px; flex-shrink: 0; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; }
.notif-title { font-size: .83rem; font-weight: 600; color: var(--tn-navy); }
.notif-text  { font-size: .78rem; color: #64748b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.notif-time  { font-size: .7rem; color: #94a3b8; margin-top: 2px; }

/* ── Mobile sidebar (works with layouts/sidebar.blade.php #sidebar) ── */
.sidebar-mobile-backdrop { display: none; }
@media (max-width: 991.98px) {
    .cif-topnav .topnav-inner { padding: .5rem 1rem; }
    #sidebar { position: fixed !important; left: 0; top: 0; z-index: 1045; transform: translateX(-100%); transition: transform .25s ease; }
    #sidebar.collapsed { width: 280px; }
    body.sidebar-mobile-open #sidebar { transform: none; }
    body.sidebar-mobile-open .sidebar-mobile-backdrop { display: block; position: fixed; inset: 0; z-index: 1040; background: rgba(13,26,58,.45); }
}
</style>

<script>
(function () {
    // Mobile sidebar open/close
    document.addEventListener('click', function (e) {
        if (e.target.closest('[data-sidebar-mobile-toggle]')) document.body.classList.toggle('sidebar-mobile-open');
        if (e.target.closest('[data-sidebar-mobile-close]'))  document.body.classList.remove('sidebar-mobile-open');
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { document.body.classList.remove('sidebar-mobile-open'); closeAll(); }
    });

    // Dropdowns: toggle on button, close on outside click / Esc / opening another.
    // Clicks inside an open menu keep it open (except links and submit buttons).
    function closeAll(except) {
        document.querySelectorAll('[data-dropdown]').forEach(function (dd) {
            if (dd === except) return;
            dd.querySelector('.dropdown-menu').classList.remove('show');
            dd.querySelector('[data-dropdown-toggle]').setAttribute('aria-expanded', 'false');
        });
    }
    document.addEventListener('click', function (e) {
        var toggle = e.target.closest('[data-dropdown-toggle]');
        if (toggle) {
            var dd   = toggle.closest('[data-dropdown]');
            var menu = dd.querySelector('.dropdown-menu');
            var open = !menu.classList.contains('show');
            closeAll(dd);
            menu.classList.toggle('show', open);
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            return;
        }
        if (!e.target.closest('[data-dropdown] .dropdown-menu') || e.target.closest('a, [type=submit]')) closeAll();
    });
})();
</script>