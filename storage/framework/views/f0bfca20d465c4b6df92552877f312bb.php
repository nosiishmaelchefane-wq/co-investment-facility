<?php
use App\Support\Dashboard;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Lazy;
use Livewire\Component;
?>

<?php
    $D     = \App\Support\Dashboard::class;
    $d     = $this->d;
    $show  = $d->canSeeMoney();
    $rows  = $show ? $d->disbursements() : collect();
    $money = $show ? $d->money() : null;
    $m     = fn ($v) => $D::m($v);
    $u     = fn ($v) => $D::u($v);
?>

<div>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($rows->isNotEmpty()): ?>
        <div class="card card-modern mb-4">
            <div class="card-header py-3 d-flex flex-wrap align-items-center gap-2">
                <h6 class="fw-bold text-navy mb-0 me-auto"><i class="bi bi-cash-stack me-1"></i> Gate 5 &amp; disbursements</h6>
                <span class="small text-muted"><?php echo e($money['cleared']); ?> cleared · <?php echo e($m($money['toDisburse'])); ?> still to be disbursed</span>
            </div>
            <div class="table-responsive">
                <table class="table table-modern align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Transaction</th>
                            <th class="text-end">Approved grant</th>
                            <th class="text-end">Released</th>
                            <th class="text-end">Still to be disbursed</th>
                            <th style="min-width: 160px">Progress</th>
                            <th>Next tranche</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $rows->take($limit); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $r): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <?php [$sl, $sc] = $D::STATUSES[$r->eoi->status] ?? [ucfirst($r->eoi->status), 'status-draft']; ?>
                            <tr <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'disb-'.e($r->eoi->id).''; ?>wire:key="disb-<?php echo e($r->eoi->id); ?>">
                                <td>
                                    <a href="<?php echo e($D::eoiUrl($r->eoi->id, 'g5')); ?>" class="link-strong"><?php echo e($r->eoi->reference); ?></a>
                                    <div class="small text-muted"><?php echo e($r->eoi->business_name ?: $r->eoi->user?->name); ?></div>
                                    <span class="badge <?php echo e($sc); ?> mt-1"><?php echo e($sl); ?></span>
                                </td>
                                <td class="text-end"><?php echo e($m($r->grant)); ?><div class="money-sub"><?php echo e($u($r->grant)); ?></div></td>
                                <td class="text-end text-success"><?php echo e($m($r->released)); ?><div class="money-sub"><?php echo e($r->paid); ?> of <?php echo e($r->tranches); ?> <?php echo e(\Illuminate\Support\Str::plural('tranche', $r->tranches)); ?></div></td>
                                <td class="text-end fw-semibold text-navy"><?php echo e($m($r->left)); ?><div class="money-sub fw-normal"><?php echo e($u($r->left)); ?></div></td>
                                <td>
                                    <div class="progress progress-thin" role="progressbar" aria-valuenow="<?php echo e($r->pct); ?>" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar bg-success" style="width: <?php echo e($r->pct); ?>%"></div></div>
                                    <div class="money-sub mt-1"><?php echo e($r->pct); ?>%<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($r->last): ?> · last <?php echo e($r->last->format('d M Y')); ?><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                                </td>
                                <td class="small">
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($r->grant > 0 && $r->left <= 0.01): ?>
                                        <span class="pill pill-ok"><i class="bi bi-check-lg"></i> Fully disbursed</span>
                                    <?php elseif($r->next): ?>
                                        <span class="fw-semibold"><?php echo e($r->next->label); ?></span> · <?php echo e($m($r->next->amount)); ?>

                                        <div class="text-muted"><?php echo e($r->cleared ? 'Ready for Finance' : 'Awaiting clearance'); ?></div>
                                    <?php else: ?>
                                        <span class="text-muted">Not scheduled yet</span>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </td>
                            </tr>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($rows->count() > $limit): ?>
                <div class="card-footer small d-flex align-items-center">
                    <span class="text-muted me-auto">Showing <?php echo e($limit); ?> of <?php echo e($rows->count()); ?></span>
                    <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none" wire:click="showAll">Show all</button>
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div><?php /**PATH C:\laragon\www\co-investment-facility\storage\framework\views/livewire/views/9c840495.blade.php ENDPATH**/ ?>