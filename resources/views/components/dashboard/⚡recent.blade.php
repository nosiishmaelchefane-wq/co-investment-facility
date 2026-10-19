<?php

use App\Support\Dashboard;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Lazy;
use Livewire\Component;

/* Dashboard · most recently updated applications (drafts left out) */
new #[Lazy] class extends Component
{
    public int $limit = 8;

    #[Computed] public function d(): Dashboard
    {
        return new Dashboard(auth()->user());
    }

    public function placeholder(): string
    {
        return <<<'HTML'
        <div class="card card-modern mb-2"><div class="card-body placeholder-glow"><span class="placeholder col-3 mb-3"></span><span class="placeholder col-12 mb-2"></span><span class="placeholder col-12 mb-2"></span><span class="placeholder col-12"></span></div></div>
        HTML;
    }
};
?>

@php
    $D      = \App\Support\Dashboard::class;
    $recent = $this->d->recent($limit);
@endphp

<div class="card card-modern mb-2">
    <div class="card-header py-3 d-flex align-items-center">
        <h6 class="fw-bold text-navy mb-0 me-auto"><i class="bi bi-clock-history me-1"></i> Recently updated</h6>
        <a href="{{ route('expression-of-interest.index') }}" class="small text-decoration-none">View all <i class="bi bi-arrow-right"></i></a>
    </div>
    @if ($recent->isEmpty())
        <div class="empty-state py-4">
            <div class="empty-icon"><i class="bi bi-file-earmark-plus"></i></div>
            <h6 class="fw-bold text-navy mb-1">No applications yet</h6>
            <p class="text-muted small mb-0">Applications appear here once enterprises submit them.</p>
        </div>
    @else
        <div class="table-responsive">
            <table class="table table-modern align-middle mb-0">
                <thead><tr><th>Reference</th><th>Business</th><th>Applicant</th><th>Status</th><th class="text-end">Updated</th></tr></thead>
                <tbody>
                    @foreach ($recent as $e)
                        @php [$sl, $sc, $si] = $D::STATUSES[$e->status] ?? [ucfirst($e->status), 'status-draft', 'bi-circle']; @endphp
                        <tr wire:key="recent-{{ $e->id }}">
                            <td><a href="{{ $D::eoiUrl($e->id) }}" class="link-strong">{{ $e->reference }}</a></td>
                            <td class="small">{{ $e->business_name ?: '—' }}</td>
                            <td class="small text-muted">{{ $e->user?->name }}</td>
                            <td><span class="badge {{ $sc }}"><i class="bi {{ $si }} me-1"></i>{{ $sl }}</span></td>
                            <td class="text-end small text-muted" title="{{ $e->updated_at?->format('d M Y H:i') }}">{{ $e->updated_at?->diffForHumans() }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>