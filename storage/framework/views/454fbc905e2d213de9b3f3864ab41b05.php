<?php
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
?>


<div class="container-fluid p-3">
    <?php
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
    ?>

    
    <div class="d-flex flex-wrap align-items-center gap-3 mb-4">
        <div class="me-auto">
            <h2 class="page-heading">Roles &amp; permissions</h2>
            <p class="page-subtitle">Decide what each role can see and do across the Co-Investment Facility.</p>
        </div>
        <button type="button" class="btn btn-cta" wire:click="addRole">
            <i class="bi bi-plus-lg me-1"></i> Add role
        </button>
    </div>

    
    <div class="row g-3 mb-4">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = [
            ['Roles',          $roles->count(),                                   'bi-person-badge', 'primary'],
            ['Permissions',    $total,                                            'bi-key',          'accent'],
            ['Modules',        $permissions->pluck('module')->unique()->count(),  'bi-grid',         'warning'],
            ['Users assigned', $this->assignedUsers,                              'bi-people',       'muted'],
        ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$label, $value, $icon, $tone]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
            <div class="col-6 col-xl-3">
                <div class="stat-card">
                    <span class="stat-icon tone-<?php echo e($tone); ?>"><i class="bi <?php echo e($icon); ?>"></i></span>
                    <div><div class="stat-value"><?php echo e($value); ?></div><div class="stat-label"><?php echo e($label); ?></div></div>
                </div>
            </div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
    </div>

    
    <div class="row g-3 mb-4">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $roles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $role): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
            <div class="col-md-6 col-xl-4 col-xxl-3" <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'role-card-'.e($role->id).''; ?>wire:key="role-card-<?php echo e($role->id); ?>">
                <div class="card card-modern role-card h-100">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex align-items-start gap-3 mb-2">
                            <span class="stat-icon <?php echo e($role->isExternal() ? 'tone-accent' : 'tone-primary'); ?>"><i class="bi <?php echo e($role->icon); ?>"></i></span>
                            <div class="me-auto overflow-hidden">
                                <div class="fw-bold text-navy text-truncate"><?php echo e($role->display_name); ?></div>
                                <div class="d-flex flex-wrap gap-1 mt-1">
                                    <span class="pill <?php echo e($role->isExternal() ? 'pill-ok' : 'pill-muted'); ?>"><?php echo e(ucfirst($role->type)); ?></span>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($role->is_system): ?>
                                        <span class="pill pill-muted" title="Seeded from the ToR — can't be deleted"><i class="bi bi-lock"></i> System</span>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </div>
                            </div>
                            <div class="d-flex gap-1">
                                <button type="button" class="btn btn-icon" title="Edit role" wire:click="editRole(<?php echo e($role->id); ?>)">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($role->isDeletable()): ?>
                                    <button type="button" class="btn btn-icon btn-icon-danger" title="Delete role"
                                            wire:click="deleteRole(<?php echo e($role->id); ?>)"
                                            wire:confirm="Delete the <?php echo e($role->display_name); ?> role? This cannot be undone.">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>
                        </div>
                        <p class="small text-muted mb-3"><?php echo e($role->description ?: 'No description.'); ?></p>
                        <div class="d-flex justify-content-between small mt-auto pt-2 border-top">
                            <span><i class="bi bi-people me-1 text-faint"></i><strong class="text-navy"><?php echo e($role->users_count); ?></strong> <?php echo e(\Illuminate\Support\Str::plural('user', $role->users_count)); ?></span>
                            <span><i class="bi bi-key me-1 text-faint"></i><strong class="text-navy"><?php echo e($role->permissions->count()); ?></strong> of <?php echo e($total); ?> permissions</span>
                        </div>
                    </div>
                </div>
            </div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            <div class="col-12">
                <div class="card card-modern">
                    <div class="empty-state">
                        <div class="empty-icon"><i class="bi bi-person-badge"></i></div>
                        <h5 class="fw-bold text-navy mb-1">No roles yet</h5>
                        <p class="text-muted small mb-0">Run <code>php artisan db:seed --class=RolesAndPermissionsSeeder</code> to create the CIF roles.</p>
                    </div>
                </div>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($roles->isNotEmpty()): ?>
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
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $roles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $role): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <th class="text-center" title="<?php echo e($role->display_name); ?>">
                                    <i class="bi <?php echo e($role->icon); ?> d-block fs-6 mb-1"></i><?php echo e($role->display_name); ?>

                                </th>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $rows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $module => $items): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <tr class="perm-group" <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'mod-'.e($module).''; ?>wire:key="mod-<?php echo e($module); ?>">
                                <td colspan="<?php echo e($roles->count() + 1); ?>" class="ps-4">
                                    <i class="bi <?php echo e($items->first()->module_icon); ?> me-2"></i><?php echo e($items->first()->module_label); ?>

                                </td>
                            </tr>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $p): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <tr <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'perm-'.e($p->id).''; ?>wire:key="perm-<?php echo e($p->id); ?>">
                                    <td class="ps-4">
                                        <div class="fw-semibold text-navy small"><?php echo e($p->label ?: $p->name); ?></div>
                                        <code class="perm-key"><?php echo e($p->name); ?></code>
                                    </td>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $roles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $role): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                        <?php
                                            $has    = isset($granted[$role->id][$p->name]);
                                            $locked = $has && $role->name === \App\Models\Role::ADMIN && in_array($p->name, \App\Models\Role::ADMIN_LOCKED_PERMISSIONS, true);
                                        ?>
                                        <td class="text-center">
                                            <button type="button" class="perm-cell <?php echo e($has ? 'is-on' : ''); ?>"
                                                    wire:click="toggle(<?php echo e($role->id); ?>, '<?php echo e($p->name); ?>')"
                                                    wire:loading.attr="disabled" wire:target="toggle"
                                                    <?php if($locked): echo 'disabled'; endif; ?>
                                                    title="<?php echo e($locked ? 'Required for System Administrator' : ($has ? 'Remove from '.$role->display_name : 'Give to '.$role->display_name)); ?>">
                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($has): ?>
                                                    <i class="bi <?php echo e($locked ? 'bi-lock-fill' : 'bi-check-circle-fill'); ?>"></i>
                                                <?php else: ?>
                                                    <span>—</span>
                                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                            </button>
                                        </td>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                </tr>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                            <tr>
                                <td colspan="<?php echo e($roles->count() + 1); ?>" class="text-center text-muted small py-4">No permissions match "<?php echo e($search); ?>".</td>
                            </tr>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
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
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    
    <?php
$__split = function ($name, $params = []) {
    return [$name, $params];
};
[$__name, $__params] = $__split('roles.drawer.role-form', []);

$__keyOuter = $__key ?? null;

$__key = null;
$__componentSlots = [];

$__key ??= \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::generateKey('lw-1170732902-0', $__key);

$__html = app('livewire')->mount($__name, $__params, $__key, $__componentSlots);

echo $__html;

unset($__html);
unset($__key);
$__key = $__keyOuter;
unset($__keyOuter);
unset($__name);
unset($__params);
unset($__componentSlots);
unset($__split);
?>
</div><?php /**PATH C:\laragon\www\co-investment-facility\storage\framework\views/livewire/views/4d6051d8.blade.php ENDPATH**/ ?>