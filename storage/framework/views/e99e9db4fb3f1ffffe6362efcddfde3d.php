<?php
use App\Models\ExpressionOfInterest;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
?>

<div>
<?php
    $eoi     = $this->eoi;
    $isOwner = $this->isOwner;

    /* ---------- Labels (same keys as the apply form) ---------- */
    $labels = [
        'legal_status' => [
            'registered_operating' => 'Registered and operating in Lesotho', 'registered_not_operating' => 'Registered, operations not yet established',
            'registration_in_progress' => 'Registration in progress', 'not_registered' => 'Not registered',
        ],
        'annual_turnover' => [
            'below_500k' => 'Below M500,000', '500k_2m' => 'M500,000 – M2 million', '2m_5m' => 'M2 – M5 million',
            '5m_10m' => 'M5 – M10 million', 'above_10m' => 'Above M10 million',
        ],
        'investment_types' => ['equity' => 'Equity', 'quasi_equity' => 'Quasi-equity', 'debt' => 'Debt'],
        'use_of_funds' => [
            'equipment' => 'Equipment / productive assets', 'business_expansion' => 'Business expansion', 'working_capital' => 'Working capital',
            'market_expansion' => 'Market expansion', 'technology' => 'Technology / systems', 'product_development' => 'Product development', 'other' => 'Other',
        ],
        'investor_status' => [
            'none' => 'No investor currently', 'discussions' => 'In discussions with potential investors', 'interest' => 'Indication of investor interest',
            'loi' => 'Letter of Intent received', 'term_sheet' => 'Term Sheet in place', 'committed' => 'Investment formally committed', 'other' => 'Other',
        ],
        'investor_evidence' => [
            'loi' => 'Letter of Intent', 'term_sheet' => 'Term Sheet', 'investment_agreement' => 'Investment Agreement',
            'other_formal' => 'Other formal evidence', 'none_available' => 'No document available', 'not_applicable' => 'Not applicable',
        ],
        'support_need' => [
            'ci_facility' => 'Has investor commitment — wants to be considered for the CAFI Co-Investment Facility',
            'progress_deal' => 'Has an interested investor — needs support to progress the transaction',
            'find_investor' => 'Investment-ready — needs help finding an investor',
            'readiness' => 'Needs support to become investment-ready',
            'assess_me' => 'Not sure — would like to be assessed',
        ],
    ];
    $label = fn ($field, $value) => $labels[$field][$value] ?? ($value ?: '—');
    $list  = fn ($field, $values) => collect($values ?? [])->map(fn ($v) => $labels[$field][$v] ?? $v)->all();

    $statuses = [
        'draft'          => ['Draft',          'status-draft',     'bi-pencil'],
        'submitted'      => ['Submitted',      'status-submitted', 'bi-send'],
        'returned'       => ['Returned',       'status-referred',  'bi-arrow-return-left'],
        'in_review'      => ['In review',      'status-in-review', 'bi-hourglass-split'],
        'invited'        => ['Invited to CI',  'status-approved',  'bi-patch-check'],
        'due_diligence'  => ['Due diligence',  'status-in-review', 'bi-search'],
        'dd_hold'        => ['DD on hold',     'status-referred',  'bi-pause-circle'],
        'committee'      => ['Investment Committee', 'status-approved', 'bi-people'],
        'ic_deferred'    => ['Deferred by IC', 'status-referred',  'bi-pause-circle'],
        'grant_readiness' => ['Grant readiness review', 'status-in-review', 'bi-clipboard-check'],
        'authorisation'  => ['Final authorisation', 'status-approved', 'bi-patch-check'],
        'authorisation_hold' => ['Authorisation on hold', 'status-referred', 'bi-pause-circle'],
        'authorised'     => ['Formally approved',     'status-approved',  'bi-patch-check-fill'],
        'capital_pending' => ['Awaiting investor capital', 'status-referred', 'bi-shield-exclamation'],
        'cleared'        => ['Cleared for disbursement', 'status-approved', 'bi-cash-coin'],
        'escalated'      => ['Escalated',      'status-rejected',  'bi-exclamation-octagon'],
        'not_progressed' => ['Not progressed', 'status-rejected',  'bi-x-circle'],
        'withdrawn'      => ['Withdrawn',      'status-draft',     'bi-slash-circle'],
    ];
    [$sLabel, $sClass, $sIcon] = $statuses[$eoi->status] ?? [ucfirst($eoi->status), 'status-draft', 'bi-circle'];

    /* ---------- Progress tracker ---------- */
    $stages = [
        ['label' => 'Submitted',          'icon' => 'bi-send'],
        ['label' => 'Initial assessment', 'icon' => 'bi-search'],
        ['label' => 'Pathway decision',   'icon' => 'bi-signpost-split'],
        ['label' => 'Outcome',            'icon' => 'bi-flag'],
    ];
    $stageIndex = match ($eoi->status) {
        'draft', 'withdrawn'        => -1,
        'submitted', 'returned'     => 0,
        'in_review'                 => 1,
        'invited', 'not_progressed', 'due_diligence', 'dd_hold', 'committee', 'ic_deferred', 'grant_readiness', 'authorisation', 'authorisation_hold', 'authorised', 'capital_pending', 'cleared', 'escalated' => 3,
        default                     => 0,
    };

    /* ---------- Money: stored in US$, shown in Maloti with US$ underneath ---------- */
    $rate      = (float) config('cif.usd_to_lsl', 18.00);
    $lsl       = fn ($usd) => 'M '.number_format((float) $usd * $rate);
    $usd       = fn ($amount) => 'US$ '.number_format((float) $amount);
    $required  = (float) ($eoi->investment_required_usd ?? 0);
    $committed = (float) ($eoi->investor_amount_usd ?? 0);
    $coverage  = $required > 0 ? min(100, round($committed / $required * 100)) : 0;
    $gap       = max(0, $required - $committed);
    $wordCount = fn ($text) => str_word_count(strip_tags((string) $text));
    $initials  = fn ($name) => collect(explode(' ', (string) $name))->filter()->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('');

    /* ---------- Activity (from the record's own dates) ---------- */
    $activity = collect([
        $eoi->withdrawn_at ? ['icon' => 'bi-slash-circle', 'tone' => 'muted',   'title' => 'Application withdrawn', 'text' => 'Withdrawn by the applicant.', 'when' => $eoi->withdrawn_at] : null,
        $eoi->status === 'returned' ? ['icon' => 'bi-arrow-return-left', 'tone' => 'warning', 'title' => 'Returned for more information', 'text' => 'Please update and resubmit.', 'when' => $eoi->updated_at] : null,
        $eoi->status === 'in_review' ? ['icon' => 'bi-hourglass-split', 'tone' => 'warning', 'title' => 'Assessment started', 'text' => 'The LEHSFF team is reviewing this EOI.', 'when' => $eoi->updated_at] : null,
        $eoi->submitted_at ? ['icon' => 'bi-send-check', 'tone' => 'accent',  'title' => 'EOI submitted', 'text' => 'Submitted by '.($eoi->representative_name ?: $eoi->user?->name).'.', 'when' => $eoi->submitted_at] : null,
        ['icon' => 'bi-pencil', 'tone' => 'primary', 'title' => 'Application started', 'text' => 'Draft created.', 'when' => $eoi->created_at],
    ])->filter()->sortByDesc('when')->values();

    /* =====================================================================
     | DECISION GATES — state derived from the EOI status for now.
     | Replace with real gate records when the gate logic is built.
     ===================================================================== */
    $latestG1 = $eoi->eligibilityScreenings()->orderByDesc('round')->first();
    $latestG2 = $eoi->dueDiligences()->orderByDesc('round')->first();
    $g1State = match (true) {
        $latestG1?->isDecided() && $latestG1->decision === 'eligible'     => 'passed',
        $latestG1?->isDecided() && $latestG1->decision === 'not_eligible' => 'failed',
        in_array($eoi->status, \App\Models\ExpressionOfInterest::PAST_GATE1, true) => 'passed',
        in_array($eoi->status, ['submitted', 'in_review', 'returned'], true) => 'current',
        $eoi->status === 'not_progressed'                                 => 'failed',
        default                                                           => 'locked',
    };
    $g2State = match (true) {
        $g1State !== 'passed'                                             => 'locked',
        $latestG2?->isDecided() && $latestG2->outcome === 'proceed'       => 'passed',
        $latestG2?->isDecided() && $latestG2->outcome === 'do_not_progress' => 'failed',
        default                                                           => 'current',
    };
    $latestG3 = $eoi->committeeReviews()->orderByDesc('round')->first();
    $g3State = match (true) {
        $g2State !== 'passed'                                                        => 'locked',
        $latestG3?->isDecided() && in_array($latestG3->outcome, ['approve', 'conditional'], true) => 'passed',
        $latestG3?->isDecided() && $latestG3->outcome === 'reject'                   => 'failed',
        default                                                                      => 'current',
    };
    // Gate 4 · Grant readiness (tab "readiness"). Transactions authorised before Gate 4 existed count as passed.
    $latestGR = $eoi->grantReadinesses()->orderByDesc('round')->first();
    $grState = match (true) {
        $g3State !== 'passed'                                                        => 'locked',
        (bool) $latestGR?->isApproved()                                              => 'passed',
        ! $latestGR && in_array($eoi->status, ['authorisation', 'authorisation_hold', 'authorised', 'capital_pending', 'cleared', 'escalated'], true) => 'passed',
        default                                                                      => 'current',
    };
    // Gate 5 · Final authorisation (tab key "g4" kept for existing links)
    $latestG4 = $eoi->authorisations()->orderByDesc('round')->first();
    $g4State = match (true) {
        $grState !== 'passed'                                                        => 'locked',
        $latestG4?->outcome === 'authorised'                                         => 'passed',
        $latestG4?->outcome === 'not_authorised'                                     => 'failed',
        default                                                                      => 'current',
    };
    $latestG5 = $eoi->conditionReview;
    $g5State = match (true) {
        $g4State !== 'passed'                => 'locked',
        $latestG5?->isFullyDisbursed()       => 'passed',
        default                              => 'current',
    };
    $gates = [
        'g1' => ['code' => 'G1', 'label' => 'Eligibility Screening', 'short' => 'Eligibility',   'icon' => 'bi-funnel-fill',          'state' => $g1State],
        'g2' => ['code' => 'G2', 'label' => 'Due Diligence & ESGC',  'short' => 'Due Diligence', 'icon' => 'bi-search',               'state' => $g2State],
        'g3' => ['code' => 'G3', 'label' => 'Committee Evaluation',  'short' => 'Committee',     'icon' => 'bi-clipboard2-data-fill', 'state' => $g3State],
        'readiness' => ['code' => 'G4', 'label' => 'Grant Readiness Review', 'short' => 'Readiness', 'icon' => 'bi-clipboard-check',  'state' => $grState],
        'g4' => ['code' => 'G5', 'label' => 'Final Authorisation',   'short' => 'Authorisation', 'icon' => 'bi-patch-check-fill',     'state' => $g4State],
        'g5' => ['code' => 'G6', 'label' => 'Conditions Precedent & Disbursement (G6–G7)', 'short' => 'Conditions & Disbursement', 'icon' => 'bi-list-check', 'state' => $g5State],
    ];
    $gateStates = [
        'passed'  => ['Passed',      'pill-ok',     'bi-check-circle-fill'],
        'current' => ['In progress', 'pill-warn',   'bi-hourglass-split'],
        'failed'  => ['Not passed',  'pill-danger', 'bi-x-circle-fill'],
        'locked'  => ['Locked',      'pill-muted',  'bi-lock-fill'],
    ];
    $gateKeys = array_keys($gates);
?>

<div class="container-fluid p-3">

    
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <a href="<?php echo e(route('expression-of-interest.index')); ?>" class="back-link">
            <i class="bi bi-arrow-left"></i> Back to applications
        </a>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($isOwner)): ?>
            <span class="pill pill-muted ms-auto"><i class="bi bi-eye"></i> Reviewer view</span>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>

    
    <div class="hero-banner mb-4">
        <div class="hero-glow"></div>
        <div class="d-flex flex-wrap align-items-start gap-3">
            <div class="avatar-sq"><?php echo e(mb_strtoupper(mb_substr($eoi->business_name ?: 'E', 0, 1))); ?></div>
            <div class="me-auto">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                    <span class="chip chip-dark"><?php echo e($eoi->reference); ?></span>
                    <span class="badge <?php echo e($sClass); ?>"><i class="bi <?php echo e($sIcon); ?> me-1"></i><?php echo e($sLabel); ?></span>
                </div>
                <h2 class="hero-title mb-1"><?php echo e($eoi->business_name ?: 'Untitled application'); ?></h2>
                <div class="hero-meta">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($eoi->district): ?> <span><i class="bi bi-geo-alt"></i> <?php echo e($eoi->district); ?></span> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($eoi->representative_name): ?> <span><i class="bi bi-person"></i> <?php echo e($eoi->representative_name); ?></span> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <span><i class="bi bi-calendar3"></i> <?php echo e($eoi->submitted_at ? 'Submitted '.$eoi->submitted_at->format('d M Y') : 'Not submitted'); ?></span>
                </div>
            </div>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isOwner): ?>
                <div class="d-flex flex-wrap gap-2">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($eoi->isEditable()): ?>
                        <button type="button" class="btn btn-light btn-sm fw-semibold"
                                wire:click="$dispatch('edit-application', { id: <?php echo e($eoi->id); ?> })">
                            <i class="bi bi-pencil me-1"></i> Edit
                        </button>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($eoi->isWithdrawable()): ?>
                        <button type="button" class="btn btn-outline-light btn-sm"
                                wire:click="withdraw"
                                wire:confirm="Withdraw <?php echo e($eoi->reference); ?>? This cannot be undone.">
                            <i class="bi bi-x-circle me-1"></i> Withdraw
                        </button>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>

        
        <div class="tracker">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $stages; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $stage): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <div class="tracker-step <?php echo e($i < $stageIndex ? 'done' : ($i === $stageIndex ? 'current' : '')); ?>">
                    <span class="tracker-dot"><i class="bi <?php echo e($i < $stageIndex ? 'bi-check-lg' : $stage['icon']); ?>"></i></span>
                    <span class="tracker-label"><?php echo e($stage['label']); ?></span>
                </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        </div>
    </div>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($eoi->status === 'returned' && $isOwner): ?>
        <div class="callout callout-warning d-flex align-items-center gap-3 mb-4">
            <i class="bi bi-arrow-return-left fs-5"></i>
            <div class="flex-grow-1 small">
                <strong>Returned for more information.</strong> Please review the reviewer's comments, update your EOI and resubmit.
                <a href="#" wire:click.prevent="showTab('g1')" class="ms-1">See what needs clarifying</a>
            </div>
            <button type="button" class="btn btn-sm btn-warning fw-semibold" wire:click="$dispatch('edit-application', { id: <?php echo e($eoi->id); ?> })">Update EOI</button>
        </div>
    <?php elseif($eoi->status === 'withdrawn'): ?>
        <div class="callout d-flex align-items-center gap-3 mb-4">
            <i class="bi bi-slash-circle fs-5 text-faint"></i>
            <div class="small"><strong>Withdrawn</strong> on <?php echo e($eoi->withdrawn_at?->format('d M Y')); ?>. No further action will be taken.</div>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    
    <nav class="page-tabs mb-4">
        <a href="#" wire:click.prevent="showTab('overview')" class="<?php echo e($tab === 'overview' ? 'active' : ''); ?>">
            <i class="bi bi-grid-1x2"></i> Overview
        </a>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->showInvestments): ?>
            <a href="#" wire:click.prevent="showTab('investments')" class="<?php echo e($tab === 'investments' ? 'active' : ''); ?>">
                <i class="bi bi-bank"></i> Investments
            </a>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <span class="page-tabs-divider" title="Decision gates"></span>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $gates; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $g): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
            <a href="#" wire:click.prevent="showTab('<?php echo e($key); ?>')" class="<?php echo e($tab === $key ? 'active' : ''); ?> is-<?php echo e($g['state']); ?>" title="<?php echo e($g['label']); ?>">
                <span class="page-tabs-code"><?php echo e($g['code']); ?></span>
                <?php echo e($g['short']); ?>

                <i class="bi <?php echo e($gateStates[$g['state']][2]); ?> page-tabs-state"></i>
            </a>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
    </nav>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($tab === 'overview'): ?>
    

    
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="stat-card">
                <span class="stat-icon tone-primary"><i class="bi bi-cash-coin"></i></span>
                <div>
                    <div class="stat-label">Investment required</div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($required): ?>
                        <div class="stat-value sm"><?php echo e($lsl($required)); ?></div>
                        <div class="small text-muted">≈ <?php echo e($usd($required)); ?></div>
                    <?php else: ?>
                        <div class="stat-value sm">—</div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="stat-card">
                <span class="stat-icon tone-accent"><i class="bi bi-briefcase"></i></span>
                <div>
                    <div class="stat-label">Investor commitment</div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($committed): ?>
                        <div class="stat-value sm"><?php echo e($lsl($committed)); ?></div>
                        <div class="small text-muted">≈ <?php echo e($usd($committed)); ?></div>
                    <?php else: ?>
                        <div class="stat-value sm">—</div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="stat-card">
                <span class="stat-icon tone-warning"><i class="bi bi-pie-chart"></i></span>
                <div>
                    <div class="stat-label">Funding gap</div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($required): ?>
                        <div class="stat-value sm"><?php echo e($lsl($gap)); ?></div>
                        <div class="small text-muted">≈ <?php echo e($usd($gap)); ?></div>
                    <?php else: ?>
                        <div class="stat-value sm">—</div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="stat-card">
                <span class="stat-icon tone-muted"><i class="bi bi-people"></i></span>
                <div>
                    <div class="stat-label">Full-time employees</div>
                    <div class="stat-value sm"><?php echo e($eoi->full_time_employees ?? '—'); ?></div>
                    <div class="small text-muted">Permanent staff</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        
        <div class="col-xl-8">

            <nav class="section-tabs mb-3">
                <a href="#sec-business">Business</a>
                <a href="#sec-operations">Operations</a>
                <a href="#sec-investment">Investment</a>
                <a href="#sec-investor">Investor</a>
                <a href="#sec-support">Support &amp; declaration</a>
            </nav>

            
            <section id="sec-business" class="section-card">
                <div class="section-card-head">
                    <span class="section-no">1</span>
                    <h5>Business details</h5>
                </div>
                <div class="detail-grid">
                    <div><dt>Business name</dt><dd><?php echo e($eoi->business_name ?: '—'); ?></dd></div>
                    <div><dt>Founder / authorised representative</dt><dd><?php echo e($eoi->representative_name ?: '—'); ?></dd></div>
                    <div><dt>Telephone</dt><dd><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($eoi->phone): ?><a href="tel:<?php echo e($eoi->phone); ?>"><?php echo e($eoi->phone); ?></a><?php else: ?> — <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></dd></div>
                    <div><dt>Email</dt><dd><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($eoi->email): ?><a href="mailto:<?php echo e($eoi->email); ?>"><?php echo e($eoi->email); ?></a><?php else: ?> — <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></dd></div>
                    <div><dt>District</dt><dd><?php echo e($eoi->district ?: '—'); ?></dd></div>
                    <div><dt>Website / social media</dt><dd><?php echo e($eoi->website ?: '—'); ?></dd></div>
                    <div><dt>Legal status</dt>
                        <dd>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($eoi->legal_status): ?>
                                <span class="pill <?php echo e($eoi->legal_status === 'registered_operating' ? 'pill-ok' : 'pill-warn'); ?>">
                                    <i class="bi <?php echo e($eoi->legal_status === 'registered_operating' ? 'bi-check-circle' : 'bi-exclamation-circle'); ?>"></i>
                                    <?php echo e($label('legal_status', $eoi->legal_status)); ?>

                                </span>
                            <?php else: ?> — <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </dd>
                    </div>
                    <div><dt>Operating since</dt>
                        <dd><?php echo e($eoi->year_commenced ?: '—'); ?> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($eoi->year_commenced): ?> <span class="text-muted">(<?php echo e(now()->year - $eoi->year_commenced); ?> yrs)</span> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></dd></div>
                </div>
            </section>

            
            <section id="sec-operations" class="section-card">
                <div class="section-card-head">
                    <span class="section-no">2</span>
                    <h5>What the business does &amp; scale</h5>
                </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($eoi->business_description): ?>
                    <div class="quote">
                        <?php echo e($eoi->business_description); ?>

                        <div class="quote-meta"><?php echo e($wordCount($eoi->business_description)); ?> words</div>
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <div class="detail-grid mt-3">
                    <div><dt>Annual turnover</dt><dd><?php echo e($label('annual_turnover', $eoi->annual_turnover)); ?></dd></div>
                    <div><dt>Full-time employees</dt><dd><?php echo e($eoi->full_time_employees ?? '—'); ?></dd></div>
                </div>
            </section>

            
            <section id="sec-investment" class="section-card">
                <div class="section-card-head">
                    <span class="section-no">3</span>
                    <h5>Investment sought</h5>
                </div>
                <div class="detail-grid">
                    <div><dt>Total investment required</dt>
                        <dd>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($required): ?>
                                <span class="d-block fs-5 fw-bold text-brand"><?php echo e($lsl($required)); ?></span>
                                <span class="small text-muted">≈ <?php echo e($usd($required)); ?></span>
                            <?php else: ?> — <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </dd>
                    </div>
                    <div><dt>Type of investment</dt>
                        <dd class="d-flex flex-wrap gap-1">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $list('investment_types', $eoi->investment_types); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $t): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <span class="tag"><?php echo e($t); ?></span>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?> — <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </dd>
                    </div>
                </div>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($eoi->use_of_funds): ?>
                    <dt class="detail-label mt-3">How the investment would be used</dt>
                    <div class="d-flex flex-wrap gap-2 mt-2">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $list('use_of_funds', array_diff($eoi->use_of_funds, ['other'])); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $u): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <span class="tag tag-accent"><i class="bi bi-check2"></i> <?php echo e($u); ?></span>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(in_array('other', $eoi->use_of_funds, true) && $eoi->use_of_funds_other): ?>
                            <span class="tag tag-accent"><i class="bi bi-check2"></i> <?php echo e($eoi->use_of_funds_other); ?></span>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($eoi->use_of_funds_description): ?>
                    <div class="quote mt-3">
                        <?php echo e($eoi->use_of_funds_description); ?>

                        <div class="quote-meta"><?php echo e($wordCount($eoi->use_of_funds_description)); ?> words</div>
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </section>

            
            <section id="sec-investor" class="section-card">
                <div class="section-card-head">
                    <span class="section-no">4</span>
                    <h5>Investor engagement</h5>
                </div>

                <?php
                    $ladder = ['none' => 'None', 'discussions' => 'Discussions', 'interest' => 'Interest', 'loi' => 'LOI', 'term_sheet' => 'Term Sheet', 'committed' => 'Committed'];
                    $pos    = array_search($eoi->investor_status, array_keys($ladder), true);
                ?>
                <div class="ladder mb-3">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = array_values($ladder); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $stepLabel): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <div class="ladder-step <?php echo e($pos !== false && $i <= $pos ? 'on' : ''); ?> <?php echo e($pos === $i ? 'current' : ''); ?>">
                            <span></span>
                            <small><?php echo e($stepLabel); ?></small>
                        </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </div>

                <div class="detail-grid">
                    <div><dt>Status</dt><dd><?php echo e($label('investor_status', $eoi->investor_status)); ?><?php echo e($eoi->investor_status === 'other' && $eoi->investor_status_other ? ': '.$eoi->investor_status_other : ''); ?></dd></div>
                    <div><dt>Investor</dt><dd><?php echo e($eoi->investor_name ?: '—'); ?></dd></div>
                    <div><dt>Amount indicated / committed</dt>
                        <dd><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($committed): ?> <?php echo e($lsl($committed)); ?> <span class="small text-muted">(≈ <?php echo e($usd($committed)); ?>)</span> <?php else: ?> — <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></dd>
                    </div>
                    <div><dt>Evidence</dt>
                        <dd class="d-flex flex-wrap gap-1">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $list('investor_evidence', $eoi->investor_evidence); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ev): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <span class="tag"><i class="bi bi-file-earmark-check"></i> <?php echo e($ev); ?></span>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?> — <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </dd>
                    </div>
                </div>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($required > 0): ?>
                    <div class="bg-light rounded-3 p-3 mt-3">
                        <div class="d-flex justify-content-between small mb-1">
                            <span class="fw-semibold text-navy">Investor coverage of requirement</span>
                            <span class="fw-bold text-brand"><?php echo e($coverage); ?>%</span>
                        </div>
                        <div class="progress" style="height: 10px;">
                            <div class="progress-bar bg-accent" style="width: <?php echo e($coverage); ?>%;"></div>
                        </div>
                        <div class="d-flex justify-content-between flex-wrap gap-2 small text-muted mt-1">
                            <span>Investor: <?php echo e($lsl($committed)); ?> (≈ <?php echo e($usd($committed)); ?>)</span>
                            <span>Gap: <?php echo e($lsl($gap)); ?> (≈ <?php echo e($usd($gap)); ?>)</span>
                        </div>
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </section>

            
            <section id="sec-support" class="section-card">
                <div class="section-card-head">
                    <span class="section-no">5</span>
                    <h5>Support requested &amp; declaration</h5>
                </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($eoi->support_need): ?>
                    <div class="callout d-flex align-items-center gap-3">
                        <i class="bi bi-signpost-split"></i>
                        <div><?php echo e($label('support_need', $eoi->support_need)); ?></div>
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                <div class="border rounded-3 p-3 mt-3">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <i class="bi <?php echo e($eoi->declaration_agree ? 'bi-patch-check-fill text-accent' : 'bi-x-circle text-danger'); ?> fs-5"></i>
                        <strong class="text-navy"><?php echo e($eoi->declaration_agree ? 'Declaration accepted' : 'Declaration not accepted'); ?></strong>
                    </div>
                    <p class="small text-muted mb-2">
                        Applicant confirmed the information is accurate and consented to LEHSFF/CAFI using it to assess the enterprise for
                        investment facilitation, investor matchmaking and/or participation in the CAFI Co-Investment Facility.
                    </p>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($eoi->declaration_name): ?>
                        <div class="small"><span class="text-muted">Signed:</span> <strong><?php echo e($eoi->declaration_name); ?></strong>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($eoi->declaration_date): ?> <span class="text-muted ms-2">on</span> <?php echo e($eoi->declaration_date->format('d M Y')); ?> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </section>
        </div>

        
        <div class="col-xl-4">
            <div class="sticky-side">

                
                <div class="card card-modern mb-3">
                    <div class="card-body">
                        <div class="card-title-sm"><i class="bi bi-signpost-2"></i> Decision gates</div>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $gates; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $g): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <?php [$gsLabel, $gsClass, $gsIcon] = $gateStates[$g['state']]; ?>
                            <a href="#" wire:click.prevent="showTab('<?php echo e($key); ?>')" class="gate-row">
                                <span class="page-tabs-code"><?php echo e($g['code']); ?></span>
                                <span class="flex-grow-1 small fw-semibold text-navy"><?php echo e($g['label']); ?></span>
                                <span class="pill <?php echo e($gsClass); ?>"><i class="bi <?php echo e($gsIcon); ?>"></i> <?php echo e($gsLabel); ?></span>
                            </a>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </div>
                </div>

                
                <div class="card card-modern mb-3">
                    <div class="card-body">
                        <div class="card-title-sm"><i class="bi bi-compass"></i> What happens next</div>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! $isOwner && in_array($eoi->status, ['submitted', 'in_review'], true)): ?>
                            <p class="small text-muted mb-2">This EOI is waiting for eligibility screening.</p>
                            <button type="button" class="btn btn-sm btn-brand w-100" wire:click="showTab('g1')"><i class="bi bi-funnel me-1"></i> Open G1 · Eligibility screening</button>
                        <?php elseif(in_array($eoi->status, ['submitted', 'in_review'], true)): ?>
                            <p class="small text-muted mb-0">The LEHSFF team is reviewing your EOI. You'll be contacted with a recommended pathway, usually within <strong>10 working days</strong>.</p>
                        <?php elseif($eoi->status === 'invited'): ?>
                            <p class="small text-muted mb-0"><?php echo e($isOwner ? "You've been invited to complete the formal Co-Investment application and due diligence." : 'Invited to the Co-Investment application. Due diligence (G2) is next.'); ?></p>
                        <?php elseif($eoi->status === 'returned'): ?>
                            <p class="small text-muted mb-0"><?php echo e($isOwner ? 'Update your EOI with the requested information and resubmit.' : 'Waiting for the applicant to update and resubmit.'); ?></p>
                        <?php elseif($eoi->status === 'draft'): ?>
                            <p class="small text-muted mb-2">This EOI hasn't been submitted yet.</p>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isOwner): ?>
                                <button type="button" class="btn btn-sm btn-brand w-100" wire:click="$dispatch('edit-application', { id: <?php echo e($eoi->id); ?> })">Continue &amp; submit</button>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <?php else: ?>
                            <p class="small text-muted mb-0">No further action is required.</p>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                </div>

                
                <div class="card card-modern mb-3">
                    <div class="card-body">
                        <div class="card-title-sm"><i class="bi bi-person-badge"></i> Contact</div>
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <span class="avatar"><?php echo e($initials($eoi->representative_name ?: $eoi->user?->name)); ?></span>
                            <div>
                                <div class="fw-semibold text-navy"><?php echo e($eoi->representative_name ?: $eoi->user?->name); ?></div>
                                <div class="small text-muted">Founder / authorised representative</div>
                            </div>
                        </div>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($eoi->email): ?> <a href="mailto:<?php echo e($eoi->email); ?>" class="contact-line"><i class="bi bi-envelope"></i> <?php echo e($eoi->email); ?></a> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($eoi->phone): ?> <a href="tel:<?php echo e($eoi->phone); ?>" class="contact-line"><i class="bi bi-telephone"></i> <?php echo e($eoi->phone); ?></a> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($eoi->website): ?> <span class="contact-line"><i class="bi bi-globe"></i> <?php echo e($eoi->website); ?></span> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! $isOwner && $eoi->user_id && \Illuminate\Support\Facades\Route::has('profile.index')): ?>
                            <a href="<?php echo e(route('profile.index', $eoi->user_id)); ?>" class="contact-line"><i class="bi bi-building"></i> View enterprise profile</a>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                </div>

                
                <div class="card card-modern mb-3">
                    <div class="card-body">
                        <div class="card-title-sm"><i class="bi bi-folder2-open"></i> Investor evidence</div>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $list('investor_evidence', array_diff($eoi->investor_evidence ?? [], ['none_available', 'not_applicable'])); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $doc): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <div class="file-row">
                                <span class="file-row-icon"><i class="bi bi-file-earmark-pdf"></i></span>
                                <div class="flex-grow-1 small">
                                    <div class="fw-semibold text-navy"><?php echo e($doc); ?></div>
                                    <div class="text-muted">Requested at CI application stage</div>
                                </div>
                            </div>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                            <p class="small text-muted mb-0">No documents listed.</p>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                </div>

                
                <div class="card card-modern">
                    <div class="card-body">
                        <div class="card-title-sm"><i class="bi bi-clock-history"></i> Activity</div>
                        <ul class="activity">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $activity; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $a): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <li>
                                    <span class="activity-icon tone-<?php echo e($a['tone']); ?>"><i class="bi <?php echo e($a['icon']); ?>"></i></span>
                                    <div>
                                        <div class="fw-semibold small text-navy"><?php echo e($a['title']); ?></div>
                                        <div class="small text-muted"><?php echo e($a['text']); ?></div>
                                        <div class="activity-time" title="<?php echo e($a['when']?->format('d M Y H:i')); ?>"><?php echo e($a['when']?->diffForHumans()); ?></div>
                                    </div>
                                </li>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php elseif($tab === 'investments'): ?>
    
    <?php
$__split = function ($name, $params = []) {
    return [$name, $params];
};
[$__name, $__params] = $__split('expression-of-interest.investments.investment-history', ['eoi-id' => $eoi->id]);

$__keyOuter = $__key ?? null;

$__key = 'investments-'.e($eoi->id).'';
$__componentSlots = [];

$__key ??= \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::generateKey('lw-585450237-0', $__key);

$__html = app('livewire')->mount($__name, $__params, $__key, $__componentSlots);

echo $__html;

unset($__html);
unset($__key);
$__key = $__keyOuter;
unset($__keyOuter);
unset($__name);
unset($__params);
unset($__componentSlots);
unset($__split);
?>

    <?php else: ?>
    
    <?php
        $gate = $gates[$tab];
        [$gsLabel, $gsClass, $gsIcon] = $gateStates[$gate['state']];
        $i    = array_search($tab, $gateKeys, true);
        $prev = $i > 0 ? $gates[$gateKeys[$i - 1]] : null;
    ?>

    <div class="card card-modern mb-4">
        <div class="card-body d-flex flex-wrap align-items-center gap-3">
            <span class="stat-icon tone-primary"><i class="bi <?php echo e($gate['icon']); ?>"></i></span>
            <div class="me-auto">
                <div class="small text-muted fw-semibold">Gate <?php echo e(substr($gate['code'], 1)); ?> of <?php echo e(count($gates)); ?></div>
                <h5 class="mb-0 text-navy fw-bold"><?php echo e($gate['label']); ?></h5>
            </div>
            <span class="pill <?php echo e($gsClass); ?>"><i class="bi <?php echo e($gsIcon); ?>"></i> <?php echo e($gsLabel); ?></span>
        </div>
    </div>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($gate['state'] === 'locked'): ?>
        <div class="callout d-flex align-items-center gap-3 mb-4">
            <i class="bi bi-lock-fill fs-5 text-faint"></i>
            <div class="small">
                <strong>This gate is locked.</strong>
                It opens once <?php echo e($prev ? $prev['code'].' — '.$prev['label'] : 'the EOI is submitted'); ?> has been passed. Below is a preview of what will be assessed.
            </div>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <div class="row g-4">
        <div class="col-xl-8">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($tab === 'g1'): ?> <?php
$__split = function ($name, $params = []) {
    return [$name, $params];
};
[$__name, $__params] = $__split('expression-of-interest.eligibility.check-eligibility', ['eoi-id' => $eoi->id]);

$__keyOuter = $__key ?? null;

$__key = 'g1-'.e($eoi->id).'';
$__componentSlots = [];

$__key ??= \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::generateKey('lw-585450237-1', $__key);

$__html = app('livewire')->mount($__name, $__params, $__key, $__componentSlots);

echo $__html;

unset($__html);
unset($__key);
$__key = $__keyOuter;
unset($__keyOuter);
unset($__name);
unset($__params);
unset($__componentSlots);
unset($__split);
?> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($tab === 'g2'): ?> <?php
$__split = function ($name, $params = []) {
    return [$name, $params];
};
[$__name, $__params] = $__split('expression-of-interest.due-diligence.due-deligence-check', ['eoi-id' => $eoi->id]);

$__keyOuter = $__key ?? null;

$__key = 'g2-'.e($eoi->id).'';
$__componentSlots = [];

$__key ??= \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::generateKey('lw-585450237-2', $__key);

$__html = app('livewire')->mount($__name, $__params, $__key, $__componentSlots);

echo $__html;

unset($__html);
unset($__key);
$__key = $__keyOuter;
unset($__keyOuter);
unset($__name);
unset($__params);
unset($__componentSlots);
unset($__split);
?> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($tab === 'g3'): ?> <?php
$__split = function ($name, $params = []) {
    return [$name, $params];
};
[$__name, $__params] = $__split('expression-of-interest.committee.committee-review', ['eoi-id' => $eoi->id]);

$__keyOuter = $__key ?? null;

$__key = 'g3-'.e($eoi->id).'';
$__componentSlots = [];

$__key ??= \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::generateKey('lw-585450237-3', $__key);

$__html = app('livewire')->mount($__name, $__params, $__key, $__componentSlots);

echo $__html;

unset($__html);
unset($__key);
$__key = $__keyOuter;
unset($__keyOuter);
unset($__name);
unset($__params);
unset($__componentSlots);
unset($__split);
?> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($tab === 'readiness'): ?> <?php
$__split = function ($name, $params = []) {
    return [$name, $params];
};
[$__name, $__params] = $__split('expression-of-interest.readiness.grant-readiness', ['eoi-id' => $eoi->id]);

$__keyOuter = $__key ?? null;

$__key = 'readiness-'.e($eoi->id).'';
$__componentSlots = [];

$__key ??= \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::generateKey('lw-585450237-4', $__key);

$__html = app('livewire')->mount($__name, $__params, $__key, $__componentSlots);

echo $__html;

unset($__html);
unset($__key);
$__key = $__keyOuter;
unset($__keyOuter);
unset($__name);
unset($__params);
unset($__componentSlots);
unset($__split);
?> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($tab === 'g4'): ?> <?php
$__split = function ($name, $params = []) {
    return [$name, $params];
};
[$__name, $__params] = $__split('expression-of-interest.authorisation.authorise-application', ['eoi-id' => $eoi->id]);

$__keyOuter = $__key ?? null;

$__key = 'g4-'.e($eoi->id).'';
$__componentSlots = [];

$__key ??= \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::generateKey('lw-585450237-5', $__key);

$__html = app('livewire')->mount($__name, $__params, $__key, $__componentSlots);

echo $__html;

unset($__html);
unset($__key);
$__key = $__keyOuter;
unset($__keyOuter);
unset($__name);
unset($__params);
unset($__componentSlots);
unset($__split);
?> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($tab === 'g5'): ?> <?php
$__split = function ($name, $params = []) {
    return [$name, $params];
};
[$__name, $__params] = $__split('expression-of-interest.conditions.condition-review', ['eoi-id' => $eoi->id]);

$__keyOuter = $__key ?? null;

$__key = 'g5-'.e($eoi->id).'';
$__componentSlots = [];

$__key ??= \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::generateKey('lw-585450237-6', $__key);

$__html = app('livewire')->mount($__name, $__params, $__key, $__componentSlots);

echo $__html;

unset($__html);
unset($__key);
$__key = $__keyOuter;
unset($__keyOuter);
unset($__name);
unset($__params);
unset($__componentSlots);
unset($__split);
?> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>

        <div class="col-xl-4">
            <div class="sticky-side">
                <div class="card card-modern">
                    <div class="card-body">
                        <div class="card-title-sm"><i class="bi bi-signpost-2"></i> All gates</div>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $gates; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $g): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <?php [$l, $c, $ic] = $gateStates[$g['state']]; ?>
                            <a href="#" wire:click.prevent="showTab('<?php echo e($key); ?>')" class="gate-row <?php echo e($key === $tab ? 'active' : ''); ?>">
                                <span class="page-tabs-code"><?php echo e($g['code']); ?></span>
                                <span class="flex-grow-1 small fw-semibold text-navy"><?php echo e($g['label']); ?></span>
                                <span class="pill <?php echo e($c); ?>" title="<?php echo e($l); ?>"><i class="bi <?php echo e($ic); ?>"></i></span>
                            </a>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>


<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isOwner): ?>
    <?php
$__split = function ($name, $params = []) {
    return [$name, $params];
};
[$__name, $__params] = $__split('expression-of-interest.drawer.express-interest', []);

$__keyOuter = $__key ?? null;

$__key = null;
$__componentSlots = [];

$__key ??= \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::generateKey('lw-585450237-7', $__key);

$__html = app('livewire')->mount($__name, $__params, $__key, $__componentSlots);

echo $__html;

unset($__html);
unset($__key);
$__key = $__keyOuter;
unset($__keyOuter);
unset($__name);
unset($__params);
unset($__componentSlots);
unset($__split);
?>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div><?php /**PATH C:\laragon\www\co-investment-facility\storage\framework\views/livewire/views/e37d4cee.blade.php ENDPATH**/ ?>