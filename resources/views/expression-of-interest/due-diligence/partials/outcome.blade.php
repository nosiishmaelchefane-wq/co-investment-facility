{{-- G2 · Outcome (§3.3.4) + earlier rounds — included from ⚡due-deligence-check --}}
@php $openConds = collect($conditions)->where('status', 'open'); @endphp
<section class="section-card" id="g2-outcome">
    <div class="section-card-head">
        <span class="section-no"><i class="bi bi-signpost-split"></i></span>
        <div class="me-auto">
            <h5>Gate 2 outcome</h5>
            <div class="small text-muted">
                @if ($decided)
                    Recorded by {{ $dd->decider?->name ?? '—' }} on {{ $dd->decided_at->format('d M Y H:i') }}.
                @else
                    Is the transaction sufficiently developed and evidenced for Investment Committee consideration?
                @endif
            </div>
        </div>
    </div>

    <div class="choice-group cols-4 @error('form.outcome') is-invalid @enderror">
        @foreach ($DD::OUTCOMES as $value => [$oLabel, $oDesc, $oIcon])
            <label class="choice choice--card">
                <input type="radio" name="g2-outcome" value="{{ $value }}" wire:model.live="form.outcome" @disabled(! $canEdit)>
                <span class="choice-box">
                    <span class="choice-icon"><i class="bi {{ $oIcon }}"></i></span>
                    <span>
                        <span class="d-block fw-semibold">{{ $oLabel }}</span>
                        <span class="d-block small text-muted lh-sm mt-1">{{ $oDesc }}</span>
                    </span>
                </span>
            </label>
        @endforeach
    </div>
    @error('form.outcome') <div class="text-danger small mt-1">{{ $message }}</div> @enderror

    @if (! $decided)
        @if ($blockers)
            <div class="callout callout-warning small mt-3 mb-0">
                <div class="fw-semibold mb-1"><i class="bi bi-exclamation-triangle me-1"></i> Not ready to proceed ({{ count($blockers) }})</div>
                <ul class="evidence-list mb-0">
                    @foreach ($blockers as $b) <li>{{ $b }}</li> @endforeach
                </ul>
            </div>
        @else
            <div class="callout callout-success small mt-3 mb-0"><i class="bi bi-check2-circle me-1"></i> All areas complete — ready to proceed to the Investment Committee.</div>
        @endif
    @endif

    <div class="mt-3">
        <label class="form-label" for="g2_conclusion">Assessor's conclusion @if ($canEdit)<span class="req">*</span>@endif</label>
        <textarea id="g2_conclusion" rows="4" class="form-control @error('form.conclusion') is-invalid @enderror" wire:model.blur="form.conclusion" @disabled(! $canEdit)
                  placeholder="Summary of the appraisal and recommendation to the Investment Committee."></textarea>
        @error('form.conclusion') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="row g-3 mt-1">
        <div class="col-md-6">
            <label class="form-label">Assessed by</label>
            <input type="text" class="form-control bg-light" value="{{ $dd?->decider?->name ?? $dd?->assessor?->name ?? auth()->user()->name }}" readonly>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="g2_date">Date</label>
            <input type="date" id="g2_date" class="form-control @error('form.assessed_on') is-invalid @enderror" wire:model.blur="form.assessed_on"
                   max="{{ now()->toDateString() }}" @disabled(! $canEdit)>
            @error('form.assessed_on') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    </div>

    @if ($canEdit)
        <div class="d-flex flex-wrap gap-2 mt-4 pt-3 border-top">
            <span class="small text-muted align-self-center"><i class="bi bi-cloud-check me-1"></i> Draft saved automatically</span>
            <button type="button" class="btn btn-brand ms-auto" wire:click="recordOutcome" wire:loading.attr="disabled" wire:target="recordOutcome"
                    wire:confirm="Record the Gate 2 outcome? The assessment is locked afterwards.">
                <span wire:loading.remove wire:target="recordOutcome"><i class="bi bi-send me-1"></i> Record Gate 2 outcome</span>
                <span wire:loading wire:target="recordOutcome"><span class="spinner-border spinner-border-sm me-1"></span> Recording…</span>
            </button>
        </div>
    @elseif ($dd?->isOnHold() && $eoi->status === 'dd_hold' && auth()->user()->can('gate2.assess'))
        <div class="d-flex flex-wrap align-items-center gap-2 mt-4 pt-3 border-top">
            <span class="small text-muted">Open items: {{ $openConds->count() }}. Resume once the clarification or remediation has been received.</span>
            <button type="button" class="btn btn-outline-primary ms-auto" wire:click="resumeAssessment"
                    wire:confirm="Resume the assessment as round {{ $round + 1 }}? Everything is carried over.">
                <i class="bi bi-arrow-repeat me-1"></i> Resume assessment
            </button>
        </div>
    @endif
</section>

@if ($history->isNotEmpty())
    <section class="section-card">
        <div class="section-card-head">
            <span class="section-no"><i class="bi bi-clock-history"></i></span>
            <h5>Earlier rounds</h5>
        </div>
        @foreach ($history as $h)
            @php [$hl, , $hi, $hp] = $DD::OUTCOMES[$h->outcome] ?? [$h->outcome, '', 'bi-circle', 'pill-muted']; @endphp
            <div class="file-row align-items-start">
                <span class="file-row-icon"><i class="bi {{ $hi }}"></i></span>
                <div class="flex-grow-1 small">
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <strong class="text-navy">Round {{ $h->round }}</strong>
                        <span class="pill {{ $hp }}">{{ $hl }}</span>
                        <span class="text-muted">{{ $h->decided_at->format('d M Y') }} · {{ $h->decider?->name }}</span>
                    </div>
                    @if ($h->conclusion) <div class="text-muted mt-1">{{ $h->conclusion }}</div> @endif
                </div>
            </div>
        @endforeach
    </section>
@endif