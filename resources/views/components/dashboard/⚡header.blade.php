<?php

use App\Support\Dashboard;
use Livewire\Attributes\Computed;
use Livewire\Component;

/* Dashboard · greeting, role and how many items are waiting for the user */
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
    $n     = $d->taskCount();
    $hour  = (int) now()->format('G');
    $hello = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
    $name  = \Illuminate\Support\Str::before(trim(auth()->user()->name), ' ') ?: auth()->user()->name;
@endphp

<div class="d-flex flex-wrap align-items-center gap-3 mb-4">
    <div class="me-auto">
        <h2 class="page-heading mb-1">{{ $hello }}, {{ $name }}</h2>
        <p class="page-subtitle mb-0">
            <span class="pill pill-muted role-pill me-1"><i class="bi bi-person-badge"></i> {{ $d->roleLabel() }}</span>
            {{ now()->format('l, d F Y') }} ·
            @if ($n)
                <strong>{{ $n }}</strong> {{ \Illuminate\Support\Str::plural('item', $n) }} {{ $n === 1 ? 'needs' : 'need' }} your attention.
            @else
                Nothing is waiting for you right now.
            @endif
        </p>
    </div>
    <a href="{{ route('expression-of-interest.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-list-ul me-1"></i> All applications</a>
    <a href="{{ route('dashboard') }}" class="btn btn-cta btn-sm"><i class="bi bi-arrow-clockwise me-1"></i> Refresh</a>
</div>