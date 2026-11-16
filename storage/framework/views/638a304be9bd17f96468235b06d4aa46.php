
<?php
    $consolidated = $revealed && $review ? $review->consolidated() : null;
    $maxTotal     = $SC::maxScore();
    $pass         = $SC::passPct();
    $scoreCell = function ($v) use ($SC) {
        if ($v === null) {
            return '<span class="text-muted">—</span>';
        }
        $tone = $v >= $SC::MET_FROM ? 'text-accent' : 'text-danger';

        return '<span class="fw-semibold '.$tone.'" title="'.e($SC::SCALE[$v][0] ?? '').'">'.$v.'</span>';
    };
    $canSee = fn ($member) => $revealed || $member->id === auth()->id();
?>
<section class="section-card" id="g3-consolidated">
    <div class="section-card-head">
        <span class="section-no"><i class="bi bi-table"></i></span>
        <div class="me-auto">
            <h5>Consolidated scorecard</h5>
            <div class="small text-muted">Each criterion is scored 1–5 (<?php echo e($SC::MET_FROM); ?>+ = met). Individual scores stay hidden until all members have submitted.</div>
        </div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($consolidated && $consolidated['average_pct'] !== null): ?>
            <span class="pill <?php echo e($consolidated['average_pct'] >= $pass ? 'pill-ok' : 'pill-warn'); ?>" title="Average of the members' totals">
                Committee average <?php echo e($consolidated['average_score']); ?> / <?php echo e($maxTotal); ?> · <?php echo e($consolidated['average_pct']); ?>%
            </span>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <span class="pill <?php echo e($status['complete'] ? 'pill-ok' : 'pill-warn'); ?>">
            <i class="bi <?php echo e($status['complete'] ? 'bi-check-lg' : 'bi-hourglass-split'); ?>"></i> <?php echo e($status['submitted']); ?> of <?php echo e($status['scoring']); ?> submitted
        </span>
    </div>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($members->isEmpty()): ?>
        <p class="small text-muted mb-0">No Committee members set up.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-modern align-middle mb-2 text-center">
                <thead>
                    <tr>
                        <th class="text-start">Criteria</th>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $members; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $member): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?> <th class="small"><?php echo e($member->name); ?></th> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        <th>Average</th>
                        <th>Met</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $SC::GROUPS; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $gKey => [$gTitle, $gCriteria]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <tr class="table-light"><td colspan="<?php echo e($members->count() + 3); ?>" class="text-start small fw-semibold text-navy"><?php echo e($gTitle); ?></td></tr>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $gCriteria; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cKey => $cText): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <tr>
                                <td class="text-start small"><?php echo e($SC::SHORT[$cKey] ?? $cText); ?></td>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $members; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $member): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                    <?php $c = $cards->get($member->id); ?>
                                    <td>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($c?->isRecused()): ?>
                                            <span class="small text-muted">Recused</span>
                                        <?php elseif(! $c?->isSubmitted()): ?>
                                            <span class="pill pill-muted">Pending</span>
                                        <?php elseif($canSee($member)): ?>
                                            <?php echo $scoreCell($c->scoreFor($cKey)); ?>

                                        <?php else: ?>
                                            <i class="bi bi-lock text-muted" title="Hidden until all scorecards are in"></i>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </td>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                <td>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($consolidated && $consolidated['criteria'][$cKey]['average'] !== null): ?>
                                        <?php $avg = $consolidated['criteria'][$cKey]['average']; ?>
                                        <span class="fw-semibold <?php echo e($avg >= $SC::MET_FROM ? 'text-accent' : 'text-danger'); ?>"><?php echo e(number_format($avg, 1)); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted small">—</span>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </td>
                                <td>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($consolidated): ?>
                                        <?php $cc = $consolidated['criteria'][$cKey]; ?>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($cc['majority'] === 'tie'): ?>
                                            <span class="pill pill-warn">Split</span>
                                        <?php else: ?>
                                            <i class="bi <?php echo e($cc['majority'] === 'met' ? 'bi-check-circle-fill text-accent' : 'bi-x-circle-fill text-danger'); ?>"></i>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        <div class="text-muted" style="font-size: .7rem"><?php echo e($cc['met']); ?>–<?php echo e($cc['not_met']); ?></div>
                                    <?php else: ?>
                                        <span class="text-muted small">—</span>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </td>
                            </tr>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </tbody>
                <tfoot>
                    <tr class="fw-semibold">
                        <td class="text-start">Total score</td>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $members; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $member): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <?php $c = $cards->get($member->id); ?>
                            <td>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($c?->isRecused() || ! $c?->isSubmitted()): ?>
                                    <span class="small text-muted">—</span>
                                <?php elseif($canSee($member)): ?>
                                    <?php echo e($c->totalScore()); ?> / <?php echo e($maxTotal); ?>

                                    <div class="small <?php echo e($c->scorePct() >= $pass ? 'text-accent' : 'text-warning'); ?>"><?php echo e($c->scorePct()); ?>%</div>
                                <?php else: ?>
                                    <i class="bi bi-lock text-muted"></i>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </td>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        <td colspan="2">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($consolidated && $consolidated['average_pct'] !== null): ?>
                                <?php echo e($consolidated['average_score']); ?> / <?php echo e($maxTotal); ?>

                                <div class="small <?php echo e($consolidated['average_pct'] >= $pass ? 'text-accent' : 'text-warning'); ?>"><?php echo e($consolidated['average_pct']); ?>% average</div>
                            <?php else: ?>
                                <span class="text-muted small">—</span>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </td>
                    </tr>
                    <tr class="fw-semibold">
                        <td class="text-start">Overall recommendation</td>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $members; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $member): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <?php $c = $cards->get($member->id); ?>
                            <td>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($c?->isRecused()): ?>
                                    <span class="small text-muted">—</span>
                                <?php elseif(! $c?->isSubmitted()): ?>
                                    <span class="pill pill-muted">Pending</span>
                                <?php elseif($canSee($member)): ?>
                                    <span class="pill <?php echo e($c->recommendation === 'approve' ? 'pill-ok' : 'pill-danger'); ?>"><?php echo e($SC::RECOMMENDATIONS[$c->recommendation] ?? '—'); ?></span>
                                <?php else: ?>
                                    <i class="bi bi-lock text-muted"></i>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </td>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        <td colspan="2">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($consolidated): ?>
                                <?php $r = $consolidated['recommendation']; ?>
                                <span class="pill <?php echo e(['approve' => 'pill-ok', 'reject' => 'pill-danger', 'tie' => 'pill-warn'][$r['majority']]); ?>">
                                    <?php echo e(['approve' => 'Approve', 'reject' => 'Reject', 'tie' => 'Split'][$r['majority']]); ?> (<?php echo e($r['approve']); ?>–<?php echo e($r['reject']); ?>)
                                </span>
                            <?php else: ?>
                                <span class="text-muted small">—</span>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($consolidated && $consolidated['average_pct'] !== null): ?>
            <?php
                $scoreSays = $consolidated['average_pct'] >= $pass ? 'approve' : 'reject';
                $votesSay  = $consolidated['recommendation']['majority'];
            ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($votesSay !== 'tie' && $scoreSays !== $votesSay): ?>
                <div class="callout callout-warning small mb-2">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    The average score (<?php echo e($consolidated['average_pct']); ?>%) is <?php echo e($scoreSays === 'approve' ? 'above' : 'below'); ?> the <?php echo e($pass); ?>% guide,
                    but most members recommend <strong><?php echo e($votesSay === 'approve' ? 'Approve' : 'Reject'); ?></strong>. Discuss this before recording the outcome.
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($revealed && $review): ?>
            <?php $withComments = $review->scorecards->filter(fn ($c) => $c->isSubmitted() && ! $c->isRecused()); ?>
            <details class="evidence mt-2">
                <summary><i class="bi bi-chat-left-text me-1"></i> Members' comments &amp; take-aways</summary>
                <div class="p-3 small">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $withComments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <div class="mb-3">
                            <div class="fw-semibold text-navy mb-1"><?php echo e($c->user?->name); ?> <span class="text-muted fw-normal">· <?php echo e($c->totalScore()); ?> / <?php echo e($maxTotal); ?> (<?php echo e($c->scorePct()); ?>%)</span></div>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $SC::GROUPS; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $gKey => [$gTitle]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(filled($c->comments[$gKey] ?? null)): ?>
                                    <?php [$gs, $gm] = $c->groupScore($gKey); ?>
                                    <div><span class="text-muted"><?php echo e($gTitle); ?> (<?php echo e($gs); ?>/<?php echo e($gm); ?>):</span> <?php echo e($c->comments[$gKey]); ?></div>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($c->innovation): ?> <div><span class="text-muted">Innovation:</span> <?php echo e(collect($c->innovation)->map(fn ($i) => $SC::INNOVATION[$i] ?? $i)->implode(', ')); ?></div> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($c->use_of_funds_notes): ?> <div><span class="text-muted">Use of funds:</span> <?php echo e($c->use_of_funds_notes); ?></div> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($c->issues): ?> <div><span class="text-muted">Issues:</span> <?php echo e($c->issues); ?></div> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($c->takeaways): ?> <div class="quote mt-1 mb-0"><?php echo e($c->takeaways); ?></div> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    <div class="text-muted"><i class="bi bi-folder2-open me-1"></i> Comments on each item of the information pack are shown in the pack above.</div>
                </div>
            </details>
        <?php else: ?>
            <div class="small text-muted"><i class="bi bi-lock me-1"></i> Scores, comments and recommendations are revealed once every member has submitted.</div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</section><?php /**PATH C:\laragon\www\co-investment-facility\resources\views/expression-of-interest/committee/partials/consolidated.blade.php ENDPATH**/ ?>