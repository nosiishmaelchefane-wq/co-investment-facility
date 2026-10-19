<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    /** 'new' | 'edit' */
    public string $mode = 'new';

    public ?int $roleId = null;

    /** System roles keep their key (used in code: hasRole('pmu')). */
    public bool $isSystem = false;

    public bool $showDrawer = false;

    public array $form = [];

    /** Selected permission names */
    public array $selected = [];

    public function mount(): void
    {
        $this->form = $this->blankForm();
    }

    /** Permissions grouped by module, in Permission::MODULES order */
    #[Computed]
    public function groups()
    {
        $order = array_keys(Permission::MODULES);

        return Permission::orderBy('sort')->orderBy('name')->get()
            ->groupBy('module')
            ->sortBy(fn ($items, $module) => ($i = array_search($module, $order)) === false ? 999 : $i);
    }

    /* ---------------- Listeners ---------------- */

    #[On('open-role')]
    public function openNew(): void
    {
        $this->authorizeAccess();
        $this->resetDrawer();
        $this->showDrawer = true;
    }

    #[On('edit-role')]
    public function openEdit(int $id): void
    {
        $this->authorizeAccess();
        $this->resetDrawer();

        $role = Role::with('permissions:id,name')->findOrFail($id);

        $this->mode     = 'edit';
        $this->roleId   = $role->id;
        $this->isSystem = (bool) $role->is_system;
        $this->form     = [
            'label'       => $role->label ?? $role->name,
            'name'        => $role->name,
            'description' => $role->description ?? '',
            'type'        => $role->type ?? Role::TYPE_INTERNAL,
        ];
        $this->selected   = $role->permissions->pluck('name')->all();
        $this->showDrawer = true;
    }

    public function closeDrawer(): void
    {
        $this->showDrawer = false;
    }

    /* ---------------- Form helpers ---------------- */

    /** New role: build the key from the name as you type. */
    public function updatedFormLabel(string $value): void
    {
        if ($this->mode === 'new') {
            $this->form['name'] = Str::slug($value, '_');
        }
    }

    /** Tick / untick every permission in a module. */
    public function toggleModule(string $module): void
    {
        $names = $this->groups->get($module, collect())->pluck('name')->all();
        $allOn = empty(array_diff($names, $this->selected));

        $this->selected = $allOn
            ? array_values(array_diff($this->selected, $names))
            : array_values(array_unique(array_merge($this->selected, $names)));
    }

    /* ---------------- Save ---------------- */

    public function save(): void
    {
        $this->authorizeAccess();

        if (! $this->isSystem) {
            $this->form['name'] = Str::slug($this->form['name'] ?: $this->form['label'], '_');
        }

        $this->validate();

        // Never let the administrator role lose access to this screen
        if ($this->form['name'] === Role::ADMIN) {
            $missing = array_diff(Role::ADMIN_LOCKED_PERMISSIONS, $this->selected);
            if ($missing) {
                $this->addError('selected', 'System Administrator must keep: '.implode(', ', $missing).'.');
                return;
            }
        }

        $role = DB::transaction(function () {
            $attributes = [
                'label'       => trim($this->form['label']),
                'description' => trim($this->form['description']) ?: null,
                'type'        => $this->form['type'],
            ];

            if ($this->mode === 'edit') {
                $role = Role::findOrFail($this->roleId);
                if (! $role->is_system) {
                    $attributes['name'] = $this->form['name'];
                }
                $role->update($attributes);
            } else {
                $role = Role::create($attributes + [
                    'name'       => $this->form['name'],
                    'guard_name' => 'web',
                    'icon'       => 'bi-person-badge',
                    'is_system'  => false,
                ]);
            }

            $role->syncPermissions($this->selected);   // also clears Spatie's permission cache

            return $role;
        });

        $this->dispatch('role-saved', id: $role->id);
        $this->dispatch('notify', type: 'success', message: $this->mode === 'new'
            ? "{$role->display_name} role created."
            : "{$role->display_name} role updated.");

        $this->closeDrawer();
    }

    protected function rules(): array
    {
        $rolesTable = config('permission.table_names.roles');
        $permTable  = config('permission.table_names.permissions');

        return [
            'form.label'       => ['required', 'string', 'max:80'],
            'form.name'        => ['required', 'string', 'max:60', 'regex:/^[a-z0-9_]+$/',
                                   Rule::unique($rolesTable, 'name')->where('guard_name', 'web')->ignore($this->roleId)],
            'form.description' => ['nullable', 'string', 'max:500'],
            'form.type'        => ['required', Rule::in([Role::TYPE_INTERNAL, Role::TYPE_EXTERNAL])],
            'selected'         => ['array'],
            'selected.*'       => ['string', Rule::exists($permTable, 'name')->where('guard_name', 'web')],
        ];
    }

    protected function messages(): array
    {
        return [
            'form.name.regex'  => 'The system key may only use lowercase letters, numbers and underscores.',
            'form.name.unique' => 'A role with this key already exists.',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'form.label'       => 'role name',
            'form.name'        => 'system key',
            'form.description' => 'description',
            'form.type'        => 'user type',
        ];
    }

    protected function blankForm(): array
    {
        return ['label' => '', 'name' => '', 'description' => '', 'type' => Role::TYPE_INTERNAL];
    }

    protected function resetDrawer(): void
    {
        $this->resetValidation();
        $this->mode     = 'new';
        $this->roleId   = null;
        $this->isSystem = false;
        $this->form     = $this->blankForm();
        $this->selected = [];
    }

    protected function authorizeAccess(): void
    {
        abort_unless(auth()->user()?->can('roles.manage'), 403);
    }
};
?>

{{--
    Role create / edit drawer — Livewire 4 + Spatie.
    Open:  $dispatch('open-role')          Edit: $dispatch('edit-role', { id: 3 })
--}}
<div>
@if ($showDrawer)
    @php
        $groups = $this->groups;
        $total  = $groups->sum(fn ($items) => $items->count());
    @endphp

    <div class="drawer-overlay drawer-fade" wire:click="closeDrawer"></div>

    <div class="drawer drawer-md drawer-panel drawer-enter" role="dialog" aria-modal="true" aria-labelledby="roleDrawerLabel"
         wire:keydown.escape.window="closeDrawer">

        {{-- Header --}}
        <div class="offcanvas-header drawer-header">
            <div>
                <div class="drawer-eyebrow">Roles &amp; permissions</div>
                <h5 class="offcanvas-title" id="roleDrawerLabel">
                    {{ $mode === 'edit' ? 'Edit role · '.$form['label'] : 'Add a new role' }}
                </h5>
            </div>
            <button type="button" class="btn-close btn-close-white" wire:click="closeDrawer" aria-label="Close"></button>
        </div>

        <form wire:submit.prevent="save" class="form-modern d-flex flex-column flex-grow-1 overflow-hidden" novalidate>
            <div class="offcanvas-body drawer-body">

                {{-- 1. Role details --}}
                <div class="form-section-head">
                    <span class="q-badge">1</span>
                    <div><h6>Role details</h6><p>How the role appears across the system.</p></div>
                </div>
                <div class="row g-3">
                    <div class="col-md-7">
                        <label class="form-label" for="r_label">Role name <span class="req">*</span></label>
                        <input type="text" id="r_label" wire:model.live.debounce.400ms="form.label" class="form-control @error('form.label') is-invalid @enderror">
                        @error('form.label') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-5">
                        <label class="form-label" for="r_key">
                            System key
                            @if ($isSystem) <i class="bi bi-lock text-faint" title="System roles keep their key"></i> @endif
                        </label>
                        <input type="text" id="r_key" wire:model="form.name" @readonly($isSystem)
                               class="form-control {{ $isSystem ? 'bg-light' : '' }} @error('form.name') is-invalid @enderror">
                        @error('form.name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="r_desc">Description <span class="opt">(optional)</span></label>
                        <textarea id="r_desc" rows="2" wire:model="form.description" class="form-control @error('form.description') is-invalid @enderror"></textarea>
                        @error('form.description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>

                <div class="mt-3">
                    <label class="form-label d-block">User type</label>
                    <div class="choice-group cols-2">
                        <label class="choice">
                            <input type="radio" wire:model="form.type" value="internal">
                            <span class="choice-box"><span class="choice-radio"></span> Internal (LEHSFF / CAFI staff)</span>
                        </label>
                        <label class="choice">
                            <input type="radio" wire:model="form.type" value="external">
                            <span class="choice-box"><span class="choice-radio"></span> External (sees own records only)</span>
                        </label>
                    </div>
                </div>

                {{-- 2. Permissions --}}
                <div class="form-section-head mt-4">
                    <span class="q-badge">2</span>
                    <div class="flex-grow-1">
                        <h6>Permissions</h6>
                        <p>Tick everything this role is allowed to do.</p>
                    </div>
                    <span class="pill {{ count($selected) ? 'pill-ok' : 'pill-muted' }} mt-1">{{ count($selected) }} of {{ $total }} selected</span>
                </div>

                @error('selected') <div class="callout callout-warning small mb-3">{{ $message }}</div> @enderror

                @forelse ($groups as $module => $items)
                    @php
                        $names = $items->pluck('name')->all();
                        $on    = count(array_intersect($names, $selected));
                    @endphp
                    <div class="perm-module" wire:key="module-{{ $module }}">
                        <div class="perm-module-head">
                            <span><i class="bi {{ $items->first()->module_icon }} me-2"></i>{{ $items->first()->module_label }}</span>
                            <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none small" wire:click="toggleModule('{{ $module }}')">
                                {{ $on === count($names) ? 'Clear' : 'Select all' }} <span class="text-muted">· {{ $on }}/{{ count($names) }}</span>
                            </button>
                        </div>
                        <div class="choice-group">
                            @foreach ($items as $p)
                                @php
                                    $locked = $form['name'] === \App\Models\Role::ADMIN && in_array($p->name, \App\Models\Role::ADMIN_LOCKED_PERMISSIONS, true);
                                @endphp
                                <label class="choice" wire:key="perm-{{ $p->id }}">
                                    <input type="checkbox" wire:model.live="selected" value="{{ $p->name }}" @disabled($locked && in_array($p->name, $selected))>
                                    <span class="choice-box">
                                        <span class="choice-check"></span>
                                        <span class="flex-grow-1">{{ $p->label ?: $p->name }} @if ($locked) <i class="bi bi-lock text-faint" title="Required for System Administrator"></i> @endif</span>
                                        <code class="perm-key">{{ $p->name }}</code>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <div class="callout small">No permissions yet. Run <code>php artisan db:seed --class=RolesAndPermissionsSeeder</code>.</div>
                @endforelse
            </div>

            {{-- Footer --}}
            <div class="drawer-footer">
                <button type="button" class="btn btn-outline-secondary" wire:click="closeDrawer">Cancel</button>
                <button type="submit" class="btn btn-brand ms-auto" wire:loading.attr="disabled" wire:target="save">
                    <span wire:loading.remove wire:target="save"><i class="bi bi-check-lg me-1"></i> {{ $mode === 'new' ? 'Create role' : 'Save role' }}</span>
                    <span wire:loading wire:target="save"><span class="spinner-border spinner-border-sm me-1"></span> Saving…</span>
                </button>
            </div>
        </form>
    </div>
@endif
</div>