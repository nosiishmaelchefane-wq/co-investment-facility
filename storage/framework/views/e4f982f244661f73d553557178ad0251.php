<?php
use App\Models\CapitalDeployment;
use App\Models\ConditionReview;
use App\Models\CpAgreement;
use App\Models\CpCondition;
use App\Models\DisbursementTranche;
use App\Models\EnterpriseProfile;
use App\Models\ExpressionOfInterest;
use App\Notifications\Gate5\CapitalDeploymentReviewed;
use App\Notifications\Gate5\Gate5Outcome;
use App\Notifications\Gate5\Gate5StaffAlert;
use App\Notifications\Gate5\GrantApprovalNotice;
use App\Notifications\Gate5\ObligationsSummary;
use App\Notifications\Gate5\TrancheReleased;
use App\Support\NotifyRecipients;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;
?>

<div>
<?php
    $CR        = \App\Models\ConditionReview::class;
    $eoi       = $this->eoi;
    $r         = $this->review;
    $applicant = $this->isApplicant;
    $canManage = $this->canManage;
    $canAgr    = $this->canManageAgreements;
    $rate      = max((float) config('cif.usd_to_lsl', 18.00), 0.0001);
    $m         = fn ($v) => $v === null || $v === '' ? '—' : 'M '.number_format((float) $v);
    $u         = fn ($v) => $v === null || $v === '' ? '' : 'US$ '.number_format((float) $v / $rate);
    $file      = fn ($kind, $id) => route('gate5.file', [$kind, $id]);
?>


<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! $this->gateOpen): ?>
    <section class="section-card">
        <div class="section-card-head">
            <span class="section-no">G6</span>
            <h5 class="me-auto">Conditions precedent, onboarding &amp; disbursement</h5>
            <span class="pill pill-muted"><i class="bi bi-lock-fill"></i> Locked</span>
        </div>
        <p class="small text-muted mb-0">Opens once the Grant Agreement is signed by both the CAFI Managing Director and the MTIBD Principal Secretary (Gate 5).
            Formal approval does not authorise payment: the conditions precedent are verified first (Gate 6), and the grant is then released against verified private investment (Gate 7).</p>
    </section>
<?php else: ?>
<?php
    $s         = $r->summary();
    $grant     = (float) $r->grant_amount;
    $private   = (float) $r->private_amount;
    $verified  = $s['capital'];
    $outcome   = $r->outcome;
    $blockers  = $applicant ? [] : $this->blockers;
    $released  = $r->tranches->filter->isReleased();
    $budgetT   = $r->budgetTotals();
    $controls  = $applicant ? [] : $this->controls;
    $gate4Bank = $applicant ? null : $this->gate4Bank;
    $agrGrant  = $r->agreements->firstWhere('kind', 'grant');
    $partyL    = fn ($k) => $CR::PARTIES[$k] ?? ucfirst((string) $k);

    $st = fn (bool $done, bool $started = true) => $done ? 'done' : ($started ? 'current' : 'todo');
    $path = [
        ['Gate 5 · Grant Agreement signed', 'done'],
        ['1 · Approval & checklist', $st((bool) $r->approval_sent_at)],
        ['2 · Verify conditions', $st($s['conditions_done'] === $s['conditions_total'] && $s['verified_areas'] === count($CR::VERIFICATION_AREAS), (bool) $r->approval_sent_at)],
        ['3 · Readiness', $st($r->isCleared(), (bool) $r->readiness_pmu_at)],
        ['4 · Onboarding', $st((bool) $r->onboarding_confirmed_at, (bool) $r->approval_sent_at)],
        ['Gate 7 · Disbursement', $r->isFullyDisbursed() ? 'done' : ($r->isCleared() ? 'current' : 'todo')],
    ];
    $headPill = match (true) {
        $r->isFullyDisbursed() => ['Fully disbursed', 'pill-ok', 'bi-check2-all'],
        (bool) $outcome        => [$CR::OUTCOMES[$outcome][0], $CR::OUTCOMES[$outcome][4], $CR::OUTCOMES[$outcome][3]],
        default                => ['In progress', 'pill-warn', 'bi-hourglass-split'],
    };
?>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($applicant)): ?>
        <div class="g2-path mb-3">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $path; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$pLabel, $pState]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <span class="g2-path-step <?php echo e($pState !== 'todo' ? 'is-'.$pState : ''); ?>">
                    <i class="bi <?php echo e(['done' => 'bi-check-lg', 'current' => 'bi-hourglass-split', 'todo' => 'bi-circle'][$pState]); ?>"></i> <?php echo e($pLabel); ?>

                </span>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    
    <section class="section-card">
        <div class="section-card-head">
            <span class="section-no">G6</span>
            <div class="me-auto">
                <h5>Conditions precedent, onboarding &amp; disbursement</h5>
                <div class="small text-muted">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($applicant): ?> What needs to happen before — and after — your CAFI grant is paid.
                    <?php else: ?> Gate 6 activates the approved grant and confirms readiness; Gate 7 releases it against verified private capital. <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($applicant)): ?>
                <a href="<?php echo e(route('gate5.record', $r)); ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-file-earmark-pdf me-1"></i> Disbursement file (PDF)</a>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <span class="pill <?php echo e($headPill[1]); ?>"><i class="bi <?php echo e($headPill[2]); ?>"></i> <?php echo e($headPill[0]); ?></span>
        </div>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canManage): ?>
            <div class="small text-muted mb-3"><i class="bi bi-cloud-check me-1"></i> Changes are saved automatically. <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($savedAt): ?> <span class="text-accent">Last saved <?php echo e($savedAt); ?>.</span> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <span wire:loading class="ms-1"><span class="spinner-border spinner-border-sm"></span></span></div>
        <?php elseif($r->isCleared() && ! $applicant): ?>
            <div class="small text-muted mb-3"><i class="bi bi-lock me-1"></i> Cleared on <?php echo e($r->cleared_at?->format('d M Y')); ?> — the Gate 6 set-up is locked. Payments are processed below (Gate 7).</div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <div class="row g-3">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = [
                ['Approved CAFI grant', $m($grant), $u($grant), 'bi-cash-coin', 'primary'],
                ['Private capital verified', $m($verified), $private > 0 ? 'of '.$m($private).' approved' : '', 'bi-briefcase', 'accent'],
                ['Disbursed', $m($r->releasedAmount()), 'Still to disburse '.$m($r->stillToDisburse()), 'bi-send-check', 'warning'],
                ['Conditions met', $s['conditions_done'].' / '.$s['conditions_total'], $s['ongoing'].' ongoing obligation(s)', 'bi-list-check', 'primary'],
            ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$tl, $tv, $ts, $ti, $tt]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <div class="col-sm-6 col-xl-3">
                    <div class="stat-card">
                        <span class="stat-icon tone-<?php echo e($tt); ?>"><i class="bi <?php echo e($ti); ?>"></i></span>
                        <div><div class="stat-value sm"><?php echo e($tv); ?></div><div class="stat-label"><?php echo e($tl); ?></div><div class="small text-muted"><?php echo e($ts); ?></div></div>
                    </div>
                </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        </div>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($r->outcome_comments && $outcome !== 'cleared'): ?>
            <div class="callout callout-warning small mt-3 mb-0"><div class="fw-semibold mb-1"><?php echo e($CR::OUTCOMES[$outcome][0] ?? 'Note'); ?> — <?php echo e($r->outcome_on?->format('d M Y')); ?></div><?php echo e($r->outcome_comments); ?></div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </section>

    
    <section class="section-card" id="g6-step1">
        <div class="section-card-head">
            <span class="section-no">1</span>
            <div class="me-auto">
                <h5><?php echo e($applicant ? 'Your Grant Agreement and conditions' : 'Communicate grant approval & initiate onboarding'); ?></h5>
                <div class="small text-muted">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($r->approval_sent_at): ?> Approval communicated on <?php echo e($r->approval_sent_at->format('d M Y')); ?>.
                    <?php else: ?> The enterprise receives the executed Grant Agreement, the approved budget, the conditions before disbursement and its reporting obligations. <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>
            <span class="pill <?php echo e($r->approval_sent_at ? 'pill-ok' : 'pill-warn'); ?>"><?php echo e($r->approval_sent_at ? 'Sent' : 'Not sent'); ?></span>
        </div>

        
        <div class="table-responsive mb-3">
            <table class="table table-modern align-middle mb-2">
                <thead><tr><th>Agreement</th><th style="width: 16%">Status</th><th style="width: 14%">Signed</th><th style="width: 26%">Signed copy</th><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canAgr): ?><th></th><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></tr></thead>
                <tbody>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $agreements; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $a): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <?php $ag = $r->agreements->firstWhere('id', $a['id']); ?>
                        <tr <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'agr-'.e($a['id']).''; ?>wire:key="agr-<?php echo e($a['id']); ?>">
                            <td class="small">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canAgr && $a['kind'] === 'other'): ?>
                                    <input type="text" class="form-control form-control-sm mb-1" wire:model.blur="agreements.<?php echo e($i); ?>.title" maxlength="150">
                                <?php else: ?> <div class="fw-semibold text-navy"><?php echo e($a['title']); ?></div> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <div class="text-muted"><?php echo e($a['parties']); ?></div>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canAgr && $a['kind'] !== 'grant'): ?>
                                    <label class="form-check small mb-0 mt-1"><input type="checkbox" class="form-check-input" wire:model.live="agreements.<?php echo e($i); ?>.required"> <span class="form-check-label">Required before disbursement</span></label>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </td>
                            <td>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canAgr): ?>
                                    <select class="form-select form-select-sm <?php $__errorArgs = ["agreements.$i.status"];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model.live="agreements.<?php echo e($i); ?>.status">
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $CR::AGREEMENT_STATUSES; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $sk => [$sl]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?> <option value="<?php echo e($sk); ?>"><?php echo e($sl); ?></option> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                    </select>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ["agreements.$i.status"];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <?php else: ?> <span class="pill <?php echo e($CR::AGREEMENT_STATUSES[$a['status']][1] ?? 'pill-muted'); ?>"><?php echo e($CR::AGREEMENT_STATUSES[$a['status']][0] ?? $a['status']); ?></span> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </td>
                            <td><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canAgr): ?><input type="date" class="form-control form-control-sm" wire:model.blur="agreements.<?php echo e($i); ?>.signed_on"><?php else: ?> <span class="small"><?php echo e($a['signed_on'] ? \Illuminate\Support\Carbon::parse($a['signed_on'])->format('d M Y') : '—'); ?></span> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></td>
                            <td class="small">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($ag?->file_path): ?> <a href="<?php echo e($file('agreement', $ag->id)); ?>" class="text-decoration-none text-truncate d-inline-block" style="max-width: 220px"><i class="bi bi-file-earmark-check"></i> <?php echo e($ag->file_name); ?></a> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canAgr): ?>
                                    <input type="file" class="form-control form-control-sm mt-1" wire:model="agreementFiles.<?php echo e($a['id']); ?>" accept=".pdf,.doc,.docx">
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ["agreementFiles.{$a['id']}"];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-danger"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <?php elseif(! $ag?->file_path): ?> <span class="text-muted">—</span> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </td>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canAgr): ?><td><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($a['kind'] === 'other'): ?><button type="button" class="btn btn-icon btn-icon-danger" wire:click="removeAgreement(<?php echo e($a['id']); ?>)"><i class="bi bi-x-lg"></i></button><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></td><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </tr>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </tbody>
            </table>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canAgr): ?> <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="addAgreement"><i class="bi bi-plus-lg me-1"></i> Add agreement</button> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>

        
        <div class="d-flex align-items-center gap-2 mb-2">
            <div class="fw-semibold text-navy me-auto"><i class="bi bi-list-check me-1"></i> Conditions precedent checklist</div>
            <span class="small text-muted">Requirement · evidence needed · who provides / verifies · status</span>
        </div>
        <div class="table-responsive">
            <table class="table table-modern align-middle mb-2 dd-table">
                <thead><tr>
                    <th>Requirement &amp; evidence needed</th>
                    <th style="width: 15%">Provides · verifies</th>
                    <th style="width: 13%">Timing</th>
                    <th style="width: 22%">Evidence</th>
                    <th style="width: 16%">Status</th>
                </tr></thead>
                <tbody>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $conds; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <?php
                            $cm      = $r->conditions->firstWhere('id', $c['id']);
                            $mine    = $applicant && in_array($c['responsible'], ['enterprise', 'investor'], true);
                            $canUp   = $this->canUploadEvidence && $cm && ! $cm->isDone() && (! $applicant || $mine);
                        ?>
                        <tr <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'cond-'.e($c['id']).''; ?>wire:key="cond-<?php echo e($c['id']); ?>" class="<?php echo e($c['status'] === 'not_met' ? 'is-flagged' : ''); ?>">
                            <td class="small">
                                <span class="pill pill-muted mb-1"><?php echo e($CR::areaLabel($c['area'])); ?></span>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canManage): ?>
                                    <input type="text" class="form-control form-control-sm mb-1" wire:model.blur="conds.<?php echo e($i); ?>.finding" maxlength="250">
                                    <input type="text" class="form-control form-control-sm" wire:model.blur="conds.<?php echo e($i); ?>.evidence_required" maxlength="250" placeholder="Evidence needed">
                                <?php else: ?>
                                    <div class="fw-semibold text-navy"><?php echo e($c['finding']); ?></div>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($c['evidence_required']): ?> <div class="text-muted">Evidence: <?php echo e($c['evidence_required']); ?></div> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($c['status'] === 'not_met' || ($canManage && $c['status'] !== 'met')): ?>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canManage): ?>
                                        <input type="text" class="form-control form-control-sm mt-1 <?php echo e($c['status'] === 'not_met' ? 'border-danger' : ''); ?>" wire:model.blur="conds.<?php echo e($i); ?>.corrective_action" maxlength="500" placeholder="Corrective action required (if not satisfied)">
                                    <?php elseif($c['corrective_action']): ?>
                                        <div class="text-danger mt-1"><i class="bi bi-exclamation-circle me-1"></i>Action: <?php echo e($c['corrective_action']); ?></div>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canManage): ?>
                                    <input type="text" class="form-control form-control-sm mt-1" wire:model.blur="conds.<?php echo e($i); ?>.note" maxlength="500" placeholder="Verification note — document reviewed, outcome">
                                <?php elseif($c['note'] && ! $applicant): ?> <div class="text-muted mt-1"><?php echo e($c['note']); ?></div> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </td>
                            <td class="small">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canManage): ?>
                                    <select class="form-select form-select-sm mb-1" wire:model.live="conds.<?php echo e($i); ?>.responsible" title="Provides the evidence">
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $CR::PARTIES; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $pk => $pl): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?> <option value="<?php echo e($pk); ?>"><?php echo e($pl); ?></option> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                    </select>
                                    <select class="form-select form-select-sm" wire:model.live="conds.<?php echo e($i); ?>.verifier" title="Verifies">
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $CR::PARTIES; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $pk => $pl): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?> <option value="<?php echo e($pk); ?>"><?php echo e($pl); ?></option> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                    </select>
                                <?php else: ?>
                                    <div><?php echo e($partyL($c['responsible'])); ?></div><div class="text-muted">verified by <?php echo e($partyL($c['verifier'])); ?></div>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </td>
                            <td class="small">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canManage): ?>
                                    <select class="form-select form-select-sm" wire:model.live="conds.<?php echo e($i); ?>.timing">
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $CR::TIMINGS; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tk => [$tl]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?> <option value="<?php echo e($tk); ?>"><?php echo e($tl); ?></option> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                    </select>
                                <?php else: ?> <span title="<?php echo e($CR::TIMINGS[$c['timing']][1] ?? ''); ?>"><?php echo e($CR::TIMINGS[$c['timing']][0] ?? '—'); ?></span> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </td>
                            <td class="small">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($cm?->evidence_path): ?>
                                    <a href="<?php echo e($file('condition', $cm->id)); ?>" class="text-decoration-none text-truncate d-inline-block" style="max-width: 190px"><i class="bi bi-paperclip"></i> <?php echo e($cm->evidence_name); ?></a>
                                    <div class="text-muted"><?php echo e($cm->evidence_at?->format('d M Y')); ?></div>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canUp): ?>
                                    <input type="file" class="form-control form-control-sm mt-1" wire:model="evidenceFiles.<?php echo e($c['id']); ?>">
                                    <span wire:loading wire:target="evidenceFiles.<?php echo e($c['id']); ?>" class="text-muted"><span class="spinner-border spinner-border-sm"></span></span>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ["evidenceFiles.{$c['id']}"];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-danger"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <?php elseif(! $cm?->evidence_path): ?> <span class="text-muted">—</span> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </td>
                            <td>
                                <span class="pill <?php echo e($CR::CONDITION_STATUSES[$c['status']][1] ?? 'pill-muted'); ?>"><?php echo e($CR::CONDITION_STATUSES[$c['status']][0] ?? $c['status']); ?></span>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($cm?->verifiedBy && $cm->isDone()): ?> <div class="small text-muted"><?php echo e($cm->verifiedBy->name); ?> · <?php echo e($cm->verified_at?->format('d M')); ?></div> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canManage): ?>
                                    <div class="d-flex flex-wrap gap-1 mt-1">
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = ['met' => 'bi-check-lg', 'not_met' => 'bi-x-lg', 'waived' => 'bi-dash-lg', 'outstanding' => 'bi-arrow-counterclockwise']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $sk => $si): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($c['status'] !== $sk): ?>
                                                <button type="button" class="btn btn-icon btn-sm" wire:click="setConditionStatus(<?php echo e($c['id']); ?>, '<?php echo e($sk); ?>')" title="<?php echo e($CR::CONDITION_STATUSES[$sk][0]); ?>"><i class="bi <?php echo e($si); ?>"></i></button>
                                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(in_array($c['source'], ['g6', 'g5'], true)): ?>
                                            <button type="button" class="btn btn-icon btn-icon-danger btn-sm" wire:click="removeCondition(<?php echo e($c['id']); ?>)" wire:confirm="Remove this item?" title="Remove"><i class="bi bi-trash"></i></button>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </div>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ["conds.$i.status"];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-danger small mt-1"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </td>
                        </tr>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canManage): ?>
            <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
                <select class="form-select form-select-sm" style="width: auto" wire:model="newArea">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $CR::AREAS; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ak => $al): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?> <option value="<?php echo e($ak); ?>"><?php echo e($al); ?></option> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </select>
                <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="addCondition"><i class="bi bi-plus-lg me-1"></i> Add requirement</button>
                <span class="small text-muted">Ongoing reporting and monitoring commitments are recorded as <em>Ongoing obligation</em> — they are not conditions precedent unless the Grant Agreement says so.</span>
            </div>

            
            <div class="border rounded-3 p-3">
                <div class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold mb-1">Programme contact for the enterprise <span class="req">*</span></label>
                        <input type="text" class="form-control form-control-sm" wire:model.blur="onboarding.contact" placeholder="Name · e-mail · phone">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold mb-1">Reporting frequency</label>
                        <select class="form-select form-select-sm" wire:model.live="onboarding.frequency">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $CR::FREQUENCIES; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $fk => $fl): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?> <option value="<?php echo e($fk); ?>"><?php echo e($fl); ?></option> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-semibold mb-1">First report</label>
                        <input type="date" class="form-control form-control-sm" wire:model.blur="onboarding.first_report_on">
                    </div>
                    <div class="col-md-3 d-flex">
                        <button type="button" class="btn btn-sm <?php echo e($r->approval_sent_at ? 'btn-outline-secondary' : 'btn-brand'); ?> ms-auto" wire:click="sendApproval" wire:loading.attr="disabled" wire:target="sendApproval"
                                wire:confirm="E-mail the grant approval, the executed Grant Agreement and the conditions checklist to the enterprise?">
                            <i class="bi bi-envelope-paper me-1"></i> <?php echo e($r->approval_sent_at ? 'Send again' : 'Send approval & checklist'); ?>

                        </button>
                    </div>
                </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['approval'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-danger small mt-2"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </section>

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($applicant)): ?>
        <section class="section-card" id="g6-step2">
            <div class="section-card-head">
                <span class="section-no">2</span>
                <div class="me-auto">
                    <h5>Verify conditions precedent</h5>
                    <div class="small text-muted">On documentary evidence and the executed Grant Agreement — not informal confirmation. Issues: document, set the corrective action and responsible party, track to resolution.</div>
                </div>
                <span class="pill <?php echo e($s['verified_areas'] === count($CR::VERIFICATION_AREAS) ? 'pill-ok' : 'pill-warn'); ?>"><?php echo e($s['verified_areas']); ?> / <?php echo e(count($CR::VERIFICATION_AREAS)); ?> areas</span>
            </div>

            <?php
                $hints = [
                    'legal'       => $r->conditions->where('area', 'legal')->every->isDone() ? 'Legal checklist items met.' : 'Legal checklist items outstanding.',
                    'agreement'   => ($agrGrant?->status === 'executed' ? 'Executed agreement on file' : 'Executed agreement missing').' · authorised grant '.$m($r->authorisation?->grant_amount).' vs '.$m($grant),
                    'budget'      => 'Budget grant total '.$m($budgetT['grant']).' vs approved '.$m($grant).(abs($budgetT['grant'] - $grant) <= 0.01 ? ' — matches' : ' — differs'),
                    'bank'        => ! empty($r->bank['changed']) ? 'Changed since Gate 4 — '.($r->bank_revalidated_at ? 'revalidated' : 'revalidation needed') : ($gate4Bank ? 'Same as verified at Gate 4' : 'No Gate 4 record'),
                    'investment'  => 'Verified '.$m($verified).' of '.$m($private).' approved private investment',
                    'contractual' => $r->conditions->where('area', 'contractual')->filter->isMandatory()->reject->isDone()->count().' contractual condition(s) open',
                    'payment'     => 'Approved grant '.$m($grant).' · disbursed '.$m($controls['disbursed'] ?? null),
                ];
            ?>
            <div class="table-responsive">
                <table class="table table-modern align-middle mb-3 dd-table">
                    <thead><tr><th>Verification area</th><th style="width: 18%">Result</th><th style="width: 30%">Note / evidence reviewed</th></tr></thead>
                    <tbody>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $CR::VERIFICATION_AREAS; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ak => [$aTitle, $aText]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <?php $vs = $verification[$ak]['status'] ?? null; ?>
                            <tr <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'va-'.e($ak).''; ?>wire:key="va-<?php echo e($ak); ?>" class="<?php echo e($vs === 'issue' ? 'is-flagged' : ''); ?>">
                                <td class="small"><div class="fw-semibold text-navy"><?php echo e($aTitle); ?></div><div class="text-muted"><?php echo e($aText); ?></div>
                                    <div class="mt-1 text-primary-emphasis"><i class="bi bi-info-circle me-1"></i><?php echo e($hints[$ak]); ?></div></td>
                                <td>
                                    <select class="form-select form-select-sm status-select <?php echo e($CR::VERIFY_STATUSES[$vs][2] ?? ''); ?>" wire:model.live="verification.<?php echo e($ak); ?>.status" <?php if(! $canManage): echo 'disabled'; endif; ?>>
                                        <option value="">—</option>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $CR::VERIFY_STATUSES; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $vk => [$vl]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?> <option value="<?php echo e($vk); ?>"><?php echo e($vl); ?></option> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                    </select>
                                </td>
                                <td><input type="text" class="form-control form-control-sm" wire:model.blur="verification.<?php echo e($ak); ?>.note" maxlength="500" <?php if(! $canManage): echo 'disabled'; endif; ?>></td>
                            </tr>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="row g-3">
                
                <div class="col-lg-6">
                    <div class="border rounded-3 p-3 h-100">
                        <div class="fw-semibold text-navy small mb-2"><i class="bi bi-bank me-1"></i> Account the grant will be paid into</div>
                        <div class="row g-2">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = ['bank' => 'Bank', 'branch' => 'Branch', 'account_name' => 'Account name', 'account_number' => 'Account number']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $bk => $bl): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <div class="col-6">
                                    <label class="form-label small mb-0"><?php echo e($bl); ?></label>
                                    <input type="text" class="form-control form-control-sm" wire:model.blur="bank.<?php echo e($bk); ?>" <?php if(! $canManage): echo 'disabled'; endif; ?>>
                                </div>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </div>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($gate4Bank): ?>
                            <div class="small text-muted mt-2">Gate 4: <?php echo e($gate4Bank['account_name'] ?? '—'); ?> · <?php echo e($gate4Bank['bank'] ?? '—'); ?> · <?php echo e($gate4Bank['account_number'] ?? '—'); ?></div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! empty($r->bank['changed'])): ?>
                            <div class="callout callout-warning small mt-2 mb-0">
                                <i class="bi bi-shield-exclamation me-1"></i> The details differ from those verified at Gate 4. Revalidate them independently (payment-diversion and fraud risk).
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($r->bank_revalidated_at): ?>
                                    <div class="text-success mt-1"><i class="bi bi-check-circle me-1"></i>Revalidated <?php echo e($r->bank_revalidated_at->format('d M Y')); ?>: <?php echo e($r->bank_revalidation_note); ?></div>
                                <?php elseif($canManage): ?>
                                    <div class="input-group input-group-sm mt-2">
                                        <input type="text" class="form-control <?php $__errorArgs = ['bankNote'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model="bankNote" placeholder="How revalidated — e.g. bank letter dated…, call-back to the bank">
                                        <button class="btn btn-outline-primary" type="button" wire:click="revalidateBank">Record</button>
                                    </div>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['bankNote'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-danger"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                </div>

                
                <div class="col-lg-6">
                    <div class="border rounded-3 p-3 h-100">
                        <div class="d-flex align-items-center mb-2">
                            <div class="fw-semibold text-navy small me-auto"><i class="bi bi-pie-chart me-1"></i> Approved grant utilisation budget</div>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canManage): ?>
                                <button type="button" class="btn btn-sm btn-link p-0 me-2" wire:click="$toggle('editBudget')"><?php echo e($editBudget ? 'Done' : 'Edit'); ?></button>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->profile?->grant_budget): ?> <button type="button" class="btn btn-sm btn-link p-0" wire:click="reloadBudgetFromProfile" wire:confirm="Replace with the enterprise’s profile budget?">Reload</button> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                        <table class="table table-sm mb-0">
                            <thead><tr><th class="small">Category</th><th class="small text-end">Grant (M)</th></tr></thead>
                            <tbody>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $budget; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $bi => $b): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                    <tr <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'bud-'.e($bi).''; ?>wire:key="bud-<?php echo e($bi); ?>">
                                        <td class="small"><?php echo e($b['category']); ?></td>
                                        <td class="text-end small">
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canManage && $editBudget): ?> <input type="number" min="0" step="100" class="form-control form-control-sm text-end" wire:model.blur="budget.<?php echo e($bi); ?>.grant">
                                            <?php else: ?> <?php echo e(number_format((float) ($b['grant'] ?? 0))); ?> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        </td>
                                    </tr>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                            </tbody>
                            <tfoot><tr class="fw-semibold <?php echo e(abs($budgetT['grant'] - $grant) > 0.01 ? 'text-danger' : ''); ?>"><td class="small">Total (approved <?php echo e($m($grant)); ?>)</td><td class="text-end small"><?php echo e(number_format($budgetT['grant'])); ?></td></tr></tfoot>
                        </table>
                        <div class="small text-muted mt-1">Changes to categories, allocations or uses go through change control before they are implemented.</div>
                    </div>
                </div>
            </div>
        </section>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    
    <section class="section-card" id="g6-investment">
        <div class="section-card-head">
            <span class="section-no"><i class="bi bi-briefcase"></i></span>
            <div class="me-auto">
                <h5>Private investment deployed</h5>
                <div class="small text-muted">CAFI grant follows verified private capital. <?php echo e($applicant ? 'Attach proof of payment (bank record / investor remittance) for each investor.' : 'Verify amount, date, investor, receiving account and consistency with the approved structure — and the tranche it backs.'); ?></div>
            </div>
            <span class="pill <?php echo e($verified > 0 ? 'pill-ok' : 'pill-warn'); ?>"><?php echo e($m($verified)); ?> verified</span>
        </div>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->canRecordCapital): ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $this->profileInvestors; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ii => $inv): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <?php $dp = $inv['deployment']; ?>
                <div class="file-row flex-wrap" <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'inv-'.e($ii).''; ?>wire:key="inv-<?php echo e($ii); ?>">
                    <span class="file-row-icon"><i class="bi bi-person-badge"></i></span>
                    <div class="flex-grow-1 small"><div class="fw-semibold text-navy"><?php echo e($inv['name']); ?></div><div class="text-muted"><?php echo e($m($inv['amount'])); ?> <?php echo e($inv['country'] ? '· '.$inv['country'] : ''); ?></div></div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($dp): ?> <span class="pill <?php echo e($CR::DEPLOYMENT_STATUSES[$dp->status][1]); ?>"><?php echo e($CR::DEPLOYMENT_STATUSES[$dp->status][0]); ?></span> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! $dp || $dp->status === 'rejected' || $dp->status === 'verified'): ?>
                        <label class="btn btn-sm btn-outline-primary mb-0"><i class="bi bi-upload me-1"></i> <?php echo e($dp ? 'Attach new proof' : 'Attach proof of payment'); ?>

                            <input type="file" class="d-none" wire:model="investorFiles.<?php echo e($ii); ?>" accept=".pdf,.jpg,.jpeg,.png"></label>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ["investorFiles.$ii"];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-danger small w-100"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <div class="table-responsive">
            <table class="table table-modern align-middle mb-0">
                <thead><tr><th>Investor</th><th class="text-end">Amount</th><th>Date</th><th>Proof</th><th>Status</th></tr></thead>
                <tbody>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $r->deployments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $d): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <tr <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'dep-'.e($d->id).''; ?>wire:key="dep-<?php echo e($d->id); ?>">
                            <td class="small fw-semibold text-navy"><?php echo e($d->investor); ?></td>
                            <td class="text-end small"><?php echo e($m($d->amount)); ?><div class="text-muted"><?php echo e($u($d->amount)); ?></div></td>
                            <td class="small"><?php echo e($d->deployed_on?->format('d M Y')); ?></td>
                            <td class="small"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($d->evidence_path): ?><a href="<?php echo e($file('deployment', $d->id)); ?>" class="text-decoration-none"><i class="bi bi-paperclip"></i> <?php echo e(\Illuminate\Support\Str::limit($d->evidence_name, 28)); ?></a><?php else: ?> — <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></td>
                            <td class="small">
                                <span class="pill <?php echo e($CR::DEPLOYMENT_STATUSES[$d->status][1]); ?>"><?php echo e($CR::DEPLOYMENT_STATUSES[$d->status][0]); ?></span>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($d->status === 'verified'): ?>
                                    <div class="text-muted"><?php echo e($d->verifier?->name); ?> · <?php echo e($d->verified_at?->format('d M')); ?><?php echo e($d->received_account ? ' · into '.$d->received_account : ''); ?><?php echo e($d->tranche ? ' · backs '.$d->tranche->label : ''); ?></div>
                                <?php elseif($d->status === 'rejected' && $d->note): ?> <div class="text-danger"><?php echo e($d->note); ?></div> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($applicant && $d->status !== 'verified' && $d->recorded_by === auth()->id()): ?>
                                    <button type="button" class="btn btn-link btn-sm p-0 text-danger" wire:click="removeDeployment(<?php echo e($d->id); ?>)" wire:confirm="Remove this record?">Remove</button>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </td>
                        </tr>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($d->status === 'submitted' && $this->canVerifyCapital): ?>
                            <tr <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'depv-'.e($d->id).''; ?>wire:key="depv-<?php echo e($d->id); ?>"><td colspan="5" class="bg-light">
                                <div class="row g-2 small">
                                    <div class="col-lg-6">
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $CR::DEPLOYMENT_CHECKS; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $dk => $dl): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                            <label class="form-check mb-0"><input type="checkbox" class="form-check-input" wire:model="dep.<?php echo e($d->id); ?>.checks.<?php echo e($dk); ?>"> <span class="form-check-label"><?php echo e($dl); ?></span></label>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                    </div>
                                    <div class="col-lg-6">
                                        <label class="form-label small mb-0">Received into account</label>
                                        <input type="text" class="form-control form-control-sm mb-1" wire:model="dep.<?php echo e($d->id); ?>.received_account" placeholder="Account number / name">
                                        <label class="form-label small mb-0">Backs tranche</label>
                                        <select class="form-select form-select-sm mb-1" wire:model="dep.<?php echo e($d->id); ?>.tranche">
                                            <option value="">— not linked yet —</option>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $r->tranches; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tt): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?> <option value="<?php echo e($tt->id); ?>"><?php echo e($tt->label); ?> · <?php echo e($m($tt->amount)); ?></option> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                        </select>
                                        <div class="d-flex gap-2">
                                            <button type="button" class="btn btn-sm btn-brand" wire:click="verifyDeployment(<?php echo e($d->id); ?>)"><i class="bi bi-check-lg me-1"></i> Verify</button>
                                            <input type="text" class="form-control form-control-sm" wire:model="rejectNote.<?php echo e($d->id); ?>" placeholder="Reason if not accepted">
                                            <button type="button" class="btn btn-sm btn-outline-danger" wire:click="rejectDeployment(<?php echo e($d->id); ?>)">Not accepted</button>
                                        </div>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ["dep.{$d->id}"];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-danger"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ["rejectNote.{$d->id}"];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-danger"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </div>
                                </div>
                            </td></tr>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        <tr><td colspan="5" class="small text-muted">No investment deployment recorded yet.</td></tr>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($applicant)): ?>
        <section class="section-card" id="g6-step3">
            <div class="section-card-head">
                <span class="section-no">3</span>
                <div class="me-auto">
                    <h5>Confirm readiness for disbursement</h5>
                    <div class="small text-muted">Consolidated evidence → programme and Finance confirmation → outcome. Not cleared while a mandatory condition is outstanding.</div>
                </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($outcome): ?> <span class="pill <?php echo e($CR::OUTCOMES[$outcome][4]); ?>"><?php echo e($CR::OUTCOMES[$outcome][0]); ?></span> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            <ul class="checklist mb-3">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $CR::READINESS_CHECKS; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $rk => $rt): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <li class="<?php echo e(($readiness[$rk] ?? false) ? 'is-done' : 'is-pending'); ?>" <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'rc-'.e($rk).''; ?>wire:key="rc-<?php echo e($rk); ?>">
                        <label class="d-flex align-items-start gap-2 mb-0 w-100">
                            <input type="checkbox" class="form-check-input mt-1" wire:model.live="readiness.<?php echo e($rk); ?>" <?php if(! $canManage): echo 'disabled'; endif; ?>> <span><?php echo e($rt); ?></span>
                        </label>
                    </li>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </ul>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! $r->isCleared()): ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($blockers): ?>
                    <div class="callout callout-warning small mb-3">
                        <div class="fw-semibold mb-1"><i class="bi bi-list-check me-1"></i> Before the transaction can be cleared (<?php echo e(count($blockers)); ?>)</div>
                        <ul class="evidence-list mb-0"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $blockers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $b): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?> <li><?php echo e($b); ?></li> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?></ul>
                    </div>
                <?php else: ?>
                    <div class="callout callout-success small mb-3"><i class="bi bi-check2-circle me-1"></i> Everything is verified and confirmed — record <strong>Cleared for disbursement</strong>.</div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <div class="row g-2 mb-3">
                <div class="col-md-6">
                    <div class="border rounded-3 p-2 small h-100">
                        <div class="fw-semibold text-navy">Programme confirmation</div>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($r->readiness_pmu_at): ?> <div class="text-success"><i class="bi bi-check-circle me-1"></i><?php echo e($r->readinessPmu?->name); ?> · <?php echo e($r->readiness_pmu_at->format('d M Y H:i')); ?></div>
                        <?php elseif($canManage): ?>
                            <button type="button" class="btn btn-sm btn-outline-primary mt-1" wire:click="confirmProgramme" wire:confirm="Confirm the transaction file is complete and ready for disbursement? Finance will be asked to confirm.">Confirm & send to Finance</button>
                        <?php else: ?> <div class="text-muted">Pending</div> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="border rounded-3 p-2 small h-100">
                        <div class="fw-semibold text-navy">Finance confirmation</div>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($r->readiness_finance_at): ?> <div class="text-success"><i class="bi bi-check-circle me-1"></i><?php echo e($r->readinessFinance?->name); ?> · <?php echo e($r->readiness_finance_at->format('d M Y H:i')); ?></div>
                        <?php elseif($this->isFinance && ! $r->isCleared() && $r->readiness_pmu_at): ?>
                            <button type="button" class="btn btn-sm btn-outline-primary mt-1" wire:click="confirmFinance" wire:confirm="Confirm disbursement readiness from Finance’s side?">Confirm (Finance)</button>
                        <?php else: ?> <div class="text-muted"><?php echo e($r->readiness_pmu_at ? 'Waiting for Finance' : 'After the programme confirmation'); ?></div> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                </div>
            </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['readiness'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-danger small mb-2"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canManage): ?>
                <div class="choice-group cols-4 <?php $__errorArgs = ['decision.outcome'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $CR::OUTCOME_CHOICES; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ok): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <?php [$oLabel, $oMean, $oNext, $oIcon] = $CR::OUTCOMES[$ok]; ?>
                        <label class="choice choice--card">
                            <input type="radio" name="g6-outcome" value="<?php echo e($ok); ?>" wire:model.live="decision.outcome">
                            <span class="choice-box"><span class="choice-icon"><i class="bi <?php echo e($oIcon); ?>"></i></span>
                                <span><span class="d-block fw-semibold"><?php echo e($oLabel); ?></span><span class="d-block small text-muted lh-sm mt-1"><?php echo e($oMean); ?></span>
                                <span class="d-block small text-brand mt-1"><i class="bi bi-arrow-right-short"></i><?php echo e($oNext); ?></span></span></span>
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
                <div class="row g-3 mt-1">
                    <div class="col-md-9">
                        <label class="form-label small fw-semibold">Comments <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($decision['outcome'] ?? null) && $decision['outcome'] !== 'cleared'): ?> <span class="req">*</span> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></label>
                        <textarea rows="2" class="form-control <?php $__errorArgs = ['decision.comments'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model.blur="decision.comments" placeholder="What is outstanding or must be corrected, who is responsible, by when (sent to the enterprise)"></textarea>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['decision.comments'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Date</label>
                        <input type="date" class="form-control" wire:model.blur="decision.on" max="<?php echo e(now()->toDateString()); ?>">
                    </div>
                </div>
                <div class="d-flex mt-3 pt-3 border-top">
                    <button type="button" class="btn btn-brand ms-auto" wire:click="recordOutcome" wire:loading.attr="disabled" wire:target="recordOutcome"
                            wire:confirm="Record this Gate 6 outcome? The enterprise will be e-mailed.">
                        <i class="bi bi-send me-1"></i> Record outcome
                    </button>
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($r->history): ?>
                <details class="evidence mt-3">
                    <summary><i class="bi bi-clock-history me-1"></i> History (<?php echo e(count($r->history)); ?>)</summary>
                    <div class="p-2 small">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = array_reverse($r->history); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $h): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <div class="mb-1"><strong><?php echo e($CR::OUTCOMES[$h['outcome']][0] ?? ucfirst(str_replace('_', ' ', $h['outcome']))); ?></strong> · <?php echo e(\Illuminate\Support\Carbon::parse($h['on'])->format('d M Y')); ?> · <?php echo e($h['by'] ?? ''); ?>

                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! empty($h['comments'])): ?> <div class="text-muted"><?php echo e($h['comments']); ?></div> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </div>
                </details>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </section>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    
    <section class="section-card" id="g6-step4">
        <div class="section-card-head">
            <span class="section-no">4</span>
            <div class="me-auto">
                <h5>Onboarding &amp; obligations after disbursement</h5>
                <div class="small text-muted">The enterprise understands its obligations, the monitoring arrangements are in place and the baseline is recorded.</div>
            </div>
            <span class="pill <?php echo e($r->onboarding_confirmed_at ? 'pill-ok' : 'pill-warn'); ?>"><?php echo e($r->onboarding_confirmed_at ? 'Briefed '.$r->onboarding_confirmed_at->format('d M Y') : 'Not confirmed'); ?></span>
        </div>

        <div class="row g-3">
            <div class="col-lg-6">
                <ul class="checklist mb-2">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $CR::ONBOARDING_ITEMS; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ok => $ol): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <li class="<?php echo e(($onboarding['items'][$ok] ?? false) ? 'is-done' : 'is-pending'); ?>" <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'ob-'.e($ok).''; ?>wire:key="ob-<?php echo e($ok); ?>">
                            <label class="d-flex align-items-start gap-2 mb-0 w-100"><input type="checkbox" class="form-check-input mt-1" wire:model.live="onboarding.items.<?php echo e($ok); ?>" <?php if(! $canManage): echo 'disabled'; endif; ?>> <span><?php echo e($ol); ?></span></label>
                        </li>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </ul>
                <div class="small text-muted">Reporting: <?php echo e($CR::FREQUENCIES[$onboarding['frequency']] ?? '—'); ?><?php echo e($onboarding['first_report_on'] ? ' · first report '.\Illuminate\Support\Carbon::parse($onboarding['first_report_on'])->format('d M Y') : ''); ?> · Contact: <?php echo e($onboarding['contact'] ?: '—'); ?></div>
            </div>
            <div class="col-lg-6">
                <div class="border rounded-3 p-3">
                    <div class="fw-semibold text-navy small mb-2"><i class="bi bi-graph-up me-1"></i> Baseline business &amp; employment</div>
                    <div class="row g-2">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $CR::BASELINE_FIELDS; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $bk => $bl): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <div class="col-6">
                                <label class="form-label small mb-0"><?php echo e($bl); ?></label>
                                <input type="number" min="0" class="form-control form-control-sm" wire:model.blur="baseline.<?php echo e($bk); ?>" <?php if(! $canManage): echo 'disabled'; endif; ?>>
                            </div>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        <div class="col-6">
                            <label class="form-label small mb-0">As at</label>
                            <input type="date" class="form-control form-control-sm" wire:model.blur="baseline.as_of" <?php if(! $canManage): echo 'disabled'; endif; ?>>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['onboarding.contact'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-danger small mt-2"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <div class="d-flex flex-wrap gap-2 align-items-center mt-3 pt-3 border-top">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($r->summary_sent_at): ?> <span class="small text-muted"><i class="bi bi-envelope-check me-1"></i> Summary of obligations sent <?php echo e($r->summary_sent_at->format('d M Y')); ?></span> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! $applicant && auth()->user()->can('gate5.verify') && $this->inGate): ?>
                <button type="button" class="btn btn-sm btn-outline-secondary ms-auto" wire:click="sendSummary" wire:confirm="E-mail the summary of obligations (onboarding record) to the enterprise?"><i class="bi bi-envelope me-1"></i> <?php echo e($r->summary_sent_at ? 'Send summary again' : 'Send summary of obligations'); ?></button>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->canConfirmOnboarding): ?>
                <button type="button" class="btn btn-sm btn-brand <?php echo e($applicant ? 'ms-auto' : ''); ?>" wire:click="confirmOnboarding"
                        wire:confirm="<?php echo e($applicant ? 'Confirm that you have been briefed on your obligations under the Grant Agreement, the reporting requirements and the monitoring arrangements?' : 'Record that the enterprise was briefed on its obligations?'); ?>">
                    <i class="bi bi-check2-square me-1"></i> <?php echo e($applicant ? 'I confirm I have been briefed on my obligations' : 'Record briefing'); ?>

                </button>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    </section>

    
    <section class="section-card" id="g7">
        <div class="section-card-head">
            <span class="section-no">G7</span>
            <div class="me-auto">
                <h5>Grant disbursement</h5>
                <div class="small text-muted">A financial control point, not a further appraisal: each payment is released only against verified, eligible private investment.</div>
            </div>
            <span class="pill <?php echo e($r->isFullyDisbursed() ? 'pill-ok' : ($r->isCleared() ? 'pill-warn' : 'pill-muted')); ?>">
                <?php echo e($r->isFullyDisbursed() ? 'Fully disbursed' : ($r->isCleared() ? $m($r->releasedAmount()).' of '.$m($grant).' released' : 'Opens when cleared')); ?>

            </span>
        </div>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($applicant)): ?>
            <?php $reqs = $r->disbursementRequirements(); ?>
            <div class="row g-3 mb-3">
                <div class="col-lg-7">
                    <div class="table-responsive">
                        <table class="table table-modern table-sm align-middle mb-0">
                            <thead><tr><th>Requirement</th><th>Verification</th><th class="text-center">✓</th></tr></thead>
                            <tbody>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $reqs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$rq, $rv, $rok]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                    <tr><td class="small fw-semibold text-navy"><?php echo e($rq); ?></td><td class="small text-muted"><?php echo e($rv); ?></td>
                                        <td class="text-center"><i class="bi <?php echo e($rok ? 'bi-check-circle-fill text-success' : 'bi-x-circle text-danger'); ?>"></i></td></tr>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="border rounded-3 p-3 small h-100">
                        <div class="fw-semibold text-navy mb-2"><i class="bi bi-shield-check me-1"></i> Programme limits</div>
                        <dl class="row mb-0">
                            <dt class="col-7 fw-normal text-muted">CAFI grant received by the enterprise</dt><dd class="col-5 text-end mb-1"><?php echo e($m($controls['enterprise_received'])); ?></dd>
                            <dt class="col-7 fw-normal text-muted">Approved for the enterprise (all grants)</dt><dd class="col-5 text-end mb-1"><?php echo e($m($controls['enterprise_approved'])); ?></dd>
                            <dt class="col-7 fw-normal text-muted">Approved transaction grant</dt><dd class="col-5 text-end mb-1"><?php echo e($m($controls['approved'])); ?></dd>
                            <dt class="col-7 fw-normal text-muted">Already disbursed</dt><dd class="col-5 text-end mb-1"><?php echo e($m($controls['disbursed'])); ?></dd>
                            <dt class="col-7 fw-normal text-muted">Undisbursed balance</dt><dd class="col-5 text-end mb-1"><?php echo e($m($controls['undisbursed'])); ?></dd>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $controls['investors']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ix): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <dt class="col-7 fw-normal text-muted">Investor exposure · <?php echo e($ix['name']); ?></dt>
                                <dd class="col-5 text-end mb-1 <?php echo e($controls['investor_cap'] !== null && $ix['exposure'] > $controls['investor_cap'] ? 'text-danger' : ''); ?>"><?php echo e($m($ix['exposure'])); ?></dd>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </dl>
                    </div>
                </div>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        
        <div class="table-responsive">
            <table class="table table-modern align-middle mb-2">
                <thead><tr><th style="width: 9%">Tranche</th><th style="width: 15%" class="text-end">Grant</th><th>Trigger / matching</th><th style="width: 30%">Payment</th><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->canEditTranches): ?><th></th><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></tr></thead>
                <tbody>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $r->tranches; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ti => $t): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <?php
                            $mt    = $r->matchingFor($t);
                            $stepT = $t->step();
                            $row   = collect($tranches)->search(fn ($x) => $x['id'] === $t->id);
                            $edit  = $this->canEditTranches && ! $t->isReleased() && ! $t->requested_at;
                            $oc    = $CR::TRANCHE_OUTCOMES[$t->outcome] ?? null;
                        ?>
                        <tr <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'tr-'.e($t->id).''; ?>wire:key="tr-<?php echo e($t->id); ?>" class="<?php echo e($stepT === 'stopped' ? 'is-flagged' : ''); ?>">
                            <td><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($edit): ?><input type="text" class="form-control form-control-sm" wire:model.blur="tranches.<?php echo e($row); ?>.label" maxlength="20"><?php else: ?> <strong><?php echo e($t->label); ?></strong> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></td>
                            <td class="text-end">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($edit): ?><input type="number" min="0" step="1000" class="form-control form-control-sm text-end <?php $__errorArgs = ["tranches.$row.amount"];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model.blur="tranches.<?php echo e($row); ?>.amount">
                                <?php else: ?> <?php echo e($m($t->amount)); ?> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <div class="small text-muted"><?php echo e($u($t->amount)); ?></div>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ["tranches.$row.amount"];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-danger small"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </td>
                            <td class="small">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($edit): ?><input type="text" class="form-control form-control-sm mb-1" wire:model.blur="tranches.<?php echo e($row); ?>.trigger" maxlength="250" placeholder="Release trigger / milestone">
                                <?php elseif($t->trigger): ?> <div><?php echo e($t->trigger); ?></div> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($applicant)): ?>
                                    <div class="<?php echo e($mt['ok'] ? 'text-success' : 'text-danger'); ?>">
                                        <i class="bi <?php echo e($mt['ok'] ? 'bi-check-circle' : 'bi-exclamation-triangle'); ?> me-1"></i>
                                        Matching: grant after this <?php echo e($m($mt['after'])); ?> needs <?php echo e($m($mt['needed'])); ?> private capital · verified <?php echo e($m($mt['verified'])); ?>

                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($mt['linked'] > 0): ?> · <?php echo e($m($mt['linked'])); ?> linked to <?php echo e($t->label); ?> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </div>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </td>
                            <td class="small">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($t->isReleased()): ?>
                                    <span class="pill pill-ok"><i class="bi bi-check-lg"></i> Paid <?php echo e($t->released_on->format('d M Y')); ?></span>
                                    <div class="text-muted">Ref <?php echo e($t->reference); ?><?php echo e($t->instruction_ref ? ' · instruction '.$t->instruction_ref : ''); ?></div>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($t->proof_path): ?> <a href="<?php echo e($file('tranche', $t->id)); ?>" class="text-decoration-none"><i class="bi bi-file-earmark-check"></i> Confirmation of payment</a> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($applicant)): ?><div class="text-muted">Requested <?php echo e($t->requester?->name ?? '—'); ?> · approved <?php echo e($t->approver?->name ?? '—'); ?> · paid <?php echo e($t->releaser?->name ?? '—'); ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <?php elseif($applicant): ?>
                                    <span class="pill <?php echo e($oc[3] ?? 'pill-muted'); ?>"><?php echo e($oc[0] ?? ($r->isCleared() ? 'Scheduled' : 'After clearance')); ?></span>
                                <?php else: ?>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($oc): ?> <span class="pill <?php echo e($oc[3]); ?>"><?php echo e($oc[0]); ?></span> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($t->outcome_note): ?> <div class="text-muted"><?php echo e($t->outcome_note); ?></div> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($stepT === 'requested'): ?> <div class="text-muted">Requested by <?php echo e($t->requester?->name); ?> <?php echo e($t->requested_at->diffForHumans()); ?> — awaiting payment approval</div> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($stepT === 'approved'): ?> <div class="text-muted">Approved by <?php echo e($t->approver?->name); ?> <?php echo e($t->approved_at->diffForHumans()); ?> — Finance to pay</div> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                                    
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->canRequestPayment && in_array($stepT, ['planned', 'held'], true)): ?>
                                        <button type="button" class="btn btn-sm btn-outline-primary mt-1" wire:click="requestPayment(<?php echo e($t->id); ?>)" wire:confirm="Matching investment verified for <?php echo e($t->label); ?> — request payment approval?">
                                            <i class="bi bi-send me-1"></i> Request payment approval</button>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->canApprovePayment && $stepT === 'requested'): ?>
                                        <div class="mt-1">
                                            <input type="text" class="form-control form-control-sm mb-1 <?php $__errorArgs = ["payNote.{$t->id}"];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model="payNote.<?php echo e($t->id); ?>" placeholder="Note (required to hold or stop)">
                                            <div class="d-flex flex-wrap gap-1">
                                                <button type="button" class="btn btn-sm btn-brand" wire:click="approvePayment(<?php echo e($t->id); ?>)" wire:confirm="Approve payment of <?php echo e($t->label); ?> (<?php echo e($m($t->amount)); ?>)?"><i class="bi bi-check2 me-1"></i> Approve</button>
                                                <button type="button" class="btn btn-sm btn-outline-warning" wire:click="holdPayment(<?php echo e($t->id); ?>, 'pending')">Hold</button>
                                                <button type="button" class="btn btn-sm btn-outline-danger" wire:click="holdPayment(<?php echo e($t->id); ?>, 'not_eligible')" wire:confirm="Mark not eligible? The payment is stopped and the transaction escalated.">Not eligible</button>
                                            </div>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ["payNote.{$t->id}"];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-danger"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        </div>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->isFinance && $this->cleared && $stepT === 'approved' && isset($release[$t->id])): ?>
                                        <div class="row g-1 mt-1">
                                            <div class="col-7"><input type="text" class="form-control form-control-sm <?php $__errorArgs = ["release.{$t->id}.instruction_ref"];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model="release.<?php echo e($t->id); ?>.instruction_ref" placeholder="Instruction ref."></div>
                                            <div class="col-5"><input type="date" class="form-control form-control-sm" wire:model="release.<?php echo e($t->id); ?>.instruction_on" max="<?php echo e(now()->toDateString()); ?>"></div>
                                            <div class="col-7"><input type="text" class="form-control form-control-sm <?php $__errorArgs = ["release.{$t->id}.reference"];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model="release.<?php echo e($t->id); ?>.reference" placeholder="Payment ref."></div>
                                            <div class="col-5"><input type="date" class="form-control form-control-sm <?php $__errorArgs = ["release.{$t->id}.on"];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model="release.<?php echo e($t->id); ?>.on" max="<?php echo e(now()->toDateString()); ?>"></div>
                                            <div class="col-12"><input type="file" class="form-control form-control-sm <?php $__errorArgs = ["releaseProof.{$t->id}"];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model="releaseProof.<?php echo e($t->id); ?>" accept=".pdf,.jpg,.jpeg,.png" title="Confirmation of payment"></div>
                                            <div class="col-12 d-flex"><button type="button" class="btn btn-sm btn-brand ms-auto" wire:click="releaseTranche(<?php echo e($t->id); ?>)" wire:loading.attr="disabled" wire:target="releaseTranche(<?php echo e($t->id); ?>),releaseProof.<?php echo e($t->id); ?>"
                                                    wire:confirm="Record the payment of <?php echo e($t->label); ?> (<?php echo e($m($t->amount)); ?>) to the verified account?"><i class="bi bi-cash me-1"></i> Record payment</button></div>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = ["release.{$t->id}.instruction_ref", "release.{$t->id}.reference", "release.{$t->id}.on", "releaseProof.{$t->id}"]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ek): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = [$ek];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="col-12 text-danger"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                        </div>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ["pay.{$t->id}"];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-danger mt-1"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </td>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->canEditTranches): ?>
                                <td><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($edit): ?><button type="button" class="btn btn-icon btn-icon-danger" wire:click="removeTranche(<?php echo e($t->id); ?>)" title="Remove"><i class="bi bi-x-lg"></i></button><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></td>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </tr>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        <tr><td colspan="5" class="small text-muted">No tranches yet. <?php echo e($applicant ? '' : 'Add the first one — the grant is linked to the corresponding investment tranche.'); ?></td></tr>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->canEditTranches): ?>
            <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="addTranche"><i class="bi bi-plus-lg me-1"></i> Add tranche</button>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <details class="evidence mt-3">
            <summary><i class="bi bi-info-circle me-1"></i> Gate 7 outcomes</summary>
            <dl class="evidence-grid">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $CR::TRANCHE_OUTCOMES; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$tl, $tm, $tn]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?> <dt><?php echo e($tl); ?></dt><dd><?php echo e($tm); ?> → <?php echo e($tn); ?></dd> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </dl>
            <div class="p-2 small text-muted">A failed or delayed payment does not end the transaction — it stays pending until the requirement is resolved.</div>
        </details>
    </section>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div><?php /**PATH C:\laragon\www\co-investment-facility\storage\framework\views/livewire/views/01b7991d.blade.php ENDPATH**/ ?>