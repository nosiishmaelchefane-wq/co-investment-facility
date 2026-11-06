<?php
use App\Models\CommitteeReview;
use App\Models\EnterpriseProfile;
use App\Models\ExpressionOfInterest;
use App\Models\GrantReadiness;
use App\Notifications\EoiGrantReadinessUpdate;
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
    $G        = \App\Models\GrantReadiness::class;
    $CR       = \App\Models\CommitteeReview::class;
    $P        = \App\Models\EnterpriseProfile::class;
    $eoi      = $this->eoi;
    $g        = $this->gr;
    $review   = $this->review;
    $stage    = $this->stage;
    $decided  = (bool) $g?->isDecided();
    $canG     = $this->canGrants;
    $canH     = $this->canHub;
    $canD     = $this->canDirector;
    $p        = $this->isApplicant ? null : $this->profile;
    $facts    = $this->isApplicant || ! $this->gateOpen ? [] : $this->facts;

    $rate = max((float) config('cif.usd_to_lsl', 18.00), 0.0001);
    $m    = fn ($v) => $v === null || $v === '' ? '—' : 'M '.number_format((float) $v);
    $u    = fn ($v) => $v === null || $v === '' ? '' : 'US$ '.number_format((float) $v / $rate);

    $private = $g?->private_amount ?? $review?->private_amount;
    $grant   = $g?->grant_amount ?? $review?->grant_amount;

    $gmDone  = (bool) $g?->gm_submitted_at;
    $hubDone = (bool) $g?->hub_decided_at;
    $dirDone = (bool) $g?->director_decided_at;
    $stepState = fn (bool $done, ?string $decision, bool $current) => match (true) {
        $done && $decision === 'returned' => 'returned',
        $done                             => 'done',
        $current                          => 'current',
        default                           => 'todo',
    };
    $path = [
        ['Gate 3 · Committee recommendation', 'done'],
        ['Grants Management review', $stepState($gmDone, null, $stage === 'grants')],
        ['Hub Manager', $stepState($hubDone, $g?->hub_decision, $stage === 'hub')],
        ['PMU Director of Implementation', $stepState($dirDone, $g?->director_decision, $stage === 'director')],
        ['Gate 5 · Final authorisation', $g?->isApproved() ? 'current' : 'todo'],
    ];
    $pathIcon = ['done' => 'bi-check-lg', 'current' => 'bi-hourglass-split', 'returned' => 'bi-arrow-return-left', 'todo' => 'bi-circle'];

    $headPill = match (true) {
        (bool) $g?->isApproved() => ['Approved to proceed', 'pill-ok', 'bi-patch-check'],
        $stage === 'hub'         => ['With the Hub Manager', 'pill-warn', 'bi-hourglass-split'],
        $stage === 'director'    => ['With the PMU Director', 'pill-warn', 'bi-hourglass-split'],
        default                  => ['Grants Management review', 'pill-warn', 'bi-hourglass-split'],
    };
    $budget  = $p?->budgetRows() ?? [];
    $btotals = $P::sumBudget($budget);
?>


<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! $this->gateOpen): ?>
    <section class="section-card">
        <div class="section-card-head">
            <span class="section-no">G4</span>
            <h5 class="me-auto">Grant readiness review</h5>
            <span class="pill pill-muted"><i class="bi bi-lock-fill"></i> Locked</span>
        </div>
        <p class="small text-muted mb-2">Opens once the Investment Committee recommends the transaction (Approve or Conditional approval at Gate 3). An internal control point before final authorisation:</p>
        <ul class="evidence-list small text-muted mb-0">
            <li><strong class="text-navy">Grants Management Representative</strong> — budget, eligible expenditure, bank account and documents; Grant Readiness Memo and Draft Grant Agreement</li>
            <li><strong class="text-navy">Hub Manager</strong> — approves the file for submission</li>
            <li><strong class="text-navy">PMU Director of Implementation</strong> — approves it to proceed to Gate 5 · Final authorisation</li>
        </ul>
    </section>


<?php elseif($this->isApplicant): ?>
    <section class="section-card">
        <div class="section-card-head">
            <span class="section-no">G4</span>
            <h5 class="me-auto">Grant readiness</h5>
            <span class="pill <?php echo e($g?->isApproved() ? 'pill-ok' : 'pill-warn'); ?>"><i class="bi <?php echo e($g?->isApproved() ? 'bi-check2-circle' : 'bi-hourglass-split'); ?>"></i> <?php echo e($g?->isApproved() ? 'Complete' : 'In progress'); ?></span>
        </div>
        <p class="small text-muted mb-0">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($g?->isApproved()): ?> Your grant file has been prepared and is with CAFI management for final authorisation.
            <?php else: ?> The LEHSFF team is preparing your grant file: reviewing the grant budget and your bank account details, and drafting the Grant Agreement. They may contact you if anything is needed. <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </p>
    </section>


<?php else: ?>
    <div class="g2-path mb-3">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $path; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$pLabel, $pState]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
            <span class="g2-path-step <?php echo e(in_array($pState, ['done', 'current'], true) ? 'is-'.$pState : ''); ?> <?php echo e($pState === 'returned' ? 'text-warning-emphasis' : ''); ?>">
                <i class="bi <?php echo e($pathIcon[$pState]); ?>"></i> <?php echo e($pLabel); ?>

            </span>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
    </div>

    
    <section class="section-card">
        <div class="section-card-head">
            <span class="section-no">G4</span>
            <div class="me-auto">
                <h5>Grant readiness review <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($round > 1): ?> <span class="pill pill-muted ms-1">Round <?php echo e($round); ?></span> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></h5>
                <div class="small text-muted">Is the transaction sufficiently prepared — budget, bank account and documents — to proceed to final authorisation?</div>
            </div>
            <span class="pill <?php echo e($headPill[1]); ?>"><i class="bi <?php echo e($headPill[2]); ?>"></i> <?php echo e($headPill[0]); ?></span>
        </div>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canG || $canH || $canD): ?>
            <div class="small text-muted mb-3">
                <i class="bi bi-cloud-check me-1"></i> Changes are saved automatically. <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($savedAt): ?> <span class="text-accent">Last saved <?php echo e($savedAt); ?>.</span> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <span wire:loading class="ms-1"><span class="spinner-border spinner-border-sm"></span></span>
            </div>
        <?php elseif($this->lockReason): ?>
            <div class="callout callout-warning small mb-3"><i class="bi bi-lock me-1"></i> <strong>Read-only.</strong> <?php echo e($this->lockReason); ?></div>
        <?php elseif(! $decided && $eoi->status === 'grant_readiness'): ?>
            <div class="small text-muted mb-3"><i class="bi bi-eye me-1"></i> Read-only — <?php echo e($G::STAGES[$stage][1] ?? 'the reviewer'); ?> is working on this step.</div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($g?->resubmission_note): ?>
            <div class="callout callout-warning small mb-3">
                <div class="fw-semibold mb-1"><i class="bi bi-arrow-return-left me-1"></i> Returned in round <?php echo e($round - 1); ?> — to resolve</div>
                <?php echo e($g->resubmission_note); ?>

            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <div class="detail-grid">
            <div><dt>Transaction reference</dt><dd><span class="chip"><?php echo e($eoi->reference); ?></span></dd></div>
            <div><dt>Enterprise</dt><dd><?php echo e($p?->registered_name ?: ($eoi->business_name ?: '—')); ?></dd></div>
            <div><dt>Sector</dt><dd><?php echo e($p?->sectorLabel() ?? '—'); ?></dd></div>
            <div><dt>Committee decision</dt>
                <dd><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($review): ?>
                        <span class="pill <?php echo e($CR::OUTCOMES[$review->outcome][4]); ?>"><?php echo e($CR::OUTCOMES[$review->outcome][0]); ?></span>
                        <span class="small text-muted">· <?php echo e($review->decided_at->format('d M Y')); ?></span>
                    <?php else: ?> — <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></dd></div>
            <div><dt>Private investment</dt><dd><?php echo e($m($private)); ?> <div class="small text-muted"><?php echo e($u($private)); ?></div></dd></div>
            <div><dt>Proposed CAFI grant</dt><dd><?php echo e($m($grant)); ?> <div class="small text-muted"><?php echo e($u($grant)); ?></div></dd></div>
        </div>

        <div class="d-flex flex-wrap gap-2 mt-3">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($g): ?>
                <a href="<?php echo e(route('grant-readiness.memo', $g)); ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-file-earmark-pdf me-1"></i> Grant Readiness Memo (PDF)</a>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($g?->agreement_path): ?>
                <a href="<?php echo e(route('grant-readiness.file', [$g, 'agreement'])); ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-file-earmark-text me-1"></i> Draft Grant Agreement</a>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>

        <div class="callout small mt-3 mb-0">
            <i class="bi bi-info-circle me-1"></i>
            An internal control point: grant compliance, budget acceptability, disbursement readiness and a complete file. It does not repeat the
            Investment Committee's assessment, and completing it is <strong>not</strong> grant authorisation or approval for payment.
        </div>
    </section>

    
    <section class="section-card" id="g4-grants">
        <div class="section-card-head">
            <span class="section-no">1</span>
            <div class="me-auto">
                <h5>Grants Management review</h5>
                <div class="small text-muted">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($gmDone): ?> Submitted by <?php echo e($g->grantsOfficer?->name ?? '—'); ?> on <?php echo e($g->gm_submitted_at->format('d M Y H:i')); ?>.
                    <?php else: ?> Grants Management Representative — record each check as a concise confirmation, exception or action required. Detail stays in the transaction file. <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($gmDone): ?>
                <span class="pill pill-ok"><i class="bi bi-check-lg"></i> Submitted</span>
            <?php else: ?>
                <span class="pill pill-warn"><i class="bi bi-hourglass-split"></i> In review</span>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>

        
        <div class="row g-2 mb-3">
            <div class="col-md-4">
                <div class="border rounded-3 p-2 h-100 small">
                    <div class="detail-label mb-1">Matching formula</div>
                    Max <?php echo e($P::grantRatioLabel()); ?> of private investment = <strong><?php echo e($m($facts['matching_max'] ?? null)); ?></strong>
                    <div class="<?php echo e(($facts['matching_ok'] ?? false) ? 'text-success' : 'text-danger'); ?>"><i class="bi <?php echo e(($facts['matching_ok'] ?? false) ? 'bi-check-circle' : 'bi-x-circle'); ?> me-1"></i><?php echo e(($facts['matching_ok'] ?? false) ? 'Grant complies' : 'Grant above the maximum'); ?></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="border rounded-3 p-2 h-100 small">
                    <div class="detail-label mb-1">Cumulative grant limit</div>
                    Earlier grants <?php echo e($m($facts['previous'] ?? 0)); ?> + this <?php echo e($m($grant)); ?> = <strong><?php echo e($m($facts['cumulative'] ?? null)); ?></strong> of <?php echo e($m($facts['cap'] ?? null)); ?>

                    <div class="text-muted"><?php echo e($u($facts['cap'] ?? null)); ?> limit</div>
                    <div class="<?php echo e(($facts['cap_ok'] ?? false) ? 'text-success' : 'text-danger'); ?>"><i class="bi <?php echo e(($facts['cap_ok'] ?? false) ? 'bi-check-circle' : 'bi-x-circle'); ?> me-1"></i><?php echo e(($facts['cap_ok'] ?? false) ? 'Within the limit' : 'Exceeds the limit'); ?></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="border rounded-3 p-2 h-100 small">
                    <?php
                        $lsPct  = $form['lesotho_pct'] ?? null;
                        $lsOut  = $p?->grant_outside_lesotho;
                        $lsWhy  = $p?->grant_outside_justification;
                        $lsMin  = (int) $G::minLesothoPct();
                    ?>
                    <div class="detail-label mb-1">Spending in Lesotho <span class="text-muted fw-normal">(from the enterprise profile)</span></div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(is_numeric($lsPct)): ?>
                        <div><strong><?php echo e(rtrim(rtrim(number_format((float) $lsPct, 1), '0'), '.')); ?>%</strong> of the grant in Lesotho · <?php echo e($m($lsOut)); ?> outside</div>
                        <div class="<?php echo e(($facts['lesotho_ok'] ?? false) ? 'text-success' : 'text-danger'); ?>">
                            <i class="bi <?php echo e(($facts['lesotho_ok'] ?? false) ? 'bi-check-circle' : 'bi-exclamation-triangle'); ?> me-1"></i>
                            <?php echo e(($facts['lesotho_ok'] ?? false) ? 'Meets' : 'Below'); ?> the <?php echo e($lsMin); ?>% minimum
                        </div>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($lsWhy): ?>
                            <details class="mt-1"><summary class="text-primary" style="cursor:pointer">Enterprise's justification</summary>
                                <div class="text-muted mt-1" style="white-space: pre-line"><?php echo e($lsWhy); ?></div></details>
                        <?php elseif(! ($facts['lesotho_ok'] ?? false)): ?>
                            <div class="text-danger mt-1">Not justified by the enterprise yet.</div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php else: ?>
                        <div class="text-muted">Not declared yet — the enterprise enters it in its profile (Business section, after the budget). Minimum <?php echo e($lsMin); ?>% in Lesotho.</div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>
        </div>

        
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($btotals['grant'] > 0 || $btotals['private'] > 0): ?>
            <details class="evidence mb-3">
                <summary><i class="bi bi-pie-chart me-1"></i> Grant utilisation budget (enterprise profile) — CAFI grant <?php echo e($m($btotals['grant'])); ?> · investor <?php echo e($m($btotals['private'])); ?></summary>
                <div class="table-responsive">
                    <table class="table table-modern table-sm align-middle mb-0">
                        <thead><tr><th>Category</th><th class="text-end">Investor (M)</th><th class="text-end">CAFI grant (M)</th></tr></thead>
                        <tbody>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $budget; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <?php if((float) ($row['private'] ?? 0) + (float) ($row['grant'] ?? 0) <= 0): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?><?php continue; ?><?php endif; ?>
                                <tr><td class="small"><?php echo e($row['category']); ?></td>
                                    <td class="text-end small"><?php echo e(number_format((float) ($row['private'] ?? 0))); ?></td>
                                    <td class="text-end small"><?php echo e(number_format((float) ($row['grant'] ?? 0))); ?></td></tr>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </tbody>
                        <tfoot><tr class="fw-semibold"><td>Total</td><td class="text-end"><?php echo e(number_format($btotals['private'])); ?></td><td class="text-end"><?php echo e(number_format($btotals['grant'])); ?></td></tr></tfoot>
                    </table>
                </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($p?->document('budget')): ?>
                    <div class="p-2 small"><a href="<?php echo e(route('enterprise-documents.download', $p->document('budget'))); ?>" class="text-decoration-none"><i class="bi bi-download me-1"></i>Operational budget (uploaded)</a></div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </details>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        
        <div class="table-responsive">
            <table class="table table-modern align-middle mb-3 dd-table">
                <thead><tr><th>The review confirms that</th><th style="width: 19%">Result</th><th style="width: 32%">Note (required for an exception)</th></tr></thead>
                <tbody>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $G::CHECKS; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ck => [$cTitle, $cText]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <?php $cs = $form['checks'][$ck]['status'] ?? null; ?>
                        <tr <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'grc-'.e($ck).''; ?>wire:key="grc-<?php echo e($ck); ?>" class="<?php echo e($cs === 'action' ? 'is-flagged' : ''); ?>">
                            <td class="small"><div class="fw-semibold text-navy"><?php echo e($cTitle); ?></div><div class="text-muted"><?php echo e($cText); ?></div></td>
                            <td>
                                <select class="form-select form-select-sm <?php echo e(['confirmed' => 'is-complete', 'exception' => 'is-clarification', 'action' => 'is-no'][$cs] ?? ''); ?> status-select" wire:model.live="form.checks.<?php echo e($ck); ?>.status" <?php if(! $canG): echo 'disabled'; endif; ?>>
                                    <option value="">—</option>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $G::CHECK_STATUSES; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $sk => [$sl]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?> <option value="<?php echo e($sk); ?>"><?php echo e($sl); ?></option> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                </select>
                            </td>
                            <td><input type="text" class="form-control form-control-sm" wire:model.blur="form.checks.<?php echo e($ck); ?>.note" maxlength="500"
                                       placeholder="<?php echo e($ck === 'budget' ? 'Justify any spending outside Lesotho' : 'Short note'); ?>" <?php if(! $canG): echo 'disabled'; endif; ?>></td>
                        </tr>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </tbody>
            </table>
        </div>

        
        <div class="g2-sub">
            <div class="g2-sub-head"><i class="bi bi-bank me-1"></i> Bank account verification <span class="text-muted fw-normal">— entered by the enterprise in its profile; check it against the evidence</span></div>
            <?php
                $bk        = $form['bank'] ?? [];
                $bkFilled  = filled($bk['bank'] ?? null) && filled($bk['account_name'] ?? null) && filled($bk['account_number'] ?? null);
                $bankDoc   = $p?->document('bank_confirmation');
                $nameMatch = $p?->registered_name && filled($bk['account_name'] ?? null)
                    && \Illuminate\Support\Str::lower(trim($bk['account_name'])) === \Illuminate\Support\Str::lower(trim($p->registered_name));
            ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($bkFilled): ?>
                <div class="detail-grid mb-2">
                    <div><dt>Bank</dt><dd><?php echo e($bk['bank']); ?><?php echo e(filled($bk['branch'] ?? null) ? ' · '.$bk['branch'] : ''); ?></dd></div>
                    <div><dt>Account name</dt><dd><?php echo e($bk['account_name']); ?>

                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($p?->registered_name): ?>
                            <div class="small <?php echo e($nameMatch ? 'text-success' : 'text-warning'); ?>"><i class="bi <?php echo e($nameMatch ? 'bi-check-circle' : 'bi-exclamation-triangle'); ?> me-1"></i><?php echo e($nameMatch ? 'Matches the registered name' : 'Differs from the registered name ('.$p->registered_name.') — check it is held in the enterprise’s name'); ?></div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></dd></div>
                    <div><dt>Account number</dt><dd><span class="font-monospace"><?php echo e($bk['account_number']); ?></span></dd></div>
                    <div><dt>Account type</dt><dd><?php echo e($G::ACCOUNT_TYPES[$bk['account_type'] ?? ''] ?? '—'); ?></dd></div>
                </div>
            <?php else: ?>
                <div class="callout callout-warning small mb-2"><i class="bi bi-exclamation-triangle me-1"></i>
                    The enterprise has not entered its bank details yet. Ask them to complete <strong>Business → Bank account for the grant</strong> in their profile.</div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <div class="row g-2">
                <div class="col-md-6">
                    <label class="form-label small fw-semibold mb-1">Evidence from the enterprise</label>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($bankDoc): ?>
                        <div class="small"><a href="<?php echo e(route('enterprise-documents.download', $bankDoc)); ?>" class="text-decoration-none"><i class="bi bi-paperclip"></i> <?php echo e(\Illuminate\Support\Str::limit($bankDoc->original_name, 45)); ?></a>
                            <span class="text-muted">· bank confirmation letter</span></div>
                    <?php else: ?>
                        <div class="small text-muted">No bank confirmation letter uploaded in the profile.</div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <label class="form-label small fw-semibold mb-1 mt-2">Other verification document <span class="text-muted fw-normal">(optional<?php echo e($bankDoc ? '' : ' — needed if the enterprise has not uploaded a letter'); ?>)</span></label>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($g?->bank_evidence_path): ?>
                        <div class="d-flex align-items-center gap-2 small">
                            <a href="<?php echo e(route('grant-readiness.file', [$g, 'bank_evidence'])); ?>" class="text-decoration-none text-truncate"><i class="bi bi-paperclip"></i> <?php echo e($g->bank_evidence_name); ?></a>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canG): ?> <label class="btn btn-sm btn-outline-secondary mb-0 ms-auto">Replace <input type="file" class="d-none" wire:model="bankEvidenceFile" accept=".pdf,.jpg,.jpeg,.png"></label> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                    <?php elseif($canG): ?>
                        <input type="file" class="form-control form-control-sm <?php $__errorArgs = ['bankEvidenceFile'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model="bankEvidenceFile" accept=".pdf,.jpg,.jpeg,.png">
                    <?php else: ?>
                        <div class="small text-muted">—</div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <span wire:loading wire:target="bankEvidenceFile" class="small text-muted"><span class="spinner-border spinner-border-sm me-1"></span> Uploading…</span>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['bankEvidenceFile'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-danger small"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold mb-1">Verified against <span class="req">*</span></label>
                    <select class="form-select form-select-sm <?php $__errorArgs = ['form.bank.verified_against'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model.live="form.bank.verified_against" <?php if(! $canG): echo 'disabled'; endif; ?>>
                        <option value="">Select…</option>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $G::BANK_EVIDENCE; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ek => $el): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?> <option value="<?php echo e($ek); ?>"><?php echo e($el); ?></option> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </select>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.bank.verified_against'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-danger small"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <div class="form-text">Then record the result in the <strong>Bank account</strong> check above: Confirmed if the details match the evidence, or Action required if the enterprise must correct its profile.</div>
                </div>
            </div>
        </div>

        
        <div class="g2-sub">
            <div class="g2-sub-head"><i class="bi bi-list-task me-1"></i> Outstanding conditions, clarifications or documents <span class="text-muted fw-normal">— with who follows up</span></div>
            <div class="table-responsive">
                <table class="table table-modern align-middle mb-2 dd-table">
                    <thead><tr><th>Issue</th><th style="width: 17%">Responsible</th><th style="width: 28%">Required action</th><th style="width: 12%">Status</th><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canG): ?><th></th><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></tr></thead>
                    <tbody>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $form['issues']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $iss): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <tr <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'gri-'.e($i).''; ?>wire:key="gri-<?php echo e($i); ?>">
                                <td><input type="text" class="form-control form-control-sm" wire:model.blur="form.issues.<?php echo e($i); ?>.issue" maxlength="250" <?php if(! $canG): echo 'disabled'; endif; ?>></td>
                                <td><select class="form-select form-select-sm" wire:model.live="form.issues.<?php echo e($i); ?>.party" <?php if(! $canG): echo 'disabled'; endif; ?>>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $G::PARTIES; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $pk => $pl): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?> <option value="<?php echo e($pk); ?>"><?php echo e($pl); ?></option> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?></select></td>
                                <td><input type="text" class="form-control form-control-sm" wire:model.blur="form.issues.<?php echo e($i); ?>.action" maxlength="250" <?php if(! $canG): echo 'disabled'; endif; ?>></td>
                                <td><select class="form-select form-select-sm" wire:model.live="form.issues.<?php echo e($i); ?>.status" <?php if(! $canG): echo 'disabled'; endif; ?>>
                                    <option value="open">Open</option><option value="resolved">Resolved</option></select></td>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canG): ?><td><button type="button" class="btn btn-icon btn-icon-danger" wire:click="removeIssue(<?php echo e($i); ?>)" title="Remove"><i class="bi bi-x-lg"></i></button></td><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </tr>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                            <tr><td colspan="5" class="small text-muted">None identified.</td></tr>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canG): ?>
                <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="addIssue"><i class="bi bi-plus-lg me-1"></i> Add issue</button>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>

        
        <div class="g2-sub">
            <div class="g2-sub-head"><i class="bi bi-file-earmark-text me-1"></i> Outputs</div>
            <div class="row g-2">
                <div class="col-md-6">
                    <div class="border rounded-3 p-2 h-100 small">
                        <div class="fw-semibold text-navy mb-1">Draft Grant Agreement <span class="req">*</span></div>
                        <div class="text-muted mb-2">Reflects the proposed grant amount, approved grant utilisation budget and applicable terms. PDF or Word.</div>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($g?->agreement_path): ?>
                            <div class="d-flex align-items-center gap-2">
                                <a href="<?php echo e(route('grant-readiness.file', [$g, 'agreement'])); ?>" class="text-decoration-none text-truncate"><i class="bi bi-file-earmark-check"></i> <?php echo e($g->agreement_name); ?></a>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canG): ?> <label class="btn btn-sm btn-outline-secondary mb-0 ms-auto">Replace <input type="file" class="d-none" wire:model="agreementFile" accept=".pdf,.docx,.doc"></label> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>
                        <?php elseif($canG): ?>
                            <input type="file" class="form-control form-control-sm <?php $__errorArgs = ['agreementFile'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model="agreementFile" accept=".pdf,.docx,.doc">
                        <?php else: ?>
                            <span class="text-muted">Not uploaded.</span>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <span wire:loading wire:target="agreementFile" class="text-muted"><span class="spinner-border spinner-border-sm me-1"></span> Uploading…</span>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['agreementFile'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-danger"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="border rounded-3 p-2 h-100 small">
                        <div class="fw-semibold text-navy mb-1">Grant Readiness Memo</div>
                        <div class="text-muted mb-2">One page, generated from this review: transaction, budget & compliance, bank verification, documents, outstanding issues, recommendation and the approval record.</div>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($g): ?>
                            <a href="<?php echo e(route('grant-readiness.memo', $g)); ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-file-earmark-pdf me-1"></i> Download PDF</a>
                        <?php else: ?>
                            <span class="text-muted">Available once you start the review.</span>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        
        <label class="form-label small fw-semibold">Readiness recommendation <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canG): ?> <span class="req">*</span> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></label>
        <div class="choice-group cols-2 <?php $__errorArgs = ['form.recommendation'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $G::RECOMMENDATIONS; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $rk => [$rl, , $ri]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <label class="choice">
                    <input type="radio" name="gr-rec" value="<?php echo e($rk); ?>" wire:model.live="form.recommendation" <?php if(! $canG): echo 'disabled'; endif; ?>>
                    <span class="choice-box"><span class="choice-radio"></span><i class="bi <?php echo e($ri); ?> <?php echo e($rk === 'ready' ? 'text-accent' : 'text-danger'); ?>"></i> <?php echo e($rl); ?></span>
                </label>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        </div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.recommendation'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-danger small mt-1"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <div class="row g-3 mt-1">
            <div class="col-md-9">
                <label class="form-label small fw-semibold">Brief justification and any material qualifications <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canG): ?> <span class="req">*</span> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></label>
                <textarea rows="2" class="form-control <?php $__errorArgs = ['form.justification'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model.blur="form.justification" maxlength="2000" <?php if(! $canG): echo 'disabled'; endif; ?>></textarea>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.justification'];
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
                <input type="date" class="form-control <?php $__errorArgs = ['form.gm_on'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model.blur="form.gm_on" max="<?php echo e(now()->toDateString()); ?>" <?php if(! $canG): echo 'disabled'; endif; ?>>
            </div>
        </div>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canG): ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->blockers): ?>
                <div class="callout callout-warning small mt-3 mb-0">
                    <div class="fw-semibold mb-1"><i class="bi bi-list-check me-1"></i> Before you can submit to the Hub Manager (<?php echo e(count($this->blockers)); ?>)</div>
                    <ul class="evidence-list mb-0"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $this->blockers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $b): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?> <li><?php echo e($b); ?></li> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?></ul>
                </div>
            <?php else: ?>
                <div class="callout callout-success small mt-3 mb-0"><i class="bi bi-check2-circle me-1"></i> Ready to submit to the Hub Manager.</div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <div class="d-flex mt-3 pt-3 border-top">
                <button type="button" class="btn btn-brand ms-auto" wire:click="submitToHub" wire:loading.attr="disabled" wire:target="submitToHub"
                        wire:confirm="Submit the Grant Readiness Memo and Draft Grant Agreement to the Hub Manager? Your part is locked afterwards.">
                    <span wire:loading.remove wire:target="submitToHub"><i class="bi bi-send me-1"></i> Submit to the Hub Manager</span>
                    <span wire:loading wire:target="submitToHub"><span class="spinner-border spinner-border-sm me-1"></span> Submitting…</span>
                </button>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </section>

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = [
        ['hub', 2, 'Hub Manager review and approval', 'Consistent with growth objectives and Facility requirements, budget and documents satisfactory, earlier issues addressed?', $G::HUB_DECISIONS, $canH, $hubDone, $g?->hubManager, $g?->hub_decided_at, 'recordHub', 'Record Hub Manager decision'],
        ['director', 3, 'PMU Director of Implementation review and approval', 'Internal steps complete, memo and draft agreement available, grant amount and key conditions documented, material issues resolved?', $G::DIRECTOR_DECISIONS, $canD, $dirDone, $g?->director, $g?->director_decided_at, 'recordDirector', 'Record PMU Director decision'],
    ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$who, $no, $title, $question, $decisions, $can, $done, $byUser, $decidedAt, $action, $button]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
        <?php
            $open   = $stage === $who;
            $before = ($who === 'hub' && in_array($stage, ['grants'], true)) || ($who === 'director' && in_array($stage, ['grants', 'hub'], true));
            $val    = $who === 'hub' ? $hub : $director;
        ?>
        <section class="section-card" id="g4-<?php echo e($who); ?>">
            <div class="section-card-head">
                <span class="section-no"><?php echo e($no); ?></span>
                <div class="me-auto">
                    <h5><?php echo e($title); ?></h5>
                    <div class="small text-muted">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($done): ?> <?php echo e($byUser?->name ?? '—'); ?> · <?php echo e($decidedAt?->format('d M Y H:i')); ?> <?php else: ?> <?php echo e($question); ?> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($done): ?>
                    <?php [$dl, , $di, $dp] = $decisions[$val['decision']] ?? ['—', '', 'bi-circle', 'pill-muted']; ?>
                    <span class="pill <?php echo e($dp); ?>"><i class="bi <?php echo e($di); ?>"></i> <?php echo e($dl); ?></span>
                <?php elseif($open): ?>
                    <span class="pill pill-warn"><i class="bi bi-hourglass-split"></i> Awaiting decision</span>
                <?php else: ?>
                    <span class="pill pill-muted"><i class="bi bi-lock-fill"></i> <?php echo e($before ? 'Locked' : '—'); ?></span>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($before && ! $done): ?>
                <div class="g1-locked"><i class="bi bi-lock"></i>
                    <div>Opens once <?php echo e($who === 'hub' ? 'Grants Management has submitted the file' : 'the Hub Manager has approved the file for submission'); ?>. The reviews are sequential.</div>
                </div>
            <?php else: ?>
                <div class="choice-group cols-2 <?php $__errorArgs = ["$who.decision"];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $decisions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $v => [$dLabel, $dDesc, $dIcon]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <label class="choice choice--card">
                            <input type="radio" name="gr-<?php echo e($who); ?>" value="<?php echo e($v); ?>" wire:model.live="<?php echo e($who); ?>.decision" <?php if(! $can): echo 'disabled'; endif; ?>>
                            <span class="choice-box">
                                <span class="choice-icon"><i class="bi <?php echo e($dIcon); ?>"></i></span>
                                <span><span class="d-block fw-semibold"><?php echo e($dLabel); ?></span><span class="d-block small text-muted lh-sm mt-1"><?php echo e($dDesc); ?></span></span>
                            </span>
                        </label>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ["$who.decision"];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-danger small mt-1"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                <div class="row g-3 mt-1">
                    <div class="col-md-9">
                        <label class="form-label small fw-semibold">Comments <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($can && ($val['decision'] ?? null) === 'returned'): ?> <span class="req">*</span> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></label>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($can): ?>
                            <textarea rows="2" class="form-control <?php $__errorArgs = ["$who.comments"];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model.blur="<?php echo e($who); ?>.comments"
                                      placeholder="<?php echo e(($val['decision'] ?? null) === 'returned' ? 'What must be resolved, and by whom' : 'Optional'); ?>"></textarea>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ["$who.comments"];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <?php else: ?>
                            <div class="quote mb-0"><?php echo e($val['comments'] ?: 'No comments.'); ?></div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Date</label>
                        <input type="date" class="form-control <?php $__errorArgs = ["$who.on"];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model.blur="<?php echo e($who); ?>.on" max="<?php echo e(now()->toDateString()); ?>" <?php if(! $can): echo 'disabled'; endif; ?>>
                    </div>
                </div>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($can): ?>
                    <div class="d-flex mt-3 pt-3 border-top">
                        <button type="button" class="btn btn-brand ms-auto" wire:click="<?php echo e($action); ?>" wire:loading.attr="disabled" wire:target="<?php echo e($action); ?>"
                                wire:confirm="Record this decision? It can't be changed afterwards.">
                            <span wire:loading.remove wire:target="<?php echo e($action); ?>"><i class="bi bi-send me-1"></i> <?php echo e($button); ?></span>
                            <span wire:loading wire:target="<?php echo e($action); ?>"><span class="spinner-border spinner-border-sm me-1"></span> Recording…</span>
                        </button>
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </section>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>

    
    <section class="section-card">
        <div class="section-card-head">
            <span class="section-no"><i class="bi bi-pen"></i></span>
            <div class="me-auto">
                <h5>Approval record</h5>
                <div class="small text-muted">The transaction proceeds to Gate 5 only after all three steps are complete.</div>
            </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($g?->isApproved()): ?> <span class="pill pill-ok"><i class="bi bi-patch-check"></i> Approved to proceed</span> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
        <div class="table-responsive">
            <table class="table table-modern table-sm align-middle mb-0">
                <thead><tr><th>Review stage</th><th>Name</th><th>Date</th><th>Decision</th></tr></thead>
                <tbody>
                    <tr>
                        <td class="small fw-semibold text-navy">Grants Management Representative</td>
                        <td class="small"><?php echo e($g?->grantsOfficer?->name ?? '—'); ?></td>
                        <td class="small"><?php echo e($gmDone ? ($g->gm_on ?? $g->gm_submitted_at)->format('d M Y') : '—'); ?></td>
                        <td><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($gmDone && $g->recommendation): ?> <?php [$rl, $rp] = $G::RECOMMENDATIONS[$g->recommendation]; ?> <span class="pill <?php echo e($rp); ?>"><?php echo e($rl); ?></span> <?php else: ?> <span class="pill pill-warn">Pending</span> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></td>
                    </tr>
                    <tr>
                        <td class="small fw-semibold text-navy">Hub Manager</td>
                        <td class="small"><?php echo e($g?->hubManager?->name ?? '—'); ?></td>
                        <td class="small"><?php echo e($hubDone ? $g->hub_on?->format('d M Y') : '—'); ?></td>
                        <td><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($hubDone): ?> <span class="pill <?php echo e($G::HUB_DECISIONS[$g->hub_decision][3]); ?>"><?php echo e($G::HUB_DECISIONS[$g->hub_decision][0]); ?></span> <?php else: ?> <span class="pill pill-muted">Pending</span> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></td>
                    </tr>
                    <tr>
                        <td class="small fw-semibold text-navy">PMU Director of Implementation</td>
                        <td class="small"><?php echo e($g?->director?->name ?? '—'); ?></td>
                        <td class="small"><?php echo e($dirDone ? $g->director_on?->format('d M Y') : '—'); ?></td>
                        <td><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($dirDone): ?> <span class="pill <?php echo e($G::DIRECTOR_DECISIONS[$g->director_decision][3]); ?>"><?php echo e($G::DIRECTOR_DECISIONS[$g->director_decision][0]); ?></span> <?php else: ?> <span class="pill pill-muted">Pending</span> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($g?->isApproved()): ?>
            <div class="callout callout-success small mt-3 mb-0">
                <i class="bi bi-box-arrow-in-right me-1"></i> Cleared to advance to <strong>Gate 5 · Final authorisation</strong> — the Grant Agreement is signed by the CAFI Managing Director, then the MTIBD Principal Secretary.
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </section>

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->history->isNotEmpty()): ?>
        <section class="section-card">
            <div class="section-card-head"><span class="section-no"><i class="bi bi-clock-history"></i></span><h5>Earlier rounds</h5></div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $this->history; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $h): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <div class="file-row align-items-start">
                    <span class="file-row-icon"><i class="bi <?php echo e($h->isApproved() ? 'bi-patch-check' : 'bi-arrow-return-left'); ?>"></i></span>
                    <div class="flex-grow-1 small">
                        <strong class="text-navy">Round <?php echo e($h->round); ?></strong>
                        <span class="pill <?php echo e($h->isApproved() ? 'pill-ok' : 'pill-warn'); ?> ms-1"><?php echo e($h->isApproved() ? 'Approved to proceed' : 'Returned'); ?></span>
                        <span class="text-muted ms-1"><?php echo e($h->decided_at->format('d M Y')); ?> · by the <?php echo e($G::STAGES[$h->returnedBy() ?? 'director'][1]); ?></span>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($h->returnComment()): ?> <div class="text-muted mt-1"><?php echo e($h->returnComment()); ?></div> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                    <a href="<?php echo e(route('grant-readiness.memo', $h)); ?>" class="btn btn-icon" title="Memo — round <?php echo e($h->round); ?>"><i class="bi bi-file-earmark-pdf"></i></a>
                </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        </section>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div><?php /**PATH C:\laragon\www\co-investment-facility\storage\framework\views/livewire/views/bff9e7e7.blade.php ENDPATH**/ ?>