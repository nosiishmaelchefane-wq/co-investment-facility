{{--
    One upload row in the enterprise profile form (inside the profile-form Livewire component).
    Expects: $type, $label, $doc (EnterpriseDocument|null), $withDate (bool), $hint
    The file uploads as soon as it's picked (wire:model="uploads.<type>" → updatedUploads()).
--}}
<div class="file-row align-items-center flex-wrap" wire:key="upload-{{ $type }}">
    <span class="file-row-icon"><i class="bi {{ $doc ? 'bi-file-earmark-check' : 'bi-cloud-arrow-up' }}"></i></span>

    <div class="flex-grow-1 small" style="min-width: 180px;">
        <div class="fw-semibold text-navy">{{ $label }}</div>
        @if ($doc)
            <a href="{{ route('enterprise-documents.download', $doc) }}" class="text-muted text-decoration-none">
                <i class="bi bi-paperclip"></i> {{ \Illuminate\Support\Str::limit($doc->original_name, 40) }} · {{ $doc->size_label }}
            </a>
        @else
            <div class="text-muted">{{ $hint }}</div>
        @endif
        @error("uploads.$type") <div class="text-danger">{{ $message }}</div> @enderror
    </div>

    @if ($withDate)
        <input type="date" wire:model.blur="docDates.{{ $type }}" max="{{ now()->toDateString() }}"
               class="form-control form-control-sm" style="width: 150px;" title="{{ $hint }}">
    @endif

    <span wire:loading wire:target="uploads.{{ $type }}" class="small text-muted">
        <span class="spinner-border spinner-border-sm me-1"></span> Uploading…
    </span>

    <label class="btn btn-sm {{ $doc ? 'btn-outline-secondary' : 'btn-brand' }} mb-0" wire:loading.remove wire:target="uploads.{{ $type }}">
        <i class="bi {{ $doc ? 'bi-arrow-repeat' : 'bi-upload' }} me-1"></i>{{ $doc ? 'Replace' : 'Upload' }}
        <input type="file" class="d-none" wire:model="uploads.{{ $type }}" accept=".pdf,.jpg,.jpeg,.png">
    </label>

    @if ($doc)
        <button type="button" class="btn btn-icon btn-icon-danger" title="Remove"
                wire:click="removeDocument('{{ $type }}')" wire:confirm="Remove {{ $label }}?">
            <i class="bi bi-trash"></i>
        </button>
    @endif
</div>