{{-- G3 · What the applicant submitted — key facts (EOI + enterprise profile) and their documents. Included from ⚡committee-review --}}
@php
    $docCount    = collect($submission['docs'])->flatten(1)->count();
    $docUploaded = collect($submission['docs'])->flatten(1)->filter(fn ($d) => $d[1])->count();
    $docMissing  = collect($submission['docs'])->flatten(1)->filter(fn ($d) => ! $d[1] && $d[2])->count();
@endphp
<section class="section-card">
    <div class="section-card-head">
        <span class="section-no"><i class="bi bi-person-vcard"></i></span>
        <div class="me-auto">
            <h5>Application &amp; enterprise documents</h5>
            <div class="small text-muted">What the applicant submitted in the EOI and the enterprise profile — for reference while reading the pack.</div>
        </div>
        @if ($profile)
            <span class="pill {{ $docMissing ? 'pill-warn' : 'pill-ok' }}">{{ $docUploaded }} / {{ $docCount }} documents</span>
        @endif
    </div>

    <details class="evidence">
        <summary><i class="bi bi-card-list me-1"></i> Key facts</summary>
        <div class="row g-0">
            @foreach ($submission['facts'] as $group => $rows)
                <div class="col-lg-6">
                    <div class="small fw-semibold text-navy px-3 pt-3">{{ $group }}</div>
                    <dl class="evidence-grid">
                        @foreach ($rows as [$fLabel, $fValue, $fSource])
                            <dt><span class="src-tag {{ $fSource === 'EOI' ? 'src-eoi' : 'src-profile' }}">{{ $fSource }}</span> {{ $fLabel }}</dt>
                            <dd>@if (blank($fValue)) <span class="text-muted fst-italic">Not provided</span> @else <span class="evidence-value">{{ $fValue }}</span> @endif</dd>
                        @endforeach
                    </dl>
                </div>
            @endforeach
        </div>
    </details>

    @if (! $profile)
        <div class="callout callout-warning small mt-3 mb-0"><i class="bi bi-exclamation-triangle me-1"></i> The applicant has no enterprise profile — only the EOI answers above are available.</div>
    @else
        <div class="row g-3 mt-1">
            @foreach ($submission['docs'] as $sectionTitle => $docs)
                <div class="col-md-6">
                    <div class="small fw-semibold text-navy mb-1">{{ $sectionTitle }}</div>
                    @foreach ($docs as [$dLabel, $doc, $dReq])
                        <div class="file-row mb-1" wire:key="sub-doc-{{ \Illuminate\Support\Str::slug($dLabel) }}">
                            <span class="file-row-icon"><i class="bi {{ $doc ? 'bi-file-earmark-check' : 'bi-file-earmark' }}"></i></span>
                            <div class="flex-grow-1 small overflow-hidden">
                                <div class="fw-semibold text-navy text-truncate">{{ $dLabel }}</div>
                                <div class="text-muted text-truncate">{{ $doc ? $doc->original_name.' · '.$doc->size_label : ($dReq ? 'Required — not uploaded' : 'Optional — not uploaded') }}</div>
                            </div>
                            @if ($doc)
                                <a href="{{ route('enterprise-documents.download', $doc) }}" class="btn btn-icon" title="Download"><i class="bi bi-download"></i></a>
                            @else
                                <span class="pill {{ $dReq ? 'pill-warn' : 'pill-muted' }}">{{ $dReq ? 'Missing' : 'Optional' }}</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>
        @canany(['eoi.view-all', 'users.manage'])
            <div class="small mt-2 text-end">
                <a href="{{ route('profile.index', $eoi->user_id) }}" target="_blank" class="text-decoration-none"><i class="bi bi-box-arrow-up-right me-1"></i> Open full enterprise profile</a>
            </div>
        @endcanany
    @endif
</section>