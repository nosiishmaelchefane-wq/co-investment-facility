<x-app-layout>
    {{--
        Account settings — GET /account-settings  (name: account.settings, any signed-in user)
        Each section is a Livewire component in resources/views/components/account/:
          profile     → name, e-mail (asks for the password + alerts the old address), phone, organisation
          password    → change password, optionally sign out everywhere else
          two-factor  → two-step sign-in on/off for this user (asks for the password)
          sessions    → signed-in devices from the database session table, sign out one / all others
        Tabs use the Bootstrap JS already in the master layout.
    --}}

    @php
        $me       = auth()->user();
        $initials = $me->initials;
        $tfOn     = $me->requiresSignInCode();   // what actually happens at sign-in
    @endphp

    <div class="container-fluid p-3 account-settings">
        <style>
            .account-settings .as-card { border: 1px solid var(--cif-border, #e2e8f0); border-radius: .85rem; background: #fff; }
            .account-settings .as-nav .nav-link { color: var(--cif-primary, #29317D); border-radius: .65rem; padding: .6rem .85rem; display: flex; align-items: center; gap: .6rem; font-weight: 500; text-align: left; }
            .account-settings .as-nav .nav-link:hover { background: rgba(8, 158, 73, .08); color: var(--cif-accent, #089E49); }
            .account-settings .as-nav .nav-link.active { background: var(--cif-accent, #089E49); color: #fff; box-shadow: 0 4px 8px rgba(8, 158, 73, .25); }
            .account-settings .as-avatar { width: 72px; height: 72px; border-radius: 50%; background: var(--cif-primary, #29317D); color: #fff; font-size: 1.6rem; font-weight: 700; display: flex; align-items: center; justify-content: center; }
            .account-settings .as-section-title { font-weight: 700; color: var(--cif-navy, #0d1a3a); margin-bottom: .15rem; }
            .account-settings .as-row + .as-row { border-top: 1px dashed var(--cif-border, #e2e8f0); }
            .account-settings .form-switch-lg .form-check-input { width: 3rem; height: 1.5rem; cursor: pointer; }
            .account-settings .form-check-input:checked { background-color: var(--cif-accent, #089E49); border-color: var(--cif-accent, #089E49); }
            .account-settings .security-state { border-radius: .75rem; padding: .9rem 1rem; }
            .account-settings .security-state.is-on  { background: rgba(8, 158, 73, .08); border: 1px solid rgba(8, 158, 73, .3); }
            .account-settings .security-state.is-off { background: #fff7ed; border: 1px solid #fed7aa; }
            .account-settings .device-icon { width: 40px; height: 40px; border-radius: .6rem; background: #f0f2fa; color: var(--cif-primary, #29317D); display: flex; align-items: center; justify-content: center; font-size: 1.15rem; flex-shrink: 0; }
        </style>

        {{-- ============================ HEADER ============================ --}}
        <div class="d-flex flex-wrap align-items-center gap-3 mb-4">
            <div class="me-auto">
                <h2 class="page-heading mb-1">Account settings</h2>
                <p class="page-subtitle mb-0">Manage your profile, password and how you sign in.</p>
            </div>
        </div>

        <div class="row g-3">
            {{-- ============================ LEFT · MENU ============================ --}}
            <div class="col-lg-3">
                <div class="as-card p-3 mb-3 text-center">
                    <div class="as-avatar mx-auto mb-2">{{ $initials }}</div>
                    <div class="fw-bold text-navy">{{ $me->name }}</div>
                    <div class="small text-muted mb-2 text-break">{{ $me->email }}</div>
                    @if ($me->primary_role)
                        <span class="pill pill-muted" style="text-transform: none; letter-spacing: normal"><i class="bi bi-person-badge"></i> {{ $me->primary_role->display_name }}</span>
                    @endif
                    @if ($me->last_login_at)
                        <div class="small text-muted mt-2">Last signed in {{ $me->last_login_at->format('d M Y, H:i') }}</div>
                    @endif
                </div>

                <div class="as-card p-2">
                    <nav class="nav flex-column as-nav" role="tablist">
                        <button class="nav-link active" data-bs-toggle="pill" data-bs-target="#tab-profile" type="button" role="tab"><i class="bi bi-person"></i> Profile</button>
                        <button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-password" type="button" role="tab"><i class="bi bi-key"></i> Password</button>
                        <button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-security" type="button" role="tab">
                            <i class="bi bi-shield-lock"></i> <span class="me-auto">Sign-in security</span>
                            <span class="badge rounded-pill {{ $tfOn ? 'text-bg-success' : 'text-bg-warning' }}">{{ $tfOn ? 'On' : 'Off' }}</span>
                        </button>
                        <button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-sessions" type="button" role="tab"><i class="bi bi-laptop"></i> Signed-in devices</button>
                    </nav>
                </div>
            </div>

            {{-- ============================ RIGHT · SECTIONS ============================ --}}
            <div class="col-lg-9">
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="tab-profile" role="tabpanel">
                        <livewire:account.profile />
                    </div>
                    <div class="tab-pane fade" id="tab-password" role="tabpanel">
                        <livewire:account.password />
                    </div>
                    <div class="tab-pane fade" id="tab-security" role="tabpanel">
                        <livewire:account.two-factor />
                    </div>
                    <div class="tab-pane fade" id="tab-sessions" role="tabpanel">
                        <livewire:account.sessions />
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>