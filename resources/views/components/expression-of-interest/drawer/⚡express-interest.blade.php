<?php

use App\Models\EnterpriseProfile;
use App\Models\ExpressionOfInterest;
use App\Services\EoiScreeningService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    /** 'new' | 'edit' */
    public string $mode = 'new';
    public ?int $applicationId = null;
    public ?string $reference = null;

    /** All form fields (bound with wire:model="form.*") */
    public array $form = [];

    /** Form fields that were filled in from the enterprise profile */
    public array $fromProfile = [];

    /** Drawer visibility — the drawer is only rendered while this is true (no JS needed) */
    public bool $showDrawer = false;

    /** Stepper state */
    public int $step = 1;
    public int $maxStep = 1;          // furthest step the user may jump to

    /** Which fields belong to which step (used for per-step validation) */
    protected const STEP_FIELDS = [
        1 => ['business_name', 'representative_name', 'phone', 'email', 'district', 'website', 'legal_status', 'year_commenced'],
        2 => ['business_description', 'annual_turnover', 'full_time_employees'],
        3 => ['investment_required_lsl', 'investment_types', 'investment_types.*', 'use_of_funds', 'use_of_funds_other', 'use_of_funds_description'],
        4 => ['investor_status', 'investor_status_other', 'investor_amount_lsl', 'investor_name', 'investor_evidence'],
        5 => ['support_need', 'declaration_agree', 'declaration_name', 'declaration_date'],
    ];
    protected const LAST_STEP = 6;   // 6 = review

    /** Profile deal status → EOI investor engagement status */
    protected const INVESTOR_STATUS_MAP = [
        'discussion' => 'discussions', 'loi' => 'loi', 'term_sheet' => 'term_sheet', 'agreement' => 'committed',
    ];

    /** Profile deal status → evidence the applicant can provide (when the commitment document is uploaded) */
    protected const EVIDENCE_MAP = [
        'loi' => 'loi', 'term_sheet' => 'term_sheet', 'agreement' => 'investment_agreement', 'discussion' => 'other_formal',
    ];

    public function mount(): void
    {
        $this->form = $this->blankForm();
    }

    /* ==================================================================
     | Events — dispatched from the list (or anywhere):
     |   $dispatch('open-application')
     |   $dispatch('edit-application',      { id: 2 })
     |   $dispatch('duplicate-application', { id: 2 })
     * ================================================================== */

    #[On('open-application')]
    public function openApplication(): void
    {
        $profile = $this->profile();

        // New applications need a complete enterprise profile
        if (! $profile?->exists || ! $profile->isComplete()) {
            $this->dispatch('notify', type: 'warning', message: 'Complete your enterprise profile before applying.');
            return;
        }

        $this->resetDrawer();
        foreach (['representative_name', 'phone', 'email', 'declaration_name'] as $field) {
            $this->form[$field] = '';   // let the profile's contact details take precedence
        }
        $this->fillFromProfile($profile);
        $this->showDrawer();
    }

    #[On('edit-application')]
    public function editApplication($id): void
    {
        $eoi = $this->findApplication($id);

        if (! $eoi) {
            $this->dispatch('notify', type: 'error', message: 'Application not found.');
            return;
        }

        // NOTE: status/permission checks are off for now — any of the user's applications can be edited.
        // To lock submitted ones again, restore:  if (! $eoi->isEditable()) { notify error; return; }

        $this->resetDrawer();
        $this->mode          = 'edit';
        $this->applicationId = $eoi->id;
        $this->reference     = $eoi->reference;
        $this->fillFrom($eoi->toFormArray());
        $this->fillFromProfile($this->profile());   // only fills answers that are still empty
        $this->maxStep = self::LAST_STEP;
        $this->showDrawer();
    }

    #[On('duplicate-application')]
    public function duplicateApplication($id): void
    {
        $eoi = $this->findApplication($id);

        if (! $eoi) {
            $this->dispatch('notify', type: 'error', message: 'Application not found.');
            return;
        }

        $this->resetDrawer();                       // stays in "new" mode → creates a new record
        $this->fillFrom($eoi->toFormArray());
        $this->fillFromProfile($this->profile());   // only fills answers that are still empty
        $this->form['declaration_agree'] = false;   // must re-confirm
        $this->form['declaration_date']  = now()->toDateString();
        $this->maxStep = self::LAST_STEP;
        $this->showDrawer();
    }

    /* ==================================================================
     | Stepper
     * ================================================================== */

    /** Validate the current step, then move on. */
    public function nextStep(): void
    {
        if ($this->step < self::LAST_STEP) {
            $this->validateStep($this->step);
            $this->step++;
            $this->maxStep = max($this->maxStep, $this->step);
        }
    }

    public function prevStep(): void
    {
        $this->step = max(1, $this->step - 1);
    }

    public function goToStep(int $step): void
    {
        if ($step < 1 || $step > $this->maxStep) {
            return;
        }
        if ($step > $this->step) {
            $this->validateStep($this->step);
        }
        $this->step = $step;
    }

    protected function validateStep(int $step): void
    {
        $keys = array_map(fn ($f) => "form.{$f}", self::STEP_FIELDS[$step] ?? []);
        $this->validate(Arr::only($this->rules(), $keys));
    }

    /** First step that contains one of the failing fields. */
    protected function stepForErrors(array $errorKeys): int
    {
        foreach (self::STEP_FIELDS as $step => $fields) {
            foreach ($errorKeys as $key) {
                $field = preg_replace('/^form\.|\.\d+$/', '', $key);
                if (in_array($field, $fields, true)) {
                    return $step;
                }
            }
        }
        return 1;
    }

    /* ==================================================================
     | Actions
     * ================================================================== */

    /** Save without full validation (only a business name is needed). */
    public function saveDraft(): void
    {
        $this->validate(
            ['form.business_name' => ['required', 'string', 'max:255']],
            ['form.business_name.required' => 'Enter at least the business name to save a draft.']
        );

        $eoi = $this->persist(ExpressionOfInterest::STATUS_DRAFT);
        if (! $eoi) {
            return;
        }

        $this->dispatch('notify', type: 'success', message: "Draft {$eoi->reference} saved.");
        $this->dispatch('application-saved');
    }

    /** Full validation → save as submitted → run internal screening. */
    public function submitApplication(): void
    {
        try {
            $this->validate();
        } catch (ValidationException $e) {
            $this->step = $this->stepForErrors(array_keys($e->errors()));   // jump to the problem
            throw $e;
        }

        $eoi = $this->persist(ExpressionOfInterest::STATUS_SUBMITTED);
        if (! $eoi) {
            return;
        }

        $message = $this->mode === 'edit'
            ? "Application {$eoi->reference} has been updated and resubmitted."
            : "Your Expression of Interest {$eoi->reference} has been submitted.";

        $this->closeDrawer();
        $this->dispatch('notify', type: 'success', message: $message);
        $this->dispatch('application-saved');
        $this->resetDrawer();
    }

    /**
     * Create or update the record. Returns null (and notifies) if the record is locked.
     */
    protected function persist(string $status): ?ExpressionOfInterest
    {
        // Existing record: must belong to the user
        if ($this->applicationId) {
            $eoi = $this->findApplication($this->applicationId);

            if (! $eoi) {
                $this->dispatch('notify', type: 'error', message: 'Application not found.');
                return null;
            }
        } else {
            $eoi = new ExpressionOfInterest(['user_id' => auth()->id()]);
        }

        $data = $this->payload();

        DB::transaction(function () use ($eoi, $data, $status) {
            $eoi->fill($data);

            // Saving a draft of a "returned" application keeps it returned
            $eoi->status = ($status === ExpressionOfInterest::STATUS_DRAFT && $eoi->status === ExpressionOfInterest::STATUS_RETURNED)
                ? ExpressionOfInterest::STATUS_RETURNED
                : $status;

            if ($status === ExpressionOfInterest::STATUS_SUBMITTED) {
                $eoi->submitted_at = now();

                $screening = app(EoiScreeningService::class)->classify($data);
                $eoi->screening_outcome = $screening['outcome'];
                $eoi->screening_reasons = $screening['reasons'];
            }

            $eoi->save();
        });

        $eoi->refresh();   // picks up the generated reference

        // Keep working on the same record (e.g. Save draft → Save draft again)
        $this->applicationId = $eoi->id;
        $this->reference     = $eoi->reference;

        return $eoi;
    }

    /** Form values → DB-ready values ('' → null for optional / numeric fields). */
    protected function payload(): array
    {
        $data = Arr::only($this->form, ExpressionOfInterest::FORM_FIELDS);

        foreach ($data as $key => $value) {
            if ($value === '') {
                $data[$key] = null;
            }
        }
        foreach (['investment_types', 'use_of_funds', 'investor_evidence'] as $key) {
            $data[$key] = array_values((array) ($data[$key] ?? []));
        }
        $data['declaration_agree'] = (bool) ($data['declaration_agree'] ?? false);

        // Investment required is entered in Maloti; the column stores US$
        $lsl = $this->form['investment_required_lsl'] ?? '';
        $data['investment_required_usd'] = filled($lsl) ? round((float) $lsl / $this->rate(), 2) : null;

        // Investor amount is entered in Maloti; the column stores US$
        $inv = $this->form['investor_amount_lsl'] ?? '';
        $data['investor_amount_usd'] = filled($inv) ? round((float) $inv / $this->rate(), 2) : null;

        // Clear answers that no longer apply
        if (! in_array('other', $data['use_of_funds'], true)) {
            $data['use_of_funds_other'] = null;
        }
        if (($data['investor_status'] ?? null) !== 'other') {
            $data['investor_status_other'] = null;
        }
        if (($data['investor_status'] ?? null) === 'none') {
            $data['investor_amount_usd'] = null;
            $data['investor_name']       = null;
            $data['investor_evidence']   = [];
        }

        return $data;
    }

    /* ==================================================================
     | Validation (full submit)
     * ================================================================== */

    protected function rules(): array
    {
        $maxWords = fn (int $max) => function ($attribute, $value, $fail) use ($max) {
            if (str_word_count(strip_tags((string) $value)) > $max) {
                $fail("The :attribute may not be more than {$max} words.");
            }
        };

        // Co-investment minimum: the investor must be investing at least US$ 50,000 (config/cif.php)
        $minUsd   = number_format(EnterpriseProfile::minInvestorUsd());
        $minLsl   = 'M '.number_format(EnterpriseProfile::minInvestorLsl());
        $ratio    = EnterpriseProfile::grantRatioLabel();
        $hasInvestor = function ($attribute, $value, $fail) use ($minUsd, $ratio) {
            if ($value === 'none') {
                $fail("To apply, you need an investor who is investing at least US$ {$minUsd}. CAFI matches {$ratio} of what the investor puts in.");
            }
        };
        $minimum = function ($attribute, $value, $fail) use ($minUsd, $minLsl) {
            if (is_numeric($value) && ! EnterpriseProfile::meetsInvestorMinimum((float) $value)) {
                $fail("The investor must be investing at least US$ {$minUsd} (about {$minLsl}). This amount is about US$ ".number_format((float) $value / $this->rate()).'.');
            }
        };

        return [
            'form.business_name'            => ['required', 'string', 'max:255'],
            'form.representative_name'      => ['required', 'string', 'max:255'],
            'form.phone'                    => ['required', 'string', 'max:30'],
            'form.email'                    => ['required', 'email', 'max:255'],
            'form.district'                 => ['required', 'string', 'max:50'],
            'form.website'                  => ['nullable', 'string', 'max:255'],
            'form.legal_status'             => ['required', 'in:registered_operating,registered_not_operating,registration_in_progress,not_registered'],
            'form.year_commenced'           => ['nullable', 'integer', 'between:1950,'.date('Y')],
            'form.business_description'     => ['required', 'string', $maxWords(100)],
            'form.annual_turnover'          => ['required', 'in:below_500k,500k_2m,2m_5m,5m_10m,above_10m'],
            'form.full_time_employees'      => ['required', 'integer', 'min:0'],
            'form.investment_required_lsl'  => ['required', 'numeric', 'min:0'],
            'form.investment_types'         => ['required', 'array', 'min:1'],
            'form.investment_types.*'       => ['in:equity,quasi_equity,debt'],
            'form.use_of_funds'             => ['required', 'array', 'min:1'],
            'form.use_of_funds_other'       => ['nullable', 'string', 'max:255'],
            'form.use_of_funds_description' => ['required', 'string', $maxWords(100)],
            'form.investor_status'          => ['required', 'in:none,discussions,interest,loi,term_sheet,committed,other', $hasInvestor],
            'form.investor_status_other'    => ['required_if:form.investor_status,other', 'nullable', 'string', 'max:255'],
            'form.investor_amount_lsl'      => ['required_unless:form.investor_status,none', 'nullable', 'numeric', 'min:0', $minimum],
            'form.investor_name'            => ['nullable', 'string', 'max:255'],
            'form.investor_evidence'        => ['nullable', 'array'],
            'form.support_need'             => ['required', 'in:ci_facility,progress_deal,find_investor,readiness,assess_me'],
            'form.declaration_agree'        => ['accepted'],
            'form.declaration_name'         => ['required', 'string', 'max:255'],
            'form.declaration_date'         => ['required', 'date'],
        ];
    }

    protected function messages(): array
    {
        return [
            'form.investor_amount_lsl.required_unless' => 'Enter the amount your investor is investing (at least US$ '.number_format(EnterpriseProfile::minInvestorUsd()).').',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'form.business_name'            => 'business name',
            'form.representative_name'      => 'founder / authorised representative',
            'form.phone'                    => 'telephone',
            'form.email'                    => 'email',
            'form.district'                 => 'district',
            'form.legal_status'             => 'legal status',
            'form.year_commenced'           => 'year operations commenced',
            'form.business_description'     => 'business description',
            'form.annual_turnover'          => 'annual turnover',
            'form.full_time_employees'      => 'number of full-time employees',
            'form.investment_required_lsl'  => 'total investment required',
            'form.investment_types'         => 'type of investment',
            'form.use_of_funds'             => 'use of investment',
            'form.use_of_funds_description' => 'planned use of the investment',
            'form.investor_status'          => 'investor engagement status',
            'form.investor_status_other'    => 'other investor status',
            'form.investor_amount_lsl'      => 'investor amount',
            'form.support_need'             => 'support needed',
            'form.declaration_agree'        => 'declaration',
            'form.declaration_name'         => 'name',
            'form.declaration_date'         => 'date',
        ];
    }

    /* ==================================================================
     | Enterprise profile → EOI answers
     * ================================================================== */

    protected function profile(): ?EnterpriseProfile
    {
        return auth()->user()?->can('eoi.submit') ? EnterpriseProfile::forUser(auth()->user()) : null;
    }

    /** EOI answers that can be taken from the enterprise profile (and the user account). */
    protected function profileAnswers(EnterpriseProfile $p): array
    {
        $user = auth()->user();

        // Annual revenue (Maloti) → turnover band
        $turnover = '';
        if (filled($p->annual_revenue)) {
            $r = (float) $p->annual_revenue;
            $turnover = match (true) {
                $r < 500_000    => 'below_500k',
                $r < 2_000_000  => '500k_2m',
                $r < 5_000_000  => '2m_5m',
                $r < 10_000_000 => '5m_10m',
                default         => 'above_10m',
            };
        }

        // Registered at OBFC → registered and operating (otherwise the applicant chooses)
        $legal = $p->obfc_registered === true || ($p->obfc_registered === null && filled($p->registration_no)) ? 'registered_operating' : '';

        // Financing type of the round → EOI investment type
        $types = match ($p->participation) {
            'equity'                                    => ['equity'],
            'convertible_note', 'safe', 'revenue_share' => ['quasi_equity'],
            default                                     => [],
        };

        // Full-time staff from the employee details, else the current total
        $fullTime = $p->ft_male !== null || $p->ft_female !== null ? (int) $p->ft_male + (int) $p->ft_female : $p->employees;
        $investor = $p->investor_contribution ?: $p->round_amount;

        // Deal status + uploaded commitment document → investor status and evidence
        $status   = self::INVESTOR_STATUS_MAP[$p->transaction_status ?? ''] ?? '';
        $evidence = $p->document('commitment') && isset(self::EVIDENCE_MAP[$p->transaction_status ?? ''])
            ? [self::EVIDENCE_MAP[$p->transaction_status]]
            : [];

        // A committed investor (term sheet / agreement) is what the Co-Investment Facility is for
        $support = in_array($p->transaction_status, ['term_sheet', 'agreement'], true) ? 'ci_facility'
                 : (in_array($p->transaction_status, ['discussion', 'loi'], true) ? 'progress_deal' : '');

        return [
            'business_name'            => $p->registered_name ?: ($user->enterprise_name ?? ''),
            'representative_name'      => $p->contact_name ?: ($p->declared_name ?: $user->name),
            'phone'                    => $p->contact_phone ?: ($user->phone ?? ''),
            'email'                    => $p->contact_email ?: ($user->email ?? ''),
            'district'                 => $p->district ?? '',
            'website'                  => $p->website ?? '',
            'legal_status'             => $legal,
            'year_commenced'           => $p->year_established !== null ? (string) $p->year_established : '',
            'business_description'     => filled($p->activity) ? Str::words(trim($p->activity), 100, '') : '',
            'annual_turnover'          => $turnover,
            'full_time_employees'      => $fullTime !== null ? (string) $fullTime : '',
            'investment_required_lsl'  => filled($p->project_cost ?: $p->round_amount) ? (string) round((float) ($p->project_cost ?: $p->round_amount)) : '',
            'investment_types'         => $types,
            'use_of_funds_description' => filled($p->investment_purpose) ? Str::words(trim($p->investment_purpose), 100, '') : '',
            'investor_status'          => $status,
            'investor_amount_lsl'      => filled($investor) ? (string) round((float) $investor) : '',
            'investor_name'            => $p->investor_name ?? '',
            'investor_evidence'        => $evidence,
            'support_need'             => $support,
            'declaration_name'         => $p->contact_name ?: ($p->declared_name ?: $user->name),
        ];
    }

    /** How many answers on a step came from the profile (for the notice in the drawer). */
    public function profileCountForStep(int $step): int
    {
        return count(array_intersect(self::STEP_FIELDS[$step] ?? [], $this->fromProfile));
    }

    /** Fill empty form answers from the profile and remember which ones came from it. */
    protected function fillFromProfile(?EnterpriseProfile $profile): void
    {
        if (! $profile?->exists) {
            return;
        }

        foreach ($this->profileAnswers($profile) as $field => $value) {
            $current = $this->form[$field] ?? null;
            $isEmpty = is_array($current) ? $current === [] : blank($current);

            if ($isEmpty && filled($value)) {
                $this->form[$field] = $value;
                $this->fromProfile[] = $field;
            }
        }

        $this->fromProfile = array_values(array_unique($this->fromProfile));
    }

    /* ==================================================================
     | Helpers
     * ================================================================== */

    /** Maloti per US dollar */
    protected function rate(): float
    {
        return max((float) config('cif.usd_to_lsl', 18.00), 0.0001);
    }

    /** Only the logged-in applicant's own records. */
    protected function findApplication($id): ?ExpressionOfInterest
    {
        return ExpressionOfInterest::ownedBy(auth()->id())->find($id);
    }

    protected function blankForm(): array
    {
        return [
            'business_name' => '', 'representative_name' => auth()->user()->name ?? '', 'phone' => auth()->user()->phone ?? '',
            'email' => auth()->user()->email ?? '', 'district' => '', 'website' => '',
            'legal_status' => '', 'year_commenced' => '',
            'business_description' => '', 'annual_turnover' => '', 'full_time_employees' => '',
            'investment_required_lsl' => '', 'investment_types' => [], 'use_of_funds' => [],
            'use_of_funds_other' => '', 'use_of_funds_description' => '',
            'investor_status' => '', 'investor_status_other' => '', 'investor_amount_lsl' => '',
            'investor_name' => '', 'investor_evidence' => [],
            'support_need' => '', 'declaration_agree' => false,
            'declaration_name' => auth()->user()->name ?? '', 'declaration_date' => now()->toDateString(),
        ];
    }

    protected function fillFrom(array $record): void
    {
        $blank  = $this->blankForm();
        $values = array_map(fn ($v) => $v ?? '', Arr::only($record, array_keys($blank)));

        $this->form = array_merge($blank, $values);

        foreach (['investment_types', 'use_of_funds', 'investor_evidence'] as $key) {
            $this->form[$key] = array_values((array) ($record[$key] ?? []));
        }
        $this->form['declaration_agree'] = (bool) ($record['declaration_agree'] ?? false);
        $this->form['declaration_date']  = $this->form['declaration_date'] ?: now()->toDateString();

        $usd = $record['investment_required_usd'] ?? null;
        $this->form['investment_required_lsl'] = filled($usd) ? (string) round((float) $usd * $this->rate()) : '';

        $inv = $record['investor_amount_usd'] ?? null;
        $this->form['investor_amount_lsl'] = filled($inv) ? (string) round((float) $inv * $this->rate()) : '';
    }

    protected function resetDrawer(): void
    {
        $this->step          = 1;
        $this->maxStep       = 1;
        $this->mode          = 'new';
        $this->applicationId = null;
        $this->reference     = null;
        $this->form          = $this->blankForm();
        $this->fromProfile   = [];
        $this->resetValidation();
    }

    protected function showDrawer(): void
    {
        $this->showDrawer = true;
    }

    #[On('close-application')]
    public function closeDrawer(): void
    {
        $this->showDrawer = false;
    }
};
?>

<div>
{{--
    EOI Apply / Edit drawer — Livewire 4 component (pure Livewire, no JavaScript).
    Place once on a page:  <livewire:expression-of-interest.drawer.express-interest />
    Open it from anywhere:
        wire:click="$dispatch('open-application')"                        new, pre-filled from the enterprise profile
        wire:click="$dispatch('edit-application', { id: 2 })"             edit, pre-filled
        wire:click="$dispatch('duplicate-application', { id: 2 })"        new, copied
    Styles: resources/css/app.css
--}}
@php
    /* Form options — keys are stored in the DB, labels are shown to the applicant */
    $districts = ['Berea', 'Butha-Buthe', 'Leribe', 'Mafeteng', 'Maseru', "Mohale's Hoek", 'Mokhotlong', "Qacha's Nek", 'Quthing', 'Thaba-Tseka'];

    $legalStatuses = [
        'registered_operating'     => 'Yes, registered and operating in Lesotho',
        'registered_not_operating' => 'Registered but operations are not yet established',
        'registration_in_progress' => 'Registration is in progress',
        'not_registered'           => 'Not registered',
    ];
    $turnoverBands = [
        'below_500k' => 'Below M500,000', '500k_2m' => 'M500,000 – M2 million', '2m_5m' => 'M2 – M5 million',
        '5m_10m' => 'M5 – M10 million', 'above_10m' => 'Above M10 million',
    ];
    $investmentTypes = ['equity' => 'Equity', 'quasi_equity' => 'Quasi-equity', 'debt' => 'Debt'];
    $useOfFunds = [
        'equipment' => 'Equipment / productive assets', 'business_expansion' => 'Business expansion',
        'working_capital' => 'Working capital', 'market_expansion' => 'Market expansion',
        'technology' => 'Technology / systems', 'product_development' => 'Product development', 'other' => 'Other (specify)',
    ];
    $investorStatuses = [
        'none' => 'I do not currently have an investor', 'discussions' => 'I am in discussions with potential investors',
        'interest' => 'I have received an indication of investor interest', 'loi' => 'I have received a Letter of Intent',
        'term_sheet' => 'I have a Term Sheet', 'committed' => 'Investment has been formally committed', 'other' => 'Other (specify)',
    ];
    $investorEvidence = [
        'loi' => 'Letter of Intent', 'term_sheet' => 'Term Sheet', 'investment_agreement' => 'Investment Agreement',
        'other_formal' => 'Other formal evidence of commitment', 'none_available' => 'No supporting document currently available',
        'not_applicable' => 'Not applicable',
    ];
    $supportNeeds = [
        'ci_facility'   => ['I already have an investor commitment and would like to be considered for the CAFI Co-Investment Facility.', 'bi-patch-check'],
        'progress_deal' => ['I have an interested investor but need support to progress the transaction.', 'bi-arrow-repeat'],
        'find_investor' => ['I am investment-ready but need help finding an investor.', 'bi-arrow-left-right'],
        'readiness'     => ['I need support to become investment-ready.', 'bi-ladder'],
        'assess_me'     => ['I am not sure and would like to be assessed.', 'bi-question-circle'],
    ];
    $steps = [
        1 => ['Business', 'bi-building'], 2 => ['Operations', 'bi-graph-up'], 3 => ['Investment', 'bi-cash-coin'],
        4 => ['Investor', 'bi-briefcase'], 5 => ['Support', 'bi-signpost-split'], 6 => ['Review', 'bi-check2-square'],
    ];

    /* Helpers for the review step */
    $one  = fn ($map, $v) => $v === null || $v === '' ? null : ($map[$v] ?? $v);
    $many = fn ($map, $vals) => collect($vals ?? [])->map(fn ($v) => $map[$v] ?? $v)->implode(', ') ?: null;
    $rate  = max((float) config('cif.usd_to_lsl', 18.00), 0.0001);
    $toUsd = fn ($m) => 'US$ '.number_format((float) $m / $rate);
    $lslUsd = fn ($m) => $m === null || $m === '' ? null : 'M '.number_format((float) $m).' (≈ '.$toUsd($m).')';

    $showUseOther      = in_array('other', $form['use_of_funds'] ?? [], true);
    $showStatusOther   = ($form['investor_status'] ?? '') === 'other';
    $showInvestorBlock = ! in_array($form['investor_status'] ?? '', ['', 'none'], true);
    $P = \App\Models\EnterpriseProfile::class;

    /* "From profile" marker next to answers taken from the enterprise profile */
    $fp = fn (string $field) => in_array($field, $fromProfile, true)
        ? '<span class="pill pill-muted ms-1" style="font-size:.68rem" title="Filled in from your enterprise profile — you can change it"><i class="bi bi-person-vcard"></i> From profile</span>'
        : '';
    $stepFromProfile = fn (int $n) => $this->profileCountForStep($n);
@endphp

{{-- ======================================================================
     DRAWER — rendered only while $showDrawer is true
     ====================================================================== --}}
@if ($showDrawer)

<div class="drawer-overlay drawer-fade" wire:click="closeDrawer"></div>

<div class="drawer drawer-lg drawer-panel drawer-enter" role="dialog" aria-modal="true" aria-labelledby="eoiDrawerLabel"
     wire:keydown.escape.window="closeDrawer">

    {{-- Header --}}
    <div class="offcanvas-header drawer-header">
        <div>
            <div class="drawer-eyebrow">CAFI Co-Investment Facility</div>
            <h5 class="offcanvas-title" id="eoiDrawerLabel">
                {{ $mode === 'edit' ? 'Edit Expression of Interest'.($reference ? ' · '.$reference : '') : 'Enterprise Expression of Interest' }}
            </h5>
        </div>
        <button type="button" class="btn-close btn-close-white" wire:click="closeDrawer" aria-label="Close"></button>
    </div>

    {{-- Stepper --}}
    <div class="stepper">
        @foreach ($steps as $n => [$label, $icon])
            <button type="button" wire:click="goToStep({{ $n }})" @disabled($n > $maxStep)
                    class="stepper-step {{ $n === $step ? 'active' : '' }} {{ $n < $step ? 'done' : '' }}">
                <span class="stepper-dot"><i class="bi {{ $n < $step ? 'bi-check-lg' : $icon }}"></i></span>
                <span class="stepper-label">{{ $label }}</span>
            </button>
        @endforeach
        <div class="stepper-track"><div class="stepper-fill" style="width: {{ ($step - 1) / (count($steps) - 1) * 100 }}%"></div></div>
    </div>

    <form wire:submit.prevent="nextStep" class="form-modern d-flex flex-column flex-grow-1 overflow-hidden" novalidate>
        <div class="offcanvas-body drawer-body" wire:key="step-{{ $step }}">

            {{-- Pre-filled notice --}}
            @if ($step < 6 && $stepFromProfile($step) > 0)
                <div class="callout callout-success small mb-3">
                    <i class="bi bi-person-vcard me-1"></i>
                    {{ $stepFromProfile($step) }} {{ \Illuminate\Support\Str::plural('answer', $stepFromProfile($step)) }} on this step {{ $stepFromProfile($step) === 1 ? 'was' : 'were' }} filled in from your
                    <a href="{{ route('profile.index') }}" target="_blank">enterprise profile</a>. Check {{ $stepFromProfile($step) === 1 ? 'it' : 'them' }} and change anything that doesn't fit this application.
                </div>
            @endif

            {{-- ───────────── STEP 1: BUSINESS ───────────── --}}
            @if ($step === 1)
                <section class="step-panel is-entering">
                    <div class="form-section-head">
                        <span class="q-badge">Q1</span>
                        <div><h6>Tell us about your business</h6><p>Who we'll be speaking to about this EOI.</p></div>
                    </div>

                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label" for="business_name">Business name <span class="req">*</span> {!! $fp('business_name') !!}</label>
                            <input type="text" id="business_name" wire:model="form.business_name" class="form-control @error('form.business_name') is-invalid @enderror">
                            @error('form.business_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="representative_name">Name of founder / authorised representative <span class="req">*</span> {!! $fp('representative_name') !!}</label>
                            <input type="text" id="representative_name" wire:model="form.representative_name" class="form-control @error('form.representative_name') is-invalid @enderror">
                            @error('form.representative_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="phone">Telephone <span class="req">*</span> {!! $fp('phone') !!}</label>
                            <input type="tel" id="phone" wire:model="form.phone" placeholder="+266 5xxx xxxx" class="form-control @error('form.phone') is-invalid @enderror">
                            @error('form.phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="email">Email <span class="req">*</span> {!! $fp('email') !!}</label>
                            <input type="email" id="email" wire:model="form.email" class="form-control @error('form.email') is-invalid @enderror">
                            @error('form.email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="district">District <span class="req">*</span> {!! $fp('district') !!}</label>
                            <select id="district" wire:model="form.district" class="form-select @error('form.district') is-invalid @enderror">
                                <option value="">Select district…</option>
                                @foreach ($districts as $d) <option value="{{ $d }}">{{ $d }}</option> @endforeach
                            </select>
                            @error('form.district') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="website">Website / social media <span class="opt">(if applicable)</span> {!! $fp('website') !!}</label>
                            <input type="text" id="website" wire:model="form.website" placeholder="https:// or @handle" class="form-control">
                        </div>
                    </div>

                    <div class="form-section-head mt-4">
                        <span class="q-badge">Q2</span>
                        <div><h6>Is your business legally established and operating in Lesotho? <span class="req">*</span> {!! $fp('legal_status') !!}</h6></div>
                    </div>
                    <div class="choice-group @error('form.legal_status') is-invalid @enderror">
                        @foreach ($legalStatuses as $value => $label)
                            <label class="choice">
                                <input type="radio" wire:model="form.legal_status" value="{{ $value }}">
                                <span class="choice-box"><span class="choice-radio"></span>{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('form.legal_status') <div class="text-danger small mt-1">{{ $message }}</div> @enderror

                    <div class="mt-3" style="max-width: 320px;">
                        <label class="form-label" for="year_commenced">When did the business commence operations? {!! $fp('year_commenced') !!}</label>
                        <input type="number" id="year_commenced" wire:model="form.year_commenced" min="1950" max="{{ date('Y') }}" placeholder="Year" class="form-control @error('form.year_commenced') is-invalid @enderror">
                        @error('form.year_commenced') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </section>
            @endif

            {{-- ───────────── STEP 2: OPERATIONS ───────────── --}}
            @if ($step === 2)
                <section class="step-panel is-entering">
                    <div class="form-section-head">
                        <span class="q-badge">Q3</span>
                        <div>
                            <h6>What does your business do? <span class="req">*</span> {!! $fp('business_description') !!}</h6>
                            <p>In no more than 100 words, describe your main product/service, the customers you serve and the problem or market opportunity you address.</p>
                        </div>
                    </div>
                    @php $words = str_word_count(strip_tags((string) $form['business_description'])); @endphp
                    <div>
                        <textarea id="business_description" rows="6" wire:model.live.debounce.600ms="form.business_description" class="form-control @error('form.business_description') is-invalid @enderror"></textarea>
                        <div class="word-count {{ $words > 100 ? 'over' : '' }}">{{ $words }} / 100 words</div>
                        @error('form.business_description') <div class="text-danger small">{{ $message }}</div> @enderror
                    </div>

                    <div class="form-section-head mt-4">
                        <span class="q-badge">Q4</span>
                        <div><h6>What is your current scale of operations?</h6></div>
                    </div>
                    <label class="form-label">Approximate annual turnover <span class="req">*</span> {!! $fp('annual_turnover') !!}</label>
                    <div class="choice-group cols-2 @error('form.annual_turnover') is-invalid @enderror">
                        @foreach ($turnoverBands as $value => $label)
                            <label class="choice">
                                <input type="radio" wire:model="form.annual_turnover" value="{{ $value }}">
                                <span class="choice-box"><span class="choice-radio"></span>{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('form.annual_turnover') <div class="text-danger small mt-1">{{ $message }}</div> @enderror

                    <div class="mt-3" style="max-width: 300px;">
                        <label class="form-label" for="full_time_employees">Number of full-time employees <span class="req">*</span> {!! $fp('full_time_employees') !!}</label>
                        <input type="number" id="full_time_employees" min="0" wire:model="form.full_time_employees" class="form-control @error('form.full_time_employees') is-invalid @enderror">
                        @error('form.full_time_employees') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </section>
            @endif

            {{-- ───────────── STEP 3: INVESTMENT ───────────── --}}
            @if ($step === 3)
                <section class="step-panel is-entering">
                    <div class="form-section-head">
                        <span class="q-badge">Q5</span>
                        <div><h6>What investment are you seeking?</h6></div>
                    </div>
                    <div style="max-width: 360px;">
                        <label class="form-label" for="investment_required_lsl">Total investment required for your growth plans <span class="req">*</span> {!! $fp('investment_required_lsl') !!}</label>
                        <div class="input-group has-validation">
                            <span class="input-group-text">M</span>
                            <input type="number" id="investment_required_lsl" min="0" step="1000" wire:model.blur="form.investment_required_lsl" class="form-control @error('form.investment_required_lsl') is-invalid @enderror">
                            @error('form.investment_required_lsl') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="form-text">
                            @if (filled($form['investment_required_lsl'])) ≈ {{ $toUsd($form['investment_required_lsl']) }} @endif
                            @if (in_array('investment_required_lsl', $fromProfile, true)) <span class="d-block">Taken from your enterprise profile (total project cost, or the current investment round).</span> @endif
                        </div>
                    </div>

                    <label class="form-label mt-3">What type of investment are you seeking or have you obtained? <span class="req">*</span> {!! $fp('investment_types') !!}</label>
                    <div class="choice-group cols-3 @error('form.investment_types') is-invalid @enderror">
                        @foreach ($investmentTypes as $value => $label)
                            <label class="choice">
                                <input type="checkbox" wire:model="form.investment_types" value="{{ $value }}">
                                <span class="choice-box"><span class="choice-check"></span>{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('form.investment_types') <div class="text-danger small mt-1">{{ $message }}</div> @enderror

                    <div class="form-section-head mt-4">
                        <span class="q-badge">Q6</span>
                        <div><h6>How would the investment be used? <span class="req">*</span></h6><p>Select all that apply.</p></div>
                    </div>
                    <div class="choice-group cols-2 @error('form.use_of_funds') is-invalid @enderror">
                        @foreach ($useOfFunds as $value => $label)
                            <label class="choice">
                                <input type="checkbox" wire:model.live="form.use_of_funds" value="{{ $value }}">
                                <span class="choice-box"><span class="choice-check"></span>{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('form.use_of_funds') <div class="text-danger small mt-1">{{ $message }}</div> @enderror

                    @if ($showUseOther)
                        <input type="text" wire:model="form.use_of_funds_other" placeholder="Please specify" class="form-control mt-2">
                    @endif

                    <label class="form-label mt-3" for="use_of_funds_description">Briefly describe the planned use of the investment <span class="req">*</span> {!! $fp('use_of_funds_description') !!}</label>
                    @php $words = str_word_count(strip_tags((string) $form['use_of_funds_description'])); @endphp
                    <div>
                        <textarea id="use_of_funds_description" rows="4" wire:model.live.debounce.600ms="form.use_of_funds_description" class="form-control @error('form.use_of_funds_description') is-invalid @enderror"></textarea>
                        <div class="word-count {{ $words > 100 ? 'over' : '' }}">{{ $words }} / 100 words</div>
                        @error('form.use_of_funds_description') <div class="text-danger small">{{ $message }}</div> @enderror
                    </div>
                </section>
            @endif

            {{-- ───────────── STEP 4: INVESTOR ───────────── --}}
            @if ($step === 4)
                <section class="step-panel is-entering">
                    <div class="form-section-head">
                        <span class="q-badge">Q7</span>
                        <div><h6>What is the status of your investor engagement? <span class="req">*</span> {!! $fp('investor_status') !!}</h6></div>
                    </div>
                    <div class="choice-group @error('form.investor_status') is-invalid @enderror">
                        @foreach ($investorStatuses as $value => $label)
                            <label class="choice">
                                <input type="radio" wire:model.live="form.investor_status" value="{{ $value }}">
                                <span class="choice-box"><span class="choice-radio"></span>{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('form.investor_status') <div class="text-danger small mt-1">{{ $message }}</div> @enderror

                    @if ($showStatusOther)
                        <input type="text" wire:model="form.investor_status_other" placeholder="Please specify" class="form-control mt-2 @error('form.investor_status_other') is-invalid @enderror">
                        @error('form.investor_status_other') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                    @endif

                    @if ($showInvestorBlock)
                        <div class="form-section-head mt-4">
                            <span class="q-badge">Q8</span>
                            <div><h6>Investor commitment</h6><p>If you already have an investor, tell us about their commitment.</p></div>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="investor_amount_lsl">Amount indicated or committed {!! $fp('investor_amount_lsl') !!}</label>
                                <div class="input-group has-validation">
                                    <span class="input-group-text">M</span>
                                    <input type="number" id="investor_amount_lsl" min="0" step="1000" wire:model.blur="form.investor_amount_lsl" class="form-control @error('form.investor_amount_lsl') is-invalid @enderror">
                                    @error('form.investor_amount_lsl') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="form-text">
                                    @if (filled($form['investor_amount_lsl'])) ≈ {{ $toUsd($form['investor_amount_lsl']) }} @endif
                                    @if (in_array('investor_amount_lsl', $fromProfile, true)) <span class="d-block">Taken from the investor contribution in your profile.</span> @endif
                                    <span class="d-block">Minimum US$ {{ number_format($P::minInvestorUsd()) }} (about M {{ number_format($P::minInvestorLsl()) }}).</span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="investor_name">Investor name {!! $fp('investor_name') !!}</label>
                                <input type="text" id="investor_name" wire:model="form.investor_name" class="form-control">
                            </div>
                        </div>
                        @php
                            $invAmount = is_numeric($form['investor_amount_lsl'] ?? null) ? (float) $form['investor_amount_lsl'] : null;
                            $cafiGrant = $P::matchingGrant($invAmount);
                        @endphp
                        @if ($cafiGrant !== null)
                            @if ($P::meetsInvestorMinimum($invAmount))
                                <div class="callout callout-success small mt-3 mb-0">
                                    <i class="bi bi-calculator me-1"></i> CAFI matching grant ({{ $P::grantRatioLabel() }} of the investor amount):
                                    <strong>M {{ number_format($cafiGrant) }}</strong> <span class="text-muted">≈ {{ $toUsd($cafiGrant) }}</span>
                                </div>
                            @else
                                <div class="callout callout-danger small mt-3 mb-0">
                                    <i class="bi bi-exclamation-triangle me-1"></i> The investor amount is below the US$ {{ number_format($P::minInvestorUsd()) }} minimum, so this application cannot be submitted.
                                </div>
                            @endif
                        @endif
                        <label class="form-label mt-3">What evidence can you provide? {!! $fp('investor_evidence') !!}</label>
                        <div class="choice-group cols-2">
                            @foreach ($investorEvidence as $value => $label)
                                <label class="choice">
                                    <input type="checkbox" wire:model="form.investor_evidence" value="{{ $value }}">
                                    <span class="choice-box"><span class="choice-check"></span>{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                    @endif
                </section>
            @endif

            {{-- ───────────── STEP 5: SUPPORT + DECLARATION ───────────── --}}
            @if ($step === 5)
                <section class="step-panel is-entering">
                    <div class="form-section-head">
                        <span class="q-badge">Q9</span>
                        <div>
                            <h6>How would you like LEHSFF to support your investment journey? <span class="req">*</span> {!! $fp('support_need') !!}</h6>
                            <p>Select the option that best describes your current need.</p>
                        </div>
                    </div>
                    <div class="choice-group @error('form.support_need') is-invalid @enderror">
                        @foreach ($supportNeeds as $value => [$label, $icon])
                            <label class="choice choice--card">
                                <input type="radio" wire:model="form.support_need" value="{{ $value }}">
                                <span class="choice-box"><span class="choice-icon"><i class="bi {{ $icon }}"></i></span><span>{{ $label }}</span></span>
                            </label>
                        @endforeach
                    </div>
                    @error('form.support_need') <div class="text-danger small mt-1">{{ $message }}</div> @enderror

                    <div class="form-section-head mt-4">
                        <span class="q-badge">Q10</span>
                        <div><h6>Declaration and consent</h6></div>
                    </div>
                    <div class="callout">
                        I confirm that the information provided is accurate to the best of my knowledge. I understand that submission
                        of this EOI does not constitute a CAFI grant application or guarantee funding. I consent to LEHSFF/CAFI using the
                        information provided to assess my enterprise for investment facilitation, investor matchmaking and/or potential
                        participation in the CAFI Co-Investment Facility.
                    </div>
                    <div class="form-check my-3">
                        <input type="checkbox" id="declaration_agree" wire:model="form.declaration_agree" class="form-check-input @error('form.declaration_agree') is-invalid @enderror">
                        <label class="form-check-label fw-semibold" for="declaration_agree">I agree <span class="req">*</span></label>
                        @error('form.declaration_agree') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="row g-3">
                        <div class="col-md-7">
                            <label class="form-label" for="declaration_name">Name <span class="req">*</span> {!! $fp('declaration_name') !!}</label>
                            <input type="text" id="declaration_name" wire:model="form.declaration_name" class="form-control @error('form.declaration_name') is-invalid @enderror">
                            @error('form.declaration_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-5">
                            <label class="form-label" for="declaration_date">Date <span class="req">*</span></label>
                            <input type="date" id="declaration_date" wire:model="form.declaration_date" class="form-control @error('form.declaration_date') is-invalid @enderror">
                            @error('form.declaration_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                </section>
            @endif

            {{-- ───────────── STEP 6: REVIEW (server-rendered from $form) ───────────── --}}
            @if ($step === 6)
                @php
                    $review = [
                        1 => ['Business', [
                            'Business name' => $form['business_name'], 'Representative' => $form['representative_name'],
                            'Telephone' => $form['phone'], 'Email' => $form['email'], 'District' => $form['district'],
                            'Website / social' => $form['website'], 'Legal status' => $one($legalStatuses, $form['legal_status']),
                            'Operating since' => $form['year_commenced'],
                        ]],
                        2 => ['Operations', [
                            'What the business does' => $form['business_description'],
                            'Annual turnover' => $one($turnoverBands, $form['annual_turnover']),
                            'Full-time employees' => $form['full_time_employees'],
                        ]],
                        3 => ['Investment', array_filter([
                            'Investment required' => $lslUsd($form['investment_required_lsl']),
                            'Investment type' => $many($investmentTypes, $form['investment_types']),
                            'Use of funds' => $many($useOfFunds, $form['use_of_funds']),
                            'Other use' => $showUseOther ? ($form['use_of_funds_other'] ?: null) : false,
                            'Planned use' => $form['use_of_funds_description'],
                        ], fn ($v) => $v !== false)],
                        4 => ['Investor', array_filter([
                            'Investor engagement' => $one($investorStatuses, $form['investor_status']),
                            'Other status' => $showStatusOther ? ($form['investor_status_other'] ?: null) : false,
                            'Investor amount' => $showInvestorBlock ? $lslUsd($form['investor_amount_lsl']) : false,
                            'Investor name' => $showInvestorBlock ? ($form['investor_name'] ?: null) : false,
                            'Evidence' => $showInvestorBlock ? $many($investorEvidence, $form['investor_evidence']) : false,
                        ], fn ($v) => $v !== false)],
                        5 => ['Support & declaration', [
                            'Support needed' => $one(array_map(fn ($s) => $s[0], $supportNeeds), $form['support_need']),
                            'Declaration' => $form['declaration_agree'] ? 'I agree' : null,
                            'Name' => $form['declaration_name'], 'Date' => $form['declaration_date'],
                        ]],
                    ];
                @endphp
                <section class="step-panel is-entering">
                    <div class="form-section-head">
                        <span class="q-badge"><i class="bi bi-eye"></i></span>
                        <div><h6>Review your Expression of Interest</h6><p>Check your answers. Click Edit to change a section.</p></div>
                    </div>

                    @if ($errors->any())
                        <div class="alert alert-danger small">Please fix the highlighted fields before submitting.</div>
                    @endif

                    <div class="review-list">
                        @foreach ($review as $n => [$title, $rows])
                            <div class="review-block">
                                <div class="review-head">
                                    {{ $title }}
                                    <button type="button" wire:click="goToStep({{ $n }})"><i class="bi bi-pencil me-1"></i>Edit</button>
                                </div>
                                <dl>
                                    @foreach ($rows as $label => $value)
                                        <dt>{{ $label }}</dt>
                                        <dd class="{{ filled($value) ? '' : 'empty' }}">{{ filled($value) ? $value : 'Not provided' }}</dd>
                                    @endforeach
                                </dl>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif
        </div>

        {{-- Footer --}}
        <div class="drawer-footer">
            <button type="button" class="btn btn-link text-decoration-none text-secondary px-0" wire:click="saveDraft" wire:loading.attr="disabled" wire:target="saveDraft">
                <span wire:loading.remove wire:target="saveDraft"><i class="bi bi-save me-1"></i> Save draft</span>
                <span wire:loading wire:target="saveDraft"><span class="spinner-border spinner-border-sm me-1"></span> Saving…</span>
            </button>

            <div class="ms-auto d-flex gap-2">
                @if ($step > 1)
                    <button type="button" class="btn btn-outline-secondary" wire:click="prevStep">
                        <i class="bi bi-arrow-left me-1"></i> Back
                    </button>
                @endif

                @if ($step < count($steps))
                    <button type="button" class="btn btn-brand" wire:click="nextStep" wire:loading.attr="disabled" wire:target="nextStep">
                        Continue <i class="bi bi-arrow-right ms-1"></i>
                    </button>
                @else
                    <button type="button" class="btn btn-accent" wire:click="submitApplication" wire:loading.attr="disabled" wire:target="submitApplication">
                        <span wire:loading.remove wire:target="submitApplication">
                            <i class="bi bi-send me-1"></i> {{ $mode === 'edit' ? 'Update & resubmit' : 'Submit EOI' }}
                        </span>
                        <span wire:loading wire:target="submitApplication"><span class="spinner-border spinner-border-sm me-1"></span> Submitting…</span>
                    </button>
                @endif
            </div>
        </div>
    </form>
</div>

@endif
</div>