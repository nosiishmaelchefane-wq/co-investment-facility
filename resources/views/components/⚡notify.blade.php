<?php

use Livewire\Component;
use Livewire\Attributes\On; 

new class extends Component
{
    public $show = false;
    public $type = 'success';
    public $message = '';

    #[On('notify')] 
    public function notify($type = '', $message = '')
    {
        $this->type = $type;
        $this->message = $message;
        $this->show = true;
    }

    public function hide()
    {
        $this->show = false;
    }
};
?>

<div
    x-data="{
        show: @entangle('show'),
        timer: null,
        startTimer() {
            clearTimeout(this.timer);
            this.timer = setTimeout(() => $wire.hide(), 10000);
        }
    }"
    x-init="$watch('show', val => { if (val) startTimer() })"
>
    <div
        x-show="show"
        class="position-fixed bottom-0 end-0 p-3"
        style="z-index: 9999; display: none;"
    >
        <div class="toast align-items-center text-bg-{{ $type }} border-0 show" role="alert">
            <div class="d-flex">
                <div class="toast-body">
                    @if($type == 'success')
                        <i class="bi bi-check-circle-fill me-2"></i>
                    @elseif($type == 'error')
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    @elseif($type == 'warning')
                        <i class="bi bi-exclamation-circle-fill me-2"></i>
                    @else
                        <i class="bi bi-info-circle-fill me-2"></i>
                    @endif
                    {{ $message }}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" wire:click="hide"></button>
            </div>
        </div>
    </div>
</div>