<?php
use App\Support\Dashboard;
use Livewire\Attributes\Computed;
use Livewire\Component;
?>

<?php
    $d     = $this->d;
    $n     = $d->taskCount();
    $hour  = (int) now()->format('G');
    $hello = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
    $name  = \Illuminate\Support\Str::before(trim(auth()->user()->name), ' ') ?: auth()->user()->name;
?>

<div class="d-flex flex-wrap align-items-center gap-3 mb-4">
    <div class="me-auto">
        <h2 class="page-heading mb-1"><?php echo e($hello); ?>, <?php echo e($name); ?></h2>
        <p class="page-subtitle mb-0">
            <span class="pill pill-muted role-pill me-1"><i class="bi bi-person-badge"></i> <?php echo e($d->roleLabel()); ?></span>
            <?php echo e(now()->format('l, d F Y')); ?> ·
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($n): ?>
                <strong><?php echo e($n); ?></strong> <?php echo e(\Illuminate\Support\Str::plural('item', $n)); ?> <?php echo e($n === 1 ? 'needs' : 'need'); ?> your attention.
            <?php else: ?>
                Nothing is waiting for you right now.
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </p>
    </div>
    <a href="<?php echo e(route('expression-of-interest.index')); ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-list-ul me-1"></i> All applications</a>
    <a href="<?php echo e(route('dashboard')); ?>" class="btn btn-cta btn-sm"><i class="bi bi-arrow-clockwise me-1"></i> Refresh</a>
</div><?php /**PATH C:\laragon\www\co-investment-facility\storage\framework\views/livewire/views/af371280.blade.php ENDPATH**/ ?>