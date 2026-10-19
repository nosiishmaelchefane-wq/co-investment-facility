<?php

use App\Models\EligibilityScreening;
use App\Models\EnterpriseDocument;
use App\Models\EnterpriseProfile;
use App\Models\ExpressionOfInterest;
use App\Notifications\EoiGate1Decision;
use App\Notifications\EoiReturnedForCorrection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/*
| G1 · Administrative review & eligibility screening (CI Facility Operations Manual §3.2)
|   Used in the EOI page:  <livewire:expression-of-interest.eligibility.check-eligibility :eoi-id="$eoi->id" />
|
|   Stage 1 · §3.2.1 Completeness review — 7 checks, auto-checked by the platform, confirmed by the officer.
|             → Confirm complete (screening opens)  or  Return for correction (EOI → returned)
|   Stage 2 · §3.2.2 Eligibility screening — the 8 CAFI checklist criteria, grouped into the 6 screening areas.
|             Each criterion shows the information the applicant submitted (EOI + enterprise profile + documents).
|   Stage 3 · §3.2.3 Gate 1 decision — Pass → invited · Clarification required → returned · Not eligible → not_progressed
|
|   Screening officer: gate1.decide.  Other staff: read-only.  Applicant: progress, outstanding items and outcome.
|   A returned EOI that is resubmitted starts a new round (criteria answers carried over, completeness re-checked).
*/
new class extends Component
{
    #[Locked]
    public int $eoiId;

    #[Locked]
    public ?int $screeningId = null;

    #[Locked]
    public int $round = 1;

    /** completeness check => ['answer' => yes|no|null, 'note' => string] */
    public array $completeness = [];

    /** criterion => ['answer' => yes|no|clarify|null, 'note' => string] */
    public array $answers = [];

    public ?string $decision = null;
    public string $comments  = '';
    public string $screenedOn = '';

    public function mount(int $eoiId): void
    {
        $eoi = ExpressionOfInterest::findOrFail($eoiId);

        abort_unless($eoi->isViewableBy(auth()->user()), 403);

        $this->eoiId = $eoi->id;
        $this->loadScreening();
    }

    /* =====================================================================
     | Data
     ===================================================================== */

    #[Computed]
    public function eoi(): ExpressionOfInterest
    {
        return ExpressionOfInterest::with('user')->findOrFail($this->eoiId);
    }

    #[Computed]
    public function screening(): ?EligibilityScreening
    {
        return $this->screeningId
            ? EligibilityScreening::with('screener:id,name', 'completenessConfirmer:id,name')->find($this->screeningId)
            : null;
    }

    /** completeness | screening | decided */
    #[Computed]
    public function stage(): string
    {
        return $this->screening?->stage() ?? EligibilityScreening::STAGE_COMPLETENESS;
    }

    /** Earlier, decided rounds (newest first) */
    #[Computed]
    public function history()
    {
        return EligibilityScreening::with('screener:id,name')
            ->where('expression_of_interest_id', $this->eoiId)
            ->whereNotNull('decided_at')
            ->when($this->screeningId, fn ($q) => $q->whereKeyNot($this->screeningId))
            ->orderByDesc('round')
            ->get();
    }

    /** §3.2.1 — what the platform checks automatically: check => ['met' => bool, 'missing' => [...]] */
    #[Computed]
    public function auto(): array
    {
        return EligibilityScreening::autoCompleteness($this->eoi, $this->profile);
    }

    /* =====================================================================
     | Submitted information per criterion (EOI + enterprise profile)
     ===================================================================== */

    /** EOI option labels (same keys as the apply form) */
    protected const EOI_LABELS = [
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

    /**
     * Profile attachments relevant to each criterion (CAFI application form).
     * Registration documents only appear when they apply to the applicant's answers (OBFC / other country).
     */
    protected const CRITERIA_DOCS = [
        'enterprise'   => ['registration_cert', 'registration_undertaking', 'foreign_registration', 'shareholders_list'],
        'investor'     => ['cap_table', 'shareholders_list'],
        'commitment'   => ['commitment'],
        'transaction'  => ['commitment'],
        'funding'      => ['financials', 'projections'],
        'use_of_funds' => ['budget', 'business_plan'],
        'alignment'    => ['employee_list'],
        'exclusions'   => ['key_individuals', 'ids', 'team_resumes', 'tax_clearance'],
    ];

    /** The applicant's enterprise profile (null if never started) */
    #[Computed]
    public function profile(): ?EnterpriseProfile
    {
        return EnterpriseProfile::with('documents')->where('user_id', $this->eoi->user_id)->first();
    }

    /**
     * criterion => ['rows' => [[label, value, source, sub?, flag?]], 'docs' => [[label, ?EnterpriseDocument]]]
     *   value: string | array (list) | null (not provided)
     *   source: 'EOI' | 'Profile'   flag: null | 'ok' | 'bad'
     */
    #[Computed]
    public function evidence(): array
    {
        $eoi  = $this->eoi;
        $p    = $this->profile;
        $rate = (float) config('cif.usd_to_lsl', 18.00);

        $l    = fn ($f, $v) => $v === null || $v === '' ? null : (self::EOI_LABELS[$f][$v] ?? $v);
        $ls   = fn ($f, $vs) => collect($vs ?? [])->map(fn ($v) => self::EOI_LABELS[$f][$v] ?? $v)->values()->all() ?: null;
        $num  = fn ($v, $suffix = '') => $v === null || $v === '' ? null : number_format((float) $v).$suffix;
        $date = fn ($d) => $d?->format('d M Y');

        // Money: EOI amounts are US$, profile amounts are Maloti — always show M first, US$ underneath
        $fromUsd = fn ($usd) => $usd === null ? [null, null] : ['M '.number_format((float) $usd * $rate), 'US$ '.number_format((float) $usd)];
        $fromLsl = fn ($lsl) => $lsl === null ? [null, null] : ['M '.number_format((float) $lsl), 'US$ '.number_format((float) $lsl / max($rate, 0.0001))];

        $eoiRow  = fn ($label, $value, $sub = null, $flag = null) => [$label, $value, 'EOI', $sub, $flag];
        $proRow  = fn ($label, $value, $sub = null, $flag = null) => [$label, $value, 'Profile', $sub, $flag];
        $money   = fn ($src, $label, array $m) => [$label, $m[0], $src, $m[1], null];
        $checks  = fn (array $defs, ?array $values) => collect($defs)->map(fn ($text, $k) => [$text, ! empty($values[$k])])->values()->all();

        $profileOnly = fn (array $rows) => $p ? $rows : [];

        $ev = [
            'enterprise' => [
                $eoiRow('Business name', $eoi->business_name),
                $eoiRow('Legal status', $l('legal_status', $eoi->legal_status)),
                $eoiRow('Year commenced', $eoi->year_commenced),
                $eoiRow('District', $eoi->district),
                $eoiRow('Full-time employees', $num($eoi->full_time_employees)),
                $eoiRow('Annual turnover', $l('annual_turnover', $eoi->annual_turnover)),
                ...$profileOnly([
                    $proRow('Registered name', $p?->registered_name),
                    $proRow('Address', collect([$p?->address, $p?->city, $p?->district])->filter()->implode(', ') ?: null),
                    $proRow('Registered at OBFC', $p?->obfc_registered === null ? null : ($p->obfc_registered ? 'Yes' : 'No'), null, $p?->obfc_registered === false ? 'bad' : null),
                    $proRow('Registration no.', $p?->registration_no),
                    $proRow('Type of registration', $p?->legal_form),
                    $proRow('Registered in another country', $p?->foreign_registered ? ($p->foreign_country ?: 'Yes') : ($p?->foreign_registered === false ? 'No' : null)),
                    $proRow('Year established', $p?->year_established),
                    $proRow('Stage of development', EnterpriseProfile::STAGES[$p?->company_stage ?? ''] ?? null),
                    $proRow('Sector', $p?->sector),
                    $proRow('Ownership', $p?->ownershipLine()),
                    $proRow('Business activity', $p?->activity),
                ]),
            ],
            'investor' => [
                $eoiRow('Investor', $eoi->investor_name),
                $eoiRow('Investor status', $l('investor_status', $eoi->investor_status), $eoi->investor_status === 'other' ? $eoi->investor_status_other : null),
                ...$profileOnly([
                    $proRow('Investors in the round', collect($p?->investorRows() ?? [])
                        ->map(fn ($r) => $r['name'].(filled($r['country'] ?? null) ? ' ('.$r['country'].')' : '').(filled($r['ticket'] ?? null) ? ' — M '.number_format((float) $r['ticket']) : ''))
                        ->all() ?: null),
                    $proRow('Lead investor type', $p?->investor_type),
                    $proRow('Financing type', EnterpriseProfile::PARTICIPATION[$p?->participation ?? ''] ?? $p?->participation),
                    $proRow('Investment history', $p ? \App\Models\EnterpriseInvestment::summaryLine($p->investments) : null,
                        'Open the Investments tab to see each investor, how often and how much they have invested'),
                    $proRow('Investor track record (whole facility)', $p ? (\App\Models\EnterpriseInvestment::trackRecordLines($p) ?: null) : null),
                ]),
            ],
            'commitment' => [
                $eoiRow('Investor status', $l('investor_status', $eoi->investor_status)),
                $eoiRow('Evidence available', $ls('investor_evidence', $eoi->investor_evidence)),
                ...$profileOnly([
                    $proRow('Status of the deal', EnterpriseProfile::TRANSACTION_STATUSES[$p?->transaction_status ?? ''] ?? $p?->transaction_status),
                ]),
            ],
            'transaction' => [
                $eoiRow('Investment type(s)', $ls('investment_types', $eoi->investment_types)),
                ...$profileOnly([
                    $proRow('Financing type', EnterpriseProfile::PARTICIPATION[$p?->participation ?? ''] ?? $p?->participation),
                    $proRow('Status of the deal', EnterpriseProfile::TRANSACTION_STATUSES[$p?->transaction_status ?? ''] ?? $p?->transaction_status),
                ]),
            ],
            'funding' => [
                $money('EOI', 'Investment required', $fromUsd($eoi->investment_required_usd)),
                $money('EOI', 'Investor amount', $fromUsd($eoi->investor_amount_usd)),
                ...$profileOnly([
                    $money('Profile', 'Current investment round', $fromLsl($p?->round_amount)),
                    $money('Profile', 'Total project cost', $fromLsl($p?->project_cost)),
                    $money('Profile', 'Investor tickets (total)', $fromLsl($p?->investor_contribution)),
                    $money('Profile', 'Annual revenue (past year)', $fromLsl($p?->annual_revenue)),
                    $money('Profile', 'CAFI matching grant ('.EnterpriseProfile::grantRatioLabel().' of investor)', $fromLsl($p?->grant_requested)),
                ]),
            ],
            'use_of_funds' => [
                $eoiRow('Intended use', $ls('use_of_funds', $eoi->use_of_funds), in_array('other', $eoi->use_of_funds ?? [], true) ? $eoi->use_of_funds_other : null),
                $eoiRow('Description', $eoi->use_of_funds_description),
                ...$profileOnly([
                    $proRow('What the money is for', $p?->investment_purpose),
                ]),
            ],
            'alignment' => [
                $eoiRow('Support needed', $l('support_need', $eoi->support_need)),
                $eoiRow('Business description', $eoi->business_description),
                ...$profileOnly([
                    $proRow('Sector', $p?->sector),
                    $proRow('Employees (current year)', $num($p?->employees), $p?->employeeBand() ? 'Number of employees: '.$p->employeeBand() : null),
                    $proRow('Employees (previous year)', $num($p?->employees_prev_year)),
                    $proRow('Main business model', EnterpriseProfile::BUSINESS_MODELS[$p?->business_model ?? ''] ?? null),
                    $proRow('Women / youth owned or managed', $p ? collect(['Women' => $p->women_owned_managed, 'Youth' => $p->youth_owned_managed])
                        ->map(fn ($v, $k) => $k.': '.($v === null ? '—' : ($v ? 'Yes' : 'No')))->implode(' · ') : null),
                    $proRow('Ownership', $p?->ownershipLine()),
                    $proRow('Current markets', $p?->labels(EnterpriseProfile::MARKETS, $p?->markets)),
                    $proRow('Products, customers & markets', $p?->customers),
                ]),
            ],
            'exclusions' => [
                $eoiRow('EOI declaration', $eoi->declaration_agree ? 'Agreed' : 'Not agreed',
                    trim(($eoi->declaration_name ?: '').($eoi->declaration_date ? ' · '.$eoi->declaration_date->format('d M Y') : ''), ' ·') ?: null,
                    $eoi->declaration_agree ? 'ok' : 'bad'),
                ...$profileOnly([
                    $proRow('E&S checklist', $p && $p->es_checklist
                        ? (($gaps = $p->esGaps()) ? collect($gaps)->map(fn ($k) => EnterpriseProfile::ES_CHECKLIST[$k][0])->all() : 'All requirements met')
                        : null, $p?->es_checklist ? (count($p->esGaps()) ? count($p->esGaps()).' to address after award' : null) : null,
                        $p?->es_checklist ? ($p->esGaps() ? 'bad' : 'ok') : null),
                    $proRow('Profile declaration', $p?->declaration_agreed ? 'Signed' : 'Not signed',
                        $p?->declared_name ? $p->declared_name.($p->declared_on ? ' · '.$p->declared_on->format('d M Y') : '') : null,
                        $p?->declaration_agreed ? 'ok' : 'bad'),
                ]),
            ],
        ];

        // Which documents apply to this applicant, and which are required (depends on their registration answers)
        $required   = $p?->requiredDocumentTypes() ?? array_keys(array_filter(EnterpriseDocument::TYPES, fn ($t) => $t[3] === true));
        $applicable = fn (string $type) => isset(EnterpriseDocument::TYPES[$type])
            && (is_bool(EnterpriseDocument::TYPES[$type][3]) || in_array($type, $required, true));

        return collect($ev)->map(fn ($rows, $key) => [
            'rows' => $rows,
            'docs' => collect(self::CRITERIA_DOCS[$key] ?? [])
                ->filter($applicable)
                ->map(fn ($type) => [EnterpriseDocument::TYPES[$type][0], $p?->document($type), in_array($type, $required, true)])
                ->values()->all(),
        ])->all();
    }

    #[Computed]
    public function isApplicant(): bool
    {
        return $this->eoi->user_id === auth()->id();
    }

    /** Can work on this round: permission + EOI submitted / in review + round not decided yet */
    #[Computed]
    public function canEdit(): bool
    {
        return ! $this->isApplicant
            && auth()->user()->can('gate1.decide')
            && in_array($this->eoi->status, [ExpressionOfInterest::STATUS_SUBMITTED, ExpressionOfInterest::STATUS_IN_REVIEW], true)
            && ! ($this->screening?->isDecided());
    }

    /* =====================================================================
     | Load the round to show / work on
     ===================================================================== */

    protected function loadScreening(): void
    {
        $eoi    = $this->eoi;
        $latest = $eoi->eligibilityScreenings()->orderByDesc('round')->first();

        // Returned (incomplete or clarification) and since resubmitted → a new round
        $resubmitted = $latest?->isReturned()
            && in_array($eoi->status, [ExpressionOfInterest::STATUS_SUBMITTED, ExpressionOfInterest::STATUS_IN_REVIEW], true);

        if ($latest && ! $resubmitted) {
            // Current round (draft or decided)
            $this->screeningId = $latest->id;
            $this->round       = $latest->round;
            $this->fillFrom($latest->answers ?? [], $latest->decision, $latest->comments, $latest->screened_on?->toDateString());
            $this->fillCompleteness($latest->completeness ?? [], useAuto: ! $latest->isDecided());
        } else {
            // New round — carry criteria answers over, re-run completeness, decision starts empty
            $this->screeningId = null;
            $this->round       = $latest ? $latest->round + 1 : 1;
            $this->fillFrom($latest?->answers ?? [], null, '', null);
            $this->fillCompleteness([], useAuto: true);
        }
    }

    protected function fillFrom(array $answers, ?string $decision, ?string $comments, ?string $date): void
    {
        $this->answers = collect(EligibilityScreening::CRITERIA)
            ->mapWithKeys(fn ($c, $k) => [$k => [
                'answer' => $answers[$k]['answer'] ?? null,
                'note'   => $answers[$k]['note'] ?? '',
            ]])->all();

        // The completeness return is stored as a decision, but isn't one of the Gate 1 options
        $this->decision   = $decision === EligibilityScreening::DECISION_INCOMPLETE ? null : $decision;
        $this->comments   = (string) $comments;
        $this->screenedOn = $date ?: now()->toDateString();
    }

    /** Saved completeness answers; gaps are pre-filled from the automatic check while the round is open */
    protected function fillCompleteness(array $saved, bool $useAuto): void
    {
        $auto = $useAuto ? $this->auto : [];

        $this->completeness = collect(EligibilityScreening::COMPLETENESS)
            ->mapWithKeys(fn ($c, $k) => [$k => [
                'answer' => $saved[$k]['answer'] ?? (isset($auto[$k]) ? ($auto[$k]['met'] ? 'yes' : 'no') : null),
                'note'   => $saved[$k]['note'] ?? (isset($auto[$k]) && ! $auto[$k]['met'] ? implode('; ', $auto[$k]['missing']) : ''),
            ]])->all();
    }

    /* =====================================================================
     | Stage 1 · §3.2.1 Completeness review
     ===================================================================== */

    /** Reset the completeness answers to what the platform finds now (e.g. after the applicant updated their profile) */
    public function rerunAutoCheck(): void
    {
        $this->authorizeStage(EligibilityScreening::STAGE_COMPLETENESS);

        unset($this->profile, $this->auto, $this->evidence);
        $this->fillCompleteness([], useAuto: true);

        $this->resetErrorBag();
        $this->dispatch('notify', type: 'info', message: 'Automatic completeness check re-run.');
    }

    public function confirmComplete(): void
    {
        $this->authorizeStage(EligibilityScreening::STAGE_COMPLETENESS);
        $this->validateCompleteness();

        $auto = $this->auto;
        foreach ($this->completeness as $k => $c) {
            if ($c['answer'] !== 'yes') {
                $this->addError("completeness.$k.answer", 'Every check must be met before screening can start — or return the application for correction.');
            } elseif (! ($auto[$k]['met'] ?? true) && blank($c['note'])) {
                $this->addError("completeness.$k.note", 'The platform flagged missing items — explain why this check is accepted.');
            }
        }
        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        $this->persist()->update([
            'completeness_confirmed_at' => now(),
            'completeness_confirmed_by' => auth()->id(),
        ]);

        unset($this->screening, $this->stage);
        $this->dispatch('notify', type: 'success', message: 'Application confirmed complete — eligibility screening can start.');
    }

    public function returnIncomplete(): void
    {
        $this->authorizeStage(EligibilityScreening::STAGE_COMPLETENESS);
        $this->validateCompleteness();

        $outstanding = 0;
        foreach ($this->completeness as $k => $c) {
            if ($c['answer'] === 'no') {
                $outstanding++;
                if (blank($c['note'])) {
                    $this->addError("completeness.$k.note", 'Tell the applicant what needs attention.');
                }
            }
        }
        if ($outstanding === 0) {
            $this->addError('completeness', 'Mark at least one check as "Not met" to return the application.');
        }
        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        DB::transaction(function () {
            $this->persist(updateEoi: false)->update([
                'decision'    => EligibilityScreening::DECISION_INCOMPLETE,
                'decided_at'  => now(),
                'screened_by' => auth()->id(),
            ]);
            $this->eoi->update(['status' => ExpressionOfInterest::STATUS_RETURNED]);
        });

        // E-mail the applicant the outstanding items (after the return is saved)
        $sentTo = $this->emailApplicant();

        $this->afterDecision(
            EligibilityScreening::DECISION_INCOMPLETE,
            'Application returned to the applicant for correction.'.$this->emailNote($sentTo)
        );
    }

    /**
     * E-mail the applicant the outcome of this round (sent after the decision is saved):
     *   incomplete → EoiReturnedForCorrection · pass / clarification / not eligible → EoiGate1Decision
     * Returns the address it was sent to, or null if it couldn't be sent (the decision itself stands).
     */
    protected function emailApplicant(): ?string
    {
        $eoi       = ExpressionOfInterest::with('user')->find($this->eoiId);
        $screening = EligibilityScreening::find($this->screeningId);
        $applicant = $eoi?->user;

        if (! $eoi || ! $screening || ! $screening->decision || ! $applicant || blank($applicant->email)) {
            return null;
        }

        try {
            $applicant->notify($screening->decision === EligibilityScreening::DECISION_INCOMPLETE
                ? new EoiReturnedForCorrection($eoi, $screening)
                : new EoiGate1Decision($eoi, $screening));

            return $applicant->email;
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    protected function emailNote(?string $sentTo): string
    {
        return $sentTo
            ? ' An e-mail was sent to '.$sentTo.'.'
            : ' The e-mail to the applicant could not be sent — please let them know.';
    }

    /* =====================================================================
     | Stage 2 + 3 · Eligibility screening & Gate 1 decision
     ===================================================================== */

    /** Re-open the completeness review (before a decision is recorded) */
    public function backToCompleteness(): void
    {
        $this->authorizeStage(EligibilityScreening::STAGE_SCREENING);

        $this->decision = null;
        $this->persist()->update(['decision' => null, 'completeness_confirmed_at' => null, 'completeness_confirmed_by' => null]);

        unset($this->screening, $this->stage);
        $this->dispatch('notify', type: 'info', message: 'Completeness review re-opened.');
    }

    public function saveDraft(): void
    {
        $this->authorizeEdit();

        $this->validate([
            'completeness.*.answer' => ['nullable', Rule::in(['yes', 'no'])],
            'completeness.*.note'   => ['nullable', 'string', 'max:1000'],
            'answers.*.answer'      => ['nullable', Rule::in(['yes', 'no', 'clarify'])],
            'answers.*.note'        => ['nullable', 'string', 'max:500'],
            'decision'              => ['nullable', Rule::in(array_keys(EligibilityScreening::DECISIONS))],
            'comments'              => ['nullable', 'string', 'max:3000'],
            'screenedOn'            => ['nullable', 'date', 'before_or_equal:today'],
        ]);

        $this->persist();
        $this->dispatch('notify', type: 'success', message: 'Draft saved.');
    }

    public function recordDecision(): void
    {
        $this->authorizeStage(EligibilityScreening::STAGE_SCREENING);

        $this->validate([
            'answers.*.answer' => ['required', Rule::in(['yes', 'no', 'clarify'])],
            'answers.*.note'   => ['nullable', 'string', 'max:500'],
            'decision'         => ['required', Rule::in(array_keys(EligibilityScreening::DECISIONS))],
            'comments'         => [$this->decision === EligibilityScreening::DECISION_ELIGIBLE ? 'nullable' : 'required', 'string', 'max:3000'],
            'screenedOn'       => ['required', 'date', 'before_or_equal:today'],
        ], [
            'answers.*.answer.required' => 'Answer every criterion.',
            'decision.required'         => 'Choose a Gate 1 decision.',
            'comments.required'         => $this->decision === EligibilityScreening::DECISION_NOT_ELIGIBLE
                ? 'State the basis for the decision — the applicant is notified of it.'
                : 'Explain what the applicant needs to provide.',
        ]);

        // Evidence-based: a "No" or "Clarification" needs a note
        foreach ($this->answers as $key => $a) {
            if (in_array($a['answer'], ['no', 'clarify'], true) && blank($a['note'])) {
                $this->addError("answers.$key.note", $a['answer'] === 'no' ? 'Record why this criterion is not met.' : 'Specify the information required.');
            }
        }

        // The decision must match the answers
        $tally = EligibilityScreening::tally($this->answers);
        if ($this->decision === EligibilityScreening::DECISION_ELIGIBLE && ($tally['no'] + $tally['clarify']) > 0) {
            $this->addError('decision', 'All criteria must be "Yes" for the application to pass.');
        }
        if ($this->decision === EligibilityScreening::DECISION_CLARIFICATION && $tally['clarify'] === 0) {
            $this->addError('decision', 'Mark at least one criterion as "Clarification" — those become the information requirements sent to the applicant.');
        }
        if ($this->decision === EligibilityScreening::DECISION_NOT_ELIGIBLE && $tally['no'] === 0) {
            $this->addError('decision', 'Mark at least one criterion as "No" — this is the basis for the decision.');
        }

        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        $decision = $this->decision;

        DB::transaction(function () use ($decision) {
            $this->persist(updateEoi: false)->update([
                'decided_at'  => now(),
                'screened_by' => auth()->id(),
            ]);

            $this->eoi->update(['status' => EligibilityScreening::DECISION_TO_EOI_STATUS[$decision]]);
        });

        // E-mail the applicant the outcome (pass / clarification required / not eligible)
        $sentTo = $this->emailApplicant();

        $this->afterDecision(
            $decision,
            'Gate 1 decision recorded: '.EligibilityScreening::DECISIONS[$decision][0].'.'.$this->emailNote($sentTo)
        );
    }

    /* =====================================================================
     | Internals
     ===================================================================== */

    protected function validateCompleteness(): void
    {
        $this->validate([
            'completeness.*.answer' => ['required', Rule::in(['yes', 'no'])],
            'completeness.*.note'   => ['nullable', 'string', 'max:1000'],
            'comments'              => ['nullable', 'string', 'max:3000'],
        ], [
            'completeness.*.answer.required' => 'Confirm this check.',
        ]);
    }

    protected function persist(bool $updateEoi = true): EligibilityScreening
    {
        $data = [
            'completeness' => $this->completeness,
            'answers'      => $this->answers,
            'decision'     => $this->stage === EligibilityScreening::STAGE_SCREENING ? $this->decision : null,
            'comments'     => trim($this->comments) ?: null,
            'screened_on'  => $this->screenedOn ?: null,
        ];

        if ($this->screeningId) {
            $screening = EligibilityScreening::findOrFail($this->screeningId);
            $screening->update($data);
        } else {
            $screening = EligibilityScreening::create($data + [
                'expression_of_interest_id' => $this->eoiId,
                'round'                     => $this->round,
                'screened_by'               => auth()->id(),
            ]);
            $this->screeningId = $screening->id;
        }

        // Review has started → EOI is in review
        if ($updateEoi && $this->eoi->status === ExpressionOfInterest::STATUS_SUBMITTED) {
            $this->eoi->update(['status' => ExpressionOfInterest::STATUS_IN_REVIEW]);
            $this->dispatch('gate-decided', gate: 'g1', decision: null);   // refresh the page header
        }

        unset($this->screening, $this->stage, $this->eoi);

        return $screening;
    }

    protected function afterDecision(string $decision, string $message): void
    {
        unset($this->eoi, $this->screening, $this->stage, $this->history, $this->canEdit, $this->evidence);

        $this->dispatch('gate-decided', gate: 'g1', decision: $decision);
        $this->dispatch('notify', type: 'success', message: $message);
    }

    protected function authorizeEdit(): void
    {
        abort_unless($this->canEdit, 403, 'This screening can no longer be changed.');
    }

    protected function authorizeStage(string $stage): void
    {
        $this->authorizeEdit();
        abort_unless($this->stage === $stage, 403, 'This action is not available at the current stage.');
    }
};
?>

<div>
@php
    $G1         = \App\Models\EligibilityScreening::class;
    $eoi        = $this->eoi;
    $screening  = $this->screening;
    $stage      = $this->stage;
    $canEdit    = $this->canEdit;
    $checks     = $G1::COMPLETENESS;
    $areas      = $G1::AREAS;
    $criteria   = $G1::CRITERIA;
    $decisions  = $G1::DECISIONS;
    $outcomes   = $G1::OUTCOMES;
    $tally      = $G1::tally($answers);
    $cTally     = $G1::tally($completeness, array_keys($checks));
    $recommend  = $G1::recommend($answers);
    $decided    = (bool) $screening?->isDecided();
    $outcome    = $decided ? $screening->decision : null;
    $returnedIncomplete = $outcome === $G1::DECISION_INCOMPLETE;
    $reachedScreening   = $stage === $G1::STAGE_SCREENING || ($decided && ! $returnedIncomplete);
    $editStage1 = $canEdit && $stage === $G1::STAGE_COMPLETENESS;
    $editStage2 = $canEdit && $stage === $G1::STAGE_SCREENING;
    $submitted  = in_array($eoi->status, ['submitted', 'in_review'], true);
    $evidence   = $this->isApplicant || ! $reachedScreening ? [] : $this->evidence;
    $profile    = $this->isApplicant ? null : $this->profile;

    // Flow tracker: [label, section, state: done | current | returned | failed | todo]
    $flow = [
        ['Completeness review', '§3.2.1', match (true) {
            $returnedIncomplete      => 'returned',
            $reachedScreening        => 'done',
            $screening || $submitted => 'current',
            default                  => 'todo',
        }],
        ['Eligibility screening', '§3.2.2', match (true) {
            $decided && ! $returnedIncomplete => 'done',
            $stage === $G1::STAGE_SCREENING   => 'current',
            default                           => 'todo',
        }],
        ['Gate 1 decision', '§3.2.3', match ($outcome) {
            $G1::DECISION_ELIGIBLE      => 'done',
            $G1::DECISION_CLARIFICATION => 'returned',
            $G1::DECISION_NOT_ELIGIBLE  => 'failed',
            default                     => 'todo',
        }],
    ];
    $flowIcon = ['done' => 'bi-check-lg', 'current' => 'bi-hourglass-split', 'returned' => 'bi-arrow-return-left', 'failed' => 'bi-x-lg'];
@endphp

{{-- ============================ FLOW TRACKER ============================ --}}
<div class="g1-flow mb-3">
    @foreach ($flow as $i => [$fLabel, $fSec, $fState])
        <div class="g1-flow-step is-{{ $fState }}">
            <span class="g1-flow-dot">@if ($fState === 'todo') {{ $i + 1 }} @else <i class="bi {{ $flowIcon[$fState] }}"></i> @endif</span>
            <div>
                <div class="g1-flow-label">{{ $fLabel }}</div>
                <div class="g1-flow-sub">{{ $fSec }}</div>
            </div>
        </div>
    @endforeach
</div>

{{-- ======================================================================
     APPLICANT VIEW — progress, outstanding items, outcome
     ====================================================================== --}}
@if ($this->isApplicant)
    <section class="section-card">
        <div class="section-card-head">
            <span class="section-no">G1</span>
            <h5 class="me-auto">Eligibility screening</h5>
            @if ($decided)
                <span class="pill {{ $outcomes[$outcome][3] }}"><i class="bi {{ $outcomes[$outcome][2] }}"></i> {{ $outcomes[$outcome][0] }}</span>
            @else
                <span class="pill pill-warn"><i class="bi bi-hourglass-split"></i> In progress</span>
            @endif
        </div>

        @if (! $decided)
            <p class="small text-muted mb-0">
                @if ($stage === $G1::STAGE_SCREENING)
                    Your application is complete and is being checked against the Facility's eligibility criteria. You'll be notified of the outcome.
                @else
                    The LEHSFF team is checking that your application, declarations and supporting documents are complete.
                @endif
            </p>

        @elseif ($screening->isReturned())
            @php $items = $screening->outstandingItems(); @endphp
            <div class="callout callout-warning small mb-3">
                <i class="bi bi-arrow-return-left me-1"></i>
                @if ($returnedIncomplete)
                    Your application is missing some information. Please complete the items below and resubmit.
                @else
                    The team needs more information before an eligibility determination can be made. Please respond to the items below and resubmit.
                @endif
            </div>
            @if ($items)
                <dt class="detail-label mb-2">Requiring your attention</dt>
                <ul class="checklist mb-3">
                    @foreach ($items as $item)
                        <li class="is-pending"><i class="bi bi-question-circle"></i><div><div class="fw-semibold text-navy">{{ $item['title'] }}</div><div class="small text-muted">{{ $item['note'] }}</div></div></li>
                    @endforeach
                </ul>
            @endif
            @if ($screening->comments)
                <div class="quote mb-3">{{ $screening->comments }}<div class="quote-meta">Comments from the LEHSFF team · {{ $screening->decided_at->format('d M Y') }}</div></div>
            @endif
            @if ($eoi->status === 'returned')
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-brand btn-sm" wire:click="$dispatch('edit-application', { id: {{ $eoi->id }} })">
                        <i class="bi bi-pencil-square me-1"></i> Update &amp; resubmit EOI
                    </button>
                    <a href="{{ route('profile.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-building me-1"></i> Update enterprise profile</a>
                </div>
            @endif

        @elseif ($outcome === $G1::DECISION_ELIGIBLE)
            <div class="callout callout-success small mb-2">
                <i class="bi bi-check2-circle me-1"></i>
                Your application has passed eligibility screening and progresses to <strong>Due Diligence and Investment Appraisal</strong>.
            </div>
            <p class="small text-muted mb-0">Passing Gate 1 means the transaction meets the Facility's fundamental eligibility requirements — it is not approval of the CI grant.</p>
            @if ($screening->comments)
                <div class="quote mt-3">{{ $screening->comments }}<div class="quote-meta">Comments from the LEHSFF team</div></div>
            @endif

        @else
            <p class="small text-muted">Decision recorded on {{ $screening->decided_at->format('d M Y') }}. Your application will not progress further.</p>
            @php $failed = $screening->failedItems(); @endphp
            @if ($failed)
                <dt class="detail-label mb-2">Basis for the decision</dt>
                <ul class="checklist mb-3">
                    @foreach ($failed as $item)
                        <li class="is-fail"><i class="bi bi-x-circle"></i><div><div class="fw-semibold text-navy">{{ $item['title'] }}</div><div class="small text-muted">{{ $item['note'] }}</div></div></li>
                    @endforeach
                </ul>
            @endif
            @if ($screening->comments)
                <div class="quote">{{ $screening->comments }}<div class="quote-meta">Comments from the LEHSFF team</div></div>
            @endif
        @endif
    </section>

{{-- ======================================================================
     STAFF VIEW — completeness · criteria · decision
     ====================================================================== --}}
@else
    {{-- Not screenable yet --}}
    @if (! $screening && ! $submitted)
        <div class="callout small mb-3">
            <i class="bi bi-info-circle me-1"></i>
            @if ($eoi->status === 'draft') This EOI hasn't been submitted yet, so it can't be screened.
            @elseif ($eoi->status === 'withdrawn') This EOI was withdrawn by the applicant.
            @elseif ($eoi->status === 'returned') Returned to the applicant — waiting for them to resubmit.
            @else No screening is open for this EOI.
            @endif
        </div>
    @endif

    {{-- ============================ SCREENING DETAILS ============================ --}}
    <section class="section-card">
        <div class="section-card-head">
            <span class="section-no">G1</span>
            <div class="me-auto">
                <h5>Administrative review &amp; eligibility screening @if ($round > 1) <span class="pill pill-muted ms-1">Round {{ $round }}</span> @endif</h5>
                <div class="small text-muted">Is there a sufficient basis for the transaction to proceed to detailed assessment?</div>
            </div>
            @if ($decided)
                <span class="pill {{ $outcomes[$outcome][3] }}"><i class="bi {{ $outcomes[$outcome][2] }}"></i> {{ $outcomes[$outcome][0] }}</span>
            @elseif ($screening)
                <span class="pill pill-warn"><i class="bi bi-hourglass-split"></i> {{ $stage === $G1::STAGE_SCREENING ? 'Screening' : 'Completeness review' }}</span>
            @else
                <span class="pill pill-muted"><i class="bi bi-circle"></i> Not started</span>
            @endif
        </div>
        <div class="detail-grid">
            <div><dt>Enterprise</dt><dd>{{ $eoi->business_name ?: '—' }}</dd></div>
            <div><dt>Transaction ID</dt><dd><span class="chip">{{ $eoi->reference }}</span></dd></div>
            <div><dt>Screened by</dt><dd>{{ $screening?->screener?->name ?? ($canEdit ? auth()->user()->name : '—') }}</dd></div>
            <div><dt>Date</dt><dd>{{ $screening?->screened_on?->format('d M Y') ?? '—' }}</dd></div>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2 small mt-3">
            @if ($profile)
                <span class="text-muted"><i class="bi bi-building me-1"></i> Enterprise profile {{ $profile->completeness() }}% complete ({{ $profile->completedCount() }}/{{ count(\App\Models\EnterpriseProfile::SECTIONS) }} sections).</span>
            @else
                <span class="text-warning"><i class="bi bi-exclamation-triangle me-1"></i> The applicant hasn't started an enterprise profile — only EOI answers are available.</span>
            @endif
            @canany(['eoi.view-all', 'users.manage'])
                <a href="{{ route('profile.index', $eoi->user_id) }}" target="_blank" class="ms-auto text-decoration-none">
                    <i class="bi bi-box-arrow-up-right me-1"></i> Open full enterprise profile
                </a>
            @endcanany
        </div>
        @if (! $canEdit && ! $decided && $submitted && ! auth()->user()->can('gate1.decide'))
            <div class="small text-muted mt-2"><i class="bi bi-eye me-1"></i> Read-only — screening is done by the PMU (permission <code>gate1.decide</code>).</div>
        @endif
    </section>

    {{-- ============================ 1 · COMPLETENESS REVIEW ============================ --}}
    @php
        $auto      = $decided ? [] : $this->auto;
        $hasRecord = collect($completeness)->contains(fn ($c) => $c['answer'] !== null);
    @endphp
    <section class="section-card">
        <div class="section-card-head">
            <span class="section-no">1</span>
            <div class="me-auto">
                <h5>Administrative completeness review</h5>
                <div class="small text-muted">Required fields, declarations and supporting documents provided.</div>
            </div>
            @if ($returnedIncomplete)
                <span class="pill pill-warn"><i class="bi bi-arrow-return-left"></i> Returned</span>
            @elseif ($reachedScreening)
                <span class="pill pill-ok"><i class="bi bi-check-lg"></i> Complete</span>
            @else
                <span class="pill pill-ok"><i class="bi bi-check-lg"></i> {{ $cTally['yes'] }} Met</span>
                <span class="pill pill-danger"><i class="bi bi-x-lg"></i> {{ $cTally['no'] }} Not met</span>
            @endif
        </div>

        @if ($screening?->completeness_confirmed_at)
            <div class="small text-muted mb-3">
                <i class="bi bi-person-check me-1"></i> Confirmed complete by {{ $screening->completenessConfirmer?->name ?? '—' }} on {{ $screening->completeness_confirmed_at->format('d M Y H:i') }}.
            </div>
        @elseif ($decided && ! $hasRecord)
            <div class="small text-muted mb-0"><i class="bi bi-info-circle me-1"></i> No completeness record for this round (screened before the completeness review was introduced).</div>
        @endif

        @if (! $decided || $hasRecord)
            <details class="g1-stage" @if (! $reachedScreening) open @endif>
                <summary class="small fw-semibold text-brand">Completeness checks ({{ count($checks) }})</summary>

                <div class="table-responsive mt-2">
                    <table class="table align-middle g1-check-table mb-0">
                        <thead>
                            <tr>
                                <th style="width: 32%">Completeness check</th>
                                <th>Platform check</th>
                                <th style="width: 34%">Officer confirmation</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($checks as $key => [$cTitle, $cReq])
                                @php
                                    $a = $auto[$key] ?? null;
                                    $c = $completeness[$key] ?? ['answer' => null, 'note' => ''];
                                @endphp
                                <tr wire:key="chk-{{ $key }}" class="{{ $c['answer'] === 'no' ? 'is-flagged' : '' }}">
                                    <td>
                                        <div class="fw-semibold text-navy small">{{ $cTitle }}</div>
                                        <div class="small text-muted lh-sm">{{ $cReq }}</div>
                                    </td>
                                    <td class="small">
                                        @if (! $a)
                                            <span class="text-muted">—</span>
                                        @elseif ($a['met'])
                                            <span class="pill pill-ok"><i class="bi bi-check-circle"></i> Complete</span>
                                        @else
                                            <span class="pill pill-warn"><i class="bi bi-exclamation-triangle"></i> {{ count($a['missing']) }} missing</span>
                                            <ul class="evidence-list mt-1 text-muted">
                                                @foreach ($a['missing'] as $m)
                                                    <li>{{ $m }}</li>
                                                @endforeach
                                            </ul>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm mb-1" role="group" aria-label="{{ $cTitle }}">
                                            @foreach ([['yes', 'Met', 'btn-outline-success', 'bi-check-lg'], ['no', 'Not met', 'btn-outline-danger', 'bi-x-lg']] as [$v, $lbl, $cls, $ic])
                                                <input type="radio" class="btn-check" name="chk-{{ $key }}" id="chk-{{ $key }}-{{ $v }}" value="{{ $v }}"
                                                       wire:model.live="completeness.{{ $key }}.answer" @disabled(! $editStage1)>
                                                <label class="btn {{ $cls }}" for="chk-{{ $key }}-{{ $v }}"><i class="bi {{ $ic }} me-1"></i>{{ $lbl }}</label>
                                            @endforeach
                                        </div>
                                        @if ($editStage1)
                                            <input type="text" wire:model.blur="completeness.{{ $key }}.note"
                                                   placeholder="{{ $c['answer'] === 'no' ? 'What the applicant must provide' : 'Note (optional)' }}"
                                                   class="form-control form-control-sm @error("completeness.$key.note") is-invalid @enderror">
                                        @elseif ($c['note'])
                                            <div class="small text-muted">{{ $c['note'] }}</div>
                                        @endif
                                        @error("completeness.$key.answer") <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                                        @error("completeness.$key.note") <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @error('completeness') <div class="text-danger small mt-2">{{ $message }}</div> @enderror

                @if ($editStage1)
                    <div class="mt-3">
                        <label class="form-label" for="g1_return_msg">Message to the applicant <span class="text-muted small">(e-mailed to the applicant with the outstanding items if returned)</span></label>
                        <textarea id="g1_return_msg" rows="2" wire:model.blur="comments" class="form-control @error('comments') is-invalid @enderror"
                                  placeholder="Optional"></textarea>
                        @error('comments') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="d-flex flex-wrap align-items-center gap-2 mt-3 pt-3 border-top">
                        <button type="button" class="btn btn-link text-decoration-none text-secondary px-0 me-2" wire:click="rerunAutoCheck" wire:loading.attr="disabled">
                            <i class="bi bi-arrow-repeat me-1"></i> Re-run automatic check
                        </button>
                        <button type="button" class="btn btn-link text-decoration-none text-secondary px-0" wire:click="saveDraft" wire:loading.attr="disabled">
                            <i class="bi bi-save me-1"></i> Save draft
                        </button>
                        <button type="button" class="btn btn-outline-warning ms-auto" wire:click="returnIncomplete" wire:loading.attr="disabled" wire:target="returnIncomplete"
                                wire:confirm="Return the application to the applicant with the items marked 'Not met'? They will be e-mailed the list and your message.">
                            <span wire:loading.remove wire:target="returnIncomplete"><i class="bi bi-arrow-return-left me-1"></i> Return for correction</span>
                            <span wire:loading wire:target="returnIncomplete"><span class="spinner-border spinner-border-sm me-1"></span> Returning…</span>
                        </button>
                        <button type="button" class="btn btn-brand" wire:click="confirmComplete" wire:loading.attr="disabled" wire:target="confirmComplete">
                            <span wire:loading.remove wire:target="confirmComplete"><i class="bi bi-check2-all me-1"></i> Confirm complete &amp; start screening</span>
                            <span wire:loading wire:target="confirmComplete"><span class="spinner-border spinner-border-sm me-1"></span> Confirming…</span>
                        </button>
                    </div>
                @endif
            </details>
        @endif
    </section>

    {{-- ============================ 2 · ELIGIBILITY CRITERIA ============================ --}}
    <section class="section-card">
        <div class="section-card-head">
            <span class="section-no">2</span>
            <div class="me-auto">
                <h5>Eligibility criteria</h5>
                <div class="small text-muted">Confirm each criterion against the EOI, enterprise profile and documents.</div>
            </div>
            @if ($reachedScreening)
                <span class="pill pill-ok"><i class="bi bi-check-lg"></i> {{ $tally['yes'] }} Yes</span>
                <span class="pill pill-danger"><i class="bi bi-x-lg"></i> {{ $tally['no'] }} No</span>
                <span class="pill pill-warn"><i class="bi bi-question-lg"></i> {{ $tally['clarify'] }} Clarification</span>
                @if ($tally['unanswered']) <span class="pill pill-muted">{{ $tally['unanswered'] }} open</span> @endif
            @endif
        </div>

        @if (! $reachedScreening)
            <div class="g1-locked">
                <i class="bi bi-lock"></i>
                <div>
                    @if ($returnedIncomplete)
                        Not reached — the application was returned at the completeness review.
                    @else
                        Screening opens once the application is confirmed complete.
                    @endif
                </div>
            </div>
        @else
            @php $n = 0; @endphp
            @foreach ($areas as $areaKey => [$areaTitle, $areaQuestion, $areaIcon])
                <div class="g1-area" wire:key="area-{{ $areaKey }}">
                    <div class="g1-area-head">
                        <i class="bi {{ $areaIcon }}"></i>
                        <div>
                            <div class="g1-area-title">{{ $areaTitle }}</div>
                            <div class="small text-muted">{{ $areaQuestion }}</div>
                        </div>
                    </div>

                    @foreach ($G1::criteriaFor($areaKey) as $key => [$title, $desc])
                        @php
                            $n++;
                            $ans    = $answers[$key]['answer'] ?? null;
                            $border = ['no' => ' border-danger', 'clarify' => ' border-warning'][$ans] ?? '';
                            $ph     = ['no' => 'Reason it is not met', 'clarify' => 'What needs to be clarified?'][$ans] ?? 'Evidence / note (optional)';
                        @endphp
                        <div class="border rounded-3 p-3 mb-2{{ $border }}" wire:key="crit-{{ $key }}">
                            <div class="d-flex gap-3">
                                <span class="section-no flex-shrink-0">{{ $n }}</span>
                                <div class="flex-grow-1 min-w-0">
                                    <div class="fw-semibold text-navy">{{ $title }}</div>
                                    <div class="small text-muted mb-2">{{ $desc }}</div>
                                    <div class="d-flex flex-wrap align-items-center gap-2">
                                        <div class="btn-group btn-group-sm flex-shrink-0" role="group" aria-label="{{ $title }}">
                                            @foreach ([['yes', 'Yes', 'btn-outline-success', 'bi-check-lg'], ['no', 'No', 'btn-outline-danger', 'bi-x-lg'], ['clarify', 'Clarification', 'btn-outline-warning', 'bi-question-lg']] as [$v, $lbl, $cls, $ic])
                                                <input type="radio" class="btn-check" name="crit-{{ $key }}" id="c-{{ $key }}-{{ $v }}" value="{{ $v }}"
                                                       wire:model.live="answers.{{ $key }}.answer" @disabled(! $editStage2)>
                                                <label class="btn {{ $cls }}" for="c-{{ $key }}-{{ $v }}"><i class="bi {{ $ic }} me-1"></i>{{ $lbl }}</label>
                                            @endforeach
                                        </div>
                                        @if ($editStage2)
                                            <input type="text" wire:model.blur="answers.{{ $key }}.note" placeholder="{{ $ph }}"
                                                   class="form-control form-control-sm flex-grow-1 @error("answers.$key.note") is-invalid @enderror" style="min-width: 220px; width: auto;">
                                        @elseif ($answers[$key]['note'] ?? '')
                                            <span class="small text-muted"><i class="bi bi-chat-left-text me-1"></i>{{ $answers[$key]['note'] }}</span>
                                        @endif
                                    </div>
                                    @error("answers.$key.answer") <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                                    @error("answers.$key.note") <div class="text-danger small mt-1">{{ $message }}</div> @enderror

                                    {{-- Submitted information --}}
                                    @php
                                        $ev      = $evidence[$key] ?? ['rows' => [], 'docs' => []];
                                        $missing = collect($ev['rows'])->filter(fn ($r) => blank($r[1]))->count()
                                                 + collect($ev['docs'])->filter(fn ($d) => ! $d[1] && $d[2])->count();   // optional documents don't count
                                    @endphp
                                    <details class="evidence mt-3" @if ($editStage2) open @endif>
                                        <summary>
                                            <i class="bi bi-file-earmark-text me-1"></i> Submitted information
                                            @if ($missing) <span class="pill pill-warn ms-2">{{ $missing }} not provided</span> @endif
                                        </summary>
                                        <dl class="evidence-grid">
                                            @foreach ($ev['rows'] as [$rLabel, $rValue, $rSource, $rSub, $rFlag])
                                                <dt><span class="src-tag {{ $rSource === 'EOI' ? 'src-eoi' : 'src-profile' }}">{{ $rSource }}</span> {{ $rLabel }}</dt>
                                                <dd>
                                                    @if (blank($rValue))
                                                        <span class="text-muted fst-italic">Not provided</span>
                                                    @elseif (is_array($rValue))
                                                        <ul class="evidence-list">
                                                            @foreach ($rValue as $item)
                                                                @if (is_array($item))
                                                                    <li class="{{ $item[1] ? 'is-yes' : 'is-no' }}"><i class="bi {{ $item[1] ? 'bi-check-circle-fill' : 'bi-x-circle' }}"></i> {{ $item[0] }}</li>
                                                                @else
                                                                    <li>{{ $item }}</li>
                                                                @endif
                                                            @endforeach
                                                        </ul>
                                                    @else
                                                        <span class="evidence-value">{{ $rValue }}</span>
                                                    @endif
                                                    @if ($rSub)
                                                        <div class="small {{ ['ok' => 'text-success', 'bad' => 'text-danger'][$rFlag] ?? 'text-muted' }}">{{ $rSub }}</div>
                                                    @endif
                                                </dd>
                                            @endforeach

                                            @if ($ev['docs'])
                                                <dt><span class="src-tag src-profile">Docs</span> Documents</dt>
                                                <dd class="d-flex flex-wrap gap-2">
                                                    @foreach ($ev['docs'] as [$dLabel, $doc, $dReq])
                                                        @if ($doc)
                                                            <a href="{{ route('enterprise-documents.download', $doc) }}" class="pill pill-ok text-decoration-none" title="{{ $doc->original_name }} · {{ $doc->size_label }}">
                                                                <i class="bi bi-download"></i> {{ $dLabel }}
                                                            </a>
                                                        @elseif ($dReq)
                                                            <span class="pill pill-warn" title="Required — not uploaded"><i class="bi bi-exclamation-circle"></i> {{ $dLabel }}</span>
                                                        @else
                                                            <span class="pill pill-muted" title="Optional — not uploaded"><i class="bi bi-dash-circle"></i> {{ $dLabel }} (optional)</span>
                                                        @endif
                                                    @endforeach
                                                </dd>
                                            @endif
                                        </dl>
                                    </details>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endforeach
        @endif
    </section>

    {{-- ============================ 3 · GATE 1 DECISION ============================ --}}
    <section class="section-card">
        <div class="section-card-head">
            <span class="section-no">3</span>
            <div class="me-auto">
                <h5>Gate 1 decision</h5>
                <div class="small text-muted">
                    @if ($decided && ! $returnedIncomplete)
                        Recorded by {{ $screening->screener?->name ?? '—' }} on {{ $screening->decided_at->format('d M Y H:i') }}.
                    @elseif (! $reachedScreening)
                        Recorded after eligibility screening.
                    @elseif ($recommend)
                        Based on the answers, the recommended outcome is <strong>{{ $decisions[$recommend][0] }}</strong>.
                    @else
                        Answer all {{ count($criteria) }} criteria to see the recommended outcome.
                    @endif
                </div>
            </div>
        </div>

        @if ($returnedIncomplete)
            <div class="g1-locked">
                <i class="bi bi-arrow-return-left"></i>
                <div>
                    Returned for correction on {{ $screening->decided_at->format('d M Y') }} by {{ $screening->screener?->name ?? '—' }}.
                    @if ($screening->comments) <div class="mt-1">“{{ $screening->comments }}”</div> @endif
                </div>
            </div>
        @elseif (! $reachedScreening)
            <div class="g1-locked"><i class="bi bi-lock"></i><div>The decision opens once eligibility screening has started.</div></div>
        @else
            <div class="choice-group cols-3 @error('decision') is-invalid @enderror">
                @foreach ($decisions as $value => [$dLabel, $dDesc, $dIcon])
                    <label class="choice choice--card">
                        <input type="radio" name="g1-decision" wire:model.live="decision" value="{{ $value }}" @disabled(! $editStage2)>
                        <span class="choice-box">
                            <span class="choice-icon"><i class="bi {{ $dIcon }}"></i></span>
                            <span>
                                <span class="d-block fw-semibold">{{ $dLabel }} @if ($recommend === $value && ! $decided) <span class="pill pill-muted ms-1">Suggested</span> @endif</span>
                                <span class="d-block small text-muted lh-sm mt-1">{{ $dDesc }}</span>
                            </span>
                        </span>
                    </label>
                @endforeach
            </div>
            @error('decision') <div class="text-danger small mt-1">{{ $message }}</div> @enderror

            <div class="small text-muted mt-2">
                <i class="bi bi-info-circle me-1"></i> A Pass means the transaction has passed the fundamental eligibility filter — not that it has been approved for the CI grant.
            </div>

            @php
                $commentLabel = match ($decision) {
                    $G1::DECISION_NOT_ELIGIBLE  => 'Basis for the decision',
                    $G1::DECISION_CLARIFICATION => 'Information required from the applicant',
                    default                     => 'Key comments / actions',
                };
            @endphp
            <div class="mt-3">
                <label class="form-label" for="g1_comments">{{ $commentLabel }} @if ($decision && $decision !== 'eligible' && $editStage2) <span class="req">*</span> @endif</label>
                @if ($editStage2)
                    <textarea id="g1_comments" rows="3" wire:model.blur="comments" class="form-control @error('comments') is-invalid @enderror"
                              placeholder="{{ $decision === 'not_eligible' ? 'The applicant is notified of this.' : 'What the applicant needs to know or do next.' }}"></textarea>
                    @error('comments') <div class="invalid-feedback">{{ $message }}</div> @enderror
                @else
                    <div class="quote">{{ $comments ?: 'No comments.' }}</div>
                @endif
            </div>

            <div class="row g-3 mt-1">
                <div class="col-md-6">
                    <label class="form-label" for="g1_officer">Screening officer</label>
                    <input type="text" id="g1_officer" class="form-control bg-light" readonly
                           value="{{ $screening?->screener?->name ?? ($canEdit ? auth()->user()->name : '—') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="g1_date">Date</label>
                    <input type="date" id="g1_date" wire:model="screenedOn" max="{{ now()->toDateString() }}" @disabled(! $editStage2)
                           class="form-control @error('screenedOn') is-invalid @enderror">
                    @error('screenedOn') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>

            @if ($editStage2)
                <div class="d-flex flex-wrap align-items-center gap-2 mt-4 pt-3 border-top">
                    <button type="button" class="btn btn-link text-decoration-none text-secondary px-0 me-2" wire:click="backToCompleteness"
                            wire:confirm="Re-open the completeness review? Your criteria answers are kept.">
                        <i class="bi bi-arrow-left me-1"></i> Back to completeness
                    </button>
                    <button type="button" class="btn btn-link text-decoration-none text-secondary px-0" wire:click="saveDraft" wire:loading.attr="disabled" wire:target="saveDraft">
                        <span wire:loading.remove wire:target="saveDraft"><i class="bi bi-save me-1"></i> Save draft</span>
                        <span wire:loading wire:target="saveDraft"><span class="spinner-border spinner-border-sm me-1"></span> Saving…</span>
                    </button>
                    <button type="button" class="btn btn-brand ms-auto" wire:click="recordDecision" wire:loading.attr="disabled" wire:target="recordDecision"
                            wire:confirm="Record the Gate 1 decision? It can't be changed afterwards, and the applicant will be e-mailed the outcome.">
                        <span wire:loading.remove wire:target="recordDecision"><i class="bi bi-send me-1"></i> Record Gate 1 decision</span>
                        <span wire:loading wire:target="recordDecision"><span class="spinner-border spinner-border-sm me-1"></span> Recording…</span>
                    </button>
                </div>
            @endif
        @endif
    </section>

    {{-- ============================ EARLIER ROUNDS ============================ --}}
    @if ($this->history->isNotEmpty())
        <section class="section-card">
            <div class="section-card-head">
                <span class="section-no"><i class="bi bi-clock-history"></i></span>
                <h5>Earlier screening rounds</h5>
            </div>
            @foreach ($this->history as $h)
                @php
                    $ht    = $G1::tally($h->answers ?? []);
                    $items = $h->outstandingItems() ?: $h->failedItems();
                @endphp
                <div class="file-row align-items-start">
                    <span class="file-row-icon"><i class="bi {{ $outcomes[$h->decision][2] ?? 'bi-circle' }}"></i></span>
                    <div class="flex-grow-1 small">
                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            <strong class="text-navy">Round {{ $h->round }}</strong>
                            <span class="pill {{ $outcomes[$h->decision][3] ?? 'pill-muted' }}">{{ $outcomes[$h->decision][0] ?? $h->decision }}</span>
                            <span class="text-muted">{{ $h->decided_at->format('d M Y') }} · {{ $h->screener?->name }}</span>
                            @if ($h->decision !== $G1::DECISION_INCOMPLETE)
                                <span class="text-muted ms-auto">{{ $ht['yes'] }} yes · {{ $ht['no'] }} no · {{ $ht['clarify'] }} clarify</span>
                            @endif
                        </div>
                        @if ($items)
                            <ul class="evidence-list text-muted mt-1">
                                @foreach ($items as $it)
                                    <li><strong>{{ $it['title'] }}</strong>@if ($it['note']) — {{ $it['note'] }}@endif</li>
                                @endforeach
                            </ul>
                        @endif
                        @if ($h->comments) <div class="text-muted mt-1">{{ $h->comments }}</div> @endif
                    </div>
                </div>
            @endforeach
        </section>
    @endif
@endif
</div>