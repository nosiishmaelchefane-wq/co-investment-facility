<?php
use App\Support\Dashboard;
use Livewire\Attributes\Computed;
use Livewire\Component;
?>

<?php
    $d     = $this->d;
    $t     = $d->totals();
    $money = $d->canSeeMoney() ? $d->money() : null;
    $m     = fn ($v) => \App\Support\Dashboard::m($v);
    $u     = fn ($v) => \App\Support\Dashboard::u($v);

    $cards = [
        ['Applications', $t['applications'], $t['screening'].' awaiting screening', 'bi-collection', 'primary'],
        ['In the pipeline', $t['active'], $t['exited'].' exited (not progressed / withdrawn)', 'bi-diagram-3', 'warning'],
    ];
    if ($money) {
        $cards[] = ['Approved CAFI grant', $m($money['grant']), $u($money['grant']).' · '.$money['transactions'].' '.\Illuminate\Support\Str::plural('transaction', $money['transactions']), 'bi-patch-check', 'accent'];
        $cards[] = ['Disbursed', $m($money['released']), $money['releasedPct'].'% of approved · '.$m($money['toDisburse']).' still to be disbursed', 'bi-cash-coin', 'accent'];
    } else {
        $tasks   = $d->taskCount();
        $cards[] = ['Needs your attention', $tasks, 'across '.count($d->tasks()).' '.\Illuminate\Support\Str::plural('task', count($d->tasks())), 'bi-bell', 'accent'];
        $cards[] = ['Drafts not submitted', $t['drafts'], 'started by enterprises', 'bi-pencil-square', 'muted'];
    }
?>

<div class="row g-3 mb-4">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $cards; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$label, $value, $sub, $icon, $tone]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
        <div class="col-6 col-xl-3" <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'kpi-'.e($loop->index).''; ?>wire:key="kpi-<?php echo e($loop->index); ?>">
            <div class="stat-card w-100 h-100">
                <span class="stat-icon tone-<?php echo e($tone); ?>"><i class="bi <?php echo e($icon); ?>"></i></span>
                <div>
                    <div class="stat-value"><?php echo e($value); ?></div>
                    <div class="stat-label"><?php echo e($label); ?></div>
                    <div class="money-sub"><?php echo e($sub); ?></div>
                </div>
            </div>
        </div>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
</div><?php /**PATH C:\laragon\www\co-investment-facility\storage\framework\views/livewire/views/71e511ce.blade.php ENDPATH**/ ?>