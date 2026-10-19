<?php

use App\Support\Dashboard;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Lazy;
use Livewire\Component;

/* Dashboard · Gate 5 & disbursements: grant, released, still to be disbursed and the next tranche per transaction */
new #[Lazy] class extends Component
{
    /** Rows shown before "Show all" */
    public int $limit = 10;

    #[Computed] public function d(): Dashboard
    {
        return new Dashboard(auth()->user());
    }

    public function showAll(): void
    {
        $this->limit = 1000;
    }

    public function placeholder(): string
    {
        return <<<'HTML'
        <div class="card card-modern mb-4"><div class="card-body placeholder-glow"><span class="placeholder col-4 mb-3"></span><span class="placeholder col-12 mb-2"></span><span class="placeholder col-12 mb-2"></span><span class="placeholder col-12"></span></div></div>
        HTML;
    }
};
?>

@php
    $D     = \App\Support\Dashboard::class;
    $d     = $this->d;
    $show  = $d->canSeeMoney();
    $rows  = $show ? $d->disbursements() : collect();
    $money = $show ? $d->money() : null;
    $m     = fn ($v) => $D::m($v);
    $u     = fn ($v) => $D::u($v);
@endphp

<div>
    @if ($rows->isNotEmpty())
        <div class="card card-modern mb-4">
            <div class="card-header py-3 d-flex flex-wrap align-items-center gap-2">
                <h6 class="fw-bold text-navy mb-0 me-auto"><i class="bi bi-cash-stack me-1"></i> Gate 5 &amp; disbursements</h6>
                <span class="small text-muted">{{ $money['cleared'] }} cleared · {{ $m($money['toDisburse']) }} still to be disbursed</span>
            </div>
            <div class="table-responsive">
                <table class="table table-modern align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Transaction</th>
                            <th class="text-end">Approved grant</th>
                            <th class="text-end">Released</th>
                            <th class="text-end">Still to be disbursed</th>
                            <th style="min-width: 160px">Progress</th>
                            <th>Next tranche</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows->take($limit) as $r)
                            @php [$sl, $sc] = $D::STATUSES[$r->eoi->status] ?? [ucfirst($r->eoi->status), 'status-draft']; @endphp
                            <tr wire:key="disb-{{ $r->eoi->id }}">
                                <td>
                                    <a href="{{ $D::eoiUrl($r->eoi->id, 'g5') }}" class="link-strong">{{ $r->eoi->reference }}</a>
                                    <div class="small text-muted">{{ $r->eoi->business_name ?: $r->eoi->user?->name }}</div>
                                    <span class="badge {{ $sc }} mt-1">{{ $sl }}</span>
                                </td>
                                <td class="text-end">{{ $m($r->grant) }}<div class="money-sub">{{ $u($r->grant) }}</div></td>
                                <td class="text-end text-success">{{ $m($r->released) }}<div class="money-sub">{{ $r->paid }} of {{ $r->tranches }} {{ \Illuminate\Support\Str::plural('tranche', $r->tranches) }}</div></td>
                                <td class="text-end fw-semibold text-navy">{{ $m($r->left) }}<div class="money-sub fw-normal">{{ $u($r->left) }}</div></td>
                                <td>
                                    <div class="progress progress-thin" role="progressbar" aria-valuenow="{{ $r->pct }}" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar bg-success" style="width: {{ $r->pct }}%"></div></div>
                                    <div class="money-sub mt-1">{{ $r->pct }}%@if ($r->last) · last {{ $r->last->format('d M Y') }}@endif</div>
                                </td>
                                <td class="small">
                                    @if ($r->grant > 0 && $r->left <= 0.01)
                                        <span class="pill pill-ok"><i class="bi bi-check-lg"></i> Fully disbursed</span>
                                    @elseif ($r->next)
                                        <span class="fw-semibold">{{ $r->next->label }}</span> · {{ $m($r->next->amount) }}
                                        <div class="text-muted">{{ $r->cleared ? 'Ready for Finance' : 'Awaiting clearance' }}</div>
                                    @else
                                        <span class="text-muted">Not scheduled yet</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($rows->count() > $limit)
                <div class="card-footer small d-flex align-items-center">
                    <span class="text-muted me-auto">Showing {{ $limit }} of {{ $rows->count() }}</span>
                    <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none" wire:click="showAll">Show all</button>
                </div>
            @endif
        </div>
    @endif
</div>