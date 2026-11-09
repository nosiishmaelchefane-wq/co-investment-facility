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
    protected const BOOLEANS = ['women_owned_managed', 'youth_owned_managed', 'obfc_registered', 'foreign_registered', 'has_investor',
                                'foreign_owned', 'primary_ops_lesotho', 'ownership_dispute'];

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
                in_array($field, ['declaration_agreed', 'es_confirmed'], true) => (bool) $v,
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
            $form['business_models'] = $p->businessModels();
            $form['shareholder_list'] = collect($p->shareholder_list ?? [])
                ->map(fn ($r) => array_merge(EnterpriseProfile::BLANK_SHAREHOLDER, array_map(fn ($v) => $v ?? '', array_intersect_key((array) $r, EnterpriseProfile::BLANK_SHAREHOLDER))))
                ->values()->all() ?: [EnterpriseProfile::BLANK_SHAREHOLDER];
        }
        if ($section === 'legal') {
            $form['markets'] = (array) ($p->markets ?? []);
            if ($form['obfc_status'] === '' && $p->obfc_registered !== null) {
                $form['obfc_status'] = $p->obfc_registered ? 'yes' : 'no';
            }
        }
        if ($section === 'people') {
            $form['key_people'] = collect($p->key_people ?? [])
                ->map(fn ($r) => array_merge(EnterpriseProfile::BLANK_KEY_PERSON, array_map(fn ($v) => $v ?? '', array_intersect_key((array) $r, EnterpriseProfile::BLANK_KEY_PERSON))))
                ->values()->all() ?: [EnterpriseProfile::BLANK_KEY_PERSON];
            $answers = (array) ($p->es_checklist ?? []);
            $notes   = (array) ($p->es_notes ?? []);
            $form['es_checklist'] = collect(EnterpriseProfile::ES_CHECKLIST)->map(fn ($q, $k) => isset(EnterpriseProfile::ES_ANSWERS[$answers[$k] ?? '']) ? $answers[$k] : '')->all();
            $form['es_notes']     = collect(EnterpriseProfile::ES_CHECKLIST)->map(fn ($q, $k) => (string) ($notes[$k] ?? ''))->all();
        }
        if ($section === 'business') {
            // Older profiles: investors already listed → "Yes, I have an investor"
            if ($form['has_investor'] === '' && $p->investorRows() !== []) {
                $form['has_investor'] = 'yes';
            }
            $form['round_investors'] = collect($p->round_investors ?? [])
                ->map(fn ($r) => ['name' => $r['name'] ?? '', 'id_number' => $r['id_number'] ?? '', 'country' => $r['country'] ?? '', 'ticket' => $r['ticket'] ?? ''])
                ->values()->all() ?: [['name' => '', 'id_number' => '', 'country' => '', 'ticket' => '']];

            $money = fn ($v) => is_numeric($v ?? null) ? (string) round((float) $v) : '';
            $form['grant_budget'] = collect($p->budgetRows())->map(fn ($r) => [
                'category' => (string) ($r['category'] ?? ''),
                'private'  => $money($r['private'] ?? null),
                'grant'    => $money($r['grant'] ?? null),
                'items'    => collect($r['items'] ?? [])->map(fn ($it) => [
                    'name'    => (string) ($it['name'] ?? ''),
                    'private' => $money($it['private'] ?? null),
                    'grant'   => $money($it['grant'] ?? null),
                ])->values()->all(),
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

    /** Grant utilisation budget: break a category into sub-categories */
    public function addBudgetItem(int $bi): void
    {
        if (isset($this->form['grant_budget'][$bi])) {
            $this->form['grant_budget'][$bi]['items'][] = ['name' => '', 'private' => '', 'grant' => ''];
        }
    }

    public function removeBudgetItem(int $bi, int $ii): void
    {
        unset($this->form['grant_budget'][$bi]['items'][$ii]);
        $this->form['grant_budget'][$bi]['items'] = array_values($this->form['grant_budget'][$bi]['items'] ?? []);
    }

    public function addKeyPerson(): void
    {
        $this->form['key_people'][] = EnterpriseProfile::BLANK_KEY_PERSON;
    }

    public function removeKeyPerson(int $i): void
    {
        unset($this->form['key_people'][$i]);
        $this->form['key_people'] = array_values($this->form['key_people']) ?: [EnterpriseProfile::BLANK_KEY_PERSON];
    }

    public function addShareholder(): void
    {
        $this->form['shareholder_list'][] = EnterpriseProfile::BLANK_SHAREHOLDER;
    }

    public function removeShareholder(int $i): void
    {
        unset($this->form['shareholder_list'][$i]);
        $this->form['shareholder_list'] = array_values($this->form['shareholder_list']) ?: [EnterpriseProfile::BLANK_SHAREHOLDER];
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
            'form.es_notes.*.required'          => 'Please add the explanation for this answer.',
            'form.es_records_system.required'   => 'Choose what you use to keep your financial records.',
            'form.es_confirmed.accepted'        => 'Please confirm your ESG checklist answers.',
            'form.expected_jobs_women.lte'      => 'Jobs for women cannot be more than the expected additional jobs.',
            'form.expected_jobs_youth.lte'      => 'Jobs for youth cannot be more than the expected additional jobs.',
            'form.foreign_registration_no.required_if' => 'Enter the registration number in the other country.',
            'form.foreign_owned_pct.required_if' => 'Enter the percentage owned by foreign shareholders.',
            'form.ownership_dispute_details.required_if' => 'Please describe the dispute, litigation or conflict.',
            'form.sector_other.required_if'     => 'Type your sector.',
            'form.round_investors.*.ticket.required_with' => 'Enter this investor\'s ticket size.',
            'form.round_investors.*.id_number.required_with' => 'Enter this investor\'s ID / passport number (or company registration number).',
        ], $this->fieldLabels());

        if ($section === 'company' && ! $this->shareholdersAreValid()) {
            return;
        }

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
        if (($this->form['has_investor'] ?? '') !== 'yes') {
            return null;
        }

        $tickets = collect($this->form['round_investors'] ?? [])->sum(fn ($r) => is_numeric($r['ticket'] ?? null) ? (float) $r['ticket'] : 0);
        $amount  = $tickets > 0 ? $tickets : (is_numeric($this->form['round_amount'] ?? null) ? (float) $this->form['round_amount'] : 0);

        return $amount > 0 ? $amount : null;
    }

    /**
     * Grant utilisation budget (when there is an investor):
     *   private column = what the investor(s) put in · CAFI grant column = the matching grant
     */
    protected function budgetIsValid(): bool
    {
        // Sub-categories must add up to their category, column by column
        $subOk = true;
        foreach ($this->form['grant_budget'] ?? [] as $bi => $row) {
            $items = collect($row['items'] ?? [])->filter(fn ($it) => filled($it['name'] ?? null) || is_numeric($it['private'] ?? null) || is_numeric($it['grant'] ?? null));
            if ($items->isEmpty()) {
                continue;
            }
            foreach (['private' => 'private investment', 'grant' => 'CAFI grant'] as $col => $label) {
                $sum    = (float) $items->sum(fn ($it) => is_numeric($it[$col] ?? null) ? (float) $it[$col] : 0);
                $target = is_numeric($row[$col] ?? null) ? (float) $row[$col] : 0;
                if (abs($sum - $target) >= 1) {
                    $this->addError("form.grant_budget.$bi.items_$col", "\"{$row['category']}\": the sub-categories add up to M ".number_format($sum)." of {$label}, but the category is M ".number_format($target).'.');
                    $subOk = false;
                }
            }
        }
        if (! $subOk) {
            $this->addError('form.grant_budget', 'Some sub-categories do not add up to their category amount — see the red rows in the budget.');
            return false;
        }

        $investor = $this->formInvestorAmount();
        if ($investor === null) {
            return true;
        }

        $totals = EnterpriseProfile::sumBudget($this->form['grant_budget'] ?? []);
        $grant  = (float) EnterpriseProfile::matchingGrant($investor);
        $ok     = true;

        if ($totals['private'] + $totals['grant'] <= 0) {
            $this->addError('form.grant_budget', 'Fill in the grant utilisation budget — how the investor\'s money and the CAFI grant will be spent.');
            return false;
        }
        if (abs($totals['private'] - $investor) >= 1) {
            $this->addError('form.grant_budget', 'The private investment column adds up to M '.number_format($totals['private']).' but your investor(s) are putting in M '.number_format($investor).'. Please make them equal.');
            $ok = false;
        }
        if (abs($totals['grant'] - $grant) >= 1) {
            $this->addError('form.grant_budget_grant', 'The CAFI grant column adds up to M '.number_format($totals['grant']).' but your matching grant is M '.number_format($grant).'. Please make them equal.');
            $ok = false;
        }

        // Grant spent outside Lesotho — at least the minimum share must be spent in Lesotho, otherwise justify it
        $base    = $totals['grant'] > 0 ? $totals['grant'] : $grant;
        $outside = $this->form['grant_outside_lesotho'] ?? '';
        if (! is_numeric($outside)) {
            $this->addError('form.grant_outside_lesotho', 'Enter how much of the CAFI grant will be spent outside Lesotho (enter 0 if it is all spent in Lesotho).');
            $ok = false;
        } elseif ((float) $outside > $base + 0.5) {
            $this->addError('form.grant_outside_lesotho', 'This cannot be more than the CAFI grant (M '.number_format($base).').');
            $ok = false;
        } elseif (($share = EnterpriseProfile::lesothoShare($base, (float) $outside)) !== null
            && $share < EnterpriseProfile::minLesothoPct()
            && blank($this->form['grant_outside_justification'] ?? null)) {
            $this->addError('form.grant_outside_justification', 'Only '.rtrim(rtrim(number_format($share, 1), '0'), '.').'% of the grant is spent in Lesotho (the minimum is '
                .(int) EnterpriseProfile::minLesothoPct().'%). Explain why the rest has to be spent outside Lesotho.');
            $ok = false;
        }

        return $ok;
    }

    /** Shareholders: the table or the uploaded list; the table may not add up to more than 100% */
    protected function shareholdersAreValid(): bool
    {
        $rows  = collect($this->form['shareholder_list'] ?? [])->filter(fn ($r) => filled($r['name'] ?? null));
        $total = $rows->sum(fn ($r) => is_numeric($r['percent'] ?? null) ? (float) $r['percent'] : 0);

        if ($rows->isEmpty() && ! $this->profile->document('shareholders_list')) {
            $this->addError('form.shareholder_list', 'Add your shareholders in the table, or upload the list of shareholders below.');
            return false;
        }
        if ($total > 100.0001) {
            $this->addError('form.shareholder_list', 'The shareholdings add up to '.rtrim(rtrim(number_format($total, 2), '0'), '.').'% — they cannot be more than 100%.');
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
                in_array($field, ['declaration_agreed', 'es_confirmed'], true) => (bool) $v,
                $field === 'round_investors'           => collect($v ?? [])->filter(fn ($r) => filled($r['name'] ?? null))->map(fn ($r) => [
                                                              'name'    => trim($r['name']),
                                                              'id_number' => trim((string) ($r['id_number'] ?? '')),
                                                              'country' => trim((string) ($r['country'] ?? '')),
                                                              'ticket'  => filled($r['ticket'] ?? null) ? (float) $r['ticket'] : null,
                                                          ])->values()->all(),
                $field === 'investment_history'        => collect($v ?? [])->filter(fn ($r) => filled($r['amount'] ?? null) || filled($r['date'] ?? null))
                                                              ->map(fn ($r) => ['amount' => filled($r['amount'] ?? null) ? (float) $r['amount'] : null, 'date' => $r['date'] ?: null])
                                                              ->all() ?: null,
                $field === 'shareholder_list'          => collect($v ?? [])->filter(fn ($r) => filled($r['name'] ?? null))->map(fn ($r) => [
                                                              'name'        => trim($r['name']),
                                                              'id_number'   => trim((string) ($r['id_number'] ?? '')),
                                                              'nationality' => trim((string) ($r['nationality'] ?? '')),
                                                              'gender'      => (string) ($r['gender'] ?? ''),
                                                              'age'         => is_numeric($r['age'] ?? null) ? (int) $r['age'] : null,
                                                              'percent'     => is_numeric($r['percent'] ?? null) ? (float) $r['percent'] : null,
                                                          ])->values()->all() ?: null,
                $field === 'key_people'                => collect($v ?? [])->filter(fn ($r) => filled($r['name'] ?? null))->map(fn ($r) => [
                                                              'name'        => trim($r['name']),
                                                              'position'    => trim((string) ($r['position'] ?? '')),
                                                              'years'       => is_numeric($r['years'] ?? null) ? (float) $r['years'] : null,
                                                              'nationality' => trim((string) ($r['nationality'] ?? '')),
                                                          ])->values()->all() ?: null,
                $field === 'grant_budget'              => collect($v ?? [])->filter(fn ($r) => filled($r['category'] ?? null))->map(fn ($r) => array_filter([
                                                              'category' => trim($r['category']),
                                                              'private'  => is_numeric($r['private'] ?? null) ? (float) $r['private'] : null,
                                                              'grant'    => is_numeric($r['grant'] ?? null) ? (float) $r['grant'] : null,
                                                              'items'    => collect($r['items'] ?? [])->filter(fn ($it) => filled($it['name'] ?? null))->map(fn ($it) => [
                                                                                'name'    => trim($it['name']),
                                                                                'private' => is_numeric($it['private'] ?? null) ? (float) $it['private'] : null,
                                                                                'grant'   => is_numeric($it['grant'] ?? null) ? (float) $it['grant'] : null,
                                                                            ])->values()->all(),
                                                          ], fn ($val, $k) => $k !== 'items' || $val !== [], ARRAY_FILTER_USE_BOTH))->values()->all() ?: null,
                $field === 'es_checklist'              => collect($v ?? [])->filter(fn ($a, $k) => isset(EnterpriseProfile::ES_CHECKLIST[$k]) && isset(EnterpriseProfile::ES_ANSWERS[$a]))->all(),
                $field === 'es_notes'                  => collect($v ?? [])->filter(fn ($t, $k) => isset(EnterpriseProfile::ES_CHECKLIST[$k])
                                                              && EnterpriseProfile::esFollowUp($k, $f['es_checklist'][$k] ?? null) !== null && filled($t))
                                                              ->map(fn ($t) => trim($t))->all() ?: null,
                is_array($v)                           => array_values($v),
                default                                => $v === '' ? null : $v,
            };
        }

        // Clear follow-up answers that no longer apply
        if ($section === 'company' && (($values['prior_cafi_support'] ?? []) === [] || $values['prior_cafi_support'] === ['none'])) {
            $values['prior_support_details'] = null;
        }
        if ($section === 'people' && ($values['es_checklist']['records'] ?? null) !== 'yes') {
            $values['es_records_system'] = null;
        }
        if ($section === 'company' && $values['sector'] !== 'Other') {
            $values['sector_other'] = null;
        }
        if ($section === 'business' && $values['has_investor'] === false) {
            foreach (['round_amount', 'participation', 'investor_type', 'transaction_status', 'grant_budget', 'grant_outside_lesotho', 'grant_outside_justification', ...EnterpriseProfile::BANK_FIELDS] as $f) {
                $values[$f] = null;
            }
            $values['round_investors'] = [];
        }
        if ($section === 'business' && array_key_exists('grant_outside_lesotho', $values)) {
            $bGrant = EnterpriseProfile::sumBudget($values['grant_budget'] ?? [])['grant'];
            $share  = EnterpriseProfile::lesothoShare($bGrant > 0 ? $bGrant : EnterpriseProfile::matchingGrant($this->formInvestorAmount()),
                is_numeric($values['grant_outside_lesotho']) ? (float) $values['grant_outside_lesotho'] : null);
            if ($share === null || $share >= EnterpriseProfile::minLesothoPct()) {
                $values['grant_outside_justification'] = null;   // only kept when it is needed
            } elseif (is_string($values['grant_outside_justification'] ?? null)) {
                $values['grant_outside_justification'] = trim($values['grant_outside_justification']);
            }
        }
        if ($section === 'legal') {
            if ($values['obfc_status'] !== 'yes') {
                $values['registration_no'] = null;
            }
            if ($values['foreign_registered'] !== true) {
                $values['foreign_country'] = null;
                $values['foreign_registration_no'] = null;
            }
            if ($values['foreign_owned'] !== true) {
                $values['foreign_owned_pct'] = null;
            }
        }
        if ($section === 'people' && $values['ownership_dispute'] !== true) {
            $values['ownership_dispute_details'] = null;
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
        $hasInvestor = ($this->form['has_investor'] ?? '') === 'yes';
        $words = fn (int $max) => function ($attribute, $value, $fail) use ($max) {
            if (str_word_count(strip_tags((string) $value)) > $max) {
                $fail("Please keep this to {$max} words or fewer.");
            }
        };

        return match ($section) {
            'company' => [
                'form.registered_name'       => ['required', 'string', 'max:255'],
                'form.short_name'            => ['nullable', 'string', 'max:120'],
                'form.month_commenced'       => ['required', 'integer', 'between:1,12', function ($attribute, $value, $fail) {
                                                    if (is_numeric($value) && (int) ($this->form['year_established'] ?? 0) === (int) date('Y') && (int) $value > (int) date('n')) {
                                                        $fail('The month cannot be in the future.');
                                                    }
                                                }],
                'form.year_established'      => ['required', 'integer', 'between:1900,'.date('Y')],
                'form.year_registered'       => [$this->profile->obfc_registered === true ? 'required' : 'nullable', 'integer', 'between:1900,'.date('Y')],
                'form.website'               => ['nullable', 'string', 'max:255'],
                'form.address'               => ['required', 'string', 'max:255'],
                'form.city'                  => ['required', 'string', 'max:80'],
                'form.district'              => ['required', Rule::in(EnterpriseProfile::DISTRICTS)],
                'form.sector'                => ['required', Rule::in(EnterpriseProfile::SECTORS)],
                'form.sector_other'          => ['required_if:form.sector,Other', 'nullable', 'string', 'max:120'],
                'form.sub_sector'            => ['required', 'string', 'max:120'],
                'form.business_models'       => ['required', 'array', 'min:1'],
                'form.business_models.*'     => [Rule::in(array_keys(EnterpriseProfile::BUSINESS_MODELS))],
                'form.shareholder_list'               => ['array'],
                'form.shareholder_list.*.name'        => ['nullable', 'string', 'max:255'],
                'form.shareholder_list.*.id_number'   => ['required_with:form.shareholder_list.*.name', 'nullable', 'string', 'max:40'],
                'form.shareholder_list.*.nationality' => ['required_with:form.shareholder_list.*.name', 'nullable', 'string', 'max:60'],
                'form.shareholder_list.*.gender'      => ['required_with:form.shareholder_list.*.name', 'nullable', Rule::in(EnterpriseProfile::GENDERS)],
                'form.shareholder_list.*.age'         => ['required_with:form.shareholder_list.*.name', 'nullable', 'integer', 'between:0,120'],
                'form.shareholder_list.*.percent'     => ['required_with:form.shareholder_list.*.name', 'nullable', 'numeric', 'gt:0', 'max:100'],
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
                'form.authorised_rep'        => ['required', 'string', 'max:255'],
                'form.alternative_contact'   => ['nullable', 'string', 'max:500'],
                'form.secondary_name'        => ['required', 'string', 'max:255'],
                'form.secondary_title'       => ['required', 'string', 'max:120'],
                'form.secondary_email'       => ['required', 'email', 'max:255'],
                'form.secondary_phone'       => ['required', 'string', 'max:30'],
            ],
            'legal' => [
                'form.obfc_status'        => ['required', Rule::in(array_keys(EnterpriseProfile::YES_NO_PROGRESS))],
                'form.legal_form'         => ['required', Rule::in(EnterpriseProfile::LEGAL_FORMS)],
                'form.registration_no'    => ['required_if:form.obfc_status,yes', 'nullable', 'string', 'max:60'],
                'form.foreign_registered' => $yesNo,
                'form.foreign_country'    => ['required_if:form.foreign_registered,yes', 'nullable', 'string', 'max:80'],
                'form.foreign_registration_no' => ['required_if:form.foreign_registered,yes', 'nullable', 'string', 'max:60'],
                'form.foreign_owned'      => $yesNo,
                'form.foreign_owned_pct'  => ['required_if:form.foreign_owned,yes', 'nullable', 'numeric', 'gt:0', 'max:100'],
                'form.primary_ops_lesotho'=> $yesNo,
                'form.rsl_tax_file'       => ['required', Rule::in(array_keys(EnterpriseProfile::YES_NO_PROGRESS))],
                'form.markets'            => ['required', 'array', 'min:1'],
                'form.markets.*'          => [Rule::in(array_keys(EnterpriseProfile::MARKETS))],
            ],
            'people' => [
                'form.employees_prev_year' => $count,
                'form.employees'           => [...$count, function ($attribute, $value, $fail) {
                                                  $parts = collect(['ft_male', 'ft_female', 'pt_male', 'pt_female'])->sum(fn ($f) => (int) ($this->form[$f] ?? 0));
                                                  if (is_numeric($value) && (int) $value !== $parts) {
                                                      $fail("The current-year total ({$value}) must equal the breakdown below ({$parts}).");
                                                  }
                                              }],
                'form.ft_male'             => $count,
                'form.ft_female'           => $count,
                'form.pt_male'             => $count,
                'form.pt_female'           => $count,
                'form.youth_male'          => $count,
                'form.youth_female'        => [...$count, function ($attribute, $value, $fail) {
                                                  $youth = (int) ($this->form['youth_male'] ?? 0) + (int) $value;
                                                  $total = (int) ($this->form['employees'] ?? 0);
                                                  if ($youth > $total) {
                                                      $fail("Youth employees ({$youth}) cannot be more than the total employees ({$total}) — they are part of the full-time / part-time figures.");
                                                  }
                                              }],
                'form.expected_jobs'       => $count,
                'form.expected_jobs_women' => [...$count, 'lte:form.expected_jobs'],
                'form.expected_jobs_youth' => [...$count, 'lte:form.expected_jobs'],
                'form.key_people'               => ['array', function ($attribute, $value, $fail) {
                                                       if (! collect($value ?? [])->contains(fn ($r) => filled($r['name'] ?? null))) {
                                                           $fail('Add at least one director or key manager.');
                                                       }
                                                   }],
                'form.key_people.*.name'        => ['nullable', 'string', 'max:255'],
                'form.key_people.*.position'    => ['required_with:form.key_people.*.name', 'nullable', 'string', 'max:120'],
                'form.key_people.*.years'       => ['required_with:form.key_people.*.name', 'nullable', 'numeric', 'min:0', 'max:80'],
                'form.key_people.*.nationality' => ['required_with:form.key_people.*.name', 'nullable', 'string', 'max:60'],
                'form.ownership_dispute'        => $yesNo,
                'form.ownership_dispute_details'=> ['required_if:form.ownership_dispute,yes', 'nullable', 'string', 'max:1000'],
                'form.management'          => ['nullable', 'string', 'max:2000'],
                ...collect(EnterpriseProfile::ES_CHECKLIST)->mapWithKeys(fn ($q, $k) => [
                    "form.es_checklist.$k" => ['required', Rule::in(array_keys(EnterpriseProfile::ES_ANSWERS))],
                    "form.es_notes.$k"     => [(EnterpriseProfile::esFollowUp($k, $this->form['es_checklist'][$k] ?? null)[1] ?? false) ? 'required' : 'nullable', 'string', 'max:1000'],
                ])->all(),
                'form.es_records_system' => [($this->form['es_checklist']['records'] ?? '') === 'yes' ? 'required' : 'nullable', Rule::in(array_keys(EnterpriseProfile::ES_RECORD_SYSTEMS))],
                'form.es_confirmed'      => ['accepted'],
            ],
            'business' => [
                'form.activity'                    => ['required', 'string', $words(100)],
                'form.company_stage'               => ['required', Rule::in(array_keys(EnterpriseProfile::STAGES))],
                'form.products_services'           => ['required', 'string', 'max:2000'],
                'form.customers'                   => ['required', 'string', 'max:2000'],
                'form.usp'                         => ['nullable', 'string', 'max:2000'],
                'form.marketing_strategy'          => ['nullable', 'string', 'max:2000'],
                'form.competition'                 => ['nullable', 'string', 'max:2000'],
                'form.annual_revenue'              => $money('annual_revenue'),
                'form.has_investor'                => $yesNo,
                'form.round_amount'                => [$hasInvestor ? 'required' : 'nullable', 'numeric', 'gt:0', 'max:9999999999999'],
                'form.round_investors'             => [$hasInvestor ? 'required' : 'nullable', 'array', function ($attribute, $value, $fail) use ($hasInvestor) {
                                                          $shared = collect($value ?? [])->contains(fn ($r) => filled($r['name'] ?? null) && is_numeric($r['ticket'] ?? null) && (float) $r['ticket'] > 0);
                                                          if ($hasInvestor && ! $shared) {
                                                              $fail('Add at least one participating investor with their ticket size.');
                                                          }
                                                      }],
                'form.round_investors.*.name'      => ['nullable', 'string', 'max:255', 'required_with:form.round_investors.*.ticket,form.round_investors.*.country'],
                'form.round_investors.*.id_number' => ['nullable', 'required_with:form.round_investors.*.name', 'string', 'max:40'],
                'form.round_investors.*.country'   => ['nullable', 'string', 'max:80'],
                'form.round_investors.*.ticket'    => ['nullable', 'required_with:form.round_investors.*.name', 'numeric', 'gt:0', 'max:9999999999999'],
                'form.participation'               => [$hasInvestor ? 'required' : 'nullable', Rule::in(array_keys(EnterpriseProfile::PARTICIPATION))],
                'form.investor_type'               => ['nullable', Rule::in(EnterpriseProfile::INVESTOR_TYPES)],
                'form.transaction_status'          => ['nullable', Rule::in(array_keys(EnterpriseProfile::TRANSACTION_STATUSES))],
                'form.project_cost'                => ['required', 'numeric', 'gt:0', 'max:9999999999999', function ($attribute, $value, $fail) {
                                                          $grant = EnterpriseProfile::matchingGrant($this->formInvestorAmount());
                                                          if ($grant !== null && is_numeric($value) && (float) $value + 0.005 < $grant) {
                                                              $fail('The total project cost cannot be less than the CAFI matching grant (M '.number_format($grant).'). It must be equal to it or more.');
                                                          }
                                                      }],
                'form.investment_purpose'          => ['nullable', 'string', 'max:2000'],
                'form.investment_history.*.amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
                'form.investment_history.*.date'   => ['nullable', 'date', 'before_or_equal:today'],
                'form.grant_budget'                => ['nullable', 'array'],
                'form.grant_budget.*.private'      => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
                'form.grant_budget.*.grant'        => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
                'form.grant_budget.*.items'           => ['nullable', 'array', 'max:20'],
                'form.grant_budget.*.items.*.name'    => ['nullable', 'string', 'max:150', 'required_with:form.grant_budget.*.items.*.private,form.grant_budget.*.items.*.grant'],
                'form.grant_budget.*.items.*.private' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
                'form.grant_budget.*.items.*.grant'   => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
                'form.grant_outside_lesotho'          => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
                'form.grant_outside_justification'    => ['nullable', 'string', 'max:2000'],
                'form.bank_name'                      => ['nullable', 'string', 'max:120'],
                'form.bank_branch'                    => ['nullable', 'string', 'max:120'],
                'form.bank_account_name'              => ['nullable', 'string', 'max:200'],
                'form.bank_account_number'            => ['nullable', 'string', 'max:40', 'regex:/^[0-9 \-]+$/'],
                'form.bank_account_type'              => ['nullable', Rule::in(array_keys(EnterpriseProfile::ACCOUNT_TYPES))],
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
            'form.year_registered' => 'year of registration', 'form.sector_other' => 'your sector',
            'form.address' => 'address', 'form.city' => 'city / town', 'form.business_models' => 'business model',
            'form.shareholder_list.*.name' => 'shareholder name', 'form.shareholder_list.*.id_number' => 'ID / registration number',
            'form.shareholder_list.*.nationality' => 'nationality', 'form.shareholder_list.*.gender' => 'gender',
            'form.shareholder_list.*.age' => 'age', 'form.shareholder_list.*.percent' => '% share',
            'form.women_owned_managed' => 'women owned / managed', 'form.youth_owned_managed' => 'youth owned / managed',
            'form.basotho_owned_pct' => 'shares held by Basotho', 'form.women_owned_pct' => 'shares held by women', 'form.youth_owned_pct' => 'shares held by youth',
            'form.prior_cafi_support' => 'CAFI support received', 'form.prior_support_details' => 'support received',
            'form.contact_name' => 'contact name', 'form.contact_title' => 'title', 'form.contact_phone' => 'phone number', 'form.contact_email' => 'e-mail',
            'form.obfc_status' => 'OBFC registration', 'form.legal_form' => 'type of registration', 'form.registration_no' => 'registration number',
            'form.foreign_registration_no' => 'registration number in the other country', 'form.foreign_owned' => 'foreign ownership',
            'form.foreign_owned_pct' => 'foreign ownership %', 'form.primary_ops_lesotho' => 'primary operations in Lesotho', 'form.rsl_tax_file' => 'RSL tax file',
            'form.month_commenced' => 'month operations commenced', 'form.sub_sector' => 'sub-sector', 'form.authorised_rep' => 'authorised representative',
            'form.alternative_contact' => 'alternative contact details', 'form.secondary_name' => 'secondary contact name', 'form.secondary_title' => 'secondary contact title',
            'form.secondary_email' => 'secondary contact e-mail', 'form.secondary_phone' => 'secondary contact phone',
            'form.youth_male' => 'youth employees (male)', 'form.youth_female' => 'youth employees (female)',
            'form.expected_jobs' => 'expected additional jobs', 'form.expected_jobs_women' => 'expected jobs for women', 'form.expected_jobs_youth' => 'expected jobs for youth',
            'form.key_people.*.name' => 'name', 'form.key_people.*.position' => 'position', 'form.key_people.*.years' => 'years of service', 'form.key_people.*.nationality' => 'nationality',
            'form.ownership_dispute' => 'ownership dispute / litigation', 'form.ownership_dispute_details' => 'details of the dispute',
            'form.foreign_registered' => 'registration in another country', 'form.foreign_country' => 'country', 'form.markets' => 'current markets',
            'form.employees_prev_year' => 'total employees (previous year)', 'form.employees' => 'total employees (current year)',
            'form.ft_male' => 'full-time males', 'form.ft_female' => 'full-time females', 'form.pt_male' => 'part-time males', 'form.pt_female' => 'part-time females',
            'form.management' => 'management summary',
            'form.activity' => 'company summary', 'form.company_stage' => 'stage of company development',
            'form.products_services' => 'products & services', 'form.customers' => 'customers', 'form.has_investor' => 'whether you have an investor',
            'form.usp' => 'unique selling proposition', 'form.marketing_strategy' => 'marketing approach', 'form.competition' => 'competition',
            'form.annual_revenue' => 'annual revenue', 'form.round_amount' => 'current investment round', 'form.round_investors' => 'participating investors',
            'form.round_investors.*.name' => 'investor name', 'form.round_investors.*.id_number' => 'ID / passport number', 'form.round_investors.*.country' => 'residence country', 'form.round_investors.*.ticket' => 'ticket size',
            'form.participation' => 'financing type', 'form.investor_type' => 'lead investor type', 'form.transaction_status' => 'status of the deal',
            'form.project_cost' => 'total project cost', 'form.investment_purpose' => 'what the money is for',
            'form.investment_history.*.amount' => 'amount', 'form.investment_history.*.date' => 'date of last investment',
            'form.grant_budget.*.private' => 'private investment', 'form.grant_budget.*.grant' => 'CAFI grant',
            'form.grant_budget.*.items.*.name' => 'sub-category name', 'form.grant_budget.*.items.*.private' => 'private investment', 'form.grant_budget.*.items.*.grant' => 'CAFI grant',
            'form.declared_name' => 'name', 'form.declared_on' => 'date',
        ];
    }

    /* ---------------- Investments received ---------------- */

    protected function blankInvestment(): array
    {
        return ['investor_name' => '', 'investor_id_number' => '', 'investor_type' => '', 'investor_country' => '', 'amount' => '', 'invested_on' => '',
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
            'investor_name' => $i->investor_name, 'investor_id_number' => $i->investor_id_number ?? '', 'investor_type' => $i->investor_type ?? '', 'investor_country' => $i->investor_country ?? '',
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
            'inv.investor_id_number' => ['required', 'string', 'max:40'],
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
            'inv.investor_name' => 'investor name', 'inv.investor_id_number' => 'ID / passport number', 'inv.amount' => 'amount', 'inv.invested_on' => 'date of investment',
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

        $values = collect($this->inv)->map(fn ($v) => is_string($v) ? (trim($v) === '' ? null : trim($v)) : $v)->all();
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
                "uploads.$type.max"   => 'This file is too big — attachments can be at most '.(EnterpriseDocument::MAX_KB / 1024).' MB.',
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
        $ynp      = fn ($v) => $P::YES_NO_PROGRESS[$v ?? ''] ?? '—';
        $radios   = function (string $field, array $options, bool $live = false) {
            $html = '<div class="btn-group btn-group-sm flex-wrap">';
            foreach ($options as $v => $l) {
                $id = 'r-'.$field.'-'.$v;
                $html .= '<input type="radio" class="btn-check" id="'.e($id).'" value="'.e($v).'" wire:model'.($live ? '.live' : '').'="form.'.e($field).'">'
                       . '<label class="btn btn-outline-secondary" for="'.e($id).'">'.e($l).'</label>';
            }
            return $html.'</div>';
        };
        $answer   = fn ($a) => ['yes' => 'Yes', 'no' => 'No', 'na' => 'Not applicable'][$a ?? ''] ?? '—';

        // ⓘ hover help — texts live in EnterpriseProfile::HELP
        $tip = fn (string $key) => isset($P::HELP[$key])
            ? '<span class="tip" tabindex="0" role="note" aria-label="'.e($P::HELP[$key]).'" data-tip="'.e($P::HELP[$key]).'"><i class="bi bi-info-circle"></i></span>'
            : '';

        $docTip = fn (string $type) => isset($D::HELP[$type])
            ? '<span class="tip" tabindex="0" role="note" aria-label="'.e($D::HELP[$type]).'" data-tip="'.e($D::HELP[$type]).'"><i class="bi bi-info-circle"></i></span>'
            : '';

        $investments = $p->exists ? $p->investments : collect();
        $I = \App\Models\EnterpriseInvestment::class;

        // Which document rows to show (conditional ones follow the registration answers)
        $docVisible = fn (array $t) => match ($t[3]) {
            'registered'     => $p->obfc_registered !== false,
            'not_registered' => $p->obfc_registered === false,
            'foreign'        => $p->foreign_registered === true,
            'investor'       => $p->has_investor !== false,
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

    <div class="callout small mb-3 d-flex align-items-start gap-2">
        <i class="bi bi-info-circle-fill text-brand mt-1"></i>
        <div><strong>Need more detail?</strong> Hover over (or tap) the <i class="bi bi-info-circle"></i> icon next to any section, question or document to see what it means and what a good answer looks like.</div>
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
                                            {{ collect($missingD)->map(fn ($t) => $D::missingLabel($t))->implode(', ') }}</div>
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
                                <div class="col-md-4"><label class="form-label small fw-semibold">Month and year operations commenced {!! $tip('year_established') !!} <span class="req">*</span></label>
                                    <div class="input-group">
                                        <select class="form-select @error('form.month_commenced') is-invalid @enderror" wire:model.live="form.month_commenced">
                                            <option value="">Month…</option>@foreach ($P::MONTHS as $mn => $ml)<option value="{{ $mn }}">{{ $ml }}</option>@endforeach
                                        </select>
                                        <input type="number" min="1900" max="{{ date('Y') }}" class="form-control @error('form.year_established') is-invalid @enderror" wire:model.live.debounce.500ms="form.year_established" placeholder="Year">
                                    </div>
                                    @error('form.month_commenced') <div class="text-danger small">{{ $message }}</div> @enderror
                                    @error('form.year_established') <div class="text-danger small">{{ $message }}</div> @enderror
                                    @php
                                        $mcY = (int) ($form['year_established'] ?? 0); $mcM = (int) ($form['month_commenced'] ?? 0);
                                        $mcAge = ($mcY >= 1900 && $mcM >= 1) ? (int) \Illuminate\Support\Carbon::create($mcY, $mcM, 1)->diffInMonths(now()) : null;
                                    @endphp
                                    @if ($mcAge !== null && $mcAge < 6)
                                        <div class="small text-warning-emphasis mt-1"><i class="bi bi-exclamation-circle me-1"></i> Operating for less than 6 months — this will be flagged for review.</div>
                                    @endif
                                </div>
                                <div class="col-md-4"><label class="form-label small fw-semibold">Year of registration {!! $tip('year_registered') !!} @if ($p->obfc_registered === true)<span class="req">*</span>@endif</label>
                                    <input type="number" min="1900" max="{{ date('Y') }}" class="form-control @error('form.year_registered') is-invalid @enderror" wire:model="form.year_registered" placeholder="{{ $p->obfc_registered === false ? 'Not registered yet' : '' }}">
                                    @error('form.year_registered') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                                <div class="col-md-4"><label class="form-label small fw-semibold">Website {!! $tip('website') !!}</label>
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
                                        <select class="form-select @error('form.'.$f) is-invalid @enderror" wire:model.live="form.{{ $f }}">
                                            <option value="">Select…</option>@foreach ($opts as $o)<option>{{ $o }}</option>@endforeach
                                            @if (filled($form[$f] ?? null) && ! in_array($form[$f], $opts, true))<option>{{ $form[$f] }}</option>@endif
                                        </select>
                                        @error('form.'.$f) <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                                @endforeach
                                @if (($form['sector'] ?? '') === 'Other')
                                    <div class="col-md-6 offset-md-6"><label class="form-label small fw-semibold">Please specify your sector {!! $tip('sector_other') !!} <span class="req">*</span></label>
                                        <input type="text" class="form-control @error('form.sector_other') is-invalid @enderror" wire:model="form.sector_other" placeholder="e.g. Renewable energy">
                                        @error('form.sector_other') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                                @endif

                                <div class="col-md-6"><label class="form-label small fw-semibold">Sub-sector {!! $tip('sub_sector') !!} <span class="req">*</span></label>
                                    <input type="text" class="form-control @error('form.sub_sector') is-invalid @enderror" wire:model="form.sub_sector" placeholder="e.g. Fruit and vegetable processing">
                                    @error('form.sub_sector') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>

                                <div class="col-12"><label class="form-label small fw-semibold">Business model — select one or more {!! $tip('business_models') !!} <span class="req">*</span></label>
                                    <div class="d-flex flex-wrap gap-2">
                                        @foreach ($P::BUSINESS_MODELS as $k => $lbl)
                                            <input type="checkbox" class="btn-check" id="bm-{{ $k }}" value="{{ $k }}" wire:model="form.business_models" autocomplete="off">
                                            <label class="btn btn-sm btn-outline-secondary" for="bm-{{ $k }}"><i class="bi bi-check2 me-1"></i>{{ $lbl }}</label>
                                        @endforeach
                                    </div>
                                    @error('form.business_models') <div class="text-danger small">{{ $message }}</div> @enderror</div>

                                <div class="col-12"><label class="form-label small fw-semibold mb-1">Role of women and youth at the firm {!! $tip('women_youth_role') !!} <span class="req">*</span></label></div>
                                @foreach (['women_owned_managed' => 'Women owned or led', 'youth_owned_managed' => 'Youth (18–35) owned or led'] as $f => $lbl)
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

                                {{-- Shareholders: table, or upload the list below --}}
                                @php $shTotal = collect($form['shareholder_list'] ?? [])->filter(fn ($r) => filled($r['name'] ?? null))->sum(fn ($r) => is_numeric($r['percent'] ?? null) ? (float) $r['percent'] : 0); @endphp
                                <div class="col-12 border-top pt-3">
                                    <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                        <span class="fw-semibold text-navy small me-auto">List of shareholders {!! $tip('shareholder_list') !!} <span class="req">*</span></span>
                                        <span class="small {{ $shTotal > 100 ? 'text-danger fw-semibold' : ($shTotal == 100 ? 'text-success' : 'text-muted') }}">Total: {{ rtrim(rtrim(number_format($shTotal, 2), '0'), '.') }}%</span>
                                    </div>
                                    <div class="form-text mt-0 mb-2">Fill in the table <strong>or</strong> upload the list of shareholders as a document below this section.</div>
                                    <div class="table-responsive">
                                        <table class="table table-modern table-sm align-middle mb-1">
                                            <thead><tr><th style="min-width:160px">Name</th><th style="min-width:130px">ID / reg. no.</th><th style="min-width:120px">Nationality</th><th style="min-width:100px">Gender</th><th style="width:80px">Age</th><th style="width:100px">% share</th><th></th></tr></thead>
                                            <tbody>
                                                @foreach ($form['shareholder_list'] ?? [] as $si => $sh)
                                                    <tr wire:key="sh-{{ $si }}">
                                                        <td><input type="text" class="form-control form-control-sm @error('form.shareholder_list.'.$si.'.name') is-invalid @enderror" wire:model.blur="form.shareholder_list.{{ $si }}.name" placeholder="Full name or company"></td>
                                                        <td><input type="text" class="form-control form-control-sm @error('form.shareholder_list.'.$si.'.id_number') is-invalid @enderror" wire:model="form.shareholder_list.{{ $si }}.id_number"></td>
                                                        <td><input type="text" class="form-control form-control-sm @error('form.shareholder_list.'.$si.'.nationality') is-invalid @enderror" wire:model="form.shareholder_list.{{ $si }}.nationality" placeholder="e.g. Mosotho"></td>
                                                        <td><select class="form-select form-select-sm @error('form.shareholder_list.'.$si.'.gender') is-invalid @enderror" wire:model="form.shareholder_list.{{ $si }}.gender">
                                                                <option value="">—</option>@foreach ($P::GENDERS as $g)<option>{{ $g }}</option>@endforeach</select></td>
                                                        <td><input type="number" min="0" max="120" class="form-control form-control-sm @error('form.shareholder_list.'.$si.'.age') is-invalid @enderror" wire:model="form.shareholder_list.{{ $si }}.age"></td>
                                                        <td><input type="number" min="0" max="100" step="0.01" class="form-control form-control-sm @error('form.shareholder_list.'.$si.'.percent') is-invalid @enderror" wire:model.live.debounce.400ms="form.shareholder_list.{{ $si }}.percent"></td>
                                                        <td class="text-end"><button type="button" class="btn btn-icon btn-icon-danger" title="Remove" wire:click="removeShareholder({{ $si }})"><i class="bi bi-trash"></i></button></td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                    @foreach ($errors->get('form.shareholder_list.*') as $msgs) <div class="text-danger small">{{ $msgs[0] }}</div> @break @endforeach
                                    @error('form.shareholder_list') <div class="text-danger small">{{ $message }}</div> @enderror
                                    <button type="button" class="btn btn-sm btn-outline-secondary mt-1" wire:click="addShareholder"><i class="bi bi-plus-lg me-1"></i> Add shareholder</button>
                                </div>

                                <div class="col-12 border-top pt-3"><div class="fw-semibold text-navy small">Applicant contact information {!! $tip('contact') !!}</div></div>
                                @foreach (['contact_name' => ['Primary contact name', 'text'], 'contact_title' => ['Position / title', 'text'], 'contact_phone' => ['Telephone (with country code)', 'tel'], 'contact_email' => ['E-mail', 'email']] as $f => [$lbl, $type])
                                    <div class="col-md-6"><label class="form-label small fw-semibold">{{ $lbl }} {!! $tip($f) !!} <span class="req">*</span></label>
                                        <input type="{{ $type }}" class="form-control @error('form.'.$f) is-invalid @enderror" wire:model="form.{{ $f }}">
                                        @error('form.'.$f) <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                                @endforeach
                                <div class="col-md-6"><label class="form-label small fw-semibold">Name and position of authorised representative {!! $tip('authorised_rep') !!} <span class="req">*</span></label>
                                    <input type="text" class="form-control @error('form.authorised_rep') is-invalid @enderror" wire:model="form.authorised_rep" placeholder="e.g. Palesa M., Managing Director">
                                    @error('form.authorised_rep') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                                <div class="col-md-6"><label class="form-label small fw-semibold">Alternative contact details {!! $tip('alternative_contact') !!}</label>
                                    <input type="text" class="form-control @error('form.alternative_contact') is-invalid @enderror" wire:model="form.alternative_contact" placeholder="Optional">
                                    @error('form.alternative_contact') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>

                                <div class="col-12 pt-2"><div class="fw-semibold text-navy small">Secondary contact person {!! $tip('secondary_contact') !!}</div></div>
                                @foreach (['secondary_name' => ['Name', 'text'], 'secondary_title' => ['Position / title', 'text'], 'secondary_email' => ['E-mail', 'email'], 'secondary_phone' => ['Telephone', 'tel']] as $f => [$lbl, $type])
                                    <div class="col-md-6"><label class="form-label small fw-semibold">{{ $lbl }} <span class="req">*</span></label>
                                        <input type="{{ $type }}" class="form-control @error('form.'.$f) is-invalid @enderror" wire:model="form.{{ $f }}">
                                        @error('form.'.$f) <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                                @endforeach
                            </div>
                        @else
                            <div class="detail-grid">
                                <div><dt>Company official name {!! $tip('registered_name') !!}</dt><dd>{{ $dash($p->registered_name) }}</dd></div>
                                <div><dt>Short name {!! $tip('short_name') !!}</dt><dd>{{ $dash($p->short_name) }}</dd></div>
                                <div><dt>Operations commenced {!! $tip('year_established') !!}</dt><dd>{{ $dash($p->commencedLabel()) }}
                                    @if ($p->operatingUnderSixMonths()) <span class="pill pill-warn ms-1"><i class="bi bi-exclamation-circle"></i> Under 6 months</span> @endif</dd></div>
                                <div><dt>Year of registration {!! $tip('year_registered') !!}</dt><dd>{{ $dash($p->year_registered) }}</dd></div>
                                <div><dt>Website {!! $tip('website') !!}</dt><dd>{{ $dash($p->website) }}</dd></div>
                                <div><dt>Address {!! $tip('address') !!}</dt><dd>{{ collect([$p->address, $p->city, $p->district])->filter()->implode(', ') ?: '—' }}</dd></div>
                                <div><dt>Sector {!! $tip('sector') !!}</dt><dd>{{ $dash($p->sectorLabel()) }}</dd></div>
                                <div><dt>Sub-sector {!! $tip('sub_sector') !!}</dt><dd>{{ $dash($p->sub_sector) }}</dd></div>
                                <div><dt>Business model {!! $tip('business_models') !!}</dt><dd>{{ $p->businessModelLabel() ?? '—' }}</dd></div>
                                <div><dt>Women owned or led {!! $tip('women_owned_managed') !!}</dt><dd>{{ $yn($p->women_owned_managed) }}</dd></div>
                                <div><dt>Youth (18–35) owned or led {!! $tip('youth_owned_managed') !!}</dt><dd>{{ $yn($p->youth_owned_managed) }}</dd></div>
                                <div><dt>Shareholding {!! $tip('basotho_owned_pct') !!}</dt><dd>{{ $p->ownershipLine() ?? '—' }}</dd></div>
                                <div><dt>Prior CAFI support {!! $tip('prior_cafi_support') !!}</dt><dd>{{ $p->labels($P::PRIOR_SUPPORT, $p->prior_cafi_support) ?? '—' }}</dd></div>
                            </div>
                            @if ($p->prior_support_details) <div class="quote mt-3 mb-0">{{ $p->prior_support_details }}<div class="quote-meta">Support received</div></div> @endif

                            <dt class="detail-label mt-3 mb-2">Shareholders {!! $tip('shareholder_list') !!}</dt>
                            @if ($p->shareholderRows())
                                <div class="table-responsive">
                                    <table class="table table-modern table-sm align-middle mb-1">
                                        <thead><tr><th>Name</th><th>ID / reg. no.</th><th>Nationality</th><th>Gender</th><th class="text-end">Age</th><th class="text-end">% share</th></tr></thead>
                                        <tbody>
                                            @foreach ($p->shareholderRows() as $sh)
                                                <tr><td class="fw-semibold text-navy">{{ $sh['name'] }}</td><td>{{ $dash($sh['id_number'] ?? null) }}</td><td>{{ $dash($sh['nationality'] ?? null) }}</td>
                                                    <td>{{ $dash($sh['gender'] ?? null) }}</td><td class="text-end">{{ $dash($sh['age'] ?? null) }}</td><td class="text-end">{{ isset($sh['percent']) ? rtrim(rtrim(number_format((float) $sh['percent'], 2), '0'), '.').'%' : '—' }}</td></tr>
                                            @endforeach
                                            <tr class="fw-bold"><td colspan="5" class="text-navy">Total</td><td class="text-end {{ $p->shareholderTotal() > 100 ? 'text-danger' : '' }}">{{ rtrim(rtrim(number_format($p->shareholderTotal(), 2), '0'), '.') }}%</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            @elseif ($p->exists && $p->document('shareholders_list'))
                                <p class="small text-muted mb-0"><i class="bi bi-paperclip me-1"></i> Provided as an uploaded list (see documents below).</p>
                            @else
                                <p class="small text-muted mb-0">No shareholders added yet.</p>
                            @endif
                            <dt class="detail-label mt-3 mb-2">Applicant contact {!! $tip('contact') !!}</dt>
                            <div class="detail-grid">
                                <div><dt>Name {!! $tip('contact_name') !!}</dt><dd>{{ $dash($p->contact_name) }}</dd></div>
                                <div><dt>Title {!! $tip('contact_title') !!}</dt><dd>{{ $dash($p->contact_title) }}</dd></div>
                                <div><dt>Phone {!! $tip('contact_phone') !!}</dt><dd>{{ $dash($p->contact_phone) }}</dd></div>
                                <div><dt>E-mail {!! $tip('contact_email') !!}</dt><dd>{{ $dash($p->contact_email) }}</dd></div>
                                <div><dt>Authorised representative {!! $tip('authorised_rep') !!}</dt><dd>{{ $dash($p->authorised_rep) }}</dd></div>
                                <div><dt>Alternative contact {!! $tip('alternative_contact') !!}</dt><dd>{{ $dash($p->alternative_contact) }}</dd></div>
                            </div>
                            <dt class="detail-label mt-3 mb-2">Secondary contact {!! $tip('secondary_contact') !!}</dt>
                            <div class="detail-grid">
                                <div><dt>Name</dt><dd>{{ $dash($p->secondary_name) }}</dd></div>
                                <div><dt>Title</dt><dd>{{ $dash($p->secondary_title) }}</dd></div>
                                <div><dt>Phone</dt><dd>{{ $dash($p->secondary_phone) }}</dd></div>
                                <div><dt>E-mail</dt><dd>{{ $dash($p->secondary_email) }}</dd></div>
                            </div>
                        @endif

                    {{-- ===================== 2 · LEGAL STATUS & MARKETS ===================== --}}
                    @elseif ($key === 'legal')
                        @if ($isEditing)
                            <div class="row g-3">
                                <div class="col-md-6"><label class="form-label small fw-semibold">Is the enterprise registered with the OBFC in Lesotho? {!! $tip('obfc_status') !!} <span class="req">*</span></label>
                                    <div>{!! $radios('obfc_status', $P::YES_NO_PROGRESS, true) !!}</div>
                                    @error('form.obfc_status') <div class="text-danger small">{{ $message }}</div> @enderror</div>
                                <div class="col-md-6"><label class="form-label small fw-semibold">Type of registration {!! $tip('legal_form') !!} <span class="req">*</span></label>
                                    <select class="form-select @error('form.legal_form') is-invalid @enderror" wire:model="form.legal_form">
                                        <option value="">Select…</option>@foreach ($P::LEGAL_FORMS as $o)<option>{{ $o }}</option>@endforeach
                                        @if (filled($form['legal_form'] ?? null) && ! in_array($form['legal_form'], $P::LEGAL_FORMS, true))<option>{{ $form['legal_form'] }}</option>@endif
                                    </select>
                                    @error('form.legal_form') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                                @if (($form['obfc_status'] ?? '') === 'yes')
                                    <div class="col-md-6"><label class="form-label small fw-semibold">Registration number {!! $tip('registration_no') !!} <span class="req">*</span></label>
                                        <input type="text" class="form-control @error('form.registration_no') is-invalid @enderror" wire:model="form.registration_no">
                                        @error('form.registration_no') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                                    <div class="col-md-6"><label class="form-label small fw-semibold">Year of registration {!! $tip('year_registered') !!}</label>
                                        <div class="form-control bg-light small text-muted">{{ filled($p->year_registered) ? $p->year_registered : 'Enter it under Company & contact' }}</div></div>
                                @elseif (in_array($form['obfc_status'] ?? '', ['no', 'in_progress'], true))
                                    <div class="col-12"><div class="callout callout-warning small mb-0"><i class="bi bi-exclamation-triangle me-1"></i> {{ $P::REGISTRATION_NOTE }} Registration with the OBFC must be completed before the Grant Agreement is signed — upload a signed commitment to register below.</div></div>
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
                                    <div class="col-md-3"><label class="form-label small fw-semibold">Country {!! $tip('foreign_country') !!} <span class="req">*</span></label>
                                        <input type="text" class="form-control @error('form.foreign_country') is-invalid @enderror" wire:model="form.foreign_country">
                                        @error('form.foreign_country') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                                    <div class="col-md-3"><label class="form-label small fw-semibold">Registration no. {!! $tip('foreign_registration_no') !!} <span class="req">*</span></label>
                                        <input type="text" class="form-control @error('form.foreign_registration_no') is-invalid @enderror" wire:model="form.foreign_registration_no">
                                        @error('form.foreign_registration_no') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                                @endif

                                <div class="col-md-6"><label class="form-label small fw-semibold">Do you have foreign ownership in your enterprise? {!! $tip('foreign_owned') !!} <span class="req">*</span></label>
                                    <div>{!! $radios('foreign_owned', ['yes' => 'Yes', 'no' => 'No'], true) !!}</div>
                                    @error('form.foreign_owned') <div class="text-danger small">{{ $message }}</div> @enderror</div>
                                @if (($form['foreign_owned'] ?? '') === 'yes')
                                    <div class="col-md-6"><label class="form-label small fw-semibold">What percentage do they own? {!! $tip('foreign_owned_pct') !!} <span class="req">*</span></label>
                                        <div class="input-group" style="max-width: 200px"><input type="number" min="0" max="100" step="0.01" class="form-control @error('form.foreign_owned_pct') is-invalid @enderror" wire:model="form.foreign_owned_pct"><span class="input-group-text">%</span></div>
                                        @error('form.foreign_owned_pct') <div class="text-danger small">{{ $message }}</div> @enderror</div>
                                @endif
                                <div class="col-md-6"><label class="form-label small fw-semibold">Does the enterprise have its primary operations in Lesotho? {!! $tip('primary_ops_lesotho') !!} <span class="req">*</span></label>
                                    <div>{!! $radios('primary_ops_lesotho', ['yes' => 'Yes', 'no' => 'No']) !!}</div>
                                    @error('form.primary_ops_lesotho') <div class="text-danger small">{{ $message }}</div> @enderror</div>
                                <div class="col-md-6"><label class="form-label small fw-semibold">Does the enterprise have an active tax file with RSL? {!! $tip('rsl_tax_file') !!} <span class="req">*</span></label>
                                    <div>{!! $radios('rsl_tax_file', $P::YES_NO_PROGRESS, true) !!}</div>
                                    @error('form.rsl_tax_file') <div class="text-danger small">{{ $message }}</div> @enderror
                                    @if (in_array($form['rsl_tax_file'] ?? '', ['no', 'in_progress'], true)) <div class="small text-warning-emphasis mt-1"><i class="bi bi-exclamation-circle me-1"></i> A tax file must be opened with RSL before the Grant Agreement is signed.</div> @endif</div>

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
                                <div><dt>Registered at OBFC {!! $tip('obfc_status') !!}</dt><dd>{{ $p->obfc_status ? $ynp($p->obfc_status) : $yn($p->obfc_registered) }}</dd></div>
                                <div><dt>Type of registration {!! $tip('legal_form') !!}</dt><dd>{{ $dash($p->legal_form) }}</dd></div>
                                <div><dt>Registration number {!! $tip('registration_no') !!}</dt><dd>{{ $dash($p->registration_no) }}</dd></div>
                                <div><dt>Registered in another country {!! $tip('foreign_registered') !!}</dt><dd>{{ $p->foreign_registered ? 'Yes — '.collect([$p->foreign_country ?: 'country not given', $p->foreign_registration_no ? 'reg. no. '.$p->foreign_registration_no : null])->filter()->implode(', ') : $yn($p->foreign_registered) }}</dd></div>
                                <div><dt>Foreign ownership {!! $tip('foreign_owned') !!}</dt><dd>{{ $p->foreign_owned ? 'Yes — '.rtrim(rtrim(number_format((float) $p->foreign_owned_pct, 2), '0'), '.').'%' : $yn($p->foreign_owned) }}</dd></div>
                                <div><dt>Primary operations in Lesotho {!! $tip('primary_ops_lesotho') !!}</dt><dd>{{ $yn($p->primary_ops_lesotho) }}</dd></div>
                                <div><dt>Active RSL tax file {!! $tip('rsl_tax_file') !!}</dt><dd>{{ $ynp($p->rsl_tax_file) }}</dd></div>
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
                                    <input type="number" min="0" class="form-control @error('form.employees') is-invalid @enderror {{ filled($form['employees'] ?? null) && (int) $form['employees'] !== $sum ? 'border-danger' : '' }}" wire:model.live.debounce.400ms="form.employees">
                                    <div class="form-text">Must equal the breakdown below.</div>
                                    @error('form.employees') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>

                                <div class="col-12"><div class="fw-semibold text-navy small">Details of current employees {!! $tip('employee_details') !!} <span class="req">*</span></div></div>
                                @foreach (['ft_male' => 'Full-time males', 'ft_female' => 'Full-time females', 'pt_male' => 'Part-time males', 'pt_female' => 'Part-time females'] as $f => $lbl)
                                    <div class="col-6 col-md-3"><label class="form-label small fw-semibold">{{ $lbl }} {!! $tip($f) !!}</label>
                                        <input type="number" min="0" class="form-control @error('form.'.$f) is-invalid @enderror" wire:model.live.debounce.400ms="form.{{ $f }}">
                                        @error('form.'.$f) <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                                @endforeach
                                @php $empTotal = filled($form['employees'] ?? null) ? (int) $form['employees'] : null; @endphp
                                <div class="col-12 mt-1">
                                    @if ($empTotal === null)
                                        <div class="small text-muted">Breakdown total: <strong>{{ $sum }}</strong> — enter the current-year total above to compare.</div>
                                    @elseif ($empTotal === $sum)
                                        <div class="small text-success"><i class="bi bi-check-circle me-1"></i> Breakdown adds up to the current-year total ({{ $sum }}).</div>
                                    @else
                                        <div class="small text-danger"><i class="bi bi-exclamation-circle me-1"></i> The breakdown adds up to <strong>{{ $sum }}</strong> but the current-year total is <strong>{{ $empTotal }}</strong> — they must match before you can save.
                                            <button type="button" class="btn btn-link btn-sm p-0 align-baseline" wire:click="$set('form.employees', {{ $sum }})">Use {{ $sum }} as the total</button></div>
                                    @endif
                                </div>
                                @foreach (['youth_male' => 'Youth employees (18–35) — male', 'youth_female' => 'Youth employees (18–35) — female'] as $f => $lbl)
                                    <div class="col-md-6"><label class="form-label small fw-semibold">{{ $lbl }} {!! $tip('youth_employees') !!} <span class="req">*</span></label>
                                        <input type="number" min="0" class="form-control @error('form.'.$f) is-invalid @enderror" wire:model.live.debounce.400ms="form.{{ $f }}">
                                        @error('form.'.$f) <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                                @endforeach
                                @php $youthSum = (int) ($form['youth_male'] ?? 0) + (int) ($form['youth_female'] ?? 0); @endphp
                                @if ($youthSum > $sum)
                                    <div class="col-12 small text-danger mt-1"><i class="bi bi-exclamation-circle me-1"></i> Youth employees ({{ $youthSum }}) cannot be more than all employees ({{ $sum }}) — youth are part of the full-time / part-time figures.</div>
                                @endif

                                <div class="col-12 border-top pt-3"><div class="fw-semibold text-navy small">Expected employment outcomes — next 24 months {!! $tip('expected_jobs') !!} <span class="req">*</span></div>
                                    <div class="form-text mt-0">How many additional jobs do you expect the proposed investment to create? These figures form the baseline for the quarterly and close-out reports.</div></div>
                                @foreach (['expected_jobs' => 'Expected additional jobs', 'expected_jobs_women' => 'of which for women', 'expected_jobs_youth' => 'of which for young people'] as $f => $lbl)
                                    <div class="col-md-4"><label class="form-label small fw-semibold">{{ $lbl }}</label>
                                        <input type="number" min="0" class="form-control @error('form.'.$f) is-invalid @enderror" wire:model.blur="form.{{ $f }}">
                                        @error('form.'.$f) <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                                @endforeach



                                {{-- ===== ESG checklist (CAFI form) ===== --}}
                                <div class="col-12 border-top pt-3">
                                    <div class="fw-semibold text-navy small">Environmental, social &amp; governance (ESG) checklist {!! $tip('es_checklist') !!} <span class="req">*</span></div>
                                    <div class="form-text mt-1">{{ $P::ES_INTRO }} For each question choose Yes, No, Do not know or Not applicable.</div>
                                </div>
                                @foreach ($P::ES_CHECKLIST as $k => [$area, $q])
                                    @php
                                        $esA  = $form['es_checklist'][$k] ?? '';
                                        $esFu = $P::esFollowUp($k, $esA);
                                        $esDq = $esA === 'yes' && in_array($k, $P::ES_DISQUALIFY_IF_YES, true);
                                    @endphp
                                    <div class="col-12 py-2 border-bottom" wire:key="es-{{ $k }}">
                                        <div class="d-flex flex-wrap align-items-start justify-content-between gap-2">
                                            <div class="small flex-grow-1" style="min-width: 260px; max-width: 640px">
                                                <span class="fw-semibold text-navy">{{ $loop->iteration }}.</span>
                                                <span class="pill pill-muted me-1" style="font-size:.68rem">{{ $area }}</span>
                                                {{ $q }} <span class="req">*</span>
                                            </div>
                                            <div class="btn-group btn-group-sm flex-wrap">
                                                @foreach ($P::ES_ANSWERS as $v => $vl)
                                                    <input type="radio" class="btn-check" id="es-{{ $k }}-{{ $v }}" value="{{ $v }}" wire:model.live="form.es_checklist.{{ $k }}">
                                                    <label class="btn btn-outline-secondary" for="es-{{ $k }}-{{ $v }}">{{ $vl }}</label>
                                                @endforeach
                                            </div>
                                        </div>
                                        @error('form.es_checklist.'.$k) <div class="text-danger small">{{ $message }}</div> @enderror

                                        @if ($esDq)
                                            <div class="callout callout-danger small mt-2 mb-0"><i class="bi bi-x-octagon me-1"></i>
                                                {{ $k === 'under_18' ? 'Work by anyone under 18 makes the enterprise ineligible for a Co-Investment grant.' : 'Activities on the exclusion list make the enterprise ineligible for a Co-Investment grant.' }}
                                            </div>
                                        @endif

                                        @if ($k === 'records' && $esA === 'yes')
                                            <div class="mt-2" style="max-width: 640px">
                                                <label class="form-label small fw-semibold mb-1">What do you use? <span class="req">*</span></label>
                                                <select class="form-select form-select-sm @error('form.es_records_system') is-invalid @enderror" wire:model="form.es_records_system">
                                                    <option value="">Select…</option>
                                                    @foreach ($P::ES_RECORD_SYSTEMS as $sv => $sl)<option value="{{ $sv }}">{{ $sl }}</option>@endforeach
                                                </select>
                                                @error('form.es_records_system') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                            </div>
                                        @endif

                                        @if ($esFu)
                                            <div class="mt-2" style="max-width: 640px">
                                                <label class="form-label small fw-semibold mb-1">{{ $esFu[0] }} @if ($esFu[1]) <span class="req">*</span> @endif</label>
                                                <textarea rows="2" class="form-control form-control-sm @error('form.es_notes.'.$k) is-invalid @enderror" wire:model="form.es_notes.{{ $k }}"></textarea>
                                                @error('form.es_notes.'.$k) <div class="invalid-feedback">{{ $message }}</div> @enderror
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                                <div class="col-12">
                                    <label class="form-check mb-0">
                                        <input type="checkbox" class="form-check-input @error('form.es_confirmed') is-invalid @enderror" wire:model="form.es_confirmed">
                                        <span class="form-check-label small">{{ $P::ES_CONFIRMATION }} <span class="req">*</span></span>
                                    </label>
                                    @error('form.es_confirmed') <div class="text-danger small">{{ $message }}</div> @enderror
                                    <div class="form-text mt-1">{{ $P::ES_NOTE }}</div>
                                </div>

                                {{-- ===== Key individuals ===== --}}
                                <div class="col-12 border-top pt-3">
                                    <div class="fw-semibold text-navy small mb-2">Directors and key management personnel {!! $tip('key_people') !!} <span class="req">*</span></div>
                                    <div class="table-responsive">
                                        <table class="table table-modern table-sm align-middle mb-1">
                                            <thead><tr><th style="min-width:170px">Name</th><th style="min-width:150px">Position</th><th style="width:120px">Years of service</th><th style="min-width:130px">Nationality</th><th></th></tr></thead>
                                            <tbody>
                                                @foreach ($form['key_people'] ?? [] as $ki => $kp)
                                                    <tr wire:key="kp-{{ $ki }}">
                                                        <td><input type="text" class="form-control form-control-sm @error('form.key_people.'.$ki.'.name') is-invalid @enderror" wire:model.blur="form.key_people.{{ $ki }}.name"></td>
                                                        <td><input type="text" class="form-control form-control-sm @error('form.key_people.'.$ki.'.position') is-invalid @enderror" wire:model="form.key_people.{{ $ki }}.position" placeholder="e.g. Director"></td>
                                                        <td><input type="number" min="0" max="80" step="0.5" class="form-control form-control-sm @error('form.key_people.'.$ki.'.years') is-invalid @enderror" wire:model="form.key_people.{{ $ki }}.years"></td>
                                                        <td><input type="text" class="form-control form-control-sm @error('form.key_people.'.$ki.'.nationality') is-invalid @enderror" wire:model="form.key_people.{{ $ki }}.nationality" placeholder="e.g. Mosotho"></td>
                                                        <td class="text-end"><button type="button" class="btn btn-icon btn-icon-danger" title="Remove" wire:click="removeKeyPerson({{ $ki }})"><i class="bi bi-trash"></i></button></td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                    @foreach ($errors->get('form.key_people.*') as $msgs) <div class="text-danger small">{{ $msgs[0] }}</div> @break @endforeach
                                    @error('form.key_people') <div class="text-danger small">{{ $message }}</div> @enderror
                                    <button type="button" class="btn btn-sm btn-outline-secondary mt-1" wire:click="addKeyPerson"><i class="bi bi-plus-lg me-1"></i> Add person</button>
                                </div>
                                <div class="col-12"><label class="form-label small fw-semibold">Is there any known ownership dispute, material litigation or conflict that could affect the transaction? {!! $tip('ownership_dispute') !!} <span class="req">*</span></label>
                                    <div>{!! $radios('ownership_dispute', ['yes' => 'Yes', 'no' => 'No'], true) !!}</div>
                                    @error('form.ownership_dispute') <div class="text-danger small">{{ $message }}</div> @enderror</div>
                                @if (($form['ownership_dispute'] ?? '') === 'yes')
                                    <div class="col-12"><label class="form-label small fw-semibold">Please describe it <span class="req">*</span></label>
                                        <textarea rows="2" class="form-control @error('form.ownership_dispute_details') is-invalid @enderror" wire:model="form.ownership_dispute_details"></textarea>
                                        @error('form.ownership_dispute_details') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                                @endif

                                <div class="col-12 border-top pt-3"><label class="form-label small fw-semibold">Management summary — the people running the company {!! $tip('management') !!}</label>
                                    <textarea rows="3" class="form-control @error('form.management') is-invalid @enderror" wire:model="form.management" placeholder="Name — position, responsibilities and experience, one person per line"></textarea>
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

                            @php $sumOf = fn (...$v) => collect($v)->every(fn ($x) => $x === null) ? '—' : collect($v)->sum(fn ($x) => (int) $x); @endphp
                            <dt class="detail-label mt-3 mb-2">Current employment</dt>
                            <div class="table-responsive">
                                <table class="table table-modern table-sm align-middle mb-0">
                                    <thead><tr><th>Category</th><th class="text-end">Male</th><th class="text-end">Female</th><th class="text-end">Total</th></tr></thead>
                                    <tbody>
                                        <tr><td>Full-time employees</td><td class="text-end">{{ $p->ft_male ?? '—' }}</td><td class="text-end">{{ $p->ft_female ?? '—' }}</td><td class="text-end fw-semibold">{{ $sumOf($p->ft_male, $p->ft_female) }}</td></tr>
                                        <tr><td>Part-time employees</td><td class="text-end">{{ $p->pt_male ?? '—' }}</td><td class="text-end">{{ $p->pt_female ?? '—' }}</td><td class="text-end fw-semibold">{{ $sumOf($p->pt_male, $p->pt_female) }}</td></tr>
                                        <tr><td>Youth employees (18–35)</td><td class="text-end">{{ $p->youth_male ?? '—' }}</td><td class="text-end">{{ $p->youth_female ?? '—' }}</td><td class="text-end fw-semibold">{{ $sumOf($p->youth_male, $p->youth_female) }}</td></tr>
                                    </tbody>
                                </table>
                            </div>
                            <dt class="detail-label mt-3 mb-2">Expected employment outcomes (24 months) {!! $tip('expected_jobs') !!}</dt>
                            <div class="detail-grid">
                                <div><dt>Additional jobs</dt><dd>{{ $p->expected_jobs ?? '—' }}</dd></div>
                                <div><dt>Jobs for women</dt><dd>{{ $p->expected_jobs_women ?? '—' }}</dd></div>
                                <div><dt>Jobs for young people</dt><dd>{{ $p->expected_jobs_youth ?? '—' }}</dd></div>
                            </div>


                            @php
                                $esFlags  = $p->esScreening();
                                $esByKey  = collect($esFlags)->keyBy(1);
                                $esAnswered = collect($P::ES_CHECKLIST)->keys()->contains(fn ($k) => isset($P::ES_ANSWERS[$p->es_checklist[$k] ?? '']));
                                $esLevel  = [
                                    'disqualify' => ['pill-danger', 'bi-x-octagon-fill', 'Disqualifies'],
                                    'dd'         => ['pill-warn',   'bi-search',          'Targeted DD'],
                                    'review'     => ['pill-muted',  'bi-eye',             'Review controls'],
                                    'clarify'    => ['pill-warn',   'bi-question-circle', 'Clarify'],
                                ];
                            @endphp
                            <dt class="detail-label mt-3 mb-2">Environmental, social &amp; governance checklist {!! $tip('es_checklist') !!}</dt>
                            @if ($esAnswered)
                                @if ($p->esDisqualified())
                                    <div class="callout callout-danger small mb-2"><i class="bi bi-x-octagon me-1"></i> An answer below means immediate disqualification under the CAFI screening rules.</div>
                                @elseif (! $esFlags)
                                    <div class="small text-muted mb-2"><i class="bi bi-check-circle text-success me-1"></i> No issue disclosed.</div>
                                @else
                                    <div class="small text-muted mb-2">{{ count($esFlags) }} {{ \Illuminate\Support\Str::plural('answer', count($esFlags)) }} flagged for review — these do not disqualify the enterprise on their own.</div>
                                @endif
                            @endif
                            <ul class="checklist mb-1">
                                @foreach ($P::ES_CHECKLIST as $k => [$area, $q])
                                    @php
                                        $a    = $p->es_checklist[$k] ?? null;
                                        $a    = isset($P::ES_ANSWERS[$a ?? '']) ? $a : null;
                                        $flag = $esByKey[$k] ?? null;
                                        $note = $p->es_notes[$k] ?? null;
                                    @endphp
                                    <li class="{{ $a === null ? 'is-pending' : ($flag ? ($flag[0] === 'disqualify' ? 'is-fail' : 'is-pending') : 'is-done') }}">
                                        <i class="bi {{ $a === null ? 'bi-circle' : ($flag ? $esLevel[$flag[0]][1] : 'bi-check-circle-fill') }}"></i>
                                        <div class="flex-grow-1">
                                            <span class="text-muted">{{ $loop->iteration }}.</span> {{ $q }}
                                            @if ($k === 'records' && $a === 'yes' && $p->es_records_system) <div class="small text-muted">Uses: {{ $P::ES_RECORD_SYSTEMS[$p->es_records_system] ?? $p->es_records_system }}</div> @endif
                                            @if (filled($note)) <div class="small text-muted fst-italic mt-1" style="white-space: pre-line">“{{ $note }}”</div> @endif
                                            @if ($flag) <span class="pill {{ $esLevel[$flag[0]][0] }} mt-1" style="font-size:.7rem"><i class="bi {{ $esLevel[$flag[0]][1] }}"></i> {{ $esLevel[$flag[0]][2] }}</span> @endif
                                        </div>
                                        <strong class="small text-nowrap">{{ $a ? $P::ES_ANSWERS[$a] : '—' }}</strong>
                                    </li>
                                @endforeach
                            </ul>
                            @if ($p->es_confirmed)
                                <div class="small text-muted"><i class="bi bi-patch-check text-accent me-1"></i> Answers confirmed by the applicant.</div>
                            @endif
                            @if ($esFlags) <div class="small text-muted mt-1">{{ $P::ES_NOTE }}</div> @endif

                            <dt class="detail-label mt-3 mb-2">Directors and key management {!! $tip('key_people') !!}</dt>
                            @if ($p->keyPeopleRows())
                                <div class="table-responsive">
                                    <table class="table table-modern table-sm align-middle mb-1">
                                        <thead><tr><th>Name</th><th>Position</th><th class="text-end">Years of service</th><th>Nationality</th></tr></thead>
                                        <tbody>
                                            @foreach ($p->keyPeopleRows() as $kp)
                                                <tr><td class="fw-semibold text-navy">{{ $kp['name'] }}</td><td>{{ $dash($kp['position'] ?? null) }}</td>
                                                    <td class="text-end">{{ isset($kp['years']) ? rtrim(rtrim(number_format((float) $kp['years'], 1), '0'), '.') : '—' }}</td><td>{{ $dash($kp['nationality'] ?? null) }}</td></tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <p class="small text-muted mb-1">No directors or key managers added yet.</p>
                            @endif
                            <div class="small mt-1"><span class="text-muted">Known ownership dispute, litigation or conflict:</span> <strong>{{ $yn($p->ownership_dispute) }}</strong></div>
                            @if ($p->ownership_dispute && $p->ownership_dispute_details) <div class="quote mt-2 mb-0">{{ $p->ownership_dispute_details }}<div class="quote-meta">Dispute / litigation</div></div> @endif

                            @if ($p->management)
                                <dt class="detail-label mt-3">Management {!! $tip('management') !!}</dt>
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
                                <div class="col-md-6"><label class="form-label small fw-semibold">Products &amp; services {!! $tip('products_services') !!} <span class="req">*</span></label>
                                    <textarea rows="3" class="form-control @error('form.products_services') is-invalid @enderror" wire:model="form.products_services" placeholder="What you sell"></textarea>
                                    @error('form.products_services') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                                <div class="col-md-6"><label class="form-label small fw-semibold">Customers {!! $tip('customers') !!} <span class="req">*</span></label>
                                    <textarea rows="3" class="form-control @error('form.customers') is-invalid @enderror" wire:model="form.customers" placeholder="Who buys from you, how many, and where they are"></textarea>
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
                                <div class="col-md-6"><label class="form-label small fw-semibold">Annual revenues for the past year (M) {!! $tip('annual_revenue') !!} <span class="req">*</span></label>
                                    <input type="number" min="0" step="1000" class="form-control @error('form.annual_revenue') is-invalid @enderror" wire:model.live.debounce.500ms="form.annual_revenue">
                                    <div class="form-text">{{ $usd($form['annual_revenue'] ?? null) }}</div>
                                    @error('form.annual_revenue') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>

                                {{-- Do you have an investor? --}}
                                <div class="col-md-6"><label class="form-label small fw-semibold">Do you have an investor? {!! $tip('has_investor') !!} <span class="req">*</span></label>
                                    <div class="btn-group btn-group-sm d-flex" style="max-width: 200px">
                                        @foreach (['yes' => 'Yes', 'no' => 'No'] as $v => $vl)
                                            <input type="radio" class="btn-check" id="hasinv-{{ $v }}" value="{{ $v }}" wire:model.live="form.has_investor">
                                            <label class="btn btn-outline-secondary" for="hasinv-{{ $v }}">{{ $vl }}</label>
                                        @endforeach
                                    </div>
                                    @error('form.has_investor') <div class="text-danger small">{{ $message }}</div> @enderror</div>

                                @php
                                    $hasInvestor  = ($form['has_investor'] ?? '') === 'yes';
                                    // Live: investor tickets, otherwise the round amount → CAFI grant
                                    $formTickets  = $hasInvestor ? collect($form['round_investors'] ?? [])->sum(fn ($r) => is_numeric($r['ticket'] ?? null) ? (float) $r['ticket'] : 0) : 0;
                                    $formInvestor = ! $hasInvestor ? null : ($formTickets > 0 ? $formTickets : (is_numeric($form['round_amount'] ?? null) && (float) $form['round_amount'] > 0 ? (float) $form['round_amount'] : null));
                                    $formGrant    = $P::matchingGrant($formInvestor);
                                    $projectCost  = is_numeric($form['project_cost'] ?? null) ? (float) $form['project_cost'] : null;
                                    $costTooLow   = $formGrant !== null && $projectCost !== null && $projectCost + 0.005 < $formGrant;
                                @endphp

                                @if (($form['has_investor'] ?? '') === 'no')
                                    <div class="col-12"><div class="callout small mb-0">
                                        <div class="fw-semibold text-navy mb-1"><i class="bi bi-people me-1"></i> Get matched with an investor through LEHSFF</div>
                                        <p class="mb-2">The Co-Investment Facility matches money from a private investor, so a CAFI grant can only be awarded once you have an investor. If you don't have one yet, we recommend LEHSFF matchmaking on the
                                            <a href="https://collaboration.lehsff.org.ls/" target="_blank" rel="noopener">LEHSFF Collaboration Platform <i class="bi bi-box-arrow-up-right"></i></a>.</p>
                                        <div class="fw-semibold mb-1">How to get matched</div>
                                        <ol class="mb-2 ps-3">
                                            <li>Go to <a href="https://collaboration.lehsff.org.ls/" target="_blank" rel="noopener">collaboration.lehsff.org.ls</a> and <strong>sign up as an Entrepreneur</strong> (or log in if you already have an account).</li>
                                            <li><strong>Fill in all of your entrepreneur profile details</strong> — your business and contact details, sector, stage of the business, products or services, the investment you are looking for and what it will be used for, and your team.</li>
                                            <li>Upload the supporting documents the profile asks for, check that every section is complete, then save your profile.</li>
                                            <li>Once your profile is complete, <strong>the CAFI team will be notified</strong>. They will review your profile and match you with suitable investors on the platform.</li>
                                        </ol>
                                        <div class="text-muted"><i class="bi bi-info-circle me-1"></i> Only complete profiles can be matched, so make sure no section is left empty. You can save this profile now.</div>
                                    </div></div>
                                @endif

                                @if ($hasInvestor)
                                    <div class="col-md-6"><label class="form-label small fw-semibold">Current investment round fund (M) {!! $tip('round_amount') !!} <span class="req">*</span></label>
                                        <input type="number" min="0" step="1000" class="form-control @error('form.round_amount') is-invalid @enderror" wire:model.live.debounce.500ms="form.round_amount">
                                        <div class="form-text">{{ $usd($form['round_amount'] ?? null) }}</div>
                                        @error('form.round_amount') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>

                                    <div class="col-12"><label class="form-label small fw-semibold">Participating investors and their committed ticket size (LSL) in the current round {!! $tip('round_investors') !!} <span class="req">*</span></label>
                                        @foreach ($form['round_investors'] ?? [] as $i => $row)
                                            <div class="row g-2 mb-2" wire:key="inv-{{ $i }}">
                                                <div class="col-md-3"><input type="text" class="form-control form-control-sm @error('form.round_investors.'.$i.'.name') is-invalid @enderror" wire:model.blur="form.round_investors.{{ $i }}.name" placeholder="Investor name">
                                                    @error('form.round_investors.'.$i.'.name') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                                                <div class="col-md-3"><input type="text" class="form-control form-control-sm @error('form.round_investors.'.$i.'.id_number') is-invalid @enderror" wire:model="form.round_investors.{{ $i }}.id_number" placeholder="ID / passport no. (or company reg. no.)">
                                                    @error('form.round_investors.'.$i.'.id_number') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                                                <div class="col-md-2"><input type="text" class="form-control form-control-sm" wire:model="form.round_investors.{{ $i }}.country" placeholder="Residence country"></div>
                                                <div class="col-md-3"><div class="input-group input-group-sm"><span class="input-group-text">M</span>
                                                    <input type="number" min="0" step="1000" class="form-control @error('form.round_investors.'.$i.'.ticket') is-invalid @enderror" wire:model.live.debounce.500ms="form.round_investors.{{ $i }}.ticket" placeholder="Ticket size"></div>
                                                    <div class="form-text">{{ $usd($row['ticket'] ?? null) }}</div>
                                                    @error('form.round_investors.'.$i.'.ticket') <div class="text-danger small">{{ $message }}</div> @enderror</div>
                                            </div>
                                        @endforeach
                                        @error('form.round_investors') <div class="text-danger small mt-1">{{ $message }}</div> @enderror</div>

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
                                @endif

                                {{-- CAFI matching grant: calculated live, with a plain description --}}
                                <div class="col-md-6"><label class="form-label small fw-semibold">CAFI matching grant (M) {!! $tip('grant_requested') !!}</label>
                                    <input type="text" class="form-control bg-light fw-semibold" value="{{ $formGrant !== null ? number_format($formGrant) : '' }}" placeholder="{{ $hasInvestor ? 'Enter your investors\' ticket sizes' : 'Needs an investor' }}" readonly>
                                    <div class="form-text">{{ $usd($formGrant) }} {{ $formGrant !== null ? '·' : '' }} {{ $P::grantRatioLabel() }} of what your investor(s) put in{{ $formTickets > 0 ? ' (ticket sizes)' : ($formInvestor !== null ? ' (current round)' : '') }}</div></div>
                                <div class="col-md-6"><label class="form-label small fw-semibold">Total project cost (M) {!! $tip('project_cost') !!} <span class="req">*</span></label>
                                    <input type="number" min="0" step="1000" class="form-control @error('form.project_cost') is-invalid @enderror {{ $costTooLow ? 'border-danger' : '' }}" wire:model.live.debounce.500ms="form.project_cost">
                                    <div class="form-text">{{ $usd($form['project_cost'] ?? null) }}@if ($formGrant !== null) · must be at least {{ $lsl($formGrant) }} (the CAFI grant)@endif</div>
                                    @if ($costTooLow) <div class="text-danger small"><i class="bi bi-exclamation-circle me-1"></i> The total project cost cannot be less than the CAFI matching grant.</div> @endif
                                    @error('form.project_cost') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                                <div class="col-12"><div class="callout small mb-0">
                                    <div class="fw-semibold text-navy mb-1"><i class="bi bi-cash-coin me-1"></i> What is the CAFI matching grant?</div>
                                    It is a non-repayable grant from the Competitiveness and Financial Inclusion (CAFI) project that matches <strong>{{ $P::grantRatioLabel() }}</strong> of the money your private investor(s) put into this round. You don't type it in — it is worked out from your investors' ticket sizes. It is paid in tranches once the conditions in the Grant Agreement are met and your investor's money is confirmed, and it can only be spent on the items in your grant utilisation budget below.
                                </div></div>
                                @if ($formInvestor !== null && ! $P::meetsInvestorMinimum($formInvestor))
                                    <div class="col-12"><div class="callout callout-warning small mb-0">
                                        <i class="bi bi-exclamation-triangle me-1"></i> Your investor(s) must be investing at least <strong>US$ {{ number_format($P::minInvestorUsd()) }}</strong> (about {{ $lsl($P::minInvestorLsl()) }}) before you can apply. You can save your profile, but the application will not be accepted.
                                    </div></div>
                                @endif
                                <div class="col-12"><label class="form-label small fw-semibold">What the money is for {!! $tip('investment_purpose') !!}</label>
                                    <textarea rows="3" class="form-control @error('form.investment_purpose') is-invalid @enderror" wire:model="form.investment_purpose"
                                              placeholder="Main items for the investor's money and for the CAFI grant, with rough amounts"></textarea>
                                    @error('form.investment_purpose') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>

                                {{-- Grant utilisation budget — always visible; totals checked live against the targets --}}
                                @php
                                    $bt        = $P::sumBudget($form['grant_budget'] ?? []);
                                    $tPrivate  = $formInvestor;                // target for the private column
                                    $tGrant    = $formGrant;                   // target for the CAFI grant column
                                    $dPrivate  = $tPrivate !== null ? $bt['private'] - $tPrivate : null;
                                    $dGrant    = $tGrant !== null ? $bt['grant'] - $tGrant : null;
                                    $okPrivate = $dPrivate !== null && abs($dPrivate) < 1;
                                    $okGrant   = $dGrant !== null && abs($dGrant) < 1;
                                    $diffCls   = fn ($d) => $d === null ? 'text-muted' : (abs($d) < 1 ? 'text-success' : 'text-danger');
                                    $diffTxt   = fn ($d) => $d === null ? '—' : (abs($d) < 1 ? '✓ Matches' : ($d > 0 ? '+' : '−').' M '.number_format(abs($d)));
                                @endphp
                                <div class="col-12 border-top pt-3"><div class="fw-semibold text-navy small mb-1">Grant utilisation budget @if ($hasInvestor) <span class="req">*</span> @endif {!! $tip('grant_budget') !!}</div>
                                    <div class="form-text mt-0 mb-2">How the investor's money and the CAFI grant will be spent. The <strong>Should add up to</strong> row shows the targets — the totals turn green when they match.</div>
                                    @unless ($hasInvestor && $formInvestor !== null)
                                        <div class="small text-muted mb-2"><i class="bi bi-info-circle me-1"></i> The targets appear as soon as you enter what your investor(s) are putting in.</div>
                                    @endunless
                                    <div class="table-responsive">
                                        <table class="table table-modern table-sm align-middle mb-1">
                                            <thead><tr><th>Expenditure category</th><th class="text-end" style="width: 24%">Private investment (M)</th><th class="text-end" style="width: 24%">CAFI grant (M)</th><th class="text-end" style="width: 16%">Total</th></tr></thead>
                                            <tbody>
                                                @foreach ($form['grant_budget'] ?? [] as $bi => $b)
                                                    @php
                                                        $n      = fn ($v) => is_numeric($v ?? null) ? (float) $v : 0;
                                                        $items  = $b['items'] ?? [];
                                                        $hasSub = $items !== [];
                                                        $subP   = collect($items)->sum(fn ($it) => $n($it['private'] ?? null));
                                                        $subG   = collect($items)->sum(fn ($it) => $n($it['grant'] ?? null));
                                                        $offP   = $hasSub && abs($subP - $n($b['private'] ?? null)) >= 1;
                                                        $offG   = $hasSub && abs($subG - $n($b['grant'] ?? null)) >= 1;
                                                    @endphp
                                                    <tr wire:key="gb-{{ $bi }}" class="{{ $hasSub ? 'table-light' : '' }}">
                                                        <td class="small">
                                                            <div class="fw-semibold text-navy">{{ $b['category'] }}</div>
                                                            <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none small" wire:click="addBudgetItem({{ $bi }})"><i class="bi bi-plus-circle me-1"></i>Add sub-category</button>
                                                        </td>
                                                        <td><input type="number" min="0" step="1000" class="form-control form-control-sm text-end @error('form.grant_budget.'.$bi.'.private') is-invalid @enderror {{ $offP ? 'border-danger' : '' }}" wire:model.live.debounce.400ms="form.grant_budget.{{ $bi }}.private"></td>
                                                        <td><input type="number" min="0" step="1000" class="form-control form-control-sm text-end @error('form.grant_budget.'.$bi.'.grant') is-invalid @enderror {{ $offG ? 'border-danger' : '' }}" wire:model.live.debounce.400ms="form.grant_budget.{{ $bi }}.grant"></td>
                                                        <td class="text-end small">{{ $lsl($n($b['private'] ?? null) + $n($b['grant'] ?? null)) }}</td>
                                                    </tr>

                                                    {{-- Sub-categories: must add up to the category amounts above --}}
                                                    @foreach ($items as $ii => $it)
                                                        <tr wire:key="gb-{{ $bi }}-{{ $ii }}">
                                                            <td class="ps-4">
                                                                <div class="input-group input-group-sm">
                                                                    <span class="input-group-text bg-white border-0 text-muted px-1">↳</span>
                                                                    <input type="text" class="form-control form-control-sm @error('form.grant_budget.'.$bi.'.items.'.$ii.'.name') is-invalid @enderror" wire:model.blur="form.grant_budget.{{ $bi }}.items.{{ $ii }}.name" placeholder="Sub-category, e.g. Delivery van">
                                                                    <button type="button" class="btn btn-icon btn-icon-danger" title="Remove" wire:click="removeBudgetItem({{ $bi }}, {{ $ii }})"><i class="bi bi-x-lg"></i></button>
                                                                </div>
                                                            </td>
                                                            <td><input type="number" min="0" step="1000" class="form-control form-control-sm text-end @error('form.grant_budget.'.$bi.'.items.'.$ii.'.private') is-invalid @enderror" wire:model.live.debounce.400ms="form.grant_budget.{{ $bi }}.items.{{ $ii }}.private"></td>
                                                            <td><input type="number" min="0" step="1000" class="form-control form-control-sm text-end @error('form.grant_budget.'.$bi.'.items.'.$ii.'.grant') is-invalid @enderror" wire:model.live.debounce.400ms="form.grant_budget.{{ $bi }}.items.{{ $ii }}.grant"></td>
                                                            <td class="text-end small text-muted">{{ $lsl($n($it['private'] ?? null) + $n($it['grant'] ?? null)) }}</td>
                                                        </tr>
                                                    @endforeach
                                                    @if ($hasSub)
                                                        <tr wire:key="gb-{{ $bi }}-sum">
                                                            <td class="ps-4 small text-muted">Sub-categories total</td>
                                                            <td class="text-end small {{ $offP ? 'text-danger fw-semibold' : 'text-success' }}">{{ $lsl($subP) }} {!! $offP ? '<div style="font-size:.72rem">should be '.e($lsl($n($b['private'] ?? null))).'</div>' : '✓' !!}</td>
                                                            <td class="text-end small {{ $offG ? 'text-danger fw-semibold' : 'text-success' }}">{{ $lsl($subG) }} {!! $offG ? '<div style="font-size:.72rem">should be '.e($lsl($n($b['grant'] ?? null))).'</div>' : '✓' !!}</td>
                                                            <td></td>
                                                        </tr>
                                                        @error('form.grant_budget.'.$bi.'.items_private') <tr><td colspan="4" class="text-danger small py-1">{{ $message }}</td></tr> @enderror
                                                        @error('form.grant_budget.'.$bi.'.items_grant') <tr><td colspan="4" class="text-danger small py-1">{{ $message }}</td></tr> @enderror
                                                    @endif
                                                @endforeach
                                            </tbody>
                                            <tfoot>
                                                <tr class="fw-bold">
                                                    <td class="small text-navy">Total entered</td>
                                                    <td class="text-end small {{ $tPrivate === null ? '' : ($okPrivate ? 'text-success' : 'text-danger') }}">{{ $lsl($bt['private']) }}</td>
                                                    <td class="text-end small {{ $tGrant === null ? '' : ($okGrant ? 'text-success' : 'text-danger') }}">{{ $lsl($bt['grant']) }}</td>
                                                    <td class="text-end small">{{ $lsl($bt['private'] + $bt['grant']) }}</td>
                                                </tr>
                                                <tr class="table-light">
                                                    <td class="small text-navy fw-semibold">Should add up to</td>
                                                    <td class="text-end small fw-semibold">{{ $tPrivate !== null ? $lsl($tPrivate) : '—' }}<div class="text-muted fw-normal" style="font-size:.75rem">investor amount</div></td>
                                                    <td class="text-end small fw-semibold">{{ $tGrant !== null ? $lsl($tGrant) : '—' }}<div class="text-muted fw-normal" style="font-size:.75rem">matching grant</div></td>
                                                    <td class="text-end small fw-semibold">{{ $tPrivate !== null ? $lsl($tPrivate + $tGrant) : '—' }}<div class="text-muted fw-normal" style="font-size:.75rem">{{ $tPrivate !== null ? $usd($tPrivate + $tGrant) : '' }}</div></td>
                                                </tr>
                                                <tr>
                                                    <td class="small text-muted">Difference</td>
                                                    <td class="text-end small {{ $diffCls($dPrivate) }}">{{ $diffTxt($dPrivate) }}</td>
                                                    <td class="text-end small {{ $diffCls($dGrant) }}">{{ $diffTxt($dGrant) }}</td>
                                                    <td></td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                    @error('form.grant_budget') <div class="text-danger small mt-1"><i class="bi bi-exclamation-circle me-1"></i>{{ $message }}</div> @enderror
                                    @error('form.grant_budget_grant') <div class="text-danger small mt-1"><i class="bi bi-exclamation-circle me-1"></i>{{ $message }}</div> @enderror
                                </div>

                                {{-- Grant spent outside Lesotho — at least the minimum share must be spent in Lesotho --}}
                                @if ($hasInvestor)
                                    @php
                                        $minLs    = $P::minLesothoPct();
                                        $lsBase   = $bt['grant'] > 0 ? $bt['grant'] : ($tGrant ?? 0);
                                        $lsOut    = is_numeric($form['grant_outside_lesotho'] ?? null) ? (float) $form['grant_outside_lesotho'] : null;
                                        $lsShare  = $P::lesothoShare($lsBase ?: null, $lsOut);
                                        $lsBelow  = $lsShare !== null && $lsShare < $minLs;
                                        $pctTxt   = fn ($v) => rtrim(rtrim(number_format($v, 1), '0'), '.').'%';
                                    @endphp
                                    <div class="col-12 border-top pt-3">
                                        <div class="fw-semibold text-navy small mb-1">Spending in Lesotho <span class="req">*</span> {!! $tip('grant_outside_lesotho') !!}</div>
                                        <div class="form-text mt-0 mb-2">At least <strong>{{ (int) $minLs }}%</strong> of the CAFI grant must be spent in Lesotho. If some of it will pay for goods or services from outside Lesotho, enter that amount (enter 0 if none).</div>
                                        <div class="row g-2 align-items-end">
                                            <div class="col-md-5">
                                                <label class="form-label small fw-semibold mb-1" for="pf_outside">CAFI grant to be spent outside Lesotho</label>
                                                <div class="input-group input-group-sm">
                                                    <span class="input-group-text">M</span>
                                                    <input type="number" id="pf_outside" min="0" step="1000" class="form-control @error('form.grant_outside_lesotho') is-invalid @enderror" wire:model.live.debounce.400ms="form.grant_outside_lesotho" placeholder="0">
                                                </div>
                                                @error('form.grant_outside_lesotho') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                                            </div>
                                            <div class="col-md-7 small">
                                                @if ($lsShare !== null)
                                                    <div class="{{ $lsBelow ? 'text-danger' : 'text-success' }}">
                                                        <i class="bi {{ $lsBelow ? 'bi-exclamation-triangle' : 'bi-check-circle' }} me-1"></i>
                                                        <strong>{{ $pctTxt($lsShare) }}</strong> spent in Lesotho ({{ $lsl($lsBase - $lsOut) }}) · {{ $pctTxt(100 - $lsShare) }} outside ({{ $lsl($lsOut) }})
                                                        — {{ $lsBelow ? 'below the '.(int) $minLs.'% minimum, please explain why below' : 'meets the '.(int) $minLs.'% minimum' }}
                                                    </div>
                                                @elseif ($lsBase <= 0)
                                                    <div class="text-muted"><i class="bi bi-info-circle me-1"></i> Fill in the CAFI grant column of the budget first.</div>
                                                @endif
                                            </div>
                                        </div>
                                        @if ($lsBelow)
                                            <div class="mt-2">
                                                <label class="form-label small fw-semibold mb-1" for="pf_outside_why">Why does this part of the grant have to be spent outside Lesotho? <span class="req">*</span> {!! $tip('grant_outside_justification') !!}</label>
                                                <textarea id="pf_outside_why" rows="3" maxlength="2000" class="form-control @error('form.grant_outside_justification') is-invalid @enderror" wire:model.blur="form.grant_outside_justification"
                                                          placeholder="e.g. The processing machine is only made by suppliers in South Africa; installation is done by the supplier's technicians."></textarea>
                                                @error('form.grant_outside_justification') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                            </div>
                                        @endif
                                    </div>
                                @endif

                                {{-- Bank account for the grant — verified by the LEHSFF team at Gate 4 --}}
                                @if ($hasInvestor)
                                    <div class="col-12 border-top pt-3">
                                        <div class="fw-semibold text-navy small mb-1"><i class="bi bi-bank me-1"></i> Bank account for the grant {!! $tip('section.bank') !!}</div>
                                        <div class="form-text mt-0 mb-2">The account the CAFI grant will be paid into. It must be in the company's registered name. Upload your bank confirmation letter in the documents below.</div>
                                        <div class="row g-2">
                                            <div class="col-md-4">
                                                <label class="form-label small fw-semibold mb-1" for="pf_bank">Bank <span class="req">*</span></label>
                                                <input type="text" id="pf_bank" maxlength="120" class="form-control form-control-sm @error('form.bank_name') is-invalid @enderror" wire:model.blur="form.bank_name" placeholder="e.g. Standard Lesotho Bank">
                                                @error('form.bank_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label small fw-semibold mb-1" for="pf_branch">Branch</label>
                                                <input type="text" id="pf_branch" maxlength="120" class="form-control form-control-sm" wire:model.blur="form.bank_branch">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label small fw-semibold mb-1" for="pf_atype">Account type</label>
                                                <select id="pf_atype" class="form-select form-select-sm @error('form.bank_account_type') is-invalid @enderror" wire:model="form.bank_account_type">
                                                    <option value="">Select…</option>
                                                    @foreach ($P::ACCOUNT_TYPES as $atk => $atl) <option value="{{ $atk }}">{{ $atl }}</option> @endforeach
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label small fw-semibold mb-1" for="pf_aname">Account name <span class="req">*</span> {!! $tip('bank_account_name') !!}</label>
                                                <input type="text" id="pf_aname" maxlength="200" class="form-control form-control-sm @error('form.bank_account_name') is-invalid @enderror" wire:model.blur="form.bank_account_name">
                                                @error('form.bank_account_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label small fw-semibold mb-1" for="pf_anum">Account number <span class="req">*</span></label>
                                                <input type="text" id="pf_anum" maxlength="40" inputmode="numeric" class="form-control form-control-sm @error('form.bank_account_number') is-invalid @enderror" wire:model.blur="form.bank_account_number">
                                                @error('form.bank_account_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                            </div>
                                        </div>
                                    </div>
                                @endif

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
                            <div class="row g-3 mb-1">
                                <div class="col-md-6"><dt class="detail-label">Products &amp; services {!! $tip('products_services') !!}</dt>
                                    <p class="small mb-2" style="white-space: pre-line">{{ $dash($p->products_services) }}</p></div>
                                <div class="col-md-6"><dt class="detail-label">Customers {!! $tip('customers') !!}</dt>
                                    <p class="small mb-2" style="white-space: pre-line">{{ $dash($p->customers) }}</p></div>
                            </div>
                            @foreach (['usp' => 'Unique selling proposition', 'marketing_strategy' => 'Marketing approach', 'competition' => 'Competition'] as $f => $lbl)
                                <dt class="detail-label">{{ $lbl }} {!! $tip($f) !!}</dt>
                                <p class="small mb-3" style="white-space: pre-line">{{ $dash($p->{$f}) }}</p>
                            @endforeach

                            <div class="detail-grid mb-3">
                                <div><dt>Do you have an investor? {!! $tip('has_investor') !!}</dt><dd>{{ $yn($p->has_investor ?? ($p->investorRows() ? true : null)) }}</dd></div>
                            </div>
                            @if ($p->has_investor === false)
                                <div class="callout small mb-3">
                                    <div class="fw-semibold text-navy mb-1"><i class="bi bi-people me-1"></i> No investor yet — LEHSFF matchmaking recommended</div>
                                    A CAFI grant can only be awarded once there is a private investor.
                                    @if ($canEdit)
                                        Sign up as an Entrepreneur on the <a href="https://collaboration.lehsff.org.ls/" target="_blank" rel="noopener">LEHSFF Collaboration Platform <i class="bi bi-box-arrow-up-right"></i></a>
                                        and fill in all of your profile details — the CAFI team will be notified and will match you with suitable investors.
                                    @else
                                        The entrepreneur has been referred to LEHSFF matchmaking on the <a href="https://collaboration.lehsff.org.ls/" target="_blank" rel="noopener">Collaboration Platform</a>.
                                    @endif
                                </div>
                            @endif
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
                                    <thead><tr><th>Investor</th><th>ID / passport / reg. no.</th><th>Residence country</th><th class="text-end">Ticket size</th></tr></thead>
                                    <tbody>
                                        @foreach ($p->investorRows() as $row)
                                            <tr><td class="fw-semibold text-navy">{{ $row['name'] }}</td><td>{{ $dash($row['id_number'] ?? null) }}</td><td>{{ $dash($row['country'] ?? null) }}</td>
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
                                                <tr class="{{ ! empty($b['items']) ? 'table-light' : '' }}"><td class="small {{ ! empty($b['items']) ? 'fw-semibold text-navy' : '' }}">{{ $b['category'] }}</td>
                                                    <td class="text-end small">{{ $lsl($b['private'] ?? null) }}</td>
                                                    <td class="text-end small">{{ $lsl($b['grant'] ?? null) }}</td>
                                                    <td class="text-end small">{{ $lsl((float) ($b['private'] ?? 0) + (float) ($b['grant'] ?? 0)) }}</td></tr>
                                                @foreach ($b['items'] ?? [] as $it)
                                                    <tr><td class="small ps-4 text-muted">↳ {{ $it['name'] ?? '' }}</td>
                                                        <td class="text-end small text-muted">{{ $lsl($it['private'] ?? null) }}</td>
                                                        <td class="text-end small text-muted">{{ $lsl($it['grant'] ?? null) }}</td>
                                                        <td class="text-end small text-muted">{{ $lsl((float) ($it['private'] ?? 0) + (float) ($it['grant'] ?? 0)) }}</td></tr>
                                                @endforeach
                                            @endforeach
                                            @php $vInv = $p->investorAmount(); $vPrivOk = $vInv === null || abs($vbt['private'] - $vInv) < 1; @endphp
                                            <tr class="fw-bold"><td class="small text-navy">Total entered</td><td class="text-end small {{ $vPrivOk ? '' : 'text-danger' }}">{{ $lsl($vbt['private']) }}</td>
                                                <td class="text-end small {{ $p->budgetMatchesGrant() ? '' : 'text-danger' }}">{{ $lsl($vbt['grant']) }}</td>
                                                <td class="text-end small">{{ $lsl($vbt['private'] + $vbt['grant']) }}<div class="text-muted fw-normal">{{ $usd($vbt['private'] + $vbt['grant']) }}</div></td></tr>
                                            <tr class="table-light"><td class="small text-navy fw-semibold">Should add up to</td>
                                                <td class="text-end small fw-semibold">{{ $lsl($vInv) }}</td>
                                                <td class="text-end small fw-semibold">{{ $lsl($p->grant_requested) }}</td>
                                                <td class="text-end small fw-semibold">{{ $vInv !== null ? $lsl($vInv + (float) $p->grant_requested) : '—' }}</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                                @unless ($p->budgetMatchesGrant() && $vPrivOk)
                                    <div class="small text-danger mb-3"><i class="bi bi-exclamation-circle me-1"></i> The totals do not match the targets — the investor amount has probably changed. Please update the budget.</div>
                                @else
                                    <div class="mb-3"></div>
                                @endunless
                            @endif

                            @if ($p->has_investor !== false && ($p->grant_outside_lesotho !== null || $p->grant_budget))
                                @php $vShare = $p->lesothoSharePct(); $vBelow = $p->needsOutsideJustification(); @endphp
                                <dt class="detail-label">Spending in Lesotho {!! $tip('grant_outside_lesotho') !!}</dt>
                                <dd class="small mb-3">
                                    @if ($vShare === null)
                                        <span class="text-muted">Not answered yet.</span>
                                    @else
                                        <span class="{{ $vBelow ? 'text-danger' : 'text-success' }}"><i class="bi {{ $vBelow ? 'bi-exclamation-triangle' : 'bi-check-circle' }} me-1"></i>
                                            {{ rtrim(rtrim(number_format($vShare, 1), '0'), '.') }}% of the CAFI grant spent in Lesotho</span>
                                        · {{ $lsl($p->grant_outside_lesotho) }} outside Lesotho (minimum in Lesotho: {{ (int) $P::minLesothoPct() }}%)
                                        @if ($p->grant_outside_justification)
                                            <div class="quote mt-1 mb-0" style="white-space: pre-line">{{ $p->grant_outside_justification }}</div>
                                        @elseif ($vBelow)
                                            <div class="text-danger mt-1">Justification missing — please edit this section and explain why.</div>
                                        @endif
                                    @endif
                                </dd>
                            @endif

                            @if ($p->has_investor !== false && collect($P::BANK_FIELDS)->contains(fn ($bf) => filled($p->{$bf})))
                                <dt class="detail-label">Bank account for the grant {!! $tip('section.bank') !!}</dt>
                                <dd class="small mb-3">
                                    {{ $dash($p->bank_account_name) }} · {{ $dash($p->bank_name) }}{{ $p->bank_branch ? ', '.$p->bank_branch : '' }}
                                    · Acc. {{ $dash($p->bank_account_number) }}{{ $p->bank_account_type ? ' ('.($P::ACCOUNT_TYPES[$p->bank_account_type] ?? $p->bank_account_type).')' : '' }}
                                    @if ($p->registered_name && $p->bank_account_name && \Illuminate\Support\Str::lower(trim($p->bank_account_name)) !== \Illuminate\Support\Str::lower(trim($p->registered_name)))
                                        <div class="text-warning mt-1"><i class="bi bi-exclamation-triangle me-1"></i> The account name differs from the registered company name ({{ $p->registered_name }}).</div>
                                    @endif
                                </dd>
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
                                        <div class="col-md-5"><label class="form-label small fw-semibold">Investor name <span class="req">*</span></label>
                                            <input type="text" class="form-control form-control-sm @error('inv.investor_name') is-invalid @enderror" wire:model="inv.investor_name">
                                            @error('inv.investor_name') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                                        <div class="col-md-3"><label class="form-label small fw-semibold">ID / passport no. <span class="req">*</span></label>
                                            <input type="text" class="form-control form-control-sm @error('inv.investor_id_number') is-invalid @enderror" wire:model="inv.investor_id_number" placeholder="or company reg. no.">
                                            @error('inv.investor_id_number') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                                        <div class="col-md-2"><label class="form-label small fw-semibold">Investor type</label>
                                            <select class="form-select form-select-sm @error('inv.investor_type') is-invalid @enderror" wire:model="inv.investor_type">
                                                <option value="">Select…</option>@foreach ($P::INVESTOR_TYPES as $o)<option>{{ $o }}</option>@endforeach</select></div>
                                        <div class="col-md-2"><label class="form-label small fw-semibold">Country</label>
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
                                                        <div class="small text-muted">{{ collect([$i->investor_id_number ? 'ID '.$i->investor_id_number : null, $i->investor_type, $i->investor_country, $i->round_label])->filter()->implode(' · ') }}</div></td>
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
                            @if ($key === 'attachments') <div class="small text-muted mb-1"><i class="bi bi-translate me-1"></i> {{ $D::LANGUAGE_NOTE }}</div> @endif
                            <div class="small text-muted mb-2"><i class="bi bi-hdd me-1"></i> {{ $D::SIZE_NOTE }}</div>
                            @foreach ($docs as $type => [$dLabel, $dHint])
                                @php
                                    $doc    = $p->exists ? $p->document($type) : null;
                                    $dReq   = in_array($type, $reqDocs, true);
                                    $either = in_array($type, $D::PLAN_OR_DECK, true);
                                @endphp
                                <div class="file-row flex-wrap" wire:key="doc-{{ $type }}">
                                    <span class="file-row-icon"><i class="bi {{ $doc ? 'bi-file-earmark-check' : 'bi-file-earmark' }}"></i></span>
                                    <div class="flex-grow-1 small" style="min-width: 180px">
                                        <div class="fw-semibold text-navy">{{ $dLabel }} {!! $docTip($type) !!} @if ($dReq) <span class="req">*</span> @endif
                                            @if ($either) <span class="pill pill-muted ms-1" style="font-size:.7rem">one of these two</span> @endif</div>
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
                            <div class="small text-muted mt-2">Documents still needed: {{ collect($p->missingDocuments())->map(fn ($t) => $D::missingLabel($t))->implode(', ') }}.</div>
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