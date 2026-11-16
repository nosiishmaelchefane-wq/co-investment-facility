
<?php
    $recused   = $card['coi'] === 'recused';
    $submitted = (bool) $myCard?->isSubmitted();
    $lock      = ! $canScore;
    $editingOther = $editingOther ?? false;
    $ownerName = $cardOwner?->name ?? auth()->user()->name;
    $myScores  = collect($card['scores'] ?? [])->filter(fn ($v) => is_numeric($v))->map(fn ($v) => (int) $v);
    $myTotal   = $myScores->sum();
    $maxTotal  = $SC::maxScore();
    $myPct     = (int) round($myTotal / max($maxTotal, 1) * 100);
    $scored    = $myScores->count();
    $nCriteria = count($SC::criteriaKeys());
    $topScore  = max(array_keys($SC::SCALE));
?>
<section class="section-card" id="g3-scorecard"
         x-data
         x-on:scroll-to.window="if ($event.detail.target === '#g3-scorecard') setTimeout(() => $el.scrollIntoView({ behavior: 'smooth', block: 'start' }), 100)"
         x-on:scorecard-invalid.window="setTimeout(() => {
             const el = document.querySelector('#g3-pack .is-invalid, #g3-scorecard .is-invalid, #g3-scorecard .text-danger');
             if (el) { el.closest('details')?.setAttribute('open', ''); el.scrollIntoView({ behavior: 'smooth', block: 'center' }); el.focus?.(); }
         }, 150)">
    <div class="section-card-head">
        <span class="section-no"><i class="bi bi-clipboard-check"></i></span>
        <div class="me-auto">
            <h5><?php echo e($editingOther ? $ownerName.'’s evaluation scorecard' : 'My evaluation scorecard'); ?></h5>
            <div class="small text-muted">Appendix F — Co-Investment Evaluation Scorecard · <?php echo e($ownerName); ?></div>
        </div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! $recused && $scored): ?>
            <span class="pill <?php echo e($myPct >= $SC::passPct() ? 'pill-ok' : 'pill-warn'); ?>" title="Your total score"><?php echo e($myTotal); ?> / <?php echo e($maxTotal); ?> · <?php echo e($myPct); ?>%</span>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($myCard?->edited_at): ?>
            <span class="pill pill-muted" title="Changed by the Committee secretary on the member’s behalf"><i class="bi bi-pencil-square"></i> Edited by <?php echo e($myCard->editor?->name ?? 'the secretary'); ?> · <?php echo e($myCard->edited_at->format('d M H:i')); ?></span>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($myCard?->reopened_at && ! $submitted): ?>
            <span class="pill pill-warn" title="Sent back by the Committee secretary to be changed and submitted again"><i class="bi bi-arrow-counterclockwise"></i> Reopened <?php echo e($myCard->reopened_at->format('d M H:i')); ?></span>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($submitted): ?>
            <span class="pill pill-ok"><i class="bi bi-check-lg"></i> Submitted <?php echo e($myCard->submitted_at->format('d M Y H:i')); ?></span>
        <?php elseif($myCard): ?>
            <span class="pill pill-warn"><i class="bi bi-pencil"></i> Draft</span>
        <?php else: ?>
            <span class="pill pill-muted">Not started</span>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>

    
    <div class="g2-sub">
        <div class="g2-sub-head"><i class="bi bi-shield-check me-1"></i> Conflict-of-interest declaration</div>
        <div class="choice-group cols-3 <?php $__errorArgs = ['card.coi'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $SC::COI; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ck => [$cl, , $cd]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <label class="choice choice--card">
                    <input type="radio" name="my-coi" value="<?php echo e($ck); ?>" wire:model.live="card.coi" <?php if($lock): echo 'disabled'; endif; ?>>
                    <span class="choice-box">
                        <span class="choice-radio"></span>
                        <span><span class="d-block fw-semibold"><?php echo e($cl); ?></span><span class="d-block small text-muted lh-sm mt-1"><?php echo e($cd); ?></span></span>
                    </span>
                </label>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        </div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['card.coi'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-danger small mt-1"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(in_array($card['coi'], ['declared', 'recused'], true)): ?>
            <textarea rows="2" class="form-control form-control-sm mt-2 <?php $__errorArgs = ['card.coi_details'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model.blur="card.coi_details"
                      placeholder="Describe the interest (relationship, nature, when)" <?php if($lock): echo 'disabled'; endif; ?>></textarea>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['card.coi_details'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($recused): ?>
        <div class="callout small mb-3"><i class="bi bi-info-circle me-1"></i> You will not score this transaction. Submit to record your recusal.</div>
    <?php else: ?>
        
        <div class="callout small mb-3">
            <div class="fw-semibold mb-1"><i class="bi bi-123 me-1"></i> Score each criterion from 1 to <?php echo e($topScore); ?></div>
            <div class="d-flex flex-wrap gap-3">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $SC::SCALE; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $sv => [$sl, $sd]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <span title="<?php echo e($sd); ?>"><strong><?php echo e($sv); ?></strong> <?php echo e($sl); ?></span>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </div>
            <div class="text-muted mt-1"><?php echo e($SC::MET_FROM); ?> or more means the criterion is met. An overall score of <?php echo e($SC::passPct()); ?>% or more supports approval — it guides, but does not replace, your recommendation.</div>
        </div>

        
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $SC::GROUPS; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $gKey => [$gTitle, $criteria]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
            <?php
                $gKeys  = array_keys($criteria);
                $gScore = collect($gKeys)->sum(fn ($k) => (int) ($card['scores'][$k] ?? 0));
                $gMax   = count($gKeys) * $topScore;
            ?>
            <div class="g2-sub" <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'grp-'.e($gKey).''; ?>wire:key="grp-<?php echo e($gKey); ?>">
                <div class="g2-sub-head d-flex align-items-center">
                    <span class="me-auto"><?php echo e($gTitle); ?></span>
                    <span class="small text-muted fw-normal"><?php echo e($gScore); ?> / <?php echo e($gMax); ?></span>
                </div>
                <div class="table-responsive">
                    <table class="table table-modern align-middle mb-2 dd-table">
                        <tbody>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $criteria; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cKey => $cText): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <tr>
                                    <td class="small">
                                        <?php echo e($cText); ?>

                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($cKey === 'innovative'): ?>
                                            <div class="d-flex flex-wrap gap-3 mt-2">
                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $SC::INNOVATION; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $iKey => $iLabel): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                                    <label class="form-check mb-0">
                                                        <input type="checkbox" class="form-check-input" wire:model.live="card.innovation.<?php echo e($iKey); ?>" <?php if($lock): echo 'disabled'; endif; ?>>
                                                        <span class="form-check-label"><?php echo e($iLabel); ?></span>
                                                    </label>
                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                            </div>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['card.innovation'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-danger small mt-1"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ["card.scores.$cKey"];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-danger small mt-1"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </td>
                                    <td class="text-center" style="width: 30%">
                                        <?php $cur = $card['scores'][$cKey] ?? null; ?>
                                        <div class="btn-group btn-group-sm" role="group" aria-label="Score">
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $SC::SCALE; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $sv => [$sl, $sd]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                                <input type="radio" class="btn-check" name="sc-<?php echo e($cKey); ?>" id="sc-<?php echo e($cKey); ?>-<?php echo e($sv); ?>" value="<?php echo e($sv); ?>" wire:model.live="card.scores.<?php echo e($cKey); ?>" <?php if($lock): echo 'disabled'; endif; ?>>
                                                <label class="btn <?php echo e($sv >= $SC::MET_FROM ? 'btn-outline-success' : 'btn-outline-danger'); ?>" for="sc-<?php echo e($cKey); ?>-<?php echo e($sv); ?>" title="<?php echo e($sl); ?> — <?php echo e($sd); ?>"><?php echo e($sv); ?></label>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                        </div>
                                        <div class="small mt-1 <?php echo e($cur === null ? 'text-muted' : ((int) $cur >= $SC::MET_FROM ? 'text-success' : 'text-danger')); ?>">
                                            <?php echo e($cur === null ? 'Not scored' : $SC::SCALE[(int) $cur][0].' · '.((int) $cur >= $SC::MET_FROM ? 'met' : 'not met')); ?>

                                        </div>
                                    </td>
                                </tr>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <textarea rows="2" class="form-control form-control-sm" wire:model.blur="card.comments.<?php echo e($gKey); ?>" placeholder="Comments" <?php if($lock): echo 'disabled'; endif; ?>></textarea>
            </div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>

        
        <div class="d-flex flex-wrap align-items-center gap-3 border rounded-3 p-3 mb-3">
            <div>
                <div class="detail-label mb-0">Your total score</div>
                <div class="fs-5 fw-semibold text-navy"><?php echo e($myTotal); ?> / <?php echo e($maxTotal); ?> <span class="small text-muted">(<?php echo e($myPct); ?>%)</span></div>
            </div>
            <div class="flex-grow-1" style="min-width: 160px">
                <div class="progress" style="height: 8px;">
                    <div class="progress-bar <?php echo e($myPct >= $SC::passPct() ? 'bg-success' : 'bg-warning'); ?>" style="width: <?php echo e($myPct); ?>%"></div>
                </div>
                <div class="small text-muted mt-1"><?php echo e($scored); ?> of <?php echo e($nCriteria); ?> criteria scored · guide <?php echo e($SC::passPct()); ?>%</div>
            </div>
        </div>

        
        <div class="g2-sub mb-0">
            <div class="g2-sub-head">Recommendation &amp; remarks</div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Use of funds — restrictions / advice</label>
                    <textarea rows="2" class="form-control form-control-sm" wire:model.blur="card.use_of_funds_notes" <?php if($lock): echo 'disabled'; endif; ?>
                              placeholder="Declaration of any restrictions for this funding. Any advice to be given with funding."></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Issues with carrying out this assignment</label>
                    <textarea rows="2" class="form-control form-control-sm" wire:model.blur="card.issues" <?php if($lock): echo 'disabled'; endif; ?>
                              placeholder="Conflict of interest, missing documents, documents hard to read / understand."></textarea>
                </div>
                <div class="col-12">
                    <label class="form-label small fw-semibold d-block">Overall recommendation <span class="req">*</span></label>
                    <div class="choice-group cols-2 <?php $__errorArgs = ['card.recommendation'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>">
                        <label class="choice"><input type="radio" name="my-rec" value="approve" wire:model.live="card.recommendation" <?php if($lock): echo 'disabled'; endif; ?>>
                            <span class="choice-box"><span class="choice-radio"></span><i class="bi bi-hand-thumbs-up text-accent"></i> Approve</span></label>
                        <label class="choice"><input type="radio" name="my-rec" value="reject" wire:model.live="card.recommendation" <?php if($lock): echo 'disabled'; endif; ?>>
                            <span class="choice-box"><span class="choice-radio"></span><i class="bi bi-hand-thumbs-down text-danger"></i> Reject</span></label>
                    </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['card.recommendation'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-danger small mt-1"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
                <div class="col-12">
                    <label class="form-label small fw-semibold">Key take-aways and overriding rationale <span class="req">*</span></label>
                    <textarea rows="3" class="form-control form-control-sm <?php $__errorArgs = ['card.takeaways'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model.blur="card.takeaways" <?php if($lock): echo 'disabled'; endif; ?>></textarea>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['card.takeaways'];
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

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($editingOther): ?>
        <div class="callout callout-warning small mb-3">
            <i class="bi bi-person-gear me-1"></i> You are editing <strong><?php echo e($ownerName); ?></strong>’s scorecard as the Committee secretary.
            Changes save as you go and are marked “Edited by <?php echo e(auth()->user()->name); ?>” on the scorecard. <?php echo e($submitted ? 'It stays submitted.' : 'It stays a draft until the member submits it.'); ?>

        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canScore && ! $editingOther && $errors->has('card.*')): ?>
        <?php
            $packMissing  = collect($CR::PACK)->filter(fn ($p, $k) => $errors->has("card.pack_comments.$k"));
            $scoreMissing = collect($SC::SHORT)->filter(fn ($l, $k) => $errors->has("card.scores.$k"));
        ?>
        <div class="callout callout-warning small mt-3 mb-0">
            <div class="fw-semibold mb-1"><i class="bi bi-exclamation-triangle me-1"></i> Your scorecard can't be submitted yet</div>
            <ul class="evidence-list mb-0">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['card.coi'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <li><?php echo e($message); ?></li> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['card.coi_details'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <li><?php echo e($message); ?></li> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($packMissing->isNotEmpty()): ?>
                    <li>
                        Comment on <?php echo e($packMissing->count() === 1 ? 'this item' : 'these '.$packMissing->count().' items'); ?> of the
                        <a href="#g3-pack">information pack</a>: <?php echo e($packMissing->map(fn ($p) => $p[0])->implode(', ')); ?>

                    </li>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($scoreMissing->isNotEmpty()): ?>
                    <li>Score: <?php echo e($scoreMissing->implode(', ')); ?></li>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['card.innovation'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <li><?php echo e($message); ?></li> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['card.recommendation'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <li><?php echo e($message); ?></li> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['card.takeaways'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <li><?php echo e($message); ?></li> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </ul>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($editingOther): ?>
        <div class="d-flex flex-wrap align-items-center gap-2 mt-3 pt-3 border-top">
            <span class="small text-muted"><i class="bi bi-check2-circle me-1"></i> All changes are saved.</span>
            <button type="button" class="btn btn-brand ms-auto" wire:click="finishMemberEdit" wire:loading.attr="disabled" wire:target="finishMemberEdit">
                <i class="bi bi-check-lg me-1"></i> Done editing
            </button>
        </div>
    <?php elseif($canScore): ?>
        <div class="d-flex flex-wrap align-items-center gap-2 mt-3 pt-3 border-top">
            <span class="small text-muted"><i class="bi bi-pen me-1"></i> Submitting signs the scorecard — your scores and your comments on the information pack — as <?php echo e(auth()->user()->name); ?>. It can't be changed afterwards.</span>
            <button type="button" class="btn btn-brand ms-auto" wire:click="submitScorecard" wire:loading.attr="disabled" wire:target="submitScorecard"
                    wire:confirm="Submit your scorecard? It can't be changed afterwards.">
                <span wire:loading.remove wire:target="submitScorecard"><i class="bi bi-send me-1"></i> <?php echo e($recused ? 'Submit recusal' : 'Submit scorecard'); ?></span>
                <span wire:loading wire:target="submitScorecard"><span class="spinner-border spinner-border-sm me-1"></span> Submitting…</span>
            </button>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</section><?php /**PATH C:\laragon\www\co-investment-facility\resources\views/expression-of-interest/committee/partials/scorecard.blade.php ENDPATH**/ ?>