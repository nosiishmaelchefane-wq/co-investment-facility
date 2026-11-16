
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($docs->isEmpty()): ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($empty): ?> <div class="small text-muted mb-2"><?php echo e($empty); ?></div> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php else: ?>
    <div class="mb-2">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $docs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $d): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
            <div class="file-row" <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'g5doc-'.e($d->id).''; ?>wire:key="g5doc-<?php echo e($d->id); ?>">
                <span class="file-row-icon"><i class="bi <?php echo e($d->kind === \App\Models\AuthorisationDocument::KIND_PS_SIGNED ? 'bi-file-earmark-check' : 'bi-file-earmark-text'); ?>"></i></span>
                <div class="flex-grow-1 small overflow-hidden">
                    <a href="<?php echo e(route('gate4.document', $d)); ?>" class="fw-semibold text-navy text-decoration-none text-truncate d-block"><?php echo e($d->name); ?></a>
                    <div class="text-muted"><?php echo e($d->size ? $d->sizeLabel().' · ' : ''); ?>added <?php echo e($d->created_at?->format('d M Y')); ?></div>
                </div>
                <a href="<?php echo e(route('gate4.document', $d)); ?>" class="btn btn-icon" title="Download"><i class="bi bi-download"></i></a>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canRemove): ?>
                    <button type="button" class="btn btn-icon btn-icon-danger" title="Remove" wire:click="removeDocument(<?php echo e($d->id); ?>)" wire:confirm="Remove <?php echo e($d->name); ?>?"><i class="bi bi-trash"></i></button>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
    </div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?><?php /**PATH C:\laragon\www\co-investment-facility\resources\views/expression-of-interest/authorisation/partials/documents.blade.php ENDPATH**/ ?>