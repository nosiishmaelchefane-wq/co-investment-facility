{{-- Investment Appraisal Memorandum (Gate 2 · §3.3) — rendered by DueDiligenceController::memorandum with barryvdh/laravel-dompdf --}}
@php
    $DD  = \App\Models\DueDiligence::class;
    $DC  = \App\Models\DueDiligenceCondition::class;
    $P   = \App\Models\EnterpriseProfile::class;
    $m   = fn ($x) => $x === null || $x === '' || (float) $x == 0 ? '—' : 'M '.number_format((float) $x);
    $u   = fn ($x) => $x === null || $x === '' || (float) $x == 0 ? '' : 'US$ '.number_format((float) $x / $rate);
    $areas      = $dd->areas ?? [];
    $conditions = $dd->conditions;
    $expected   = $P::matchingGrant($v['private'] ?: null);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Investment Appraisal Memorandum — {{ $eoi->reference }}</title>
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
    {{ $eoi->reference }} · {{ $eoi->business_name }} · Round {{ $dd->round }} · Generated {{ now()->format('d M Y H:i') }} · Confidential — for the Investment Committee
    <span style="float: right">Page <span class="page"></span></span>
</footer>

<h1>{{ $profile?->registered_name ?: $eoi->business_name }}</h1>
<div class="muted">
    Reference {{ $eoi->reference }} · Gate 2 round {{ $dd->round }}
    @if ($dd->isDecided())
        · Outcome: <strong>{{ $DD::OUTCOMES[$dd->outcome][0] ?? $dd->outcome }}</strong> ({{ $dd->decided_at->format('d M Y') }})
    @else
        · <span class="pill warn">Draft — assessment in progress</span>
    @endif
</div>

{{-- 1 · Enterprise & transaction at a glance --}}
<h2>1. Enterprise and transaction</h2>
<table>
    <tr>
        <td style="width: 50%; padding-right: 4mm; vertical-align: top">
            <table class="kv">
                <tr><td class="k">Sector</td><td>{{ $profile?->sectorLabel() ?? '—' }}@if ($profile?->sub_sector) · {{ $profile->sub_sector }}@endif</td></tr>
                <tr><td class="k">District</td><td>{{ $profile?->district ?? $eoi->district ?? '—' }}</td></tr>
                <tr><td class="k">Operating since</td><td>{{ $profile?->commencedLabel() ?? $eoi->year_commenced ?? '—' }}</td></tr>
                <tr><td class="k">OBFC registration</td><td>{{ $P::YES_NO_PROGRESS[$profile?->obfc_status ?? ''] ?? '—' }}@if ($profile?->registration_no) · {{ $profile->registration_no }}@endif</td></tr>
                <tr><td class="k">Business model(s)</td><td>{{ $profile?->businessModelLabel() ?? '—' }}</td></tr>
                <tr><td class="k">Employees</td><td>{{ $profile?->employees ?? '—' }}@if ($profile?->expected_jobs) · {{ $profile->expected_jobs }} new jobs expected @endif</td></tr>
            </table>
        </td>
        <td style="width: 50%; vertical-align: top">
            <table class="kv">
                <tr><td class="k">Investor(s)</td><td>{{ $dd->investor_names ?: ($eoi->investor_name ?? '—') }}</td></tr>
                <tr><td class="k">Transaction stage</td><td>{{ $DD::STAGES[$dd->stage][0] ?? '—' }}</td></tr>
                <tr><td class="k">Instrument</td><td>{{ $DD::INSTRUMENTS[$dd->instrument] ?? '—' }}</td></tr>
                <tr><td class="k">ESGC risk</td><td>{{ $DD::RATINGS[$dd->esgc_category] ?? '—' }}</td></tr>
                <tr><td class="k">Assessed by</td><td>{{ $dd->assessor?->name ?? '—' }}</td></tr>
                <tr><td class="k">Date</td><td>{{ $dd->assessed_on?->format('d M Y') ?? '—' }}</td></tr>
            </table>
        </td>
    </tr>
</table>

@if ($dd->summary)
    <p style="margin-top: 3mm"><strong>Summary.</strong> {{ $dd->summary }}</p>
@endif

<table class="grid" style="margin-top: 3mm">
    <tr><th>Co-investment</th><th class="num">Amount</th><th>Note</th></tr>
    <tr>
        <td>Private capital (validated)</td>
        <td class="num">{{ $m($v['private']) }}<span class="usd">{{ $u($v['private']) }}</span></td>
        <td class="small">{{ $v['private'] > 0 ? ($P::meetsInvestorMinimum($v['private']) ? 'Meets' : 'Below') .' the US$ '.number_format($P::minInvestorUsd()).' minimum' : '' }}</td>
    </tr>
    <tr>
        <td>CAFI grant</td>
        <td class="num">{{ $m($v['grant']) }}<span class="usd">{{ $u($v['grant']) }}</span></td>
        <td class="small">@if ($expected) Maximum {{ $P::grantRatioLabel() }} of private capital = {{ $m($expected) }} @endif</td>
    </tr>
    <tr>
        <th>Total transaction</th>
        <th class="num">{{ $m($v['total']) }}<span class="usd">{{ $u($v['total']) }}</span></th>
        <th class="small">@if ($profile?->project_cost) Total project cost {{ $m($profile->project_cost) }} @endif</th>
    </tr>
</table>

{{-- 2 · The five appraisal questions --}}
<h2>2. Transaction appraisal</h2>
@foreach ($DD::MEMO_QUESTIONS as $k => [$title, $question])
    <div class="q">
        <h3>{{ $loop->iteration }}. {{ $title }}</h3>
        <div class="question">{{ $question }}</div>
        <div class="answer">{{ trim($memo[$k]['text'] ?? '') !== '' ? $memo[$k]['text'] : 'Not answered.' }}</div>
    </div>
@endforeach

{{-- 3 · Checklist --}}
<h2>3. Due diligence checklist</h2>
<table class="grid">
    <tr><th style="width: 24%">Area</th><th style="width: 18%">Status</th><th>Findings</th></tr>
    @foreach ($DD::AREAS as $k => [$aTitle])
        @php $st = $areas[$k]['status'] ?? null; @endphp
        <tr>
            <td>{{ $aTitle }}</td>
            <td><span class="pill {{ ['complete' => 'ok', 'clarification' => 'warn', 'not_applicable' => 'grey'][$st] ?? 'grey' }}">{{ $DD::STATUSES[$st][0] ?? 'Not assessed' }}</span></td>
            <td>{{ $areas[$k]['finding'] ?? '' ?: '—' }}</td>
        </tr>
    @endforeach
</table>

{{-- 4 · Conditions --}}
<h2>4. Conditions and actions</h2>
@if ($conditions->isEmpty())
    <p class="muted">No conditions recorded.</p>
@else
    <table class="grid">
        <tr><th>Condition / action</th><th style="width: 28%">When</th><th style="width: 13%">Owner</th><th style="width: 12%">Status</th></tr>
        @foreach ($conditions as $c)
            <tr>
                <td>{{ $c->finding ?: '—' }}</td>
                <td>{{ $DC::TREATMENTS[$c->treatment][0] ?? $c->treatment }}</td>
                <td>{{ $DC::OWNERS[$c->owner] ?? $c->owner }}</td>
                <td><span class="pill {{ $c->status === 'resolved' ? 'ok' : 'warn' }}">{{ $DC::STATUSES[$c->status][0] ?? $c->status }}</span></td>
            </tr>
        @endforeach
    </table>
@endif

{{-- 5 · Expected outcomes, risks, recommendation --}}
<h2>5. Expected outcomes, risks and recommendation</h2>
<table class="kv">
    <tr><td class="k">Expected outcomes</td><td>{{ $dd->expected_outcomes ?: '—' }}</td></tr>
    <tr><td class="k">Key risks</td><td>{{ $dd->key_risks ?: '—' }}</td></tr>
    <tr><td class="k">Gate 2 outcome</td><td>{{ $dd->isDecided() ? ($DD::OUTCOMES[$dd->outcome][0] ?? $dd->outcome) : 'Not yet recorded' }}</td></tr>
    <tr><td class="k">Conclusion</td><td>{{ $dd->conclusion ?: '—' }}</td></tr>
</table>

<table class="sign">
    <tr>
        <td><div class="line">Prepared by: {{ $dd->assessor?->name ?? '' }}</div></td>
        <td><div class="line">Reviewed by (PMU)</div></td>
    </tr>
</table>

</body>
</html>