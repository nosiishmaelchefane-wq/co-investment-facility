
<?php
    $investors = $ddForm['investor_names'] ?? null;
    $stage     = $DD::STAGES[$ddForm['stage'] ?? ''][0] ?? null;
?>
<section class="section-card">
    <div class="section-card-head">
        <span class="section-no"><i class="bi bi-file-earmark-text"></i></span>
        <h5 class="me-auto">Transaction at a glance</h5>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($stage): ?> <span class="pill pill-ok"><?php echo e($stage); ?></span> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>

    <div class="row g-3 mb-3">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = [
            ['Total transaction value',   $m($validated['total']),   $u($validated['total']),   'bi-bank',       'primary'],
            ['Private capital committed', $m($validated['private']), $u($validated['private']), 'bi-briefcase',  'accent'],
            ['CAFI grant requested',      $m($validated['grant']),   $u($validated['grant']),   'bi-cash-coin',  'warning'],
        ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$label, $value, $sub, $icon, $tone]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
            <div class="col-md-4">
                <div class="stat-card">
                    <span class="stat-icon tone-<?php echo e($tone); ?>"><i class="bi <?php echo e($icon); ?>"></i></span>
                    <div>
                        <div class="stat-value sm"><?php echo e($value); ?></div>
                        <div class="stat-label"><?php echo e($label); ?></div>
                        <div class="small text-muted"><?php echo e($sub); ?></div>
                    </div>
                </div>
            </div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
    </div>

    <div class="table-responsive">
        <table class="table table-modern align-middle mb-0 tss-table">
            <tbody>
                <tr><th>Enterprise</th><td><?php echo e(($ddForm['summary'] ?? '') ?: ($eoi->business_name ?: '—')); ?></td></tr>
                <tr><th>Investors</th><td><?php echo e($investors ?: '—'); ?></td></tr>
                <tr><th>Funding structure</th><td><?php echo e($validated['structure'] ?? '—'); ?></td></tr>
                <tr><th>Expected outcomes</th><td><?php echo e(($ddForm['expected_outcomes'] ?? '') ?: '—'); ?></td></tr>
                <tr><th>Key risks</th><td><?php echo e(($ddForm['key_risks'] ?? '') ?: '—'); ?></td></tr>
                <tr><th>Gate 2</th><td>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($dd): ?>
                        <span class="pill pill-ok"><i class="bi bi-check2-circle"></i> Proceed</span>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($ddForm['esgc_category'] ?? null): ?> · ESGC <span class="rating rating-<?php echo e($ddForm['esgc_category']); ?>"><?php echo e($DD::RATINGS[$ddForm['esgc_category']]); ?></span> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php else: ?> — <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </td></tr>
            </tbody>
        </table>
    </div>
</section><?php /**PATH C:\laragon\www\co-investment-facility\resources\views/expression-of-interest/committee/partials/glance.blade.php ENDPATH**/ ?>