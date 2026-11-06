<!DOCTYPE html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">

    <title>Co-Investment Facility | <?php echo e(config('app.name', 'LEHSFF')); ?></title>
    <meta name="description" content="The LEHSFF Co-Investment Facility matches private investment in Basotho enterprises with a non-repayable CAFI grant. How it works, who can apply and what investors get.">

    
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />

    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <style>
        :root {
            --cif-primary:       #29317D;
            --cif-primary-dark:  #1f2663;
            --cif-accent:        #089E49;
            --cif-accent-dark:   #07863e;
            --cif-navy:          #0d1a3a;
        }

        html { scroll-behavior: smooth; }
        body { font-family: 'Figtree', system-ui, sans-serif; color: #334155; background: #f8fafc; }
        section[id] { scroll-margin-top: 1rem; }

        /* ---------- Helpers ---------- */
        .text-cif-primary { color: var(--cif-primary) !important; }
        .text-cif-accent  { color: var(--cif-accent) !important; }
        .text-cif-navy    { color: var(--cif-navy) !important; }
        .bg-cif-primary   { background: var(--cif-primary) !important; }
        .bg-cif-navy      { background: var(--cif-navy) !important; }
        .text-white-60    { color: rgba(255,255,255,.6) !important; }
        .text-white-75    { color: rgba(255,255,255,.75) !important; }
        .eyebrow { font-size: .8rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--cif-accent); }

        /* ---------- Buttons ---------- */
        .btn-cif-primary { background: var(--cif-primary); border-color: var(--cif-primary); color: #fff; font-weight: 600; }
        .btn-cif-primary:hover, .btn-cif-primary:focus { background: var(--cif-primary-dark); border-color: var(--cif-primary-dark); color: #fff; }
        .btn-cif-accent  { background: var(--cif-accent); border-color: var(--cif-accent); color: #fff; font-weight: 600; }
        .btn-cif-accent:hover, .btn-cif-accent:focus { background: var(--cif-accent-dark); border-color: var(--cif-accent-dark); color: #fff; }
        .btn-outline-light-soft { border: 1px solid rgba(255,255,255,.3); color: #fff; font-weight: 600; }
        .btn-outline-light-soft:hover { background: rgba(255,255,255,.1); color: #fff; }
        .btn-link-cif { background: none; border: 0; padding: 0; font-weight: 600; color: var(--cif-primary); text-decoration: none; }
        .btn-link-cif:hover { text-decoration: underline; }
        .btn-link-cif.accent { color: var(--cif-accent); }

        /* ---------- Navbar ---------- */
        .cif-nav { position: absolute; inset: 0 0 auto 0; z-index: 30; }
        .cif-nav .nav-link { color: rgba(255,255,255,.75); font-weight: 500; padding: .5rem .7rem; }
        .cif-nav .nav-link:hover { color: #fff; }
        .brand-mark { width: 40px; height: 40px; border-radius: 12px; background: var(--cif-accent); color: #fff;
                      display: inline-flex; align-items: center; justify-content: center; font-weight: 800; }

        /* ---------- Hero ---------- */
        .hero { position: relative; overflow: hidden; background: var(--cif-navy);
                background-image:
                    linear-gradient(rgba(255,255,255,.04) 1px, transparent 1px),
                    linear-gradient(90deg, rgba(255,255,255,.04) 1px, transparent 1px);
                background-size: 48px 48px; padding: 9rem 0 6rem; }
        .hero .glow { position: absolute; border-radius: 50%; filter: blur(80px); pointer-events: none; }
        .hero .glow-1 { width: 480px; height: 480px; left: -160px; top: -160px; background: var(--cif-primary); opacity: .6; }
        .hero .glow-2 { width: 420px; height: 420px; right: 0; bottom: -190px; background: var(--cif-accent); opacity: .2; }
        .hero h1 { font-size: clamp(2.2rem, 4.5vw, 3.6rem); font-weight: 800; line-height: 1.1; color: #fff; }
        .hero-badge { display: inline-flex; align-items: center; gap: .5rem; padding: .4rem 1rem; border-radius: 999px;
                      border: 1px solid rgba(8,158,73,.4); background: rgba(8,158,73,.1); color: #6ee7a8; font-size: .875rem; font-weight: 500; }
        .hero-badge .dot { width: 8px; height: 8px; border-radius: 50%; background: var(--cif-accent); }
        .hero-stats { border-top: 1px solid rgba(255,255,255,.1); }
        .hero-stats dt { font-size: .72rem; letter-spacing: .08em; text-transform: uppercase; color: rgba(255,255,255,.5); font-weight: 500; }
        .hero-stats dd { font-size: 2rem; font-weight: 700; color: #fff; margin: 0; }

        /* ---------- Auth card ---------- */
        .auth-card { background: #fff; border-radius: 1rem; padding: 2.25rem; box-shadow: 0 25px 50px -12px rgba(0,0,0,.35);
                     max-width: 460px; margin: 0 auto; }
        .auth-tabs { display: grid; grid-template-columns: 1fr 1fr; background: #f1f5f9; border-radius: .75rem; padding: 4px; }
        .auth-tab { border: 0; background: transparent; border-radius: .6rem; padding: .6rem; font-weight: 600; font-size: .9rem; color: #64748b; transition: all .2s; }
        .auth-tab.is-active { background: #fff; color: var(--cif-primary); box-shadow: 0 1px 3px rgba(13,26,58,.12); }
        .auth-panel[hidden] { display: none !important; }
        .auth-panel.is-entering { animation: authIn .28s ease-out; }
        @keyframes authIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: none; } }

        .auth-card .form-label { font-size: .875rem; font-weight: 500; color: #334155; margin-bottom: .35rem; }
        .auth-card .form-control { padding: .7rem .9rem; font-size: .9rem; border-color: #cbd5e1; border-radius: .5rem; }
        .auth-card .form-control:focus { border-color: var(--cif-primary); box-shadow: 0 0 0 .2rem rgba(41,49,125,.15); }
        .auth-card .input-group-text { background: #fff; border-color: #cbd5e1; color: #94a3b8; }
        .auth-card .input-group > .input-group-text + .form-control { border-left: 0; }
        .auth-card .input-group > .form-control.has-toggle { border-right: 0; }
        .auth-card .input-group .btn-toggle { background: #fff; border: 1px solid #cbd5e1; border-left: 0; color: #94a3b8; }
        .auth-card .input-group .btn-toggle:hover { color: var(--cif-primary); }
        .auth-card .input-group:focus-within .input-group-text,
        .auth-card .input-group:focus-within .btn-toggle,
        .auth-card .input-group:focus-within .form-control { border-color: var(--cif-primary); box-shadow: none; }
        .auth-card .input-group:focus-within { box-shadow: 0 0 0 .2rem rgba(41,49,125,.15); border-radius: .5rem; }
        .auth-card .form-check-input:checked { background-color: var(--cif-primary); border-color: var(--cif-primary); }
        .auth-card .btn-lg { font-size: .95rem; padding: .8rem; border-radius: .5rem; }

        /* ---------- Sections ---------- */
        .section { padding: 5rem 0; }
        .section-title { font-weight: 700; color: var(--cif-navy); font-size: clamp(1.8rem, 3vw, 2.4rem); }

        .pathway-card { position: relative; overflow: hidden; background: #fff; border: 1px solid #e2e8f0; border-radius: 1rem;
                        padding: 2rem; height: 100%; transition: transform .2s, box-shadow .2s; }
        .pathway-card:hover { transform: translateY(-4px); box-shadow: 0 12px 30px rgba(13,26,58,.1); }
        .pathway-card::before { content: ''; position: absolute; inset: 0 0 auto 0; height: 4px; background: var(--bar); }
        .icon-box { width: 48px; height: 48px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.35rem; flex-shrink: 0; }
        .icon-box.sm { width: 40px; height: 40px; font-size: 1.1rem; border-radius: 10px; }
        .icon-box.primary { background: rgba(41,49,125,.1); color: var(--cif-primary); }
        .icon-box.accent  { background: rgba(8,158,73,.1);  color: var(--cif-accent); }
        .arrow-link { font-weight: 600; text-decoration: none; }
        .arrow-link:hover { text-decoration: underline; }

        .info-tile { background: #fff; border: 1px solid #e2e8f0; border-radius: .75rem; padding: 1.5rem; height: 100%; }

        /* How the money works */
        .money-flow { display: grid; grid-template-columns: 1fr auto 1fr auto 1fr; gap: 1rem; align-items: stretch; }
        .money-flow .arrow { display: flex; align-items: center; color: #94a3b8; font-size: 1.5rem; }
        .money-box { background: #fff; border: 1px solid #e2e8f0; border-radius: 1rem; padding: 1.5rem; text-align: center; }
        .money-box .big { font-size: 1.8rem; font-weight: 800; color: var(--cif-navy); line-height: 1; }
        @media (max-width: 767.98px) { .money-flow { grid-template-columns: 1fr; } .money-flow .arrow { justify-content: center; transform: rotate(90deg); } }

        /* Audience lists */
        .audience-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 1rem; padding: 2rem; height: 100%; }
        .tick-list { list-style: none; padding: 0; margin: 0; }
        .tick-list li { display: flex; gap: .6rem; padding: .45rem 0; font-size: .92rem; }
        .tick-list li i { color: var(--cif-accent); margin-top: .15rem; }
        .tick-list.primary li i { color: var(--cif-primary); }

        /* Gate timeline */
        .timeline { position: relative; margin: 0 auto; max-width: 900px; }
        .timeline::before { content: ''; position: absolute; left: 27px; top: 8px; bottom: 8px; width: 2px;
                            background: linear-gradient(180deg, var(--cif-primary), var(--cif-accent)); }
        .tl-item { position: relative; display: flex; gap: 1.25rem; padding-bottom: 1.5rem; }
        .tl-item:last-child { padding-bottom: 0; }
        .gate-no { position: relative; z-index: 1; flex-shrink: 0; width: 56px; height: 56px; border-radius: 50%; background: var(--cif-primary);
                   color: #fff; font-weight: 700; font-size: 1.05rem; display: flex; align-items: center; justify-content: center;
                   border: 4px solid #f8fafc; box-shadow: 0 4px 10px rgba(13,26,58,.15); }
        .gate-no.accent { background: var(--cif-accent); }
        .tl-body { background: #fff; border: 1px solid #e2e8f0; border-radius: .9rem; padding: 1.1rem 1.3rem; flex: 1; }
        .tl-body h3 { font-size: 1.02rem; font-weight: 700; color: var(--cif-navy); margin: 0; }
        .tl-phase { font-size: .7rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: #94a3b8; }
        .tl-meta { display: flex; flex-wrap: wrap; gap: .4rem 1rem; font-size: .8rem; margin-top: .6rem; }
        .tl-meta span { color: #64748b; }
        .tl-meta strong { color: var(--cif-navy); font-weight: 600; }

        /* Key rules */
        .rule { display: flex; gap: .9rem; }
        .rule-no { flex-shrink: 0; width: 32px; height: 32px; border-radius: 8px; background: rgba(41,49,125,.08); color: var(--cif-primary);
                   font-weight: 700; display: flex; align-items: center; justify-content: center; font-size: .9rem; }

        /* FAQ */
        .faq .accordion-item { border: 1px solid #e2e8f0; border-radius: .75rem !important; overflow: hidden; margin-bottom: .75rem; }
        .faq .accordion-button { font-weight: 600; color: var(--cif-navy); }
        .faq .accordion-button:not(.collapsed) { background: rgba(41,49,125,.05); color: var(--cif-primary); box-shadow: none; }
        .faq .accordion-button:focus { box-shadow: 0 0 0 .2rem rgba(41,49,125,.15); }
        .faq .accordion-body { font-size: .92rem; color: #475569; }

        .cta-box { position: relative; overflow: hidden; background: var(--cif-primary); border-radius: 1.5rem; padding: 3.5rem; }
        .cta-box .glow { position: absolute; right: -80px; top: -80px; width: 290px; height: 290px; border-radius: 50%;
                         background: var(--cif-accent); opacity: .3; filter: blur(70px); }
        .check-item { background: rgba(255,255,255,.1); border-radius: .5rem; padding: .8rem 1rem; color: #fff; font-size: .9rem; }
        .check-item i { color: #34d27b; }

        @media (max-width: 575.98px) { .auth-card { padding: 1.5rem; } .cta-box { padding: 2rem; } .section { padding: 3.5rem 0; } }
    </style>
</head>

<body>
<?php
    // Facility rules — read from config/cif.php so the page always matches what the system applies
    $P          = \App\Models\EnterpriseProfile::class;
    $ratioLabel = $P::grantRatioLabel();                                       // e.g. "50%"
    $ratio      = $P::grantRatio();
    $minInvUsd  = $P::minInvestorUsd();
    $minLesotho = (int) config('cif.co_investment.min_lesotho_pct', 70);
    $exInvest   = 1000000;                                                     // worked example, in Maloti
    $exGrant    = $exInvest * $ratio;

    $gates = [
        ['no' => 1, 'phase' => 'Apply & assess', 'title' => 'Eligibility Screening',
         'text'  => 'We check that the application is complete and that the enterprise, the investor and the proposed transaction fit the Facility’s rules.',
         'who'   => 'LEHSFF programme team', 'you' => 'Complete your enterprise profile and submit the Expression of Interest with the required documents.'],
        ['no' => 2, 'phase' => 'Apply & assess', 'title' => 'Due Diligence & ESGC',
         'text'  => 'A closer look at the business, its finances, legal and tax compliance, and environmental, social, governance and climate (ESGC) risks. The findings go into an Investment Appraisal Memorandum.',
         'who'   => 'LEHSFF programme team', 'you' => 'Answer questions and provide any additional documents requested.'],
        ['no' => 3, 'phase' => 'Decide', 'title' => 'Investment Committee',
         'text'  => 'An independent Investment Committee reviews the memorandum, scores the transaction against approved criteria and recommends approval, conditional approval, deferral or rejection.',
         'who'   => 'Investment Committee', 'you' => 'Nothing — you are informed of the outcome and any conditions.'],
        ['no' => 4, 'phase' => 'Decide', 'title' => 'Grant Readiness',
         'text'  => 'The grant file is prepared: the grant amount, the grant utilisation budget (at least '.$minLesotho.'% spent in Lesotho), the verified bank account and the Draft Grant Agreement.',
         'who'   => 'Grants Management, Hub Manager, PMU Director', 'you' => 'Make sure your budget, Lesotho spending and bank details in your profile are correct.'],
        ['no' => 5, 'phase' => 'Decide', 'title' => 'Final Authorisation',
         'text'  => 'The Grant Agreement is signed by the CAFI Managing Director and then by the Principal Secretary of the Ministry of Trade, Industry and Business Development — the formal approval of the grant.',
         'who'   => 'CAFI Managing Director, MTIBD Principal Secretary', 'you' => 'Nothing — you receive the executed Grant Agreement.'],
        ['no' => 6, 'phase' => 'Fund', 'title' => 'Conditions Precedent & Onboarding',
         'text'  => 'Before any payment, the conditions in the Grant Agreement are verified against documents, the investor’s money is confirmed, and you are briefed on your reporting and monitoring obligations.',
         'who'   => 'LEHSFF programme team and Finance', 'you' => 'Upload the evidence for each condition and proof that your investor has paid in.'],
        ['no' => 7, 'phase' => 'Fund', 'title' => 'Grant Disbursement',
         'text'  => 'The grant is paid in tranches. Each payment is released only against verified private investment, approved by a second person and paid into the verified account.',
         'who'   => 'Programme team, PMU Director and Finance', 'you' => 'Nothing — you are notified when each tranche is paid.'],
    ];
?>


<nav class="cif-nav">
    <div class="container py-3 d-flex align-items-center justify-content-between">
        <a href="#" class="d-flex align-items-center gap-3 text-decoration-none">
            <img src="<?php echo e(asset('images/lehsff-logo.png')); ?>" alt="Co-Investment Facility" style="height: 45px; width: auto;">
        </a>
        <ul class="nav d-none d-lg-flex gap-1">
            <li class="nav-item"><a class="nav-link" href="#about">About</a></li>
            <li class="nav-item"><a class="nav-link" href="#who">Enterprises &amp; investors</a></li>
            <li class="nav-item"><a class="nav-link" href="#how-it-works">How it works</a></li>
            <li class="nav-item"><a class="nav-link" href="#eligibility">Eligibility</a></li>
            <li class="nav-item"><a class="nav-link" href="#faq">FAQ</a></li>
        </ul>

        <a href="#auth" data-auth-show="register" class="btn btn-sm btn-outline-light-soft px-3 py-2">Submit EOI</a>
    </div>
</nav>


<section class="hero">
    <div class="glow glow-1"></div>
    <div class="glow glow-2"></div>

    <div class="container position-relative">
        <div class="row align-items-center g-5">

            
            <div class="col-lg-7">
                <span class="hero-badge mb-4"><span class="dot"></span> Now accepting Transaction EOIs</span>

                <h1 class="mb-4">
                    Unlocking capital for
                    <span class="text-cif-accent">Basotho enterprises</span>
                    through co-investment.
                </h1>

                <p class="fs-5 text-white-75 mb-0" style="max-width: 580px;">
                    When a private investor puts money into a growing Basotho enterprise, the LEHSFF Co-Investment Facility
                    adds a <strong class="text-white">non-repayable CAFI grant</strong> of <?php echo e($ratioLabel); ?> of that investment.
                    Apply with your investor, or let us match you with one.
                </p>

                <dl class="hero-stats row mt-5 pt-4 mb-0" style="max-width: 580px;">
                    <div class="col-4"><dt>Matching grant</dt><dd class="text-cif-accent"><?php echo e($ratioLabel); ?></dd></div>
                    <div class="col-4"><dt>Review gates</dt><dd><?php echo e(count($gates)); ?></dd></div>
                    <div class="col-4"><dt>Pathways</dt><dd>2</dd></div>
                </dl>
            </div>

            
            <div class="col-lg-5" id="auth" style="scroll-margin-top: 6rem;">
                <div class="auth-card">

                    
                    <div class="auth-tabs mb-4" role="tablist">
                        <button type="button" role="tab" class="auth-tab is-active" data-auth-show="login">Sign in</button>
                        <button type="button" role="tab" class="auth-tab" data-auth-show="register">Register</button>
                    </div>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('status')): ?>
                        <div class="alert alert-success py-2 small"><?php echo e(session('status')); ?></div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                    
                    <div class="auth-panel" data-auth-panel="login">
                        <?php
$__split = function ($name, $params = []) {
    return [$name, $params];
};
[$__name, $__params] = $__split('auth.login', []);

$__keyOuter = $__key ?? null;

$__key = null;
$__componentSlots = [];

$__key ??= \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::generateKey('lw-2912045727-0', $__key);

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
                    </div>

                    
                    <div class="auth-panel" data-auth-panel="register" hidden>
                        <?php
$__split = function ($name, $params = []) {
    return [$name, $params];
};
[$__name, $__params] = $__split('auth.register', []);

$__keyOuter = $__key ?? null;

$__key = null;
$__componentSlots = [];

$__key ??= \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::generateKey('lw-2912045727-1', $__key);

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
                    </div>

                </div>
            </div>
        </div>
    </div>
</section>


<section id="about" class="section bg-white">
    <div class="container">
        <div class="row g-5 align-items-center">
            <div class="col-lg-5">
                <div class="eyebrow">About the Facility</div>
                <h2 class="section-title mt-2">Private investment, matched with a grant</h2>
                <p class="text-secondary mt-3">
                    The Co-Investment Facility is run by the <strong>Lesotho Enterprise Hub &amp; Seed Financing Facility (LEHSFF)</strong>
                    under the <strong>Competitiveness and Financial Inclusion (CAFI) Project</strong>. Its purpose is to bring more private
                    capital to Basotho enterprises with real growth potential — creating jobs, especially for women and young people.
                </p>
                <p class="text-secondary">
                    The Facility does not replace investors. It works <em>alongside</em> them: when an investor commits money to an
                    enterprise, CAFI adds a matching grant, so the enterprise gets more capital to grow and the investor's money goes further.
                </p>
            </div>
            <div class="col-lg-7">
                <div class="money-flow">
                    <div class="money-box">
                        <div class="icon-box primary sm mb-2"><i class="bi bi-briefcase"></i></div>
                        <div class="small text-muted mb-1">Investor puts in</div>
                        <div class="big">M <?php echo e(number_format($exInvest)); ?></div>
                        <div class="small text-muted mt-1">equity, loan or other</div>
                    </div>
                    <div class="arrow"><i class="bi bi-plus-lg"></i></div>
                    <div class="money-box">
                        <div class="icon-box accent sm mb-2"><i class="bi bi-gift"></i></div>
                        <div class="small text-muted mb-1">CAFI matching grant</div>
                        <div class="big text-cif-accent">M <?php echo e(number_format($exGrant)); ?></div>
                        <div class="small text-muted mt-1"><?php echo e($ratioLabel); ?> · non-repayable</div>
                    </div>
                    <div class="arrow"><i class="bi bi-arrow-right"></i></div>
                    <div class="money-box" style="border-color: var(--cif-accent);">
                        <div class="icon-box accent sm mb-2"><i class="bi bi-graph-up-arrow"></i></div>
                        <div class="small text-muted mb-1">Total for growth</div>
                        <div class="big">M <?php echo e(number_format($exInvest + $exGrant)); ?></div>
                        <div class="small text-muted mt-1">for the approved budget</div>
                    </div>
                </div>
                <p class="small text-muted text-center mt-3 mb-0">Illustration only. The actual grant is set during the review and stated in the Grant Agreement.</p>

                <div class="row g-3 mt-2">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = [
                        ['bi-cash-coin', 'Non-repayable', 'The grant is not a loan and does not take shares in your company.'],
                        ['bi-layers', 'Paid in tranches', 'Released step by step, each time against verified investor money.'],
                        ['bi-geo-alt', 'Spent in Lesotho', 'At least '.$minLesotho.'% of the grant must be spent in Lesotho.'],
                    ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$icon, $t, $d]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <div class="col-md-4">
                            <div class="info-tile">
                                <i class="bi <?php echo e($icon); ?> text-cif-primary fs-4"></i>
                                <h3 class="h6 fw-bold text-cif-navy mt-2 mb-1"><?php echo e($t); ?></h3>
                                <p class="small text-secondary mb-0"><?php echo e($d); ?></p>
                            </div>
                        </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>


<section id="pathways" class="section">
    <div class="container">
        <div class="text-center mx-auto mb-5" style="max-width: 640px;">
            <div class="eyebrow">Two ways in</div>
            <h2 class="section-title mt-2">Choose your pathway</h2>
            <p class="text-secondary mt-3">
                Whether you already have an investor or need one, every transaction follows the same transparent review process.
            </p>
        </div>

        <div class="row g-4">
            <div class="col-md-6">
                <div class="pathway-card" style="--bar: var(--cif-primary);">
                    <div class="icon-box primary mb-4"><i class="bi bi-file-earmark-text"></i></div>
                    <div class="small fw-semibold text-uppercase text-muted">Pathway 1</div>
                    <h3 class="h5 fw-bold text-cif-navy mt-1">Direct Transaction EOI</h3>
                    <p class="text-secondary small mt-3">
                        You already have a committed investor. Submit one Expression of Interest with your enterprise,
                        investor, transaction structure and intended use of funds. A signed term sheet, letter of intent or
                        investment agreement shows the investor's commitment.
                    </p>
                    <a href="#auth" data-auth-show="register" class="arrow-link text-cif-primary">Start an EOI →</a>
                </div>
            </div>
            <div class="col-md-6">
                <div class="pathway-card" style="--bar: var(--cif-accent);">
                    <div class="icon-box accent mb-4"><i class="bi bi-arrow-left-right"></i></div>
                    <div class="small fw-semibold text-uppercase text-muted">Pathway 2</div>
                    <h3 class="h5 fw-bold text-cif-navy mt-1">LEHSFF Facilitated Matchmaking</h3>
                    <p class="text-secondary small mt-3">
                        No investor yet? Build your enterprise profile and we'll match you with investors by sector,
                        ticket size and investment preferences, then facilitate the introduction. Once an investor commits,
                        you continue with a Transaction EOI.
                    </p>
                    <a href="#auth" data-auth-show="register" class="arrow-link text-cif-accent">Find an investor →</a>
                </div>
            </div>
        </div>
    </div>
</section>


<section id="who" class="section bg-white">
    <div class="container">
        <div class="text-center mx-auto mb-5" style="max-width: 680px;">
            <div class="eyebrow">Who it is for</div>
            <h2 class="section-title mt-2">What the Facility means for you</h2>
        </div>

        <div class="row g-4">
            
            <div class="col-lg-6">
                <div class="audience-card">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="icon-box accent"><i class="bi bi-shop"></i></div>
                        <div>
                            <div class="small fw-semibold text-uppercase text-muted">For enterprises</div>
                            <h3 class="h5 fw-bold text-cif-navy mb-0">Grow with more capital</h3>
                        </div>
                    </div>
                    <div class="fw-semibold small text-cif-navy mt-3 mb-1">What you get</div>
                    <ul class="tick-list">
                        <li><i class="bi bi-check-circle-fill"></i> A non-repayable grant of <?php echo e($ratioLabel); ?> of what your investor puts in</li>
                        <li><i class="bi bi-check-circle-fill"></i> Help finding an investor if you do not have one yet</li>
                        <li><i class="bi bi-check-circle-fill"></i> A structured review that strengthens your business case and governance</li>
                        <li><i class="bi bi-check-circle-fill"></i> Follow-up support and monitoring once you are in the portfolio</li>
                    </ul>
                    <div class="fw-semibold small text-cif-navy mt-3 mb-1">What is expected of you</div>
                    <ul class="tick-list primary">
                        <li><i class="bi bi-dot"></i> Be registered in Lesotho (OBFC) and tax-compliant before the Grant Agreement is signed</li>
                        <li><i class="bi bi-dot"></i> Use the grant only for the approved budget, with at least <?php echo e($minLesotho); ?>% spent in Lesotho</li>
                        <li><i class="bi bi-dot"></i> Keep proper records and report regularly on spending, jobs and progress</li>
                        <li><i class="bi bi-dot"></i> Meet the environmental, social and governance commitments agreed with you</li>
                    </ul>
                    <a href="#auth" data-auth-show="register" class="btn btn-cif-accent mt-3">Register your enterprise</a>
                </div>
            </div>

            
            <div class="col-lg-6">
                <div class="audience-card">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="icon-box primary"><i class="bi bi-briefcase"></i></div>
                        <div>
                            <div class="small fw-semibold text-uppercase text-muted">For investors</div>
                            <h3 class="h5 fw-bold text-cif-navy mb-0">Make your investment go further</h3>
                        </div>
                    </div>
                    <div class="fw-semibold small text-cif-navy mt-3 mb-1">What you get</div>
                    <ul class="tick-list primary">
                        <li><i class="bi bi-check-circle-fill"></i> Your investee receives a grant on top of your money — more capital without diluting your stake</li>
                        <li><i class="bi bi-check-circle-fill"></i> An independent due diligence and ESGC review of the enterprise</li>
                        <li><i class="bi bi-check-circle-fill"></i> Access to a pipeline of screened Basotho enterprises through matchmaking</li>
                        <li><i class="bi bi-check-circle-fill"></i> Grant spending tied to an approved budget, with regular reporting</li>
                    </ul>
                    <div class="fw-semibold small text-cif-navy mt-3 mb-1">What is expected of you</div>
                    <ul class="tick-list">
                        <li><i class="bi bi-dot"></i> A firm commitment — investing at least US$ <?php echo e(number_format($minInvUsd)); ?> in the transaction</li>
                        <li><i class="bi bi-dot"></i> Proof of your investment (bank records or remittance confirmations) before each grant tranche</li>
                        <li><i class="bi bi-dot"></i> A signed investment agreement or term sheet with the enterprise</li>
                        <li><i class="bi bi-dot"></i> Cooperation with the review — identity and source-of-funds checks where needed</li>
                    </ul>
                    <a href="#contact" class="btn btn-cif-primary mt-3">Talk to us about investing</a>
                </div>
            </div>
        </div>
    </div>
</section>


<section id="how-it-works" class="section">
    <div class="container">
        <div class="text-center mx-auto mb-5" style="max-width: 680px;">
            <div class="eyebrow">The process</div>
            <h2 class="section-title mt-2"><?php echo e(count($gates)); ?> gates from application to funding</h2>
            <p class="text-secondary mt-3">
                Every transaction passes the same gates, each with a clear decision and a named decision-maker.
                You can follow your application online and are notified by e-mail whenever something is needed from you.
            </p>
        </div>

        <div class="timeline">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $gates; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $gate): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <div class="tl-item">
                    <div class="gate-no <?php echo e($gate['phase'] === 'Fund' ? 'accent' : ''); ?>"><?php echo e($gate['no']); ?></div>
                    <div class="tl-body">
                        <div class="tl-phase"><?php echo e($gate['phase']); ?></div>
                        <h3><?php echo e($gate['title']); ?></h3>
                        <p class="small text-secondary mt-1 mb-0"><?php echo e($gate['text']); ?></p>
                        <div class="tl-meta">
                            <span><i class="bi bi-person-check me-1"></i><strong>Decides:</strong> <?php echo e($gate['who']); ?></span>
                            <span><i class="bi bi-hand-index me-1"></i><strong>Your part:</strong> <?php echo e($gate['you']); ?></span>
                        </div>
                    </div>
                </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            <div class="tl-item">
                <div class="gate-no accent"><i class="bi bi-flag-fill"></i></div>
                <div class="tl-body">
                    <div class="tl-phase">After funding</div>
                    <h3>Portfolio monitoring &amp; support</h3>
                    <p class="small text-secondary mt-1 mb-0">
                        Funded enterprises join the LEHSFF portfolio. You report on spending, jobs and milestones at the agreed frequency,
                        and the team follows up on your commitments and supports your growth.
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>


<section class="section bg-white">
    <div class="container">
        <div class="row g-5">
            <div class="col-lg-4">
                <div class="eyebrow">Good to know</div>
                <h2 class="section-title mt-2">The key rules</h2>
                <p class="text-secondary mt-3">These rules protect public funds and make sure the grant reaches the enterprise it was approved for.</p>
            </div>
            <div class="col-lg-8">
                <div class="row g-4">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = [
                        ['The grant follows the investor', 'CAFI matches '.$ratioLabel.' of the private investment. A grant tranche is only paid once the matching investor money has actually arrived and been verified.'],
                        ['Approved budget only', 'The grant may only be spent on the categories in the approved grant utilisation budget. Changes need approval first.'],
                        ['Spend it in Lesotho', 'At least '.$minLesotho.'% of the grant must be spent in Lesotho. Any spending outside Lesotho must be justified.'],
                        ['Paid to the verified account', 'Payments go only to a bank account in the enterprise’s name, verified against bank documents.'],
                        ['Conditions first', 'Formal approval is not payment. The conditions in the Grant Agreement are verified before the first payment.'],
                        ['Report and stay compliant', 'Funded enterprises keep records, report regularly and meet their environmental, social and governance commitments.'],
                    ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => [$t, $d]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <div class="col-md-6">
                            <div class="rule">
                                <div class="rule-no"><?php echo e($i + 1); ?></div>
                                <div>
                                    <h3 class="h6 fw-bold text-cif-navy mb-1"><?php echo e($t); ?></h3>
                                    <p class="small text-secondary mb-0"><?php echo e($d); ?></p>
                                </div>
                            </div>
                        </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>


<section id="eligibility" class="section">
    <div class="container">
        <div class="cta-box">
            <div class="glow"></div>
            <div class="row align-items-center g-5 position-relative">
                <div class="col-lg-6">
                    <h2 class="fw-bold text-white" style="font-size: clamp(1.8rem, 3vw, 2.4rem);">Is your enterprise ready?</h2>
                    <p class="text-white-75 mt-3">Have these ready before you submit your Transaction EOI. Registering is free, and you can save your profile and finish it later.</p>
                    <div class="d-flex flex-wrap gap-3 mt-4">
                        <a href="#auth" data-auth-show="register" class="btn btn-cif-accent px-4 py-2">Register your enterprise</a>
                        <a href="#" class="btn btn-outline-light-soft px-4 py-2">Download guidelines</a>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="d-grid gap-2">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = [
                            'A registered enterprise operating in Lesotho (or ready to register with the OBFC)',
                            'A committed investor investing at least US$ '.number_format($minInvUsd).', or readiness for matchmaking',
                            'Business registration, ownership and ID documents',
                            'Financial statements, projections and tax / statutory compliance',
                            'A grant utilisation budget showing how the money will be spent',
                            'A bank account in the enterprise’s name',
                        ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <div class="check-item d-flex gap-2 align-items-start">
                                <i class="bi bi-check-circle-fill"></i> <span><?php echo e($item); ?></span>
                            </div>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>


<section id="faq" class="section bg-white">
    <div class="container" style="max-width: 860px;">
        <div class="text-center mb-5">
            <div class="eyebrow">Questions</div>
            <h2 class="section-title mt-2">Frequently asked questions</h2>
        </div>

        <div class="accordion faq" id="faqList">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = [
                ['Do we have to pay the grant back?', 'No. The CAFI matching grant is non-repayable and does not give CAFI shares in your company. It must, however, be used for the approved budget and reported on — misuse can lead to the grant being recovered.'],
                ['How much grant can we get?', 'The grant is '.$ratioLabel.' of the money your private investor(s) put into the transaction, subject to the Facility’s limits. The final amount is confirmed by the Investment Committee and written into the Grant Agreement.'],
                ['We do not have an investor yet. Can we still apply?', 'Yes. Register and complete your enterprise profile, and choose matchmaking. LEHSFF matches you with suitable investors and facilitates the introduction. Once an investor commits, you submit a Transaction EOI.'],
                ['What kind of investment counts?', 'Equity, convertible notes / SAFEs, loans or revenue-based financing from a private investor can all qualify. The investor must commit at least US$ '.number_format($minInvUsd).' and provide proof of the money when it is paid in.'],
                ['How long does the process take?', 'It depends mainly on how complete your application is and how quickly requested documents come in. You can follow each gate online, and you are e-mailed whenever something is needed from you.'],
                ['When is the grant paid?', 'After the Grant Agreement is signed and the conditions precedent are met. It is paid in tranches, each one released after your investor’s matching money for that tranche has been verified.'],
                ['What does the Facility check about investors?', 'The investor’s identity, commitment and the source and arrival of the investment, plus any conflicts of interest. These checks protect both the enterprise and the public funds.'],
                ['Who makes the decisions?', 'Screening and due diligence are done by the LEHSFF programme team, an independent Investment Committee recommends the transaction, and the Grant Agreement is signed by the CAFI Managing Director and the MTIBD Principal Secretary.'],
            ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => [$q, $a]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <div class="accordion-item">
                    <h3 class="accordion-header">
                        <button class="accordion-button <?php echo e($i ? 'collapsed' : ''); ?>" type="button" data-bs-toggle="collapse" data-bs-target="#faq<?php echo e($i); ?>" aria-expanded="<?php echo e($i ? 'false' : 'true'); ?>" aria-controls="faq<?php echo e($i); ?>">
                            <?php echo e($q); ?>

                        </button>
                    </h3>
                    <div id="faq<?php echo e($i); ?>" class="accordion-collapse collapse <?php echo e($i ? '' : 'show'); ?>" data-bs-parent="#faqList">
                        <div class="accordion-body"><?php echo e($a); ?></div>
                    </div>
                </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        </div>

        <div id="contact" class="info-tile d-flex flex-column flex-md-row align-items-md-center gap-3 mt-5">
            <div class="icon-box primary"><i class="bi bi-chat-dots"></i></div>
            <div class="flex-grow-1">
                <h3 class="h6 fw-bold text-cif-navy mb-1">Still have questions?</h3>
                <p class="small text-secondary mb-0">Investors interested in co-investing, and enterprises unsure whether they qualify, are welcome to contact the LEHSFF team.</p>
            </div>
            <a href="mailto:<?php echo e(config('mail.from.address')); ?>" class="btn btn-cif-primary">Contact us</a>
        </div>
    </div>
</section>


<footer class="bg-cif-navy text-white-60">
    <div class="container py-4 d-flex flex-column flex-md-row align-items-center justify-content-between gap-3 small">
        <div class="d-flex align-items-center gap-2">
            <span class="brand-mark" style="width:32px;height:32px;border-radius:8px;font-size:.75rem;">CI</span>
            <span>&copy; <?php echo e(date('Y')); ?> LEHSFF Co-Investment Facility · CAFI. All rights reserved.</span>
        </div>
        <div class="d-flex gap-4">
            <a href="#" class="text-white-60 text-decoration-none">Privacy</a>
            <a href="#" class="text-white-60 text-decoration-none">Terms</a>
            <a href="#contact" class="text-white-60 text-decoration-none">Support</a>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var panels = document.querySelectorAll('[data-auth-panel]');
    var tabs   = document.querySelectorAll('.auth-tab');
    var card   = document.getElementById('auth');

    function showAuth(view, scroll) {
        panels.forEach(function (panel) {
            var active = panel.getAttribute('data-auth-panel') === view;
            panel.hidden = !active;                       // native attribute: works without any CSS framework
            panel.classList.remove('is-entering');
            if (active) {
                void panel.offsetWidth;                   // restart fade-in
                panel.classList.add('is-entering');
                var first = panel.querySelector('input:not([type=hidden])');
                if (first) first.focus({ preventScroll: true });
            }
        });
        tabs.forEach(function (tab) {
            var active = tab.getAttribute('data-auth-show') === view;
            tab.classList.toggle('is-active', active);
            tab.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        if (scroll && card) card.scrollIntoView({ behavior: 'smooth', block: 'center' });
        if (history.replaceState) history.replaceState(null, '', '#' + view);
    }

    // Anything with data-auth-show switches the card (tabs, inline links, page CTAs)
    document.addEventListener('click', function (e) {
        var trigger = e.target.closest('[data-auth-show]');
        if (!trigger) return;
        e.preventDefault();
        showAuth(trigger.getAttribute('data-auth-show'), !card.contains(trigger));
    });

    // Password show / hide (delegated, so it keeps working after Livewire re-renders)
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-toggle-password]');
        if (!btn) return;
        var input = document.getElementById(btn.getAttribute('data-toggle-password'));
        if (!input) return;
        var show  = input.type === 'password';
        input.type = show ? 'text' : 'password';
        var icon = btn.querySelector('i');
        if (icon) icon.className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
        btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    });

    // /#register opens the register form directly
    if (location.hash === '#register') showAuth('register', false);
});
</script>

</body>
</html><?php /**PATH C:\laragon\www\co-investment-facility\resources\views/welcome.blade.php ENDPATH**/ ?>