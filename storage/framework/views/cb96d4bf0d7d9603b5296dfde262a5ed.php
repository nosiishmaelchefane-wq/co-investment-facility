<?php
use App\Support\Dashboard;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Lazy;
use Livewire\Component;
?>

<?php
    $D      = \App\Support\Dashboard::class;
    $recent = $this->d->recent($limit);
?>

<div class="card card-modern mb-2">
    <div class="card-header py-3 d-flex align-items-center">
        <h6 class="fw-bold text-navy mb-0 me-auto"><i class="bi bi-clock-history me-1"></i> Recently updated</h6>
        <a href="<?php echo e(route('expression-of-interest.index')); ?>" class="small text-decoration-none">View all <i class="bi bi-arrow-right"></i></a>
    </div>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($recent->isEmpty()): ?>
        <div class="empty-state py-4">
            <div class="empty-icon"><i class="bi bi-file-earmark-plus"></i></div>
            <h6 class="fw-bold text-navy mb-1">No applications yet</h6>
            <p class="text-muted small mb-0">Applications appear here once enterprises submit them.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-modern align-middle mb-0">
                <thead><tr><th>Reference</th><th>Business</th><th>Applicant</th><th>Status</th><th class="text-end">Updated</th></tr></thead>
                <tbody>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $recent; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $e): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <?php [$sl, $sc, $si] = $D::STATUSES[$e->status] ?? [ucfirst($e->status), 'status-draft', 'bi-circle']; ?>
                        <tr <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'recent-'.e($e->id).''; ?>wire:key="recent-<?php echo e($e->id); ?>">
                            <td><a href="<?php echo e($D::eoiUrl($e->id)); ?>" class="link-strong"><?php echo e($e->reference); ?></a></td>
                            <td class="small"><?php echo e($e->business_name ?: '—'); ?></td>
                            <td class="small text-muted"><?php echo e($e->user?->name); ?></td>
                            <td><span class="badge <?php echo e($sc); ?>"><i class="bi <?php echo e($si); ?> me-1"></i><?php echo e($sl); ?></span></td>
                            <td class="text-end small text-muted" title="<?php echo e($e->updated_at?->format('d M Y H:i')); ?>"><?php echo e($e->updated_at?->diffForHumans()); ?></td>
                        </tr>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div><?php /**PATH C:\laragon\www\co-investment-facility\storage\framework\views/livewire/views/ecaf9982.blade.php ENDPATH**/ ?>