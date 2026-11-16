


<section class="section-card">
    <div class="section-card-head">
        <span class="section-no"><i class="bi bi-list-check"></i></span>
        <div class="me-auto">
            <h5>Committee review criteria</h5>
            <div class="small text-muted">The Committee's view on each area — recorded by the secretary during deliberation.</div>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-modern align-middle mb-0 dd-table">
            <thead><tr><th style="width: 20%">Area</th><th>Key consideration</th><th style="width: 17%">Committee view</th><th style="width: 30%">Notes</th></tr></thead>
            <tbody>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $CR::CRITERIA; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => [$cTitle, $cQ]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <?php $view = $criteria[$key]['view']; ?>
                    <tr <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'crit-'.e($key).''; ?>wire:key="crit-<?php echo e($key); ?>">
                        <td class="fw-semibold text-navy small"><?php echo e($cTitle); ?></td>
                        <td class="small text-muted"><?php echo e($cQ); ?></td>
                        <td>
                            <select class="form-select form-select-sm status-select answer-select <?php echo e($CR::VIEWS[$view][1] ?? ''); ?>" wire:model.live="criteria.<?php echo e($key); ?>.view" <?php if(! $canRecord): echo 'disabled'; endif; ?>>
                                <option value="">—</option>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $CR::VIEWS; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $vk => [$vl]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?> <option value="<?php echo e($vk); ?>"><?php echo e($vl); ?></option> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                            </select>
                        </td>
                        <td><input type="text" class="form-control form-control-sm" wire:model.blur="criteria.<?php echo e($key); ?>.note" maxlength="500" <?php if(! $canRecord): echo 'disabled'; endif; ?>></td>
                    </tr>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>


<section class="section-card">
    <div class="section-card-head">
        <span class="section-no"><i class="bi bi-chat-square-text"></i></span>
        <div class="me-auto">
            <h5>Committee deliberation</h5>
            <div class="small text-muted">Material to the recommentation deliberation.</div>
        </div>
    </div>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $CR::STEPS; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $sKey => $sLabel): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
        <?php $done = $deliberation[$sKey]['done']; ?>
        <div class="file-row align-items-start" <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'step-'.e($sKey).''; ?>wire:key="step-<?php echo e($sKey); ?>">
            <label class="file-row-icon mb-0" style="cursor: <?php echo e($canRecord ? 'pointer' : 'default'); ?>">
                <input type="checkbox" class="form-check-input m-0" wire:model.live="deliberation.<?php echo e($sKey); ?>.done" <?php if(! $canRecord): echo 'disabled'; endif; ?>>
            </label>
            <div class="flex-grow-1">
                <div class="fw-semibold small <?php echo e($done ? 'text-accent' : 'text-navy'); ?>"><?php echo e($loop->iteration); ?> · <?php echo e($sLabel); ?></div>
                <input type="text" class="form-control form-control-sm mt-1" wire:model.blur="deliberation.<?php echo e($sKey); ?>.note" maxlength="1000" placeholder="Notes" <?php if(! $canRecord): echo 'disabled'; endif; ?>>
            </div>
        </div>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>

    <dt class="detail-label mt-3 mb-2">Questions log</dt>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $questions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $q): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
        <div class="file-row align-items-start" <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'q-'.e($i).''; ?>wire:key="q-<?php echo e($i); ?>">
            <span class="file-row-icon"><i class="bi bi-question-lg"></i></span>
            <div class="flex-grow-1 small">
                <div class="fw-semibold text-navy"><?php echo e($q['question']); ?></div>
                <div class="text-muted">Asked by <?php echo e($q['asked_by']); ?></div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(filled($q['answer'] ?? null)): ?> <div class="mt-1"><i class="bi bi-reply me-1 text-accent"></i><?php echo e($q['answer']); ?></div> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canRecord): ?>
                <button type="button" class="btn btn-icon btn-icon-danger" wire:click="removeQuestion(<?php echo e($i); ?>)" title="Remove"><i class="bi bi-x-lg"></i></button>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        <p class="small text-muted">No questions logged.</p>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canRecord): ?>
        <div class="border rounded-3 p-2 mt-2">
            <div class="row g-2">
                <div class="col-md-3">
                    <select class="form-select form-select-sm <?php $__errorArgs = ['newQuestion.asked_by'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model="newQuestion.asked_by">
                        <option value="">Asked by…</option>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $members; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $member): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?> <option value="<?php echo e($member->name); ?>"><?php echo e($member->name); ?></option> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </select>
                </div>
                <div class="col-md-9">
                    <input type="text" class="form-control form-control-sm <?php $__errorArgs = ['newQuestion.question'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model="newQuestion.question" maxlength="1000" placeholder="Question">
                </div>
                <div class="col-md-9 offset-md-3">
                    <input type="text" class="form-control form-control-sm" wire:model="newQuestion.answer" maxlength="2000" placeholder="Answer (optional)">
                </div>
                <div class="col-12 text-end">
                    <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="addQuestion"><i class="bi bi-plus-lg me-1"></i> Add question</button>
                </div>
            </div>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</section>


<section class="section-card">
    <div class="section-card-head">
        <span class="section-no"><i class="bi bi-shield-exclamation"></i></span>
        <div class="me-auto">
            <h5>Material risks &amp; conditions</h5>
            <div class="small text-muted">Do risks have mitigation, are conditions clearly defined, and is the grant still consistent with the private investment?</div>
        </div>
    </div>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($dd && filled($ddForm['key_risks'] ?? null)): ?>
        <dt class="detail-label mb-2">Key risks (Gate 2)</dt>
        <div class="quote small mb-3"><?php echo e($ddForm['key_risks']); ?></div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <div class="table-responsive mb-2">
        <table class="table table-modern align-middle mb-0 dd-table">
            <thead><tr><th>Condition</th><th style="width: 24%">Treatment</th><th style="width: 11%">Source</th><th style="width: 16%">Committee</th><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canRecord): ?><th></th><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></tr></thead>
            <tbody>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $conditions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <tr <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'cond-'.e($i).''; ?>wire:key="cond-<?php echo e($i); ?>" class="<?php echo e($c['decision'] === 'removed' ? 'text-decoration-line-through text-muted' : ''); ?>">
                        <td>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($c['source'] === 'committee' || $c['decision'] === 'amended'): ?>
                                <input type="text" class="form-control form-control-sm" wire:model.blur="conditions.<?php echo e($i); ?>.finding" maxlength="250" placeholder="Condition" <?php if(! $canRecord): echo 'disabled'; endif; ?>>
                            <?php else: ?>
                                <span class="small fw-semibold text-navy"><?php echo e($c['finding']); ?></span>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($c['status'] ?? null) === 'resolved'): ?> <span class="pill pill-ok ms-1">Resolved</span> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </td>
                        <td>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($c['source'] === 'committee' || $c['decision'] === 'amended'): ?>
                                <input type="text" class="form-control form-control-sm" wire:model.blur="conditions.<?php echo e($i); ?>.treatment" maxlength="100" <?php if(! $canRecord): echo 'disabled'; endif; ?>>
                            <?php else: ?>
                                <span class="small"><?php echo e($c['treatment']); ?></span>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </td>
                        <td><span class="pill <?php echo e($c['source'] === 'committee' ? 'pill-warn' : 'pill-muted'); ?>"><?php echo e($c['source'] === 'committee' ? 'Committee' : 'Gate 2'); ?></span></td>
                        <td>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($c['source'] === 'committee'): ?>
                                <span class="pill pill-warn">Added</span>
                            <?php else: ?>
                                <select class="form-select form-select-sm" wire:model.live="conditions.<?php echo e($i); ?>.decision" <?php if(! $canRecord): echo 'disabled'; endif; ?>>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = ['accepted', 'amended', 'removed']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $dk): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?> <option value="<?php echo e($dk); ?>"><?php echo e($CR::CONDITION_DECISIONS[$dk]); ?></option> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                </select>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </td>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canRecord): ?>
                            <td>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($c['source'] === 'committee'): ?>
                                    <button type="button" class="btn btn-icon btn-icon-danger" wire:click="removeCondition(<?php echo e($i); ?>)" title="Remove"><i class="bi bi-x-lg"></i></button>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </td>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </tr>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    <tr><td colspan="5" class="small text-muted">No conditions.</td></tr>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canRecord): ?>
        <button type="button" class="btn btn-sm btn-outline-secondary mb-3" wire:click="addCondition"><i class="bi bi-plus-lg me-1"></i> Add Committee condition</button>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <div class="callout small mb-0">
        <i class="bi bi-calculator me-1"></i> <strong>Co-investment:</strong>
        proposed CAFI grant <?php echo e($m($validated['grant'])); ?> alongside <?php echo e($m($validated['private'])); ?> committed private capital.
    </div>
</section><?php /**PATH C:\laragon\www\co-investment-facility\resources\views/expression-of-interest/committee/partials/record.blade.php ENDPATH**/ ?>