{{-- Gate 4 · Grant Readiness Memo (one page, CI Facility Operations Manual §3.5) — rendered by GrantReadinessController::memo --}}
@php
    $G   = \App\Models\GrantReadiness::class;
    $CR  = \App\Models\CommitteeReview::class;
    $P   = \App\Models\EnterpriseProfile::class;
    $m   = fn ($x) => $x === null || $x === '' ? '—' : 'M '.number_format((float) $x);
    $u   = fn ($x) => $x === null || $x === '' ? '' : 'US$ '.number_format((float) $x / $rate);
    $chk = fn ($k) => $g->checks[$k] ?? [];
    $st  = fn ($k) => $G::CHECK_STATUSES[$chk($k)['status'] ?? ''][0] ?? 'Not recorded';
    $cls = fn ($k) => ['confirmed' => 'ok', 'exception' => 'warn', 'action' => 'bad'][$chk($k)['status'] ?? ''] ?? 'grey';
    $note = fn ($k) => trim((string) ($chk($k)['note'] ?? ''));
    $bank = $g->bank ?? [];
    $issues = collect($g->issues ?? [])->filter(fn ($i) => filled($i['issue'] ?? null));
    $review = $g->committeeReview;
    $mask = fn ($n) => $n ? str_repeat('•', max(strlen($n) - 4, 0)).substr($n, -4) : '—';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Grant Readiness Memo — {{ $eoi->reference }}</title>
    <style>
        @page { margin: 14mm 14mm 14mm 14mm; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 8.4pt; color: #1f2937; line-height: 1.35; }
        h1 { font-size: 12.5pt; color: #0b2e59; margin: 0; }
        .sub { color: #6b7280; font-size: 7.6pt; margin-bottom: 3mm; }
        table { width: 100%; border-collapse: collapse; }
        .memo td, .memo th { border: 1px solid #d1d5db; padding: 1.6mm 2mm; vertical-align: top; text-align: left; }
        .memo th { width: 26%; background: #eef2f7; color: #0b2e59; }
        .pill { display: inline-block; padding: 0.2mm 1.6mm; border-radius: 1.5mm; font-size: 7pt; font-weight: bold; }
        .ok { background: #dcfce7; color: #166534; } .warn { background: #fef3c7; color: #92400e; }
        .bad { background: #fee2e2; color: #991b1b; } .grey { background: #f3f4f6; color: #374151; }
        .line { margin-bottom: 0.8mm; }
        .muted { color: #6b7280; }
        .rec { font-size: 10pt; font-weight: bold; }
        .appr td, .appr th { border: 1px solid #d1d5db; padding: 1.6mm 2mm; text-align: left; }
        .appr th { background: #eef2f7; color: #0b2e59; }
        .foot { margin-top: 2mm; font-size: 6.8pt; color: #6b7280; }
    </style>
</head>
<body>

<h1>Grant Readiness Memo</h1>
<div class="sub">
    LEHSFF · CAFI Co-Investment Facility · Gate 4 Grant Readiness Review · {{ $eoi->reference }} · round {{ $g->round }}
    · {{ $g->isApproved() ? 'Approved to proceed' : ($g->isDecided() ? 'Returned' : 'In review') }} · generated {{ now()->format('d M Y') }}
</div>

<table class="memo">
    {{-- 1 --}}
    <tr>
        <th>1. Transaction details</th>
        <td>
            <div class="line"><strong>{{ $profile?->registered_name ?: $eoi->business_name }}</strong> · {{ $eoi->reference }} · {{ $profile?->sectorLabel() ?? '—' }}</div>
            <div class="line">Private investment: <strong>{{ $m($g->private_amount) }}</strong> <span class="muted">({{ $u($g->private_amount) }})</span>
                · Proposed CAFI grant: <strong>{{ $m($g->grant_amount) }}</strong> <span class="muted">({{ $u($g->grant_amount) }})</span></div>
            <div class="line">Investment Committee decision: {{ $review ? ($CR::OUTCOMES[$review->outcome][0] ?? $review->outcome).' · '.$review->decided_at?->format('d M Y') : '—' }}</div>
        </td>
    </tr>
    {{-- 2 --}}
    <tr>
        <th>2. Grant budget and compliance</th>
        <td>
            <div class="line"><span class="pill {{ $cls('grant_amount') }}">{{ $st('grant_amount') }}</span>
                Matching: max {{ $P::grantRatioLabel() }} of private investment = {{ $m($facts['matching_max']) }} — {{ $facts['matching_ok'] ? 'complies' : 'does not comply' }}.
                Cumulative CAFI grants {{ $m($facts['cumulative']) }} of the {{ $m($facts['cap']) }} limit — {{ $facts['cap_ok'] ? 'within the limit' : 'exceeds the limit' }}.
                @if ($note('grant_amount')) {{ $note('grant_amount') }} @endif</div>
            <div class="line"><span class="pill {{ $cls('eligible') }}">{{ $st('eligible') }}</span> Eligible expenditure aligned with growth objectives. @if ($note('eligible')) {{ $note('eligible') }} @endif</div>
            <div class="line"><span class="pill {{ $cls('budget') }}">{{ $st('budget') }}</span>
                {{ $g->lesotho_pct !== null ? rtrim(rtrim(number_format((float) $g->lesotho_pct, 2), '0'), '.').'%' : '—' }} of the grant spent in Lesotho
                (minimum {{ (int) $G::minLesothoPct() }}%){{ $profile?->grant_outside_lesotho !== null ? ' · M '.number_format((float) $profile->grant_outside_lesotho).' outside Lesotho' : '' }}.
                @if ($profile?->grant_outside_justification) <span class="muted">Enterprise: {{ \Illuminate\Support\Str::limit($profile->grant_outside_justification, 300) }}</span> @endif
                @if ($note('budget')) {{ $note('budget') }} @endif</div>
        </td>
    </tr>
    {{-- 3 --}}
    <tr>
        <th>3. Bank account verification</th>
        <td>
            <span class="pill {{ $cls('bank') }}">{{ $st('bank') }}</span>
            {{ $bank['account_name'] ?? '—' }} · {{ $bank['bank'] ?? '—' }}{{ filled($bank['branch'] ?? null) ? ', '.$bank['branch'] : '' }}
            · account {{ $mask($bank['account_number'] ?? null) }} ({{ $G::ACCOUNT_TYPES[$bank['account_type'] ?? ''] ?? '—' }})
            · verified against: {{ $G::BANK_EVIDENCE[$bank['verified_against'] ?? ''] ?? '—' }}.
            @if ($note('bank')) {{ $note('bank') }} @endif
        </td>
    </tr>
    {{-- 4 --}}
    <tr>
        <th>4. Documentation and agreement</th>
        <td>
            <div class="line"><span class="pill {{ $cls('documents') }}">{{ $st('documents') }}</span> Required supporting documents available and consistent. @if ($note('documents')) {{ $note('documents') }} @endif</div>
            <div class="line">Draft Grant Agreement: {{ $g->agreement_name ?: 'not uploaded' }} — reflects the proposed grant amount, approved budget and applicable terms.</div>
        </td>
    </tr>
    {{-- 5 --}}
    <tr>
        <th>5. Outstanding issues</th>
        <td>
            @if ($issues->isEmpty())
                None identified.
            @else
                @foreach ($issues as $i)
                    <div class="line">{{ $loop->iteration }}. {{ $i['issue'] }} — <span class="muted">{{ $G::PARTIES[$i['party'] ?? ''] ?? '—' }}</span>@if (filled($i['action'] ?? null)): {{ $i['action'] }}@endif
                        @if (($i['status'] ?? 'open') === 'resolved') <span class="pill ok">Resolved</span> @endif</div>
                @endforeach
            @endif
            @if ($note('outstanding')) <div class="line muted">{{ $note('outstanding') }}</div> @endif
        </td>
    </tr>
    {{-- 6 --}}
    <tr>
        <th>6. Readiness recommendation</th>
        <td>
            <span class="rec">{{ $G::RECOMMENDATIONS[$g->recommendation][0] ?? 'Not recorded' }}</span>
            @if ($g->justification) — {{ $g->justification }} @endif
        </td>
    </tr>
</table>

<table class="appr" style="margin-top: 3mm">
    <tr><th>Review stage</th><th>Name</th><th>Date</th><th>Decision</th></tr>
    <tr>
        <td>Grants Management Representative</td>
        <td>{{ $g->grantsOfficer?->name ?? '—' }}</td>
        <td>{{ $g->gm_submitted_at ? ($g->gm_on ?? $g->gm_submitted_at)->format('d M Y') : '—' }}</td>
        <td>{{ $G::RECOMMENDATIONS[$g->recommendation][0] ?? '—' }}</td>
    </tr>
    <tr>
        <td>Hub Manager</td>
        <td>{{ $g->hubManager?->name ?? '—' }}</td>
        <td>{{ $g->hub_on?->format('d M Y') ?? '—' }}</td>
        <td>{{ $G::HUB_DECISIONS[$g->hub_decision][0] ?? 'Pending' }}{{ $g->hub_comments ? ' — '.\Illuminate\Support\Str::limit($g->hub_comments, 120) : '' }}</td>
    </tr>
    <tr>
        <td>PMU Director of Implementation</td>
        <td>{{ $g->director?->name ?? '—' }}</td>
        <td>{{ $g->director_on?->format('d M Y') ?? '—' }}</td>
        <td>{{ $G::DIRECTOR_DECISIONS[$g->director_decision][0] ?? 'Pending' }}{{ $g->director_comments ? ' — '.\Illuminate\Support\Str::limit($g->director_comments, 120) : '' }}</td>
    </tr>
</table>

<div class="foot">
    Completion of Gate 4 confirms readiness for final authorisation. It is not final grant authorisation, execution of the Grant Agreement or approval for disbursement.
    Budget details, bank verification evidence and supporting documents are held in the transaction file on the LEHSFF platform.
</div>

</body>
</html>