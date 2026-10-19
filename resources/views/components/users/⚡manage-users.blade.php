<?php

use App\Models\Role;
use App\Models\User;
use App\Notifications\AccountAccessLink;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    /** Filters (wire:model.live — no JS) */
    public string $search = '';
    public string $role   = '';
    public string $status = '';

    public function mount(): void
    {
        $this->authorizeAccess();
    }

    /** Any filter change → back to page 1 */
    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'role', 'status'], true)) {
            $this->resetPage();
        }
    }

    /* ---------------- Data ---------------- */

    #[Computed]
    public function users()
    {
        return User::query()
            ->with('roles:id,name,label,icon,type')
            ->search($this->search)
            ->when($this->role !== '' && Role::where('name', $this->role)->exists(), fn ($q) => $q->role($this->role))
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->orderBy('name')
            ->paginate(10);
    }

    #[Computed]
    public function roles()
    {
        return Role::withCount('users')->orderBy('id')->get();
    }

    #[Computed]
    public function stats(): array
    {
        $byStatus = User::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return [
            'total'    => (int) $byStatus->sum(),
            'active'   => (int) ($byStatus[User::STATUS_ACTIVE] ?? 0),
            'invited'  => (int) ($byStatus[User::STATUS_INVITED] ?? 0),
            'inactive' => (int) ($byStatus[User::STATUS_INACTIVE] ?? 0),
        ];
    }

    /* ---------------- Filters ---------------- */

    /** Role card click → filter by that role (click again to clear). */
    public function filterRole(string $role): void
    {
        $this->role = $this->role === $role ? '' : $role;
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'role', 'status');
        $this->resetPage();
    }

    /* ---------------- Drawer ---------------- */

    public function addUser(): void
    {
        $this->dispatch('open-user');
    }

    public function editUser(int $id): void
    {
        $this->dispatch('edit-user', id: $id);
    }

    #[On('user-saved')]
    public function refresh(): void
    {
        unset($this->users, $this->roles, $this->stats);
    }

    /* ---------------- Row actions ---------------- */

    public function toggleStatus(int $id): void
    {
        $this->authorizeAccess();

        $user = User::findOrFail($id);

        if ($user->is(auth()->user())) {
            $this->dispatch('notify', type: 'error', message: "You can't deactivate your own account.");
            return;
        }

        if ($user->isActive()) {
            if ($user->hasRole(Role::ADMIN) && User::activeAdminCount(exceptId: $user->id) === 0) {
                $this->dispatch('notify', type: 'error', message: "{$user->name} is the last active System Administrator.");
                return;
            }

            $user->update(['status' => User::STATUS_INACTIVE]);
            $this->dispatch('notify', type: 'success', message: "{$user->name} has been deactivated.");
        } else {
            // Never signed in? Put them back to "invited", otherwise "active".
            $user->update(['status' => $user->last_login_at ? User::STATUS_ACTIVE : User::STATUS_INVITED]);
            $this->dispatch('notify', type: 'success', message: "{$user->name} has been reactivated.");
        }

        $this->refresh();
    }

    public function sendPasswordReset(int $id): void
    {
        $this->authorizeAccess();
        $this->sendLink(User::findOrFail($id), 'reset');
    }

    public function resendInvite(int $id): void
    {
        $this->authorizeAccess();

        $user = User::findOrFail($id);
        $user->update(['invited_at' => now()]);

        $this->sendLink($user, 'invite');
    }

    protected function sendLink(User $user, string $type): void
    {
        if (! $user->isActive()) {
            $this->dispatch('notify', type: 'error', message: "Reactivate {$user->name} first.");
            return;
        }

        try {
            $user->notify(new AccountAccessLink($type));
            $this->dispatch('notify', type: 'success', message: ($type === 'invite' ? 'Invitation' : 'Password reset link')." sent to {$user->email}.");
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('notify', type: 'error', message: "Couldn't send the email to {$user->email}. "
                .(config('app.debug') ? $e->getMessage() : 'Check the mail settings.'));
        }
    }

    protected function authorizeAccess(): void
    {
        abort_unless(auth()->user()?->can('users.manage'), 403);
    }
};
?>

{{-- ONE root element --}}
<div class="container-fluid p-3">
    @php
        $users    = $this->users;
        $roles    = $this->roles;
        $stats    = $this->stats;
        $statuses = \App\Models\User::STATUSES;
        $filtered = $search !== '' || $role !== '' || $status !== '';
    @endphp

    {{-- ============================ HEADER ============================ --}}
    <div class="d-flex flex-wrap align-items-center gap-3 mb-4">
        <div class="me-auto">
            <h2 class="page-heading">User management</h2>
            <p class="page-subtitle">Create accounts, assign roles and manage access to the Co-Investment Facility.</p>
        </div>
        <button type="button" class="btn btn-cta" wire:click="addUser">
            <i class="bi bi-person-plus me-1"></i> Add user
        </button>
    </div>

    {{-- ============================ STATS ============================ --}}
    <div class="row g-3 mb-4">
        @foreach ([
            ['Total users',     $stats['total'],    'bi-people',       'primary', ''],
            ['Active',          $stats['active'],   'bi-check-circle', 'accent',  'active'],
            ['Pending invites', $stats['invited'],  'bi-envelope',     'warning', 'invited'],
            ['Inactive',        $stats['inactive'], 'bi-slash-circle', 'muted',   'inactive'],
        ] as [$label, $value, $icon, $tone, $key])
            <div class="col-6 col-xl-3">
                <div class="stat-card">
                    <span class="stat-icon tone-{{ $tone }}"><i class="bi {{ $icon }}"></i></span>
                    <div><div class="stat-value">{{ $value }}</div><div class="stat-label">{{ $label }}</div></div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- ============================ ROLE CARDS (click to filter) ============================ --}}
    <div class="filter-cards mb-4">
        @foreach ($roles as $r)
            <button type="button" wire:click="filterRole('{{ $r->name }}')" wire:key="rc-{{ $r->id }}"
                    class="filter-card {{ $role === $r->name ? 'active' : '' }}">
                <span class="filter-card-icon"><i class="bi {{ $r->icon }}"></i></span>
                <span class="filter-card-body">
                    <span class="filter-card-value">{{ $r->users_count }}</span>
                    <span class="filter-card-label">{{ $r->display_name }}</span>
                </span>
            </button>
        @endforeach
    </div>

    {{-- ============================ TABLE ============================ --}}
    <div class="card card-modern">
        <div class="card-header py-3">
            <div class="row g-2 align-items-center">
                <div class="col-md">
                    <div class="input-search">
                        <i class="bi bi-search"></i>
                        <input type="search" class="form-control" wire:model.live.debounce.300ms="search" placeholder="Search by name, email or organisation…">
                    </div>
                </div>
                <div class="col-sm-6 col-md-auto">
                    <select class="form-select" wire:model.live="role">
                        <option value="">All roles</option>
                        @foreach ($roles as $r)
                            <option value="{{ $r->name }}">{{ $r->display_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-6 col-md-auto">
                    <select class="form-select" wire:model.live="status">
                        <option value="">All statuses</option>
                        @foreach ($statuses as $key => [$label])
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                @if ($filtered)
                    <div class="col-md-auto">
                        <button type="button" class="btn btn-link text-decoration-none text-secondary px-1" wire:click="clearFilters">
                            <i class="bi bi-x-lg me-1"></i> Clear
                        </button>
                    </div>
                @endif
            </div>
        </div>

        @if ($users->isEmpty())
            <div class="empty-state">
                <div class="empty-icon"><i class="bi bi-person-x"></i></div>
                <h5 class="fw-bold text-navy mb-1">{{ $filtered ? 'No users found' : 'No users yet' }}</h5>
                <p class="text-muted small mb-3">{{ $filtered ? 'Try a different search or clear the filters.' : 'Add the first user to get started.' }}</p>
                @if ($filtered)
                    <button type="button" class="btn btn-brand btn-sm" wire:click="clearFilters">Clear filters</button>
                @else
                    <button type="button" class="btn btn-brand btn-sm" wire:click="addUser"><i class="bi bi-person-plus me-1"></i> Add user</button>
                @endif
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 table-modern">
                    <thead>
                        <tr>
                            <th class="ps-4">User</th>
                            <th>Role</th>
                            <th>Organisation</th>
                            <th>Phone</th>
                            <th>Status</th>
                            <th>Last login</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($users as $u)
                            @php
                                $r        = $u->primary_role;
                                $external = $r?->isExternal();
                                [$sLabel, $sClass, $sIcon] = $statuses[$u->status] ?? [ucfirst($u->status), 'status-draft', 'bi-circle'];
                                $isMe     = $u->is(auth()->user());
                                $profileUrl = $u->roles->contains('name', 'enterprise') ? route('profile.index', $u) : null;
                            @endphp
                            <tr wire:key="user-{{ $u->id }}">
                                <td class="ps-4">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="avatar avatar-sm {{ $external ? 'bg-accent' : '' }}">{{ $u->initials }}</span>
                                        <div class="overflow-hidden">
                                            <div class="fw-semibold text-navy text-truncate">
                                                @if ($profileUrl)
                                                    <a href="{{ $profileUrl }}" class="link-strong" title="View enterprise profile">{{ $u->name }}</a>
                                                @else
                                                    {{ $u->name }}
                                                @endif
                                                @if ($isMe) <span class="pill pill-muted ms-1">You</span> @endif
                                            </div>
                                            <div class="small text-muted text-truncate">{{ $u->email }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    @if ($r)
                                        <span class="tag {{ $external ? 'tag-accent' : '' }}"><i class="bi {{ $r->icon }}"></i> {{ $r->display_name }}</span>
                                    @else
                                        <span class="text-faint small">No role</span>
                                    @endif
                                </td>
                                <td class="small">{{ $u->organisation_name ?: '—' }}</td>
                                <td class="small text-muted">{{ $u->phone ?: '—' }}</td>
                                <td><span class="badge {{ $sClass }}"><i class="bi {{ $sIcon }} me-1"></i>{{ $sLabel }}</span></td>
                                <td class="small text-muted" title="{{ $u->last_login_at?->format('d M Y H:i') }}">{{ $u->last_login_at?->diffForHumans() ?? 'Never' }}</td>
                                <td class="text-end pe-4">
                                    <div class="d-inline-flex gap-1">
                                        @if ($profileUrl)
                                            <a href="{{ $profileUrl }}" class="btn btn-icon" title="View enterprise profile">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                        @endif
                                        <button type="button" class="btn btn-icon" title="Edit" wire:click="editUser({{ $u->id }})">
                                            <i class="bi bi-pencil"></i>
                                        </button>

                                        @if ($u->status === \App\Models\User::STATUS_INVITED)
                                            <button type="button" class="btn btn-icon" title="Resend invitation"
                                                    wire:click="resendInvite({{ $u->id }})" wire:loading.attr="disabled" wire:target="resendInvite({{ $u->id }})">
                                                <i class="bi bi-send"></i>
                                            </button>
                                        @elseif ($u->isActive())
                                            <button type="button" class="btn btn-icon" title="Send password reset"
                                                    wire:click="sendPasswordReset({{ $u->id }})"
                                                    wire:confirm="Send a password reset link to {{ $u->email }}?">
                                                <i class="bi bi-key"></i>
                                            </button>
                                        @endif

                                        @unless ($isMe)
                                            @if ($u->isActive())
                                                <button type="button" class="btn btn-icon btn-icon-danger" title="Deactivate"
                                                        wire:click="toggleStatus({{ $u->id }})"
                                                        wire:confirm="Deactivate {{ $u->name }}? They will no longer be able to sign in.">
                                                    <i class="bi bi-person-x"></i>
                                                </button>
                                            @else
                                                <button type="button" class="btn btn-icon" title="Reactivate" wire:click="toggleStatus({{ $u->id }})">
                                                    <i class="bi bi-person-check"></i>
                                                </button>
                                            @endif
                                        @endunless
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="card-footer d-flex flex-wrap align-items-center gap-2 small text-muted">
                @if ($users->hasPages())
                    <div class="w-100">{{ $users->links() }}</div>
                @else
                    <span>Showing {{ $users->total() }} {{ \Illuminate\Support\Str::plural('user', $users->total()) }}</span>
                @endif
            </div>
        @endif
    </div>

    {{-- Create / edit drawer --}}
    <livewire:users.drawer.user-form />
</div>