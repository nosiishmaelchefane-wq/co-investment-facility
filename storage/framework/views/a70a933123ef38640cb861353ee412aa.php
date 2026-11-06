<?php
use App\Support\Dashboard;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Lazy;
use Livewire\Component;
?>

<?php
    $d     = $this->d;
    $show  = $d->canSeeMoney();
    $money = $show ? $d->money() : null;
    $m     = fn ($v) => \App\Support\Dashboard::m($v);
    $u     = fn ($v) => \App\Support\Dashboard::u($v);
?>

<div class="<?php echo e($show ? 'col-lg-5' : 'd-none'); ?>">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($show): ?>
        <div class="card card-modern h-100">
            <div class="card-header py-3"><h6 class="fw-bold text-navy mb-0"><i class="bi bi-pie-chart me-1"></i> CAFI grant position</h6></div>
            <div class="card-body">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($money['grant'] > 0): ?>
                    <div wire:ignore><div class="chart-box" data-cif-chart="grant" data-cif-data="<?php echo e(json_encode(['released' => $money['released'], 'scheduled' => $money['scheduled'], 'unscheduled' => $money['unscheduled']])); ?>"></div></div>
                    <div class="row g-2 text-center mt-1">
                        <div class="col-4"><div class="small text-muted">Approved</div><div class="fw-bold text-navy"><?php echo e($m($money['grant'])); ?></div><div class="money-sub"><?php echo e($u($money['grant'])); ?></div></div>
                        <div class="col-4"><div class="small text-muted">Released</div><div class="fw-bold text-success"><?php echo e($m($money['released'])); ?></div><div class="money-sub"><?php echo e($u($money['released'])); ?></div></div>
                        <div class="col-4"><div class="small text-muted">Still to be disbursed</div><div class="fw-bold text-navy"><?php echo e($m($money['toDisburse'])); ?></div><div class="money-sub"><?php echo e($u($money['toDisburse'])); ?></div></div>
                    </div>
                    <hr class="my-3">
                    <div class="d-flex justify-content-between small">
                        <span class="text-muted">Investor capital verified</span>
                        <span class="fw-semibold"><?php echo e($m($money['verified'])); ?> <span class="text-muted fw-normal">of <?php echo e($m($money['private'])); ?> committed</span></span>
                    </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($money['leverage'] !== null): ?>
                        <div class="d-flex justify-content-between small mt-1">
                            <span class="text-muted">Private capital per M 1 of grant released</span>
                            <span class="fw-semibold">M <?php echo e(number_format($money['leverage'], 1)); ?></span>
                        </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php else: ?>
                    <div class="text-muted small py-5 text-center">No grant has been approved yet — figures appear once a transaction reaches Gate 5.</div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div><?php /**PATH C:\laragon\www\co-investment-facility\storage\framework\views/livewire/views/7298bebf.blade.php ENDPATH**/ ?>