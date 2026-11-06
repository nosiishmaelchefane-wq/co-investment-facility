{{-- G3 · Committee information pack (§3.4.1) — built from the Gate 2 assessment, incl. the Investment Appraisal Memorandum.
     Each Committee member comments on every item (saved with their scorecard). Other members' comments are shown
     once every scorecard is in, like the scores. Included from ⚡committee-review --}}
@php
    $pack        = $this->pack;
    $g2Url       = fn ($anchor) => route('expression-of-interest.show', $eoi->id).'?tab=g2'.($anchor ? '#'.$anchor : '');
    $iCanComment = $canScore && ($card['coi'] ?? null) !== 'recused';
    $included    = collect($pack)->filter(fn ($p) => $p['present'])->count();
    $myComments  = collect($card['pack_comments'] ?? [])->filter(fn ($t) => filled(trim((string) $t)))->count();
    $others      = $review?->scorecards->filter(fn ($c) => $c->isSubmitted() && ! $c->isRecused()) ?? collect();
@endphp
<section class="section-card" id="g3-pack">
    <div class="section-card-head">
        <span class="section-no"><i class="bi bi-folder2-open"></i></span>
        <div class="me-auto">
            <h5>Committee information pack</h5>
            <div class="small text-muted">
                From the Gate 2 assessment @if ($dd) (round {{ $dd->round }}, decided {{ $dd->decided_at->format('d M Y') }}) @endif.
                @if ($isMember) Read each item and add your comment — comments are part of your scorecard. @endif
            </div>
        </div>
        @if ($dd)
            <a href="{{ route('due-diligence.memorandum', $dd) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-file-earmark-pdf me-1"></i> Memorandum PDF</a>
        @endif
        <span class="pill {{ $included === count($CR::PACK) ? 'pill-ok' : 'pill-warn' }}">{{ $included }} / {{ count($CR::PACK) }} included</span>
        @if ($isMember && ($card['coi'] ?? null) !== 'recused')
            <span class="pill {{ $myComments === count($CR::PACK) ? 'pill-ok' : 'pill-warn' }}"><i class="bi bi-chat-left-text"></i> {{ $myComments }} / {{ count($CR::PACK) }} commented</span>
        @endif
    </div>

    @if (! $dd)
        <div class="callout callout-warning small mb-0"><i class="bi bi-exclamation-triangle me-1"></i> No completed Gate 2 assessment (Proceed) was found for this transaction.</div>
    @else
        @foreach ($CR::PACK as $key => [$doc, $purpose, $icon, $anchor])
            @php
                $item     = $pack[$key] ?? ['present' => false, 'status' => null, 'lines' => [], 'link' => null];
                $mine     = trim((string) ($card['pack_comments'][$key] ?? ''));
                $comments = $revealed ? $others->filter(fn ($c) => filled($c->pack_comments[$key] ?? null)) : collect();
            @endphp
            <details class="pack-item border rounded-3 mb-2 {{ $key === 'iam' ? 'border-primary' : '' }}" wire:key="pack-{{ $key }}" @if ($key === 'iam' || $errors->has("card.pack_comments.$key")) open @endif>
                <summary class="d-flex flex-wrap align-items-center gap-2 p-3">
                    <span class="file-row-icon flex-shrink-0"><i class="bi {{ $icon }}"></i></span>
                    <div class="flex-grow-1 small overflow-hidden" style="min-width: 200px">
                        <div class="fw-semibold text-navy">
                            {{ $loop->iteration }}. {{ $doc }}
                            @if (in_array($key, ['tss', 'iam'], true)) <span class="pill pill-muted ms-1">Key document</span> @endif
                        </div>
                        <div class="text-muted text-truncate">{{ $purpose }}</div>
                    </div>
                    @if ($item['status']) <span class="pill {{ $item['status'][1] }}">{{ $item['status'][0] }}</span> @endif
                    @if (! $item['present']) <span class="pill pill-danger">Missing</span> @endif
                    @if ($isMember && ($card['coi'] ?? null) !== 'recused')
                        @if ($mine !== '')
                            <span class="pill pill-ok" title="You have commented"><i class="bi bi-chat-left-text-fill"></i></span>
                        @else
                            <span class="pill pill-warn" title="Your comment is still needed"><i class="bi bi-chat-left"></i></span>
                        @endif
                    @endif
                    @if ($revealed && $comments->isNotEmpty())
                        <span class="pill pill-muted" title="Members' comments"><i class="bi bi-people"></i> {{ $comments->count() }}</span>
                    @endif
                </summary>

                <div class="px-3 pb-3">
                    {{-- Content of the pack item --}}
                    <dl class="evidence-grid mb-2">
                        @foreach ($item['lines'] as $line)
                            <dt>{{ $line[0] }}</dt>
                            <dd>
                                @if (! empty($line[2])) <div class="small text-muted fst-italic mb-1">{{ $line[2] }}</div> @endif
                                <span class="evidence-value" style="white-space: pre-line">{{ $line[1] }}</span>
                            </dd>
                        @endforeach
                    </dl>
                    <div class="d-flex flex-wrap gap-3 mb-2">
                        @if ($item['link'])
                            <a href="{{ $item['link'] }}" class="small text-decoration-none"><i class="bi bi-file-earmark-pdf me-1"></i>Download PDF</a>
                        @endif
                        @if ($anchor)
                            <a href="{{ $g2Url($anchor) }}" target="_blank" class="small text-decoration-none"><i class="bi bi-box-arrow-up-right me-1"></i>Open in Gate 2</a>
                        @endif
                    </div>

                    {{-- My comment --}}
                    @if ($iCanComment)
                        <label class="form-label small fw-semibold mb-1">
                            Your comment @if (\App\Models\CommitteeScorecard::PACK_COMMENTS_REQUIRED) <span class="req">*</span> @endif
                        </label>
                        <textarea rows="2" class="form-control form-control-sm @error("card.pack_comments.$key") is-invalid @enderror"
                                  wire:model.blur="card.pack_comments.{{ $key }}" maxlength="2000"
                                  placeholder="Your view on this item — is it sufficient, and what concerns or questions do you have?"></textarea>
                        @error("card.pack_comments.$key") <div class="invalid-feedback">{{ $message }}</div> @enderror
                    @elseif ($isMember && $mine !== '' && ! $revealed)
                        <div class="quote small mb-0"><span class="text-muted">Your comment:</span> {{ $mine }}</div>
                    @endif

                    {{-- Members' comments (after every scorecard is in) --}}
                    @if ($revealed)
                        @if ($comments->isNotEmpty())
                            <div class="mt-2">
                                @foreach ($comments as $c)
                                    <div class="small mb-1"><span class="fw-semibold text-navy">{{ $c->user?->name }}:</span> {{ $c->pack_comments[$key] }}</div>
                                @endforeach
                            </div>
                        @else
                            <div class="small text-muted mt-1">No comments from the members.</div>
                        @endif
                    @elseif (! $isMember)
                        <div class="small text-muted mt-1"><i class="bi bi-lock me-1"></i> Members' comments are shown once every scorecard is in.</div>
                    @endif
                </div>
            </details>
        @endforeach
    @endif
</section>

@once
    <style>
        .pack-item > summary { list-style: none; cursor: pointer; }
        .pack-item > summary::-webkit-details-marker { display: none; }
        .pack-item[open] > summary { border-bottom: 1px solid var(--bs-border-color, #e5e7eb); margin-bottom: .75rem; }
    </style>
@endonce