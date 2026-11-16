
<?php $size = $CR::expectedSize(); ?>
<section class="section-card">
    <div class="section-card-head">
        <span class="section-no"><i class="bi bi-people"></i></span>
        <div class="me-auto">
            <h5>Committee members &amp; declarations</h5>
            <div class="small text-muted">Scores are consolidated only once every member who is not recused has submitted.</div>
        </div>
        <span class="pill <?php echo e($status['members'] >= $size ? 'pill-ok' : 'pill-danger'); ?>">
            <i class="bi <?php echo e($status['members'] >= $size ? 'bi-check-lg' : 'bi-exclamation-triangle'); ?>"></i> Members <?php echo e($status['members']); ?> / <?php echo e($size); ?>

        </span>
        <span class="pill <?php echo e($status['complete'] ? 'pill-ok' : 'pill-warn'); ?>">Submitted <?php echo e($status['submitted']); ?> / <?php echo e($status['scoring']); ?></span>
    </div>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($members->isEmpty()): ?>
        <div class="callout callout-warning small mb-0">
            <i class="bi bi-exclamation-triangle me-1"></i> No active users have the <strong>Investment Committee</strong> role. Assign it in user management.
        </div>
    <?php else: ?>
        <div class="row g-3">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $members; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $member): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <?php
                    $c    = $cards->get($member->id);
                    $mine = $member->id === auth()->id();
                    $ini  = collect(explode(' ', $member->name))->filter()->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->take(2)->implode('');
                ?>
                <div class="col-md-4" <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'member-'.e($member->id).''; ?>wire:key="member-<?php echo e($member->id); ?>">
                    <div class="border rounded-3 p-3 h-100 <?php echo e($mine ? 'border-primary' : ''); ?>">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="avatar-sm"><?php echo e($ini); ?></span>
                            <div class="me-auto overflow-hidden">
                                <div class="fw-semibold text-navy text-truncate">
                                    <?php echo e($member->name); ?> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($mine): ?> <span class="pill pill-muted">You</span> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->secretary?->id === $member->id): ?> <span class="pill pill-ok" title="Records the Committee's decisions"><i class="bi bi-journal-check"></i> Secretary</span> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </div>
                                <div class="small text-muted text-truncate"><?php echo e($member->email); ?></div>
                            </div>
                        </div>

                        <dt class="detail-label">Conflict of interest</dt>
                        <div class="small mb-2">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($c?->coi): ?>
                                <?php [$coiL, $coiP] = $SC::COI[$c->coi]; ?>
                                <span class="pill <?php echo e($coiP); ?>"><?php echo e($coiL); ?></span>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($c->coi_details): ?> <span class="text-muted d-block mt-1"><?php echo e($c->coi_details); ?></span> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <?php else: ?>
                                <span class="pill pill-muted">Not declared</span>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>

                        <dt class="detail-label">Scorecard</dt>
                        <div class="d-flex flex-wrap gap-1 small">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($c?->isRecused()): ?>
                                <span class="pill pill-muted">Not scoring</span>
                            <?php elseif($c?->isSubmitted()): ?>
                                <span class="pill pill-ok"><i class="bi bi-check-lg"></i> Submitted <?php echo e($c->submitted_at->format('d M')); ?></span>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($revealed || $mine): ?>
                                    <span class="pill <?php echo e($c->scorePct() >= $SC::passPct() ? 'pill-ok' : 'pill-warn'); ?>" title="Total score"><?php echo e($c->totalScore()); ?> / <?php echo e($SC::maxScore()); ?> · <?php echo e($c->scorePct()); ?>%</span>
                                    <span class="pill <?php echo e($c->recommendation === 'approve' ? 'pill-ok' : 'pill-danger'); ?>">
                                        <i class="bi <?php echo e($c->recommendation === 'approve' ? 'bi-hand-thumbs-up' : 'bi-hand-thumbs-down'); ?>"></i> <?php echo e($SC::RECOMMENDATIONS[$c->recommendation] ?? ''); ?>

                                    </span>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <?php elseif($c): ?>
                                <span class="pill pill-warn"><i class="bi bi-pencil"></i> Draft</span>
                            <?php else: ?>
                                <span class="pill pill-muted">Not started</span>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($c?->edited_at): ?>
                                <span class="pill pill-muted" title="Changed by the Committee secretary"><i class="bi bi-pencil-square"></i> Edited by <?php echo e($c->editor?->name ?? 'the secretary'); ?></span>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>

                        
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canRecord): ?>
                            <div class="d-flex flex-wrap gap-2 mt-3 pt-2 border-top">
                                <button type="button" class="btn btn-sm <?php echo e($this->editingMemberId === $member->id ? 'btn-brand' : 'btn-outline-secondary'); ?>"
                                        wire:click="editMemberCard(<?php echo e($member->id); ?>)" wire:loading.attr="disabled" wire:target="editMemberCard(<?php echo e($member->id); ?>)"
                                        title="Correct the information this member entered">
                                    <i class="bi bi-pencil-square me-1"></i> <?php echo e($this->editingMemberId === $member->id ? 'Editing…' : 'Edit'); ?>

                                </button>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($c?->isSubmitted()): ?>
                                    <button type="button" class="btn btn-sm btn-outline-warning" wire:click="reopenMemberCard(<?php echo e($member->id); ?>)"
                                            wire:confirm="Reopen <?php echo e($member->name); ?>'s scorecard? It goes back to draft so they can change it and submit it again."
                                            title="Send it back to the member to change and resubmit">
                                        <i class="bi bi-arrow-counterclockwise me-1"></i> Reopen
                                    </button>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</section><?php /**PATH C:\laragon\www\co-investment-facility\resources\views/expression-of-interest/committee/partials/members.blade.php ENDPATH**/ ?>