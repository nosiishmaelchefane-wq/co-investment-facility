<?php
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
?>


<div>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($showDrawer): ?>
    <?php
        $groups = $this->groups;
        $total  = $groups->sum(fn ($items) => $items->count());
    ?>

    <div class="drawer-overlay drawer-fade" wire:click="closeDrawer"></div>

    <div class="drawer drawer-md drawer-panel drawer-enter" role="dialog" aria-modal="true" aria-labelledby="roleDrawerLabel"
         wire:keydown.escape.window="closeDrawer">

        
        <div class="offcanvas-header drawer-header">
            <div>
                <div class="drawer-eyebrow">Roles &amp; permissions</div>
                <h5 class="offcanvas-title" id="roleDrawerLabel">
                    <?php echo e($mode === 'edit' ? 'Edit role · '.$form['label'] : 'Add a new role'); ?>

                </h5>
            </div>
            <button type="button" class="btn-close btn-close-white" wire:click="closeDrawer" aria-label="Close"></button>
        </div>

        <form wire:submit.prevent="save" class="form-modern d-flex flex-column flex-grow-1 overflow-hidden" novalidate>
            <div class="offcanvas-body drawer-body">

                
                <div class="form-section-head">
                    <span class="q-badge">1</span>
                    <div><h6>Role details</h6><p>How the role appears across the system.</p></div>
                </div>
                <div class="row g-3">
                    <div class="col-md-7">
                        <label class="form-label" for="r_label">Role name <span class="req">*</span></label>
                        <input type="text" id="r_label" wire:model.live.debounce.400ms="form.label" class="form-control <?php $__errorArgs = ['form.label'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.label'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label" for="r_key">
                            System key
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isSystem): ?> <i class="bi bi-lock text-faint" title="System roles keep their key"></i> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </label>
                        <input type="text" id="r_key" wire:model="form.name" <?php if($isSystem): echo 'readonly'; endif; ?>
                               class="form-control <?php echo e($isSystem ? 'bg-light' : ''); ?> <?php $__errorArgs = ['form.name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="r_desc">Description <span class="opt">(optional)</span></label>
                        <textarea id="r_desc" rows="2" wire:model="form.description" class="form-control <?php $__errorArgs = ['form.description'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>"></textarea>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.description'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
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

                
                <div class="form-section-head mt-4">
                    <span class="q-badge">2</span>
                    <div class="flex-grow-1">
                        <h6>Permissions</h6>
                        <p>Tick everything this role is allowed to do.</p>
                    </div>
                    <span class="pill <?php echo e(count($selected) ? 'pill-ok' : 'pill-muted'); ?> mt-1"><?php echo e(count($selected)); ?> of <?php echo e($total); ?> selected</span>
                </div>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['selected'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="callout callout-warning small mb-3"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $groups; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $module => $items): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <?php
                        $names = $items->pluck('name')->all();
                        $on    = count(array_intersect($names, $selected));
                    ?>
                    <div class="perm-module" <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'module-'.e($module).''; ?>wire:key="module-<?php echo e($module); ?>">
                        <div class="perm-module-head">
                            <span><i class="bi <?php echo e($items->first()->module_icon); ?> me-2"></i><?php echo e($items->first()->module_label); ?></span>
                            <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none small" wire:click="toggleModule('<?php echo e($module); ?>')">
                                <?php echo e($on === count($names) ? 'Clear' : 'Select all'); ?> <span class="text-muted">· <?php echo e($on); ?>/<?php echo e(count($names)); ?></span>
                            </button>
                        </div>
                        <div class="choice-group">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $p): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <?php
                                    $locked = $form['name'] === \App\Models\Role::ADMIN && in_array($p->name, \App\Models\Role::ADMIN_LOCKED_PERMISSIONS, true);
                                ?>
                                <label class="choice" <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'perm-'.e($p->id).''; ?>wire:key="perm-<?php echo e($p->id); ?>">
                                    <input type="checkbox" wire:model.live="selected" value="<?php echo e($p->name); ?>" <?php if($locked && in_array($p->name, $selected)): echo 'disabled'; endif; ?>>
                                    <span class="choice-box">
                                        <span class="choice-check"></span>
                                        <span class="flex-grow-1"><?php echo e($p->label ?: $p->name); ?> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($locked): ?> <i class="bi bi-lock text-faint" title="Required for System Administrator"></i> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></span>
                                        <code class="perm-key"><?php echo e($p->name); ?></code>
                                    </span>
                                </label>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </div>
                    </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    <div class="callout small">No permissions yet. Run <code>php artisan db:seed --class=RolesAndPermissionsSeeder</code>.</div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            
            <div class="drawer-footer">
                <button type="button" class="btn btn-outline-secondary" wire:click="closeDrawer">Cancel</button>
                <button type="submit" class="btn btn-brand ms-auto" wire:loading.attr="disabled" wire:target="save">
                    <span wire:loading.remove wire:target="save"><i class="bi bi-check-lg me-1"></i> <?php echo e($mode === 'new' ? 'Create role' : 'Save role'); ?></span>
                    <span wire:loading wire:target="save"><span class="spinner-border spinner-border-sm me-1"></span> Saving…</span>
                </button>
            </div>
        </form>
    </div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div><?php /**PATH C:\laragon\www\co-investment-facility\storage\framework\views/livewire/views/0ea13ec7.blade.php ENDPATH**/ ?>