<?php

use App\Models\EnterpriseDocument;
use App\Models\EnterpriseInvestment;
use App\Models\EnterpriseProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

/*
| Enterprise profile — follows the CAFI Co-Investment Facility Application Form, in 5 sections:
|   Company & contact · Legal status & markets · Employees & key people · Business & financials · Attachments & declaration
|   Own profile (eoi.submit): click "Edit" on a section, change it, Save. Documents upload straight away.
|   Staff (eoi.view-all / users.manage): read-only, opened from User management → /enterprise-profile/{user}
*/
new class extends Component
{
    use WithFileUploads;

    /** Yes/No questions stored as booleans */
    protected const BOOLEANS = ['women_owned_managed', 'youth_owned_managed', 'obfc_registered', 'foreign_registered'];

    #[Locked] public ?int $userId = null;
    #[Locked] public bool $readOnly = false;

    /** Section being edited (null = viewing) */
    public ?string $editing = null;

    public array $form = [];

    /** type => uploaded file */
    public array $uploads = [];

    /** Investments received — add / edit form (saved straight away, like documents) */
    public bool $invOpen = false;
    public ?int $invEditingId = null;
    public array $inv = [];
    public $invProof = null;

    public function mount(?int $userId = null): void
    {
        $me = auth()->user();

        if ($userId === null || $userId === $me->id) {
            if (! $me->can('eoi.submit')) {
                abort_unless($me->can('users.manage'), 403);
                $this->redirectRoute('users.index', navigate: false);
                return;
            }
            $this->userId = $me->id;

            // New profile: open the first section straight away so the questions are visible
            if (! EnterpriseProfile::where('user_id', $me->id)->exists()) {
                $this->edit('company');
            }
        } else {
            abort_unless($me->can('eoi.view-all') || $me->can('users.manage'), 403);
            $this->userId   = $userId;
            $this->readOnly = true;
        }
    }

    #[Computed] public function owner(): User
    {
        return User::findOrFail($this->userId);
    }

    #[Computed] public function profile(): EnterpriseProfile
    {
        return EnterpriseProfile::forUser($this->owner);
    }

    /* ---------------- Editing ---------------- */

    public function edit(string $section): void
    {
        abort_if($this->readOnly || ! isset(EnterpriseProfile::FIELDS[$section]), 403);

        $p    = $this->profile;
        $form = [];

        foreach (array_keys(EnterpriseProfile::FIELDS[$section]) as $field) {
            $v = $p->{$field};

            $form[$field] = match (true) {
                in_array($field, self::BOOLEANS, true) => $v === null ? '' : ($v ? 'yes' : 'no'),
                $field === 'declaration_agreed'        => (bool) $v,
                $v instanceof \DateTimeInterface       => $v->format('Y-m-d'),
                is_array($v)                           => $v,
                default                                => $v ?? '',
            };
        }

        // Shape the list / table answers
        if ($section === 'company') {
            $form['prior_cafi_support'] = (array) ($p->prior_cafi_support ?? []);
            $form['contact_name']  = $form['contact_name']  ?: auth()->user()->name;
            $form['contact_email'] = $form['contact_email'] ?: auth()->user()->email;
            $form['contact_phone'] = $form['contact_phone'] ?: (auth()->user()->phone ?? '');
        }
        if ($section === 'legal') {
            $form['markets'] = (array) ($p->markets ?? []);
        }
        if ($section === 'people') {
            if ($form['employee_band'] === '' && $p->employees !== null) {
                $form['employee_band'] = (int) $p->employees > 10 ? 'over_10' : '0_10';
            }
            $answers = (array) ($p->es_checklist ?? []);
            $form['es_checklist'] = collect(EnterpriseProfile::ES_CHECKLIST)->map(fn ($q, $k) => $answers[$k] ?? '')->all();
        }
        if ($section === 'business') {
            $form['round_investors'] = collect($p->round_investors ?? [])
                ->map(fn ($r) => ['name' => $r['name'] ?? '', 'country' => $r['country'] ?? '', 'ticket' => $r['ticket'] ?? ''])
                ->values()->all() ?: [['name' => '', 'country' => '', 'ticket' => '']];

            $form['grant_budget'] = collect($p->budgetRows())->map(fn ($r) => [
                'category' => (string) ($r['category'] ?? ''),
                'private'  => is_numeric($r['private'] ?? null) ? (string) round((float) $r['private']) : '',
                'grant'    => is_numeric($r['grant'] ?? null) ? (string) round((float) $r['grant']) : '',
            ])->all();

            $history = (array) ($p->investment_history ?? []);
            $form['investment_history'] = collect(EnterpriseProfile::FUNDING_SOURCES)
                ->map(fn ($l, $k) => ['amount' => $history[$k]['amount'] ?? '', 'date' => $history[$k]['date'] ?? ''])->all();
        }
        if ($section === 'attachments') {
            $form['declared_on']   = $form['declared_on']   ?: now()->toDateString();
            $form['declared_name'] = $form['declared_name'] ?: auth()->user()->name;
        }

        $this->form    = $form;
        $this->editing = $section;
        $this->resetValidation();
    }

    public function cancel(): void
    {
        $this->editing = null;
        $this->resetValidation();
    }

    public function addInvestor(): void
    {
        $this->form['round_investors'][] = ['name' => '', 'country' => '', 'ticket' => ''];
    }

    public function removeInvestor(int $i): void
    {
        unset($this->form['round_investors'][$i]);
        $this->form['round_investors'] = array_values($this->form['round_investors']) ?: [['name' => '', 'country' => '', 'ticket' => '']];
    }

    public function save(): void
    {
        abort_if($this->readOnly || ! $this->editing, 403);

        $section = $this->editing;

        $this->validate($this->sectionRules($section), [
            'form.declaration_agreed.accepted'  => 'Please confirm the declaration.',
            'form.prior_support_details.required' => 'Please explain the support you received.',
            'form.registration_no.required_if'  => 'Enter the registration number.',
            'form.foreign_country.required_if'  => 'Enter the country the firm is registered in.',
            'form.es_checklist.*.required'      => 'Please answer this question.',
        ], $this->fieldLabels());

        if ($section === 'business' && ! $this->budgetIsValid()) {
            return;
        }

        if ($section === 'company' && in_array('none', $this->form['prior_cafi_support'], true) && count($this->form['prior_cafi_support']) > 1) {
            $this->addError('form.prior_cafi_support', '"None" cannot be combined with other projects.');
            return;
        }

        $p = EnterpriseProfile::where('user_id', $this->userId)->first() ?? new EnterpriseProfile();
        $p->forceFill(['user_id' => $this->userId] + $this->values($section))->save();   // forceFill: a missing column errors instead of being skipped
        $p->load('documents')->refreshStatus();

        $this->editing = null;
        unset($this->profile);

        $this->dispatch('notify', type: 'success', message: EnterpriseProfile::SECTIONS[$section][0].' saved.');
    }

    /** What the investor(s) are putting in, from the form (tickets, otherwise the round amount) */
    protected function formInvestorAmount(): ?float
    {
        $tickets = collect($this->form['round_investors'] ?? [])->sum(fn ($r) => is_numeric($r['ticket'] ?? null) ? (float) $r['ticket'] : 0);
        $amount  = $tickets > 0 ? $tickets : (is_numeric($this->form['round_amount'] ?? null) ? (float) $this->form['round_amount'] : 0);

        return $amount > 0 ? $amount : null;
    }

    /** Grant utilisation budget: needed once the investor amount is known; the grant column must equal the matching grant */
    protected function budgetIsValid(): bool
    {
        $investor = $this->formInvestorAmount();
        if ($investor === null) {
            return true;
        }

        $totals = EnterpriseProfile::sumBudget($this->form['grant_budget'] ?? []);
        $grant  = (float) EnterpriseProfile::matchingGrant($investor);

        if ($totals['private'] + $totals['grant'] <= 0) {
            $this->addError('form.grant_budget', 'Fill in the grant utilisation budget — how the investor\'s money and the CAFI grant will be spent.');
            return false;
        }
        if (abs($totals['grant'] - $grant) >= 1) {
            $this->addError('form.grant_budget', 'The CAFI grant column adds up to M '.number_format($totals['grant']).' but your matching grant is M '.number_format($grant).'. Please make them equal.');
            return false;
        }

        return true;
    }

    /** Form → DB values for a section */
    protected function values(string $section): array
    {
        $f      = $this->form;
        $values = [];

        foreach (array_keys(EnterpriseProfile::FIELDS[$section]) as $field) {
            $v = $f[$field] ?? null;

            $values[$field] = match (true) {
                in_array($field, self::BOOLEANS, true) => $v === 'yes' ? true : ($v === 'no' ? false : null),
                $field === 'declaration_agreed'        => (bool) $v,
                $field === 'round_investors'           => collect($v ?? [])->filter(fn ($r) => filled($r['name'] ?? null))->map(fn ($r) => [
                                                              'name'    => trim($r['name']),
                                                              'country' => trim((string) ($r['country'] ?? '')),
                                                              'ticket'  => filled($r['ticket'] ?? null) ? (float) $r['ticket'] : null,
                                                          ])->values()->all(),
                $field === 'investment_history'        => collect($v ?? [])->filter(fn ($r) => filled($r['amount'] ?? null) || filled($r['date'] ?? null))
                                                              ->map(fn ($r) => ['amount' => filled($r['amount'] ?? null) ? (float) $r['amount'] : null, 'date' => $r['date'] ?: null])
                                                              ->all() ?: null,
                $field === 'grant_budget'              => collect($v ?? [])->filter(fn ($r) => filled($r['category'] ?? null))->map(fn ($r) => [
                                                              'category' => trim($r['category']),
                                                              'private'  => is_numeric($r['private'] ?? null) ? (float) $r['private'] : null,
                                                              'grant'    => is_numeric($r['grant'] ?? null) ? (float) $r['grant'] : null,
                                                          ])->values()->all() ?: null,
                $field === 'es_checklist'              => collect($v ?? [])->filter(fn ($a, $k) => isset(EnterpriseProfile::ES_CHECKLIST[$k]) && in_array($a, ['yes', 'no', 'na'], true))->all(),
                is_array($v)                           => array_values($v),
                default                                => $v === '' ? null : $v,
            };
        }

        // Clear follow-up answers that no longer apply
        if ($section === 'company' && (($values['prior_cafi_support'] ?? []) === [] || $values['prior_cafi_support'] === ['none'])) {
            $values['prior_support_details'] = null;
        }
        if ($section === 'legal') {
            if ($values['obfc_registered'] !== true) {
                $values['registration_no'] = null;
            }
            if ($values['foreign_registered'] !== true) {
                $values['foreign_country'] = null;
            }
        }

        return $values;
    }

    protected function sectionRules(string $section): array
    {
        $r     = fn (string $f) => EnterpriseProfile::FIELDS[$section][$f] ? 'required' : 'nullable';
        $pct   = ['nullable', 'numeric', 'min:0', 'max:100'];
        $count = ['required', 'integer', 'min:0', 'max:100000'];
        $money = fn (string $f) => [$r($f), 'numeric', 'min:0', 'max:9999999999999'];
        $yesNo = ['required', 'in:yes,no'];
        $words = fn (int $max) => function ($attribute, $value, $fail) use ($max) {
            if (str_word_count(strip_tags((string) $value)) > $max) {
                $fail("Please keep this to {$max} words or fewer.");
            }
        };

        return match ($section) {
            'company' => [
                'form.registered_name'       => ['required', 'string', 'max:255'],
                'form.short_name'            => ['nullable', 'string', 'max:120'],
                'form.year_established'      => ['required', 'integer', 'between:1900,'.date('Y')],
                'form.website'               => ['nullable', 'string', 'max:255'],
                'form.address'               => ['required', 'string', 'max:255'],
                'form.city'                  => ['required', 'string', 'max:80'],
                'form.district'              => ['required', Rule::in(EnterpriseProfile::DISTRICTS)],
                'form.sector'                => ['required', Rule::in(EnterpriseProfile::SECTORS)],
                'form.business_model'        => ['required', Rule::in(array_keys(EnterpriseProfile::BUSINESS_MODELS))],
                'form.women_owned_managed'   => $yesNo,
                'form.youth_owned_managed'   => $yesNo,
                'form.basotho_owned_pct'     => $pct,
                'form.women_owned_pct'       => $pct,
                'form.youth_owned_pct'       => $pct,
                'form.prior_cafi_support'    => ['required', 'array', 'min:1'],
                'form.prior_cafi_support.*'  => [Rule::in(array_keys(EnterpriseProfile::PRIOR_SUPPORT))],
                'form.prior_support_details' => [($this->form['prior_cafi_support'] ?? []) === ['none'] ? 'nullable' : 'required', 'string', 'max:2000'],
                'form.contact_name'          => ['required', 'string', 'max:255'],
                'form.contact_title'         => ['required', 'string', 'max:120'],
                'form.contact_phone'         => ['required', 'string', 'max:30'],
                'form.contact_email'         => ['required', 'email', 'max:255'],
            ],
            'legal' => [
                'form.obfc_registered'    => $yesNo,
                'form.legal_form'         => ['required', Rule::in(EnterpriseProfile::LEGAL_FORMS)],
                'form.registration_no'    => ['required_if:form.obfc_registered,yes', 'nullable', 'string', 'max:60'],
                'form.foreign_registered' => $yesNo,
                'form.foreign_country'    => ['required_if:form.foreign_registered,yes', 'nullable', 'string', 'max:80'],
                'form.markets'            => ['required', 'array', 'min:1'],
                'form.markets.*'          => [Rule::in(array_keys(EnterpriseProfile::MARKETS))],
            ],
            'people' => [
                'form.employees_prev_year' => $count,
                'form.employees'           => $count,
                'form.ft_male'             => $count,
                'form.ft_female'           => $count,
                'form.pt_male'             => $count,
                'form.pt_female'           => $count,
                'form.employee_band'       => ['required', Rule::in(array_keys(EnterpriseProfile::EMPLOYEE_BANDS))],
                'form.management'          => ['nullable', 'string', 'max:2000'],
                ...collect(EnterpriseProfile::ES_CHECKLIST)->mapWithKeys(fn ($q, $k) => [
                    "form.es_checklist.$k" => [$q[1] ? 'required' : 'nullable', in_array($k, EnterpriseProfile::ES_ALLOW_NA, true) ? 'in:yes,no,na' : 'in:yes,no'],
                ])->all(),
            ],
            'business' => [
                'form.activity'                    => ['required', 'string', $words(100)],
                'form.company_stage'               => ['required', Rule::in(array_keys(EnterpriseProfile::STAGES))],
                'form.customers'                   => ['nullable', 'string', 'max:2000'],
                'form.usp'                         => ['nullable', 'string', 'max:2000'],
                'form.marketing_strategy'          => ['nullable', 'string', 'max:2000'],
                'form.competition'                 => ['nullable', 'string', 'max:2000'],
                'form.annual_revenue'              => $money('annual_revenue'),
                'form.round_amount'                => [...$money('round_amount'), 'gt:0'],
                'form.round_investors'             => ['nullable', 'array'],
                'form.round_investors.*.name'      => ['nullable', 'string', 'max:255', 'required_with:form.round_investors.*.ticket,form.round_investors.*.country'],
                'form.round_investors.*.country'   => ['nullable', 'string', 'max:80'],
                'form.round_investors.*.ticket'    => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
                'form.participation'               => ['required', Rule::in(array_keys(EnterpriseProfile::PARTICIPATION))],
                'form.investor_type'               => ['nullable', Rule::in(EnterpriseProfile::INVESTOR_TYPES)],
                'form.transaction_status'          => ['nullable', Rule::in(array_keys(EnterpriseProfile::TRANSACTION_STATUSES))],
                'form.project_cost'                => [...$money('project_cost'), 'gt:0'],
                'form.investment_purpose'          => ['nullable', 'string', 'max:2000'],
                'form.investment_history.*.amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
                'form.investment_history.*.date'   => ['nullable', 'date', 'before_or_equal:today'],
                'form.grant_budget'                => ['nullable', 'array'],
                'form.grant_budget.*.private'      => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
                'form.grant_budget.*.grant'        => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
            ],
            'attachments' => [
                'form.declaration_agreed' => ['accepted'],
                'form.declared_name'      => ['required', 'string', 'max:255'],
                'form.declared_on'        => ['required', 'date', 'before_or_equal:today'],
            ],
        };
    }

    protected function fieldLabels(): array
    {
        return [
            'form.registered_name' => 'company official name', 'form.short_name' => 'company short name', 'form.year_established' => 'year of establishment',
            'form.address' => 'address', 'form.city' => 'city / town', 'form.business_model' => 'main business model',
            'form.women_owned_managed' => 'women owned / managed', 'form.youth_owned_managed' => 'youth owned / managed',
            'form.basotho_owned_pct' => 'shares held by Basotho', 'form.women_owned_pct' => 'shares held by women', 'form.youth_owned_pct' => 'shares held by youth',
            'form.prior_cafi_support' => 'CAFI support received', 'form.prior_support_details' => 'support received',
            'form.contact_name' => 'contact name', 'form.contact_title' => 'title', 'form.contact_phone' => 'phone number', 'form.contact_email' => 'e-mail',
            'form.obfc_registered' => 'OBFC registration', 'form.legal_form' => 'type of registration', 'form.registration_no' => 'registration number',
            'form.foreign_registered' => 'registration in another country', 'form.foreign_country' => 'country', 'form.markets' => 'current markets',
            'form.employees_prev_year' => 'total employees (previous year)', 'form.employees' => 'total employees (current year)',
            'form.ft_male' => 'full-time males', 'form.ft_female' => 'full-time females', 'form.pt_male' => 'part-time males', 'form.pt_female' => 'part-time females',
            'form.employee_band' => 'current number of employees',
            'form.management' => 'ownership & management summary',
            'form.activity' => 'company summary', 'form.company_stage' => 'stage of company development', 'form.customers' => 'products, customers & markets',
            'form.usp' => 'unique selling proposition', 'form.marketing_strategy' => 'marketing approach', 'form.competition' => 'competition',
            'form.annual_revenue' => 'annual revenue', 'form.round_amount' => 'current investment round', 'form.round_investors' => 'participating investors',
            'form.round_investors.*.name' => 'investor name', 'form.round_investors.*.country' => 'residence country', 'form.round_investors.*.ticket' => 'ticket size',
            'form.participation' => 'financing type', 'form.investor_type' => 'lead investor type', 'form.transaction_status' => 'status of the deal',
            'form.project_cost' => 'total project cost', 'form.investment_purpose' => 'what the money is for',
            'form.investment_history.*.amount' => 'amount', 'form.investment_history.*.date' => 'date of last investment',
            'form.grant_budget.*.private' => 'private investment', 'form.grant_budget.*.grant' => 'CAFI grant',
            'form.declared_name' => 'name', 'form.declared_on' => 'date',
        ];
    }

    /* ---------------- Investments received ---------------- */

    protected function blankInvestment(): array
    {
        return ['investor_name' => '', 'investor_type' => '', 'investor_country' => '', 'amount' => '', 'invested_on' => '',
                'instrument' => '', 'round_label' => '', 'notes' => ''];
    }

    public function newInvestment(): void
    {
        abort_if($this->readOnly, 403);

        $this->inv          = $this->blankInvestment();
        $this->invProof     = null;
        $this->invEditingId = null;
        $this->invOpen      = true;
        $this->resetValidation();
    }

    public function editInvestment(int $id): void
    {
        abort_if($this->readOnly, 403);

        $i = $this->ownInvestment($id);
        abort_unless($i->isEditableByEnterprise(), 403);

        $this->inv = [
            'investor_name' => $i->investor_name, 'investor_type' => $i->investor_type ?? '', 'investor_country' => $i->investor_country ?? '',
            'amount' => (string) round((float) $i->amount), 'invested_on' => $i->invested_on?->format('Y-m-d') ?? '',
            'instrument' => $i->instrument ?? '', 'round_label' => $i->round_label ?? '', 'notes' => $i->notes ?? '',
        ];
        $this->invProof     = null;
        $this->invEditingId = $i->id;
        $this->invOpen      = true;
        $this->resetValidation();
    }

    public function cancelInvestment(): void
    {
        $this->invOpen      = false;
        $this->invEditingId = null;
        $this->invProof     = null;
        $this->resetValidation();
    }

    public function saveInvestment(): void
    {
        abort_if($this->readOnly, 403);

        $this->validate([
            'inv.investor_name'    => ['required', 'string', 'max:255'],
            'inv.investor_type'    => ['nullable', Rule::in(EnterpriseProfile::INVESTOR_TYPES)],
            'inv.investor_country' => ['nullable', 'string', 'max:80'],
            'inv.amount'           => ['required', 'numeric', 'gt:0', 'max:9999999999999'],
            'inv.invested_on'      => ['required', 'date', 'before_or_equal:today'],
            'inv.instrument'       => ['nullable', Rule::in(array_keys(EnterpriseInvestment::INSTRUMENTS))],
            'inv.round_label'      => ['nullable', 'string', 'max:60'],
            'inv.notes'            => ['nullable', 'string', 'max:1000'],
            'invProof'             => ['nullable', 'file', 'mimes:'.EnterpriseInvestment::PROOF_MIMES, 'max:'.EnterpriseInvestment::MAX_KB],
        ], [
            'invProof.max'   => 'The proof must be '.(EnterpriseInvestment::MAX_KB / 1024).' MB or smaller.',
            'invProof.mimes' => 'Upload a PDF, image, Word or Excel file.',
        ], [
            'inv.investor_name' => 'investor name', 'inv.amount' => 'amount', 'inv.invested_on' => 'date of investment',
            'inv.investor_type' => 'investor type', 'inv.instrument' => 'instrument', 'inv.round_label' => 'round', 'invProof' => 'proof',
        ]);

        $p = $this->profile;
        if (! $p->exists) {
            $p->save();   // forUser() already set user_id
        }

        $i = $this->invEditingId ? $this->ownInvestment($this->invEditingId) : new EnterpriseInvestment([
            'enterprise_profile_id' => $p->id, 'source' => 'enterprise', 'recorded_by' => auth()->id(),
        ]);
        abort_if($i->exists && ! $i->isEditableByEnterprise(), 403);

        $values = collect($this->inv)->map(fn ($v) => $v === '' ? null : $v)->all();
        $i->fill($values + ['status' => EnterpriseInvestment::STATUS_DECLARED]);   // a changed declaration goes back for verification

        if ($this->invProof) {
            if ($i->proof_path) {
                \Illuminate\Support\Facades\Storage::disk(EnterpriseInvestment::DISK)->delete($i->proof_path);
            }
            $i->fill([
                'proof_path' => $this->invProof->store("enterprise-investments/{$p->id}", EnterpriseInvestment::DISK),
                'proof_name' => $this->invProof->getClientOriginalName(),
                'proof_size' => $this->invProof->getSize(),
            ]);
        }
        $i->save();

        $this->cancelInvestment();
        unset($this->profile);
        $this->dispatch('notify', type: 'success', message: 'Investment saved.');
    }

    public function deleteInvestment(int $id): void
    {
        abort_if($this->readOnly, 403);

        $i = $this->ownInvestment($id);
        abort_unless($i->isEditableByEnterprise(), 403);
        $i->delete();

        unset($this->profile);
        $this->dispatch('notify', type: 'success', message: 'Investment removed.');
    }

    protected function ownInvestment(int $id): EnterpriseInvestment
    {
        return EnterpriseInvestment::whereHas('profile', fn ($q) => $q->where('user_id', $this->userId))->findOrFail($id);
    }

    /* ---------------- Documents ---------------- */

    public function updatedUploads($file, string $type): void
    {
        abort_if($this->readOnly || ! isset(EnterpriseDocument::TYPES[$type]), 403);

        $mimes = EnterpriseDocument::mimesFor($type);

        $this->validate(
            ["uploads.$type" => ['file', "mimes:$mimes", 'max:'.EnterpriseDocument::MAX_KB]],
            [
                "uploads.$type.max"   => 'Files must be '.(EnterpriseDocument::MAX_KB / 1024).' MB or smaller.',
                "uploads.$type.mimes" => 'Upload one of: '.strtoupper(str_replace(',', ', ', $mimes)).'.',
            ],
        );

        $p = $this->profile;
        if (! $p->exists) {
            $p->save();   // forUser() already set user_id
        }

        DB::transaction(function () use ($p, $type, $file) {
            $p->documents()->where('type', $type)->get()->each->delete();   // also removes the old file
            $p->documents()->create([
                'type'          => $type,
                'path'          => $file->store("enterprise-documents/{$p->id}", EnterpriseDocument::DISK),
                'original_name' => $file->getClientOriginalName(),
                'mime'          => $file->getMimeType(),
                'size'          => $file->getSize(),
                'uploaded_by'   => auth()->id(),
            ]);
        });

        unset($this->uploads[$type], $this->profile);
        $this->profile->refreshStatus();
        $this->dispatch('notify', type: 'success', message: EnterpriseDocument::TYPES[$type][0].' uploaded.');
    }

    public function removeDocument(string $type): void
    {
        abort_if($this->readOnly, 403);

        $this->profile->documents()->where('type', $type)->get()->each->delete();
        unset($this->profile);
        $this->profile->refreshStatus();
        $this->dispatch('notify', type: 'success', message: (EnterpriseDocument::TYPES[$type][0] ?? 'Document').' removed.');
    }
};
?>

<div class="container-fluid p-3">
@if ($userId)
    @php
        $owner    = $this->owner;
        $p        = $this->profile;
        $P        = \App\Models\EnterpriseProfile::class;
        $D        = \App\Models\EnterpriseDocument::class;
        $status   = $p->exists ? $p->sectionStatus() : array_fill_keys(array_keys($P::SECTIONS), false);
        $done     = count(array_filter($status));
        $total    = count($P::SECTIONS);
        $percent  = (int) round($done / $total * 100);
        $canEdit  = ! $readOnly;
        $reqDocs  = $p->requiredDocumentTypes();
        $rate     = (float) config('cif.usd_to_lsl', 18.00);
        $lsl      = fn ($m) => $m === null || $m === '' ? '—' : 'M '.number_format((float) $m);
        $usd      = fn ($m) => $m === null || $m === '' ? '' : 'US$ '.number_format((float) $m / max($rate, 0.0001));
        $dash     = fn ($v) => filled($v) ? $v : '—';
        $yn       = fn ($v) => $v === null ? '—' : ($v ? 'Yes' : 'No');
        $answer   = fn ($a) => ['yes' => 'Yes', 'no' => 'No', 'na' => 'Not applicable'][$a ?? ''] ?? '—';

        // ⓘ hover help — texts live in EnterpriseProfile::HELP
        $tip = fn (string $key) => isset($P::HELP[$key])
            ? '<span class="tip" tabindex="0" role="note" aria-label="'.e($P::HELP[$key]).'" data-tip="'.e($P::HELP[$key]).'"><i class="bi bi-info-circle"></i></span>'
            : '';

        $investments = $p->exists ? $p->investments : collect();
        $I = \App\Models\EnterpriseInvestment::class;

        // Which document rows to show (conditional ones follow the registration answers)
        $docVisible = fn (array $t) => match ($t[3]) {
            'registered'     => $p->obfc_registered !== false,
            'not_registered' => $p->obfc_registered === false,
            'foreign'        => $p->foreign_registered === true,
            default          => true,
        };
    @endphp

    @if ($readOnly)
        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <a href="{{ route('users.index') }}" class="back-link"><i class="bi bi-arrow-left"></i> Back to users</a>
            <span class="pill pill-muted ms-auto"><i class="bi bi-eye"></i> Read-only view</span>
        </div>
    @endif

    {{-- ============================ HEADER ============================ --}}
    <div class="hero-banner mb-4">
        <div class="hero-glow"></div>
        <div class="d-flex flex-wrap align-items-center gap-3">
            <div class="avatar-sq">{{ $p->initial }}</div>
            <div class="me-auto">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                    @if ($p->registration_no) <span class="chip chip-dark">{{ $p->registration_no }}</span> @endif
                    <span class="badge {{ $percent === 100 ? 'status-approved' : ($p->exists ? 'status-in-review' : 'status-draft') }}">
                        {{ $percent === 100 ? 'Profile complete' : ($p->exists ? 'In progress' : 'Not started') }}
                    </span>
                </div>
                <h2 class="hero-title mb-1">{{ $p->registered_name ?: ($readOnly ? ($owner->organisation_name ?? $owner->name) : 'Your enterprise profile') }}</h2>
                <div class="hero-meta">
                    @if ($readOnly) <span><i class="bi bi-person"></i> {{ $owner->name }} · {{ $owner->email }}</span> @endif
                    @if ($p->district) <span><i class="bi bi-geo-alt"></i> {{ collect([$p->city, $p->district])->filter()->implode(', ') }}</span> @endif
                    @if ($p->sector) <span><i class="bi bi-tag"></i> {{ $p->sector }}</span> @endif
                    @if ($p->updated_at) <span><i class="bi bi-clock-history"></i> Updated {{ $p->updated_at->diffForHumans() }}</span> @endif
                </div>
            </div>
            <div class="text-white" style="min-width: 200px;">
                <div class="d-flex justify-content-between small mb-1"><span>Completeness</span><strong>{{ $done }} / {{ $total }}</strong></div>
                <div class="progress" style="height: 8px; background: rgba(255,255,255,.2);">
                    <div class="progress-bar bg-accent" style="width: {{ $percent }}%;"></div>
                </div>
            </div>
        </div>
    </div>

    @unless ($readOnly)
        <div class="callout small mb-4">
            <div class="fw-semibold text-navy mb-1"><i class="bi bi-info-circle me-1"></i> Co-Investment Facility application — disclaimers</div>
            @foreach ($P::DISCLAIMERS as $text) <p class="mb-1">{{ $text }}</p> @endforeach
        </div>
    @endunless

    <div class="row g-4">
        <div class="col-xl-8">
            @foreach ($P::SECTIONS as $key => [$title, $icon, $desc])
                @php $isEditing = $editing === $key; @endphp
                <section class="section-card" id="sec-{{ $key }}" wire:key="sec-{{ $key }}">
                    <div class="section-card-head">
                        <span class="section-no">{{ $loop->iteration }}</span>
                        <div class="me-auto">
                            <h5>{{ $title }} {!! $tip('section.'.$key) !!}</h5>
                            <div class="small text-muted">{{ $desc }}</div>
                        </div>
                        <span class="pill {{ $status[$key] ? 'pill-ok' : 'pill-warn' }}"><i class="bi {{ $status[$key] ? 'bi-check-lg' : 'bi-exclamation-circle' }}"></i> {{ $status[$key] ? 'Complete' : 'Incomplete' }}</span>
                        @if ($canEdit && ! $isEditing)
                            <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="edit('{{ $key }}')" @disabled($editing)><i class="bi bi-pencil me-1"></i>{{ $key === 'attachments' ? 'Declaration' : 'Edit' }}</button>
                        @endif
                    </div>

                    {{-- What is still needed in this section --}}
                    @php
                        $missingQ = $p->missingFields($key);
                        $missingD = $p->missingSectionDocuments($key);
                    @endphp
                    @if (! $isEditing && ($missingQ || $missingD))
                        <div class="callout callout-warning small mb-3">
                            <div class="d-flex flex-wrap align-items-start gap-2">
                                <div class="flex-grow-1">
                                    @if ($missingQ)
                                        <div><strong>{{ count($missingQ) }} {{ \Illuminate\Support\Str::plural('question', count($missingQ)) }} to answer:</strong>
                                            {{ collect($missingQ)->map(fn ($f) => $P::LABELS[$f] ?? $f)->implode(', ') }}</div>
                                    @endif
                                    @if ($missingD)
                                        <div class="{{ $missingQ ? 'mt-1' : '' }}"><strong>{{ count($missingD) }} {{ \Illuminate\Support\Str::plural('document', count($missingD)) }} to upload:</strong>
                                            {{ collect($missingD)->map(fn ($t) => $D::TYPES[$t][0])->implode(', ') }}</div>
                                    @endif
                                </div>
                                @if ($canEdit && $missingQ)
                                    <button type="button" class="btn btn-sm btn-brand" wire:click="edit('{{ $key }}')" @disabled($editing)><i class="bi bi-pencil-square me-1"></i> Answer {{ count($missingQ) === 1 ? 'question' : 'questions' }}</button>
                                @endif
                            </div>
                        </div>
                    @endif

                    {{-- ===================== 1 · COMPANY & CONTACT ===================== --}}
                    @if ($key === 'company')
                        @if ($isEditing)
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label small fw-semibold">Have you received any grant support from any of these CAFI-funded projects? {!! $tip('prior_cafi_support') !!} <span class="req">*</span></label>
                                    <div class="d-flex flex-wrap gap-3">
                                        @foreach ($P::PRIOR_SUPPORT as $k => $lbl)
                                            <label class="form-check mb-0"><input type="checkbox" class="form-check-input" value="{{ $k }}" wire:model.live="form.prior_cafi_support"> <span class="form-check-label small">{{ $lbl }}</span></label>
                                        @endforeach
                                    </div>
                                    @error('form.prior_cafi_support') <div class="text-danger small">{{ $message }}</div> @enderror
                                </div>
                                @if (($form['prior_cafi_support'] ?? []) !== ['none'] && ($form['prior_cafi_support'] ?? []) !== [])
                                    <div class="col-12"><label class="form-label small fw-semibold">Please explain the support received {!! $tip('prior_support_details') !!} <span class="req">*</span></label>
                                        <textarea rows="2" class="form-control @error('form.prior_support_details') is-invalid @enderror" wire:model="form.prior_support_details"></textarea>
                                        @error('form.prior_support_details') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                                @endif

                                <div class="col-md-8"><label class="form-label small fw-semibold">Company official name {!! $tip('registered_name') !!} <span class="req">*</span></label>
                                    <input type="text" class="form-control @error('form.registered_name') is-invalid @enderror" wire:model="form.registered_name">
                                    @error('form.registered_name') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                                <div class="col-md-4"><label class="form-label small fw-semibold">Company short name {!! $tip('short_name') !!}</label>
                                    <input type="text" class="form-control @error('form.short_name') is-invalid @enderror" wire:model="form.short_name">
                                    @error('form.short_name') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                                <div class="col-md-4"><label class="form-label small fw-semibold">Year of establishment {!! $tip('year_established') !!} <span class="req">*</span></label>
                                    <input type="number" min="1900" max="{{ date('Y') }}" class="form-control @error('form.year_established') is-invalid @enderror" wire:model="form.year_established">
                                    @error('form.year_established') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                                <div class="col-md-8"><label class="form-label small fw-semibold">Website {!! $tip('website') !!}</label>
                                    <input type="text" class="form-control @error('form.website') is-invalid @enderror" wire:model="form.website" placeholder="https://">
                                    @error('form.website') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                                <div class="col-md-8"><label class="form-label small fw-semibold">Address {!! $tip('address') !!} <span class="req">*</span></label>
                                    <input type="text" class="form-control @error('form.address') is-invalid @enderror" wire:model="form.address">
                                    @error('form.address') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                                <div class="col-md-4"><label class="form-label small fw-semibold">City / town {!! $tip('city') !!} <span class="req">*</span></label>
                                    <input type="text" class="form-control @error('form.city') is-invalid @enderror" wire:model="form.city">
                                    @error('form.city') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                                @foreach (['district' => ['District', $P::DISTRICTS], 'sector' => ['Sector', $P::SECTORS]] as $f => [$lbl, $opts])
                                    <div class="col-md-6"><label class="form-label small fw-semibold">{{ $lbl }} {!! $tip($f) !!} <span class="req">*</span></label>
                                        <select class="form-select @error('form.'.$f) is-invalid @enderror" wire:model="form.{{ $f }}">
                                            <option value="">Select…</option>@foreach ($opts as $o)<option>{{ $o }}</option>@endforeach
                                            @if (filled($form[$f] ?? null) && ! in_array($form[$f], $opts, true))<option>{{ $form[$f] }}</option>@endif
                                        </select>
                                        @error('form.'.$f) <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                                @endforeach

                                <div class="col-12"><label class="form-label small fw-semibold">Main business model {!! $tip('business_model') !!} <span class="req">*</span></label>
                                    <div class="d-flex flex-wrap gap-2">
                                        @foreach ($P::BUSINESS_MODELS as $k => $lbl)
                                            <input type="radio" class="btn-check" id="bm-{{ $k }}" value="{{ $k }}" wire:model="form.business_model">
                                            <label class="btn btn-sm btn-outline-secondary" for="bm-{{ $k }}">{{ $lbl }}</label>
                                        @endforeach
                                    </div>
                                    @error('form.business_model') <div class="text-danger small">{{ $message }}</div> @enderror</div>

                                <div class="col-12"><label class="form-label small fw-semibold mb-1">Role of women and youth at the firm {!! $tip('women_youth_role') !!} <span class="req">*</span></label></div>
                                @foreach (['women_owned_managed' => 'Women owned / managed', 'youth_owned_managed' => 'Youth (under 35) owned or managed'] as $f => $lbl)
                                    <div class="col-md-6 d-flex align-items-center justify-content-between gap-2">
                                        <span class="small">{{ $lbl }} {!! $tip($f) !!}</span>
                                        <div class="btn-group btn-group-sm">
                                            @foreach (['yes' => 'Yes', 'no' => 'No'] as $v => $vl)
                                                <input type="radio" class="btn-check" id="{{ $f }}-{{ $v }}" value="{{ $v }}" wire:model="form.{{ $f }}">
                                                <label class="btn btn-outline-secondary" for="{{ $f }}-{{ $v }}">{{ $vl }}</label>
                                            @endforeach
                                        </div>
                                    </div>
                                    @error('form.'.$f) <div class="col-12 text-danger small mt-0">{{ $message }}</div> @enderror
                                @endforeach

                                @foreach (['basotho_owned_pct' => '% shares held by Basotho', 'women_owned_pct' => '% shares held by women', 'youth_owned_pct' => '% shares held by youth'] as $f => $lbl)
                                    <div class="col-md-4"><label class="form-label small fw-semibold">{{ $lbl }} {!! $tip($f) !!}</label>
                                        <input type="number" min="0" max="100" class="form-control @error('form.'.$f) is-invalid @enderror" wire:model="form.{{ $f }}">
                                        @error('form.'.$f) <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                                @endforeach
                                <div class="col-12 form-text mt-1">Share ownership: the percentage of the company's shares held by Lesotho citizens, by women and by young people (35 or under). A shareholder can count in more than one group.</div>

                                <div class="col-12 border-top pt-3"><div class="fw-semibold text-navy small">Applicant contact information {!! $tip('contact') !!}</div></div>
                                @foreach (['contact_name' => ['Contact name', 'text'], 'contact_title' => ['Title', 'text'], 'contact_phone' => ['Phone number', 'tel'], 'contact_email' => ['E-mail', 'email']] as $f => [$lbl, $type])
                                    <div class="col-md-6"><label class="form-label small fw-semibold">{{ $lbl }} {!! $tip($f) !!} <span class="req">*</span></label>
                                        <input type="{{ $type }}" class="form-control @error('form.'.$f) is-invalid @enderror" wire:model="form.{{ $f }}">
                                        @error('form.'.$f) <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                                @endforeach
                            </div>
                        @else
                            <div class="detail-grid">
                                <div><dt>Company official name {!! $tip('registered_name') !!}</dt><dd>{{ $dash($p->registered_name) }}</dd></div>
                                <div><dt>Short name {!! $tip('short_name') !!}</dt><dd>{{ $dash($p->short_name) }}</dd></div>
                                <div><dt>Year of establishment {!! $tip('year_established') !!}</dt><dd>{{ $dash($p->year_established) }}</dd></div>
                                <div><dt>Website {!! $tip('website') !!}</dt><dd>{{ $dash($p->website) }}</dd></div>
                                <div><dt>Address {!! $tip('address') !!}</dt><dd>{{ collect([$p->address, $p->city, $p->district])->filter()->implode(', ') ?: '—' }}</dd></div>
                                <div><dt>Sector {!! $tip('sector') !!}</dt><dd>{{ $dash($p->sector) }}</dd></div>
                                <div><dt>Main business model {!! $tip('business_model') !!}</dt><dd>{{ $P::BUSINESS_MODELS[$p->business_model ?? ''] ?? '—' }}</dd></div>
                                <div><dt>Women owned / managed {!! $tip('women_owned_managed') !!}</dt><dd>{{ $yn($p->women_owned_managed) }}</dd></div>
                                <div><dt>Youth owned / managed {!! $tip('youth_owned_managed') !!}</dt><dd>{{ $yn($p->youth_owned_managed) }}</dd></div>
                                <div><dt>Shareholding {!! $tip('basotho_owned_pct') !!}</dt><dd>{{ $p->ownershipLine() ?? '—' }}</dd></div>
                                <div><dt>Prior CAFI support {!! $tip('prior_cafi_support') !!}</dt><dd>{{ $p->labels($P::PRIOR_SUPPORT, $p->prior_cafi_support) ?? '—' }}</dd></div>
                            </div>
                            @if ($p->prior_support_details) <div class="quote mt-3 mb-0">{{ $p->prior_support_details }}<div class="quote-meta">Support received</div></div> @endif
                            <dt class="detail-label mt-3 mb-2">Applicant contact {!! $tip('contact') !!}</dt>
                            <div class="detail-grid">
                                <div><dt>Name {!! $tip('contact_name') !!}</dt><dd>{{ $dash($p->contact_name) }}</dd></div>
                                <div><dt>Title {!! $tip('contact_title') !!}</dt><dd>{{ $dash($p->contact_title) }}</dd></div>
                                <div><dt>Phone {!! $tip('contact_phone') !!}</dt><dd>{{ $dash($p->contact_phone) }}</dd></div>
                                <div><dt>E-mail {!! $tip('contact_email') !!}</dt><dd>{{ $dash($p->contact_email) }}</dd></div>
                            </div>
                        @endif

                    {{-- ===================== 2 · LEGAL STATUS & MARKETS ===================== --}}
                    @elseif ($key === 'legal')
                        @if ($isEditing)
                            <div class="row g-3">
                                <div class="col-md-6"><label class="form-label small fw-semibold">Is your business registered at the OBFC (Ministry of Trade, Industry and Business Development)? {!! $tip('obfc_registered') !!} <span class="req">*</span></label>
                                    <div class="btn-group btn-group-sm d-flex" style="max-width: 200px">
                                        @foreach (['yes' => 'Yes', 'no' => 'No'] as $v => $vl)
                                            <input type="radio" class="btn-check" id="obfc-{{ $v }}" value="{{ $v }}" wire:model.live="form.obfc_registered">
                                            <label class="btn btn-outline-secondary" for="obfc-{{ $v }}">{{ $vl }}</label>
                                        @endforeach
                                    </div>
                                    @error('form.obfc_registered') <div class="text-danger small">{{ $message }}</div> @enderror</div>
                                <div class="col-md-6"><label class="form-label small fw-semibold">Type of registration {!! $tip('legal_form') !!} <span class="req">*</span></label>
                                    <select class="form-select @error('form.legal_form') is-invalid @enderror" wire:model="form.legal_form">
                                        <option value="">Select…</option>@foreach ($P::LEGAL_FORMS as $o)<option>{{ $o }}</option>@endforeach
                                        @if (filled($form['legal_form'] ?? null) && ! in_array($form['legal_form'], $P::LEGAL_FORMS, true))<option>{{ $form['legal_form'] }}</option>@endif
                                    </select>
                                    @error('form.legal_form') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                                @if (($form['obfc_registered'] ?? '') === 'yes')
                                    <div class="col-md-6"><label class="form-label small fw-semibold">Registration number {!! $tip('registration_no') !!} <span class="req">*</span></label>
                                        <input type="text" class="form-control @error('form.registration_no') is-invalid @enderror" wire:model="form.registration_no">
                                        @error('form.registration_no') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                                @elseif (($form['obfc_registered'] ?? '') === 'no')
                                    <div class="col-12"><div class="callout callout-warning small mb-0"><i class="bi bi-exclamation-triangle me-1"></i> {{ $P::REGISTRATION_NOTE }} Upload a signed undertaking to register below.</div></div>
                                @endif

                                <div class="col-md-6"><label class="form-label small fw-semibold">Are you currently registered in another country? {!! $tip('foreign_registered') !!} <span class="req">*</span></label>
                                    <div class="btn-group btn-group-sm d-flex" style="max-width: 200px">
                                        @foreach (['yes' => 'Yes', 'no' => 'No'] as $v => $vl)
                                            <input type="radio" class="btn-check" id="foreign-{{ $v }}" value="{{ $v }}" wire:model.live="form.foreign_registered">
                                            <label class="btn btn-outline-secondary" for="foreign-{{ $v }}">{{ $vl }}</label>
                                        @endforeach
                                    </div>
                                    @error('form.foreign_registered') <div class="text-danger small">{{ $message }}</div> @enderror</div>
                                @if (($form['foreign_registered'] ?? '') === 'yes')
                                    <div class="col-md-6"><label class="form-label small fw-semibold">Country the firm is registered in {!! $tip('foreign_country') !!} <span class="req">*</span></label>
                                        <input type="text" class="form-control @error('form.foreign_country') is-invalid @enderror" wire:model="form.foreign_country">
                                        @error('form.foreign_country') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                                @endif

                                <div class="col-12 border-top pt-3"><label class="form-label small fw-semibold">What are your current markets? (choose all that apply) {!! $tip('markets') !!} <span class="req">*</span></label>
                                    <div class="d-flex flex-wrap gap-3">
                                        @foreach ($P::MARKETS as $k => $lbl)
                                            <label class="form-check mb-0"><input type="checkbox" class="form-check-input" value="{{ $k }}" wire:model="form.markets"> <span class="form-check-label small">{{ $lbl }}</span></label>
                                        @endforeach
                                    </div>
                                    @error('form.markets') <div class="text-danger small">{{ $message }}</div> @enderror</div>
                            </div>
                        @else
                            <div class="detail-grid">
                                <div><dt>Registered at OBFC {!! $tip('obfc_registered') !!}</dt><dd>{{ $yn($p->obfc_registered) }}</dd></div>
                                <div><dt>Type of registration {!! $tip('legal_form') !!}</dt><dd>{{ $dash($p->legal_form) }}</dd></div>
                                <div><dt>Registration number {!! $tip('registration_no') !!}</dt><dd>{{ $dash($p->registration_no) }}</dd></div>
                                <div><dt>Registered in another country {!! $tip('foreign_registered') !!}</dt><dd>{{ $p->foreign_registered ? 'Yes — '.($p->foreign_country ?: 'country not given') : $yn($p->foreign_registered) }}</dd></div>
                                <div><dt>Current markets {!! $tip('markets') !!}</dt><dd>{{ $p->labels($P::MARKETS, $p->markets) ?? '—' }}</dd></div>
                            </div>
                            @if ($p->obfc_registered === false)
                                <div class="callout callout-warning small mt-3 mb-0"><i class="bi bi-exclamation-triangle me-1"></i> {{ $P::REGISTRATION_NOTE }}</div>
                            @endif
                        @endif

                    {{-- ===================== 3 · EMPLOYEES & KEY PEOPLE ===================== --}}
                    @elseif ($key === 'people')
                        @if ($isEditing)
                            @php $sum = collect(['ft_male', 'ft_female', 'pt_male', 'pt_female'])->sum(fn ($f) => (int) ($form[$f] ?? 0)); @endphp
                            <div class="row g-3">
                                <div class="col-12"><div class="fw-semibold text-navy small">Company employment history <span class="req">*</span></div></div>
                                <div class="col-md-6"><label class="form-label small fw-semibold">Total employees — previous year {!! $tip('employees_prev_year') !!}</label>
                                    <input type="number" min="0" class="form-control @error('form.employees_prev_year') is-invalid @enderror" wire:model="form.employees_prev_year">
                                    @error('form.employees_prev_year') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                                <div class="col-md-6"><label class="form-label small fw-semibold">Total employees — current year {!! $tip('employees') !!}</label>
                                    <input type="number" min="0" class="form-control @error('form.employees') is-invalid @enderror" wire:model.blur="form.employees">
                                    @error('form.employees') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>

                                <div class="col-12"><div class="fw-semibold text-navy small">Details of current employees {!! $tip('employee_details') !!} <span class="req">*</span></div></div>
                                @foreach (['ft_male' => 'Full-time males', 'ft_female' => 'Full-time females', 'pt_male' => 'Part-time males', 'pt_female' => 'Part-time females'] as $f => $lbl)
                                    <div class="col-6 col-md-3"><label class="form-label small fw-semibold">{{ $lbl }} {!! $tip($f) !!}</label>
                                        <input type="number" min="0" class="form-control @error('form.'.$f) is-invalid @enderror" wire:model.blur="form.{{ $f }}">
                                        @error('form.'.$f) <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                                @endforeach
                                @if (filled($form['employees'] ?? null) && $sum !== (int) $form['employees'])
                                    <div class="col-12 form-text mt-1 text-warning-emphasis">The details add up to {{ $sum }}; the current-year total is {{ (int) $form['employees'] }}.</div>
                                @endif

                                <div class="col-12"><label class="form-label small fw-semibold">What is the current number of your employees? {!! $tip('employee_band') !!} <span class="req">*</span></label>
                                    <div class="btn-group btn-group-sm d-flex" style="max-width: 220px">
                                        @foreach ($P::EMPLOYEE_BANDS as $v => $vl)
                                            <input type="radio" class="btn-check" id="band-{{ $v }}" value="{{ $v }}" wire:model="form.employee_band">
                                            <label class="btn btn-outline-secondary" for="band-{{ $v }}">{{ $vl }}</label>
                                        @endforeach
                                    </div>
                                    @error('form.employee_band') <div class="text-danger small">{{ $message }}</div> @enderror</div>


                                <div class="col-12 border-top pt-3"><div class="fw-semibold text-navy small">Environmental and social checklist</div></div>
                                @foreach ($P::ES_CHECKLIST as $k => [$q, $req])
                                    <div class="col-12 d-flex flex-wrap align-items-center justify-content-between gap-2 py-1 border-bottom" wire:key="es-{{ $k }}">
                                        <span class="small flex-grow-1" style="min-width: 240px">{{ $q }} @if ($req) <span class="req">*</span> @endif</span>
                                        <div class="btn-group btn-group-sm">
                                            @foreach (['yes' => 'Yes', 'no' => 'No'] + (in_array($k, $P::ES_ALLOW_NA, true) ? ['na' => 'N/A'] : []) as $v => $vl)
                                                <input type="radio" class="btn-check" id="es-{{ $k }}-{{ $v }}" value="{{ $v }}" wire:model="form.es_checklist.{{ $k }}">
                                                <label class="btn btn-outline-secondary" for="es-{{ $k }}-{{ $v }}">{{ $vl }}</label>
                                            @endforeach
                                        </div>
                                        @error('form.es_checklist.'.$k) <div class="w-100 text-danger small">{{ $message }}</div> @enderror
                                    </div>
                                @endforeach
                                <div class="col-12 form-text mt-1">{{ $P::ES_NOTE }}</div>

                                <div class="col-12 border-top pt-3"><label class="form-label small fw-semibold">Ownership &amp; management summary {!! $tip('management') !!}</label>
                                    <textarea rows="3" class="form-control @error('form.management') is-invalid @enderror" wire:model="form.management" placeholder="Shareholders (with %) and the key people running the business"></textarea>
                                    @error('form.management') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                            </div>
                        @else
                            @php $breakdown = $p->employeeBreakdownTotal(); $gaps = $p->esGaps(); @endphp
                            <div class="table-responsive">
                                <table class="table table-modern table-sm align-middle mb-2">
                                    <thead><tr><th></th><th class="text-end">Previous year</th><th class="text-end">Current year</th></tr></thead>
                                    <tbody><tr><td class="fw-semibold text-navy">Total employees</td><td class="text-end">{{ $p->employees_prev_year ?? '—' }}</td><td class="text-end">{{ $p->employees ?? '—' }}</td></tr></tbody>
                                </table>
                                <table class="table table-modern table-sm align-middle mb-0">
                                    <thead><tr><th class="text-end">FT males</th><th class="text-end">FT females</th><th class="text-end">PT males</th><th class="text-end">PT females</th></tr></thead>
                                    <tbody><tr>@foreach (['ft_male', 'ft_female', 'pt_male', 'pt_female'] as $f)<td class="text-end">{{ $p->{$f} ?? '—' }}</td>@endforeach</tr></tbody>
                                </table>
                            </div>
                            @if ($breakdown !== null && $p->employees !== null && $breakdown !== (int) $p->employees)
                                <div class="small text-warning-emphasis mt-1"><i class="bi bi-exclamation-circle me-1"></i> The details add up to {{ $breakdown }}; the current-year total is {{ $p->employees }}.</div>
                            @endif
                            <div class="small mt-2"><span class="text-muted">Current number of employees:</span> <strong>{{ $p->employeeBand() ?? '—' }}</strong></div>


                            <dt class="detail-label mt-3 mb-2">Environmental and social checklist {!! $tip('es_checklist') !!}</dt>
                            <ul class="checklist mb-1">
                                @foreach ($P::ES_CHECKLIST as $k => [$q])
                                    @php $a = $p->es_checklist[$k] ?? null; $gap = in_array($k, $gaps, true); @endphp
                                    <li class="{{ $a === null ? 'is-pending' : ($gap ? 'is-fail' : 'is-done') }}">
                                        <i class="bi {{ $a === null ? 'bi-circle' : ($gap ? 'bi-exclamation-circle-fill' : 'bi-check-circle-fill') }}"></i>
                                        <div class="flex-grow-1">{{ $q }}</div>
                                        <strong class="small">{{ $answer($a) }}</strong>
                                    </li>
                                @endforeach
                            </ul>
                            @if ($gaps) <div class="small text-muted">{{ $P::ES_NOTE }}</div> @endif

                            @if ($p->management)
                                <dt class="detail-label mt-3">Ownership &amp; management {!! $tip('management') !!}</dt>
                                <p class="small mb-0" style="white-space: pre-line">{{ $p->management }}</p>
                            @endif
                        @endif

                    {{-- ===================== 4 · BUSINESS & FINANCIALS ===================== --}}
                    @elseif ($key === 'business')
                        @if ($isEditing)
                            @php $words = str_word_count(strip_tags((string) ($form['activity'] ?? ''))); @endphp
                            <div class="row g-3">
                                <div class="col-12"><label class="form-label small fw-semibold">Company summary (max. 100 words) {!! $tip('activity') !!} <span class="req">*</span></label>
                                    <textarea rows="4" class="form-control @error('form.activity') is-invalid @enderror" wire:model.live.debounce.600ms="form.activity"></textarea>
                                    <div class="form-text {{ $words > 100 ? 'text-danger' : '' }}">{{ $words }} / 100 words</div>
                                    @error('form.activity') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                                <div class="col-12"><label class="form-label small fw-semibold">Which stage of the business lifecycle is your startup / SME in? {!! $tip('company_stage') !!} <span class="req">*</span></label>
                                    <div class="d-flex flex-wrap gap-2">
                                        @foreach ($P::STAGES as $k => $lbl)
                                            <input type="radio" class="btn-check" id="stage-{{ $k }}" value="{{ $k }}" wire:model="form.company_stage">
                                            <label class="btn btn-sm btn-outline-secondary" for="stage-{{ $k }}">{{ $lbl }}</label>
                                        @endforeach
                                    </div>
                                    @error('form.company_stage') <div class="text-danger small">{{ $message }}</div> @enderror</div>
                                <div class="col-12"><label class="form-label small fw-semibold">Products, customers &amp; markets {!! $tip('customers') !!}</label>
                                    <textarea rows="3" class="form-control @error('form.customers') is-invalid @enderror" wire:model="form.customers" placeholder="What you sell, who buys it and where"></textarea>
                                    @error('form.customers') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                                @foreach ([
                                    'usp'                => ['Unique selling proposition', 'Why customers choose you over the alternatives'],
                                    'marketing_strategy' => ['Marketing approach', 'How you reach, sell to and keep customers'],
                                    'competition'        => ['Competition', 'Who you compete with and how you compare'],
                                ] as $f => [$lbl, $ph])
                                    <div class="col-12"><label class="form-label small fw-semibold">{{ $lbl }} {!! $tip($f) !!}</label>
                                        <textarea rows="3" class="form-control @error('form.'.$f) is-invalid @enderror" wire:model="form.{{ $f }}" placeholder="{{ $ph }}"></textarea>
                                        @error('form.'.$f) <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                                @endforeach

                                <div class="col-12 border-top pt-3"><div class="fw-semibold text-navy small">Financials</div></div>
                                @foreach (['annual_revenue' => 'Annual revenues for the past year', 'round_amount' => 'Current investment round fund'] as $f => $lbl)
                                    <div class="col-md-6"><label class="form-label small fw-semibold">{{ $lbl }} (M) {!! $tip($f) !!} <span class="req">*</span></label>
                                        <input type="number" min="0" step="1000" class="form-control @error('form.'.$f) is-invalid @enderror" wire:model.blur="form.{{ $f }}">
                                        <div class="form-text">{{ $usd($form[$f] ?? null) }}</div>
                                        @error('form.'.$f) <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                                @endforeach

                                <div class="col-12"><label class="form-label small fw-semibold">Participating investors and their committed ticket size (LSL) in the current round {!! $tip('round_investors') !!}</label>
                                    @foreach ($form['round_investors'] ?? [] as $i => $row)
                                        <div class="row g-2 mb-2" wire:key="inv-{{ $i }}">
                                            <div class="col-md-5"><input type="text" class="form-control form-control-sm @error('form.round_investors.'.$i.'.name') is-invalid @enderror" wire:model="form.round_investors.{{ $i }}.name" placeholder="Investor name">
                                                @error('form.round_investors.'.$i.'.name') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                                            <div class="col-md-3"><input type="text" class="form-control form-control-sm" wire:model="form.round_investors.{{ $i }}.country" placeholder="Residence country"></div>
                                            <div class="col-md-3"><div class="input-group input-group-sm"><span class="input-group-text">M</span>
                                                <input type="number" min="0" step="1000" class="form-control @error('form.round_investors.'.$i.'.ticket') is-invalid @enderror" wire:model.blur="form.round_investors.{{ $i }}.ticket" placeholder="Ticket size"></div>
                                                <div class="form-text">{{ $usd($row['ticket'] ?? null) }}</div></div>
                                            <div class="col-md-1 text-end"><button type="button" class="btn btn-icon btn-icon-danger" title="Remove" wire:click="removeInvestor({{ $i }})"><i class="bi bi-trash"></i></button></div>
                                        </div>
                                    @endforeach
                                    <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="addInvestor"><i class="bi bi-plus-lg me-1"></i> Add investor</button>
                                    @error('form.round_investors') <div class="text-danger small">{{ $message }}</div> @enderror</div>

                                <div class="col-md-6"><label class="form-label small fw-semibold">Investment round financing type {!! $tip('participation') !!} <span class="req">*</span></label>
                                    <select class="form-select @error('form.participation') is-invalid @enderror" wire:model="form.participation">
                                        <option value="">Select…</option>@foreach ($P::PARTICIPATION as $k => $o)<option value="{{ $k }}">{{ $o }}</option>@endforeach</select>
                                    @error('form.participation') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                                <div class="col-md-6"><label class="form-label small fw-semibold">Status of the deal {!! $tip('transaction_status') !!}</label>
                                    <select class="form-select @error('form.transaction_status') is-invalid @enderror" wire:model="form.transaction_status">
                                        <option value="">Select…</option>@foreach ($P::TRANSACTION_STATUSES as $k => $o)<option value="{{ $k }}">{{ $o }}</option>@endforeach</select>
                                    @error('form.transaction_status') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                                <div class="col-md-6"><label class="form-label small fw-semibold">Lead investor type {!! $tip('investor_type') !!}</label>
                                    <select class="form-select @error('form.investor_type') is-invalid @enderror" wire:model="form.investor_type">
                                        <option value="">Select…</option>@foreach ($P::INVESTOR_TYPES as $o)<option>{{ $o }}</option>@endforeach</select>
                                    @error('form.investor_type') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>

                                @php
                                    // Live: investor tickets, otherwise the round amount → CAFI grant
                                    $formTickets  = collect($form['round_investors'] ?? [])->sum(fn ($r) => is_numeric($r['ticket'] ?? null) ? (float) $r['ticket'] : 0);
                                    $formInvestor = $formTickets > 0 ? $formTickets : (is_numeric($form['round_amount'] ?? null) ? (float) $form['round_amount'] : null);
                                    $formGrant    = $P::matchingGrant($formInvestor);
                                @endphp
                                <div class="col-md-6"><label class="form-label small fw-semibold">CAFI matching grant (M) {!! $tip('grant_requested') !!}</label>
                                    <input type="text" class="form-control bg-light" value="{{ $formGrant !== null ? number_format($formGrant) : '' }}" placeholder="Calculated for you" readonly>
                                    <div class="form-text">{{ $usd($formGrant) }} {{ $formGrant !== null ? '·' : '' }} {{ $P::grantRatioLabel() }} of what your investor(s) put in{{ $formTickets > 0 ? ' (ticket sizes)' : ' (current round)' }}</div></div>
                                <div class="col-md-6"><label class="form-label small fw-semibold">Total project cost (M) {!! $tip('project_cost') !!}</label>
                                    <input type="number" min="0" step="1000" class="form-control @error('form.project_cost') is-invalid @enderror" wire:model.blur="form.project_cost">
                                    <div class="form-text">{{ $usd($form['project_cost'] ?? null) }}</div>
                                    @error('form.project_cost') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                                @if ($formInvestor !== null && ! $P::meetsInvestorMinimum($formInvestor))
                                    <div class="col-12"><div class="callout callout-warning small mb-0">
                                        <i class="bi bi-exclamation-triangle me-1"></i> Your investor(s) must be investing at least <strong>US$ {{ number_format($P::minInvestorUsd()) }}</strong> (about {{ $lsl($P::minInvestorLsl()) }}) before you can apply. You can save your profile, but the application will not be accepted.
                                    </div></div>
                                @endif
                                <div class="col-12"><label class="form-label small fw-semibold">What the money is for {!! $tip('investment_purpose') !!}</label>
                                    <textarea rows="3" class="form-control @error('form.investment_purpose') is-invalid @enderror" wire:model="form.investment_purpose"
                                              placeholder="Main items for the investor's money and for the CAFI grant, with rough amounts"></textarea>
                                    @error('form.investment_purpose') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>

                                {{-- Grant utilisation budget — appears once the investor amount is known --}}
                                <div class="col-12 border-top pt-3"><div class="fw-semibold text-navy small mb-2">Grant utilisation budget @if ($formInvestor !== null) <span class="req">*</span> @endif {!! $tip('grant_budget') !!}</div>
                                    @if ($formInvestor === null)
                                        <div class="callout small mb-0"><i class="bi bi-info-circle me-1"></i> Enter what your investor(s) are putting in (ticket sizes or the current round) first — the budget table will then appear here.</div>
                                    @else
                                        @php $bt = $P::sumBudget($form['grant_budget'] ?? []); $bGrantOk = abs($bt['grant'] - (float) $formGrant) < 1; @endphp
                                        <div class="table-responsive">
                                            <table class="table table-modern table-sm align-middle mb-1">
                                                <thead><tr><th>Expenditure category</th><th class="text-end" style="width: 24%">Private investment (M)</th><th class="text-end" style="width: 24%">CAFI grant (M)</th><th class="text-end" style="width: 16%">Total</th></tr></thead>
                                                <tbody>
                                                    @foreach ($form['grant_budget'] ?? [] as $bi => $b)
                                                        <tr wire:key="gb-{{ $bi }}">
                                                            <td class="small">{{ $b['category'] }}</td>
                                                            <td><input type="number" min="0" step="1000" class="form-control form-control-sm text-end @error('form.grant_budget.'.$bi.'.private') is-invalid @enderror" wire:model.blur="form.grant_budget.{{ $bi }}.private"></td>
                                                            <td><input type="number" min="0" step="1000" class="form-control form-control-sm text-end @error('form.grant_budget.'.$bi.'.grant') is-invalid @enderror" wire:model.blur="form.grant_budget.{{ $bi }}.grant"></td>
                                                            <td class="text-end small">{{ $lsl((is_numeric($b['private'] ?? null) ? (float) $b['private'] : 0) + (is_numeric($b['grant'] ?? null) ? (float) $b['grant'] : 0)) }}</td>
                                                        </tr>
                                                    @endforeach
                                                    <tr class="fw-bold">
                                                        <td class="small text-navy">Total</td>
                                                        <td class="text-end small">{{ $lsl($bt['private']) }}</td>
                                                        <td class="text-end small {{ $bGrantOk ? 'text-success' : 'text-danger' }}">{{ $lsl($bt['grant']) }}</td>
                                                        <td class="text-end small">{{ $lsl($bt['private'] + $bt['grant']) }}</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                        <div class="small {{ $bGrantOk ? 'text-muted' : 'text-danger' }}">
                                            <i class="bi {{ $bGrantOk ? 'bi-check-circle text-success' : 'bi-exclamation-circle' }} me-1"></i>
                                            The CAFI grant column must add up to your matching grant of <strong>{{ $lsl($formGrant) }}</strong>{{ $bGrantOk ? '.' : ' (currently '.$lsl($bt['grant']).').' }}
                                            Investor amount: {{ $lsl($formInvestor) }}{{ abs($bt['private'] - $formInvestor) >= 1 && $bt['private'] > 0 ? ' — the private column shows '.$lsl($bt['private']) : '' }}.
                                        </div>
                                        @error('form.grant_budget') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                                    @endif
                                </div>

                                <div class="col-12 border-top pt-3"><div class="fw-semibold text-navy small mb-2">Total amount of investment in the company to date {!! $tip('investment_history') !!}</div>
                                    <div class="table-responsive">
                                        <table class="table table-modern table-sm align-middle mb-0">
                                            <thead><tr><th>Source</th><th>Overall amount (M)</th><th>Date of last investment</th></tr></thead>
                                            <tbody>
                                                @foreach ($P::FUNDING_SOURCES as $k => $lbl)
                                                    <tr wire:key="hist-{{ $k }}">
                                                        <td class="small fw-semibold text-navy">{{ $lbl }}</td>
                                                        <td><input type="number" min="0" step="1000" class="form-control form-control-sm @error('form.investment_history.'.$k.'.amount') is-invalid @enderror" wire:model="form.investment_history.{{ $k }}.amount"></td>
                                                        <td><input type="date" max="{{ now()->toDateString() }}" class="form-control form-control-sm @error('form.investment_history.'.$k.'.date') is-invalid @enderror" wire:model="form.investment_history.{{ $k }}.date"></td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        @else
                            @if ($p->activity) <div class="quote mb-3">{{ $p->activity }}<div class="quote-meta">Company summary</div></div> @endif
                            <div class="detail-grid mb-3">
                                <div><dt>Stage of development {!! $tip('company_stage') !!}</dt><dd>{{ $P::STAGES[$p->company_stage ?? ''] ?? '—' }}</dd></div>
                                <div><dt>Annual revenue (past year) {!! $tip('annual_revenue') !!}</dt><dd>{{ $lsl($p->annual_revenue) }} <span class="small text-muted">{{ $usd($p->annual_revenue) }}</span></dd></div>
                            </div>
                            <dt class="detail-label">Products, customers &amp; markets {!! $tip('customers') !!}</dt>
                            <p class="small mb-3" style="white-space: pre-line">{{ $dash($p->customers) }}</p>
                            @foreach (['usp' => 'Unique selling proposition', 'marketing_strategy' => 'Marketing approach', 'competition' => 'Competition'] as $f => $lbl)
                                <dt class="detail-label">{{ $lbl }} {!! $tip($f) !!}</dt>
                                <p class="small mb-3" style="white-space: pre-line">{{ $dash($p->{$f}) }}</p>
                            @endforeach

                            <dt class="detail-label mb-2">Current investment round {!! $tip('round_amount') !!}</dt>
                            <div class="row g-2 mb-3">
                                @foreach (['Investment round' => $p->round_amount, 'Total project cost' => $p->project_cost, 'CAFI matching grant ('.$P::grantRatioLabel().')' => $p->grant_requested] as $lbl => $amt)
                                    <div class="col-md-4"><div class="border rounded-3 p-2 h-100">
                                        <div class="small text-muted">{{ $lbl }}</div>
                                        <div class="fw-bold text-navy">{{ $lsl($amt) }}</div>
                                        <div class="small text-muted">{{ $usd($amt) }}</div>
                                    </div></div>
                                @endforeach
                            </div>
                            @if ($p->investorAmount() !== null && ! $P::meetsInvestorMinimum($p->investorAmount()))
                                <div class="callout callout-warning small mb-3"><i class="bi bi-exclamation-triangle me-1"></i> The investor amount is below the US$ {{ number_format($P::minInvestorUsd()) }} minimum (about {{ $lsl($P::minInvestorLsl()) }}), so an application cannot be submitted yet.</div>
                            @endif
                            <div class="detail-grid mb-2">
                                <div><dt>Financing type {!! $tip('participation') !!}</dt><dd>{{ $P::PARTICIPATION[$p->participation ?? ''] ?? '—' }}</dd></div>
                                <div><dt>Status of the deal {!! $tip('transaction_status') !!}</dt><dd>{{ $P::TRANSACTION_STATUSES[$p->transaction_status ?? ''] ?? '—' }}</dd></div>
                                <div><dt>Lead investor type {!! $tip('investor_type') !!}</dt><dd>{{ $dash($p->investor_type) }}</dd></div>
                            </div>
                            @if ($p->investorRows())
                                <table class="table table-modern table-sm align-middle mb-3">
                                    <thead><tr><th>Investor</th><th>Residence country</th><th class="text-end">Ticket size</th></tr></thead>
                                    <tbody>
                                        @foreach ($p->investorRows() as $row)
                                            <tr><td class="fw-semibold text-navy">{{ $row['name'] }}</td><td>{{ $dash($row['country'] ?? null) }}</td>
                                                <td class="text-end">{{ $lsl($row['ticket'] ?? null) }} <div class="small text-muted">{{ $usd($row['ticket'] ?? null) }}</div></td></tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            @endif
                            <dt class="detail-label">What the money is for {!! $tip('investment_purpose') !!}</dt>
                            <p class="small mb-3" style="white-space: pre-line">{{ $dash($p->investment_purpose) }}</p>

                            @if ($p->grant_budget)
                                @php $vbt = $p->budgetTotals(); @endphp
                                <dt class="detail-label mb-2">Grant utilisation budget {!! $tip('grant_budget') !!}</dt>
                                <div class="table-responsive">
                                    <table class="table table-modern table-sm align-middle mb-1">
                                        <thead><tr><th>Expenditure category</th><th class="text-end">Private investment</th><th class="text-end">CAFI grant</th><th class="text-end">Total</th></tr></thead>
                                        <tbody>
                                            @foreach ($p->budgetRows() as $b)
                                                <tr><td class="small">{{ $b['category'] }}</td>
                                                    <td class="text-end small">{{ $lsl($b['private'] ?? null) }}</td>
                                                    <td class="text-end small">{{ $lsl($b['grant'] ?? null) }}</td>
                                                    <td class="text-end small">{{ $lsl((float) ($b['private'] ?? 0) + (float) ($b['grant'] ?? 0)) }}</td></tr>
                                            @endforeach
                                            <tr class="fw-bold"><td class="small text-navy">Total</td><td class="text-end small">{{ $lsl($vbt['private']) }}</td>
                                                <td class="text-end small {{ $p->budgetMatchesGrant() ? '' : 'text-danger' }}">{{ $lsl($vbt['grant']) }}</td>
                                                <td class="text-end small">{{ $lsl($vbt['private'] + $vbt['grant']) }}<div class="text-muted fw-normal">{{ $usd($vbt['private'] + $vbt['grant']) }}</div></td></tr>
                                        </tbody>
                                    </table>
                                </div>
                                @unless ($p->budgetMatchesGrant())
                                    <div class="small text-danger mb-3"><i class="bi bi-exclamation-circle me-1"></i> The CAFI grant column ({{ $lsl($vbt['grant']) }}) does not match the matching grant ({{ $lsl($p->grant_requested) }}) — the investor amount has probably changed. Please update the budget.</div>
                                @else
                                    <div class="mb-3"></div>
                                @endunless
                            @endif

                            @if ($p->investment_history)
                                <dt class="detail-label mb-2">Investment in the company to date {!! $tip('investment_history') !!}</dt>
                                <table class="table table-modern table-sm align-middle mb-0">
                                    <thead><tr><th>Source</th><th class="text-end">Overall amount</th><th>Last investment</th></tr></thead>
                                    <tbody>
                                        @foreach ($P::FUNDING_SOURCES as $k => $lbl)
                                            @continue(! isset($p->investment_history[$k]))
                                            @php $h = $p->investment_history[$k]; @endphp
                                            <tr><td>{{ $lbl }}</td><td class="text-end">{{ $lsl($h['amount'] ?? null) }}</td>
                                                <td class="small text-muted">{{ filled($h['date'] ?? null) ? \Illuminate\Support\Carbon::parse($h['date'])->format('d M Y') : '—' }}</td></tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            @endif
                        @endif

                        {{-- ===================== INVESTMENTS RECEIVED (one row per investment) ===================== --}}
                        <div class="border-top mt-3 pt-3">
                            <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                                <dt class="detail-label mb-0 me-auto">Investments received {!! $tip('investments') !!}</dt>
                                @if ($canEdit && ! $invOpen)
                                    <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="newInvestment" @disabled($editing)><i class="bi bi-plus-lg me-1"></i> Add investment</button>
                                @endif
                            </div>

                            @if ($invOpen && $canEdit)
                                <div class="border rounded-3 p-3 mb-3 bg-light">
                                    <div class="fw-semibold text-navy small mb-2">{{ $invEditingId ? 'Edit investment' : 'New investment' }}</div>
                                    <div class="row g-2">
                                        <div class="col-md-6"><label class="form-label small fw-semibold">Investor name <span class="req">*</span></label>
                                            <input type="text" class="form-control form-control-sm @error('inv.investor_name') is-invalid @enderror" wire:model="inv.investor_name">
                                            @error('inv.investor_name') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                                        <div class="col-md-3"><label class="form-label small fw-semibold">Investor type</label>
                                            <select class="form-select form-select-sm @error('inv.investor_type') is-invalid @enderror" wire:model="inv.investor_type">
                                                <option value="">Select…</option>@foreach ($P::INVESTOR_TYPES as $o)<option>{{ $o }}</option>@endforeach</select></div>
                                        <div class="col-md-3"><label class="form-label small fw-semibold">Country</label>
                                            <input type="text" class="form-control form-control-sm" wire:model="inv.investor_country"></div>
                                        <div class="col-md-4"><label class="form-label small fw-semibold">Amount (M) <span class="req">*</span></label>
                                            <input type="number" min="0" step="1000" class="form-control form-control-sm @error('inv.amount') is-invalid @enderror" wire:model.blur="inv.amount">
                                            <div class="form-text">{{ $usd($inv['amount'] ?? null) }}</div>
                                            @error('inv.amount') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                                        <div class="col-md-4"><label class="form-label small fw-semibold">Date of investment <span class="req">*</span></label>
                                            <input type="date" max="{{ now()->toDateString() }}" class="form-control form-control-sm @error('inv.invested_on') is-invalid @enderror" wire:model="inv.invested_on">
                                            @error('inv.invested_on') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                                        <div class="col-md-4"><label class="form-label small fw-semibold">Instrument</label>
                                            <select class="form-select form-select-sm" wire:model="inv.instrument">
                                                <option value="">Select…</option>@foreach ($I::INSTRUMENTS as $k => $o)<option value="{{ $k }}">{{ $o }}</option>@endforeach</select></div>
                                        <div class="col-md-4"><label class="form-label small fw-semibold">Round</label>
                                            <input type="text" class="form-control form-control-sm" wire:model="inv.round_label" placeholder="e.g. Seed, Series A"></div>
                                        <div class="col-md-8"><label class="form-label small fw-semibold">Proof of investment</label>
                                            <input type="file" class="form-control form-control-sm @error('invProof') is-invalid @enderror" wire:model="invProof">
                                            <div class="form-text">Bank statement, share certificate or signed agreement. Optional, but it speeds up verification.</div>
                                            @error('invProof') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                                        <div class="col-12"><label class="form-label small fw-semibold">Notes</label>
                                            <input type="text" class="form-control form-control-sm" wire:model="inv.notes" placeholder="Anything reviewers should know"></div>
                                    </div>
                                    <div class="d-flex gap-2 mt-3">
                                        <button type="button" class="btn btn-link btn-sm text-secondary text-decoration-none px-0" wire:click="cancelInvestment">Cancel</button>
                                        <button type="button" class="btn btn-brand btn-sm ms-auto" wire:click="saveInvestment" wire:loading.attr="disabled" wire:target="saveInvestment,invProof"><i class="bi bi-check-lg me-1"></i> Save investment</button>
                                    </div>
                                </div>
                            @endif

                            @if ($investments->isNotEmpty())
                                <div class="table-responsive">
                                    <table class="table table-modern table-sm align-middle mb-0">
                                        <thead><tr><th>Date</th><th>Investor</th><th class="text-end">Amount</th><th>Instrument</th><th>Status</th>@if ($canEdit)<th></th>@endif</tr></thead>
                                        <tbody>
                                            @foreach ($investments as $i)
                                                @php [$sLabel, $sPill, $sIcon] = $I::STATUSES[$i->status] ?? $I::STATUSES['declared']; @endphp
                                                <tr wire:key="inv-row-{{ $i->id }}">
                                                    <td class="small text-nowrap">{{ $i->invested_on?->format('d M Y') }}</td>
                                                    <td><div class="fw-semibold text-navy small">{{ $i->investor_name }}</div>
                                                        <div class="small text-muted">{{ collect([$i->investor_type, $i->investor_country, $i->round_label])->filter()->implode(' · ') }}</div></td>
                                                    <td class="text-end text-nowrap">{{ $lsl($i->amount) }}<div class="small text-muted">{{ $usd($i->amount) }}</div></td>
                                                    <td class="small">{{ $I::INSTRUMENTS[$i->instrument ?? ''] ?? '—' }}</td>
                                                    <td><span class="pill {{ $sPill }}"><i class="bi {{ $sIcon }}"></i> {{ $sLabel }}</span>
                                                        @if ($i->status === 'rejected' && $i->review_note) <div class="small text-danger mt-1">{{ $i->review_note }}</div> @endif
                                                        @if ($i->proof_path) <a href="{{ route('investments.proof', $i) }}" class="small d-block text-decoration-none mt-1"><i class="bi bi-paperclip"></i> Proof</a> @endif</td>
                                                    @if ($canEdit)
                                                        <td class="text-end text-nowrap">
                                                            @if ($i->isEditableByEnterprise())
                                                                <button type="button" class="btn btn-icon" title="Edit" wire:click="editInvestment({{ $i->id }})" @disabled($invOpen || $editing)><i class="bi bi-pencil"></i></button>
                                                                <button type="button" class="btn btn-icon btn-icon-danger" title="Remove" wire:click="deleteInvestment({{ $i->id }})" wire:confirm="Remove this investment?"><i class="bi bi-trash"></i></button>
                                                            @else
                                                                <i class="bi bi-lock text-muted" title="Verified or recorded by a reviewer — cannot be changed"></i>
                                                            @endif
                                                        </td>
                                                    @endif
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @elseif (! $invOpen)
                                <p class="small text-muted mb-0">No investments listed yet.@if ($canEdit) Add each investment you have received — one row per investment, even if the same investor invested more than once.@endif</p>
                            @endif
                        </div>
                    @endif

                    {{-- ===================== DOCUMENTS FOR THIS SECTION ===================== --}}
                    @php $docs = array_filter($D::forSection($key), $docVisible); @endphp
                    @if ($docs)
                        <div class="{{ $key === 'attachments' ? '' : 'border-top mt-3 pt-3' }}">
                            @if ($key === 'attachments') <div class="small text-muted mb-2"><i class="bi bi-translate me-1"></i> {{ $D::LANGUAGE_NOTE }}</div> @endif
                            @foreach ($docs as $type => [$dLabel, $dHint])
                                @php $doc = $p->exists ? $p->document($type) : null; $dReq = in_array($type, $reqDocs, true); @endphp
                                <div class="file-row flex-wrap" wire:key="doc-{{ $type }}">
                                    <span class="file-row-icon"><i class="bi {{ $doc ? 'bi-file-earmark-check' : 'bi-file-earmark' }}"></i></span>
                                    <div class="flex-grow-1 small" style="min-width: 180px">
                                        <div class="fw-semibold text-navy">{{ $dLabel }} @if ($dReq) <span class="req">*</span> @endif</div>
                                        @if ($doc)
                                            <a href="{{ route('enterprise-documents.download', $doc) }}" class="text-muted text-decoration-none"><i class="bi bi-paperclip"></i> {{ \Illuminate\Support\Str::limit($doc->original_name, 40) }} · {{ $doc->size_label }}</a>
                                        @else
                                            <div class="text-muted">{{ $dHint }}</div>
                                        @endif
                                        @error("uploads.$type") <div class="text-danger">{{ $message }}</div> @enderror
                                    </div>
                                    @if ($canEdit)
                                        <span wire:loading wire:target="uploads.{{ $type }}" class="small text-muted"><span class="spinner-border spinner-border-sm me-1"></span> Uploading…</span>
                                        <label class="btn btn-sm {{ $doc ? 'btn-outline-secondary' : 'btn-brand' }} mb-0" wire:loading.remove wire:target="uploads.{{ $type }}">
                                            <i class="bi {{ $doc ? 'bi-arrow-repeat' : 'bi-upload' }} me-1"></i>{{ $doc ? 'Replace' : 'Upload' }}
                                            <input type="file" class="d-none" wire:model="uploads.{{ $type }}" accept="{{ $D::acceptFor($type) }}">
                                        </label>
                                        @if ($doc)
                                            <button type="button" class="btn btn-icon btn-icon-danger" title="Remove" wire:click="removeDocument('{{ $type }}')" wire:confirm="Remove {{ $dLabel }}?"><i class="bi bi-trash"></i></button>
                                        @endif
                                    @elseif ($doc)
                                        <a href="{{ route('enterprise-documents.download', $doc) }}" class="btn btn-icon" title="Download"><i class="bi bi-download"></i></a>
                                    @else
                                        <span class="pill {{ $dReq ? 'pill-danger' : 'pill-muted' }}">Missing</span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif

                    {{-- ===================== 5 · DECLARATION ===================== --}}
                    @if ($key === 'attachments')
                        <div class="border-top mt-3 pt-3">
                            <dt class="detail-label mb-2">Declaration</dt>
                            @if ($isEditing)
                                <label class="form-check mb-2">
                                    <input type="checkbox" class="form-check-input @error('form.declaration_agreed') is-invalid @enderror" wire:model="form.declaration_agreed">
                                    <span class="form-check-label small">{{ $P::DECLARATION }}</span>
                                </label>
                                @error('form.declaration_agreed') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                                <div class="row g-2">
                                    <div class="col-md-8"><input type="text" class="form-control form-control-sm @error('form.declared_name') is-invalid @enderror" wire:model="form.declared_name" placeholder="Full name and position"></div>
                                    <div class="col-md-4"><input type="date" class="form-control form-control-sm @error('form.declared_on') is-invalid @enderror" wire:model="form.declared_on" max="{{ now()->toDateString() }}"></div>
                                </div>
                            @elseif ($p->declaration_agreed)
                                <div class="small"><i class="bi bi-patch-check-fill text-accent me-1"></i> Signed by <strong>{{ $p->declared_name }}</strong> on {{ $p->declared_on?->format('d M Y') }}</div>
                            @else
                                <div class="d-flex flex-wrap align-items-center gap-2 small">
                                    <span class="text-muted"><i class="bi bi-pen me-1"></i> Not signed yet.</span>
                                    @if ($canEdit) <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="edit('attachments')" @disabled($editing)>Sign declaration</button> @endif
                                </div>
                            @endif
                        </div>
                    @endif

                    @if ($isEditing)
                        @if ($errors->any())
                            <div class="callout callout-danger small mt-3 mb-0">
                                <i class="bi bi-exclamation-triangle me-1"></i> Not saved — please fix:
                                <ul class="mb-0 mt-1">@foreach ($errors->all() as $msg)<li>{{ $msg }}</li>@endforeach</ul>
                            </div>
                        @endif
                        <div class="d-flex gap-2 mt-3 pt-3 border-top">
                            <button type="button" class="btn btn-link text-secondary text-decoration-none px-0" wire:click="cancel">Cancel</button>
                            <button type="button" class="btn btn-brand ms-auto" wire:click="save" wire:loading.attr="disabled" wire:target="save">
                                <span wire:loading.remove wire:target="save"><i class="bi bi-check-lg me-1"></i>Save</span>
                                <span wire:loading wire:target="save"><span class="spinner-border spinner-border-sm me-1"></span>Saving…</span>
                            </button>
                        </div>
                    @endif
                </section>
            @endforeach
        </div>

        {{-- ============================ SIDEBAR ============================ --}}
        <div class="col-xl-4">
            <div class="sticky-side">
                <div class="card card-modern">
                    <div class="card-body">
                        <div class="card-title-sm"><i class="bi bi-list-check"></i> Sections</div>
                        @foreach ($P::SECTIONS as $key => [$title])
                            <a href="#sec-{{ $key }}" class="gate-row">
                                <span class="page-tabs-code">{{ $loop->iteration }}</span>
                                <span class="flex-grow-1 small fw-semibold text-navy">{{ $title }}</span>
                                <span class="pill {{ $status[$key] ? 'pill-ok' : 'pill-warn' }}"><i class="bi {{ $status[$key] ? 'bi-check-circle-fill' : 'bi-exclamation-circle' }}"></i></span>
                            </a>
                        @endforeach
                        @if ($p->exists && $p->missingDocuments())
                            <div class="small text-muted mt-2">Documents still needed: {{ collect($p->missingDocuments())->map(fn ($t) => $D::TYPES[$t][0])->implode(', ') }}.</div>
                        @elseif ($percent === 100)
                            <div class="small text-muted mt-2"><i class="bi bi-check-circle-fill text-accent me-1"></i> Complete. Keep it up to date if anything changes.</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif
</div>