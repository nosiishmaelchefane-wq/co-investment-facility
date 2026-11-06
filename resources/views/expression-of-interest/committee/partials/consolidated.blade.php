{{-- G3 · Consolidated scorecard — scores 1–5 per criterion, totals and recommendations.
     Hidden until every non-recused member has submitted (a member always sees their own). Included from ⚡committee-review --}}
@php
    $consolidated = $revealed && $review ? $review->consolidated() : null;
    $maxTotal     = $SC::maxScore();
    $pass         = $SC::passPct();
    $scoreCell = function ($v) use ($SC) {
        if ($v === null) {
            return '<span class="text-muted">—</span>';
        }
        $tone = $v >= $SC::MET_FROM ? 'text-accent' : 'text-danger';

        return '<span class="fw-semibold '.$tone.'" title="'.e($SC::SCALE[$v][0] ?? '').'">'.$v.'</span>';
    };
    $canSee = fn ($member) => $revealed || $member->id === auth()->id();
@endphp
<section class="section-card" id="g3-consolidated">
    <div class="section-card-head">
        <span class="section-no"><i class="bi bi-table"></i></span>
        <div class="me-auto">
            <h5>Consolidated scorecard</h5>
            <div class="small text-muted">Each criterion is scored 1–5 ({{ $SC::MET_FROM }}+ = met). Individual scores stay hidden until all members have submitted.</div>
        </div>
        @if ($consolidated && $consolidated['average_pct'] !== null)
            <span class="pill {{ $consolidated['average_pct'] >= $pass ? 'pill-ok' : 'pill-warn' }}" title="Average of the members' totals">
                Committee average {{ $consolidated['average_score'] }} / {{ $maxTotal }} · {{ $consolidated['average_pct'] }}%
            </span>
        @endif
        <span class="pill {{ $status['complete'] ? 'pill-ok' : 'pill-warn' }}">
            <i class="bi {{ $status['complete'] ? 'bi-check-lg' : 'bi-hourglass-split' }}"></i> {{ $status['submitted'] }} of {{ $status['scoring'] }} submitted
        </span>
    </div>

    @if ($members->isEmpty())
        <p class="small text-muted mb-0">No Committee members set up.</p>
    @else
        <div class="table-responsive">
            <table class="table table-modern align-middle mb-2 text-center">
                <thead>
                    <tr>
                        <th class="text-start">Criteria</th>
                        @foreach ($members as $member) <th class="small">{{ $member->name }}</th> @endforeach
                        <th>Average</th>
                        <th>Met</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($SC::GROUPS as $gKey => [$gTitle, $gCriteria])
                        <tr class="table-light"><td colspan="{{ $members->count() + 3 }}" class="text-start small fw-semibold text-navy">{{ $gTitle }}</td></tr>
                        @foreach ($gCriteria as $cKey => $cText)
                            <tr>
                                <td class="text-start small">{{ $SC::SHORT[$cKey] ?? $cText }}</td>
                                @foreach ($members as $member)
                                    @php $c = $cards->get($member->id); @endphp
                                    <td>
                                        @if ($c?->isRecused())
                                            <span class="small text-muted">Recused</span>
                                        @elseif (! $c?->isSubmitted())
                                            <span class="pill pill-muted">Pending</span>
                                        @elseif ($canSee($member))
                                            {!! $scoreCell($c->scoreFor($cKey)) !!}
                                        @else
                                            <i class="bi bi-lock text-muted" title="Hidden until all scorecards are in"></i>
                                        @endif
                                    </td>
                                @endforeach
                                <td>
                                    @if ($consolidated && $consolidated['criteria'][$cKey]['average'] !== null)
                                        @php $avg = $consolidated['criteria'][$cKey]['average']; @endphp
                                        <span class="fw-semibold {{ $avg >= $SC::MET_FROM ? 'text-accent' : 'text-danger' }}">{{ number_format($avg, 1) }}</span>
                                    @else
                                        <span class="text-muted small">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($consolidated)
                                        @php $cc = $consolidated['criteria'][$cKey]; @endphp
                                        @if ($cc['majority'] === 'tie')
                                            <span class="pill pill-warn">Split</span>
                                        @else
                                            <i class="bi {{ $cc['majority'] === 'met' ? 'bi-check-circle-fill text-accent' : 'bi-x-circle-fill text-danger' }}"></i>
                                        @endif
                                        <div class="text-muted" style="font-size: .7rem">{{ $cc['met'] }}–{{ $cc['not_met'] }}</div>
                                    @else
                                        <span class="text-muted small">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="fw-semibold">
                        <td class="text-start">Total score</td>
                        @foreach ($members as $member)
                            @php $c = $cards->get($member->id); @endphp
                            <td>
                                @if ($c?->isRecused() || ! $c?->isSubmitted())
                                    <span class="small text-muted">—</span>
                                @elseif ($canSee($member))
                                    {{ $c->totalScore() }} / {{ $maxTotal }}
                                    <div class="small {{ $c->scorePct() >= $pass ? 'text-accent' : 'text-warning' }}">{{ $c->scorePct() }}%</div>
                                @else
                                    <i class="bi bi-lock text-muted"></i>
                                @endif
                            </td>
                        @endforeach
                        <td colspan="2">
                            @if ($consolidated && $consolidated['average_pct'] !== null)
                                {{ $consolidated['average_score'] }} / {{ $maxTotal }}
                                <div class="small {{ $consolidated['average_pct'] >= $pass ? 'text-accent' : 'text-warning' }}">{{ $consolidated['average_pct'] }}% average</div>
                            @else
                                <span class="text-muted small">—</span>
                            @endif
                        </td>
                    </tr>
                    <tr class="fw-semibold">
                        <td class="text-start">Overall recommendation</td>
                        @foreach ($members as $member)
                            @php $c = $cards->get($member->id); @endphp
                            <td>
                                @if ($c?->isRecused())
                                    <span class="small text-muted">—</span>
                                @elseif (! $c?->isSubmitted())
                                    <span class="pill pill-muted">Pending</span>
                                @elseif ($canSee($member))
                                    <span class="pill {{ $c->recommendation === 'approve' ? 'pill-ok' : 'pill-danger' }}">{{ $SC::RECOMMENDATIONS[$c->recommendation] ?? '—' }}</span>
                                @else
                                    <i class="bi bi-lock text-muted"></i>
                                @endif
                            </td>
                        @endforeach
                        <td colspan="2">
                            @if ($consolidated)
                                @php $r = $consolidated['recommendation']; @endphp
                                <span class="pill {{ ['approve' => 'pill-ok', 'reject' => 'pill-danger', 'tie' => 'pill-warn'][$r['majority']] }}">
                                    {{ ['approve' => 'Approve', 'reject' => 'Reject', 'tie' => 'Split'][$r['majority']] }} ({{ $r['approve'] }}–{{ $r['reject'] }})
                                </span>
                            @else
                                <span class="text-muted small">—</span>
                            @endif
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        @if ($consolidated && $consolidated['average_pct'] !== null)
            @php
                $scoreSays = $consolidated['average_pct'] >= $pass ? 'approve' : 'reject';
                $votesSay  = $consolidated['recommendation']['majority'];
            @endphp
            @if ($votesSay !== 'tie' && $scoreSays !== $votesSay)
                <div class="callout callout-warning small mb-2">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    The average score ({{ $consolidated['average_pct'] }}%) is {{ $scoreSays === 'approve' ? 'above' : 'below' }} the {{ $pass }}% guide,
                    but most members recommend <strong>{{ $votesSay === 'approve' ? 'Approve' : 'Reject' }}</strong>. Discuss this before recording the outcome.
                </div>
            @endif
        @endif

        @if ($revealed && $review)
            @php $withComments = $review->scorecards->filter(fn ($c) => $c->isSubmitted() && ! $c->isRecused()); @endphp
            <details class="evidence mt-2">
                <summary><i class="bi bi-chat-left-text me-1"></i> Members' comments &amp; take-aways</summary>
                <div class="p-3 small">
                    @foreach ($withComments as $c)
                        <div class="mb-3">
                            <div class="fw-semibold text-navy mb-1">{{ $c->user?->name }} <span class="text-muted fw-normal">· {{ $c->totalScore() }} / {{ $maxTotal }} ({{ $c->scorePct() }}%)</span></div>
                            @foreach ($SC::GROUPS as $gKey => [$gTitle])
                                @if (filled($c->comments[$gKey] ?? null))
                                    @php [$gs, $gm] = $c->groupScore($gKey); @endphp
                                    <div><span class="text-muted">{{ $gTitle }} ({{ $gs }}/{{ $gm }}):</span> {{ $c->comments[$gKey] }}</div>
                                @endif
                            @endforeach
                            @if ($c->innovation) <div><span class="text-muted">Innovation:</span> {{ collect($c->innovation)->map(fn ($i) => $SC::INNOVATION[$i] ?? $i)->implode(', ') }}</div> @endif
                            @if ($c->use_of_funds_notes) <div><span class="text-muted">Use of funds:</span> {{ $c->use_of_funds_notes }}</div> @endif
                            @if ($c->issues) <div><span class="text-muted">Issues:</span> {{ $c->issues }}</div> @endif
                            @if ($c->takeaways) <div class="quote mt-1 mb-0">{{ $c->takeaways }}</div> @endif
                        </div>
                    @endforeach
                    <div class="text-muted"><i class="bi bi-folder2-open me-1"></i> Comments on each item of the information pack are shown in the pack above.</div>
                </div>
            </details>
        @else
            <div class="small text-muted"><i class="bi bi-lock me-1"></i> Scores, comments and recommendations are revealed once every member has submitted.</div>
        @endif
    @endif
</section>