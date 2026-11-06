<?php
use App\Support\Dashboard;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Lazy;
use Livewire\Component;
?>

<?php
    $c     = $this->chart;
    $empty = array_sum($c['submitted']) === 0 && (! $c['money'] || array_sum($c['released']) === 0);
?>

<div class="<?php echo e($c['money'] ? 'col-lg-7' : 'col-12'); ?>">
    <div class="card card-modern h-100">
        <div class="card-header py-3"><h6 class="fw-bold text-navy mb-0"><i class="bi bi-graph-up me-1"></i> Last 12 months</h6></div>
        <div class="card-body">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($empty): ?>
                <div class="text-muted small py-5 text-center">No activity in the last 12 months.</div>
            <?php else: ?>
                <div wire:ignore><div class="chart-box" data-cif-chart="trend" data-cif-data="<?php echo e(json_encode($c)); ?>"></div></div>
                <div class="small text-muted mt-1">Bars: applications submitted<?php echo e($c['money'] ? ' · Line: CAFI grant released (Maloti)' : ''); ?>.</div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    </div>
</div><?php /**PATH C:\laragon\www\co-investment-facility\storage\framework\views/livewire/views/0f4081d7.blade.php ENDPATH**/ ?>