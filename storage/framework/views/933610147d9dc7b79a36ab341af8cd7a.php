
<?php
    $mm       = $p?->matchmakingResult();
    $compact  = $compact ?? false;
    $editing  = $editing ?? false;
    $canFresh = ($refresh ?? true) && \App\Support\CollaborationPlatform::enabled() && ! $compact;
    $m        = (array) ($mm['match'] ?? []);
    $inv      = (array) ($mm['investor'] ?? []);
    $date     = fn ($d) => $d ? \Illuminate\Support\Carbon::parse($d)->format('d M Y') : null;
    $statusPill = match ($m['status'] ?? null) {
        'deal_closed' => 'pill-ok', 'in_talks' => 'pill-warn', 'introduced', 'proposed' => 'pill-muted', default => 'pill-muted',
    };
    $ticket = collect([$inv['ticket_min'] ?? null, $inv['ticket_max'] ?? null])->filter()->map(fn ($v) => number_format((float) $v))->implode(' – ');
?>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($mm && $compact): ?>
    <span class="pill pill-ok" title="The investor was found through the LEHSFF Collaboration Platform matchmaking">
        <i class="bi bi-diagram-3"></i> Matchmaking · <?php echo e($m['status_label'] ?? ''); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($m['score'])): ?> · <?php echo e($m['score']); ?>% <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </span>
<?php elseif($mm): ?>
    <div class="border rounded-3 p-3 mb-3" style="border-color: #86efac !important; background: #f0fdf4;">
        <div class="d-flex flex-wrap align-items-start gap-2">
            <span class="stat-icon tone-accent" style="width:36px;height:36px"><i class="bi bi-diagram-3"></i></span>
            <div class="me-auto">
                <div class="fw-semibold text-navy">Investor found through matchmaking</div>
                <div class="small text-muted">LEHSFF Collaboration Platform · investor matchmaking</div>
            </div>
            <span class="pill <?php echo e($statusPill); ?>"><?php echo e($m['status_label'] ?? ucfirst((string) ($m['status'] ?? ''))); ?></span>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($m['score'])): ?>
                <span class="pill <?php echo e($m['score'] >= 70 ? 'pill-ok' : ($m['score'] >= 50 ? 'pill-warn' : 'pill-muted')); ?>" title="How well the enterprise fits the investor's preferences">
                    <i class="bi bi-bullseye"></i> Match score <?php echo e($m['score']); ?>%
                </span>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
        <div class="row g-2 small mt-1">
            <div class="col-md-4"><span class="text-muted">Investor:</span> <strong><?php echo e($inv['name'] ?? '—'); ?></strong>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! empty($inv['type_labels'])): ?> <span class="text-muted">(<?php echo e(implode(', ', (array) $inv['type_labels'])); ?>)</span> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
            <div class="col-md-4"><span class="text-muted">Introduced:</span> <?php echo e($date($m['introduced_at'] ?? null) ?? '—'); ?>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! empty($m['matched_by'])): ?> <span class="text-muted">by <?php echo e($m['matched_by']); ?></span> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
            <div class="col-md-4"><span class="text-muted">Typical ticket:</span>
                <?php echo e($ticket ? ($inv['ticket_currency'] ?? 'USD').' '.$ticket : '—'); ?></div>
        </div>
        <div class="small text-muted mt-2">
            Answers marked <span class="pill pill-ok" style="font-size:.66rem"><i class="bi bi-diagram-3"></i> Matchmaking</span> were filled in automatically
            (matched by the e-mail <?php echo e($p->user?->email); ?>). <?php echo e($editing ? 'You can change them.' : 'The applicant can change them.'); ?>

            The investment amount is entered by the applicant.
            · Checked <?php echo e($p->matchmaking_synced_at?->diffForHumans() ?? '—'); ?>

        </div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canFresh): ?>
            <button type="button" class="btn btn-sm btn-outline-secondary mt-2" wire:click="refreshMatchmaking" wire:loading.attr="disabled" wire:target="refreshMatchmaking">
                <span wire:loading.remove wire:target="refreshMatchmaking"><i class="bi bi-arrow-repeat me-1"></i> Refresh from matchmaking</span>
                <span wire:loading wire:target="refreshMatchmaking"><span class="spinner-border spinner-border-sm me-1"></span> Checking…</span>
            </button>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
<?php elseif($canFresh && $p?->exists): ?>
    <div class="d-flex flex-wrap align-items-center gap-2 small text-muted mb-2">
        <i class="bi bi-diagram-3"></i>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($p->matchmaking['last_result'] ?? null) && $p->matchmaking_synced_at): ?>
            No investor match on the Collaboration Platform for <?php echo e($p->user?->email); ?> (checked <?php echo e($p->matchmaking_synced_at->diffForHumans()); ?>).
        <?php else: ?>
            Investor information can be filled in from the Collaboration Platform matchmaking.
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <button type="button" class="btn btn-sm btn-link p-0 text-decoration-none" wire:click="refreshMatchmaking" wire:loading.attr="disabled" wire:target="refreshMatchmaking">
            <i class="bi bi-arrow-repeat me-1"></i> Check matchmaking
        </button>
    </div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?><?php /**PATH C:\laragon\www\co-investment-facility\resources\views/profile/partials/matchmaking-source.blade.php ENDPATH**/ ?>