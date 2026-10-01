{{--
    CIF Top Navigation — template only (no routes, no permissions).
    Bootstrap 5 (dropdowns need bootstrap.bundle.js, already in layouts/app).
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

    // Placeholder notifications — replace with $user->unreadNotifications later
    $notifications = [
        ['icon' => 'bi-patch-check-fill',  'tone' => 'primary', 'title' => 'Ready for final authorisation', 'text' => 'A transaction is awaiting MD approval.',        'time' => '5 min ago'],
        ['icon' => 'bi-chat-left-dots',    'tone' => 'warning', 'title' => 'Clarification requested',       'text' => 'Gate 1 reviewer requested more information.',  'time' => '1 hr ago'],
        ['icon' => 'bi-shield-fill-check', 'tone' => 'accent',  'title' => 'Investor capital verified',     'text' => 'Tranche 1 is cleared for disbursement.',       'time' => 'Yesterday'],
    ];
@endphp

<nav class="cif-topnav" id="cifTopnav">
    <div class="topnav-inner">

        {{-- Mobile: open sidebar --}}
        <button type="button" class="topnav-icon-btn d-lg-none" data-sidebar-mobile-toggle aria-label="Open menu">
            <i class="bi bi-list fs-5"></i>
        </button>

        {{-- Title + breadcrumb --}}
        <div class="topnav-title me-auto">
            <h1 class="page-title mb-0">@yield('page-title', 'Dashboard')</h1>
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

        {{-- Notifications --}}
        <div class="dropdown">
            <button class="topnav-icon-btn" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" aria-label="Notifications">
                <i class="bi bi-bell"></i>
                @if (count($notifications))
                    <span class="notif-count">{{ count($notifications) }}</span>
                @endif
            </button>
            <div class="dropdown-menu dropdown-menu-end topnav-menu notif-menu p-0">
                <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
                    <strong class="menu-heading">Notifications</strong>
                    <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none link-cif">Mark all read</button>
                </div>

                <div class="notif-list">
                    @forelse ($notifications as $n)
                        <a href="#" class="notif-item">
                            <span class="notif-icon tone-{{ $n['tone'] }}"><i class="bi {{ $n['icon'] }}"></i></span>
                            <span class="flex-grow-1 overflow-hidden">
                                <span class="d-block notif-title">{{ $n['title'] }}</span>
                                <span class="d-block notif-text">{{ $n['text'] }}</span>
                                <span class="d-block notif-time">{{ $n['time'] }}</span>
                            </span>
                        </a>
                    @empty
                        <div class="text-center text-muted small py-4">
                            <i class="bi bi-bell-slash d-block fs-4 mb-1"></i> You're all caught up
                        </div>
                    @endforelse
                </div>

                <a href="#" class="d-block text-center small py-2 border-top text-decoration-none link-cif fw-semibold">View all notifications</a>
            </div>
        </div>

        {{-- User --}}
        <div class="dropdown">
            <button class="topnav-user" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <span class="user-avatar">{{ $initials }}</span>
                <span class="d-none d-md-block text-start lh-sm overflow-hidden">
                    <span class="d-block user-name text-truncate">{{ $name }}</span>
                    <span class="d-block user-role text-truncate">{{ $email }}</span>
                </span>
                <i class="bi bi-chevron-down small d-none d-md-block"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end topnav-menu" style="min-width: 240px;">
                <li class="px-3 py-2 border-bottom mb-1">
                    <div class="d-flex align-items-center gap-2">
                        <span class="user-avatar">{{ $initials }}</span>
                        <span class="overflow-hidden">
                            <span class="d-block user-name text-truncate">{{ $name }}</span>
                            <span class="d-block user-role text-truncate">{{ $email }}</span>
                        </span>
                    </div>
                </li>
                <li><a class="dropdown-item" href="#"><i class="bi bi-person"></i> My profile</a></li>
                <li><a class="dropdown-item" href="#"><i class="bi bi-gear"></i> Account settings</a></li>
                <li><a class="dropdown-item" href="#"><i class="bi bi-question-circle"></i> Help &amp; support</a></li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <form method="POST" action="#">
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

    position: sticky; top: 0; z-index: 1020;
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
.topnav-search kbd { position: absolute; right: .6rem; top: 50%; transform: translateY(-50%); background: #fff; color: #94a3b8;
                     border: 1px solid var(--tn-border); font-size: .7rem; padding: .1rem .4rem; }
.topnav-search.mobile { width: auto; margin: 0 1rem .75rem; }

/* Buttons */
.topnav-icon-btn { position: relative; width: 40px; height: 40px; border-radius: 10px; border: 1px solid var(--tn-border);
                   background: #fff; color: var(--tn-primary); display: inline-flex; align-items: center; justify-content: center;
                   transition: all .2s; }
.topnav-icon-btn:hover, .topnav-icon-btn[aria-expanded="true"] { border-color: var(--tn-accent); color: var(--tn-accent); background: rgba(8,158,73,.06); }
.notif-count { position: absolute; top: -6px; right: -6px; min-width: 18px; height: 18px; padding: 0 5px; border-radius: 9px;
               background: var(--tn-accent); color: #fff; font-size: .65rem; font-weight: 700; display: flex; align-items: center;
               justify-content: center; border: 2px solid #fff; }

.btn-topnav-action { height: 40px; border-radius: 10px; background: var(--tn-primary); color: #fff; font-weight: 600; font-size: .85rem; border: 0; }
.btn-topnav-action:hover, .btn-topnav-action.show { background: #1f2663; color: #fff; }

.topnav-user { display: flex; align-items: center; gap: .6rem; padding: .25rem .5rem .25rem .25rem; border-radius: 12px;
               border: 1px solid transparent; background: transparent; color: #64748b; max-width: 240px; transition: all .2s; }
.topnav-user:hover, .topnav-user[aria-expanded="true"] { background: var(--tn-soft); border-color: var(--tn-border); }
.user-avatar { width: 38px; height: 38px; flex-shrink: 0; border-radius: 50%; background: var(--tn-primary); color: #fff;
               font-weight: 700; font-size: .8rem; display: inline-flex; align-items: center; justify-content: center;
               box-shadow: 0 0 0 2px #fff, 0 0 0 4px rgba(8,158,73,.35); }
.user-name { font-weight: 600; font-size: .85rem; color: var(--tn-navy); }
.user-role { font-size: .72rem; color: #94a3b8; }

/* Dropdowns */
.topnav-menu { border: 1px solid var(--tn-border); border-radius: 12px; box-shadow: 0 12px 32px rgba(13,26,58,.12); padding: .4rem; margin-top: .5rem !important; }
.topnav-menu .dropdown-item { display: flex; align-items: center; gap: .6rem; border-radius: 8px; font-size: .85rem; padding: .5rem .75rem; color: #334155; }
.topnav-menu .dropdown-item i { color: var(--tn-primary); width: 18px; text-align: center; }
.topnav-menu .dropdown-item:hover { background: rgba(8,158,73,.08); color: var(--tn-accent); }
.topnav-menu .dropdown-item:hover i { color: var(--tn-accent); }
.topnav-menu .dropdown-item.text-danger i { color: #dc3545; }
.topnav-menu .dropdown-item.text-danger:hover { background: #fee2e2; color: #b91c1c; }
.topnav-menu .dropdown-header { font-size: .68rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: #94a3b8; }
.menu-heading { color: var(--tn-navy); font-size: .9rem; }
.link-cif { color: var(--tn-primary); }
.link-cif:hover { color: var(--tn-accent); }

/* Notifications */
.notif-menu { width: 340px; max-width: calc(100vw - 2rem); padding: 0 !important; }
.notif-list { max-height: 340px; overflow-y: auto; }
.notif-item { display: flex; gap: .75rem; padding: .8rem 1rem; text-decoration: none; border-bottom: 1px solid #f1f5f9; transition: background .15s; }
.notif-item:hover { background: var(--tn-soft); }
.notif-icon { width: 36px; height: 36px; flex-shrink: 0; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; }
.tone-primary { background: rgba(41,49,125,.1); color: var(--tn-primary); }
.tone-accent  { background: rgba(8,158,73,.1);  color: var(--tn-accent); }
.tone-warning { background: #fef3c7; color: #b45309; }
.notif-title { font-size: .83rem; font-weight: 600; color: var(--tn-navy); }
.notif-text  { font-size: .78rem; color: #64748b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.notif-time  { font-size: .7rem; color: #94a3b8; margin-top: 2px; }

/* ── Mobile sidebar (works with layouts/sidebar.blade.php #sidebar) ── */
.sidebar-mobile-backdrop { display: none; }
@media (max-width: 991.98px) {
    .cif-topnav .topnav-inner { padding: .5rem 1rem; }
    #sidebar { position: fixed !important; left: 0; top: 0; z-index: 1045; transform: translateX(-100%); transition: transform .25s ease; }
    #sidebar.collapsed { width: 280px; }                   /* always full width on mobile */
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
        if (e.key === 'Escape') document.body.classList.remove('sidebar-mobile-open');

        // "/" focuses search (unless already typing)
        if (e.key === '/' && !/input|textarea|select/i.test(document.activeElement.tagName)) {
            var input = document.querySelector('.topnav-search:not(.mobile) input');
            if (input && input.offsetParent !== null) { e.preventDefault(); input.focus(); }
        }
    });
})();
</script>