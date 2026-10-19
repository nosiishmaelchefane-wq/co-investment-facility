{{-- G3 · Committee record: review criteria (§3.4.2), deliberation (§3.4.3), risks & conditions — included from ⚡committee-review --}}

{{-- ============================ REVIEW CRITERIA ============================ --}}
<section class="section-card">
    <div class="section-card-head">
        <span class="section-no"><i class="bi bi-list-check"></i></span>
        <div class="me-auto">
            <h5>Committee review criteria</h5>
            <div class="small text-muted">The Committee's view on each area — recorded by the secretary during deliberation.</div>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-modern align-middle mb-0 dd-table">
            <thead><tr><th style="width: 20%">Area</th><th>Key consideration</th><th style="width: 17%">Committee view</th><th style="width: 30%">Notes</th></tr></thead>
            <tbody>
                @foreach ($CR::CRITERIA as $key => [$cTitle, $cQ])
                    @php $view = $criteria[$key]['view']; @endphp
                    <tr wire:key="crit-{{ $key }}">
                        <td class="fw-semibold text-navy small">{{ $cTitle }}</td>
                        <td class="small text-muted">{{ $cQ }}</td>
                        <td>
                            <select class="form-select form-select-sm status-select answer-select {{ $CR::VIEWS[$view][1] ?? '' }}" wire:model.live="criteria.{{ $key }}.view" @disabled(! $canRecord)>
                                <option value="">—</option>
                                @foreach ($CR::VIEWS as $vk => [$vl]) <option value="{{ $vk }}">{{ $vl }}</option> @endforeach
                            </select>
                        </td>
                        <td><input type="text" class="form-control form-control-sm" wire:model.blur="criteria.{{ $key }}.note" maxlength="500" @disabled(! $canRecord)></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>

{{-- ============================ DELIBERATION ============================ --}}
<section class="section-card">
    <div class="section-card-head">
        <span class="section-no"><i class="bi bi-chat-square-text"></i></span>
        <div class="me-auto">
            <h5>Committee deliberation</h5>
            <div class="small text-muted">Material to the recommentation deliberation.</div>
        </div>
    </div>

    @foreach ($CR::STEPS as $sKey => $sLabel)
        @php $done = $deliberation[$sKey]['done']; @endphp
        <div class="file-row align-items-start" wire:key="step-{{ $sKey }}">
            <label class="file-row-icon mb-0" style="cursor: {{ $canRecord ? 'pointer' : 'default' }}">
                <input type="checkbox" class="form-check-input m-0" wire:model.live="deliberation.{{ $sKey }}.done" @disabled(! $canRecord)>
            </label>
            <div class="flex-grow-1">
                <div class="fw-semibold small {{ $done ? 'text-accent' : 'text-navy' }}">{{ $loop->iteration }} · {{ $sLabel }}</div>
                <input type="text" class="form-control form-control-sm mt-1" wire:model.blur="deliberation.{{ $sKey }}.note" maxlength="1000" placeholder="Notes" @disabled(! $canRecord)>
            </div>
        </div>
    @endforeach

    <dt class="detail-label mt-3 mb-2">Questions log</dt>
    @forelse ($questions as $i => $q)
        <div class="file-row align-items-start" wire:key="q-{{ $i }}">
            <span class="file-row-icon"><i class="bi bi-question-lg"></i></span>
            <div class="flex-grow-1 small">
                <div class="fw-semibold text-navy">{{ $q['question'] }}</div>
                <div class="text-muted">Asked by {{ $q['asked_by'] }}</div>
                @if (filled($q['answer'] ?? null)) <div class="mt-1"><i class="bi bi-reply me-1 text-accent"></i>{{ $q['answer'] }}</div> @endif
            </div>
            @if ($canRecord)
                <button type="button" class="btn btn-icon btn-icon-danger" wire:click="removeQuestion({{ $i }})" title="Remove"><i class="bi bi-x-lg"></i></button>
            @endif
        </div>
    @empty
        <p class="small text-muted">No questions logged.</p>
    @endforelse

    @if ($canRecord)
        <div class="border rounded-3 p-2 mt-2">
            <div class="row g-2">
                <div class="col-md-3">
                    <select class="form-select form-select-sm @error('newQuestion.asked_by') is-invalid @enderror" wire:model="newQuestion.asked_by">
                        <option value="">Asked by…</option>
                        @foreach ($members as $member) <option value="{{ $member->name }}">{{ $member->name }}</option> @endforeach
                    </select>
                </div>
                <div class="col-md-9">
                    <input type="text" class="form-control form-control-sm @error('newQuestion.question') is-invalid @enderror" wire:model="newQuestion.question" maxlength="1000" placeholder="Question">
                </div>
                <div class="col-md-9 offset-md-3">
                    <input type="text" class="form-control form-control-sm" wire:model="newQuestion.answer" maxlength="2000" placeholder="Answer (optional)">
                </div>
                <div class="col-12 text-end">
                    <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="addQuestion"><i class="bi bi-plus-lg me-1"></i> Add question</button>
                </div>
            </div>
        </div>
    @endif
</section>

{{-- ============================ RISKS & CONDITIONS ============================ --}}
<section class="section-card">
    <div class="section-card-head">
        <span class="section-no"><i class="bi bi-shield-exclamation"></i></span>
        <div class="me-auto">
            <h5>Material risks &amp; conditions</h5>
            <div class="small text-muted">Do risks have mitigation, are conditions clearly defined, and is the grant still consistent with the private investment?</div>
        </div>
    </div>

    @if ($dd && filled($ddForm['key_risks'] ?? null))
        <dt class="detail-label mb-2">Key risks (Gate 2)</dt>
        <div class="quote small mb-3">{{ $ddForm['key_risks'] }}</div>
    @endif

    <div class="table-responsive mb-2">
        <table class="table table-modern align-middle mb-0 dd-table">
            <thead><tr><th>Condition</th><th style="width: 24%">Treatment</th><th style="width: 11%">Source</th><th style="width: 16%">Committee</th>@if ($canRecord)<th></th>@endif</tr></thead>
            <tbody>
                @forelse ($conditions as $i => $c)
                    <tr wire:key="cond-{{ $i }}" class="{{ $c['decision'] === 'removed' ? 'text-decoration-line-through text-muted' : '' }}">
                        <td>
                            @if ($c['source'] === 'committee' || $c['decision'] === 'amended')
                                <input type="text" class="form-control form-control-sm" wire:model.blur="conditions.{{ $i }}.finding" maxlength="250" placeholder="Condition" @disabled(! $canRecord)>
                            @else
                                <span class="small fw-semibold text-navy">{{ $c['finding'] }}</span>
                                @if (($c['status'] ?? null) === 'resolved') <span class="pill pill-ok ms-1">Resolved</span> @endif
                            @endif
                        </td>
                        <td>
                            @if ($c['source'] === 'committee' || $c['decision'] === 'amended')
                                <input type="text" class="form-control form-control-sm" wire:model.blur="conditions.{{ $i }}.treatment" maxlength="100" @disabled(! $canRecord)>
                            @else
                                <span class="small">{{ $c['treatment'] }}</span>
                            @endif
                        </td>
                        <td><span class="pill {{ $c['source'] === 'committee' ? 'pill-warn' : 'pill-muted' }}">{{ $c['source'] === 'committee' ? 'Committee' : 'Gate 2' }}</span></td>
                        <td>
                            @if ($c['source'] === 'committee')
                                <span class="pill pill-warn">Added</span>
                            @else
                                <select class="form-select form-select-sm" wire:model.live="conditions.{{ $i }}.decision" @disabled(! $canRecord)>
                                    @foreach (['accepted', 'amended', 'removed'] as $dk) <option value="{{ $dk }}">{{ $CR::CONDITION_DECISIONS[$dk] }}</option> @endforeach
                                </select>
                            @endif
                        </td>
                        @if ($canRecord)
                            <td>
                                @if ($c['source'] === 'committee')
                                    <button type="button" class="btn btn-icon btn-icon-danger" wire:click="removeCondition({{ $i }})" title="Remove"><i class="bi bi-x-lg"></i></button>
                                @endif
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="5" class="small text-muted">No conditions.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($canRecord)
        <button type="button" class="btn btn-sm btn-outline-secondary mb-3" wire:click="addCondition"><i class="bi bi-plus-lg me-1"></i> Add Committee condition</button>
    @endif

    <div class="callout small mb-0">
        <i class="bi bi-calculator me-1"></i> <strong>Co-investment:</strong>
        proposed CAFI grant {{ $m($validated['grant']) }} alongside {{ $m($validated['private']) }} committed private capital.
    </div>
</section>