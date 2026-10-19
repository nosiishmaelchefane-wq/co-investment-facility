<?php

use App\Support\Dashboard;
use Livewire\Attributes\Computed;
use Livewire\Component;

/* Dashboard · four headline figures (grant figures only for roles that may see money) */
new class extends Component
{
    #[Computed] public function d(): Dashboard
    {
        return new Dashboard(auth()->user());
    }
};
?>

@php
    $d     = $this->d;
    $t     = $d->totals();
    $money = $d->canSeeMoney() ? $d->money() : null;
    $m     = fn ($v) => \App\Support\Dashboard::m($v);
    $u     = fn ($v) => \App\Support\Dashboard::u($v);

    $cards = [
        ['Applications', $t['applications'], $t['screening'].' awaiting screening', 'bi-collection', 'primary'],
        ['In the pipeline', $t['active'], $t['exited'].' exited (not progressed / withdrawn)', 'bi-diagram-3', 'warning'],
    ];
    if ($money) {
        $cards[] = ['Approved CAFI grant', $m($money['grant']), $u($money['grant']).' · '.$money['transactions'].' '.\Illuminate\Support\Str::plural('transaction', $money['transactions']), 'bi-patch-check', 'accent'];
        $cards[] = ['Disbursed', $m($money['released']), $money['releasedPct'].'% of approved · '.$m($money['toDisburse']).' still to be disbursed', 'bi-cash-coin', 'accent'];
    } else {
        $tasks   = $d->taskCount();
        $cards[] = ['Needs your attention', $tasks, 'across '.count($d->tasks()).' '.\Illuminate\Support\Str::plural('task', count($d->tasks())), 'bi-bell', 'accent'];
        $cards[] = ['Drafts not submitted', $t['drafts'], 'started by enterprises', 'bi-pencil-square', 'muted'];
    }
@endphp

<div class="row g-3 mb-4">
    @foreach ($cards as [$label, $value, $sub, $icon, $tone])
        <div class="col-6 col-xl-3" wire:key="kpi-{{ $loop->index }}">
            <div class="stat-card w-100 h-100">
                <span class="stat-icon tone-{{ $tone }}"><i class="bi {{ $icon }}"></i></span>
                <div>
                    <div class="stat-value">{{ $value }}</div>
                    <div class="stat-label">{{ $label }}</div>
                    <div class="money-sub">{{ $sub }}</div>
                </div>
            </div>
        </div>
    @endforeach
</div>