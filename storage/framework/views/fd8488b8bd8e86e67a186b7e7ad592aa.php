
<?php
    $consol = $revealed && $review ? $review->consolidated() : null;
    $maj    = $consol['recommendation']['majority'] ?? null;
    $out    = $decision['outcome'];
    $diverges = $consol && $out && (
        ($maj === 'approve' && in_array($out, ['reject'], true)) ||
        ($maj === 'reject' && in_array($out, ['approve', 'conditional'], true))
    );
?>
<section class="section-card" id="g3-outcome">
    <div class="section-card-head">
        <span class="section-no"><i class="bi bi-signpost-split"></i></span>
        <div class="me-auto">
            <h5>Committee outcome</h5>
            <div class="small text-muted">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($decided): ?>
                    Recorded by <?php echo e($review->decider?->name ?? '—'); ?> on <?php echo e($review->decided_at->format('d M Y H:i')); ?>.
                <?php else: ?>
                    Conditional approval = substantively satisfied, with defined conditions. Defer = not yet enough information or assurance.
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>
    </div>

    <div class="choice-group cols-4 <?php $__errorArgs = ['decision.outcome'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $CR::OUTCOMES; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => [$oLabel, $oMeaning, $oNext, $oIcon]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
            <label class="choice choice--card">
                <input type="radio" name="g3-outcome" value="<?php echo e($value); ?>" wire:model.live="decision.outcome" <?php if(! $canRecord): echo 'disabled'; endif; ?>>
                <span class="choice-box">
                    <span class="choice-icon"><i class="bi <?php echo e($oIcon); ?>"></i></span>
                    <span>
                        <span class="d-block fw-semibold"><?php echo e($oLabel); ?></span>
                        <span class="d-block small text-muted lh-sm mt-1"><?php echo e($oMeaning); ?></span>
                        <span class="d-block small text-brand mt-1"><i class="bi bi-arrow-right-short"></i><?php echo e($oNext); ?></span>
                    </span>
                </span>
            </label>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
    </div>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['decision.outcome'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-danger small mt-1"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! $decided && $blockers): ?>
        <div class="callout callout-warning small mt-3 mb-0">
            <div class="fw-semibold mb-1"><i class="bi bi-exclamation-triangle me-1"></i> Not ready to record</div>
            <ul class="evidence-list mb-0"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $blockers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $b): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?> <li><?php echo e($b); ?></li> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?></ul>
        </div>
    <?php elseif($diverges): ?>
        <div class="callout callout-warning small mt-3 mb-0">
            <i class="bi bi-exclamation-triangle me-1"></i> This outcome differs from the members' majority recommendation
            (<?php echo e($maj === 'approve' ? 'Approve' : 'Reject'); ?>). Explain why in the rationale.
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <div class="row g-3 mt-1">
        <div class="col-md-4">
            <label class="form-label small fw-semibold">Private investment (US$)</label>
            <div class="input-group has-validation">
                <span class="input-group-text">US$</span>
                <input type="number" min="0" step="1000" class="form-control <?php $__errorArgs = ['decision.private_usd'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model.blur="decision.private_usd" <?php if(! $canRecord): echo 'disabled'; endif; ?>>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['decision.private_usd'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
            <div class="small text-muted"><?php echo e($u($decision['private_amount'])); ?> · Gate 2 validated <?php echo e($m($validated['private'])); ?></div>
        </div>
        <div class="col-md-4">
            <label class="form-label small fw-semibold">Recommended CAFI grant (US$) <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(in_array($out, ['approve', 'conditional'], true)): ?> <span class="req">*</span> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></label>
            <div class="input-group has-validation">
                <span class="input-group-text">US$</span>
                <input type="number" min="0" step="1000" class="form-control <?php $__errorArgs = ['decision.grant_usd'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model.blur="decision.grant_usd" <?php if(! $canRecord): echo 'disabled'; endif; ?>>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['decision.grant_usd'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
            <div class="small text-muted"><?php echo e($u($decision['grant_amount'])); ?> · Gate 2 validated <?php echo e($m($validated['grant'])); ?></div>
        </div>
        <div class="col-md-4">
            <label class="form-label small fw-semibold">Committee members present</label>
            <div class="small text-navy pt-1">
                <?php echo e($members->reject(fn ($mbr) => $cards->get($mbr->id)?->isRecused())->pluck('name')->implode(', ') ?: '—'); ?>

            </div>
        </div>
        <div class="col-12">
            <label class="form-label small fw-semibold">Rationale for the decision <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canRecord): ?> <span class="req">*</span> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></label>
            <textarea rows="3" class="form-control <?php $__errorArgs = ['decision.rationale'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model.blur="decision.rationale" <?php if(! $canRecord): echo 'disabled'; endif; ?>
                      placeholder="Why the Committee reached this outcome."></textarea>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['decision.rationale'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canRecord && $out === 'reject'): ?>
                <div class="small text-warning mt-1"><i class="bi bi-envelope me-1"></i> The rationale is e-mailed to the applicant as the Committee's reasons.</div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
        <div class="col-12">
            <label class="form-label small fw-semibold">Actions required before progression <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($out === 'defer' && $canRecord): ?> <span class="req">*</span> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></label>
            <textarea rows="2" class="form-control <?php $__errorArgs = ['decision.actions'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model.blur="decision.actions" <?php if(! $canRecord): echo 'disabled'; endif; ?>
                      placeholder="<?php echo e($out === 'defer' ? 'What must be provided or assessed before resubmission.' : 'e.g. Conditions precedent before disbursement.'); ?>"></textarea>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['decision.actions'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    </div>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canRecord): ?>
        <div class="d-flex flex-wrap gap-2 mt-4 pt-3 border-top">
            <span class="small text-muted align-self-center"><i class="bi bi-cloud-check me-1"></i> Draft saved automatically</span>
            <button type="button" class="btn btn-brand ms-auto" wire:click="recordDecision" wire:loading.attr="disabled" wire:target="recordDecision"
                    wire:confirm="Record the Committee decision? It is locked afterwards. The applicant, the PMU and the Committee members will be e-mailed." <?php if($blockers): echo 'disabled'; endif; ?>>
                <span wire:loading.remove wire:target="recordDecision"><i class="bi bi-send me-1"></i> Record Committee decision</span>
                <span wire:loading wire:target="recordDecision"><span class="spinner-border spinner-border-sm me-1"></span> Recording…</span>
            </button>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($decided && $review->outcome === 'defer' && $eoi->status === 'ic_deferred' && auth()->user()->can('gate2.assess')): ?>
        <div class="border-top mt-4 pt-3">
            <label class="form-label small fw-semibold">Response to the Committee <span class="req">*</span></label>
            <textarea rows="3" class="form-control <?php $__errorArgs = ['resubmissionNote'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model="resubmissionNote"
                      placeholder="How each of the Committee's requests has been addressed."></textarea>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['resubmissionNote'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <div class="text-end mt-2">
                <button type="button" class="btn btn-outline-primary" wire:click="resubmit" wire:confirm="Resubmit to the Investment Committee as round <?php echo e($round + 1); ?>? The Committee members will be e-mailed with your response.">
                    <i class="bi bi-arrow-repeat me-1"></i> Resubmit to the Committee
                </button>
            </div>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</section>


<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($decided): ?>
    <section class="section-card">
        <div class="section-card-head">
            <span class="section-no"><i class="bi bi-journal-check"></i></span>
            <h5 class="me-auto">Decision record</h5>
            <?php [$oL, , $oN, $oI, $oP] = $CR::OUTCOMES[$review->outcome]; ?>
            <span class="pill <?php echo e($oP); ?>"><i class="bi <?php echo e($oI); ?>"></i> <?php echo e($oL); ?></span>
        </div>

        <div class="table-responsive mb-3">
            <table class="table table-modern align-middle mb-0 tss-table">
                <tbody>
                    <tr><th>Transaction / enterprise</th><td><?php echo e($eoi->reference); ?> · <?php echo e($eoi->business_name); ?></td></tr>
                    <tr><th>Meeting</th><td><?php echo e($review->meeting_ref ?: '—'); ?> · <?php echo e($review->meeting_date?->format('d M Y') ?? '—'); ?> <?php echo e($review->venue ? '· '.$review->venue : ''); ?></td></tr>
                    <tr><th>Members present</th><td><?php echo e($review->scorecards->reject->isRecused()->map(fn ($c) => $c->user?->name)->filter()->implode(', ') ?: '—'); ?></td></tr>
                    <tr><th>Conflict-of-interest declarations</th><td>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $review->scorecards->whereIn('coi', ['declared', 'recused']); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <div><?php echo e($c->user?->name); ?> — <?php echo e($SC::COI[$c->coi][0]); ?><?php echo e($c->coi_details ? ': '.$c->coi_details : ''); ?></div>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?> None declared. <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </td></tr>
                    <tr><th>Outcome</th><td><span class="pill <?php echo e($oP); ?>"><?php echo e($oL); ?></span> <span class="small text-muted">· <?php echo e($oN); ?></span></td></tr>
                    <tr><th>Private investment</th><td><?php echo e($m($review->private_amount)); ?> <span class="small text-muted"><?php echo e($u($review->private_amount)); ?></span></td></tr>
                    <tr><th>Proposed CAFI grant</th><td><?php echo e($m($review->grant_amount)); ?> <span class="small text-muted"><?php echo e($u($review->grant_amount)); ?></span></td></tr>
                    <tr><th>Key conditions</th><td>
                        <?php $live = collect($review->conditions ?? [])->reject(fn ($c) => ($c['decision'] ?? '') === 'removed'); ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $live; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?> <div>• <?php echo e($c['finding']); ?> <span class="text-muted small">(<?php echo e($c['treatment']); ?>)</span></div> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?> — <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </td></tr>
                    <tr><th>Rationale</th><td><?php echo e($review->rationale); ?></td></tr>
                    <tr><th>Actions before progression</th><td><?php echo e($review->actions ?: '—'); ?></td></tr>
                </tbody>
            </table>
        </div>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($review->isApproved()): ?>
            <dt class="detail-label mb-2">Handed over to Gate 4</dt>
            <div class="d-flex flex-wrap gap-2 small mb-3">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = ['Investment Appraisal Memorandum', 'Transaction Summary Sheet', 'Supporting due diligence', 'Committee decision & scorecards', 'Conditions']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $h): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <span class="pill pill-ok"><i class="bi bi-check-lg"></i> <?php echo e($h); ?></span>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </div>
            <dt class="detail-label mb-2">Authorisation sequence</dt>
            <div class="d-flex flex-wrap align-items-center gap-2 small">
                <span class="pill pill-muted"><i class="bi bi-person-badge"></i> CAFI Managing Director</span>
                <i class="bi bi-arrow-right text-muted"></i>
                <span class="pill pill-muted"><i class="bi bi-bank2"></i> MTIBD Principal Secretary <span class="text-muted">(where applicable)</span></span>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </section>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>


<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($history->isNotEmpty()): ?>
    <section class="section-card">
        <div class="section-card-head">
            <span class="section-no"><i class="bi bi-clock-history"></i></span>
            <h5>Earlier Committee rounds</h5>
        </div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $history; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $h): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
            <?php [$hl, , , $hi, $hp] = $CR::OUTCOMES[$h->outcome] ?? [$h->outcome, '', '', 'bi-circle', 'pill-muted']; ?>
            <div class="file-row align-items-start">
                <span class="file-row-icon"><i class="bi <?php echo e($hi); ?>"></i></span>
                <div class="flex-grow-1 small">
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <strong class="text-navy">Round <?php echo e($h->round); ?></strong>
                        <span class="pill <?php echo e($hp); ?>"><?php echo e($hl); ?></span>
                        <span class="text-muted"><?php echo e($h->decided_at->format('d M Y')); ?> · <?php echo e($h->decider?->name); ?></span>
                    </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($h->rationale): ?> <div class="text-muted mt-1"><?php echo e($h->rationale); ?></div> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($h->actions): ?> <div class="mt-1"><span class="text-muted">Actions:</span> <?php echo e($h->actions); ?></div> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
    </section>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?><?php /**PATH C:\laragon\www\co-investment-facility\resources\views/expression-of-interest/committee/partials/outcome.blade.php ENDPATH**/ ?>