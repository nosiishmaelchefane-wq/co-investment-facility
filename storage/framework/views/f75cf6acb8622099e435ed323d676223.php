
<?php
    $DD  = \App\Models\DueDiligence::class;
    $DC  = \App\Models\DueDiligenceCondition::class;
    $P   = \App\Models\EnterpriseProfile::class;
    // Amounts are stored in Maloti; shown in US$ first, Maloti underneath
    $m   = fn ($x) => $x === null || $x === '' || (float) $x == 0 ? '—' : 'US$ '.number_format((float) $x / $rate);
    $u   = fn ($x) => $x === null || $x === '' || (float) $x == 0 ? '' : 'M '.number_format((float) $x);
    $areas      = $dd->areas ?? [];
    $conditions = $dd->conditions;
    $expected   = $P::matchingGrant($v['private'] ?: null);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Investment Appraisal Memorandum — <?php echo e($eoi->reference); ?></title>
    <style>
        @page { margin: 28mm 18mm 22mm 18mm; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 9.5pt; color: #1f2937; line-height: 1.45; }
        header { position: fixed; top: -20mm; left: 0; right: 0; height: 14mm; border-bottom: 2px solid #0b2e59; }
        header .org { font-size: 8pt; color: #6b7280; }
        header .title { font-size: 11pt; font-weight: bold; color: #0b2e59; }
        footer { position: fixed; bottom: -14mm; left: 0; right: 0; height: 8mm; font-size: 7.5pt; color: #6b7280; border-top: 1px solid #d1d5db; padding-top: 2mm; }
        footer .page:after { content: counter(page); }
        h1 { font-size: 15pt; color: #0b2e59; margin: 0 0 2mm; }
        h2 { font-size: 11pt; color: #0b2e59; margin: 7mm 0 2mm; padding-bottom: 1mm; border-bottom: 1px solid #d1d5db; }
        h3 { font-size: 10pt; color: #0b2e59; margin: 0 0 1mm; }
        .muted { color: #6b7280; }
        .small { font-size: 8pt; }
        table { width: 100%; border-collapse: collapse; }
        table.grid td, table.grid th { border: 1px solid #d1d5db; padding: 2mm 2.5mm; vertical-align: top; text-align: left; }
        table.grid th { background: #eef2f7; color: #0b2e59; font-weight: bold; }
        table.kv td { padding: 1.2mm 2mm; vertical-align: top; border-bottom: 1px solid #eef0f3; }
        table.kv td.k { width: 38%; color: #6b7280; }
        .num { text-align: right; white-space: nowrap; }
        .usd { display: block; font-size: 7.5pt; color: #6b7280; }
        .q { border: 1px solid #d1d5db; border-left: 4px solid #0b2e59; padding: 3mm 4mm; margin-bottom: 3.5mm; page-break-inside: avoid; }
        .q .question { font-style: italic; color: #4b5563; margin-bottom: 2mm; }
        .q .answer { white-space: pre-line; }
        .pill { display: inline-block; padding: 0.5mm 2mm; border-radius: 2mm; font-size: 7.5pt; font-weight: bold; }
        .ok { background: #dcfce7; color: #166534; }
        .warn { background: #fef3c7; color: #92400e; }
        .bad { background: #fee2e2; color: #991b1b; }
        .grey { background: #f3f4f6; color: #374151; }
        .sign td { padding-top: 12mm; width: 50%; }
        .line { border-top: 1px solid #9ca3af; padding-top: 1mm; margin-right: 8mm; }
    </style>
</head>
<body>

<header>
    <div class="org">LEHSFF · CAFI Co-Investment Facility</div>
    <div class="title">Investment Appraisal Memorandum</div>
</header>
<footer>
    <?php echo e($eoi->reference); ?> · <?php echo e($eoi->business_name); ?> · Round <?php echo e($dd->round); ?> · Generated <?php echo e(now()->format('d M Y H:i')); ?> · Confidential — for the Investment Committee
    <span style="float: right">Page <span class="page"></span></span>
</footer>

<h1><?php echo e($profile?->registered_name ?: $eoi->business_name); ?></h1>
<div class="muted">
    Reference <?php echo e($eoi->reference); ?> · Gate 2 round <?php echo e($dd->round); ?>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($dd->isDecided()): ?>
        · Outcome: <strong><?php echo e($DD::OUTCOMES[$dd->outcome][0] ?? $dd->outcome); ?></strong> (<?php echo e($dd->decided_at->format('d M Y')); ?>)
    <?php else: ?>
        · <span class="pill warn">Draft — assessment in progress</span>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>


<h2>1. Enterprise and transaction</h2>
<table>
    <tr>
        <td style="width: 50%; padding-right: 4mm; vertical-align: top">
            <table class="kv">
                <tr><td class="k">Sector</td><td><?php echo e($profile?->sectorLabel() ?? '—'); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($profile?->sub_sector): ?> · <?php echo e($profile->sub_sector); ?><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></td></tr>
                <tr><td class="k">District</td><td><?php echo e($profile?->district ?? $eoi->district ?? '—'); ?></td></tr>
                <tr><td class="k">Operating since</td><td><?php echo e($profile?->commencedLabel() ?? $eoi->year_commenced ?? '—'); ?></td></tr>
                <tr><td class="k">OBFC registration</td><td><?php echo e($P::YES_NO_PROGRESS[$profile?->obfc_status ?? ''] ?? '—'); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($profile?->registration_no): ?> · <?php echo e($profile->registration_no); ?><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></td></tr>
                <tr><td class="k">Business model(s)</td><td><?php echo e($profile?->businessModelLabel() ?? '—'); ?></td></tr>
                <tr><td class="k">Employees</td><td><?php echo e($profile?->employees ?? '—'); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($profile?->expected_jobs): ?> · <?php echo e($profile->expected_jobs); ?> new jobs expected <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></td></tr>
            </table>
        </td>
        <td style="width: 50%; vertical-align: top">
            <table class="kv">
                <tr><td class="k">Investor(s)</td><td><?php echo e($dd->investor_names ?: ($eoi->investor_name ?? '—')); ?></td></tr>
                <tr><td class="k">Transaction stage</td><td><?php echo e($DD::STAGES[$dd->stage][0] ?? '—'); ?></td></tr>
                <tr><td class="k">Instrument</td><td><?php echo e($DD::INSTRUMENTS[$dd->instrument] ?? '—'); ?></td></tr>
                <tr><td class="k">ESGC risk</td><td><?php echo e($DD::RATINGS[$dd->esgc_category] ?? '—'); ?></td></tr>
                <tr><td class="k">Assessed by</td><td><?php echo e($dd->assessor?->name ?? '—'); ?></td></tr>
                <tr><td class="k">Date</td><td><?php echo e($dd->assessed_on?->format('d M Y') ?? '—'); ?></td></tr>
            </table>
        </td>
    </tr>
</table>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($dd->summary): ?>
    <p style="margin-top: 3mm"><strong>Summary.</strong> <?php echo e($dd->summary); ?></p>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

<table class="grid" style="margin-top: 3mm">
    <tr><th>Co-investment</th><th class="num">Amount</th><th>Note</th></tr>
    <tr>
        <td>Private capital (validated)</td>
        <td class="num"><?php echo e($m($v['private'])); ?><span class="usd"><?php echo e($u($v['private'])); ?></span></td>
        <td class="small"><?php echo e($v['private'] > 0 ? ($P::meetsInvestorMinimum($v['private']) ? 'Meets' : 'Below') .' the US$ '.number_format($P::minInvestorUsd()).' minimum' : ''); ?></td>
    </tr>
    <tr>
        <td>CAFI grant</td>
        <td class="num"><?php echo e($m($v['grant'])); ?><span class="usd"><?php echo e($u($v['grant'])); ?></span></td>
        <td class="small"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($expected): ?> Maximum <?php echo e($P::grantRatioLabel()); ?> of private capital = <?php echo e($m($expected)); ?> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></td>
    </tr>
    <tr>
        <th>Total transaction</th>
        <th class="num"><?php echo e($m($v['total'])); ?><span class="usd"><?php echo e($u($v['total'])); ?></span></th>
        <th class="small"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($profile?->project_cost): ?> Total project cost <?php echo e($m($profile->project_cost)); ?> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></th>
    </tr>
</table>


<h2>2. Transaction appraisal</h2>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $DD::memoQuestions($memo); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => [$title, $question]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
    <?php $mrow = $DD::memoRows($memo)[$k]; ?>
    <div class="q">
        <h3><?php echo e($loop->iteration); ?>. <?php echo e($title); ?></h3>
        <div class="question"><?php echo e($question); ?></div>
        <div class="answer"><?php echo nl2br(e(trim($mrow['text']) !== '' ? $mrow['text'] : 'Not answered.')); ?></div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($mrow['verified']): ?>
            <div class="question">Verified by <?php echo e($mrow['verified_by'] ?: 'the officer'); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($mrow['verified_at']): ?> on <?php echo e(\Illuminate\Support\Carbon::parse($mrow['verified_at'])->format('d M Y')); ?><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>


<h2>3. Due diligence checklist</h2>
<table class="grid">
    <tr><th style="width: 24%">Area</th><th style="width: 18%">Status</th><th>Findings</th></tr>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $DD::AREAS; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => [$aTitle]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
        <?php $st = $areas[$k]['status'] ?? null; ?>
        <tr>
            <td><?php echo e($aTitle); ?></td>
            <td><span class="pill <?php echo e(['complete' => 'ok', 'clarification' => 'warn', 'not_applicable' => 'grey'][$st] ?? 'grey'); ?>"><?php echo e($DD::STATUSES[$st][0] ?? 'Not assessed'); ?></span></td>
            <td><?php echo e($areas[$k]['finding'] ?? '' ?: '—'); ?></td>
        </tr>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
</table>


<h2>4. Conditions and actions</h2>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($conditions->isEmpty()): ?>
    <p class="muted">No conditions recorded.</p>
<?php else: ?>
    <table class="grid">
        <tr><th>Condition / action</th><th style="width: 28%">When</th><th style="width: 13%">Owner</th><th style="width: 12%">Status</th></tr>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $conditions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
            <tr>
                <td><?php echo e($c->finding ?: '—'); ?></td>
                <td><?php echo e($DC::TREATMENTS[$c->treatment][0] ?? $c->treatment); ?></td>
                <td><?php echo e($DC::OWNERS[$c->owner] ?? $c->owner); ?></td>
                <td><span class="pill <?php echo e($c->status === 'resolved' ? 'ok' : 'warn'); ?>"><?php echo e($DC::STATUSES[$c->status][0] ?? $c->status); ?></span></td>
            </tr>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
    </table>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>


<h2>5. Expected outcomes, risks and recommendation</h2>
<table class="kv">
    <tr><td class="k">Expected outcomes</td><td><?php echo e($dd->expected_outcomes ?: '—'); ?></td></tr>
    <tr><td class="k">Key risks</td><td><?php echo e($dd->key_risks ?: '—'); ?></td></tr>
    <tr><td class="k">Gate 2 outcome</td><td><?php echo e($dd->isDecided() ? ($DD::OUTCOMES[$dd->outcome][0] ?? $dd->outcome) : 'Not yet recorded'); ?></td></tr>
    <tr><td class="k">Conclusion</td><td><?php echo e($dd->conclusion ?: '—'); ?></td></tr>
</table>

<table class="sign">
    <tr>
        <td><div class="line">Prepared by: <?php echo e($dd->assessor?->name ?? ''); ?></div></td>
        <td><div class="line">Reviewed by (PMU)</div></td>
    </tr>
</table>

</body>
</html><?php /**PATH C:\laragon\www\co-investment-facility\resources\views/pdf/investment-appraisal-memorandum.blade.php ENDPATH**/ ?>