<?php

use Livewire\Component;

/*
| Compose Email · ready-made messages (App\Support\EmailTemplates).
| Clicking one sends "use-template" to the composer, which fills the subject and message.
*/
new class extends Component
{
};
?>

<div class="ce-card p-3 mb-3">
    <span class="ce-label d-block mb-2"><i class="bi bi-file-earmark-text me-1"></i> Templates</span>
    <p class="small text-muted mb-2">Click one to fill the subject and message. Text in [square brackets] is for you to complete.</p>
    @foreach (\App\Support\EmailTemplates::all() as $key => [$title, $hint])
        <div class="template-item" wire:key="tpl-{{ $key }}" role="button" tabindex="0"
             wire:click="$dispatch('use-template', { key: '{{ $key }}' })"
             wire:confirm="Replace the subject and message with “{{ $title }}”?">
            <div class="fw-semibold small text-navy">{{ $title }}</div>
            <div class="small text-muted">{{ $hint }}</div>
        </div>
    @endforeach
</div>