{{-- G2 · 7 Investment appraisal — Transaction Summary Sheet + IAM — included from ⚡due-deligence-check --}}
@php
    $t        = $form['transaction'];
    $uofShare = collect($t['use_of_funds'])
        ->map(fn ($r) => [$DD::USE_CATEGORIES[$r['category']] ?? $r['category'], (float) $r['private'] + (float) $r['grant']])
        ->filter(fn ($r) => $r[1] > 0)
        ->map(fn ($r) => $r[0].' '.($uof['total'] > 0 ? round($r[1] / $uof['total'] * 100) : 0).'%')
        ->implode(' · ');
    $investorLine = collect($form['investors'])->filter(fn ($i) => filled($i['name']))
        ->map(fn ($i) => $i['name'].(filled($i['type']) ? ' ('.$i['type'].', '.strtolower($DD::INVESTOR_ROLES[$i['role']] ?? '').')' : ''))
        ->implode(' · ');
    $riskHint = collect($form['risks'])->whereIn('rating', ['medium', 'high'])->pluck('issue')->filter()->take(4)->implode(' · ');
@endphp
<section class="section-card" id="g2-appraisal">
    <div class="section-card-head">
        <span class="section-no">7</span>
        <div class="me-auto">
            <h5>Investment appraisal</h5>
            <div class="small text-muted">{{ $DD::AREAS['appraisal'][2] }}</div>
        </div>
        @include('expression-of-interest.due-diligence.partials.area-pill', ['pct' => $progress['appraisal']])
    </div>

    <dt class="detail-label mb-2">Transaction Summary Sheet</dt>
    <div class="table-responsive mb-3">
        <table class="table table-modern align-middle mb-0 tss-table">
            <tbody>
                <tr><th>Enterprise summary</th><td>
                    <textarea rows="2" class="form-control form-control-sm" wire:model.blur="form.tss.enterprise_summary" @disabled(! $canEdit) placeholder="Business overview and sector"></textarea>
                </td></tr>
                <tr><th>Participating investor(s)</th><td>{{ $investorLine ?: '—' }}</td></tr>
                <tr><th>Total transaction value</th><td>{{ $m($validated['total']) }} <span class="small text-muted">· {{ $u($validated['total']) }}</span></td></tr>
                <tr><th>Private capital committed</th><td>{{ $m($validated['private']) }} <span class="small text-muted">· {{ $u($validated['private']) }}</span></td></tr>
                <tr><th>Co-investment requested</th><td>{{ $m($validated['grant']) }} <span class="small text-muted">· {{ $u($validated['grant']) }}</span></td></tr>
                <tr><th>Funding structure</th><td>{{ $validated['structure'] ?? '—' }}</td></tr>
                <tr><th>Use of funds</th><td>{{ $uofShare ?: '—' }}</td></tr>
                <tr><th>Expected outcomes</th><td>
                    <textarea rows="2" class="form-control form-control-sm" wire:model.blur="form.tss.expected_outcomes" @disabled(! $canEdit) placeholder="Revenue growth, jobs, exports, innovation…"></textarea>
                </td></tr>
                <tr><th>Current transaction stage</th><td>
                    @if ($t['stage']) <span class="pill pill-ok">{{ $DD::STAGES[$t['stage']][0] }}</span> @else — @endif
                </td></tr>
                <tr><th>Key risks</th><td>
                    <textarea rows="2" class="form-control form-control-sm" wire:model.blur="form.tss.key_risks" @disabled(! $canEdit)
                              placeholder="{{ $riskHint ?: 'Commercial, legal, operational and ESGC risks' }}"></textarea>
                </td></tr>
            </tbody>
        </table>
    </div>

    <dt class="detail-label mb-2">Investment Appraisal Memorandum</dt>
    @if ($dd?->iam_path)
        <div class="file-row">
            <span class="file-row-icon"><i class="bi {{ str_ends_with(strtolower($dd->iam_name), '.pdf') ? 'bi-file-earmark-pdf' : 'bi-file-earmark-word' }}"></i></span>
            <div class="flex-grow-1 small">
                <div class="fw-semibold text-navy">{{ $dd->iam_name }}</div>
                <div class="text-muted">Uploaded by {{ $dd->iamUploader?->name ?? '—' }} · {{ $dd->iam_uploaded_at?->format('d M Y H:i') }} · {{ number_format($dd->iam_size / 1024) }} KB</div>
            </div>
            <a href="{{ route('due-diligence.memorandum', $dd) }}" class="btn btn-icon" title="Download"><i class="bi bi-download"></i></a>
            @if ($canMemo)
                <label class="btn btn-sm btn-outline-secondary mb-0">
                    <span wire:loading.remove wire:target="iam"><i class="bi bi-upload me-1"></i> Replace</span>
                    <span wire:loading wire:target="iam"><span class="spinner-border spinner-border-sm me-1"></span> Uploading…</span>
                    <input type="file" class="d-none" wire:model="iam" accept=".pdf,.doc,.docx">
                </label>
            @endif
        </div>
    @elseif ($canMemo)
        <label class="file-row upload-drop mb-0">
            <span class="file-row-icon"><i class="bi bi-cloud-arrow-up"></i></span>
            <div class="flex-grow-1 small">
                <div class="fw-semibold text-navy" wire:loading.remove wire:target="iam">Upload the Investment Appraisal Memorandum</div>
                <div class="fw-semibold text-navy" wire:loading wire:target="iam"><span class="spinner-border spinner-border-sm me-1"></span> Uploading…</div>
                <div class="text-muted">PDF or Word, up to 20 MB</div>
            </div>
            <input type="file" class="d-none" wire:model="iam" accept=".pdf,.doc,.docx">
        </label>
    @else
        <div class="small text-muted">Not uploaded yet.</div>
    @endif
    @error('iam') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
</section>