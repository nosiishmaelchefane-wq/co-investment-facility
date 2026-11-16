<?php
use App\Models\EligibilityScreening;
use App\Models\EnterpriseDocument;
use App\Models\EnterpriseProfile;
use App\Models\ExpressionOfInterest;
use App\Notifications\EoiGate1Decision;
use App\Notifications\EoiReturnedForCorrection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
?>

<div>
<?php
    $G1         = \App\Models\EligibilityScreening::class;
    $eoi        = $this->eoi;
    $screening  = $this->screening;
    $stage      = $this->stage;
    $canEdit    = $this->canEdit;
    $checks     = $G1::COMPLETENESS;
    $areas      = $G1::AREAS;
    $criteria   = $G1::CRITERIA;
    $decisions  = $G1::DECISIONS;
    $outcomes   = $G1::OUTCOMES;
    $tally      = $G1::tally($answers);
    $cTally     = $G1::tally($completeness, array_keys($checks));
    $recommend  = $G1::recommend($answers);
    $decided    = (bool) $screening?->isDecided();
    $outcome    = $decided ? $screening->decision : null;
    $returnedIncomplete = $outcome === $G1::DECISION_INCOMPLETE;
    $reachedScreening   = $stage === $G1::STAGE_SCREENING || ($decided && ! $returnedIncomplete);
    $editStage1 = $canEdit && $stage === $G1::STAGE_COMPLETENESS;
    $editStage2 = $canEdit && $stage === $G1::STAGE_SCREENING;
    $submitted  = in_array($eoi->status, ['submitted', 'in_review'], true);
    $evidence   = $this->isApplicant || ! $reachedScreening ? [] : $this->evidence;
    $profile    = $this->isApplicant ? null : $this->profile;

    // Flow tracker: [label, section, state: done | current | returned | failed | todo]
    $flow = [
        ['Completeness review', '§3.2.1', match (true) {
            $returnedIncomplete      => 'returned',
            $reachedScreening        => 'done',
            $screening || $submitted => 'current',
            default                  => 'todo',
        }],
        ['Eligibility screening', '§3.2.2', match (true) {
            $decided && ! $returnedIncomplete => 'done',
            $stage === $G1::STAGE_SCREENING   => 'current',
            default                           => 'todo',
        }],
        ['Gate 1 decision', '§3.2.3', match ($outcome) {
            $G1::DECISION_ELIGIBLE      => 'done',
            $G1::DECISION_CLARIFICATION => 'returned',
            $G1::DECISION_NOT_ELIGIBLE  => 'failed',
            default                     => 'todo',
        }],
    ];
    $flowIcon = ['done' => 'bi-check-lg', 'current' => 'bi-hourglass-split', 'returned' => 'bi-arrow-return-left', 'failed' => 'bi-x-lg'];
?>


<div class="g1-flow mb-3">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $flow; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => [$fLabel, $fSec, $fState]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
        <div class="g1-flow-step is-<?php echo e($fState); ?>">
            <span class="g1-flow-dot"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($fState === 'todo'): ?> <?php echo e($i + 1); ?> <?php else: ?> <i class="bi <?php echo e($flowIcon[$fState]); ?>"></i> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></span>
            <div>
                <div class="g1-flow-label"><?php echo e($fLabel); ?></div>
                <div class="g1-flow-sub"><?php echo e($fSec); ?></div>
            </div>
        </div>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
</div>


<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->isApplicant): ?>
    <section class="section-card">
        <div class="section-card-head">
            <span class="section-no">G1</span>
            <h5 class="me-auto">Eligibility screening</h5>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($decided): ?>
                <span class="pill <?php echo e($outcomes[$outcome][3]); ?>"><i class="bi <?php echo e($outcomes[$outcome][2]); ?>"></i> <?php echo e($outcomes[$outcome][0]); ?></span>
            <?php else: ?>
                <span class="pill pill-warn"><i class="bi bi-hourglass-split"></i> In progress</span>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! $decided): ?>
            <p class="small text-muted mb-0">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($stage === $G1::STAGE_SCREENING): ?>
                    Your application is complete and is being checked against the Facility's eligibility criteria. You'll be notified of the outcome.
                <?php else: ?>
                    The LEHSFF team is checking that your application, declarations and supporting documents are complete.
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </p>

        <?php elseif($screening->isReturned()): ?>
            <?php $items = $screening->outstandingItems(); ?>
            <div class="callout callout-warning small mb-3">
                <i class="bi bi-arrow-return-left me-1"></i>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($returnedIncomplete): ?>
                    Your application is missing some information. Please complete the items below and resubmit.
                <?php else: ?>
                    The team needs more information before an eligibility determination can be made. Please respond to the items below and resubmit.
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($items): ?>
                <dt class="detail-label mb-2">Requiring your attention</dt>
                <ul class="checklist mb-3">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <li class="is-pending"><i class="bi bi-question-circle"></i><div><div class="fw-semibold text-navy"><?php echo e($item['title']); ?></div><div class="small text-muted"><?php echo e($item['note']); ?></div></div></li>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </ul>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($screening->comments): ?>
                <div class="quote mb-3"><?php echo e($screening->comments); ?><div class="quote-meta">Comments from the LEHSFF team · <?php echo e($screening->decided_at->format('d M Y')); ?></div></div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($eoi->status === 'returned'): ?>
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-brand btn-sm" wire:click="$dispatch('edit-application', { id: <?php echo e($eoi->id); ?> })">
                        <i class="bi bi-pencil-square me-1"></i> Update &amp; resubmit EOI
                    </button>
                    <a href="<?php echo e(route('profile.index')); ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-building me-1"></i> Update enterprise profile</a>
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <?php elseif($outcome === $G1::DECISION_ELIGIBLE): ?>
            <div class="callout callout-success small mb-2">
                <i class="bi bi-check2-circle me-1"></i>
                Your application has passed eligibility screening and progresses to <strong>Due Diligence and Investment Appraisal</strong>.
            </div>
            <p class="small text-muted mb-0">Passing Gate 1 means the transaction meets the Facility's fundamental eligibility requirements — it is not approval of the CI grant.</p>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($screening->comments): ?>
                <div class="quote mt-3"><?php echo e($screening->comments); ?><div class="quote-meta">Comments from the LEHSFF team</div></div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <?php else: ?>
            <p class="small text-muted">Decision recorded on <?php echo e($screening->decided_at->format('d M Y')); ?>. Your application will not progress further.</p>
            <?php $failed = $screening->failedItems(); ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($failed): ?>
                <dt class="detail-label mb-2">Basis for the decision</dt>
                <ul class="checklist mb-3">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $failed; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <li class="is-fail"><i class="bi bi-x-circle"></i><div><div class="fw-semibold text-navy"><?php echo e($item['title']); ?></div><div class="small text-muted"><?php echo e($item['note']); ?></div></div></li>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </ul>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($screening->comments): ?>
                <div class="quote"><?php echo e($screening->comments); ?><div class="quote-meta">Comments from the LEHSFF team</div></div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </section>


<?php else: ?>
    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! $screening && ! $submitted): ?>
        <div class="callout small mb-3">
            <i class="bi bi-info-circle me-1"></i>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($eoi->status === 'draft'): ?> This EOI hasn't been submitted yet, so it can't be screened.
            <?php elseif($eoi->status === 'withdrawn'): ?> This EOI was withdrawn by the applicant.
            <?php elseif($eoi->status === 'returned'): ?> Returned to the applicant — waiting for them to resubmit.
            <?php else: ?> No screening is open for this EOI.
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    
    <section class="section-card">
        <div class="section-card-head">
            <span class="section-no">G1</span>
            <div class="me-auto">
                <h5>Administrative review &amp; eligibility screening <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($round > 1): ?> <span class="pill pill-muted ms-1">Round <?php echo e($round); ?></span> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></h5>
                <div class="small text-muted">Is there a sufficient basis for the transaction to proceed to detailed assessment?</div>
            </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($decided): ?>
                <span class="pill <?php echo e($outcomes[$outcome][3]); ?>"><i class="bi <?php echo e($outcomes[$outcome][2]); ?>"></i> <?php echo e($outcomes[$outcome][0]); ?></span>
            <?php elseif($screening): ?>
                <span class="pill pill-warn"><i class="bi bi-hourglass-split"></i> <?php echo e($stage === $G1::STAGE_SCREENING ? 'Screening' : 'Completeness review'); ?></span>
            <?php else: ?>
                <span class="pill pill-muted"><i class="bi bi-circle"></i> Not started</span>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
        <div class="detail-grid">
            <div><dt>Enterprise</dt><dd><?php echo e($eoi->business_name ?: '—'); ?></dd></div>
            <div><dt>Transaction ID</dt><dd><span class="chip"><?php echo e($eoi->reference); ?></span></dd></div>
            <div><dt>Screened by</dt><dd><?php echo e($screening?->screener?->name ?? ($canEdit ? auth()->user()->name : '—')); ?></dd></div>
            <div><dt>Date</dt><dd><?php echo e($screening?->screened_on?->format('d M Y') ?? '—'); ?></dd></div>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2 small mt-3">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($profile): ?>
                <span class="text-muted"><i class="bi bi-building me-1"></i> Enterprise profile <?php echo e($profile->completeness()); ?>% complete (<?php echo e($profile->completedCount()); ?>/<?php echo e(count(\App\Models\EnterpriseProfile::SECTIONS)); ?> sections).</span>
            <?php else: ?>
                <span class="text-warning"><i class="bi bi-exclamation-triangle me-1"></i> The applicant hasn't started an enterprise profile — only EOI answers are available.</span>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->any(['eoi.view-all', 'users.manage'])): ?>
                <a href="<?php echo e(route('profile.index', $eoi->user_id)); ?>" target="_blank" class="ms-auto text-decoration-none">
                    <i class="bi bi-box-arrow-up-right me-1"></i> Open full enterprise profile
                </a>
            <?php endif; ?>
        </div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! $canEdit && ! $decided && $submitted && ! auth()->user()->can('gate1.decide')): ?>
            <div class="small text-muted mt-2"><i class="bi bi-eye me-1"></i> Read-only — screening is done by the PMU (permission <code>gate1.decide</code>).</div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </section>

    
    <?php
        $auto      = $decided ? [] : $this->auto;
        $hasRecord = collect($completeness)->contains(fn ($c) => $c['answer'] !== null);
    ?>
    <section class="section-card">
        <div class="section-card-head">
            <span class="section-no">1</span>
            <div class="me-auto">
                <h5>Administrative completeness review</h5>
                <div class="small text-muted">Required fields, declarations and supporting documents provided.</div>
            </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($returnedIncomplete): ?>
                <span class="pill pill-warn"><i class="bi bi-arrow-return-left"></i> Returned</span>
            <?php elseif($reachedScreening): ?>
                <span class="pill pill-ok"><i class="bi bi-check-lg"></i> Complete</span>
            <?php else: ?>
                <span class="pill pill-ok"><i class="bi bi-check-lg"></i> <?php echo e($cTally['yes']); ?> Met</span>
                <span class="pill pill-danger"><i class="bi bi-x-lg"></i> <?php echo e($cTally['no']); ?> Not met</span>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($screening?->completeness_confirmed_at): ?>
            <div class="small text-muted mb-3">
                <i class="bi bi-person-check me-1"></i> Confirmed complete by <?php echo e($screening->completenessConfirmer?->name ?? '—'); ?> on <?php echo e($screening->completeness_confirmed_at->format('d M Y H:i')); ?>.
            </div>
        <?php elseif($decided && ! $hasRecord): ?>
            <div class="small text-muted mb-0"><i class="bi bi-info-circle me-1"></i> No completeness record for this round (screened before the completeness review was introduced).</div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! $decided || $hasRecord): ?>
            <details class="g1-stage" <?php if(! $reachedScreening): ?> open <?php endif; ?>>
                <summary class="small fw-semibold text-brand">Completeness checks (<?php echo e(count($checks)); ?>)</summary>

                <div class="table-responsive mt-2">
                    <table class="table align-middle g1-check-table mb-0">
                        <thead>
                            <tr>
                                <th style="width: 32%">Completeness check</th>
                                <th>Platform check</th>
                                <th style="width: 34%">Officer confirmation</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $checks; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => [$cTitle, $cReq]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <?php
                                    $a = $auto[$key] ?? null;
                                    $c = $completeness[$key] ?? ['answer' => null, 'note' => ''];
                                ?>
                                <tr <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'chk-'.e($key).''; ?>wire:key="chk-<?php echo e($key); ?>" class="<?php echo e($c['answer'] === 'no' ? 'is-flagged' : ''); ?>">
                                    <td>
                                        <div class="fw-semibold text-navy small"><?php echo e($cTitle); ?></div>
                                        <div class="small text-muted lh-sm"><?php echo e($cReq); ?></div>
                                    </td>
                                    <td class="small">
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! $a): ?>
                                            <span class="text-muted">—</span>
                                        <?php elseif($a['met']): ?>
                                            <span class="pill pill-ok"><i class="bi bi-check-circle"></i> Complete</span>
                                        <?php else: ?>
                                            <span class="pill pill-warn"><i class="bi bi-exclamation-triangle"></i> <?php echo e(count($a['missing'])); ?> missing</span>
                                            <ul class="evidence-list mt-1 text-muted">
                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $a['missing']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $m): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                                    <li><?php echo e($m); ?></li>
                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                            </ul>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm mb-1" role="group" aria-label="<?php echo e($cTitle); ?>">
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = [['yes', 'Met', 'btn-outline-success', 'bi-check-lg'], ['no', 'Not met', 'btn-outline-danger', 'bi-x-lg']]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$v, $lbl, $cls, $ic]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                                <input type="radio" class="btn-check" name="chk-<?php echo e($key); ?>" id="chk-<?php echo e($key); ?>-<?php echo e($v); ?>" value="<?php echo e($v); ?>"
                                                       wire:model.live="completeness.<?php echo e($key); ?>.answer" <?php if(! $editStage1): echo 'disabled'; endif; ?>>
                                                <label class="btn <?php echo e($cls); ?>" for="chk-<?php echo e($key); ?>-<?php echo e($v); ?>"><i class="bi <?php echo e($ic); ?> me-1"></i><?php echo e($lbl); ?></label>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                        </div>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($editStage1): ?>
                                            <input type="text" wire:model.blur="completeness.<?php echo e($key); ?>.note"
                                                   placeholder="<?php echo e($c['answer'] === 'no' ? 'What the applicant must provide' : 'Note (optional)'); ?>"
                                                   class="form-control form-control-sm <?php $__errorArgs = ["completeness.$key.note"];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>">
                                        <?php elseif($c['note']): ?>
                                            <div class="small text-muted"><?php echo e($c['note']); ?></div>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ["completeness.$key.answer"];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-danger small mt-1"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ["completeness.$key.note"];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-danger small mt-1"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </td>
                                </tr>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['completeness'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-danger small mt-2"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($editStage1): ?>
                    <div class="mt-3">
                        <label class="form-label" for="g1_return_msg">Message to the applicant <span class="text-muted small">(e-mailed to the applicant with the outstanding items if returned)</span></label>
                        <textarea id="g1_return_msg" rows="2" wire:model.blur="comments" class="form-control <?php $__errorArgs = ['comments'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>"
                                  placeholder="Optional"></textarea>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['comments'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>

                    <div class="d-flex flex-wrap align-items-center gap-2 mt-3 pt-3 border-top">
                        <button type="button" class="btn btn-link text-decoration-none text-secondary px-0 me-2" wire:click="rerunAutoCheck" wire:loading.attr="disabled">
                            <i class="bi bi-arrow-repeat me-1"></i> Re-run automatic check
                        </button>
                        <button type="button" class="btn btn-link text-decoration-none text-secondary px-0" wire:click="saveDraft" wire:loading.attr="disabled">
                            <i class="bi bi-save me-1"></i> Save draft
                        </button>
                        <button type="button" class="btn btn-outline-warning ms-auto" wire:click="returnIncomplete" wire:loading.attr="disabled" wire:target="returnIncomplete"
                                wire:confirm="Return the application to the applicant with the items marked 'Not met'? They will be e-mailed the list and your message.">
                            <span wire:loading.remove wire:target="returnIncomplete"><i class="bi bi-arrow-return-left me-1"></i> Return for correction</span>
                            <span wire:loading wire:target="returnIncomplete"><span class="spinner-border spinner-border-sm me-1"></span> Returning…</span>
                        </button>
                        <button type="button" class="btn btn-brand" wire:click="confirmComplete" wire:loading.attr="disabled" wire:target="confirmComplete">
                            <span wire:loading.remove wire:target="confirmComplete"><i class="bi bi-check2-all me-1"></i> Confirm complete &amp; start screening</span>
                            <span wire:loading wire:target="confirmComplete"><span class="spinner-border spinner-border-sm me-1"></span> Confirming…</span>
                        </button>
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </details>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </section>

    
    <section class="section-card">
        <div class="section-card-head">
            <span class="section-no">2</span>
            <div class="me-auto">
                <h5>Eligibility criteria</h5>
                <div class="small text-muted">Confirm each criterion against the EOI, enterprise profile and documents.</div>
            </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($reachedScreening): ?>
                <span class="pill pill-ok"><i class="bi bi-check-lg"></i> <?php echo e($tally['yes']); ?> Yes</span>
                <span class="pill pill-danger"><i class="bi bi-x-lg"></i> <?php echo e($tally['no']); ?> No</span>
                <span class="pill pill-warn"><i class="bi bi-question-lg"></i> <?php echo e($tally['clarify']); ?> Clarification</span>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($tally['unanswered']): ?> <span class="pill pill-muted"><?php echo e($tally['unanswered']); ?> open</span> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! $reachedScreening): ?>
            <div class="g1-locked">
                <i class="bi bi-lock"></i>
                <div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($returnedIncomplete): ?>
                        Not reached — the application was returned at the completeness review.
                    <?php else: ?>
                        Screening opens once the application is confirmed complete.
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <?php $n = 0; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $areas; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $areaKey => [$areaTitle, $areaQuestion, $areaIcon]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <div class="g1-area" <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'area-'.e($areaKey).''; ?>wire:key="area-<?php echo e($areaKey); ?>">
                    <div class="g1-area-head">
                        <i class="bi <?php echo e($areaIcon); ?>"></i>
                        <div>
                            <div class="g1-area-title"><?php echo e($areaTitle); ?></div>
                            <div class="small text-muted"><?php echo e($areaQuestion); ?></div>
                        </div>
                    </div>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $G1::criteriaFor($areaKey); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => [$title, $desc]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <?php
                            $n++;
                            $ans    = $answers[$key]['answer'] ?? null;
                            $border = ['no' => ' border-danger', 'clarify' => ' border-warning'][$ans] ?? '';
                            $ph     = ['no' => 'Reason it is not met', 'clarify' => 'What needs to be clarified?'][$ans] ?? 'Evidence / note (optional)';
                        ?>
                        <div class="border rounded-3 p-3 mb-2<?php echo e($border); ?>" <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'crit-'.e($key).''; ?>wire:key="crit-<?php echo e($key); ?>">
                            <div class="d-flex gap-3">
                                <span class="section-no flex-shrink-0"><?php echo e($n); ?></span>
                                <div class="flex-grow-1 min-w-0">
                                    <div class="fw-semibold text-navy"><?php echo e($title); ?></div>
                                    <div class="small text-muted mb-2"><?php echo e($desc); ?></div>
                                    <div class="d-flex flex-wrap align-items-center gap-2">
                                        <div class="btn-group btn-group-sm flex-shrink-0" role="group" aria-label="<?php echo e($title); ?>">
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = [['yes', 'Yes', 'btn-outline-success', 'bi-check-lg'], ['no', 'No', 'btn-outline-danger', 'bi-x-lg'], ['clarify', 'Clarification', 'btn-outline-warning', 'bi-question-lg']]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$v, $lbl, $cls, $ic]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                                <input type="radio" class="btn-check" name="crit-<?php echo e($key); ?>" id="c-<?php echo e($key); ?>-<?php echo e($v); ?>" value="<?php echo e($v); ?>"
                                                       wire:model.live="answers.<?php echo e($key); ?>.answer" <?php if(! $editStage2): echo 'disabled'; endif; ?>>
                                                <label class="btn <?php echo e($cls); ?>" for="c-<?php echo e($key); ?>-<?php echo e($v); ?>"><i class="bi <?php echo e($ic); ?> me-1"></i><?php echo e($lbl); ?></label>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                        </div>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($editStage2): ?>
                                            <input type="text" wire:model.blur="answers.<?php echo e($key); ?>.note" placeholder="<?php echo e($ph); ?>"
                                                   class="form-control form-control-sm flex-grow-1 <?php $__errorArgs = ["answers.$key.note"];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" style="min-width: 220px; width: auto;">
                                        <?php elseif($answers[$key]['note'] ?? ''): ?>
                                            <span class="small text-muted"><i class="bi bi-chat-left-text me-1"></i><?php echo e($answers[$key]['note']); ?></span>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </div>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ["answers.$key.answer"];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-danger small mt-1"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ["answers.$key.note"];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-danger small mt-1"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                                    
                                    <?php
                                        $ev      = $evidence[$key] ?? ['rows' => [], 'docs' => []];
                                        $missing = collect($ev['rows'])->filter(fn ($r) => blank($r[1]))->count()
                                                 + collect($ev['docs'])->filter(fn ($d) => ! $d[1] && $d[2])->count();   // optional documents don't count
                                    ?>
                                    <details class="evidence mt-3" <?php if($editStage2): ?> open <?php endif; ?>>
                                        <summary>
                                            <i class="bi bi-file-earmark-text me-1"></i> Submitted information
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($missing): ?> <span class="pill pill-warn ms-2"><?php echo e($missing); ?> not provided</span> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
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
                                                                    <li class="<?php echo e($item[1] ? 'is-yes' : 'is-no'); ?>"><i class="bi <?php echo e($item[1] ? 'bi-check-circle-fill' : 'bi-x-circle'); ?>"></i> <?php echo e($item[0]); ?></li>
                                                                <?php else: ?>
                                                                    <li><?php echo e($item); ?></li>
                                                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                                        </ul>
                                                    <?php else: ?>
                                                        <span class="evidence-value"><?php echo e($rValue); ?></span>
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
                                </div>
                            </div>
                        </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </section>

    
    <section class="section-card">
        <div class="section-card-head">
            <span class="section-no">3</span>
            <div class="me-auto">
                <h5>Gate 1 decision</h5>
                <div class="small text-muted">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($decided && ! $returnedIncomplete): ?>
                        Recorded by <?php echo e($screening->screener?->name ?? '—'); ?> on <?php echo e($screening->decided_at->format('d M Y H:i')); ?>.
                    <?php elseif(! $reachedScreening): ?>
                        Recorded after eligibility screening.
                    <?php elseif($recommend): ?>
                        Based on the answers, the recommended outcome is <strong><?php echo e($decisions[$recommend][0]); ?></strong>.
                    <?php else: ?>
                        Answer all <?php echo e(count($criteria)); ?> criteria to see the recommended outcome.
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>
        </div>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($returnedIncomplete): ?>
            <div class="g1-locked">
                <i class="bi bi-arrow-return-left"></i>
                <div>
                    Returned for correction on <?php echo e($screening->decided_at->format('d M Y')); ?> by <?php echo e($screening->screener?->name ?? '—'); ?>.
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($screening->comments): ?> <div class="mt-1">“<?php echo e($screening->comments); ?>”</div> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>
        <?php elseif(! $reachedScreening): ?>
            <div class="g1-locked"><i class="bi bi-lock"></i><div>The decision opens once eligibility screening has started.</div></div>
        <?php else: ?>
            <div class="choice-group cols-3 <?php $__errorArgs = ['decision'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $decisions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => [$dLabel, $dDesc, $dIcon]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <label class="choice choice--card">
                        <input type="radio" name="g1-decision" wire:model.live="decision" value="<?php echo e($value); ?>" <?php if(! $editStage2): echo 'disabled'; endif; ?>>
                        <span class="choice-box">
                            <span class="choice-icon"><i class="bi <?php echo e($dIcon); ?>"></i></span>
                            <span>
                                <span class="d-block fw-semibold"><?php echo e($dLabel); ?> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($recommend === $value && ! $decided): ?> <span class="pill pill-muted ms-1">Suggested</span> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></span>
                                <span class="d-block small text-muted lh-sm mt-1"><?php echo e($dDesc); ?></span>
                            </span>
                        </span>
                    </label>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['decision'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-danger small mt-1"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <div class="small text-muted mt-2">
                <i class="bi bi-info-circle me-1"></i> A Pass means the transaction has passed the fundamental eligibility filter — not that it has been approved for the CI grant.
            </div>

            <?php
                $commentLabel = match ($decision) {
                    $G1::DECISION_NOT_ELIGIBLE  => 'Basis for the decision',
                    $G1::DECISION_CLARIFICATION => 'Information required from the applicant',
                    default                     => 'Key comments / actions',
                };
            ?>
            <div class="mt-3">
                <label class="form-label" for="g1_comments"><?php echo e($commentLabel); ?> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($decision && $decision !== 'eligible' && $editStage2): ?> <span class="req">*</span> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></label>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($editStage2): ?>
                    <textarea id="g1_comments" rows="3" wire:model.blur="comments" class="form-control <?php $__errorArgs = ['comments'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>"
                              placeholder="<?php echo e($decision === 'not_eligible' ? 'The applicant is notified of this.' : 'What the applicant needs to know or do next.'); ?>"></textarea>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['comments'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php else: ?>
                    <div class="quote"><?php echo e($comments ?: 'No comments.'); ?></div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            <div class="row g-3 mt-1">
                <div class="col-md-6">
                    <label class="form-label" for="g1_officer">Screening officer</label>
                    <input type="text" id="g1_officer" class="form-control bg-light" readonly
                           value="<?php echo e($screening?->screener?->name ?? ($canEdit ? auth()->user()->name : '—')); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="g1_date">Date</label>
                    <input type="date" id="g1_date" wire:model="screenedOn" max="<?php echo e(now()->toDateString()); ?>" <?php if(! $editStage2): echo 'disabled'; endif; ?>
                           class="form-control <?php $__errorArgs = ['screenedOn'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['screenedOn'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($editStage2): ?>
                <div class="d-flex flex-wrap align-items-center gap-2 mt-4 pt-3 border-top">
                    <button type="button" class="btn btn-link text-decoration-none text-secondary px-0 me-2" wire:click="backToCompleteness"
                            wire:confirm="Re-open the completeness review? Your criteria answers are kept.">
                        <i class="bi bi-arrow-left me-1"></i> Back to completeness
                    </button>
                    <button type="button" class="btn btn-link text-decoration-none text-secondary px-0" wire:click="saveDraft" wire:loading.attr="disabled" wire:target="saveDraft">
                        <span wire:loading.remove wire:target="saveDraft"><i class="bi bi-save me-1"></i> Save draft</span>
                        <span wire:loading wire:target="saveDraft"><span class="spinner-border spinner-border-sm me-1"></span> Saving…</span>
                    </button>
                    <button type="button" class="btn btn-brand ms-auto" wire:click="recordDecision" wire:loading.attr="disabled" wire:target="recordDecision"
                            wire:confirm="Record the Gate 1 decision? It can't be changed afterwards, and the applicant will be e-mailed the outcome.">
                        <span wire:loading.remove wire:target="recordDecision"><i class="bi bi-send me-1"></i> Record Gate 1 decision</span>
                        <span wire:loading wire:target="recordDecision"><span class="spinner-border spinner-border-sm me-1"></span> Recording…</span>
                    </button>
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </section>

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->history->isNotEmpty()): ?>
        <section class="section-card">
            <div class="section-card-head">
                <span class="section-no"><i class="bi bi-clock-history"></i></span>
                <h5>Earlier screening rounds</h5>
            </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $this->history; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $h): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <?php
                    $ht    = $G1::tally($h->answers ?? []);
                    $items = $h->outstandingItems() ?: $h->failedItems();
                ?>
                <div class="file-row align-items-start">
                    <span class="file-row-icon"><i class="bi <?php echo e($outcomes[$h->decision][2] ?? 'bi-circle'); ?>"></i></span>
                    <div class="flex-grow-1 small">
                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            <strong class="text-navy">Round <?php echo e($h->round); ?></strong>
                            <span class="pill <?php echo e($outcomes[$h->decision][3] ?? 'pill-muted'); ?>"><?php echo e($outcomes[$h->decision][0] ?? $h->decision); ?></span>
                            <span class="text-muted"><?php echo e($h->decided_at->format('d M Y')); ?> · <?php echo e($h->screener?->name); ?></span>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($h->decision !== $G1::DECISION_INCOMPLETE): ?>
                                <span class="text-muted ms-auto"><?php echo e($ht['yes']); ?> yes · <?php echo e($ht['no']); ?> no · <?php echo e($ht['clarify']); ?> clarify</span>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($items): ?>
                            <ul class="evidence-list text-muted mt-1">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $it): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                    <li><strong><?php echo e($it['title']); ?></strong><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($it['note']): ?> — <?php echo e($it['note']); ?><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></li>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                            </ul>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($h->comments): ?> <div class="text-muted mt-1"><?php echo e($h->comments); ?></div> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        </section>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div><?php /**PATH C:\laragon\www\co-investment-facility\storage\framework\views/livewire/views/a776dc7d.blade.php ENDPATH**/ ?>