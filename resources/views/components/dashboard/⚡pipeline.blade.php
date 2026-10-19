<?php

use App\Support\Dashboard;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Lazy;
use Livewire\Component;

/* Dashboard · pipeline by gate: a tile per gate (status chips link to the filtered EOI list) + two charts */
new #[Lazy] class extends Component
{
    #[Computed] public function d(): Dashboard
    {
        return new Dashboard(auth()->user());
    }

    /** Data for the two charts */
    #[Computed] public function chart(): array
    {
        $d = $this->d;
        $s = $d->stageCounts();
        $t = $d->totals();

        return [
            'stages'   => ['labels' => collect(Dashboard::STAGES)->pluck(0)->values()->all(), 'data' => array_values($s)],
            'outcomes' => [
                'labels' => ['In the pipeline', 'Disbursing', 'Not progressed', 'Withdrawn'],
                'data'   => [$t['active'] - $s['disbursing'], $s['disbursing'], $d->count('not_progressed'), $d->count('withdrawn')],
            ],
        ];
    }

    public function placeholder(): string
    {
        return <<<'HTML'
        <div class="card card-modern mb-4">
            <div class="card-header py-3"><h6 class="fw-bold text-navy mb-0"><i class="bi bi-diagram-3 me-1"></i> Pipeline by gate</h6></div>
            <div class="card-body placeholder-glow"><span class="placeholder col-12 mb-3" style="height: 90px"></span><span class="placeholder col-12" style="height: 260px"></span></div>
        </div>
        HTML;
    }
};
?>

@php
    $D      = \App\Support\Dashboard::class;
    $d      = $this->d;
    $counts = $d->statusCounts();
    $t      = $d->totals();
    $chart  = $this->chart;
    $empty  = array_sum($chart['stages']['data']) === 0;
@endphp

<div class="card card-modern mb-4">
    <div class="card-header py-3 d-flex flex-wrap align-items-center gap-2">
        <h6 class="fw-bold text-navy mb-0 me-auto"><i class="bi bi-diagram-3 me-1"></i> Pipeline by gate</h6>
        <span class="small text-muted">{{ $t['active'] }} active · {{ $t['drafts'] }} {{ \Illuminate\Support\Str::plural('draft', $t['drafts']) }} not submitted · {{ $t['exited'] }} exited</span>
    </div>
    <div class="card-body">
        <div class="row g-2 mb-3">
            @foreach ($D::STAGES as $key => [$label, $statuses, $icon])
                <div class="col-6 col-md-4 col-xl-2" wire:key="stage-{{ $key }}">
                    <div class="stage-tile">
                        <div class="small text-muted"><i class="bi {{ $icon }} me-1"></i>{{ $label }}</div>
                        <div class="stage-n">{{ $d->count(...$statuses) }}</div>
                        <div class="d-flex flex-wrap gap-1 mt-1">
                            @foreach ($statuses as $s)
                                @if ($counts[$s] ?? 0)
                                    <a href="{{ route('expression-of-interest.index', ['status' => $s]) }}" class="badge stage-chip {{ $D::STATUSES[$s][1] }}">{{ $D::STATUSES[$s][0] }} {{ $counts[$s] }}</a>
                                @endif
                            @endforeach
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        @if ($empty)
            <div class="text-muted small py-4 text-center">No applications in the pipeline yet — the charts appear once applications are submitted.</div>
        @else
            <div class="row g-4">
                <div class="col-lg-7">
                    <div class="small fw-semibold text-muted mb-1">Applications at each gate</div>
                    <div wire:ignore><div class="chart-box" data-cif-chart="stages" data-cif-data="{{ json_encode($chart['stages']) }}"></div></div>
                </div>
                <div class="col-lg-5">
                    <div class="small fw-semibold text-muted mb-1">Where applications are</div>
                    <div wire:ignore><div class="chart-box" data-cif-chart="outcomes" data-cif-data="{{ json_encode($chart['outcomes']) }}"></div></div>
                </div>
            </div>
        @endif
    </div>
</div>