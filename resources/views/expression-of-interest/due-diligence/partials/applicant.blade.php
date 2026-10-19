{{-- G2 · Applicant view — progress, outcome and actions they own. Included from ⚡due-deligence-check --}}
@php
    $mine    = collect($conditions)->where('owner', 'enterprise')->where('status', 'open');
    $myEsap  = collect($esap)->where('status', 'pending');
@endphp
<section class="section-card">
    <div class="section-card-head">
        <span class="section-no">G2</span>
        <h5 class="me-auto">Due diligence &amp; investment appraisal</h5>
        @if ($decided)
            @php [$oLabel, , $oIcon, $oPill] = $DD::OUTCOMES[$dd->outcome]; @endphp
            <span class="pill {{ $oPill }}"><i class="bi {{ $oIcon }}"></i> {{ $oLabel }}</span>
        @else
            <span class="pill pill-warn"><i class="bi bi-hourglass-split"></i> In progress</span>
        @endif
    </div>

    @if (! $decided)
        <p class="small text-muted">
            The LEHSFF team is assessing your enterprise, your investor's commitment, the transaction and its environmental, social, gender and climate
            considerations. The team may contact you for further information.
        </p>
        @foreach ($DD::AREAS as $key => [$aTitle, $aIcon])
            @php $pct = $progress[$key]; @endphp
            <div class="d-flex align-items-center gap-2 small mb-2">
                <i class="bi {{ $pct === 100 ? 'bi-check-circle-fill text-accent' : 'bi-circle text-muted' }}"></i>
                <span class="text-navy">{{ $aTitle }}</span>
            </div>
        @endforeach

    @elseif ($dd->outcome === 'proceed')
        <div class="callout callout-success small mb-0">
            <i class="bi bi-check2-circle me-1"></i> Due diligence is complete. Your application will be considered by the <strong>Investment Committee</strong>.
        </div>

    @elseif ($dd->outcome === 'do_not_progress')
        <p class="small text-muted mb-2">Your application will not progress further.</p>
        @if ($dd->conclusion)
            <div class="quote">{{ $dd->conclusion }}<div class="quote-meta">From the LEHSFF team · {{ $dd->decided_at->format('d M Y') }}</div></div>
        @endif

    @else
        <div class="callout callout-warning small mb-0">
            <i class="bi bi-pause-circle me-1"></i>
            {{ $dd->outcome === 'remediation' ? 'A corrective action is required before your application can proceed.' : 'Additional information is required to complete due diligence.' }}
            The LEHSFF team will be in touch.
        </div>
    @endif

    @if ($mine->isNotEmpty() || $myEsap->isNotEmpty())
        <dt class="detail-label mt-3 mb-2">Actions for your enterprise</dt>
        <ul class="checklist mb-0">
            @foreach ($mine as $c)
                <li class="is-pending">
                    <i class="bi bi-exclamation-circle"></i>
                    <div><div class="fw-semibold text-navy">{{ $c['finding'] }}</div>
                    <div class="small text-muted">{{ \App\Models\DueDiligenceCondition::TREATMENTS[$c['treatment']][0] ?? '' }}</div></div>
                </li>
            @endforeach
            @foreach ($myEsap as $a)
                <li class="is-pending">
                    <i class="bi bi-circle"></i>
                    <div><div class="fw-semibold text-navy">{{ $a['action'] }}</div>
                    <div class="small text-muted">ESAP · {{ \App\Models\EsapAction::TREATMENTS[$a['treatment']] ?? '' }}{{ $a['timing'] ? ' — '.$a['timing'] : '' }}</div></div>
                </li>
            @endforeach
        </ul>
    @endif
</section>