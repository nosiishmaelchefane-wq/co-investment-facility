<?php
use App\Models\EnterpriseDocument;
use App\Models\EnterpriseInvestment;
use App\Models\EnterpriseProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;
?>

<div class="container-fluid p-3">
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($userId): ?>
    <?php
        $owner    = $this->owner;
        $p        = $this->profile;
        $P        = \App\Models\EnterpriseProfile::class;
        $D        = \App\Models\EnterpriseDocument::class;
        $status   = $p->exists ? $p->sectionStatus() : array_fill_keys(array_keys($P::SECTIONS), false);
        $done     = count(array_filter($status));
        $total    = count($P::SECTIONS);
        $percent  = (int) round($done / $total * 100);
        $canEdit  = ! $readOnly;
        $reqDocs  = $p->requiredDocumentTypes();
        $rate     = (float) config('cif.usd_to_lsl', 18.00);
        $lsl      = fn ($m) => $m === null || $m === '' ? '—' : 'M '.number_format((float) $m);
        $usd      = fn ($m) => $m === null || $m === '' ? '' : 'US$ '.number_format((float) $m / max($rate, 0.0001));
        $dash     = fn ($v) => filled($v) ? $v : '—';
        $yn       = fn ($v) => $v === null ? '—' : ($v ? 'Yes' : 'No');
        $ynp      = fn ($v) => $P::YES_NO_PROGRESS[$v ?? ''] ?? '—';
        $radios   = function (string $field, array $options, bool $live = false) {
            $html = '<div class="btn-group btn-group-sm flex-wrap">';
            foreach ($options as $v => $l) {
                $id = 'r-'.$field.'-'.$v;
                $html .= '<input type="radio" class="btn-check" id="'.e($id).'" value="'.e($v).'" wire:model'.($live ? '.live' : '').'="form.'.e($field).'">'
                       . '<label class="btn btn-outline-secondary" for="'.e($id).'">'.e($l).'</label>';
            }
            return $html.'</div>';
        };
        $answer   = fn ($a) => ['yes' => 'Yes', 'no' => 'No', 'na' => 'Not applicable'][$a ?? ''] ?? '—';

        // ⓘ hover help — texts live in EnterpriseProfile::HELP
        $tip = fn (string $key) => isset($P::HELP[$key])
            ? '<span class="tip" tabindex="0" role="note" aria-label="'.e($P::HELP[$key]).'" data-tip="'.e($P::HELP[$key]).'"><i class="bi bi-info-circle"></i></span>'
            : '';

        $docTip = fn (string $type) => isset($D::HELP[$type])
            ? '<span class="tip" tabindex="0" role="note" aria-label="'.e($D::HELP[$type]).'" data-tip="'.e($D::HELP[$type]).'"><i class="bi bi-info-circle"></i></span>'
            : '';

        $investments = $p->exists ? $p->investments : collect();
        $I = \App\Models\EnterpriseInvestment::class;

        // Which document rows to show (conditional ones follow the registration answers)
        $docVisible = fn (array $t) => match ($t[3]) {
            'registered'     => $p->obfc_registered !== false,
            'not_registered' => $p->obfc_registered === false,
            'foreign'        => $p->foreign_registered === true,
            'investor'       => $p->has_investor !== false,
            default          => true,
        };
    ?>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($readOnly): ?>
        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <a href="<?php echo e(route('users.index')); ?>" class="back-link"><i class="bi bi-arrow-left"></i> Back to users</a>
            <span class="pill pill-muted ms-auto"><i class="bi bi-eye"></i> Read-only view</span>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    
    <div class="hero-banner mb-4">
        <div class="hero-glow"></div>
        <div class="d-flex flex-wrap align-items-center gap-3">
            <div class="avatar-sq"><?php echo e($p->initial); ?></div>
            <div class="me-auto">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($p->registration_no): ?> <span class="chip chip-dark"><?php echo e($p->registration_no); ?></span> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <span class="badge <?php echo e($percent === 100 ? 'status-approved' : ($p->exists ? 'status-in-review' : 'status-draft')); ?>">
                        <?php echo e($percent === 100 ? 'Profile complete' : ($p->exists ? 'In progress' : 'Not started')); ?>

                    </span>
                </div>
                <h2 class="hero-title mb-1"><?php echo e($p->registered_name ?: ($readOnly ? ($owner->organisation_name ?? $owner->name) : 'Your enterprise profile')); ?></h2>
                <div class="hero-meta">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($readOnly): ?> <span><i class="bi bi-person"></i> <?php echo e($owner->name); ?> · <?php echo e($owner->email); ?></span> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($p->district): ?> <span><i class="bi bi-geo-alt"></i> <?php echo e(collect([$p->city, $p->district])->filter()->implode(', ')); ?></span> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($p->sector): ?> <span><i class="bi bi-tag"></i> <?php echo e($p->sector); ?></span> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($p->updated_at): ?> <span><i class="bi bi-clock-history"></i> Updated <?php echo e($p->updated_at->diffForHumans()); ?></span> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>
            <div class="text-white" style="min-width: 200px;">
                <div class="d-flex justify-content-between small mb-1"><span>Completeness</span><strong><?php echo e($done); ?> / <?php echo e($total); ?></strong></div>
                <div class="progress" style="height: 8px; background: rgba(255,255,255,.2);">
                    <div class="progress-bar bg-accent" style="width: <?php echo e($percent); ?>%;"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="callout small mb-3 d-flex align-items-start gap-2">
        <i class="bi bi-info-circle-fill text-brand mt-1"></i>
        <div><strong>Need more detail?</strong> Hover over (or tap) the <i class="bi bi-info-circle"></i> icon next to any section, question or document to see what it means and what a good answer looks like.</div>
    </div>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($readOnly)): ?>
        <div class="callout small mb-4">
            <div class="fw-semibold text-navy mb-1"><i class="bi bi-info-circle me-1"></i> Co-Investment Facility application — disclaimers</div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $P::DISCLAIMERS; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $text): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?> <p class="mb-1"><?php echo e($text); ?></p> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <div class="row g-4">
        <div class="col-xl-8">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $P::SECTIONS; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => [$title, $icon, $desc]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <?php $isEditing = $editing === $key; ?>
                <section class="section-card" id="sec-<?php echo e($key); ?>" <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'sec-'.e($key).''; ?>wire:key="sec-<?php echo e($key); ?>">
                    <div class="section-card-head">
                        <span class="section-no"><?php echo e($loop->iteration); ?></span>
                        <div class="me-auto">
                            <h5><?php echo e($title); ?> <?php echo $tip('section.'.$key); ?></h5>
                            <div class="small text-muted"><?php echo e($desc); ?></div>
                        </div>
                        <span class="pill <?php echo e($status[$key] ? 'pill-ok' : 'pill-warn'); ?>"><i class="bi <?php echo e($status[$key] ? 'bi-check-lg' : 'bi-exclamation-circle'); ?>"></i> <?php echo e($status[$key] ? 'Complete' : 'Incomplete'); ?></span>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canEdit && ! $isEditing): ?>
                            <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="edit('<?php echo e($key); ?>')" <?php if($editing): echo 'disabled'; endif; ?>><i class="bi bi-pencil me-1"></i><?php echo e($key === 'attachments' ? 'Declaration' : 'Edit'); ?></button>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>

                    
                    <?php
                        $missingQ = $p->missingFields($key);
                        $missingD = $p->missingSectionDocuments($key);
                    ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! $isEditing && ($missingQ || $missingD)): ?>
                        <div class="callout callout-warning small mb-3">
                            <div class="d-flex flex-wrap align-items-start gap-2">
                                <div class="flex-grow-1">
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($missingQ): ?>
                                        <div><strong><?php echo e(count($missingQ)); ?> <?php echo e(\Illuminate\Support\Str::plural('question', count($missingQ))); ?> to answer:</strong>
                                            <?php echo e(collect($missingQ)->map(fn ($f) => $P::LABELS[$f] ?? $f)->implode(', ')); ?></div>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($missingD): ?>
                                        <div class="<?php echo e($missingQ ? 'mt-1' : ''); ?>"><strong><?php echo e(count($missingD)); ?> <?php echo e(\Illuminate\Support\Str::plural('document', count($missingD))); ?> to upload:</strong>
                                            <?php echo e(collect($missingD)->map(fn ($t) => $D::missingLabel($t))->implode(', ')); ?></div>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </div>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canEdit && $missingQ): ?>
                                    <button type="button" class="btn btn-sm btn-brand" wire:click="edit('<?php echo e($key); ?>')" <?php if($editing): echo 'disabled'; endif; ?>><i class="bi bi-pencil-square me-1"></i> Answer <?php echo e(count($missingQ) === 1 ? 'question' : 'questions'); ?></button>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                    
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($key === 'company'): ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isEditing): ?>
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label small fw-semibold">Have you received any grant support from any of these CAFI-funded projects? <?php echo $tip('prior_cafi_support'); ?> <span class="req">*</span></label>
                                    <div class="d-flex flex-wrap gap-3">
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $P::PRIOR_SUPPORT; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $lbl): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                            <label class="form-check mb-0"><input type="checkbox" class="form-check-input" value="<?php echo e($k); ?>" wire:model.live="form.prior_cafi_support"> <span class="form-check-label small"><?php echo e($lbl); ?></span></label>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                    </div>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.prior_cafi_support'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-danger small"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </div>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($form['prior_cafi_support'] ?? []) !== ['none'] && ($form['prior_cafi_support'] ?? []) !== []): ?>
                                    <div class="col-12"><label class="form-label small fw-semibold">Please explain the support received <?php echo $tip('prior_support_details'); ?> <span class="req">*</span></label>
                                        <textarea rows="2" class="form-control <?php $__errorArgs = ['form.prior_support_details'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model="form.prior_support_details"></textarea>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.prior_support_details'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                                <div class="col-md-8"><label class="form-label small fw-semibold">Company official name <?php echo $tip('registered_name'); ?> <span class="req">*</span></label>
                                    <input type="text" class="form-control <?php $__errorArgs = ['form.registered_name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model="form.registered_name">
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.registered_name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                                <div class="col-md-4"><label class="form-label small fw-semibold">Company short name <?php echo $tip('short_name'); ?></label>
                                    <input type="text" class="form-control <?php $__errorArgs = ['form.short_name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model="form.short_name">
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.short_name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                                <div class="col-md-4"><label class="form-label small fw-semibold">Month and year operations commenced <?php echo $tip('year_established'); ?> <span class="req">*</span></label>
                                    <div class="input-group">
                                        <select class="form-select <?php $__errorArgs = ['form.month_commenced'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model.live="form.month_commenced">
                                            <option value="">Month…</option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $P::MONTHS; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $mn => $ml): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?><option value="<?php echo e($mn); ?>"><?php echo e($ml); ?></option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                        </select>
                                        <input type="number" min="1900" max="<?php echo e(date('Y')); ?>" class="form-control <?php $__errorArgs = ['form.year_established'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model.live.debounce.500ms="form.year_established" placeholder="Year">
                                    </div>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.month_commenced'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-danger small"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.year_established'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-danger small"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    <?php
                                        $mcY = (int) ($form['year_established'] ?? 0); $mcM = (int) ($form['month_commenced'] ?? 0);
                                        $mcAge = ($mcY >= 1900 && $mcM >= 1) ? (int) \Illuminate\Support\Carbon::create($mcY, $mcM, 1)->diffInMonths(now()) : null;
                                    ?>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($mcAge !== null && $mcAge < 6): ?>
                                        <div class="small text-warning-emphasis mt-1"><i class="bi bi-exclamation-circle me-1"></i> Operating for less than 6 months — this will be flagged for review.</div>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </div>
                                <div class="col-md-4"><label class="form-label small fw-semibold">Year of registration <?php echo $tip('year_registered'); ?> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($p->obfc_registered === true): ?><span class="req">*</span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></label>
                                    <input type="number" min="1900" max="<?php echo e(date('Y')); ?>" class="form-control <?php $__errorArgs = ['form.year_registered'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model="form.year_registered" placeholder="<?php echo e($p->obfc_registered === false ? 'Not registered yet' : ''); ?>">
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.year_registered'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                                <div class="col-md-4"><label class="form-label small fw-semibold">Website <?php echo $tip('website'); ?></label>
                                    <input type="text" class="form-control <?php $__errorArgs = ['form.website'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model="form.website" placeholder="https://">
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.website'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                                <div class="col-md-8"><label class="form-label small fw-semibold">Address <?php echo $tip('address'); ?> <span class="req">*</span></label>
                                    <input type="text" class="form-control <?php $__errorArgs = ['form.address'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model="form.address">
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.address'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                                <div class="col-md-4"><label class="form-label small fw-semibold">City / town <?php echo $tip('city'); ?> <span class="req">*</span></label>
                                    <input type="text" class="form-control <?php $__errorArgs = ['form.city'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model="form.city">
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.city'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = ['district' => ['District', $P::DISTRICTS], 'sector' => ['Sector', $P::SECTORS]]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $f => [$lbl, $opts]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                    <div class="col-md-6"><label class="form-label small fw-semibold"><?php echo e($lbl); ?> <?php echo $tip($f); ?> <span class="req">*</span></label>
                                        <select class="form-select <?php $__errorArgs = ['form.'.$f];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model.live="form.<?php echo e($f); ?>">
                                            <option value="">Select…</option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $opts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $o): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?><option><?php echo e($o); ?></option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(filled($form[$f] ?? null) && ! in_array($form[$f], $opts, true)): ?><option><?php echo e($form[$f]); ?></option><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        </select>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.'.$f];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($form['sector'] ?? '') === 'Other'): ?>
                                    <div class="col-md-6 offset-md-6"><label class="form-label small fw-semibold">Please specify your sector <?php echo $tip('sector_other'); ?> <span class="req">*</span></label>
                                        <input type="text" class="form-control <?php $__errorArgs = ['form.sector_other'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model="form.sector_other" placeholder="e.g. Renewable energy">
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.sector_other'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                                <div class="col-md-6"><label class="form-label small fw-semibold">Sub-sector <?php echo $tip('sub_sector'); ?> <span class="req">*</span></label>
                                    <input type="text" class="form-control <?php $__errorArgs = ['form.sub_sector'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model="form.sub_sector" placeholder="e.g. Fruit and vegetable processing">
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.sub_sector'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>

                                <div class="col-12"><label class="form-label small fw-semibold">Business model — select one or more <?php echo $tip('business_models'); ?> <span class="req">*</span></label>
                                    <div class="d-flex flex-wrap gap-2">
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $P::BUSINESS_MODELS; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $lbl): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                            <input type="checkbox" class="btn-check" id="bm-<?php echo e($k); ?>" value="<?php echo e($k); ?>" wire:model="form.business_models" autocomplete="off">
                                            <label class="btn btn-sm btn-outline-secondary" for="bm-<?php echo e($k); ?>"><i class="bi bi-check2 me-1"></i><?php echo e($lbl); ?></label>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                    </div>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.business_models'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-danger small"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>

                                <div class="col-12"><label class="form-label small fw-semibold mb-1">Role of women and youth at the firm <?php echo $tip('women_youth_role'); ?> <span class="req">*</span></label></div>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = ['women_owned_managed' => 'Women owned or led', 'youth_owned_managed' => 'Youth (18–35) owned or led']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $f => $lbl): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                    <div class="col-md-6 d-flex align-items-center justify-content-between gap-2">
                                        <span class="small"><?php echo e($lbl); ?> <?php echo $tip($f); ?></span>
                                        <div class="btn-group btn-group-sm">
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = ['yes' => 'Yes', 'no' => 'No']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $v => $vl): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                                <input type="radio" class="btn-check" id="<?php echo e($f); ?>-<?php echo e($v); ?>" value="<?php echo e($v); ?>" wire:model="form.<?php echo e($f); ?>">
                                                <label class="btn btn-outline-secondary" for="<?php echo e($f); ?>-<?php echo e($v); ?>"><?php echo e($vl); ?></label>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                        </div>
                                    </div>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.'.$f];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="col-12 text-danger small mt-0"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>

                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = ['basotho_owned_pct' => '% shares held by Basotho', 'women_owned_pct' => '% shares held by women', 'youth_owned_pct' => '% shares held by youth']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $f => $lbl): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                    <div class="col-md-4"><label class="form-label small fw-semibold"><?php echo e($lbl); ?> <?php echo $tip($f); ?></label>
                                        <input type="number" min="0" max="100" class="form-control <?php $__errorArgs = ['form.'.$f];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model="form.<?php echo e($f); ?>">
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.'.$f];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                <div class="col-12 form-text mt-1">Share ownership: the percentage of the company's shares held by Lesotho citizens, by women and by young people (35 or under). A shareholder can count in more than one group.</div>

                                
                                <?php $shTotal = collect($form['shareholder_list'] ?? [])->filter(fn ($r) => filled($r['name'] ?? null))->sum(fn ($r) => is_numeric($r['percent'] ?? null) ? (float) $r['percent'] : 0); ?>
                                <div class="col-12 border-top pt-3">
                                    <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                        <span class="fw-semibold text-navy small me-auto">List of shareholders <?php echo $tip('shareholder_list'); ?> <span class="req">*</span></span>
                                        <span class="small <?php echo e($shTotal > 100 ? 'text-danger fw-semibold' : ($shTotal == 100 ? 'text-success' : 'text-muted')); ?>">Total: <?php echo e(rtrim(rtrim(number_format($shTotal, 2), '0'), '.')); ?>%</span>
                                    </div>
                                    <div class="form-text mt-0 mb-2">Fill in the table <strong>or</strong> upload the list of shareholders as a document below this section.</div>
                                    <div class="table-responsive">
                                        <table class="table table-modern table-sm align-middle mb-1">
                                            <thead><tr><th style="min-width:160px">Name</th><th style="min-width:130px">ID / reg. no.</th><th style="min-width:120px">Nationality</th><th style="min-width:100px">Gender</th><th style="width:80px">Age</th><th style="width:100px">% share</th><th></th></tr></thead>
                                            <tbody>
                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $form['shareholder_list'] ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $si => $sh): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                                    <tr <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'sh-'.e($si).''; ?>wire:key="sh-<?php echo e($si); ?>">
                                                        <td><input type="text" class="form-control form-control-sm <?php $__errorArgs = ['form.shareholder_list.'.$si.'.name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model.blur="form.shareholder_list.<?php echo e($si); ?>.name" placeholder="Full name or company"></td>
                                                        <td><input type="text" class="form-control form-control-sm <?php $__errorArgs = ['form.shareholder_list.'.$si.'.id_number'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model="form.shareholder_list.<?php echo e($si); ?>.id_number"></td>
                                                        <td><input type="text" class="form-control form-control-sm <?php $__errorArgs = ['form.shareholder_list.'.$si.'.nationality'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model="form.shareholder_list.<?php echo e($si); ?>.nationality" placeholder="e.g. Mosotho"></td>
                                                        <td><select class="form-select form-select-sm <?php $__errorArgs = ['form.shareholder_list.'.$si.'.gender'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model="form.shareholder_list.<?php echo e($si); ?>.gender">
                                                                <option value="">—</option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $P::GENDERS; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $g): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?><option><?php echo e($g); ?></option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?></select></td>
                                                        <td><input type="number" min="0" max="120" class="form-control form-control-sm <?php $__errorArgs = ['form.shareholder_list.'.$si.'.age'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model="form.shareholder_list.<?php echo e($si); ?>.age"></td>
                                                        <td><input type="number" min="0" max="100" step="0.01" class="form-control form-control-sm <?php $__errorArgs = ['form.shareholder_list.'.$si.'.percent'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model.live.debounce.400ms="form.shareholder_list.<?php echo e($si); ?>.percent"></td>
                                                        <td class="text-end"><button type="button" class="btn btn-icon btn-icon-danger" title="Remove" wire:click="removeShareholder(<?php echo e($si); ?>)"><i class="bi bi-trash"></i></button></td>
                                                    </tr>
                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $errors->get('form.shareholder_list.*'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $msgs): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?> <div class="text-danger small"><?php echo e($msgs[0]); ?></div> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?><?php break; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.shareholder_list'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-danger small"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    <button type="button" class="btn btn-sm btn-outline-secondary mt-1" wire:click="addShareholder"><i class="bi bi-plus-lg me-1"></i> Add shareholder</button>
                                </div>

                                <div class="col-12 border-top pt-3"><div class="fw-semibold text-navy small">Applicant contact information <?php echo $tip('contact'); ?></div></div>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = ['contact_name' => ['Primary contact name', 'text'], 'contact_title' => ['Position / title', 'text'], 'contact_phone' => ['Telephone (with country code)', 'tel'], 'contact_email' => ['E-mail', 'email']]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $f => [$lbl, $type]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                    <div class="col-md-6"><label class="form-label small fw-semibold"><?php echo e($lbl); ?> <?php echo $tip($f); ?> <span class="req">*</span></label>
                                        <input type="<?php echo e($type); ?>" class="form-control <?php $__errorArgs = ['form.'.$f];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model="form.<?php echo e($f); ?>">
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.'.$f];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                <div class="col-md-6"><label class="form-label small fw-semibold">Name and position of authorised representative <?php echo $tip('authorised_rep'); ?> <span class="req">*</span></label>
                                    <input type="text" class="form-control <?php $__errorArgs = ['form.authorised_rep'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model="form.authorised_rep" placeholder="e.g. Palesa M., Managing Director">
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.authorised_rep'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                                <div class="col-md-6"><label class="form-label small fw-semibold">Alternative contact details <?php echo $tip('alternative_contact'); ?></label>
                                    <input type="text" class="form-control <?php $__errorArgs = ['form.alternative_contact'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model="form.alternative_contact" placeholder="Optional">
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.alternative_contact'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>

                                <div class="col-12 pt-2"><div class="fw-semibold text-navy small">Secondary contact person <?php echo $tip('secondary_contact'); ?></div></div>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = ['secondary_name' => ['Name', 'text'], 'secondary_title' => ['Position / title', 'text'], 'secondary_email' => ['E-mail', 'email'], 'secondary_phone' => ['Telephone', 'tel']]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $f => [$lbl, $type]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                    <div class="col-md-6"><label class="form-label small fw-semibold"><?php echo e($lbl); ?> <span class="req">*</span></label>
                                        <input type="<?php echo e($type); ?>" class="form-control <?php $__errorArgs = ['form.'.$f];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model="form.<?php echo e($f); ?>">
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.'.$f];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="detail-grid">
                                <div><dt>Company official name <?php echo $tip('registered_name'); ?></dt><dd><?php echo e($dash($p->registered_name)); ?></dd></div>
                                <div><dt>Short name <?php echo $tip('short_name'); ?></dt><dd><?php echo e($dash($p->short_name)); ?></dd></div>
                                <div><dt>Operations commenced <?php echo $tip('year_established'); ?></dt><dd><?php echo e($dash($p->commencedLabel())); ?>

                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($p->operatingUnderSixMonths()): ?> <span class="pill pill-warn ms-1"><i class="bi bi-exclamation-circle"></i> Under 6 months</span> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></dd></div>
                                <div><dt>Year of registration <?php echo $tip('year_registered'); ?></dt><dd><?php echo e($dash($p->year_registered)); ?></dd></div>
                                <div><dt>Website <?php echo $tip('website'); ?></dt><dd><?php echo e($dash($p->website)); ?></dd></div>
                                <div><dt>Address <?php echo $tip('address'); ?></dt><dd><?php echo e(collect([$p->address, $p->city, $p->district])->filter()->implode(', ') ?: '—'); ?></dd></div>
                                <div><dt>Sector <?php echo $tip('sector'); ?></dt><dd><?php echo e($dash($p->sectorLabel())); ?></dd></div>
                                <div><dt>Sub-sector <?php echo $tip('sub_sector'); ?></dt><dd><?php echo e($dash($p->sub_sector)); ?></dd></div>
                                <div><dt>Business model <?php echo $tip('business_models'); ?></dt><dd><?php echo e($p->businessModelLabel() ?? '—'); ?></dd></div>
                                <div><dt>Women owned or led <?php echo $tip('women_owned_managed'); ?></dt><dd><?php echo e($yn($p->women_owned_managed)); ?></dd></div>
                                <div><dt>Youth (18–35) owned or led <?php echo $tip('youth_owned_managed'); ?></dt><dd><?php echo e($yn($p->youth_owned_managed)); ?></dd></div>
                                <div><dt>Shareholding <?php echo $tip('basotho_owned_pct'); ?></dt><dd><?php echo e($p->ownershipLine() ?? '—'); ?></dd></div>
                                <div><dt>Prior CAFI support <?php echo $tip('prior_cafi_support'); ?></dt><dd><?php echo e($p->labels($P::PRIOR_SUPPORT, $p->prior_cafi_support) ?? '—'); ?></dd></div>
                            </div>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($p->prior_support_details): ?> <div class="quote mt-3 mb-0"><?php echo e($p->prior_support_details); ?><div class="quote-meta">Support received</div></div> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                            <dt class="detail-label mt-3 mb-2">Shareholders <?php echo $tip('shareholder_list'); ?></dt>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($p->shareholderRows()): ?>
                                <div class="table-responsive">
                                    <table class="table table-modern table-sm align-middle mb-1">
                                        <thead><tr><th>Name</th><th>ID / reg. no.</th><th>Nationality</th><th>Gender</th><th class="text-end">Age</th><th class="text-end">% share</th></tr></thead>
                                        <tbody>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $p->shareholderRows(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $sh): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                                <tr><td class="fw-semibold text-navy"><?php echo e($sh['name']); ?></td><td><?php echo e($dash($sh['id_number'] ?? null)); ?></td><td><?php echo e($dash($sh['nationality'] ?? null)); ?></td>
                                                    <td><?php echo e($dash($sh['gender'] ?? null)); ?></td><td class="text-end"><?php echo e($dash($sh['age'] ?? null)); ?></td><td class="text-end"><?php echo e(isset($sh['percent']) ? rtrim(rtrim(number_format((float) $sh['percent'], 2), '0'), '.').'%' : '—'); ?></td></tr>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                            <tr class="fw-bold"><td colspan="5" class="text-navy">Total</td><td class="text-end <?php echo e($p->shareholderTotal() > 100 ? 'text-danger' : ''); ?>"><?php echo e(rtrim(rtrim(number_format($p->shareholderTotal(), 2), '0'), '.')); ?>%</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            <?php elseif($p->exists && $p->document('shareholders_list')): ?>
                                <p class="small text-muted mb-0"><i class="bi bi-paperclip me-1"></i> Provided as an uploaded list (see documents below).</p>
                            <?php else: ?>
                                <p class="small text-muted mb-0">No shareholders added yet.</p>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <dt class="detail-label mt-3 mb-2">Applicant contact <?php echo $tip('contact'); ?></dt>
                            <div class="detail-grid">
                                <div><dt>Name <?php echo $tip('contact_name'); ?></dt><dd><?php echo e($dash($p->contact_name)); ?></dd></div>
                                <div><dt>Title <?php echo $tip('contact_title'); ?></dt><dd><?php echo e($dash($p->contact_title)); ?></dd></div>
                                <div><dt>Phone <?php echo $tip('contact_phone'); ?></dt><dd><?php echo e($dash($p->contact_phone)); ?></dd></div>
                                <div><dt>E-mail <?php echo $tip('contact_email'); ?></dt><dd><?php echo e($dash($p->contact_email)); ?></dd></div>
                                <div><dt>Authorised representative <?php echo $tip('authorised_rep'); ?></dt><dd><?php echo e($dash($p->authorised_rep)); ?></dd></div>
                                <div><dt>Alternative contact <?php echo $tip('alternative_contact'); ?></dt><dd><?php echo e($dash($p->alternative_contact)); ?></dd></div>
                            </div>
                            <dt class="detail-label mt-3 mb-2">Secondary contact <?php echo $tip('secondary_contact'); ?></dt>
                            <div class="detail-grid">
                                <div><dt>Name</dt><dd><?php echo e($dash($p->secondary_name)); ?></dd></div>
                                <div><dt>Title</dt><dd><?php echo e($dash($p->secondary_title)); ?></dd></div>
                                <div><dt>Phone</dt><dd><?php echo e($dash($p->secondary_phone)); ?></dd></div>
                                <div><dt>E-mail</dt><dd><?php echo e($dash($p->secondary_email)); ?></dd></div>
                            </div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                    
                    <?php elseif($key === 'legal'): ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isEditing): ?>
                            <div class="row g-3">
                                <div class="col-md-6"><label class="form-label small fw-semibold">Is the enterprise registered with the OBFC in Lesotho? <?php echo $tip('obfc_status'); ?> <span class="req">*</span></label>
                                    <div><?php echo $radios('obfc_status', $P::YES_NO_PROGRESS, true); ?></div>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.obfc_status'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-danger small"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                                <div class="col-md-6"><label class="form-label small fw-semibold">Type of registration <?php echo $tip('legal_form'); ?> <span class="req">*</span></label>
                                    <select class="form-select <?php $__errorArgs = ['form.legal_form'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model="form.legal_form">
                                        <option value="">Select…</option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $P::LEGAL_FORMS; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $o): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?><option><?php echo e($o); ?></option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(filled($form['legal_form'] ?? null) && ! in_array($form['legal_form'], $P::LEGAL_FORMS, true)): ?><option><?php echo e($form['legal_form']); ?></option><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </select>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.legal_form'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($form['obfc_status'] ?? '') === 'yes'): ?>
                                    <div class="col-md-6"><label class="form-label small fw-semibold">Registration number <?php echo $tip('registration_no'); ?> <span class="req">*</span></label>
                                        <input type="text" class="form-control <?php $__errorArgs = ['form.registration_no'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model="form.registration_no">
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.registration_no'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                                    <div class="col-md-6"><label class="form-label small fw-semibold">Year of registration <?php echo $tip('year_registered'); ?></label>
                                        <div class="form-control bg-light small text-muted"><?php echo e(filled($p->year_registered) ? $p->year_registered : 'Enter it under Company & contact'); ?></div></div>
                                <?php elseif(in_array($form['obfc_status'] ?? '', ['no', 'in_progress'], true)): ?>
                                    <div class="col-12"><div class="callout callout-warning small mb-0"><i class="bi bi-exclamation-triangle me-1"></i> <?php echo e($P::REGISTRATION_NOTE); ?> Registration with the OBFC must be completed before the Grant Agreement is signed — upload a signed commitment to register below.</div></div>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                                <div class="col-md-6"><label class="form-label small fw-semibold">Are you currently registered in another country? <?php echo $tip('foreign_registered'); ?> <span class="req">*</span></label>
                                    <div class="btn-group btn-group-sm d-flex" style="max-width: 200px">
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = ['yes' => 'Yes', 'no' => 'No']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $v => $vl): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                            <input type="radio" class="btn-check" id="foreign-<?php echo e($v); ?>" value="<?php echo e($v); ?>" wire:model.live="form.foreign_registered">
                                            <label class="btn btn-outline-secondary" for="foreign-<?php echo e($v); ?>"><?php echo e($vl); ?></label>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                    </div>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.foreign_registered'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-danger small"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($form['foreign_registered'] ?? '') === 'yes'): ?>
                                    <div class="col-md-3"><label class="form-label small fw-semibold">Country <?php echo $tip('foreign_country'); ?> <span class="req">*</span></label>
                                        <input type="text" class="form-control <?php $__errorArgs = ['form.foreign_country'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model="form.foreign_country">
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.foreign_country'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                                    <div class="col-md-3"><label class="form-label small fw-semibold">Registration no. <?php echo $tip('foreign_registration_no'); ?> <span class="req">*</span></label>
                                        <input type="text" class="form-control <?php $__errorArgs = ['form.foreign_registration_no'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model="form.foreign_registration_no">
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.foreign_registration_no'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                                <div class="col-md-6"><label class="form-label small fw-semibold">Do you have foreign ownership in your enterprise? <?php echo $tip('foreign_owned'); ?> <span class="req">*</span></label>
                                    <div><?php echo $radios('foreign_owned', ['yes' => 'Yes', 'no' => 'No'], true); ?></div>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.foreign_owned'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-danger small"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($form['foreign_owned'] ?? '') === 'yes'): ?>
                                    <div class="col-md-6"><label class="form-label small fw-semibold">What percentage do they own? <?php echo $tip('foreign_owned_pct'); ?> <span class="req">*</span></label>
                                        <div class="input-group" style="max-width: 200px"><input type="number" min="0" max="100" step="0.01" class="form-control <?php $__errorArgs = ['form.foreign_owned_pct'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model="form.foreign_owned_pct"><span class="input-group-text">%</span></div>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.foreign_owned_pct'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-danger small"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <div class="col-md-6"><label class="form-label small fw-semibold">Does the enterprise have its primary operations in Lesotho? <?php echo $tip('primary_ops_lesotho'); ?> <span class="req">*</span></label>
                                    <div><?php echo $radios('primary_ops_lesotho', ['yes' => 'Yes', 'no' => 'No']); ?></div>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.primary_ops_lesotho'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-danger small"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                                <div class="col-md-6"><label class="form-label small fw-semibold">Does the enterprise have an active tax file with RSL? <?php echo $tip('rsl_tax_file'); ?> <span class="req">*</span></label>
                                    <div><?php echo $radios('rsl_tax_file', $P::YES_NO_PROGRESS, true); ?></div>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.rsl_tax_file'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-danger small"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(in_array($form['rsl_tax_file'] ?? '', ['no', 'in_progress'], true)): ?> <div class="small text-warning-emphasis mt-1"><i class="bi bi-exclamation-circle me-1"></i> A tax file must be opened with RSL before the Grant Agreement is signed.</div> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>

                                <div class="col-12 border-top pt-3"><label class="form-label small fw-semibold">What are your current markets? (choose all that apply) <?php echo $tip('markets'); ?> <span class="req">*</span></label>
                                    <div class="d-flex flex-wrap gap-3">
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $P::MARKETS; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $lbl): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                            <label class="form-check mb-0"><input type="checkbox" class="form-check-input" value="<?php echo e($k); ?>" wire:model="form.markets"> <span class="form-check-label small"><?php echo e($lbl); ?></span></label>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                    </div>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.markets'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-danger small"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                            </div>
                        <?php else: ?>
                            <div class="detail-grid">
                                <div><dt>Registered at OBFC <?php echo $tip('obfc_status'); ?></dt><dd><?php echo e($p->obfc_status ? $ynp($p->obfc_status) : $yn($p->obfc_registered)); ?></dd></div>
                                <div><dt>Type of registration <?php echo $tip('legal_form'); ?></dt><dd><?php echo e($dash($p->legal_form)); ?></dd></div>
                                <div><dt>Registration number <?php echo $tip('registration_no'); ?></dt><dd><?php echo e($dash($p->registration_no)); ?></dd></div>
                                <div><dt>Registered in another country <?php echo $tip('foreign_registered'); ?></dt><dd><?php echo e($p->foreign_registered ? 'Yes — '.collect([$p->foreign_country ?: 'country not given', $p->foreign_registration_no ? 'reg. no. '.$p->foreign_registration_no : null])->filter()->implode(', ') : $yn($p->foreign_registered)); ?></dd></div>
                                <div><dt>Foreign ownership <?php echo $tip('foreign_owned'); ?></dt><dd><?php echo e($p->foreign_owned ? 'Yes — '.rtrim(rtrim(number_format((float) $p->foreign_owned_pct, 2), '0'), '.').'%' : $yn($p->foreign_owned)); ?></dd></div>
                                <div><dt>Primary operations in Lesotho <?php echo $tip('primary_ops_lesotho'); ?></dt><dd><?php echo e($yn($p->primary_ops_lesotho)); ?></dd></div>
                                <div><dt>Active RSL tax file <?php echo $tip('rsl_tax_file'); ?></dt><dd><?php echo e($ynp($p->rsl_tax_file)); ?></dd></div>
                                <div><dt>Current markets <?php echo $tip('markets'); ?></dt><dd><?php echo e($p->labels($P::MARKETS, $p->markets) ?? '—'); ?></dd></div>
                            </div>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($p->obfc_registered === false): ?>
                                <div class="callout callout-warning small mt-3 mb-0"><i class="bi bi-exclamation-triangle me-1"></i> <?php echo e($P::REGISTRATION_NOTE); ?></div>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                    
                    <?php elseif($key === 'people'): ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isEditing): ?>
                            <?php $sum = collect(['ft_male', 'ft_female', 'pt_male', 'pt_female'])->sum(fn ($f) => (int) ($form[$f] ?? 0)); ?>
                            <div class="row g-3">
                                <div class="col-12"><div class="fw-semibold text-navy small">Company employment history <span class="req">*</span></div></div>
                                <div class="col-md-6"><label class="form-label small fw-semibold">Total employees — previous year <?php echo $tip('employees_prev_year'); ?></label>
                                    <input type="number" min="0" class="form-control <?php $__errorArgs = ['form.employees_prev_year'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model="form.employees_prev_year">
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.employees_prev_year'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                                <div class="col-md-6"><label class="form-label small fw-semibold">Total employees — current year <?php echo $tip('employees'); ?></label>
                                    <input type="number" min="0" class="form-control <?php $__errorArgs = ['form.employees'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?> <?php echo e(filled($form['employees'] ?? null) && (int) $form['employees'] !== $sum ? 'border-danger' : ''); ?>" wire:model.live.debounce.400ms="form.employees">
                                    <div class="form-text">Must equal the breakdown below.</div>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.employees'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>

                                <div class="col-12"><div class="fw-semibold text-navy small">Details of current employees <?php echo $tip('employee_details'); ?> <span class="req">*</span></div></div>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = ['ft_male' => 'Full-time males', 'ft_female' => 'Full-time females', 'pt_male' => 'Part-time males', 'pt_female' => 'Part-time females']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $f => $lbl): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                    <div class="col-6 col-md-3"><label class="form-label small fw-semibold"><?php echo e($lbl); ?> <?php echo $tip($f); ?></label>
                                        <input type="number" min="0" class="form-control <?php $__errorArgs = ['form.'.$f];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model.live.debounce.400ms="form.<?php echo e($f); ?>">
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.'.$f];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                <?php $empTotal = filled($form['employees'] ?? null) ? (int) $form['employees'] : null; ?>
                                <div class="col-12 mt-1">
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($empTotal === null): ?>
                                        <div class="small text-muted">Breakdown total: <strong><?php echo e($sum); ?></strong> — enter the current-year total above to compare.</div>
                                    <?php elseif($empTotal === $sum): ?>
                                        <div class="small text-success"><i class="bi bi-check-circle me-1"></i> Breakdown adds up to the current-year total (<?php echo e($sum); ?>).</div>
                                    <?php else: ?>
                                        <div class="small text-danger"><i class="bi bi-exclamation-circle me-1"></i> The breakdown adds up to <strong><?php echo e($sum); ?></strong> but the current-year total is <strong><?php echo e($empTotal); ?></strong> — they must match before you can save.
                                            <button type="button" class="btn btn-link btn-sm p-0 align-baseline" wire:click="$set('form.employees', <?php echo e($sum); ?>)">Use <?php echo e($sum); ?> as the total</button></div>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </div>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = ['youth_male' => 'Youth employees (18–35) — male', 'youth_female' => 'Youth employees (18–35) — female']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $f => $lbl): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                    <div class="col-md-6"><label class="form-label small fw-semibold"><?php echo e($lbl); ?> <?php echo $tip('youth_employees'); ?> <span class="req">*</span></label>
                                        <input type="number" min="0" class="form-control <?php $__errorArgs = ['form.'.$f];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model.live.debounce.400ms="form.<?php echo e($f); ?>">
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.'.$f];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                <?php $youthSum = (int) ($form['youth_male'] ?? 0) + (int) ($form['youth_female'] ?? 0); ?>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($youthSum > $sum): ?>
                                    <div class="col-12 small text-danger mt-1"><i class="bi bi-exclamation-circle me-1"></i> Youth employees (<?php echo e($youthSum); ?>) cannot be more than all employees (<?php echo e($sum); ?>) — youth are part of the full-time / part-time figures.</div>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                                <div class="col-12 border-top pt-3"><div class="fw-semibold text-navy small">Expected employment outcomes — next 24 months <?php echo $tip('expected_jobs'); ?> <span class="req">*</span></div>
                                    <div class="form-text mt-0">How many additional jobs do you expect the proposed investment to create? These figures form the baseline for the quarterly and close-out reports.</div></div>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = ['expected_jobs' => 'Expected additional jobs', 'expected_jobs_women' => 'of which for women', 'expected_jobs_youth' => 'of which for young people']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $f => $lbl): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                    <div class="col-md-4"><label class="form-label small fw-semibold"><?php echo e($lbl); ?></label>
                                        <input type="number" min="0" class="form-control <?php $__errorArgs = ['form.'.$f];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model.blur="form.<?php echo e($f); ?>">
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.'.$f];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>



                                
                                <div class="col-12 border-top pt-3">
                                    <div class="fw-semibold text-navy small">Environmental, social &amp; governance (ESG) checklist <?php echo $tip('es_checklist'); ?> <span class="req">*</span></div>
                                    <div class="form-text mt-1"><?php echo e($P::ES_INTRO); ?> For each question choose Yes, No, Do not know or Not applicable.</div>
                                </div>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $P::ES_CHECKLIST; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => [$area, $q]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                    <?php
                                        $esA  = $form['es_checklist'][$k] ?? '';
                                        $esFu = $P::esFollowUp($k, $esA);
                                        $esDq = $esA === 'yes' && in_array($k, $P::ES_DISQUALIFY_IF_YES, true);
                                    ?>
                                    <div class="col-12 py-2 border-bottom" <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'es-'.e($k).''; ?>wire:key="es-<?php echo e($k); ?>">
                                        <div class="d-flex flex-wrap align-items-start justify-content-between gap-2">
                                            <div class="small flex-grow-1" style="min-width: 260px; max-width: 640px">
                                                <span class="fw-semibold text-navy"><?php echo e($loop->iteration); ?>.</span>
                                                <span class="pill pill-muted me-1" style="font-size:.68rem"><?php echo e($area); ?></span>
                                                <?php echo e($q); ?> <span class="req">*</span>
                                            </div>
                                            <div class="btn-group btn-group-sm flex-wrap">
                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $P::ES_ANSWERS; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $v => $vl): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                                    <input type="radio" class="btn-check" id="es-<?php echo e($k); ?>-<?php echo e($v); ?>" value="<?php echo e($v); ?>" wire:model.live="form.es_checklist.<?php echo e($k); ?>">
                                                    <label class="btn btn-outline-secondary" for="es-<?php echo e($k); ?>-<?php echo e($v); ?>"><?php echo e($vl); ?></label>
                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                            </div>
                                        </div>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.es_checklist.'.$k];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-danger small"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($esDq): ?>
                                            <div class="callout callout-danger small mt-2 mb-0"><i class="bi bi-x-octagon me-1"></i>
                                                <?php echo e($k === 'under_18' ? 'Work by anyone under 18 makes the enterprise ineligible for a Co-Investment grant.' : 'Activities on the exclusion list make the enterprise ineligible for a Co-Investment grant.'); ?>

                                            </div>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($k === 'records' && $esA === 'yes'): ?>
                                            <div class="mt-2" style="max-width: 640px">
                                                <label class="form-label small fw-semibold mb-1">What do you use? <span class="req">*</span></label>
                                                <select class="form-select form-select-sm <?php $__errorArgs = ['form.es_records_system'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model="form.es_records_system">
                                                    <option value="">Select…</option>
                                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $P::ES_RECORD_SYSTEMS; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $sv => $sl): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?><option value="<?php echo e($sv); ?>"><?php echo e($sl); ?></option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                                </select>
                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.es_records_system'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                            </div>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($esFu): ?>
                                            <div class="mt-2" style="max-width: 640px">
                                                <label class="form-label small fw-semibold mb-1"><?php echo e($esFu[0]); ?> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($esFu[1]): ?> <span class="req">*</span> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></label>
                                                <textarea rows="2" class="form-control form-control-sm <?php $__errorArgs = ['form.es_notes.'.$k];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model="form.es_notes.<?php echo e($k); ?>"></textarea>
                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.es_notes.'.$k];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                            </div>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </div>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                <div class="col-12">
                                    <label class="form-check mb-0">
                                        <input type="checkbox" class="form-check-input <?php $__errorArgs = ['form.es_confirmed'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model="form.es_confirmed">
                                        <span class="form-check-label small"><?php echo e($P::ES_CONFIRMATION); ?> <span class="req">*</span></span>
                                    </label>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.es_confirmed'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-danger small"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    <div class="form-text mt-1"><?php echo e($P::ES_NOTE); ?></div>
                                </div>

                                
                                <div class="col-12 border-top pt-3">
                                    <div class="fw-semibold text-navy small mb-2">Directors and key management personnel <?php echo $tip('key_people'); ?> <span class="req">*</span></div>
                                    <div class="table-responsive">
                                        <table class="table table-modern table-sm align-middle mb-1">
                                            <thead><tr><th style="min-width:170px">Name</th><th style="min-width:150px">Position</th><th style="width:120px">Years of service</th><th style="min-width:130px">Nationality</th><th></th></tr></thead>
                                            <tbody>
                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $form['key_people'] ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ki => $kp): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                                    <tr <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'kp-'.e($ki).''; ?>wire:key="kp-<?php echo e($ki); ?>">
                                                        <td><input type="text" class="form-control form-control-sm <?php $__errorArgs = ['form.key_people.'.$ki.'.name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model.blur="form.key_people.<?php echo e($ki); ?>.name"></td>
                                                        <td><input type="text" class="form-control form-control-sm <?php $__errorArgs = ['form.key_people.'.$ki.'.position'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model="form.key_people.<?php echo e($ki); ?>.position" placeholder="e.g. Director"></td>
                                                        <td><input type="number" min="0" max="80" step="0.5" class="form-control form-control-sm <?php $__errorArgs = ['form.key_people.'.$ki.'.years'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model="form.key_people.<?php echo e($ki); ?>.years"></td>
                                                        <td><input type="text" class="form-control form-control-sm <?php $__errorArgs = ['form.key_people.'.$ki.'.nationality'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model="form.key_people.<?php echo e($ki); ?>.nationality" placeholder="e.g. Mosotho"></td>
                                                        <td class="text-end"><button type="button" class="btn btn-icon btn-icon-danger" title="Remove" wire:click="removeKeyPerson(<?php echo e($ki); ?>)"><i class="bi bi-trash"></i></button></td>
                                                    </tr>
                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $errors->get('form.key_people.*'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $msgs): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?> <div class="text-danger small"><?php echo e($msgs[0]); ?></div> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?><?php break; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.key_people'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-danger small"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    <button type="button" class="btn btn-sm btn-outline-secondary mt-1" wire:click="addKeyPerson"><i class="bi bi-plus-lg me-1"></i> Add person</button>
                                </div>
                                <div class="col-12"><label class="form-label small fw-semibold">Is there any known ownership dispute, material litigation or conflict that could affect the transaction? <?php echo $tip('ownership_dispute'); ?> <span class="req">*</span></label>
                                    <div><?php echo $radios('ownership_dispute', ['yes' => 'Yes', 'no' => 'No'], true); ?></div>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.ownership_dispute'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-danger small"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($form['ownership_dispute'] ?? '') === 'yes'): ?>
                                    <div class="col-12"><label class="form-label small fw-semibold">Please describe it <span class="req">*</span></label>
                                        <textarea rows="2" class="form-control <?php $__errorArgs = ['form.ownership_dispute_details'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model="form.ownership_dispute_details"></textarea>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.ownership_dispute_details'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                                <div class="col-12 border-top pt-3"><label class="form-label small fw-semibold">Management summary — the people running the company <?php echo $tip('management'); ?></label>
                                    <textarea rows="3" class="form-control <?php $__errorArgs = ['form.management'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model="form.management" placeholder="Name — position, responsibilities and experience, one person per line"></textarea>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.management'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                            </div>
                        <?php else: ?>
                            <?php $breakdown = $p->employeeBreakdownTotal(); $gaps = $p->esGaps(); ?>
                            <div class="table-responsive">
                                <table class="table table-modern table-sm align-middle mb-2">
                                    <thead><tr><th></th><th class="text-end">Previous year</th><th class="text-end">Current year</th></tr></thead>
                                    <tbody><tr><td class="fw-semibold text-navy">Total employees</td><td class="text-end"><?php echo e($p->employees_prev_year ?? '—'); ?></td><td class="text-end"><?php echo e($p->employees ?? '—'); ?></td></tr></tbody>
                                </table>
                                <table class="table table-modern table-sm align-middle mb-0">
                                    <thead><tr><th class="text-end">FT males</th><th class="text-end">FT females</th><th class="text-end">PT males</th><th class="text-end">PT females</th></tr></thead>
                                    <tbody><tr><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = ['ft_male', 'ft_female', 'pt_male', 'pt_female']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $f): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?><td class="text-end"><?php echo e($p->{$f} ?? '—'); ?></td><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?></tr></tbody>
                                </table>
                            </div>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($breakdown !== null && $p->employees !== null && $breakdown !== (int) $p->employees): ?>
                                <div class="small text-warning-emphasis mt-1"><i class="bi bi-exclamation-circle me-1"></i> The details add up to <?php echo e($breakdown); ?>; the current-year total is <?php echo e($p->employees); ?>.</div>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                            <?php $sumOf = fn (...$v) => collect($v)->every(fn ($x) => $x === null) ? '—' : collect($v)->sum(fn ($x) => (int) $x); ?>
                            <dt class="detail-label mt-3 mb-2">Current employment</dt>
                            <div class="table-responsive">
                                <table class="table table-modern table-sm align-middle mb-0">
                                    <thead><tr><th>Category</th><th class="text-end">Male</th><th class="text-end">Female</th><th class="text-end">Total</th></tr></thead>
                                    <tbody>
                                        <tr><td>Full-time employees</td><td class="text-end"><?php echo e($p->ft_male ?? '—'); ?></td><td class="text-end"><?php echo e($p->ft_female ?? '—'); ?></td><td class="text-end fw-semibold"><?php echo e($sumOf($p->ft_male, $p->ft_female)); ?></td></tr>
                                        <tr><td>Part-time employees</td><td class="text-end"><?php echo e($p->pt_male ?? '—'); ?></td><td class="text-end"><?php echo e($p->pt_female ?? '—'); ?></td><td class="text-end fw-semibold"><?php echo e($sumOf($p->pt_male, $p->pt_female)); ?></td></tr>
                                        <tr><td>Youth employees (18–35)</td><td class="text-end"><?php echo e($p->youth_male ?? '—'); ?></td><td class="text-end"><?php echo e($p->youth_female ?? '—'); ?></td><td class="text-end fw-semibold"><?php echo e($sumOf($p->youth_male, $p->youth_female)); ?></td></tr>
                                    </tbody>
                                </table>
                            </div>
                            <dt class="detail-label mt-3 mb-2">Expected employment outcomes (24 months) <?php echo $tip('expected_jobs'); ?></dt>
                            <div class="detail-grid">
                                <div><dt>Additional jobs</dt><dd><?php echo e($p->expected_jobs ?? '—'); ?></dd></div>
                                <div><dt>Jobs for women</dt><dd><?php echo e($p->expected_jobs_women ?? '—'); ?></dd></div>
                                <div><dt>Jobs for young people</dt><dd><?php echo e($p->expected_jobs_youth ?? '—'); ?></dd></div>
                            </div>


                            <?php
                                $esFlags  = $p->esScreening();
                                $esByKey  = collect($esFlags)->keyBy(1);
                                $esAnswered = collect($P::ES_CHECKLIST)->keys()->contains(fn ($k) => isset($P::ES_ANSWERS[$p->es_checklist[$k] ?? '']));
                                $esLevel  = [
                                    'disqualify' => ['pill-danger', 'bi-x-octagon-fill', 'Disqualifies'],
                                    'dd'         => ['pill-warn',   'bi-search',          'Targeted DD'],
                                    'review'     => ['pill-muted',  'bi-eye',             'Review controls'],
                                    'clarify'    => ['pill-warn',   'bi-question-circle', 'Clarify'],
                                ];
                            ?>
                            <dt class="detail-label mt-3 mb-2">Environmental, social &amp; governance checklist <?php echo $tip('es_checklist'); ?></dt>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($esAnswered): ?>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($p->esDisqualified()): ?>
                                    <div class="callout callout-danger small mb-2"><i class="bi bi-x-octagon me-1"></i> An answer below means immediate disqualification under the CAFI screening rules.</div>
                                <?php elseif(! $esFlags): ?>
                                    <div class="small text-muted mb-2"><i class="bi bi-check-circle text-success me-1"></i> No issue disclosed.</div>
                                <?php else: ?>
                                    <div class="small text-muted mb-2"><?php echo e(count($esFlags)); ?> <?php echo e(\Illuminate\Support\Str::plural('answer', count($esFlags))); ?> flagged for review — these do not disqualify the enterprise on their own.</div>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <ul class="checklist mb-1">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $P::ES_CHECKLIST; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => [$area, $q]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                    <?php
                                        $a    = $p->es_checklist[$k] ?? null;
                                        $a    = isset($P::ES_ANSWERS[$a ?? '']) ? $a : null;
                                        $flag = $esByKey[$k] ?? null;
                                        $note = $p->es_notes[$k] ?? null;
                                    ?>
                                    <li class="<?php echo e($a === null ? 'is-pending' : ($flag ? ($flag[0] === 'disqualify' ? 'is-fail' : 'is-pending') : 'is-done')); ?>">
                                        <i class="bi <?php echo e($a === null ? 'bi-circle' : ($flag ? $esLevel[$flag[0]][1] : 'bi-check-circle-fill')); ?>"></i>
                                        <div class="flex-grow-1">
                                            <span class="text-muted"><?php echo e($loop->iteration); ?>.</span> <?php echo e($q); ?>

                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($k === 'records' && $a === 'yes' && $p->es_records_system): ?> <div class="small text-muted">Uses: <?php echo e($P::ES_RECORD_SYSTEMS[$p->es_records_system] ?? $p->es_records_system); ?></div> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(filled($note)): ?> <div class="small text-muted fst-italic mt-1" style="white-space: pre-line">“<?php echo e($note); ?>”</div> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($flag): ?> <span class="pill <?php echo e($esLevel[$flag[0]][0]); ?> mt-1" style="font-size:.7rem"><i class="bi <?php echo e($esLevel[$flag[0]][1]); ?>"></i> <?php echo e($esLevel[$flag[0]][2]); ?></span> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        </div>
                                        <strong class="small text-nowrap"><?php echo e($a ? $P::ES_ANSWERS[$a] : '—'); ?></strong>
                                    </li>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                            </ul>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($p->es_confirmed): ?>
                                <div class="small text-muted"><i class="bi bi-patch-check text-accent me-1"></i> Answers confirmed by the applicant.</div>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($esFlags): ?> <div class="small text-muted mt-1"><?php echo e($P::ES_NOTE); ?></div> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                            <dt class="detail-label mt-3 mb-2">Directors and key management <?php echo $tip('key_people'); ?></dt>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($p->keyPeopleRows()): ?>
                                <div class="table-responsive">
                                    <table class="table table-modern table-sm align-middle mb-1">
                                        <thead><tr><th>Name</th><th>Position</th><th class="text-end">Years of service</th><th>Nationality</th></tr></thead>
                                        <tbody>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $p->keyPeopleRows(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $kp): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                                <tr><td class="fw-semibold text-navy"><?php echo e($kp['name']); ?></td><td><?php echo e($dash($kp['position'] ?? null)); ?></td>
                                                    <td class="text-end"><?php echo e(isset($kp['years']) ? rtrim(rtrim(number_format((float) $kp['years'], 1), '0'), '.') : '—'); ?></td><td><?php echo e($dash($kp['nationality'] ?? null)); ?></td></tr>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="small text-muted mb-1">No directors or key managers added yet.</p>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <div class="small mt-1"><span class="text-muted">Known ownership dispute, litigation or conflict:</span> <strong><?php echo e($yn($p->ownership_dispute)); ?></strong></div>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($p->ownership_dispute && $p->ownership_dispute_details): ?> <div class="quote mt-2 mb-0"><?php echo e($p->ownership_dispute_details); ?><div class="quote-meta">Dispute / litigation</div></div> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($p->management): ?>
                                <dt class="detail-label mt-3">Management <?php echo $tip('management'); ?></dt>
                                <p class="small mb-0" style="white-space: pre-line"><?php echo e($p->management); ?></p>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                    
                    <?php elseif($key === 'business'): ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isEditing): ?>
                            <?php $words = str_word_count(strip_tags((string) ($form['activity'] ?? ''))); ?>
                            <div class="row g-3">
                                <div class="col-12"><label class="form-label small fw-semibold">Company summary (max. 100 words) <?php echo $tip('activity'); ?> <span class="req">*</span></label>
                                    <textarea rows="4" class="form-control <?php $__errorArgs = ['form.activity'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model.live.debounce.600ms="form.activity"></textarea>
                                    <div class="form-text <?php echo e($words > 100 ? 'text-danger' : ''); ?>"><?php echo e($words); ?> / 100 words</div>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.activity'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                                <div class="col-12"><label class="form-label small fw-semibold">Which stage of the business lifecycle is your startup / SME in? <?php echo $tip('company_stage'); ?> <span class="req">*</span></label>
                                    <div class="d-flex flex-wrap gap-2">
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $P::STAGES; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $lbl): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                            <input type="radio" class="btn-check" id="stage-<?php echo e($k); ?>" value="<?php echo e($k); ?>" wire:model="form.company_stage">
                                            <label class="btn btn-sm btn-outline-secondary" for="stage-<?php echo e($k); ?>"><?php echo e($lbl); ?></label>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                    </div>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.company_stage'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-danger small"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                                <div class="col-md-6"><label class="form-label small fw-semibold">Products &amp; services <?php echo $tip('products_services'); ?> <span class="req">*</span></label>
                                    <textarea rows="3" class="form-control <?php $__errorArgs = ['form.products_services'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model="form.products_services" placeholder="What you sell"></textarea>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.products_services'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                                <div class="col-md-6"><label class="form-label small fw-semibold">Customers <?php echo $tip('customers'); ?> <span class="req">*</span></label>
                                    <textarea rows="3" class="form-control <?php $__errorArgs = ['form.customers'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model="form.customers" placeholder="Who buys from you, how many, and where they are"></textarea>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.customers'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = [
                                    'usp'                => ['Unique selling proposition', 'Why customers choose you over the alternatives'],
                                    'marketing_strategy' => ['Marketing approach', 'How you reach, sell to and keep customers'],
                                    'competition'        => ['Competition', 'Who you compete with and how you compare'],
                                ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $f => [$lbl, $ph]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                    <div class="col-12"><label class="form-label small fw-semibold"><?php echo e($lbl); ?> <?php echo $tip($f); ?></label>
                                        <textarea rows="3" class="form-control <?php $__errorArgs = ['form.'.$f];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model="form.<?php echo e($f); ?>" placeholder="<?php echo e($ph); ?>"></textarea>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.'.$f];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>

                                <div class="col-12 border-top pt-3"><div class="fw-semibold text-navy small">Financials</div></div>
                                <div class="col-md-6"><label class="form-label small fw-semibold">Annual revenues for the past year (M) <?php echo $tip('annual_revenue'); ?> <span class="req">*</span></label>
                                    <input type="number" min="0" step="1000" class="form-control <?php $__errorArgs = ['form.annual_revenue'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model.live.debounce.500ms="form.annual_revenue">
                                    <div class="form-text"><?php echo e($usd($form['annual_revenue'] ?? null)); ?></div>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.annual_revenue'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>

                                
                                <div class="col-md-6"><label class="form-label small fw-semibold">Do you have an investor? <?php echo $tip('has_investor'); ?> <span class="req">*</span></label>
                                    <div class="btn-group btn-group-sm d-flex" style="max-width: 200px">
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = ['yes' => 'Yes', 'no' => 'No']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $v => $vl): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                            <input type="radio" class="btn-check" id="hasinv-<?php echo e($v); ?>" value="<?php echo e($v); ?>" wire:model.live="form.has_investor">
                                            <label class="btn btn-outline-secondary" for="hasinv-<?php echo e($v); ?>"><?php echo e($vl); ?></label>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                    </div>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.has_investor'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-danger small"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>

                                <?php
                                    $hasInvestor  = ($form['has_investor'] ?? '') === 'yes';
                                    // Live: investor tickets, otherwise the round amount → CAFI grant
                                    $formTickets  = $hasInvestor ? collect($form['round_investors'] ?? [])->sum(fn ($r) => is_numeric($r['ticket'] ?? null) ? (float) $r['ticket'] : 0) : 0;
                                    $formInvestor = ! $hasInvestor ? null : ($formTickets > 0 ? $formTickets : (is_numeric($form['round_amount'] ?? null) && (float) $form['round_amount'] > 0 ? (float) $form['round_amount'] : null));
                                    $formGrant    = $P::matchingGrant($formInvestor);
                                    $projectCost  = is_numeric($form['project_cost'] ?? null) ? (float) $form['project_cost'] : null;
                                    $costTooLow   = $formGrant !== null && $projectCost !== null && $projectCost + 0.005 < $formGrant;
                                ?>

                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($form['has_investor'] ?? '') === 'no'): ?>
                                    <div class="col-12"><div class="callout small mb-0">
                                        <div class="fw-semibold text-navy mb-1"><i class="bi bi-people me-1"></i> Get matched with an investor through LEHSFF</div>
                                        <p class="mb-2">The Co-Investment Facility matches money from a private investor, so a CAFI grant can only be awarded once you have an investor. If you don't have one yet, we recommend LEHSFF matchmaking on the
                                            <a href="https://collaboration.lehsff.org.ls/" target="_blank" rel="noopener">LEHSFF Collaboration Platform <i class="bi bi-box-arrow-up-right"></i></a>.</p>
                                        <div class="fw-semibold mb-1">How to get matched</div>
                                        <ol class="mb-2 ps-3">
                                            <li>Go to <a href="https://collaboration.lehsff.org.ls/" target="_blank" rel="noopener">collaboration.lehsff.org.ls</a> and <strong>sign up as an Entrepreneur</strong> (or log in if you already have an account).</li>
                                            <li><strong>Fill in all of your entrepreneur profile details</strong> — your business and contact details, sector, stage of the business, products or services, the investment you are looking for and what it will be used for, and your team.</li>
                                            <li>Upload the supporting documents the profile asks for, check that every section is complete, then save your profile.</li>
                                            <li>Once your profile is complete, <strong>the CAFI team will be notified</strong>. They will review your profile and match you with suitable investors on the platform.</li>
                                        </ol>
                                        <div class="text-muted"><i class="bi bi-info-circle me-1"></i> Only complete profiles can be matched, so make sure no section is left empty. You can save this profile now.</div>
                                    </div></div>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($hasInvestor): ?>
                                    <div class="col-md-6"><label class="form-label small fw-semibold">Current investment round fund (M) <?php echo $tip('round_amount'); ?> <span class="req">*</span></label>
                                        <input type="number" min="0" step="1000" class="form-control <?php $__errorArgs = ['form.round_amount'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model.live.debounce.500ms="form.round_amount">
                                        <div class="form-text"><?php echo e($usd($form['round_amount'] ?? null)); ?></div>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.round_amount'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>

                                    <div class="col-12"><label class="form-label small fw-semibold">Participating investors and their committed ticket size (LSL) in the current round <?php echo $tip('round_investors'); ?> <span class="req">*</span></label>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $form['round_investors'] ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                            <div class="row g-2 mb-2" <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'inv-'.e($i).''; ?>wire:key="inv-<?php echo e($i); ?>">
                                                <div class="col-md-3"><input type="text" class="form-control form-control-sm <?php $__errorArgs = ['form.round_investors.'.$i.'.name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model.blur="form.round_investors.<?php echo e($i); ?>.name" placeholder="Investor name">
                                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.round_investors.'.$i.'.name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                                                <div class="col-md-3"><input type="text" class="form-control form-control-sm <?php $__errorArgs = ['form.round_investors.'.$i.'.id_number'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model="form.round_investors.<?php echo e($i); ?>.id_number" placeholder="ID / passport no. (or company reg. no.)">
                                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.round_investors.'.$i.'.id_number'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                                                <div class="col-md-2"><input type="text" class="form-control form-control-sm" wire:model="form.round_investors.<?php echo e($i); ?>.country" placeholder="Residence country"></div>
                                                <div class="col-md-3"><div class="input-group input-group-sm"><span class="input-group-text">M</span>
                                                    <input type="number" min="0" step="1000" class="form-control <?php $__errorArgs = ['form.round_investors.'.$i.'.ticket'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model.live.debounce.500ms="form.round_investors.<?php echo e($i); ?>.ticket" placeholder="Ticket size"></div>
                                                    <div class="form-text"><?php echo e($usd($row['ticket'] ?? null)); ?></div>
                                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.round_investors.'.$i.'.ticket'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-danger small"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                                            </div>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.round_investors'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-danger small mt-1"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>

                                    <div class="col-md-6"><label class="form-label small fw-semibold">Investment round financing type <?php echo $tip('participation'); ?> <span class="req">*</span></label>
                                        <select class="form-select <?php $__errorArgs = ['form.participation'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model="form.participation">
                                            <option value="">Select…</option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $P::PARTICIPATION; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $o): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?><option value="<?php echo e($k); ?>"><?php echo e($o); ?></option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?></select>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.participation'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                                    <div class="col-md-6"><label class="form-label small fw-semibold">Status of the deal <?php echo $tip('transaction_status'); ?></label>
                                        <select class="form-select <?php $__errorArgs = ['form.transaction_status'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model="form.transaction_status">
                                            <option value="">Select…</option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $P::TRANSACTION_STATUSES; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $o): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?><option value="<?php echo e($k); ?>"><?php echo e($o); ?></option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?></select>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.transaction_status'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                                    <div class="col-md-6"><label class="form-label small fw-semibold">Lead investor type <?php echo $tip('investor_type'); ?></label>
                                        <select class="form-select <?php $__errorArgs = ['form.investor_type'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model="form.investor_type">
                                            <option value="">Select…</option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $P::INVESTOR_TYPES; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $o): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?><option><?php echo e($o); ?></option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?></select>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.investor_type'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                                
                                <div class="col-md-6"><label class="form-label small fw-semibold">CAFI matching grant (M) <?php echo $tip('grant_requested'); ?></label>
                                    <input type="text" class="form-control bg-light fw-semibold" value="<?php echo e($formGrant !== null ? number_format($formGrant) : ''); ?>" placeholder="<?php echo e($hasInvestor ? 'Enter your investors\' ticket sizes' : 'Needs an investor'); ?>" readonly>
                                    <div class="form-text"><?php echo e($usd($formGrant)); ?> <?php echo e($formGrant !== null ? '·' : ''); ?> <?php echo e($P::grantRatioLabel()); ?> of what your investor(s) put in<?php echo e($formTickets > 0 ? ' (ticket sizes)' : ($formInvestor !== null ? ' (current round)' : '')); ?></div></div>
                                <div class="col-md-6"><label class="form-label small fw-semibold">Total project cost (M) <?php echo $tip('project_cost'); ?> <span class="req">*</span></label>
                                    <input type="number" min="0" step="1000" class="form-control <?php $__errorArgs = ['form.project_cost'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?> <?php echo e($costTooLow ? 'border-danger' : ''); ?>" wire:model.live.debounce.500ms="form.project_cost">
                                    <div class="form-text"><?php echo e($usd($form['project_cost'] ?? null)); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($formGrant !== null): ?> · must be at least <?php echo e($lsl($formGrant)); ?> (the CAFI grant)<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($costTooLow): ?> <div class="text-danger small"><i class="bi bi-exclamation-circle me-1"></i> The total project cost cannot be less than the CAFI matching grant.</div> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.project_cost'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                                <div class="col-12"><div class="callout small mb-0">
                                    <div class="fw-semibold text-navy mb-1"><i class="bi bi-cash-coin me-1"></i> What is the CAFI matching grant?</div>
                                    It is a non-repayable grant from the Competitiveness and Financial Inclusion (CAFI) project that matches <strong><?php echo e($P::grantRatioLabel()); ?></strong> of the money your private investor(s) put into this round. You don't type it in — it is worked out from your investors' ticket sizes. It is paid in tranches once the conditions in the Grant Agreement are met and your investor's money is confirmed, and it can only be spent on the items in your grant utilisation budget below.
                                </div></div>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($formInvestor !== null && ! $P::meetsInvestorMinimum($formInvestor)): ?>
                                    <div class="col-12"><div class="callout callout-warning small mb-0">
                                        <i class="bi bi-exclamation-triangle me-1"></i> Your investor(s) must be investing at least <strong>US$ <?php echo e(number_format($P::minInvestorUsd())); ?></strong> (about <?php echo e($lsl($P::minInvestorLsl())); ?>) before you can apply. You can save your profile, but the application will not be accepted.
                                    </div></div>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <div class="col-12"><label class="form-label small fw-semibold">What the money is for <?php echo $tip('investment_purpose'); ?></label>
                                    <textarea rows="3" class="form-control <?php $__errorArgs = ['form.investment_purpose'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model="form.investment_purpose"
                                              placeholder="Main items for the investor's money and for the CAFI grant, with rough amounts"></textarea>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.investment_purpose'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>

                                
                                <?php
                                    $bt        = $P::sumBudget($form['grant_budget'] ?? []);
                                    $tPrivate  = $formInvestor;                // target for the private column
                                    $tGrant    = $formGrant;                   // target for the CAFI grant column
                                    $dPrivate  = $tPrivate !== null ? $bt['private'] - $tPrivate : null;
                                    $dGrant    = $tGrant !== null ? $bt['grant'] - $tGrant : null;
                                    $okPrivate = $dPrivate !== null && abs($dPrivate) < 1;
                                    $okGrant   = $dGrant !== null && abs($dGrant) < 1;
                                    $diffCls   = fn ($d) => $d === null ? 'text-muted' : (abs($d) < 1 ? 'text-success' : 'text-danger');
                                    $diffTxt   = fn ($d) => $d === null ? '—' : (abs($d) < 1 ? '✓ Matches' : ($d > 0 ? '+' : '−').' M '.number_format(abs($d)));
                                ?>
                                <div class="col-12 border-top pt-3"><div class="fw-semibold text-navy small mb-1">Grant utilisation budget <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($hasInvestor): ?> <span class="req">*</span> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?> <?php echo $tip('grant_budget'); ?></div>
                                    <div class="form-text mt-0 mb-2">How the investor's money and the CAFI grant will be spent. The <strong>Should add up to</strong> row shows the targets — the totals turn green when they match.</div>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($hasInvestor && $formInvestor !== null)): ?>
                                        <div class="small text-muted mb-2"><i class="bi bi-info-circle me-1"></i> The targets appear as soon as you enter what your investor(s) are putting in.</div>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    <div class="table-responsive">
                                        <table class="table table-modern table-sm align-middle mb-1">
                                            <thead><tr><th>Expenditure category</th><th class="text-end" style="width: 24%">Private investment (M)</th><th class="text-end" style="width: 24%">CAFI grant (M)</th><th class="text-end" style="width: 16%">Total</th></tr></thead>
                                            <tbody>
                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $form['grant_budget'] ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $bi => $b): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                                    <?php
                                                        $n      = fn ($v) => is_numeric($v ?? null) ? (float) $v : 0;
                                                        $items  = $b['items'] ?? [];
                                                        $hasSub = $items !== [];
                                                        $subP   = collect($items)->sum(fn ($it) => $n($it['private'] ?? null));
                                                        $subG   = collect($items)->sum(fn ($it) => $n($it['grant'] ?? null));
                                                        $offP   = $hasSub && abs($subP - $n($b['private'] ?? null)) >= 1;
                                                        $offG   = $hasSub && abs($subG - $n($b['grant'] ?? null)) >= 1;
                                                    ?>
                                                    <tr <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'gb-'.e($bi).''; ?>wire:key="gb-<?php echo e($bi); ?>" class="<?php echo e($hasSub ? 'table-light' : ''); ?>">
                                                        <td class="small">
                                                            <div class="fw-semibold text-navy"><?php echo e($b['category']); ?></div>
                                                            <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none small" wire:click="addBudgetItem(<?php echo e($bi); ?>)"><i class="bi bi-plus-circle me-1"></i>Add sub-category</button>
                                                        </td>
                                                        <td><input type="number" min="0" step="1000" class="form-control form-control-sm text-end <?php $__errorArgs = ['form.grant_budget.'.$bi.'.private'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?> <?php echo e($offP ? 'border-danger' : ''); ?>" wire:model.live.debounce.400ms="form.grant_budget.<?php echo e($bi); ?>.private"></td>
                                                        <td><input type="number" min="0" step="1000" class="form-control form-control-sm text-end <?php $__errorArgs = ['form.grant_budget.'.$bi.'.grant'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?> <?php echo e($offG ? 'border-danger' : ''); ?>" wire:model.live.debounce.400ms="form.grant_budget.<?php echo e($bi); ?>.grant"></td>
                                                        <td class="text-end small"><?php echo e($lsl($n($b['private'] ?? null) + $n($b['grant'] ?? null))); ?></td>
                                                    </tr>

                                                    
                                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ii => $it): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                                        <tr <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'gb-'.e($bi).'-'.e($ii).''; ?>wire:key="gb-<?php echo e($bi); ?>-<?php echo e($ii); ?>">
                                                            <td class="ps-4">
                                                                <div class="input-group input-group-sm">
                                                                    <span class="input-group-text bg-white border-0 text-muted px-1">↳</span>
                                                                    <input type="text" class="form-control form-control-sm <?php $__errorArgs = ['form.grant_budget.'.$bi.'.items.'.$ii.'.name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model.blur="form.grant_budget.<?php echo e($bi); ?>.items.<?php echo e($ii); ?>.name" placeholder="Sub-category, e.g. Delivery van">
                                                                    <button type="button" class="btn btn-icon btn-icon-danger" title="Remove" wire:click="removeBudgetItem(<?php echo e($bi); ?>, <?php echo e($ii); ?>)"><i class="bi bi-x-lg"></i></button>
                                                                </div>
                                                            </td>
                                                            <td><input type="number" min="0" step="1000" class="form-control form-control-sm text-end <?php $__errorArgs = ['form.grant_budget.'.$bi.'.items.'.$ii.'.private'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model.live.debounce.400ms="form.grant_budget.<?php echo e($bi); ?>.items.<?php echo e($ii); ?>.private"></td>
                                                            <td><input type="number" min="0" step="1000" class="form-control form-control-sm text-end <?php $__errorArgs = ['form.grant_budget.'.$bi.'.items.'.$ii.'.grant'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model.live.debounce.400ms="form.grant_budget.<?php echo e($bi); ?>.items.<?php echo e($ii); ?>.grant"></td>
                                                            <td class="text-end small text-muted"><?php echo e($lsl($n($it['private'] ?? null) + $n($it['grant'] ?? null))); ?></td>
                                                        </tr>
                                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($hasSub): ?>
                                                        <tr <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'gb-'.e($bi).'-sum'; ?>wire:key="gb-<?php echo e($bi); ?>-sum">
                                                            <td class="ps-4 small text-muted">Sub-categories total</td>
                                                            <td class="text-end small <?php echo e($offP ? 'text-danger fw-semibold' : 'text-success'); ?>"><?php echo e($lsl($subP)); ?> <?php echo $offP ? '<div style="font-size:.72rem">should be '.e($lsl($n($b['private'] ?? null))).'</div>' : '✓'; ?></td>
                                                            <td class="text-end small <?php echo e($offG ? 'text-danger fw-semibold' : 'text-success'); ?>"><?php echo e($lsl($subG)); ?> <?php echo $offG ? '<div style="font-size:.72rem">should be '.e($lsl($n($b['grant'] ?? null))).'</div>' : '✓'; ?></td>
                                                            <td></td>
                                                        </tr>
                                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.grant_budget.'.$bi.'.items_private'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <tr><td colspan="4" class="text-danger small py-1"><?php echo e($message); ?></td></tr> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.grant_budget.'.$bi.'.items_grant'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <tr><td colspan="4" class="text-danger small py-1"><?php echo e($message); ?></td></tr> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                            </tbody>
                                            <tfoot>
                                                <tr class="fw-bold">
                                                    <td class="small text-navy">Total entered</td>
                                                    <td class="text-end small <?php echo e($tPrivate === null ? '' : ($okPrivate ? 'text-success' : 'text-danger')); ?>"><?php echo e($lsl($bt['private'])); ?></td>
                                                    <td class="text-end small <?php echo e($tGrant === null ? '' : ($okGrant ? 'text-success' : 'text-danger')); ?>"><?php echo e($lsl($bt['grant'])); ?></td>
                                                    <td class="text-end small"><?php echo e($lsl($bt['private'] + $bt['grant'])); ?></td>
                                                </tr>
                                                <tr class="table-light">
                                                    <td class="small text-navy fw-semibold">Should add up to</td>
                                                    <td class="text-end small fw-semibold"><?php echo e($tPrivate !== null ? $lsl($tPrivate) : '—'); ?><div class="text-muted fw-normal" style="font-size:.75rem">investor amount</div></td>
                                                    <td class="text-end small fw-semibold"><?php echo e($tGrant !== null ? $lsl($tGrant) : '—'); ?><div class="text-muted fw-normal" style="font-size:.75rem">matching grant</div></td>
                                                    <td class="text-end small fw-semibold"><?php echo e($tPrivate !== null ? $lsl($tPrivate + $tGrant) : '—'); ?><div class="text-muted fw-normal" style="font-size:.75rem"><?php echo e($tPrivate !== null ? $usd($tPrivate + $tGrant) : ''); ?></div></td>
                                                </tr>
                                                <tr>
                                                    <td class="small text-muted">Difference</td>
                                                    <td class="text-end small <?php echo e($diffCls($dPrivate)); ?>"><?php echo e($diffTxt($dPrivate)); ?></td>
                                                    <td class="text-end small <?php echo e($diffCls($dGrant)); ?>"><?php echo e($diffTxt($dGrant)); ?></td>
                                                    <td></td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.grant_budget'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-danger small mt-1"><i class="bi bi-exclamation-circle me-1"></i><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.grant_budget_grant'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-danger small mt-1"><i class="bi bi-exclamation-circle me-1"></i><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </div>

                                
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($hasInvestor): ?>
                                    <?php
                                        $minLs    = $P::minLesothoPct();
                                        $lsBase   = $bt['grant'] > 0 ? $bt['grant'] : ($tGrant ?? 0);
                                        $lsOut    = is_numeric($form['grant_outside_lesotho'] ?? null) ? (float) $form['grant_outside_lesotho'] : null;
                                        $lsShare  = $P::lesothoShare($lsBase ?: null, $lsOut);
                                        $lsBelow  = $lsShare !== null && $lsShare < $minLs;
                                        $pctTxt   = fn ($v) => rtrim(rtrim(number_format($v, 1), '0'), '.').'%';
                                    ?>
                                    <div class="col-12 border-top pt-3">
                                        <div class="fw-semibold text-navy small mb-1">Spending in Lesotho <span class="req">*</span> <?php echo $tip('grant_outside_lesotho'); ?></div>
                                        <div class="form-text mt-0 mb-2">At least <strong><?php echo e((int) $minLs); ?>%</strong> of the CAFI grant must be spent in Lesotho. If some of it will pay for goods or services from outside Lesotho, enter that amount (enter 0 if none).</div>
                                        <div class="row g-2 align-items-end">
                                            <div class="col-md-5">
                                                <label class="form-label small fw-semibold mb-1" for="pf_outside">CAFI grant to be spent outside Lesotho</label>
                                                <div class="input-group input-group-sm">
                                                    <span class="input-group-text">M</span>
                                                    <input type="number" id="pf_outside" min="0" step="1000" class="form-control <?php $__errorArgs = ['form.grant_outside_lesotho'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model.live.debounce.400ms="form.grant_outside_lesotho" placeholder="0">
                                                </div>
                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.grant_outside_lesotho'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-danger small mt-1"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                            </div>
                                            <div class="col-md-7 small">
                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($lsShare !== null): ?>
                                                    <div class="<?php echo e($lsBelow ? 'text-danger' : 'text-success'); ?>">
                                                        <i class="bi <?php echo e($lsBelow ? 'bi-exclamation-triangle' : 'bi-check-circle'); ?> me-1"></i>
                                                        <strong><?php echo e($pctTxt($lsShare)); ?></strong> spent in Lesotho (<?php echo e($lsl($lsBase - $lsOut)); ?>) · <?php echo e($pctTxt(100 - $lsShare)); ?> outside (<?php echo e($lsl($lsOut)); ?>)
                                                        — <?php echo e($lsBelow ? 'below the '.(int) $minLs.'% minimum, please explain why below' : 'meets the '.(int) $minLs.'% minimum'); ?>

                                                    </div>
                                                <?php elseif($lsBase <= 0): ?>
                                                    <div class="text-muted"><i class="bi bi-info-circle me-1"></i> Fill in the CAFI grant column of the budget first.</div>
                                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                            </div>
                                        </div>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($lsBelow): ?>
                                            <div class="mt-2">
                                                <label class="form-label small fw-semibold mb-1" for="pf_outside_why">Why does this part of the grant have to be spent outside Lesotho? <span class="req">*</span> <?php echo $tip('grant_outside_justification'); ?></label>
                                                <textarea id="pf_outside_why" rows="3" maxlength="2000" class="form-control <?php $__errorArgs = ['form.grant_outside_justification'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model.blur="form.grant_outside_justification"
                                                          placeholder="e.g. The processing machine is only made by suppliers in South Africa; installation is done by the supplier's technicians."></textarea>
                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.grant_outside_justification'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                            </div>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </div>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                                
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($hasInvestor): ?>
                                    <div class="col-12 border-top pt-3">
                                        <div class="fw-semibold text-navy small mb-1"><i class="bi bi-bank me-1"></i> Bank account for the grant <?php echo $tip('section.bank'); ?></div>
                                        <div class="form-text mt-0 mb-2">The account the CAFI grant will be paid into. It must be in the company's registered name. Upload your bank confirmation letter in the documents below.</div>
                                        <div class="row g-2">
                                            <div class="col-md-4">
                                                <label class="form-label small fw-semibold mb-1" for="pf_bank">Bank <span class="req">*</span></label>
                                                <input type="text" id="pf_bank" maxlength="120" class="form-control form-control-sm <?php $__errorArgs = ['form.bank_name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model.blur="form.bank_name" placeholder="e.g. Standard Lesotho Bank">
                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.bank_name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label small fw-semibold mb-1" for="pf_branch">Branch</label>
                                                <input type="text" id="pf_branch" maxlength="120" class="form-control form-control-sm" wire:model.blur="form.bank_branch">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label small fw-semibold mb-1" for="pf_atype">Account type</label>
                                                <select id="pf_atype" class="form-select form-select-sm <?php $__errorArgs = ['form.bank_account_type'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model="form.bank_account_type">
                                                    <option value="">Select…</option>
                                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $P::ACCOUNT_TYPES; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $atk => $atl): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?> <option value="<?php echo e($atk); ?>"><?php echo e($atl); ?></option> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label small fw-semibold mb-1" for="pf_aname">Account name <span class="req">*</span> <?php echo $tip('bank_account_name'); ?></label>
                                                <input type="text" id="pf_aname" maxlength="200" class="form-control form-control-sm <?php $__errorArgs = ['form.bank_account_name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model.blur="form.bank_account_name">
                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.bank_account_name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label small fw-semibold mb-1" for="pf_anum">Account number <span class="req">*</span></label>
                                                <input type="text" id="pf_anum" maxlength="40" inputmode="numeric" class="form-control form-control-sm <?php $__errorArgs = ['form.bank_account_number'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model.blur="form.bank_account_number">
                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.bank_account_number'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                                <div class="col-12 border-top pt-3"><div class="fw-semibold text-navy small mb-2">Total amount of investment in the company to date <?php echo $tip('investment_history'); ?></div>
                                    <div class="table-responsive">
                                        <table class="table table-modern table-sm align-middle mb-0">
                                            <thead><tr><th>Source</th><th>Overall amount (M)</th><th>Date of last investment</th></tr></thead>
                                            <tbody>
                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $P::FUNDING_SOURCES; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $lbl): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                                    <tr <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'hist-'.e($k).''; ?>wire:key="hist-<?php echo e($k); ?>">
                                                        <td class="small fw-semibold text-navy"><?php echo e($lbl); ?></td>
                                                        <td><input type="number" min="0" step="1000" class="form-control form-control-sm <?php $__errorArgs = ['form.investment_history.'.$k.'.amount'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model="form.investment_history.<?php echo e($k); ?>.amount"></td>
                                                        <td><input type="date" max="<?php echo e(now()->toDateString()); ?>" class="form-control form-control-sm <?php $__errorArgs = ['form.investment_history.'.$k.'.date'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model="form.investment_history.<?php echo e($k); ?>.date"></td>
                                                    </tr>
                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($p->activity): ?> <div class="quote mb-3"><?php echo e($p->activity); ?><div class="quote-meta">Company summary</div></div> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <div class="detail-grid mb-3">
                                <div><dt>Stage of development <?php echo $tip('company_stage'); ?></dt><dd><?php echo e($P::STAGES[$p->company_stage ?? ''] ?? '—'); ?></dd></div>
                                <div><dt>Annual revenue (past year) <?php echo $tip('annual_revenue'); ?></dt><dd><?php echo e($lsl($p->annual_revenue)); ?> <span class="small text-muted"><?php echo e($usd($p->annual_revenue)); ?></span></dd></div>
                            </div>
                            <div class="row g-3 mb-1">
                                <div class="col-md-6"><dt class="detail-label">Products &amp; services <?php echo $tip('products_services'); ?></dt>
                                    <p class="small mb-2" style="white-space: pre-line"><?php echo e($dash($p->products_services)); ?></p></div>
                                <div class="col-md-6"><dt class="detail-label">Customers <?php echo $tip('customers'); ?></dt>
                                    <p class="small mb-2" style="white-space: pre-line"><?php echo e($dash($p->customers)); ?></p></div>
                            </div>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = ['usp' => 'Unique selling proposition', 'marketing_strategy' => 'Marketing approach', 'competition' => 'Competition']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $f => $lbl): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <dt class="detail-label"><?php echo e($lbl); ?> <?php echo $tip($f); ?></dt>
                                <p class="small mb-3" style="white-space: pre-line"><?php echo e($dash($p->{$f})); ?></p>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>

                            <div class="detail-grid mb-3">
                                <div><dt>Do you have an investor? <?php echo $tip('has_investor'); ?></dt><dd><?php echo e($yn($p->has_investor ?? ($p->investorRows() ? true : null))); ?></dd></div>
                            </div>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($p->has_investor === false): ?>
                                <div class="callout small mb-3">
                                    <div class="fw-semibold text-navy mb-1"><i class="bi bi-people me-1"></i> No investor yet — LEHSFF matchmaking recommended</div>
                                    A CAFI grant can only be awarded once there is a private investor.
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canEdit): ?>
                                        Sign up as an Entrepreneur on the <a href="https://collaboration.lehsff.org.ls/" target="_blank" rel="noopener">LEHSFF Collaboration Platform <i class="bi bi-box-arrow-up-right"></i></a>
                                        and fill in all of your profile details — the CAFI team will be notified and will match you with suitable investors.
                                    <?php else: ?>
                                        The entrepreneur has been referred to LEHSFF matchmaking on the <a href="https://collaboration.lehsff.org.ls/" target="_blank" rel="noopener">Collaboration Platform</a>.
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </div>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <dt class="detail-label mb-2">Current investment round <?php echo $tip('round_amount'); ?></dt>
                            <div class="row g-2 mb-3">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = ['Investment round' => $p->round_amount, 'Total project cost' => $p->project_cost, 'CAFI matching grant ('.$P::grantRatioLabel().')' => $p->grant_requested]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $lbl => $amt): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                    <div class="col-md-4"><div class="border rounded-3 p-2 h-100">
                                        <div class="small text-muted"><?php echo e($lbl); ?></div>
                                        <div class="fw-bold text-navy"><?php echo e($lsl($amt)); ?></div>
                                        <div class="small text-muted"><?php echo e($usd($amt)); ?></div>
                                    </div></div>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                            </div>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($p->investorAmount() !== null && ! $P::meetsInvestorMinimum($p->investorAmount())): ?>
                                <div class="callout callout-warning small mb-3"><i class="bi bi-exclamation-triangle me-1"></i> The investor amount is below the US$ <?php echo e(number_format($P::minInvestorUsd())); ?> minimum (about <?php echo e($lsl($P::minInvestorLsl())); ?>), so an application cannot be submitted yet.</div>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <div class="detail-grid mb-2">
                                <div><dt>Financing type <?php echo $tip('participation'); ?></dt><dd><?php echo e($P::PARTICIPATION[$p->participation ?? ''] ?? '—'); ?></dd></div>
                                <div><dt>Status of the deal <?php echo $tip('transaction_status'); ?></dt><dd><?php echo e($P::TRANSACTION_STATUSES[$p->transaction_status ?? ''] ?? '—'); ?></dd></div>
                                <div><dt>Lead investor type <?php echo $tip('investor_type'); ?></dt><dd><?php echo e($dash($p->investor_type)); ?></dd></div>
                            </div>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($p->investorRows()): ?>
                                <table class="table table-modern table-sm align-middle mb-3">
                                    <thead><tr><th>Investor</th><th>ID / passport / reg. no.</th><th>Residence country</th><th class="text-end">Ticket size</th></tr></thead>
                                    <tbody>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $p->investorRows(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                            <tr><td class="fw-semibold text-navy"><?php echo e($row['name']); ?></td><td><?php echo e($dash($row['id_number'] ?? null)); ?></td><td><?php echo e($dash($row['country'] ?? null)); ?></td>
                                                <td class="text-end"><?php echo e($lsl($row['ticket'] ?? null)); ?> <div class="small text-muted"><?php echo e($usd($row['ticket'] ?? null)); ?></div></td></tr>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <dt class="detail-label">What the money is for <?php echo $tip('investment_purpose'); ?></dt>
                            <p class="small mb-3" style="white-space: pre-line"><?php echo e($dash($p->investment_purpose)); ?></p>

                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($p->grant_budget): ?>
                                <?php $vbt = $p->budgetTotals(); ?>
                                <dt class="detail-label mb-2">Grant utilisation budget <?php echo $tip('grant_budget'); ?></dt>
                                <div class="table-responsive">
                                    <table class="table table-modern table-sm align-middle mb-1">
                                        <thead><tr><th>Expenditure category</th><th class="text-end">Private investment</th><th class="text-end">CAFI grant</th><th class="text-end">Total</th></tr></thead>
                                        <tbody>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $p->budgetRows(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $b): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                                <tr class="<?php echo e(! empty($b['items']) ? 'table-light' : ''); ?>"><td class="small <?php echo e(! empty($b['items']) ? 'fw-semibold text-navy' : ''); ?>"><?php echo e($b['category']); ?></td>
                                                    <td class="text-end small"><?php echo e($lsl($b['private'] ?? null)); ?></td>
                                                    <td class="text-end small"><?php echo e($lsl($b['grant'] ?? null)); ?></td>
                                                    <td class="text-end small"><?php echo e($lsl((float) ($b['private'] ?? 0) + (float) ($b['grant'] ?? 0))); ?></td></tr>
                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $b['items'] ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $it): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                                    <tr><td class="small ps-4 text-muted">↳ <?php echo e($it['name'] ?? ''); ?></td>
                                                        <td class="text-end small text-muted"><?php echo e($lsl($it['private'] ?? null)); ?></td>
                                                        <td class="text-end small text-muted"><?php echo e($lsl($it['grant'] ?? null)); ?></td>
                                                        <td class="text-end small text-muted"><?php echo e($lsl((float) ($it['private'] ?? 0) + (float) ($it['grant'] ?? 0))); ?></td></tr>
                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                            <?php $vInv = $p->investorAmount(); $vPrivOk = $vInv === null || abs($vbt['private'] - $vInv) < 1; ?>
                                            <tr class="fw-bold"><td class="small text-navy">Total entered</td><td class="text-end small <?php echo e($vPrivOk ? '' : 'text-danger'); ?>"><?php echo e($lsl($vbt['private'])); ?></td>
                                                <td class="text-end small <?php echo e($p->budgetMatchesGrant() ? '' : 'text-danger'); ?>"><?php echo e($lsl($vbt['grant'])); ?></td>
                                                <td class="text-end small"><?php echo e($lsl($vbt['private'] + $vbt['grant'])); ?><div class="text-muted fw-normal"><?php echo e($usd($vbt['private'] + $vbt['grant'])); ?></div></td></tr>
                                            <tr class="table-light"><td class="small text-navy fw-semibold">Should add up to</td>
                                                <td class="text-end small fw-semibold"><?php echo e($lsl($vInv)); ?></td>
                                                <td class="text-end small fw-semibold"><?php echo e($lsl($p->grant_requested)); ?></td>
                                                <td class="text-end small fw-semibold"><?php echo e($vInv !== null ? $lsl($vInv + (float) $p->grant_requested) : '—'); ?></td></tr>
                                        </tbody>
                                    </table>
                                </div>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($p->budgetMatchesGrant() && $vPrivOk)): ?>
                                    <div class="small text-danger mb-3"><i class="bi bi-exclamation-circle me-1"></i> The totals do not match the targets — the investor amount has probably changed. Please update the budget.</div>
                                <?php else: ?>
                                    <div class="mb-3"></div>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($p->has_investor !== false && ($p->grant_outside_lesotho !== null || $p->grant_budget)): ?>
                                <?php $vShare = $p->lesothoSharePct(); $vBelow = $p->needsOutsideJustification(); ?>
                                <dt class="detail-label">Spending in Lesotho <?php echo $tip('grant_outside_lesotho'); ?></dt>
                                <dd class="small mb-3">
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($vShare === null): ?>
                                        <span class="text-muted">Not answered yet.</span>
                                    <?php else: ?>
                                        <span class="<?php echo e($vBelow ? 'text-danger' : 'text-success'); ?>"><i class="bi <?php echo e($vBelow ? 'bi-exclamation-triangle' : 'bi-check-circle'); ?> me-1"></i>
                                            <?php echo e(rtrim(rtrim(number_format($vShare, 1), '0'), '.')); ?>% of the CAFI grant spent in Lesotho</span>
                                        · <?php echo e($lsl($p->grant_outside_lesotho)); ?> outside Lesotho (minimum in Lesotho: <?php echo e((int) $P::minLesothoPct()); ?>%)
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($p->grant_outside_justification): ?>
                                            <div class="quote mt-1 mb-0" style="white-space: pre-line"><?php echo e($p->grant_outside_justification); ?></div>
                                        <?php elseif($vBelow): ?>
                                            <div class="text-danger mt-1">Justification missing — please edit this section and explain why.</div>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </dd>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($p->has_investor !== false && collect($P::BANK_FIELDS)->contains(fn ($bf) => filled($p->{$bf}))): ?>
                                <dt class="detail-label">Bank account for the grant <?php echo $tip('section.bank'); ?></dt>
                                <dd class="small mb-3">
                                    <?php echo e($dash($p->bank_account_name)); ?> · <?php echo e($dash($p->bank_name)); ?><?php echo e($p->bank_branch ? ', '.$p->bank_branch : ''); ?>

                                    · Acc. <?php echo e($dash($p->bank_account_number)); ?><?php echo e($p->bank_account_type ? ' ('.($P::ACCOUNT_TYPES[$p->bank_account_type] ?? $p->bank_account_type).')' : ''); ?>

                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($p->registered_name && $p->bank_account_name && \Illuminate\Support\Str::lower(trim($p->bank_account_name)) !== \Illuminate\Support\Str::lower(trim($p->registered_name))): ?>
                                        <div class="text-warning mt-1"><i class="bi bi-exclamation-triangle me-1"></i> The account name differs from the registered company name (<?php echo e($p->registered_name); ?>).</div>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </dd>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($p->investment_history): ?>
                                <dt class="detail-label mb-2">Investment in the company to date <?php echo $tip('investment_history'); ?></dt>
                                <table class="table table-modern table-sm align-middle mb-0">
                                    <thead><tr><th>Source</th><th class="text-end">Overall amount</th><th>Last investment</th></tr></thead>
                                    <tbody>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $P::FUNDING_SOURCES; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $lbl): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                            <?php if(! isset($p->investment_history[$k])): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?><?php continue; ?><?php endif; ?>
                                            <?php $h = $p->investment_history[$k]; ?>
                                            <tr><td><?php echo e($lbl); ?></td><td class="text-end"><?php echo e($lsl($h['amount'] ?? null)); ?></td>
                                                <td class="small text-muted"><?php echo e(filled($h['date'] ?? null) ? \Illuminate\Support\Carbon::parse($h['date'])->format('d M Y') : '—'); ?></td></tr>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                        
                        <div class="border-top mt-3 pt-3">
                            <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                                <dt class="detail-label mb-0 me-auto">Investments received <?php echo $tip('investments'); ?></dt>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canEdit && ! $invOpen): ?>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="newInvestment" <?php if($editing): echo 'disabled'; endif; ?>><i class="bi bi-plus-lg me-1"></i> Add investment</button>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>

                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($invOpen && $canEdit): ?>
                                <div class="border rounded-3 p-3 mb-3 bg-light">
                                    <div class="fw-semibold text-navy small mb-2"><?php echo e($invEditingId ? 'Edit investment' : 'New investment'); ?></div>
                                    <div class="row g-2">
                                        <div class="col-md-5"><label class="form-label small fw-semibold">Investor name <span class="req">*</span></label>
                                            <input type="text" class="form-control form-control-sm <?php $__errorArgs = ['inv.investor_name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model="inv.investor_name">
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['inv.investor_name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                                        <div class="col-md-3"><label class="form-label small fw-semibold">ID / passport no. <span class="req">*</span></label>
                                            <input type="text" class="form-control form-control-sm <?php $__errorArgs = ['inv.investor_id_number'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model="inv.investor_id_number" placeholder="or company reg. no.">
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['inv.investor_id_number'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                                        <div class="col-md-2"><label class="form-label small fw-semibold">Investor type</label>
                                            <select class="form-select form-select-sm <?php $__errorArgs = ['inv.investor_type'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model="inv.investor_type">
                                                <option value="">Select…</option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $P::INVESTOR_TYPES; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $o): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?><option><?php echo e($o); ?></option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?></select></div>
                                        <div class="col-md-2"><label class="form-label small fw-semibold">Country</label>
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
                                            <select class="form-select form-select-sm" wire:model="inv.instrument">
                                                <option value="">Select…</option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $I::INSTRUMENTS; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $o): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?><option value="<?php echo e($k); ?>"><?php echo e($o); ?></option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?></select></div>
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
                                            <div class="form-text">Bank statement, share certificate or signed agreement. Optional, but it speeds up verification.</div>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['invProof'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                                        <div class="col-12"><label class="form-label small fw-semibold">Notes</label>
                                            <input type="text" class="form-control form-control-sm" wire:model="inv.notes" placeholder="Anything reviewers should know"></div>
                                    </div>
                                    <div class="d-flex gap-2 mt-3">
                                        <button type="button" class="btn btn-link btn-sm text-secondary text-decoration-none px-0" wire:click="cancelInvestment">Cancel</button>
                                        <button type="button" class="btn btn-brand btn-sm ms-auto" wire:click="saveInvestment" wire:loading.attr="disabled" wire:target="saveInvestment,invProof"><i class="bi bi-check-lg me-1"></i> Save investment</button>
                                    </div>
                                </div>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($investments->isNotEmpty()): ?>
                                <div class="table-responsive">
                                    <table class="table table-modern table-sm align-middle mb-0">
                                        <thead><tr><th>Date</th><th>Investor</th><th class="text-end">Amount</th><th>Instrument</th><th>Status</th><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canEdit): ?><th></th><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></tr></thead>
                                        <tbody>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $investments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                                <?php [$sLabel, $sPill, $sIcon] = $I::STATUSES[$i->status] ?? $I::STATUSES['declared']; ?>
                                                <tr <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'inv-row-'.e($i->id).''; ?>wire:key="inv-row-<?php echo e($i->id); ?>">
                                                    <td class="small text-nowrap"><?php echo e($i->invested_on?->format('d M Y')); ?></td>
                                                    <td><div class="fw-semibold text-navy small"><?php echo e($i->investor_name); ?></div>
                                                        <div class="small text-muted"><?php echo e(collect([$i->investor_id_number ? 'ID '.$i->investor_id_number : null, $i->investor_type, $i->investor_country, $i->round_label])->filter()->implode(' · ')); ?></div></td>
                                                    <td class="text-end text-nowrap"><?php echo e($lsl($i->amount)); ?><div class="small text-muted"><?php echo e($usd($i->amount)); ?></div></td>
                                                    <td class="small"><?php echo e($I::INSTRUMENTS[$i->instrument ?? ''] ?? '—'); ?></td>
                                                    <td><span class="pill <?php echo e($sPill); ?>"><i class="bi <?php echo e($sIcon); ?>"></i> <?php echo e($sLabel); ?></span>
                                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($i->status === 'rejected' && $i->review_note): ?> <div class="small text-danger mt-1"><?php echo e($i->review_note); ?></div> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($i->proof_path): ?> <a href="<?php echo e(route('investments.proof', $i)); ?>" class="small d-block text-decoration-none mt-1"><i class="bi bi-paperclip"></i> Proof</a> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></td>
                                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canEdit): ?>
                                                        <td class="text-end text-nowrap">
                                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($i->isEditableByEnterprise()): ?>
                                                                <button type="button" class="btn btn-icon" title="Edit" wire:click="editInvestment(<?php echo e($i->id); ?>)" <?php if($invOpen || $editing): echo 'disabled'; endif; ?>><i class="bi bi-pencil"></i></button>
                                                                <button type="button" class="btn btn-icon btn-icon-danger" title="Remove" wire:click="deleteInvestment(<?php echo e($i->id); ?>)" wire:confirm="Remove this investment?"><i class="bi bi-trash"></i></button>
                                                            <?php else: ?>
                                                                <i class="bi bi-lock text-muted" title="Verified or recorded by a reviewer — cannot be changed"></i>
                                                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                                        </td>
                                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                                </tr>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php elseif(! $invOpen): ?>
                                <p class="small text-muted mb-0">No investments listed yet.<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canEdit): ?> Add each investment you have received — one row per investment, even if the same investor invested more than once.<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></p>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                    
                    <?php $docs = array_filter($D::forSection($key), $docVisible); ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($docs): ?>
                        <div class="<?php echo e($key === 'attachments' ? '' : 'border-top mt-3 pt-3'); ?>">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($key === 'attachments'): ?> <div class="small text-muted mb-1"><i class="bi bi-translate me-1"></i> <?php echo e($D::LANGUAGE_NOTE); ?></div> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <div class="small text-muted mb-2"><i class="bi bi-hdd me-1"></i> <?php echo e($D::SIZE_NOTE); ?></div>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $docs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $type => [$dLabel, $dHint]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <?php
                                    $doc    = $p->exists ? $p->document($type) : null;
                                    $dReq   = in_array($type, $reqDocs, true);
                                    $either = in_array($type, $D::PLAN_OR_DECK, true);
                                ?>
                                <div class="file-row flex-wrap" <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'doc-'.e($type).''; ?>wire:key="doc-<?php echo e($type); ?>">
                                    <span class="file-row-icon"><i class="bi <?php echo e($doc ? 'bi-file-earmark-check' : 'bi-file-earmark'); ?>"></i></span>
                                    <div class="flex-grow-1 small" style="min-width: 180px">
                                        <div class="fw-semibold text-navy"><?php echo e($dLabel); ?> <?php echo $docTip($type); ?> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($dReq): ?> <span class="req">*</span> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($either): ?> <span class="pill pill-muted ms-1" style="font-size:.7rem">one of these two</span> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($doc): ?>
                                            <a href="<?php echo e(route('enterprise-documents.download', $doc)); ?>" class="text-muted text-decoration-none"><i class="bi bi-paperclip"></i> <?php echo e(\Illuminate\Support\Str::limit($doc->original_name, 40)); ?> · <?php echo e($doc->size_label); ?></a>
                                        <?php else: ?>
                                            <div class="text-muted"><?php echo e($dHint); ?></div>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ["uploads.$type"];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-danger"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </div>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canEdit): ?>
                                        <span wire:loading wire:target="uploads.<?php echo e($type); ?>" class="small text-muted"><span class="spinner-border spinner-border-sm me-1"></span> Uploading…</span>
                                        <label class="btn btn-sm <?php echo e($doc ? 'btn-outline-secondary' : 'btn-brand'); ?> mb-0" wire:loading.remove wire:target="uploads.<?php echo e($type); ?>">
                                            <i class="bi <?php echo e($doc ? 'bi-arrow-repeat' : 'bi-upload'); ?> me-1"></i><?php echo e($doc ? 'Replace' : 'Upload'); ?>

                                            <input type="file" class="d-none" wire:model="uploads.<?php echo e($type); ?>" accept="<?php echo e($D::acceptFor($type)); ?>">
                                        </label>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($doc): ?>
                                            <button type="button" class="btn btn-icon btn-icon-danger" title="Remove" wire:click="removeDocument('<?php echo e($type); ?>')" wire:confirm="Remove <?php echo e($dLabel); ?>?"><i class="bi bi-trash"></i></button>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    <?php elseif($doc): ?>
                                        <a href="<?php echo e(route('enterprise-documents.download', $doc)); ?>" class="btn btn-icon" title="Download"><i class="bi bi-download"></i></a>
                                    <?php else: ?>
                                        <span class="pill <?php echo e($dReq ? 'pill-danger' : 'pill-muted'); ?>">Missing</span>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </div>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                    
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($key === 'attachments'): ?>
                        <div class="border-top mt-3 pt-3">
                            <dt class="detail-label mb-2">Declaration</dt>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isEditing): ?>
                                <label class="form-check mb-2">
                                    <input type="checkbox" class="form-check-input <?php $__errorArgs = ['form.declaration_agreed'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model="form.declaration_agreed">
                                    <span class="form-check-label small"><?php echo e($P::DECLARATION); ?></span>
                                </label>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.declaration_agreed'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-danger small mb-2"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <div class="row g-2">
                                    <div class="col-md-8"><input type="text" class="form-control form-control-sm <?php $__errorArgs = ['form.declared_name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model="form.declared_name" placeholder="Full name and position"></div>
                                    <div class="col-md-4"><input type="date" class="form-control form-control-sm <?php $__errorArgs = ['form.declared_on'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model="form.declared_on" max="<?php echo e(now()->toDateString()); ?>"></div>
                                </div>
                            <?php elseif($p->declaration_agreed): ?>
                                <div class="small"><i class="bi bi-patch-check-fill text-accent me-1"></i> Signed by <strong><?php echo e($p->declared_name); ?></strong> on <?php echo e($p->declared_on?->format('d M Y')); ?></div>
                            <?php else: ?>
                                <div class="d-flex flex-wrap align-items-center gap-2 small">
                                    <span class="text-muted"><i class="bi bi-pen me-1"></i> Not signed yet.</span>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canEdit): ?> <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="edit('attachments')" <?php if($editing): echo 'disabled'; endif; ?>>Sign declaration</button> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </div>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isEditing): ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->any()): ?>
                            <div class="callout callout-danger small mt-3 mb-0">
                                <i class="bi bi-exclamation-triangle me-1"></i> Not saved — please fix:
                                <ul class="mb-0 mt-1"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $msg): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?><li><?php echo e($msg); ?></li><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?></ul>
                            </div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <div class="d-flex gap-2 mt-3 pt-3 border-top">
                            <button type="button" class="btn btn-link text-secondary text-decoration-none px-0" wire:click="cancel">Cancel</button>
                            <button type="button" class="btn btn-brand ms-auto" wire:click="save" wire:loading.attr="disabled" wire:target="save">
                                <span wire:loading.remove wire:target="save"><i class="bi bi-check-lg me-1"></i>Save</span>
                                <span wire:loading wire:target="save"><span class="spinner-border spinner-border-sm me-1"></span>Saving…</span>
                            </button>
                        </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </section>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        </div>

        
        <div class="col-xl-4">
            <div class="sticky-side">
                <div class="card card-modern">
                    <div class="card-body">
                        <div class="card-title-sm"><i class="bi bi-list-check"></i> Sections</div>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $P::SECTIONS; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => [$title]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <a href="#sec-<?php echo e($key); ?>" class="gate-row">
                                <span class="page-tabs-code"><?php echo e($loop->iteration); ?></span>
                                <span class="flex-grow-1 small fw-semibold text-navy"><?php echo e($title); ?></span>
                                <span class="pill <?php echo e($status[$key] ? 'pill-ok' : 'pill-warn'); ?>"><i class="bi <?php echo e($status[$key] ? 'bi-check-circle-fill' : 'bi-exclamation-circle'); ?>"></i></span>
                            </a>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($p->exists && $p->missingDocuments()): ?>
                            <div class="small text-muted mt-2">Documents still needed: <?php echo e(collect($p->missingDocuments())->map(fn ($t) => $D::missingLabel($t))->implode(', ')); ?>.</div>
                        <?php elseif($percent === 100): ?>
                            <div class="small text-muted mt-2"><i class="bi bi-check-circle-fill text-accent me-1"></i> Complete. Keep it up to date if anything changes.</div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div><?php /**PATH C:\laragon\www\co-investment-facility\storage\framework\views/livewire/views/02d8ef13.blade.php ENDPATH**/ ?>