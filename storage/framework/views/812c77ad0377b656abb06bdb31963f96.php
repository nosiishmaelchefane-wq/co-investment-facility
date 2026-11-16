<?php
use App\Support\UserAgent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
?>

<div>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->guard()->guest()): ?>
        
    <?php else: ?>
<div class="as-card p-4">
    <div class="as-section-title">Signed-in devices</div>
    <p class="small text-muted mb-3">Where your account is signed in right now. Sign out anything you don't recognise and change your password.</p>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! $this->supported): ?>
        <div class="callout callout-warning small mb-0">
            <i class="bi bi-exclamation-triangle me-1"></i>
            Device list isn't available: sessions are not stored in the database. Set <code>SESSION_DRIVER=database</code> in <code>.env</code>
            (run <code>php artisan session:table</code> and <code>php artisan migrate</code> if the table doesn't exist).
        </div>
    <?php else: ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $this->sessions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
            <div class="as-row d-flex align-items-center gap-3 py-3" <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'sess-'.e($s->id).''; ?>wire:key="sess-<?php echo e($s->id); ?>">
                <span class="device-icon"><i class="bi <?php echo e($s->device['icon']); ?>"></i></span>
                <div class="me-auto">
                    <div class="fw-semibold small text-navy">
                        <?php echo e($s->device['label']); ?>

                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($s->current): ?> <span class="badge text-bg-success ms-1">This device</span> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                    <div class="small text-muted" title="Last active <?php echo e($s->active->format('d M Y H:i')); ?>">
                        <?php echo e($s->ip); ?> · <?php echo e($s->current ? 'active now' : $s->active->diffForHumans()); ?>

                    </div>
                </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($s->current)): ?>
                    <button type="button" class="btn btn-sm btn-outline-danger" wire:click="signOut('<?php echo e($s->id); ?>')" wire:confirm="Sign out <?php echo e($s->device['label']); ?>?">Sign out</button>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            <p class="small text-muted mb-0">No devices found.</p>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->sessions->where('current', false)->isNotEmpty() || $confirming): ?>
            <div class="mt-3">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! $confirming): ?>
                    <div class="d-flex justify-content-end">
                        <button type="button" class="btn btn-outline-danger" wire:click="$set('confirming', true)"><i class="bi bi-box-arrow-right me-1"></i> Sign out all other devices</button>
                    </div>
                <?php else: ?>
                    <form class="p-3 rounded-3" style="background: #fff7ed; border: 1px solid #fed7aa;" wire:submit="signOutOthers">
                        <label class="form-label small fw-semibold" for="ss-password">Enter your password to sign out everywhere else</label>
                        <div class="d-flex flex-wrap gap-2">
                            <input type="password" class="form-control <?php $__errorArgs = ['password'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" style="max-width: 280px;" id="ss-password" wire:model="password" autocomplete="current-password" autofocus>
                            <button type="submit" class="btn btn-danger" wire:loading.attr="disabled" wire:target="signOutOthers">
                                <span wire:loading.remove wire:target="signOutOthers">Sign out others</span>
                                <span wire:loading wire:target="signOutOthers"><span class="spinner-border spinner-border-sm"></span></span>
                            </button>
                            <button type="button" class="btn btn-outline-secondary" wire:click="$set('confirming', false)">Cancel</button>
                        </div>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['password'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-danger small mt-1"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </form>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div><?php /**PATH C:\laragon\www\co-investment-facility\storage\framework\views/livewire/views/38fbc68d.blade.php ENDPATH**/ ?>