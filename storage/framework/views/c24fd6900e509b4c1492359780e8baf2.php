<?php
use App\Support\Dashboard;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Lazy;
use Livewire\Component;
?>

<?php
    $D      = \App\Support\Dashboard::class;
    $d      = $this->d;
    $counts = $d->statusCounts();
    $t      = $d->totals();
    $chart  = $this->chart;
    $empty  = array_sum($chart['stages']['data']) === 0;
?>

<div class="card card-modern mb-4">
    <div class="card-header py-3 d-flex flex-wrap align-items-center gap-2">
        <h6 class="fw-bold text-navy mb-0 me-auto"><i class="bi bi-diagram-3 me-1"></i> Pipeline by gate</h6>
        <span class="small text-muted"><?php echo e($t['active']); ?> active · <?php echo e($t['drafts']); ?> <?php echo e(\Illuminate\Support\Str::plural('draft', $t['drafts'])); ?> not submitted · <?php echo e($t['exited']); ?> exited</span>
    </div>
    <div class="card-body">
        <div class="row g-2 mb-3">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $D::STAGES; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => [$label, $statuses, $icon]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <div class="col-6 col-md-4 col-xl-2" <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'stage-'.e($key).''; ?>wire:key="stage-<?php echo e($key); ?>">
                    <div class="stage-tile">
                        <div class="small text-muted"><i class="bi <?php echo e($icon); ?> me-1"></i><?php echo e($label); ?></div>
                        <div class="stage-n"><?php echo e($d->count(...$statuses)); ?></div>
                        <div class="d-flex flex-wrap gap-1 mt-1">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $statuses; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($counts[$s] ?? 0): ?>
                                    <a href="<?php echo e(route('expression-of-interest.index', ['status' => $s])); ?>" class="badge stage-chip <?php echo e($D::STATUSES[$s][1]); ?>"><?php echo e($D::STATUSES[$s][0]); ?> <?php echo e($counts[$s]); ?></a>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        </div>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($empty): ?>
            <div class="text-muted small py-4 text-center">No applications in the pipeline yet — the charts appear once applications are submitted.</div>
        <?php else: ?>
            <div class="row g-4">
                <div class="col-lg-7">
                    <div class="small fw-semibold text-muted mb-1">Applications at each gate</div>
                    <div wire:ignore><div class="chart-box" data-cif-chart="stages" data-cif-data="<?php echo e(json_encode($chart['stages'])); ?>"></div></div>
                </div>
                <div class="col-lg-5">
                    <div class="small fw-semibold text-muted mb-1">Where applications are</div>
                    <div wire:ignore><div class="chart-box" data-cif-chart="outcomes" data-cif-data="<?php echo e(json_encode($chart['outcomes'])); ?>"></div></div>
                </div>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
</div><?php /**PATH C:\laragon\www\co-investment-facility\storage\framework\views/livewire/views/9143126c.blade.php ENDPATH**/ ?>