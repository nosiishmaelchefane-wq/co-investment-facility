{{-- G2 · 6 Consolidated risk assessment — included from ⚡due-deligence-check --}}
@php
    $counts = $DD::riskCounts($form['risks']);
    $order  = array_flip(array_keys($DD::RATINGS));
    $sorted = collect($form['risks'])->sortByDesc(fn ($r) => $order[$r['rating']] ?? -1)->values();
@endphp
<section class="section-card" id="g2-risk">
    <div class="section-card-head">
        <span class="section-no">6</span>
        <div class="me-auto">
            <h5>Consolidated risk assessment</h5>
            <div class="small text-muted">{{ $DD::AREAS['risk'][2] }}</div>
        </div>
        @include('expression-of-interest.due-diligence.partials.area-pill', ['pct' => $progress['risk']])
    </div>

    <div class="row g-3 mb-3">
        @foreach ([['Low', $counts['low'], 'low'], ['Medium', $counts['medium'], 'medium'], ['High', $counts['high'], 'high'], ['Exclusion', $counts['exclusion'], 'high']] as [$lbl, $count, $tone])
            <div class="col-6 col-md-3">
                <div class="border rounded-3 p-3 text-center h-100">
                    <div class="fs-4 fw-bold text-navy">{{ $count }}</div>
                    <span class="rating rating-{{ $tone }}">{{ $lbl }}</span>
                </div>
            </div>
        @endforeach
    </div>

    @if ($sorted->isNotEmpty())
        <div class="table-responsive mb-3">
            <table class="table table-modern align-middle mb-0">
                <thead><tr><th>Source</th><th>Principal risk</th><th>Rating</th><th>Treatment</th></tr></thead>
                <tbody>
                    @foreach ($sorted as $r)
                        <tr>
                            <td class="small">{{ $DD::RISK_CATEGORIES[$r['category']][0] ?? $r['category'] }}</td>
                            <td class="small">{{ $r['issue'] ?: '—' }}</td>
                            <td>@if ($r['rating'])<span class="rating rating-{{ $r['rating'] }}">{{ $DD::RATINGS[$r['rating']] }}</span>@else<span class="text-muted small">—</span>@endif</td>
                            <td class="small">{{ $DD::RISK_TREATMENTS[$r['treatment']] ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <p class="small text-muted">Risks are recorded in the register under 3 · H.</p>
    @endif

    <div class="d-flex flex-wrap align-items-center gap-2">
        <label class="small fw-semibold text-navy me-2" for="risk_conclusion">Conclusion</label>
        <select id="risk_conclusion" class="form-select form-select-sm" style="width: auto;" wire:model.live="form.risk_conclusion" @disabled(! $canEdit)>
            <option value="">Select…</option>
            @foreach ($DD::RISK_CONCLUSIONS as $ck => [$cl]) <option value="{{ $ck }}">{{ $cl }}</option> @endforeach
        </select>
        @if ($counts['exclusion'] && $form['risk_conclusion'] && $form['risk_conclusion'] !== 'not_acceptable')
            <span class="small text-danger"><i class="bi bi-exclamation-octagon me-1"></i> A risk is recorded as grounds for exclusion.</span>
        @endif
    </div>
</section>