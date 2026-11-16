<?php
use App\Notifications\AccountSecurityChanged;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Livewire\Component;
?>

<div>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->guard()->guest()): ?>
        
    <?php else: ?>
<?php
    $s      = $this->strength();
    $labels = ['', 'Weak', 'Fair', 'Good', 'Strong'];
    $colors = ['', 'bg-danger', 'bg-warning', 'bg-info', 'bg-success'];
?>

<form class="as-card p-4" wire:submit="save">
    <div class="as-section-title">Password</div>
    <p class="small text-muted mb-4">Use at least 8 characters with letters and numbers. You'll stay signed in on this device.</p>

    <div class="row g-3" style="max-width: 520px;">
        <div class="col-12">
            <label class="form-label small" for="pw-current">Current password <span class="text-danger">*</span></label>
            <input type="password" class="form-control <?php $__errorArgs = ['current_password'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" id="pw-current" wire:model="current_password" autocomplete="current-password">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['current_password'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-danger small mt-1"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
        <div class="col-12">
            <label class="form-label small" for="pw-new">New password <span class="text-danger">*</span></label>
            <input type="password" class="form-control <?php $__errorArgs = ['password'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" id="pw-new" wire:model.live.debounce.300ms="password" autocomplete="new-password">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($s): ?>
                <div class="progress mt-2" style="height: 5px;"><div class="progress-bar <?php echo e($colors[$s]); ?>" style="width: <?php echo e($s * 25); ?>%"></div></div>
                <div class="small text-muted mt-1">Strength: <?php echo e($labels[$s]); ?></div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['password'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-danger small mt-1"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
        <div class="col-12">
            <label class="form-label small" for="pw-confirm">Confirm new password <span class="text-danger">*</span></label>
            <input type="password" class="form-control" id="pw-confirm" wire:model="password_confirmation" autocomplete="new-password">
        </div>
        <div class="col-12">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="pw-logout" wire:model="logoutOthers">
                <label class="form-check-label small" for="pw-logout">Sign me out on all other devices</label>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-end gap-2 mt-4">
        <button type="submit" class="btn btn-cta" wire:loading.attr="disabled" wire:target="save">
            <span wire:loading.remove wire:target="save"><i class="bi bi-key me-1"></i> Update password</span>
            <span wire:loading wire:target="save"><span class="spinner-border spinner-border-sm me-1"></span> Updating…</span>
        </button>
    </div>
</form>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div><?php /**PATH C:\laragon\www\co-investment-facility\storage\framework\views/livewire/views/9cc63327.blade.php ENDPATH**/ ?>