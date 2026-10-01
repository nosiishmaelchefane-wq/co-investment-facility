<x-app-layout>

@php
    /*
     | TEMPLATE: sample record so the page renders.
     | Later, pass the real record as $eoi and delete the sample block.
     */
    $eoi = $eoi ?? (object) [
        'id' => request('id', 1), 'reference' => 'EOI-2026-0001', 'status' => 'in_review',
        'business_name' => 'Maluti Fresh Foods', 'representative_name' => 'Thabo Mokoena',
        'phone' => '+266 5800 1111', 'email' => 'thabo@malutifresh.co.ls', 'district' => 'Maseru',
        'website' => 'www.malutifresh.co.ls', 'legal_status' => 'registered_operating', 'year_commenced' => 2019,
        'business_description' => 'We process and package dried fruit (peaches, apples and apricots) for retailers across Lesotho and South Africa. Our customers are supermarket chains and school feeding programmes. We address post-harvest losses for smallholder farmers by buying surplus fruit and turning it into shelf-stable products.',
        'annual_turnover' => '2m_5m', 'full_time_employees' => 14,
        'investment_required_usd' => 250000, 'investment_types' => ['equity'],
        'use_of_funds' => ['equipment', 'working_capital', 'market_expansion'], 'use_of_funds_other' => null,
        'use_of_funds_description' => 'Install a second solar-powered drying line, fund working capital for export orders and register products with two new South African retailers.',
        'investor_status' => 'term_sheet', 'investor_status_other' => null,
        'investor_amount_usd' => 150000, 'investor_name' => 'Basotho Capital Partners',
        'investor_evidence' => ['term_sheet'], 'support_need' => 'ci_facility',
        'declaration_agree' => true, 'declaration_name' => 'Thabo Mokoena', 'declaration_date' => now()->subDays(12)->toDateString(),
        'submitted_at' => now()->subDays(12), 'updated_at' => now()->subDays(3),
    ];

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
    $label  = fn ($field, $value) => $labels[$field][$value] ?? ($value ?: '—');
    $list   = fn ($field, $values) => collect($values ?? [])->map(fn ($v) => $labels[$field][$v] ?? $v)->all();

    $statuses = [
        'draft'          => ['Draft',          'status-draft',     'bi-pencil'],
        'submitted'      => ['Submitted',      'status-submitted', 'bi-send'],
        'returned'       => ['Returned',       'status-referred',  'bi-arrow-return-left'],
        'in_review'      => ['In review',      'status-in-review', 'bi-hourglass-split'],
        'invited'        => ['Invited to CI',  'status-approved',  'bi-patch-check'],
        'not_progressed' => ['Not progressed', 'status-rejected',  'bi-x-circle'],
        'withdrawn'      => ['Withdrawn',      'status-draft',     'bi-slash-circle'],
    ];
    [$sLabel, $sClass, $sIcon] = $statuses[$eoi->status] ?? [ucfirst($eoi->status), 'status-draft', 'bi-circle'];

    $canEdit     = in_array($eoi->status, ['draft', 'returned'], true);
    $canWithdraw = in_array($eoi->status, ['draft', 'submitted', 'returned', 'in_review'], true);

    /* ---------- Progress tracker ---------- */
    $stages = [
        ['key' => 'submitted',  'label' => 'Submitted',           'icon' => 'bi-send'],
        ['key' => 'in_review',  'label' => 'Initial assessment',  'icon' => 'bi-search'],
        ['key' => 'pathway',    'label' => 'Pathway decision',    'icon' => 'bi-signpost-split'],
        ['key' => 'outcome',    'label' => 'Outcome',             'icon' => 'bi-flag'],
    ];
    $stageIndex = match ($eoi->status) {
        'draft'                       => -1,
        'submitted', 'returned'       => 0,
        'in_review'                   => 1,
        'invited', 'not_progressed'   => 3,
        default                       => 0,
    };

    /* ---------- Numbers ---------- */
    $required   = (float) $eoi->investment_required_usd;
    $committed  = (float) ($eoi->investor_amount_usd ?? 0);
    $coverage   = $required > 0 ? min(100, round($committed / $required * 100)) : 0;
    $gap        = max(0, $required - $committed);
    $submitted  = $eoi->submitted_at ? \Illuminate\Support\Carbon::parse($eoi->submitted_at) : null;
    $wordCount  = fn ($text) => str_word_count(strip_tags((string) $text));

    /* ---------- Activity (sample) ---------- */
    $activity = [
        ['icon' => 'bi-hourglass-split', 'tone' => 'warning', 'title' => 'Assessment started',   'text' => 'Your EOI is being reviewed by the LEHSFF team.', 'when' => now()->subDays(3)],
        ['icon' => 'bi-envelope-check',  'tone' => 'primary', 'title' => 'Acknowledgement sent', 'text' => 'Confirmation emailed to '.$eoi->email.'.',       'when' => now()->subDays(12)],
        ['icon' => 'bi-send-check',      'tone' => 'accent',  'title' => 'EOI submitted',        'text' => 'Submitted by '.$eoi->representative_name.'.',     'when' => now()->subDays(12)],
    ];

    // Record for pre-filling the edit drawer
    $record = collect((array) $eoi)->except(['status', 'submitted_at', 'updated_at'])->all();
@endphp

<div class="eoi-show">

    {{-- ============================ TOP BAR ============================ --}}
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <a href="{{ \Illuminate\Support\Facades\Route::has('expression-of-interest.index') ? route('expression-of-interest.index') : '#' }}" class="eoi-back">
            <i class="bi bi-arrow-left"></i> Back to applications
        </a>
    </div>

    {{-- ============================ HEADER ============================ --}}
    <div class="eoi-header mb-4">
        <div class="eoi-header-glow"></div>
        <div class="d-flex flex-wrap align-items-start gap-3 position-relative">
            <div class="eoi-header-avatar">{{ mb_strtoupper(mb_substr($eoi->business_name, 0, 1)) }}</div>
            <div class="me-auto">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                    <span class="eoi-ref-chip">{{ $eoi->reference }}</span>
                    <span class="badge {{ $sClass }}"><i class="bi {{ $sIcon }} me-1"></i>{{ $sLabel }}</span>
                </div>
                <h2 class="eoi-header-title mb-1">{{ $eoi->business_name }}</h2>
                <div class="eoi-header-meta">
                    <span><i class="bi bi-geo-alt"></i> {{ $eoi->district }}</span>
                    <span><i class="bi bi-person"></i> {{ $eoi->representative_name }}</span>
                    <span><i class="bi bi-calendar3"></i> {{ $submitted ? 'Submitted '.$submitted->format('d M Y') : 'Not submitted' }}</span>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-light btn-sm fw-semibold" data-eoi-edit='@json($record)'>
                        <i class="bi bi-pencil me-1"></i> Edit
                    </button>
                <a href="#" class="btn btn-outline-light btn-sm"><i class="bi bi-download me-1"></i> PDF</a>
                    <form method="POST" action="#" data-confirm="Withdraw {{ $eoi->reference }}? This cannot be undone." class="d-inline">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-outline-light btn-sm eoi-btn-danger"><i class="bi bi-x-circle me-1"></i> Withdraw</button>
                    </form>
            </div>
        </div>

        {{-- Progress --}}
        <div class="eoi-tracker position-relative">
            @foreach ($stages as $i => $stage)
                <div class="eoi-track {{ $i < $stageIndex ? 'done' : ($i === $stageIndex ? 'current' : '') }}">
                    <span class="eoi-track-dot"><i class="bi {{ $i < $stageIndex ? 'bi-check-lg' : $stage['icon'] }}"></i></span>
                    <span class="eoi-track-label">{{ $stage['label'] }}</span>
                </div>
            @endforeach
        </div>
    </div>

    @if ($eoi->status === 'returned')
        <div class="alert eoi-alert-warning d-flex gap-3 mb-4">
            <i class="bi bi-arrow-return-left fs-5"></i>
            <div class="flex-grow-1 small">
                <strong>Returned for more information.</strong> Please review the reviewer's comments, update your EOI and resubmit.
            </div>
            <button type="button" class="btn btn-sm btn-warning fw-semibold" data-eoi-edit='@json($record)'>Update EOI</button>
        </div>
    @endif

    {{-- ============================ KPIs ============================ --}}
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="eoi-kpi">
                <span class="eoi-kpi-icon tone-primary"><i class="bi bi-cash-coin"></i></span>
                <div><div class="eoi-kpi-label">Investment required</div><div class="eoi-kpi-value">US$ {{ number_format($required) }}</div></div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="eoi-kpi">
                <span class="eoi-kpi-icon tone-accent"><i class="bi bi-briefcase"></i></span>
                <div><div class="eoi-kpi-label">Investor commitment</div><div class="eoi-kpi-value">{{ $committed ? 'US$ '.number_format($committed) : '—' }}</div></div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="eoi-kpi">
                <span class="eoi-kpi-icon tone-warning"><i class="bi bi-pie-chart"></i></span>
                <div><div class="eoi-kpi-label">Funding gap</div><div class="eoi-kpi-value">US$ {{ number_format($gap) }}</div></div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="eoi-kpi">
                <span class="eoi-kpi-icon tone-muted"><i class="bi bi-people"></i></span>
                <div><div class="eoi-kpi-label">Full-time employees</div><div class="eoi-kpi-value">{{ $eoi->full_time_employees ?? '—' }}</div></div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        {{-- ============================ MAIN ============================ --}}
        <div class="col-xl-8">

            {{-- Section nav --}}
            <nav class="eoi-tabs mb-3">
                <a href="#sec-business" class="active">Business</a>
                <a href="#sec-operations">Operations</a>
                <a href="#sec-investment">Investment</a>
                <a href="#sec-investor">Investor</a>
                <a href="#sec-support">Support &amp; declaration</a>
            </nav>

            {{-- 1. Business --}}
            <section id="sec-business" class="eoi-section">
                <div class="eoi-section-head">
                    <span class="eoi-section-no">1</span>
                    <h5>Business details</h5>
                </div>
                <div class="eoi-grid">
                    <div><dt>Business name</dt><dd>{{ $eoi->business_name }}</dd></div>
                    <div><dt>Founder / authorised representative</dt><dd>{{ $eoi->representative_name }}</dd></div>
                    <div><dt>Telephone</dt><dd><a href="tel:{{ $eoi->phone }}">{{ $eoi->phone }}</a></dd></div>
                    <div><dt>Email</dt><dd><a href="mailto:{{ $eoi->email }}">{{ $eoi->email }}</a></dd></div>
                    <div><dt>District</dt><dd>{{ $eoi->district }}</dd></div>
                    <div><dt>Website / social media</dt><dd>{{ $eoi->website ?: '—' }}</dd></div>
                    <div><dt>Legal status</dt>
                        <dd>
                            <span class="eoi-pill {{ $eoi->legal_status === 'registered_operating' ? 'ok' : 'warn' }}">
                                <i class="bi {{ $eoi->legal_status === 'registered_operating' ? 'bi-check-circle' : 'bi-exclamation-circle' }}"></i>
                                {{ $label('legal_status', $eoi->legal_status) }}
                            </span>
                        </dd>
                    </div>
                    <div><dt>Operating since</dt><dd>{{ $eoi->year_commenced ?: '—' }} @if ($eoi->year_commenced) <span class="text-muted">({{ now()->year - $eoi->year_commenced }} yrs)</span> @endif</dd></div>
                </div>
            </section>

            {{-- 2. Operations --}}
            <section id="sec-operations" class="eoi-section">
                <div class="eoi-section-head">
                    <span class="eoi-section-no">2</span>
                    <h5>What the business does &amp; scale</h5>
                </div>
                <div class="eoi-quote">
                    {{ $eoi->business_description }}
                    <div class="eoi-quote-meta">{{ $wordCount($eoi->business_description) }} words</div>
                </div>
                <div class="eoi-grid mt-3">
                    <div><dt>Annual turnover</dt><dd>{{ $label('annual_turnover', $eoi->annual_turnover) }}</dd></div>
                    <div><dt>Full-time employees</dt><dd>{{ $eoi->full_time_employees }}</dd></div>
                </div>
            </section>

            {{-- 3. Investment --}}
            <section id="sec-investment" class="eoi-section">
                <div class="eoi-section-head">
                    <span class="eoi-section-no">3</span>
                    <h5>Investment sought</h5>
                </div>
                <div class="eoi-grid">
                    <div><dt>Total investment required</dt><dd class="fs-5 fw-bold text-cif-primary">US$ {{ number_format($required) }}</dd></div>
                    <div><dt>Type of investment</dt>
                        <dd class="d-flex flex-wrap gap-1">
                            @foreach ($list('investment_types', $eoi->investment_types) as $t)
                                <span class="eoi-tag">{{ $t }}</span>
                            @endforeach
                        </dd>
                    </div>
                </div>

                <dt class="eoi-dt mt-3">How the investment would be used</dt>
                <div class="d-flex flex-wrap gap-2 mt-2">
                    @foreach ($list('use_of_funds', $eoi->use_of_funds) as $u)
                        <span class="eoi-tag accent"><i class="bi bi-check2"></i> {{ $u }}</span>
                    @endforeach
                    @if ($eoi->use_of_funds_other)
                        <span class="eoi-tag accent"><i class="bi bi-check2"></i> {{ $eoi->use_of_funds_other }}</span>
                    @endif
                </div>

                <div class="eoi-quote mt-3">
                    {{ $eoi->use_of_funds_description }}
                    <div class="eoi-quote-meta">{{ $wordCount($eoi->use_of_funds_description) }} words</div>
                </div>
            </section>

            {{-- 4. Investor --}}
            <section id="sec-investor" class="eoi-section">
                <div class="eoi-section-head">
                    <span class="eoi-section-no">4</span>
                    <h5>Investor engagement</h5>
                </div>

                {{-- Engagement ladder --}}
                @php
                    $ladder = ['none', 'discussions', 'interest', 'loi', 'term_sheet', 'committed'];
                    $pos    = array_search($eoi->investor_status, $ladder, true);
                @endphp
                <div class="eoi-ladder mb-3">
                    @foreach ($ladder as $i => $step)
                        <div class="eoi-ladder-step {{ $pos !== false && $i <= $pos ? 'on' : '' }} {{ $pos === $i ? 'current' : '' }}">
                            <span></span>
                            <small>{{ ['none' => 'None', 'discussions' => 'Discussions', 'interest' => 'Interest', 'loi' => 'LOI', 'term_sheet' => 'Term Sheet', 'committed' => 'Committed'][$step] }}</small>
                        </div>
                    @endforeach
                </div>

                <div class="eoi-grid">
                    <div><dt>Status</dt><dd>{{ $label('investor_status', $eoi->investor_status) }}{{ $eoi->investor_status_other ? ': '.$eoi->investor_status_other : '' }}</dd></div>
                    <div><dt>Investor</dt><dd>{{ $eoi->investor_name ?: '—' }}</dd></div>
                    <div><dt>Amount indicated / committed</dt><dd>{{ $committed ? 'US$ '.number_format($committed) : '—' }}</dd></div>
                    <div><dt>Evidence</dt>
                        <dd class="d-flex flex-wrap gap-1">
                            @forelse ($list('investor_evidence', $eoi->investor_evidence) as $ev)
                                <span class="eoi-tag"><i class="bi bi-file-earmark-check"></i> {{ $ev }}</span>
                            @empty
                                —
                            @endforelse
                        </dd>
                    </div>
                </div>

                @if ($required > 0)
                    <div class="eoi-coverage mt-3">
                        <div class="d-flex justify-content-between small mb-1">
                            <span class="fw-semibold text-cif-navy">Investor coverage of requirement</span>
                            <span class="fw-bold text-cif-primary">{{ $coverage }}%</span>
                        </div>
                        <div class="progress" style="height: 10px;">
                            <div class="progress-bar" style="width: {{ $coverage }}%; background: var(--cif-accent);"></div>
                        </div>
                        <div class="d-flex justify-content-between small text-muted mt-1">
                            <span>Investor: US$ {{ number_format($committed) }}</span>
                            <span>Gap: US$ {{ number_format($gap) }}</span>
                        </div>
                    </div>
                @endif
            </section>

            {{-- 5. Support + declaration --}}
            <section id="sec-support" class="eoi-section">
                <div class="eoi-section-head">
                    <span class="eoi-section-no">5</span>
                    <h5>Support requested &amp; declaration</h5>
                </div>
                <div class="eoi-support">
                    <i class="bi bi-signpost-split"></i>
                    <div>{{ $label('support_need', $eoi->support_need) }}</div>
                </div>

                <div class="eoi-declaration mt-3">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <i class="bi {{ $eoi->declaration_agree ? 'bi-patch-check-fill text-cif-accent' : 'bi-x-circle text-danger' }} fs-5"></i>
                        <strong class="text-cif-navy">{{ $eoi->declaration_agree ? 'Declaration accepted' : 'Declaration not accepted' }}</strong>
                    </div>
                    <p class="small text-muted mb-2">
                        Applicant confirmed the information is accurate and consented to LEHSFF/CAFI using it to assess the enterprise for
                        investment facilitation, investor matchmaking and/or participation in the CAFI Co-Investment Facility.
                    </p>
                    <div class="small"><span class="text-muted">Signed:</span> <strong>{{ $eoi->declaration_name }}</strong>
                        <span class="text-muted ms-2">on</span> {{ \Illuminate\Support\Carbon::parse($eoi->declaration_date)->format('d M Y') }}</div>
                </div>
            </section>
        </div>

        {{-- ============================ SIDEBAR ============================ --}}
        <div class="col-xl-4">
            <div class="eoi-side-sticky">

                {{-- Next step --}}
                <div class="card eoi-card mb-3">
                    <div class="card-body">
                        <div class="eoi-side-title"><i class="bi bi-compass"></i> What happens next</div>
                        @if ($eoi->status === 'in_review' || $eoi->status === 'submitted')
                            <p class="small text-muted mb-0">The LEHSFF team is reviewing your EOI. You'll be contacted with a recommended pathway, usually within <strong>10 working days</strong>.</p>
                        @elseif ($eoi->status === 'invited')
                            <p class="small text-muted mb-2">You've been invited to complete the formal Co-Investment application and due diligence.</p>
                            <a href="#" class="btn btn-sm btn-cif-accent w-100">Start CI application</a>
                        @elseif ($eoi->status === 'returned')
                            <p class="small text-muted mb-0">Update your EOI with the requested information and resubmit.</p>
                        @elseif ($eoi->status === 'draft')
                            <p class="small text-muted mb-2">This EOI hasn't been submitted yet.</p>
                            <button type="button" class="btn btn-sm btn-cif-primary w-100" data-eoi-edit='@json($record)'>Continue &amp; submit</button>
                        @else
                            <p class="small text-muted mb-0">No further action is required.</p>
                        @endif
                    </div>
                </div>

                {{-- Contact --}}
                <div class="card eoi-card mb-3">
                    <div class="card-body">
                        <div class="eoi-side-title"><i class="bi bi-person-badge"></i> Contact</div>
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <span class="eoi-contact-avatar">{{ collect(explode(' ', $eoi->representative_name))->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('') }}</span>
                            <div>
                                <div class="fw-semibold text-cif-navy">{{ $eoi->representative_name }}</div>
                                <div class="small text-muted">Founder / authorised representative</div>
                            </div>
                        </div>
                        <a href="mailto:{{ $eoi->email }}" class="eoi-contact-line"><i class="bi bi-envelope"></i> {{ $eoi->email }}</a>
                        <a href="tel:{{ $eoi->phone }}" class="eoi-contact-line"><i class="bi bi-telephone"></i> {{ $eoi->phone }}</a>
                        @if ($eoi->website)
                            <span class="eoi-contact-line"><i class="bi bi-globe"></i> {{ $eoi->website }}</span>
                        @endif
                    </div>
                </div>

                {{-- Documents --}}
                <div class="card eoi-card mb-3">
                    <div class="card-body">
                        <div class="eoi-side-title"><i class="bi bi-folder2-open"></i> Supporting documents</div>
                        @forelse ($list('investor_evidence', array_diff($eoi->investor_evidence ?? [], ['none_available', 'not_applicable'])) as $doc)
                            <div class="eoi-doc">
                                <span class="eoi-doc-icon"><i class="bi bi-file-earmark-pdf"></i></span>
                                <div class="flex-grow-1 small">
                                    <div class="fw-semibold text-cif-navy">{{ $doc }}</div>
                                    <div class="text-muted">Requested at CI application stage</div>
                                </div>
                            </div>
                        @empty
                            <p class="small text-muted mb-0">No documents listed.</p>
                        @endforelse
                    </div>
                </div>

                {{-- Activity --}}
                <div class="card eoi-card">
                    <div class="card-body">
                        <div class="eoi-side-title"><i class="bi bi-clock-history"></i> Activity</div>
                        <ul class="eoi-activity">
                            @foreach ($activity as $a)
                                <li>
                                    <span class="eoi-activity-icon tone-{{ $a['tone'] }}"><i class="bi {{ $a['icon'] }}"></i></span>
                                    <div>
                                        <div class="fw-semibold small text-cif-navy">{{ $a['title'] }}</div>
                                        <div class="small text-muted">{{ $a['text'] }}</div>
                                        <div class="eoi-activity-time">{{ $a['when']->diffForHumans() }}</div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.eoi-show {
    --cif-primary: #29317D; --cif-primary-dark: #1f2663;
    --cif-accent:  #089E49; --cif-accent-dark:  #07863e;
    --cif-navy:    #0d1a3a; --cif-soft: #f0f2fa; --cif-border: #e2e8f0;
}
.text-cif-primary { color: var(--cif-primary) !important; }
.text-cif-accent  { color: var(--cif-accent) !important; }
.text-cif-navy    { color: var(--cif-navy) !important; }
.btn-cif-primary  { background: var(--cif-primary); border-color: var(--cif-primary); color: #fff; font-weight: 600; }
.btn-cif-primary:hover { background: var(--cif-primary-dark); border-color: var(--cif-primary-dark); color: #fff; }
.btn-cif-accent   { background: var(--cif-accent); border-color: var(--cif-accent); color: #fff; font-weight: 600; }
.btn-cif-accent:hover { background: var(--cif-accent-dark); border-color: var(--cif-accent-dark); color: #fff; }

.status-draft     { background: #e2e8f0; color: #475569; }
.status-submitted { background: rgba(41,49,125,.12); color: #29317D; }
.status-in-review { background: #fef3c7; color: #92400e; }
.status-referred  { background: #ffedd5; color: #9a3412; }
.status-approved  { background: rgba(8,158,73,.12); color: #07863e; }
.status-rejected  { background: #fee2e2; color: #b91c1c; }

.eoi-back { font-size: .85rem; font-weight: 600; color: #64748b; text-decoration: none; }
.eoi-back:hover { color: var(--cif-primary); }

/* Header */
.eoi-header { position: relative; overflow: hidden; border-radius: 1rem; padding: 1.75rem 1.75rem 1.25rem; color: #fff;
              background: linear-gradient(120deg, var(--cif-navy) 0%, var(--cif-primary) 100%); }
.eoi-header-glow { position: absolute; right: -60px; top: -90px; width: 300px; height: 300px; border-radius: 50%; background: var(--cif-accent); opacity: .3; filter: blur(70px); }
.eoi-header-avatar { width: 56px; height: 56px; border-radius: 14px; background: var(--cif-accent); color: #fff; font-weight: 800; font-size: 1.5rem;
                     display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.eoi-ref-chip { font-size: .75rem; font-weight: 700; letter-spacing: .04em; padding: .2rem .6rem; border-radius: 6px; background: rgba(255,255,255,.12); color: #fff; }
.eoi-header-title { font-weight: 800; font-size: 1.6rem; }
.eoi-header-meta { display: flex; flex-wrap: wrap; gap: 1rem; font-size: .82rem; color: rgba(255,255,255,.7); }
.eoi-header-meta i { margin-right: .25rem; }
.eoi-btn-danger:hover { background: #dc3545; border-color: #dc3545; color: #fff; }

/* Tracker */
.eoi-tracker { display: flex; justify-content: space-between; margin-top: 1.75rem; padding-top: 1.25rem; border-top: 1px solid rgba(255,255,255,.12); }
.eoi-tracker::before { content: ''; position: absolute; left: 18px; right: 18px; top: calc(1.25rem + 17px); height: 2px; background: rgba(255,255,255,.18); }
.eoi-track { position: relative; z-index: 1; display: flex; flex-direction: column; align-items: center; gap: .4rem; flex: 1; }
.eoi-track:first-child { align-items: flex-start; } .eoi-track:last-child { align-items: flex-end; }
.eoi-track-dot { width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center;
                 background: var(--cif-navy); border: 2px solid rgba(255,255,255,.25); color: rgba(255,255,255,.5); }
.eoi-track-label { font-size: .72rem; font-weight: 600; color: rgba(255,255,255,.55); }
.eoi-track.done .eoi-track-dot { background: var(--cif-accent); border-color: var(--cif-accent); color: #fff; }
.eoi-track.done .eoi-track-label { color: #7ef0ae; }
.eoi-track.current .eoi-track-dot { background: #fff; border-color: #fff; color: var(--cif-primary); box-shadow: 0 0 0 5px rgba(255,255,255,.15); }
.eoi-track.current .eoi-track-label { color: #fff; }

.eoi-alert-warning { background: #fff7ed; border: 1px solid #fed7aa; color: #9a3412; border-radius: .75rem; align-items: center; }

/* KPIs */
.eoi-kpi { display: flex; align-items: center; gap: .9rem; background: #fff; border: 1px solid var(--cif-border); border-radius: .75rem; padding: 1rem 1.1rem; height: 100%; }
.eoi-kpi-icon { width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; flex-shrink: 0; }
.eoi-kpi-label { font-size: .74rem; color: #64748b; }
.eoi-kpi-value { font-size: 1.15rem; font-weight: 800; color: var(--cif-navy); }
.tone-primary { background: rgba(41,49,125,.1); color: var(--cif-primary); }
.tone-accent  { background: rgba(8,158,73,.1);  color: var(--cif-accent); }
.tone-warning { background: #fef3c7; color: #b45309; }
.tone-muted   { background: #f1f5f9; color: #64748b; }

/* Section nav */
.eoi-tabs { display: flex; gap: .25rem; overflow-x: auto; background: #fff; border: 1px solid var(--cif-border); border-radius: .75rem; padding: .3rem;
            position: sticky; top: 76px; z-index: 5; }
.eoi-tabs a { white-space: nowrap; padding: .45rem .9rem; border-radius: .5rem; font-size: .82rem; font-weight: 600; color: #64748b; text-decoration: none; }
.eoi-tabs a:hover { color: var(--cif-primary); background: var(--cif-soft); }
.eoi-tabs a.active { background: var(--cif-primary); color: #fff; }

/* Sections */
.eoi-section { background: #fff; border: 1px solid var(--cif-border); border-radius: .9rem; padding: 1.4rem 1.5rem; margin-bottom: 1rem; scroll-margin-top: 140px; }
.eoi-section-head { display: flex; align-items: center; gap: .75rem; margin-bottom: 1.1rem; padding-bottom: .9rem; border-bottom: 1px dashed var(--cif-border); }
.eoi-section-head h5 { margin: 0; font-weight: 700; font-size: 1rem; color: var(--cif-navy); }
.eoi-section-no { width: 30px; height: 30px; border-radius: 8px; background: var(--cif-primary); color: #fff; font-weight: 800; font-size: .8rem;
                  display: flex; align-items: center; justify-content: center; }
.eoi-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem 1.5rem; margin: 0; }
@media (max-width: 575.98px) { .eoi-grid { grid-template-columns: 1fr; } }
.eoi-grid dt, .eoi-dt { font-size: .72rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: #94a3b8; margin-bottom: .25rem; }
.eoi-grid dd { margin: 0; font-size: .9rem; color: var(--cif-navy); word-break: break-word; }
.eoi-grid dd a { color: var(--cif-primary); text-decoration: none; }
.eoi-grid dd a:hover { color: var(--cif-accent); }

.eoi-pill { display: inline-flex; align-items: center; gap: .35rem; font-size: .8rem; font-weight: 600; padding: .25rem .6rem; border-radius: 999px; }
.eoi-pill.ok   { background: rgba(8,158,73,.1); color: var(--cif-accent-dark); }
.eoi-pill.warn { background: #fef3c7; color: #92400e; }
.eoi-tag { display: inline-flex; align-items: center; gap: .3rem; font-size: .78rem; font-weight: 600; padding: .3rem .65rem; border-radius: 6px;
           background: var(--cif-soft); color: var(--cif-primary); }
.eoi-tag.accent { background: rgba(8,158,73,.08); color: var(--cif-accent-dark); }

.eoi-quote { position: relative; background: #f8fafc; border-left: 4px solid var(--cif-primary); border-radius: .5rem; padding: 1rem 1.1rem;
             font-size: .9rem; line-height: 1.65; color: #334155; }
.eoi-quote-meta { font-size: .7rem; color: #94a3b8; margin-top: .5rem; text-align: right; }

/* Investor ladder */
.eoi-ladder { display: flex; gap: 4px; }
.eoi-ladder-step { flex: 1; text-align: center; }
.eoi-ladder-step span { display: block; height: 8px; border-radius: 4px; background: var(--cif-border); margin-bottom: .35rem; }
.eoi-ladder-step small { font-size: .68rem; color: #94a3b8; font-weight: 600; }
.eoi-ladder-step.on span { background: var(--cif-accent); }
.eoi-ladder-step.current small { color: var(--cif-accent-dark); }
.eoi-coverage { background: #f8fafc; border-radius: .6rem; padding: .9rem 1rem; }

.eoi-support { display: flex; gap: .8rem; align-items: center; background: rgba(41,49,125,.05); border: 1px solid rgba(41,49,125,.15);
               border-radius: .6rem; padding: .9rem 1rem; font-size: .9rem; color: var(--cif-navy); font-weight: 500; }
.eoi-support i { font-size: 1.3rem; color: var(--cif-primary); }
.eoi-declaration { border: 1px solid var(--cif-border); border-radius: .6rem; padding: 1rem; }

/* Sidebar */
.eoi-side-sticky { position: sticky; top: 76px; }
.eoi-card { border: 1px solid var(--cif-border); border-radius: .9rem; }
.eoi-side-title { font-weight: 700; font-size: .85rem; color: var(--cif-navy); margin-bottom: .8rem; }
.eoi-side-title i { color: var(--cif-accent); margin-right: .35rem; }
.eoi-contact-avatar { width: 44px; height: 44px; border-radius: 50%; background: var(--cif-primary); color: #fff; font-weight: 700;
                      display: flex; align-items: center; justify-content: center; }
.eoi-contact-line { display: flex; align-items: center; gap: .6rem; padding: .5rem .6rem; border-radius: .5rem; font-size: .85rem; color: #334155; text-decoration: none; word-break: break-all; }
.eoi-contact-line i { color: var(--cif-primary); }
a.eoi-contact-line:hover { background: var(--cif-soft); color: var(--cif-primary); }
.eoi-doc { display: flex; align-items: center; gap: .75rem; padding: .6rem; border: 1px solid var(--cif-border); border-radius: .6rem; margin-bottom: .5rem; }
.eoi-doc-icon { width: 36px; height: 36px; border-radius: 8px; background: #fee2e2; color: #dc2626; display: flex; align-items: center; justify-content: center; }
.eoi-activity { list-style: none; padding: 0; margin: 0; position: relative; }
.eoi-activity::before { content: ''; position: absolute; left: 15px; top: 6px; bottom: 6px; width: 2px; background: var(--cif-border); }
.eoi-activity li { position: relative; display: flex; gap: .75rem; padding-bottom: 1rem; }
.eoi-activity li:last-child { padding-bottom: 0; }
.eoi-activity-icon { position: relative; z-index: 1; width: 32px; height: 32px; flex-shrink: 0; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: .85rem; border: 2px solid #fff; }
.eoi-activity-time { font-size: .7rem; color: #94a3b8; margin-top: .15rem; }

@media (max-width: 1199.98px) { .eoi-side-sticky, .eoi-tabs { position: static; } }
@media (max-width: 575.98px) {
    .eoi-header { padding: 1.25rem; }
    .eoi-track-label { display: none; }
    .eoi-section { padding: 1.1rem; }
}
@media print {
    .eoi-back, .eoi-header .btn, .eoi-header form, .eoi-tabs, .eoi-alert-warning .btn { display: none !important; }
    .eoi-header { background: var(--cif-primary) !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .eoi-side-sticky { position: static; }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    /* ---------- Section nav: highlight current section ---------- */
    var links = document.querySelectorAll('.eoi-tabs a');
    var sections = Array.prototype.map.call(links, function (a) { return document.querySelector(a.getAttribute('href')); });
    if ('IntersectionObserver' in window) {
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) return;
                links.forEach(function (a) { a.classList.toggle('active', a.getAttribute('href') === '#' + entry.target.id); });
            });
        }, { rootMargin: '-40% 0px -55% 0px' });
        sections.forEach(function (s) { if (s) io.observe(s); });
    }
    links.forEach(function (a) {
        a.addEventListener('click', function (e) {
            e.preventDefault();
            document.querySelector(a.getAttribute('href')).scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });

    /* ---------- Edit → open the apply drawer pre-filled ---------- */
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-eoi-edit]');
        if (!btn || !window.eoiDrawer) return;
        e.preventDefault();
        window.eoiDrawer.open(JSON.parse(btn.getAttribute('data-eoi-edit')), 'edit');
    });

    /* ---------- Confirm before withdraw ---------- */
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            if (form.dataset.confirmed) return;
            e.preventDefault();
            var msg = form.dataset.confirm;
            if (window.Swal) {
                Swal.fire({ title: 'Withdraw application?', text: msg, icon: 'warning', showCancelButton: true,
                            confirmButtonText: 'Yes, withdraw', confirmButtonColor: '#dc3545', cancelButtonColor: '#94a3b8' })
                    .then(function (r) { if (r.isConfirmed) { form.dataset.confirmed = 1; form.submit(); } });
            } else if (confirm(msg)) {
                form.dataset.confirmed = 1; form.submit();
            }
        });
    });
});
</script>

{{-- Edit drawer (same form as Apply) --}}
@include('expression-of-interest.partials.apply-drawer')

</x-app-layout>