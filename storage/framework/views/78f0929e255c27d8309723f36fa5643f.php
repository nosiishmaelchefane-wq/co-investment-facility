<?php
use App\Notifications\AccountSecurityChanged;
use Illuminate\Support\Str;
use Livewire\Component;
?>

<div>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->guard()->guest()): ?>
        
    <?php else: ?>
<?php
    $platformOn = (bool) config('cif.login.otp_enabled', true);
    $changedAt  = auth()->user()?->two_factor_changed_at;
?>

<div class="as-card p-4">
    <div class="as-section-title">Two-step sign-in</div>
    <p class="small text-muted mb-4">When it is on, after your password we e-mail you a 6-digit code that you enter to finish signing in.</p>

    <?php $effective = $platformOn && $enabled; ?>
    <div class="security-state <?php echo e($effective ? 'is-on' : 'is-off'); ?> d-flex align-items-start gap-3 mb-3">
        <i class="bi <?php echo e($effective ? 'bi-shield-check text-success' : 'bi-shield-exclamation text-warning'); ?> fs-3"></i>
        <div class="me-auto">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($effective): ?>
                <div class="fw-semibold">Two-step sign-in is <span class="text-success">on</span></div>
                <div class="small text-muted">You'll be asked for a code sent to <?php echo e($this->masked()); ?> each time you sign in.<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($changedAt): ?> Changed <?php echo e($changedAt->format('d M Y')); ?>.<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
            <?php elseif(! $platformOn): ?>
                <div class="fw-semibold">Two-step sign-in is <span class="text-warning">switched off for everyone</span></div>
                <div class="small text-muted">The administrator has turned it off for the whole platform, so no code is asked for right now. Your choice below applies once it is switched back on.</div>
            <?php else: ?>
                <div class="fw-semibold">Two-step sign-in is <span class="text-warning">off</span></div>
                <div class="small text-muted">Anyone with your password can sign in. We recommend turning it back on.<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($changedAt): ?> Changed <?php echo e($changedAt->format('d M Y')); ?>.<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    </div>

    <div class="as-row d-flex align-items-center gap-3 py-3">
        <div class="me-auto">
            <div class="fw-semibold small text-navy">Ask for an e-mailed code when I sign in</div>
            <div class="small text-muted">Turning this off makes your account easier to get into — for you and for anyone else.</div>
        </div>
        <div class="form-check form-switch form-switch-lg mb-0">
            
            <input class="form-check-input" type="checkbox" role="switch" id="tf-switch" aria-label="Two-step sign-in"
                   <?php if($enabled): echo 'checked'; endif; ?> wire:click.prevent="ask" <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'tf-'.e($enabled ? 'on' : 'off').''; ?>wire:key="tf-<?php echo e($enabled ? 'on' : 'off'); ?>">
        </div>
    </div>


    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($pending !== null): ?>
        <div class="modal d-block" tabindex="-1" role="dialog" aria-modal="true" style="background: rgba(13, 26, 58, .45);" wire:keydown.escape.window="cancel">
            <div class="modal-dialog modal-dialog-centered">
                <form class="modal-content" wire:submit="confirm">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-shield-lock me-1"></i> Turn two-step sign-in <?php echo e($pending ? 'on' : 'off'); ?></h5>
                        <button type="button" class="btn-close" wire:click="cancel" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="small mb-3">To change how you sign in, confirm it's you by entering your password.</p>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! $pending): ?>
                            <div class="callout callout-warning small mb-3">
                                <i class="bi bi-exclamation-triangle me-1"></i> With two-step sign-in <strong>off</strong>, only your password protects your account.
                            </div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <label class="form-label small" for="tf-password">Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control <?php $__errorArgs = ['password'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" id="tf-password" wire:model="password" autocomplete="current-password" autofocus>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['password'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-danger small mt-1"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" wire:click="cancel">Cancel</button>
                        <button type="submit" class="btn <?php echo e($pending ? 'btn-cta' : 'btn-warning'); ?>" wire:loading.attr="disabled" wire:target="confirm">
                            <span wire:loading.remove wire:target="confirm"><i class="bi bi-check-lg me-1"></i> Turn <?php echo e($pending ? 'on' : 'off'); ?></span>
                            <span wire:loading wire:target="confirm"><span class="spinner-border spinner-border-sm me-1"></span> Saving…</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div><?php /**PATH C:\laragon\www\co-investment-facility\storage\framework\views/livewire/views/c13adb74.blade.php ENDPATH**/ ?>