<?php
use App\Models\Authorisation;
use App\Models\CommitteeReview;
use App\Models\DueDiligence;
use App\Models\EnterpriseProfile;
use App\Models\ExpressionOfInterest;
use App\Models\GrantReadiness;
use App\Notifications\EoiAuthorisationUpdate;
use App\Notifications\EoiGate4Outcome;
use App\Support\NotifyRecipients;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;
?>

<div>
<?php
    $A        = \App\Models\Authorisation::class;
    $CR       = \App\Models\CommitteeReview::class;
    $DD       = \App\Models\DueDiligence::class;
    $eoi      = $this->eoi;
    $a        = $this->auth;
    $review   = $this->review;
    $stage    = $this->stage;
    $decided  = (bool) $a?->isDecided();
    $outcome  = $a?->outcome;
    $canMd    = $this->canMd;
    $canPs    = $this->canPs;
    $p        = $this->isApplicant ? null : $this->profile;

    $rate = (float) config('cif.usd_to_lsl', 18.00);
    $m    = fn ($v) => $v === null || $v === '' ? '—' : 'M '.number_format((float) $v);
    $u    = fn ($v) => $v === null || $v === '' ? '' : 'US$ '.number_format((float) $v / max($rate, 0.0001));

    $private    = $a?->private_amount ?? $review?->private_amount;
    $grant      = $a?->grant_amount ?? $review?->grant_amount;
    $instrument = $a?->instrument ?? $this->dd?->instrument;
    $mdDone     = $a?->md_decided_at !== null;
    $psDone     = $a?->ps_decided_at !== null;
    $openConds  = collect($conditions)->where('status', 'open')->count();

    // Path: done | current | failed | returned | todo
    $mdState = match (true) {
        $mdDone && $a->md_decision === 'approve' => 'done',
        $mdDone && $a->md_decision === 'return'  => 'returned',
        $mdDone && $a->md_decision === 'decline' => 'failed',
        default                                  => 'current',
    };
    $psState = match (true) {
        $psDone && $a->ps_decision === 'authorise' => 'done',
        $psDone && $a->ps_decision === 'clarify'   => 'returned',
        $psDone && $a->ps_decision === 'decline'   => 'failed',
        $stage === 'ps'                            => 'current',
        default                                    => 'todo',
    };
    $path = [
        ['Gate 4 · Grant readiness', $this->readiness ? 'done' : ($this->readinessOk ? 'done' : 'current')],
        ['Managing Director signature', $mdState],
        ['Principal Secretary signature', $psState],
        ['Gate 6 · Conditions precedent & onboarding', $outcome === 'authorised' ? 'current' : 'todo'],
    ];
    $pathIcon = ['done' => 'bi-check-lg', 'current' => 'bi-hourglass-split', 'returned' => 'bi-arrow-return-left', 'failed' => 'bi-x-lg', 'todo' => 'bi-circle'];

    $headPill = match (true) {
        $decided            => [$A::OUTCOMES[$outcome][0], $A::OUTCOMES[$outcome][4], $A::OUTCOMES[$outcome][3]],
        ! $this->readinessOk => ['Waiting for Gate 4', 'pill-muted', 'bi-lock'],
        $stage === 'ps'     => ['Awaiting Principal Secretary signature', 'pill-warn', 'bi-hourglass-split'],
        default             => ['Awaiting Managing Director signature', 'pill-warn', 'bi-hourglass-split'],
    };
?>


<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! $this->gateOpen): ?>
    <section class="section-card">
        <div class="section-card-head">
            <span class="section-no">G5</span>
            <h5 class="me-auto">Final authorisation</h5>
            <span class="pill pill-muted"><i class="bi bi-lock-fill"></i> Locked</span>
        </div>
        <p class="small text-muted mb-0">Opens once Gate 4 · Grant readiness is approved by the PMU Director of Implementation.
            The Grant Agreement is then signed by the CAFI Managing Director and, after that, by the MTIBD Principal Secretary — both signatures constitute formal approval of the grant.</p>
    </section>


<?php elseif($this->isApplicant): ?>
    <section class="section-card">
        <div class="section-card-head">
            <span class="section-no">G5</span>
            <h5 class="me-auto">Final authorisation</h5>
            <span class="pill <?php echo e($decided ? $A::OUTCOMES[$outcome][4] : 'pill-warn'); ?>"><i class="bi <?php echo e($decided ? $A::OUTCOMES[$outcome][3] : 'bi-hourglass-split'); ?>"></i> <?php echo e($decided ? $A::OUTCOMES[$outcome][0] : 'In progress'); ?></span>
        </div>
        <p class="small text-muted mb-0">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! $decided): ?> Your Grant Agreement is with CAFI management for signature.
            <?php elseif($outcome === 'authorised'): ?> Your grant has been formally approved — the Grant Agreement has been signed by the CAFI Managing Director and the MTIBD Principal Secretary. The next step is meeting the conditions precedent and onboarding. Approval does not itself trigger payment.
            <?php elseif($outcome === 'clarification'): ?> Further information is being prepared before authorisation can be completed. The LEHSFF team will contact you if anything is needed from you.
            <?php else: ?> Your grant was not approved and will not proceed further.
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </p>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($outcome === 'authorised' && $openConds): ?>
            <dt class="detail-label mt-3 mb-2">Conditions still to be met</dt>
            <ul class="checklist mb-0">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = collect($conditions)->where('status', 'open'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <li class="is-pending"><i class="bi bi-exclamation-circle"></i>
                        <div><div class="fw-semibold text-navy"><?php echo e($c['finding']); ?></div><div class="small text-muted"><?php echo e($A::CONDITION_TYPES[$c['type'] ?? ''][1] ?? ''); ?></div></div></li>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </ul>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </section>


<?php else: ?>
    
    <div class="g2-path mb-3">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $path; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$pLabel, $pState]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
            <span class="g2-path-step <?php echo e(in_array($pState, ['done', 'current'], true) ? 'is-'.$pState : ''); ?> <?php echo e($pState === 'failed' ? 'text-danger' : ''); ?> <?php echo e($pState === 'returned' ? 'text-warning-emphasis' : ''); ?>">
                <i class="bi <?php echo e($pathIcon[$pState]); ?>"></i> <?php echo e($pLabel); ?>

            </span>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
    </div>

    
    <section class="section-card">
        <div class="section-card-head">
            <span class="section-no">G5</span>
            <div class="me-auto">
                <h5>Final authorisation <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($round > 1): ?> <span class="pill pill-muted ms-1">Round <?php echo e($round); ?></span> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></h5>
                <div class="small text-muted">Formal approval of the grant through the sequential signing of the Grant Agreement by the CAFI Managing Director and the MTIBD Principal Secretary.</div>
            </div>
            <span class="pill <?php echo e($headPill[1]); ?>"><i class="bi <?php echo e($headPill[2]); ?>"></i> <?php echo e($headPill[0]); ?></span>
        </div>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canMd || $canPs): ?>
            <div class="small text-muted mb-3">
                <i class="bi bi-cloud-check me-1"></i> Changes are saved automatically. <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($savedAt): ?> <span class="text-accent">Last saved <?php echo e($savedAt); ?>.</span> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <span wire:loading class="ms-1"><span class="spinner-border spinner-border-sm"></span></span>
            </div>
        <?php elseif(! $decided && $eoi->status === 'authorisation' && ! auth()->user()->canAny(['gate4.md', 'gate4.ps', 'gate2.assess'])): ?>
            <div class="small text-muted mb-3"><i class="bi bi-eye me-1"></i> Read-only — the PMU sends the Grant Agreement for signing and records the signed decisions.</div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! $this->readinessOk && ! $decided): ?>
            <div class="callout callout-warning small mb-3">
                <i class="bi bi-lock me-1"></i> Waiting for <strong>Gate 4 · Grant readiness</strong> — the Grant Readiness Memo and Draft Grant Agreement must be approved by the Hub Manager and the PMU Director of Implementation first.
                <a href="<?php echo e(route('expression-of-interest.show', $eoi->id)); ?>?tab=readiness" class="ms-1">Open Gate 4</a>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <div class="callout small mb-3">
            <i class="bi bi-envelope-paper me-1"></i>
            The Managing Director and the Principal Secretary sign the Grant Agreement outside the system: for each of them, <strong>1</strong> send them the agreement yourself
            (the Gate 4 Draft Grant Agreement is used for the MD, and the MD-signed copy for the PS, unless you attach another file), <strong>2</strong> upload the signed copy when it comes back,
            <strong>3</strong> record the decision written on it. The Principal Secretary's step opens once the Managing Director has signed.
            Recording a decision e-mails the PMU (and, on a final outcome, the applicant, the Committee and Finance).
        </div>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($a?->resubmission_note): ?>
            <div class="callout small mb-3">
                <div class="fw-semibold mb-1"><i class="bi bi-arrow-repeat me-1"></i> Resubmitted after clarification</div>
                <?php echo e($a->resubmission_note); ?>

            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <div class="detail-grid">
            <div><dt>Transaction reference</dt><dd><span class="chip"><?php echo e($eoi->reference); ?></span></dd></div>
            <div><dt>Enterprise</dt><dd><?php echo e($p?->registered_name ?: ($eoi->business_name ?: '—')); ?></dd></div>
            <div><dt>Investor(s)</dt><dd><?php echo e($this->dd?->investor_names ?: ($p?->investor_name ?: ($eoi->investor_name ?: '—'))); ?></dd></div>
            <div><dt>Gate 4 · readiness</dt>
                <dd><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->readiness): ?>
                        <span class="pill pill-ok"><i class="bi bi-check2-circle"></i> Approved to proceed</span>
                        <span class="small text-muted">· <?php echo e($this->readiness->decided_at->format('d M Y')); ?></span>
                    <?php elseif($this->readinessOk): ?> <span class="small text-muted">Not required (before Gate 4 was introduced)</span>
                    <?php else: ?> <span class="pill pill-warn">Pending</span> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></dd></div>
            <div><dt>Committee outcome</dt>
                <dd><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($review): ?>
                        <span class="pill <?php echo e($CR::OUTCOMES[$review->outcome][4]); ?>"><i class="bi <?php echo e($CR::OUTCOMES[$review->outcome][3]); ?>"></i> <?php echo e($CR::OUTCOMES[$review->outcome][0]); ?></span>
                        <span class="small text-muted">· <?php echo e($review->decided_at->format('d M Y')); ?></span>
                    <?php else: ?> — <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></dd></div>
            <div><dt>Approved private investment</dt><dd><?php echo e($m($private)); ?> <div class="small text-muted"><?php echo e($u($private)); ?></div></dd></div>
            <div><dt>Approved CAFI grant</dt><dd><?php echo e($m($grant)); ?> <div class="small text-muted"><?php echo e($u($grant)); ?></div></dd></div>
            <div><dt>Instrument</dt><dd><?php echo e($DD::INSTRUMENTS[$instrument ?? ''] ?? '—'); ?></dd></div>
            <div><dt>Outstanding actions</dt><dd><?php echo e($openConds ? $openConds.' open '.\Illuminate\Support\Str::plural('condition', $openConds) : 'None'); ?> <a href="#g4-conditions" class="small ms-1">See conditions</a></dd></div>
            <div><dt>Authorised next stage</dt><dd class="<?php echo e($outcome ? '' : 'text-faint'); ?>"><?php echo e($a?->nextStage() ?? 'Managing Director signature'); ?></dd></div>
        </div>

        <div class="callout small mt-3 mb-0">
            <i class="bi bi-info-circle me-1"></i>
            Gate 5 confirms institutional authorisation of a transaction already assessed (Gate 2), recommended (Gate 3) and prepared (Gate 4) — it does not reopen the technical assessment.
            Formal approval leads to conditions precedent and onboarding (Gate 6); it does <strong>not</strong> trigger payment.
        </div>
    </section>

    
    <?php
        $file     = $this->file;
        $included = collect($file)->filter(fn ($f) => $f[3])->count();
    ?>
    <section class="section-card">
        <div class="section-card-head">
            <span class="section-no"><i class="bi bi-folder2-open"></i></span>
            <div class="me-auto">
                <h5>Transaction file</h5>
                <div class="small text-muted">What the signing authorities receive: the Grant Readiness Memo, the Draft Grant Agreement, the Committee recommendation and supporting documents.</div>
            </div>
            <span class="pill <?php echo e($included === count($file) ? 'pill-ok' : 'pill-warn'); ?>"><?php echo e($included); ?> / <?php echo e(count($file)); ?> included</span>
        </div>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $file; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => [$doc, $purpose, $icon, $present, $links]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
            <div class="file-row flex-wrap" <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'file-'.e($key).''; ?>wire:key="file-<?php echo e($key); ?>">
                <span class="file-row-icon"><i class="bi <?php echo e($icon); ?>"></i></span>
                <div class="flex-grow-1 small overflow-hidden" style="min-width: 200px">
                    <div class="fw-semibold text-navy"><?php echo e($doc); ?> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(in_array($key, ['readiness_memo', 'agreement', 'recommendation'], true)): ?> <span class="pill pill-muted ms-1">Key input</span> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                    <div class="text-muted text-truncate"><?php echo e($purpose); ?> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($key === 'conditions'): ?> · <?php echo e($openConds); ?> open <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($present): ?>
                    <span class="pill pill-ok"><i class="bi bi-check-lg"></i> Included</span>
                <?php else: ?>
                    <span class="pill pill-danger">Missing</span>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $links; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$lLabel, $lUrl]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <a href="<?php echo e($lUrl); ?>" <?php if(! \Illuminate\Support\Str::startsWith($lUrl, '#')): ?> target="_blank" <?php endif; ?> class="btn btn-sm btn-outline-secondary">
                        <i class="bi <?php echo e(\Illuminate\Support\Str::contains($lUrl, 'download') || $lLabel === 'Download' ? 'bi-download' : (\Illuminate\Support\Str::startsWith($lUrl, '#') ? 'bi-arrow-down' : 'bi-box-arrow-up-right')); ?> me-1"></i><?php echo e($lLabel); ?>

                    </a>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
    </section>

    
    <section class="section-card">
        <div class="section-card-head">
            <span class="section-no">1</span>
            <div class="me-auto">
                <h5>CAFI Managing Director review and signature</h5>
                <div class="small text-muted">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($mdDone): ?> Signed by <?php echo e($a->signerName('md')); ?> on <?php echo e($a->md_on?->format('d M Y')); ?> · recorded by <?php echo e($a->managingDirector?->name ?? '—'); ?> on <?php echo e($a->md_decided_at->format('d M Y H:i')); ?>.
                    <?php else: ?> Send the Grant Agreement for signing, upload the signed copy, then record what the Managing Director decided. <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($mdDone): ?>
                <?php [$mdL, , $mdI] = $A::MD_DECISIONS[$a->md_decision]; ?>
                <span class="pill <?php echo e(['approve' => 'pill-ok', 'return' => 'pill-warn', 'decline' => 'pill-danger'][$a->md_decision]); ?>"><i class="bi <?php echo e($mdI); ?>"></i> <?php echo e($mdL); ?></span>
            <?php else: ?>
                <span class="pill pill-warn"><i class="bi bi-hourglass-split"></i> In review</span>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>

        <?php echo $__env->make('expression-of-interest.authorisation.partials.signing', ['role' => 'md', 'a' => $a, 'can' => $canMd, 'signing' => $signing, 'decidedRole' => $mdDone, 'defaultDoc' => $canMd ? $this->defaultDocument('md', $a) : null], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canMd && ! $a?->hasSignedCopy('md')): ?>
            <div class="small text-muted mb-2"><i class="bi bi-info-circle me-1"></i> You can fill in step 3 while waiting; the decision can only be recorded once the signed copy is uploaded.</div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <div class="d-flex align-items-center gap-2 mb-2">
            <span class="pill <?php echo e($mdDone ? 'pill-ok' : 'pill-warn'); ?>">3</span>
            <span class="fw-semibold text-navy small">Record the Managing Director's decision, as written on the signed document</span>
        </div>

        <dt class="detail-label mb-2">The review confirms that</dt>
        <ul class="checklist mb-3">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $A::MD_CHECKS; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ck => $ctext): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <?php $ok = $md['checks'][$ck] ?? false; ?>
                <li class="<?php echo e($ok ? 'is-done' : 'is-pending'); ?>" <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'mdc-'.e($ck).''; ?>wire:key="mdc-<?php echo e($ck); ?>">
                    <label class="d-flex align-items-start gap-2 mb-0 w-100">
                        <input type="checkbox" class="form-check-input mt-1" wire:model.live="md.checks.<?php echo e($ck); ?>" <?php if(! $canMd): echo 'disabled'; endif; ?>>
                        <span><?php echo e($ctext); ?></span>
                    </label>
                </li>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        </ul>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canMd): ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->blockers): ?>
                <div class="callout callout-warning small mb-3">
                    <div class="fw-semibold mb-1"><i class="bi bi-list-check me-1"></i> Before the Managing Director can sign (<?php echo e(count($this->blockers)); ?>)</div>
                    <ul class="evidence-list mb-0"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $this->blockers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $b): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?> <li><?php echo e($b); ?></li> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?></ul>
                </div>
            <?php else: ?>
                <div class="callout callout-success small mb-3"><i class="bi bi-check2-circle me-1"></i> Ready to sign and forward to the Principal Secretary.</div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <label class="form-label small fw-semibold">Decision <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canMd): ?> <span class="req">*</span> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></label>
        <div class="choice-group cols-3 <?php $__errorArgs = ['md.decision'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $A::MD_DECISIONS; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $v => [$dLabel, $dDesc, $dIcon]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <label class="choice choice--card">
                    <input type="radio" name="md-decision" value="<?php echo e($v); ?>" wire:model.live="md.decision" <?php if(! $canMd): echo 'disabled'; endif; ?>>
                    <span class="choice-box">
                        <span class="choice-icon"><i class="bi <?php echo e($dIcon); ?>"></i></span>
                        <span><span class="d-block fw-semibold"><?php echo e($dLabel); ?></span><span class="d-block small text-muted lh-sm mt-1"><?php echo e($dDesc); ?></span></span>
                    </span>
                </label>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        </div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['md.decision'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-danger small mt-1"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <div class="row g-3 mt-1">
            <div class="col-12">
                <label class="form-label small fw-semibold">Comments / rationale <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canMd): ?> <span class="req">*</span> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></label>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canMd): ?>
                    <textarea rows="3" class="form-control <?php $__errorArgs = ['md.comments'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model.blur="md.comments"
                              placeholder="<?php echo e(($md['decision'] ?? null) === 'return' ? 'What must be clarified or refined, and by whom' : 'Basis for the decision, and anything the Principal Secretary should note'); ?>"></textarea>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['md.comments'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php else: ?>
                    <div class="quote mb-0"><?php echo e($md['comments'] ?: 'No comments yet.'); ?></div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
            <div class="col-md-6">
                <label class="form-label small fw-semibold">Signed by (Managing Director)</label>
                <input type="text" class="form-control bg-light" readonly value="<?php echo e($a?->md_signer_name ?: ($signing['md']['name'] ?? '') ?: '—'); ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label small fw-semibold">Date signed</label>
                <input type="date" class="form-control <?php $__errorArgs = ['md.on'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model.blur="md.on" max="<?php echo e(now()->toDateString()); ?>" <?php if(! $canMd): echo 'disabled'; endif; ?>>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['md.on'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canMd): ?>
            <div class="d-flex flex-wrap gap-2 mt-4 pt-3 border-top">
                <button type="button" class="btn btn-brand ms-auto" wire:click="recordMd" wire:loading.attr="disabled" wire:target="recordMd"
                        wire:confirm="Record the Managing Director's signed decision? It can't be changed afterwards. The PMU will be e-mailed — and the applicant too if it is Not approve.">
                    <span wire:loading.remove wire:target="recordMd"><i class="bi bi-send me-1"></i> Record Managing Director decision</span>
                    <span wire:loading wire:target="recordMd"><span class="spinner-border spinner-border-sm me-1"></span> Recording…</span>
                </button>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </section>

    
    <section class="section-card">
        <div class="section-card-head">
            <span class="section-no">2</span>
            <div class="me-auto">
                <h5>MTIBD Principal Secretary review and signature</h5>
                <div class="small text-muted">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($psDone): ?> Signed by <?php echo e($a->signerName('ps')); ?> on <?php echo e($a->ps_on?->format('d M Y')); ?> · recorded by <?php echo e($a->principalSecretary?->name ?? '—'); ?> on <?php echo e($a->ps_decided_at->format('d M Y H:i')); ?>.
                    <?php else: ?> The second signature on the Grant Agreement — completing both signatures constitutes formal approval of the grant. <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($psDone): ?>
                <?php [$psL, , $psI] = $A::PS_DECISIONS[$a->ps_decision]; ?>
                <span class="pill <?php echo e(['authorise' => 'pill-ok', 'clarify' => 'pill-warn', 'decline' => 'pill-danger'][$a->ps_decision]); ?>"><i class="bi <?php echo e($psI); ?>"></i> <?php echo e($psL); ?></span>
            <?php elseif($stage === 'ps'): ?>
                <span class="pill pill-warn"><i class="bi bi-hourglass-split"></i> Awaiting decision</span>
            <?php else: ?>
                <span class="pill pill-muted"><i class="bi bi-lock-fill"></i> Locked</span>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! $psDone && $stage !== 'ps'): ?>
            <div class="g1-locked">
                <i class="bi bi-lock"></i>
                <div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($mdDone && $a->md_decision !== 'approve'): ?> Not reached — the Managing Director did not sign the Grant Agreement.
                    <?php else: ?> Opens once the Managing Director has signed the Grant Agreement and forwarded it. The signatures are sequential. <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($mdDone && $a->md_comments): ?>
                <div class="quote mb-3"><?php echo e($a->md_comments); ?><div class="quote-meta">Managing Director (<?php echo e($a->signerName('md')); ?>) · <?php echo e($a->md_on?->format('d M Y')); ?></div></div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <?php echo $__env->make('expression-of-interest.authorisation.partials.signing', ['role' => 'ps', 'a' => $a, 'can' => $canPs, 'signing' => $signing, 'decidedRole' => $psDone, 'defaultDoc' => $canPs ? $this->defaultDocument('ps', $a) : null], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

            <div class="d-flex align-items-center gap-2 mb-2">
                <span class="pill <?php echo e($psDone ? 'pill-ok' : 'pill-warn'); ?>">3</span>
                <span class="fw-semibold text-navy small">Record the Principal Secretary's decision, as written on the signed document</span>
            </div>

            <div class="choice-group cols-3 <?php $__errorArgs = ['ps.decision'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $A::PS_DECISIONS; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $v => [$dLabel, $dDesc, $dIcon]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <label class="choice choice--card">
                        <input type="radio" name="ps-decision" value="<?php echo e($v); ?>" wire:model.live="ps.decision" <?php if(! $canPs): echo 'disabled'; endif; ?>>
                        <span class="choice-box">
                            <span class="choice-icon"><i class="bi <?php echo e($dIcon); ?>"></i></span>
                            <span><span class="d-block fw-semibold"><?php echo e($dLabel); ?></span><span class="d-block small text-muted lh-sm mt-1"><?php echo e($dDesc); ?></span></span>
                        </span>
                    </label>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['ps.decision'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-danger small mt-1"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <div class="row g-3 mt-1">
                <div class="col-12">
                    <label class="form-label small fw-semibold">Comments <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canPs && ($ps['decision'] ?? null) && $ps['decision'] !== 'authorise'): ?> <span class="req">*</span> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></label>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canPs): ?>
                        <textarea rows="3" class="form-control <?php $__errorArgs = ['ps.comments'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model.blur="ps.comments"
                                  placeholder="<?php echo e(($ps['decision'] ?? null) === 'clarify' ? 'What must be clarified or corrected' : (($ps['decision'] ?? null) === 'decline' ? 'Why the agreement is not signed' : 'Optional')); ?>"></textarea>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['ps.comments'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php else: ?>
                        <div class="quote mb-0"><?php echo e($ps['comments'] ?: 'No comments.'); ?></div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Signed by (Principal Secretary)</label>
                    <input type="text" class="form-control bg-light" readonly value="<?php echo e($a?->ps_signer_name ?: ($signing['ps']['name'] ?? '') ?: '—'); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Date signed</label>
                    <input type="date" class="form-control <?php $__errorArgs = ['ps.on'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model.blur="ps.on" max="<?php echo e(now()->toDateString()); ?>" <?php if(! $canPs): echo 'disabled'; endif; ?>>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['ps.on'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canPs): ?>
                <div class="d-flex flex-wrap gap-2 mt-4 pt-3 border-top">
                    <button type="button" class="btn btn-brand ms-auto" wire:click="recordPs" wire:loading.attr="disabled" wire:target="recordPs"
                            wire:confirm="Record the Principal Secretary's signed decision? It can't be changed afterwards. The PMU will be e-mailed — and, if authorised or declined, the applicant, the Committee and Finance too.">
                        <span wire:loading.remove wire:target="recordPs"><i class="bi bi-patch-check me-1"></i> Record Principal Secretary decision</span>
                        <span wire:loading wire:target="recordPs"><span class="spinner-border spinner-border-sm me-1"></span> Recording…</span>
                    </button>
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </section>

    
    <section class="section-card" id="g4-conditions">
        <div class="section-card-head">
            <span class="section-no">3</span>
            <div class="me-auto">
                <h5>Conditions attached</h5>
                <div class="small text-muted">Each condition is classified and assigned to the stage where it must be met — authorisation does not mean they are already satisfied.</div>
            </div>
            <span class="pill <?php echo e($openConds ? 'pill-warn' : 'pill-ok'); ?>"><?php echo e($openConds); ?> open</span>
        </div>

        <div class="table-responsive">
            <table class="table table-modern align-middle mb-2">
                <thead>
                    <tr><th>Condition</th><th style="width: 27%">Type</th><th style="width: 22%">Treatment</th><th style="width: 9%">Source</th><th style="width: 12%">Status</th><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canMd): ?><th></th><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></tr>
                </thead>
                <tbody>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $conditions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <?php $t = $A::CONDITION_TYPES[$c['type'] ?? ''] ?? null; ?>
                        <tr <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'g4c-'.e($i).''; ?>wire:key="g4c-<?php echo e($i); ?>" class="<?php echo e(($c['type'] ?? null) === 'material' && $c['status'] === 'open' ? 'is-flagged' : ''); ?>">
                            <td class="small">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canMd && $c['source'] === 'g4'): ?>
                                    <input type="text" class="form-control form-control-sm <?php $__errorArgs = ['conditions.'.$i.'.finding'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model.blur="conditions.<?php echo e($i); ?>.finding" maxlength="250" placeholder="Describe the condition">
                                <?php else: ?>
                                    <?php echo e($c['finding']); ?>

                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </td>
                            <td>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canMd): ?>
                                    <select class="form-select form-select-sm <?php echo e(blank($c['type'] ?? null) ? 'border-warning' : ''); ?>" wire:model.live="conditions.<?php echo e($i); ?>.type">
                                        <option value="">Classify…</option>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $A::CONDITION_TYPES; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tk => [$tl]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?> <option value="<?php echo e($tk); ?>"><?php echo e($tl); ?></option> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                    </select>
                                <?php else: ?>
                                    <span class="small"><?php echo e($t[0] ?? '—'); ?></span>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </td>
                            <td class="small text-muted"><?php echo e($t[1] ?? '—'); ?></td>
                            <td><span class="pill pill-muted"><?php echo e($A::CONDITION_SOURCES[$c['source']] ?? $c['source']); ?></span></td>
                            <td>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canMd): ?>
                                    <select class="form-select form-select-sm" wire:model.live="conditions.<?php echo e($i); ?>.status">
                                        <option value="open">Open</option><option value="resolved">Resolved</option>
                                    </select>
                                <?php else: ?>
                                    <span class="pill <?php echo e($c['status'] === 'open' ? 'pill-warn' : 'pill-ok'); ?>"><?php echo e(ucfirst($c['status'])); ?></span>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </td>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canMd): ?>
                                <td><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($c['source'] === 'g4'): ?><button type="button" class="btn btn-icon btn-icon-danger" wire:click="removeCondition(<?php echo e($i); ?>)" title="Remove"><i class="bi bi-x-lg"></i></button><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></td>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </tr>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        <tr><td colspan="6" class="small text-muted">No conditions attached.</td></tr>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canMd): ?>
            <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="addCondition"><i class="bi bi-plus-lg me-1"></i> Add condition</button>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <details class="evidence mt-3">
            <summary><i class="bi bi-info-circle me-1"></i> How conditions are treated</summary>
            <dl class="evidence-grid">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $A::CONDITION_TYPES; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$tl, $tt, $tBlocks]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <dt><?php echo e($tl); ?></dt><dd><?php echo e($tt); ?> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($tBlocks): ?> <span class="pill pill-danger ms-1">Blocks approval</span> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></dd>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </dl>
        </details>
    </section>

    
    <section class="section-card">
        <div class="section-card-head">
            <span class="section-no">4</span>
            <div class="me-auto">
                <h5>Gate 5 outcome &amp; handover</h5>
                <div class="small text-muted">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($decided): ?> Round <?php echo e($round); ?> closed on <?php echo e($a->decided_at->format('d M Y H:i')); ?>.
                    <?php else: ?> Recorded once both signatures are in — or as soon as either signatory returns the agreement or does not approve. <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($decided): ?> <span class="pill <?php echo e($A::OUTCOMES[$outcome][4]); ?>"><i class="bi <?php echo e($A::OUTCOMES[$outcome][3]); ?>"></i> <?php echo e($A::OUTCOMES[$outcome][0]); ?></span> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>

        <div class="row g-2 mb-3">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $A::OUTCOMES; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ok => [$oLabel, $oMeaning, $oNext, $oIcon, $oPill]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <div class="col-md-4">
                    <div class="border rounded-3 p-3 h-100 <?php echo e($outcome === $ok ? 'border-2 border-primary' : ($decided ? 'opacity-50' : '')); ?>">
                        <span class="pill <?php echo e($oPill); ?> mb-2"><i class="bi <?php echo e($oIcon); ?>"></i> <?php echo e($oLabel); ?></span>
                        <div class="small text-muted"><?php echo e($oMeaning); ?></div>
                        <div class="small fw-semibold text-navy mt-1"><i class="bi bi-arrow-right me-1"></i><?php echo e($oNext); ?></div>
                    </div>
                </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        </div>

        <dt class="detail-label mb-2">Decision record</dt>
        <div class="table-responsive">
            <table class="table table-modern table-sm align-middle mb-3">
                <thead><tr><th>Authority</th><th>Decision</th><th>Date signed</th><th>Comments</th><th>Signed copy</th></tr></thead>
                <tbody>
                    <tr>
                        <td class="small fw-semibold text-navy">CAFI Managing Director <div class="text-muted fw-normal"><?php echo e($a?->md_signer_name); ?></div></td>
                        <td><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($mdDone): ?><span class="pill <?php echo e(['approve' => 'pill-ok', 'return' => 'pill-warn', 'decline' => 'pill-danger'][$a->md_decision]); ?>"><?php echo e($A::MD_DECISIONS[$a->md_decision][0]); ?></span><?php else: ?><span class="pill pill-warn">Pending</span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></td>
                        <td class="small <?php echo e($mdDone ? '' : 'text-faint'); ?>"><?php echo e($mdDone ? $a->md_on?->format('d M Y') : '—'); ?></td>
                        <td class="small <?php echo e($mdDone ? '' : 'text-faint'); ?>"><?php echo e($mdDone ? \Illuminate\Support\Str::limit($a->md_comments, 120) : '—'); ?></td>
                        <td class="small"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($a?->hasSignedCopy('md')): ?><a href="<?php echo e(route('gate4.file', [$a, 'md_signed'])); ?>" class="text-decoration-none"><i class="bi bi-file-earmark-check"></i> Download</a><?php else: ?><span class="text-faint">—</span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></td>
                    </tr>
                    <tr>
                        <td class="small fw-semibold text-navy">MTIBD Principal Secretary <div class="text-muted fw-normal"><?php echo e($a?->ps_signer_name); ?></div></td>
                        <td><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($psDone): ?><span class="pill <?php echo e(['authorise' => 'pill-ok', 'clarify' => 'pill-warn', 'decline' => 'pill-danger'][$a->ps_decision]); ?>"><?php echo e($A::PS_DECISIONS[$a->ps_decision][0]); ?></span><?php elseif($stage === 'ps'): ?><span class="pill pill-warn">Pending</span><?php else: ?><span class="pill pill-muted">Not started</span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></td>
                        <td class="small <?php echo e($psDone ? '' : 'text-faint'); ?>"><?php echo e($psDone ? $a->ps_on?->format('d M Y') : '—'); ?></td>
                        <td class="small <?php echo e($psDone ? '' : 'text-faint'); ?>"><?php echo e($psDone ? (\Illuminate\Support\Str::limit($a->ps_comments, 120) ?: '—') : '—'); ?></td>
                        <td class="small"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($a?->hasSignedCopy('ps')): ?><a href="<?php echo e(route('gate4.file', [$a, 'ps_signed'])); ?>" class="text-decoration-none"><i class="bi bi-file-earmark-check"></i> Download</a><?php else: ?><span class="text-faint">—</span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($outcome === 'authorised'): ?>
            <div class="callout callout-success small mb-0">
                <i class="bi bi-box-arrow-in-right me-1"></i> Fully executed Grant Agreement — handed over to <strong>Gate 6</strong> for conditions precedent and onboarding
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($openConds): ?> — <?php echo e($openConds); ?> <?php echo e(\Illuminate\Support\Str::plural('condition', $openConds)); ?> to be met. <?php else: ?>. <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                Disbursement remains subject to the conditions precedent and financial controls.
            </div>
        <?php elseif($outcome === 'not_authorised'): ?>
            <div class="callout callout-warning small mb-0"><i class="bi bi-x-octagon me-1"></i> The grant did not receive the required signatures and cannot proceed. The applicant has been notified by e-mail.</div>
        <?php elseif($outcome === 'clarification'): ?>
            <div class="callout callout-warning small mb-3">
                <i class="bi bi-arrow-return-left me-1"></i>
                Returned by the <?php echo e($a->heldBy() === 'ps' ? 'Principal Secretary' : 'Managing Director'); ?>.
                Resolve the matters raised and resubmit — it goes back to the <?php echo e($a->heldBy() === 'ps' ? 'Principal Secretary (the Managing Director\'s approval stands)' : 'Managing Director'); ?>.
            </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->canResubmit): ?>
                <label class="form-label small fw-semibold">How the clarification was addressed <span class="req">*</span></label>
                <textarea rows="3" class="form-control <?php $__errorArgs = ['resubmissionNote'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model.blur="resubmissionNote"></textarea>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['resubmissionNote'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <div class="d-flex mt-3">
                    <button type="button" class="btn btn-outline-primary ms-auto" wire:click="resubmit" wire:confirm="Resubmit for authorisation as round <?php echo e($round + 1); ?>?">
                        <i class="bi bi-arrow-repeat me-1"></i> Resubmit for authorisation
                    </button>
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php else: ?>
            <div class="empty-state py-4">
                <div class="empty-icon"><i class="bi bi-box-arrow-in-right"></i></div>
                <p class="small text-muted mb-0">Once both signatures are recorded, the transaction is handed over to <strong>Gate 6</strong> for conditions precedent and onboarding.</p>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </section>

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->history->isNotEmpty()): ?>
        <section class="section-card">
            <div class="section-card-head"><span class="section-no"><i class="bi bi-clock-history"></i></span><h5>Earlier rounds</h5></div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $this->history; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $h): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <?php [$hl, , , $hi, $hp] = $A::OUTCOMES[$h->outcome] ?? [$h->outcome, '', '', 'bi-circle', 'pill-muted']; ?>
                <div class="file-row align-items-start">
                    <span class="file-row-icon"><i class="bi <?php echo e($hi); ?>"></i></span>
                    <div class="flex-grow-1 small">
                        <strong class="text-navy">Round <?php echo e($h->round); ?></strong> <span class="pill <?php echo e($hp); ?> ms-1"><?php echo e($hl); ?></span>
                        <span class="text-muted ms-1"><?php echo e($h->decided_at->format('d M Y')); ?> · by the <?php echo e($h->ps_decided_at ? 'Principal Secretary' : 'Managing Director'); ?></span>
                        <?php $hc = $h->ps_decided_at ? $h->ps_comments : $h->md_comments; ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($hc): ?> <div class="text-muted mt-1"><?php echo e($hc); ?></div> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        </section>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div><?php /**PATH C:\laragon\www\co-investment-facility\storage\framework\views/livewire/views/6c649f39.blade.php ENDPATH**/ ?>