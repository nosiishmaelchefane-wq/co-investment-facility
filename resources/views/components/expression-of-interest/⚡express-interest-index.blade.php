<?php

use App\Models\EnterpriseProfile;
use App\Models\ExpressionOfInterest;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

/*
| EOI list.
|   Enterprise (eoi.submit)  → all of THEIR applications (they can apply as many times as they like)
|                              New applications only once the enterprise profile is 100% complete.
|   Staff (eoi.view-all)     → every application, with the applicant shown
| Search + status filter run on the server (wire:model.live) — no JavaScript.
*/
new class extends Component
{
    /** Kept in the URL, so filters survive a refresh / back button */
    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $status = '';

    /** Status groups used by the stat cards */
    public const GROUPS = [
        'under_review' => ['submitted', 'in_review'],
    ];

    /** Statuses a filter value stands for */
    protected function statusesFor(string $value): array
    {
        return self::GROUPS[$value] ?? [$value];
    }

    /** Staff see every application; enterprises see their own. */
    #[Computed]
    public function seesAll(): bool
    {
        $user = auth()->user();

        return ! $user->hasRole('enterprise') && ($user->can('eoi.view-all') || $user->can('gate3.score'));
    }

    /** The logged-in enterprise's profile (unsaved if never started) */
    #[Computed]
    public function profile(): ?EnterpriseProfile
    {
        return auth()->user()->can('eoi.submit') ? EnterpriseProfile::forUser(auth()->user()) : null;
    }

    /** Applying needs a complete enterprise profile */
    #[Computed]
    public function profileComplete(): bool
    {
        return (bool) $this->profile?->exists && $this->profile->isComplete();
    }

    /** All applications in scope (for the stat cards) */
    #[Computed]
    public function scoped()
    {
        return ExpressionOfInterest::query()
            ->visibleTo(auth()->user())
            ->get(['id', 'status']);
    }

    /** Filtered list for the table */
    #[Computed]
    public function eois()
    {
        $term = trim($this->search);

        return ExpressionOfInterest::query()
            ->with('user:id,name,email')
            ->visibleTo(auth()->user())
            ->when($term !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('reference', 'like', "%{$term}%")
                ->orWhere('business_name', 'like', "%{$term}%")
                ->orWhere('district', 'like', "%{$term}%")
                ->orWhere('investor_name', 'like', "%{$term}%")
                ->when($this->seesAll, fn ($q) => $q->orWhereHas('user', fn ($u) => $u
                    ->where('name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%")))))
            ->when($this->status !== '', fn ($q) => $q->whereIn('status', $this->statusesFor($this->status)))
            ->latest()
            ->get();
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'status');
    }

    /** Stat card click → filter by that status (click again to clear) */
    public function filterStatus(string $value): void
    {
        $this->status = $this->status === $value ? '' : $value;
    }

    /** Apply button → open the drawer with an empty form (a NEW application every time). */
    public function openApp(): void
    {
        abort_unless(auth()->user()->can('eoi.submit'), 403);

        if (! $this->profileComplete) {
            $this->dispatch('notify', type: 'warning', message: 'Complete your enterprise profile before applying.');
            return;
        }

        $this->dispatch('open-application');
    }

    /** Edit button → open the drawer in edit mode for this application. */
    public function editApp(int $id): void
    {
        $this->dispatch('edit-application', id: $id);
    }

    public function withdraw(int $id): void
    {
        $eoi = ExpressionOfInterest::ownedBy(auth()->id())->find($id);

        if (! $eoi || ! $eoi->isWithdrawable()) {
            $this->dispatch('notify', type: 'error', message: 'This application can no longer be withdrawn.');
            return;
        }

        $eoi->update([
            'status'       => ExpressionOfInterest::STATUS_WITHDRAWN,
            'withdrawn_at' => now(),
        ]);

        $this->refreshList();
        $this->dispatch('notify', type: 'success', message: "Application {$eoi->reference} has been withdrawn.");
    }

    /** Re-render after the drawer saves (new or edited application). */
    #[On('application-saved')]
    public function refreshList(): void
    {
        unset($this->eois, $this->scoped);
    }
};
?>

{{-- ONE root element: everything (markup + drawer) lives inside this div --}}
<div class="container-fluid p-3">
    @php
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
            'authorisation'  => ['Final authorisation', 'status-approved', 'bi-patch-check'],
            'authorisation_hold' => ['Authorisation on hold', 'status-referred', 'bi-pause-circle'],
            'authorised'     => ['Authorised',     'status-approved',  'bi-patch-check-fill'],
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
    @endphp

    {{-- ============================ HEADER ============================ --}}
    <div class="d-flex flex-wrap align-items-center gap-3 mb-4">
        <div class="me-auto">
            <h2 class="page-heading">Expressions of Interest</h2>
            <p class="page-subtitle">
                {{ $seesAll ? 'All applications to the CAFI Co-Investment Facility.' : 'Track your applications to the CAFI Co-Investment Facility. You can apply for more than one transaction.' }}
            </p>
        </div>
        @if ($canApply)
            <button type="button" class="btn btn-cta" wire:click="openApp">
                <i class="bi bi-plus-lg me-1"></i> {{ $all->isEmpty() ? 'Apply for Co-Investment' : 'New application' }}
            </button>
        @endif
    </div>

    {{-- ============================ PROFILE NOT COMPLETE ============================ --}}
    @if ($isEnterprise && ! $canApply)
        @php
            $done    = $profile?->exists ? $profile->completedCount() : 0;
            $total   = count(\App\Models\EnterpriseProfile::SECTIONS);
            $pending = $profile?->exists
                ? collect($profile->sectionStatus())->reject()->keys()->map(fn ($k) => \App\Models\EnterpriseProfile::SECTIONS[$k][0])->implode(', ')
                : null;
        @endphp
        <div class="callout callout-warning d-flex flex-wrap align-items-center gap-3 mb-4">
            <i class="bi bi-building-exclamation fs-4"></i>
            <div class="me-auto">
                <div class="fw-semibold">Complete your enterprise profile to apply</div>
                <div class="small">
                    @if ($profile?->exists)
                        {{ $done }} of {{ $total }} sections complete. Still to do: {{ $pending }}.
                    @else
                        You haven't started your enterprise profile yet. It takes 4 short sections.
                    @endif
                </div>
            </div>
            <a href="{{ route('profile.index') }}" class="btn btn-brand btn-sm">
                <i class="bi bi-pencil-square me-1"></i> {{ $profile?->exists ? 'Complete profile' : 'Start profile' }}
            </a>
        </div>
    @endif

    {{-- ============================ STATS ============================ --}}
    <div class="row g-3 mb-4">
        @foreach ([
            ['Total applications', $all->count(),                    'bi-collection',      'primary', ''],
            ['Drafts',             $count('draft'),                  'bi-pencil-square',   'muted',   'draft'],
            ['Under review',       $count('submitted', 'in_review'), 'bi-hourglass-split', 'warning', 'under_review'],
            ['Invited to CI',      $count('invited'),                'bi-patch-check',     'accent',  'invited'],
        ] as [$label, $value, $icon, $tone, $key])
            <div class="col-6 col-xl-3">
                <button type="button" class="stat-card w-100 text-start border-0 {{ $status === $key && $key !== '' ? 'is-active' : '' }}"
                        wire:click="{{ $key === '' ? 'clearFilters' : "filterStatus('$key')" }}" title="{{ $key === '' ? 'Show all' : 'Filter: '.$label }}">
                    <span class="stat-icon tone-{{ $tone }}"><i class="bi {{ $icon }}"></i></span>
                    <div>
                        <div class="stat-value">{{ $value }}</div>
                        <div class="stat-label">{{ $label }}</div>
                    </div>
                </button>
            </div>
        @endforeach
    </div>

    {{-- ============================ TABLE ============================ --}}
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
                        <option value="">All statuses ({{ $all->count() }})</option>
                        <option value="under_review">Under review — submitted + in review ({{ $count('submitted', 'in_review') }})</option>
                        @foreach ($statuses as $key => [$label])
                            <option value="{{ $key }}">{{ $label }} ({{ $count($key) }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto" wire:loading wire:target="search, status, filterStatus, clearFilters">
                    <span class="spinner-border spinner-border-sm text-muted"></span>
                </div>
                @if ($filtered)
                    <div class="col-md-auto">
                        <button type="button" class="btn btn-link text-decoration-none text-secondary px-1" wire:click="clearFilters">
                            <i class="bi bi-x-lg me-1"></i> Clear
                        </button>
                    </div>
                @endif
            </div>
        </div>

        @if ($eois->isEmpty())
            <div class="empty-state">
                <div class="empty-icon"><i class="bi {{ $filtered ? 'bi-search' : 'bi-file-earmark-plus' }}"></i></div>
                @if ($filtered)
                    <h5 class="fw-bold text-navy mb-1">No applications match</h5>
                    <p class="text-muted small mb-3">Try a different search or status.</p>
                    <button type="button" class="btn btn-brand btn-sm" wire:click="clearFilters">Clear filters</button>
                @else
                    <h5 class="fw-bold text-navy mb-1">No applications yet</h5>
                    @if ($canApply)
                        <p class="text-muted small mb-3">Submit an Expression of Interest to be considered for the Co-Investment Facility.</p>
                        <button type="button" class="btn btn-brand btn-sm" wire:click="openApp"><i class="bi bi-plus-lg me-1"></i> Apply now</button>
                    @elseif ($isEnterprise)
                        <p class="text-muted small mb-0">Complete your enterprise profile first — then you can apply.</p>
                    @else
                        <p class="text-muted small mb-0">No applications have been submitted yet.</p>
                    @endif
                @endif
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 table-modern">
                    <thead>
                        <tr>
                            <th class="ps-4">Reference</th>
                            <th>Business</th>
                            @if ($seesAll) <th>Applicant</th> @endif
                            <th class="text-end">Investment</th>
                            <th>Investor</th>
                            <th>Submitted</th>
                            <th>Status</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($eois as $eoi)
                            @php
                                [$sLabel, $sClass, $sIcon] = $statuses[$eoi->status] ?? [ucfirst($eoi->status), 'status-draft', 'bi-circle'];
                                $isOwner = $eoi->user_id === auth()->id();
                                $usd     = $eoi->investment_required_usd;
                            @endphp
                            <tr wire:key="eoi-{{ $eoi->id }}">
                                <td class="ps-4">
                                    <a href="{{ route('expression-of-interest.show', ['id' => $eoi->id]) }}" class="link-strong">{{ $eoi->reference }}</a>
                                </td>
                                <td>
                                    <div class="fw-semibold text-navy">{{ $eoi->business_name ?: 'Untitled draft' }}</div>
                                    <div class="text-muted small"><i class="bi bi-geo-alt me-1"></i>{{ $eoi->district ?: '—' }}</div>
                                </td>
                                @if ($seesAll)
                                    <td class="small">
                                        <div class="fw-semibold text-navy">{{ $eoi->user?->name ?? '—' }}</div>
                                        <div class="text-muted">{{ $eoi->user?->email }}</div>
                                    </td>
                                @endif
                                <td class="text-end">
                                    @if ($usd !== null)
                                        <div class="fw-semibold">M {{ number_format((float) $usd * $rate) }}</div>
                                        <div class="small text-muted">US$ {{ number_format((float) $usd) }}</div>
                                    @else — @endif
                                </td>
                                <td class="small">{{ $investorLabels[$eoi->investor_status] ?? '—' }}</td>
                                <td class="small text-muted">{{ $eoi->submitted_at?->format('d M Y') ?? '—' }}</td>
                                <td><span class="badge {{ $sClass }}"><i class="bi {{ $sIcon }} me-1"></i>{{ $sLabel }}</span></td>
                                <td class="text-end pe-4">
                                    <div class="d-inline-flex gap-1">
                                        <a href="{{ route('expression-of-interest.show', ['id' => $eoi->id]) }}" class="btn btn-icon" title="View application">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        @if ($isOwner && $eoi->isEditable())
                                            <button type="button" class="btn btn-icon" title="Edit" wire:click="editApp({{ $eoi->id }})">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                        @endif
                                        @if ($isOwner && $eoi->isWithdrawable())
                                            <button type="button" class="btn btn-icon btn-icon-danger" title="Withdraw"
                                                    wire:click="withdraw({{ $eoi->id }})"
                                                    wire:confirm="Withdraw {{ $eoi->reference }}? This cannot be undone.">
                                                <i class="bi bi-x-circle"></i>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-footer small text-muted d-flex flex-wrap gap-2 align-items-center">
                <span>Showing {{ $eois->count() }} of {{ $all->count() }} {{ \Illuminate\Support\Str::plural('application', $all->count()) }}</span>
                @if ($status !== '')
                    <span class="pill pill-muted">Status: {{ $status === 'under_review' ? 'Under review' : ($statuses[$status][0] ?? $status) }}</span>
                @endif
                @if ($search !== '')
                    <span class="pill pill-muted">Search: “{{ $search }}”</span>
                @endif
            </div>
        @endif
    </div>

    {{-- Apply / Edit drawer (still needed to edit existing drafts while the profile is incomplete) --}}
    @if ($isEnterprise)
        <livewire:expression-of-interest.drawer.express-interest />
    @endif
</div>