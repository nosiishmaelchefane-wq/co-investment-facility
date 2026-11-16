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
        $usdMain  = fn ($m) => $m === null || $m === '' ? '—' : 'US$ '.number_format((float) $m / max($rate, 0.0001));   // a Maloti amount shown in US$ (main currency)
        $mFromUsd = fn ($u) => ! is_numeric($u) || (float) $u <= 0 ? '' : '≈ M '.number_format((float) $u * max($rate, 0.0001));   // a US$ input → Maloti hint
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
                                
                                <div class="col-md-6"><label class="form-label small fw-semibold">1.1 Official company name <?php echo $tip('registered_name'); ?> <span class="req">*</span></label>
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
                                <div class="col-md-6"><label class="form-label small fw-semibold">1.2 Trading name, if different <?php echo $tip('short_name'); ?></label>
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

                                <div class="col-md-5"><label class="form-label small fw-semibold">1.3 Month and year operations commenced <?php echo $tip('year_established'); ?> <span class="req">*</span></label>
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
                                </div>

                                <div class="col-md-7"><label class="form-label small fw-semibold">1.4 Is the enterprise registered with OBFC in Lesotho? <?php echo $tip('obfc_status'); ?> <span class="req">*</span></label>
                                    <div><?php echo $radios('obfc_status', $P::YES_NO_PROGRESS, true); ?></div>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.obfc_status'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-danger small"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($form['obfc_status'] ?? '') === 'yes'): ?>
                                    <div class="col-md-5"><label class="form-label small fw-semibold">1.5 Year of registration <?php echo $tip('year_registered'); ?> <span class="req">*</span></label>
                                        <input type="number" min="1900" max="<?php echo e(date('Y')); ?>" class="form-control <?php $__errorArgs = ['form.year_registered'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model="form.year_registered">
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.year_registered'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                                    <div class="col-md-7"><label class="form-label small fw-semibold">1.6 Registration number <?php echo $tip('registration_no'); ?></label>
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
                                <?php elseif(in_array($form['obfc_status'] ?? '', ['no', 'in_progress'], true)): ?>
                                    <div class="col-12"><div class="callout callout-warning small mb-0"><i class="bi bi-exclamation-triangle me-1"></i> <?php echo e($P::REGISTRATION_NOTE); ?> Registration with the OBFC must be completed before the Grant Agreement is signed — upload a signed commitment to register under Legal status.</div></div>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                                <div class="col-12"><label class="form-label small fw-semibold">1.7 In your opinion, which stage of the business lifecycle does your startup / SME fall under? <?php echo $tip('company_stage'); ?> <span class="req">*</span></label>
                                    <select class="form-select <?php $__errorArgs = ['form.company_stage'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model="form.company_stage" style="max-width: 420px">
                                        <option value="">Select…</option>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $P::STAGES; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $lbl): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?> <option value="<?php echo e($k); ?>"><?php echo e($lbl); ?></option> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                    </select>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.company_stage'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>

                                <div class="col-12 border-top pt-3"><div class="fw-semibold text-navy small">1.8 Physical business address <?php echo $tip('address'); ?></div></div>
                                <div class="col-md-6"><label class="form-label small fw-semibold">Address <span class="req">*</span></label>
                                    <input type="text" class="form-control <?php $__errorArgs = ['form.address'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model="form.address" placeholder="Plot / street / village or area">
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.address'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                                <div class="col-md-3"><label class="form-label small fw-semibold">Town <?php echo $tip('city'); ?> <span class="req">*</span></label>
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
                                <div class="col-md-3"><label class="form-label small fw-semibold">District <?php echo $tip('district'); ?> <span class="req">*</span></label>
                                    <select class="form-select <?php $__errorArgs = ['form.district'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model="form.district">
                                        <option value="">Select…</option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $P::DISTRICTS; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $o): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?><option><?php echo e($o); ?></option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(filled($form['district'] ?? null) && ! in_array($form['district'], $P::DISTRICTS, true)): ?><option><?php echo e($form['district']); ?></option><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </select>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.district'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>

                                <div class="col-md-6"><label class="form-label small fw-semibold">1.9 Primary sector <?php echo $tip('sector'); ?> <span class="req">*</span></label>
                                    <select class="form-select <?php $__errorArgs = ['form.sector'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model.live="form.sector">
                                        <option value="">Select…</option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $P::SECTORS; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $o): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?><option><?php echo e($o); ?></option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(filled($form['sector'] ?? null) && ! in_array($form['sector'], $P::SECTORS, true)): ?><option><?php echo e($form['sector']); ?></option><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </select>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.sector'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
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
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($form['sector'] ?? '') === 'Other'): ?>
                                    <div class="col-md-6"><label class="form-label small fw-semibold">Please specify your sector <?php echo $tip('sector_other'); ?> <span class="req">*</span></label>
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

                                <?php $sumWords = str_word_count(strip_tags((string) ($form['activity'] ?? ''))); ?>
                                <div class="col-12"><label class="form-label small fw-semibold">1.10 Company summary or narrative of the main business activity (max. 100 words) <?php echo $tip('activity'); ?> <span class="req">*</span></label>
                                    <textarea rows="4" class="form-control <?php $__errorArgs = ['form.activity'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model.live.debounce.600ms="form.activity"></textarea>
                                    <div class="form-text <?php echo e($sumWords > 100 ? 'text-danger' : ''); ?>"><?php echo e($sumWords); ?> / 100 words</div>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.activity'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                                <div class="col-12"><label class="form-label small fw-semibold">1.11 Main products and / or services the enterprise currently produces or provides <?php echo $tip('products_services'); ?></label>
                                    <textarea rows="3" class="form-control <?php $__errorArgs = ['form.products_services'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model="form.products_services" placeholder="One per line, e.g. Dried fruit · Fruit juice · Contract drying for farmers"></textarea>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.products_services'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>

                                <div class="col-12 border-top pt-3"><label class="form-label small fw-semibold">1.12 Has the enterprise received support from a CAFI-funded programme? <?php echo $tip('prior_cafi_support'); ?> <span class="req">*</span></label>
                                    <div><?php echo $radios('prior_support_yn', ['yes' => 'Yes', 'no' => 'No'], true); ?></div>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.prior_support_yn'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-danger small"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($form['prior_support_yn'] ?? '') === 'yes'): ?>
                                    <div class="col-12"><label class="form-label small fw-semibold">1.13 Which programme, and the type of support received? <span class="req">*</span></label>
                                        <div class="d-flex flex-wrap gap-3">
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $P::PRIOR_SUPPORT_CHOICES; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                                <label class="form-check mb-0"><input type="checkbox" class="form-check-input" value="<?php echo e($k); ?>" wire:model="form.prior_cafi_support"> <span class="form-check-label small"><?php echo e($P::PRIOR_SUPPORT[$k]); ?></span></label>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                        </div>
                                        <?php $legacySupport = array_diff((array) ($form['prior_cafi_support'] ?? []), $P::PRIOR_SUPPORT_CHOICES); ?>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($legacySupport): ?>
                                            <div class="form-text">Also recorded earlier: <?php echo e($p->labels($P::PRIOR_SUPPORT, array_values($legacySupport))); ?>.</div>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.prior_cafi_support'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-danger small"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                                    <div class="col-12"><label class="form-label small fw-semibold">1.14 Please explain the support received <?php echo $tip('prior_support_details'); ?> <span class="req">*</span></label>
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

                                
                                <div class="col-12 border-top pt-3"><div class="fw-semibold text-navy small">Additional information</div></div>
                                <div class="col-md-6"><label class="form-label small fw-semibold">Website <?php echo $tip('website'); ?></label>
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
                                <div><dt>Trading name <?php echo $tip('short_name'); ?></dt><dd><?php echo e($dash($p->short_name)); ?></dd></div>
                                <div><dt>Operations commenced <?php echo $tip('year_established'); ?></dt><dd><?php echo e($dash($p->commencedLabel())); ?>

                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($readOnly && $p->operatingUnderSixMonths()): ?> <span class="pill pill-warn ms-1"><i class="bi bi-exclamation-circle"></i> Under 6 months</span> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></dd></div>
                                <div><dt>Registered with OBFC <?php echo $tip('obfc_status'); ?></dt><dd><?php echo e($p->obfc_status ? $ynp($p->obfc_status) : $yn($p->obfc_registered)); ?></dd></div>
                                <div><dt>Year of registration <?php echo $tip('year_registered'); ?></dt><dd><?php echo e($dash($p->year_registered)); ?></dd></div>
                                <div><dt>Registration number <?php echo $tip('registration_no'); ?></dt><dd><?php echo e($dash($p->registration_no)); ?></dd></div>
                                <div><dt>Business lifecycle stage <?php echo $tip('company_stage'); ?></dt><dd><?php echo e($P::STAGES[$p->company_stage ?? ''] ?? '—'); ?></dd></div>
                                <div><dt>Website <?php echo $tip('website'); ?></dt><dd><?php echo e($dash($p->website)); ?></dd></div>
                                <div><dt>Address <?php echo $tip('address'); ?></dt><dd><?php echo e(collect([$p->address, $p->city, $p->district])->filter()->implode(', ') ?: '—'); ?></dd></div>
                                <div><dt>Sector <?php echo $tip('sector'); ?></dt><dd><?php echo e($dash($p->sectorLabel())); ?></dd></div>
                                <div><dt>Sub-sector <?php echo $tip('sub_sector'); ?></dt><dd><?php echo e($dash($p->sub_sector)); ?></dd></div>
                                <div><dt>Business model <?php echo $tip('business_models'); ?></dt><dd><?php echo e($p->businessModelLabel() ?? '—'); ?></dd></div>
                                <div><dt>Women owned or led <?php echo $tip('women_owned_managed'); ?></dt><dd><?php echo e($yn($p->women_owned_managed)); ?></dd></div>
                                <div><dt>Youth (18–35) owned or led <?php echo $tip('youth_owned_managed'); ?></dt><dd><?php echo e($yn($p->youth_owned_managed)); ?></dd></div>
                                <div><dt>Shareholding <?php echo $tip('basotho_owned_pct'); ?></dt><dd><?php echo e($p->ownershipLine() ?? '—'); ?></dd></div>
                                <div><dt>Support from a CAFI-funded programme <?php echo $tip('prior_cafi_support'); ?></dt><dd><?php echo e($p->noPriorSupport() ? ((array) $p->prior_cafi_support === ['none'] ? 'No' : '—') : 'Yes — '.$p->labels($P::PRIOR_SUPPORT, array_values(array_diff((array) $p->prior_cafi_support, ['none'])))); ?></dd></div>
                            </div>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($p->prior_support_details): ?> <div class="quote mt-3 mb-0"><?php echo e($p->prior_support_details); ?><div class="quote-meta">Support received</div></div> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($p->activity): ?> <div class="quote mt-3 mb-0"><?php echo e($p->activity); ?><div class="quote-meta">Company summary</div></div> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($p->products_services): ?>
                                <dt class="detail-label mt-3">Main products and / or services <?php echo $tip('products_services'); ?></dt>
                                <p class="small mb-0" style="white-space: pre-line"><?php echo e($p->products_services); ?></p>
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
                                <div class="col-md-6"><label class="form-label small fw-semibold">2.1 Legal form of the enterprise <?php echo $tip('legal_form'); ?> <span class="req">*</span></label>
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
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($p->obfc_registered === false || in_array($p->obfc_status, ['no', 'in_progress'], true)): ?>
                                    <div class="col-md-6 d-flex align-items-end"><div class="callout callout-warning small mb-0 w-100"><i class="bi bi-exclamation-triangle me-1"></i> Not yet registered with the OBFC (Company profile 1.4) — upload a signed commitment to register below.</div></div>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                                <div class="col-md-6"><label class="form-label small fw-semibold">2.2 Is the enterprise registered in another country? <?php echo $tip('foreign_registered'); ?> <span class="req">*</span></label>
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
                                    <div class="col-md-3"><label class="form-label small fw-semibold">2.3 Country <?php echo $tip('foreign_country'); ?> <span class="req">*</span></label>
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
                                    <div class="col-md-3"><label class="form-label small fw-semibold">2.3 Registration no. <?php echo $tip('foreign_registration_no'); ?> <span class="req">*</span></label>
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

                                
                                <?php $shTotal = collect($form['shareholder_list'] ?? [])->filter(fn ($r) => filled($r['name'] ?? null))->sum(fn ($r) => is_numeric($r['percent'] ?? null) ? (float) $r['percent'] : 0); ?>
                                <div class="col-12 border-top pt-3">
                                    <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                        <span class="fw-semibold text-navy small me-auto">2.4 List of shareholders <?php echo $tip('shareholder_list'); ?> <span class="req">*</span></span>
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

                                <div class="col-md-6"><label class="form-label small fw-semibold">2.5 Do you have foreign ownership in your enterprise? <?php echo $tip('foreign_owned'); ?> <span class="req">*</span></label>
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
                                    <div class="col-md-6"><label class="form-label small fw-semibold">2.6 What percentage do they own? <?php echo $tip('foreign_owned_pct'); ?> <span class="req">*</span></label>
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
                                <div class="col-md-6"><label class="form-label small fw-semibold">2.7 Does the enterprise have its primary operations in Lesotho? <?php echo $tip('primary_ops_lesotho'); ?> <span class="req">*</span></label>
                                    <div><?php echo $radios('primary_ops_lesotho', ['yes' => 'Yes', 'no' => 'No']); ?></div>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.primary_ops_lesotho'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-danger small"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                                <div class="col-md-6"><label class="form-label small fw-semibold">2.8 Does the enterprise have an active tax file with RSL? <?php echo $tip('rsl_tax_file'); ?> <span class="req">*</span></label>
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
                                <div><dt>Legal form <?php echo $tip('legal_form'); ?></dt><dd><?php echo e($dash($p->legal_form)); ?></dd></div>
                                <div><dt>Registered in another country <?php echo $tip('foreign_registered'); ?></dt><dd><?php echo e($p->foreign_registered ? 'Yes — '.collect([$p->foreign_country ?: 'country not given', $p->foreign_registration_no ? 'reg. no. '.$p->foreign_registration_no : null])->filter()->implode(', ') : $yn($p->foreign_registered)); ?></dd></div>
                                <div><dt>Foreign ownership <?php echo $tip('foreign_owned'); ?></dt><dd><?php echo e($p->foreign_owned ? 'Yes — '.rtrim(rtrim(number_format((float) $p->foreign_owned_pct, 2), '0'), '.').'%' : $yn($p->foreign_owned)); ?></dd></div>
                                <div><dt>Primary operations in Lesotho <?php echo $tip('primary_ops_lesotho'); ?></dt><dd><?php echo e($yn($p->primary_ops_lesotho)); ?></dd></div>
                                <div><dt>Active RSL tax file <?php echo $tip('rsl_tax_file'); ?></dt><dd><?php echo e($ynp($p->rsl_tax_file)); ?></dd></div>
                                <div><dt>Current markets <?php echo $tip('markets'); ?></dt><dd><?php echo e($p->labels($P::MARKETS, $p->markets) ?? '—'); ?></dd></div>
                            </div>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($p->obfc_registered === false): ?>
                                <div class="callout callout-warning small mt-3 mb-0"><i class="bi bi-exclamation-triangle me-1"></i> <?php echo e($P::REGISTRATION_NOTE); ?></div>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

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
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($readOnly && $esAnswered): ?>
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
                                    <?php $flag = $readOnly ? $flag : null; ?> 
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
                            <div class="row g-3">
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
                                <div class="col-md-6"><label class="form-label small fw-semibold">Annual revenues for the past year (US$) <?php echo $tip('annual_revenue'); ?> <span class="req">*</span></label>
                                    <input type="number" min="0" step="1000" class="form-control <?php $__errorArgs = ['form.annual_revenue'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model.live.debounce.500ms="form.annual_revenue">
                                    <div class="form-text"><?php echo e($mFromUsd($form['annual_revenue'] ?? null)); ?></div>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.annual_revenue'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>

                                
                                <?php
                                    $hasInvestor = ($form['has_investor'] ?? '') === 'yes';
                                    $fx      = fn ($v) => is_numeric($v) ? (float) $v : null;
                                    $eligAmt = $fx($form['lead_investor_amount'] ?? null);   // US$
                                    $formInvestor = $hasInvestor && $eligAmt > 0 ? $P::toLsl($eligAmt, 'USD') : null;
                                    $formGrant    = $P::matchingGrant($formInvestor);
                                    $usdRate      = $P::usdRate();
                                    $equiv = fn ($v) => ! is_numeric($v) || (float) $v <= 0 ? '' : '≈ '.$lsl($P::toLsl((float) $v, 'USD')).' at M '.rtrim(rtrim(number_format($usdRate, 2), '0'), '.').' per US$';
                                    $projectCost  = $fx($form['project_cost'] ?? null) !== null ? $P::toLsl($fx($form['project_cost']), 'USD') : null;   // US$ entered → Maloti
                                    $costTooLow   = $formGrant !== null && $projectCost !== null && $projectCost + 0.5 < $formGrant;
                                    $wc = fn ($f) => str_word_count(strip_tags((string) ($form[$f] ?? '')));
                                ?>
                                <div class="col-12 border-top pt-3"><div class="fw-semibold text-navy">8.2 Investor information <?php echo $tip('section.investor'); ?></div></div>
                                <div class="col-md-6"><label class="form-label small fw-semibold">8.2.1 Has a private investor been identified? <?php echo $tip('has_investor'); ?> <span class="req">*</span></label>
                                    <div><?php echo $radios('has_investor', ['yes' => 'Yes', 'no' => 'No'], true); ?></div>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.has_investor'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-danger small"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>

                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($form['has_investor'] ?? '') === 'no'): ?>
                                    <div class="col-12"><div class="callout callout-warning small mb-0">
                                        <i class="bi bi-info-circle me-1"></i> The Co-Investment Facility matches money from a private investor, so a CAFI grant can only be awarded once you have an investor. You can save your profile now and add your investor later.
                                    </div></div>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($hasInvestor): ?>
                                    <div class="col-md-6"><label class="form-label small fw-semibold">8.2.2 Lead investor name <?php echo $tip('lead_investor_name'); ?> <span class="req">*</span></label>
                                        <input type="text" class="form-control <?php $__errorArgs = ['form.lead_investor_name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model="form.lead_investor_name" placeholder="Person, company or fund">
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.lead_investor_name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>

                                    <div class="col-12"><label class="form-label small fw-semibold mb-1">8.2.3 Lead investor contact <?php echo $tip('lead_investor_contact'); ?></label>
                                        <div class="row g-2">
                                            <div class="col-md-4"><input type="text" class="form-control <?php $__errorArgs = ['form.lead_investor_contact_name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model="form.lead_investor_contact_name" placeholder="Name *">
                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.lead_investor_contact_name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                                            <div class="col-md-4"><input type="email" class="form-control <?php $__errorArgs = ['form.lead_investor_email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model="form.lead_investor_email" placeholder="E-mail *">
                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.lead_investor_email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                                            <div class="col-md-4"><input type="tel" class="form-control <?php $__errorArgs = ['form.lead_investor_phone'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model="form.lead_investor_phone" placeholder="Telephone">
                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.lead_investor_phone'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                                        </div></div>

                                    <div class="col-md-6"><label class="form-label small fw-semibold">8.2.4 Investor website, if available</label>
                                        <input type="text" class="form-control <?php $__errorArgs = ['form.lead_investor_website'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model="form.lead_investor_website" placeholder="https://">
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.lead_investor_website'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                                    <div class="col-md-6"><label class="form-label small fw-semibold">8.2.5 Investor type <?php echo $tip('investor_type'); ?> <span class="req">*</span></label>
                                        <select class="form-select <?php $__errorArgs = ['form.investor_type'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model="form.investor_type">
                                            <option value="">Select…</option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $P::LEAD_INVESTOR_TYPES; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $o): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?><option><?php echo e($o); ?></option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(filled($form['investor_type'] ?? null) && ! in_array($form['investor_type'], $P::LEAD_INVESTOR_TYPES, true)): ?><option><?php echo e($form['investor_type']); ?></option><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        </select>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.investor_type'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                                    <div class="col-md-6"><label class="form-label small fw-semibold">8.2.6 Investor country <?php echo $tip('lead_investor_country'); ?> <span class="req">*</span></label>
                                        <select class="form-select <?php $__errorArgs = ['form.lead_investor_country'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model="form.lead_investor_country">
                                            <option value="">Select…</option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $P::COUNTRIES; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $o): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?><option><?php echo e($o); ?></option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(filled($form['lead_investor_country'] ?? null) && ! in_array($form['lead_investor_country'], $P::COUNTRIES, true)): ?><option><?php echo e($form['lead_investor_country']); ?></option><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        </select>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.lead_investor_country'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                                    <div class="col-md-6"><label class="form-label small fw-semibold">8.2.7 Investment amount from the investor <?php echo $tip('lead_investor_amount'); ?> <span class="req">*</span></label>
                                        <div class="input-group"><span class="input-group-text">US$</span>
                                            <input type="number" min="0" step="1000" class="form-control <?php $__errorArgs = ['form.lead_investor_amount'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model.live.debounce.500ms="form.lead_investor_amount">
                                        </div>
                                        <div class="form-text"><?php echo e($equiv($form['lead_investor_amount'] ?? null)); ?></div>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.lead_investor_amount'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-danger small"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                                    <div class="col-md-6"><label class="form-label small fw-semibold">8.2.8 Current investor engagement status <span class="req">*</span></label>
                                        <select class="form-select <?php $__errorArgs = ['form.transaction_status'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model="form.transaction_status">
                                            <option value="">Select…</option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $P::ENGAGEMENT_STATUSES; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $o): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?><option value="<?php echo e($k); ?>"><?php echo e($o); ?></option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(filled($form['transaction_status'] ?? null) && ! isset($P::ENGAGEMENT_STATUSES[$form['transaction_status']])): ?><option value="<?php echo e($form['transaction_status']); ?>"><?php echo e($P::TRANSACTION_STATUSES[$form['transaction_status']] ?? $form['transaction_status']); ?></option><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        </select>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.transaction_status'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                                    <div class="col-md-6"><label class="form-label small fw-semibold">8.2.9 Has the investor indicated or confirmed an investment commitment? <?php echo $tip('investor_commitment'); ?> <span class="req">*</span></label>
                                        <div><?php echo $radios('investor_commitment', $P::COMMITMENT); ?></div>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.investor_commitment'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-danger small"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                                    <div class="col-12"><div class="small text-muted"><i class="bi bi-paperclip me-1"></i> 8.2.10 Upload any evidence of investor engagement or commitment (e.g. an investment memorandum) under <strong>Documents</strong> below this section.</div></div>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($formInvestor !== null && ! $P::meetsInvestorMinimum($formInvestor)): ?>
                                        <div class="col-12"><div class="callout callout-warning small mb-0">
                                            <i class="bi bi-exclamation-triangle me-1"></i> The investment must be at least <strong>US$ <?php echo e(number_format($P::minInvestorUsd())); ?></strong> (about <?php echo e($lsl($P::minInvestorLsl())); ?>) before you can apply. You can save your profile now.
                                        </div></div>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                                    
                                    <div class="col-12 border-top pt-3"><div class="fw-semibold text-navy">8.3 Transaction information <?php echo $tip('section.transaction'); ?></div></div>
                                    <div class="col-md-6"><label class="form-label small fw-semibold">8.3.1 What is the proposed investment instrument? <?php echo $tip('participation'); ?> <span class="req">*</span></label>
                                        <select class="form-select <?php $__errorArgs = ['form.participation'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model="form.participation">
                                            <option value="">Select…</option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $P::INSTRUMENTS; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $o): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?><option value="<?php echo e($k); ?>"><?php echo e($o); ?></option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(filled($form['participation'] ?? null) && ! isset($P::INSTRUMENTS[$form['participation']])): ?><option value="<?php echo e($form['participation']); ?>"><?php echo e($P::PARTICIPATION[$form['participation']] ?? $form['participation']); ?></option><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        </select>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.participation'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                                    <div class="col-md-6"><label class="form-label small fw-semibold">8.3.2 Has the investment amount been agreed with the investor? <span class="req">*</span></label>
                                        <div><?php echo $radios('tx_amount_agreed', ['yes' => 'Yes', 'no' => 'No'], true); ?></div>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.tx_amount_agreed'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-danger small"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($form['tx_amount_agreed'] ?? '') === 'yes'): ?>
                                        <div class="col-md-6"><label class="form-label small fw-semibold">8.3.3 Agreed investment amount you will receive</label>
                                            <div class="form-control bg-light"><?php echo e($eligAmt ? 'US$ '.number_format($eligAmt) : '—'); ?></div>
                                            <div class="form-text">The amount entered in 8.2.7<?php echo e($eligAmt ? ' · '.$equiv($eligAmt) : ''); ?>. Change it there if the agreed amount is different.</div></div>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    <div class="col-md-6"><label class="form-label small fw-semibold">8.3.4 Have the principal transaction terms been agreed? <span class="req">*</span></label>
                                        <div><?php echo $radios('tx_terms_agreed', ['yes' => 'Yes', 'no' => 'No']); ?></div>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.tx_terms_agreed'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-danger small"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                                    <div class="col-md-4"><label class="form-label small fw-semibold">8.3.5 What is the valuation of your enterprise? <?php echo $tip('valuation'); ?></label>
                                        <div class="input-group"><span class="input-group-text">US$</span>
                                            <input type="number" min="0" step="1000" class="form-control <?php $__errorArgs = ['form.valuation'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model.live.debounce.500ms="form.valuation"></div>
                                        <div class="form-text"><?php echo e($equiv($form['valuation'] ?? null)); ?></div>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.valuation'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-danger small"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                                    <div class="col-md-4"><label class="form-label small fw-semibold">8.3.6 Who developed the valuation?</label>
                                        <select class="form-select <?php $__errorArgs = ['form.valuation_by'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model="form.valuation_by">
                                            <option value="">Select…</option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $P::VALUATION_BY; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $o): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?><option value="<?php echo e($k); ?>"><?php echo e($o); ?></option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?></select>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.valuation_by'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                                    <div class="col-md-4"><label class="form-label small fw-semibold">8.3.7 How was this valuation worked out?</label>
                                        <select class="form-select <?php $__errorArgs = ['form.valuation_method'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model="form.valuation_method">
                                            <option value="">Select…</option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $P::VALUATION_METHODS; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $o): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?><option value="<?php echo e($k); ?>"><?php echo e($o); ?></option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?></select>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.valuation_method'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                                    <div class="col-md-6"><label class="form-label small fw-semibold">8.3.8 When is the investment expected to close or be deployed? <?php echo $tip('expected_close'); ?> <span class="req">*</span></label>
                                        <input type="text" maxlength="120" class="form-control <?php $__errorArgs = ['form.expected_close'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model="form.expected_close" placeholder="e.g. March 2027, or within 3 months">
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.expected_close'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                                    <div class="col-md-6"><label class="form-label small fw-semibold">8.3.9 Are there material conditions outstanding before the investment can proceed? <span class="req">*</span></label>
                                        <div><?php echo $radios('tx_conditions_outstanding', ['yes' => 'Yes', 'no' => 'No']); ?></div>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.tx_conditions_outstanding'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-danger small"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                                    <div class="col-12"><label class="form-label small fw-semibold">8.3.10 Briefly describe the transaction and the intended business outcome (max. 100 words) <?php echo $tip('tx_description'); ?> <span class="req">*</span></label>
                                        <textarea rows="3" class="form-control <?php $__errorArgs = ['form.tx_description'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model.live.debounce.600ms="form.tx_description"></textarea>
                                        <div class="form-text <?php echo e($wc('tx_description') > 100 ? 'text-danger' : ''); ?>"><?php echo e($wc('tx_description')); ?> / 100 words</div>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.tx_description'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                                    <div class="col-12"><div class="small text-muted"><i class="bi bi-paperclip me-1"></i> 8.3.11 Upload a Letter of Intent, Term Sheet or other available transaction document under <strong>Documents</strong> below this section.</div></div>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                                <div class="col-md-6"><label class="form-label small fw-semibold">Total project cost <?php echo $tip('project_cost'); ?> <span class="req">*</span></label>
                                    <div class="input-group"><span class="input-group-text">US$</span>
                                        <input type="number" min="0" step="1000" class="form-control <?php $__errorArgs = ['form.project_cost'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model.live.debounce.500ms="form.project_cost"></div>
                                    <div class="form-text"><?php echo e($equiv($form['project_cost'] ?? null)); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($formGrant !== null): ?> · must be at least US$ <?php echo e(number_format($formGrant / $usdRate)); ?> (<?php echo e($lsl($formGrant)); ?>), the indicative CAFI grant <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($costTooLow): ?> <div class="small text-danger"><i class="bi bi-exclamation-circle me-1"></i> The total project cost cannot be less than the indicative CAFI grant.</div> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.project_cost'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-danger small"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>

                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($hasInvestor): ?>
                                    
                                    <?php
                                        $planT     = $P::planTotals($form['grant_plan'] ?? []);
                                        $grantUsd  = $formGrant !== null ? $formGrant / $usdRate : null;
                                        $availUsd  = $p->availableCumulativeLsl() / $usdRate;
                                        $capUsd    = (float) config('cif.co_investment.cumulative_cap_usd', 350000);
                                        $limitUsd  = $grantUsd !== null ? min($grantUsd, $availUsd) : null;
                                        $balance   = $limitUsd !== null ? $limitUsd - $planT['total'] : null;
                                        $over      = $limitUsd !== null && $planT['total'] > $limitUsd + 0.5;
                                        $lsPct     = $planT['total'] > 0 ? round(($planT['total'] - $planT['outside']) / $planT['total'] * 100, 1) : null;
                                        $minLs     = $P::minLesothoPct();
                                        $u         = fn ($v) => $v === null ? '—' : 'US$ '.number_format((float) $v);
                                    ?>
                                    <div class="col-12 border-top pt-3"><div class="fw-semibold text-navy">8.4 Proposed use of the CAFI co-investment grant <?php echo $tip('section.grant_use'); ?></div>
                                        <div class="small text-muted">Budget only what the CAFI grant will pay for — not the investor's money. Amounts in US$.</div></div>

                                    
                                    <div class="col-12">
                                        <div class="small fw-semibold text-navy mb-2">A. Indicative grant allocation</div>
                                        <div class="row g-2">
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = [
                                                ['Eligible private investment', $formInvestor !== null ? $u($formInvestor / $usdRate) : '—', $formInvestor !== null ? $lsl($formInvestor) : 'From 8.2.7', null],
                                                ['Indicative CAFI grant', $u($grantUsd), ($formInvestor ? $P::effectiveGrantPct($formInvestor).'% of the investment · ' : '').$P::grantRatioLabel(), null],
                                                ['Available cumulative allocation', $u($availUsd), 'US$ '.number_format($capUsd).' lifetime cap, less earlier grants', null],
                                                ['Total proposed grant utilisation', $u($planT['total']), 'From the budget below', $over ? 'text-danger' : null],
                                                ['Unallocated grant balance', $balance === null ? '—' : ($balance < 0 ? '− ' : '').'US$ '.number_format(abs($balance)), 'Indicative grant less proposed utilisation', $balance !== null && $balance < -0.5 ? 'text-danger' : null],
                                            ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$aLabel, $aValue, $aNote, $aCls]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                                <div class="col-6 col-lg"><div class="border rounded-3 p-2 h-100">
                                                    <div class="small text-muted"><?php echo e($aLabel); ?></div>
                                                    <div class="fw-bold <?php echo e($aCls ?? 'text-navy'); ?>"><?php echo e($aValue); ?></div>
                                                    <div class="small text-muted" style="font-size: .75rem"><?php echo e($aNote); ?></div>
                                                </div></div>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                        </div>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($over): ?>
                                            <div class="callout callout-danger small mt-2 mb-0"><i class="bi bi-exclamation-octagon me-1"></i> The proposed budget is more than the indicative grant<?php echo e($availUsd < ($grantUsd ?? INF) ? ' / the grant still available under the lifetime cap' : ''); ?>. Reduce it by <?php echo e($u(ceil($planT['total'] - $limitUsd))); ?> — lower the quantity, unit cost or lump sum of one or more lines in <strong>B. Proposed grant utilisation budget</strong> below.</div>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </div>

                                    
                                    <div class="col-12">
                                        <div class="small fw-semibold text-navy mb-1">B. Proposed grant utilisation budget <span class="req">*</span> <?php echo $tip('grant_plan'); ?></div>
                                        <div class="small text-muted mb-2">Add each item or activity under its category. Quantity × unit cost gives the total; tick <em>Lump sum</em> for costs that are not quantity-based.</div>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $P::GRANT_CATEGORIES; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ck => [$cLabel, $cEx, $cGuide]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                            <?php $items = $form['grant_plan'][$ck] ?? []; ?>
                                            <div class="border rounded-3 mb-2" <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'gp-'.e($ck).''; ?>wire:key="gp-<?php echo e($ck); ?>">
                                                <div class="d-flex flex-wrap align-items-center gap-2 px-3 py-2 bg-light rounded-top">
                                                    <div class="me-auto">
                                                        <div class="fw-semibold text-navy small"><?php echo e($cLabel); ?></div>
                                                        <div class="text-muted" style="font-size: .75rem"><?php echo e($cGuide); ?></div>
                                                    </div>
                                                    <span class="small fw-semibold"><?php echo e($u($planT['categories'][$ck] ?? 0)); ?></span>
                                                    <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="addPlanItem('<?php echo e($ck); ?>')"><i class="bi bi-plus-lg me-1"></i>Add item / activity</button>
                                                </div>
                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($items): ?>
                                                    <div class="px-3 pb-2">
                                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ii => $it): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                                            <?php
                                                                $lineTotal = $P::planItemTotal($it);
                                                                $lump      = ! empty($it['lump_sum']);
                                                                $outside   = ($it['location'] ?? 'lesotho') === 'outside';
                                                                $e         = 'form.grant_plan.'.$ck.'.'.$ii.'.';
                                                            ?>
                                                            <div class="border rounded-3 bg-white p-3 mt-2 position-relative" <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'gp-'.e($ck).'-'.e($ii).''; ?>wire:key="gp-<?php echo e($ck); ?>-<?php echo e($ii); ?>">
                                                                <div class="d-flex align-items-start gap-2 mb-2">
                                                                    <span class="badge rounded-pill text-bg-light border mt-1"><?php echo e($ii + 1); ?></span>
                                                                    <div class="flex-grow-1">
                                                                        <input type="text" class="form-control fw-semibold <?php $__errorArgs = [$e.'item'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model.blur="form.grant_plan.<?php echo e($ck); ?>.<?php echo e($ii); ?>.item" placeholder="Item / activity — <?php echo e($cEx); ?>">
                                                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = [$e.'item'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                                                    </div>
                                                                    <div class="text-end ps-2" style="min-width: 120px">
                                                                        <div class="text-muted" style="font-size: .7rem; text-transform: uppercase; letter-spacing: .03em">Total</div>
                                                                        <div class="fw-bold <?php echo e($lineTotal > 0 ? 'text-navy' : 'text-muted'); ?>"><?php echo e($u($lineTotal)); ?></div>
                                                                        <div class="text-muted" style="font-size: .72rem"><?php echo e($lineTotal > 0 ? $lsl($lineTotal * $usdRate) : ''); ?></div>
                                                                    </div>
                                                                    <button type="button" class="btn btn-icon btn-icon-danger" title="Remove this line" wire:click="removePlanItem('<?php echo e($ck); ?>', <?php echo e($ii); ?>)"><i class="bi bi-trash"></i></button>
                                                                </div>

                                                                <div class="row g-2">
                                                                    <div class="col-lg-6">
                                                                        <label class="form-label small text-muted mb-1">Description and justification</label>
                                                                        <textarea rows="2" class="form-control form-control-sm" wire:model.blur="form.grant_plan.<?php echo e($ck); ?>.<?php echo e($ii); ?>.description" placeholder="<?php echo e($cGuide); ?>"></textarea>
                                                                    </div>

                                                                    <div class="col-sm-7 col-lg-4">
                                                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                                                            <label class="form-label small text-muted mb-0">Cost (US$)</label>
                                                                            <div class="form-check form-switch mb-0 small">
                                                                                <input class="form-check-input" type="checkbox" role="switch" id="lump-<?php echo e($ck); ?>-<?php echo e($ii); ?>" wire:model.live="form.grant_plan.<?php echo e($ck); ?>.<?php echo e($ii); ?>.lump_sum">
                                                                                <label class="form-check-label" for="lump-<?php echo e($ck); ?>-<?php echo e($ii); ?>">Lump sum</label>
                                                                            </div>
                                                                        </div>
                                                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($lump): ?>
                                                                            <div class="input-group input-group-sm">
                                                                                <span class="input-group-text">US$</span>
                                                                                <input type="number" min="0" step="100" class="form-control <?php $__errorArgs = [$e.'amount'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model.live.debounce.400ms="form.grant_plan.<?php echo e($ck); ?>.<?php echo e($ii); ?>.amount" placeholder="Amount">
                                                                            </div>
                                                                        <?php else: ?>
                                                                            <div class="input-group input-group-sm">
                                                                                <input type="number" min="0" step="1" class="form-control <?php $__errorArgs = [$e.'quantity'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model.live.debounce.400ms="form.grant_plan.<?php echo e($ck); ?>.<?php echo e($ii); ?>.quantity" placeholder="Qty">
                                                                                <span class="input-group-text">×</span>
                                                                                <span class="input-group-text">US$</span>
                                                                                <input type="number" min="0" step="10" class="form-control <?php $__errorArgs = [$e.'unit_cost'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model.live.debounce.400ms="form.grant_plan.<?php echo e($ck); ?>.<?php echo e($ii); ?>.unit_cost" placeholder="Unit cost">
                                                                            </div>
                                                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = [$e.'total'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-danger small mt-1"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                                                    </div>

                                                                    <div class="col-sm-5 col-lg-2">
                                                                        <label class="form-label small text-muted mb-1">Location</label>
                                                                        <select class="form-select form-select-sm <?php echo e($outside ? 'border-warning' : ''); ?>" wire:model.live="form.grant_plan.<?php echo e($ck); ?>.<?php echo e($ii); ?>.location">
                                                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $P::LOCATIONS; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $lk => $ll): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?> <option value="<?php echo e($lk); ?>"><?php echo e($ll); ?></option> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                                                        </select>
                                                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($outside): ?>
                                                                            <input type="text" class="form-control form-control-sm mt-1 <?php $__errorArgs = [$e.'country'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model.blur="form.grant_plan.<?php echo e($ck); ?>.<?php echo e($ii); ?>.country" placeholder="Country *">
                                                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = [$e.'country'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="px-3 pb-3 pt-1 small text-muted">No items yet — click <strong>Add item / activity</strong>.</div>
                                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                            </div>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                        <div class="d-flex justify-content-between border rounded-3 px-3 py-2 fw-semibold <?php echo e($over ? 'border-danger text-danger' : ''); ?>">
                                            <span>Total proposed grant utilisation</span><span><?php echo e($u($planT['total'])); ?> <span class="small text-muted fw-normal">· <?php echo e($lsl($planT['total'] * $usdRate)); ?></span></span>
                                        </div>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.grant_plan'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-danger small mt-1"><i class="bi bi-exclamation-circle me-1"></i><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </div>

                                    
                                    <div class="col-12"><div class="small fw-semibold text-navy">C. Grant utilisation declarations</div></div>
                                    <?php
                                        $outsideLines = collect($form['grant_plan'] ?? [])->flatMap(fn ($its, $k) => collect($its)->filter(fn ($it) => ($it['location'] ?? '') === 'outside')->map(fn ($it) => $it + ['cat' => $P::GRANT_CATEGORIES[$k][0]]));
                                    ?>
                                    <div class="col-12"><label class="form-label small fw-semibold">1. Will any proposed grant expenditure take place outside Lesotho?</label>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($outsideLines->isEmpty()): ?>
                                            <div class="small">No — every budget line is in Lesotho.</div>
                                        <?php else: ?>
                                            <div class="small mb-1">Yes — <?php echo e($outsideLines->count()); ?> <?php echo e(\Illuminate\Support\Str::plural('line', $outsideLines->count())); ?>, <?php echo e($u($planT['outside'])); ?>:</div>
                                            <ul class="small mb-2">
                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $outsideLines; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ol): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?> <li><?php echo e($ol['item'] ?: 'Unnamed item'); ?> (<?php echo e($ol['cat']); ?>) — <?php echo e($u($P::planItemTotal($ol))); ?><?php echo e(filled($ol['country'] ?? null) ? ', '.$ol['country'] : ''); ?></li> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                            </ul>
                                            <label class="form-label small fw-semibold">Explain how this expenditure directly supports the growth of the Lesotho enterprise <span class="req">*</span></label>
                                            <textarea rows="3" maxlength="2000" class="form-control <?php $__errorArgs = ['form.grant_outside_justification'];
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
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </div>
                                    <div class="col-md-6"><label class="form-label small fw-semibold">2. Will at least <?php echo e((int) $minLs); ?>% of the CAFI grant be spent in Lesotho? <?php echo $tip('grant_lesotho_70'); ?> <span class="req">*</span></label>
                                        <div><?php echo $radios('grant_lesotho_70', $P::LESOTHO_70); ?></div>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.grant_lesotho_70'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-danger small"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($lsPct !== null): ?>
                                            <div class="small mt-1 <?php echo e($lsPct >= $minLs ? 'text-success' : 'text-warning-emphasis'); ?>"><i class="bi <?php echo e($lsPct >= $minLs ? 'bi-check-circle' : 'bi-exclamation-triangle'); ?> me-1"></i>From your budget: <?php echo e(rtrim(rtrim(number_format($lsPct, 1), '0'), '.')); ?>% in Lesotho.</div>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </div>
                                    <div class="col-md-6"><label class="form-label small fw-semibold">3. Will any expenditure in this budget also be financed by another grant or public funding source? <?php echo $tip('double_financing'); ?> <span class="req">*</span></label>
                                        <div><?php echo $radios('double_financing', ['yes' => 'Yes', 'no' => 'No'], true); ?></div>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.double_financing'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-danger small"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($form['double_financing'] ?? '') === 'yes'): ?>
                                        <div class="col-12"><label class="form-label small fw-semibold">Identify the expenditure and the funding source <span class="req">*</span></label>
                                            <textarea rows="2" class="form-control <?php $__errorArgs = ['form.double_financing_details'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model.blur="form.double_financing_details"></textarea>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.double_financing_details'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    <div class="col-12"><label class="form-label small fw-semibold">4. How will the proposed grant expenditure support the enterprise's growth plan? (max. 150 words) <?php echo $tip('grant_growth_support'); ?> <span class="req">*</span></label>
                                        <textarea rows="4" class="form-control <?php $__errorArgs = ['form.grant_growth_support'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model.live.debounce.600ms="form.grant_growth_support"></textarea>
                                        <div class="form-text <?php echo e($wc('grant_growth_support') > 150 ? 'text-danger' : ''); ?>"><?php echo e($wc('grant_growth_support')); ?> / 150 words</div>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.grant_growth_support'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                                    <div class="col-12"><div class="small text-muted"><i class="bi bi-paperclip me-1"></i> 5. Upload a supporting quotation or cost estimate, where available, under <strong>Documents</strong> below (optional now — more evidence may be requested during due diligence).</div></div>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                                <div class="col-12 border-top pt-3"><div class="fw-semibold text-navy small mb-2">Total amount of investment in the company to date <?php echo $tip('investment_history'); ?></div>
                                    <div class="table-responsive">
                                        <table class="table table-modern table-sm align-middle mb-0">
                                            <thead><tr><th>Source</th><th>Overall amount (US$)</th><th>Date of last investment</th></tr></thead>
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
                            <div class="detail-grid mb-3">
                                <div><dt>Annual revenue (past year) <?php echo $tip('annual_revenue'); ?></dt><dd><?php echo e($usdMain($p->annual_revenue)); ?> <span class="small text-muted"><?php echo e($p->annual_revenue !== null ? $lsl($p->annual_revenue) : ''); ?></span></dd></div>
                            </div>
                            <div class="row g-3 mb-1">
                                <div class="col-12"><dt class="detail-label">Customers <?php echo $tip('customers'); ?></dt>
                                    <p class="small mb-2" style="white-space: pre-line"><?php echo e($dash($p->customers)); ?></p></div>
                            </div>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = ['usp' => 'Unique selling proposition', 'marketing_strategy' => 'Marketing approach', 'competition' => 'Competition']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $f => $lbl): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <dt class="detail-label"><?php echo e($lbl); ?> <?php echo $tip($f); ?></dt>
                                <p class="small mb-3" style="white-space: pre-line"><?php echo e($dash($p->{$f})); ?></p>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>

                            <?php
                                $vCur = $p->investment_currency ?: 'LSL';
                                $vM   = fn ($v) => $v === null ? '—' : $P::money((float) $v, $vCur).($vCur === 'USD' ? ' · '.$lsl($P::toLsl((float) $v, 'USD')) : '');
                                $vYn  = fn ($v) => $v === null ? '—' : ($v ? 'Yes' : 'No');
                            ?>
                            <dt class="detail-label mt-2 mb-2">8.2 Investor information <?php echo $tip('section.investor'); ?></dt>
                            <div class="detail-grid mb-3">
                                <div><dt>Private investor identified</dt><dd><?php echo e($yn($p->has_investor ?? ($p->investorRows() ? true : null))); ?></dd></div>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($p->has_investor !== false): ?>
                                    <div><dt>Lead investor</dt><dd><?php echo e($dash($p->lead_investor_name ?: collect($p->investorRows())->pluck('name')->implode(', '))); ?></dd></div>
                                    <div><dt>Contact</dt><dd><?php echo e(collect([$p->lead_investor_contact_name, $p->lead_investor_email, $p->lead_investor_phone])->filter()->implode(' · ') ?: '—'); ?></dd></div>
                                    <div><dt>Website</dt><dd><?php echo e($dash($p->lead_investor_website)); ?></dd></div>
                                    <div><dt>Investor type</dt><dd><?php echo e($dash($p->investor_type)); ?></dd></div>
                                    <div><dt>Country</dt><dd><?php echo e($dash($p->lead_investor_country)); ?></dd></div>
                                    <div><dt>Investment amount</dt><dd><?php echo e($vM($p->lead_investor_amount)); ?></dd></div>
                                    <div><dt>Engagement status</dt><dd><?php echo e($P::TRANSACTION_STATUSES[$p->transaction_status ?? ''] ?? '—'); ?></dd></div>
                                    <div><dt>Commitment</dt><dd><?php echo e($P::COMMITMENT[$p->investor_commitment ?? ''] ?? '—'); ?></dd></div>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($p->has_investor === false): ?>
                                <div class="callout callout-warning small mb-3"><i class="bi bi-info-circle me-1"></i> No investor yet — a CAFI grant can only be awarded once there is a private investor.</div>
                            <?php else: ?>
                                <dt class="detail-label mb-2">8.3 Transaction information <?php echo $tip('section.transaction'); ?></dt>
                                <div class="detail-grid mb-2">
                                    <div><dt>Instrument</dt><dd><?php echo e($P::PARTICIPATION[$p->participation ?? ''] ?? '—'); ?></dd></div>
                                    <div><dt>Amount agreed</dt><dd><?php echo e($vYn($p->tx_amount_agreed)); ?><?php echo e($p->tx_amount_agreed ? ' — '.$vM($p->tx_amount) : ''); ?></dd></div>
                                    <div><dt>Principal terms agreed</dt><dd><?php echo e($vYn($p->tx_terms_agreed)); ?></dd></div>
                                    <div><dt>Valuation</dt><dd><?php echo e($vM($p->valuation)); ?><?php echo e($p->valuation_by ? ' · '.($P::VALUATION_BY[$p->valuation_by] ?? '') : ''); ?><?php echo e($p->valuation_method ? ' · '.($P::VALUATION_METHODS[$p->valuation_method] ?? '') : ''); ?></dd></div>
                                    <div><dt>Expected close / deployment</dt><dd><?php echo e($dash($p->expected_close)); ?></dd></div>
                                    <div><dt>Material conditions outstanding</dt><dd><?php echo e($vYn($p->tx_conditions_outstanding)); ?></dd></div>
                                    <div><dt>Total project cost</dt><dd><?php echo e($p->project_cost !== null ? 'US$ '.number_format((float) $p->project_cost / $P::usdRate()) : '—'); ?> <span class="small text-muted"><?php echo e($p->project_cost !== null ? $lsl($p->project_cost) : ''); ?></span></dd></div>
                                </div>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($p->tx_description): ?> <div class="quote mb-3"><?php echo e($p->tx_description); ?><div class="quote-meta">Transaction and intended outcome</div></div> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($p->investorAmount() !== null && ! $P::meetsInvestorMinimum($p->investorAmount())): ?>
                                    <div class="callout callout-warning small mb-3"><i class="bi bi-exclamation-triangle me-1"></i> The investment is below the US$ <?php echo e(number_format($P::minInvestorUsd())); ?> minimum (about <?php echo e($lsl($P::minInvestorLsl())); ?>).</div>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                                <?php
                                    $vRate  = $P::usdRate();
                                    $vPlanT = $P::planTotals((array) ($p->grant_plan ?? []));
                                    $vInv   = $p->investorAmount();
                                    $vGrant = $P::matchingGrant($vInv);
                                    $vU     = fn ($v) => $v === null ? '—' : 'US$ '.number_format((float) $v);
                                ?>
                                <dt class="detail-label mb-2">8.4 Proposed use of the CAFI grant <?php echo $tip('section.grant_use'); ?></dt>
                                <div class="detail-grid mb-2">
                                    <div><dt>Eligible private investment</dt><dd><?php echo e($vInv !== null ? $vU($vInv / $vRate) : '—'); ?> <span class="small text-muted"><?php echo e($vInv !== null ? $lsl($vInv) : ''); ?></span></dd></div>
                                    <div><dt>Indicative CAFI grant</dt><dd><?php echo e($vGrant !== null ? $vU($vGrant / $vRate) : '—'); ?></dd></div>
                                    <div><dt>Available cumulative allocation</dt><dd><?php echo e($vU($p->availableCumulativeLsl() / $vRate)); ?></dd></div>
                                    <div><dt>Total proposed grant utilisation</dt><dd><?php echo e($vU($vPlanT['total'])); ?></dd></div>
                                    <div><dt>Unallocated grant balance</dt><dd><?php echo e($vGrant !== null ? $vU($vGrant / $vRate - $vPlanT['total']) : '—'); ?></dd></div>
                                </div>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($vPlanT['total'] > 0): ?>
                                    <div class="table-responsive mb-2">
                                        <table class="table table-modern table-sm align-middle mb-0">
                                            <thead><tr><th>Item / activity</th><th>Description</th><th class="text-end">Qty</th><th class="text-end">Unit cost</th><th class="text-end">Total (US$)</th><th>Location</th></tr></thead>
                                            <tbody>
                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $P::GRANT_CATEGORIES; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ck => [$cLabel]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                                    <?php if(empty($p->grant_plan[$ck])): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?><?php continue; ?><?php endif; ?>
                                                    <tr class="table-light"><td colspan="4" class="small fw-semibold text-navy"><?php echo e($cLabel); ?></td><td class="text-end small fw-semibold"><?php echo e($vU($vPlanT['categories'][$ck])); ?></td><td></td></tr>
                                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $p->grant_plan[$ck]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $it): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                                        <tr class="small">
                                                            <td><?php echo e($it['item'] ?? ''); ?></td>
                                                            <td class="text-muted"><?php echo e($it['description'] ?? ''); ?></td>
                                                            <td class="text-end"><?php echo e(! empty($it['lump_sum']) ? 'Lump sum' : (is_numeric($it['quantity'] ?? null) ? rtrim(rtrim(number_format((float) $it['quantity'], 2), '0'), '.') : '—')); ?></td>
                                                            <td class="text-end"><?php echo e(! empty($it['lump_sum']) ? '' : $vU($it['unit_cost'] ?? null)); ?></td>
                                                            <td class="text-end"><?php echo e($vU($P::planItemTotal($it))); ?></td>
                                                            <td><?php echo e(($it['location'] ?? 'lesotho') === 'outside' ? 'Outside Lesotho'.(filled($it['country'] ?? null) ? ' ('.$it['country'].')' : '') : 'Lesotho'); ?></td>
                                                        </tr>
                                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                                <tr class="fw-semibold"><td colspan="4">Total proposed grant utilisation</td><td class="text-end"><?php echo e($vU($vPlanT['total'])); ?></td><td class="small text-muted fw-normal"><?php echo e($lsl($vPlanT['total'] * $vRate)); ?></td></tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! $p->budgetMatchesGrant()): ?>
                                        <div class="callout callout-warning small mb-2"><i class="bi bi-exclamation-triangle me-1"></i> The proposed budget is more than the indicative grant — the investment amount has probably changed. Please update the budget.</div>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <?php elseif($p->grant_budget): ?>
                                    <div class="small text-muted mb-2">An earlier budget is saved in Maloti — edit this section to move it to the new US$ budget.</div>
                                <?php else: ?>
                                    <div class="small text-muted mb-2">No grant budget yet.</div>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <div class="detail-grid mb-3">
                                    <div><dt>Spent outside Lesotho</dt><dd><?php echo e($vPlanT['outside'] > 0 ? 'Yes — '.$vU($vPlanT['outside']) : ($vPlanT['total'] > 0 ? 'No' : '—')); ?></dd></div>
                                    <div><dt>At least <?php echo e((int) $P::minLesothoPct()); ?>% in Lesotho</dt><dd><?php echo e($P::LESOTHO_70[$p->grant_lesotho_70 ?? ''] ?? '—'); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($vPlanT['total'] > 0): ?> <span class="small text-muted">· budget: <?php echo e(rtrim(rtrim(number_format(($vPlanT['total'] - $vPlanT['outside']) / $vPlanT['total'] * 100, 1), '0'), '.')); ?>%</span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></dd></div>
                                    <div><dt>Also financed by another grant</dt><dd><?php echo e($vYn($p->double_financing)); ?></dd></div>
                                </div>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($p->grant_outside_justification): ?> <div class="quote mb-2"><?php echo e($p->grant_outside_justification); ?><div class="quote-meta">Why some of the grant is spent outside Lesotho</div></div> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($p->double_financing_details): ?> <div class="quote mb-2"><?php echo e($p->double_financing_details); ?><div class="quote-meta">Other funding</div></div> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($p->grant_growth_support): ?> <div class="quote mb-3"><?php echo e($p->grant_growth_support); ?><div class="quote-meta">How the grant supports the growth plan</div></div> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($p->investment_history): ?>
                                <dt class="detail-label mb-2">Investment in the company to date <?php echo $tip('investment_history'); ?></dt>
                                <table class="table table-modern table-sm align-middle mb-0">
                                    <thead><tr><th>Source</th><th class="text-end">Overall amount</th><th>Last investment</th></tr></thead>
                                    <tbody>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $P::FUNDING_SOURCES; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $lbl): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                            <?php if(! isset($p->investment_history[$k])): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?><?php continue; ?><?php endif; ?>
                                            <?php $h = $p->investment_history[$k]; ?>
                                            <tr><td><?php echo e($lbl); ?></td><td class="text-end"><?php echo e($usdMain($h['amount'] ?? null)); ?> <div class="small text-muted"><?php echo e(isset($h['amount']) ? $lsl($h['amount']) : ''); ?></div></td>
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
                                        <div class="col-md-6"><label class="form-label small fw-semibold">Investor name <span class="req">*</span></label>
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
                                        <div class="col-md-3"><label class="form-label small fw-semibold">Investor type</label>
                                            <select class="form-select form-select-sm <?php $__errorArgs = ['inv.investor_type'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model="inv.investor_type">
                                                <option value="">Select…</option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $P::INVESTOR_TYPES; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $o): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?><option><?php echo e($o); ?></option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?></select></div>
                                        <div class="col-md-3"><label class="form-label small fw-semibold">Country</label>
                                            <input type="text" class="form-control form-control-sm" wire:model="inv.investor_country"></div>
                                        <div class="col-md-4"><label class="form-label small fw-semibold">Amount (US$) <span class="req">*</span></label>
                                            <input type="number" min="0" step="1000" class="form-control form-control-sm <?php $__errorArgs = ['inv.amount'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model.blur="inv.amount">
                                            <div class="form-text"><?php echo e($mFromUsd($inv['amount'] ?? null)); ?></div>
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
                                                        <div class="small text-muted"><?php echo e(collect([$i->investor_type, $i->investor_country, $i->round_label])->filter()->implode(' · ')); ?></div></td>
                                                    <td class="text-end text-nowrap"><?php echo e($usdMain($i->amount)); ?><div class="small text-muted"><?php echo e($lsl($i->amount)); ?></div></td>
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