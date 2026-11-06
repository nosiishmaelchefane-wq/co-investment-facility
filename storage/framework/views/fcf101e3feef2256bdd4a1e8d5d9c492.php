<?php
use App\Models\Role;
use App\Models\User;
use App\Notifications\AccountAccessLink;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
?>


<div>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($showDrawer): ?>
    <?php
        $isEnterprise = $form['role'] === 'enterprise';
    ?>

    <div class="drawer-overlay drawer-fade" wire:click="closeDrawer"></div>

    <div class="drawer drawer-md drawer-panel drawer-enter" role="dialog" aria-modal="true" aria-labelledby="userDrawerLabel"
         wire:keydown.escape.window="closeDrawer">

        
        <div class="offcanvas-header drawer-header">
            <div>
                <div class="drawer-eyebrow">User management</div>
                <h5 class="offcanvas-title" id="userDrawerLabel">
                    <?php echo e($mode === 'edit' ? 'Edit user · '.$form['name'] : 'Add a new user'); ?>

                </h5>
            </div>
            <button type="button" class="btn-close btn-close-white" wire:click="closeDrawer" aria-label="Close"></button>
        </div>

        <form wire:submit.prevent="save" class="form-modern d-flex flex-column flex-grow-1 overflow-hidden" novalidate>
            <div class="offcanvas-body drawer-body">

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isSelf): ?>
                    <div class="callout small mb-3">
                        <i class="bi bi-info-circle me-1"></i> This is your own account — your role and status can't be changed here.
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                
                <div class="form-section-head">
                    <span class="q-badge">1</span>
                    <div><h6>Personal details</h6><p>Who the account belongs to.</p></div>
                </div>
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label" for="u_name">Full name <span class="req">*</span></label>
                        <input type="text" id="u_name" wire:model="form.name" class="form-control <?php $__errorArgs = ['form.name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                    <div class="col-md-7">
                        <label class="form-label" for="u_email">Email <span class="req">*</span></label>
                        <input type="email" id="u_email" wire:model="form.email" class="form-control <?php $__errorArgs = ['form.email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label" for="u_phone">Phone <span class="opt">(optional)</span></label>
                        <input type="tel" id="u_phone" wire:model="form.phone" placeholder="+266 5xxx xxxx" class="form-control <?php $__errorArgs = ['form.phone'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.phone'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                </div>

                
                <div class="form-section-head mt-4">
                    <span class="q-badge">2</span>
                    <div><h6>Role <span class="req">*</span></h6><p>Decides what this user can see and do.</p></div>
                </div>
                <div class="choice-group cols-2 <?php $__errorArgs = ['form.role'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $this->roles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $r): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <label class="choice choice--card" <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'role-'.e($r->id).''; ?>wire:key="role-<?php echo e($r->id); ?>">
                            <input type="radio" wire:model.live="form.role" value="<?php echo e($r->name); ?>" <?php if($isSelf): echo 'disabled'; endif; ?>>
                            <span class="choice-box">
                                <span class="choice-icon"><i class="bi <?php echo e($r->icon); ?>"></i></span>
                                <span>
                                    <span class="d-block fw-semibold"><?php echo e($r->display_name); ?></span>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($r->description): ?>
                                        <span class="d-block small text-muted lh-sm mt-1"><?php echo e($r->description); ?></span>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </span>
                            </span>
                        </label>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.role'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-danger small mt-1"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                <div class="mt-3">
                    <label class="form-label" for="u_org">
                        <?php echo e($isEnterprise ? 'Enterprise name' : 'Organisation / unit'); ?>

                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isEnterprise): ?> <span class="req">*</span> <?php else: ?> <span class="opt">(optional)</span> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </label>
                    <input type="text" id="u_org" wire:model="form.organisation"
                           placeholder="<?php echo e($isEnterprise ? 'e.g. Maluti Fresh Foods' : 'e.g. LEHSFF PMU'); ?>"
                           class="form-control <?php $__errorArgs = ['form.organisation'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.organisation'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($form['role'] === 'committee'): ?>
                    <?php
                        $sec       = $this->currentSecretary;
                        $isCurrent = $sec && $userId && $sec->id === $userId;
                    ?>
                    <div class="callout small mt-3">
                        <i class="bi bi-info-circle me-1"></i>
                        The Investment Committee has <strong>3 fixed members</strong>. Members declare conflicts of interest and score each transaction at Gate 3.
                    </div>

                    <label class="choice choice--card mt-3">
                        <input type="checkbox" wire:model.live="form.committee_secretary">
                        <span class="choice-box">
                            <span class="choice-icon"><i class="bi bi-journal-check"></i></span>
                            <span>
                                <span class="d-block fw-semibold">Committee secretary</span>
                                <span class="d-block small text-muted lh-sm mt-1">
                                    Records the Committee's decisions for all members: review criteria, deliberation, questions log,
                                    material risks &amp; conditions, and the Committee outcome and rationale. The secretary also scores as a member.
                                </span>
                            </span>
                        </span>
                    </label>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($form['committee_secretary'] && $sec && ! $isCurrent): ?>
                        <div class="small text-warning mt-1">
                            <i class="bi bi-arrow-left-right me-1"></i> <?php echo e($sec->name); ?> is the secretary now — saving makes this user the secretary instead.
                        </div>
                    <?php elseif(! $form['committee_secretary'] && $isCurrent): ?>
                        <div class="small text-warning mt-1">
                            <i class="bi bi-exclamation-triangle me-1"></i> Unticking leaves the Committee without a secretary — nobody will be able to record decisions until another member is ticked.
                        </div>
                    <?php elseif(! $sec): ?>
                        <div class="small text-muted mt-1"><i class="bi bi-info-circle me-1"></i> No Committee secretary has been set yet.</div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($mode === 'new'): ?>
                    <div class="form-section-head mt-4">
                        <span class="q-badge">3</span>
                        <div><h6>How will they sign in?</h6><p>Invite them by email or set a temporary password.</p></div>
                    </div>
                    <div class="choice-group cols-2">
                        <label class="choice choice--card">
                            <input type="radio" wire:model.live="form.access" value="invite">
                            <span class="choice-box">
                                <span class="choice-icon"><i class="bi bi-envelope"></i></span>
                                <span>
                                    <span class="d-block fw-semibold">Send invitation</span>
                                    <span class="d-block small text-muted lh-sm mt-1">They set their own password from the email link (<?php echo e(\App\Models\User::SETUP_LINK_HOURS); ?> hours).</span>
                                </span>
                            </span>
                        </label>
                        <label class="choice choice--card">
                            <input type="radio" wire:model.live="form.access" value="password">
                            <span class="choice-box">
                                <span class="choice-icon"><i class="bi bi-key"></i></span>
                                <span>
                                    <span class="d-block fw-semibold">Set temporary password</span>
                                    <span class="d-block small text-muted lh-sm mt-1">Share it with them; they change it after signing in.</span>
                                </span>
                            </span>
                        </label>
                    </div>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($form['access'] === 'password'): ?>
                        <div class="row g-3 mt-1">
                            <div class="col-md-6">
                                <label class="form-label" for="u_pw">Temporary password <span class="req">*</span></label>
                                <input type="password" id="u_pw" wire:model="form.password" autocomplete="new-password" class="form-control <?php $__errorArgs = ['form.password'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['form.password'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="invalid-feedback"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="u_pw2">Confirm password <span class="req">*</span></label>
                                <input type="password" id="u_pw2" wire:model="form.password_confirmation" autocomplete="new-password" class="form-control">
                            </div>
                        </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($mode === 'edit' || $form['access'] === 'password'): ?>
                    <div class="form-section-head mt-4">
                        <span class="q-badge"><?php echo e($mode === 'edit' ? 3 : 4); ?></span>
                        <div><h6>Account status</h6><p>Inactive users cannot sign in.</p></div>
                    </div>
                    <div class="choice-group cols-2">
                        <label class="choice">
                            <input type="radio" wire:model="form.status" value="active" <?php if($isSelf): echo 'disabled'; endif; ?>>
                            <span class="choice-box"><span class="choice-radio"></span> Active</span>
                        </label>
                        <label class="choice">
                            <input type="radio" wire:model="form.status" value="inactive" <?php if($isSelf): echo 'disabled'; endif; ?>>
                            <span class="choice-box"><span class="choice-radio"></span> Inactive</span>
                        </label>
                    </div>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($mode === 'edit'): ?>
                        <p class="small text-muted mt-2 mb-0">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($currentStatus === \App\Models\User::STATUS_INVITED): ?>
                                <i class="bi bi-envelope me-1"></i> Invitation pending — use <strong>Resend invitation</strong> in the table if they didn't get it.
                            <?php else: ?>
                                <i class="bi bi-key me-1"></i> To change their password, use <strong>Send password reset</strong> in the table.
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </p>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            
            <div class="drawer-footer">
                <button type="button" class="btn btn-outline-secondary" wire:click="closeDrawer">Cancel</button>
                <button type="submit" class="btn btn-brand ms-auto" wire:loading.attr="disabled" wire:target="save">
                    <span wire:loading.remove wire:target="save">
                        <i class="bi <?php echo e($mode === 'new' ? 'bi-person-plus' : 'bi-check-lg'); ?> me-1"></i>
                        <?php echo e($mode === 'new' ? ($form['access'] === 'invite' ? 'Create & send invite' : 'Create account') : 'Save changes'); ?>

                    </span>
                    <span wire:loading wire:target="save"><span class="spinner-border spinner-border-sm me-1"></span> Saving…</span>
                </button>
            </div>
        </form>
    </div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div><?php /**PATH C:\laragon\www\co-investment-facility\storage\framework\views/livewire/views/32a719b5.blade.php ENDPATH**/ ?>