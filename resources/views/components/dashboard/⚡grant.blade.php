<?php

use App\Support\Dashboard;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Lazy;
use Livewire\Component;

/* Dashboard · CAFI grant position: released / in tranches / not yet in a tranche (roles that may see money only) */
new #[Lazy] class extends Component
{
    #[Computed] public function d(): Dashboard
    {
        return new Dashboard(auth()->user());
    }

    public function placeholder(): string
    {
        return <<<'HTML'
        <div class="col-lg-5"><div class="card card-modern h-100"><div class="card-body placeholder-glow"><span class="placeholder col-6 mb-3"></span><span class="placeholder col-12" style="height: 260px"></span></div></div></div>
        HTML;
    }
};
?>

@php
    $d     = $this->d;
    $show  = $d->canSeeMoney();
    $money = $show ? $d->money() : null;
    $m     = fn ($v) => \App\Support\Dashboard::m($v);
    $u     = fn ($v) => \App\Support\Dashboard::u($v);
@endphp

<div class="{{ $show ? 'col-lg-5' : 'd-none' }}">
    @if ($show)
        <div class="card card-modern h-100">
            <div class="card-header py-3"><h6 class="fw-bold text-navy mb-0"><i class="bi bi-pie-chart me-1"></i> CAFI grant position</h6></div>
            <div class="card-body">
                @if ($money['grant'] > 0)
                    <div wire:ignore><div class="chart-box" data-cif-chart="grant" data-cif-data="{{ json_encode(['released' => $money['released'], 'scheduled' => $money['scheduled'], 'unscheduled' => $money['unscheduled']]) }}"></div></div>
                    <div class="row g-2 text-center mt-1">
                        <div class="col-4"><div class="small text-muted">Approved</div><div class="fw-bold text-navy">{{ $m($money['grant']) }}</div><div class="money-sub">{{ $u($money['grant']) }}</div></div>
                        <div class="col-4"><div class="small text-muted">Released</div><div class="fw-bold text-success">{{ $m($money['released']) }}</div><div class="money-sub">{{ $u($money['released']) }}</div></div>
                        <div class="col-4"><div class="small text-muted">Still to be disbursed</div><div class="fw-bold text-navy">{{ $m($money['toDisburse']) }}</div><div class="money-sub">{{ $u($money['toDisburse']) }}</div></div>
                    </div>
                    <hr class="my-3">
                    <div class="d-flex justify-content-between small">
                        <span class="text-muted">Investor capital verified</span>
                        <span class="fw-semibold">{{ $m($money['verified']) }} <span class="text-muted fw-normal">of {{ $m($money['private']) }} committed</span></span>
                    </div>
                    @if ($money['leverage'] !== null)
                        <div class="d-flex justify-content-between small mt-1">
                            <span class="text-muted">Private capital per M 1 of grant released</span>
                            <span class="fw-semibold">M {{ number_format($money['leverage'], 1) }}</span>
                        </div>
                    @endif
                @else
                    <div class="text-muted small py-5 text-center">No grant has been approved yet — figures appear once a transaction reaches Gate 5.</div>
                @endif
            </div>
        </div>
    @endif
</div>