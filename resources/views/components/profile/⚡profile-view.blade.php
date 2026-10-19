<?php

use App\Models\EnterpriseDocument;
use App\Models\EnterpriseProfile;
use App\Models\User;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/*
| Tab 1 · Profile — read-only view of the 11 sections.
| Used for the enterprise's own profile (with edit buttons) and by staff (read-only).
| "Edit" buttons dispatch edit-section(step) → parent opens the form at that step.
*/
new class extends Component
{
    /** Whose profile is shown */
    #[Locked]
    public int $userId;

    /** Own profile → edit buttons; staff → read-only */
    #[Locked]
    public bool $canEdit = false;

    public function mount(int $userId, bool $canEdit = false): void
    {
        $me = auth()->user();

        // Own profile, or staff allowed to view enterprise profiles
        abort_unless($userId === $me->id || $me->can('eoi.view-all') || $me->can('users.manage'), 403);

        $this->userId  = $userId;
        $this->canEdit = $canEdit && $userId === $me->id && $me->can('eoi.submit');
    }

    #[Computed]
    public function profile(): EnterpriseProfile
    {
        return EnterpriseProfile::forUser(User::findOrFail($this->userId));
    }
};
?>

<div>
    @php
        $p        = $this->profile;
        $sections = \App\Models\EnterpriseProfile::SECTIONS;
        $status   = $p->exists ? $p->sectionStatus() : array_fill_keys(array_keys($sections), false);
        $keys     = array_keys($sections);

        $rate = (float) config('cif.usd_to_lsl', 18.00);
        $lsl  = fn ($m) => $m === null || $m === '' ? '—' : 'M '.number_format((float) $m);
        $usd  = fn ($m) => 'US$ '.number_format((float) $m / max($rate, 0.0001));
        $dash = fn ($v) => filled($v) ? $v : '—';
        $initials = fn ($name) => collect(explode(' ', (string) $name))->filter()->map(fn ($x) => mb_substr($x, 0, 1))->take(2)->implode('');

        // Data for the shared section heading partial
        $head = function (string $key) use ($sections, $status, $keys, $canEdit) {
            [$title, $icon, $desc, $step] = $sections[$key];
            return ['no' => array_search($key, $keys) + 1, 'title' => $title, 'icon' => $icon, 'desc' => $desc, 'step' => $step, 'done' => $status[$key], 'canEdit' => $canEdit];
        };
    @endphp

    @if (! $p->exists)
        {{-- ============================ EMPTY STATE ============================ --}}
        <div class="card card-modern">
            <div class="empty-state">
                <div class="empty-icon"><i class="bi bi-building-add"></i></div>
                @if ($canEdit)
                    <h5 class="fw-bold text-navy mb-1">Set up your enterprise profile</h5>
                    <p class="text-muted small mb-3">Your profile covers the 11 areas LEHSFF needs to assess a Co-Investment application. You can save as you go.</p>
                    <button type="button" class="btn btn-brand" wire:click="$dispatch('edit-section', { step: 1 })">
                        <i class="bi bi-pencil-square me-1"></i> Start my profile
                    </button>
                @else
                    <h5 class="fw-bold text-navy mb-1">No profile yet</h5>
                    <p class="text-muted small mb-0">This enterprise hasn't started its profile.</p>
                @endif
            </div>
        </div>
    @else
    <div class="row g-4">
        <div class="col-xl-8">

            {{-- 1. Enterprise profile --}}
            <section id="sec-enterprise" class="section-card">
                @include('profile.partials.section-head', $head('enterprise'))
                <div class="detail-grid">
                    <div><dt>Registered name</dt><dd>{{ $dash($p->registered_name) }}</dd></div>
                    <div><dt>Trading name</dt><dd>{{ $dash($p->trading_name) }}</dd></div>
                    <div><dt>Registration number</dt><dd>{{ $dash($p->registration_no) }}</dd></div>
                    <div><dt>Legal form</dt><dd>{{ $dash($p->legal_form) }}</dd></div>
                    <div><dt>Registered on</dt><dd>{{ $p->registered_on?->format('d M Y') ?? '—' }}</dd></div>
                    <div><dt>Sector</dt><dd>{{ $dash($p->sector) }}</dd></div>
                    <div><dt>Location</dt><dd>{{ collect([$p->address, $p->district])->filter()->implode(', ') ?: '—' }}</dd></div>
                    <div><dt>Employees</dt><dd>{{ $p->employees ?? '—' }} @if ($p->women_employees !== null) <span class="text-muted">({{ $p->women_employees }} women)</span> @endif</dd></div>
                </div>
                @php $own = array_filter(['Basotho-owned' => $p->basotho_owned_pct, 'Women-owned' => $p->women_owned_pct, 'Youth-owned' => $p->youth_owned_pct], fn ($v) => $v !== null); @endphp
                @if ($own)
                    <dt class="detail-label mt-3">Ownership</dt>
                    <div class="d-flex flex-wrap gap-2 mt-2">
                        @foreach ($own as $label => $pct) <span class="tag tag-accent">{{ $label }} · {{ $pct }}%</span> @endforeach
                    </div>
                @endif
                @if ($p->activity)
                    <div class="quote mt-3">{{ $p->activity }}<div class="quote-meta">Business activity</div></div>
                @endif
            </section>

            {{-- 2. Commercial profile --}}
            <section id="sec-commercial" class="section-card">
                @include('profile.partials.section-head', $head('commercial'))
                @if ($p->products)
                    <dt class="detail-label">Products / services</dt>
                    <div class="d-flex flex-wrap gap-2 mt-2 mb-3">
                        @foreach ($p->products as $prod) <span class="tag">{{ $prod }}</span> @endforeach
                    </div>
                @endif
                <div class="detail-grid">
                    <div><dt>Annual revenue (latest)</dt>
                        <dd>@if ($p->annual_revenue !== null)<span class="fw-bold text-brand">{{ $lsl($p->annual_revenue) }}</span> <span class="small text-muted">≈ {{ $usd($p->annual_revenue) }}</span>@else — @endif</dd></div>
                    <div><dt>Revenue growth</dt>
                        <dd>@if ($p->revenue_growth !== null)<span class="pill {{ $p->revenue_growth >= 0 ? 'pill-ok' : 'pill-danger' }}"><i class="bi {{ $p->revenue_growth >= 0 ? 'bi-arrow-up-right' : 'bi-arrow-down-right' }}"></i> {{ (float) $p->revenue_growth }}% year on year</span>@else — @endif</dd></div>
                    <div><dt>Customers</dt><dd>{{ $dash($p->customers) }}</dd></div>
                    <div><dt>Markets</dt><dd class="d-flex flex-wrap gap-1">@forelse ($p->markets ?? [] as $m)<span class="tag">{{ $m }}</span>@empty — @endforelse</dd></div>
                </div>
                @if ($p->traction) <div class="quote mt-3">{{ $p->traction }}<div class="quote-meta">Commercial traction</div></div> @endif
                @if ($p->growth_opportunity) <div class="quote mt-2">{{ $p->growth_opportunity }}<div class="quote-meta">Growth opportunity</div></div> @endif
            </section>

            {{-- 3. Management & governance --}}
            <section id="sec-governance" class="section-card">
                @include('profile.partials.section-head', $head('governance'))
                <div class="row g-3">
                    <div class="col-md-6">
                        <dt class="detail-label mb-2">Shareholders</dt>
                        @forelse ($p->shareholders ?? [] as $s)
                            <div class="d-flex align-items-center gap-2 small mb-2">
                                <span class="avatar avatar-sm">{{ $initials($s['name'] ?? '') }}</span>
                                <div class="flex-grow-1"><div class="fw-semibold text-navy">{{ $s['name'] ?? '' }}</div><div class="text-muted">{{ $s['type'] ?? '' }}</div></div>
                                <strong class="text-navy">{{ $s['percent'] ?? 0 }}%</strong>
                            </div>
                        @empty
                            <p class="small text-muted">No shareholders added.</p>
                        @endforelse
                    </div>
                    <div class="col-md-6">
                        <dt class="detail-label mb-2">Management team</dt>
                        @forelse ($p->management_team ?? [] as $m)
                            <div class="d-flex align-items-center gap-2 small mb-2">
                                <span class="avatar avatar-sm bg-accent">{{ $initials($m['name'] ?? '') }}</span>
                                <div class="flex-grow-1"><div class="fw-semibold text-navy">{{ $m['name'] ?? '' }}</div><div class="text-muted">{{ $m['role'] ?? '' }}</div></div>
                                @if (filled($m['years'] ?? null)) <span class="text-muted">{{ $m['years'] }} yrs</span> @endif
                            </div>
                        @empty
                            <p class="small text-muted">No team members added.</p>
                        @endforelse
                    </div>
                </div>
                <div class="detail-grid mt-3">
                    <div><dt>Board size</dt><dd>{{ $p->board_size !== null ? $p->board_size.' directors' : '—' }}</dd></div>
                    <div><dt>Independent directors</dt>
                        <dd>@if ($p->independent_directors) {{ $p->independent_directors }} @elseif ($p->board_size !== null) <span class="pill pill-warn"><i class="bi bi-exclamation-circle"></i> None</span> @else — @endif</dd></div>
                    <div><dt>Board meetings</dt><dd>{{ $dash($p->board_meetings) }}</dd></div>
                    <div><dt>Audited accounts</dt>
                        <dd><span class="pill {{ $p->audited_accounts ? 'pill-ok' : 'pill-muted' }}"><i class="bi {{ $p->audited_accounts ? 'bi-check-circle' : 'bi-dash-circle' }}"></i> {{ $p->audited_accounts ? 'Yes' : 'No' }}</span></dd></div>
                </div>
            </section>

            {{-- 4. Investment requirement --}}
            <section id="sec-investment" class="section-card">
                @include('profile.partials.section-head', $head('investment'))
                <div class="detail-grid">
                    <div><dt>Amount sought</dt>
                        <dd>@if ($p->amount_sought !== null)<span class="d-block fs-5 fw-bold text-brand">{{ $lsl($p->amount_sought) }}</span><span class="small text-muted">≈ {{ $usd($p->amount_sought) }}</span>@else — @endif</dd></div>
                    <div><dt>Purpose</dt><dd>{{ $dash($p->investment_purpose) }}</dd></div>
                </div>

                @php
                    $parts = array_filter([
                        ['Own contribution', (float) $p->own_contribution,      'bg-secondary'],
                        ['Investor',         (float) $p->investor_contribution, 'bg-brand'],
                        ['CI grant',         (float) $p->grant_requested,       'bg-accent'],
                    ], fn ($x) => $x[1] > 0);
                    $base = (float) $p->project_cost ?: array_sum(array_column($parts, 1));
                @endphp
                @if ($parts && $base > 0)
                    <dt class="detail-label mt-3 mb-2">Financing requirement</dt>
                    <div class="progress" style="height: 14px;">
                        @foreach ($parts as [$label, $amt, $bg])
                            <div class="progress-bar {{ $bg }}" style="width: {{ min(100, round($amt / $base * 100)) }}%;" title="{{ $label }}"></div>
                        @endforeach
                    </div>
                    <div class="d-flex flex-wrap gap-3 small mt-2">
                        @foreach ($parts as [$label, $amt, $bg])
                            <span><span class="d-inline-block rounded-circle {{ $bg }} me-1" style="width:10px;height:10px;"></span>{{ $label }}: <strong>{{ $lsl($amt) }}</strong> ({{ round($amt / $base * 100) }}%)</span>
                        @endforeach
                        @if ($p->project_cost) <span class="ms-auto text-muted">Total project cost {{ $lsl($p->project_cost) }}</span> @endif
                    </div>
                @endif

                @if ($p->use_of_funds)
                    <dt class="detail-label mt-3 mb-2">Use of funds</dt>
                    <table class="table table-modern table-sm mb-0">
                        <tbody>
                            @foreach ($p->use_of_funds as $row)
                                <tr><td>{{ $row['item'] ?? '' }}</td><td class="text-end fw-semibold">{{ $lsl($row['amount'] ?? null) }}</td><td class="text-end small text-muted">≈ {{ $usd($row['amount'] ?? 0) }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </section>

            {{-- 5. Investor profile --}}
            <section id="sec-investor" class="section-card">
                @include('profile.partials.section-head', $head('investor'))
                <div class="detail-grid">
                    <div><dt>Investor</dt><dd class="fw-semibold">{{ $dash($p->investor_name) }}</dd></div>
                    <div><dt>Investor type</dt><dd>{{ $dash($p->investor_type) }}</dd></div>
                    <div><dt>Country of registration</dt><dd>{{ $dash($p->investor_country) }}</dd></div>
                    <div><dt>Contact</dt><dd>{{ $dash($p->investor_contact) }}</dd></div>
                    <div><dt>Nature of participation</dt><dd>@if ($p->participation)<span class="tag">{{ \App\Models\EnterpriseProfile::PARTICIPATION[$p->participation] ?? $p->participation }}</span>@else — @endif</dd></div>
                </div>
                <dt class="detail-label mt-3 mb-2">Eligibility</dt>
                <ul class="checklist">
                    @foreach (\App\Models\EnterpriseProfile::INVESTOR_ELIGIBILITY as $k => $text)
                        @php $ok = ! empty($p->investor_eligibility[$k]); @endphp
                        <li class="{{ $ok ? 'is-done' : 'is-pending' }}"><i class="bi {{ $ok ? 'bi-check-circle-fill' : 'bi-circle' }}"></i><div>{{ $text }}</div></li>
                    @endforeach
                </ul>
            </section>

            {{-- 6. Transaction details --}}
            <section id="sec-transaction" class="section-card">
                @include('profile.partials.section-head', $head('transaction'))
                <div class="detail-grid">
                    <div><dt>Instrument</dt><dd>@if ($p->instrument)<span class="tag">{{ \App\Models\EnterpriseProfile::PARTICIPATION[$p->instrument] ?? $p->instrument }}</span>@else — @endif</dd></div>
                    <div><dt>Transaction status</dt><dd>@if ($p->transaction_status)<span class="pill pill-ok"><i class="bi bi-check-circle"></i> {{ \App\Models\EnterpriseProfile::TRANSACTION_STATUSES[$p->transaction_status] ?? $p->transaction_status }}</span>@else — @endif</dd></div>
                    <div><dt>Proposed structure</dt><dd>{{ $dash($p->structure) }}</dd></div>
                    <div><dt>Stake / valuation</dt>
                        <dd>{{ $p->stake_pct !== null ? (float) $p->stake_pct.'%' : '—' }} @if ($p->valuation) at {{ $lsl($p->valuation) }} <span class="small text-muted">post-money</span> @endif</dd></div>
                </div>
                @if ($p->key_terms)
                    <div class="quote mt-3" style="white-space: pre-line;">{{ $p->key_terms }}<div class="quote-meta">Key terms</div></div>
                @endif
            </section>

            {{-- 7. Investor commitment --}}
            <section id="sec-commitment" class="section-card">
                @include('profile.partials.section-head', $head('commitment'))
                <ul class="timeline">
                    @foreach (\App\Models\EnterpriseDocument::typesFor('commitment') as $type => [$label])
                        @php $doc = $p->document($type); @endphp
                        <li>
                            <span class="timeline-dot {{ $doc ? 'bg-accent text-white' : '' }}"><i class="bi {{ $doc ? 'bi-check-lg' : 'bi-hourglass' }}"></i></span>
                            <div class="flex-grow-1 d-flex flex-wrap justify-content-between gap-2">
                                <div>
                                    <div class="fw-semibold text-navy small">{{ $label }}</div>
                                    <div class="small text-muted">{{ $doc ? ($doc->document_date?->format('d M Y') ?? 'Uploaded '.$doc->created_at->format('d M Y')) : 'Not yet received' }}</div>
                                </div>
                                @if ($doc)
                                    <a href="{{ route('enterprise-documents.download', $doc) }}" class="pill pill-ok align-self-start text-decoration-none"><i class="bi bi-download"></i> {{ $doc->size_label }}</a>
                                @else
                                    <span class="pill pill-muted align-self-start">Pending</span>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            </section>

            {{-- 8. Financial information --}}
            <section id="sec-financials" class="section-card">
                @include('profile.partials.section-head', $head('financials'))
                @php $rows = collect($p->financials ?? [])->filter(fn ($r) => filled($r['year'] ?? null)); @endphp
                @if ($rows->isNotEmpty())
                    <div class="table-responsive">
                        <table class="table table-modern table-sm align-middle mb-3">
                            <thead><tr><th>Maloti</th>@foreach ($rows as $r)<th class="text-end">{{ $r['year'] }}</th>@endforeach</tr></thead>
                            <tbody>
                                @foreach (['revenue' => 'Revenue', 'gross_profit' => 'Gross profit', 'net_profit' => 'Net profit', 'total_assets' => 'Total assets'] as $k => $label)
                                    <tr><td class="fw-semibold text-navy">{{ $label }}</td>@foreach ($rows as $r)<td class="text-end">{{ filled($r[$k] ?? null) ? number_format((float) $r[$k]) : '—' }}</td>@endforeach</tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
                <dt class="detail-label mb-2">Records &amp; statutory information</dt>
                <ul class="checklist">
                    @foreach (\App\Models\EnterpriseProfile::STATUTORY as $k => $text)
                        @php $ok = ! empty($p->statutory[$k]); @endphp
                        <li class="{{ $ok ? 'is-done' : 'is-fail' }}"><i class="bi {{ $ok ? 'bi-check-circle-fill' : 'bi-x-circle-fill' }}"></i><div>{{ $text }} @unless ($ok) <span class="small text-muted">— not confirmed</span> @endunless</div></li>
                    @endforeach
                </ul>
            </section>

            {{-- 9. Grant utilisation --}}
            <section id="sec-grant" class="section-card">
                @include('profile.partials.section-head', $head('grant'))
                @php $grantTotal = collect($p->grant_allocations ?? [])->sum(fn ($r) => (float) ($r['amount'] ?? 0)); @endphp
                @if ($p->grant_allocations)
                    <table class="table table-modern table-sm align-middle mb-0">
                        <thead><tr><th>Item</th><th class="text-end">Amount</th><th>Share</th><th>Timing</th></tr></thead>
                        <tbody>
                            @foreach ($p->grant_allocations as $row)
                                @php $amt = (float) ($row['amount'] ?? 0); @endphp
                                <tr>
                                    <td>{{ $row['item'] ?? '' }}</td>
                                    <td class="text-end fw-semibold">{{ $lsl($amt) }}</td>
                                    <td style="min-width: 120px;"><div class="progress" style="height: 6px;"><div class="progress-bar bg-accent" style="width: {{ $grantTotal > 0 ? round($amt / $grantTotal * 100) : 0 }}%;"></div></div></td>
                                    <td class="small text-muted">{{ $row['timing'] ?? '' }}</td>
                                </tr>
                            @endforeach
                            <tr><td class="fw-bold text-navy">Total</td><td class="text-end fw-bold text-brand">{{ $lsl($grantTotal) }}</td><td colspan="2" class="small text-muted">≈ {{ $usd($grantTotal) }}</td></tr>
                        </tbody>
                    </table>
                    @unless ($p->grantAllocationMatches())
                        <div class="callout callout-warning small mt-3 mb-0"><i class="bi bi-exclamation-triangle me-1"></i> Allocations ({{ $lsl($grantTotal) }}) don't add up to the grant requested ({{ $lsl($p->grant_requested) }}).</div>
                    @endunless
                @else
                    <p class="small text-muted mb-0">No grant allocation entered yet.</p>
                @endif
            </section>

            {{-- 10. Supporting documentation --}}
            <section id="sec-documents" class="section-card">
                @include('profile.partials.section-head', $head('documents'))
                <div class="row g-2">
                    @foreach (\App\Models\EnterpriseDocument::typesFor('supporting') as $type => [$label, $section])
                        @php
                            $doc      = $p->document($type);
                            $required = in_array($type, \App\Models\EnterpriseDocument::REQUIRED_SUPPORTING, true);
                        @endphp
                        <div class="col-md-6">
                            <div class="file-row mb-0 h-100">
                                <span class="file-row-icon"><i class="bi {{ $doc ? 'bi-file-earmark-pdf' : 'bi-file-earmark-x' }}"></i></span>
                                <div class="flex-grow-1 small overflow-hidden">
                                    <div class="fw-semibold text-navy text-truncate">{{ $label }}</div>
                                    <div class="text-muted">{{ $doc ? $doc->size_label : ($required ? 'Required' : 'Optional') }}</div>
                                </div>
                                @if ($doc)
                                    <a href="{{ route('enterprise-documents.download', $doc) }}" class="btn btn-icon" title="Download"><i class="bi bi-download"></i></a>
                                @else
                                    <span class="pill {{ $required ? 'pill-danger' : 'pill-muted' }}">Missing</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>

            {{-- 11. Declarations --}}
            <section id="sec-declaration" class="section-card">
                @include('profile.partials.section-head', $head('declaration'))
                <ul class="checklist">
                    @foreach (\App\Models\EnterpriseProfile::DECLARATIONS as $k => $text)
                        @php $ok = ! empty($p->declarations[$k]); @endphp
                        <li class="{{ $ok ? 'is-done' : 'is-pending' }}"><i class="bi {{ $ok ? 'bi-check-circle-fill' : 'bi-circle' }}"></i><div>{{ $text }}</div></li>
                    @endforeach
                </ul>
                @if ($status['declaration'])
                    <div class="small mt-3"><span class="text-muted">Signed by</span> <strong>{{ $p->declared_name }}</strong>, {{ $p->declared_position }} <span class="text-muted">on</span> {{ $p->declared_on->format('d M Y') }}</div>
                @else
                    <div class="callout callout-warning small mt-3 mb-0">
                        <i class="bi bi-pen me-1"></i> Not signed yet
                        @if ($canEdit) — <a href="#" wire:click.prevent="$dispatch('edit-section', { step: 6 })">complete the declaration</a>@endif.
                    </div>
                @endif
            </section>
        </div>

        {{-- ============================ SIDEBAR ============================ --}}
        <div class="col-xl-4">
            <div class="sticky-side">
                <div class="card card-modern mb-3">
                    <div class="card-body">
                        <div class="card-title-sm"><i class="bi bi-list-check"></i> Profile sections</div>
                        @foreach ($sections as $key => [$title])
                            <a href="#sec-{{ $key }}" class="gate-row">
                                <span class="page-tabs-code">{{ $loop->iteration }}</span>
                                <span class="flex-grow-1 small fw-semibold text-navy">{{ $title }}</span>
                                <span class="pill {{ $status[$key] ? 'pill-ok' : 'pill-warn' }}" title="{{ $status[$key] ? 'Complete' : 'Incomplete' }}">
                                    <i class="bi {{ $status[$key] ? 'bi-check-circle-fill' : 'bi-exclamation-circle' }}"></i>
                                </span>
                            </a>
                        @endforeach
                    </div>
                </div>

                @php
                    $missing   = collect($status)->reject()->keys();
                    $nextStep  = $missing->isNotEmpty() ? $sections[$missing->first()][3] : null;
                @endphp
                <div class="card card-modern">
                    <div class="card-body">
                        <div class="card-title-sm"><i class="bi bi-compass"></i> {{ $canEdit ? 'Next steps' : 'Status' }}</div>
                        @if ($nextStep && ! $canEdit)
                            <p class="small text-muted mb-0">{{ $missing->count() }} {{ \Illuminate\Support\Str::plural('section', $missing->count()) }} still incomplete: {{ $missing->map(fn ($k) => $sections[$k][0])->implode(', ') }}.</p>
                        @elseif ($nextStep)
                            <p class="small text-muted mb-3">Complete the remaining {{ $missing->count() }} {{ \Illuminate\Support\Str::plural('section', $missing->count()) }} so your profile can be used for Co-Investment applications.</p>
                            <button type="button" class="btn btn-brand btn-sm w-100" wire:click="$dispatch('edit-section', { step: {{ $nextStep }} })">
                                <i class="bi bi-pencil-square me-1"></i> Continue with {{ $sections[$missing->first()][0] }}
                            </button>
                        @else
                            <p class="small text-muted mb-0"><i class="bi bi-check-circle-fill text-accent me-1"></i> {{ $canEdit ? 'Your profile is complete. Keep it up to date if anything changes.' : 'All 11 sections are complete.' }}</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>