
<?php
    $A          = \App\Models\Authorisation::class;
    $title      = $A::AUTHORITIES[$role][1];
    $sent       = (bool) $a?->sentForSigning($role);
    $signed     = (bool) $a?->hasSignedCopy($role);
    $reqPath    = $a?->{"{$role}_request_path"};
    $s          = $signing[$role] ?? [];
    $defaultDoc = $defaultDoc ?? null;
?>

<div class="border rounded-3 p-3 mb-3 bg-light">
    <div class="row g-3">
        
        <div class="col-lg-7">
            <div class="d-flex align-items-center gap-2 mb-2">
                <span class="pill <?php echo e($sent ? 'pill-ok' : ($signed ? 'pill-muted' : 'pill-warn')); ?>"><i class="bi <?php echo e($sent ? 'bi-envelope-check' : 'bi-envelope'); ?>"></i> 1</span>
                <span class="fw-semibold text-navy small">Send the Grant Agreement to the <?php echo e($title); ?> for signature</span>
            </div>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($sent): ?>
                <div class="small mb-2">
                    Sent to <strong><?php echo e($a->{"{$role}_signer_name"}); ?></strong> (<?php echo e($a->{"{$role}_signer_email"}); ?>)
                    on <?php echo e($a->{"{$role}_sent_at"}->format('d M Y')); ?>

                    <?php $sender = $role === 'md' ? $a->mdSender : $a->psSender; ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($sender): ?> by <?php echo e($sender->name); ?> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>.
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($reqPath): ?> <a href="<?php echo e(route('gate4.file', [$a, $role.'_request'])); ?>" class="text-decoration-none ms-1"><i class="bi bi-paperclip"></i> <?php echo e(\Illuminate\Support\Str::limit($a->{"{$role}_request_name"}, 40)); ?></a> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            <?php elseif(! $can): ?>
                <div class="small text-muted mb-2"><?php echo e($signed ? 'Sent outside the system.' : 'Not sent yet.'); ?></div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($can && ! $signed && ! $decidedRole): ?>
                <div class="row g-2">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold mb-1">Name <span class="req">*</span></label>
                        <input type="text" class="form-control form-control-sm <?php $__errorArgs = ['signing.'.$role.'.name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model="signing.<?php echo e($role); ?>.name" placeholder="<?php echo e($A::AUTHORITIES[$role][0]); ?>">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['signing.'.$role.'.name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold mb-1">E-mail <span class="req">*</span></label>
                        <input type="email" class="form-control form-control-sm <?php $__errorArgs = ['signing.'.$role.'.email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model="signing.<?php echo e($role); ?>.email">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['signing.'.$role.'.email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-semibold mb-1">Grant Agreement to be signed <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($reqPath || $defaultDoc)): ?> <span class="req">*</span> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></label>
                        <input type="file" class="form-control form-control-sm <?php $__errorArgs = ['requestFile.'.$role];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model="requestFile.<?php echo e($role); ?>" accept=".pdf,.docx,.doc">
                        <div class="form-text">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($reqPath): ?> Leave empty to keep the current document.
                            <?php elseif($defaultDoc): ?> Leave empty to send <strong><?php echo e($defaultDoc[2]); ?></strong> <?php echo e($role === 'md' ? '(the Draft Grant Agreement from Gate 4)' : '(the agreement the Managing Director signed)'); ?>.
                            <?php else: ?> The Grant Agreement for signature (PDF or Word). <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['requestFile.'.$role];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-semibold mb-1">Note (optional)</label>
                        <textarea rows="2" class="form-control form-control-sm" wire:model="signing.<?php echo e($role); ?>.message" placeholder="e.g. hand-delivered to the <?php echo e($title); ?>'s office with the Grant Readiness Memo, follow up on Friday"></textarea>
                    </div>
                    <div class="col-12 d-flex">
                        <button type="button" class="btn btn-sm <?php echo e($sent ? 'btn-outline-secondary' : 'btn-brand'); ?> ms-auto" wire:click="markSent('<?php echo e($role); ?>')"
                                wire:loading.attr="disabled" wire:target="markSent('<?php echo e($role); ?>'),requestFile.<?php echo e($role); ?>">
                            <span wire:loading.remove wire:target="markSent('<?php echo e($role); ?>')"><i class="bi bi-check2-square me-1"></i><?php echo e($sent ? 'Update' : 'Save & mark as sent for signing'); ?></span>
                            <span wire:loading wire:target="markSent('<?php echo e($role); ?>')"><span class="spinner-border spinner-border-sm me-1"></span> Saving…</span>
                        </button>
                    </div>
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>

        
        <div class="col-lg-5">
            <div class="d-flex align-items-center gap-2 mb-2">
                <span class="pill <?php echo e($signed ? 'pill-ok' : 'pill-warn'); ?>"><i class="bi <?php echo e($signed ? 'bi-file-earmark-check' : 'bi-hourglass-split'); ?>"></i> 2</span>
                <span class="fw-semibold text-navy small">Upload the signed Grant Agreement</span>
            </div>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($signed): ?>
                <div class="file-row mb-0">
                    <span class="file-row-icon"><i class="bi bi-file-earmark-check"></i></span>
                    <div class="flex-grow-1 small overflow-hidden">
                        <a href="<?php echo e(route('gate4.file', [$a, $role.'_signed'])); ?>" class="fw-semibold text-navy text-decoration-none text-truncate d-block"><?php echo e($a->{"{$role}_signed_name"}); ?></a>
                        <div class="text-muted">Signed by <?php echo e($a->signerName($role)); ?> · uploaded <?php echo e($a->{"{$role}_signed_at"}?->format('d M Y')); ?></div>
                    </div>
                    <a href="<?php echo e(route('gate4.file', [$a, $role.'_signed'])); ?>" class="btn btn-icon" title="Download"><i class="bi bi-download"></i></a>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($can && ! $decidedRole): ?>
                        <button type="button" class="btn btn-icon btn-icon-danger" title="Remove" wire:click="removeSigned('<?php echo e($role); ?>')" wire:confirm="Remove the signed copy?"><i class="bi bi-trash"></i></button>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            <?php elseif($can && ! $decidedRole): ?>
                <span wire:loading wire:target="signedFile.<?php echo e($role); ?>" class="small text-muted"><span class="spinner-border spinner-border-sm me-1"></span> Uploading…</span>
                <div wire:loading.remove wire:target="signedFile.<?php echo e($role); ?>">
                    <input type="file" class="form-control form-control-sm <?php $__errorArgs = ['signedFile.'.$role];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" wire:model="signedFile.<?php echo e($role); ?>" accept=".pdf,.jpg,.jpeg,.png">
                    <div class="form-text">
                        When the signed agreement comes back — PDF, or a clear scan / photo.
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($role === 'ps'): ?> This is the fully executed Grant Agreement (both signatures). <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['signedFile.'.$role];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            <?php else: ?>
                <div class="small text-muted"><?php echo e($sent ? 'Waiting for the signed copy.' : '—'); ?></div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    </div>
</div><?php /**PATH C:\laragon\www\co-investment-facility\resources\views/expression-of-interest/authorisation/partials/signing.blade.php ENDPATH**/ ?>