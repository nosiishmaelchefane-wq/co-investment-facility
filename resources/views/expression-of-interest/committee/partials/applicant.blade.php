{{-- G3 · Applicant view — outcome and the conditions they own. Included from ⚡committee-review --}}
@php
    $mine = collect($review?->conditions ?? [])
        ->filter(fn ($c) => ($c['decision'] ?? '') !== 'removed' && ($c['owner'] ?? 'enterprise') === 'enterprise' && ($c['status'] ?? 'open') !== 'resolved');
@endphp
<section class="section-card">
    <div class="section-card-head">
        <span class="section-no">G3</span>
        <h5 class="me-auto">Investment Committee</h5>
        @if ($decided)
            @php [$oL, , , $oI, $oP] = $CR::OUTCOMES[$review->outcome]; @endphp
            <span class="pill {{ $oP }}"><i class="bi {{ $oI }}"></i> {{ $oL }}</span>
        @else
            <span class="pill pill-warn"><i class="bi bi-hourglass-split"></i> Under review</span>
        @endif
    </div>

    @if (! $decided)
        <p class="small text-muted mb-0">Your application has completed due diligence and is with the Investment Committee. You'll be notified of the Committee's recommendation.</p>

    @elseif ($review->isApproved())
        <div class="callout callout-success small mb-2">
            <i class="bi bi-check2-circle me-1"></i>
            The Investment Committee has recommended your application{{ $review->outcome === 'conditional' ? ', subject to conditions' : '' }}.
            It now goes to <strong>final authorisation</strong>.
        </div>
        <p class="small text-muted mb-0">The Committee's recommendation is not yet a grant approval — the CAFI Managing Director (and, where applicable, the MTIBD Principal Secretary) gives final authorisation.</p>

    @elseif ($review->outcome === 'defer')
        <div class="callout callout-warning small mb-0">
            <i class="bi bi-pause-circle me-1"></i> The Committee needs more information before making a recommendation. The LEHSFF team will contact you.
        </div>

    @else
        <p class="small text-muted mb-2">The Investment Committee decided that the transaction should not proceed under the Facility.</p>
        @if ($review->rationale)
            <div class="quote">{{ $review->rationale }}<div class="quote-meta">Investment Committee · {{ $review->decided_at->format('d M Y') }}</div></div>
        @endif
    @endif

    @if ($decided && $review->isApproved() && $mine->isNotEmpty())
        <dt class="detail-label mt-3 mb-2">Conditions for your enterprise</dt>
        <ul class="checklist mb-0">
            @foreach ($mine as $c)
                <li class="is-pending"><i class="bi bi-exclamation-circle"></i>
                    <div><div class="fw-semibold text-navy">{{ $c['finding'] }}</div><div class="small text-muted">{{ $c['treatment'] }}</div></div></li>
            @endforeach
        </ul>
    @endif
</section>