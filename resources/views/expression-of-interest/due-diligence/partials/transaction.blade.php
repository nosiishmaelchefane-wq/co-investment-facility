{{-- G2 · 3 Transaction due diligence & appraisal (§3.3.3 A–I) — included from ⚡due-deligence-check --}}
@php
    $t         = $form['transaction'];
    $stageKeys = array_keys($DD::STAGES);
    $stageAt   = $t['stage'] ? array_search($t['stage'], $stageKeys, true) : -1;
@endphp
<section class="section-card" id="g2-transaction">
    <div class="section-card-head">
        <span class="section-no">3</span>
        <div class="me-auto">
            <h5>Transaction due diligence &amp; appraisal</h5>
            <div class="small text-muted">{{ $DD::AREAS['transaction'][2] }}</div>
        </div>
        @include('expression-of-interest.due-diligence.partials.area-pill', ['pct' => $progress['transaction']])
    </div>

    {{-- ---------------- A. Stage ---------------- --}}
    <div class="g2-sub">
        <div class="g2-sub-head"><span class="g2-sub-letter">A</span> Existence &amp; status of the transaction</div>
        <div class="stage-ladder">
            @foreach ($DD::STAGES as $sKey => [$sLabel])
                @php $sState = $loop->index < $stageAt ? 'done' : ($loop->index === $stageAt ? 'current' : 'todo'); @endphp
                <label class="stage-ladder-step is-{{ $sState }} {{ $canEdit ? 'is-clickable' : '' }}" wire:key="stage-{{ $sKey }}">
                    <input type="radio" class="visually-hidden" name="dd-stage" value="{{ $sKey }}" wire:model.live="form.transaction.stage" @disabled(! $canEdit)>
                    <span class="stage-ladder-dot">
                        @if ($sState === 'done')<i class="bi bi-check-lg"></i>@elseif ($sState === 'current')<i class="bi bi-geo-alt-fill"></i>@endif
                    </span>
                    <span class="stage-ladder-label">{{ $sLabel }}</span>
                </label>
            @endforeach
        </div>

        @if ($t['stage'])
            <div class="small text-muted mt-2"><i class="bi bi-info-circle me-1"></i> Evidence that may be considered: {{ $DD::STAGES[$t['stage']][1] }}</div>
        @endif
        @if (in_array($t['stage'], ['interest', 'indicative'], true))
            <div class="callout callout-warning small mt-2 mb-0">
                <i class="bi bi-exclamation-triangle me-1"></i> This is <strong>investor interest</strong>, not commitment. The CI process generally needs a credible commitment.
            </div>
        @endif

        <div class="row g-2 mt-1">
            <div class="col-md-3">
                <label class="detail-label" for="stage_date">Date reached</label>
                <input type="date" id="stage_date" class="form-control form-control-sm" wire:model.blur="form.transaction.stage_date" @disabled(! $canEdit)>
            </div>
            <div class="col-md-9">
                <label class="detail-label" for="stage_evidence">Evidence supporting this stage</label>
                <input type="text" id="stage_evidence" class="form-control form-control-sm" wire:model.blur="form.transaction.stage_evidence" maxlength="500"
                       placeholder="e.g. Term sheet signed 30 Sep by the lead investor" @disabled(! $canEdit)>
            </div>
        </div>
    </div>

    {{-- ---------------- B. Commitment ---------------- --}}
    <div class="g2-sub">
        <div class="g2-sub-head"><span class="g2-sub-letter">B</span> Investor commitment</div>
        <div class="row g-3">
            <div class="col-lg-6">
                <dl class="evidence-grid border rounded-3 mb-0">
                    <dt>Investor(s)</dt>
                    <dd>{{ collect($form['investors'])->pluck('name')->filter()->implode(' · ') ?: '—' }}</dd>
                    <dt>Private capital proposed</dt>
                    <dd>{{ $m($capital['committed'] + $capital['indicative'] + $capital['potential']) }}
                        <div class="small text-muted">{{ $u($capital['committed'] + $capital['indicative'] + $capital['potential']) }}</div></dd>
                    @foreach ($DD::COMMITMENT_FIELDS as $cKey => $cLabel)
                        <dt>{{ $cLabel }}</dt>
                        <dd><input type="text" class="form-control form-control-sm" wire:model.blur="form.transaction.commitment.{{ $cKey }}" maxlength="250" @disabled(! $canEdit)></dd>
                    @endforeach
                </dl>
            </div>
            <div class="col-lg-6">
                <div class="table-responsive">
                    <table class="table table-modern align-middle mb-2 dd-table">
                        <thead><tr><th>Capital</th><th>Source</th><th class="text-end">Amount (M)</th><th class="text-center" title="Eligible private investment">Elig.</th>@if ($canEdit)<th></th>@endif</tr></thead>
                        <tbody>
                            @forelse ($t['capital'] as $i => $c)
                                <tr wire:key="cap-{{ $i }}">
                                    <td style="width: 28%">
                                        <select class="form-select form-select-sm" wire:model.live="form.transaction.capital.{{ $i }}.category" @disabled(! $canEdit)>
                                            @foreach ($DD::CAPITAL as $ck => [$cl]) <option value="{{ $ck }}">{{ $cl }}</option> @endforeach
                                        </select>
                                    </td>
                                    <td><input type="text" class="form-control form-control-sm" wire:model.blur="form.transaction.capital.{{ $i }}.source" maxlength="250" placeholder="Investor / source" @disabled(! $canEdit)></td>
                                    <td style="width: 26%">
                                        <input type="number" min="0" step="1000" class="form-control form-control-sm text-end" wire:model.blur="form.transaction.capital.{{ $i }}.amount" @disabled(! $canEdit)>
                                        <div class="text-muted dd-hint text-end">{{ $u($c['amount']) }}</div>
                                    </td>
                                    <td class="text-center"><input type="checkbox" class="form-check-input" wire:model.live="form.transaction.capital.{{ $i }}.eligible" @disabled(! $canEdit)></td>
                                    @if ($canEdit)
                                        <td><button type="button" class="btn btn-icon btn-icon-danger" wire:click="removeRow('capital', {{ $i }})" title="Remove"><i class="bi bi-x-lg"></i></button></td>
                                    @endif
                                </tr>
                            @empty
                                <tr><td colspan="5" class="small text-muted">No capital recorded.</td></tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr class="small">
                                <td colspan="2" class="text-muted">Committed · indicative · potential</td>
                                <td colspan="3" class="text-end text-muted">{{ $m($capital['committed']) }} · {{ $m($capital['indicative']) }} · {{ $m($capital['potential']) }}</td>
                            </tr>
                            <tr class="fw-semibold">
                                <td colspan="2">Eligible for co-investment calculation</td>
                                <td colspan="3" class="text-end">{{ $m($capital['eligible']) }}<div class="small text-muted fw-normal">{{ $u($capital['eligible']) }}</div></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                @if ($canEdit)
                    <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="addRow('capital')"><i class="bi bi-plus-lg me-1"></i> Add capital</button>
                @endif
                <div class="small text-muted mt-2"><i class="bi bi-info-circle me-1"></i> Only committed capital that meets the definition of eligible private investment counts towards matching.</div>
            </div>
        </div>
    </div>

    {{-- ---------------- C. Structure ---------------- --}}
    <div class="g2-sub">
        <div class="g2-sub-head"><span class="g2-sub-letter">C</span> Transaction structure</div>
        <dl class="evidence-grid border rounded-3 mb-0">
            <dt>Investment instrument</dt>
            <dd>
                <select class="form-select form-select-sm" wire:model.live="form.transaction.structure.instrument" @disabled(! $canEdit)>
                    <option value="">Select…</option>
                    @foreach ($DD::INSTRUMENTS as $ik => $il) <option value="{{ $ik }}">{{ $il }}</option> @endforeach
                </select>
            </dd>
            <dt>Investment amount</dt>
            <dd>{{ $m($capital['eligible']) }} eligible committed <span class="small text-muted">· from B</span></dd>
            <dt>Co-investment amount</dt>
            <dd>{{ $m($uof['grant']) }}
                @if ($match['ratio']) <span class="small text-muted">· M {{ number_format($match['ratio'], 2) }} private per M1 of grant</span> @endif
                <span class="small text-muted">· from E</span></dd>
            <dt>Valuation (M)</dt>
            <dd>
                <input type="number" min="0" step="1000" class="form-control form-control-sm" wire:model.blur="form.transaction.structure.valuation" @disabled(! $canEdit)>
                <div class="text-muted dd-hint">{{ $u($t['structure']['valuation']) }}</div>
            </dd>
            @foreach ($DD::STRUCTURE_FIELDS as $sKey => [$sLabel, $sFocus])
                @continue($sKey === 'instrument')
                <dt>{{ $sLabel }}</dt>
                <dd><input type="text" class="form-control form-control-sm" wire:model.blur="form.transaction.structure.{{ $sKey }}" maxlength="500"
                           placeholder="{{ $sFocus }}" @disabled(! $canEdit)></dd>
            @endforeach
        </dl>
    </div>

    {{-- ---------------- D. Financing requirement ---------------- --}}
    <div class="g2-sub">
        <div class="g2-sub-head"><span class="g2-sub-letter">D</span> Investment vs financing requirement</div>
        <div class="small text-muted mb-2">What is the investment intended to finance, and why is this amount appropriate for the growth plan?</div>
        @include('expression-of-interest.due-diligence.partials.answer-table', ['items' => $DD::FIT, 'path' => 'form.transaction.fit', 'rows' => $t['fit']])
    </div>

    {{-- ---------------- E. Use of funds ---------------- --}}
    <div class="g2-sub">
        <div class="g2-sub-head"><span class="g2-sub-letter">E</span> Use of funds schedule</div>
        <div class="table-responsive">
            <table class="table table-modern align-middle mb-2 dd-table">
                <thead><tr><th>Use of funds</th><th class="text-end">Private investment (M)</th><th class="text-end">CAFI grant (M)</th><th class="text-end">Total</th><th class="text-center">Eligible</th></tr></thead>
                <tbody>
                    @foreach ($t['use_of_funds'] as $i => $r)
                        <tr wire:key="uof-{{ $r['category'] }}">
                            <td class="small fw-semibold text-navy">{{ $DD::USE_CATEGORIES[$r['category']] ?? $r['category'] }}</td>
                            <td><input type="number" min="0" step="1000" class="form-control form-control-sm text-end" wire:model.blur="form.transaction.use_of_funds.{{ $i }}.private" @disabled(! $canEdit)></td>
                            <td><input type="number" min="0" step="1000" class="form-control form-control-sm text-end" wire:model.blur="form.transaction.use_of_funds.{{ $i }}.grant" @disabled(! $canEdit)></td>
                            <td class="text-end small fw-semibold">{{ $m((float) $r['private'] + (float) $r['grant']) }}</td>
                            <td class="text-center"><input type="checkbox" class="form-check-input" wire:model.live="form.transaction.use_of_funds.{{ $i }}.eligible" @disabled(! $canEdit)></td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="fw-semibold">
                        <td>Total</td>
                        <td class="text-end">{{ $m($uof['private']) }}<div class="small text-muted fw-normal">{{ $u($uof['private']) }}</div></td>
                        <td class="text-end">{{ $m($uof['grant']) }}<div class="small text-muted fw-normal">{{ $u($uof['grant']) }}</div></td>
                        <td class="text-end">{{ $m($uof['total']) }}<div class="small text-muted fw-normal">{{ $u($uof['total']) }}</div></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        @if ($uof['private'] > 0 && abs($uof['private'] - $capital['eligible']) > 1)
            <div class="callout callout-warning small mb-2">
                <i class="bi bi-exclamation-triangle me-1"></i> Private investment in the schedule ({{ $m($uof['private']) }}) differs from eligible committed capital ({{ $m($capital['eligible']) }}).
            </div>
        @endif
        <div class="d-flex flex-wrap gap-3 small">
            @foreach ($DD::UOF_CHECKS as $ck => $cl)
                <label class="form-check mb-0">
                    <input type="checkbox" class="form-check-input" wire:model.live="form.transaction.uof_checks.{{ $ck }}" @disabled(! $canEdit)>
                    <span class="form-check-label">{{ $cl }}</span>
                </label>
            @endforeach
        </div>
    </div>

    {{-- ---------------- F. Reconciliation ---------------- --}}
    <div class="g2-sub">
        <div class="g2-sub-head"><span class="g2-sub-letter">F</span> Co-investment reconciliation</div>
        @php
            $recon = [
                'total'   => ['Total transaction value',   $application['total'],   $validated['total']],
                'private' => ['Private capital committed', $application['private'], $validated['private']],
                'grant'   => ['Co-investment requested',   $application['grant'],   $validated['grant']],
            ];
        @endphp
        <div class="table-responsive">
            <table class="table table-modern align-middle mb-2 dd-table">
                <thead><tr><th>Figure</th><th class="text-end">Application</th><th class="text-end" style="width: 22%">Investor documents (M)</th><th class="text-end">Validated</th><th class="text-center">Reconciles</th></tr></thead>
                <tbody>
                    @foreach ($recon as $rKey => [$rLabel, $rApp, $rVal])
                        @php
                            $rDocs = $t['recon_docs'][$rKey];
                            $ok    = $rApp !== null && abs((float) $rApp - $rVal) < 1 && ($rDocs === null || $rDocs === '' || abs((float) $rDocs - $rVal) < 1);
                        @endphp
                        <tr>
                            <td class="small fw-semibold text-navy">{{ $rLabel }}</td>
                            <td class="text-end small">{{ $m($rApp) }}</td>
                            <td><input type="number" min="0" step="1000" class="form-control form-control-sm text-end" wire:model.blur="form.transaction.recon_docs.{{ $rKey }}" @disabled(! $canEdit)></td>
                            <td class="text-end small fw-semibold">{{ $m($rVal) }}<div class="text-muted fw-normal">{{ $u($rVal) }}</div></td>
                            <td class="text-center">
                                @if ($ok) <i class="bi bi-check-circle-fill text-accent"></i> @else <span class="pill pill-warn">Adjusted</span> @endif
                            </td>
                        </tr>
                    @endforeach
                    <tr>
                        <td class="small fw-semibold text-navy">Funding structure</td>
                        <td class="text-end small">{{ $application['structure'] ?: '—' }}</td>
                        <td><input type="text" class="form-control form-control-sm" wire:model.blur="form.transaction.recon_docs.structure" maxlength="100" @disabled(! $canEdit)></td>
                        <td class="text-end small fw-semibold">{{ $validated['structure'] ?? '—' }}</td>
                        <td class="text-center">
                            @if ($validated['structure'] && strcasecmp((string) $application['structure'], $validated['structure']) === 0)
                                <i class="bi bi-check-circle-fill text-accent"></i>
                            @else
                                <span class="pill pill-warn">Check</span>
                            @endif
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="callout {{ $match['within'] ? 'callout-success' : 'callout-warning' }} small mb-0">
            <i class="bi bi-calculator me-1"></i>
            @if ($validated['grant'] > 0 && $validated['private'] > 0)
                Grant {{ $m($validated['grant']) }} against eligible private capital {{ $m($validated['private']) }}
                = <strong>1 : {{ number_format($match['ratio'], 2) }}</strong> —
                {{ $match['within'] ? "within the Facility's matching rules" : "outside the Facility's matching rules" }}
                (max {{ rtrim(rtrim(number_format($match['max_ratio'], 2), '0'), '.') }} grant per M1 private{{ $match['max_lsl'] ? ', ceiling '.$m($match['max_lsl']) : '' }}).
                @if ($capital['indicative'] + $capital['potential'] > 0)
                    Indicative and potential capital ({{ $m($capital['indicative'] + $capital['potential']) }}) is excluded.
                @endif
            @else
                Record eligible committed capital (B) and the CI grant (E) to test the matching rules.
            @endif
        </div>
    </div>

    {{-- ---------------- G. Readiness ---------------- --}}
    <div class="g2-sub">
        <div class="g2-sub-head"><span class="g2-sub-letter">G</span> Credibility &amp; readiness for financial close</div>
        <div class="small text-muted mb-2">Document the current state of the transaction and its outstanding conditions — not a prediction of closing.</div>
        @include('expression-of-interest.due-diligence.partials.answer-table', ['items' => $DD::READINESS, 'path' => 'form.transaction.readiness', 'rows' => $t['readiness']])
    </div>

    {{-- ---------------- H. Risk register ---------------- --}}
    <div class="g2-sub">
        <div class="g2-sub-head"><span class="g2-sub-letter">H</span> Transaction risks <span class="small text-muted fw-normal ms-1">— risk register (consolidated in 6)</span></div>
        <div class="table-responsive">
            <table class="table table-modern align-middle mb-2 dd-table">
                <thead><tr><th style="width: 18%">Risk</th><th>Issue</th><th style="width: 12%">Rating</th><th>Mitigation</th><th style="width: 16%">Treatment</th>@if ($canEdit)<th></th>@endif</tr></thead>
                <tbody>
                    @forelse ($form['risks'] as $i => $r)
                        <tr wire:key="risk-{{ $i }}">
                            <td>
                                <select class="form-select form-select-sm" wire:model.live="form.risks.{{ $i }}.category" @disabled(! $canEdit)>
                                    @foreach ($DD::RISK_CATEGORIES as $rk => [$rl]) <option value="{{ $rk }}">{{ $rl }}</option> @endforeach
                                </select>
                            </td>
                            <td><input type="text" class="form-control form-control-sm" wire:model.blur="form.risks.{{ $i }}.issue" maxlength="500"
                                       placeholder="{{ $DD::RISK_CATEGORIES[$r['category']][1] ?? '' }}" @disabled(! $canEdit)></td>
                            <td>
                                <select class="form-select form-select-sm rating-select is-{{ $r['rating'] ?: 'none' }}" wire:model.live="form.risks.{{ $i }}.rating" @disabled(! $canEdit)>
                                    <option value="">—</option>
                                    @foreach ($DD::RATINGS as $rk => $rl) <option value="{{ $rk }}">{{ $rl }}</option> @endforeach
                                </select>
                            </td>
                            <td><input type="text" class="form-control form-control-sm" wire:model.blur="form.risks.{{ $i }}.mitigation" maxlength="500" @disabled(! $canEdit)></td>
                            <td>
                                <select class="form-select form-select-sm" wire:model.live="form.risks.{{ $i }}.treatment" @disabled(! $canEdit)>
                                    <option value="">—</option>
                                    @foreach ($DD::RISK_TREATMENTS as $rk => $rl) <option value="{{ $rk }}">{{ $rl }}</option> @endforeach
                                </select>
                            </td>
                            @if ($canEdit)
                                <td><button type="button" class="btn btn-icon btn-icon-danger" wire:click="removeRow('risks', {{ $i }})" title="Remove"><i class="bi bi-x-lg"></i></button></td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="6" class="small text-muted">No risks recorded.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($canEdit)
            <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="addRow('risks')"><i class="bi bi-plus-lg me-1"></i> Add risk</button>
        @endif
    </div>

    {{-- ---------------- I. Conditions & actions ---------------- --}}
    <div class="g2-sub mb-0">
        <div class="g2-sub-head"><span class="g2-sub-letter">I</span> Conditions &amp; actions</div>
        <div class="small text-muted mb-2">Separate what must be resolved before IC from conditions of approval and post-investment monitoring — every material risk needs an owner.</div>
        <div class="table-responsive">
            <table class="table table-modern align-middle mb-2 dd-table">
                <thead><tr><th>Finding</th><th style="width: 26%">Treatment</th><th style="width: 13%">Owner</th><th style="width: 13%">Status</th>@if ($canEdit)<th></th>@endif</tr></thead>
                <tbody>
                    @forelse ($conditions as $i => $c)
                        <tr wire:key="cond-{{ $c['id'] ?? 'n'.$i }}" class="{{ \App\Models\DueDiligenceCondition::blocks($c) ? 'is-flagged' : '' }}">
                            <td><input type="text" class="form-control form-control-sm" wire:model.blur="conditions.{{ $i }}.finding" maxlength="250" placeholder="Finding / action" @disabled(! $canEdit)></td>
                            <td>
                                <select class="form-select form-select-sm" wire:model.live="conditions.{{ $i }}.treatment" @disabled(! $canEdit)>
                                    @foreach (\App\Models\DueDiligenceCondition::TREATMENTS as $tk => [$tl]) <option value="{{ $tk }}">{{ $tl }}</option> @endforeach
                                </select>
                            </td>
                            <td>
                                <select class="form-select form-select-sm" wire:model.live="conditions.{{ $i }}.owner" @disabled(! $canEdit)>
                                    @foreach (\App\Models\DueDiligenceCondition::OWNERS as $ok => $ol) <option value="{{ $ok }}">{{ $ol }}</option> @endforeach
                                </select>
                            </td>
                            <td>
                                <select class="form-select form-select-sm" wire:model.live="conditions.{{ $i }}.status" @disabled(! $canEdit)>
                                    @foreach (\App\Models\DueDiligenceCondition::STATUSES as $sk => [$sl]) <option value="{{ $sk }}">{{ $sl }}</option> @endforeach
                                </select>
                            </td>
                            @if ($canEdit)
                                <td><button type="button" class="btn btn-icon btn-icon-danger" wire:click="removeRow('conditions', {{ $i }})" title="Remove"><i class="bi bi-x-lg"></i></button></td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="5" class="small text-muted">No conditions recorded.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($canEdit)
            <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="addRow('conditions')"><i class="bi bi-plus-lg me-1"></i> Add condition</button>
        @endif
    </div>
</section>