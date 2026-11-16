<?php
use App\Models\EnterpriseInvestment;
use App\Models\EnterpriseProfile;
use App\Models\ExpressionOfInterest;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;
?>

<div>
    <?php
        $p         = $this->profile;
        $I         = \App\Models\EnterpriseInvestment::class;
        $P         = \App\Models\EnterpriseProfile::class;
        $canManage = $this->canManage;
        $all       = $this->investments;
        $groups    = $this->groups;
        $facility  = $this->facility;
        $roundRows = $this->roundRows;

        $rate = max((float) config('cif.usd_to_lsl', 18.00), 0.0001);
        $lsl  = fn ($m) => $m === null || $m === '' ? '—' : 'M '.number_format((float) $m);
        $usd  = fn ($m) => $m === null || $m === '' ? '' : 'US$ '.number_format((float) $m / $rate);

        $counted   = $all->where('status', '!=', $I::STATUS_REJECTED);
        $total     = (float) $counted->sum('amount');
        $verified  = (float) $all->where('status', $I::STATUS_VERIFIED)->sum('amount');
        $pending   = $all->where('status', $I::STATUS_DECLARED)->count();
        $repeat    = $groups->where('count', '>', 1)->count();
        $committed = (float) collect($roundRows)->sum(fn ($r) => is_numeric($r['ticket'] ?? null) ? (float) $r['ticket'] : 0);
        $shown     = $investor === '' ? $all : $all->filter(fn ($i) => $i->investor_key === $investor);

        // Display name for an investor key
        $nameFor = fn ($key) => $groups->firstWhere('key', $key)['name'] ?? (collect($roundRows)->firstWhere('key', $key)['name'] ?? $key);
    ?>

    
    <div class="card card-modern mb-4">
        <div class="card-body d-flex flex-wrap align-items-center gap-3">
            <span class="stat-icon tone-primary"><i class="bi bi-bank"></i></span>
            <div class="me-auto" style="max-width: 640px">
                <h5 class="mb-1 text-navy fw-bold">Investment history</h5>
                <div class="small text-muted">Every investment made into <?php echo e($p?->short_name ?: ($p?->registered_name ?: $this->eoi->business_name)); ?>, one row per investment. Investors are recognised by name, so the <strong>Investor portfolio</strong> below shows how much each investor has put into every enterprise on the facility — use it to judge their track record at Gate 1 and to monitor their returns.</div>
            </div>
            <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="$parent.showTab('g1')"><i class="bi bi-arrow-left me-1"></i> Gate 1 · Eligibility</button>
        </div>
    </div>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! $p): ?>
        <div class="callout callout-warning small"><i class="bi bi-exclamation-triangle me-1"></i> This enterprise has no profile yet, so there is no investment history to show.</div>
    <?php else: ?>

    
    <div class="row g-3 mb-4">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = [
            ['Committed — current round', $lsl($committed), $roundRows ? $usd($committed).' · '.count($roundRows).' '.\Illuminate\Support\Str::plural('investor', count($roundRows)).' (not yet invested)' : 'no investors listed on the profile', 'bi-briefcase', true],
            ['Invested to date', $lsl($total), $total > 0 ? $usd($total).' · already put in' : 'nothing recorded under Investments received', 'bi-cash-stack', false],
            ['Investments', $counted->count(), $counted->isEmpty() ? 'none recorded yet' : ($pending ? $pending.' not yet verified' : 'all reviewed'), 'bi-receipt', false],
            ['Investors', $groups->where('count', '>', 0)->count(), $groups->isEmpty() ? 'none recorded yet' : ($repeat ? $repeat.' invested more than once' : 'no repeat investors'), 'bi-people', false],
            ['Verified', $lsl($verified), $total > 0 ? round($verified / $total * 100).'% of invested to date' : 'checked against proof', 'bi-patch-check', false],
        ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$kLabel, $kValue, $kSub, $kIcon, $kHighlight]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
            <div class="col-6 col-lg">
                <div class="card card-modern h-100 <?php echo e($kHighlight ? 'border-primary' : ''); ?>"><div class="card-body">
                    <div class="small text-muted fw-semibold"><i class="bi <?php echo e($kIcon); ?> me-1"></i> <?php echo e($kLabel); ?></div>
                    <div class="fs-5 fw-bold text-navy"><?php echo e($kValue); ?></div>
                    <div class="small text-muted"><?php echo e($kSub); ?></div>
                </div></div>
            </div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
    </div>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($all->isEmpty()): ?>
        <div class="callout small mb-4">
            <i class="bi bi-info-circle me-1"></i> <strong>No investments recorded yet.</strong>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($roundRows): ?>
                The <?php echo e($lsl($committed)); ?> shown under <em>Committed — current round</em> is what the investor(s) have <strong>promised</strong> for this application (from the profile). <em>Invested to date</em> only counts money that has <strong>actually been put in</strong>, recorded by the enterprise under <em>Investments received</em> on its profile.
            <?php else: ?>
                <em>Invested to date</em> counts money investors have actually put into the enterprise. It fills in when the enterprise adds investments under <em>Investments received</em> on its profile.
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($invOpen && $canManage && $invEditingId): ?>
        <div class="card card-modern mb-4">
            <div class="card-body">
                <div class="card-title-sm"><i class="bi bi-pencil-square"></i> Edit investment</div>
                <div class="row g-2">
                    <div class="col-md-6"><label class="form-label small fw-semibold">Investor name <span class="req">*</span></label>
                        <input type="text" class="form-control form-control-sm <?php $__errorArgs = ['inv.investor_name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model.blur="inv.investor_name" list="known-investors">
                        <datalist id="known-investors"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $groups; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $g): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?><option value="<?php echo e($g['name']); ?>"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $roundRows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $r): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?><option value="<?php echo e($r['name']); ?>"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?></datalist>
                        <div class="form-text">Use the same spelling each time — investors are recognised across enterprises by name.</div>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['inv.investor_name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                    <div class="col-md-3"><label class="form-label small fw-semibold">Investor type</label>
                        <select class="form-select form-select-sm" wire:model="inv.investor_type"><option value="">Select…</option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $P::INVESTOR_TYPES; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $o): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?><option><?php echo e($o); ?></option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?></select></div>
                    <div class="col-md-3"><label class="form-label small fw-semibold">Country</label>
                        <input type="text" class="form-control form-control-sm" wire:model="inv.investor_country"></div>
                    <div class="col-md-4"><label class="form-label small fw-semibold">Amount (M) <span class="req">*</span></label>
                        <input type="number" min="0" step="1000" class="form-control form-control-sm <?php $__errorArgs = ['inv.amount'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model.blur="inv.amount">
                        <div class="form-text"><?php echo e($usd($inv['amount'] ?? null)); ?></div>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['inv.amount'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                    <div class="col-md-4"><label class="form-label small fw-semibold">Date of investment <span class="req">*</span></label>
                        <input type="date" max="<?php echo e(now()->toDateString()); ?>" class="form-control form-control-sm <?php $__errorArgs = ['inv.invested_on'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model="inv.invested_on">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['inv.invested_on'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                    <div class="col-md-4"><label class="form-label small fw-semibold">Instrument</label>
                        <select class="form-select form-select-sm" wire:model="inv.instrument"><option value="">Select…</option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $I::INSTRUMENTS; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $o): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?><option value="<?php echo e($k); ?>"><?php echo e($o); ?></option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?></select></div>
                    <div class="col-md-4"><label class="form-label small fw-semibold">Round</label>
                        <input type="text" class="form-control form-control-sm" wire:model="inv.round_label" placeholder="e.g. Seed, Series A"></div>
                    <div class="col-md-8"><label class="form-label small fw-semibold">Proof of investment</label>
                        <input type="file" class="form-control form-control-sm <?php $__errorArgs = ['invProof'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model="invProof">
                        <div class="form-text">Bank statement, share certificate or signed agreement — max <?php echo e($I::MAX_KB / 1024); ?> MB.</div>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['invProof'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                    <div class="col-12"><label class="form-label small fw-semibold">Notes</label>
                        <input type="text" class="form-control form-control-sm" wire:model="inv.notes"></div>
                </div>
                <div class="d-flex gap-2 mt-3">
                    <button type="button" class="btn btn-link btn-sm text-secondary text-decoration-none px-0" wire:click="cancelInvestment">Cancel</button>
                    <button type="button" class="btn btn-brand btn-sm ms-auto" wire:click="saveInvestment" wire:loading.attr="disabled" wire:target="saveInvestment,invProof"><i class="bi bi-check-lg me-1"></i> Save investment</button>
                </div>
            </div>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    
    <div class="card card-modern mb-4">
        <div class="card-body">
            <div class="card-title-sm"><i class="bi bi-people"></i> By investor — in this enterprise</div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($groups->isEmpty()): ?>
                <p class="small text-muted mb-0">No investments recorded yet.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-modern table-sm align-middle mb-0">
                        <thead><tr><th>Investor</th><th class="text-center">Times invested</th><th class="text-end">Total here</th><th class="text-end">Verified</th><th>First – last</th><th>Across the facility</th><th></th></tr></thead>
                        <tbody>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $groups; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $g): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <?php $fr = $facility[$g['key']] ?? null; ?>
                                <tr <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'grp-'.e(md5($g['key'])).''; ?>wire:key="grp-<?php echo e(md5($g['key'])); ?>" class="<?php echo e($investor === $g['key'] ? 'table-active' : ''); ?>">
                                    <td><div class="fw-semibold text-navy small"><?php echo e($g['name']); ?></div>
                                        <div class="small text-muted"><?php echo e(collect([$g['type'], $g['country']])->filter()->implode(' · ')); ?></div></td>
                                    <td class="text-center">
                                        <span class="fw-bold text-navy"><?php echo e($g['count']); ?></span>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($g['count'] > 1): ?> <span class="pill pill-ok ms-1"><i class="bi bi-arrow-repeat"></i> Repeat</span> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </td>
                                    <td class="text-end text-nowrap"><?php echo e($lsl($g['total'])); ?><div class="small text-muted"><?php echo e($usd($g['total'])); ?></div></td>
                                    <td class="text-end text-nowrap <?php echo e($g['verified'] < $g['total'] ? 'text-warning' : ''); ?>"><?php echo e($lsl($g['verified'])); ?></td>
                                    <td class="small text-nowrap"><?php echo e($g['first']?->format('M Y')); ?> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($g['count'] > 1): ?> – <?php echo e($g['last']?->format('M Y')); ?> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></td>
                                    <td class="small text-nowrap"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($fr && $fr['investments']): ?>
                                            <a href="#track-<?php echo e(md5($g['key'])); ?>" class="text-decoration-none fw-semibold"><?php echo e($fr['enterprises']); ?> <?php echo e(\Illuminate\Support\Str::plural('enterprise', $fr['enterprises'])); ?></a>
                                            <div class="text-muted"><?php echo e($lsl($fr['total'])); ?> in total</div>
                                        <?php else: ?> <span class="text-muted">—</span> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></td>
                                    <td class="text-end"><button type="button" class="btn btn-sm btn-outline-secondary" wire:click="showInvestor(<?php echo \Illuminate\Support\Js::from($g['key'])->toHtml() ?>)"><?php echo e($investor === $g['key'] ? 'Show all' : 'Show'); ?></button></td>
                                </tr>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="small text-muted mt-2"><i class="bi bi-info-circle me-1"></i> Rejected investments are not counted. Investors are matched by name (ignoring case, punctuation and "(Pty) Ltd").</div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    </div>

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($roundRows): ?>
        <div class="card card-modern mb-4">
            <div class="card-body">
                <div class="card-title-sm"><i class="bi bi-briefcase"></i> Investors in the current round <span class="small text-muted fw-normal">· from the enterprise profile</span></div>
                <div class="table-responsive">
                    <table class="table table-modern table-sm align-middle mb-0">
                        <thead><tr><th>Investor</th><th class="text-end">Committed now</th><th>Invested here before</th><th>Across the facility</th></tr></thead>
                        <tbody>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $roundRows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ri => $r): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <?php
                                    $k    = $r['key'];
                                    $prev = $groups->firstWhere('key', $k);
                                    $fr   = $facility[$k] ?? null;
                                ?>
                                <tr <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'round-'.e($ri).''; ?>wire:key="round-<?php echo e($ri); ?>">
                                    <td><div class="fw-semibold text-navy small"><?php echo e($r['name']); ?></div>
                                        <div class="small text-muted"><?php echo e($r['country'] ?? ''); ?></div></td>
                                    <td class="text-end text-nowrap"><?php echo e($lsl($r['ticket'] ?? null)); ?><div class="small text-muted"><?php echo e($usd($r['ticket'] ?? null)); ?></div></td>
                                    <td class="small">
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($prev && $prev['count'] > 0): ?>
                                            <span class="pill pill-ok"><i class="bi bi-arrow-repeat"></i> <?php echo e($prev['count']); ?> <?php echo e(\Illuminate\Support\Str::plural('time', $prev['count'])); ?></span> · <?php echo e($lsl($prev['total'])); ?>

                                        <?php else: ?>
                                            <span class="pill pill-muted">New investor</span>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </td>
                                    <td class="small"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($fr && $fr['investments']): ?>
                                            <a href="#track-<?php echo e(md5($k)); ?>" class="text-decoration-none fw-semibold"><?php echo e($fr['investments']); ?> <?php echo e(\Illuminate\Support\Str::plural('investment', $fr['investments'])); ?> in <?php echo e($fr['enterprises']); ?> <?php echo e(\Illuminate\Support\Str::plural('enterprise', $fr['enterprises'])); ?></a>
                                        <?php else: ?> <span class="pill pill-muted">No record in the facility</span> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></td>
                                </tr>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($facility): ?>
        <div class="card card-modern mb-4">
            <div class="card-body">
                <div class="card-title-sm"><i class="bi bi-diagram-3"></i> Investor portfolio across the facility</div>
                <p class="small text-muted mb-3">How much each investor has put into each enterprise on the facility — use it to monitor their returns. Rejected investments are not counted; current-round commitments are shown separately.</p>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $facility; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $fr): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <details class="border rounded-3 mb-2" id="track-<?php echo e(md5($key)); ?>" <?php if($loop->first): ?> open <?php endif; ?>>
                        <summary class="d-flex flex-wrap align-items-center gap-2 p-2" style="cursor: pointer">
                            <span class="me-auto fw-semibold text-navy small"><?php echo e($nameFor($key)); ?></span>
                            <span class="pill <?php echo e($fr['investments'] > 1 ? 'pill-ok' : 'pill-muted'); ?>"><i class="bi bi-receipt"></i> <?php echo e($fr['investments']); ?> <?php echo e(\Illuminate\Support\Str::plural('investment', $fr['investments'])); ?></span>
                            <span class="pill pill-muted"><i class="bi bi-buildings"></i> <?php echo e($fr['enterprises']); ?> <?php echo e(\Illuminate\Support\Str::plural('enterprise', $fr['enterprises'])); ?></span>
                            <span class="small fw-semibold text-navy"><?php echo e($lsl($fr['total'])); ?></span>
                            <span class="small text-muted"><?php echo e($lsl($fr['verified'])); ?> verified</span>
                        </summary>
                        <div class="px-2 pb-2">
                            <div class="table-responsive">
                                <table class="table table-modern table-sm align-middle mb-0">
                                    <thead><tr><th>Enterprise</th><th class="text-center">Investments</th><th class="text-end">Invested</th><th style="min-width: 130px">Share of portfolio</th><th class="text-end">Verified</th><th>First – last</th><th class="text-end">Current round</th></tr></thead>
                                    <tbody>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $fr['per']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                            <?php $share = $fr['total'] > 0 && $row['count'] ? round($row['total'] / $fr['total'] * 100) : null; ?>
                                            <tr class="<?php echo e($row['current'] ? 'table-active' : ''); ?>">
                                                <td class="small fw-semibold text-navy"><?php echo e($row['name']); ?> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($row['current']): ?> <span class="pill pill-muted ms-1">This application</span> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></td>
                                                <td class="text-center"><?php echo e($row['count'] ?: '—'); ?></td>
                                                <td class="text-end text-nowrap"><?php echo e($row['count'] ? $lsl($row['total']) : '—'); ?><div class="small text-muted"><?php echo e($row['count'] ? $usd($row['total']) : ''); ?></div></td>
                                                <td><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($share !== null): ?>
                                                        <div class="d-flex align-items-center gap-2"><div class="progress flex-grow-1" style="height: 6px"><div class="progress-bar bg-brand" style="width: <?php echo e($share); ?>%"></div></div><span class="small text-muted"><?php echo e($share); ?>%</span></div>
                                                    <?php else: ?> <span class="small text-muted">—</span> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></td>
                                                <td class="text-end text-nowrap"><?php echo e($row['count'] ? $lsl($row['verified']) : '—'); ?></td>
                                                <td class="small text-nowrap"><?php echo e($row['first']?->format('M Y') ?? '—'); ?> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($row['count'] > 1): ?> – <?php echo e($row['last']?->format('M Y')); ?> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></td>
                                                <td class="text-end text-nowrap small"><?php echo e($row['committed'] !== null ? $lsl($row['committed']) : '—'); ?></td>
                                            </tr>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                        <?php $committedTotal = collect($fr['per'])->sum(fn ($r) => (float) ($r['committed'] ?? 0)); ?>
                                        <tr class="fw-bold">
                                            <td class="small text-navy">Total</td>
                                            <td class="text-center"><?php echo e($fr['investments']); ?></td>
                                            <td class="text-end text-nowrap"><?php echo e($lsl($fr['total'])); ?><div class="small text-muted fw-normal"><?php echo e($usd($fr['total'])); ?></div></td>
                                            <td class="small text-muted fw-normal"><?php echo e($fr['enterprises']); ?> <?php echo e(\Illuminate\Support\Str::plural('enterprise', $fr['enterprises'])); ?></td>
                                            <td class="text-end text-nowrap"><?php echo e($lsl($fr['verified'])); ?></td>
                                            <td></td>
                                            <td class="text-end text-nowrap small"><?php echo e($committedTotal > 0 ? $lsl($committedTotal) : '—'); ?></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </details>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </div>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    
    <div class="card card-modern">
        <div class="card-body">
            <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                <div class="card-title-sm mb-0 me-auto"><i class="bi bi-list-ul"></i> <?php echo e($investor === '' ? 'All investments' : 'Investments by '.$nameFor($investor)); ?></div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($investor !== ''): ?> <button type="button" class="btn btn-sm btn-link text-decoration-none" wire:click="$set('investor', '')">Show all</button> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($shown->isEmpty()): ?>
                <p class="small text-muted mb-0">No investments to show.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-modern table-sm align-middle mb-0">
                        <thead><tr><th>Date</th><th>Investor</th><th class="text-end">Amount</th><th>Instrument / round</th><th>Status</th><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canManage): ?><th class="text-end">Actions</th><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></tr></thead>
                        <tbody>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $shown; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <?php [$sLabel, $sPill, $sIcon] = $I::STATUSES[$i->status] ?? $I::STATUSES['declared']; ?>
                                <tr <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'inv-'.e($i->id).''; ?>wire:key="inv-<?php echo e($i->id); ?>">
                                    <td class="small text-nowrap"><?php echo e($i->invested_on?->format('d M Y')); ?></td>
                                    <td><div class="fw-semibold text-navy small"><?php echo e($i->investor_name); ?></div>
                                        <div class="small text-muted"><?php echo e($I::SOURCES[$i->source] ?? ''); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($i->recorder && $i->source === 'reviewer'): ?> (<?php echo e($i->recorder->name); ?>)<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($i->notes): ?> <div class="small text-muted fst-italic"><?php echo e($i->notes); ?></div> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></td>
                                    <td class="text-end text-nowrap"><?php echo e($lsl($i->amount)); ?><div class="small text-muted"><?php echo e($usd($i->amount)); ?></div></td>
                                    <td class="small"><?php echo e(collect([$I::INSTRUMENTS[$i->instrument ?? ''] ?? null, $i->round_label])->filter()->implode(' · ') ?: '—'); ?></td>
                                    <td>
                                        <span class="pill <?php echo e($sPill); ?>"><i class="bi <?php echo e($sIcon); ?>"></i> <?php echo e($sLabel); ?></span>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($i->reviewer): ?> <div class="small text-muted mt-1"><?php echo e($i->reviewer->name); ?>, <?php echo e($i->reviewed_at?->format('d M Y')); ?></div> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($i->review_note): ?> <div class="small <?php echo e($i->status === 'rejected' ? 'text-danger' : 'text-muted'); ?>"><?php echo e($i->review_note); ?></div> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($i->proof_path): ?>
                                            <a href="<?php echo e(route('investments.proof', $i)); ?>" class="small d-block text-decoration-none mt-1"><i class="bi bi-paperclip"></i> <?php echo e(\Illuminate\Support\Str::limit($i->proof_name, 28)); ?> · <?php echo e($i->proof_size_label); ?></a>
                                        <?php else: ?>
                                            <div class="small text-muted mt-1"><i class="bi bi-paperclip"></i> No proof</div>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </td>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canManage): ?>
                                        <td class="text-end text-nowrap">
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($i->status === 'declared'): ?>
                                                <button type="button" class="btn btn-sm btn-outline-success" wire:click="startReview(<?php echo e($i->id); ?>, 'verified')" title="Verify"><i class="bi bi-check-lg"></i></button>
                                                <button type="button" class="btn btn-sm btn-outline-danger" wire:click="startReview(<?php echo e($i->id); ?>, 'rejected')" title="Reject"><i class="bi bi-x-lg"></i></button>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="resetReview(<?php echo e($i->id); ?>)" wire:confirm="Set this investment back to 'not yet verified'?" title="Undo review"><i class="bi bi-arrow-counterclockwise"></i></button>
                                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                            <button type="button" class="btn btn-icon" title="Edit" wire:click="editInvestment(<?php echo e($i->id); ?>)"><i class="bi bi-pencil"></i></button>
                                            <button type="button" class="btn btn-icon btn-icon-danger" title="Delete" wire:click="deleteInvestment(<?php echo e($i->id); ?>)" wire:confirm="Delete this investment record?"><i class="bi bi-trash"></i></button>
                                        </td>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </tr>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canManage && $reviewingId === $i->id): ?>
                                    <tr <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'inv-review-'.e($i->id).''; ?>wire:key="inv-review-<?php echo e($i->id); ?>">
                                        <td colspan="6" class="bg-light">
                                            <div class="d-flex flex-wrap align-items-start gap-2">
                                                <div class="flex-grow-1">
                                                    <input type="text" class="form-control form-control-sm <?php $__errorArgs = ['reviewNote'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model="reviewNote"
                                                           placeholder="<?php echo e($reviewStatus === 'rejected' ? 'Why is it rejected? (required — the enterprise sees this)' : 'Note (optional), e.g. checked against bank statement'); ?>">
                                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['reviewNote'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                                </div>
                                                <button type="button" class="btn btn-sm btn-link text-secondary text-decoration-none" wire:click="cancelReview">Cancel</button>
                                                <button type="button" class="btn btn-sm <?php echo e($reviewStatus === 'rejected' ? 'btn-danger' : 'btn-success'); ?>" wire:click="confirmReview">
                                                    <?php echo e($reviewStatus === 'rejected' ? 'Reject investment' : 'Mark as verified'); ?>

                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div><?php /**PATH C:\laragon\www\co-investment-facility\storage\framework\views/livewire/views/8424d70f.blade.php ENDPATH**/ ?>