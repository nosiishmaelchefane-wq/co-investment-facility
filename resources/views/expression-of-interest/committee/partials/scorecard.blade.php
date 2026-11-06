{{-- G3 · My evaluation scorecard (Appendix F) — included from ⚡committee-review --}}
@php
    $recused   = $card['coi'] === 'recused';
    $submitted = (bool) $myCard?->isSubmitted();
    $lock      = ! $canScore;
    $myScores  = collect($card['scores'] ?? [])->filter(fn ($v) => is_numeric($v))->map(fn ($v) => (int) $v);
    $myTotal   = $myScores->sum();
    $maxTotal  = $SC::maxScore();
    $myPct     = (int) round($myTotal / max($maxTotal, 1) * 100);
    $scored    = $myScores->count();
    $nCriteria = count($SC::criteriaKeys());
    $topScore  = max(array_keys($SC::SCALE));
@endphp
<section class="section-card" id="g3-scorecard"
         x-data
         x-on:scorecard-invalid.window="setTimeout(() => {
             const el = document.querySelector('#g3-pack .is-invalid, #g3-scorecard .is-invalid, #g3-scorecard .text-danger');
             if (el) { el.closest('details')?.setAttribute('open', ''); el.scrollIntoView({ behavior: 'smooth', block: 'center' }); el.focus?.(); }
         }, 150)">
    <div class="section-card-head">
        <span class="section-no"><i class="bi bi-clipboard-check"></i></span>
        <div class="me-auto">
            <h5>My evaluation scorecard</h5>
            <div class="small text-muted">Appendix F — Co-Investment Evaluation Scorecard · {{ auth()->user()->name }}</div>
        </div>
        @if (! $recused && $scored)
            <span class="pill {{ $myPct >= $SC::passPct() ? 'pill-ok' : 'pill-warn' }}" title="Your total score">{{ $myTotal }} / {{ $maxTotal }} · {{ $myPct }}%</span>
        @endif
        @if ($submitted)
            <span class="pill pill-ok"><i class="bi bi-check-lg"></i> Submitted {{ $myCard->submitted_at->format('d M Y H:i') }}</span>
        @elseif ($myCard)
            <span class="pill pill-warn"><i class="bi bi-pencil"></i> Draft</span>
        @else
            <span class="pill pill-muted">Not started</span>
        @endif
    </div>

    {{-- Conflict of interest --}}
    <div class="g2-sub">
        <div class="g2-sub-head"><i class="bi bi-shield-check me-1"></i> Conflict-of-interest declaration</div>
        <div class="choice-group cols-3 @error('card.coi') is-invalid @enderror">
            @foreach ($SC::COI as $ck => [$cl, , $cd])
                <label class="choice choice--card">
                    <input type="radio" name="my-coi" value="{{ $ck }}" wire:model.live="card.coi" @disabled($lock)>
                    <span class="choice-box">
                        <span class="choice-radio"></span>
                        <span><span class="d-block fw-semibold">{{ $cl }}</span><span class="d-block small text-muted lh-sm mt-1">{{ $cd }}</span></span>
                    </span>
                </label>
            @endforeach
        </div>
        @error('card.coi') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
        @if (in_array($card['coi'], ['declared', 'recused'], true))
            <textarea rows="2" class="form-control form-control-sm mt-2 @error('card.coi_details') is-invalid @enderror" wire:model.blur="card.coi_details"
                      placeholder="Describe the interest (relationship, nature, when)" @disabled($lock)></textarea>
            @error('card.coi_details') <div class="invalid-feedback">{{ $message }}</div> @enderror
        @endif
    </div>

    @if ($recused)
        <div class="callout small mb-3"><i class="bi bi-info-circle me-1"></i> You will not score this transaction. Submit to record your recusal.</div>
    @else
        {{-- Scoring scale --}}
        <div class="callout small mb-3">
            <div class="fw-semibold mb-1"><i class="bi bi-123 me-1"></i> Score each criterion from 1 to {{ $topScore }}</div>
            <div class="d-flex flex-wrap gap-3">
                @foreach ($SC::SCALE as $sv => [$sl, $sd])
                    <span title="{{ $sd }}"><strong>{{ $sv }}</strong> {{ $sl }}</span>
                @endforeach
            </div>
            <div class="text-muted mt-1">{{ $SC::MET_FROM }} or more means the criterion is met. An overall score of {{ $SC::passPct() }}% or more supports approval — it guides, but does not replace, your recommendation.</div>
        </div>

        {{-- Criteria --}}
        @foreach ($SC::GROUPS as $gKey => [$gTitle, $criteria])
            @php
                $gKeys  = array_keys($criteria);
                $gScore = collect($gKeys)->sum(fn ($k) => (int) ($card['scores'][$k] ?? 0));
                $gMax   = count($gKeys) * $topScore;
            @endphp
            <div class="g2-sub" wire:key="grp-{{ $gKey }}">
                <div class="g2-sub-head d-flex align-items-center">
                    <span class="me-auto">{{ $gTitle }}</span>
                    <span class="small text-muted fw-normal">{{ $gScore }} / {{ $gMax }}</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-modern align-middle mb-2 dd-table">
                        <tbody>
                            @foreach ($criteria as $cKey => $cText)
                                <tr>
                                    <td class="small">
                                        {{ $cText }}
                                        @if ($cKey === 'innovative')
                                            <div class="d-flex flex-wrap gap-3 mt-2">
                                                @foreach ($SC::INNOVATION as $iKey => $iLabel)
                                                    <label class="form-check mb-0">
                                                        <input type="checkbox" class="form-check-input" wire:model.live="card.innovation.{{ $iKey }}" @disabled($lock)>
                                                        <span class="form-check-label">{{ $iLabel }}</span>
                                                    </label>
                                                @endforeach
                                            </div>
                                            @error('card.innovation') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                                        @endif
                                        @error("card.scores.$cKey") <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                                    </td>
                                    <td class="text-center" style="width: 30%">
                                        @php $cur = $card['scores'][$cKey] ?? null; @endphp
                                        <div class="btn-group btn-group-sm" role="group" aria-label="Score">
                                            @foreach ($SC::SCALE as $sv => [$sl, $sd])
                                                <input type="radio" class="btn-check" name="sc-{{ $cKey }}" id="sc-{{ $cKey }}-{{ $sv }}" value="{{ $sv }}" wire:model.live="card.scores.{{ $cKey }}" @disabled($lock)>
                                                <label class="btn {{ $sv >= $SC::MET_FROM ? 'btn-outline-success' : 'btn-outline-danger' }}" for="sc-{{ $cKey }}-{{ $sv }}" title="{{ $sl }} — {{ $sd }}">{{ $sv }}</label>
                                            @endforeach
                                        </div>
                                        <div class="small mt-1 {{ $cur === null ? 'text-muted' : ((int) $cur >= $SC::MET_FROM ? 'text-success' : 'text-danger') }}">
                                            {{ $cur === null ? 'Not scored' : $SC::SCALE[(int) $cur][0].' · '.((int) $cur >= $SC::MET_FROM ? 'met' : 'not met') }}
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <textarea rows="2" class="form-control form-control-sm" wire:model.blur="card.comments.{{ $gKey }}" placeholder="Comments" @disabled($lock)></textarea>
            </div>
        @endforeach

        {{-- Total --}}
        <div class="d-flex flex-wrap align-items-center gap-3 border rounded-3 p-3 mb-3">
            <div>
                <div class="detail-label mb-0">Your total score</div>
                <div class="fs-5 fw-semibold text-navy">{{ $myTotal }} / {{ $maxTotal }} <span class="small text-muted">({{ $myPct }}%)</span></div>
            </div>
            <div class="flex-grow-1" style="min-width: 160px">
                <div class="progress" style="height: 8px;">
                    <div class="progress-bar {{ $myPct >= $SC::passPct() ? 'bg-success' : 'bg-warning' }}" style="width: {{ $myPct }}%"></div>
                </div>
                <div class="small text-muted mt-1">{{ $scored }} of {{ $nCriteria }} criteria scored · guide {{ $SC::passPct() }}%</div>
            </div>
        </div>

        {{-- Recommendation & remarks --}}
        <div class="g2-sub mb-0">
            <div class="g2-sub-head">Recommendation &amp; remarks</div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Use of funds — restrictions / advice</label>
                    <textarea rows="2" class="form-control form-control-sm" wire:model.blur="card.use_of_funds_notes" @disabled($lock)
                              placeholder="Declaration of any restrictions for this funding. Any advice to be given with funding."></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Issues with carrying out this assignment</label>
                    <textarea rows="2" class="form-control form-control-sm" wire:model.blur="card.issues" @disabled($lock)
                              placeholder="Conflict of interest, missing documents, documents hard to read / understand."></textarea>
                </div>
                <div class="col-12">
                    <label class="form-label small fw-semibold d-block">Overall recommendation <span class="req">*</span></label>
                    <div class="choice-group cols-2 @error('card.recommendation') is-invalid @enderror">
                        <label class="choice"><input type="radio" name="my-rec" value="approve" wire:model.live="card.recommendation" @disabled($lock)>
                            <span class="choice-box"><span class="choice-radio"></span><i class="bi bi-hand-thumbs-up text-accent"></i> Approve</span></label>
                        <label class="choice"><input type="radio" name="my-rec" value="reject" wire:model.live="card.recommendation" @disabled($lock)>
                            <span class="choice-box"><span class="choice-radio"></span><i class="bi bi-hand-thumbs-down text-danger"></i> Reject</span></label>
                    </div>
                    @error('card.recommendation') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                </div>
                <div class="col-12">
                    <label class="form-label small fw-semibold">Key take-aways and overriding rationale <span class="req">*</span></label>
                    <textarea rows="3" class="form-control form-control-sm @error('card.takeaways') is-invalid @enderror" wire:model.blur="card.takeaways" @disabled($lock)></textarea>
                    @error('card.takeaways') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>
        </div>
    @endif

    @if ($canScore && $errors->has('card.*'))
        @php
            $packMissing  = collect($CR::PACK)->filter(fn ($p, $k) => $errors->has("card.pack_comments.$k"));
            $scoreMissing = collect($SC::SHORT)->filter(fn ($l, $k) => $errors->has("card.scores.$k"));
        @endphp
        <div class="callout callout-warning small mt-3 mb-0">
            <div class="fw-semibold mb-1"><i class="bi bi-exclamation-triangle me-1"></i> Your scorecard can't be submitted yet</div>
            <ul class="evidence-list mb-0">
                @error('card.coi') <li>{{ $message }}</li> @enderror
                @error('card.coi_details') <li>{{ $message }}</li> @enderror
                @if ($packMissing->isNotEmpty())
                    <li>
                        Comment on {{ $packMissing->count() === 1 ? 'this item' : 'these '.$packMissing->count().' items' }} of the
                        <a href="#g3-pack">information pack</a>: {{ $packMissing->map(fn ($p) => $p[0])->implode(', ') }}
                    </li>
                @endif
                @if ($scoreMissing->isNotEmpty())
                    <li>Score: {{ $scoreMissing->implode(', ') }}</li>
                @endif
                @error('card.innovation') <li>{{ $message }}</li> @enderror
                @error('card.recommendation') <li>{{ $message }}</li> @enderror
                @error('card.takeaways') <li>{{ $message }}</li> @enderror
            </ul>
        </div>
    @endif

    @if ($canScore)
        <div class="d-flex flex-wrap align-items-center gap-2 mt-3 pt-3 border-top">
            <span class="small text-muted"><i class="bi bi-pen me-1"></i> Submitting signs the scorecard — your scores and your comments on the information pack — as {{ auth()->user()->name }}. It can't be changed afterwards.</span>
            <button type="button" class="btn btn-brand ms-auto" wire:click="submitScorecard" wire:loading.attr="disabled" wire:target="submitScorecard"
                    wire:confirm="Submit your scorecard? It can't be changed afterwards.">
                <span wire:loading.remove wire:target="submitScorecard"><i class="bi bi-send me-1"></i> {{ $recused ? 'Submit recusal' : 'Submit scorecard' }}</span>
                <span wire:loading wire:target="submitScorecard"><span class="spinner-border spinner-border-sm me-1"></span> Submitting…</span>
            </button>
        </div>
    @endif
</section>