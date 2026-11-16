<?php
use App\Models\CommitteeReview;
use App\Models\CommitteeScorecard;
use App\Models\DueDiligence;
use App\Models\EnterpriseDocument;
use App\Models\EnterpriseProfile;
use App\Models\ExpressionOfInterest;
use App\Notifications\EoiCommitteeDecisionRecorded;
use App\Notifications\EoiGate3Outcome;
use App\Notifications\EoiReadyForCommittee;
use App\Notifications\EoiScorecardsComplete;
use App\Support\NotifyRecipients;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
?>

<div>
<?php
    $CR        = \App\Models\CommitteeReview::class;
    $SC        = \App\Models\CommitteeScorecard::class;
    $DD        = \App\Models\DueDiligence::class;
    $eoi       = $this->eoi;
    $review    = $this->review;
    $dd        = $this->dd;
    $members   = $this->members;
    $status    = $this->status;
    $revealed  = $this->revealed;
    $canScore  = $this->canScore;
    $canRecord = $this->canRecord;
    $isMember  = $this->isMember;
    $decided   = (bool) $review?->isDecided();
    $cards     = $review?->scorecards->keyBy('user_id') ?? collect();
    $myCard    = $this->myCard;

    // Secretary correcting a member's scorecard: the scorecard and pack comments show that member's card
    $editingOther = $this->canEditMember;
    $cardOwner    = $editingOther ? $members->firstWhere('id', $this->editingMemberId) : auth()->user();
    if ($editingOther) {
        $myCard   = $cards->get($this->editingMemberId);
        $canScore = true;
    }
    $blockers  = $this->isApplicant ? [] : $this->blockers;
    $history   = $this->isApplicant ? collect() : $this->history;
    $submission = $this->isApplicant || ! $this->gateOpen ? ['facts' => [], 'docs' => []] : $this->submission;
    $profile    = $this->isApplicant ? null : $this->profile;

    // Figures from Gate 2
    $ddForm    = $dd?->toForm() ?? [];
    $validated = $dd ? $DD::validated($ddForm) : ['total' => 0, 'private' => 0, 'grant' => 0, 'structure' => null];

    $rate = (float) config('cif.usd_to_lsl', 18.00);
    // Amounts are stored in Maloti: $m = US$ (main) · $u = Maloti equivalent
    $m    = fn ($v) => $v === null || $v === '' ? '—' : 'US$ '.number_format((float) $v / max($rate, 0.0001));
    $u    = fn ($v) => $v === null || $v === '' ? '' : '≈ M '.number_format((float) $v);

    // Gate 3 path
    $path = [
        ['Gate 2 passed', 'done'],
        ['TSS + Investment Appraisal Memorandum', $dd ? 'done' : 'todo'],
        ['Investment Committee review', $decided ? 'done' : 'current'],
        ['Approve / Conditional / Defer / Reject', $decided ? 'done' : 'todo'],
        ['Gate 4 · Grant readiness', $review?->isApproved() ? 'current' : 'todo'],
    ];
?>


<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! $this->gateOpen): ?>
    <section class="section-card">
        <div class="section-card-head">
            <span class="section-no">G3</span>
            <h5 class="me-auto">Investment Committee review &amp; recommendation</h5>
            <span class="pill pill-muted"><i class="bi bi-lock-fill"></i> Locked</span>
        </div>
        <p class="small text-muted mb-2">Only transactions that pass Gate 2 (Proceed) go to the Investment Committee. The Committee reviews:</p>
        <ul class="evidence-list small text-muted mb-0">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $CR::CRITERIA; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$cTitle, $cQ]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?> <li><strong class="text-navy"><?php echo e($cTitle); ?></strong> — <?php echo e($cQ); ?></li> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        </ul>
    </section>


<?php elseif($this->isApplicant): ?>
    <?php echo $__env->make('expression-of-interest.committee.partials.applicant', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>


<?php else: ?>
    <div class="g2-path mb-3">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $path; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$pLabel, $pState]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
            <span class="g2-path-step <?php echo e($pState !== 'todo' ? 'is-'.$pState : ''); ?>">
                <i class="bi <?php echo e(['done' => 'bi-check-lg', 'current' => 'bi-hourglass-split', 'todo' => 'bi-circle'][$pState]); ?>"></i> <?php echo e($pLabel); ?>

            </span>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
    </div>

    
    <section class="section-card">
        <div class="section-card-head">
            <span class="section-no">G3</span>
            <div class="me-auto">
                <h5>Investment Committee review &amp; recommendation <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($round > 1): ?> <span class="pill pill-muted ms-1">Round <?php echo e($round); ?></span> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></h5>
                <div class="small text-muted">Does the transaction, as presented, satisfy the Facility's requirements and justify the use of public grant resources?</div>
            </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($decided): ?>
                <?php [$oL, , , $oI, $oP] = $CR::OUTCOMES[$review->outcome]; ?>
                <span class="pill <?php echo e($oP); ?>"><i class="bi <?php echo e($oI); ?>"></i> <?php echo e($oL); ?></span>
            <?php elseif($eoi->status === 'committee'): ?>
                <span class="pill pill-warn"><i class="bi bi-hourglass-split"></i> Under review</span>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canRecord || $canScore): ?>
            <div class="small text-muted mb-3">
                <i class="bi bi-cloud-check me-1"></i> Changes are saved automatically.
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($savedAt): ?> <span class="text-accent">Last saved <?php echo e($savedAt); ?>.</span> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <span wire:loading class="ms-1"><span class="spinner-border spinner-border-sm"></span> Saving…</span>
            </div>
        <?php elseif(! $decided && ! $isMember && ! auth()->user()->can('gate3.decide')): ?>
            <div class="small text-muted mb-3"><i class="bi bi-eye me-1"></i> Read-only — Gate 3 is carried out by the Investment Committee.</div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! $decided && ! $this->secretary): ?>
            <div class="callout callout-warning small mb-3">
                <i class="bi bi-exclamation-triangle me-1"></i>
                No <strong>Committee secretary</strong> has been set, so nobody can record the Committee's decisions.
                In user management, open one of the Committee members and tick <em>Committee secretary</em>.
            </div>
        <?php elseif(! $decided && $isMember && ! $canRecord): ?>
            <div class="callout small mb-3">
                <i class="bi bi-journal-check me-1"></i>
                <strong><?php echo e($this->secretary->name); ?></strong> (Committee secretary) records the Committee's decisions —
                review criteria, deliberation, questions, conditions and the outcome. Your part is your conflict declaration, pack comments and scorecard.
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($review?->resubmission_note): ?>
            <div class="callout small mb-3">
                <div class="fw-semibold mb-1"><i class="bi bi-arrow-repeat me-1"></i> Resubmitted after deferral</div>
                <?php echo e($review->resubmission_note); ?>

            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <div class="row g-3">
            <div class="col-md-6">
                <dt class="detail-label">Enterprise</dt>
                <dd class="mb-0 text-navy"><?php echo e($eoi->business_name ?: '—'); ?> <span class="chip ms-1"><?php echo e($eoi->reference); ?></span></dd>
            </div>
            <div class="col-md-6">
                <dt class="detail-label">Committee secretary</dt>
                <dd class="mb-0 text-navy">
                    <?php echo e($decided ? ($review->secretary?->name ?? $this->secretary?->name ?? '—') : ($this->secretary?->name ?? 'Not set')); ?>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! $decided && $this->secretary?->id === auth()->id()): ?> <span class="pill pill-muted ms-1">You</span> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </dd>
            </div>
            <div class="col-md-3">
                <label class="detail-label" for="ic_ref">Meeting ref.</label>
                <input type="text" id="ic_ref" class="form-control form-control-sm" wire:model.blur="meeting.meeting_ref" maxlength="50" placeholder="IC-2026-11" <?php if(! $canRecord): echo 'disabled'; endif; ?>>
            </div>
            <div class="col-md-3">
                <label class="detail-label" for="ic_date">Meeting date</label>
                <input type="date" id="ic_date" class="form-control form-control-sm <?php $__errorArgs = ['meeting.meeting_date'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model.blur="meeting.meeting_date" <?php if(! $canRecord): echo 'disabled'; endif; ?>>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['meeting.meeting_date'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
            <div class="col-md-3">
                <label class="detail-label" for="ic_venue">Venue</label>
                <input type="text" id="ic_venue" class="form-control form-control-sm" wire:model.blur="meeting.venue" maxlength="250" placeholder="Boardroom / online" <?php if(! $canRecord): echo 'disabled'; endif; ?>>
            </div>
            <div class="col-md-3">
                <label class="detail-label" for="ic_pack">Pack circulated</label>
                <input type="date" id="ic_pack" class="form-control form-control-sm" wire:model.blur="meeting.pack_circulated_on" <?php if(! $canRecord): echo 'disabled'; endif; ?>>
            </div>
        </div>

        <div class="callout small mt-3 mb-0">
            <i class="bi bi-info-circle me-1"></i>
            The Committee relies on the Gate 1 and Gate 2 assessments — it does not re-perform eligibility or due diligence checks,
            value the business or replace the investor's commercial decision.
        </div>
    </section>

    <?php echo $__env->make('expression-of-interest.committee.partials.members', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->make('expression-of-interest.committee.partials.pack', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->make('expression-of-interest.committee.partials.submission', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->make('expression-of-interest.committee.partials.glance', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($editingOther || ($isMember && ($review === null || ! $decided || $myCard))): ?>
        <?php echo $__env->make('expression-of-interest.committee.partials.scorecard', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php echo $__env->make('expression-of-interest.committee.partials.consolidated', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->make('expression-of-interest.committee.partials.record', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->make('expression-of-interest.committee.partials.outcome', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div><?php /**PATH C:\laragon\www\co-investment-facility\storage\framework\views/livewire/views/efa7a98f.blade.php ENDPATH**/ ?>