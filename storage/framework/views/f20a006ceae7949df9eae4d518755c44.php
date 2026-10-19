<?php
use App\Models\User;
use App\Notifications\LoginOtpCode;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
?>

<div>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($step === 'otp'): ?>
        
        <div class="text-center mb-3"><i class="bi bi-shield-lock text-cif-primary" style="font-size: 2.75rem;"></i></div>
        <h2 class="h3 fw-bold text-cif-navy mb-1 text-center">Enter your sign-in code</h2>
        <p class="text-secondary small mb-4 text-center">We sent a 6-digit code to <strong><?php echo e($maskedEmail); ?></strong>. It expires in 10 minutes.</p>

        <form wire:submit="verifyOtp" novalidate>
            <div class="mb-3">
                <label for="login_otp" class="form-label">Code</label>
                <input id="login_otp" type="text" wire:model="otp" inputmode="numeric" pattern="[0-9]*" maxlength="6" autocomplete="one-time-code" autofocus
                       placeholder="••••••"
                       class="form-control form-control-lg text-center fw-bold <?php $__errorArgs = ['otp'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" style="letter-spacing: .5em;">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['otp'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-danger small mt-1"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($otpMessage): ?>
                <div class="alert alert-info small py-2"><?php echo e($otpMessage); ?></div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <button type="submit" class="btn btn-cif-primary btn-lg w-100" wire:loading.attr="disabled" wire:target="verifyOtp">
                <span wire:loading.remove wire:target="verifyOtp">Verify &amp; sign in <i class="bi bi-arrow-right ms-1"></i></span>
                <span wire:loading wire:target="verifyOtp"><span class="spinner-border spinner-border-sm me-2"></span>Checking…</span>
            </button>
        </form>

        
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->canSkipOtp()): ?>
            <button type="button" class="btn btn-outline-warning w-100 mt-2" wire:click="skipOtp" wire:loading.attr="disabled" wire:target="skipOtp">
                <span wire:loading.remove wire:target="skipOtp"><i class="bi bi-skip-forward me-1"></i> Skip code for now</span>
                <span wire:loading wire:target="skipOtp"><span class="spinner-border spinner-border-sm me-1"></span> Signing in…</span>
            </button>
            <p class="text-center text-muted mt-1 mb-0" style="font-size: .75rem;">Temporary — the code will be required again later.</p>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mt-3 small">
            <button type="button" class="btn btn-link btn-sm text-secondary text-decoration-none px-0" wire:click="cancelOtp"><i class="bi bi-arrow-left me-1"></i> Use a different account</button>
            <button type="button" class="btn btn-link btn-sm fw-semibold text-cif-primary text-decoration-none px-0" wire:click="resendOtp" wire:loading.attr="disabled" wire:target="resendOtp">
                <span wire:loading.remove wire:target="resendOtp">Resend code</span>
                <span wire:loading wire:target="resendOtp"><span class="spinner-border spinner-border-sm me-1"></span> Sending…</span>
            </button>
        </div>
        <p class="text-center text-muted mt-3 mb-0" style="font-size: .75rem;">Can't find it? Check your spam or junk folder.</p>
    <?php else: ?>
    
    <h2 class="h3 fw-bold text-cif-navy mb-1">Welcome back</h2>
    <p class="text-secondary small mb-4">Access your transactions, reviews and approvals.</p>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('status')): ?>
        <div class="alert alert-success small py-2"><i class="bi bi-check-circle me-1"></i> <?php echo e(session('status')); ?></div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('verification_error')): ?>
        <div class="alert alert-warning small py-2"><i class="bi bi-exclamation-triangle me-1"></i> <?php echo e(session('verification_error')); ?></div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <form wire:submit="login" novalidate>
        
        <div class="mb-3">
            <label for="login_email" class="form-label">Email address</label>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                <input id="login_email" type="email" wire:model="email" autocomplete="username"
                       placeholder="you@company.co.ls"
                       class="form-control <?php $__errorArgs = ['email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>">
            </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-danger small mt-1"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($unverifiedEmail): ?>
                <div class="mt-2 small">
                    <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="resendVerification" wire:loading.attr="disabled" wire:target="resendVerification">
                        <span wire:loading.remove wire:target="resendVerification"><i class="bi bi-envelope me-1"></i> Resend verification e-mail</span>
                        <span wire:loading wire:target="resendVerification"><span class="spinner-border spinner-border-sm me-1"></span> Sending…</span>
                    </button>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($resendMessage): ?> <div class="text-secondary mt-1"><?php echo e($resendMessage); ?></div> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>

        
        <div class="mb-3">
            <div class="d-flex justify-content-between align-items-center">
                <label for="login_password" class="form-label">Password</label>
                <a href="<?php echo e(\Illuminate\Support\Facades\Route::has('password.request') ? route('password.request') : url('/forgot-password')); ?>" class="small fw-semibold text-cif-primary text-decoration-none mb-1">Forgot password?</a>
            </div>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                <input id="login_password" type="password" wire:model="password" autocomplete="current-password"
                       placeholder="••••••••"
                       class="form-control has-toggle <?php $__errorArgs = ['password'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>">
                <button type="button" class="btn btn-toggle" data-toggle-password="login_password" aria-label="Show password">
                    <i class="bi bi-eye"></i>
                </button>
            </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['password'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-danger small mt-1"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>

        
        <div class="form-check mb-4">
            <input class="form-check-input" type="checkbox" id="remember" wire:model="remember">
            <label class="form-check-label small text-secondary" for="remember">Keep me signed in</label>
        </div>

        <button type="submit" class="btn btn-cif-primary btn-lg w-100" wire:loading.attr="disabled" wire:target="login">
            <span wire:loading.remove wire:target="login"><?php echo e($this->otpEnabled() ? 'Continue' : 'Sign in'); ?> <i class="bi bi-arrow-right ms-1"></i></span>
            <span wire:loading wire:target="login"><span class="spinner-border spinner-border-sm me-2"></span>Checking…</span>
        </button>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->otpEnabled()): ?>
            <p class="text-center text-muted small mt-2 mb-0"><i class="bi bi-shield-lock me-1"></i> We'll e-mail you a code to finish signing in.</p>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </form>

    <p class="text-center small text-secondary mt-4 mb-2">
        New to the Facility?
        <button type="button" class="btn-link-cif accent" data-auth-show="register">Register your enterprise</button>
    </p>
    <p class="text-center text-muted mb-0" style="font-size: .75rem;">
        Committee, PMU and approver accounts are created by the System Administrator.
    </p>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div><?php /**PATH C:\laragon\www\co-investment-facility\storage\framework\views/livewire/views/207254df.blade.php ENDPATH**/ ?>