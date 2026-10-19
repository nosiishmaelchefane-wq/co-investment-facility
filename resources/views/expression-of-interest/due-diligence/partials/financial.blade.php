{{-- G2 · 4 Financial & commercial appraisal — included from ⚡due-deligence-check --}}
<section class="section-card" id="g2-financial">
    <div class="section-card-head">
        <span class="section-no">4</span>
        <div class="me-auto">
            <h5>Financial &amp; commercial appraisal</h5>
            <div class="small text-muted">{{ $DD::AREAS['financial'][2] }}</div>
        </div>
        @include('expression-of-interest.due-diligence.partials.area-pill', ['pct' => $progress['financial']])
    </div>

    <div class="table-responsive">
        <table class="table table-modern align-middle mb-2 dd-table">
            <thead>
                <tr>
                    <th style="width: 16%">Year</th>
                    <th class="text-end">Revenue (M)</th>
                    <th class="text-end">Gross margin %</th>
                    <th class="text-end">Net profit (M)</th>
                    <th class="text-end">Debt / equity</th>
                    <th class="text-end">FT jobs</th>
                    <th class="text-center">Proj.</th>
                    @if ($canEdit)<th></th>@endif
                </tr>
            </thead>
            <tbody>
                @forelse ($form['financials'] as $i => $f)
                    <tr wire:key="fin-{{ $i }}" class="{{ $f['projected'] ? 'is-projected' : '' }}">
                        <td><input type="text" class="form-control form-control-sm" wire:model.blur="form.financials.{{ $i }}.year" maxlength="10" placeholder="2025" @disabled(! $canEdit)></td>
                        <td><input type="number" step="1000" class="form-control form-control-sm text-end" wire:model.blur="form.financials.{{ $i }}.revenue" @disabled(! $canEdit)></td>
                        <td><input type="number" step="0.1" class="form-control form-control-sm text-end" wire:model.blur="form.financials.{{ $i }}.gross_margin" @disabled(! $canEdit)></td>
                        <td><input type="number" step="1000" class="form-control form-control-sm text-end" wire:model.blur="form.financials.{{ $i }}.net_profit" @disabled(! $canEdit)></td>
                        <td><input type="number" step="0.01" min="0" class="form-control form-control-sm text-end" wire:model.blur="form.financials.{{ $i }}.debt_equity" @disabled(! $canEdit)></td>
                        <td><input type="number" step="1" min="0" class="form-control form-control-sm text-end" wire:model.blur="form.financials.{{ $i }}.jobs" @disabled(! $canEdit)></td>
                        <td class="text-center"><input type="checkbox" class="form-check-input" wire:model.live="form.financials.{{ $i }}.projected" @disabled(! $canEdit)></td>
                        @if ($canEdit)
                            <td><button type="button" class="btn btn-icon btn-icon-danger" wire:click="removeRow('financials', {{ $i }})" title="Remove"><i class="bi bi-x-lg"></i></button></td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="8" class="small text-muted">No financial data — add the last three years and any projections.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($canEdit)
        <button type="button" class="btn btn-sm btn-outline-secondary mb-3" wire:click="addRow('financials')"><i class="bi bi-plus-lg me-1"></i> Add year</button>
    @endif

    @php
        $hist = collect($form['financials'])->reject(fn ($r) => $r['projected'])->filter(fn ($r) => filled($r['revenue']))->sortBy('year')->values();
        $cagr = null;
        if ($hist->count() >= 2 && (float) $hist->first()['revenue'] > 0) {
            $years = max(1, (int) $hist->last()['year'] - (int) $hist->first()['year']);
            $cagr  = (pow((float) $hist->last()['revenue'] / (float) $hist->first()['revenue'], 1 / $years) - 1) * 100;
        }
    @endphp
    @if ($cagr !== null)
        <div class="small text-muted mb-3"><i class="bi bi-graph-up-arrow me-1"></i> Historical revenue growth: <strong class="text-navy">{{ number_format($cagr, 1) }}% a year</strong> ({{ $hist->first()['year'] }}–{{ $hist->last()['year'] }}).</div>
    @endif

    <div class="row g-3">
        <div class="col-md-6">
            <label class="detail-label" for="fin_assess">Financial assessment</label>
            <textarea id="fin_assess" rows="4" class="form-control form-control-sm" wire:model.blur="form.financial_assessment" @disabled(! $canEdit)
                      placeholder="Performance, trends, leverage, quality of records, reasonableness of projections."></textarea>
        </div>
        <div class="col-md-6">
            <label class="detail-label" for="com_assess">Commercial assessment</label>
            <textarea id="com_assess" rows="4" class="form-control form-control-sm" wire:model.blur="form.commercial_assessment" @disabled(! $canEdit)
                      placeholder="Market, customers, demand evidence, competitive position."></textarea>
        </div>
    </div>
</section>