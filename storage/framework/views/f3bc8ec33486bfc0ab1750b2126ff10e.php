<?php
use App\Models\Role;
use App\Models\User;
use App\Notifications\AccountAccessLink;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;
?>


<div class="container-fluid p-3">
    <?php
        $users    = $this->users;
        $roles    = $this->roles;
        $stats    = $this->stats;
        $statuses = \App\Models\User::STATUSES;
        $filtered = $search !== '' || $role !== '' || $status !== '';
    ?>

    
    <div class="d-flex flex-wrap align-items-center gap-3 mb-4">
        <div class="me-auto">
            <h2 class="page-heading">User management</h2>
            <p class="page-subtitle">Create accounts, assign roles and manage access to the Co-Investment Facility.</p>
        </div>
        <button type="button" class="btn btn-cta" wire:click="addUser">
            <i class="bi bi-person-plus me-1"></i> Add user
        </button>
    </div>

    
    <div class="row g-3 mb-4">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = [
            ['Total users',     $stats['total'],    'bi-people',       'primary', ''],
            ['Active',          $stats['active'],   'bi-check-circle', 'accent',  'active'],
            ['Pending invites', $stats['invited'],  'bi-envelope',     'warning', 'invited'],
            ['Inactive',        $stats['inactive'], 'bi-slash-circle', 'muted',   'inactive'],
        ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$label, $value, $icon, $tone, $key]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
            <div class="col-6 col-xl-3">
                <div class="stat-card">
                    <span class="stat-icon tone-<?php echo e($tone); ?>"><i class="bi <?php echo e($icon); ?>"></i></span>
                    <div><div class="stat-value"><?php echo e($value); ?></div><div class="stat-label"><?php echo e($label); ?></div></div>
                </div>
            </div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
    </div>

    
    <div class="filter-cards mb-4">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $roles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $r): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
            <button type="button" wire:click="filterRole('<?php echo e($r->name); ?>')" <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'rc-'.e($r->id).''; ?>wire:key="rc-<?php echo e($r->id); ?>"
                    class="filter-card <?php echo e($role === $r->name ? 'active' : ''); ?>">
                <span class="filter-card-icon"><i class="bi <?php echo e($r->icon); ?>"></i></span>
                <span class="filter-card-body">
                    <span class="filter-card-value"><?php echo e($r->users_count); ?></span>
                    <span class="filter-card-label"><?php echo e($r->display_name); ?></span>
                </span>
            </button>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
    </div>

    
    <div class="card card-modern">
        <div class="card-header py-3">
            <div class="row g-2 align-items-center">
                <div class="col-md">
                    <div class="input-search">
                        <i class="bi bi-search"></i>
                        <input type="search" class="form-control" wire:model.live.debounce.300ms="search" placeholder="Search by name, email or organisation…">
                    </div>
                </div>
                <div class="col-sm-6 col-md-auto">
                    <select class="form-select" wire:model.live="role">
                        <option value="">All roles</option>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $roles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $r): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <option value="<?php echo e($r->name); ?>"><?php echo e($r->display_name); ?></option>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </select>
                </div>
                <div class="col-sm-6 col-md-auto">
                    <select class="form-select" wire:model.live="status">
                        <option value="">All statuses</option>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $statuses; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => [$label]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <option value="<?php echo e($key); ?>"><?php echo e($label); ?></option>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </select>
                </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($filtered): ?>
                    <div class="col-md-auto">
                        <button type="button" class="btn btn-link text-decoration-none text-secondary px-1" wire:click="clearFilters">
                            <i class="bi bi-x-lg me-1"></i> Clear
                        </button>
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($users->isEmpty()): ?>
            <div class="empty-state">
                <div class="empty-icon"><i class="bi bi-person-x"></i></div>
                <h5 class="fw-bold text-navy mb-1"><?php echo e($filtered ? 'No users found' : 'No users yet'); ?></h5>
                <p class="text-muted small mb-3"><?php echo e($filtered ? 'Try a different search or clear the filters.' : 'Add the first user to get started.'); ?></p>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($filtered): ?>
                    <button type="button" class="btn btn-brand btn-sm" wire:click="clearFilters">Clear filters</button>
                <?php else: ?>
                    <button type="button" class="btn btn-brand btn-sm" wire:click="addUser"><i class="bi bi-person-plus me-1"></i> Add user</button>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 table-modern">
                    <thead>
                        <tr>
                            <th class="ps-4">User</th>
                            <th>Role</th>
                            <th>Organisation</th>
                            <th>Phone</th>
                            <th>Status</th>
                            <th>Last login</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $users; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $u): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <?php
                                $r        = $u->primary_role;
                                $external = $r?->isExternal();
                                [$sLabel, $sClass, $sIcon] = $statuses[$u->status] ?? [ucfirst($u->status), 'status-draft', 'bi-circle'];
                                $isMe     = $u->is(auth()->user());
                                $profileUrl = $u->roles->contains('name', 'enterprise') ? route('profile.index', $u) : null;
                            ?>
                            <tr <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'user-'.e($u->id).''; ?>wire:key="user-<?php echo e($u->id); ?>">
                                <td class="ps-4">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="avatar avatar-sm <?php echo e($external ? 'bg-accent' : ''); ?>"><?php echo e($u->initials); ?></span>
                                        <div class="overflow-hidden">
                                            <div class="fw-semibold text-navy text-truncate">
                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($profileUrl): ?>
                                                    <a href="<?php echo e($profileUrl); ?>" class="link-strong" title="View enterprise profile"><?php echo e($u->name); ?></a>
                                                <?php else: ?>
                                                    <?php echo e($u->name); ?>

                                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isMe): ?> <span class="pill pill-muted ms-1">You</span> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                            </div>
                                            <div class="small text-muted text-truncate"><?php echo e($u->email); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($r): ?>
                                        <span class="tag <?php echo e($external ? 'tag-accent' : ''); ?>"><i class="bi <?php echo e($r->icon); ?>"></i> <?php echo e($r->display_name); ?></span>
                                    <?php else: ?>
                                        <span class="text-faint small">No role</span>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </td>
                                <td class="small"><?php echo e($u->organisation_name ?: '—'); ?></td>
                                <td class="small text-muted"><?php echo e($u->phone ?: '—'); ?></td>
                                <td><span class="badge <?php echo e($sClass); ?>"><i class="bi <?php echo e($sIcon); ?> me-1"></i><?php echo e($sLabel); ?></span></td>
                                <td class="small text-muted" title="<?php echo e($u->last_login_at?->format('d M Y H:i')); ?>"><?php echo e($u->last_login_at?->diffForHumans() ?? 'Never'); ?></td>
                                <td class="text-end pe-4">
                                    <div class="d-inline-flex gap-1">
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($profileUrl): ?>
                                            <a href="<?php echo e($profileUrl); ?>" class="btn btn-icon" title="View enterprise profile">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        <button type="button" class="btn btn-icon" title="Edit" wire:click="editUser(<?php echo e($u->id); ?>)">
                                            <i class="bi bi-pencil"></i>
                                        </button>

                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($u->status === \App\Models\User::STATUS_INVITED): ?>
                                            <button type="button" class="btn btn-icon" title="Resend invitation"
                                                    wire:click="resendInvite(<?php echo e($u->id); ?>)" wire:loading.attr="disabled" wire:target="resendInvite(<?php echo e($u->id); ?>)">
                                                <i class="bi bi-send"></i>
                                            </button>
                                        <?php elseif($u->isActive()): ?>
                                            <button type="button" class="btn btn-icon" title="Send password reset"
                                                    wire:click="sendPasswordReset(<?php echo e($u->id); ?>)"
                                                    wire:confirm="Send a password reset link to <?php echo e($u->email); ?>?">
                                                <i class="bi bi-key"></i>
                                            </button>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($isMe)): ?>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($u->isActive()): ?>
                                                <button type="button" class="btn btn-icon btn-icon-danger" title="Deactivate"
                                                        wire:click="toggleStatus(<?php echo e($u->id); ?>)"
                                                        wire:confirm="Deactivate <?php echo e($u->name); ?>? They will no longer be able to sign in.">
                                                    <i class="bi bi-person-x"></i>
                                                </button>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-icon" title="Reactivate" wire:click="toggleStatus(<?php echo e($u->id); ?>)">
                                                    <i class="bi bi-person-check"></i>
                                                </button>
                                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="card-footer d-flex flex-wrap align-items-center gap-2 small text-muted">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($users->hasPages()): ?>
                    <div class="w-100"><?php echo e($users->links()); ?></div>
                <?php else: ?>
                    <span>Showing <?php echo e($users->total()); ?> <?php echo e(\Illuminate\Support\Str::plural('user', $users->total())); ?></span>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>

    
    <?php
$__split = function ($name, $params = []) {
    return [$name, $params];
};
[$__name, $__params] = $__split('users.drawer.user-form', []);

$__keyOuter = $__key ?? null;

$__key = null;
$__componentSlots = [];

$__key ??= \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::generateKey('lw-1253780826-0', $__key);

$__html = app('livewire')->mount($__name, $__params, $__key, $__componentSlots);

echo $__html;

unset($__html);
unset($__key);
$__key = $__keyOuter;
unset($__keyOuter);
unset($__name);
unset($__params);
unset($__componentSlots);
unset($__split);
?>
</div><?php /**PATH C:\laragon\www\co-investment-facility\storage\framework\views/livewire/views/faa76808.blade.php ENDPATH**/ ?>