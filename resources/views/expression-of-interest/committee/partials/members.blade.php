{{-- G3 · Committee members & declarations — included from ⚡committee-review --}}
@php $size = $CR::expectedSize(); @endphp
<section class="section-card">
    <div class="section-card-head">
        <span class="section-no"><i class="bi bi-people"></i></span>
        <div class="me-auto">
            <h5>Committee members &amp; declarations</h5>
            <div class="small text-muted">Scores are consolidated only once every member who is not recused has submitted.</div>
        </div>
        <span class="pill {{ $status['members'] >= $size ? 'pill-ok' : 'pill-danger' }}">
            <i class="bi {{ $status['members'] >= $size ? 'bi-check-lg' : 'bi-exclamation-triangle' }}"></i> Members {{ $status['members'] }} / {{ $size }}
        </span>
        <span class="pill {{ $status['complete'] ? 'pill-ok' : 'pill-warn' }}">Submitted {{ $status['submitted'] }} / {{ $status['scoring'] }}</span>
    </div>

    @if ($members->isEmpty())
        <div class="callout callout-warning small mb-0">
            <i class="bi bi-exclamation-triangle me-1"></i> No active users have the <strong>Investment Committee</strong> role. Assign it in user management.
        </div>
    @else
        <div class="row g-3">
            @foreach ($members as $member)
                @php
                    $c    = $cards->get($member->id);
                    $mine = $member->id === auth()->id();
                    $ini  = collect(explode(' ', $member->name))->filter()->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->take(2)->implode('');
                @endphp
                <div class="col-md-4" wire:key="member-{{ $member->id }}">
                    <div class="border rounded-3 p-3 h-100 {{ $mine ? 'border-primary' : '' }}">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="avatar-sm">{{ $ini }}</span>
                            <div class="me-auto overflow-hidden">
                                <div class="fw-semibold text-navy text-truncate">{{ $member->name }} @if ($mine) <span class="pill pill-muted">You</span> @endif</div>
                                <div class="small text-muted text-truncate">{{ $member->email }}</div>
                            </div>
                        </div>

                        <dt class="detail-label">Conflict of interest</dt>
                        <div class="small mb-2">
                            @if ($c?->coi)
                                @php [$coiL, $coiP] = $SC::COI[$c->coi]; @endphp
                                <span class="pill {{ $coiP }}">{{ $coiL }}</span>
                                @if ($c->coi_details) <span class="text-muted d-block mt-1">{{ $c->coi_details }}</span> @endif
                            @else
                                <span class="pill pill-muted">Not declared</span>
                            @endif
                        </div>

                        <dt class="detail-label">Scorecard</dt>
                        <div class="d-flex flex-wrap gap-1 small">
                            @if ($c?->isRecused())
                                <span class="pill pill-muted">Not scoring</span>
                            @elseif ($c?->isSubmitted())
                                <span class="pill pill-ok"><i class="bi bi-check-lg"></i> Submitted {{ $c->submitted_at->format('d M') }}</span>
                                @if ($revealed || $mine)
                                    <span class="pill {{ $c->recommendation === 'approve' ? 'pill-ok' : 'pill-danger' }}">
                                        <i class="bi {{ $c->recommendation === 'approve' ? 'bi-hand-thumbs-up' : 'bi-hand-thumbs-down' }}"></i> {{ $SC::RECOMMENDATIONS[$c->recommendation] ?? '' }}
                                    </span>
                                @endif
                            @elseif ($c)
                                <span class="pill pill-warn"><i class="bi bi-pencil"></i> Draft</span>
                            @else
                                <span class="pill pill-muted">Not started</span>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</section>