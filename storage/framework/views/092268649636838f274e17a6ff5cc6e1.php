<?php
use App\Models\DueDiligence;
use App\Models\DueDiligenceCondition;
use App\Models\EnterpriseDocument;
use App\Models\EnterpriseInvestment;
use App\Models\EnterpriseProfile;
use App\Models\ExpressionOfInterest;
use App\Notifications\EoiDueDiligenceResumed;
use App\Notifications\EoiGate2Outcome;
use App\Notifications\EoiReadyForCommittee;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
?>

<div>
<?php
    $DD       = \App\Models\DueDiligence::class;
    $DC       = \App\Models\DueDiligenceCondition::class;
    $eoi      = $this->eoi;
    $dd       = $this->dd;
    $canEdit  = $this->canEdit;
    $canMemo  = $this->canMemo;
    $decided  = (bool) $dd?->isDecided();
    $memoDone = $this->memoDone();
    $memo     = $DD::memoRows($form['memo'] ?? []);
    $answered = collect($memo)->filter(fn ($r) => filled(trim($r['text'])))->count();
    $v        = $DD::validated($form);
    $progress = $DD::progress($form, $memoDone);
    $blockers = $this->isApplicant ? [] : $DD::proceedBlockers($form, $conditions, $memoDone);
    $req      = $this->isApplicant ? [] : $this->requested;
    $evidence = $this->isApplicant || ! $this->gateOpen ? [] : $this->evidence;
    $rate     = (float) config('cif.usd_to_lsl', 18.00);
    // Amounts are stored in Maloti and shown in US$ first, Maloti underneath
    $m        = fn ($x) => $x === null || $x === '' ? '—' : 'US$ '.number_format((float) $x / max($rate, 0.0001));
    $u        = fn ($x) => $x === null || $x === '' ? '' : '≈ M '.number_format((float) $x);
?>


<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! $this->gateOpen): ?>
    <section class="section-card">
        <div class="section-card-head">
            <span class="section-no">G2</span>
            <h5 class="me-auto">Due diligence</h5>
            <span class="pill pill-muted"><i class="bi bi-lock-fill"></i> Locked</span>
        </div>
        <p class="small text-muted mb-0">Opens once the application passes Gate 1.</p>
    </section>


<?php elseif($this->isApplicant): ?>
    <?php $mine = collect($conditions)->where('owner', 'enterprise')->where('status', 'open'); ?>
    <section class="section-card">
        <div class="section-card-head">
            <span class="section-no">G2</span>
            <h5 class="me-auto">Due diligence</h5>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($decided): ?>
                <?php [$oL, , $oI, $oP] = $DD::OUTCOMES[$dd->outcome]; ?>
                <span class="pill <?php echo e($oP); ?>"><i class="bi <?php echo e($oI); ?>"></i> <?php echo e($oL); ?></span>
            <?php else: ?>
                <span class="pill pill-warn"><i class="bi bi-hourglass-split"></i> In progress</span>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
        <p class="small text-muted mb-0">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! $decided): ?> The LEHSFF team is assessing your enterprise, investor and transaction. They may contact you for more information.
            <?php elseif($dd->outcome === 'proceed'): ?> Due diligence is complete — your application goes to the Investment Committee.
            <?php elseif($dd->outcome === 'do_not_progress'): ?> Your application will not progress further.
            <?php else: ?> More information or a corrective action is needed before your application can proceed. The LEHSFF team will be in touch.
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </p>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($mine->isNotEmpty()): ?>
            <dt class="detail-label mt-3 mb-2">Actions for your enterprise</dt>
            <ul class="checklist mb-0">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $mine; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <li class="is-pending"><i class="bi bi-exclamation-circle"></i>
                        <div><div class="fw-semibold text-navy"><?php echo e($c['finding']); ?></div><div class="small text-muted"><?php echo e($DC::TREATMENTS[$c['treatment']][0] ?? ''); ?></div></div></li>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </ul>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </section>


<?php else: ?>
    
    <section class="section-card">
        <div class="section-card-head">
            <span class="section-no">G2</span>
            <div class="me-auto">
                <h5>Due diligence &amp; investment appraisal <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($round > 1): ?> <span class="pill pill-muted ms-1">Round <?php echo e($round); ?></span> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></h5>
                <div class="small text-muted">Is the transaction credible and well enough evidenced to go to the Investment Committee?</div>
            </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($decided): ?>
                <?php [$oL, , $oI, $oP] = $DD::OUTCOMES[$dd->outcome]; ?>
                <span class="pill <?php echo e($oP); ?>"><i class="bi <?php echo e($oI); ?>"></i> <?php echo e($oL); ?></span>
            <?php else: ?>
                <span class="pill pill-warn"><?php echo e($progress['done']); ?> / <?php echo e($progress['total']); ?> steps</span>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>

        <div class="progress mb-2" style="height: 6px;">
            <div class="progress-bar <?php echo e($progress['pct'] === 100 ? 'bg-accent' : 'bg-brand'); ?>" style="width: <?php echo e($progress['pct']); ?>%;"></div>
        </div>
        <div class="d-flex flex-wrap gap-3 small text-muted">
            <span><i class="bi bi-building me-1"></i><?php echo e($eoi->business_name); ?> <span class="chip ms-1"><?php echo e($eoi->reference); ?></span></span>
            <span><i class="bi bi-person me-1"></i><?php echo e($dd?->assessor?->name ?? auth()->user()->name); ?></span>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($dd?->started_on): ?> <span><i class="bi bi-calendar me-1"></i>Started <?php echo e($dd->started_on->format('d M Y')); ?></span> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canEdit || $canMemo): ?>
                <span class="ms-auto"><i class="bi bi-cloud-check me-1"></i>Saved automatically <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($savedAt): ?> · <?php echo e($savedAt); ?> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <span wire:loading class="spinner-border spinner-border-sm ms-1"></span></span>
            <?php elseif(! $decided): ?>
                <span class="ms-auto"><i class="bi bi-eye me-1"></i>Read-only</span>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    </section>

    
    <section class="section-card" id="g2-checklist">
        <div class="section-card-head">
            <span class="section-no">1</span>
            <div class="me-auto">
                <h5>Assessment checklist</h5>
                <div class="small text-muted">Proportionate and risk-based — check what matters, record what you found. Your findings are used to draft the memorandum.</div>
            </div>
            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->any(['eoi.view-all', 'users.manage'])): ?>
                <a href="<?php echo e(route('profile.index', $eoi->user_id)); ?>" target="_blank" class="small text-decoration-none"><i class="bi bi-box-arrow-up-right me-1"></i>Enterprise profile &amp; documents</a>
            <?php endif; ?>
        </div>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $DD::AREAS; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => [$aTitle, $aIcon, $aQ, $aCovers]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
            <?php $st = $form['areas'][$key]['status']; ?>
            <div class="border rounded-3 p-3 mb-2 <?php echo e($st === 'clarification' ? 'border-warning' : ''); ?>" id="g2-area-<?php echo e($key); ?>" <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'area-'.e($key).''; ?>wire:key="area-<?php echo e($key); ?>">
                <div class="d-flex flex-wrap align-items-start gap-2 mb-2">
                    <span class="section-no flex-shrink-0"><i class="bi <?php echo e($aIcon); ?>"></i></span>
                    <div class="flex-grow-1" style="min-width: 220px">
                        <div class="fw-semibold text-navy"><?php echo e($aTitle); ?></div>
                        <div class="small text-muted"><?php echo e($aQ); ?></div>
                        <div class="dd-hint text-muted mt-1"><i class="bi bi-lightbulb me-1"></i><?php echo e($aCovers); ?></div>
                    </div>
                    <select class="form-select form-select-sm status-select is-<?php echo e($st ?: 'pending'); ?>" style="width: auto" wire:model.live="form.areas.<?php echo e($key); ?>.status" <?php if(! $canEdit): echo 'disabled'; endif; ?>>
                        <option value="">Not assessed</option>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $DD::STATUSES; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $sk => [$sl]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?> <option value="<?php echo e($sk); ?>"><?php echo e($sl); ?></option> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </select>
                </div>
                <textarea rows="2" class="form-control form-control-sm" wire:model.blur="form.areas.<?php echo e($key); ?>.finding" <?php if(! $canEdit): echo 'disabled'; endif; ?>
                          placeholder="Findings — what you checked and what you found"></textarea>

                
                <?php
                    $ev      = $evidence[$key] ?? ['rows' => [], 'docs' => []];
                    $missing = collect($ev['rows'])->filter(fn ($r) => blank($r[1]))->count()
                             + collect($ev['docs'])->filter(fn ($d) => ! $d[1] && $d[2])->count();
                    $flagged = collect($ev['rows'])->filter(fn ($r) => ($r[4] ?? null) === 'bad')->count();
                ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($ev['rows'] || $ev['docs']): ?>
                    <details class="evidence mt-2">
                        <summary>
                            <i class="bi bi-file-earmark-text me-1"></i> Submitted information &amp; documents
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($missing): ?> <span class="pill pill-warn ms-2"><?php echo e($missing); ?> not provided</span> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($flagged): ?> <span class="pill pill-danger ms-1"><?php echo e($flagged); ?> flagged</span> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </summary>
                        <dl class="evidence-grid">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $ev['rows']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$rLabel, $rValue, $rSource, $rSub, $rFlag]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <dt><span class="src-tag <?php echo e($rSource === 'EOI' ? 'src-eoi' : 'src-profile'); ?>"><?php echo e($rSource); ?></span> <?php echo e($rLabel); ?></dt>
                                <dd>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(blank($rValue)): ?>
                                        <span class="text-muted fst-italic">Not provided</span>
                                    <?php elseif(is_array($rValue)): ?>
                                        <ul class="evidence-list">
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $rValue; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(is_array($item)): ?>
                                                    <li class="<?php echo e($item[1] ? 'is-yes' : 'is-no'); ?>"><i class="bi <?php echo e($item[1] ? 'bi-check-circle-fill text-success' : 'bi-x-circle text-danger'); ?>"></i> <?php echo e($item[0]); ?></li>
                                                <?php else: ?>
                                                    <li><?php echo e($item); ?></li>
                                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                        </ul>
                                    <?php else: ?>
                                        <span class="evidence-value <?php echo e($rFlag === 'bad' ? 'text-danger' : ''); ?>"><?php echo e($rValue); ?></span>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($rSub): ?>
                                        <div class="small <?php echo e(['ok' => 'text-success', 'bad' => 'text-danger'][$rFlag] ?? 'text-muted'); ?>"><?php echo e($rSub); ?></div>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </dd>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>

                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($ev['docs']): ?>
                                <dt><span class="src-tag src-profile">Docs</span> Documents</dt>
                                <dd class="d-flex flex-wrap gap-2">
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $ev['docs']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$dLabel, $doc, $dReq]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($doc): ?>
                                            <a href="<?php echo e(route('enterprise-documents.download', $doc)); ?>" class="pill pill-ok text-decoration-none" title="<?php echo e($doc->original_name); ?> · <?php echo e($doc->size_label); ?>">
                                                <i class="bi bi-download"></i> <?php echo e($dLabel); ?>

                                            </a>
                                        <?php elseif($dReq): ?>
                                            <span class="pill pill-warn" title="Required — not uploaded"><i class="bi bi-exclamation-circle"></i> <?php echo e($dLabel); ?></span>
                                        <?php else: ?>
                                            <span class="pill pill-muted" title="Optional — not uploaded"><i class="bi bi-dash-circle"></i> <?php echo e($dLabel); ?> (optional)</span>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                </dd>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </dl>
                    </details>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
    </section>

    
    <section class="section-card" id="g2-figures">
        <div class="section-card-head">
            <span class="section-no">2</span>
            <div class="me-auto">
                <h5>Transaction &amp; co-investment</h5>
                <div class="small text-muted">The validated figures that go to the Committee.</div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label small fw-semibold">Investor(s)</label>
                <input type="text" class="form-control form-control-sm" wire:model.blur="form.investor_names" maxlength="250" placeholder="e.g. Highlands Seed Partners (lead) · R. Mokoena" <?php if(! $canEdit): echo 'disabled'; endif; ?>>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Transaction stage</label>
                <select class="form-select form-select-sm" wire:model.live="form.stage" <?php if(! $canEdit): echo 'disabled'; endif; ?>>
                    <option value="">Select…</option>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $DD::STAGES; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $sk => [$sl]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?> <option value="<?php echo e($sk); ?>"><?php echo e($sl); ?></option> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </select>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($form['stage']): ?> <div class="dd-hint text-muted mt-1">Evidence: <?php echo e($DD::STAGES[$form['stage']][1]); ?></div> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Instrument</label>
                <select class="form-select form-select-sm" wire:model.live="form.instrument" <?php if(! $canEdit): echo 'disabled'; endif; ?>>
                    <option value="">Select…</option>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $DD::INSTRUMENTS; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ik => $il): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?> <option value="<?php echo e($ik); ?>"><?php echo e($il); ?></option> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-semibold">Private capital committed (US$)</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text">US$</span>
                    <input type="number" min="0" step="1000" class="form-control" wire:model.blur="form.private_usd" <?php if(! $canEdit): echo 'disabled'; endif; ?>>
                </div>
                <div class="dd-hint text-muted mt-1"><?php echo e($u($form['private_amount'])); ?> · Only signed, eligible commitments. Applicant stated <?php echo e($m($req['private'] ?? null)); ?></div>
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-semibold">CAFI grant (US$)</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text">US$</span>
                    <input type="number" min="0" step="1000" class="form-control" wire:model.blur="form.grant_usd" <?php if(! $canEdit): echo 'disabled'; endif; ?>>
                </div>
                <div class="dd-hint text-muted mt-1"><?php echo e($u($form['grant_amount'])); ?> · <?php echo e(\App\Models\EnterpriseProfile::grantRatioLabel()); ?> of the validated investor amount = <?php echo e($m(\App\Models\EnterpriseProfile::matchingGrant(is_numeric($form['private_amount'] ?? null) ? (float) $form['private_amount'] : null))); ?></div>
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-semibold">ESGC risk</label>
                <select class="form-select form-select-sm rating-select is-<?php echo e($form['esgc_category'] ?: 'none'); ?>" wire:model.live="form.esgc_category" <?php if(! $canEdit): echo 'disabled'; endif; ?>>
                    <option value="">Select…</option>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $DD::RATINGS; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $rk => $rl): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?> <option value="<?php echo e($rk); ?>"><?php echo e($rl); ?></option> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </select>
            </div>
        </div>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($form['stage'] && ! in_array($form['stage'], $DD::COMMITTED_STAGES, true)): ?>
            <div class="callout callout-warning small mt-3 mb-0"><i class="bi bi-exclamation-triangle me-1"></i> This is investor <strong>interest</strong>, not commitment — an LOI or later is needed.</div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <div class="callout small mt-3 mb-0">
            <i class="bi bi-calculator me-1"></i>
            Total transaction (private capital + CAFI grant): <strong><?php echo e($m($v['total'])); ?></strong> <span class="text-muted"><?php echo e($u($v['total'])); ?></span>
        </div>
    </section>

    
    <section class="section-card" id="g2-conditions">
        <div class="section-card-head">
            <span class="section-no">3</span>
            <div class="me-auto">
                <h5>Conditions &amp; actions</h5>
                <div class="small text-muted">Anything that must be done, and when — including ESAP actions. "Before the Committee" items block Proceed while open. Open items owned by the enterprise or investor are e-mailed to the applicant with the outcome.</div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-modern align-middle mb-2 dd-table">
                <thead><tr><th>Condition / action</th><th style="width: 26%">When</th><th style="width: 13%">Owner</th><th style="width: 13%">Status</th><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canEdit): ?><th></th><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></tr></thead>
                <tbody>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $conditions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <tr <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'cond-'.e($c['id'] ?? 'n'.$i).''; ?>wire:key="cond-<?php echo e($c['id'] ?? 'n'.$i); ?>" class="<?php echo e($DC::blocks($c) ? 'is-flagged' : ''); ?>">
                            <td><input type="text" class="form-control form-control-sm" wire:model.blur="conditions.<?php echo e($i); ?>.finding" maxlength="250" placeholder="e.g. Signed shareholder agreement" <?php if(! $canEdit): echo 'disabled'; endif; ?>></td>
                            <td><select class="form-select form-select-sm" wire:model.live="conditions.<?php echo e($i); ?>.treatment" <?php if(! $canEdit): echo 'disabled'; endif; ?>>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $DC::TREATMENTS; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tk => [$tl]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?> <option value="<?php echo e($tk); ?>"><?php echo e($tl); ?></option> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?></select></td>
                            <td><select class="form-select form-select-sm" wire:model.live="conditions.<?php echo e($i); ?>.owner" <?php if(! $canEdit): echo 'disabled'; endif; ?>>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $DC::OWNERS; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ok => $ol): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?> <option value="<?php echo e($ok); ?>"><?php echo e($ol); ?></option> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?></select></td>
                            <td><select class="form-select form-select-sm" wire:model.live="conditions.<?php echo e($i); ?>.status" <?php if(! $canEdit): echo 'disabled'; endif; ?>>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $DC::STATUSES; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $sk => [$sl]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?> <option value="<?php echo e($sk); ?>"><?php echo e($sl); ?></option> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?></select></td>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canEdit): ?>
                                <td><button type="button" class="btn btn-icon btn-icon-danger" wire:click="removeCondition(<?php echo e($i); ?>)" title="Remove"><i class="bi bi-x-lg"></i></button></td>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </tr>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        <tr><td colspan="5" class="small text-muted">None yet.</td></tr>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canEdit): ?>
            <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="addCondition"><i class="bi bi-plus-lg me-1"></i> Add condition</button>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </section>

    
    <section class="section-card" id="g2-summary">
        <div class="section-card-head">
            <span class="section-no">4</span>
            <div class="me-auto">
                <h5>Summary &amp; Investment Appraisal Memorandum</h5>
                <div class="small text-muted">With the figures above, this forms the Transaction Summary Sheet and the memorandum for the Investment Committee.</div>
            </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($dd): ?>
                <a href="<?php echo e(route('due-diligence.memorandum', $dd)); ?>" class="btn btn-sm btn-outline-secondary" title="Download the memorandum as PDF">
                    <i class="bi bi-file-earmark-pdf me-1"></i> Download PDF
                </a>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
        <div class="row g-3">
            <div class="col-12">
                <label class="form-label small fw-semibold">Enterprise &amp; transaction summary</label>
                <textarea rows="2" class="form-control form-control-sm" wire:model.blur="form.summary" <?php if(! $canEdit): echo 'disabled'; endif; ?> placeholder="Business, sector and what the investment is for"></textarea>
            </div>
            <div class="col-md-6">
                <label class="form-label small fw-semibold">Expected outcomes</label>
                <textarea rows="2" class="form-control form-control-sm" wire:model.blur="form.expected_outcomes" <?php if(! $canEdit): echo 'disabled'; endif; ?> placeholder="Revenue, jobs, exports, innovation"></textarea>
            </div>
            <div class="col-md-6">
                <label class="form-label small fw-semibold">Key risks</label>
                <textarea rows="2" class="form-control form-control-sm" wire:model.blur="form.key_risks" <?php if(! $canEdit): echo 'disabled'; endif; ?> placeholder="Main risks and how they are mitigated"></textarea>
            </div>
        </div>

        
        <div class="d-flex flex-wrap align-items-center gap-2 mt-4 mb-2">
            <div class="me-auto">
                <div class="fw-semibold text-navy">Investment Appraisal Memorandum</div>
                <div class="small text-muted">
                    One section per enterprise profile section, drafted automatically from the profile, your checklist findings, key figures and conditions — it updates as you assess.
                    Read each section, change it if needed, then click <strong>Verify</strong>. Changing the text clears the verification.
                </div>
            </div>
            <span class="pill <?php echo e($answered === count($memo) ? 'pill-ok' : 'pill-warn'); ?>"><?php echo e($answered); ?> / <?php echo e(count($memo)); ?> answered</span>
            <span class="pill <?php echo e($memoDone ? 'pill-ok' : 'pill-warn'); ?>"><i class="bi bi-patch-check"></i> <?php echo e($DD::memoVerifiedCount($form['memo'] ?? [])); ?> / <?php echo e(count($memo)); ?> verified</span>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canMemo): ?>
                <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="redraftMemo" wire:loading.attr="disabled" wire:target="redraftMemo"
                        wire:confirm="Replace all five sections — including your edits and verifications — with the automatic draft?">
                    <i class="bi bi-arrow-repeat me-1"></i> Redraft all
                </button>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $DD::memoQuestions($form['memo'] ?? []); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $mk => [$mTitle, $mQuestion]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
            <?php $row = $memo[$mk]; ?>
            <div class="border rounded-3 p-3 mb-2 <?php echo e($row['verified'] ? 'border-success' : ''); ?>" <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'memo-'.e($mk).''; ?>wire:key="memo-<?php echo e($mk); ?>">
                <div class="d-flex align-items-start gap-2 mb-2">
                    <span class="section-no flex-shrink-0"><?php echo e($loop->iteration); ?></span>
                    <div class="flex-grow-1">
                        <div class="fw-semibold text-navy"><i class="bi <?php echo e(\App\Models\EnterpriseProfile::SECTIONS[$mk][1] ?? 'bi-dot'); ?> me-1"></i><?php echo e($mTitle); ?></div>
                        <div class="small text-muted"><?php echo e($mQuestion); ?></div>
                    </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($row['edited']): ?>
                        <span class="pill pill-ok" title="Written or changed by the officer — not redrafted automatically"><i class="bi bi-pencil"></i> Edited</span>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canMemo): ?>
                            <button type="button" class="btn btn-icon" wire:click="redraftMemo('<?php echo e($mk); ?>')" title="Replace with the automatic draft"
                                    wire:confirm="Replace this answer with the automatic draft? Your changes will be lost.">
                                <i class="bi bi-arrow-counterclockwise"></i>
                            </button>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php else: ?>
                        <span class="pill pill-muted" title="Drafted from the assessment — updates automatically until you edit it"><i class="bi bi-magic"></i> Auto-drafted</span>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
                <textarea rows="<?php echo e($mk === 'business' ? 12 : 6); ?>" class="form-control form-control-sm" wire:model.blur="form.memo.<?php echo e($mk); ?>.text" <?php if(! $canMemo): echo 'disabled'; endif; ?>
                          placeholder="Answer for the Investment Committee"></textarea>

                
                <div class="d-flex flex-wrap align-items-center gap-2 mt-2">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($row['verified']): ?>
                        <span class="pill pill-ok"><i class="bi bi-patch-check-fill"></i> Verified</span>
                        <span class="small text-muted">
                            <?php echo e($row['verified_by'] ?: 'Officer'); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($row['verified_at']): ?> · <?php echo e(\Illuminate\Support\Carbon::parse($row['verified_at'])->format('d M Y H:i')); ?><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </span>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canMemo): ?>
                            <button type="button" class="btn btn-sm btn-link text-secondary text-decoration-none ms-auto" wire:click="unverifyMemo('<?php echo e($mk); ?>')"
                                    wire:loading.attr="disabled" wire:target="unverifyMemo('<?php echo e($mk); ?>')">
                                <i class="bi bi-arrow-counterclockwise me-1"></i> Undo
                            </button>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php else: ?>
                        <span class="pill pill-warn"><i class="bi bi-hourglass-split"></i> Not verified</span>
                        <span class="small text-muted">Check the text against the profile and your assessment.</span>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canMemo): ?>
                            <button type="button" class="btn btn-sm btn-outline-success ms-auto" wire:click="verifyMemo('<?php echo e($mk); ?>')"
                                    wire:loading.attr="disabled" wire:target="verifyMemo('<?php echo e($mk); ?>')" <?php if(blank(trim($row['text']))): echo 'disabled'; endif; ?>>
                                <i class="bi bi-check2-circle me-1"></i> Verify section
                            </button>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($dd?->iam_path): ?>
            <div class="file-row mt-3 mb-0">
                <span class="file-row-icon"><i class="bi bi-paperclip"></i></span>
                <div class="flex-grow-1 small">
                    <div class="fw-semibold text-navy">Earlier uploaded memorandum · <?php echo e($dd->iam_name); ?></div>
                    <div class="text-muted"><?php echo e($dd->iamUploader?->name); ?> · <?php echo e($dd->iam_uploaded_at?->format('d M Y H:i')); ?></div>
                </div>
                <a href="<?php echo e(route('due-diligence.memorandum', [$dd, 'file' => 1])); ?>" class="btn btn-icon" title="Download"><i class="bi bi-download"></i></a>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </section>

    
    <section class="section-card" id="g2-outcome">
        <div class="section-card-head">
            <span class="section-no">5</span>
            <div class="me-auto">
                <h5>Gate 2 outcome</h5>
                <div class="small text-muted"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($decided): ?> Recorded by <?php echo e($dd->decider?->name); ?> on <?php echo e($dd->decided_at->format('d M Y H:i')); ?>. <?php else: ?> Is the transaction ready for the Investment Committee? <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
            </div>
        </div>

        <div class="choice-group cols-4 <?php $__errorArgs = ['form.outcome'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $DD::OUTCOMES; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => [$oLabel, $oDesc, $oIcon]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <label class="choice choice--card">
                    <input type="radio" name="g2-outcome" value="<?php echo e($value); ?>" wire:model.live="form.outcome" <?php if(! $canEdit): echo 'disabled'; endif; ?>>
                    <span class="choice-box">
                        <span class="choice-icon"><i class="bi <?php echo e($oIcon); ?>"></i></span>
                        <span><span class="d-block fw-semibold"><?php echo e($oLabel); ?></span><span class="d-block small text-muted lh-sm mt-1"><?php echo e($oDesc); ?></span></span>
                    </span>
                </label>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        </div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.outcome'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-danger small mt-1"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! $decided): ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($blockers): ?>
                <div class="callout callout-warning small mt-3 mb-0">
                    <div class="fw-semibold mb-1"><i class="bi bi-list-check me-1"></i> Before you can Proceed (<?php echo e(count($blockers)); ?>)</div>
                    <ul class="evidence-list mb-0"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $blockers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $b): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?> <li><?php echo e($b); ?></li> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?></ul>
                </div>
            <?php else: ?>
                <div class="callout callout-success small mt-3 mb-0"><i class="bi bi-check2-circle me-1"></i> Ready to proceed to the Investment Committee.</div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <div class="row g-3 mt-1">
            <div class="col-md-9">
                <label class="form-label small fw-semibold">Conclusion <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canEdit): ?><span class="req">*</span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></label>
                <textarea rows="3" class="form-control <?php $__errorArgs = ['form.conclusion'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model.blur="form.conclusion" <?php if(! $canEdit): echo 'disabled'; endif; ?>
                          placeholder="Summary of the assessment and your recommendation"></textarea>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.conclusion'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canEdit && ($form['outcome'] ?? null) === 'do_not_progress'): ?>
                    <div class="dd-hint text-warning mt-1"><i class="bi bi-envelope me-1"></i> This conclusion is e-mailed to the applicant as the basis for the decision.</div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Date</label>
                <input type="date" class="form-control <?php $__errorArgs = ['form.assessed_on'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model.blur="form.assessed_on" max="<?php echo e(now()->toDateString()); ?>" <?php if(! $canEdit): echo 'disabled'; endif; ?>>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.assessed_on'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canEdit): ?>
            <div class="d-flex mt-4 pt-3 border-top">
                <button type="button" class="btn btn-brand ms-auto" wire:click="recordOutcome" wire:loading.attr="disabled" wire:target="recordOutcome"
                        wire:confirm="Record the Gate 2 outcome? The assessment and memorandum are locked afterwards, and the applicant will be e-mailed (and the Investment Committee too, if it proceeds).">
                    <span wire:loading.remove wire:target="recordOutcome"><i class="bi bi-send me-1"></i> Record outcome</span>
                    <span wire:loading wire:target="recordOutcome"><span class="spinner-border spinner-border-sm me-1"></span> Recording…</span>
                </button>
            </div>
        <?php elseif($dd?->isOnHold() && $eoi->status === 'dd_hold' && auth()->user()->can('gate2.assess')): ?>
            <div class="d-flex align-items-center gap-2 mt-4 pt-3 border-top">
                <span class="small text-muted">Resume once the clarification or remediation has been received. The applicant is e-mailed that the assessment has resumed.</span>
                <button type="button" class="btn btn-outline-primary ms-auto" wire:click="resumeAssessment" wire:confirm="Resume as round <?php echo e($round + 1); ?>?">
                    <i class="bi bi-arrow-repeat me-1"></i> Resume assessment
                </button>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </section>

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->history->isNotEmpty()): ?>
        <section class="section-card">
            <div class="section-card-head"><span class="section-no"><i class="bi bi-clock-history"></i></span><h5>Earlier rounds</h5></div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $this->history; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $h): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <?php [$hl, , $hi, $hp] = $DD::OUTCOMES[$h->outcome] ?? [$h->outcome, '', 'bi-circle', 'pill-muted']; ?>
                <div class="file-row align-items-start">
                    <span class="file-row-icon"><i class="bi <?php echo e($hi); ?>"></i></span>
                    <div class="flex-grow-1 small">
                        <strong class="text-navy">Round <?php echo e($h->round); ?></strong> <span class="pill <?php echo e($hp); ?> ms-1"><?php echo e($hl); ?></span>
                        <span class="text-muted ms-1"><?php echo e($h->decided_at->format('d M Y')); ?> · <?php echo e($h->decider?->name); ?></span>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($h->conclusion): ?> <div class="text-muted mt-1"><?php echo e($h->conclusion); ?></div> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                    <a href="<?php echo e(route('due-diligence.memorandum', $h)); ?>" class="btn btn-icon" title="Memorandum (PDF) — round <?php echo e($h->round); ?>"><i class="bi bi-file-earmark-pdf"></i></a>
                </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        </section>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div><?php /**PATH C:\laragon\www\co-investment-facility\storage\framework\views/livewire/views/0d243462.blade.php ENDPATH**/ ?>