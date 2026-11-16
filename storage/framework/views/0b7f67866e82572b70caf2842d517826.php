
<?php
    $docCount    = collect($submission['docs'])->flatten(1)->count();
    $docUploaded = collect($submission['docs'])->flatten(1)->filter(fn ($d) => $d[1])->count();
    $docMissing  = collect($submission['docs'])->flatten(1)->filter(fn ($d) => ! $d[1] && $d[2])->count();
?>
<section class="section-card">
    <div class="section-card-head">
        <span class="section-no"><i class="bi bi-person-vcard"></i></span>
        <div class="me-auto">
            <h5>Application &amp; enterprise documents</h5>
            <div class="small text-muted">What the applicant submitted in the EOI and the enterprise profile — for reference while reading the pack.</div>
        </div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($profile): ?>
            <span class="pill <?php echo e($docMissing ? 'pill-warn' : 'pill-ok'); ?>"><?php echo e($docUploaded); ?> / <?php echo e($docCount); ?> documents</span>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>

    <details class="evidence">
        <summary><i class="bi bi-card-list me-1"></i> Key facts</summary>
        <div class="row g-0">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $submission['facts']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $group => $rows): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <div class="col-lg-6">
                    <div class="small fw-semibold text-navy px-3 pt-3"><?php echo e($group); ?></div>
                    <dl class="evidence-grid">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $rows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$fLabel, $fValue, $fSource]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <dt><span class="src-tag <?php echo e($fSource === 'EOI' ? 'src-eoi' : 'src-profile'); ?>"><?php echo e($fSource); ?></span> <?php echo e($fLabel); ?></dt>
                            <dd><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(blank($fValue)): ?> <span class="text-muted fst-italic">Not provided</span> <?php else: ?> <span class="evidence-value"><?php echo e($fValue); ?></span> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></dd>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </dl>
                </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        </div>
    </details>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! $profile): ?>
        <div class="callout callout-warning small mt-3 mb-0"><i class="bi bi-exclamation-triangle me-1"></i> The applicant has no enterprise profile — only the EOI answers above are available.</div>
    <?php else: ?>
        <div class="row g-3 mt-1">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $submission['docs']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $sectionTitle => $docs): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <div class="col-md-6">
                    <div class="small fw-semibold text-navy mb-1"><?php echo e($sectionTitle); ?></div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $docs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$dLabel, $doc, $dReq]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <div class="file-row mb-1" <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'sub-doc-'.e(\Illuminate\Support\Str::slug($dLabel)).''; ?>wire:key="sub-doc-<?php echo e(\Illuminate\Support\Str::slug($dLabel)); ?>">
                            <span class="file-row-icon"><i class="bi <?php echo e($doc ? 'bi-file-earmark-check' : 'bi-file-earmark'); ?>"></i></span>
                            <div class="flex-grow-1 small overflow-hidden">
                                <div class="fw-semibold text-navy text-truncate"><?php echo e($dLabel); ?></div>
                                <div class="text-muted text-truncate"><?php echo e($doc ? $doc->original_name.' · '.$doc->size_label : ($dReq ? 'Required — not uploaded' : 'Optional — not uploaded')); ?></div>
                            </div>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($doc): ?>
                                <a href="<?php echo e(route('enterprise-documents.download', $doc)); ?>" class="btn btn-icon" title="Download"><i class="bi bi-download"></i></a>
                            <?php else: ?>
                                <span class="pill <?php echo e($dReq ? 'pill-warn' : 'pill-muted'); ?>"><?php echo e($dReq ? 'Missing' : 'Optional'); ?></span>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        </div>
        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->any(['eoi.view-all', 'users.manage'])): ?>
            <div class="small mt-2 text-end">
                <a href="<?php echo e(route('profile.index', $eoi->user_id)); ?>" target="_blank" class="text-decoration-none"><i class="bi bi-box-arrow-up-right me-1"></i> Open full enterprise profile</a>
            </div>
        <?php endif; ?>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</section><?php /**PATH C:\laragon\www\co-investment-facility\resources\views/expression-of-interest/committee/partials/submission.blade.php ENDPATH**/ ?>