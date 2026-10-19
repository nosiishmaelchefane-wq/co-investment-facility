{{-- G2 · 1 Enterprise due diligence (§3.3.1) — included from ⚡due-deligence-check --}}
@php
    $entRows = $form['enterprise'];
    $gaps    = collect($entRows)->filter(fn ($r) => filled($r['gap']));
@endphp
<section class="section-card" id="g2-enterprise">
    <div class="section-card-head">
        <span class="section-no">1</span>
        <div class="me-auto">
            <h5>Enterprise due diligence</h5>
            <div class="small text-muted">{{ $DD::AREAS['enterprise'][2] }}</div>
        </div>
        @include('expression-of-interest.due-diligence.partials.area-pill', ['pct' => $progress['enterprise']])
    </div>

    <p class="small text-muted">
        Proportionate to the size, age and complexity of the enterprise. Test whether the information is internally consistent —
        not simply whether documents exist.
    </p>

    <div class="table-responsive">
        <table class="table table-modern align-middle mb-0 dd-table">
            <thead>
                <tr><th style="width: 20%">Area</th><th style="width: 24%">Evidence reviewed</th><th style="width: 16%">Status</th><th>Finding</th><th style="width: 13%">Gap</th></tr>
            </thead>
            <tbody>
                @foreach ($DD::ENTERPRISE_AREAS as $key => [$aLabel, $aFocus, $aEvidence])
                    @php $row = $entRows[$key]; @endphp
                    <tr wire:key="ent-{{ $key }}">
                        <td>
                            <div class="fw-semibold text-navy small">{{ $aLabel }}</div>
                            <div class="text-muted dd-hint">{{ $aFocus }}</div>
                        </td>
                        <td>
                            <textarea rows="2" class="form-control form-control-sm" wire:model.blur="form.enterprise.{{ $key }}.evidence"
                                      placeholder="{{ $aEvidence }}" @disabled(! $canEdit)></textarea>
                        </td>
                        <td>
                            @include('expression-of-interest.due-diligence.partials.status-select', ['model' => "form.enterprise.$key.status", 'value' => $row['status']])
                        </td>
                        <td>
                            <textarea rows="2" class="form-control form-control-sm" wire:model.blur="form.enterprise.{{ $key }}.finding"
                                      placeholder="Finding" @disabled(! $canEdit)></textarea>
                        </td>
                        <td>
                            <select class="form-select form-select-sm" wire:model.live="form.enterprise.{{ $key }}.gap" @disabled(! $canEdit)>
                                <option value="">None</option>
                                @foreach ($DD::GAP_TYPES as $gk => $gl)
                                    <option value="{{ $gk }}">{{ $gl }}</option>
                                @endforeach
                            </select>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="row g-3 mt-2">
        <div class="col-md-7">
            <label class="form-label small fw-semibold" for="ent_consistency"><i class="bi bi-arrow-left-right me-1"></i> Consistency check</label>
            <textarea id="ent_consistency" rows="3" class="form-control form-control-sm" wire:model.blur="form.enterprise_consistency" @disabled(! $canEdit)
                      placeholder="e.g. Reported revenue vs bank deposits and customer invoices — consistent / inconsistent because…"></textarea>
        </div>
        <div class="col-md-5">
            <div class="callout {{ $gaps->where('gap', 'substantive')->isNotEmpty() ? 'callout-warning' : '' }} small h-100">
                <div class="fw-semibold mb-1"><i class="bi bi-tools me-1"></i> Gaps identified</div>
                @forelse ($gaps as $gKey => $g)
                    <div class="mb-1"><span class="pill pill-muted me-1">{{ $DD::GAP_TYPES[$g['gap']] }}</span> {{ $DD::ENTERPRISE_AREAS[$gKey][0] }}</div>
                @empty
                    <span class="text-muted">None recorded.</span>
                @endforelse
                <div class="text-muted mt-1 dd-hint">Administrative = can be corrected · Substantive = affects credibility or viability.</div>
            </div>
        </div>
    </div>
</section>