{{-- G2 · 2 Investor due diligence (§3.3.2) — included from ⚡due-deligence-check --}}
@php $investors = $form['investors']; @endphp
<section class="section-card" id="g2-investor">
    <div class="section-card-head">
        <span class="section-no">2</span>
        <div class="me-auto">
            <h5>Investor due diligence</h5>
            <div class="small text-muted">{{ $DD::AREAS['investor'][2] }}</div>
        </div>
        @include('expression-of-interest.due-diligence.partials.area-pill', ['pct' => $progress['investor']])
    </div>

    {{-- Investors --}}
    <div class="row g-3 mb-3">
        @forelse ($investors as $i => $inv)
            <div class="col-md-6" wire:key="inv-{{ $i }}">
                <div class="border rounded-3 p-3 h-100">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="avatar-sm">{{ mb_strtoupper(mb_substr($inv['name'] ?: '?', 0, 1)) }}</span>
                        <input type="text" class="form-control form-control-sm fw-semibold" wire:model.blur="form.investors.{{ $i }}.name" maxlength="250"
                               placeholder="Investor name" @disabled(! $canEdit)>
                        @if ($canEdit)
                            <button type="button" class="btn btn-icon btn-icon-danger" title="Remove investor"
                                    wire:click="removeRow('investors', {{ $i }})" wire:confirm="Remove this investor?"><i class="bi bi-trash"></i></button>
                        @endif
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <input type="text" class="form-control form-control-sm" wire:model.blur="form.investors.{{ $i }}.type" maxlength="100"
                                   placeholder="Type (e.g. VC fund, angel)" @disabled(! $canEdit)>
                        </div>
                        <div class="col-6">
                            <input type="text" class="form-control form-control-sm" wire:model.blur="form.investors.{{ $i }}.country" maxlength="100"
                                   placeholder="Country" @disabled(! $canEdit)>
                        </div>
                        <div class="col-6">
                            <select class="form-select form-select-sm" wire:model.live="form.investors.{{ $i }}.role" @disabled(! $canEdit)>
                                @foreach ($DD::INVESTOR_ROLES as $rk => $rl) <option value="{{ $rk }}">{{ $rl }}</option> @endforeach
                            </select>
                        </div>
                        <div class="col-6">
                            <select class="form-select form-select-sm" wire:model.live="form.investors.{{ $i }}.route" @disabled(! $canEdit)>
                                @foreach ($DD::INVESTOR_ROUTES as $rk => [$rl]) <option value="{{ $rk }}">{{ $rl }} evidence</option> @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="small text-muted mt-2">{{ $DD::INVESTOR_ROUTES[$inv['route']][1] ?? '' }}</div>
                </div>
            </div>
        @empty
            <div class="col-12"><div class="callout callout-warning small mb-0"><i class="bi bi-exclamation-triangle me-1"></i> No investor recorded yet.</div></div>
        @endforelse
    </div>
    @if ($canEdit)
        <button type="button" class="btn btn-sm btn-outline-secondary mb-3" wire:click="addRow('investors')"><i class="bi bi-plus-lg me-1"></i> Add investor</button>
    @endif

    <div class="callout small mb-3">
        <i class="bi bi-info-circle me-1"></i> <strong>Evidence-equivalence, not document-equivalence.</strong>
        The absence of institutional structures is not, by itself, a negative finding.
    </div>

    <div class="table-responsive">
        <table class="table table-modern align-middle mb-0 dd-table">
            <thead>
                <tr>
                    <th style="width: 20%">Area</th>
                    @foreach ($investors as $inv)
                        <th>{{ $inv['name'] ?: 'Investor' }} <span class="text-muted fw-normal">· {{ $DD::INVESTOR_ROLES[$inv['role']] ?? '' }}</span></th>
                    @endforeach
                    <th style="width: 16%">Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($DD::INVESTOR_AREAS as $key => [$aLabel, $aFocus])
                    <tr wire:key="inv-area-{{ $key }}">
                        <td>
                            <div class="fw-semibold text-navy small">{{ $aLabel }}</div>
                            <div class="text-muted dd-hint">{{ $aFocus }}</div>
                        </td>
                        @foreach ($investors as $i => $inv)
                            <td>
                                <input type="text" class="form-control form-control-sm" wire:model.blur="form.investors.{{ $i }}.evidence.{{ $key }}" maxlength="250"
                                       placeholder="Evidence" @disabled(! $canEdit)>
                            </td>
                        @endforeach
                        <td>
                            @include('expression-of-interest.due-diligence.partials.status-select', ['model' => "form.investor_checks.$key.status", 'value' => $form['investor_checks'][$key]['status']])
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>