{{-- G3 · Transaction at a glance (Transaction Summary Sheet) — included from ⚡committee-review --}}
@php
    $investors = $ddForm['investor_names'] ?? null;
    $stage     = $DD::STAGES[$ddForm['stage'] ?? ''][0] ?? null;
@endphp
<section class="section-card">
    <div class="section-card-head">
        <span class="section-no"><i class="bi bi-file-earmark-text"></i></span>
        <h5 class="me-auto">Transaction at a glance</h5>
        @if ($stage) <span class="pill pill-ok">{{ $stage }}</span> @endif
    </div>

    <div class="row g-3 mb-3">
        @foreach ([
            ['Total transaction value',   $m($validated['total']),   $u($validated['total']),   'bi-bank',       'primary'],
            ['Private capital committed', $m($validated['private']), $u($validated['private']), 'bi-briefcase',  'accent'],
            ['CAFI grant requested',      $m($validated['grant']),   $u($validated['grant']),   'bi-cash-coin',  'warning'],
        ] as [$label, $value, $sub, $icon, $tone])
            <div class="col-md-4">
                <div class="stat-card">
                    <span class="stat-icon tone-{{ $tone }}"><i class="bi {{ $icon }}"></i></span>
                    <div>
                        <div class="stat-value sm">{{ $value }}</div>
                        <div class="stat-label">{{ $label }}</div>
                        <div class="small text-muted">{{ $sub }}</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="table-responsive">
        <table class="table table-modern align-middle mb-0 tss-table">
            <tbody>
                <tr><th>Enterprise</th><td>{{ ($ddForm['summary'] ?? '') ?: ($eoi->business_name ?: '—') }}</td></tr>
                <tr><th>Investors</th><td>{{ $investors ?: '—' }}</td></tr>
                <tr><th>Funding structure</th><td>{{ $validated['structure'] ?? '—' }}</td></tr>
                <tr><th>Expected outcomes</th><td>{{ ($ddForm['expected_outcomes'] ?? '') ?: '—' }}</td></tr>
                <tr><th>Key risks</th><td>{{ ($ddForm['key_risks'] ?? '') ?: '—' }}</td></tr>
                <tr><th>Gate 2</th><td>
                    @if ($dd)
                        <span class="pill pill-ok"><i class="bi bi-check2-circle"></i> Proceed</span>
                        @if ($ddForm['esgc_category'] ?? null) · ESGC <span class="rating rating-{{ $ddForm['esgc_category'] }}">{{ $DD::RATINGS[$ddForm['esgc_category']] }}</span> @endif
                    @else — @endif
                </td></tr>
            </tbody>
        </table>
    </div>
</section>