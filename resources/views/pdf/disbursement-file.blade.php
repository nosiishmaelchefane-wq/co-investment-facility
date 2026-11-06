{{-- Gate 6 / Gate 7 · Disbursement file (CI Facility Operations Manual §3.7–§3.8) — rendered by Gate5FileController::record --}}
@php
    $CR   = \App\Models\ConditionReview::class;
    $m    = fn ($x) => $x === null || $x === '' ? '—' : 'M '.number_format((float) $x);
    $u    = fn ($x) => $x === null || $x === '' ? '' : 'US$ '.number_format((float) $x / $rate);
    $d    = fn ($x) => $x ? \Illuminate\Support\Carbon::parse($x)->format('d M Y') : '—';
    $s    = $r->summary();
    $c    = $r->controls();
    $bank = (array) ($r->bank ?? []);
    $mask = fn ($n) => $n ? str_repeat('•', max(strlen($n) - 4, 0)).substr($n, -4) : '—';
    $ob   = (array) ($r->onboarding ?? []);
    $bl   = (array) ($r->baseline ?? []);
    $party = fn ($k) => $CR::PARTIES[$k] ?? ucfirst((string) $k);
    $status = $r->isFullyDisbursed() ? 'Fully disbursed' : ($r->outcome ? ($CR::OUTCOMES[$r->outcome][0] ?? $r->outcome) : 'In progress');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Disbursement File — {{ $eoi->reference }}</title>
    <style>
        @page { margin: 14mm; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 8.2pt; color: #1f2937; line-height: 1.35; }
        h1 { font-size: 12.5pt; color: #0b2e59; margin: 0; }
        h2 { font-size: 9.6pt; color: #0b2e59; margin: 4mm 0 1.5mm; border-bottom: 1px solid #0b2e59; padding-bottom: 0.6mm; }
        .sub { color: #6b7280; font-size: 7.6pt; margin-bottom: 2mm; }
        table { width: 100%; border-collapse: collapse; }
        td, th { border: 1px solid #d1d5db; padding: 1.2mm 1.6mm; vertical-align: top; text-align: left; }
        th { background: #eef2f7; color: #0b2e59; font-size: 7.6pt; }
        .kv th { width: 30%; }
        .r { text-align: right; }
        .pill { display: inline-block; padding: 0.2mm 1.4mm; border-radius: 1.5mm; font-size: 6.8pt; font-weight: bold; }
        .ok { background: #dcfce7; color: #166534; } .warn { background: #fef3c7; color: #92400e; }
        .bad { background: #fee2e2; color: #991b1b; } .grey { background: #f3f4f6; color: #374151; }
        .muted { color: #6b7280; }
        .foot { margin-top: 3mm; font-size: 6.8pt; color: #6b7280; }
    </style>
</head>
<body>

<h1>Disbursement File</h1>
<div class="sub">
    LEHSFF · CAFI Co-Investment Facility · Gate 6 Conditions Precedent &amp; Onboarding · Gate 7 Grant Disbursement ·
    {{ $eoi->reference }} · {{ $status }} · generated {{ now()->format('d M Y H:i') }}
</div>

<table class="kv">
    <tr><th>Enterprise</th><td><strong>{{ $profile?->registered_name ?: $eoi->business_name }}</strong> · {{ $eoi->user?->email }}</td></tr>
    <tr><th>Approved CAFI grant</th><td><strong>{{ $m($r->grant_amount) }}</strong> <span class="muted">{{ $u($r->grant_amount) }}</span> · approved private investment {{ $m($r->private_amount) }}</td></tr>
    <tr><th>Disbursed / remaining</th><td>{{ $m($r->releasedAmount()) }} disbursed · {{ $m($r->stillToDisburse()) }} still to disburse · verified private capital {{ $m($s['capital']) }}</td></tr>
    <tr><th>Approval communicated</th><td>{{ $d($r->approval_sent_at) }}</td></tr>
</table>

{{-- 1 · CP checklist --}}
<h2>1. Conditions precedent checklist</h2>
<table>
    <thead><tr><th>Area</th><th>Requirement / evidence needed</th><th>Provides · verifies</th><th>Timing</th><th>Status</th><th>Verified</th></tr></thead>
    <tbody>
        @foreach ($r->conditions as $cn)
            <tr>
                <td>{{ $CR::areaLabel($cn->area) }}</td>
                <td><strong>{{ $cn->finding }}</strong>
                    @if ($cn->evidence_required) <div class="muted">Evidence: {{ $cn->evidence_required }}</div> @endif
                    @if ($cn->evidence_name) <div class="muted">On file: {{ $cn->evidence_name }}</div> @endif
                    @if ($cn->note) <div>{{ $cn->note }}</div> @endif
                    @if ($cn->corrective_action) <div style="color:#991b1b">Corrective action: {{ $cn->corrective_action }}</div> @endif</td>
                <td>{{ $party($cn->responsible) }} · {{ $party($cn->verifier) }}</td>
                <td>{{ $CR::TIMINGS[$cn->timing][0] ?? '—' }}</td>
                <td><span class="pill {{ ['met' => 'ok', 'waived' => 'grey', 'not_met' => 'bad'][$cn->status] ?? 'warn' }}">{{ $CR::CONDITION_STATUSES[$cn->status][0] ?? $cn->status }}</span></td>
                <td>{{ $cn->verifiedBy?->name ?? '—' }}<div class="muted">{{ $d($cn->verified_at) }}</div></td>
            </tr>
        @endforeach
    </tbody>
</table>

{{-- 2 · Verification record --}}
<h2>2. Verification record</h2>
<table>
    <thead><tr><th style="width:28%">Area</th><th style="width:14%">Result</th><th>Note / evidence reviewed</th></tr></thead>
    <tbody>
        @foreach ($CR::VERIFICATION_AREAS as $ak => [$aTitle])
            @php $v = $r->verification[$ak] ?? []; $vs = $v['status'] ?? null; @endphp
            <tr>
                <td>{{ $aTitle }}</td>
                <td><span class="pill {{ ['verified' => 'ok', 'issue' => 'bad', 'not_applicable' => 'grey'][$vs] ?? 'warn' }}">{{ $CR::VERIFY_STATUSES[$vs][0] ?? 'Not recorded' }}</span></td>
                <td>{{ $v['note'] ?? '' }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
<table class="kv" style="margin-top:1.5mm">
    <tr><th>Payment account</th><td>{{ $bank['account_name'] ?? '—' }} · {{ $bank['bank'] ?? '—' }} {{ $bank['branch'] ?? '' }} · {{ $mask($bank['account_number'] ?? null) }}
        @if (! empty($bank['changed'])) <div style="color:#92400e">Changed since Gate 4 — {{ $r->bank_revalidated_at ? 'revalidated '.$d($r->bank_revalidated_at).': '.$r->bank_revalidation_note : 'NOT revalidated' }}</div> @endif</td></tr>
    <tr><th>Agreements</th><td>@foreach ($r->agreements as $a) <div>{{ $a->title }} — {{ $CR::AGREEMENT_STATUSES[$a->status][0] ?? $a->status }}{{ $a->signed_on ? ' · signed '.$d($a->signed_on) : '' }}{{ $a->required ? '' : ' (not required)' }}</div> @endforeach</td></tr>
    <tr><th>Budget (grant)</th><td>@foreach ((array) $r->budget as $b) {{ $b['category'] ?? '' }}: {{ $m($b['grant'] ?? null) }}@if (! $loop->last) · @endif @endforeach — total {{ $m($r->budgetTotals()['grant']) }}</td></tr>
</table>

{{-- 3 · Readiness --}}
<h2>3. Disbursement readiness confirmation</h2>
<table>
    @foreach ($CR::READINESS_CHECKS as $rk => $rt)
        <tr><td style="width:6%" class="r">{{ ($r->readiness_checks[$rk] ?? false) ? '✓' : '—' }}</td><td>{{ $rt }}</td></tr>
    @endforeach
</table>
<table class="kv" style="margin-top:1.5mm">
    <tr><th>Programme confirmation</th><td>{{ $r->readinessPmu?->name ?? '—' }} · {{ $d($r->readiness_pmu_at) }}</td></tr>
    <tr><th>Finance confirmation</th><td>{{ $r->readinessFinance?->name ?? '—' }} · {{ $d($r->readiness_finance_at) }}</td></tr>
    <tr><th>Gate 6 outcome</th><td><strong>{{ $r->outcome ? ($CR::OUTCOMES[$r->outcome][0] ?? $r->outcome) : 'Not recorded' }}</strong> · {{ $d($r->outcome_on) }} · {{ $r->decider?->name }}
        @if ($r->outcome_comments) <div>{{ $r->outcome_comments }}</div> @endif</td></tr>
</table>

{{-- 4 · Disbursement --}}
<h2>4. Disbursement record (Gate 7)</h2>
<table>
    <thead><tr><th>Investor</th><th class="r">Amount</th><th>Date</th><th>Received into</th><th>Backs</th><th>Status</th></tr></thead>
    <tbody>
        @forelse ($r->deployments as $dp)
            <tr><td>{{ $dp->investor }}</td><td class="r">{{ $m($dp->amount) }}</td><td>{{ $d($dp->deployed_on) }}</td><td>{{ $dp->received_account ?: '—' }}</td>
                <td>{{ $dp->tranche?->label ?? '—' }}</td><td>{{ $CR::DEPLOYMENT_STATUSES[$dp->status][0] ?? $dp->status }}{{ $dp->verifier ? ' · '.$dp->verifier->name : '' }}</td></tr>
        @empty
            <tr><td colspan="6" class="muted">No investment deployment recorded.</td></tr>
        @endforelse
    </tbody>
</table>
<table style="margin-top:1.5mm">
    <thead><tr><th>Tranche</th><th class="r">Grant</th><th>Matching calculation</th><th>Approval</th><th>Instruction · payment</th><th>Outcome</th></tr></thead>
    <tbody>
        @forelse ($r->tranches as $t)
            @php $mt = $r->matchingFor($t); @endphp
            <tr>
                <td>{{ $t->label }}</td>
                <td class="r">{{ $m($t->amount) }}</td>
                <td>Verified capital {{ $m($t->capital_verified ?? $mt['verified']) }} · grant after {{ $m($mt['after']) }} needs {{ $m($mt['needed']) }} — {{ $mt['ok'] ? 'meets the rule' : 'NOT met' }}</td>
                <td>Requested {{ $t->requester?->name ?? '—' }} {{ $d($t->requested_at) }}<br>Approved {{ $t->approver?->name ?? '—' }} {{ $d($t->approved_at) }}</td>
                <td>{{ $t->instruction_ref ?: '—' }} · {{ $d($t->instruction_on) }}<br>{{ $t->reference ?: '—' }} · {{ $d($t->released_on) }} {{ $t->releaser ? '· '.$t->releaser->name : '' }}</td>
                <td>{{ $t->released_on ? 'Paid' : ($CR::TRANCHE_OUTCOMES[$t->outcome][0] ?? 'Planned') }}@if ($t->outcome_note) <div class="muted">{{ $t->outcome_note }}</div> @endif</td>
            </tr>
        @empty
            <tr><td colspan="6" class="muted">No tranches.</td></tr>
        @endforelse
    </tbody>
</table>
<table class="kv" style="margin-top:1.5mm">
    <tr><th>Enterprise — lifetime CAFI grant</th><td>Received {{ $m($c['enterprise_received']) }} · approved {{ $m($c['enterprise_approved']) }} · remaining under the {{ $m($c['lifetime_cap']) }} limit {{ $m($c['lifetime_remaining']) }}</td></tr>
    <tr><th>Transaction</th><td>Approved {{ $m($c['approved']) }} · disbursed {{ $m($c['disbursed']) }} · undisbursed {{ $m($c['undisbursed']) }}</td></tr>
    <tr><th>Investor exposure</th><td>@foreach ($c['investors'] as $ix) {{ $ix['name'] }}: {{ $m($ix['exposure']) }}@if (! $loop->last) · @endif @endforeach</td></tr>
</table>

{{-- 5 · Onboarding --}}
<h2>5. Enterprise onboarding record</h2>
<table class="kv">
    <tr><th>Briefing covered</th><td>@foreach ($CR::ONBOARDING_ITEMS as $ok => $ol) <div>{{ ($ob['items'][$ok] ?? false) ? '✓' : '—' }} {{ $ol }}</div> @endforeach</td></tr>
    <tr><th>Reporting</th><td>{{ $CR::FREQUENCIES[$ob['frequency'] ?? ''] ?? '—' }} · first report {{ $d($ob['first_report_on'] ?? null) }} · contact {{ $ob['contact'] ?? '—' }}</td></tr>
    <tr><th>Baseline (as at {{ $d($bl['as_of'] ?? null) }})</th><td>@foreach ($CR::BASELINE_FIELDS as $bk => $bll) {{ $bll }}: {{ isset($bl[$bk]) ? number_format((float) $bl[$bk]) : '—' }}@if (! $loop->last) · @endif @endforeach</td></tr>
    <tr><th>Summary of obligations sent</th><td>{{ $d($r->summary_sent_at) }}</td></tr>
    <tr><th>Enterprise confirmed briefing</th><td>{{ $d($r->onboarding_confirmed_at) }}</td></tr>
</table>

<div class="foot">Confidential — LEHSFF programme record. Bank account numbers are masked. Supporting documents are held in the system under {{ $eoi->reference }}.</div>
</body>
</html>