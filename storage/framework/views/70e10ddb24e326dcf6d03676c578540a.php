
<?php
    $pack        = $this->pack;
    $g2Url       = fn ($anchor) => route('expression-of-interest.show', $eoi->id).'?tab=g2'.($anchor ? '#'.$anchor : '');
    $iCanComment = $canScore && ($card['coi'] ?? null) !== 'recused';
    $included    = collect($pack)->filter(fn ($p) => $p['present'])->count();
    $myComments  = collect($card['pack_comments'] ?? [])->filter(fn ($t) => filled(trim((string) $t)))->count();
    $others      = $review?->scorecards->filter(fn ($c) => $c->isSubmitted() && ! $c->isRecused()) ?? collect();
?>
<section class="section-card" id="g3-pack">
    <div class="section-card-head">
        <span class="section-no"><i class="bi bi-folder2-open"></i></span>
        <div class="me-auto">
            <h5>Committee information pack</h5>
            <div class="small text-muted">
                From the Gate 2 assessment <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($dd): ?> (round <?php echo e($dd->round); ?>, decided <?php echo e($dd->decided_at->format('d M Y')); ?>) <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>.
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isMember): ?> Read each item and add your comment — comments are part of your scorecard. <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($dd): ?>
            <a href="<?php echo e(route('due-diligence.memorandum', $dd)); ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-file-earmark-pdf me-1"></i> Memorandum PDF</a>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <span class="pill <?php echo e($included === count($CR::PACK) ? 'pill-ok' : 'pill-warn'); ?>"><?php echo e($included); ?> / <?php echo e(count($CR::PACK)); ?> included</span>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isMember && ($card['coi'] ?? null) !== 'recused'): ?>
            <span class="pill <?php echo e($myComments === count($CR::PACK) ? 'pill-ok' : 'pill-warn'); ?>"><i class="bi bi-chat-left-text"></i> <?php echo e($myComments); ?> / <?php echo e(count($CR::PACK)); ?> commented</span>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! $dd): ?>
        <div class="callout callout-warning small mb-0"><i class="bi bi-exclamation-triangle me-1"></i> No completed Gate 2 assessment (Proceed) was found for this transaction.</div>
    <?php else: ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $CR::PACK; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => [$doc, $purpose, $icon, $anchor]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
            <?php
                $item     = $pack[$key] ?? ['present' => false, 'status' => null, 'lines' => [], 'link' => null];
                $mine     = trim((string) ($card['pack_comments'][$key] ?? ''));
                $comments = $revealed ? $others->filter(fn ($c) => filled($c->pack_comments[$key] ?? null)) : collect();
            ?>
            <details class="pack-item border rounded-3 mb-2 <?php echo e($key === 'iam' ? 'border-primary' : ''); ?>" <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'pack-'.e($key).''; ?>wire:key="pack-<?php echo e($key); ?>" <?php if($key === 'iam' || $errors->has("card.pack_comments.$key")): ?> open <?php endif; ?>>
                <summary class="d-flex flex-wrap align-items-center gap-2 p-3">
                    <span class="file-row-icon flex-shrink-0"><i class="bi <?php echo e($icon); ?>"></i></span>
                    <div class="flex-grow-1 small overflow-hidden" style="min-width: 200px">
                        <div class="fw-semibold text-navy">
                            <?php echo e($loop->iteration); ?>. <?php echo e($doc); ?>

                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(in_array($key, ['tss', 'iam'], true)): ?> <span class="pill pill-muted ms-1">Key document</span> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                        <div class="text-muted text-truncate"><?php echo e($purpose); ?></div>
                    </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($item['status']): ?> <span class="pill <?php echo e($item['status'][1]); ?>"><?php echo e($item['status'][0]); ?></span> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! $item['present']): ?> <span class="pill pill-danger">Missing</span> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isMember && ($card['coi'] ?? null) !== 'recused'): ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($mine !== ''): ?>
                            <span class="pill pill-ok" title="You have commented"><i class="bi bi-chat-left-text-fill"></i></span>
                        <?php else: ?>
                            <span class="pill pill-warn" title="Your comment is still needed"><i class="bi bi-chat-left"></i></span>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($revealed && $comments->isNotEmpty()): ?>
                        <span class="pill pill-muted" title="Members' comments"><i class="bi bi-people"></i> <?php echo e($comments->count()); ?></span>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </summary>

                <div class="px-3 pb-3">
                    
                    <dl class="evidence-grid mb-2">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $item['lines']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $line): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <dt><?php echo e($line[0]); ?></dt>
                            <dd>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! empty($line[2])): ?> <div class="small text-muted fst-italic mb-1"><?php echo e($line[2]); ?></div> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <span class="evidence-value" style="white-space: pre-line"><?php echo e($line[1]); ?></span>
                            </dd>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </dl>
                    <div class="d-flex flex-wrap gap-3 mb-2">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($item['link']): ?>
                            <a href="<?php echo e($item['link']); ?>" class="small text-decoration-none"><i class="bi bi-file-earmark-pdf me-1"></i>Download PDF</a>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($anchor): ?>
                            <a href="<?php echo e($g2Url($anchor)); ?>" target="_blank" class="small text-decoration-none"><i class="bi bi-box-arrow-up-right me-1"></i>Open in Gate 2</a>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>

                    
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($iCanComment): ?>
                        <label class="form-label small fw-semibold mb-1">
                            Your comment <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(\App\Models\CommitteeScorecard::PACK_COMMENTS_REQUIRED): ?> <span class="req">*</span> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </label>
                        <textarea rows="2" class="form-control form-control-sm <?php $__errorArgs = ["card.pack_comments.$key"];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>"
                                  wire:model.blur="card.pack_comments.<?php echo e($key); ?>" maxlength="2000"
                                  placeholder="Your view on this item — is it sufficient, and what concerns or questions do you have?"></textarea>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ["card.pack_comments.$key"];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php elseif($isMember && $mine !== '' && ! $revealed): ?>
                        <div class="quote small mb-0"><span class="text-muted">Your comment:</span> <?php echo e($mine); ?></div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                    
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($revealed): ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($comments->isNotEmpty()): ?>
                            <div class="mt-2">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $comments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                    <div class="small mb-1"><span class="fw-semibold text-navy"><?php echo e($c->user?->name); ?>:</span> <?php echo e($c->pack_comments[$key]); ?></div>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="small text-muted mt-1">No comments from the members.</div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php elseif(! $isMember): ?>
                        <div class="small text-muted mt-1"><i class="bi bi-lock me-1"></i> Members' comments are shown once every scorecard is in.</div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </details>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</section>

<?php if (! $__env->hasRenderedOnce('bc901ba6-076a-4d0f-a44a-11a0a30b9374')): $__env->markAsRenderedOnce('bc901ba6-076a-4d0f-a44a-11a0a30b9374'); ?>
    <style>
        .pack-item > summary { list-style: none; cursor: pointer; }
        .pack-item > summary::-webkit-details-marker { display: none; }
        .pack-item[open] > summary { border-bottom: 1px solid var(--bs-border-color, #e5e7eb); margin-bottom: .75rem; }
    </style>
<?php endif; ?><?php /**PATH C:\laragon\www\co-investment-facility\resources\views/expression-of-interest/committee/partials/pack.blade.php ENDPATH**/ ?>