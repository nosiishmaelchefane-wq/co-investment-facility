<?php
use Livewire\Component;
use Livewire\Attributes\On;
?>

<div
    x-data="{
        show: <?php if ((object) ('show') instanceof \Livewire\WireDirective) : ?>window.Livewire.find('<?php echo e($__livewire->getId()); ?>').entangle('<?php echo e('show'->value()); ?>')<?php echo e('show'->hasModifier('live') ? '.live' : ''); ?><?php else : ?>window.Livewire.find('<?php echo e($__livewire->getId()); ?>').entangle('<?php echo e('show'); ?>')<?php endif; ?>,
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
        <div class="toast align-items-center text-bg-<?php echo e($type); ?> border-0 show" role="alert">
            <div class="d-flex">
                <div class="toast-body">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($type == 'success'): ?>
                        <i class="bi bi-check-circle-fill me-2"></i>
                    <?php elseif($type == 'error'): ?>
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <?php elseif($type == 'warning'): ?>
                        <i class="bi bi-exclamation-circle-fill me-2"></i>
                    <?php else: ?>
                        <i class="bi bi-info-circle-fill me-2"></i>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php echo e($message); ?>

                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" wire:click="hide"></button>
            </div>
        </div>
    </div>
</div><?php /**PATH C:\laragon\www\co-investment-facility\storage\framework\views/livewire/views/ef87e7fb.blade.php ENDPATH**/ ?>