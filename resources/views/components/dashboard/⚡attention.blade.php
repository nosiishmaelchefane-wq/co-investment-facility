<?php

use App\Support\Dashboard;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Lazy;
use Livewire\Component;

/*
| Dashboard · "Needs your attention" — one card per job the user can act on (built in App\Support\Dashboard::tasks()).
| Each card lists up to 5 applications; "Show all" expands the card.
*/
new #[Lazy] class extends Component
{
    /** Cards opened with "Show all" (index => true) */
    public array $expanded = [];

    #[Computed] public function d(): Dashboard
    {
        return new Dashboard(auth()->user());
    }

    public function toggle(int $i): void
    {
        $this->expanded[$i] = ! ($this->expanded[$i] ?? false);
    }

    public function placeholder(): string
    {
        return <<<'HTML'
        <div class="mb-4">
            <h5 class="fw-bold text-navy mb-2"><i class="bi bi-bell me-1"></i> Needs your attention</h5>
            <div class="row g-3">
                <div class="col-md-6 col-xxl-4"><div class="task-card p-3 placeholder-glow"><span class="placeholder col-7 mb-2"></span><span class="placeholder col-12"></span><span class="placeholder col-10"></span></div></div>
                <div class="col-md-6 col-xxl-4"><div class="task-card p-3 placeholder-glow"><span class="placeholder col-6 mb-2"></span><span class="placeholder col-12"></span><span class="placeholder col-9"></span></div></div>
                <div class="col-md-6 col-xxl-4 d-none d-xxl-block"><div class="task-card p-3 placeholder-glow"><span class="placeholder col-8 mb-2"></span><span class="placeholder col-12"></span><span class="placeholder col-8"></span></div></div>
            </div>
        </div>
        HTML;
    }
};
?>

@php
    $tasks = $this->d->tasks();
    $total = $this->d->taskCount();
@endphp

<div class="mb-4">
    <div class="d-flex align-items-center gap-2 mb-2">
        <h5 class="fw-bold text-navy mb-0"><i class="bi bi-bell me-1"></i> Needs your attention</h5>
        @if ($total) <span class="badge rounded-pill bg-danger">{{ $total }}</span> @endif
    </div>

    @if (empty($tasks))
        <div class="card card-modern">
            <div class="empty-state py-4">
                <div class="empty-icon"><i class="bi bi-check2-all"></i></div>
                <h6 class="fw-bold text-navy mb-1">You're all caught up</h6>
                <p class="text-muted small mb-0">New work appears here as soon as an application reaches a step you're responsible for.</p>
            </div>
        </div>
    @else
        <div class="row g-3">
            @foreach ($tasks as $i => $t)
                @php $open = $expanded[$i] ?? false; $count = $t['items']->count(); @endphp
                <div class="col-md-6 col-xxl-4" wire:key="task-{{ $i }}">
                    <div class="task-card">
                        <div class="task-head">
                            <span class="stat-icon tone-{{ $t['tone'] }} task-icon"><i class="bi {{ $t['icon'] }}"></i></span>
                            <div>
                                <div class="fw-semibold text-navy">{{ $t['title'] }}</div>
                                <div class="small text-muted">{{ $t['hint'] }}</div>
                                <span class="pill pill-muted role-pill mt-1"><i class="bi bi-person"></i> {{ $t['role'] }}</span>
                            </div>
                            <span class="task-count">{{ $count }}</span>
                        </div>
                        <ul class="task-list">
                            @foreach ($open ? $t['items'] : $t['items']->take(5) as $it)
                                <li>
                                    <a href="{{ $it['url'] }}">
                                        <span class="task-ref">{{ $it['ref'] }}</span>
                                        <span class="small text-truncate">{{ $it['name'] }}@if ($it['meta'])<span class="text-muted"> — {{ $it['meta'] }}</span>@endif</span>
                                        <i class="bi bi-chevron-right ms-auto small text-muted"></i>
                                    </a>
                                </li>
                            @endforeach
                            @if ($count > 5)
                                <li class="pt-1">
                                    <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none" wire:click="toggle({{ $i }})">
                                        {{ $open ? 'Show fewer' : 'Show all '.$count }}
                                    </button>
                                </li>
                            @endif
                        </ul>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>