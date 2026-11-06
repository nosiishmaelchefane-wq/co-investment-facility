<?php
use App\Models\EnterpriseProfile;
use App\Models\ExpressionOfInterest;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
?>


<div class="container-fluid p-3">
    <?php
        $eois     = $this->eois;
        $all      = $this->scoped;
        $seesAll  = $this->seesAll;
        $isEnterprise = auth()->user()->can('eoi.submit');
        $profile  = $this->profile;
        $canApply = $isEnterprise && $this->profileComplete;
        $filtered = $search !== '' || $status !== '';

        $statuses = [
            'draft'          => ['Draft',          'status-draft',     'bi-pencil'],
            'submitted'      => ['Submitted',      'status-submitted', 'bi-send'],
            'returned'       => ['Returned',       'status-referred',  'bi-arrow-return-left'],
            'in_review'      => ['In review',      'status-in-review', 'bi-hourglass-split'],
            'invited'        => ['Invited to CI',  'status-approved',  'bi-patch-check'],
            'due_diligence'  => ['Due diligence',  'status-in-review', 'bi-search'],
            'dd_hold'        => ['DD on hold',     'status-referred',  'bi-pause-circle'],
            'committee'      => ['Investment Committee', 'status-approved', 'bi-people'],
            'ic_deferred'    => ['Deferred by IC', 'status-referred',  'bi-pause-circle'],
            'grant_readiness' => ['Grant readiness review', 'status-in-review', 'bi-clipboard-check'],
            'authorisation'  => ['Final authorisation', 'status-approved', 'bi-patch-check'],
            'authorisation_hold' => ['Authorisation on hold', 'status-referred', 'bi-pause-circle'],
            'authorised'     => ['Formally approved',     'status-approved',  'bi-patch-check-fill'],
            'capital_pending' => ['Awaiting investor capital', 'status-referred', 'bi-shield-exclamation'],
            'cleared'        => ['Cleared for disbursement', 'status-approved', 'bi-cash-coin'],
            'escalated'      => ['Escalated',      'status-rejected',  'bi-exclamation-octagon'],
            'not_progressed' => ['Not progressed', 'status-rejected',  'bi-x-circle'],
            'withdrawn'      => ['Withdrawn',      'status-draft',     'bi-slash-circle'],
        ];

        $investorLabels = [
            'none' => 'No investor', 'discussions' => 'In discussions', 'interest' => 'Interest indicated',
            'loi' => 'Letter of Intent', 'term_sheet' => 'Term Sheet', 'committed' => 'Committed', 'other' => 'Other',
        ];

        $rate  = (float) config('cif.usd_to_lsl', 18.00);
        $count = fn (...$s) => $all->whereIn('status', $s)->count();
    ?>

    
    <div class="d-flex flex-wrap align-items-center gap-3 mb-4">
        <div class="me-auto">
            <h2 class="page-heading">Expressions of Interest</h2>
            <p class="page-subtitle">
                <?php echo e($seesAll ? 'All applications to the CAFI Co-Investment Facility.' : 'Track your applications to the CAFI Co-Investment Facility. You can apply for more than one transaction.'); ?>

            </p>
        </div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canApply): ?>
            <button type="button" class="btn btn-cta" wire:click="openApp">
                <i class="bi bi-plus-lg me-1"></i> <?php echo e($all->isEmpty() ? 'Apply for Co-Investment' : 'New application'); ?>

            </button>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isEnterprise && ! $canApply): ?>
        <?php
            $done    = $profile?->exists ? $profile->completedCount() : 0;
            $total   = count(\App\Models\EnterpriseProfile::SECTIONS);
            $pending = $profile?->exists
                ? collect($profile->sectionStatus())->reject()->keys()->map(fn ($k) => \App\Models\EnterpriseProfile::SECTIONS[$k][0])->implode(', ')
                : null;
        ?>
        <div class="callout callout-warning d-flex flex-wrap align-items-center gap-3 mb-4">
            <i class="bi bi-building-exclamation fs-4"></i>
            <div class="me-auto">
                <div class="fw-semibold">Complete your enterprise profile to apply</div>
                <div class="small">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($profile?->exists): ?>
                        <?php echo e($done); ?> of <?php echo e($total); ?> sections complete. Still to do: <?php echo e($pending); ?>.
                    <?php else: ?>
                        You haven't started your enterprise profile yet. It takes 4 short sections.
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>
            <a href="<?php echo e(route('profile.index')); ?>" class="btn btn-brand btn-sm">
                <i class="bi bi-pencil-square me-1"></i> <?php echo e($profile?->exists ? 'Complete profile' : 'Start profile'); ?>

            </a>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    
    <div class="row g-3 mb-4">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = [
            ['Total applications', $all->count(),                    'bi-collection',      'primary', ''],
            ['Drafts',             $count('draft'),                  'bi-pencil-square',   'muted',   'draft'],
            ['Under review',       $count('submitted', 'in_review'), 'bi-hourglass-split', 'warning', 'under_review'],
            ['Invited to CI',      $count('invited'),                'bi-patch-check',     'accent',  'invited'],
        ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$label, $value, $icon, $tone, $key]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
            <div class="col-6 col-xl-3">
                <button type="button" class="stat-card w-100 text-start border-0 <?php echo e($status === $key && $key !== '' ? 'is-active' : ''); ?>"
                        wire:click="<?php echo e($key === '' ? 'clearFilters' : "filterStatus('$key')"); ?>" title="<?php echo e($key === '' ? 'Show all' : 'Filter: '.$label); ?>">
                    <span class="stat-icon tone-<?php echo e($tone); ?>"><i class="bi <?php echo e($icon); ?>"></i></span>
                    <div>
                        <div class="stat-value"><?php echo e($value); ?></div>
                        <div class="stat-label"><?php echo e($label); ?></div>
                    </div>
                </button>
            </div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
    </div>

    
    <div class="card card-modern">
        <div class="card-header py-3">
            <div class="row g-2 align-items-center">
                <div class="col-md">
                    <div class="input-search">
                        <i class="bi bi-search"></i>
                        <input type="search" class="form-control" wire:model.live.debounce.300ms="search" wire:keydown.escape="$set('search', '')"
                               placeholder="Search by reference, business, district or investor…">
                    </div>
                </div>
                <div class="col-md-auto">
                    <select class="form-select" wire:model.live="status">
                        <option value="">All statuses (<?php echo e($all->count()); ?>)</option>
                        <option value="under_review">Under review — submitted + in review (<?php echo e($count('submitted', 'in_review')); ?>)</option>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $statuses; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => [$label]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <option value="<?php echo e($key); ?>"><?php echo e($label); ?> (<?php echo e($count($key)); ?>)</option>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </select>
                </div>
                <div class="col-auto" wire:loading wire:target="search, status, filterStatus, clearFilters">
                    <span class="spinner-border spinner-border-sm text-muted"></span>
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

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($eois->isEmpty()): ?>
            <div class="empty-state">
                <div class="empty-icon"><i class="bi <?php echo e($filtered ? 'bi-search' : 'bi-file-earmark-plus'); ?>"></i></div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($filtered): ?>
                    <h5 class="fw-bold text-navy mb-1">No applications match</h5>
                    <p class="text-muted small mb-3">Try a different search or status.</p>
                    <button type="button" class="btn btn-brand btn-sm" wire:click="clearFilters">Clear filters</button>
                <?php else: ?>
                    <h5 class="fw-bold text-navy mb-1">No applications yet</h5>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canApply): ?>
                        <p class="text-muted small mb-3">Submit an Expression of Interest to be considered for the Co-Investment Facility.</p>
                        <button type="button" class="btn btn-brand btn-sm" wire:click="openApp"><i class="bi bi-plus-lg me-1"></i> Apply now</button>
                    <?php elseif($isEnterprise): ?>
                        <p class="text-muted small mb-0">Complete your enterprise profile first — then you can apply.</p>
                    <?php else: ?>
                        <p class="text-muted small mb-0">No applications have been submitted yet.</p>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 table-modern">
                    <thead>
                        <tr>
                            <th class="ps-4">Reference</th>
                            <th>Business</th>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($seesAll): ?> <th>Applicant</th> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <th class="text-end">Investment</th>
                            <th>Investor</th>
                            <th>Submitted</th>
                            <th>Status</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $eois; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $eoi): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <?php
                                [$sLabel, $sClass, $sIcon] = $statuses[$eoi->status] ?? [ucfirst($eoi->status), 'status-draft', 'bi-circle'];
                                $isOwner = $eoi->user_id === auth()->id();
                                $usd     = $eoi->investment_required_usd;
                            ?>
                            <tr <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'eoi-'.e($eoi->id).''; ?>wire:key="eoi-<?php echo e($eoi->id); ?>">
                                <td class="ps-4">
                                    <a href="<?php echo e(route('expression-of-interest.show', ['id' => $eoi->id])); ?>" class="link-strong"><?php echo e($eoi->reference); ?></a>
                                </td>
                                <td>
                                    <div class="fw-semibold text-navy"><?php echo e($eoi->business_name ?: 'Untitled draft'); ?></div>
                                    <div class="text-muted small"><i class="bi bi-geo-alt me-1"></i><?php echo e($eoi->district ?: '—'); ?></div>
                                </td>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($seesAll): ?>
                                    <td class="small">
                                        <div class="fw-semibold text-navy"><?php echo e($eoi->user?->name ?? '—'); ?></div>
                                        <div class="text-muted"><?php echo e($eoi->user?->email); ?></div>
                                    </td>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <td class="text-end">
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($usd !== null): ?>
                                        <div class="fw-semibold">M <?php echo e(number_format((float) $usd * $rate)); ?></div>
                                        <div class="small text-muted">US$ <?php echo e(number_format((float) $usd)); ?></div>
                                    <?php else: ?> — <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </td>
                                <td class="small"><?php echo e($investorLabels[$eoi->investor_status] ?? '—'); ?></td>
                                <td class="small text-muted"><?php echo e($eoi->submitted_at?->format('d M Y') ?? '—'); ?></td>
                                <td><span class="badge <?php echo e($sClass); ?>"><i class="bi <?php echo e($sIcon); ?> me-1"></i><?php echo e($sLabel); ?></span></td>
                                <td class="text-end pe-4">
                                    <div class="d-inline-flex gap-1">
                                        <a href="<?php echo e(route('expression-of-interest.show', ['id' => $eoi->id])); ?>" class="btn btn-icon" title="View application">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isOwner && $eoi->isEditable()): ?>
                                            <button type="button" class="btn btn-icon" title="Edit" wire:click="editApp(<?php echo e($eoi->id); ?>)">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isOwner && $eoi->isWithdrawable()): ?>
                                            <button type="button" class="btn btn-icon btn-icon-danger" title="Withdraw"
                                                    wire:click="withdraw(<?php echo e($eoi->id); ?>)"
                                                    wire:confirm="Withdraw <?php echo e($eoi->reference); ?>? This cannot be undone.">
                                                <i class="bi bi-x-circle"></i>
                                            </button>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer small text-muted d-flex flex-wrap gap-2 align-items-center">
                <span>Showing <?php echo e($eois->count()); ?> of <?php echo e($all->count()); ?> <?php echo e(\Illuminate\Support\Str::plural('application', $all->count())); ?></span>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($status !== ''): ?>
                    <span class="pill pill-muted">Status: <?php echo e($status === 'under_review' ? 'Under review' : ($statuses[$status][0] ?? $status)); ?></span>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($search !== ''): ?>
                    <span class="pill pill-muted">Search: “<?php echo e($search); ?>”</span>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isEnterprise): ?>
        <?php
$__split = function ($name, $params = []) {
    return [$name, $params];
};
[$__name, $__params] = $__split('expression-of-interest.drawer.express-interest', []);

$__keyOuter = $__key ?? null;

$__key = null;
$__componentSlots = [];

$__key ??= \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::generateKey('lw-3699318704-0', $__key);

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
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div><?php /**PATH C:\laragon\www\co-investment-facility\storage\framework\views/livewire/views/78989ef2.blade.php ENDPATH**/ ?>