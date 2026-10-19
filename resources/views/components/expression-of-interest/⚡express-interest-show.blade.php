<?php

use App\Models\ExpressionOfInterest;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

/*
| EOI detail page.
|   Page:  @livewire('expression-of-interest.express-interest-show', ['id' => $id])
|   Who:   the applicant who owns the EOI, or staff with `eoi.view-all`
|   Tabs:  overview (real data) + Investments (reviewers) + G1–G5 (gate components)
*/
new class extends Component
{
    #[Locked]
    public int $eoiId;

    #[Url]
    public string $tab = 'overview';

    public function mount(int $id): void
    {
        $eoi = ExpressionOfInterest::findOrFail($id);

        abort_unless($eoi->isViewableBy(auth()->user()), 403);

        $this->eoiId = $eoi->id;

        if (! in_array($this->tab, $this->allowedTabs(), true)) {
            $this->tab = 'overview';
        }
    }

    #[Computed]
    public function eoi(): ExpressionOfInterest
    {
        return ExpressionOfInterest::with('user:id,name,email')->findOrFail($this->eoiId);
    }

    /** The logged-in user submitted this EOI */
    #[Computed]
    public function isOwner(): bool
    {
        return $this->eoi->user_id === auth()->id();
    }

    public function showTab(string $tab): void
    {
        $this->tab = in_array($tab, $this->allowedTabs(), true) ? $tab : 'overview';
    }

    /** Investments tab: reviewers only (the enterprise manages its investments on its profile) */
    #[Computed]
    public function showInvestments(): bool
    {
        return ! $this->isOwner;
    }

    protected function allowedTabs(): array
    {
        return ['overview', 'g1', 'g2', 'g3', 'g4', 'g5', ...($this->showInvestments ? ['investments'] : [])];
    }

    /** The edit drawer saved, or a gate decision was recorded → reload the record */
    #[On('application-saved')]
    #[On('gate-decided')]
    public function refreshEoi(): void
    {
        unset($this->eoi);
    }

    public function withdraw(): void
    {
        $eoi = $this->eoi;

        if (! $this->isOwner || ! $eoi->isWithdrawable()) {
            $this->dispatch('notify', type: 'error', message: 'This application can no longer be withdrawn.');
            return;
        }

        $eoi->update([
            'status'       => ExpressionOfInterest::STATUS_WITHDRAWN,
            'withdrawn_at' => now(),
        ]);

        unset($this->eoi);
        $this->dispatch('notify', type: 'success', message: "Application {$eoi->reference} has been withdrawn.");
    }
};
?>

<div>
@php
    $eoi     = $this->eoi;
    $isOwner = $this->isOwner;

    /* ---------- Labels (same keys as the apply form) ---------- */
    $labels = [
        'legal_status' => [
            'registered_operating' => 'Registered and operating in Lesotho', 'registered_not_operating' => 'Registered, operations not yet established',
            'registration_in_progress' => 'Registration in progress', 'not_registered' => 'Not registered',
        ],
        'annual_turnover' => [
            'below_500k' => 'Below M500,000', '500k_2m' => 'M500,000 – M2 million', '2m_5m' => 'M2 – M5 million',
            '5m_10m' => 'M5 – M10 million', 'above_10m' => 'Above M10 million',
        ],
        'investment_types' => ['equity' => 'Equity', 'quasi_equity' => 'Quasi-equity', 'debt' => 'Debt'],
        'use_of_funds' => [
            'equipment' => 'Equipment / productive assets', 'business_expansion' => 'Business expansion', 'working_capital' => 'Working capital',
            'market_expansion' => 'Market expansion', 'technology' => 'Technology / systems', 'product_development' => 'Product development', 'other' => 'Other',
        ],
        'investor_status' => [
            'none' => 'No investor currently', 'discussions' => 'In discussions with potential investors', 'interest' => 'Indication of investor interest',
            'loi' => 'Letter of Intent received', 'term_sheet' => 'Term Sheet in place', 'committed' => 'Investment formally committed', 'other' => 'Other',
        ],
        'investor_evidence' => [
            'loi' => 'Letter of Intent', 'term_sheet' => 'Term Sheet', 'investment_agreement' => 'Investment Agreement',
            'other_formal' => 'Other formal evidence', 'none_available' => 'No document available', 'not_applicable' => 'Not applicable',
        ],
        'support_need' => [
            'ci_facility' => 'Has investor commitment — wants to be considered for the CAFI Co-Investment Facility',
            'progress_deal' => 'Has an interested investor — needs support to progress the transaction',
            'find_investor' => 'Investment-ready — needs help finding an investor',
            'readiness' => 'Needs support to become investment-ready',
            'assess_me' => 'Not sure — would like to be assessed',
        ],
    ];
    $label = fn ($field, $value) => $labels[$field][$value] ?? ($value ?: '—');
    $list  = fn ($field, $values) => collect($values ?? [])->map(fn ($v) => $labels[$field][$v] ?? $v)->all();

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
    [$sLabel, $sClass, $sIcon] = $statuses[$eoi->status] ?? [ucfirst($eoi->status), 'status-draft', 'bi-circle'];

    /* ---------- Progress tracker ---------- */
    $stages = [
        ['label' => 'Submitted',          'icon' => 'bi-send'],
        ['label' => 'Initial assessment', 'icon' => 'bi-search'],
        ['label' => 'Pathway decision',   'icon' => 'bi-signpost-split'],
        ['label' => 'Outcome',            'icon' => 'bi-flag'],
    ];
    $stageIndex = match ($eoi->status) {
        'draft', 'withdrawn'        => -1,
        'submitted', 'returned'     => 0,
        'in_review'                 => 1,
        'invited', 'not_progressed', 'due_diligence', 'dd_hold', 'committee', 'ic_deferred', 'authorisation', 'authorisation_hold', 'authorised', 'capital_pending', 'cleared', 'escalated' => 3,
        default                     => 0,
    };

    /* ---------- Money: stored in US$, shown in Maloti with US$ underneath ---------- */
    $rate      = (float) config('cif.usd_to_lsl', 18.00);
    $lsl       = fn ($usd) => 'M '.number_format((float) $usd * $rate);
    $usd       = fn ($amount) => 'US$ '.number_format((float) $amount);
    $required  = (float) ($eoi->investment_required_usd ?? 0);
    $committed = (float) ($eoi->investor_amount_usd ?? 0);
    $coverage  = $required > 0 ? min(100, round($committed / $required * 100)) : 0;
    $gap       = max(0, $required - $committed);
    $wordCount = fn ($text) => str_word_count(strip_tags((string) $text));
    $initials  = fn ($name) => collect(explode(' ', (string) $name))->filter()->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('');

    /* ---------- Activity (from the record's own dates) ---------- */
    $activity = collect([
        $eoi->withdrawn_at ? ['icon' => 'bi-slash-circle', 'tone' => 'muted',   'title' => 'Application withdrawn', 'text' => 'Withdrawn by the applicant.', 'when' => $eoi->withdrawn_at] : null,
        $eoi->status === 'returned' ? ['icon' => 'bi-arrow-return-left', 'tone' => 'warning', 'title' => 'Returned for more information', 'text' => 'Please update and resubmit.', 'when' => $eoi->updated_at] : null,
        $eoi->status === 'in_review' ? ['icon' => 'bi-hourglass-split', 'tone' => 'warning', 'title' => 'Assessment started', 'text' => 'The LEHSFF team is reviewing this EOI.', 'when' => $eoi->updated_at] : null,
        $eoi->submitted_at ? ['icon' => 'bi-send-check', 'tone' => 'accent',  'title' => 'EOI submitted', 'text' => 'Submitted by '.($eoi->representative_name ?: $eoi->user?->name).'.', 'when' => $eoi->submitted_at] : null,
        ['icon' => 'bi-pencil', 'tone' => 'primary', 'title' => 'Application started', 'text' => 'Draft created.', 'when' => $eoi->created_at],
    ])->filter()->sortByDesc('when')->values();

    /* =====================================================================
     | DECISION GATES — state derived from the EOI status for now.
     | Replace with real gate records when the gate logic is built.
     ===================================================================== */
    $latestG1 = $eoi->eligibilityScreenings()->orderByDesc('round')->first();
    $latestG2 = $eoi->dueDiligences()->orderByDesc('round')->first();
    $g1State = match (true) {
        $latestG1?->isDecided() && $latestG1->decision === 'eligible'     => 'passed',
        $latestG1?->isDecided() && $latestG1->decision === 'not_eligible' => 'failed',
        in_array($eoi->status, \App\Models\ExpressionOfInterest::PAST_GATE1, true) => 'passed',
        in_array($eoi->status, ['submitted', 'in_review', 'returned'], true) => 'current',
        $eoi->status === 'not_progressed'                                 => 'failed',
        default                                                           => 'locked',
    };
    $g2State = match (true) {
        $g1State !== 'passed'                                             => 'locked',
        $latestG2?->isDecided() && $latestG2->outcome === 'proceed'       => 'passed',
        $latestG2?->isDecided() && $latestG2->outcome === 'do_not_progress' => 'failed',
        default                                                           => 'current',
    };
    $latestG3 = $eoi->committeeReviews()->orderByDesc('round')->first();
    $g3State = match (true) {
        $g2State !== 'passed'                                                        => 'locked',
        $latestG3?->isDecided() && in_array($latestG3->outcome, ['approve', 'conditional'], true) => 'passed',
        $latestG3?->isDecided() && $latestG3->outcome === 'reject'                   => 'failed',
        default                                                                      => 'current',
    };
    $latestG4 = $eoi->authorisations()->orderByDesc('round')->first();
    $g4State = match (true) {
        $g3State !== 'passed'                                                        => 'locked',
        $latestG4?->outcome === 'authorised'                                         => 'passed',
        $latestG4?->outcome === 'not_authorised'                                     => 'failed',
        default                                                                      => 'current',
    };
    $latestG5 = $eoi->conditionReview;
    $g5State = match (true) {
        $g4State !== 'passed'                => 'locked',
        $latestG5?->outcome === 'cleared'    => 'passed',
        default                              => 'current',
    };
    $gates = [
        'g1' => ['code' => 'G1', 'label' => 'Eligibility Screening', 'short' => 'Eligibility',   'icon' => 'bi-funnel-fill',          'state' => $g1State],
        'g2' => ['code' => 'G2', 'label' => 'Due Diligence & ESGC',  'short' => 'Due Diligence', 'icon' => 'bi-search',               'state' => $g2State],
        'g3' => ['code' => 'G3', 'label' => 'Committee Evaluation',  'short' => 'Committee',     'icon' => 'bi-clipboard2-data-fill', 'state' => $g3State],
        'g4' => ['code' => 'G4', 'label' => 'Final Authorisation',   'short' => 'Authorisation', 'icon' => 'bi-patch-check-fill',     'state' => $g4State],
        'g5' => ['code' => 'G5', 'label' => 'Conditions Precedent',  'short' => 'Conditions',    'icon' => 'bi-list-check',           'state' => $g5State],
    ];
    $gateStates = [
        'passed'  => ['Passed',      'pill-ok',     'bi-check-circle-fill'],
        'current' => ['In progress', 'pill-warn',   'bi-hourglass-split'],
        'failed'  => ['Not passed',  'pill-danger', 'bi-x-circle-fill'],
        'locked'  => ['Locked',      'pill-muted',  'bi-lock-fill'],
    ];
    $gateKeys = array_keys($gates);
@endphp

<div class="container-fluid p-3">

    {{-- ============================ TOP BAR ============================ --}}
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <a href="{{ route('expression-of-interest.index') }}" class="back-link">
            <i class="bi bi-arrow-left"></i> Back to applications
        </a>
        @unless ($isOwner)
            <span class="pill pill-muted ms-auto"><i class="bi bi-eye"></i> Reviewer view</span>
        @endunless
    </div>

    {{-- ============================ HEADER ============================ --}}
    <div class="hero-banner mb-4">
        <div class="hero-glow"></div>
        <div class="d-flex flex-wrap align-items-start gap-3">
            <div class="avatar-sq">{{ mb_strtoupper(mb_substr($eoi->business_name ?: 'E', 0, 1)) }}</div>
            <div class="me-auto">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                    <span class="chip chip-dark">{{ $eoi->reference }}</span>
                    <span class="badge {{ $sClass }}"><i class="bi {{ $sIcon }} me-1"></i>{{ $sLabel }}</span>
                </div>
                <h2 class="hero-title mb-1">{{ $eoi->business_name ?: 'Untitled application' }}</h2>
                <div class="hero-meta">
                    @if ($eoi->district) <span><i class="bi bi-geo-alt"></i> {{ $eoi->district }}</span> @endif
                    @if ($eoi->representative_name) <span><i class="bi bi-person"></i> {{ $eoi->representative_name }}</span> @endif
                    <span><i class="bi bi-calendar3"></i> {{ $eoi->submitted_at ? 'Submitted '.$eoi->submitted_at->format('d M Y') : 'Not submitted' }}</span>
                </div>
            </div>

            @if ($isOwner)
                <div class="d-flex flex-wrap gap-2">
                    @if ($eoi->isEditable())
                        <button type="button" class="btn btn-light btn-sm fw-semibold"
                                wire:click="$dispatch('edit-application', { id: {{ $eoi->id }} })">
                            <i class="bi bi-pencil me-1"></i> Edit
                        </button>
                    @endif
                    @if ($eoi->isWithdrawable())
                        <button type="button" class="btn btn-outline-light btn-sm"
                                wire:click="withdraw"
                                wire:confirm="Withdraw {{ $eoi->reference }}? This cannot be undone.">
                            <i class="bi bi-x-circle me-1"></i> Withdraw
                        </button>
                    @endif
                </div>
            @endif
        </div>

        {{-- Progress --}}
        <div class="tracker">
            @foreach ($stages as $i => $stage)
                <div class="tracker-step {{ $i < $stageIndex ? 'done' : ($i === $stageIndex ? 'current' : '') }}">
                    <span class="tracker-dot"><i class="bi {{ $i < $stageIndex ? 'bi-check-lg' : $stage['icon'] }}"></i></span>
                    <span class="tracker-label">{{ $stage['label'] }}</span>
                </div>
            @endforeach
        </div>
    </div>

    @if ($eoi->status === 'returned' && $isOwner)
        <div class="callout callout-warning d-flex align-items-center gap-3 mb-4">
            <i class="bi bi-arrow-return-left fs-5"></i>
            <div class="flex-grow-1 small">
                <strong>Returned for more information.</strong> Please review the reviewer's comments, update your EOI and resubmit.
                <a href="#" wire:click.prevent="showTab('g1')" class="ms-1">See what needs clarifying</a>
            </div>
            <button type="button" class="btn btn-sm btn-warning fw-semibold" wire:click="$dispatch('edit-application', { id: {{ $eoi->id }} })">Update EOI</button>
        </div>
    @elseif ($eoi->status === 'withdrawn')
        <div class="callout d-flex align-items-center gap-3 mb-4">
            <i class="bi bi-slash-circle fs-5 text-faint"></i>
            <div class="small"><strong>Withdrawn</strong> on {{ $eoi->withdrawn_at?->format('d M Y') }}. No further action will be taken.</div>
        </div>
    @endif

    {{-- ============================ TABS: Overview + Decision Gates ============================ --}}
    <nav class="page-tabs mb-4">
        <a href="#" wire:click.prevent="showTab('overview')" class="{{ $tab === 'overview' ? 'active' : '' }}">
            <i class="bi bi-grid-1x2"></i> Overview
        </a>
        @if ($this->showInvestments)
            <a href="#" wire:click.prevent="showTab('investments')" class="{{ $tab === 'investments' ? 'active' : '' }}">
                <i class="bi bi-bank"></i> Investments
            </a>
        @endif
        <span class="page-tabs-divider" title="Decision gates"></span>
        @foreach ($gates as $key => $g)
            <a href="#" wire:click.prevent="showTab('{{ $key }}')" class="{{ $tab === $key ? 'active' : '' }} is-{{ $g['state'] }}" title="{{ $g['label'] }}">
                <span class="page-tabs-code">{{ $g['code'] }}</span>
                {{ $g['short'] }}
                <i class="bi {{ $gateStates[$g['state']][2] }} page-tabs-state"></i>
            </a>
        @endforeach
    </nav>

    @if ($tab === 'overview')
    {{-- ======================================================================
         OVERVIEW
         ====================================================================== --}}

    {{-- KPIs (Maloti, with US$ underneath) --}}
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="stat-card">
                <span class="stat-icon tone-primary"><i class="bi bi-cash-coin"></i></span>
                <div>
                    <div class="stat-label">Investment required</div>
                    @if ($required)
                        <div class="stat-value sm">{{ $lsl($required) }}</div>
                        <div class="small text-muted">≈ {{ $usd($required) }}</div>
                    @else
                        <div class="stat-value sm">—</div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="stat-card">
                <span class="stat-icon tone-accent"><i class="bi bi-briefcase"></i></span>
                <div>
                    <div class="stat-label">Investor commitment</div>
                    @if ($committed)
                        <div class="stat-value sm">{{ $lsl($committed) }}</div>
                        <div class="small text-muted">≈ {{ $usd($committed) }}</div>
                    @else
                        <div class="stat-value sm">—</div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="stat-card">
                <span class="stat-icon tone-warning"><i class="bi bi-pie-chart"></i></span>
                <div>
                    <div class="stat-label">Funding gap</div>
                    @if ($required)
                        <div class="stat-value sm">{{ $lsl($gap) }}</div>
                        <div class="small text-muted">≈ {{ $usd($gap) }}</div>
                    @else
                        <div class="stat-value sm">—</div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="stat-card">
                <span class="stat-icon tone-muted"><i class="bi bi-people"></i></span>
                <div>
                    <div class="stat-label">Full-time employees</div>
                    <div class="stat-value sm">{{ $eoi->full_time_employees ?? '—' }}</div>
                    <div class="small text-muted">Permanent staff</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        {{-- MAIN --}}
        <div class="col-xl-8">

            <nav class="section-tabs mb-3">
                <a href="#sec-business">Business</a>
                <a href="#sec-operations">Operations</a>
                <a href="#sec-investment">Investment</a>
                <a href="#sec-investor">Investor</a>
                <a href="#sec-support">Support &amp; declaration</a>
            </nav>

            {{-- 1. Business --}}
            <section id="sec-business" class="section-card">
                <div class="section-card-head">
                    <span class="section-no">1</span>
                    <h5>Business details</h5>
                </div>
                <div class="detail-grid">
                    <div><dt>Business name</dt><dd>{{ $eoi->business_name ?: '—' }}</dd></div>
                    <div><dt>Founder / authorised representative</dt><dd>{{ $eoi->representative_name ?: '—' }}</dd></div>
                    <div><dt>Telephone</dt><dd>@if ($eoi->phone)<a href="tel:{{ $eoi->phone }}">{{ $eoi->phone }}</a>@else — @endif</dd></div>
                    <div><dt>Email</dt><dd>@if ($eoi->email)<a href="mailto:{{ $eoi->email }}">{{ $eoi->email }}</a>@else — @endif</dd></div>
                    <div><dt>District</dt><dd>{{ $eoi->district ?: '—' }}</dd></div>
                    <div><dt>Website / social media</dt><dd>{{ $eoi->website ?: '—' }}</dd></div>
                    <div><dt>Legal status</dt>
                        <dd>
                            @if ($eoi->legal_status)
                                <span class="pill {{ $eoi->legal_status === 'registered_operating' ? 'pill-ok' : 'pill-warn' }}">
                                    <i class="bi {{ $eoi->legal_status === 'registered_operating' ? 'bi-check-circle' : 'bi-exclamation-circle' }}"></i>
                                    {{ $label('legal_status', $eoi->legal_status) }}
                                </span>
                            @else — @endif
                        </dd>
                    </div>
                    <div><dt>Operating since</dt>
                        <dd>{{ $eoi->year_commenced ?: '—' }} @if ($eoi->year_commenced) <span class="text-muted">({{ now()->year - $eoi->year_commenced }} yrs)</span> @endif</dd></div>
                </div>
            </section>

            {{-- 2. Operations --}}
            <section id="sec-operations" class="section-card">
                <div class="section-card-head">
                    <span class="section-no">2</span>
                    <h5>What the business does &amp; scale</h5>
                </div>
                @if ($eoi->business_description)
                    <div class="quote">
                        {{ $eoi->business_description }}
                        <div class="quote-meta">{{ $wordCount($eoi->business_description) }} words</div>
                    </div>
                @endif
                <div class="detail-grid mt-3">
                    <div><dt>Annual turnover</dt><dd>{{ $label('annual_turnover', $eoi->annual_turnover) }}</dd></div>
                    <div><dt>Full-time employees</dt><dd>{{ $eoi->full_time_employees ?? '—' }}</dd></div>
                </div>
            </section>

            {{-- 3. Investment --}}
            <section id="sec-investment" class="section-card">
                <div class="section-card-head">
                    <span class="section-no">3</span>
                    <h5>Investment sought</h5>
                </div>
                <div class="detail-grid">
                    <div><dt>Total investment required</dt>
                        <dd>
                            @if ($required)
                                <span class="d-block fs-5 fw-bold text-brand">{{ $lsl($required) }}</span>
                                <span class="small text-muted">≈ {{ $usd($required) }}</span>
                            @else — @endif
                        </dd>
                    </div>
                    <div><dt>Type of investment</dt>
                        <dd class="d-flex flex-wrap gap-1">
                            @forelse ($list('investment_types', $eoi->investment_types) as $t)
                                <span class="tag">{{ $t }}</span>
                            @empty — @endforelse
                        </dd>
                    </div>
                </div>

                @if ($eoi->use_of_funds)
                    <dt class="detail-label mt-3">How the investment would be used</dt>
                    <div class="d-flex flex-wrap gap-2 mt-2">
                        @foreach ($list('use_of_funds', array_diff($eoi->use_of_funds, ['other'])) as $u)
                            <span class="tag tag-accent"><i class="bi bi-check2"></i> {{ $u }}</span>
                        @endforeach
                        @if (in_array('other', $eoi->use_of_funds, true) && $eoi->use_of_funds_other)
                            <span class="tag tag-accent"><i class="bi bi-check2"></i> {{ $eoi->use_of_funds_other }}</span>
                        @endif
                    </div>
                @endif

                @if ($eoi->use_of_funds_description)
                    <div class="quote mt-3">
                        {{ $eoi->use_of_funds_description }}
                        <div class="quote-meta">{{ $wordCount($eoi->use_of_funds_description) }} words</div>
                    </div>
                @endif
            </section>

            {{-- 4. Investor --}}
            <section id="sec-investor" class="section-card">
                <div class="section-card-head">
                    <span class="section-no">4</span>
                    <h5>Investor engagement</h5>
                </div>

                @php
                    $ladder = ['none' => 'None', 'discussions' => 'Discussions', 'interest' => 'Interest', 'loi' => 'LOI', 'term_sheet' => 'Term Sheet', 'committed' => 'Committed'];
                    $pos    = array_search($eoi->investor_status, array_keys($ladder), true);
                @endphp
                <div class="ladder mb-3">
                    @foreach (array_values($ladder) as $i => $stepLabel)
                        <div class="ladder-step {{ $pos !== false && $i <= $pos ? 'on' : '' }} {{ $pos === $i ? 'current' : '' }}">
                            <span></span>
                            <small>{{ $stepLabel }}</small>
                        </div>
                    @endforeach
                </div>

                <div class="detail-grid">
                    <div><dt>Status</dt><dd>{{ $label('investor_status', $eoi->investor_status) }}{{ $eoi->investor_status === 'other' && $eoi->investor_status_other ? ': '.$eoi->investor_status_other : '' }}</dd></div>
                    <div><dt>Investor</dt><dd>{{ $eoi->investor_name ?: '—' }}</dd></div>
                    <div><dt>Amount indicated / committed</dt>
                        <dd>@if ($committed) {{ $lsl($committed) }} <span class="small text-muted">(≈ {{ $usd($committed) }})</span> @else — @endif</dd>
                    </div>
                    <div><dt>Evidence</dt>
                        <dd class="d-flex flex-wrap gap-1">
                            @forelse ($list('investor_evidence', $eoi->investor_evidence) as $ev)
                                <span class="tag"><i class="bi bi-file-earmark-check"></i> {{ $ev }}</span>
                            @empty — @endforelse
                        </dd>
                    </div>
                </div>

                @if ($required > 0)
                    <div class="bg-light rounded-3 p-3 mt-3">
                        <div class="d-flex justify-content-between small mb-1">
                            <span class="fw-semibold text-navy">Investor coverage of requirement</span>
                            <span class="fw-bold text-brand">{{ $coverage }}%</span>
                        </div>
                        <div class="progress" style="height: 10px;">
                            <div class="progress-bar bg-accent" style="width: {{ $coverage }}%;"></div>
                        </div>
                        <div class="d-flex justify-content-between flex-wrap gap-2 small text-muted mt-1">
                            <span>Investor: {{ $lsl($committed) }} (≈ {{ $usd($committed) }})</span>
                            <span>Gap: {{ $lsl($gap) }} (≈ {{ $usd($gap) }})</span>
                        </div>
                    </div>
                @endif
            </section>

            {{-- 5. Support + declaration --}}
            <section id="sec-support" class="section-card">
                <div class="section-card-head">
                    <span class="section-no">5</span>
                    <h5>Support requested &amp; declaration</h5>
                </div>
                @if ($eoi->support_need)
                    <div class="callout d-flex align-items-center gap-3">
                        <i class="bi bi-signpost-split"></i>
                        <div>{{ $label('support_need', $eoi->support_need) }}</div>
                    </div>
                @endif

                <div class="border rounded-3 p-3 mt-3">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <i class="bi {{ $eoi->declaration_agree ? 'bi-patch-check-fill text-accent' : 'bi-x-circle text-danger' }} fs-5"></i>
                        <strong class="text-navy">{{ $eoi->declaration_agree ? 'Declaration accepted' : 'Declaration not accepted' }}</strong>
                    </div>
                    <p class="small text-muted mb-2">
                        Applicant confirmed the information is accurate and consented to LEHSFF/CAFI using it to assess the enterprise for
                        investment facilitation, investor matchmaking and/or participation in the CAFI Co-Investment Facility.
                    </p>
                    @if ($eoi->declaration_name)
                        <div class="small"><span class="text-muted">Signed:</span> <strong>{{ $eoi->declaration_name }}</strong>
                            @if ($eoi->declaration_date) <span class="text-muted ms-2">on</span> {{ $eoi->declaration_date->format('d M Y') }} @endif
                        </div>
                    @endif
                </div>
            </section>
        </div>

        {{-- SIDEBAR --}}
        <div class="col-xl-4">
            <div class="sticky-side">

                {{-- Gate summary --}}
                <div class="card card-modern mb-3">
                    <div class="card-body">
                        <div class="card-title-sm"><i class="bi bi-signpost-2"></i> Decision gates</div>
                        @foreach ($gates as $key => $g)
                            @php [$gsLabel, $gsClass, $gsIcon] = $gateStates[$g['state']]; @endphp
                            <a href="#" wire:click.prevent="showTab('{{ $key }}')" class="gate-row">
                                <span class="page-tabs-code">{{ $g['code'] }}</span>
                                <span class="flex-grow-1 small fw-semibold text-navy">{{ $g['label'] }}</span>
                                <span class="pill {{ $gsClass }}"><i class="bi {{ $gsIcon }}"></i> {{ $gsLabel }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>

                {{-- Next step --}}
                <div class="card card-modern mb-3">
                    <div class="card-body">
                        <div class="card-title-sm"><i class="bi bi-compass"></i> What happens next</div>
                        @if (! $isOwner && in_array($eoi->status, ['submitted', 'in_review'], true))
                            <p class="small text-muted mb-2">This EOI is waiting for eligibility screening.</p>
                            <button type="button" class="btn btn-sm btn-brand w-100" wire:click="showTab('g1')"><i class="bi bi-funnel me-1"></i> Open G1 · Eligibility screening</button>
                        @elseif (in_array($eoi->status, ['submitted', 'in_review'], true))
                            <p class="small text-muted mb-0">The LEHSFF team is reviewing your EOI. You'll be contacted with a recommended pathway, usually within <strong>10 working days</strong>.</p>
                        @elseif ($eoi->status === 'invited')
                            <p class="small text-muted mb-0">{{ $isOwner ? "You've been invited to complete the formal Co-Investment application and due diligence." : 'Invited to the Co-Investment application. Due diligence (G2) is next.' }}</p>
                        @elseif ($eoi->status === 'returned')
                            <p class="small text-muted mb-0">{{ $isOwner ? 'Update your EOI with the requested information and resubmit.' : 'Waiting for the applicant to update and resubmit.' }}</p>
                        @elseif ($eoi->status === 'draft')
                            <p class="small text-muted mb-2">This EOI hasn't been submitted yet.</p>
                            @if ($isOwner)
                                <button type="button" class="btn btn-sm btn-brand w-100" wire:click="$dispatch('edit-application', { id: {{ $eoi->id }} })">Continue &amp; submit</button>
                            @endif
                        @else
                            <p class="small text-muted mb-0">No further action is required.</p>
                        @endif
                    </div>
                </div>

                {{-- Contact --}}
                <div class="card card-modern mb-3">
                    <div class="card-body">
                        <div class="card-title-sm"><i class="bi bi-person-badge"></i> Contact</div>
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <span class="avatar">{{ $initials($eoi->representative_name ?: $eoi->user?->name) }}</span>
                            <div>
                                <div class="fw-semibold text-navy">{{ $eoi->representative_name ?: $eoi->user?->name }}</div>
                                <div class="small text-muted">Founder / authorised representative</div>
                            </div>
                        </div>
                        @if ($eoi->email) <a href="mailto:{{ $eoi->email }}" class="contact-line"><i class="bi bi-envelope"></i> {{ $eoi->email }}</a> @endif
                        @if ($eoi->phone) <a href="tel:{{ $eoi->phone }}" class="contact-line"><i class="bi bi-telephone"></i> {{ $eoi->phone }}</a> @endif
                        @if ($eoi->website) <span class="contact-line"><i class="bi bi-globe"></i> {{ $eoi->website }}</span> @endif
                        @if (! $isOwner && $eoi->user_id && \Illuminate\Support\Facades\Route::has('profile.index'))
                            <a href="{{ route('profile.index', $eoi->user_id) }}" class="contact-line"><i class="bi bi-building"></i> View enterprise profile</a>
                        @endif
                    </div>
                </div>

                {{-- Documents --}}
                <div class="card card-modern mb-3">
                    <div class="card-body">
                        <div class="card-title-sm"><i class="bi bi-folder2-open"></i> Investor evidence</div>
                        @forelse ($list('investor_evidence', array_diff($eoi->investor_evidence ?? [], ['none_available', 'not_applicable'])) as $doc)
                            <div class="file-row">
                                <span class="file-row-icon"><i class="bi bi-file-earmark-pdf"></i></span>
                                <div class="flex-grow-1 small">
                                    <div class="fw-semibold text-navy">{{ $doc }}</div>
                                    <div class="text-muted">Requested at CI application stage</div>
                                </div>
                            </div>
                        @empty
                            <p class="small text-muted mb-0">No documents listed.</p>
                        @endforelse
                    </div>
                </div>

                {{-- Activity --}}
                <div class="card card-modern">
                    <div class="card-body">
                        <div class="card-title-sm"><i class="bi bi-clock-history"></i> Activity</div>
                        <ul class="activity">
                            @foreach ($activity as $a)
                                <li>
                                    <span class="activity-icon tone-{{ $a['tone'] }}"><i class="bi {{ $a['icon'] }}"></i></span>
                                    <div>
                                        <div class="fw-semibold small text-navy">{{ $a['title'] }}</div>
                                        <div class="small text-muted">{{ $a['text'] }}</div>
                                        <div class="activity-time" title="{{ $a['when']?->format('d M Y H:i') }}">{{ $a['when']?->diffForHumans() }}</div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @elseif ($tab === 'investments')
    {{-- ======================================================================
         INVESTMENTS (reviewers)
         ====================================================================== --}}
    <livewire:expression-of-interest.investments.investment-history :eoi-id="$eoi->id" wire:key="investments-{{ $eoi->id }}" />

    @else
    {{-- ======================================================================
         DECISION GATE TAB
         ====================================================================== --}}
    @php
        $gate = $gates[$tab];
        [$gsLabel, $gsClass, $gsIcon] = $gateStates[$gate['state']];
        $i    = array_search($tab, $gateKeys, true);
        $prev = $i > 0 ? $gates[$gateKeys[$i - 1]] : null;
    @endphp

    <div class="card card-modern mb-4">
        <div class="card-body d-flex flex-wrap align-items-center gap-3">
            <span class="stat-icon tone-primary"><i class="bi {{ $gate['icon'] }}"></i></span>
            <div class="me-auto">
                <div class="small text-muted fw-semibold">Gate {{ substr($gate['code'], 1) }} of {{ count($gates) }}</div>
                <h5 class="mb-0 text-navy fw-bold">{{ $gate['label'] }}</h5>
            </div>
            <span class="pill {{ $gsClass }}"><i class="bi {{ $gsIcon }}"></i> {{ $gsLabel }}</span>
        </div>
    </div>

    @if ($gate['state'] === 'locked')
        <div class="callout d-flex align-items-center gap-3 mb-4">
            <i class="bi bi-lock-fill fs-5 text-faint"></i>
            <div class="small">
                <strong>This gate is locked.</strong>
                It opens once {{ $prev ? $prev['code'].' — '.$prev['label'] : 'the EOI is submitted' }} has been passed. Below is a preview of what will be assessed.
            </div>
        </div>
    @endif

    <div class="row g-4">
        <div class="col-xl-8">
            @if ($tab === 'g1') <livewire:expression-of-interest.eligibility.check-eligibility :eoi-id="$eoi->id" wire:key="g1-{{ $eoi->id }}" /> @endif
            @if ($tab === 'g2') <livewire:expression-of-interest.due-diligence.due-deligence-check :eoi-id="$eoi->id" wire:key="g2-{{ $eoi->id }}" /> @endif
            @if ($tab === 'g3') <livewire:expression-of-interest.committee.committee-review :eoi-id="$eoi->id" wire:key="g3-{{ $eoi->id }}" /> @endif
            @if ($tab === 'g4') <livewire:expression-of-interest.authorisation.authorise-application :eoi-id="$eoi->id" wire:key="g4-{{ $eoi->id }}" /> @endif
            @if ($tab === 'g5') <livewire:expression-of-interest.conditions.condition-review :eoi-id="$eoi->id" wire:key="g5-{{ $eoi->id }}" /> @endif
        </div>

        <div class="col-xl-4">
            <div class="sticky-side">
                <div class="card card-modern">
                    <div class="card-body">
                        <div class="card-title-sm"><i class="bi bi-signpost-2"></i> All gates</div>
                        @foreach ($gates as $key => $g)
                            @php [$l, $c, $ic] = $gateStates[$g['state']]; @endphp
                            <a href="#" wire:click.prevent="showTab('{{ $key }}')" class="gate-row {{ $key === $tab ? 'active' : '' }}">
                                <span class="page-tabs-code">{{ $g['code'] }}</span>
                                <span class="flex-grow-1 small fw-semibold text-navy">{{ $g['label'] }}</span>
                                <span class="pill {{ $c }}" title="{{ $l }}"><i class="bi {{ $ic }}"></i></span>
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>

{{-- Edit drawer (opened with $dispatch('edit-application', { id })) — applicant only --}}
@if ($isOwner)
    <livewire:expression-of-interest.drawer.express-interest />
@endif
</div>