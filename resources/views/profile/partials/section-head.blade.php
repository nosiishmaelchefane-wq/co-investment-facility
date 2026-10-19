{{--
    Section heading for the enterprise profile (used inside the profile-view Livewire component).
    Expects: $no, $title, $icon, $desc, $done, $step, $canEdit
--}}
<div class="section-card-head">
    <span class="section-no">{{ $no }}</span>
    <div class="me-auto">
        <h5><i class="bi {{ $icon }} me-1 text-faint"></i> {{ $title }}</h5>
        <div class="small text-muted">{{ $desc }}</div>
    </div>
    <span class="pill {{ $done ? 'pill-ok' : 'pill-warn' }}">
        <i class="bi {{ $done ? 'bi-check-circle' : 'bi-exclamation-circle' }}"></i> {{ $done ? 'Complete' : 'Incomplete' }}
    </span>
    @if ($canEdit ?? false)
        <button type="button" class="btn btn-icon" title="Edit this section" wire:click="$dispatch('edit-section', { step: {{ $step }} })">
            <i class="bi bi-pencil"></i>
        </button>
    @endif
</div>