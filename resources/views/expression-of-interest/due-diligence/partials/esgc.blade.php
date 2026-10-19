{{-- G2 · 5 ESGC assessment + ESAP — included from ⚡due-deligence-check --}}
<section class="section-card" id="g2-esgc">
    <div class="section-card-head">
        <span class="section-no">5</span>
        <div class="me-auto">
            <h5>ESGC assessment</h5>
            <div class="small text-muted">{{ $DD::AREAS['esgc'][2] }}</div>
        </div>
        @include('expression-of-interest.due-diligence.partials.area-pill', ['pct' => $progress['esgc']])
    </div>

    @foreach ($DD::ESGC as $key => [$dLabel, $dIcon])
        @php $e = $form['esgc'][$key]; @endphp
        <div class="file-row align-items-start" wire:key="esgc-{{ $key }}">
            <span class="file-row-icon"><i class="bi {{ $dIcon }}"></i></span>
            <div class="flex-grow-1">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                    <span class="fw-semibold text-navy small me-auto">{{ $dLabel }}</span>
                    <select class="form-select form-select-sm rating-select is-{{ $e['rating'] ?: 'none' }}" style="width: auto;" wire:model.live="form.esgc.{{ $key }}.rating" @disabled(! $canEdit)>
                        <option value="">Risk rating…</option>
                        @foreach ($DD::RATINGS as $rk => $rl) <option value="{{ $rk }}">{{ $rl }} risk</option> @endforeach
                    </select>
                </div>
                <textarea rows="2" class="form-control form-control-sm" wire:model.blur="form.esgc.{{ $key }}.note" @disabled(! $canEdit)
                          placeholder="Findings for {{ strtolower($dLabel) }} considerations"></textarea>
            </div>
        </div>
    @endforeach

    <div class="d-flex flex-wrap align-items-center gap-2 mt-3">
        <label class="small fw-semibold text-navy me-2" for="esgc_cat">ESGC risk classification</label>
        <select id="esgc_cat" class="form-select form-select-sm rating-select is-{{ $form['esgc_category'] ?: 'none' }}" style="width: auto;" wire:model.live="form.esgc_category" @disabled(! $canEdit)>
            <option value="">Select…</option>
            @foreach ($DD::RATINGS as $rk => $rl) <option value="{{ $rk }}">{{ $rl }}</option> @endforeach
        </select>
        @php $highest = collect($form['esgc'])->pluck('rating')->filter()->sortBy(fn ($r) => array_search($r, array_keys($DD::RATINGS)))->last(); @endphp
        @if ($highest && $form['esgc_category'] && array_search($form['esgc_category'], array_keys($DD::RATINGS)) < array_search($highest, array_keys($DD::RATINGS)))
            <span class="small text-warning"><i class="bi bi-exclamation-triangle me-1"></i> Lower than the highest dimension rating ({{ $DD::RATINGS[$highest] }}).</span>
        @endif
    </div>

    {{-- ESAP --}}
    <div class="g2-sub mt-4 mb-0">
        <div class="g2-sub-head"><i class="bi bi-list-task me-1"></i> Environmental &amp; social action plan (ESAP)</div>
        <div class="table-responsive">
            <table class="table table-modern align-middle mb-2 dd-table">
                <thead><tr><th>Action</th><th style="width: 15%">Dimension</th><th style="width: 18%">Treatment</th><th style="width: 18%">Timing</th><th style="width: 12%">Status</th>@if ($canEdit)<th></th>@endif</tr></thead>
                <tbody>
                    @forelse ($esap as $i => $a)
                        <tr wire:key="esap-{{ $a['id'] ?? 'n'.$i }}">
                            <td><input type="text" class="form-control form-control-sm" wire:model.blur="esap.{{ $i }}.action" maxlength="250" placeholder="Action" @disabled(! $canEdit)></td>
                            <td>
                                <select class="form-select form-select-sm" wire:model.live="esap.{{ $i }}.dimension" @disabled(! $canEdit)>
                                    <option value="">—</option>
                                    @foreach ($DD::ESGC as $dk => [$dl]) <option value="{{ $dk }}">{{ $dl }}</option> @endforeach
                                </select>
                            </td>
                            <td>
                                <select class="form-select form-select-sm" wire:model.live="esap.{{ $i }}.treatment" @disabled(! $canEdit)>
                                    @foreach (\App\Models\EsapAction::TREATMENTS as $tk => $tl) <option value="{{ $tk }}">{{ $tl }}</option> @endforeach
                                </select>
                            </td>
                            <td><input type="text" class="form-control form-control-sm" wire:model.blur="esap.{{ $i }}.timing" maxlength="250" placeholder="e.g. Within 6 months" @disabled(! $canEdit)></td>
                            <td>
                                <select class="form-select form-select-sm" wire:model.live="esap.{{ $i }}.status" @disabled(! $canEdit)>
                                    @foreach (\App\Models\EsapAction::STATUSES as $sk => $sl) <option value="{{ $sk }}">{{ $sl }}</option> @endforeach
                                </select>
                            </td>
                            @if ($canEdit)
                                <td><button type="button" class="btn btn-icon btn-icon-danger" wire:click="removeRow('esap', {{ $i }})" title="Remove"><i class="bi bi-x-lg"></i></button></td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="6" class="small text-muted">No ESAP actions.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($canEdit)
            <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="addRow('esap')"><i class="bi bi-plus-lg me-1"></i> Add action</button>
        @endif
    </div>
</section>