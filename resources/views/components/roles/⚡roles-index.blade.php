<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    /** Filters the permission matrix */
    public string $search = '';

    public function mount(): void
    {
        $this->authorizeAccess();
    }

    /* ---------------- Data ---------------- */

    #[Computed]
    public function roles()
    {
        return Role::with('permissions:id,name')->withCount('users')->orderBy('id')->get();
    }

    #[Computed]
    public function permissions()
    {
        return Permission::orderBy('sort')->orderBy('name')->get();
    }

    /** Distinct users that hold at least one role */
    #[Computed]
    public function assignedUsers(): int
    {
        return DB::table(config('permission.table_names.model_has_roles'))->distinct()->count('model_id');
    }

    /* ---------------- Drawer ---------------- */

    public function addRole(): void
    {
        $this->dispatch('open-role');
    }

    public function editRole(int $id): void
    {
        $this->dispatch('edit-role', id: $id);
    }

    #[On('role-saved')]
    public function refresh(): void
    {
        unset($this->roles, $this->permissions, $this->assignedUsers);
    }

    /* ---------------- Actions ---------------- */

    /** Click a cell in the matrix to grant / revoke one permission. */
    public function toggle(int $roleId, string $permission): void
    {
        $this->authorizeAccess();

        $role = Role::findOrFail($roleId);

        if ($role->hasPermissionTo($permission)) {
            if ($role->name === Role::ADMIN && in_array($permission, Role::ADMIN_LOCKED_PERMISSIONS, true)) {
                $this->dispatch('notify', type: 'error', message: "System Administrator must keep \"{$permission}\" so nobody gets locked out.");
                return;
            }
            $role->revokePermissionTo($permission);
            $this->dispatch('notify', type: 'success', message: "Removed {$permission} from {$role->display_name}.");
        } else {
            $role->givePermissionTo($permission);
            $this->dispatch('notify', type: 'success', message: "Gave {$permission} to {$role->display_name}.");
        }

        unset($this->roles);
    }

    public function deleteRole(int $id): void
    {
        $this->authorizeAccess();

        $role = Role::withCount('users')->findOrFail($id);

        if (! $role->isDeletable()) {
            $this->dispatch('notify', type: 'error', message: "{$role->display_name} is a system role and can't be deleted.");
            return;
        }

        if ($role->users_count > 0) {
            $this->dispatch('notify', type: 'error', message: "Move the {$role->users_count} user(s) to another role before deleting {$role->display_name}.");
            return;
        }

        $name = $role->display_name;
        $role->delete();

        $this->refresh();
        $this->dispatch('notify', type: 'success', message: "{$name} has been deleted.");
    }

    protected function authorizeAccess(): void
    {
        abort_unless(auth()->user()?->can('roles.manage'), 403);
    }
};
?>

{{-- ONE root element --}}
<div class="container-fluid p-3">
    @php
        $roles       = $this->roles;
        $permissions = $this->permissions;
        $total       = $permissions->count();
        $moduleOrder = array_keys(\App\Models\Permission::MODULES);

        // role id => [permission name => index]  (isset() lookup)
        $granted = $roles->mapWithKeys(fn ($r) => [$r->id => $r->permissions->pluck('name')->flip()->all()]);

        // Matrix rows: search, then group by module in MODULES order
        $q = mb_strtolower(trim($search));
        $rows = $permissions
            ->when($q !== '', fn ($c) => $c->filter(fn ($p) => str_contains(mb_strtolower($p->name.' '.$p->label.' '.$p->module_label), $q)))
            ->groupBy('module')
            ->sortBy(fn ($items, $module) => ($i = array_search($module, $moduleOrder)) === false ? 999 : $i);
    @endphp

    {{-- ============================ HEADER ============================ --}}
    <div class="d-flex flex-wrap align-items-center gap-3 mb-4">
        <div class="me-auto">
            <h2 class="page-heading">Roles &amp; permissions</h2>
            <p class="page-subtitle">Decide what each role can see and do across the Co-Investment Facility.</p>
        </div>
        <button type="button" class="btn btn-cta" wire:click="addRole">
            <i class="bi bi-plus-lg me-1"></i> Add role
        </button>
    </div>

    {{-- ============================ STATS ============================ --}}
    <div class="row g-3 mb-4">
        @foreach ([
            ['Roles',          $roles->count(),                                   'bi-person-badge', 'primary'],
            ['Permissions',    $total,                                            'bi-key',          'accent'],
            ['Modules',        $permissions->pluck('module')->unique()->count(),  'bi-grid',         'warning'],
            ['Users assigned', $this->assignedUsers,                              'bi-people',       'muted'],
        ] as [$label, $value, $icon, $tone])
            <div class="col-6 col-xl-3">
                <div class="stat-card">
                    <span class="stat-icon tone-{{ $tone }}"><i class="bi {{ $icon }}"></i></span>
                    <div><div class="stat-value">{{ $value }}</div><div class="stat-label">{{ $label }}</div></div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- ============================ ROLE CARDS ============================ --}}
    <div class="row g-3 mb-4">
        @forelse ($roles as $role)
            <div class="col-md-6 col-xl-4 col-xxl-3" wire:key="role-card-{{ $role->id }}">
                <div class="card card-modern role-card h-100">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex align-items-start gap-3 mb-2">
                            <span class="stat-icon {{ $role->isExternal() ? 'tone-accent' : 'tone-primary' }}"><i class="bi {{ $role->icon }}"></i></span>
                            <div class="me-auto overflow-hidden">
                                <div class="fw-bold text-navy text-truncate">{{ $role->display_name }}</div>
                                <div class="d-flex flex-wrap gap-1 mt-1">
                                    <span class="pill {{ $role->isExternal() ? 'pill-ok' : 'pill-muted' }}">{{ ucfirst($role->type) }}</span>
                                    @if ($role->is_system)
                                        <span class="pill pill-muted" title="Seeded from the ToR — can't be deleted"><i class="bi bi-lock"></i> System</span>
                                    @endif
                                </div>
                            </div>
                            <div class="d-flex gap-1">
                                <button type="button" class="btn btn-icon" title="Edit role" wire:click="editRole({{ $role->id }})">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                @if ($role->isDeletable())
                                    <button type="button" class="btn btn-icon btn-icon-danger" title="Delete role"
                                            wire:click="deleteRole({{ $role->id }})"
                                            wire:confirm="Delete the {{ $role->display_name }} role? This cannot be undone.">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                @endif
                            </div>
                        </div>
                        <p class="small text-muted mb-3">{{ $role->description ?: 'No description.' }}</p>
                        <div class="d-flex justify-content-between small mt-auto pt-2 border-top">
                            <span><i class="bi bi-people me-1 text-faint"></i><strong class="text-navy">{{ $role->users_count }}</strong> {{ \Illuminate\Support\Str::plural('user', $role->users_count) }}</span>
                            <span><i class="bi bi-key me-1 text-faint"></i><strong class="text-navy">{{ $role->permissions->count() }}</strong> of {{ $total }} permissions</span>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12">
                <div class="card card-modern">
                    <div class="empty-state">
                        <div class="empty-icon"><i class="bi bi-person-badge"></i></div>
                        <h5 class="fw-bold text-navy mb-1">No roles yet</h5>
                        <p class="text-muted small mb-0">Run <code>php artisan db:seed --class=RolesAndPermissionsSeeder</code> to create the CIF roles.</p>
                    </div>
                </div>
            </div>
        @endforelse
    </div>

    {{-- ============================ PERMISSION MATRIX ============================ --}}
    @if ($roles->isNotEmpty())
        <div class="card card-modern">
            <div class="card-header py-3 d-flex flex-wrap align-items-center gap-2">
                <div class="me-auto">
                    <div class="fw-bold text-navy">Permission matrix</div>
                    <div class="small text-muted fw-normal">Click a cell to grant or remove a permission.</div>
                </div>
                <div class="input-search" style="min-width: 260px;">
                    <i class="bi bi-search"></i>
                    <input type="search" class="form-control" wire:model.live.debounce.300ms="search" placeholder="Search permissions…">
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-modern perm-matrix align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="ps-4">Permission</th>
                            @foreach ($roles as $role)
                                <th class="text-center" title="{{ $role->display_name }}">
                                    <i class="bi {{ $role->icon }} d-block fs-6 mb-1"></i>{{ $role->display_name }}
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $module => $items)
                            <tr class="perm-group" wire:key="mod-{{ $module }}">
                                <td colspan="{{ $roles->count() + 1 }}" class="ps-4">
                                    <i class="bi {{ $items->first()->module_icon }} me-2"></i>{{ $items->first()->module_label }}
                                </td>
                            </tr>
                            @foreach ($items as $p)
                                <tr wire:key="perm-{{ $p->id }}">
                                    <td class="ps-4">
                                        <div class="fw-semibold text-navy small">{{ $p->label ?: $p->name }}</div>
                                        <code class="perm-key">{{ $p->name }}</code>
                                    </td>
                                    @foreach ($roles as $role)
                                        @php
                                            $has    = isset($granted[$role->id][$p->name]);
                                            $locked = $has && $role->name === \App\Models\Role::ADMIN && in_array($p->name, \App\Models\Role::ADMIN_LOCKED_PERMISSIONS, true);
                                        @endphp
                                        <td class="text-center">
                                            <button type="button" class="perm-cell {{ $has ? 'is-on' : '' }}"
                                                    wire:click="toggle({{ $role->id }}, '{{ $p->name }}')"
                                                    wire:loading.attr="disabled" wire:target="toggle"
                                                    @disabled($locked)
                                                    title="{{ $locked ? 'Required for System Administrator' : ($has ? 'Remove from '.$role->display_name : 'Give to '.$role->display_name) }}">
                                                @if ($has)
                                                    <i class="bi {{ $locked ? 'bi-lock-fill' : 'bi-check-circle-fill' }}"></i>
                                                @else
                                                    <span>—</span>
                                                @endif
                                            </button>
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        @empty
                            <tr>
                                <td colspan="{{ $roles->count() + 1 }}" class="text-center text-muted small py-4">No permissions match "{{ $search }}".</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="card-footer small text-muted d-flex flex-wrap gap-3">
                <span><i class="bi bi-check-circle-fill perm-yes me-1"></i> Granted</span>
                <span><span class="perm-no me-1">—</span> Not granted</span>
                <span><i class="bi bi-lock-fill text-faint me-1"></i> Locked</span>
                <span class="ms-md-auto"><i class="bi bi-info-circle me-1"></i> Separation of duties: capital is verified by PMU and paid out by Finance; Gate 4 needs both the MD and the PS.</span>
            </div>
        </div>
    @endif

    {{-- Create / edit role drawer --}}
    <livewire:roles.drawer.role-form />
</div>