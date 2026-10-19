{{-- G3 · Consolidated scorecard — hidden until every non-recused member has submitted. Included from ⚡committee-review --}}
@php
    $consolidated = $revealed && $review ? $review->consolidated() : null;
    $cell = function ($v) {
        return match ($v) {
            'met'     => '<i class="bi bi-check-circle-fill text-accent" title="Met"></i>',
            'not_met' => '<i class="bi bi-x-circle-fill text-danger" title="Not met"></i>',
            default   => '<span class="text-muted">—</span>',
        };
    };
@endphp
<section class="section-card">
    <div class="section-card-head">
        <span class="section-no"><i class="bi bi-table"></i></span>
        <div class="me-auto">
            <h5>Consolidated scorecard</h5>
            <div class="small text-muted">Individual scores stay hidden until all members have submitted.</div>
        </div>
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
                        <th>Committee</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($SC::SHORT as $cKey => $cLabel)
                        <tr>
                            <td class="text-start small">{{ $cLabel }}</td>
                            @foreach ($members as $member)
                                @php $c = $cards->get($member->id); @endphp
                                <td>
                                    @if ($c?->isRecused())
                                        <span class="small text-muted">Recused</span>
                                    @elseif (! $c?->isSubmitted())
                                        <span class="pill pill-muted">Pending</span>
                                    @elseif ($revealed || $member->id === auth()->id())
                                        {!! $cell($c->answers[$cKey] ?? null) !!}
                                    @else
                                        <i class="bi bi-lock text-muted" title="Hidden until all scorecards are in"></i>
                                    @endif
                                </td>
                            @endforeach
                            <td>
                                @if ($consolidated)
                                    @php $cc = $consolidated['criteria'][$cKey]; @endphp
                                    {!! $cc['majority'] === 'tie' ? '<span class="pill pill-warn">Split</span>' : $cell($cc['majority']) !!}
                                    <div class="text-muted" style="font-size: .7rem">{{ $cc['met'] }}–{{ $cc['not_met'] }}</div>
                                @else
                                    <span class="text-muted small">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="fw-semibold">
                        <td class="text-start">Overall recommendation</td>
                        @foreach ($members as $member)
                            @php $c = $cards->get($member->id); @endphp
                            <td>
                                @if ($c?->isRecused())
                                    <span class="small text-muted">—</span>
                                @elseif (! $c?->isSubmitted())
                                    <span class="pill pill-muted">Pending</span>
                                @elseif ($revealed || $member->id === auth()->id())
                                    <span class="pill {{ $c->recommendation === 'approve' ? 'pill-ok' : 'pill-danger' }}">{{ $SC::RECOMMENDATIONS[$c->recommendation] ?? '—' }}</span>
                                @else
                                    <i class="bi bi-lock text-muted"></i>
                                @endif
                            </td>
                        @endforeach
                        <td>
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

        @if ($revealed && $review)
            @php $withComments = $review->scorecards->filter(fn ($c) => $c->isSubmitted() && ! $c->isRecused()); @endphp
            <details class="evidence mt-2">
                <summary><i class="bi bi-chat-left-text me-1"></i> Members' comments &amp; take-aways</summary>
                <div class="p-3 small">
                    @foreach ($withComments as $c)
                        <div class="mb-3">
                            <div class="fw-semibold text-navy mb-1">{{ $c->user?->name }}</div>
                            @foreach ($SC::GROUPS as $gKey => [$gTitle])
                                @if (filled($c->comments[$gKey] ?? null)) <div><span class="text-muted">{{ $gTitle }}:</span> {{ $c->comments[$gKey] }}</div> @endif
                            @endforeach
                            @if ($c->innovation) <div><span class="text-muted">Innovation:</span> {{ collect($c->innovation)->map(fn ($i) => $SC::INNOVATION[$i] ?? $i)->implode(', ') }}</div> @endif
                            @if ($c->use_of_funds_notes) <div><span class="text-muted">Use of funds:</span> {{ $c->use_of_funds_notes }}</div> @endif
                            @if ($c->issues) <div><span class="text-muted">Issues:</span> {{ $c->issues }}</div> @endif
                            @if ($c->takeaways) <div class="quote mt-1 mb-0">{{ $c->takeaways }}</div> @endif
                        </div>
                    @endforeach
                </div>
            </details>
        @else
            <div class="small text-muted"><i class="bi bi-lock me-1"></i> Scores, comments and recommendations are revealed once every member has submitted.</div>
        @endif
    @endif
</section>