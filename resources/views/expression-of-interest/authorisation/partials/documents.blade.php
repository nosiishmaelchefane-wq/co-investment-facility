{{--
    G5 · list of authorisation documents (sent to the MD for review / signed by the PS). Included from ⚡authorise-application.
    Vars: $docs (Collection of AuthorisationDocument), $canRemove (bool), $empty (?string — text when there are none; null shows nothing)
--}}
@if ($docs->isEmpty())
    @if ($empty) <div class="small text-muted mb-2">{{ $empty }}</div> @endif
@else
    <div class="mb-2">
        @foreach ($docs as $d)
            <div class="file-row" wire:key="g5doc-{{ $d->id }}">
                <span class="file-row-icon"><i class="bi {{ $d->kind === \App\Models\AuthorisationDocument::KIND_PS_SIGNED ? 'bi-file-earmark-check' : 'bi-file-earmark-text' }}"></i></span>
                <div class="flex-grow-1 small overflow-hidden">
                    <a href="{{ route('gate4.document', $d) }}" class="fw-semibold text-navy text-decoration-none text-truncate d-block">{{ $d->name }}</a>
                    <div class="text-muted">{{ $d->size ? $d->sizeLabel().' · ' : '' }}added {{ $d->created_at?->format('d M Y') }}</div>
                </div>
                <a href="{{ route('gate4.document', $d) }}" class="btn btn-icon" title="Download"><i class="bi bi-download"></i></a>
                @if ($canRemove)
                    <button type="button" class="btn btn-icon btn-icon-danger" title="Remove" wire:click="removeDocument({{ $d->id }})" wire:confirm="Remove {{ $d->name }}?"><i class="bi bi-trash"></i></button>
                @endif
            </div>
        @endforeach
    </div>
@endif