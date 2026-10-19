<?php

use App\Models\DueDiligence;
use App\Models\DueDiligenceCondition;
use App\Models\EnterpriseDocument;
use App\Models\EnterpriseProfile;
use App\Models\ExpressionOfInterest;
use App\Models\User;
use App\Notifications\EoiDueDiligenceResumed;
use App\Notifications\EoiGate2Outcome;
use App\Notifications\EoiReadyForCommittee;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

/*
| G2 · Due Diligence & Investment Appraisal (§3.3) — simplified
|   <livewire:expression-of-interest.due-diligence.due-deligence-check :eoi-id="$eoi->id" />
|
|   1 Checklist (6 areas: status + findings) · 2 Key figures · 3 Conditions · 4 Summary + memorandum · 5 Outcome
|   PMU (gate2.assess) edits — saved automatically — and records the outcome. gate2.memo can upload the memorandum.
|   Proceed → committee · Clarification / Remediation → dd_hold (resume = new round) · Do not progress → not_progressed
|
|   E-mails (sent after the change is saved; a failed e-mail never undoes it):
|     Outcome recorded  → applicant: EoiGate2Outcome (proceed / clarification / remediation / do not progress)
|     Proceed           → Investment Committee (gate3.score / gate3.decide): EoiReadyForCommittee
|     Resume assessment → applicant: EoiDueDiligenceResumed
*/
new class extends Component
{
    use WithFileUploads;

    #[Locked] public int $eoiId;
    #[Locked] public ?int $ddId = null;
    #[Locked] public int $round = 1;

    public array $form = [];
    public array $conditions = [];   // [id, finding, treatment, owner, status]
    public $iam = null;
    public ?string $savedAt = null;

    public function mount(int $eoiId): void
    {
        $eoi = ExpressionOfInterest::findOrFail($eoiId);
        abort_unless($eoi->isViewableBy(auth()->user()), 403);

        $this->eoiId = $eoi->id;
        $this->load();
    }

    /* ---------------- Data ---------------- */

    #[Computed] public function eoi(): ExpressionOfInterest
    {
        return ExpressionOfInterest::with('user')->findOrFail($this->eoiId);
    }

    #[Computed] public function dd(): ?DueDiligence
    {
        return $this->ddId ? DueDiligence::with('assessor:id,name', 'decider:id,name', 'iamUploader:id,name')->find($this->ddId) : null;
    }

    #[Computed] public function profile(): ?EnterpriseProfile
    {
        return EnterpriseProfile::with('documents')->where('user_id', $this->eoi->user_id)->first();
    }

    #[Computed] public function history()
    {
        return DueDiligence::with('decider:id,name')->where('expression_of_interest_id', $this->eoiId)
            ->whereNotNull('decided_at')->when($this->ddId, fn ($q) => $q->whereKeyNot($this->ddId))
            ->orderByDesc('round')->get();
    }

    #[Computed] public function isApplicant(): bool { return $this->eoi->user_id === auth()->id(); }

    #[Computed] public function gateOpen(): bool
    {
        return in_array($this->eoi->status, ExpressionOfInterest::PAST_GATE1, true) || $this->ddId !== null;
    }

    #[Computed] public function isOpenForWork(): bool
    {
        return ! $this->isApplicant
            && in_array($this->eoi->status, [ExpressionOfInterest::STATUS_INVITED, ExpressionOfInterest::STATUS_DUE_DILIGENCE], true)
            && ! ($this->dd?->isDecided());
    }

    #[Computed] public function canEdit(): bool { return $this->isOpenForWork && auth()->user()->can('gate2.assess'); }

    #[Computed] public function canMemo(): bool
    {
        return $this->isOpenForWork && (auth()->user()->can('gate2.memo') || auth()->user()->can('gate2.assess'));
    }

    #[Computed] public function hasIam(): bool { return (bool) $this->dd?->iam_path; }

    /* ---------------- Submitted information per checklist area ---------------- */

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
    ];

    /** Profile attachments relevant to each checklist area */
    protected const AREA_DOCS = [
        'enterprise'  => ['registration_cert', 'registration_undertaking', 'foreign_registration', 'shareholders_list', 'key_individuals', 'ids', 'team_resumes', 'tax_clearance'],
        'investor'    => ['commitment', 'cap_table', 'shareholders_list'],
        'transaction' => ['commitment', 'budget', 'business_plan'],
        'financial'   => ['financials', 'projections', 'business_plan'],
        'esgc'        => ['employee_list'],
        'risk'        => ['key_individuals', 'ids'],
    ];

    /**
     * area => ['rows' => [[label, value, source, sub?, flag?]], 'docs' => [[label, ?EnterpriseDocument, required]]]
     *   value: string | list | null (not provided) · source: 'EOI' | 'Profile' · flag: null | 'ok' | 'bad'
     */
    #[Computed] public function evidence(): array
    {
        $eoi  = $this->eoi;
        $p    = $this->profile;
        $P    = EnterpriseProfile::class;
        $rate = (float) config('cif.usd_to_lsl', 18.00);

        $l     = fn ($f, $v) => $v === null || $v === '' ? null : (self::EOI_LABELS[$f][$v] ?? $v);
        $ls    = fn ($f, $vs) => collect($vs ?? [])->map(fn ($v) => self::EOI_LABELS[$f][$v] ?? $v)->values()->all() ?: null;
        $num   = fn ($v) => $v === null || $v === '' ? null : number_format((float) $v);
        $yn    = fn ($v) => $v === null ? null : ($v ? 'Yes' : 'No');
        $usdM  = fn ($usd) => $usd === null ? [null, null] : ['M '.number_format((float) $usd * $rate), 'US$ '.number_format((float) $usd)];
        $lslM  = fn ($lsl) => $lsl === null ? [null, null] : ['M '.number_format((float) $lsl), 'US$ '.number_format((float) $lsl / max($rate, 0.0001))];

        $eoiRow = fn ($label, $value, $sub = null, $flag = null) => [$label, $value, 'EOI', $sub, $flag];
        $proRow = fn ($label, $value, $sub = null, $flag = null) => [$label, $value, 'Profile', $sub, $flag];
        $money  = fn ($src, $label, array $m) => [$label, $m[0], $src, $m[1], null];
        $prof   = fn (array $rows) => $p ? $rows : [];

        $investors = collect($p?->investorRows() ?? [])
            ->map(fn ($r) => $r['name'].(filled($r['country'] ?? null) ? ' ('.$r['country'].')' : '').(filled($r['ticket'] ?? null) ? ' — M '.number_format((float) $r['ticket']) : ''))
            ->all() ?: null;
        $history = collect($p?->investment_history ?? [])
            ->map(fn ($h, $k) => ($P::FUNDING_SOURCES[$k] ?? $k).': '.(filled($h['amount'] ?? null) ? 'M '.number_format((float) $h['amount']) : '—')
                .(filled($h['date'] ?? null) ? ' (last '.\Illuminate\Support\Carbon::parse($h['date'])->format('M Y').')' : ''))
            ->values()->all() ?: null;
        $es = $p?->es_checklist
            ? collect($P::ES_CHECKLIST)->map(fn ($q, $k) => $q[0].' — '.(['yes' => 'Yes', 'no' => 'No', 'na' => 'N/A'][$p->es_checklist[$k] ?? ''] ?? '—'))->values()->all()
            : null;
        $gaps = $p ? $p->esGaps() : [];

        $ev = [
            'enterprise' => [
                $eoiRow('Business name', $eoi->business_name),
                $eoiRow('Legal status', $l('legal_status', $eoi->legal_status)),
                $eoiRow('Year commenced', $eoi->year_commenced),
                ...$prof([
                    $proRow('Registered name', $p?->registered_name, $p?->short_name ? 'Short name: '.$p->short_name : null),
                    $proRow('Registered at OBFC', $yn($p?->obfc_registered), $p?->registration_no ? 'Registration no. '.$p->registration_no : null, $p?->obfc_registered === false ? 'bad' : null),
                    $proRow('Type of registration', $p?->legal_form),
                    $proRow('Registered in another country', $p?->foreign_registered ? ($p->foreign_country ?: 'Yes') : $yn($p?->foreign_registered)),
                    $proRow('Address', collect([$p?->address, $p?->city, $p?->district])->filter()->implode(', ') ?: null),
                    $proRow('Sector · business model', collect([$p?->sector, $P::BUSINESS_MODELS[$p?->business_model ?? ''] ?? null])->filter()->implode(' · ') ?: null),
                    $proRow('Established · stage', collect([$p?->year_established, $P::STAGES[$p?->company_stage ?? ''] ?? null])->filter()->implode(' · ') ?: null),
                    $proRow('Employees', $p?->employees !== null ? $num($p->employees).' current · '.($num($p->employees_prev_year) ?? '—').' previous year' : null,
                        $p?->employeeBreakdownTotal() !== null ? "FT {$p->ft_male} M / {$p->ft_female} F · PT {$p->pt_male} M / {$p->pt_female} F" : null),
                    $proRow('Shareholding', $p?->ownershipLine()),
                    $proRow('Ownership & management', $p?->management),
                    $proRow('Prior CAFI support', $p?->labels($P::PRIOR_SUPPORT, $p?->prior_cafi_support), $p?->prior_support_details),
                    $proRow('RSL tax file', ['yes' => 'Yes', 'no' => 'No'][$p?->es_checklist['rsl_tax_file'] ?? ''] ?? null, null, ($p?->es_checklist['rsl_tax_file'] ?? null) === 'no' ? 'bad' : null),
                ]),
            ],
            'investor' => [
                $eoiRow('Investor', $eoi->investor_name),
                $eoiRow('Investor status', $l('investor_status', $eoi->investor_status), $eoi->investor_status === 'other' ? $eoi->investor_status_other : null),
                $money('EOI', 'Investor amount', $usdM($eoi->investor_amount_usd)),
                ...$prof([
                    $proRow('Investors in the round', $investors),
                    $proRow('Lead investor type', $p?->investor_type),
                    $money('Profile', 'Current investment round', $lslM($p?->round_amount)),
                ]),
            ],
            'transaction' => [
                $eoiRow('Evidence available', $ls('investor_evidence', $eoi->investor_evidence)),
                $eoiRow('Investment type(s)', $ls('investment_types', $eoi->investment_types)),
                $money('EOI', 'Investment required', $usdM($eoi->investment_required_usd)),
                $eoiRow('Intended use', $ls('use_of_funds', $eoi->use_of_funds), in_array('other', $eoi->use_of_funds ?? [], true) ? $eoi->use_of_funds_other : null),
                $eoiRow('Planned use', $eoi->use_of_funds_description),
                ...$prof([
                    $proRow('Financing type', $P::PARTICIPATION[$p?->participation ?? ''] ?? $p?->participation),
                    $proRow('Status of the deal', $P::TRANSACTION_STATUSES[$p?->transaction_status ?? ''] ?? null),
                    $money('Profile', 'Total project cost', $lslM($p?->project_cost)),
                    $money('Profile', 'CAFI matching grant ('.$P::grantRatioLabel().')', $lslM($p?->grant_requested)),
                    $proRow('What the money is for', $p?->investment_purpose),
                ]),
            ],
            'financial' => [
                $eoiRow('Annual turnover', $l('annual_turnover', $eoi->annual_turnover)),
                ...$prof([
                    $money('Profile', 'Annual revenue (past year)', $lslM($p?->annual_revenue)),
                    $proRow('Investment to date', $history),
                    $proRow('Current markets', $p?->labels($P::MARKETS, $p?->markets)),
                    $proRow('Products, customers & markets', $p?->customers),
                    $proRow('Company summary', $p?->activity),
                ]),
            ],
            'esgc' => [
                ...$prof([
                    $proRow('Women / youth owned or managed', $p ? 'Women: '.($yn($p->women_owned_managed) ?? '—').' · Youth: '.($yn($p->youth_owned_managed) ?? '—') : null),
                    $proRow('E&S checklist', $es, $p?->es_checklist ? (count($gaps) ? count($gaps).' to address — must comply after the grant is awarded' : 'All requirements met') : null,
                        $p?->es_checklist ? (count($gaps) ? 'bad' : 'ok') : null),
                ]),
            ],
            'risk' => [
                $eoiRow('EOI declaration', $eoi->declaration_agree ? 'Agreed' : 'Not agreed', null, $eoi->declaration_agree ? 'ok' : 'bad'),
                ...$prof([
                    $proRow('E&S gaps', $gaps ? collect($gaps)->map(fn ($k) => $P::ES_CHECKLIST[$k][0])->all() : 'None', null, $gaps ? 'bad' : 'ok'),
                    $proRow('Prior CAFI grant support', $p?->labels($P::PRIOR_SUPPORT, $p?->prior_cafi_support), 'Check for double financing', null),
                    $proRow('Profile declaration', $p?->declaration_agreed ? 'Signed' : 'Not signed',
                        $p?->declared_name ? $p->declared_name.($p->declared_on ? ' · '.$p->declared_on->format('d M Y') : '') : null, $p?->declaration_agreed ? 'ok' : 'bad'),
                ]),
            ],
        ];

        // Only documents that apply to this applicant (registration ones depend on their answers)
        $required   = $p?->requiredDocumentTypes() ?? array_keys(array_filter(EnterpriseDocument::TYPES, fn ($t) => $t[3] === true));
        $applicable = fn (string $t) => isset(EnterpriseDocument::TYPES[$t]) && (is_bool(EnterpriseDocument::TYPES[$t][3]) || in_array($t, $required, true));

        return collect($ev)->map(fn ($rows, $area) => [
            'rows' => $rows,
            'docs' => collect(self::AREA_DOCS[$area] ?? [])->filter($applicable)
                ->map(fn ($t) => [EnterpriseDocument::TYPES[$t][0], $p?->document($t), in_array($t, $required, true)])
                ->values()->all(),
        ])->all();
    }

    /** What the applicant asked for (to compare with the validated figures) */
    #[Computed] public function requested(): array
    {
        $p = $this->profile; $eoi = $this->eoi; $rate = (float) config('cif.usd_to_lsl', 18.00);

        return [
            'private' => $p?->investor_contribution ?? ($eoi->investor_amount_usd !== null ? (float) $eoi->investor_amount_usd * $rate : null),
            'grant'   => $p?->grant_requested,
        ];
    }

    /* ---------------- Load ---------------- */

    protected function load(): void
    {
        $dd = DueDiligence::with('conditions')->where('expression_of_interest_id', $this->eoiId)->orderByDesc('round')->first();

        $this->ddId       = $dd?->id;
        $this->round      = $dd?->round ?? 1;
        $this->conditions = $dd ? $dd->conditions->map->only(['id', 'finding', 'treatment', 'owner', 'status'])->values()->all() : [];

        $f = $dd ? $dd->toForm() : DueDiligence::prefill($this->eoi, $this->profile);
        $f['areas'] = collect(DueDiligence::AREAS)->mapWithKeys(fn ($a, $k) => [$k => [
            'status'  => $f['areas'][$k]['status'] ?? null,
            'finding' => $f['areas'][$k]['finding'] ?? '',
        ]])->all();
        foreach (['private_amount', 'grant_amount'] as $n) {
            $f[$n] = $f[$n] !== null && $f[$n] !== '' ? (float) $f[$n] : null;
        }
        $f['assessed_on'] = $f['assessed_on'] ?: now()->toDateString();
        $this->form = $f;

        unset($this->dd, $this->history, $this->hasIam);
    }

    /* ---------------- Autosave ---------------- */

    public function updated(string $name): void
    {
        if (! Str::startsWith($name, ['form', 'conditions'])) {
            return;
        }
        if (! $this->canEdit) {
            $this->load();
            return;
        }
        $this->persist();
    }

    public function addCondition(): void
    {
        $this->authorizeEdit();
        $this->conditions[] = ['id' => null, 'finding' => '', 'treatment' => 'before_ic', 'owner' => 'enterprise', 'status' => 'open'];
        $this->persist();
    }

    public function removeCondition(int $i): void
    {
        $this->authorizeEdit();
        unset($this->conditions[$i]);
        $this->conditions = array_values($this->conditions);
        $this->persist();
    }

    /* ---------------- Memorandum ---------------- */

    public function updatedIam(): void
    {
        abort_unless($this->canMemo, 403);
        $this->validate(['iam' => ['required', 'file', 'mimes:pdf,doc,docx', 'max:20480']], [], ['iam' => 'memorandum']);

        $dd = $this->persist();
        if ($dd->iam_path) {
            Storage::disk('local')->delete($dd->iam_path);
        }
        $dd->update([
            'iam_path'        => $this->iam->store("due-diligence/{$dd->id}", 'local'),
            'iam_name'        => $this->iam->getClientOriginalName(),
            'iam_size'        => $this->iam->getSize(),
            'iam_uploaded_by' => auth()->id(),
            'iam_uploaded_at' => now(),
        ]);

        $this->reset('iam');
        unset($this->dd, $this->hasIam);
        $this->dispatch('notify', type: 'success', message: 'Memorandum uploaded.');
    }

    /* ---------------- Outcome ---------------- */

    public function recordOutcome(): void
    {
        $this->authorizeEdit();

        $this->validate([
            'form.outcome'     => ['required', Rule::in(array_keys(DueDiligence::OUTCOMES))],
            'form.conclusion'  => ['required', 'string', 'max:5000'],
            'form.assessed_on' => ['required', 'date', 'before_or_equal:today'],
        ], ['form.outcome.required' => 'Choose the outcome.', 'form.conclusion.required' => 'Write your conclusion.']);

        $outcome = $this->form['outcome'];

        if ($outcome === DueDiligence::OUTCOME_PROCEED && DueDiligence::proceedBlockers($this->form, $this->conditions, $this->hasIam)) {
            $this->addError('form.outcome', 'Not ready to proceed — see the list above.');
            return;
        }
        if (in_array($outcome, [DueDiligence::OUTCOME_CLARIFICATION, DueDiligence::OUTCOME_REMEDIATION], true)
            && ! collect($this->conditions)->contains('status', 'open')) {
            $this->addError('form.outcome', 'Add at least one open condition saying what is required.');
            return;
        }

        DB::transaction(function () use ($outcome) {
            $this->persist(updateEoi: false)->update(['decided_at' => now(), 'decided_by' => auth()->id()]);
            $this->eoi->update(['status' => DueDiligence::OUTCOME_TO_EOI_STATUS[$outcome]]);
        });

        // E-mails (after the outcome is saved)
        $notes = [$this->emailApplicantOutcome()];
        if ($outcome === DueDiligence::OUTCOME_PROCEED) {
            $notes[] = $this->emailCommittee();
        }

        unset($this->eoi, $this->dd, $this->history, $this->isOpenForWork, $this->canEdit, $this->canMemo);
        $this->dispatch('gate-decided', gate: 'g2', decision: $outcome);
        $this->dispatch('notify', type: 'success',
            message: 'Gate 2 outcome recorded: '.DueDiligence::OUTCOMES[$outcome][0].'. '.implode(' ', array_filter($notes)));
    }

    /** On hold → new round, everything carried over */
    public function resumeAssessment(): void
    {
        abort_unless(! $this->isApplicant && auth()->user()->can('gate2.assess')
            && $this->eoi->status === ExpressionOfInterest::STATUS_DD_HOLD && $this->dd?->isOnHold(), 403);

        $old = $this->dd->load('conditions');

        DB::transaction(function () use ($old) {
            $new = DueDiligence::create(array_merge($old->only(DueDiligence::FORM_COLUMNS), [
                'expression_of_interest_id' => $this->eoiId,
                'round'                     => $old->round + 1,
                'assessor_id'               => auth()->id(),
                'started_on'                => now()->toDateString(),
                'outcome'                   => null,
                'conclusion'                => null,
                'assessed_on'               => now()->toDateString(),
            ]));
            foreach ($old->conditions as $c) {
                $new->conditions()->create($c->only(['finding', 'treatment', 'owner', 'status', 'resolved_at', 'position']));
            }
            $this->eoi->update(['status' => ExpressionOfInterest::STATUS_DUE_DILIGENCE]);
        });

        // Let the applicant know their response was received
        $note = $this->emailApplicantResumed();

        unset($this->eoi);
        $this->load();
        unset($this->isOpenForWork, $this->canEdit, $this->canMemo);
        $this->dispatch('gate-decided', gate: 'g2', decision: null);
        $this->dispatch('notify', type: 'success', message: "Assessment resumed — round {$this->round}. Upload an updated memorandum. {$note}");
    }

    /* ---------------- E-mails ---------------- */

    /** Applicant: the Gate 2 outcome. Returns a short note for the officer. */
    protected function emailApplicantOutcome(): string
    {
        $eoi = ExpressionOfInterest::with('user')->find($this->eoiId);
        $dd  = DueDiligence::find($this->ddId);

        if (! $eoi || ! $dd || ! $dd->outcome) {
            return '';
        }

        return $this->sendToApplicant($eoi, new EoiGate2Outcome($eoi, $dd));
    }

    /** Applicant: the assessment has resumed */
    protected function emailApplicantResumed(): string
    {
        $eoi = ExpressionOfInterest::with('user')->find($this->eoiId);

        return $eoi ? $this->sendToApplicant($eoi, new EoiDueDiligenceResumed($eoi)) : '';
    }

    protected function sendToApplicant(ExpressionOfInterest $eoi, $notification): string
    {
        $applicant = $eoi->user;

        if (! $applicant || blank($applicant->email)) {
            return 'The applicant has no e-mail address — please let them know.';
        }

        try {
            $applicant->notify($notification);

            return 'The applicant was e-mailed ('.$applicant->email.').';
        } catch (\Throwable $e) {
            report($e);

            return 'The e-mail to the applicant could not be sent — please let them know.'.$this->debugReason($e);
        }
    }

    /** Investment Committee members (role "committee") + the Committee secretary (gate3.decide): ready for review */
    protected function emailCommittee(): string
    {
        $eoi = ExpressionOfInterest::find($this->eoiId);
        $dd  = DueDiligence::find($this->ddId);

        if (! $eoi || ! $dd) {
            return '';
        }

        return \App\Support\NotifyRecipients::send(
            \App\Support\NotifyRecipients::committee(),
            new EoiReadyForCommittee($eoi, $dd),
            'the Investment Committee',
        );
    }

    /** While APP_DEBUG=true, show why an e-mail failed (the full error is always in storage/logs/laravel.log) */
    protected function debugReason(\Throwable $e): string
    {
        return config('app.debug') ? ' ['.class_basename($e).': '.Str::limit($e->getMessage(), 200).']' : '';
    }

    /* ---------------- Internals ---------------- */

    protected function persist(bool $updateEoi = true): DueDiligence
    {
        $data = collect(DueDiligence::FORM_COLUMNS)->mapWithKeys(fn ($c) => [$c => $this->form[$c] ?? null])->all();
        foreach (['private_amount', 'grant_amount', 'stage', 'instrument', 'esgc_category', 'outcome', 'assessed_on'] as $c) {
            $data[$c] = $data[$c] === '' ? null : $data[$c];
        }

        $dd = DB::transaction(function () use ($data) {
            if ($this->ddId) {
                $dd = DueDiligence::findOrFail($this->ddId);
                $dd->update($data);
            } else {
                $dd = DueDiligence::create($data + [
                    'expression_of_interest_id' => $this->eoiId,
                    'round'                     => $this->round,
                    'assessor_id'               => auth()->id(),
                    'started_on'                => now()->toDateString(),
                ]);
                $this->ddId = $dd->id;
            }
            $this->syncConditions($dd);

            return $dd;
        });

        if ($updateEoi && $this->eoi->status === ExpressionOfInterest::STATUS_INVITED) {
            $this->eoi->update(['status' => ExpressionOfInterest::STATUS_DUE_DILIGENCE]);
            $this->dispatch('gate-decided', gate: 'g2', decision: null);
        }

        $this->savedAt = now()->format('H:i');
        unset($this->dd, $this->eoi);

        return $dd;
    }

    protected function syncConditions(DueDiligence $dd): void
    {
        $keep = [];
        foreach (array_values($this->conditions) as $i => $row) {
            $values = [
                'finding'   => Str::limit((string) $row['finding'], 250, ''),
                'treatment' => $row['treatment'],
                'owner'     => $row['owner'],
                'status'    => $row['status'],
                'position'  => $i,
            ];
            $model = ! empty($row['id']) ? $dd->conditions()->whereKey($row['id'])->first() : null;

            if ($model) {
                if ($model->status !== $values['status']) {
                    $values['resolved_at'] = $values['status'] === 'resolved' ? now() : null;
                }
                $model->update($values);
            } else {
                $model = $dd->conditions()->create($values + ['resolved_at' => $values['status'] === 'resolved' ? now() : null]);
            }

            $keep[] = $model->id;
            $this->conditions[$i]['id'] = $model->id;
        }
        $dd->conditions()->whereNotIn('id', $keep)->delete();
    }

    protected function authorizeEdit(): void
    {
        abort_unless($this->canEdit, 403, 'This assessment can no longer be changed.');
    }

    #[On('gate-decided')]
    public function refreshGate(): void
    {
        unset($this->eoi, $this->gateOpen, $this->isOpenForWork, $this->canEdit, $this->canMemo);
    }
};
?>

<div>
@php
    $DD       = \App\Models\DueDiligence::class;
    $DC       = \App\Models\DueDiligenceCondition::class;
    $eoi      = $this->eoi;
    $dd       = $this->dd;
    $canEdit  = $this->canEdit;
    $canMemo  = $this->canMemo;
    $decided  = (bool) $dd?->isDecided();
    $hasIam   = $this->hasIam;
    $v        = $DD::validated($form);
    $progress = $DD::progress($form, $hasIam);
    $blockers = $this->isApplicant ? [] : $DD::proceedBlockers($form, $conditions, $hasIam);
    $req      = $this->isApplicant ? [] : $this->requested;
    $evidence = $this->isApplicant || ! $this->gateOpen ? [] : $this->evidence;
    $rate     = (float) config('cif.usd_to_lsl', 18.00);
    $m        = fn ($x) => $x === null || $x === '' ? '—' : 'M '.number_format((float) $x);
    $u        = fn ($x) => $x === null || $x === '' ? '' : 'US$ '.number_format((float) $x / max($rate, 0.0001));
@endphp

{{-- ======================= LOCKED ======================= --}}
@if (! $this->gateOpen)
    <section class="section-card">
        <div class="section-card-head">
            <span class="section-no">G2</span>
            <h5 class="me-auto">Due diligence</h5>
            <span class="pill pill-muted"><i class="bi bi-lock-fill"></i> Locked</span>
        </div>
        <p class="small text-muted mb-0">Opens once the application passes Gate 1.</p>
    </section>

{{-- ======================= APPLICANT ======================= --}}
@elseif ($this->isApplicant)
    @php $mine = collect($conditions)->where('owner', 'enterprise')->where('status', 'open'); @endphp
    <section class="section-card">
        <div class="section-card-head">
            <span class="section-no">G2</span>
            <h5 class="me-auto">Due diligence</h5>
            @if ($decided)
                @php [$oL, , $oI, $oP] = $DD::OUTCOMES[$dd->outcome]; @endphp
                <span class="pill {{ $oP }}"><i class="bi {{ $oI }}"></i> {{ $oL }}</span>
            @else
                <span class="pill pill-warn"><i class="bi bi-hourglass-split"></i> In progress</span>
            @endif
        </div>
        <p class="small text-muted mb-0">
            @if (! $decided) The LEHSFF team is assessing your enterprise, investor and transaction. They may contact you for more information.
            @elseif ($dd->outcome === 'proceed') Due diligence is complete — your application goes to the Investment Committee.
            @elseif ($dd->outcome === 'do_not_progress') Your application will not progress further.
            @else More information or a corrective action is needed before your application can proceed. The LEHSFF team will be in touch.
            @endif
        </p>
        @if ($mine->isNotEmpty())
            <dt class="detail-label mt-3 mb-2">Actions for your enterprise</dt>
            <ul class="checklist mb-0">
                @foreach ($mine as $c)
                    <li class="is-pending"><i class="bi bi-exclamation-circle"></i>
                        <div><div class="fw-semibold text-navy">{{ $c['finding'] }}</div><div class="small text-muted">{{ $DC::TREATMENTS[$c['treatment']][0] ?? '' }}</div></div></li>
                @endforeach
            </ul>
        @endif
    </section>

{{-- ======================= STAFF ======================= --}}
@else
    {{-- Header --}}
    <section class="section-card">
        <div class="section-card-head">
            <span class="section-no">G2</span>
            <div class="me-auto">
                <h5>Due diligence &amp; investment appraisal @if ($round > 1) <span class="pill pill-muted ms-1">Round {{ $round }}</span> @endif</h5>
                <div class="small text-muted">Is the transaction credible and well enough evidenced to go to the Investment Committee?</div>
            </div>
            @if ($decided)
                @php [$oL, , $oI, $oP] = $DD::OUTCOMES[$dd->outcome]; @endphp
                <span class="pill {{ $oP }}"><i class="bi {{ $oI }}"></i> {{ $oL }}</span>
            @else
                <span class="pill pill-warn">{{ $progress['done'] }} / {{ $progress['total'] }} steps</span>
            @endif
        </div>

        <div class="progress mb-2" style="height: 6px;">
            <div class="progress-bar {{ $progress['pct'] === 100 ? 'bg-accent' : 'bg-brand' }}" style="width: {{ $progress['pct'] }}%;"></div>
        </div>
        <div class="d-flex flex-wrap gap-3 small text-muted">
            <span><i class="bi bi-building me-1"></i>{{ $eoi->business_name }} <span class="chip ms-1">{{ $eoi->reference }}</span></span>
            <span><i class="bi bi-person me-1"></i>{{ $dd?->assessor?->name ?? auth()->user()->name }}</span>
            @if ($dd?->started_on) <span><i class="bi bi-calendar me-1"></i>Started {{ $dd->started_on->format('d M Y') }}</span> @endif
            @if ($canEdit)
                <span class="ms-auto"><i class="bi bi-cloud-check me-1"></i>Saved automatically @if ($savedAt) · {{ $savedAt }} @endif
                    <span wire:loading class="spinner-border spinner-border-sm ms-1"></span></span>
            @elseif (! $decided)
                <span class="ms-auto"><i class="bi bi-eye me-1"></i>Read-only</span>
            @endif
        </div>
    </section>

    {{-- 1 · Checklist --}}
    <section class="section-card" id="g2-checklist">
        <div class="section-card-head">
            <span class="section-no">1</span>
            <div class="me-auto">
                <h5>Assessment checklist</h5>
                <div class="small text-muted">Proportionate and risk-based — check what matters, record what you found.</div>
            </div>
            @canany(['eoi.view-all', 'users.manage'])
                <a href="{{ route('profile.index', $eoi->user_id) }}" target="_blank" class="small text-decoration-none"><i class="bi bi-box-arrow-up-right me-1"></i>Enterprise profile &amp; documents</a>
            @endcanany
        </div>

        @foreach ($DD::AREAS as $key => [$aTitle, $aIcon, $aQ, $aCovers])
            @php $st = $form['areas'][$key]['status']; @endphp
            <div class="border rounded-3 p-3 mb-2 {{ $st === 'clarification' ? 'border-warning' : '' }}" id="g2-area-{{ $key }}" wire:key="area-{{ $key }}">
                <div class="d-flex flex-wrap align-items-start gap-2 mb-2">
                    <span class="section-no flex-shrink-0"><i class="bi {{ $aIcon }}"></i></span>
                    <div class="flex-grow-1" style="min-width: 220px">
                        <div class="fw-semibold text-navy">{{ $aTitle }}</div>
                        <div class="small text-muted">{{ $aQ }}</div>
                        <div class="dd-hint text-muted mt-1"><i class="bi bi-lightbulb me-1"></i>{{ $aCovers }}</div>
                    </div>
                    <select class="form-select form-select-sm status-select is-{{ $st ?: 'pending' }}" style="width: auto" wire:model.live="form.areas.{{ $key }}.status" @disabled(! $canEdit)>
                        <option value="">Not assessed</option>
                        @foreach ($DD::STATUSES as $sk => [$sl]) <option value="{{ $sk }}">{{ $sl }}</option> @endforeach
                    </select>
                </div>
                <textarea rows="2" class="form-control form-control-sm" wire:model.blur="form.areas.{{ $key }}.finding" @disabled(! $canEdit)
                          placeholder="Findings — what you checked and what you found"></textarea>

                {{-- What the applicant submitted (EOI + enterprise profile + documents) --}}
                @php
                    $ev      = $evidence[$key] ?? ['rows' => [], 'docs' => []];
                    $missing = collect($ev['rows'])->filter(fn ($r) => blank($r[1]))->count()
                             + collect($ev['docs'])->filter(fn ($d) => ! $d[1] && $d[2])->count();
                @endphp
                @if ($ev['rows'] || $ev['docs'])
                    <details class="evidence mt-2">
                        <summary>
                            <i class="bi bi-file-earmark-text me-1"></i> Submitted information &amp; documents
                            @if ($missing) <span class="pill pill-warn ms-2">{{ $missing }} not provided</span> @endif
                        </summary>
                        <dl class="evidence-grid">
                            @foreach ($ev['rows'] as [$rLabel, $rValue, $rSource, $rSub, $rFlag])
                                <dt><span class="src-tag {{ $rSource === 'EOI' ? 'src-eoi' : 'src-profile' }}">{{ $rSource }}</span> {{ $rLabel }}</dt>
                                <dd>
                                    @if (blank($rValue))
                                        <span class="text-muted fst-italic">Not provided</span>
                                    @elseif (is_array($rValue))
                                        <ul class="evidence-list">@foreach ($rValue as $item)<li>{{ $item }}</li>@endforeach</ul>
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
                @endif
            </div>
        @endforeach
    </section>

    {{-- 2 · Key figures --}}
    <section class="section-card" id="g2-figures">
        <div class="section-card-head">
            <span class="section-no">2</span>
            <div class="me-auto">
                <h5>Transaction &amp; co-investment</h5>
                <div class="small text-muted">The validated figures that go to the Committee.</div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label small fw-semibold">Investor(s)</label>
                <input type="text" class="form-control form-control-sm" wire:model.blur="form.investor_names" maxlength="250" placeholder="e.g. Highlands Seed Partners (lead) · R. Mokoena" @disabled(! $canEdit)>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Transaction stage</label>
                <select class="form-select form-select-sm" wire:model.live="form.stage" @disabled(! $canEdit)>
                    <option value="">Select…</option>
                    @foreach ($DD::STAGES as $sk => [$sl]) <option value="{{ $sk }}">{{ $sl }}</option> @endforeach
                </select>
                @if ($form['stage']) <div class="dd-hint text-muted mt-1">Evidence: {{ $DD::STAGES[$form['stage']][1] }}</div> @endif
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Instrument</label>
                <select class="form-select form-select-sm" wire:model.live="form.instrument" @disabled(! $canEdit)>
                    <option value="">Select…</option>
                    @foreach ($DD::INSTRUMENTS as $ik => $il) <option value="{{ $ik }}">{{ $il }}</option> @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-semibold">Private capital committed (M)</label>
                <input type="number" min="0" step="1000" class="form-control form-control-sm" wire:model.blur="form.private_amount" @disabled(! $canEdit)>
                <div class="dd-hint text-muted mt-1">{{ $u($form['private_amount']) }} · Only signed, eligible commitments. Applicant stated {{ $m($req['private'] ?? null) }}</div>
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-semibold">CAFI grant (M)</label>
                <input type="number" min="0" step="1000" class="form-control form-control-sm" wire:model.blur="form.grant_amount" @disabled(! $canEdit)>
                <div class="dd-hint text-muted mt-1">{{ $u($form['grant_amount']) }} · {{ \App\Models\EnterpriseProfile::grantRatioLabel() }} of the validated investor amount = {{ $m(\App\Models\EnterpriseProfile::matchingGrant(is_numeric($form['private_amount'] ?? null) ? (float) $form['private_amount'] : null)) }}</div>
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-semibold">ESGC risk</label>
                <select class="form-select form-select-sm rating-select is-{{ $form['esgc_category'] ?: 'none' }}" wire:model.live="form.esgc_category" @disabled(! $canEdit)>
                    <option value="">Select…</option>
                    @foreach ($DD::RATINGS as $rk => $rl) <option value="{{ $rk }}">{{ $rl }}</option> @endforeach
                </select>
            </div>
        </div>

        @if ($form['stage'] && ! in_array($form['stage'], $DD::COMMITTED_STAGES, true))
            <div class="callout callout-warning small mt-3 mb-0"><i class="bi bi-exclamation-triangle me-1"></i> This is investor <strong>interest</strong>, not commitment — an LOI or later is needed.</div>
        @endif
        <div class="callout small mt-3 mb-0">
            <i class="bi bi-calculator me-1"></i>
            Total transaction (private capital + CAFI grant): <strong>{{ $m($v['total']) }}</strong> <span class="text-muted">({{ $u($v['total']) }})</span>
        </div>
    </section>

    {{-- 3 · Conditions --}}
    <section class="section-card" id="g2-conditions">
        <div class="section-card-head">
            <span class="section-no">3</span>
            <div class="me-auto">
                <h5>Conditions &amp; actions</h5>
                <div class="small text-muted">Anything that must be done, and when — including ESAP actions. "Before the Committee" items block Proceed while open. Open items owned by the enterprise or investor are e-mailed to the applicant with the outcome.</div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-modern align-middle mb-2 dd-table">
                <thead><tr><th>Condition / action</th><th style="width: 26%">When</th><th style="width: 13%">Owner</th><th style="width: 13%">Status</th>@if ($canEdit)<th></th>@endif</tr></thead>
                <tbody>
                    @forelse ($conditions as $i => $c)
                        <tr wire:key="cond-{{ $c['id'] ?? 'n'.$i }}" class="{{ $DC::blocks($c) ? 'is-flagged' : '' }}">
                            <td><input type="text" class="form-control form-control-sm" wire:model.blur="conditions.{{ $i }}.finding" maxlength="250" placeholder="e.g. Signed shareholder agreement" @disabled(! $canEdit)></td>
                            <td><select class="form-select form-select-sm" wire:model.live="conditions.{{ $i }}.treatment" @disabled(! $canEdit)>
                                @foreach ($DC::TREATMENTS as $tk => [$tl]) <option value="{{ $tk }}">{{ $tl }}</option> @endforeach</select></td>
                            <td><select class="form-select form-select-sm" wire:model.live="conditions.{{ $i }}.owner" @disabled(! $canEdit)>
                                @foreach ($DC::OWNERS as $ok => $ol) <option value="{{ $ok }}">{{ $ol }}</option> @endforeach</select></td>
                            <td><select class="form-select form-select-sm" wire:model.live="conditions.{{ $i }}.status" @disabled(! $canEdit)>
                                @foreach ($DC::STATUSES as $sk => [$sl]) <option value="{{ $sk }}">{{ $sl }}</option> @endforeach</select></td>
                            @if ($canEdit)
                                <td><button type="button" class="btn btn-icon btn-icon-danger" wire:click="removeCondition({{ $i }})" title="Remove"><i class="bi bi-x-lg"></i></button></td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="5" class="small text-muted">None yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($canEdit)
            <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="addCondition"><i class="bi bi-plus-lg me-1"></i> Add condition</button>
        @endif
    </section>

    {{-- 4 · Summary + memorandum --}}
    <section class="section-card" id="g2-summary">
        <div class="section-card-head">
            <span class="section-no">4</span>
            <div class="me-auto">
                <h5>Summary for the Investment Committee</h5>
                <div class="small text-muted">With the figures above, this forms the Transaction Summary Sheet.</div>
            </div>
        </div>
        <div class="row g-3">
            <div class="col-12">
                <label class="form-label small fw-semibold">Enterprise &amp; transaction summary</label>
                <textarea rows="2" class="form-control form-control-sm" wire:model.blur="form.summary" @disabled(! $canEdit) placeholder="Business, sector and what the investment is for"></textarea>
            </div>
            <div class="col-md-6">
                <label class="form-label small fw-semibold">Expected outcomes</label>
                <textarea rows="2" class="form-control form-control-sm" wire:model.blur="form.expected_outcomes" @disabled(! $canEdit) placeholder="Revenue, jobs, exports, innovation"></textarea>
            </div>
            <div class="col-md-6">
                <label class="form-label small fw-semibold">Key risks</label>
                <textarea rows="2" class="form-control form-control-sm" wire:model.blur="form.key_risks" @disabled(! $canEdit) placeholder="Main risks and how they are mitigated"></textarea>
            </div>
        </div>

        <label class="form-label small fw-semibold mt-3">Investment Appraisal Memorandum</label>
        @if ($dd?->iam_path)
            <div class="file-row mb-0">
                <span class="file-row-icon"><i class="bi bi-file-earmark-text"></i></span>
                <div class="flex-grow-1 small">
                    <div class="fw-semibold text-navy">{{ $dd->iam_name }}</div>
                    <div class="text-muted">{{ $dd->iamUploader?->name }} · {{ $dd->iam_uploaded_at?->format('d M Y H:i') }}</div>
                </div>
                <a href="{{ route('due-diligence.memorandum', $dd) }}" class="btn btn-icon" title="Download"><i class="bi bi-download"></i></a>
                @if ($canMemo)
                    <label class="btn btn-sm btn-outline-secondary mb-0"><i class="bi bi-upload me-1"></i> Replace
                        <input type="file" class="d-none" wire:model="iam" accept=".pdf,.doc,.docx"></label>
                @endif
            </div>
        @elseif ($canMemo)
            <label class="file-row upload-drop mb-0">
                <span class="file-row-icon"><i class="bi bi-cloud-arrow-up"></i></span>
                <div class="flex-grow-1 small">
                    <div class="fw-semibold text-navy" wire:loading.remove wire:target="iam">Upload memorandum</div>
                    <div class="fw-semibold text-navy" wire:loading wire:target="iam">Uploading…</div>
                    <div class="text-muted">PDF or Word, up to 20 MB</div>
                </div>
                <input type="file" class="d-none" wire:model="iam" accept=".pdf,.doc,.docx">
            </label>
        @else
            <div class="small text-muted">Not uploaded yet.</div>
        @endif
        @error('iam') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
    </section>

    {{-- 5 · Outcome --}}
    <section class="section-card" id="g2-outcome">
        <div class="section-card-head">
            <span class="section-no">5</span>
            <div class="me-auto">
                <h5>Gate 2 outcome</h5>
                <div class="small text-muted">@if ($decided) Recorded by {{ $dd->decider?->name }} on {{ $dd->decided_at->format('d M Y H:i') }}. @else Is the transaction ready for the Investment Committee? @endif</div>
            </div>
        </div>

        <div class="choice-group cols-4 @error('form.outcome') is-invalid @enderror">
            @foreach ($DD::OUTCOMES as $value => [$oLabel, $oDesc, $oIcon])
                <label class="choice choice--card">
                    <input type="radio" name="g2-outcome" value="{{ $value }}" wire:model.live="form.outcome" @disabled(! $canEdit)>
                    <span class="choice-box">
                        <span class="choice-icon"><i class="bi {{ $oIcon }}"></i></span>
                        <span><span class="d-block fw-semibold">{{ $oLabel }}</span><span class="d-block small text-muted lh-sm mt-1">{{ $oDesc }}</span></span>
                    </span>
                </label>
            @endforeach
        </div>
        @error('form.outcome') <div class="text-danger small mt-1">{{ $message }}</div> @enderror

        @if (! $decided)
            @if ($blockers)
                <div class="callout callout-warning small mt-3 mb-0">
                    <div class="fw-semibold mb-1"><i class="bi bi-list-check me-1"></i> Before you can Proceed ({{ count($blockers) }})</div>
                    <ul class="evidence-list mb-0">@foreach ($blockers as $b) <li>{{ $b }}</li> @endforeach</ul>
                </div>
            @else
                <div class="callout callout-success small mt-3 mb-0"><i class="bi bi-check2-circle me-1"></i> Ready to proceed to the Investment Committee.</div>
            @endif
        @endif

        <div class="row g-3 mt-1">
            <div class="col-md-9">
                <label class="form-label small fw-semibold">Conclusion @if ($canEdit)<span class="req">*</span>@endif</label>
                <textarea rows="3" class="form-control @error('form.conclusion') is-invalid @enderror" wire:model.blur="form.conclusion" @disabled(! $canEdit)
                          placeholder="Summary of the assessment and your recommendation"></textarea>
                @error('form.conclusion') <div class="invalid-feedback">{{ $message }}</div> @enderror
                @if ($canEdit && ($form['outcome'] ?? null) === 'do_not_progress')
                    <div class="dd-hint text-warning mt-1"><i class="bi bi-envelope me-1"></i> This conclusion is e-mailed to the applicant as the basis for the decision.</div>
                @endif
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Date</label>
                <input type="date" class="form-control @error('form.assessed_on') is-invalid @enderror" wire:model.blur="form.assessed_on" max="{{ now()->toDateString() }}" @disabled(! $canEdit)>
                @error('form.assessed_on') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        </div>

        @if ($canEdit)
            <div class="d-flex mt-4 pt-3 border-top">
                <button type="button" class="btn btn-brand ms-auto" wire:click="recordOutcome" wire:loading.attr="disabled" wire:target="recordOutcome"
                        wire:confirm="Record the Gate 2 outcome? The assessment is locked afterwards, and the applicant will be e-mailed (and the Investment Committee too, if it proceeds).">
                    <span wire:loading.remove wire:target="recordOutcome"><i class="bi bi-send me-1"></i> Record outcome</span>
                    <span wire:loading wire:target="recordOutcome"><span class="spinner-border spinner-border-sm me-1"></span> Recording…</span>
                </button>
            </div>
        @elseif ($dd?->isOnHold() && $eoi->status === 'dd_hold' && auth()->user()->can('gate2.assess'))
            <div class="d-flex align-items-center gap-2 mt-4 pt-3 border-top">
                <span class="small text-muted">Resume once the clarification or remediation has been received. The applicant is e-mailed that the assessment has resumed.</span>
                <button type="button" class="btn btn-outline-primary ms-auto" wire:click="resumeAssessment" wire:confirm="Resume as round {{ $round + 1 }}?">
                    <i class="bi bi-arrow-repeat me-1"></i> Resume assessment
                </button>
            </div>
        @endif
    </section>

    {{-- Earlier rounds --}}
    @if ($this->history->isNotEmpty())
        <section class="section-card">
            <div class="section-card-head"><span class="section-no"><i class="bi bi-clock-history"></i></span><h5>Earlier rounds</h5></div>
            @foreach ($this->history as $h)
                @php [$hl, , $hi, $hp] = $DD::OUTCOMES[$h->outcome] ?? [$h->outcome, '', 'bi-circle', 'pill-muted']; @endphp
                <div class="file-row align-items-start">
                    <span class="file-row-icon"><i class="bi {{ $hi }}"></i></span>
                    <div class="flex-grow-1 small">
                        <strong class="text-navy">Round {{ $h->round }}</strong> <span class="pill {{ $hp }} ms-1">{{ $hl }}</span>
                        <span class="text-muted ms-1">{{ $h->decided_at->format('d M Y') }} · {{ $h->decider?->name }}</span>
                        @if ($h->conclusion) <div class="text-muted mt-1">{{ $h->conclusion }}</div> @endif
                    </div>
                </div>
            @endforeach
        </section>
    @endif
@endif
</div>