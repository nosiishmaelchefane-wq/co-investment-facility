{{--
    EOI Apply / Edit drawer — template partial.
    Include once per page:   @include('expression-of-interest.partials.apply-drawer')
    Open it from any button:
        <button data-bs-toggle="offcanvas" data-bs-target="#eoiDrawer">Apply</button>          (new)
        window.eoiDrawer.open({ business_name: '…', district: 'Maseru', … }, 'edit')           (pre-filled)
--}}
@php
    /*
     | Form options (from "CAFI Co-Investment – Enterprise Expression of Interest Form", 2026-09-25).
     | Keys are the values stored in the database; labels are what the applicant sees.
     */
    $districts = ['Berea', 'Butha-Buthe', 'Leribe', 'Mafeteng', 'Maseru', "Mohale's Hoek", 'Mokhotlong', "Qacha's Nek", 'Quthing', 'Thaba-Tseka'];

    $legalStatuses = [
        'registered_operating'   => 'Yes, registered and operating in Lesotho',
        'registered_not_operating' => 'Registered but operations are not yet established',
        'registration_in_progress' => 'Registration is in progress',
        'not_registered'         => 'Not registered',
    ];

    $turnoverBands = [
        'below_500k' => 'Below M500,000',
        '500k_2m'    => 'M500,000 – M2 million',
        '2m_5m'      => 'M2 – M5 million',
        '5m_10m'     => 'M5 – M10 million',
        'above_10m'  => 'Above M10 million',
    ];

    $investmentTypes = [
        'equity'       => 'Equity',
        'quasi_equity' => 'Quasi-equity',
        'debt'         => 'Debt',
    ];

    $useOfFunds = [
        'equipment'           => 'Equipment / productive assets',
        'business_expansion'  => 'Business expansion',
        'working_capital'     => 'Working capital',
        'market_expansion'    => 'Market expansion',
        'technology'          => 'Technology / systems',
        'product_development' => 'Product development',
        'other'               => 'Other (specify)',
    ];

    $investorStatuses = [
        'none'           => 'I do not currently have an investor',
        'discussions'    => 'I am in discussions with potential investors',
        'interest'       => 'I have received an indication of investor interest',
        'loi'            => 'I have received a Letter of Intent',
        'term_sheet'     => 'I have a Term Sheet',
        'committed'      => 'Investment has been formally committed',
        'other'          => 'Other (specify)',
    ];

    $investorEvidence = [
        'loi'                  => 'Letter of Intent',
        'term_sheet'           => 'Term Sheet',
        'investment_agreement' => 'Investment Agreement',
        'other_formal'         => 'Other formal evidence of commitment',
        'none_available'       => 'No supporting document currently available',
        'not_applicable'       => 'Not applicable',
    ];

    $supportNeeds = [
        'ci_facility'     => ['I already have an investor commitment and would like to be considered for the CAFI Co-Investment Facility.', 'bi-patch-check'],
        'progress_deal'   => ['I have an interested investor but need support to progress the transaction.', 'bi-arrow-repeat'],
        'find_investor'   => ['I am investment-ready but need help finding an investor.', 'bi-arrow-left-right'],
        'readiness'       => ['I need support to become investment-ready.', 'bi-ladder'],
        'assess_me'       => ['I am not sure and would like to be assessed.', 'bi-question-circle'],
    ];


    $steps = [
        1 => ['Business',   'bi-building'],
        2 => ['Operations', 'bi-graph-up'],
        3 => ['Investment', 'bi-cash-coin'],
        4 => ['Investor',   'bi-briefcase'],
        5 => ['Support',    'bi-signpost-split'],
        6 => ['Review',     'bi-check2-square'],
    ];
@endphp

{{-- ======================================================================
     DRAWER — EOI FORM
     ====================================================================== --}}
<div class="offcanvas offcanvas-end eoi-drawer" tabindex="-1" id="eoiDrawer" aria-labelledby="eoiDrawerLabel"
     data-bs-backdrop="static" data-open-on-load="{{ $errors->any() ? '1' : '0' }}">

    {{-- Header --}}
    <div class="offcanvas-header eoi-drawer-header">
        <div>
            <div class="eoi-drawer-eyebrow">CAFI Co-Investment Facility</div>
            <h5 class="offcanvas-title" id="eoiDrawerLabel">Enterprise Expression of Interest</h5>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>

    {{-- Stepper --}}
    <div class="eoi-stepper">
        @foreach ($steps as $n => [$label, $icon])
            <button type="button" class="eoi-step {{ $n === 1 ? 'active' : '' }}" data-step-target="{{ $n }}" {{ $n === 1 ? '' : 'disabled' }}>
                <span class="eoi-step-dot"><i class="bi {{ $icon }}"></i></span>
                <span class="eoi-step-label">{{ $label }}</span>
            </button>
        @endforeach
        <div class="eoi-progress"><div class="eoi-progress-bar" style="width: 0%"></div></div>
    </div>

    <form method="POST" action="#" id="eoiForm" class="d-flex flex-column flex-grow-1 overflow-hidden" novalidate>
        @csrf
        <input type="hidden" name="_method" value="POST" data-eoi-method>
        <input type="hidden" name="eoi_id" value="" data-eoi-id>

        <div class="offcanvas-body eoi-drawer-body">

            @if ($errors->any())
                <div class="alert alert-danger small">
                    <strong>Please check the form.</strong>
                    <ul class="mb-0 mt-1 ps-3">
                        @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
                    </ul>
                </div>
            @endif

            {{-- ───────────── STEP 1: BUSINESS ───────────── --}}
            <section class="eoi-step-panel" data-step="1">
                <div class="eoi-section-head">
                    <span class="eoi-q-badge">Q1</span>
                    <div>
                        <h6>Tell us about your business</h6>
                        <p>Who we'll be speaking to about this EOI.</p>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label" for="business_name">Business name <span class="req">*</span></label>
                        <input type="text" class="form-control" id="business_name" name="business_name" value="{{ old('business_name') }}" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="representative_name">Name of founder / authorised representative <span class="req">*</span></label>
                        <input type="text" class="form-control" id="representative_name" name="representative_name" value="{{ old('representative_name', auth()->user()->name ?? '') }}" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="phone">Telephone <span class="req">*</span></label>
                        <input type="tel" class="form-control" id="phone" name="phone" placeholder="+266 5xxx xxxx" value="{{ old('phone') }}" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="email">Email <span class="req">*</span></label>
                        <input type="email" class="form-control" id="email" name="email" value="{{ old('email', auth()->user()->email ?? '') }}" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="district">District <span class="req">*</span></label>
                        <select class="form-select" id="district" name="district" required>
                            <option value="">Select district…</option>
                            @foreach ($districts as $d)
                                <option value="{{ $d }}" @selected(old('district') === $d)>{{ $d }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="website">Website / social media <span class="opt">(if applicable)</span></label>
                        <input type="text" class="form-control" id="website" name="website" placeholder="https:// or @handle" value="{{ old('website') }}">
                    </div>
                </div>

                <div class="eoi-section-head mt-4">
                    <span class="eoi-q-badge">Q2</span>
                    <div>
                        <h6>Is your business legally established and operating in Lesotho? <span class="req">*</span></h6>
                    </div>
                </div>
                <div class="eoi-options">
                    @foreach ($legalStatuses as $value => $label)
                        <label class="eoi-option">
                            <input type="radio" name="legal_status" value="{{ $value }}" @checked(old('legal_status') === $value) required>
                            <span class="eoi-option-box"><span class="eoi-radio"></span>{{ $label }}</span>
                        </label>
                    @endforeach
                </div>

                <div class="mt-3" style="max-width: 220px;">
                    <label class="form-label" for="year_commenced">When did the business commence operations?</label>
                    <input type="number" class="form-control" id="year_commenced" name="year_commenced" min="1950" max="{{ date('Y') }}" placeholder="Year" value="{{ old('year_commenced') }}">
                </div>
            </section>

            {{-- ───────────── STEP 2: OPERATIONS ───────────── --}}
            <section class="eoi-step-panel" data-step="2" hidden>
                <div class="eoi-section-head">
                    <span class="eoi-q-badge">Q3</span>
                    <div>
                        <h6>What does your business do? <span class="req">*</span></h6>
                        <p>In no more than 100 words, describe your main product/service, the customers you serve and the problem or market opportunity you address.</p>
                    </div>
                </div>
                <textarea class="form-control" id="business_description" name="business_description" rows="6" data-max-words="100" required>{{ old('business_description') }}</textarea>
                <div class="eoi-wordcount" data-for="business_description"><span>0</span> / 100 words</div>

                <div class="eoi-section-head mt-4">
                    <span class="eoi-q-badge">Q4</span>
                    <div><h6>What is your current scale of operations?</h6></div>
                </div>

                <label class="form-label">Approximate annual turnover <span class="req">*</span></label>
                <div class="eoi-options eoi-options-grid">
                    @foreach ($turnoverBands as $value => $label)
                        <label class="eoi-option">
                            <input type="radio" name="annual_turnover" value="{{ $value }}" @checked(old('annual_turnover') === $value) required>
                            <span class="eoi-option-box"><span class="eoi-radio"></span>{{ $label }}</span>
                        </label>
                    @endforeach
                </div>

                <div class="mt-3" style="max-width: 260px;">
                    <label class="form-label" for="full_time_employees">Number of full-time employees <span class="req">*</span></label>
                    <input type="number" class="form-control" id="full_time_employees" name="full_time_employees" min="0" value="{{ old('full_time_employees') }}" required>
                </div>
            </section>

            {{-- ───────────── STEP 3: INVESTMENT ───────────── --}}
            <section class="eoi-step-panel" data-step="3" hidden>
                <div class="eoi-section-head">
                    <span class="eoi-q-badge">Q5</span>
                    <div><h6>What investment are you seeking?</h6></div>
                </div>

                <div style="max-width: 320px;">
                    <label class="form-label" for="investment_required_usd">Total investment required for your growth plans <span class="req">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text">US$</span>
                        <input type="number" class="form-control" id="investment_required_usd" name="investment_required_usd" min="0" step="1000" value="{{ old('investment_required_usd') }}" required>
                    </div>
                </div>

                <label class="form-label mt-3">What type of investment are you seeking or have you obtained? <span class="req">*</span></label>
                <div class="eoi-options eoi-options-inline" data-require-one>
                    @foreach ($investmentTypes as $value => $label)
                        <label class="eoi-option">
                            <input type="checkbox" name="investment_types[]" value="{{ $value }}" @checked(in_array($value, old('investment_types', [])))>
                            <span class="eoi-option-box"><span class="eoi-check"></span>{{ $label }}</span>
                        </label>
                    @endforeach
                </div>

                <div class="eoi-section-head mt-4">
                    <span class="eoi-q-badge">Q6</span>
                    <div>
                        <h6>How would the investment be used? <span class="req">*</span></h6>
                        <p>Select all that apply.</p>
                    </div>
                </div>
                <div class="eoi-options eoi-options-grid" data-require-one>
                    @foreach ($useOfFunds as $value => $label)
                        <label class="eoi-option">
                            <input type="checkbox" name="use_of_funds[]" value="{{ $value }}" @checked(in_array($value, old('use_of_funds', [])))
                                   @if ($value === 'other') data-reveal="use_of_funds_other_wrap" @endif>
                            <span class="eoi-option-box"><span class="eoi-check"></span>{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
                <div id="use_of_funds_other_wrap" class="mt-2" hidden>
                    <input type="text" class="form-control" name="use_of_funds_other" placeholder="Please specify" value="{{ old('use_of_funds_other') }}">
                </div>

                <label class="form-label mt-3" for="use_of_funds_description">Briefly describe the planned use of the investment <span class="req">*</span></label>
                <textarea class="form-control" id="use_of_funds_description" name="use_of_funds_description" rows="4" data-max-words="100" required>{{ old('use_of_funds_description') }}</textarea>
                <div class="eoi-wordcount" data-for="use_of_funds_description"><span>0</span> / 100 words</div>
            </section>

            {{-- ───────────── STEP 4: INVESTOR ───────────── --}}
            <section class="eoi-step-panel" data-step="4" hidden>
                <div class="eoi-section-head">
                    <span class="eoi-q-badge">Q7</span>
                    <div><h6>What is the status of your investor engagement? <span class="req">*</span></h6></div>
                </div>
                <div class="eoi-options">
                    @foreach ($investorStatuses as $value => $label)
                        <label class="eoi-option">
                            <input type="radio" name="investor_status" value="{{ $value }}" @checked(old('investor_status') === $value) required>
                            <span class="eoi-option-box"><span class="eoi-radio"></span>{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
                <div id="investor_status_other_wrap" class="mt-2" hidden>
                    <input type="text" class="form-control" name="investor_status_other" placeholder="Please specify" value="{{ old('investor_status_other') }}">
                </div>

                <div id="investorDetails" hidden>
                    <div class="eoi-section-head mt-4">
                        <span class="eoi-q-badge">Q8</span>
                        <div>
                            <h6>Investor commitment</h6>
                            <p>If you already have an investor, tell us about their commitment.</p>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="investor_amount_usd">Amount indicated or committed</label>
                            <div class="input-group">
                                <span class="input-group-text">US$</span>
                                <input type="number" class="form-control" id="investor_amount_usd" name="investor_amount_usd" min="0" step="1000" value="{{ old('investor_amount_usd') }}">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="investor_name">Investor name</label>
                            <input type="text" class="form-control" id="investor_name" name="investor_name" value="{{ old('investor_name') }}">
                        </div>
                    </div>

                    <label class="form-label mt-3">What evidence can you provide?</label>
                    <div class="eoi-options eoi-options-grid">
                        @foreach ($investorEvidence as $value => $label)
                            <label class="eoi-option">
                                <input type="checkbox" name="investor_evidence[]" value="{{ $value }}" @checked(in_array($value, old('investor_evidence', [])))>
                                <span class="eoi-option-box"><span class="eoi-check"></span>{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            </section>

            {{-- ───────────── STEP 5: SUPPORT + DECLARATION ───────────── --}}
            <section class="eoi-step-panel" data-step="5" hidden>
                <div class="eoi-section-head">
                    <span class="eoi-q-badge">Q9</span>
                    <div>
                        <h6>How would you like LEHSFF to support your investment journey? <span class="req">*</span></h6>
                        <p>Select the option that best describes your current need.</p>
                    </div>
                </div>
                <div class="eoi-options">
                    @foreach ($supportNeeds as $value => [$label, $icon])
                        <label class="eoi-option eoi-option-card">
                            <input type="radio" name="support_need" value="{{ $value }}" @checked(old('support_need') === $value) required>
                            <span class="eoi-option-box">
                                <span class="eoi-option-icon"><i class="bi {{ $icon }}"></i></span>
                                <span>{{ $label }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>

                <div class="eoi-section-head mt-4">
                    <span class="eoi-q-badge">Q10</span>
                    <div><h6>Declaration and consent</h6></div>
                </div>
                <div class="eoi-declaration">
                    I confirm that the information provided is accurate to the best of my knowledge. I understand that submission
                    of this EOI does not constitute a CAFI grant application or guarantee funding. I consent to LEHSFF/CAFI using the
                    information provided to assess my enterprise for investment facilitation, investor matchmaking and/or potential
                    participation in the CAFI Co-Investment Facility.
                </div>

                <div class="form-check my-3">
                    <input class="form-check-input" type="checkbox" id="declaration_agree" name="declaration_agree" value="1" @checked(old('declaration_agree')) required>
                    <label class="form-check-label fw-semibold" for="declaration_agree">I agree <span class="req">*</span></label>
                </div>

                <div class="row g-3">
                    <div class="col-md-7">
                        <label class="form-label" for="declaration_name">Name <span class="req">*</span></label>
                        <input type="text" class="form-control" id="declaration_name" name="declaration_name" value="{{ old('declaration_name', auth()->user()->name ?? '') }}" required>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label" for="declaration_date">Date <span class="req">*</span></label>
                        <input type="date" class="form-control" id="declaration_date" name="declaration_date" value="{{ old('declaration_date', now()->toDateString()) }}" required>
                    </div>
                </div>
            </section>

            {{-- ───────────── STEP 6: REVIEW ───────────── --}}
            <section class="eoi-step-panel" data-step="6" hidden>
                <div class="eoi-section-head">
                    <span class="eoi-q-badge"><i class="bi bi-eye"></i></span>
                    <div>
                        <h6>Review your Expression of Interest</h6>
                        <p>Check your answers. Click a section to edit it.</p>
                    </div>
                </div>
                <div id="eoiReview" class="eoi-review"></div>
            </section>
        </div>

        {{-- Footer --}}
        <div class="eoi-drawer-footer">
            <button type="submit" name="action" value="draft" class="btn btn-link text-decoration-none text-secondary px-0" formnovalidate>
                <i class="bi bi-save me-1"></i> Save draft
            </button>
            <div class="ms-auto d-flex gap-2">
                <button type="button" class="btn btn-outline-secondary" data-eoi-prev hidden>
                    <i class="bi bi-arrow-left me-1"></i> Back
                </button>
                <button type="button" class="btn btn-cif-primary" data-eoi-next>
                    Continue <i class="bi bi-arrow-right ms-1"></i>
                </button>
                <button type="submit" name="action" value="submit" class="btn btn-cif-accent" data-eoi-submit hidden>
                    <i class="bi bi-send me-1"></i> <span data-eoi-submit-label>Submit EOI</span>
                </button>
            </div>
        </div>
    </form>
</div>

{{-- ======================================================================
     STYLES
     ====================================================================== --}}
<style>
.eoi-page, .eoi-drawer {
    --cif-primary: #29317D; --cif-primary-dark: #1f2663;
    --cif-accent:  #089E49; --cif-accent-dark:  #07863e;
    --cif-navy:    #0d1a3a; --cif-soft: #f0f2fa; --cif-border: #e2e8f0;
}
.text-cif-primary { color: var(--cif-primary) !important; }
.text-cif-navy    { color: var(--cif-navy) !important; }
.btn-cif-primary  { background: var(--cif-primary); border-color: var(--cif-primary); color: #fff; font-weight: 600; }
.btn-cif-primary:hover { background: var(--cif-primary-dark); border-color: var(--cif-primary-dark); color: #fff; }
.btn-cif-accent   { background: var(--cif-accent); border-color: var(--cif-accent); color: #fff; font-weight: 600; }
.btn-cif-accent:hover { background: var(--cif-accent-dark); border-color: var(--cif-accent-dark); color: #fff; }

/* Status badges (also in layouts/app) */
.status-draft     { background: #e2e8f0; color: #475569; }
.status-submitted { background: rgba(41,49,125,.12); color: #29317D; }
.status-in-review { background: #fef3c7; color: #92400e; }
.status-referred  { background: #ffedd5; color: #9a3412; }
.status-approved  { background: rgba(8,158,73,.12); color: #07863e; }
.status-rejected  { background: #fee2e2; color: #b91c1c; }

/* Hero */
.eoi-hero { position: relative; overflow: hidden; border-radius: 1rem; padding: 2rem 2.25rem; color: #fff;
            background: linear-gradient(120deg, var(--cif-navy) 0%, var(--cif-primary) 100%); }
.eoi-hero-glow { position: absolute; right: -60px; top: -80px; width: 280px; height: 280px; border-radius: 50%;
                 background: var(--cif-accent); opacity: .35; filter: blur(70px); }
.eoi-chip { display: inline-flex; align-items: center; gap: .4rem; padding: .3rem .8rem; border-radius: 999px; font-size: .75rem;
            font-weight: 600; background: rgba(8,158,73,.2); color: #7ef0ae; border: 1px solid rgba(8,158,73,.4); }
.eoi-hero-title { font-weight: 800; font-size: 1.75rem; margin-bottom: .5rem; }
.eoi-hero-text { color: rgba(255,255,255,.78); max-width: 640px; }
.eoi-hero-meta { font-size: .78rem; color: rgba(255,255,255,.6); }
.btn-eoi-apply { background: var(--cif-accent); color: #fff; font-weight: 700; border: 0; border-radius: .75rem; padding: .8rem 1.5rem;
                 box-shadow: 0 10px 24px rgba(8,158,73,.35); }
.btn-eoi-apply:hover { background: var(--cif-accent-dark); color: #fff; transform: translateY(-1px); }
.eoi-note { background: #fff; border: 1px solid var(--cif-border); border-left: 4px solid var(--cif-primary); color: #475569; }
.eoi-note i { color: var(--cif-primary); }

.eoi-empty-icon { width: 64px; height: 64px; margin: 0 auto; border-radius: 16px; background: var(--cif-soft); color: var(--cif-primary);
                  display: flex; align-items: center; justify-content: center; font-size: 1.75rem; }

.eoi-question { display: flex; gap: .75rem; align-items: flex-start; padding: .75rem; border-radius: .6rem; background: #f8fafc; height: 100%; }
.eoi-question-no { width: 28px; height: 28px; flex-shrink: 0; border-radius: 8px; background: var(--cif-primary); color: #fff;
                   font-size: .75rem; font-weight: 700; display: flex; align-items: center; justify-content: center; }

.eoi-timeline { list-style: none; padding: 0; margin: 0; position: relative; }
.eoi-timeline::before { content: ''; position: absolute; left: 17px; top: 8px; bottom: 8px; width: 2px; background: var(--cif-border); }
.eoi-timeline li { position: relative; display: flex; gap: .9rem; padding-bottom: 1.1rem; }
.eoi-timeline li:last-child { padding-bottom: 0; }
.eoi-timeline .dot { position: relative; z-index: 1; width: 36px; height: 36px; flex-shrink: 0; border-radius: 50%; background: #fff;
                     border: 2px solid var(--cif-border); color: #94a3b8; display: flex; align-items: center; justify-content: center; }
.eoi-timeline li.done .dot { background: var(--cif-accent); border-color: var(--cif-accent); color: #fff; }
.eoi-timeline strong { font-size: .85rem; color: var(--cif-navy); }
.eoi-timeline p { font-size: .76rem; color: #64748b; margin: 0; }

.eoi-pathway { border-radius: .6rem; padding: .7rem .9rem; border-left: 4px solid; }
.eoi-pathway.tone-accent  { background: rgba(8,158,73,.07);  border-color: var(--cif-accent);  color: #065f2e; }
.eoi-pathway.tone-primary { background: rgba(41,49,125,.06); border-color: var(--cif-primary); color: var(--cif-primary); }
.eoi-pathway.tone-muted   { background: #f8fafc; border-color: #cbd5e1; color: #475569; }

/* ── Drawer ── */
.eoi-drawer { width: 760px !important; max-width: 100vw; display: flex; flex-direction: column; }
.eoi-drawer-header { background: linear-gradient(120deg, var(--cif-navy), var(--cif-primary)); color: #fff; padding: 1.25rem 1.5rem; }
.eoi-drawer-eyebrow { font-size: .7rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: #7ef0ae; }
.eoi-drawer-header .offcanvas-title { font-weight: 700; }

.eoi-stepper { position: relative; display: flex; justify-content: space-between; padding: 1rem 1.5rem 1.25rem;
               border-bottom: 1px solid var(--cif-border); background: #fff; }
.eoi-step { position: relative; z-index: 1; border: 0; background: none; display: flex; flex-direction: column; align-items: center; gap: .35rem; padding: 0; }
.eoi-step-dot { width: 36px; height: 36px; border-radius: 50%; background: #fff; border: 2px solid var(--cif-border); color: #94a3b8;
                display: flex; align-items: center; justify-content: center; transition: all .2s; }
.eoi-step-label { font-size: .7rem; font-weight: 600; color: #94a3b8; }
.eoi-step.active .eoi-step-dot { border-color: var(--cif-primary); background: var(--cif-primary); color: #fff; box-shadow: 0 0 0 4px rgba(41,49,125,.12); }
.eoi-step.active .eoi-step-label { color: var(--cif-primary); }
.eoi-step.done .eoi-step-dot { border-color: var(--cif-accent); background: var(--cif-accent); color: #fff; }
.eoi-step.done .eoi-step-label { color: var(--cif-accent); }
.eoi-step:disabled { cursor: default; }
.eoi-progress { position: absolute; left: calc(1.5rem + 18px); right: calc(1.5rem + 18px); top: calc(1rem + 17px); height: 2px; background: var(--cif-border); }
.eoi-progress-bar { height: 100%; background: var(--cif-accent); transition: width .3s ease; }

.eoi-drawer-body { padding: 1.5rem; background: #fbfcfe; flex: 1; overflow-y: auto; }
.eoi-step-panel.is-entering { animation: eoiIn .25s ease-out; }
@keyframes eoiIn { from { opacity: 0; transform: translateX(12px); } to { opacity: 1; transform: none; } }

.eoi-section-head { display: flex; gap: .85rem; align-items: flex-start; margin-bottom: 1rem; }
.eoi-section-head h6 { font-weight: 700; color: var(--cif-navy); margin: .3rem 0 .15rem; }
.eoi-section-head p { font-size: .8rem; color: #64748b; margin: 0; }
.eoi-q-badge { min-width: 36px; height: 36px; padding: 0 .4rem; border-radius: 10px; background: rgba(41,49,125,.1); color: var(--cif-primary);
               font-weight: 800; font-size: .78rem; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }

.eoi-drawer .form-label { font-size: .83rem; font-weight: 600; color: #334155; }
.eoi-drawer .form-control, .eoi-drawer .form-select { border-color: #cbd5e1; border-radius: .5rem; padding: .6rem .8rem; font-size: .88rem; }
.eoi-drawer .form-control:focus, .eoi-drawer .form-select:focus { border-color: var(--cif-primary); box-shadow: 0 0 0 .2rem rgba(41,49,125,.12); }
.eoi-drawer .form-check-input:checked { background-color: var(--cif-accent); border-color: var(--cif-accent); }
.eoi-drawer .is-invalid { border-color: #dc3545 !important; }
.req { color: #dc3545; }
.opt { color: #94a3b8; font-weight: 400; }

/* Option cards */
.eoi-options { display: grid; gap: .5rem; }
.eoi-options-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
.eoi-options-inline { grid-template-columns: repeat(3, minmax(0, 1fr)); }
@media (max-width: 575.98px) { .eoi-options-grid, .eoi-options-inline { grid-template-columns: 1fr; } }
.eoi-option { position: relative; margin: 0; cursor: pointer; }
.eoi-option input { position: absolute; opacity: 0; pointer-events: none; }
.eoi-option-box { display: flex; align-items: center; gap: .7rem; height: 100%; padding: .7rem .9rem; border: 1.5px solid var(--cif-border);
                  border-radius: .6rem; background: #fff; font-size: .85rem; color: #334155; transition: all .15s; }
.eoi-option:hover .eoi-option-box { border-color: #a5acd8; }
.eoi-option input:focus-visible + .eoi-option-box { box-shadow: 0 0 0 .2rem rgba(41,49,125,.15); }
.eoi-option input:checked + .eoi-option-box { border-color: var(--cif-accent); background: rgba(8,158,73,.05); color: var(--cif-navy); font-weight: 500; }
.eoi-radio, .eoi-check { width: 18px; height: 18px; flex-shrink: 0; border: 2px solid #cbd5e1; background: #fff; transition: all .15s; position: relative; }
.eoi-radio { border-radius: 50%; }
.eoi-check { border-radius: 5px; }
.eoi-option input:checked + .eoi-option-box .eoi-radio { border-color: var(--cif-accent); box-shadow: inset 0 0 0 4px #fff; background: var(--cif-accent); }
.eoi-option input:checked + .eoi-option-box .eoi-check { border-color: var(--cif-accent); background: var(--cif-accent); }
.eoi-option input:checked + .eoi-option-box .eoi-check::after { content: ''; position: absolute; left: 4px; top: 0; width: 6px; height: 10px;
                                                                border: solid #fff; border-width: 0 2px 2px 0; transform: rotate(45deg); }
.eoi-options.is-invalid .eoi-option-box { border-color: #f1a7ae; }

.eoi-option-card .eoi-option-box { align-items: flex-start; padding: .85rem 1rem; }
.eoi-option-icon { width: 32px; height: 32px; flex-shrink: 0; border-radius: 8px; background: var(--cif-soft); color: var(--cif-primary);
                   display: flex; align-items: center; justify-content: center; }
.eoi-option-card input:checked + .eoi-option-box .eoi-option-icon { background: var(--cif-accent); color: #fff; }

.eoi-wordcount { text-align: right; font-size: .72rem; color: #94a3b8; margin-top: .3rem; }
.eoi-wordcount.over { color: #dc3545; font-weight: 600; }

.eoi-declaration { background: #fff; border: 1px solid var(--cif-border); border-left: 4px solid var(--cif-primary);
                   border-radius: .6rem; padding: 1rem; font-size: .83rem; color: #475569; line-height: 1.6; }

/* Review */
.eoi-review-section { background: #fff; border: 1px solid var(--cif-border); border-radius: .75rem; margin-bottom: .75rem; overflow: hidden; }
.eoi-review-head { display: flex; justify-content: space-between; align-items: center; padding: .65rem 1rem; background: #f8fafc;
                   border-bottom: 1px solid var(--cif-border); font-weight: 700; font-size: .83rem; color: var(--cif-navy); }
.eoi-review-head button { border: 0; background: none; font-size: .78rem; font-weight: 600; color: var(--cif-primary); }
.eoi-review-head button:hover { color: var(--cif-accent); }
.eoi-review dl { display: grid; grid-template-columns: 40% 60%; margin: 0; padding: .5rem 1rem; font-size: .82rem; }
.eoi-review dt { font-weight: 500; color: #64748b; padding: .35rem 0; }
.eoi-review dd { margin: 0; padding: .35rem 0; color: var(--cif-navy); word-break: break-word; }
.eoi-review dd.empty { color: #cbd5e1; font-style: italic; }

.eoi-drawer-footer { display: flex; align-items: center; gap: .75rem; padding: 1rem 1.5rem; border-top: 1px solid var(--cif-border); background: #fff; }

@media (max-width: 575.98px) {
    .eoi-step-label { display: none; }
    .eoi-drawer-body { padding: 1rem; }
    .eoi-hero { padding: 1.5rem; }
}
</style>

{{-- ======================================================================
     SCRIPT
     ====================================================================== --}}
<script>
document.addEventListener('DOMContentLoaded', function () {
    var drawerEl = document.getElementById('eoiDrawer');
    var form     = document.getElementById('eoiForm');
    if (!drawerEl || !form) return;

    var panels   = form.querySelectorAll('.eoi-step-panel');
    var stepBtns = drawerEl.querySelectorAll('.eoi-step');
    var bar      = drawerEl.querySelector('.eoi-progress-bar');
    var btnPrev  = form.querySelector('[data-eoi-prev]');
    var btnNext  = form.querySelector('[data-eoi-next]');
    var btnSub   = form.querySelector('[data-eoi-submit]');
    var body     = form.querySelector('.eoi-drawer-body');
    var total    = panels.length;
    var current  = 1;
    var reached  = 1;

    /* ---------- Navigation ---------- */
    function go(step) {
        current = step;
        reached = Math.max(reached, step);

        panels.forEach(function (p) {
            var active = +p.dataset.step === step;
            p.hidden = !active;
            p.classList.remove('is-entering');
            if (active) { void p.offsetWidth; p.classList.add('is-entering'); }
        });
        stepBtns.forEach(function (b) {
            var n = +b.dataset.stepTarget;
            b.classList.toggle('active', n === step);
            b.classList.toggle('done', n < step);
            b.disabled = n > reached;
        });
        bar.style.width = ((step - 1) / (total - 1) * 100) + '%';
        btnPrev.hidden = step === 1;
        btnNext.hidden = step === total;
        btnSub.hidden  = step !== total;
        if (step === total) buildReview();
        body.scrollTop = 0;
    }

    btnNext.addEventListener('click', function () { if (validateStep(current)) go(current + 1); });
    btnPrev.addEventListener('click', function () { go(current - 1); });
    stepBtns.forEach(function (b) {
        b.addEventListener('click', function () {
            var target = +b.dataset.stepTarget;
            if (target < current || validateStep(current)) go(target);
        });
    });

    /* ---------- Validation (per step) ---------- */
    function validateStep(step) {
        var panel = form.querySelector('.eoi-step-panel[data-step="' + step + '"]');
        var ok = true, firstBad = null;

        panel.querySelectorAll('input, select, textarea').forEach(function (el) {
            if (inHiddenConditional(el)) return; // skip hidden conditional fields
            if (el.type === 'radio' || el.type === 'checkbox') return;
            var bad = !el.checkValidity() || tooManyWords(el);
            el.classList.toggle('is-invalid', bad);
            if (bad) { ok = false; firstBad = firstBad || el; }
        });

        // Required radio groups
        var groups = {};
        panel.querySelectorAll('input[type=radio][required]').forEach(function (r) { groups[r.name] = r; });
        Object.keys(groups).forEach(function (name) {
            var checked = panel.querySelector('input[name="' + name + '"]:checked');
            var wrap = groups[name].closest('.eoi-options');
            if (wrap) wrap.classList.toggle('is-invalid', !checked);
            if (!checked) { ok = false; firstBad = firstBad || wrap; }
        });

        // "Select at least one" checkbox groups
        panel.querySelectorAll('[data-require-one]').forEach(function (wrap) {
            var any = wrap.querySelector('input:checked');
            wrap.classList.toggle('is-invalid', !any);
            if (!any) { ok = false; firstBad = firstBad || wrap; }
        });

        // Single required checkbox (declaration)
        panel.querySelectorAll('input[type=checkbox][required]').forEach(function (c) {
            c.classList.toggle('is-invalid', !c.checked);
            if (!c.checked) { ok = false; firstBad = firstBad || c; }
        });

        if (firstBad) firstBad.scrollIntoView({ behavior: 'smooth', block: 'center' });
        return ok;
    }

    form.addEventListener('input',  function (e) { e.target.classList.remove('is-invalid'); var w = e.target.closest('.eoi-options'); if (w) w.classList.remove('is-invalid'); });
    form.addEventListener('change', function (e) { var w = e.target.closest('.eoi-options'); if (w) w.classList.remove('is-invalid'); });

    // Final submit: validate every step
    form.addEventListener('submit', function (e) {
        if (e.submitter && e.submitter.value === 'draft') return;
        for (var s = 1; s < total; s++) {
            if (!validateStep(s)) { e.preventDefault(); go(s); validateStep(s); return; }
        }
    });

    /* ---------- Word counters (100 words) ---------- */
    function words(text) { return (text.trim().match(/\S+/g) || []).length; }
    function tooManyWords(el) { return el.dataset.maxWords && words(el.value) > +el.dataset.maxWords; }
    form.querySelectorAll('[data-max-words]').forEach(function (el) {
        var counter = form.querySelector('.eoi-wordcount[data-for="' + el.id + '"]');
        function update() {
            var n = words(el.value);
            counter.querySelector('span').textContent = n;
            counter.classList.toggle('over', n > +el.dataset.maxWords);
        }
        el.addEventListener('input', update); update();
    });

    /* ---------- Conditional fields ---------- */
    function syncConditionals() {
        var useOther = form.querySelector('input[name="use_of_funds[]"][value="other"]');
        document.getElementById('use_of_funds_other_wrap').hidden = !useOther.checked;

        var status = (form.querySelector('input[name="investor_status"]:checked') || {}).value;
        document.getElementById('investor_status_other_wrap').hidden = status !== 'other';
        document.getElementById('investorDetails').hidden = !status || status === 'none';
    }
    form.addEventListener('change', syncConditionals);
    syncConditionals();

    /* ---------- Review ---------- */
    var reviewMap = [
        { step: 1, title: 'Business', fields: [
            ['business_name', 'Business name'], ['representative_name', 'Representative'], ['phone', 'Telephone'],
            ['email', 'Email'], ['district', 'District'], ['website', 'Website / social'],
            ['legal_status', 'Legal status'], ['year_commenced', 'Operating since'] ] },
        { step: 2, title: 'Operations', fields: [
            ['business_description', 'What the business does'], ['annual_turnover', 'Annual turnover'],
            ['full_time_employees', 'Full-time employees'] ] },
        { step: 3, title: 'Investment', fields: [
            ['investment_required_usd', 'Investment required (US$)', 'money'], ['investment_types[]', 'Investment type'],
            ['use_of_funds[]', 'Use of funds'], ['use_of_funds_other', 'Other use'], ['use_of_funds_description', 'Planned use'] ] },
        { step: 4, title: 'Investor', fields: [
            ['investor_status', 'Investor engagement'], ['investor_status_other', 'Other status'],
            ['investor_amount_usd', 'Investor amount (US$)', 'money'], ['investor_name', 'Investor name'],
            ['investor_evidence[]', 'Evidence'] ] },
        { step: 5, title: 'Support & declaration', fields: [
            ['support_need', 'Support needed'], ['declaration_agree', 'Declaration'],
            ['declaration_name', 'Name'], ['declaration_date', 'Date'] ] },
    ];

    function labelFor(input) {
        var box = input.closest('.eoi-option');
        return box ? box.querySelector('.eoi-option-box').textContent.trim() : input.value;
    }

    function valueOf(name, type) {
        var els = form.querySelectorAll('[name="' + name + '"]');
        if (!els.length) return '';
        var first = els[0];
        if (inHiddenConditional(first)) return null; // conditional field not shown → skip in review
        if (first.type === 'radio' || first.type === 'checkbox') {
            var picked = Array.prototype.filter.call(els, function (e) { return e.checked; });
            if (name === 'declaration_agree') return picked.length ? 'I agree' : '';
            return picked.map(labelFor).join(', ');
        }
        if (first.tagName === 'SELECT') return first.value ? first.options[first.selectedIndex].text : '';
        if (type === 'money' && first.value) return Number(first.value).toLocaleString();
        return first.value.trim();
    }

    function inHiddenConditional(el) {
        for (var n = el.parentElement; n && n !== form; n = n.parentElement) {
            if (n.hidden && !n.classList.contains('eoi-step-panel')) return true;
        }
        return false;
    }

    function esc(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

    function buildReview() {
        var html = '';
        reviewMap.forEach(function (sec) {
            var rows = '';
            sec.fields.forEach(function (f) {
                var v = valueOf(f[0], f[2]);
                if (v === null) return;
                rows += '<dt>' + esc(f[1]) + '</dt><dd class="' + (v ? '' : 'empty') + '">' + (v ? esc(v) : 'Not provided') + '</dd>';
            });
            html += '<div class="eoi-review-section">' +
                        '<div class="eoi-review-head">' + esc(sec.title) +
                            '<button type="button" data-edit-step="' + sec.step + '"><i class="bi bi-pencil me-1"></i>Edit</button>' +
                        '</div><dl>' + rows + '</dl></div>';
        });
        document.getElementById('eoiReview').innerHTML = html;
    }
    document.getElementById('eoiReview').addEventListener('click', function (e) {
        var btn = e.target.closest('[data-edit-step]');
        if (btn) go(+btn.dataset.editStep);
    });

    /* ---------- Open automatically after a failed server-side submit ---------- */
    if (drawerEl.dataset.openOnLoad === '1' && window.bootstrap) {
        bootstrap.Offcanvas.getOrCreateInstance(drawerEl).show();
    }

    /* ---------- Public API: open new / pre-filled ---------- */
    var titleEl   = document.getElementById('eoiDrawerLabel');
    var submitLbl = form.querySelector('[data-eoi-submit-label]');

    function fill(data) {
        Object.keys(data || {}).forEach(function (key) {
            var val = data[key];
            var els = form.querySelectorAll('[name="' + key + '"], [name="' + key + '[]"]');
            els.forEach(function (el) {
                if (el.type === 'radio')         el.checked = el.value === String(val);
                else if (el.type === 'checkbox') el.checked = Array.isArray(val) ? val.indexOf(el.value) !== -1 : !!val;
                else                             el.value = val == null ? '' : val;
            });
        });
        form.querySelectorAll('[data-max-words]').forEach(function (el) { el.dispatchEvent(new Event('input')); });
        syncConditionals();
    }

    window.eoiDrawer = {
        open: function (data, mode) {
            var isEdit = mode === 'edit';
            form.reset();
            form.querySelectorAll('.is-invalid').forEach(function (el) { el.classList.remove('is-invalid'); });
            form.querySelector('[data-eoi-method]').value = isEdit ? 'PUT' : 'POST';
            form.querySelector('[data-eoi-id]').value     = isEdit && data && data.id ? data.id : '';
            titleEl.textContent   = isEdit ? 'Edit Expression of Interest' + (data && data.reference ? ' · ' + data.reference : '') : 'Enterprise Expression of Interest';
            submitLbl.textContent = isEdit ? 'Update & resubmit' : 'Submit EOI';
            if (data) fill(data);
            reached = isEdit ? total - 1 : 1;   // edit: allow jumping between steps
            go(1);
            bootstrap.Offcanvas.getOrCreateInstance(drawerEl).show();
        }
    };

    // Plain "Apply" buttons (data-bs-toggle) open a fresh form
    drawerEl.addEventListener('show.bs.offcanvas', function (e) {
        if (e.relatedTarget && e.relatedTarget.hasAttribute('data-eoi-new')) {
            form.reset(); syncConditionals();
            form.querySelectorAll('[data-max-words]').forEach(function (el) { el.dispatchEvent(new Event('input')); });
            form.querySelector('[data-eoi-method]').value = 'POST';
            form.querySelector('[data-eoi-id]').value = '';
            titleEl.textContent = 'Enterprise Expression of Interest';
            submitLbl.textContent = 'Submit EOI';
            reached = 1; go(1);
        }
    });

    go(1);
});
</script>