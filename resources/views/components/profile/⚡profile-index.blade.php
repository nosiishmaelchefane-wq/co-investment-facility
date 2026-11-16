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
                                'foreign_owned', 'primary_ops_lesotho', 'ownership_dispute',
                                'tx_amount_agreed', 'tx_terms_agreed', 'tx_conditions_outstanding', 'double_financing'];

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
            // 1.12 Received CAFI support? (Yes / No) → 1.13 which programmes. Stored as one list: ['none'] = No.
            $support = (array) ($p->prior_cafi_support ?? []);
            $form['prior_cafi_support'] = array_values(array_diff($support, ['none']));
            $form['prior_support_yn']   = $support === ['none'] ? 'no' : ($form['prior_cafi_support'] !== [] ? 'yes' : '');

            if ($form['obfc_status'] === '' && $p->obfc_registered !== null) {
                $form['obfc_status'] = $p->obfc_registered ? 'yes' : 'no';
            }
            $form['contact_name']  = $form['contact_name']  ?: auth()->user()->name;
            $form['contact_email'] = $form['contact_email'] ?: auth()->user()->email;
            $form['contact_phone'] = $form['contact_phone'] ?: (auth()->user()->phone ?? '');
            $form['business_models'] = $p->businessModels();
        }
        if ($section === 'legal') {
            $form['markets'] = (array) ($p->markets ?? []);
            $form['shareholder_list'] = collect($p->shareholder_list ?? [])
                ->map(fn ($r) => array_merge(EnterpriseProfile::BLANK_SHAREHOLDER, array_map(fn ($v) => $v ?? '', array_intersect_key((array) $r, EnterpriseProfile::BLANK_SHAREHOLDER))))
                ->values()->all() ?: [EnterpriseProfile::BLANK_SHAREHOLDER];
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
            // 8.2.7 / 8.3.5 are entered in US$ (Maloti shown underneath). Older answers in Maloti / Rand are converted.
            $num  = fn ($v) => is_numeric($v ?? null) ? (string) (0 + $v) : '';
            $toUsd = fn ($v) => is_numeric($v) ? round(EnterpriseProfile::toLsl((float) $v, $p->investment_currency ?: 'LSL') / EnterpriseProfile::usdRate()) : null;
            foreach (['lead_investor_amount', 'valuation'] as $mf) {
                $form[$mf] = $num(strtoupper((string) $p->investment_currency) === 'USD' ? $p->{$mf} : $toUsd($p->{$mf}));
            }
            $form['investment_currency'] = 'USD';
            $form['project_cost'] = is_numeric($p->project_cost) ? (string) round((float) $p->project_cost / EnterpriseProfile::usdRate()) : '';   // entered in US$, stored in Maloti

            // 8.4 grant plan: category => line items (US$). Older profiles: their Maloti grant column becomes lump sums.
            $plan = (array) ($p->grant_plan ?? []);
            if ($plan === [] && $p->grant_budget) {
                $plan = $this->planFromOldBudget($p->budgetRows());
            }
            $form['grant_plan'] = collect(EnterpriseProfile::GRANT_CATEGORIES)->map(fn ($c, $k) => collect($plan[$k] ?? [])
                ->map(fn ($it) => array_merge(EnterpriseProfile::BLANK_PLAN_ITEM, array_intersect_key((array) $it, EnterpriseProfile::BLANK_PLAN_ITEM), [
                    'quantity'  => $num($it['quantity'] ?? null),
                    'unit_cost' => $num($it['unit_cost'] ?? null),
                    'amount'    => $num($it['amount'] ?? null),
                    'lump_sum'  => (bool) ($it['lump_sum'] ?? false),
                ]))->values()->all())->all();

            // US$ is the main currency on the profile: Maloti amounts are shown in US$ while editing, saved back in Maloti
            $asUsd = fn ($m) => is_numeric($m) ? (string) round((float) $m / EnterpriseProfile::usdRate()) : '';
            $form['annual_revenue'] = $asUsd($p->annual_revenue);
            $history = (array) ($p->investment_history ?? []);
            $form['investment_history'] = collect(EnterpriseProfile::FUNDING_SOURCES)
                ->map(fn ($l, $k) => ['amount' => $asUsd($history[$k]['amount'] ?? null), 'date' => $history[$k]['date'] ?? ''])->all();
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

    /** 8.4 B: add / remove an item or activity under a category */
    public function addPlanItem(string $cat): void
    {
        if (isset(EnterpriseProfile::GRANT_CATEGORIES[$cat]) && count($this->form['grant_plan'][$cat] ?? []) < 25) {
            $this->form['grant_plan'][$cat][] = EnterpriseProfile::BLANK_PLAN_ITEM;
        }
    }

    public function removePlanItem(string $cat, int $i): void
    {
        unset($this->form['grant_plan'][$cat][$i]);
        $this->form['grant_plan'][$cat] = array_values($this->form['grant_plan'][$cat] ?? []);
    }

    /** Older Maloti budget (grant column) → US$ lump sums under the new categories */
    protected function planFromOldBudget(array $rows): array
    {
        $map = ['equipment' => 'productive_assets', 'capital' => 'productive_assets', 'productive' => 'productive_assets', 'technology' => 'technology', 'digital' => 'technology',
                'working' => 'working_capital', 'product' => 'product_dev', 'market' => 'market_expansion'];
        $rate = EnterpriseProfile::usdRate();
        $plan = [];
        foreach ($rows as $r) {
            $cat = 'other';
            foreach ($map as $needle => $key) {
                if (stripos((string) ($r['category'] ?? ''), $needle) !== false) { $cat = $key; break; }
            }
            $lines = collect($r['items'] ?? [])->filter(fn ($it) => (float) ($it['grant'] ?? 0) > 0);
            if ($lines->isEmpty() && (float) ($r['grant'] ?? 0) > 0) {
                $lines = collect([['name' => $r['category'], 'grant' => $r['grant']]]);
            }
            foreach ($lines as $it) {
                $plan[$cat][] = ['item' => (string) ($it['name'] ?? ''), 'description' => '', 'quantity' => '', 'unit_cost' => '', 'lump_sum' => true,
                                 'amount' => floor((float) $it['grant'] / $rate), 'location' => 'lesotho', 'country' => ''];   // round down so the converted total never exceeds the grant
            }
        }

        return $plan;
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
            'form.prior_support_yn.required'    => 'Please answer this question.',
            'form.prior_cafi_support.required_if' => 'Choose the programme(s) that supported you.',
            'form.year_registered.required_if'  => 'Enter the year the company was registered with the OBFC.',
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
            'form.tx_amount.required_if'        => 'Enter the investment amount you will receive.',
            'form.double_financing_details.required_if' => 'Identify the expenditure and the other funding source.',
            'form.grant_plan.*.*.item.required_with' => 'Name the item or activity.',
            'form.grant_plan.*.*.country.required_if' => 'Enter the country.',
        ], $this->fieldLabels());

        if ($section === 'legal' && ! $this->shareholdersAreValid()) {
            return;
        }

        if ($section === 'business' && ! $this->planIsValid()) {
            return;
        }

        $p = EnterpriseProfile::where('user_id', $this->userId)->first() ?? new EnterpriseProfile();
        $p->forceFill(['user_id' => $this->userId] + $this->values($section))->save();   // forceFill: a missing column errors instead of being skipped
        $p->load('documents')->refreshStatus();

        $this->editing = null;
        unset($this->profile);

        $this->dispatch('notify', type: 'success', message: EnterpriseProfile::SECTIONS[$section][0].' saved.');
    }

    /** The eligible private investment from the form (8.2.7, entered in US$), in Maloti */
    protected function formInvestorAmount(): ?float
    {
        if (($this->form['has_investor'] ?? '') !== 'yes') {
            return null;
        }
        $amount = is_numeric($this->form['lead_investor_amount'] ?? null) ? (float) $this->form['lead_investor_amount'] : null;

        return $amount !== null && $amount > 0 ? EnterpriseProfile::toLsl($amount, 'USD') : null;
    }

    /**
     * 8.4 grant plan (only when there is an investor): within the indicative grant and the lifetime cap,
     * every line priced, a country and an explanation for spending outside Lesotho.
     */
    protected function planIsValid(): bool
    {
        if (($this->form['has_investor'] ?? '') !== 'yes') {
            return true;
        }

        $plan   = $this->form['grant_plan'] ?? [];
        $totals = EnterpriseProfile::planTotals($plan);
        $rate   = EnterpriseProfile::usdRate();
        $grant  = EnterpriseProfile::matchingGrant($this->formInvestorAmount());
        $ok     = true;

        foreach ($plan as $cat => $items) {
            foreach ($items as $i => $it) {
                $has = filled($it['item'] ?? null) || filled($it['description'] ?? null) || EnterpriseProfile::planItemTotal($it) > 0;
                if ($has && EnterpriseProfile::planItemTotal($it) <= 0) {
                    $this->addError("form.grant_plan.$cat.$i.total", ! empty($it['lump_sum']) ? 'Enter the amount.' : 'Enter the quantity and unit cost.');
                    $ok = false;
                }
            }
        }
        if (! $ok) {
            $this->addError('form.grant_plan', 'Some budget lines have no cost — see the lines marked in red.');
            return false;
        }

        if ($grant !== null && $totals['total'] <= 0) {
            $this->addError('form.grant_plan', 'Add at least one item or activity the CAFI grant will pay for.');
            return false;
        }
        if ($grant !== null && $totals['total'] * $rate > $grant + 1) {
            $this->addError('form.grant_plan', 'The proposed grant utilisation (US$ '.number_format($totals['total']).') is more than your indicative CAFI grant (US$ '.number_format($grant / $rate).'). Reduce the budget.');
            $ok = false;
        }
        $avail = $this->profile->availableCumulativeLsl();
        if ($totals['total'] * $rate > $avail + 1) {
            $this->addError('form.grant_plan', 'The proposed grant utilisation is more than the grant still available to this enterprise under the US$ '.number_format((float) config('cif.co_investment.cumulative_cap_usd', 350000)).' lifetime cap (US$ '.number_format($avail / $rate).').');
            $ok = false;
        }
        if ($totals['outside'] > 0 && blank($this->form['grant_outside_justification'] ?? null)) {
            $this->addError('form.grant_outside_justification', 'Some of the grant is spent outside Lesotho — explain how this expenditure directly supports the growth of the Lesotho enterprise.');
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
                $field === 'grant_plan'                => collect(EnterpriseProfile::GRANT_CATEGORIES)->map(fn ($c, $k) => collect($v[$k] ?? [])
                                                              ->filter(fn ($it) => filled($it['item'] ?? null) || EnterpriseProfile::planItemTotal((array) $it) > 0)
                                                              ->map(fn ($it) => [
                                                                  'item'        => trim((string) ($it['item'] ?? '')),
                                                                  'description' => trim((string) ($it['description'] ?? '')),
                                                                  'quantity'    => empty($it['lump_sum']) && is_numeric($it['quantity'] ?? null) ? (float) $it['quantity'] : null,
                                                                  'unit_cost'   => empty($it['lump_sum']) && is_numeric($it['unit_cost'] ?? null) ? (float) $it['unit_cost'] : null,
                                                                  'lump_sum'    => ! empty($it['lump_sum']),
                                                                  'amount'      => ! empty($it['lump_sum']) && is_numeric($it['amount'] ?? null) ? (float) $it['amount'] : null,
                                                                  'location'    => ($it['location'] ?? 'lesotho') === 'outside' ? 'outside' : 'lesotho',
                                                                  'country'     => ($it['location'] ?? '') === 'outside' ? trim((string) ($it['country'] ?? '')) : '',
                                                              ])->values()->all())->filter()->all() ?: null,
                $field === 'es_checklist'              => collect($v ?? [])->filter(fn ($a, $k) => isset(EnterpriseProfile::ES_CHECKLIST[$k]) && isset(EnterpriseProfile::ES_ANSWERS[$a]))->all(),
                $field === 'es_notes'                  => collect($v ?? [])->filter(fn ($t, $k) => isset(EnterpriseProfile::ES_CHECKLIST[$k])
                                                              && EnterpriseProfile::esFollowUp($k, $f['es_checklist'][$k] ?? null) !== null && filled($t))
                                                              ->map(fn ($t) => trim($t))->all() ?: null,
                is_array($v)                           => array_values($v),
                default                                => $v === '' ? null : $v,
            };
        }

        // 1.12 / 1.13: No → ['none']; Yes → the programmes ticked
        if ($section === 'company') {
            $yn = $f['prior_support_yn'] ?? '';
            $values['prior_cafi_support'] = match ($yn) {
                'no'    => ['none'],
                'yes'   => array_values(array_diff((array) ($f['prior_cafi_support'] ?? []), ['none'])),
                default => [],
            };
            // 1.5 / 1.6 only apply when the enterprise is registered with the OBFC
            if ($values['obfc_status'] !== 'yes') {
                $values['year_registered'] = null;
                $values['registration_no'] = null;
            }
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
            foreach ([...EnterpriseProfile::INVESTOR_FIELDS, 'grant_plan', 'grant_outside_justification', 'grant_lesotho_70', 'double_financing', 'double_financing_details', 'grant_growth_support'] as $f) {
                $values[$f] = null;
            }
        }
        if ($section === 'business') {
            $values['investment_currency'] = $values['has_investor'] === false ? null : 'USD';
            $values['project_cost'] = is_numeric($values['project_cost'] ?? null) ? EnterpriseProfile::toLsl((float) $values['project_cost'], 'USD') : null;
            $values['annual_revenue'] = is_numeric($values['annual_revenue'] ?? null) ? EnterpriseProfile::toLsl((float) $values['annual_revenue'], 'USD') : null;
            if (is_array($values['investment_history'] ?? null)) {
                $values['investment_history'] = collect($values['investment_history'])->map(fn ($r) => ['amount' => $r['amount'] !== null ? EnterpriseProfile::toLsl((float) $r['amount'], 'USD') : null] + $r)->all();
            }
            // 8.3.3 is not asked again: once the amount is agreed it is the amount given in 8.2.7
            $values['tx_amount'] = ($values['tx_amount_agreed'] ?? null) === true ? ($values['lead_investor_amount'] ?? null) : null;
            if (($values['double_financing'] ?? null) !== true) {
                $values['double_financing_details'] = null;
            }
            if (EnterpriseProfile::planTotals($values['grant_plan'] ?? [])['outside'] <= 0) {
                $values['grant_outside_justification'] = null;
            } elseif (is_string($values['grant_outside_justification'] ?? null)) {
                $values['grant_outside_justification'] = trim($values['grant_outside_justification']);
            }
        }
        if ($section === 'legal') {
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
                // Form v2 §1 Company profile (1.1–1.14)
                'form.registered_name'       => ['required', 'string', 'max:255'],
                'form.short_name'            => ['nullable', 'string', 'max:120'],
                'form.month_commenced'       => ['required', 'integer', 'between:1,12', function ($attribute, $value, $fail) {
                                                    if (is_numeric($value) && (int) ($this->form['year_established'] ?? 0) === (int) date('Y') && (int) $value > (int) date('n')) {
                                                        $fail('The month cannot be in the future.');
                                                    }
                                                }],
                'form.year_established'      => ['required', 'integer', 'between:1900,'.date('Y')],
                'form.obfc_status'           => ['required', Rule::in(array_keys(EnterpriseProfile::YES_NO_PROGRESS))],
                'form.year_registered'       => ['required_if:form.obfc_status,yes', 'nullable', 'integer', 'between:1900,'.date('Y')],
                'form.registration_no'       => ['nullable', 'string', 'max:60'],
                'form.company_stage'         => ['required', Rule::in(array_keys(EnterpriseProfile::STAGES))],
                'form.activity'              => ['required', 'string', $words(100)],
                'form.products_services'     => ['nullable', 'string', 'max:2000'],
                'form.website'               => ['nullable', 'string', 'max:255'],
                'form.address'               => ['required', 'string', 'max:255'],
                'form.city'                  => ['required', 'string', 'max:80'],
                'form.district'              => ['required', Rule::in(EnterpriseProfile::DISTRICTS)],
                'form.sector'                => ['required', Rule::in(EnterpriseProfile::SECTORS)],
                'form.sector_other'          => ['required_if:form.sector,Other', 'nullable', 'string', 'max:120'],
                'form.sub_sector'            => ['required', 'string', 'max:120'],
                'form.business_models'       => ['required', 'array', 'min:1'],
                'form.business_models.*'     => [Rule::in(array_keys(EnterpriseProfile::BUSINESS_MODELS))],
                'form.women_owned_managed'   => $yesNo,
                'form.youth_owned_managed'   => $yesNo,
                'form.basotho_owned_pct'     => $pct,
                'form.women_owned_pct'       => $pct,
                'form.youth_owned_pct'       => $pct,
                'form.prior_support_yn'      => ['required', 'in:yes,no'],
                'form.prior_cafi_support'    => ['required_if:form.prior_support_yn,yes', 'array'],
                'form.prior_cafi_support.*'  => [Rule::in(array_values(array_diff(array_keys(EnterpriseProfile::PRIOR_SUPPORT), ['none'])))],
                'form.prior_support_details' => [($this->form['prior_support_yn'] ?? '') === 'yes' ? 'required' : 'nullable', 'string', 'max:2000'],
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
                // Form v2 §2 Company legal status (2.1–2.8) + current markets
                'form.legal_form'         => ['required', Rule::in(EnterpriseProfile::LEGAL_FORMS)],
                'form.shareholder_list'               => ['array'],
                'form.shareholder_list.*.name'        => ['nullable', 'string', 'max:255'],
                'form.shareholder_list.*.id_number'   => ['required_with:form.shareholder_list.*.name', 'nullable', 'string', 'max:40'],
                'form.shareholder_list.*.nationality' => ['required_with:form.shareholder_list.*.name', 'nullable', 'string', 'max:60'],
                'form.shareholder_list.*.gender'      => ['required_with:form.shareholder_list.*.name', 'nullable', Rule::in(EnterpriseProfile::GENDERS)],
                'form.shareholder_list.*.age'         => ['required_with:form.shareholder_list.*.name', 'nullable', 'integer', 'between:0,120'],
                'form.shareholder_list.*.percent'     => ['required_with:form.shareholder_list.*.name', 'nullable', 'numeric', 'gt:0', 'max:100'],
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
                'form.customers'                   => ['required', 'string', 'max:2000'],
                'form.usp'                         => ['nullable', 'string', 'max:2000'],
                'form.marketing_strategy'          => ['nullable', 'string', 'max:2000'],
                'form.competition'                 => ['nullable', 'string', 'max:2000'],
                'form.annual_revenue'              => $money('annual_revenue'),
                'form.has_investor'                => $yesNo,
                // 8.2 Investor information
                'form.lead_investor_name'          => [$hasInvestor ? 'required' : 'nullable', 'string', 'max:255'],
                'form.lead_investor_contact_name'  => [$hasInvestor ? 'required' : 'nullable', 'string', 'max:255'],
                'form.lead_investor_email'         => [$hasInvestor ? 'required' : 'nullable', 'email', 'max:255'],
                'form.lead_investor_phone'         => ['nullable', 'string', 'max:40'],
                'form.lead_investor_website'       => ['nullable', 'string', 'max:255'],
                'form.investor_type'               => [$hasInvestor ? 'required' : 'nullable', Rule::in(EnterpriseProfile::INVESTOR_TYPES)],
                'form.lead_investor_country'       => [$hasInvestor ? 'required' : 'nullable', 'string', 'max:80'],
                'form.investment_currency'         => ['nullable', Rule::in(array_keys(EnterpriseProfile::CURRENCIES))],
                'form.lead_investor_amount'        => [$hasInvestor ? 'required' : 'nullable', 'numeric', 'gt:0', 'max:9999999999999'],
                'form.transaction_status'          => [$hasInvestor ? 'required' : 'nullable', Rule::in(array_keys(EnterpriseProfile::TRANSACTION_STATUSES))],
                'form.investor_commitment'         => [$hasInvestor ? 'required' : 'nullable', Rule::in(array_keys(EnterpriseProfile::COMMITMENT))],
                // 8.3 Transaction information
                'form.participation'               => [$hasInvestor ? 'required' : 'nullable', Rule::in(array_keys(EnterpriseProfile::PARTICIPATION))],
                'form.tx_amount_agreed'            => [$hasInvestor ? 'required' : 'nullable', 'in:yes,no'],
                'form.tx_amount'                   => ['nullable', 'numeric', 'gt:0', 'max:9999999999999'],
                'form.tx_terms_agreed'             => [$hasInvestor ? 'required' : 'nullable', 'in:yes,no'],
                'form.valuation'                   => ['nullable', 'numeric', 'gt:0', 'max:9999999999999'],
                'form.valuation_by'                => ['nullable', Rule::in(array_keys(EnterpriseProfile::VALUATION_BY))],
                'form.valuation_method'            => ['nullable', Rule::in(array_keys(EnterpriseProfile::VALUATION_METHODS))],
                'form.expected_close'              => [$hasInvestor ? 'required' : 'nullable', 'string', 'max:120'],
                'form.tx_conditions_outstanding'   => [$hasInvestor ? 'required' : 'nullable', 'in:yes,no'],
                'form.tx_description'              => [$hasInvestor ? 'required' : 'nullable', 'string', $words(100)],
                'form.project_cost'                => ['required', 'numeric', 'gt:0', 'max:9999999999999', function ($attribute, $value, $fail) {
                                                          $grant = EnterpriseProfile::matchingGrant($this->formInvestorAmount());
                                                          if ($grant !== null && is_numeric($value) && EnterpriseProfile::toLsl((float) $value, 'USD') + 0.5 < $grant) {
                                                              $fail('The total project cost cannot be less than the indicative CAFI grant (US$ '.number_format($grant / EnterpriseProfile::usdRate()).'). It must be equal to it or more.');
                                                          }
                                                      }],
                'form.investment_history.*.amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
                'form.investment_history.*.date'   => ['nullable', 'date', 'before_or_equal:today'],
                // 8.4 Proposed use of the CAFI grant (US$)
                'form.grant_plan'                  => ['nullable', 'array'],
                'form.grant_plan.*'                => ['array', 'max:25'],
                'form.grant_plan.*.*.item'         => ['nullable', 'string', 'max:150', 'required_with:form.grant_plan.*.*.quantity,form.grant_plan.*.*.unit_cost,form.grant_plan.*.*.amount'],
                'form.grant_plan.*.*.description'  => ['nullable', 'string', 'max:500'],
                'form.grant_plan.*.*.quantity'     => ['nullable', 'numeric', 'gt:0', 'max:1000000'],
                'form.grant_plan.*.*.unit_cost'    => ['nullable', 'numeric', 'gt:0', 'max:9999999999'],
                'form.grant_plan.*.*.amount'       => ['nullable', 'numeric', 'gt:0', 'max:9999999999'],
                'form.grant_plan.*.*.location'     => ['nullable', 'in:lesotho,outside'],
                'form.grant_plan.*.*.country'      => ['required_if:form.grant_plan.*.*.location,outside', 'nullable', 'string', 'max:80'],
                'form.grant_outside_justification' => ['nullable', 'string', 'max:2000'],
                'form.grant_lesotho_70'            => [$hasInvestor ? 'required' : 'nullable', Rule::in(array_keys(EnterpriseProfile::LESOTHO_70))],
                'form.double_financing'            => [$hasInvestor ? 'required' : 'nullable', 'in:yes,no'],
                'form.double_financing_details'    => ['required_if:form.double_financing,yes', 'nullable', 'string', 'max:2000'],
                'form.grant_growth_support'        => [$hasInvestor ? 'required' : 'nullable', 'string', $words(150)],
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
            'form.registered_name' => 'company official name', 'form.short_name' => 'trading name', 'form.prior_support_yn' => 'CAFI support', 'form.year_established' => 'year of establishment',
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
            'form.annual_revenue' => 'annual revenue',
            'form.lead_investor_name' => 'lead investor name', 'form.lead_investor_contact_name' => 'contact name', 'form.lead_investor_email' => 'contact e-mail',
            'form.lead_investor_phone' => 'contact telephone', 'form.lead_investor_website' => 'investor website', 'form.lead_investor_country' => 'investor country',
            'form.investment_currency' => 'currency', 'form.lead_investor_amount' => 'investment amount from the investor', 'form.investor_commitment' => 'investor commitment',
            'form.participation' => 'investment instrument', 'form.investor_type' => 'investor type', 'form.transaction_status' => 'investor engagement status',
            'form.tx_amount_agreed' => 'whether the amount has been agreed', 'form.tx_amount' => 'agreed investment amount', 'form.tx_terms_agreed' => 'whether the principal terms are agreed',
            'form.valuation' => 'valuation', 'form.valuation_by' => 'who developed the valuation', 'form.valuation_method' => 'valuation method',
            'form.expected_close' => 'expected close or deployment', 'form.tx_conditions_outstanding' => 'material conditions outstanding', 'form.tx_description' => 'transaction description',
            'form.project_cost' => 'total project cost',
            'form.grant_plan.*.*.item' => 'item / activity', 'form.grant_plan.*.*.description' => 'description', 'form.grant_plan.*.*.quantity' => 'quantity',
            'form.grant_plan.*.*.unit_cost' => 'unit cost', 'form.grant_plan.*.*.amount' => 'amount', 'form.grant_plan.*.*.country' => 'country',
            'form.grant_outside_justification' => 'explanation', 'form.grant_lesotho_70' => 'answer', 'form.double_financing' => 'answer',
            'form.double_financing_details' => 'expenditure and funding source', 'form.grant_growth_support' => 'answer',
            'form.investment_history.*.amount' => 'amount', 'form.investment_history.*.date' => 'date of last investment',
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
            'amount' => (string) round((float) $i->amount / EnterpriseProfile::usdRate()), 'invested_on' => $i->invested_on?->format('Y-m-d') ?? '',
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

        $values = collect($this->inv)->map(fn ($v) => is_string($v) ? (trim($v) === '' ? null : trim($v)) : $v)->all();
        $values['amount'] = EnterpriseProfile::toLsl((float) $values['amount'], 'USD');   // entered in US$, stored in Maloti
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
        $usdMain  = fn ($m) => $m === null || $m === '' ? '—' : 'US$ '.number_format((float) $m / max($rate, 0.0001));   // a Maloti amount shown in US$ (main currency)
        $mFromUsd = fn ($u) => ! is_numeric($u) || (float) $u <= 0 ? '' : '≈ M '.number_format((float) $u * max($rate, 0.0001));   // a US$ input → Maloti hint
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
                                {{-- ===== Form v2 §1 Company profile, in the form's order (1.1–1.14) ===== --}}
                                <div class="col-md-6"><label class="form-label small fw-semibold">1.1 Official company name {!! $tip('registered_name') !!} <span class="req">*</span></label>
                                    <input type="text" class="form-control @error('form.registered_name') is-invalid @enderror" wire:model="form.registered_name">
                                    @error('form.registered_name') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                                <div class="col-md-6"><label class="form-label small fw-semibold">1.2 Trading name, if different {!! $tip('short_name') !!}</label>
                                    <input type="text" class="form-control @error('form.short_name') is-invalid @enderror" wire:model="form.short_name">
                                    @error('form.short_name') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>

                                <div class="col-md-5"><label class="form-label small fw-semibold">1.3 Month and year operations commenced {!! $tip('year_established') !!} <span class="req">*</span></label>
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
                                </div>

                                <div class="col-md-7"><label class="form-label small fw-semibold">1.4 Is the enterprise registered with OBFC in Lesotho? {!! $tip('obfc_status') !!} <span class="req">*</span></label>
                                    <div>{!! $radios('obfc_status', $P::YES_NO_PROGRESS, true) !!}</div>
                                    @error('form.obfc_status') <div class="text-danger small">{{ $message }}</div> @enderror</div>
                                @if (($form['obfc_status'] ?? '') === 'yes')
                                    <div class="col-md-5"><label class="form-label small fw-semibold">1.5 Year of registration {!! $tip('year_registered') !!} <span class="req">*</span></label>
                                        <input type="number" min="1900" max="{{ date('Y') }}" class="form-control @error('form.year_registered') is-invalid @enderror" wire:model="form.year_registered">
                                        @error('form.year_registered') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                                    <div class="col-md-7"><label class="form-label small fw-semibold">1.6 Registration number {!! $tip('registration_no') !!}</label>
                                        <input type="text" class="form-control @error('form.registration_no') is-invalid @enderror" wire:model="form.registration_no">
                                        @error('form.registration_no') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                                @elseif (in_array($form['obfc_status'] ?? '', ['no', 'in_progress'], true))
                                    <div class="col-12"><div class="callout callout-warning small mb-0"><i class="bi bi-exclamation-triangle me-1"></i> {{ $P::REGISTRATION_NOTE }} Registration with the OBFC must be completed before the Grant Agreement is signed — upload a signed commitment to register under Legal status.</div></div>
                                @endif

                                <div class="col-12"><label class="form-label small fw-semibold">1.7 In your opinion, which stage of the business lifecycle does your startup / SME fall under? {!! $tip('company_stage') !!} <span class="req">*</span></label>
                                    <select class="form-select @error('form.company_stage') is-invalid @enderror" wire:model="form.company_stage" style="max-width: 420px">
                                        <option value="">Select…</option>
                                        @foreach ($P::STAGES as $k => $lbl) <option value="{{ $k }}">{{ $lbl }}</option> @endforeach
                                    </select>
                                    @error('form.company_stage') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>

                                <div class="col-12 border-top pt-3"><div class="fw-semibold text-navy small">1.8 Physical business address {!! $tip('address') !!}</div></div>
                                <div class="col-md-6"><label class="form-label small fw-semibold">Address <span class="req">*</span></label>
                                    <input type="text" class="form-control @error('form.address') is-invalid @enderror" wire:model="form.address" placeholder="Plot / street / village or area">
                                    @error('form.address') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                                <div class="col-md-3"><label class="form-label small fw-semibold">Town {!! $tip('city') !!} <span class="req">*</span></label>
                                    <input type="text" class="form-control @error('form.city') is-invalid @enderror" wire:model="form.city">
                                    @error('form.city') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                                <div class="col-md-3"><label class="form-label small fw-semibold">District {!! $tip('district') !!} <span class="req">*</span></label>
                                    <select class="form-select @error('form.district') is-invalid @enderror" wire:model="form.district">
                                        <option value="">Select…</option>@foreach ($P::DISTRICTS as $o)<option>{{ $o }}</option>@endforeach
                                        @if (filled($form['district'] ?? null) && ! in_array($form['district'], $P::DISTRICTS, true))<option>{{ $form['district'] }}</option>@endif
                                    </select>
                                    @error('form.district') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>

                                <div class="col-md-6"><label class="form-label small fw-semibold">1.9 Primary sector {!! $tip('sector') !!} <span class="req">*</span></label>
                                    <select class="form-select @error('form.sector') is-invalid @enderror" wire:model.live="form.sector">
                                        <option value="">Select…</option>@foreach ($P::SECTORS as $o)<option>{{ $o }}</option>@endforeach
                                        @if (filled($form['sector'] ?? null) && ! in_array($form['sector'], $P::SECTORS, true))<option>{{ $form['sector'] }}</option>@endif
                                    </select>
                                    @error('form.sector') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                                <div class="col-md-6"><label class="form-label small fw-semibold">Sub-sector {!! $tip('sub_sector') !!} <span class="req">*</span></label>
                                    <input type="text" class="form-control @error('form.sub_sector') is-invalid @enderror" wire:model="form.sub_sector" placeholder="e.g. Fruit and vegetable processing">
                                    @error('form.sub_sector') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                                @if (($form['sector'] ?? '') === 'Other')
                                    <div class="col-md-6"><label class="form-label small fw-semibold">Please specify your sector {!! $tip('sector_other') !!} <span class="req">*</span></label>
                                        <input type="text" class="form-control @error('form.sector_other') is-invalid @enderror" wire:model="form.sector_other" placeholder="e.g. Renewable energy">
                                        @error('form.sector_other') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                                @endif

                                @php $sumWords = str_word_count(strip_tags((string) ($form['activity'] ?? ''))); @endphp
                                <div class="col-12"><label class="form-label small fw-semibold">1.10 Company summary or narrative of the main business activity (max. 100 words) {!! $tip('activity') !!} <span class="req">*</span></label>
                                    <textarea rows="4" class="form-control @error('form.activity') is-invalid @enderror" wire:model.live.debounce.600ms="form.activity"></textarea>
                                    <div class="form-text {{ $sumWords > 100 ? 'text-danger' : '' }}">{{ $sumWords }} / 100 words</div>
                                    @error('form.activity') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                                <div class="col-12"><label class="form-label small fw-semibold">1.11 Main products and / or services the enterprise currently produces or provides {!! $tip('products_services') !!}</label>
                                    <textarea rows="3" class="form-control @error('form.products_services') is-invalid @enderror" wire:model="form.products_services" placeholder="One per line, e.g. Dried fruit · Fruit juice · Contract drying for farmers"></textarea>
                                    @error('form.products_services') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>

                                <div class="col-12 border-top pt-3"><label class="form-label small fw-semibold">1.12 Has the enterprise received support from a CAFI-funded programme? {!! $tip('prior_cafi_support') !!} <span class="req">*</span></label>
                                    <div>{!! $radios('prior_support_yn', ['yes' => 'Yes', 'no' => 'No'], true) !!}</div>
                                    @error('form.prior_support_yn') <div class="text-danger small">{{ $message }}</div> @enderror</div>
                                @if (($form['prior_support_yn'] ?? '') === 'yes')
                                    <div class="col-12"><label class="form-label small fw-semibold">1.13 Which programme, and the type of support received? <span class="req">*</span></label>
                                        <div class="d-flex flex-wrap gap-3">
                                            @foreach ($P::PRIOR_SUPPORT_CHOICES as $k)
                                                <label class="form-check mb-0"><input type="checkbox" class="form-check-input" value="{{ $k }}" wire:model="form.prior_cafi_support"> <span class="form-check-label small">{{ $P::PRIOR_SUPPORT[$k] }}</span></label>
                                            @endforeach
                                        </div>
                                        @php $legacySupport = array_diff((array) ($form['prior_cafi_support'] ?? []), $P::PRIOR_SUPPORT_CHOICES); @endphp
                                        @if ($legacySupport)
                                            <div class="form-text">Also recorded earlier: {{ $p->labels($P::PRIOR_SUPPORT, array_values($legacySupport)) }}.</div>
                                        @endif
                                        @error('form.prior_cafi_support') <div class="text-danger small">{{ $message }}</div> @enderror</div>
                                    <div class="col-12"><label class="form-label small fw-semibold">1.14 Please explain the support received {!! $tip('prior_support_details') !!} <span class="req">*</span></label>
                                        <textarea rows="2" class="form-control @error('form.prior_support_details') is-invalid @enderror" wire:model="form.prior_support_details"></textarea>
                                        @error('form.prior_support_details') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                                @endif

                                {{-- ===== Additional company information ===== --}}
                                <div class="col-12 border-top pt-3"><div class="fw-semibold text-navy small">Additional information</div></div>
                                <div class="col-md-6"><label class="form-label small fw-semibold">Website {!! $tip('website') !!}</label>
                                    <input type="text" class="form-control @error('form.website') is-invalid @enderror" wire:model="form.website" placeholder="https://">
                                    @error('form.website') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
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
                                <div><dt>Trading name {!! $tip('short_name') !!}</dt><dd>{{ $dash($p->short_name) }}</dd></div>
                                <div><dt>Operations commenced {!! $tip('year_established') !!}</dt><dd>{{ $dash($p->commencedLabel()) }}
                                    @if ($readOnly && $p->operatingUnderSixMonths()) <span class="pill pill-warn ms-1"><i class="bi bi-exclamation-circle"></i> Under 6 months</span> @endif</dd></div>
                                <div><dt>Registered with OBFC {!! $tip('obfc_status') !!}</dt><dd>{{ $p->obfc_status ? $ynp($p->obfc_status) : $yn($p->obfc_registered) }}</dd></div>
                                <div><dt>Year of registration {!! $tip('year_registered') !!}</dt><dd>{{ $dash($p->year_registered) }}</dd></div>
                                <div><dt>Registration number {!! $tip('registration_no') !!}</dt><dd>{{ $dash($p->registration_no) }}</dd></div>
                                <div><dt>Business lifecycle stage {!! $tip('company_stage') !!}</dt><dd>{{ $P::STAGES[$p->company_stage ?? ''] ?? '—' }}</dd></div>
                                <div><dt>Website {!! $tip('website') !!}</dt><dd>{{ $dash($p->website) }}</dd></div>
                                <div><dt>Address {!! $tip('address') !!}</dt><dd>{{ collect([$p->address, $p->city, $p->district])->filter()->implode(', ') ?: '—' }}</dd></div>
                                <div><dt>Sector {!! $tip('sector') !!}</dt><dd>{{ $dash($p->sectorLabel()) }}</dd></div>
                                <div><dt>Sub-sector {!! $tip('sub_sector') !!}</dt><dd>{{ $dash($p->sub_sector) }}</dd></div>
                                <div><dt>Business model {!! $tip('business_models') !!}</dt><dd>{{ $p->businessModelLabel() ?? '—' }}</dd></div>
                                <div><dt>Women owned or led {!! $tip('women_owned_managed') !!}</dt><dd>{{ $yn($p->women_owned_managed) }}</dd></div>
                                <div><dt>Youth (18–35) owned or led {!! $tip('youth_owned_managed') !!}</dt><dd>{{ $yn($p->youth_owned_managed) }}</dd></div>
                                <div><dt>Shareholding {!! $tip('basotho_owned_pct') !!}</dt><dd>{{ $p->ownershipLine() ?? '—' }}</dd></div>
                                <div><dt>Support from a CAFI-funded programme {!! $tip('prior_cafi_support') !!}</dt><dd>{{ $p->noPriorSupport() ? ((array) $p->prior_cafi_support === ['none'] ? 'No' : '—') : 'Yes — '.$p->labels($P::PRIOR_SUPPORT, array_values(array_diff((array) $p->prior_cafi_support, ['none']))) }}</dd></div>
                            </div>
                            @if ($p->prior_support_details) <div class="quote mt-3 mb-0">{{ $p->prior_support_details }}<div class="quote-meta">Support received</div></div> @endif
                            @if ($p->activity) <div class="quote mt-3 mb-0">{{ $p->activity }}<div class="quote-meta">Company summary</div></div> @endif
                            @if ($p->products_services)
                                <dt class="detail-label mt-3">Main products and / or services {!! $tip('products_services') !!}</dt>
                                <p class="small mb-0" style="white-space: pre-line">{{ $p->products_services }}</p>
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
                                <div class="col-md-6"><label class="form-label small fw-semibold">2.1 Legal form of the enterprise {!! $tip('legal_form') !!} <span class="req">*</span></label>
                                    <select class="form-select @error('form.legal_form') is-invalid @enderror" wire:model="form.legal_form">
                                        <option value="">Select…</option>@foreach ($P::LEGAL_FORMS as $o)<option>{{ $o }}</option>@endforeach
                                        @if (filled($form['legal_form'] ?? null) && ! in_array($form['legal_form'], $P::LEGAL_FORMS, true))<option>{{ $form['legal_form'] }}</option>@endif
                                    </select>
                                    @error('form.legal_form') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                                @if ($p->obfc_registered === false || in_array($p->obfc_status, ['no', 'in_progress'], true))
                                    <div class="col-md-6 d-flex align-items-end"><div class="callout callout-warning small mb-0 w-100"><i class="bi bi-exclamation-triangle me-1"></i> Not yet registered with the OBFC (Company profile 1.4) — upload a signed commitment to register below.</div></div>
                                @endif

                                <div class="col-md-6"><label class="form-label small fw-semibold">2.2 Is the enterprise registered in another country? {!! $tip('foreign_registered') !!} <span class="req">*</span></label>
                                    <div class="btn-group btn-group-sm d-flex" style="max-width: 200px">
                                        @foreach (['yes' => 'Yes', 'no' => 'No'] as $v => $vl)
                                            <input type="radio" class="btn-check" id="foreign-{{ $v }}" value="{{ $v }}" wire:model.live="form.foreign_registered">
                                            <label class="btn btn-outline-secondary" for="foreign-{{ $v }}">{{ $vl }}</label>
                                        @endforeach
                                    </div>
                                    @error('form.foreign_registered') <div class="text-danger small">{{ $message }}</div> @enderror</div>
                                @if (($form['foreign_registered'] ?? '') === 'yes')
                                    <div class="col-md-3"><label class="form-label small fw-semibold">2.3 Country {!! $tip('foreign_country') !!} <span class="req">*</span></label>
                                        <input type="text" class="form-control @error('form.foreign_country') is-invalid @enderror" wire:model="form.foreign_country">
                                        @error('form.foreign_country') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                                    <div class="col-md-3"><label class="form-label small fw-semibold">2.3 Registration no. {!! $tip('foreign_registration_no') !!} <span class="req">*</span></label>
                                        <input type="text" class="form-control @error('form.foreign_registration_no') is-invalid @enderror" wire:model="form.foreign_registration_no">
                                        @error('form.foreign_registration_no') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                                @endif

                                {{-- 2.4 Shareholders: table, or upload the list below --}}
                                @php $shTotal = collect($form['shareholder_list'] ?? [])->filter(fn ($r) => filled($r['name'] ?? null))->sum(fn ($r) => is_numeric($r['percent'] ?? null) ? (float) $r['percent'] : 0); @endphp
                                <div class="col-12 border-top pt-3">
                                    <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                        <span class="fw-semibold text-navy small me-auto">2.4 List of shareholders {!! $tip('shareholder_list') !!} <span class="req">*</span></span>
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

                                <div class="col-md-6"><label class="form-label small fw-semibold">2.5 Do you have foreign ownership in your enterprise? {!! $tip('foreign_owned') !!} <span class="req">*</span></label>
                                    <div>{!! $radios('foreign_owned', ['yes' => 'Yes', 'no' => 'No'], true) !!}</div>
                                    @error('form.foreign_owned') <div class="text-danger small">{{ $message }}</div> @enderror</div>
                                @if (($form['foreign_owned'] ?? '') === 'yes')
                                    <div class="col-md-6"><label class="form-label small fw-semibold">2.6 What percentage do they own? {!! $tip('foreign_owned_pct') !!} <span class="req">*</span></label>
                                        <div class="input-group" style="max-width: 200px"><input type="number" min="0" max="100" step="0.01" class="form-control @error('form.foreign_owned_pct') is-invalid @enderror" wire:model="form.foreign_owned_pct"><span class="input-group-text">%</span></div>
                                        @error('form.foreign_owned_pct') <div class="text-danger small">{{ $message }}</div> @enderror</div>
                                @endif
                                <div class="col-md-6"><label class="form-label small fw-semibold">2.7 Does the enterprise have its primary operations in Lesotho? {!! $tip('primary_ops_lesotho') !!} <span class="req">*</span></label>
                                    <div>{!! $radios('primary_ops_lesotho', ['yes' => 'Yes', 'no' => 'No']) !!}</div>
                                    @error('form.primary_ops_lesotho') <div class="text-danger small">{{ $message }}</div> @enderror</div>
                                <div class="col-md-6"><label class="form-label small fw-semibold">2.8 Does the enterprise have an active tax file with RSL? {!! $tip('rsl_tax_file') !!} <span class="req">*</span></label>
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
                                <div><dt>Legal form {!! $tip('legal_form') !!}</dt><dd>{{ $dash($p->legal_form) }}</dd></div>
                                <div><dt>Registered in another country {!! $tip('foreign_registered') !!}</dt><dd>{{ $p->foreign_registered ? 'Yes — '.collect([$p->foreign_country ?: 'country not given', $p->foreign_registration_no ? 'reg. no. '.$p->foreign_registration_no : null])->filter()->implode(', ') : $yn($p->foreign_registered) }}</dd></div>
                                <div><dt>Foreign ownership {!! $tip('foreign_owned') !!}</dt><dd>{{ $p->foreign_owned ? 'Yes — '.rtrim(rtrim(number_format((float) $p->foreign_owned_pct, 2), '0'), '.').'%' : $yn($p->foreign_owned) }}</dd></div>
                                <div><dt>Primary operations in Lesotho {!! $tip('primary_ops_lesotho') !!}</dt><dd>{{ $yn($p->primary_ops_lesotho) }}</dd></div>
                                <div><dt>Active RSL tax file {!! $tip('rsl_tax_file') !!}</dt><dd>{{ $ynp($p->rsl_tax_file) }}</dd></div>
                                <div><dt>Current markets {!! $tip('markets') !!}</dt><dd>{{ $p->labels($P::MARKETS, $p->markets) ?? '—' }}</dd></div>
                            </div>
                            @if ($p->obfc_registered === false)
                                <div class="callout callout-warning small mt-3 mb-0"><i class="bi bi-exclamation-triangle me-1"></i> {{ $P::REGISTRATION_NOTE }}</div>
                            @endif

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

                                        {{-- Eligibility flags (e.g. work by anyone under 18) are not shown to the applicant — reviewers see them in the gate workflows --}}

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
                            @if ($readOnly && $esAnswered)
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
                                    @php $flag = $readOnly ? $flag : null; @endphp {{-- flags: reviewers only --}}
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
                            <div class="row g-3">
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
                                <div class="col-md-6"><label class="form-label small fw-semibold">Annual revenues for the past year (US$) {!! $tip('annual_revenue') !!} <span class="req">*</span></label>
                                    <input type="number" min="0" step="1000" class="form-control @error('form.annual_revenue') is-invalid @enderror" wire:model.live.debounce.500ms="form.annual_revenue">
                                    <div class="form-text">{{ $mFromUsd($form['annual_revenue'] ?? null) }}</div>
                                    @error('form.annual_revenue') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>

                                {{-- ======================= 8.2 INVESTOR INFORMATION ======================= --}}
                                @php
                                    $hasInvestor = ($form['has_investor'] ?? '') === 'yes';
                                    $fx      = fn ($v) => is_numeric($v) ? (float) $v : null;
                                    $eligAmt = $fx($form['lead_investor_amount'] ?? null);   // US$
                                    $formInvestor = $hasInvestor && $eligAmt > 0 ? $P::toLsl($eligAmt, 'USD') : null;
                                    $formGrant    = $P::matchingGrant($formInvestor);
                                    $usdRate      = $P::usdRate();
                                    $equiv = fn ($v) => ! is_numeric($v) || (float) $v <= 0 ? '' : '≈ '.$lsl($P::toLsl((float) $v, 'USD')).' at M '.rtrim(rtrim(number_format($usdRate, 2), '0'), '.').' per US$';
                                    $projectCost  = $fx($form['project_cost'] ?? null) !== null ? $P::toLsl($fx($form['project_cost']), 'USD') : null;   // US$ entered → Maloti
                                    $costTooLow   = $formGrant !== null && $projectCost !== null && $projectCost + 0.5 < $formGrant;
                                    $wc = fn ($f) => str_word_count(strip_tags((string) ($form[$f] ?? '')));
                                @endphp
                                <div class="col-12 border-top pt-3"><div class="fw-semibold text-navy">8.2 Investor information {!! $tip('section.investor') !!}</div></div>
                                <div class="col-md-6"><label class="form-label small fw-semibold">8.2.1 Has a private investor been identified? {!! $tip('has_investor') !!} <span class="req">*</span></label>
                                    <div>{!! $radios('has_investor', ['yes' => 'Yes', 'no' => 'No'], true) !!}</div>
                                    @error('form.has_investor') <div class="text-danger small">{{ $message }}</div> @enderror</div>

                                @if (($form['has_investor'] ?? '') === 'no')
                                    <div class="col-12"><div class="callout callout-warning small mb-0">
                                        <i class="bi bi-info-circle me-1"></i> The Co-Investment Facility matches money from a private investor, so a CAFI grant can only be awarded once you have an investor. You can save your profile now and add your investor later.
                                    </div></div>
                                @endif

                                @if ($hasInvestor)
                                    <div class="col-md-6"><label class="form-label small fw-semibold">8.2.2 Lead investor name {!! $tip('lead_investor_name') !!} <span class="req">*</span></label>
                                        <input type="text" class="form-control @error('form.lead_investor_name') is-invalid @enderror" wire:model="form.lead_investor_name" placeholder="Person, company or fund">
                                        @error('form.lead_investor_name') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>

                                    <div class="col-12"><label class="form-label small fw-semibold mb-1">8.2.3 Lead investor contact {!! $tip('lead_investor_contact') !!}</label>
                                        <div class="row g-2">
                                            <div class="col-md-4"><input type="text" class="form-control @error('form.lead_investor_contact_name') is-invalid @enderror" wire:model="form.lead_investor_contact_name" placeholder="Name *">
                                                @error('form.lead_investor_contact_name') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                                            <div class="col-md-4"><input type="email" class="form-control @error('form.lead_investor_email') is-invalid @enderror" wire:model="form.lead_investor_email" placeholder="E-mail *">
                                                @error('form.lead_investor_email') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                                            <div class="col-md-4"><input type="tel" class="form-control @error('form.lead_investor_phone') is-invalid @enderror" wire:model="form.lead_investor_phone" placeholder="Telephone">
                                                @error('form.lead_investor_phone') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                                        </div></div>

                                    <div class="col-md-6"><label class="form-label small fw-semibold">8.2.4 Investor website, if available</label>
                                        <input type="text" class="form-control @error('form.lead_investor_website') is-invalid @enderror" wire:model="form.lead_investor_website" placeholder="https://">
                                        @error('form.lead_investor_website') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                                    <div class="col-md-6"><label class="form-label small fw-semibold">8.2.5 Investor type {!! $tip('investor_type') !!} <span class="req">*</span></label>
                                        <select class="form-select @error('form.investor_type') is-invalid @enderror" wire:model="form.investor_type">
                                            <option value="">Select…</option>@foreach ($P::LEAD_INVESTOR_TYPES as $o)<option>{{ $o }}</option>@endforeach
                                            @if (filled($form['investor_type'] ?? null) && ! in_array($form['investor_type'], $P::LEAD_INVESTOR_TYPES, true))<option>{{ $form['investor_type'] }}</option>@endif
                                        </select>
                                        @error('form.investor_type') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                                    <div class="col-md-6"><label class="form-label small fw-semibold">8.2.6 Investor country {!! $tip('lead_investor_country') !!} <span class="req">*</span></label>
                                        <select class="form-select @error('form.lead_investor_country') is-invalid @enderror" wire:model="form.lead_investor_country">
                                            <option value="">Select…</option>@foreach ($P::COUNTRIES as $o)<option>{{ $o }}</option>@endforeach
                                            @if (filled($form['lead_investor_country'] ?? null) && ! in_array($form['lead_investor_country'], $P::COUNTRIES, true))<option>{{ $form['lead_investor_country'] }}</option>@endif
                                        </select>
                                        @error('form.lead_investor_country') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                                    <div class="col-md-6"><label class="form-label small fw-semibold">8.2.7 Investment amount from the investor {!! $tip('lead_investor_amount') !!} <span class="req">*</span></label>
                                        <div class="input-group"><span class="input-group-text">US$</span>
                                            <input type="number" min="0" step="1000" class="form-control @error('form.lead_investor_amount') is-invalid @enderror" wire:model.live.debounce.500ms="form.lead_investor_amount">
                                        </div>
                                        <div class="form-text">{{ $equiv($form['lead_investor_amount'] ?? null) }}</div>
                                        @error('form.lead_investor_amount') <div class="text-danger small">{{ $message }}</div> @enderror</div>
                                    <div class="col-md-6"><label class="form-label small fw-semibold">8.2.8 Current investor engagement status <span class="req">*</span></label>
                                        <select class="form-select @error('form.transaction_status') is-invalid @enderror" wire:model="form.transaction_status">
                                            <option value="">Select…</option>@foreach ($P::ENGAGEMENT_STATUSES as $k => $o)<option value="{{ $k }}">{{ $o }}</option>@endforeach
                                            @if (filled($form['transaction_status'] ?? null) && ! isset($P::ENGAGEMENT_STATUSES[$form['transaction_status']]))<option value="{{ $form['transaction_status'] }}">{{ $P::TRANSACTION_STATUSES[$form['transaction_status']] ?? $form['transaction_status'] }}</option>@endif
                                        </select>
                                        @error('form.transaction_status') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                                    <div class="col-md-6"><label class="form-label small fw-semibold">8.2.9 Has the investor indicated or confirmed an investment commitment? {!! $tip('investor_commitment') !!} <span class="req">*</span></label>
                                        <div>{!! $radios('investor_commitment', $P::COMMITMENT) !!}</div>
                                        @error('form.investor_commitment') <div class="text-danger small">{{ $message }}</div> @enderror</div>
                                    <div class="col-12"><div class="small text-muted"><i class="bi bi-paperclip me-1"></i> 8.2.10 Upload any evidence of investor engagement or commitment (e.g. an investment memorandum) under <strong>Documents</strong> below this section.</div></div>
                                    @if ($formInvestor !== null && ! $P::meetsInvestorMinimum($formInvestor))
                                        <div class="col-12"><div class="callout callout-warning small mb-0">
                                            <i class="bi bi-exclamation-triangle me-1"></i> The investment must be at least <strong>US$ {{ number_format($P::minInvestorUsd()) }}</strong> (about {{ $lsl($P::minInvestorLsl()) }}) before you can apply. You can save your profile now.
                                        </div></div>
                                    @endif

                                    {{-- ======================= 8.3 TRANSACTION INFORMATION ======================= --}}
                                    <div class="col-12 border-top pt-3"><div class="fw-semibold text-navy">8.3 Transaction information {!! $tip('section.transaction') !!}</div></div>
                                    <div class="col-md-6"><label class="form-label small fw-semibold">8.3.1 What is the proposed investment instrument? {!! $tip('participation') !!} <span class="req">*</span></label>
                                        <select class="form-select @error('form.participation') is-invalid @enderror" wire:model="form.participation">
                                            <option value="">Select…</option>@foreach ($P::INSTRUMENTS as $k => $o)<option value="{{ $k }}">{{ $o }}</option>@endforeach
                                            @if (filled($form['participation'] ?? null) && ! isset($P::INSTRUMENTS[$form['participation']]))<option value="{{ $form['participation'] }}">{{ $P::PARTICIPATION[$form['participation']] ?? $form['participation'] }}</option>@endif
                                        </select>
                                        @error('form.participation') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                                    <div class="col-md-6"><label class="form-label small fw-semibold">8.3.2 Has the investment amount been agreed with the investor? <span class="req">*</span></label>
                                        <div>{!! $radios('tx_amount_agreed', ['yes' => 'Yes', 'no' => 'No'], true) !!}</div>
                                        @error('form.tx_amount_agreed') <div class="text-danger small">{{ $message }}</div> @enderror</div>
                                    @if (($form['tx_amount_agreed'] ?? '') === 'yes')
                                        <div class="col-md-6"><label class="form-label small fw-semibold">8.3.3 Agreed investment amount you will receive</label>
                                            <div class="form-control bg-light">{{ $eligAmt ? 'US$ '.number_format($eligAmt) : '—' }}</div>
                                            <div class="form-text">The amount entered in 8.2.7{{ $eligAmt ? ' · '.$equiv($eligAmt) : '' }}. Change it there if the agreed amount is different.</div></div>
                                    @endif
                                    <div class="col-md-6"><label class="form-label small fw-semibold">8.3.4 Have the principal transaction terms been agreed? <span class="req">*</span></label>
                                        <div>{!! $radios('tx_terms_agreed', ['yes' => 'Yes', 'no' => 'No']) !!}</div>
                                        @error('form.tx_terms_agreed') <div class="text-danger small">{{ $message }}</div> @enderror</div>
                                    <div class="col-md-4"><label class="form-label small fw-semibold">8.3.5 What is the valuation of your enterprise? {!! $tip('valuation') !!}</label>
                                        <div class="input-group"><span class="input-group-text">US$</span>
                                            <input type="number" min="0" step="1000" class="form-control @error('form.valuation') is-invalid @enderror" wire:model.live.debounce.500ms="form.valuation"></div>
                                        <div class="form-text">{{ $equiv($form['valuation'] ?? null) }}</div>
                                        @error('form.valuation') <div class="text-danger small">{{ $message }}</div> @enderror</div>
                                    <div class="col-md-4"><label class="form-label small fw-semibold">8.3.6 Who developed the valuation?</label>
                                        <select class="form-select @error('form.valuation_by') is-invalid @enderror" wire:model="form.valuation_by">
                                            <option value="">Select…</option>@foreach ($P::VALUATION_BY as $k => $o)<option value="{{ $k }}">{{ $o }}</option>@endforeach</select>
                                        @error('form.valuation_by') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                                    <div class="col-md-4"><label class="form-label small fw-semibold">8.3.7 How was this valuation worked out?</label>
                                        <select class="form-select @error('form.valuation_method') is-invalid @enderror" wire:model="form.valuation_method">
                                            <option value="">Select…</option>@foreach ($P::VALUATION_METHODS as $k => $o)<option value="{{ $k }}">{{ $o }}</option>@endforeach</select>
                                        @error('form.valuation_method') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                                    <div class="col-md-6"><label class="form-label small fw-semibold">8.3.8 When is the investment expected to close or be deployed? {!! $tip('expected_close') !!} <span class="req">*</span></label>
                                        <input type="text" maxlength="120" class="form-control @error('form.expected_close') is-invalid @enderror" wire:model="form.expected_close" placeholder="e.g. March 2027, or within 3 months">
                                        @error('form.expected_close') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                                    <div class="col-md-6"><label class="form-label small fw-semibold">8.3.9 Are there material conditions outstanding before the investment can proceed? <span class="req">*</span></label>
                                        <div>{!! $radios('tx_conditions_outstanding', ['yes' => 'Yes', 'no' => 'No']) !!}</div>
                                        @error('form.tx_conditions_outstanding') <div class="text-danger small">{{ $message }}</div> @enderror</div>
                                    <div class="col-12"><label class="form-label small fw-semibold">8.3.10 Briefly describe the transaction and the intended business outcome (max. 100 words) {!! $tip('tx_description') !!} <span class="req">*</span></label>
                                        <textarea rows="3" class="form-control @error('form.tx_description') is-invalid @enderror" wire:model.live.debounce.600ms="form.tx_description"></textarea>
                                        <div class="form-text {{ $wc('tx_description') > 100 ? 'text-danger' : '' }}">{{ $wc('tx_description') }} / 100 words</div>
                                        @error('form.tx_description') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                                    <div class="col-12"><div class="small text-muted"><i class="bi bi-paperclip me-1"></i> 8.3.11 Upload a Letter of Intent, Term Sheet or other available transaction document under <strong>Documents</strong> below this section.</div></div>
                                @endif

                                <div class="col-md-6"><label class="form-label small fw-semibold">Total project cost {!! $tip('project_cost') !!} <span class="req">*</span></label>
                                    <div class="input-group"><span class="input-group-text">US$</span>
                                        <input type="number" min="0" step="1000" class="form-control @error('form.project_cost') is-invalid @enderror" wire:model.live.debounce.500ms="form.project_cost"></div>
                                    <div class="form-text">{{ $equiv($form['project_cost'] ?? null) }}@if ($formGrant !== null) · must be at least US$ {{ number_format($formGrant / $usdRate) }} ({{ $lsl($formGrant) }}), the indicative CAFI grant @endif</div>
                                    @if ($costTooLow) <div class="small text-danger"><i class="bi bi-exclamation-circle me-1"></i> The total project cost cannot be less than the indicative CAFI grant.</div> @endif
                                    @error('form.project_cost') <div class="text-danger small">{{ $message }}</div> @enderror</div>

                                @if ($hasInvestor)
                                    {{-- ======================= 8.4 PROPOSED USE OF THE CAFI GRANT ======================= --}}
                                    @php
                                        $planT     = $P::planTotals($form['grant_plan'] ?? []);
                                        $grantUsd  = $formGrant !== null ? $formGrant / $usdRate : null;
                                        $availUsd  = $p->availableCumulativeLsl() / $usdRate;
                                        $capUsd    = (float) config('cif.co_investment.cumulative_cap_usd', 350000);
                                        $limitUsd  = $grantUsd !== null ? min($grantUsd, $availUsd) : null;
                                        $balance   = $limitUsd !== null ? $limitUsd - $planT['total'] : null;
                                        $over      = $limitUsd !== null && $planT['total'] > $limitUsd + 0.5;
                                        $lsPct     = $planT['total'] > 0 ? round(($planT['total'] - $planT['outside']) / $planT['total'] * 100, 1) : null;
                                        $minLs     = $P::minLesothoPct();
                                        $u         = fn ($v) => $v === null ? '—' : 'US$ '.number_format((float) $v);
                                    @endphp
                                    <div class="col-12 border-top pt-3"><div class="fw-semibold text-navy">8.4 Proposed use of the CAFI co-investment grant {!! $tip('section.grant_use') !!}</div>
                                        <div class="small text-muted">Budget only what the CAFI grant will pay for — not the investor's money. Amounts in US$.</div></div>

                                    {{-- A. Indicative grant allocation --}}
                                    <div class="col-12">
                                        <div class="small fw-semibold text-navy mb-2">A. Indicative grant allocation</div>
                                        <div class="row g-2">
                                            @foreach ([
                                                ['Eligible private investment', $formInvestor !== null ? $u($formInvestor / $usdRate) : '—', $formInvestor !== null ? $lsl($formInvestor) : 'From 8.2.7', null],
                                                ['Indicative CAFI grant', $u($grantUsd), ($formInvestor ? $P::effectiveGrantPct($formInvestor).'% of the investment · ' : '').$P::grantRatioLabel(), null],
                                                ['Available cumulative allocation', $u($availUsd), 'US$ '.number_format($capUsd).' lifetime cap, less earlier grants', null],
                                                ['Total proposed grant utilisation', $u($planT['total']), 'From the budget below', $over ? 'text-danger' : null],
                                                ['Unallocated grant balance', $balance === null ? '—' : ($balance < 0 ? '− ' : '').'US$ '.number_format(abs($balance)), 'Indicative grant less proposed utilisation', $balance !== null && $balance < -0.5 ? 'text-danger' : null],
                                            ] as [$aLabel, $aValue, $aNote, $aCls])
                                                <div class="col-6 col-lg"><div class="border rounded-3 p-2 h-100">
                                                    <div class="small text-muted">{{ $aLabel }}</div>
                                                    <div class="fw-bold {{ $aCls ?? 'text-navy' }}">{{ $aValue }}</div>
                                                    <div class="small text-muted" style="font-size: .75rem">{{ $aNote }}</div>
                                                </div></div>
                                            @endforeach
                                        </div>
                                        @if ($over)
                                            <div class="callout callout-danger small mt-2 mb-0"><i class="bi bi-exclamation-octagon me-1"></i> The proposed budget is more than the indicative grant{{ $availUsd < ($grantUsd ?? INF) ? ' / the grant still available under the lifetime cap' : '' }}. Reduce it by {{ $u(ceil($planT['total'] - $limitUsd)) }} — lower the quantity, unit cost or lump sum of one or more lines in <strong>B. Proposed grant utilisation budget</strong> below.</div>
                                        @endif
                                    </div>

                                    {{-- B. Proposed grant utilisation budget --}}
                                    <div class="col-12">
                                        <div class="small fw-semibold text-navy mb-1">B. Proposed grant utilisation budget <span class="req">*</span> {!! $tip('grant_plan') !!}</div>
                                        <div class="small text-muted mb-2">Add each item or activity under its category. Quantity × unit cost gives the total; tick <em>Lump sum</em> for costs that are not quantity-based.</div>
                                        @foreach ($P::GRANT_CATEGORIES as $ck => [$cLabel, $cEx, $cGuide])
                                            @php $items = $form['grant_plan'][$ck] ?? []; @endphp
                                            <div class="border rounded-3 mb-2" wire:key="gp-{{ $ck }}">
                                                <div class="d-flex flex-wrap align-items-center gap-2 px-3 py-2 bg-light rounded-top">
                                                    <div class="me-auto">
                                                        <div class="fw-semibold text-navy small">{{ $cLabel }}</div>
                                                        <div class="text-muted" style="font-size: .75rem">{{ $cGuide }}</div>
                                                    </div>
                                                    <span class="small fw-semibold">{{ $u($planT['categories'][$ck] ?? 0) }}</span>
                                                    <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="addPlanItem('{{ $ck }}')"><i class="bi bi-plus-lg me-1"></i>Add item / activity</button>
                                                </div>
                                                @if ($items)
                                                    <div class="px-3 pb-2">
                                                        @foreach ($items as $ii => $it)
                                                            @php
                                                                $lineTotal = $P::planItemTotal($it);
                                                                $lump      = ! empty($it['lump_sum']);
                                                                $outside   = ($it['location'] ?? 'lesotho') === 'outside';
                                                                $e         = 'form.grant_plan.'.$ck.'.'.$ii.'.';
                                                            @endphp
                                                            <div class="border rounded-3 bg-white p-3 mt-2 position-relative" wire:key="gp-{{ $ck }}-{{ $ii }}">
                                                                <div class="d-flex align-items-start gap-2 mb-2">
                                                                    <span class="badge rounded-pill text-bg-light border mt-1">{{ $ii + 1 }}</span>
                                                                    <div class="flex-grow-1">
                                                                        <input type="text" class="form-control fw-semibold @error($e.'item') is-invalid @enderror" wire:model.blur="form.grant_plan.{{ $ck }}.{{ $ii }}.item" placeholder="Item / activity — {{ $cEx }}">
                                                                        @error($e.'item') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                                                    </div>
                                                                    <div class="text-end ps-2" style="min-width: 120px">
                                                                        <div class="text-muted" style="font-size: .7rem; text-transform: uppercase; letter-spacing: .03em">Total</div>
                                                                        <div class="fw-bold {{ $lineTotal > 0 ? 'text-navy' : 'text-muted' }}">{{ $u($lineTotal) }}</div>
                                                                        <div class="text-muted" style="font-size: .72rem">{{ $lineTotal > 0 ? $lsl($lineTotal * $usdRate) : '' }}</div>
                                                                    </div>
                                                                    <button type="button" class="btn btn-icon btn-icon-danger" title="Remove this line" wire:click="removePlanItem('{{ $ck }}', {{ $ii }})"><i class="bi bi-trash"></i></button>
                                                                </div>

                                                                <div class="row g-2">
                                                                    <div class="col-lg-6">
                                                                        <label class="form-label small text-muted mb-1">Description and justification</label>
                                                                        <textarea rows="2" class="form-control form-control-sm" wire:model.blur="form.grant_plan.{{ $ck }}.{{ $ii }}.description" placeholder="{{ $cGuide }}"></textarea>
                                                                    </div>

                                                                    <div class="col-sm-7 col-lg-4">
                                                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                                                            <label class="form-label small text-muted mb-0">Cost (US$)</label>
                                                                            <div class="form-check form-switch mb-0 small">
                                                                                <input class="form-check-input" type="checkbox" role="switch" id="lump-{{ $ck }}-{{ $ii }}" wire:model.live="form.grant_plan.{{ $ck }}.{{ $ii }}.lump_sum">
                                                                                <label class="form-check-label" for="lump-{{ $ck }}-{{ $ii }}">Lump sum</label>
                                                                            </div>
                                                                        </div>
                                                                        @if ($lump)
                                                                            <div class="input-group input-group-sm">
                                                                                <span class="input-group-text">US$</span>
                                                                                <input type="number" min="0" step="100" class="form-control @error($e.'amount') is-invalid @enderror" wire:model.live.debounce.400ms="form.grant_plan.{{ $ck }}.{{ $ii }}.amount" placeholder="Amount">
                                                                            </div>
                                                                        @else
                                                                            <div class="input-group input-group-sm">
                                                                                <input type="number" min="0" step="1" class="form-control @error($e.'quantity') is-invalid @enderror" wire:model.live.debounce.400ms="form.grant_plan.{{ $ck }}.{{ $ii }}.quantity" placeholder="Qty">
                                                                                <span class="input-group-text">×</span>
                                                                                <span class="input-group-text">US$</span>
                                                                                <input type="number" min="0" step="10" class="form-control @error($e.'unit_cost') is-invalid @enderror" wire:model.live.debounce.400ms="form.grant_plan.{{ $ck }}.{{ $ii }}.unit_cost" placeholder="Unit cost">
                                                                            </div>
                                                                        @endif
                                                                        @error($e.'total') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                                                                    </div>

                                                                    <div class="col-sm-5 col-lg-2">
                                                                        <label class="form-label small text-muted mb-1">Location</label>
                                                                        <select class="form-select form-select-sm {{ $outside ? 'border-warning' : '' }}" wire:model.live="form.grant_plan.{{ $ck }}.{{ $ii }}.location">
                                                                            @foreach ($P::LOCATIONS as $lk => $ll) <option value="{{ $lk }}">{{ $ll }}</option> @endforeach
                                                                        </select>
                                                                        @if ($outside)
                                                                            <input type="text" class="form-control form-control-sm mt-1 @error($e.'country') is-invalid @enderror" wire:model.blur="form.grant_plan.{{ $ck }}.{{ $ii }}.country" placeholder="Country *">
                                                                            @error($e.'country') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                                                        @endif
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                @else
                                                    <div class="px-3 pb-3 pt-1 small text-muted">No items yet — click <strong>Add item / activity</strong>.</div>
                                                @endif
                                            </div>
                                        @endforeach
                                        <div class="d-flex justify-content-between border rounded-3 px-3 py-2 fw-semibold {{ $over ? 'border-danger text-danger' : '' }}">
                                            <span>Total proposed grant utilisation</span><span>{{ $u($planT['total']) }} <span class="small text-muted fw-normal">· {{ $lsl($planT['total'] * $usdRate) }}</span></span>
                                        </div>
                                        @error('form.grant_plan') <div class="text-danger small mt-1"><i class="bi bi-exclamation-circle me-1"></i>{{ $message }}</div> @enderror
                                    </div>

                                    {{-- C. Grant utilisation declarations --}}
                                    <div class="col-12"><div class="small fw-semibold text-navy">C. Grant utilisation declarations</div></div>
                                    @php
                                        $outsideLines = collect($form['grant_plan'] ?? [])->flatMap(fn ($its, $k) => collect($its)->filter(fn ($it) => ($it['location'] ?? '') === 'outside')->map(fn ($it) => $it + ['cat' => $P::GRANT_CATEGORIES[$k][0]]));
                                    @endphp
                                    <div class="col-12"><label class="form-label small fw-semibold">1. Will any proposed grant expenditure take place outside Lesotho?</label>
                                        @if ($outsideLines->isEmpty())
                                            <div class="small">No — every budget line is in Lesotho.</div>
                                        @else
                                            <div class="small mb-1">Yes — {{ $outsideLines->count() }} {{ \Illuminate\Support\Str::plural('line', $outsideLines->count()) }}, {{ $u($planT['outside']) }}:</div>
                                            <ul class="small mb-2">
                                                @foreach ($outsideLines as $ol) <li>{{ $ol['item'] ?: 'Unnamed item' }} ({{ $ol['cat'] }}) — {{ $u($P::planItemTotal($ol)) }}{{ filled($ol['country'] ?? null) ? ', '.$ol['country'] : '' }}</li> @endforeach
                                            </ul>
                                            <label class="form-label small fw-semibold">Explain how this expenditure directly supports the growth of the Lesotho enterprise <span class="req">*</span></label>
                                            <textarea rows="3" maxlength="2000" class="form-control @error('form.grant_outside_justification') is-invalid @enderror" wire:model.blur="form.grant_outside_justification"
                                                      placeholder="e.g. The processing machine is only made by suppliers in South Africa; installation is done by the supplier's technicians."></textarea>
                                            @error('form.grant_outside_justification') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                        @endif
                                    </div>
                                    <div class="col-md-6"><label class="form-label small fw-semibold">2. Will at least {{ (int) $minLs }}% of the CAFI grant be spent in Lesotho? {!! $tip('grant_lesotho_70') !!} <span class="req">*</span></label>
                                        <div>{!! $radios('grant_lesotho_70', $P::LESOTHO_70) !!}</div>
                                        @error('form.grant_lesotho_70') <div class="text-danger small">{{ $message }}</div> @enderror
                                        @if ($lsPct !== null)
                                            <div class="small mt-1 {{ $lsPct >= $minLs ? 'text-success' : 'text-warning-emphasis' }}"><i class="bi {{ $lsPct >= $minLs ? 'bi-check-circle' : 'bi-exclamation-triangle' }} me-1"></i>From your budget: {{ rtrim(rtrim(number_format($lsPct, 1), '0'), '.') }}% in Lesotho.</div>
                                        @endif
                                    </div>
                                    <div class="col-md-6"><label class="form-label small fw-semibold">3. Will any expenditure in this budget also be financed by another grant or public funding source? {!! $tip('double_financing') !!} <span class="req">*</span></label>
                                        <div>{!! $radios('double_financing', ['yes' => 'Yes', 'no' => 'No'], true) !!}</div>
                                        @error('form.double_financing') <div class="text-danger small">{{ $message }}</div> @enderror</div>
                                    @if (($form['double_financing'] ?? '') === 'yes')
                                        <div class="col-12"><label class="form-label small fw-semibold">Identify the expenditure and the funding source <span class="req">*</span></label>
                                            <textarea rows="2" class="form-control @error('form.double_financing_details') is-invalid @enderror" wire:model.blur="form.double_financing_details"></textarea>
                                            @error('form.double_financing_details') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                                    @endif
                                    <div class="col-12"><label class="form-label small fw-semibold">4. How will the proposed grant expenditure support the enterprise's growth plan? (max. 150 words) {!! $tip('grant_growth_support') !!} <span class="req">*</span></label>
                                        <textarea rows="4" class="form-control @error('form.grant_growth_support') is-invalid @enderror" wire:model.live.debounce.600ms="form.grant_growth_support"></textarea>
                                        <div class="form-text {{ $wc('grant_growth_support') > 150 ? 'text-danger' : '' }}">{{ $wc('grant_growth_support') }} / 150 words</div>
                                        @error('form.grant_growth_support') <div class="invalid-feedback">{{ $message }}</div> @enderror</div>
                                    <div class="col-12"><div class="small text-muted"><i class="bi bi-paperclip me-1"></i> 5. Upload a supporting quotation or cost estimate, where available, under <strong>Documents</strong> below (optional now — more evidence may be requested during due diligence).</div></div>
                                @endif

                                <div class="col-12 border-top pt-3"><div class="fw-semibold text-navy small mb-2">Total amount of investment in the company to date {!! $tip('investment_history') !!}</div>
                                    <div class="table-responsive">
                                        <table class="table table-modern table-sm align-middle mb-0">
                                            <thead><tr><th>Source</th><th>Overall amount (US$)</th><th>Date of last investment</th></tr></thead>
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
                            <div class="detail-grid mb-3">
                                <div><dt>Annual revenue (past year) {!! $tip('annual_revenue') !!}</dt><dd>{{ $usdMain($p->annual_revenue) }} <span class="small text-muted">{{ $p->annual_revenue !== null ? $lsl($p->annual_revenue) : '' }}</span></dd></div>
                            </div>
                            <div class="row g-3 mb-1">
                                <div class="col-12"><dt class="detail-label">Customers {!! $tip('customers') !!}</dt>
                                    <p class="small mb-2" style="white-space: pre-line">{{ $dash($p->customers) }}</p></div>
                            </div>
                            @foreach (['usp' => 'Unique selling proposition', 'marketing_strategy' => 'Marketing approach', 'competition' => 'Competition'] as $f => $lbl)
                                <dt class="detail-label">{{ $lbl }} {!! $tip($f) !!}</dt>
                                <p class="small mb-3" style="white-space: pre-line">{{ $dash($p->{$f}) }}</p>
                            @endforeach

                            @php
                                $vCur = $p->investment_currency ?: 'LSL';
                                $vM   = fn ($v) => $v === null ? '—' : $P::money((float) $v, $vCur).($vCur === 'USD' ? ' · '.$lsl($P::toLsl((float) $v, 'USD')) : '');
                                $vYn  = fn ($v) => $v === null ? '—' : ($v ? 'Yes' : 'No');
                            @endphp
                            <dt class="detail-label mt-2 mb-2">8.2 Investor information {!! $tip('section.investor') !!}</dt>
                            <div class="detail-grid mb-3">
                                <div><dt>Private investor identified</dt><dd>{{ $yn($p->has_investor ?? ($p->investorRows() ? true : null)) }}</dd></div>
                                @if ($p->has_investor !== false)
                                    <div><dt>Lead investor</dt><dd>{{ $dash($p->lead_investor_name ?: collect($p->investorRows())->pluck('name')->implode(', ')) }}</dd></div>
                                    <div><dt>Contact</dt><dd>{{ collect([$p->lead_investor_contact_name, $p->lead_investor_email, $p->lead_investor_phone])->filter()->implode(' · ') ?: '—' }}</dd></div>
                                    <div><dt>Website</dt><dd>{{ $dash($p->lead_investor_website) }}</dd></div>
                                    <div><dt>Investor type</dt><dd>{{ $dash($p->investor_type) }}</dd></div>
                                    <div><dt>Country</dt><dd>{{ $dash($p->lead_investor_country) }}</dd></div>
                                    <div><dt>Investment amount</dt><dd>{{ $vM($p->lead_investor_amount) }}</dd></div>
                                    <div><dt>Engagement status</dt><dd>{{ $P::TRANSACTION_STATUSES[$p->transaction_status ?? ''] ?? '—' }}</dd></div>
                                    <div><dt>Commitment</dt><dd>{{ $P::COMMITMENT[$p->investor_commitment ?? ''] ?? '—' }}</dd></div>
                                @endif
                            </div>
                            @if ($p->has_investor === false)
                                <div class="callout callout-warning small mb-3"><i class="bi bi-info-circle me-1"></i> No investor yet — a CAFI grant can only be awarded once there is a private investor.</div>
                            @else
                                <dt class="detail-label mb-2">8.3 Transaction information {!! $tip('section.transaction') !!}</dt>
                                <div class="detail-grid mb-2">
                                    <div><dt>Instrument</dt><dd>{{ $P::PARTICIPATION[$p->participation ?? ''] ?? '—' }}</dd></div>
                                    <div><dt>Amount agreed</dt><dd>{{ $vYn($p->tx_amount_agreed) }}{{ $p->tx_amount_agreed ? ' — '.$vM($p->tx_amount) : '' }}</dd></div>
                                    <div><dt>Principal terms agreed</dt><dd>{{ $vYn($p->tx_terms_agreed) }}</dd></div>
                                    <div><dt>Valuation</dt><dd>{{ $vM($p->valuation) }}{{ $p->valuation_by ? ' · '.($P::VALUATION_BY[$p->valuation_by] ?? '') : '' }}{{ $p->valuation_method ? ' · '.($P::VALUATION_METHODS[$p->valuation_method] ?? '') : '' }}</dd></div>
                                    <div><dt>Expected close / deployment</dt><dd>{{ $dash($p->expected_close) }}</dd></div>
                                    <div><dt>Material conditions outstanding</dt><dd>{{ $vYn($p->tx_conditions_outstanding) }}</dd></div>
                                    <div><dt>Total project cost</dt><dd>{{ $p->project_cost !== null ? 'US$ '.number_format((float) $p->project_cost / $P::usdRate()) : '—' }} <span class="small text-muted">{{ $p->project_cost !== null ? $lsl($p->project_cost) : '' }}</span></dd></div>
                                </div>
                                @if ($p->tx_description) <div class="quote mb-3">{{ $p->tx_description }}<div class="quote-meta">Transaction and intended outcome</div></div> @endif
                                @if ($p->investorAmount() !== null && ! $P::meetsInvestorMinimum($p->investorAmount()))
                                    <div class="callout callout-warning small mb-3"><i class="bi bi-exclamation-triangle me-1"></i> The investment is below the US$ {{ number_format($P::minInvestorUsd()) }} minimum (about {{ $lsl($P::minInvestorLsl()) }}).</div>
                                @endif

                                @php
                                    $vRate  = $P::usdRate();
                                    $vPlanT = $P::planTotals((array) ($p->grant_plan ?? []));
                                    $vInv   = $p->investorAmount();
                                    $vGrant = $P::matchingGrant($vInv);
                                    $vU     = fn ($v) => $v === null ? '—' : 'US$ '.number_format((float) $v);
                                @endphp
                                <dt class="detail-label mb-2">8.4 Proposed use of the CAFI grant {!! $tip('section.grant_use') !!}</dt>
                                <div class="detail-grid mb-2">
                                    <div><dt>Eligible private investment</dt><dd>{{ $vInv !== null ? $vU($vInv / $vRate) : '—' }} <span class="small text-muted">{{ $vInv !== null ? $lsl($vInv) : '' }}</span></dd></div>
                                    <div><dt>Indicative CAFI grant</dt><dd>{{ $vGrant !== null ? $vU($vGrant / $vRate) : '—' }}</dd></div>
                                    <div><dt>Available cumulative allocation</dt><dd>{{ $vU($p->availableCumulativeLsl() / $vRate) }}</dd></div>
                                    <div><dt>Total proposed grant utilisation</dt><dd>{{ $vU($vPlanT['total']) }}</dd></div>
                                    <div><dt>Unallocated grant balance</dt><dd>{{ $vGrant !== null ? $vU($vGrant / $vRate - $vPlanT['total']) : '—' }}</dd></div>
                                </div>
                                @if ($vPlanT['total'] > 0)
                                    <div class="table-responsive mb-2">
                                        <table class="table table-modern table-sm align-middle mb-0">
                                            <thead><tr><th>Item / activity</th><th>Description</th><th class="text-end">Qty</th><th class="text-end">Unit cost</th><th class="text-end">Total (US$)</th><th>Location</th></tr></thead>
                                            <tbody>
                                                @foreach ($P::GRANT_CATEGORIES as $ck => [$cLabel])
                                                    @continue(empty($p->grant_plan[$ck]))
                                                    <tr class="table-light"><td colspan="4" class="small fw-semibold text-navy">{{ $cLabel }}</td><td class="text-end small fw-semibold">{{ $vU($vPlanT['categories'][$ck]) }}</td><td></td></tr>
                                                    @foreach ($p->grant_plan[$ck] as $it)
                                                        <tr class="small">
                                                            <td>{{ $it['item'] ?? '' }}</td>
                                                            <td class="text-muted">{{ $it['description'] ?? '' }}</td>
                                                            <td class="text-end">{{ ! empty($it['lump_sum']) ? 'Lump sum' : (is_numeric($it['quantity'] ?? null) ? rtrim(rtrim(number_format((float) $it['quantity'], 2), '0'), '.') : '—') }}</td>
                                                            <td class="text-end">{{ ! empty($it['lump_sum']) ? '' : $vU($it['unit_cost'] ?? null) }}</td>
                                                            <td class="text-end">{{ $vU($P::planItemTotal($it)) }}</td>
                                                            <td>{{ ($it['location'] ?? 'lesotho') === 'outside' ? 'Outside Lesotho'.(filled($it['country'] ?? null) ? ' ('.$it['country'].')' : '') : 'Lesotho' }}</td>
                                                        </tr>
                                                    @endforeach
                                                @endforeach
                                                <tr class="fw-semibold"><td colspan="4">Total proposed grant utilisation</td><td class="text-end">{{ $vU($vPlanT['total']) }}</td><td class="small text-muted fw-normal">{{ $lsl($vPlanT['total'] * $vRate) }}</td></tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    @if (! $p->budgetMatchesGrant())
                                        <div class="callout callout-warning small mb-2"><i class="bi bi-exclamation-triangle me-1"></i> The proposed budget is more than the indicative grant — the investment amount has probably changed. Please update the budget.</div>
                                    @endif
                                @elseif ($p->grant_budget)
                                    <div class="small text-muted mb-2">An earlier budget is saved in Maloti — edit this section to move it to the new US$ budget.</div>
                                @else
                                    <div class="small text-muted mb-2">No grant budget yet.</div>
                                @endif
                                <div class="detail-grid mb-3">
                                    <div><dt>Spent outside Lesotho</dt><dd>{{ $vPlanT['outside'] > 0 ? 'Yes — '.$vU($vPlanT['outside']) : ($vPlanT['total'] > 0 ? 'No' : '—') }}</dd></div>
                                    <div><dt>At least {{ (int) $P::minLesothoPct() }}% in Lesotho</dt><dd>{{ $P::LESOTHO_70[$p->grant_lesotho_70 ?? ''] ?? '—' }}@if ($vPlanT['total'] > 0) <span class="small text-muted">· budget: {{ rtrim(rtrim(number_format(($vPlanT['total'] - $vPlanT['outside']) / $vPlanT['total'] * 100, 1), '0'), '.') }}%</span>@endif</dd></div>
                                    <div><dt>Also financed by another grant</dt><dd>{{ $vYn($p->double_financing) }}</dd></div>
                                </div>
                                @if ($p->grant_outside_justification) <div class="quote mb-2">{{ $p->grant_outside_justification }}<div class="quote-meta">Why some of the grant is spent outside Lesotho</div></div> @endif
                                @if ($p->double_financing_details) <div class="quote mb-2">{{ $p->double_financing_details }}<div class="quote-meta">Other funding</div></div> @endif
                                @if ($p->grant_growth_support) <div class="quote mb-3">{{ $p->grant_growth_support }}<div class="quote-meta">How the grant supports the growth plan</div></div> @endif
                            @endif

                            @if ($p->investment_history)
                                <dt class="detail-label mb-2">Investment in the company to date {!! $tip('investment_history') !!}</dt>
                                <table class="table table-modern table-sm align-middle mb-0">
                                    <thead><tr><th>Source</th><th class="text-end">Overall amount</th><th>Last investment</th></tr></thead>
                                    <tbody>
                                        @foreach ($P::FUNDING_SOURCES as $k => $lbl)
                                            @continue(! isset($p->investment_history[$k]))
                                            @php $h = $p->investment_history[$k]; @endphp
                                            <tr><td>{{ $lbl }}</td><td class="text-end">{{ $usdMain($h['amount'] ?? null) }} <div class="small text-muted">{{ isset($h['amount']) ? $lsl($h['amount']) : '' }}</div></td>
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
                                        <div class="col-md-4"><label class="form-label small fw-semibold">Amount (US$) <span class="req">*</span></label>
                                            <input type="number" min="0" step="1000" class="form-control form-control-sm @error('inv.amount') is-invalid @enderror" wire:model.blur="inv.amount">
                                            <div class="form-text">{{ $mFromUsd($inv['amount'] ?? null) }}</div>
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
                                                    <td class="text-end text-nowrap">{{ $usdMain($i->amount) }}<div class="small text-muted">{{ $lsl($i->amount) }}</div></td>
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