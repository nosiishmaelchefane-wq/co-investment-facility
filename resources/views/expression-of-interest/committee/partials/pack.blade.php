{{-- G3 · Committee information pack (§3.4.1) — built from the Gate 2 assessment. Included from ⚡committee-review --}}
@php
    $g2Url   = fn ($anchor) => route('expression-of-interest.show', $eoi->id).'?tab=g2'.($anchor ? '#'.$anchor : '');
    $openG2  = $dd ? $dd->conditions->where('status', 'open')->count() : 0;
    $present = [
        'tss'         => (bool) $dd,
        'iam'         => (bool) $dd?->iam_path,
        'enterprise'  => (bool) $dd,
        'investor'    => (bool) $dd,
        'transaction' => (bool) $dd,
        'financial'   => (bool) $dd,
        'esgc'        => (bool) $dd,
        'risk'        => (bool) $dd,
        'calculation' => (bool) $dd,
        'conditions'  => (bool) $dd,
    ];
    $included = count(array_filter($present));
@endphp
<section class="section-card">
    <div class="section-card-head">
        <span class="section-no"><i class="bi bi-folder2-open"></i></span>
        <div class="me-auto">
            <h5>Committee information pack</h5>
            <div class="small text-muted">From the Gate 2 assessment @if ($dd) (round {{ $dd->round }}, decided {{ $dd->decided_at->format('d M Y') }}) @endif. The Transaction Summary Sheet is the front page.</div>
        </div>
        <span class="pill {{ $included === count($present) ? 'pill-ok' : 'pill-warn' }}">{{ $included }} / {{ count($present) }} included</span>
    </div>

    @if (! $dd)
        <div class="callout callout-warning small mb-0"><i class="bi bi-exclamation-triangle me-1"></i> No completed Gate 2 assessment (Proceed) was found for this transaction.</div>
    @else
        @foreach ($CR::PACK as $key => [$doc, $purpose, $icon, $anchor])
            <div class="file-row" wire:key="pack-{{ $key }}">
                <span class="file-row-icon"><i class="bi {{ $icon }}"></i></span>
                <div class="flex-grow-1 small overflow-hidden">
                    <div class="fw-semibold text-navy">
                        {{ $doc }}
                        @if (in_array($key, ['tss', 'iam'], true)) <span class="pill pill-muted ms-1">Key document</span> @endif
                    </div>
                    <div class="text-muted text-truncate">
                        {{ $purpose }}
                        @if ($key === 'iam' && $dd->iam_name) · {{ $dd->iam_name }} @endif
                        @if ($key === 'conditions') · {{ $openG2 }} open @endif
                    </div>
                </div>
                @if ($present[$key])
                    <span class="pill pill-ok"><i class="bi bi-check-lg"></i> Included</span>
                    @if ($key === 'iam')
                        <a href="{{ route('due-diligence.memorandum', $dd) }}" class="btn btn-icon" title="Download"><i class="bi bi-download"></i></a>
                    @else
                        <a href="{{ $g2Url($anchor) }}" target="_blank" class="btn btn-icon" title="Open in Gate 2"><i class="bi bi-box-arrow-up-right"></i></a>
                    @endif
                @else
                    <span class="pill pill-danger">Missing</span>
                @endif
            </div>
        @endforeach
    @endif
</section>