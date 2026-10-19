<?php

use App\Support\Dashboard;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Lazy;
use Livewire\Component;

/* Dashboard · last 12 months: applications submitted (bars) + CAFI grant released (line, money roles only) */
new #[Lazy] class extends Component
{
    #[Computed] public function d(): Dashboard
    {
        return new Dashboard(auth()->user());
    }

    #[Computed] public function chart(): array
    {
        return $this->d->monthly() + ['money' => $this->d->canSeeMoney()];
    }

    public function placeholder(): string
    {
        return <<<'HTML'
        <div class="col-lg-7"><div class="card card-modern h-100"><div class="card-body placeholder-glow"><span class="placeholder col-4 mb-3"></span><span class="placeholder col-12" style="height: 280px"></span></div></div></div>
        HTML;
    }
};
?>

@php
    $c     = $this->chart;
    $empty = array_sum($c['submitted']) === 0 && (! $c['money'] || array_sum($c['released']) === 0);
@endphp

<div class="{{ $c['money'] ? 'col-lg-7' : 'col-12' }}">
    <div class="card card-modern h-100">
        <div class="card-header py-3"><h6 class="fw-bold text-navy mb-0"><i class="bi bi-graph-up me-1"></i> Last 12 months</h6></div>
        <div class="card-body">
            @if ($empty)
                <div class="text-muted small py-5 text-center">No activity in the last 12 months.</div>
            @else
                <div wire:ignore><div class="chart-box" data-cif-chart="trend" data-cif-data="{{ json_encode($c) }}"></div></div>
                <div class="small text-muted mt-1">Bars: applications submitted{{ $c['money'] ? ' · Line: CAFI grant released (Maloti)' : '' }}.</div>
            @endif
        </div>
    </div>
</div>