<?php

use App\Models\EnterpriseDocument;
use App\Models\EnterpriseProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

/*
| Tab 2 · Update profile — 6-step form on the page.
|   Continue  → validates the step (required fields) and saves it
|   Save draft / stepper / Back → saves without requiring fields (type checks only)
|   Uploads   → saved straight away (commitment evidence + supporting documents)
*/
new class extends Component
{
    use WithFileUploads;

    #[Url(as: 'step')]
    public int $step = 1;

    public ?int $profileId = null;

    public array $form = [];

    /** type => uploaded file (wire:model) */
    public array $uploads = [];

    /** type => date (commitment documents) */
    public array $docDates = [];

    public function mount(int $startStep = 0): void
    {
        abort_unless(auth()->user()?->can('eoi.submit'), 403);

        $profile = EnterpriseProfile::forUser(auth()->user());

        $this->profileId = $profile->id;
        $this->form      = $this->toForm($profile);
        $this->docDates  = $profile->documents
            ->filter(fn ($d) => $d->document_date)
            ->mapWithKeys(fn ($d) => [$d->type => $d->document_date->toDateString()])
            ->all();

        if ($startStep > 0) {
            $this->step = $startStep;
        }
        $this->step = min(max($this->step, 1), count(EnterpriseProfile::STEPS));
    }

    /* =====================================================================
     | Navigation
     ===================================================================== */

    /** Continue: required fields for this step must be filled. */
    public function next(): void
    {
        if (! $this->validateStep(strict: true)) {
            return;
        }

        $this->persist();

        $this->step = min($this->step + 1, count(EnterpriseProfile::STEPS));
        $this->dispatch('notify', type: 'success', message: 'Saved.');
    }

    public function back(): void
    {
        $this->saveQuietly();
        $this->step = max($this->step - 1, 1);
    }

    public function goToStep(int $step): void
    {
        $this->saveQuietly();
        $this->step = min(max($step, 1), count(EnterpriseProfile::STEPS));
    }

    public function saveDraft(): void
    {
        if (! $this->validateStep(strict: false)) {
            return;
        }

        $this->persist();
        $this->dispatch('notify', type: 'success', message: 'Draft saved.');
    }

    /** Last step: sign the declaration and finish. */
    public function finish(): void
    {
        if (! $this->validateStep(strict: true)) {
            return;
        }

        $profile = $this->persist();

        $missing = collect($profile->sectionStatus())->reject()->keys()
            ->map(fn ($k) => EnterpriseProfile::SECTIONS[$k][0]);

        if ($missing->isEmpty()) {
            $this->dispatch('notify', type: 'success', message: 'Your enterprise profile is complete.');
        } else {
            $this->dispatch('notify', type: 'warning', message: 'Saved. Still incomplete: '.$missing->implode(', ').'.');
        }

        $this->dispatch('show-tab', tab: 'profile');
    }

    /** Leaving a step without Continue: keep what's valid, never block. */
    protected function saveQuietly(): void
    {
        if ($this->validateStep(strict: false, report: false)) {
            $this->persist(notify: false);
        }
    }

    /* =====================================================================
     | Repeatable rows
     ===================================================================== */

    protected array $rowTemplates = [
        'shareholders'      => ['name' => '', 'type' => '', 'percent' => ''],
        'management_team'   => ['name' => '', 'role' => '', 'years' => ''],
        'use_of_funds'      => ['item' => '', 'amount' => ''],
        'grant_allocations' => ['item' => '', 'amount' => '', 'timing' => ''],
    ];

    public function addRow(string $list): void
    {
        if (isset($this->rowTemplates[$list]) && count($this->form[$list]) < 20) {
            $this->form[$list][] = $this->rowTemplates[$list];
        }
    }

    public function removeRow(string $list, int $index): void
    {
        if (isset($this->rowTemplates[$list])) {
            unset($this->form[$list][$index]);
            $this->form[$list] = array_values($this->form[$list]);
            $this->resetValidation("form.{$list}");
        }
    }

    /* =====================================================================
     | Documents
     ===================================================================== */

    /** Runs as soon as a file is picked (wire:model="uploads.<type>"). */
    public function updatedUploads($file, string $type): void
    {
        abort_unless(array_key_exists($type, EnterpriseDocument::TYPES), 422);

        $this->validate(
            ["uploads.$type" => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:10240']],
            ["uploads.$type.max" => 'Files must be 10 MB or smaller.', "uploads.$type.mimes" => 'Upload a PDF, JPG or PNG file.'],
        );

        $profile = $this->persist(notify: false);   // make sure the profile exists

        DB::transaction(function () use ($profile, $type, $file) {
            $profile->documents()->where('type', $type)->get()->each->delete();   // removes the old file too

            $profile->documents()->create([
                'type'          => $type,
                'path'          => $file->store("enterprise-documents/{$profile->id}", EnterpriseDocument::DISK),
                'original_name' => $file->getClientOriginalName(),
                'mime'          => $file->getMimeType(),
                'size'          => $file->getSize(),
                'document_date' => $this->docDates[$type] ?? null,
                'uploaded_by'   => auth()->id(),
            ]);
        });

        unset($this->uploads[$type], $this->docs);
        $profile->refreshStatus();

        $this->dispatch('profile-saved');
        $this->dispatch('notify', type: 'success', message: EnterpriseDocument::TYPES[$type][0].' uploaded.');
    }

        /** Date next to a commitment document. */
    public function updatedDocDates($value, ?string $type = null): void
    {
        // Livewire also fires this when the whole array is replaced — ignore those.
        if ($type === null) {
            return;
        }

        $this->validate(["docDates.$type" => ['nullable', 'date', 'before_or_equal:today']]);

        if ($this->profileId) {
            EnterpriseDocument::where('enterprise_profile_id', $this->profileId)
                ->where('type', $type)
                ->update(['document_date' => $value ?: null]);
        }
    }

    public function removeDocument(string $type): void
    {
        if (! $this->profileId) {
            return;
        }

        $profile = EnterpriseProfile::findOrFail($this->profileId);
        $profile->documents()->where('type', $type)->get()->each->delete();
        $profile->refreshStatus();
        unset($this->docs);

        $this->dispatch('profile-saved');
        $this->dispatch('notify', type: 'success', message: (EnterpriseDocument::TYPES[$type][0] ?? 'Document').' removed.');
    }

    /* =====================================================================
     | Validation
     ===================================================================== */

    protected function validateStep(bool $strict, bool $report = true): bool
    {
        $rules = $this->rulesFor($this->step, $strict);

        if (! $report) {
            return validator(['form' => $this->form], $rules)->passes();
        }

        $this->validate($rules, $this->messages(), $this->validationAttributes());

        // Checks that need uploaded documents
        if ($strict && $this->step === 4 && ! $this->hasAnyDocument(array_keys(EnterpriseDocument::typesFor('commitment')))) {
            $this->addError('commitment', 'Upload at least one piece of investor-commitment evidence (LOI, term sheet or agreement).');
            return false;
        }
        if ($strict && $this->step === 5) {
            $missing = collect(EnterpriseDocument::REQUIRED_SUPPORTING)->reject(fn ($t) => $this->hasAnyDocument([$t]));
            if ($missing->isNotEmpty()) {
                $this->addError('documents', 'Still required: '.$missing->map(fn ($t) => EnterpriseDocument::TYPES[$t][0])->implode(', ').'.');
                return false;
            }
        }

        return true;
    }

    protected function rulesFor(int $step, bool $strict): array
    {
        $req   = $strict ? 'required' : 'nullable';
        $money = ['nullable', 'numeric', 'min:0', 'max:9999999999999'];
        $pct   = ['nullable', 'integer', 'min:0', 'max:100'];

        return match ($step) {
            1 => [
                'form.registered_name'     => [$req, 'string', 'max:255'],
                'form.trading_name'        => ['nullable', 'string', 'max:255'],
                'form.registration_no'     => [$req, 'string', 'max:60'],
                'form.legal_form'          => [$req, Rule::in(EnterpriseProfile::LEGAL_FORMS)],
                'form.registered_on'       => ['nullable', 'date', 'before_or_equal:today'],
                'form.district'            => [$req, Rule::in(EnterpriseProfile::DISTRICTS)],
                'form.address'             => [$req, 'string', 'max:255'],
                'form.sector'              => [$req, Rule::in(EnterpriseProfile::SECTORS)],
                'form.employees'           => ['nullable', 'integer', 'min:0', 'max:100000'],
                'form.women_employees'     => ['nullable', 'integer', 'min:0', function ($attr, $value, $fail) {
                    if (filled($value) && filled($this->form['employees']) && (int) $value > (int) $this->form['employees']) {
                        $fail('Women employees cannot be more than total employees.');
                    }
                }],
                'form.basotho_owned_pct'   => $pct,
                'form.women_owned_pct'     => $pct,
                'form.youth_owned_pct'     => $pct,
                'form.activity'            => [$req, 'string', 'max:2000'],
                'form.shareholders'        => $strict ? ['required', 'array', 'min:1', $this->percentTotal()] : ['array'],
                'form.shareholders.*.name'    => [$req, 'string', 'max:255'],
                'form.shareholders.*.type'    => ['nullable', 'string', 'max:100'],
                'form.shareholders.*.percent' => [$req, 'numeric', 'min:0', 'max:100'],
                'form.management_team'        => $strict ? ['required', 'array', 'min:1'] : ['array'],
                'form.management_team.*.name' => [$req, 'string', 'max:255'],
                'form.management_team.*.role' => [$req, 'string', 'max:100'],
                'form.management_team.*.years'=> ['nullable', 'integer', 'min:0', 'max:70'],
                'form.board_size'             => ['nullable', 'integer', 'min:0', 'max:30'],
                'form.independent_directors'  => ['nullable', 'integer', 'min:0', 'max:30'],
                'form.board_meetings'         => [$req, Rule::in(EnterpriseProfile::BOARD_MEETINGS)],
                'form.audited_accounts'       => ['boolean'],
            ],
            2 => [
                'form.products'           => [$req, 'string', 'max:1000'],
                'form.customers'          => [$req, 'string', 'max:2000'],
                'form.markets'            => $strict ? ['required', 'array', 'min:1'] : ['array'],
                'form.markets.*'          => [Rule::in(EnterpriseProfile::MARKETS)],
                'form.annual_revenue'     => array_merge([$req], array_slice($money, 1)),
                'form.revenue_growth'     => ['nullable', 'numeric', 'min:-100', 'max:10000'],
                'form.traction'           => ['nullable', 'string', 'max:2000'],
                'form.growth_opportunity' => [$req, 'string', 'max:2000'],
            ],
            3 => [
                'form.amount_sought'         => [$req, 'numeric', 'gt:0', 'max:9999999999999'],
                'form.project_cost'          => $money,
                'form.own_contribution'      => $money,
                'form.investor_contribution' => $money,
                'form.grant_requested'       => array_merge([$req], array_slice($money, 1)),
                'form.investment_purpose'    => [$req, 'string', 'max:2000'],
                'form.use_of_funds'          => $strict ? ['required', 'array', 'min:1'] : ['array'],
                'form.use_of_funds.*.item'   => [$req, 'string', 'max:255'],
                'form.use_of_funds.*.amount' => array_merge([$req], array_slice($money, 1)),
                'form.grant_allocations'          => $strict ? ['required', 'array', 'min:1', $this->grantTotal()] : ['array'],
                'form.grant_allocations.*.item'   => [$req, 'string', 'max:255'],
                'form.grant_allocations.*.amount' => array_merge([$req], array_slice($money, 1)),
                'form.grant_allocations.*.timing' => ['nullable', 'string', 'max:50'],
            ],
            4 => [
                'form.investor_name'        => [$req, 'string', 'max:255'],
                'form.investor_type'        => [$req, Rule::in(EnterpriseProfile::INVESTOR_TYPES)],
                'form.investor_country'     => ['nullable', 'string', 'max:60'],
                'form.investor_contact'     => ['nullable', 'string', 'max:255'],
                'form.participation'        => [$req, Rule::in(array_keys(EnterpriseProfile::PARTICIPATION))],
                'form.investor_eligibility'   => ['array'],
                'form.investor_eligibility.*' => $strict ? ['accepted'] : ['boolean'],
                'form.instrument'           => [$req, Rule::in(array_keys(EnterpriseProfile::PARTICIPATION))],
                'form.structure'            => [$req, 'string', 'max:255'],
                'form.stake_pct'            => ['nullable', 'numeric', 'min:0', 'max:100'],
                'form.valuation'            => $money,
                'form.transaction_status'   => [$req, Rule::in(array_keys(EnterpriseProfile::TRANSACTION_STATUSES))],
                'form.key_terms'            => ['nullable', 'string', 'max:2000'],
            ],
            5 => [
                'form.financials'                => $strict ? ['array', $this->atLeastOneRevenue()] : ['array'],
                'form.financials.*.year'         => ['nullable', 'string', 'max:20'],
                'form.financials.*.revenue'      => ['nullable', 'numeric'],
                'form.financials.*.gross_profit' => ['nullable', 'numeric'],
                'form.financials.*.net_profit'   => ['nullable', 'numeric'],
                'form.financials.*.total_assets' => ['nullable', 'numeric'],
                'form.statutory'                 => ['array'],
                'form.statutory.*'               => ['boolean'],
            ],
            6 => [
                'form.declarations'      => ['array'],
                'form.declarations.*'    => $strict ? ['accepted'] : ['boolean'],
                'form.declared_name'     => [$req, 'string', 'max:255'],
                'form.declared_position' => [$req, 'string', 'max:100'],
                'form.declared_on'       => [$req, 'date', 'before_or_equal:today'],
            ],
            default => [],
        };
    }

    protected function percentTotal(): \Closure
    {
        return function ($attr, $rows, $fail) {
            $total = collect($rows)->sum(fn ($r) => (float) ($r['percent'] ?? 0));
            if ($total > 100.0001) {
                $fail("Shareholding adds up to {$total}% — it can't be more than 100%.");
            }
        };
    }

    protected function grantTotal(): \Closure
    {
        return function ($attr, $rows, $fail) {
            $total = collect($rows)->sum(fn ($r) => (float) ($r['amount'] ?? 0));
            $grant = (float) ($this->form['grant_requested'] ?? 0);
            if (abs($total - $grant) >= 0.01) {
                $fail('Grant allocations add up to M '.number_format($total).' but the grant requested is M '.number_format($grant).'.');
            }
        };
    }

    protected function atLeastOneRevenue(): \Closure
    {
        return function ($attr, $rows, $fail) {
            if (collect($rows)->filter(fn ($r) => filled($r['revenue'] ?? null))->isEmpty()) {
                $fail('Enter revenue for at least one financial year.');
            }
        };
    }

    protected function messages(): array
    {
        return [
            'form.investor_eligibility.*.accepted' => 'Confirm every eligibility statement.',
            'form.declarations.*.accepted'         => 'Every declaration must be confirmed.',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'form.registered_name' => 'registered name', 'form.registration_no' => 'registration number',
            'form.legal_form' => 'legal form', 'form.registered_on' => 'registration date', 'form.district' => 'district',
            'form.address' => 'physical address', 'form.sector' => 'sector', 'form.employees' => 'employees',
            'form.women_employees' => 'women employees', 'form.activity' => 'business activity',
            'form.basotho_owned_pct' => 'Basotho-owned %', 'form.women_owned_pct' => 'women-owned %', 'form.youth_owned_pct' => 'youth-owned %',
            'form.shareholders' => 'shareholders', 'form.shareholders.*.name' => 'shareholder name', 'form.shareholders.*.percent' => 'shareholding %',
            'form.management_team' => 'management team', 'form.management_team.*.name' => 'name', 'form.management_team.*.role' => 'position',
            'form.management_team.*.years' => 'years of experience', 'form.board_meetings' => 'board meetings',
            'form.products' => 'products / services', 'form.customers' => 'main customers', 'form.markets' => 'markets',
            'form.annual_revenue' => 'annual revenue', 'form.revenue_growth' => 'revenue growth', 'form.growth_opportunity' => 'growth opportunity',
            'form.amount_sought' => 'amount sought', 'form.project_cost' => 'project cost', 'form.own_contribution' => 'own contribution',
            'form.investor_contribution' => 'investor contribution', 'form.grant_requested' => 'CI grant requested',
            'form.investment_purpose' => 'purpose', 'form.use_of_funds' => 'use of funds', 'form.use_of_funds.*.item' => 'item',
            'form.use_of_funds.*.amount' => 'amount', 'form.grant_allocations' => 'grant allocation',
            'form.grant_allocations.*.item' => 'item', 'form.grant_allocations.*.amount' => 'amount',
            'form.investor_name' => 'investor name', 'form.investor_type' => 'investor type', 'form.participation' => 'nature of participation',
            'form.instrument' => 'instrument', 'form.structure' => 'proposed structure', 'form.stake_pct' => 'stake',
            'form.valuation' => 'valuation', 'form.transaction_status' => 'transaction status',
            'form.declared_name' => 'full name', 'form.declared_position' => 'position', 'form.declared_on' => 'date',
        ];
    }

    /* =====================================================================
     | Persistence
     ===================================================================== */

    protected function persist(bool $notify = true): EnterpriseProfile
    {
        $profile = EnterpriseProfile::firstOrNew(['user_id' => auth()->id()]);
        $profile->fill($this->payload());
        $profile->user_id = auth()->id();
        $profile->save();

        $profile->load('documents');
        $profile->refreshStatus();

        $this->profileId = $profile->id;

        if ($notify) {
            $this->dispatch('profile-saved');
        }

        return $profile;
    }

    /** Form array → model attributes (empty strings become null). */
    protected function payload(): array
    {
        $f    = $this->form;
        $text = fn ($v) => filled($v) ? trim((string) $v) : null;
        $num  = fn ($v) => is_numeric($v) ? $v : null;
        $int  = fn ($v) => is_numeric($v) ? (int) $v : null;
        $rows = fn (array $rows, array $keys) => array_values(array_filter(
            array_map(fn ($r) => array_intersect_key($r, array_flip($keys)), $rows),
            fn ($r) => collect($r)->filter(fn ($v) => filled($v))->isNotEmpty()
        ));
        $bools = fn ($values, array $keys) => collect($keys)->mapWithKeys(fn ($k) => [$k => (bool) ($values[$k] ?? false)])->all();

        return [
            'registered_name'       => $text($f['registered_name']),
            'trading_name'          => $text($f['trading_name']),
            'registration_no'       => $text($f['registration_no']),
            'legal_form'            => $text($f['legal_form']),
            'registered_on'         => $text($f['registered_on']),
            'district'              => $text($f['district']),
            'address'               => $text($f['address']),
            'sector'                => $text($f['sector']),
            'employees'             => $int($f['employees']),
            'women_employees'       => $int($f['women_employees']),
            'basotho_owned_pct'     => $int($f['basotho_owned_pct']),
            'women_owned_pct'       => $int($f['women_owned_pct']),
            'youth_owned_pct'       => $int($f['youth_owned_pct']),
            'activity'              => $text($f['activity']),

            'products'              => collect(explode(',', (string) $f['products']))->map(fn ($x) => trim($x))->filter()->values()->all() ?: null,
            'customers'             => $text($f['customers']),
            'markets'               => array_values(array_intersect(EnterpriseProfile::MARKETS, $f['markets'] ?? [])) ?: null,
            'annual_revenue'        => $num($f['annual_revenue']),
            'revenue_growth'        => $num($f['revenue_growth']),
            'traction'              => $text($f['traction']),
            'growth_opportunity'    => $text($f['growth_opportunity']),

            'shareholders'          => $rows($f['shareholders'], ['name', 'type', 'percent']),
            'management_team'       => $rows($f['management_team'], ['name', 'role', 'years']),
            'board_size'            => $int($f['board_size']),
            'independent_directors' => $int($f['independent_directors']),
            'board_meetings'        => $text($f['board_meetings']),
            'audited_accounts'      => (bool) $f['audited_accounts'],

            'amount_sought'         => $num($f['amount_sought']),
            'project_cost'          => $num($f['project_cost']),
            'own_contribution'      => $num($f['own_contribution']),
            'investor_contribution' => $num($f['investor_contribution']),
            'grant_requested'       => $num($f['grant_requested']),
            'investment_purpose'    => $text($f['investment_purpose']),
            'use_of_funds'          => $rows($f['use_of_funds'], ['item', 'amount']),

            'investor_name'         => $text($f['investor_name']),
            'investor_type'         => $text($f['investor_type']),
            'investor_country'      => $text($f['investor_country']),
            'investor_contact'      => $text($f['investor_contact']),
            'participation'         => $text($f['participation']),
            'investor_eligibility'  => $bools($f['investor_eligibility'], array_keys(EnterpriseProfile::INVESTOR_ELIGIBILITY)),

            'instrument'            => $text($f['instrument']),
            'structure'             => $text($f['structure']),
            'stake_pct'             => $num($f['stake_pct']),
            'valuation'             => $num($f['valuation']),
            'transaction_status'    => $text($f['transaction_status']),
            'key_terms'             => $text($f['key_terms']),

            'financials'            => array_values(array_filter($f['financials'], fn ($r) => filled($r['year'] ?? null))),
            'statutory'             => $bools($f['statutory'], array_keys(EnterpriseProfile::STATUTORY)),

            'grant_allocations'     => $rows($f['grant_allocations'], ['item', 'amount', 'timing']),

            'declarations'          => $bools($f['declarations'], array_keys(EnterpriseProfile::DECLARATIONS)),
            'declared_name'         => $text($f['declared_name']),
            'declared_position'     => $text($f['declared_position']),
            'declared_on'           => $text($f['declared_on']),
        ];
    }

    /** Model → form array (with sensible blanks for new profiles). */
    protected function toForm(EnterpriseProfile $p): array
    {
        $rowsOr = fn ($rows, string $tpl) => $rows ?: [$this->rowTemplates[$tpl]];
        $bools  = fn ($values, array $keys) => collect($keys)->mapWithKeys(fn ($k) => [$k => (bool) ($values[$k] ?? false)])->all();

        // Last three financial years, keeping anything already entered
        $year       = (int) now()->format('Y');
        $financials = collect($p->financials ?? [])->values();
        $defaults   = collect(range($year - 3, $year - 1))->map(fn ($y) => ['year' => "FY{$y}", 'revenue' => '', 'gross_profit' => '', 'net_profit' => '', 'total_assets' => '']);
        $financials = $financials->isNotEmpty() ? $financials->pad(3, ['year' => '', 'revenue' => '', 'gross_profit' => '', 'net_profit' => '', 'total_assets' => '']) : $defaults;

        $num = fn ($v) => $v === null ? '' : (string) (float) $v;

        return [
            'registered_name'       => $p->registered_name ?? '',
            'trading_name'          => $p->trading_name ?? '',
            'registration_no'       => $p->registration_no ?? '',
            'legal_form'            => $p->legal_form ?? '',
            'registered_on'         => $p->registered_on?->toDateString() ?? '',
            'district'              => $p->district ?? '',
            'address'               => $p->address ?? '',
            'sector'                => $p->sector ?? '',
            'employees'             => $p->employees ?? '',
            'women_employees'       => $p->women_employees ?? '',
            'basotho_owned_pct'     => $p->basotho_owned_pct ?? '',
            'women_owned_pct'       => $p->women_owned_pct ?? '',
            'youth_owned_pct'       => $p->youth_owned_pct ?? '',
            'activity'              => $p->activity ?? '',

            'products'              => implode(', ', $p->products ?? []),
            'customers'             => $p->customers ?? '',
            'markets'               => $p->markets ?? [],
            'annual_revenue'        => $num($p->annual_revenue),
            'revenue_growth'        => $num($p->revenue_growth),
            'traction'              => $p->traction ?? '',
            'growth_opportunity'    => $p->growth_opportunity ?? '',

            'shareholders'          => $rowsOr($p->shareholders, 'shareholders'),
            'management_team'       => $rowsOr($p->management_team, 'management_team'),
            'board_size'            => $p->board_size ?? '',
            'independent_directors' => $p->independent_directors ?? '',
            'board_meetings'        => $p->board_meetings ?? '',
            'audited_accounts'      => (bool) $p->audited_accounts,

            'amount_sought'         => $num($p->amount_sought),
            'project_cost'          => $num($p->project_cost),
            'own_contribution'      => $num($p->own_contribution),
            'investor_contribution' => $num($p->investor_contribution),
            'grant_requested'       => $num($p->grant_requested),
            'investment_purpose'    => $p->investment_purpose ?? '',
            'use_of_funds'          => $rowsOr($p->use_of_funds, 'use_of_funds'),

            'investor_name'         => $p->investor_name ?? '',
            'investor_type'         => $p->investor_type ?? '',
            'investor_country'      => $p->investor_country ?? 'Lesotho',
            'investor_contact'      => $p->investor_contact ?? '',
            'participation'         => $p->participation ?? '',
            'investor_eligibility'  => $bools($p->investor_eligibility, array_keys(EnterpriseProfile::INVESTOR_ELIGIBILITY)),

            'instrument'            => $p->instrument ?? '',
            'structure'             => $p->structure ?? '',
            'stake_pct'             => $num($p->stake_pct),
            'valuation'             => $num($p->valuation),
            'transaction_status'    => $p->transaction_status ?? '',
            'key_terms'             => $p->key_terms ?? '',

            'financials'            => $financials->all(),
            'statutory'             => $bools($p->statutory, array_keys(EnterpriseProfile::STATUTORY)),

            'grant_allocations'     => $rowsOr($p->grant_allocations, 'grant_allocations'),

            'declarations'          => $bools($p->declarations, array_keys(EnterpriseProfile::DECLARATIONS)),
            'declared_name'         => $p->declared_name ?? auth()->user()->name,
            'declared_position'     => $p->declared_position ?? '',
            'declared_on'           => $p->declared_on?->toDateString() ?? now()->toDateString(),
        ];
    }

    /* =====================================================================
     | View helpers
     ===================================================================== */

    protected function hasAnyDocument(array $types): bool
    {
        return $this->profileId
            && EnterpriseDocument::where('enterprise_profile_id', $this->profileId)->whereIn('type', $types)->exists();
    }

    /** Uploaded documents keyed by type */
    #[Computed]
    public function docs()
    {
        return $this->profileId
            ? EnterpriseDocument::where('enterprise_profile_id', $this->profileId)->get()->keyBy('type')
            : collect();
    }
};
?>

<div>
    @php
        $steps    = \App\Models\EnterpriseProfile::STEPS;
        $sections = \App\Models\EnterpriseProfile::SECTIONS;
        $M        = \App\Models\EnterpriseProfile::class;
        $sectionNo = fn ($key) => array_search($key, array_keys($sections)) + 1;
        $docs     = $this->docs;
        $rate     = (float) config('cif.usd_to_lsl', 18.00);
        $usd      = fn ($m) => is_numeric($m) ? 'US$ '.number_format((float) $m / max($rate, 0.0001)) : '';
        $err      = fn ($key) => $errors->has($key) ? 'is-invalid' : '';
    @endphp

    <div class="card card-modern">

        {{-- ============================ STEPPER ============================ --}}
        <div class="stepper rounded-top">
            @foreach ($steps as $n => [$label, $icon])
                <button type="button" wire:click="goToStep({{ $n }})"
                        class="stepper-step {{ $n === $step ? 'active' : '' }} {{ $n < $step ? 'done' : '' }}">
                    <span class="stepper-dot"><i class="bi {{ $n < $step ? 'bi-check-lg' : $icon }}"></i></span>
                    <span class="stepper-label">{{ $label }}</span>
                </button>
            @endforeach
            <div class="stepper-track"><div class="stepper-fill" style="width: {{ ($step - 1) / (count($steps) - 1) * 100 }}%"></div></div>
        </div>

        <form wire:submit.prevent="{{ $step < count($steps) ? 'next' : 'finish' }}" class="form-modern" novalidate>
            <div class="card-body p-4" wire:key="step-{{ $step }}">
                <div class="small text-muted mb-3">
                    Step {{ $step }} of {{ count($steps) }} · {{ collect($steps[$step][2])->map(fn ($k) => $sections[$k][0])->implode(', ') }}
                </div>

                @if ($errors->any())
                    <div class="callout callout-warning small mb-3">
                        <i class="bi bi-exclamation-triangle me-1"></i> Please fix the highlighted fields below.
                    </div>
                @endif

                {{-- ───────────── STEP 1 · ENTERPRISE + GOVERNANCE ───────────── --}}
                @if ($step === 1)
                    <div class="form-section-head">
                        <span class="q-badge">{{ $sectionNo('enterprise') }}</span>
                        <div><h6>Enterprise profile</h6><p>{{ $sections['enterprise'][2] }}.</p></div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="ep_rn">Registered name <span class="req">*</span></label>
                            <input id="ep_rn" wire:model="form.registered_name" class="form-control {{ $err('form.registered_name') }}">
                            @error('form.registered_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="ep_tn">Trading name <span class="opt">(if different)</span></label>
                            <input id="ep_tn" wire:model="form.trading_name" class="form-control {{ $err('form.trading_name') }}">
                            @error('form.trading_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="ep_reg">Registration number <span class="req">*</span></label>
                            <input id="ep_reg" wire:model="form.registration_no" class="form-control {{ $err('form.registration_no') }}">
                            @error('form.registration_no') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="ep_lf">Legal form <span class="req">*</span></label>
                            <select id="ep_lf" wire:model="form.legal_form" class="form-select {{ $err('form.legal_form') }}">
                                <option value="">Select…</option>
                                @foreach ($M::LEGAL_FORMS as $o) <option value="{{ $o }}">{{ $o }}</option> @endforeach
                            </select>
                            @error('form.legal_form') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="ep_ro">Date registered</label>
                            <input type="date" id="ep_ro" wire:model="form.registered_on" max="{{ now()->toDateString() }}" class="form-control {{ $err('form.registered_on') }}">
                            @error('form.registered_on') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="ep_dist">District <span class="req">*</span></label>
                            <select id="ep_dist" wire:model="form.district" class="form-select {{ $err('form.district') }}">
                                <option value="">Select…</option>
                                @foreach ($M::DISTRICTS as $o) <option value="{{ $o }}">{{ $o }}</option> @endforeach
                            </select>
                            @error('form.district') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-8">
                            <label class="form-label" for="ep_addr">Physical address <span class="req">*</span></label>
                            <input id="ep_addr" wire:model="form.address" class="form-control {{ $err('form.address') }}">
                            @error('form.address') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="ep_sec">Sector <span class="req">*</span></label>
                            <select id="ep_sec" wire:model="form.sector" class="form-select {{ $err('form.sector') }}">
                                <option value="">Select…</option>
                                @foreach ($M::SECTORS as $o) <option value="{{ $o }}">{{ $o }}</option> @endforeach
                            </select>
                            @error('form.sector') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="ep_emp">Full-time employees</label>
                            <input type="number" min="0" id="ep_emp" wire:model="form.employees" class="form-control {{ $err('form.employees') }}">
                            @error('form.employees') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="ep_wemp">of whom women</label>
                            <input type="number" min="0" id="ep_wemp" wire:model="form.women_employees" class="form-control {{ $err('form.women_employees') }}">
                            @error('form.women_employees') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label">Ownership</label>
                            <div class="row g-2">
                                @foreach (['basotho_owned_pct' => 'Basotho-owned', 'women_owned_pct' => 'Women-owned', 'youth_owned_pct' => 'Youth-owned'] as $k => $label)
                                    <div class="col-md-4">
                                        <div class="input-group has-validation">
                                            <span class="input-group-text small">{{ $label }}</span>
                                            <input type="number" min="0" max="100" wire:model="form.{{ $k }}" class="form-control {{ $err("form.$k") }}">
                                            <span class="input-group-text">%</span>
                                            @error("form.$k") <div class="invalid-feedback">{{ $message }}</div> @enderror
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="ep_act">Operations &amp; business activity <span class="req">*</span></label>
                            <textarea id="ep_act" rows="3" wire:model="form.activity" class="form-control {{ $err('form.activity') }}" placeholder="What the business does, who it serves and how it operates."></textarea>
                            @error('form.activity') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="form-section-head mt-4">
                        <span class="q-badge">{{ $sectionNo('governance') }}</span>
                        <div><h6>Management &amp; governance</h6><p>{{ $sections['governance'][2] }}.</p></div>
                    </div>

                    <label class="form-label">Shareholders <span class="req">*</span></label>
                    @error('form.shareholders') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                    @foreach ($form['shareholders'] as $i => $row)
                        <div class="row g-2 mb-2" wire:key="sh-{{ $i }}">
                            <div class="col-md-5">
                                <input wire:model="form.shareholders.{{ $i }}.name" placeholder="Name" class="form-control {{ $err("form.shareholders.$i.name") }}">
                                @error("form.shareholders.$i.name") <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-4"><input wire:model="form.shareholders.{{ $i }}.type" placeholder="e.g. Founder, Institutional" class="form-control"></div>
                            <div class="col-md-2">
                                <div class="input-group has-validation">
                                    <input type="number" min="0" max="100" step="0.01" wire:model="form.shareholders.{{ $i }}.percent" class="form-control {{ $err("form.shareholders.$i.percent") }}">
                                    <span class="input-group-text">%</span>
                                    @error("form.shareholders.$i.percent") <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>
                            <div class="col-md-1 text-end">
                                <button type="button" class="btn btn-icon btn-icon-danger" title="Remove" wire:click="removeRow('shareholders', {{ $i }})"><i class="bi bi-trash"></i></button>
                            </div>
                        </div>
                    @endforeach
                    <button type="button" class="btn btn-link btn-sm text-decoration-none px-0 mb-3" wire:click="addRow('shareholders')"><i class="bi bi-plus-lg me-1"></i> Add shareholder</button>

                    <label class="form-label d-block">Management team <span class="req">*</span></label>
                    @error('form.management_team') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                    @foreach ($form['management_team'] as $i => $row)
                        <div class="row g-2 mb-2" wire:key="mt-{{ $i }}">
                            <div class="col-md-5">
                                <input wire:model="form.management_team.{{ $i }}.name" placeholder="Name" class="form-control {{ $err("form.management_team.$i.name") }}">
                                @error("form.management_team.$i.name") <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-4">
                                <input wire:model="form.management_team.{{ $i }}.role" placeholder="Position" class="form-control {{ $err("form.management_team.$i.role") }}">
                                @error("form.management_team.$i.role") <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-2">
                                <div class="input-group has-validation">
                                    <input type="number" min="0" wire:model="form.management_team.{{ $i }}.years" class="form-control {{ $err("form.management_team.$i.years") }}">
                                    <span class="input-group-text">yrs</span>
                                </div>
                            </div>
                            <div class="col-md-1 text-end">
                                <button type="button" class="btn btn-icon btn-icon-danger" title="Remove" wire:click="removeRow('management_team', {{ $i }})"><i class="bi bi-trash"></i></button>
                            </div>
                        </div>
                    @endforeach
                    <button type="button" class="btn btn-link btn-sm text-decoration-none px-0 mb-3" wire:click="addRow('management_team')"><i class="bi bi-plus-lg me-1"></i> Add team member</button>

                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label" for="ep_bs">Board size</label>
                            <input type="number" min="0" id="ep_bs" wire:model="form.board_size" class="form-control {{ $err('form.board_size') }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="ep_ind">Independent directors</label>
                            <input type="number" min="0" id="ep_ind" wire:model="form.independent_directors" class="form-control {{ $err('form.independent_directors') }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="ep_bm">Board meetings <span class="req">*</span></label>
                            <select id="ep_bm" wire:model="form.board_meetings" class="form-select {{ $err('form.board_meetings') }}">
                                <option value="">Select…</option>
                                @foreach ($M::BOARD_MEETINGS as $o) <option value="{{ $o }}">{{ $o }}</option> @endforeach
                            </select>
                            @error('form.board_meetings') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-3">
                            <label class="form-label d-block">Audited accounts</label>
                            <label class="choice"><input type="checkbox" wire:model="form.audited_accounts"><span class="choice-box"><span class="choice-check"></span> Yes</span></label>
                        </div>
                    </div>
                @endif

                {{-- ───────────── STEP 2 · COMMERCIAL ───────────── --}}
                @if ($step === 2)
                    <div class="form-section-head">
                        <span class="q-badge">{{ $sectionNo('commercial') }}</span>
                        <div><h6>Commercial profile</h6><p>{{ $sections['commercial'][2] }}.</p></div>
                    </div>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label" for="ep_prod">Products / services <span class="req">*</span></label>
                            <input id="ep_prod" wire:model="form.products" class="form-control {{ $err('form.products') }}" placeholder="e.g. Dried peaches, Dried apples">
                            <div class="form-text">Separate with commas.</div>
                            @error('form.products') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="ep_cust">Main customers <span class="req">*</span></label>
                            <textarea id="ep_cust" rows="2" wire:model="form.customers" class="form-control {{ $err('form.customers') }}"></textarea>
                            @error('form.customers') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label">Markets served <span class="req">*</span></label>
                            <div class="choice-group cols-3 {{ $err('form.markets') }}">
                                @foreach ($M::MARKETS as $m)
                                    <label class="choice"><input type="checkbox" wire:model="form.markets" value="{{ $m }}"><span class="choice-box"><span class="choice-check"></span>{{ $m }}</span></label>
                                @endforeach
                            </div>
                            @error('form.markets') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="ep_rev">Annual revenue — latest year <span class="req">*</span></label>
                            <div class="input-group has-validation">
                                <span class="input-group-text">M</span>
                                <input type="number" min="0" step="0.01" id="ep_rev" wire:model.live.debounce.500ms="form.annual_revenue" class="form-control {{ $err('form.annual_revenue') }}">
                                @error('form.annual_revenue') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="form-text">{{ $usd($form['annual_revenue']) ? '≈ '.$usd($form['annual_revenue']) : '' }}</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="ep_gr">Revenue growth (last 12 months)</label>
                            <div class="input-group has-validation">
                                <input type="number" step="0.1" id="ep_gr" wire:model="form.revenue_growth" class="form-control {{ $err('form.revenue_growth') }}">
                                <span class="input-group-text">%</span>
                                @error('form.revenue_growth') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="ep_trac">Commercial traction <span class="opt">(optional)</span></label>
                            <textarea id="ep_trac" rows="2" wire:model="form.traction" class="form-control {{ $err('form.traction') }}" placeholder="Contracts, orders, growth in customers…"></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="ep_opp">Growth opportunity <span class="req">*</span></label>
                            <textarea id="ep_opp" rows="2" wire:model="form.growth_opportunity" class="form-control {{ $err('form.growth_opportunity') }}"></textarea>
                            @error('form.growth_opportunity') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                @endif

                {{-- ───────────── STEP 3 · INVESTMENT + GRANT ───────────── --}}
                @if ($step === 3)
                    <div class="form-section-head">
                        <span class="q-badge">{{ $sectionNo('investment') }}</span>
                        <div><h6>Investment requirement</h6><p>{{ $sections['investment'][2] }}. Amounts in Maloti.</p></div>
                    </div>
                    <div class="row g-3">
                        @foreach (['amount_sought' => ['Amount sought', true], 'project_cost' => ['Total project cost', false]] as $k => [$label, $required])
                            <div class="col-md-6">
                                <label class="form-label">{{ $label }} @if ($required) <span class="req">*</span> @endif</label>
                                <div class="input-group has-validation">
                                    <span class="input-group-text">M</span>
                                    <input type="number" min="0" step="0.01" wire:model.live.debounce.500ms="form.{{ $k }}" class="form-control {{ $err("form.$k") }}">
                                    @error("form.$k") <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="form-text">{{ $usd($form[$k]) ? '≈ '.$usd($form[$k]) : '' }}</div>
                            </div>
                        @endforeach
                        <div class="col-12">
                            <label class="form-label" for="ep_purp">Purpose <span class="req">*</span></label>
                            <textarea id="ep_purp" rows="2" wire:model="form.investment_purpose" class="form-control {{ $err('form.investment_purpose') }}"></textarea>
                            @error('form.investment_purpose') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        @foreach (['own_contribution' => ['Own contribution', false], 'investor_contribution' => ['Investor contribution', false], 'grant_requested' => ['CI grant requested', true]] as $k => [$label, $required])
                            <div class="col-md-4">
                                <label class="form-label">{{ $label }} @if ($required) <span class="req">*</span> @endif</label>
                                <div class="input-group has-validation">
                                    <span class="input-group-text">M</span>
                                    <input type="number" min="0" step="0.01" wire:model.live.debounce.500ms="form.{{ $k }}" class="form-control {{ $err("form.$k") }}">
                                    @error("form.$k") <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <label class="form-label mt-3">Use of funds <span class="req">*</span></label>
                    @error('form.use_of_funds') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                    @foreach ($form['use_of_funds'] as $i => $row)
                        <div class="row g-2 mb-2" wire:key="uof-{{ $i }}">
                            <div class="col-md-8">
                                <input wire:model="form.use_of_funds.{{ $i }}.item" placeholder="What the money is for" class="form-control {{ $err("form.use_of_funds.$i.item") }}">
                                @error("form.use_of_funds.$i.item") <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-3">
                                <div class="input-group has-validation">
                                    <span class="input-group-text">M</span>
                                    <input type="number" min="0" step="0.01" wire:model="form.use_of_funds.{{ $i }}.amount" class="form-control {{ $err("form.use_of_funds.$i.amount") }}">
                                    @error("form.use_of_funds.$i.amount") <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>
                            <div class="col-md-1 text-end"><button type="button" class="btn btn-icon btn-icon-danger" wire:click="removeRow('use_of_funds', {{ $i }})"><i class="bi bi-trash"></i></button></div>
                        </div>
                    @endforeach
                    <button type="button" class="btn btn-link btn-sm text-decoration-none px-0" wire:click="addRow('use_of_funds')"><i class="bi bi-plus-lg me-1"></i> Add line</button>

                    <div class="form-section-head mt-4">
                        <span class="q-badge">{{ $sectionNo('grant') }}</span>
                        <div><h6>Grant utilisation</h6><p>{{ $sections['grant'][2] }}.</p></div>
                    </div>
                    @error('form.grant_allocations') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                    @foreach ($form['grant_allocations'] as $i => $row)
                        <div class="row g-2 mb-2" wire:key="ga-{{ $i }}">
                            <div class="col-md-6">
                                <input wire:model="form.grant_allocations.{{ $i }}.item" placeholder="Item" class="form-control {{ $err("form.grant_allocations.$i.item") }}">
                                @error("form.grant_allocations.$i.item") <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-3">
                                <div class="input-group has-validation">
                                    <span class="input-group-text">M</span>
                                    <input type="number" min="0" step="0.01" wire:model.live.debounce.500ms="form.grant_allocations.{{ $i }}.amount" class="form-control {{ $err("form.grant_allocations.$i.amount") }}">
                                    @error("form.grant_allocations.$i.amount") <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>
                            <div class="col-md-2"><input wire:model="form.grant_allocations.{{ $i }}.timing" placeholder="e.g. Q1 2027" class="form-control"></div>
                            <div class="col-md-1 text-end"><button type="button" class="btn btn-icon btn-icon-danger" wire:click="removeRow('grant_allocations', {{ $i }})"><i class="bi bi-trash"></i></button></div>
                        </div>
                    @endforeach
                    <button type="button" class="btn btn-link btn-sm text-decoration-none px-0" wire:click="addRow('grant_allocations')"><i class="bi bi-plus-lg me-1"></i> Add item</button>

                    @php
                        $allocated = collect($form['grant_allocations'])->sum(fn ($r) => (float) ($r['amount'] ?? 0));
                        $grant     = (float) ($form['grant_requested'] ?: 0);
                    @endphp
                    <div class="callout {{ $grant > 0 && abs($allocated - $grant) < 0.01 ? 'callout-success' : '' }} small mt-2 mb-0 d-flex justify-content-between">
                        <span><i class="bi bi-info-circle me-1"></i> Allocations must add up to the CI grant requested.</span>
                        <strong>M {{ number_format($allocated) }} / M {{ number_format($grant) }}</strong>
                    </div>
                @endif

                {{-- ───────────── STEP 4 · INVESTOR + TRANSACTION + COMMITMENT ───────────── --}}
                @if ($step === 4)
                    <div class="form-section-head">
                        <span class="q-badge">{{ $sectionNo('investor') }}</span>
                        <div><h6>Investor profile</h6><p>{{ $sections['investor'][2] }}.</p></div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="ep_in">Investor name <span class="req">*</span></label>
                            <input id="ep_in" wire:model="form.investor_name" class="form-control {{ $err('form.investor_name') }}">
                            @error('form.investor_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="ep_it">Investor type <span class="req">*</span></label>
                            <select id="ep_it" wire:model="form.investor_type" class="form-select {{ $err('form.investor_type') }}">
                                <option value="">Select…</option>
                                @foreach ($M::INVESTOR_TYPES as $o) <option value="{{ $o }}">{{ $o }}</option> @endforeach
                            </select>
                            @error('form.investor_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="ep_ic">Country of registration</label>
                            <input id="ep_ic" wire:model="form.investor_country" class="form-control {{ $err('form.investor_country') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="ep_icon">Contact person &amp; email</label>
                            <input id="ep_icon" wire:model="form.investor_contact" class="form-control {{ $err('form.investor_contact') }}" placeholder="Name · email">
                        </div>
                    </div>

                    <label class="form-label mt-3">Nature of participation <span class="req">*</span></label>
                    <div class="choice-group cols-3 {{ $err('form.participation') }}">
                        @foreach ($M::PARTICIPATION as $k => $label)
                            <label class="choice"><input type="radio" wire:model="form.participation" value="{{ $k }}"><span class="choice-box"><span class="choice-radio"></span>{{ $label }}</span></label>
                        @endforeach
                    </div>
                    @error('form.participation') <div class="text-danger small mt-1">{{ $message }}</div> @enderror

                    <label class="form-label mt-3">Eligibility <span class="req">*</span></label>
                    <div class="choice-group">
                        @foreach ($M::INVESTOR_ELIGIBILITY as $k => $text)
                            <label class="choice"><input type="checkbox" wire:model="form.investor_eligibility.{{ $k }}"><span class="choice-box"><span class="choice-check"></span>{{ $text }}</span></label>
                        @endforeach
                    </div>
                    @if ($errors->has('form.investor_eligibility.*')) <div class="text-danger small mt-1">{{ $errors->first('form.investor_eligibility.*') }}</div> @endif

                    <div class="form-section-head mt-4">
                        <span class="q-badge">{{ $sectionNo('transaction') }}</span>
                        <div><h6>Transaction details</h6><p>{{ $sections['transaction'][2] }}.</p></div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label" for="ep_instr">Instrument <span class="req">*</span></label>
                            <select id="ep_instr" wire:model="form.instrument" class="form-select {{ $err('form.instrument') }}">
                                <option value="">Select…</option>
                                @foreach ($M::PARTICIPATION as $k => $label) <option value="{{ $k }}">{{ $label }}</option> @endforeach
                            </select>
                            @error('form.instrument') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="ep_stake">Stake offered</label>
                            <div class="input-group has-validation">
                                <input type="number" min="0" max="100" step="0.01" id="ep_stake" wire:model="form.stake_pct" class="form-control {{ $err('form.stake_pct') }}">
                                <span class="input-group-text">%</span>
                                @error('form.stake_pct') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="ep_val">Post-money valuation</label>
                            <div class="input-group has-validation">
                                <span class="input-group-text">M</span>
                                <input type="number" min="0" step="0.01" id="ep_val" wire:model="form.valuation" class="form-control {{ $err('form.valuation') }}">
                                @error('form.valuation') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label" for="ep_str">Proposed structure <span class="req">*</span></label>
                            <input id="ep_str" wire:model="form.structure" class="form-control {{ $err('form.structure') }}" placeholder="e.g. Minority equity stake with board seat">
                            @error('form.structure') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="ep_ts">Transaction status <span class="req">*</span></label>
                            <select id="ep_ts" wire:model="form.transaction_status" class="form-select {{ $err('form.transaction_status') }}">
                                <option value="">Select…</option>
                                @foreach ($M::TRANSACTION_STATUSES as $k => $label) <option value="{{ $k }}">{{ $label }}</option> @endforeach
                            </select>
                            @error('form.transaction_status') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="ep_kt">Key terms <span class="opt">(optional)</span></label>
                            <textarea id="ep_kt" rows="3" wire:model="form.key_terms" class="form-control {{ $err('form.key_terms') }}" placeholder="One per line, e.g. Investor board seat: 1 of 4"></textarea>
                        </div>
                    </div>

                    <div class="form-section-head mt-4">
                        <span class="q-badge">{{ $sectionNo('commitment') }}</span>
                        <div><h6>Investor commitment</h6><p>{{ $sections['commitment'][2] }}. Upload at least one.</p></div>
                    </div>
                    @error('commitment') <div class="callout callout-warning small mb-2">{{ $message }}</div> @enderror
                    @foreach (\App\Models\EnterpriseDocument::typesFor('commitment') as $type => [$label])
                        @include('profile.partials.upload-row', ['type' => $type, 'label' => $label, 'doc' => $docs[$type] ?? null, 'withDate' => true, 'hint' => 'Date signed / received'])
                    @endforeach
                @endif

                {{-- ───────────── STEP 5 · FINANCIALS + DOCUMENTS ───────────── --}}
                @if ($step === 5)
                    <div class="form-section-head">
                        <span class="q-badge">{{ $sectionNo('financials') }}</span>
                        <div><h6>Financial information</h6><p>{{ $sections['financials'][2] }}. Amounts in Maloti.</p></div>
                    </div>
                    @error('form.financials') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                    <div class="table-responsive">
                        <table class="table table-modern table-sm align-middle">
                            <thead>
                                <tr>
                                    <th style="min-width: 130px;"></th>
                                    @foreach ($form['financials'] as $i => $r)
                                        <th><input wire:model="form.financials.{{ $i }}.year" class="form-control form-control-sm fw-semibold" placeholder="FY"></th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach (['revenue' => 'Revenue', 'gross_profit' => 'Gross profit', 'net_profit' => 'Net profit', 'total_assets' => 'Total assets'] as $k => $label)
                                    <tr>
                                        <td class="fw-semibold text-navy small">{{ $label }}</td>
                                        @foreach ($form['financials'] as $i => $r)
                                            <td><input type="number" step="0.01" wire:model="form.financials.{{ $i }}.{{ $k }}" class="form-control form-control-sm {{ $err("form.financials.$i.$k") }}"></td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <label class="form-label">Records &amp; statutory information</label>
                    <div class="choice-group cols-2">
                        @foreach ($M::STATUTORY as $k => $text)
                            <label class="choice"><input type="checkbox" wire:model="form.statutory.{{ $k }}"><span class="choice-box"><span class="choice-check"></span>{{ $text }}</span></label>
                        @endforeach
                    </div>

                    <div class="form-section-head mt-4">
                        <span class="q-badge">{{ $sectionNo('documents') }}</span>
                        <div><h6>Supporting documentation</h6><p>{{ $sections['documents'][2] }}. PDF, JPG or PNG, max 10 MB each.</p></div>
                    </div>
                    @error('documents') <div class="callout callout-warning small mb-2">{{ $message }}</div> @enderror
                    @foreach (\App\Models\EnterpriseDocument::typesFor('supporting') as $type => [$label])
                        @include('profile.partials.upload-row', [
                            'type' => $type, 'label' => $label, 'doc' => $docs[$type] ?? null, 'withDate' => false,
                            'hint' => in_array($type, \App\Models\EnterpriseDocument::REQUIRED_SUPPORTING, true) ? 'Required' : 'Optional',
                        ])
                    @endforeach
                @endif

                {{-- ───────────── STEP 6 · DECLARATION + REVIEW ───────────── --}}
                @if ($step === 6)
                    <div class="form-section-head">
                        <span class="q-badge">{{ $sectionNo('declaration') }}</span>
                        <div><h6>Declarations</h6><p>{{ $sections['declaration'][2] }}.</p></div>
                    </div>
                    <div class="choice-group">
                        @foreach ($M::DECLARATIONS as $k => $text)
                            <label class="choice"><input type="checkbox" wire:model="form.declarations.{{ $k }}"><span class="choice-box"><span class="choice-check"></span>{{ $text }}</span></label>
                        @endforeach
                    </div>
                    @if ($errors->has('form.declarations.*')) <div class="text-danger small mt-1">{{ $errors->first('form.declarations.*') }}</div> @endif

                    <div class="row g-3 mt-1">
                        <div class="col-md-5">
                            <label class="form-label" for="ep_dn">Full name <span class="req">*</span></label>
                            <input id="ep_dn" wire:model="form.declared_name" class="form-control {{ $err('form.declared_name') }}">
                            @error('form.declared_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="ep_dp">Position <span class="req">*</span></label>
                            <input id="ep_dp" wire:model="form.declared_position" class="form-control {{ $err('form.declared_position') }}" placeholder="e.g. Managing Director">
                            @error('form.declared_position') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="ep_dd">Date <span class="req">*</span></label>
                            <input type="date" id="ep_dd" wire:model="form.declared_on" max="{{ now()->toDateString() }}" class="form-control {{ $err('form.declared_on') }}">
                            @error('form.declared_on') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    @php
                        $saved  = $this->profileId ? \App\Models\EnterpriseProfile::with('documents')->find($this->profileId) : null;
                        $status = $saved?->sectionStatus() ?? array_fill_keys(array_keys($sections), false);
                    @endphp
                    <div class="form-section-head mt-4">
                        <span class="q-badge"><i class="bi bi-eye"></i></span>
                        <div><h6>Review</h6><p>Status of each section as last saved.</p></div>
                    </div>
                    <div class="row g-2">
                        @foreach ($sections as $key => [$title, $icon, $desc, $stepNo])
                            <div class="col-md-6">
                                <div class="file-row mb-0">
                                    <span class="file-row-icon"><i class="bi {{ $icon }}"></i></span>
                                    <div class="flex-grow-1 small fw-semibold text-navy">{{ $title }}</div>
                                    <span class="pill {{ $status[$key] ? 'pill-ok' : 'pill-warn' }} me-1">{{ $status[$key] ? 'Complete' : 'Incomplete' }}</span>
                                    @if ($stepNo !== 6)
                                        <button type="button" class="btn btn-icon" title="Edit" wire:click="goToStep({{ $stepNo }})"><i class="bi bi-pencil"></i></button>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- ============================ FOOTER ============================ --}}
            <div class="card-footer d-flex flex-wrap align-items-center gap-2 py-3">
                <button type="button" class="btn btn-link text-decoration-none text-secondary px-0" wire:click="saveDraft" wire:loading.attr="disabled" wire:target="saveDraft">
                    <span wire:loading.remove wire:target="saveDraft"><i class="bi bi-save me-1"></i> Save draft</span>
                    <span wire:loading wire:target="saveDraft"><span class="spinner-border spinner-border-sm me-1"></span> Saving…</span>
                </button>
                <div class="ms-auto d-flex gap-2">
                    @if ($step > 1)
                        <button type="button" class="btn btn-outline-secondary" wire:click="back"><i class="bi bi-arrow-left me-1"></i> Back</button>
                    @endif
                    @if ($step < count($steps))
                        <button type="submit" class="btn btn-brand" wire:loading.attr="disabled" wire:target="next">
                            <span wire:loading.remove wire:target="next">Save &amp; continue <i class="bi bi-arrow-right ms-1"></i></span>
                            <span wire:loading wire:target="next"><span class="spinner-border spinner-border-sm me-1"></span> Saving…</span>
                        </button>
                    @else
                        <button type="submit" class="btn btn-accent" wire:loading.attr="disabled" wire:target="finish">
                            <span wire:loading.remove wire:target="finish"><i class="bi bi-check-lg me-1"></i> Sign &amp; save profile</span>
                            <span wire:loading wire:target="finish"><span class="spinner-border spinner-border-sm me-1"></span> Saving…</span>
                        </button>
                    @endif
                </div>
            </div>
        </form>
    </div>
    <livewire:notify />
</div>