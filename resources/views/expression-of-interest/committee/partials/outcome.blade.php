{{-- G3 · Outcome (§3.4.4), decision record & handover (§3.4.5), earlier rounds — included from ⚡committee-review --}}
@php
    $consol = $revealed && $review ? $review->consolidated() : null;
    $maj    = $consol['recommendation']['majority'] ?? null;
    $out    = $decision['outcome'];
    $diverges = $consol && $out && (
        ($maj === 'approve' && in_array($out, ['reject'], true)) ||
        ($maj === 'reject' && in_array($out, ['approve', 'conditional'], true))
    );
@endphp
<section class="section-card" id="g3-outcome">
    <div class="section-card-head">
        <span class="section-no"><i class="bi bi-signpost-split"></i></span>
        <div class="me-auto">
            <h5>Committee outcome</h5>
            <div class="small text-muted">
                @if ($decided)
                    Recorded by {{ $review->decider?->name ?? '—' }} on {{ $review->decided_at->format('d M Y H:i') }}.
                @else
                    Conditional approval = substantively satisfied, with defined conditions. Defer = not yet enough information or assurance.
                @endif
            </div>
        </div>
    </div>

    <div class="choice-group cols-4 @error('decision.outcome') is-invalid @enderror">
        @foreach ($CR::OUTCOMES as $value => [$oLabel, $oMeaning, $oNext, $oIcon])
            <label class="choice choice--card">
                <input type="radio" name="g3-outcome" value="{{ $value }}" wire:model.live="decision.outcome" @disabled(! $canRecord)>
                <span class="choice-box">
                    <span class="choice-icon"><i class="bi {{ $oIcon }}"></i></span>
                    <span>
                        <span class="d-block fw-semibold">{{ $oLabel }}</span>
                        <span class="d-block small text-muted lh-sm mt-1">{{ $oMeaning }}</span>
                        <span class="d-block small text-brand mt-1"><i class="bi bi-arrow-right-short"></i>{{ $oNext }}</span>
                    </span>
                </span>
            </label>
        @endforeach
    </div>
    @error('decision.outcome') <div class="text-danger small mt-1">{{ $message }}</div> @enderror

    @if (! $decided && $blockers)
        <div class="callout callout-warning small mt-3 mb-0">
            <div class="fw-semibold mb-1"><i class="bi bi-exclamation-triangle me-1"></i> Not ready to record</div>
            <ul class="evidence-list mb-0">@foreach ($blockers as $b) <li>{{ $b }}</li> @endforeach</ul>
        </div>
    @elseif ($diverges)
        <div class="callout callout-warning small mt-3 mb-0">
            <i class="bi bi-exclamation-triangle me-1"></i> This outcome differs from the members' majority recommendation
            ({{ $maj === 'approve' ? 'Approve' : 'Reject' }}). Explain why in the rationale.
        </div>
    @endif

    <div class="row g-3 mt-1">
        <div class="col-md-4">
            <label class="form-label small fw-semibold">Private investment (M)</label>
            <input type="number" min="0" step="1000" class="form-control @error('decision.private_amount') is-invalid @enderror" wire:model.blur="decision.private_amount" @disabled(! $canRecord)>
            <div class="small text-muted">{{ $u($decision['private_amount']) }} · Gate 2 validated {{ $m($validated['private']) }}</div>
            @error('decision.private_amount') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-4">
            <label class="form-label small fw-semibold">Recommended CAFI grant (M) @if (in_array($out, ['approve', 'conditional'], true)) <span class="req">*</span> @endif</label>
            <input type="number" min="0" step="1000" class="form-control @error('decision.grant_amount') is-invalid @enderror" wire:model.blur="decision.grant_amount" @disabled(! $canRecord)>
            <div class="small text-muted">{{ $u($decision['grant_amount']) }} · Gate 2 validated {{ $m($validated['grant']) }}</div>
            @error('decision.grant_amount') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-4">
            <label class="form-label small fw-semibold">Committee members present</label>
            <div class="small text-navy pt-1">
                {{ $members->reject(fn ($mbr) => $cards->get($mbr->id)?->isRecused())->pluck('name')->implode(', ') ?: '—' }}
            </div>
        </div>
        <div class="col-12">
            <label class="form-label small fw-semibold">Rationale for the decision @if ($canRecord) <span class="req">*</span> @endif</label>
            <textarea rows="3" class="form-control @error('decision.rationale') is-invalid @enderror" wire:model.blur="decision.rationale" @disabled(! $canRecord)
                      placeholder="Why the Committee reached this outcome."></textarea>
            @error('decision.rationale') <div class="invalid-feedback">{{ $message }}</div> @enderror
            @if ($canRecord && $out === 'reject')
                <div class="small text-warning mt-1"><i class="bi bi-envelope me-1"></i> The rationale is e-mailed to the applicant as the Committee's reasons.</div>
            @endif
        </div>
        <div class="col-12">
            <label class="form-label small fw-semibold">Actions required before progression @if ($out === 'defer' && $canRecord) <span class="req">*</span> @endif</label>
            <textarea rows="2" class="form-control @error('decision.actions') is-invalid @enderror" wire:model.blur="decision.actions" @disabled(! $canRecord)
                      placeholder="{{ $out === 'defer' ? 'What must be provided or assessed before resubmission.' : 'e.g. Conditions precedent before disbursement.' }}"></textarea>
            @error('decision.actions') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    </div>

    @if ($canRecord)
        <div class="d-flex flex-wrap gap-2 mt-4 pt-3 border-top">
            <span class="small text-muted align-self-center"><i class="bi bi-cloud-check me-1"></i> Draft saved automatically</span>
            <button type="button" class="btn btn-brand ms-auto" wire:click="recordDecision" wire:loading.attr="disabled" wire:target="recordDecision"
                    wire:confirm="Record the Committee decision? It is locked afterwards. The applicant, the PMU and the Committee members will be e-mailed." @disabled($blockers)>
                <span wire:loading.remove wire:target="recordDecision"><i class="bi bi-send me-1"></i> Record Committee decision</span>
                <span wire:loading wire:target="recordDecision"><span class="spinner-border spinner-border-sm me-1"></span> Recording…</span>
            </button>
        </div>
    @endif

    {{-- Deferred → PMU resubmits --}}
    @if ($decided && $review->outcome === 'defer' && $eoi->status === 'ic_deferred' && auth()->user()->can('gate2.assess'))
        <div class="border-top mt-4 pt-3">
            <label class="form-label small fw-semibold">Response to the Committee <span class="req">*</span></label>
            <textarea rows="3" class="form-control @error('resubmissionNote') is-invalid @enderror" wire:model="resubmissionNote"
                      placeholder="How each of the Committee's requests has been addressed."></textarea>
            @error('resubmissionNote') <div class="invalid-feedback">{{ $message }}</div> @enderror
            <div class="text-end mt-2">
                <button type="button" class="btn btn-outline-primary" wire:click="resubmit" wire:confirm="Resubmit to the Investment Committee as round {{ $round + 1 }}? The Committee members will be e-mailed with your response.">
                    <i class="bi bi-arrow-repeat me-1"></i> Resubmit to the Committee
                </button>
            </div>
        </div>
    @endif
</section>

{{-- ============================ DECISION RECORD & HANDOVER ============================ --}}
@if ($decided)
    <section class="section-card">
        <div class="section-card-head">
            <span class="section-no"><i class="bi bi-journal-check"></i></span>
            <h5 class="me-auto">Decision record</h5>
            @php [$oL, , $oN, $oI, $oP] = $CR::OUTCOMES[$review->outcome]; @endphp
            <span class="pill {{ $oP }}"><i class="bi {{ $oI }}"></i> {{ $oL }}</span>
        </div>

        <div class="table-responsive mb-3">
            <table class="table table-modern align-middle mb-0 tss-table">
                <tbody>
                    <tr><th>Transaction / enterprise</th><td>{{ $eoi->reference }} · {{ $eoi->business_name }}</td></tr>
                    <tr><th>Meeting</th><td>{{ $review->meeting_ref ?: '—' }} · {{ $review->meeting_date?->format('d M Y') ?? '—' }} {{ $review->venue ? '· '.$review->venue : '' }}</td></tr>
                    <tr><th>Members present</th><td>{{ $review->scorecards->reject->isRecused()->map(fn ($c) => $c->user?->name)->filter()->implode(', ') ?: '—' }}</td></tr>
                    <tr><th>Conflict-of-interest declarations</th><td>
                        @forelse ($review->scorecards->whereIn('coi', ['declared', 'recused']) as $c)
                            <div>{{ $c->user?->name }} — {{ $SC::COI[$c->coi][0] }}{{ $c->coi_details ? ': '.$c->coi_details : '' }}</div>
                        @empty None declared. @endforelse
                    </td></tr>
                    <tr><th>Outcome</th><td><span class="pill {{ $oP }}">{{ $oL }}</span> <span class="small text-muted">· {{ $oN }}</span></td></tr>
                    <tr><th>Private investment</th><td>{{ $m($review->private_amount) }} <span class="small text-muted">· {{ $u($review->private_amount) }}</span></td></tr>
                    <tr><th>Proposed CAFI grant</th><td>{{ $m($review->grant_amount) }} <span class="small text-muted">· {{ $u($review->grant_amount) }}</span></td></tr>
                    <tr><th>Key conditions</th><td>
                        @php $live = collect($review->conditions ?? [])->reject(fn ($c) => ($c['decision'] ?? '') === 'removed'); @endphp
                        @forelse ($live as $c) <div>• {{ $c['finding'] }} <span class="text-muted small">({{ $c['treatment'] }})</span></div> @empty — @endforelse
                    </td></tr>
                    <tr><th>Rationale</th><td>{{ $review->rationale }}</td></tr>
                    <tr><th>Actions before progression</th><td>{{ $review->actions ?: '—' }}</td></tr>
                </tbody>
            </table>
        </div>

        @if ($review->isApproved())
            <dt class="detail-label mb-2">Handed over to Gate 4</dt>
            <div class="d-flex flex-wrap gap-2 small mb-3">
                @foreach (['Investment Appraisal Memorandum', 'Transaction Summary Sheet', 'Supporting due diligence', 'Committee decision & scorecards', 'Conditions'] as $h)
                    <span class="pill pill-ok"><i class="bi bi-check-lg"></i> {{ $h }}</span>
                @endforeach
            </div>
            <dt class="detail-label mb-2">Authorisation sequence</dt>
            <div class="d-flex flex-wrap align-items-center gap-2 small">
                <span class="pill pill-muted"><i class="bi bi-person-badge"></i> CAFI Managing Director</span>
                <i class="bi bi-arrow-right text-muted"></i>
                <span class="pill pill-muted"><i class="bi bi-bank2"></i> MTIBD Principal Secretary <span class="text-muted">(where applicable)</span></span>
            </div>
        @endif
    </section>
@endif

{{-- ============================ EARLIER ROUNDS ============================ --}}
@if ($history->isNotEmpty())
    <section class="section-card">
        <div class="section-card-head">
            <span class="section-no"><i class="bi bi-clock-history"></i></span>
            <h5>Earlier Committee rounds</h5>
        </div>
        @foreach ($history as $h)
            @php [$hl, , , $hi, $hp] = $CR::OUTCOMES[$h->outcome] ?? [$h->outcome, '', '', 'bi-circle', 'pill-muted']; @endphp
            <div class="file-row align-items-start">
                <span class="file-row-icon"><i class="bi {{ $hi }}"></i></span>
                <div class="flex-grow-1 small">
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <strong class="text-navy">Round {{ $h->round }}</strong>
                        <span class="pill {{ $hp }}">{{ $hl }}</span>
                        <span class="text-muted">{{ $h->decided_at->format('d M Y') }} · {{ $h->decider?->name }}</span>
                    </div>
                    @if ($h->rationale) <div class="text-muted mt-1">{{ $h->rationale }}</div> @endif
                    @if ($h->actions) <div class="mt-1"><span class="text-muted">Actions:</span> {{ $h->actions }}</div> @endif
                </div>
            </div>
        @endforeach
    </section>
@endif