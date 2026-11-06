<?php
use App\Support\Dashboard;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Lazy;
use Livewire\Component;
?>

<?php
    $tasks = $this->d->tasks();
    $total = $this->d->taskCount();
?>

<div class="mb-4">
    <div class="d-flex align-items-center gap-2 mb-2">
        <h5 class="fw-bold text-navy mb-0"><i class="bi bi-bell me-1"></i> Needs your attention</h5>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($total): ?> <span class="badge rounded-pill bg-danger"><?php echo e($total); ?></span> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(empty($tasks)): ?>
        <div class="card card-modern">
            <div class="empty-state py-4">
                <div class="empty-icon"><i class="bi bi-check2-all"></i></div>
                <h6 class="fw-bold text-navy mb-1">You're all caught up</h6>
                <p class="text-muted small mb-0">New work appears here as soon as an application reaches a step you're responsible for.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="row g-3">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $tasks; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $t): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <?php $open = $expanded[$i] ?? false; $count = $t['items']->count(); ?>
                <div class="col-md-6 col-xxl-4" <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'task-'.e($i).''; ?>wire:key="task-<?php echo e($i); ?>">
                    <div class="task-card">
                        <div class="task-head">
                            <span class="stat-icon tone-<?php echo e($t['tone']); ?> task-icon"><i class="bi <?php echo e($t['icon']); ?>"></i></span>
                            <div>
                                <div class="fw-semibold text-navy"><?php echo e($t['title']); ?></div>
                                <div class="small text-muted"><?php echo e($t['hint']); ?></div>
                                <span class="pill pill-muted role-pill mt-1"><i class="bi bi-person"></i> <?php echo e($t['role']); ?></span>
                            </div>
                            <span class="task-count"><?php echo e($count); ?></span>
                        </div>
                        <ul class="task-list">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $open ? $t['items'] : $t['items']->take(5); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $it): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <li>
                                    <a href="<?php echo e($it['url']); ?>">
                                        <span class="task-ref"><?php echo e($it['ref']); ?></span>
                                        <span class="small text-truncate"><?php echo e($it['name']); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($it['meta']): ?><span class="text-muted"> — <?php echo e($it['meta']); ?></span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></span>
                                        <i class="bi bi-chevron-right ms-auto small text-muted"></i>
                                    </a>
                                </li>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($count > 5): ?>
                                <li class="pt-1">
                                    <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none" wire:click="toggle(<?php echo e($i); ?>)">
                                        <?php echo e($open ? 'Show fewer' : 'Show all '.$count); ?>

                                    </button>
                                </li>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </ul>
                    </div>
                </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div><?php /**PATH C:\laragon\www\co-investment-facility\storage\framework\views/livewire/views/0c5b1ed1.blade.php ENDPATH**/ ?>