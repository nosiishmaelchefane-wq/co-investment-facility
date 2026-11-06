<?php

use App\Models\CapitalDeployment;
use App\Models\ConditionReview;
use App\Models\CpAgreement;
use App\Models\CpCondition;
use App\Models\DisbursementTranche;
use App\Models\EnterpriseProfile;
use App\Models\ExpressionOfInterest;
use App\Notifications\Gate5\CapitalDeploymentReviewed;
use App\Notifications\Gate5\Gate5Outcome;
use App\Notifications\Gate5\Gate5StaffAlert;
use App\Notifications\Gate5\GrantApprovalNotice;
use App\Notifications\Gate5\ObligationsSummary;
use App\Notifications\Gate5\TrancheReleased;
use App\Support\NotifyRecipients;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

/*
| G6 · Conditions Precedent & Onboarding (§3.7)  +  G7 · Grant Disbursement (§3.8) — one tab (key "g5")
|   <livewire:expression-of-interest.conditions.condition-review :eoi-id="$eoi->id" />
|
|   Starts automatically once Gate 5 = formally approved (both signatures). The executed Grant Agreement, the approved
|   budget and the Gate 4 bank details are carried in; the CP checklist is built from the approval record.
|
|   Step 1  Communicate approval & initiate onboarding — executed agreement + checklist e-mailed to the enterprise (PMU)
|   Step 2  Verify conditions precedent — 7 areas on documentary evidence; issues → corrective action, party, tracking
|   Step 3  Confirm readiness — programme (PMU) + Finance confirmations → Cleared / Pending / Returned (or Escalated)
|   Step 4  Onboarding & post-disbursement obligations — baseline, obligations, enterprise confirms it was briefed
|   Gate 7  Per tranche: matching investment verified (PMU) → payment approval (disburse.approve, not the requester)
|           → payment instruction + confirmation of payment with proof (Finance). Grant follows verified private capital.
|
|   Who:  gate5.verify (PMU) set-up, verification, outcome, payment requests · capital.verify verifies deployments
|         gate5.evidence / capital.upload (enterprise) evidence + proof of investment, confirms onboarding
|         disburse.process (Finance) readiness confirmation, instruction & confirmation · disburse.approve payment approval
|   Files: route "gate5.file" (condition | agreement | deployment | tranche) · record PDF: route "gate5.record"
*/
new class extends Component
{
    use WithFileUploads;

    #[Locked] public int $eoiId;
    #[Locked] public ?int $reviewId = null;

    public array $conds = [];          // [id, area, finding, evidence_required, responsible, verifier, timing, status, note, corrective_action, source]
    public array $agreements = [];     // [id, kind, title, parties, required, status, signed_on]
    public array $verification = [];   // area => [status, note]
    public array $bank = [];           // bank, branch, account_name, account_number, account_type
    public string $bankNote = '';
    public array $readiness = [];      // check => bool
    public array $budget = [];
    public bool $editBudget = false;
    public array $onboarding = [];     // contact, frequency, first_report_on, items{}
    public array $baseline = [];
    public array $tranches = [];       // [id, label, amount, trigger]

    /** Gate 7 · deployment verification: id => [checks{}, received_account, tranche] */
    public array $dep = [];
    public array $rejectNote = [];

    /** Gate 7 · per tranche: id => note (hold / stop) · release form (Finance) · proof */
    public array $payNote = [];
    public array $release = [];
    public array $releaseProof = [];

    /** Uploads */
    public array $evidenceFiles = [];
    public array $agreementFiles = [];
    public array $investorFiles = [];

    public array $decision = ['outcome' => null, 'comments' => '', 'on' => ''];
    public string $newArea = 'contractual';
    public ?string $savedAt = null;

    public function mount(int $eoiId): void
    {
        $eoi = ExpressionOfInterest::findOrFail($eoiId);
        abort_unless($eoi->isViewableBy(auth()->user()), 403);

        $this->eoiId = $eoi->id;

        // Gate 6 starts automatically once Gate 5 formally approved the grant
        if (in_array($eoi->status, ExpressionOfInterest::GATE5_VISIBLE, true) && ! $eoi->conditionReview()->exists()) {
            DB::transaction(fn () => ConditionReview::start($eoi));
        }

        $this->load();
    }

    /* =====================================================================
     | Data & permissions
     ===================================================================== */

    #[Computed] public function eoi(): ExpressionOfInterest
    {
        return ExpressionOfInterest::with('user:id,name,email')->findOrFail($this->eoiId);
    }

    #[Computed] public function review(): ?ConditionReview
    {
        return $this->reviewId
            ? ConditionReview::with(['conditions.verifiedBy:id,name', 'agreements', 'deployments.verifier:id,name', 'deployments.recorder:id,name',
                'tranches.releaser:id,name', 'tranches.requester:id,name', 'tranches.approver:id,name', 'decider:id,name',
                'readinessPmu:id,name', 'readinessFinance:id,name', 'authorisation', 'expressionOfInterest'])->find($this->reviewId)
            : null;
    }

    #[Computed] public function profile(): ?EnterpriseProfile
    {
        return EnterpriseProfile::where('user_id', $this->eoi->user_id)->first();
    }

    /** Bank details verified at Gate 4 (to compare with what will be paid into) */
    #[Computed] public function gate4Bank(): ?array
    {
        return \App\Models\GrantReadiness::where('expression_of_interest_id', $this->eoiId)
            ->where('outcome', \App\Models\GrantReadiness::OUTCOME_APPROVED)->orderByDesc('round')->value('bank');
    }

    #[Computed] public function isApplicant(): bool { return $this->eoi->user_id === auth()->id(); }
    #[Computed] public function gateOpen(): bool    { return $this->reviewId !== null; }
    #[Computed] public function cleared(): bool     { return (bool) $this->review?->isCleared(); }
    #[Computed] public function inGate(): bool      { return in_array($this->eoi->status, ExpressionOfInterest::GATE5_VISIBLE, true); }

    /** PMU set-up and verification (locked once cleared) */
    #[Computed] public function canManage(): bool
    {
        return ! $this->isApplicant && $this->inGate && ! $this->cleared && auth()->user()->can('gate5.verify');
    }

    /** Agreements stay editable after clearance (e.g. the Investment Agreement is signed later) until the grant is fully paid */
    #[Computed] public function canManageAgreements(): bool
    {
        return ! $this->isApplicant && $this->inGate && auth()->user()->can('gate5.verify') && ! $this->review?->isFullyDisbursed();
    }

    #[Computed] public function canUploadEvidence(): bool
    {
        return $this->inGate && ! $this->cleared && (
            ($this->isApplicant && auth()->user()->can('gate5.evidence')) || $this->canManage
        );
    }

    #[Computed] public function canRecordCapital(): bool
    {
        return $this->isApplicant && $this->inGate && $this->eoi->status !== ExpressionOfInterest::STATUS_ESCALATED && auth()->user()->can('capital.upload');
    }

    #[Computed] public function canVerifyCapital(): bool
    {
        return ! $this->isApplicant && $this->inGate && auth()->user()->can('capital.verify');
    }

    #[Computed] public function isFinance(): bool
    {
        return ! $this->isApplicant && $this->inGate && auth()->user()->can('disburse.process');
    }

    #[Computed] public function canApprovePayment(): bool
    {
        return ! $this->isApplicant && $this->cleared && auth()->user()->can('disburse.approve');
    }

    #[Computed] public function canRequestPayment(): bool
    {
        return ! $this->isApplicant && $this->cleared && auth()->user()->can('gate5.verify');
    }

    #[Computed] public function canEditTranches(): bool
    {
        if ($this->isApplicant || ! $this->inGate) {
            return false;
        }

        return $this->canManage || ($this->cleared && auth()->user()->canAny(['gate5.verify', 'disburse.process']));
    }

    /** The enterprise confirms it was briefed (or the PMU records it on its behalf) */
    #[Computed] public function canConfirmOnboarding(): bool
    {
        $r = $this->review;

        return $this->inGate && $r && $r->approval_sent_at && ! $r->onboarding_confirmed_at
            && ($this->isApplicant || auth()->user()->can('gate5.verify'));
    }

    #[Computed] public function blockers(): array
    {
        return $this->review?->clearanceBlockers() ?? [];
    }

    #[Computed] public function controls(): array
    {
        return $this->review?->controls() ?? [];
    }

    /** Investors from the enterprise profile, with the latest proof recorded for each */
    #[Computed] public function profileInvestors(): array
    {
        $rows = collect($this->profile?->investorRows() ?? [])->map(fn ($r) => [
            'name'    => trim((string) $r['name']),
            'country' => $r['country'] ?? null,
            'amount'  => is_numeric($r['ticket'] ?? null) ? (float) $r['ticket'] : null,
        ]);

        if ($rows->isEmpty() && filled($this->eoi->investor_name)) {
            $rows = collect([['name' => trim($this->eoi->investor_name), 'country' => null,
                'amount' => $this->review?->private_amount !== null ? (float) $this->review->private_amount : null]]);
        }

        $deployments = $this->review?->deployments ?? collect();

        return $rows->values()->map(fn ($row) => $row + [
            'deployment' => $deployments->filter(fn ($d) => strcasecmp(trim($d->investor), $row['name']) === 0)->sortByDesc('id')->first(),
        ])->all();
    }

    /* =====================================================================
     | Load
     ===================================================================== */

    protected function load(): void
    {
        $r = ConditionReview::with('conditions', 'agreements', 'tranches', 'deployments')->where('expression_of_interest_id', $this->eoiId)->first();

        $this->reviewId = $r?->id;
        if (! $r) {
            return;
        }

        $this->conds = $r->conditions->map(fn ($c) => [
            'id' => $c->id, 'area' => $c->area, 'finding' => $c->finding, 'evidence_required' => (string) $c->evidence_required,
            'responsible' => $c->responsible ?? 'enterprise', 'verifier' => $c->verifier ?? 'pmu', 'timing' => $c->timing ?? 'before_disbursement',
            'status' => $c->status, 'note' => (string) $c->note, 'corrective_action' => (string) $c->corrective_action, 'source' => $c->source,
        ])->values()->all();

        $this->agreements = $r->agreements->map(fn ($a) => [
            'id' => $a->id, 'kind' => $a->kind, 'title' => $a->title, 'parties' => (string) $a->parties,
            'required' => (bool) $a->required, 'status' => $a->status, 'signed_on' => $a->signed_on?->toDateString(),
        ])->values()->all();

        $v = (array) ($r->verification ?? []);
        $this->verification = collect(ConditionReview::VERIFICATION_AREAS)->mapWithKeys(fn ($a, $k) => [$k => [
            'status' => $v[$k]['status'] ?? null, 'note' => (string) ($v[$k]['note'] ?? ''),
        ]])->all();

        $b = (array) ($r->bank ?? []);
        $this->bank = collect(['bank', 'branch', 'account_name', 'account_number', 'account_type'])
            ->mapWithKeys(fn ($k) => [$k => (string) ($b[$k] ?? '')])->all();

        $this->readiness = collect(ConditionReview::READINESS_CHECKS)->mapWithKeys(fn ($t, $k) => [$k => (bool) ($r->readiness_checks[$k] ?? false)])->all();

        $this->budget = collect($r->budget ?? [])->map(fn ($x) => [
            'category' => (string) ($x['category'] ?? ''),
            'private'  => is_numeric($x['private'] ?? null) ? (float) $x['private'] : null,
            'grant'    => is_numeric($x['grant'] ?? null) ? (float) $x['grant'] : null,
        ])->values()->all();

        $o = (array) ($r->onboarding ?? []);
        $this->onboarding = [
            'contact'         => (string) ($o['contact'] ?? ''),
            'frequency'       => $o['frequency'] ?? 'quarterly',
            'first_report_on' => $o['first_report_on'] ?? null,
            'items'           => collect(ConditionReview::ONBOARDING_ITEMS)->mapWithKeys(fn ($l, $k) => [$k => (bool) ($o['items'][$k] ?? false)])->all(),
        ];
        $bl = (array) ($r->baseline ?? []);
        $this->baseline = collect(ConditionReview::BASELINE_FIELDS)->mapWithKeys(fn ($l, $k) => [$k => $bl[$k] ?? null])->all()
            + ['as_of' => $bl['as_of'] ?? now()->toDateString()];

        $this->tranches = $r->tranches->map(fn ($t) => [
            'id' => $t->id, 'label' => $t->label, 'amount' => $t->amount !== null ? (float) $t->amount : null, 'trigger' => (string) $t->trigger,
        ])->values()->all();

        $dep = [];
        foreach ($r->deployments->where('status', 'submitted') as $d) {
            $dep[$d->id] = [
                'checks'           => collect(ConditionReview::DEPLOYMENT_CHECKS)->mapWithKeys(fn ($l, $k) => [$k => (bool) ($this->dep[$d->id]['checks'][$k] ?? $d->checks[$k] ?? false)])->all(),
                'received_account' => (string) ($this->dep[$d->id]['received_account'] ?? $d->received_account ?? ($b['account_number'] ?? '')),
                'tranche'          => $this->dep[$d->id]['tranche'] ?? $d->disbursement_tranche_id,
            ];
        }
        $this->dep = $dep;

        $this->decision = [
            'outcome'  => $r->outcome,
            'comments' => (string) $r->outcome_comments,
            'on'       => $r->outcome_on?->toDateString() ?? now()->toDateString(),
        ];

        $release = [];
        foreach ($r->tranches->whereNull('released_on') as $t) {
            $release[$t->id] = [
                'instruction_ref' => (string) ($this->release[$t->id]['instruction_ref'] ?? $t->instruction_ref ?? ''),
                'instruction_on'  => (string) (($this->release[$t->id]['instruction_on'] ?? '') ?: ($t->instruction_on?->toDateString() ?? now()->toDateString())),
                'reference'       => (string) ($this->release[$t->id]['reference'] ?? ''),
                'on'              => (string) (($this->release[$t->id]['on'] ?? '') ?: now()->toDateString()),
            ];
        }
        $this->release = $release;

        unset($this->review, $this->blockers, $this->cleared, $this->controls, $this->profileInvestors);
    }

    /* =====================================================================
     | Autosave (PMU)
     ===================================================================== */

    public function updated(string $name): void
    {
        if (Str::startsWith($name, 'tranches')) {
            $this->canEditTranches ? $this->syncTranches() : $this->load();
            $this->saved();
            return;
        }
        if (! Str::startsWith($name, ['conds', 'agreements', 'verification', 'bank', 'readiness', 'budget', 'onboarding', 'baseline'])) {
            return;
        }
        $isAgreement = Str::startsWith($name, 'agreements');
        if (! ($isAgreement ? $this->canManageAgreements : $this->canManage)) {
            $this->load();
            return;
        }

        $r = ConditionReview::findOrFail($this->reviewId);

        match (true) {
            Str::startsWith($name, 'conds')      => $this->syncConditions(),
            Str::startsWith($name, 'agreements') => $this->syncAgreements(),
            Str::startsWith($name, 'bank')       => $this->syncBank($r),
            default                              => $r->update([
                'verification'     => collect($this->verification)->map(fn ($v) => [
                    'status' => isset(ConditionReview::VERIFY_STATUSES[$v['status'] ?? '']) ? $v['status'] : null,
                    'note'   => Str::limit(trim((string) $v['note']), 500, ''),
                ])->all(),
                'readiness_checks' => $this->readiness,
                'budget'           => collect($this->budget)->map(fn ($b) => [
                    'category' => Str::limit(trim((string) $b['category']), 100, ''),
                    'private'  => is_numeric($b['private'] ?? null) ? max(0, (float) $b['private']) : null,
                    'grant'    => is_numeric($b['grant'] ?? null) ? max(0, (float) $b['grant']) : null,
                ])->values()->all(),
                'onboarding'       => [
                    'contact'         => Str::limit(trim($this->onboarding['contact']), 250, ''),
                    'frequency'       => array_key_exists($this->onboarding['frequency'], ConditionReview::FREQUENCIES) ? $this->onboarding['frequency'] : 'quarterly',
                    'first_report_on' => $this->onboarding['first_report_on'] ?: null,
                    'items'           => $this->onboarding['items'],
                ],
                'baseline'         => collect($this->baseline)->map(fn ($v, $k) => $k === 'as_of' ? ($v ?: null) : (is_numeric($v) ? max(0, (float) $v) : null))->all(),
            ]),
        };

        // Anything that feeds the readiness confirmation changed → the confirmations must be given again (not after clearance)
        if (! $this->cleared && Str::startsWith($name, ['conds', 'agreements', 'verification', 'bank', 'readiness', 'budget'])) {
            $this->resetConfirmations();
        }

        $this->saved();
    }

    protected function resetConfirmations(): void
    {
        ConditionReview::whereKey($this->reviewId)->whereNotNull('readiness_pmu_at')
            ->update(['readiness_pmu_at' => null, 'readiness_pmu_by' => null, 'readiness_finance_at' => null, 'readiness_finance_by' => null]);
    }

    protected function syncConditions(): void
    {
        foreach ($this->conds as $row) {
            CpCondition::where('condition_review_id', $this->reviewId)->whereKey($row['id'])->update([
                'area'              => array_key_exists($row['area'], ConditionReview::AREAS) || array_key_exists($row['area'], ConditionReview::LEGACY_AREAS) ? $row['area'] : 'contractual',
                'finding'           => Str::limit(trim((string) $row['finding']), 250, '') ?: '—',
                'evidence_required' => Str::limit(trim((string) $row['evidence_required']), 250, '') ?: null,
                'responsible'       => array_key_exists($row['responsible'], ConditionReview::PARTIES) ? $row['responsible'] : 'enterprise',
                'verifier'          => array_key_exists($row['verifier'], ConditionReview::PARTIES) ? $row['verifier'] : 'pmu',
                'timing'            => array_key_exists($row['timing'], ConditionReview::TIMINGS) ? $row['timing'] : 'before_disbursement',
                'note'              => Str::limit(trim((string) $row['note']), 500, '') ?: null,
                'corrective_action' => Str::limit(trim((string) $row['corrective_action']), 500, '') ?: null,
            ]);
        }
    }

    protected function syncAgreements(): void
    {
        foreach ($this->agreements as $i => $row) {
            $a = CpAgreement::where('condition_review_id', $this->reviewId)->findOrFail($row['id']);

            if ($row['status'] === 'executed' && (! $a->file_path || blank($row['signed_on']))) {
                $this->agreements[$i]['status'] = $a->status;
                $this->addError("agreements.$i.status", 'Upload the signed copy and enter the signing date first.');
                $row['status'] = $a->status;
            }

            $a->update([
                'title'     => Str::limit(trim((string) $row['title']), 150, '') ?: $a->title,
                'parties'   => Str::limit(trim((string) $row['parties']), 250, '') ?: null,
                'required'  => (bool) $row['required'],
                'status'    => array_key_exists($row['status'], ConditionReview::AGREEMENT_STATUSES) ? $row['status'] : 'drafting',
                'signed_on' => $row['signed_on'] ?: null,
            ]);
        }
    }

    /** A change against the Gate 4 details must be independently revalidated before payment */
    protected function syncBank(ConditionReview $r): void
    {
        $g4      = (array) ($this->gate4Bank ?? []);
        $norm    = fn ($v) => preg_replace('/\s+/', '', Str::lower((string) $v));
        $changed = $g4 && collect(['bank', 'account_name', 'account_number'])->contains(fn ($k) => $norm($this->bank[$k] ?? '') !== $norm($g4[$k] ?? ''));

        $r->update([
            'bank'                   => collect($this->bank)->map(fn ($v) => Str::limit(trim((string) $v), 150, ''))->all() + ['source' => $g4 ? 'gate4' : 'gate6', 'changed' => $changed],
            'bank_revalidated_at'    => null,
            'bank_revalidated_by'    => null,
            'bank_revalidation_note' => null,
        ]);
    }

    public function revalidateBank(): void
    {
        abort_unless($this->canManage, 403);
        $this->validate(['bankNote' => ['required', 'string', 'max:500']], ['bankNote.required' => 'Record how the new account details were independently revalidated (who, how, which document).']);

        ConditionReview::whereKey($this->reviewId)->update([
            'bank_revalidated_at' => now(), 'bank_revalidated_by' => auth()->id(), 'bank_revalidation_note' => trim($this->bankNote),
        ]);
        $this->reset('bankNote');
        $this->resetConfirmations();
        $this->saved();
        $this->dispatch('notify', type: 'success', message: 'Bank account revalidation recorded.');
    }

    public function reloadBudgetFromProfile(): void
    {
        abort_unless($this->canManage && $this->profile?->grant_budget, 403);
        ConditionReview::whereKey($this->reviewId)->update(['budget' => json_encode($this->profile->budgetRows())]);
        $this->resetConfirmations();
        $this->load();
        $this->editBudget = false;
        $this->saved();
    }

    /* =====================================================================
     | Step 1 · approval communication & CP checklist
     ===================================================================== */

    public function sendApproval(): void
    {
        abort_unless($this->canManage, 403);

        $agr = CpAgreement::where('condition_review_id', $this->reviewId)->where('kind', 'grant')->first();
        if (! $agr?->file_path || $agr->status !== 'executed') {
            $this->addError('approval', 'The executed Grant Agreement (signed by both the MD and the PS) must be on file first.');
            return;
        }
        if (blank($this->onboarding['contact'])) {
            $this->addError('approval', 'Name the programme contact person for the enterprise first.');
            return;
        }

        $eoi  = ExpressionOfInterest::with('user')->find($this->eoiId);
        $user = $eoi?->user;
        if (! $user || blank($user->email)) {
            $this->addError('approval', 'The applicant has no e-mail address.');
            return;
        }
        try {
            $user->notify(new GrantApprovalNotice($eoi, ConditionReview::findOrFail($this->reviewId)));
        } catch (\Throwable $e) {
            report($e);
            $this->addError('approval', 'The e-mail could not be sent — try again.'.NotifyRecipients::debugReason($e));
            return;
        }

        ConditionReview::whereKey($this->reviewId)->update(['approval_sent_at' => now(), 'approval_sent_by' => auth()->id()]);
        $this->saved();
        $this->dispatch('notify', type: 'success', message: 'Grant approval, executed agreement and conditions checklist e-mailed to '.$user->email.'.');
    }

    public function addCondition(): void
    {
        abort_unless($this->canManage, 403);
        $area = array_key_exists($this->newArea, ConditionReview::AREAS) ? $this->newArea : 'contractual';

        CpCondition::create([
            'condition_review_id' => $this->reviewId,
            'area'     => $area,
            'finding'  => 'New requirement',
            'timing'   => $area === 'reporting' ? 'ongoing' : 'before_disbursement',
            'source'   => 'g6',
            'position' => count($this->conds),
        ]);
        $this->resetConfirmations();
        $this->load();
        $this->saved();
    }

    public function removeCondition(int $id): void
    {
        abort_unless($this->canManage, 403);
        $c = CpCondition::where('condition_review_id', $this->reviewId)->findOrFail($id);
        abort_unless(in_array($c->source, ['g6', 'g5'], true), 403, 'Conditions from the approval record can be waived, not removed.');
        $c->delete();
        $this->load();
        $this->saved();
    }

    /** outstanding | met | not_met | waived (PMU) */
    public function setConditionStatus(int $id, string $status): void
    {
        abort_unless($this->canManage && array_key_exists($status, ConditionReview::CONDITION_STATUSES), 403);

        $i = collect($this->conds)->search(fn ($c) => $c['id'] === $id);
        $c = CpCondition::where('condition_review_id', $this->reviewId)->findOrFail($id);

        $fail = fn (string $msg) => $this->addError("conds.$i.status", $msg);
        if ($status === 'met' && ! $c->evidence_path && blank($c->note)) {
            $fail('Verification is based on documentary evidence — upload it or note the document reviewed.');
            return;
        }
        if ($status === 'waived' && blank($c->note)) {
            $fail('Explain why it is waived or does not apply.');
            return;
        }
        if ($status === 'not_met' && blank($c->corrective_action)) {
            $fail('Specify the corrective action required (and the responsible party) before marking it not satisfied.');
            return;
        }

        $c->update([
            'status'      => $status,
            'verified_by' => $status === 'outstanding' ? null : auth()->id(),
            'verified_at' => $status === 'outstanding' ? null : now(),
        ]);
        $this->resetConfirmations();
        $this->load();
        $this->saved();
    }

    public function updatedEvidenceFiles($file, $key): void
    {
        abort_unless($this->canUploadEvidence, 403);

        $c = CpCondition::where('condition_review_id', $this->reviewId)->findOrFail((int) $key);
        abort_if($c->isDone(), 403, 'This condition is already closed.');
        abort_if($this->isApplicant && ! in_array($c->responsible, ['enterprise', 'investor'], true), 403, 'The LEHSFF team provides the evidence for this item.');

        $this->validate(
            ["evidenceFiles.$key" => ['file', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx,zip', 'max:20480']],
            ["evidenceFiles.$key.max" => 'Files must be 20 MB or smaller.', "evidenceFiles.$key.mimes" => 'Upload a PDF, image, Word, Excel or ZIP file.'],
        );

        if ($c->evidence_path) {
            Storage::disk(ConditionReview::DISK)->delete($c->evidence_path);
        }
        $c->update([
            'evidence_path' => $file->store("gate5/{$this->reviewId}/conditions", ConditionReview::DISK),
            'evidence_name' => $file->getClientOriginalName(),
            'evidence_size' => $file->getSize(),
            'evidence_by'   => auth()->id(),
            'evidence_at'   => now(),
            'status'        => $c->status === 'not_met' ? 'outstanding' : $c->status,   // resubmitted → verify again
        ]);

        unset($this->evidenceFiles[$key]);
        $this->load();

        if ($this->isApplicant) {
            $this->staffAlert(NotifyRecipients::withPermission('gate5.verify'), 'evidence_uploaded', 'the PMU', ['condition' => $c->finding, 'file' => $c->evidence_name]);
        }
        $this->dispatch('notify', type: 'success', message: 'Evidence uploaded'.($this->isApplicant ? ' — the LEHSFF team will verify it.' : '.'));
    }

    /* ---------------- Agreements ---------------- */

    public function addAgreement(): void
    {
        abort_unless($this->canManageAgreements, 403);
        CpAgreement::create(['condition_review_id' => $this->reviewId, 'kind' => 'other', 'title' => 'Other agreement', 'required' => false]);
        $this->load();
        $this->saved();
    }

    public function removeAgreement(int $id): void
    {
        abort_unless($this->canManageAgreements, 403);
        $a = CpAgreement::where('condition_review_id', $this->reviewId)->findOrFail($id);
        abort_unless($a->kind === 'other', 403, 'The Grant and Investment Agreements can be marked not required, but not removed.');
        $a->delete();
        $this->load();
        $this->saved();
    }

    public function updatedAgreementFiles($file, $key): void
    {
        abort_unless($this->canManageAgreements, 403);
        $this->validate(
            ["agreementFiles.$key" => ['file', 'mimes:pdf,doc,docx', 'max:20480']],
            ["agreementFiles.$key.mimes" => 'Upload a PDF or Word file.', "agreementFiles.$key.max" => 'Files must be 20 MB or smaller.'],
        );

        $a = CpAgreement::where('condition_review_id', $this->reviewId)->findOrFail((int) $key);
        if ($a->file_path) {
            Storage::disk(ConditionReview::DISK)->delete($a->file_path);
        }
        $a->update([
            'file_path'   => $file->store("gate5/{$this->reviewId}/agreements", ConditionReview::DISK),
            'file_name'   => $file->getClientOriginalName(),
            'file_size'   => $file->getSize(),
            'uploaded_by' => auth()->id(),
        ]);

        unset($this->agreementFiles[$key]);
        if (! $this->cleared) {
            $this->resetConfirmations();
        }
        $this->load();
        $this->dispatch('notify', type: 'success', message: $a->title.' uploaded.');
    }

    /* =====================================================================
     | Step 3 · readiness confirmations & outcome
     ===================================================================== */

    public function confirmProgramme(): void
    {
        abort_unless($this->canManage, 403);

        $r = ConditionReview::with('conditions', 'agreements', 'deployments')->findOrFail($this->reviewId);
        $pending = $r->blockersBeforeConfirmation();
        if ($pending) {
            $this->addError('readiness', 'Not ready to confirm — '.count($pending).' item(s) still open (see the list).');
            return;
        }

        $r->update(['readiness_pmu_at' => now(), 'readiness_pmu_by' => auth()->id(), 'readiness_finance_at' => null, 'readiness_finance_by' => null]);
        $note = $this->staffAlert(NotifyRecipients::finance(), 'readiness_requested', 'Finance');
        $this->saved();
        $this->dispatch('notify', type: 'success', message: 'Programme confirmation recorded — Finance asked to confirm. '.$note);
    }

    public function confirmFinance(): void
    {
        abort_unless($this->isFinance && ! $this->cleared, 403);

        $r = ConditionReview::findOrFail($this->reviewId);
        if (! $r->readiness_pmu_at) {
            $this->addError('readiness', 'The programme team confirms readiness first.');
            return;
        }
        abort_if($r->readiness_pmu_by === auth()->id(), 403, 'A different person must give the Finance confirmation.');

        $r->update(['readiness_finance_at' => now(), 'readiness_finance_by' => auth()->id()]);
        $note = $this->staffAlert(NotifyRecipients::withPermission('gate5.verify'), 'readiness_requested', 'the PMU', ['finance_confirmed' => true]);
        $this->saved();
        $this->dispatch('notify', type: 'success', message: 'Finance confirmation recorded — the programme team can now record "Cleared for disbursement".');
    }

    public function recordOutcome(): void
    {
        abort_unless($this->canManage, 403);

        $this->validate([
            'decision.outcome'  => ['required', Rule::in(ConditionReview::OUTCOME_CHOICES)],
            'decision.comments' => [($this->decision['outcome'] ?? null) === ConditionReview::OUTCOME_CLEARED ? 'nullable' : 'required', 'string', 'max:3000'],
            'decision.on'       => ['required', 'date', 'before_or_equal:today'],
        ], [
            'decision.outcome.required'  => 'Choose the outcome.',
            'decision.comments.required' => 'Say what is outstanding or must be corrected, who is responsible and by when.',
        ]);

        $outcome = $this->decision['outcome'];
        if ($outcome === ConditionReview::OUTCOME_CLEARED && $this->blockers) {
            $this->addError('decision.outcome', 'Not ready to clear for disbursement — see the list above.');
            return;
        }

        DB::transaction(function () use ($outcome) {
            $r = ConditionReview::findOrFail($this->reviewId);
            $r->update([
                'outcome'          => $outcome,
                'outcome_comments' => trim($this->decision['comments']) ?: null,
                'outcome_on'       => $this->decision['on'],
                'decided_by'       => auth()->id(),
                'decided_at'       => now(),
                'cleared_at'       => $outcome === ConditionReview::OUTCOME_CLEARED ? now() : null,
                'history'          => array_merge($r->history ?? [], [[
                    'outcome' => $outcome, 'comments' => trim($this->decision['comments']) ?: null,
                    'on' => $this->decision['on'], 'by' => auth()->user()->name, 'at' => now()->toDateTimeString(),
                ]]),
            ]);
            // Returned / pending: confirmations are given again after re-verification
            if ($outcome !== ConditionReview::OUTCOME_CLEARED) {
                $r->update(['readiness_pmu_at' => null, 'readiness_pmu_by' => null, 'readiness_finance_at' => null, 'readiness_finance_by' => null]);
            }
            $this->eoi->update(['status' => ConditionReview::OUTCOME_TO_EOI_STATUS[$outcome]]);
        });

        $this->refreshFlags();

        $notes = [$this->emailApplicant(fn ($eoi, $r) => new Gate5Outcome($eoi, $r))];
        if ($outcome === ConditionReview::OUTCOME_CLEARED) {
            $notes[] = $this->staffAlert(
                NotifyRecipients::finance()->merge(NotifyRecipients::anyOf(['gate5.verify', 'disburse.approve'])), 'cleared', 'Finance, the PMU and payment approvers');
        }

        $this->dispatch('gate-decided', gate: 'g5', decision: $outcome);
        $this->dispatch('notify', type: 'success', message: 'Gate 6 outcome recorded: '.ConditionReview::OUTCOMES[$outcome][0].'. '.implode(' ', array_filter($notes)));
    }

    /* =====================================================================
     | Step 4 · onboarding
     ===================================================================== */

    public function sendSummary(): void
    {
        abort_unless($this->canManage || (! $this->isApplicant && auth()->user()->can('gate5.verify')), 403);

        if (blank($this->onboarding['contact']) || in_array(false, $this->onboarding['items'], true)) {
            $this->addError('onboarding.contact', 'Name the contact person and cover every onboarding item before sending the summary.');
            return;
        }

        $eoi  = ExpressionOfInterest::with('user')->find($this->eoiId);
        $user = $eoi?->user;
        if (! $user || blank($user->email)) {
            $this->addError('onboarding.contact', 'The applicant has no e-mail address.');
            return;
        }
        try {
            $user->notify(new ObligationsSummary($eoi, ConditionReview::findOrFail($this->reviewId)));
        } catch (\Throwable $e) {
            report($e);
            $this->addError('onboarding.contact', 'The summary could not be e-mailed — try again.'.NotifyRecipients::debugReason($e));
            return;
        }

        ConditionReview::whereKey($this->reviewId)->update(['summary_sent_at' => now(), 'summary_sent_by' => auth()->id()]);
        $this->saved();
        $this->dispatch('notify', type: 'success', message: 'Summary of obligations e-mailed to '.$user->email.'.');
    }

    /** Enterprise: "I have been briefed" — or the PMU records the briefing on its behalf */
    public function confirmOnboarding(): void
    {
        abort_unless($this->canConfirmOnboarding, 403);

        ConditionReview::whereKey($this->reviewId)->update(['onboarding_confirmed_at' => now(), 'onboarding_confirmed_by' => auth()->id()]);
        $this->saved();
        $this->dispatch('notify', type: 'success', message: $this->isApplicant ? 'Thank you — your confirmation has been recorded.' : 'Briefing recorded.');
    }

    /* =====================================================================
     | Gate 7 · matching investment
     ===================================================================== */

    public function updatedInvestorFiles($file, $key): void
    {
        abort_unless($this->canRecordCapital, 403);

        $i   = (int) $key;
        $row = $this->profileInvestors[$i] ?? null;
        abort_unless($row, 404);

        $this->validate(
            ["investorFiles.$i" => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:20480']],
            ["investorFiles.$i.mimes" => 'Upload a PDF or a scan / photo (JPG, PNG).', "investorFiles.$i.max" => 'The file must be 20 MB or smaller.'],
        );
        if (! $row['amount'] || $row['amount'] <= 0) {
            $this->addError("investorFiles.$i", 'This investor has no amount on your enterprise profile — add it under "Investors in the round" first.');
            return;
        }
        if (($row['deployment']?->status ?? null) === 'submitted') {
            $this->addError("investorFiles.$i", 'Proof for this investor is waiting for verification.');
            return;
        }

        $d = CapitalDeployment::create([
            'condition_review_id' => $this->reviewId,
            'investor'            => $row['name'],
            'amount'              => $row['amount'],
            'deployed_on'         => now()->toDateString(),
            'evidence_path'       => $file->store("gate5/{$this->reviewId}/capital", ConditionReview::DISK),
            'evidence_name'       => $file->getClientOriginalName(),
            'evidence_size'       => $file->getSize(),
            'recorded_by'         => auth()->id(),
        ]);

        unset($this->investorFiles[$i]);
        $this->load();
        $this->staffAlert(NotifyRecipients::withPermission('capital.verify'), 'capital_recorded', 'the PMU',
            ['investor' => $d->investor, 'amount' => $d->amount, 'on' => $d->deployed_on?->format('d M Y')]);
        $this->dispatch('notify', type: 'success', message: 'Proof of investment for '.$d->investor.' attached — the LEHSFF team will verify it.');
    }

    /** §3.8.2 — amount, date, investor, account, structure confirmed; optionally the tranche it backs */
    public function verifyDeployment(int $id): void
    {
        abort_unless($this->canVerifyCapital, 403);

        $d = CapitalDeployment::where('condition_review_id', $this->reviewId)->findOrFail($id);
        abort_unless($d->status === 'submitted', 403, 'This deployment has already been reviewed.');

        $row = $this->dep[$id] ?? [];
        if (in_array(false, $row['checks'] ?? [false], true) || blank($row['received_account'] ?? null)) {
            $this->addError("dep.$id", 'Confirm every check and record the account the investment was received into.');
            return;
        }
        $tranche = filled($row['tranche'] ?? null)
            ? DisbursementTranche::where('condition_review_id', $this->reviewId)->whereKey((int) $row['tranche'])->value('id') : null;

        $d->update([
            'status' => 'verified', 'verified_by' => auth()->id(), 'verified_at' => now(), 'note' => null,
            'checks' => $row['checks'], 'received_account' => Str::limit(trim($row['received_account']), 150, ''),
            'disbursement_tranche_id' => $tranche,
        ]);

        $this->load();
        $note = $this->emailApplicant(fn ($eoi, $r) => new CapitalDeploymentReviewed($eoi, $r, $d->fresh()));
        $this->dispatch('notify', type: 'success', message: 'Investment verified. '.$note);
    }

    public function rejectDeployment(int $id): void
    {
        abort_unless($this->canVerifyCapital, 403);
        $this->validate(["rejectNote.$id" => ['required', 'string', 'max:500']], ["rejectNote.$id.required" => 'Say why it is not accepted.']);

        $d = CapitalDeployment::where('condition_review_id', $this->reviewId)->findOrFail($id);
        abort_unless($d->status === 'submitted', 403);
        $d->update(['status' => 'rejected', 'verified_by' => auth()->id(), 'verified_at' => now(), 'note' => trim($this->rejectNote[$id])]);

        unset($this->rejectNote[$id]);
        $this->load();
        $note = $this->emailApplicant(fn ($eoi, $r) => new CapitalDeploymentReviewed($eoi, $r, $d->fresh()));
        $this->dispatch('notify', type: 'success', message: 'Marked as not accepted. '.$note);
    }

    public function removeDeployment(int $id): void
    {
        $d = CapitalDeployment::where('condition_review_id', $this->reviewId)->findOrFail($id);
        abort_unless($this->isApplicant && $d->status !== 'verified' && $d->recorded_by === auth()->id(), 403);
        $d->delete();
        $this->load();
    }

    /* =====================================================================
     | Gate 7 · tranches & payments
     ===================================================================== */

    protected function unscheduled(?int $exceptId = null): float
    {
        $grant = (float) ConditionReview::whereKey($this->reviewId)->value('grant_amount');
        $used  = (float) DisbursementTranche::where('condition_review_id', $this->reviewId)
            ->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))->sum('amount');

        return max(0, $grant - $used);
    }

    protected function syncTranches(): void
    {
        $this->resetErrorBag('tranches');

        foreach ($this->tranches as $i => $row) {
            $t = DisbursementTranche::where('condition_review_id', $this->reviewId)->find($row['id']);
            if (! $t || $t->isReleased() || $t->requested_at) {
                continue;   // requested / approved / released tranches can't change
            }
            $amount = is_numeric($row['amount'] ?? null) ? max(0, (float) $row['amount']) : null;
            $room   = $this->unscheduled($row['id']);
            if ($amount !== null && $amount > $room + 0.01) {
                $this->addError("tranches.$i.amount", 'Only M '.number_format($room).' of the grant is left — the amount was capped.');
                $amount = $room;
                $this->tranches[$i]['amount'] = $room;
            }
            $t->update([
                'position' => $i,
                'label'    => Str::limit(trim((string) $row['label']), 20, '') ?: 'T'.($i + 1),
                'amount'   => $amount,
                'trigger'  => Str::limit(trim((string) $row['trigger']), 250, '') ?: null,
            ]);
        }
        unset($this->review);
    }

    public function addTranche(): void
    {
        abort_unless($this->canEditTranches, 403);
        $left = $this->unscheduled();
        if ($left <= 0.01) {
            $this->dispatch('notify', type: 'warning', message: 'The whole grant is already in tranches.');
            return;
        }
        DisbursementTranche::create([
            'condition_review_id' => $this->reviewId,
            'position'            => count($this->tranches),
            'label'               => 'T'.(count($this->tranches) + 1),
            'amount'              => $left,
            'trigger'             => count($this->tranches) === 0 ? 'Conditions precedent met and matching investment verified' : null,
        ]);
        $this->load();
        $this->saved();
    }

    public function removeTranche(int $id): void
    {
        abort_unless($this->canEditTranches, 403);
        DisbursementTranche::where('condition_review_id', $this->reviewId)->whereNull('released_on')->whereNull('requested_at')->findOrFail($id)->delete();
        $this->load();
        $this->saved();
    }

    /** PMU: matching investment verified for this tranche → request payment approval */
    public function requestPayment(int $id): void
    {
        abort_unless($this->canRequestPayment, 403);

        $r = $this->review;
        $t = $r->tranches->firstWhere('id', $id);
        abort_unless($t && ! $t->isReleased() && ! $t->approved_at, 404);

        $fail = fn (string $msg) => [$this->addError("pay.$id", $msg), $this->dispatch('notify', type: 'error', message: $msg)];
        $earlier = $r->tranches->where('position', '<', $t->position)->reject->isReleased();
        if ($earlier->isNotEmpty()) {
            $fail('Release '.$earlier->pluck('label')->implode(', ').' first.');
            return;
        }
        if ((float) $t->amount <= 0) {
            $fail('Enter an amount for '.$t->label.' first.');
            return;
        }
        $mt = $r->matchingFor($t);
        if (! $mt['ok']) {
            $fail('Matching rule: M '.number_format($mt['after']).' of grant needs at least M '.number_format($mt['needed'])
                .' of verified private capital — only M '.number_format($mt['verified']).' is verified.');
            return;
        }
        $unsigned = $r->agreements->filter(fn ($a) => $a->required && $a->status !== 'executed');
        if ($unsigned->isNotEmpty()) {
            $fail('Not executed yet: '.$unsigned->pluck('title')->implode(', ').' — upload the signed copy and mark it Executed (or untick "Required before disbursement").');
            return;
        }
        $c = $this->controls;
        if ($mt['after'] > (float) $r->grant_amount + 0.01) {
            $fail('This would exceed the approved grant.');
            return;
        }
        if ($c['enterprise_approved'] > $c['lifetime_cap'] + 1) {
            $fail('The enterprise is over its lifetime CAFI grant limit — escalate.');
            return;
        }

        $t->update(['requested_by' => auth()->id(), 'requested_at' => now(), 'capital_verified' => $mt['verified'], 'outcome' => null, 'outcome_note' => null]);

        $note = $this->staffAlert(NotifyRecipients::withPermission('disburse.approve'), 'payment_requested', 'the payment approvers',
            ['tranche' => $t->label, 'amount' => $t->amount, 'capital' => $mt['verified'], 'allowed' => $mt['allowed']]);
        $this->load();
        $this->dispatch('notify', type: 'success', message: "Payment approval requested for {$t->label}. {$note}");
    }

    /** Payment approval (disburse.approve) — a different person from the one who requested it */
    public function approvePayment(int $id): void
    {
        abort_unless($this->canApprovePayment, 403);

        $r = $this->review;
        $t = $r->tranches->firstWhere('id', $id);
        abort_unless($t && $t->requested_at && ! $t->approved_at && ! $t->isReleased(), 404);
        abort_if($t->requested_by === auth()->id(), 403, 'The payment must be approved by someone other than the person who requested it.');

        $mt = $r->matchingFor($t);
        if (! $mt['ok']) {
            $this->addError("pay.$id", 'The matching calculation no longer holds — hold the payment.');
            return;
        }

        $partial = (float) $t->amount < $r->stillToDisburse() - 0.01;
        $t->update(['approved_by' => auth()->id(), 'approved_at' => now(), 'outcome' => $partial ? 'partial' : 'approved',
            'approval_note' => Str::limit(trim((string) ($this->payNote[$id] ?? '')), 500, '') ?: null]);

        $note = $this->staffAlert(NotifyRecipients::finance(), 'payment_approved', 'Finance', ['tranche' => $t->label, 'amount' => $t->amount]);
        unset($this->payNote[$id]);
        $this->load();
        $this->dispatch('notify', type: 'success', message: "{$t->label} approved for payment. {$note}");
    }

    /** Hold (pending verification) or stop (not eligible → escalated) a payment — approver or PMU */
    public function holdPayment(int $id, string $outcome): void
    {
        abort_unless(in_array($outcome, ['pending', 'not_eligible'], true) && ($this->canApprovePayment || $this->canRequestPayment), 403);
        $this->validate(["payNote.$id" => ['required', 'string', 'max:500']], ["payNote.$id.required" => 'Say what is missing or why the payment is not eligible.']);

        $t = DisbursementTranche::where('condition_review_id', $this->reviewId)->whereNull('released_on')->findOrFail($id);
        $t->update([
            'outcome' => $outcome, 'outcome_note' => trim($this->payNote[$id]),
            'requested_at' => null, 'requested_by' => null, 'approved_at' => null, 'approved_by' => null,
        ]);

        $note = '';
        if ($outcome === 'not_eligible') {
            DB::transaction(function () use ($t) {
                $r = ConditionReview::findOrFail($this->reviewId);
                $r->update(['history' => array_merge($r->history ?? [], [[
                    'outcome' => 'payment_not_eligible', 'comments' => $t->label.': '.$t->outcome_note,
                    'on' => now()->toDateString(), 'by' => auth()->user()->name, 'at' => now()->toDateTimeString(),
                ]])]);
                $this->eoi->update(['status' => ExpressionOfInterest::STATUS_ESCALATED]);
            });
            $note = $this->staffAlert(NotifyRecipients::anyOf(['gate5.verify', 'disburse.approve']), 'payment_stopped', 'the PMU and approvers', ['note' => $t->outcome_note]);
            $this->refreshFlags();
            $this->dispatch('gate-decided', gate: 'g5', decision: 'escalated');
        }

        unset($this->payNote[$id]);
        $this->load();
        $this->dispatch('notify', type: 'success', message: $t->label.': '.ConditionReview::TRANCHE_OUTCOMES[$outcome][0].'. '.$note);
    }

    /** Finance: payment instruction + confirmation of payment (proof) — only after approval */
    public function releaseTranche(int $id): void
    {
        abort_unless($this->isFinance && $this->cleared, 403);

        $r = $this->review;
        $t = $r->tranches->firstWhere('id', $id);
        abort_unless($t && ! $t->isReleased(), 404);

        if (! $t->approved_at) {
            $this->addError("release.$id.reference", 'This payment has not been approved yet.');
            return;
        }

        try {
            $this->validate([
                "release.$id.instruction_ref" => ['required', 'string', 'max:100'],
                "release.$id.instruction_on"  => ['required', 'date', 'before_or_equal:today'],
                "release.$id.reference"       => ['required', 'string', 'max:100'],
                "release.$id.on"              => ['required', 'date', 'before_or_equal:today', 'after_or_equal:release.'.$id.'.instruction_on'],
                "releaseProof.$id"            => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:20480'],
            ], [
                "release.$id.instruction_ref.required" => 'Enter the payment instruction reference.',
                "release.$id.reference.required"       => 'Enter the payment (bank) reference.',
                "release.$id.on.after_or_equal"        => 'The payment date can\'t be before the instruction.',
                "releaseProof.$id.required"            => 'Attach the confirmation of payment (bank confirmation / remittance advice).',
                "releaseProof.$id.mimes"               => 'Upload a PDF or a scan / photo.',
            ]);
        } catch (ValidationException $e) {
            $this->dispatch('notify', type: 'error', message: $t->label.' not released: '.collect($e->errors())->flatten()->implode(' '));
            throw $e;
        }

        $file = $this->releaseProof[$id];
        $t->update([
            'instruction_ref' => trim($this->release[$id]['instruction_ref']),
            'instruction_on'  => $this->release[$id]['instruction_on'],
            'reference'       => trim($this->release[$id]['reference']),
            'released_on'     => $this->release[$id]['on'],
            'released_by'     => auth()->id(),
            'proof_path'      => $file->store("gate5/{$this->reviewId}/tranches", ConditionReview::DISK),
            'proof_name'      => $file->getClientOriginalName(),
            'proof_size'      => $file->getSize(),
        ]);

        unset($this->release[$id], $this->releaseProof[$id]);
        $this->load();

        $notes = [$this->emailApplicant(fn ($eoi, $rv) => new TrancheReleased($eoi, $rv, $t->fresh()))];
        $notes[] = NotifyRecipients::send(NotifyRecipients::withPermission('gate5.verify'),
            new TrancheReleased(ExpressionOfInterest::find($this->eoiId), ConditionReview::findOrFail($this->reviewId), $t->fresh()), 'the PMU', except: auth()->id());

        $this->dispatch('gate-decided', gate: 'g5', decision: null);
        $this->dispatch('notify', type: 'success', message: "{$t->label} paid and recorded. ".implode(' ', array_filter($notes)));
    }

    /* =====================================================================
     | E-mails & internals
     ===================================================================== */

    protected function emailApplicant(callable $make): string
    {
        $eoi    = ExpressionOfInterest::with('user')->find($this->eoiId);
        $review = $this->reviewId ? ConditionReview::find($this->reviewId) : null;
        $user   = $eoi?->user;

        if (! $eoi || ! $review) {
            return '';
        }
        if (! $user || blank($user->email)) {
            return 'The applicant has no e-mail address — please let them know.';
        }
        try {
            $user->notify($make($eoi, $review));

            return 'The applicant was e-mailed ('.$user->email.').';
        } catch (\Throwable $e) {
            report($e);

            return 'The e-mail to the applicant could not be sent.'.NotifyRecipients::debugReason($e);
        }
    }

    protected function staffAlert($users, string $event, string $who, array $data = []): string
    {
        $eoi    = ExpressionOfInterest::find($this->eoiId);
        $review = $this->reviewId ? ConditionReview::find($this->reviewId) : null;

        return $eoi && $review
            ? NotifyRecipients::send(collect($users)->unique('id')->values(), new Gate5StaffAlert($eoi, $review, $event, $data), $who, except: auth()->id())
            : '';
    }

    protected function saved(): void
    {
        $this->savedAt = now()->format('H:i');
        unset($this->review, $this->blockers, $this->controls);
    }

    protected function refreshFlags(): void
    {
        unset($this->eoi, $this->review, $this->cleared, $this->inGate, $this->canManage, $this->canManageAgreements, $this->canEditTranches, $this->canUploadEvidence,
            $this->canRecordCapital, $this->canVerifyCapital, $this->isFinance, $this->canApprovePayment, $this->canRequestPayment,
            $this->canConfirmOnboarding, $this->blockers, $this->controls);
    }

    #[On('gate-decided')]
    public function refreshGate(): void
    {
        $this->refreshFlags();
    }
};
?>

<div>
@php
    $CR        = \App\Models\ConditionReview::class;
    $eoi       = $this->eoi;
    $r         = $this->review;
    $applicant = $this->isApplicant;
    $canManage = $this->canManage;
    $canAgr    = $this->canManageAgreements;
    $rate      = max((float) config('cif.usd_to_lsl', 18.00), 0.0001);
    $m         = fn ($v) => $v === null || $v === '' ? '—' : 'M '.number_format((float) $v);
    $u         = fn ($v) => $v === null || $v === '' ? '' : 'US$ '.number_format((float) $v / $rate);
    $file      = fn ($kind, $id) => route('gate5.file', [$kind, $id]);
@endphp

{{-- ======================================================================
     LOCKED
     ====================================================================== --}}
@if (! $this->gateOpen)
    <section class="section-card">
        <div class="section-card-head">
            <span class="section-no">G6</span>
            <h5 class="me-auto">Conditions precedent, onboarding &amp; disbursement</h5>
            <span class="pill pill-muted"><i class="bi bi-lock-fill"></i> Locked</span>
        </div>
        <p class="small text-muted mb-0">Opens once the Grant Agreement is signed by both the CAFI Managing Director and the MTIBD Principal Secretary (Gate 5).
            Formal approval does not authorise payment: the conditions precedent are verified first (Gate 6), and the grant is then released against verified private investment (Gate 7).</p>
    </section>
@else
@php
    $s         = $r->summary();
    $grant     = (float) $r->grant_amount;
    $private   = (float) $r->private_amount;
    $verified  = $s['capital'];
    $outcome   = $r->outcome;
    $blockers  = $applicant ? [] : $this->blockers;
    $released  = $r->tranches->filter->isReleased();
    $budgetT   = $r->budgetTotals();
    $controls  = $applicant ? [] : $this->controls;
    $gate4Bank = $applicant ? null : $this->gate4Bank;
    $agrGrant  = $r->agreements->firstWhere('kind', 'grant');
    $partyL    = fn ($k) => $CR::PARTIES[$k] ?? ucfirst((string) $k);

    $st = fn (bool $done, bool $started = true) => $done ? 'done' : ($started ? 'current' : 'todo');
    $path = [
        ['Gate 5 · Grant Agreement signed', 'done'],
        ['1 · Approval & checklist', $st((bool) $r->approval_sent_at)],
        ['2 · Verify conditions', $st($s['conditions_done'] === $s['conditions_total'] && $s['verified_areas'] === count($CR::VERIFICATION_AREAS), (bool) $r->approval_sent_at)],
        ['3 · Readiness', $st($r->isCleared(), (bool) $r->readiness_pmu_at)],
        ['4 · Onboarding', $st((bool) $r->onboarding_confirmed_at, (bool) $r->approval_sent_at)],
        ['Gate 7 · Disbursement', $r->isFullyDisbursed() ? 'done' : ($r->isCleared() ? 'current' : 'todo')],
    ];
    $headPill = match (true) {
        $r->isFullyDisbursed() => ['Fully disbursed', 'pill-ok', 'bi-check2-all'],
        (bool) $outcome        => [$CR::OUTCOMES[$outcome][0], $CR::OUTCOMES[$outcome][4], $CR::OUTCOMES[$outcome][3]],
        default                => ['In progress', 'pill-warn', 'bi-hourglass-split'],
    };
@endphp

    @unless ($applicant)
        <div class="g2-path mb-3">
            @foreach ($path as [$pLabel, $pState])
                <span class="g2-path-step {{ $pState !== 'todo' ? 'is-'.$pState : '' }}">
                    <i class="bi {{ ['done' => 'bi-check-lg', 'current' => 'bi-hourglass-split', 'todo' => 'bi-circle'][$pState] }}"></i> {{ $pLabel }}
                </span>
            @endforeach
        </div>
    @endunless

    {{-- ============================ HEADER ============================ --}}
    <section class="section-card">
        <div class="section-card-head">
            <span class="section-no">G6</span>
            <div class="me-auto">
                <h5>Conditions precedent, onboarding &amp; disbursement</h5>
                <div class="small text-muted">
                    @if ($applicant) What needs to happen before — and after — your CAFI grant is paid.
                    @else Gate 6 activates the approved grant and confirms readiness; Gate 7 releases it against verified private capital. @endif
                </div>
            </div>
            @unless ($applicant)
                <a href="{{ route('gate5.record', $r) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-file-earmark-pdf me-1"></i> Disbursement file (PDF)</a>
            @endunless
            <span class="pill {{ $headPill[1] }}"><i class="bi {{ $headPill[2] }}"></i> {{ $headPill[0] }}</span>
        </div>

        @if ($canManage)
            <div class="small text-muted mb-3"><i class="bi bi-cloud-check me-1"></i> Changes are saved automatically. @if ($savedAt) <span class="text-accent">Last saved {{ $savedAt }}.</span> @endif
                <span wire:loading class="ms-1"><span class="spinner-border spinner-border-sm"></span></span></div>
        @elseif ($r->isCleared() && ! $applicant)
            <div class="small text-muted mb-3"><i class="bi bi-lock me-1"></i> Cleared on {{ $r->cleared_at?->format('d M Y') }} — the Gate 6 set-up is locked. Payments are processed below (Gate 7).</div>
        @endif

        <div class="row g-3">
            @foreach ([
                ['Approved CAFI grant', $m($grant), $u($grant), 'bi-cash-coin', 'primary'],
                ['Private capital verified', $m($verified), $private > 0 ? 'of '.$m($private).' approved' : '', 'bi-briefcase', 'accent'],
                ['Disbursed', $m($r->releasedAmount()), 'Still to disburse '.$m($r->stillToDisburse()), 'bi-send-check', 'warning'],
                ['Conditions met', $s['conditions_done'].' / '.$s['conditions_total'], $s['ongoing'].' ongoing obligation(s)', 'bi-list-check', 'primary'],
            ] as [$tl, $tv, $ts, $ti, $tt])
                <div class="col-sm-6 col-xl-3">
                    <div class="stat-card">
                        <span class="stat-icon tone-{{ $tt }}"><i class="bi {{ $ti }}"></i></span>
                        <div><div class="stat-value sm">{{ $tv }}</div><div class="stat-label">{{ $tl }}</div><div class="small text-muted">{{ $ts }}</div></div>
                    </div>
                </div>
            @endforeach
        </div>

        @if ($r->outcome_comments && $outcome !== 'cleared')
            <div class="callout callout-warning small mt-3 mb-0"><div class="fw-semibold mb-1">{{ $CR::OUTCOMES[$outcome][0] ?? 'Note' }} — {{ $r->outcome_on?->format('d M Y') }}</div>{{ $r->outcome_comments }}</div>
        @endif
    </section>

    {{-- ============================ STEP 1 · APPROVAL & CHECKLIST (§3.7.1) ============================ --}}
    <section class="section-card" id="g6-step1">
        <div class="section-card-head">
            <span class="section-no">1</span>
            <div class="me-auto">
                <h5>{{ $applicant ? 'Your Grant Agreement and conditions' : 'Communicate grant approval & initiate onboarding' }}</h5>
                <div class="small text-muted">
                    @if ($r->approval_sent_at) Approval communicated on {{ $r->approval_sent_at->format('d M Y') }}.
                    @else The enterprise receives the executed Grant Agreement, the approved budget, the conditions before disbursement and its reporting obligations. @endif
                </div>
            </div>
            <span class="pill {{ $r->approval_sent_at ? 'pill-ok' : 'pill-warn' }}">{{ $r->approval_sent_at ? 'Sent' : 'Not sent' }}</span>
        </div>

        {{-- Agreements --}}
        <div class="table-responsive mb-3">
            <table class="table table-modern align-middle mb-2">
                <thead><tr><th>Agreement</th><th style="width: 16%">Status</th><th style="width: 14%">Signed</th><th style="width: 26%">Signed copy</th>@if ($canAgr)<th></th>@endif</tr></thead>
                <tbody>
                    @foreach ($agreements as $i => $a)
                        @php $ag = $r->agreements->firstWhere('id', $a['id']); @endphp
                        <tr wire:key="agr-{{ $a['id'] }}">
                            <td class="small">
                                @if ($canAgr && $a['kind'] === 'other')
                                    <input type="text" class="form-control form-control-sm mb-1" wire:model.blur="agreements.{{ $i }}.title" maxlength="150">
                                @else <div class="fw-semibold text-navy">{{ $a['title'] }}</div> @endif
                                <div class="text-muted">{{ $a['parties'] }}</div>
                                @if ($canAgr && $a['kind'] !== 'grant')
                                    <label class="form-check small mb-0 mt-1"><input type="checkbox" class="form-check-input" wire:model.live="agreements.{{ $i }}.required"> <span class="form-check-label">Required before disbursement</span></label>
                                @endif
                            </td>
                            <td>
                                @if ($canAgr)
                                    <select class="form-select form-select-sm @error("agreements.$i.status") is-invalid @enderror" wire:model.live="agreements.{{ $i }}.status">
                                        @foreach ($CR::AGREEMENT_STATUSES as $sk => [$sl]) <option value="{{ $sk }}">{{ $sl }}</option> @endforeach
                                    </select>
                                    @error("agreements.$i.status") <div class="invalid-feedback">{{ $message }}</div> @enderror
                                @else <span class="pill {{ $CR::AGREEMENT_STATUSES[$a['status']][1] ?? 'pill-muted' }}">{{ $CR::AGREEMENT_STATUSES[$a['status']][0] ?? $a['status'] }}</span> @endif
                            </td>
                            <td>@if ($canAgr)<input type="date" class="form-control form-control-sm" wire:model.blur="agreements.{{ $i }}.signed_on">@else <span class="small">{{ $a['signed_on'] ? \Illuminate\Support\Carbon::parse($a['signed_on'])->format('d M Y') : '—' }}</span> @endif</td>
                            <td class="small">
                                @if ($ag?->file_path) <a href="{{ $file('agreement', $ag->id) }}" class="text-decoration-none text-truncate d-inline-block" style="max-width: 220px"><i class="bi bi-file-earmark-check"></i> {{ $ag->file_name }}</a> @endif
                                @if ($canAgr)
                                    <input type="file" class="form-control form-control-sm mt-1" wire:model="agreementFiles.{{ $a['id'] }}" accept=".pdf,.doc,.docx">
                                    @error("agreementFiles.{$a['id']}") <div class="text-danger">{{ $message }}</div> @enderror
                                @elseif (! $ag?->file_path) <span class="text-muted">—</span> @endif
                            </td>
                            @if ($canAgr)<td>@if ($a['kind'] === 'other')<button type="button" class="btn btn-icon btn-icon-danger" wire:click="removeAgreement({{ $a['id'] }})"><i class="bi bi-x-lg"></i></button>@endif</td>@endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @if ($canAgr) <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="addAgreement"><i class="bi bi-plus-lg me-1"></i> Add agreement</button> @endif
        </div>

        {{-- CP checklist --}}
        <div class="d-flex align-items-center gap-2 mb-2">
            <div class="fw-semibold text-navy me-auto"><i class="bi bi-list-check me-1"></i> Conditions precedent checklist</div>
            <span class="small text-muted">Requirement · evidence needed · who provides / verifies · status</span>
        </div>
        <div class="table-responsive">
            <table class="table table-modern align-middle mb-2 dd-table">
                <thead><tr>
                    <th>Requirement &amp; evidence needed</th>
                    <th style="width: 15%">Provides · verifies</th>
                    <th style="width: 13%">Timing</th>
                    <th style="width: 22%">Evidence</th>
                    <th style="width: 16%">Status</th>
                </tr></thead>
                <tbody>
                    @foreach ($conds as $i => $c)
                        @php
                            $cm      = $r->conditions->firstWhere('id', $c['id']);
                            $mine    = $applicant && in_array($c['responsible'], ['enterprise', 'investor'], true);
                            $canUp   = $this->canUploadEvidence && $cm && ! $cm->isDone() && (! $applicant || $mine);
                        @endphp
                        <tr wire:key="cond-{{ $c['id'] }}" class="{{ $c['status'] === 'not_met' ? 'is-flagged' : '' }}">
                            <td class="small">
                                <span class="pill pill-muted mb-1">{{ $CR::areaLabel($c['area']) }}</span>
                                @if ($canManage)
                                    <input type="text" class="form-control form-control-sm mb-1" wire:model.blur="conds.{{ $i }}.finding" maxlength="250">
                                    <input type="text" class="form-control form-control-sm" wire:model.blur="conds.{{ $i }}.evidence_required" maxlength="250" placeholder="Evidence needed">
                                @else
                                    <div class="fw-semibold text-navy">{{ $c['finding'] }}</div>
                                    @if ($c['evidence_required']) <div class="text-muted">Evidence: {{ $c['evidence_required'] }}</div> @endif
                                @endif
                                @if ($c['status'] === 'not_met' || ($canManage && $c['status'] !== 'met'))
                                    @if ($canManage)
                                        <input type="text" class="form-control form-control-sm mt-1 {{ $c['status'] === 'not_met' ? 'border-danger' : '' }}" wire:model.blur="conds.{{ $i }}.corrective_action" maxlength="500" placeholder="Corrective action required (if not satisfied)">
                                    @elseif ($c['corrective_action'])
                                        <div class="text-danger mt-1"><i class="bi bi-exclamation-circle me-1"></i>Action: {{ $c['corrective_action'] }}</div>
                                    @endif
                                @endif
                                @if ($canManage)
                                    <input type="text" class="form-control form-control-sm mt-1" wire:model.blur="conds.{{ $i }}.note" maxlength="500" placeholder="Verification note — document reviewed, outcome">
                                @elseif ($c['note'] && ! $applicant) <div class="text-muted mt-1">{{ $c['note'] }}</div> @endif
                            </td>
                            <td class="small">
                                @if ($canManage)
                                    <select class="form-select form-select-sm mb-1" wire:model.live="conds.{{ $i }}.responsible" title="Provides the evidence">
                                        @foreach ($CR::PARTIES as $pk => $pl) <option value="{{ $pk }}">{{ $pl }}</option> @endforeach
                                    </select>
                                    <select class="form-select form-select-sm" wire:model.live="conds.{{ $i }}.verifier" title="Verifies">
                                        @foreach ($CR::PARTIES as $pk => $pl) <option value="{{ $pk }}">{{ $pl }}</option> @endforeach
                                    </select>
                                @else
                                    <div>{{ $partyL($c['responsible']) }}</div><div class="text-muted">verified by {{ $partyL($c['verifier']) }}</div>
                                @endif
                            </td>
                            <td class="small">
                                @if ($canManage)
                                    <select class="form-select form-select-sm" wire:model.live="conds.{{ $i }}.timing">
                                        @foreach ($CR::TIMINGS as $tk => [$tl]) <option value="{{ $tk }}">{{ $tl }}</option> @endforeach
                                    </select>
                                @else <span title="{{ $CR::TIMINGS[$c['timing']][1] ?? '' }}">{{ $CR::TIMINGS[$c['timing']][0] ?? '—' }}</span> @endif
                            </td>
                            <td class="small">
                                @if ($cm?->evidence_path)
                                    <a href="{{ $file('condition', $cm->id) }}" class="text-decoration-none text-truncate d-inline-block" style="max-width: 190px"><i class="bi bi-paperclip"></i> {{ $cm->evidence_name }}</a>
                                    <div class="text-muted">{{ $cm->evidence_at?->format('d M Y') }}</div>
                                @endif
                                @if ($canUp)
                                    <input type="file" class="form-control form-control-sm mt-1" wire:model="evidenceFiles.{{ $c['id'] }}">
                                    <span wire:loading wire:target="evidenceFiles.{{ $c['id'] }}" class="text-muted"><span class="spinner-border spinner-border-sm"></span></span>
                                    @error("evidenceFiles.{$c['id']}") <div class="text-danger">{{ $message }}</div> @enderror
                                @elseif (! $cm?->evidence_path) <span class="text-muted">—</span> @endif
                            </td>
                            <td>
                                <span class="pill {{ $CR::CONDITION_STATUSES[$c['status']][1] ?? 'pill-muted' }}">{{ $CR::CONDITION_STATUSES[$c['status']][0] ?? $c['status'] }}</span>
                                @if ($cm?->verifiedBy && $cm->isDone()) <div class="small text-muted">{{ $cm->verifiedBy->name }} · {{ $cm->verified_at?->format('d M') }}</div> @endif
                                @if ($canManage)
                                    <div class="d-flex flex-wrap gap-1 mt-1">
                                        @foreach (['met' => 'bi-check-lg', 'not_met' => 'bi-x-lg', 'waived' => 'bi-dash-lg', 'outstanding' => 'bi-arrow-counterclockwise'] as $sk => $si)
                                            @if ($c['status'] !== $sk)
                                                <button type="button" class="btn btn-icon btn-sm" wire:click="setConditionStatus({{ $c['id'] }}, '{{ $sk }}')" title="{{ $CR::CONDITION_STATUSES[$sk][0] }}"><i class="bi {{ $si }}"></i></button>
                                            @endif
                                        @endforeach
                                        @if (in_array($c['source'], ['g6', 'g5'], true))
                                            <button type="button" class="btn btn-icon btn-icon-danger btn-sm" wire:click="removeCondition({{ $c['id'] }})" wire:confirm="Remove this item?" title="Remove"><i class="bi bi-trash"></i></button>
                                        @endif
                                    </div>
                                    @error("conds.$i.status") <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if ($canManage)
            <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
                <select class="form-select form-select-sm" style="width: auto" wire:model="newArea">
                    @foreach ($CR::AREAS as $ak => $al) <option value="{{ $ak }}">{{ $al }}</option> @endforeach
                </select>
                <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="addCondition"><i class="bi bi-plus-lg me-1"></i> Add requirement</button>
                <span class="small text-muted">Ongoing reporting and monitoring commitments are recorded as <em>Ongoing obligation</em> — they are not conditions precedent unless the Grant Agreement says so.</span>
            </div>

            {{-- Send the approval communication --}}
            <div class="border rounded-3 p-3">
                <div class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold mb-1">Programme contact for the enterprise <span class="req">*</span></label>
                        <input type="text" class="form-control form-control-sm" wire:model.blur="onboarding.contact" placeholder="Name · e-mail · phone">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold mb-1">Reporting frequency</label>
                        <select class="form-select form-select-sm" wire:model.live="onboarding.frequency">
                            @foreach ($CR::FREQUENCIES as $fk => $fl) <option value="{{ $fk }}">{{ $fl }}</option> @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-semibold mb-1">First report</label>
                        <input type="date" class="form-control form-control-sm" wire:model.blur="onboarding.first_report_on">
                    </div>
                    <div class="col-md-3 d-flex">
                        <button type="button" class="btn btn-sm {{ $r->approval_sent_at ? 'btn-outline-secondary' : 'btn-brand' }} ms-auto" wire:click="sendApproval" wire:loading.attr="disabled" wire:target="sendApproval"
                                wire:confirm="E-mail the grant approval, the executed Grant Agreement and the conditions checklist to the enterprise?">
                            <i class="bi bi-envelope-paper me-1"></i> {{ $r->approval_sent_at ? 'Send again' : 'Send approval & checklist' }}
                        </button>
                    </div>
                </div>
                @error('approval') <div class="text-danger small mt-2">{{ $message }}</div> @enderror
            </div>
        @endif
    </section>

    {{-- ============================ STEP 2 · VERIFY (§3.7.2) — staff ============================ --}}
    @unless ($applicant)
        <section class="section-card" id="g6-step2">
            <div class="section-card-head">
                <span class="section-no">2</span>
                <div class="me-auto">
                    <h5>Verify conditions precedent</h5>
                    <div class="small text-muted">On documentary evidence and the executed Grant Agreement — not informal confirmation. Issues: document, set the corrective action and responsible party, track to resolution.</div>
                </div>
                <span class="pill {{ $s['verified_areas'] === count($CR::VERIFICATION_AREAS) ? 'pill-ok' : 'pill-warn' }}">{{ $s['verified_areas'] }} / {{ count($CR::VERIFICATION_AREAS) }} areas</span>
            </div>

            @php
                $hints = [
                    'legal'       => $r->conditions->where('area', 'legal')->every->isDone() ? 'Legal checklist items met.' : 'Legal checklist items outstanding.',
                    'agreement'   => ($agrGrant?->status === 'executed' ? 'Executed agreement on file' : 'Executed agreement missing').' · authorised grant '.$m($r->authorisation?->grant_amount).' vs '.$m($grant),
                    'budget'      => 'Budget grant total '.$m($budgetT['grant']).' vs approved '.$m($grant).(abs($budgetT['grant'] - $grant) <= 0.01 ? ' — matches' : ' — differs'),
                    'bank'        => ! empty($r->bank['changed']) ? 'Changed since Gate 4 — '.($r->bank_revalidated_at ? 'revalidated' : 'revalidation needed') : ($gate4Bank ? 'Same as verified at Gate 4' : 'No Gate 4 record'),
                    'investment'  => 'Verified '.$m($verified).' of '.$m($private).' approved private investment',
                    'contractual' => $r->conditions->where('area', 'contractual')->filter->isMandatory()->reject->isDone()->count().' contractual condition(s) open',
                    'payment'     => 'Approved grant '.$m($grant).' · disbursed '.$m($controls['disbursed'] ?? null),
                ];
            @endphp
            <div class="table-responsive">
                <table class="table table-modern align-middle mb-3 dd-table">
                    <thead><tr><th>Verification area</th><th style="width: 18%">Result</th><th style="width: 30%">Note / evidence reviewed</th></tr></thead>
                    <tbody>
                        @foreach ($CR::VERIFICATION_AREAS as $ak => [$aTitle, $aText])
                            @php $vs = $verification[$ak]['status'] ?? null; @endphp
                            <tr wire:key="va-{{ $ak }}" class="{{ $vs === 'issue' ? 'is-flagged' : '' }}">
                                <td class="small"><div class="fw-semibold text-navy">{{ $aTitle }}</div><div class="text-muted">{{ $aText }}</div>
                                    <div class="mt-1 text-primary-emphasis"><i class="bi bi-info-circle me-1"></i>{{ $hints[$ak] }}</div></td>
                                <td>
                                    <select class="form-select form-select-sm status-select {{ $CR::VERIFY_STATUSES[$vs][2] ?? '' }}" wire:model.live="verification.{{ $ak }}.status" @disabled(! $canManage)>
                                        <option value="">—</option>
                                        @foreach ($CR::VERIFY_STATUSES as $vk => [$vl]) <option value="{{ $vk }}">{{ $vl }}</option> @endforeach
                                    </select>
                                </td>
                                <td><input type="text" class="form-control form-control-sm" wire:model.blur="verification.{{ $ak }}.note" maxlength="500" @disabled(! $canManage)></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="row g-3">
                {{-- Bank account --}}
                <div class="col-lg-6">
                    <div class="border rounded-3 p-3 h-100">
                        <div class="fw-semibold text-navy small mb-2"><i class="bi bi-bank me-1"></i> Account the grant will be paid into</div>
                        <div class="row g-2">
                            @foreach (['bank' => 'Bank', 'branch' => 'Branch', 'account_name' => 'Account name', 'account_number' => 'Account number'] as $bk => $bl)
                                <div class="col-6">
                                    <label class="form-label small mb-0">{{ $bl }}</label>
                                    <input type="text" class="form-control form-control-sm" wire:model.blur="bank.{{ $bk }}" @disabled(! $canManage)>
                                </div>
                            @endforeach
                        </div>
                        @if ($gate4Bank)
                            <div class="small text-muted mt-2">Gate 4: {{ $gate4Bank['account_name'] ?? '—' }} · {{ $gate4Bank['bank'] ?? '—' }} · {{ $gate4Bank['account_number'] ?? '—' }}</div>
                        @endif
                        @if (! empty($r->bank['changed']))
                            <div class="callout callout-warning small mt-2 mb-0">
                                <i class="bi bi-shield-exclamation me-1"></i> The details differ from those verified at Gate 4. Revalidate them independently (payment-diversion and fraud risk).
                                @if ($r->bank_revalidated_at)
                                    <div class="text-success mt-1"><i class="bi bi-check-circle me-1"></i>Revalidated {{ $r->bank_revalidated_at->format('d M Y') }}: {{ $r->bank_revalidation_note }}</div>
                                @elseif ($canManage)
                                    <div class="input-group input-group-sm mt-2">
                                        <input type="text" class="form-control @error('bankNote') is-invalid @enderror" wire:model="bankNote" placeholder="How revalidated — e.g. bank letter dated…, call-back to the bank">
                                        <button class="btn btn-outline-primary" type="button" wire:click="revalidateBank">Record</button>
                                    </div>
                                    @error('bankNote') <div class="text-danger">{{ $message }}</div> @enderror
                                @endif
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Budget --}}
                <div class="col-lg-6">
                    <div class="border rounded-3 p-3 h-100">
                        <div class="d-flex align-items-center mb-2">
                            <div class="fw-semibold text-navy small me-auto"><i class="bi bi-pie-chart me-1"></i> Approved grant utilisation budget</div>
                            @if ($canManage)
                                <button type="button" class="btn btn-sm btn-link p-0 me-2" wire:click="$toggle('editBudget')">{{ $editBudget ? 'Done' : 'Edit' }}</button>
                                @if ($this->profile?->grant_budget) <button type="button" class="btn btn-sm btn-link p-0" wire:click="reloadBudgetFromProfile" wire:confirm="Replace with the enterprise’s profile budget?">Reload</button> @endif
                            @endif
                        </div>
                        <table class="table table-sm mb-0">
                            <thead><tr><th class="small">Category</th><th class="small text-end">Grant (M)</th></tr></thead>
                            <tbody>
                                @foreach ($budget as $bi => $b)
                                    <tr wire:key="bud-{{ $bi }}">
                                        <td class="small">{{ $b['category'] }}</td>
                                        <td class="text-end small">
                                            @if ($canManage && $editBudget) <input type="number" min="0" step="100" class="form-control form-control-sm text-end" wire:model.blur="budget.{{ $bi }}.grant">
                                            @else {{ number_format((float) ($b['grant'] ?? 0)) }} @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot><tr class="fw-semibold {{ abs($budgetT['grant'] - $grant) > 0.01 ? 'text-danger' : '' }}"><td class="small">Total (approved {{ $m($grant) }})</td><td class="text-end small">{{ number_format($budgetT['grant']) }}</td></tr></tfoot>
                        </table>
                        <div class="small text-muted mt-1">Changes to categories, allocations or uses go through change control before they are implemented.</div>
                    </div>
                </div>
            </div>
        </section>
    @endunless

    {{-- ============================ PRIVATE INVESTMENT (Gate 6 · §3.7.2 · Gate 7 · §3.8.2) ============================ --}}
    <section class="section-card" id="g6-investment">
        <div class="section-card-head">
            <span class="section-no"><i class="bi bi-briefcase"></i></span>
            <div class="me-auto">
                <h5>Private investment deployed</h5>
                <div class="small text-muted">CAFI grant follows verified private capital. {{ $applicant ? 'Attach proof of payment (bank record / investor remittance) for each investor.' : 'Verify amount, date, investor, receiving account and consistency with the approved structure — and the tranche it backs.' }}</div>
            </div>
            <span class="pill {{ $verified > 0 ? 'pill-ok' : 'pill-warn' }}">{{ $m($verified) }} verified</span>
        </div>

        @if ($this->canRecordCapital)
            @foreach ($this->profileInvestors as $ii => $inv)
                @php $dp = $inv['deployment']; @endphp
                <div class="file-row flex-wrap" wire:key="inv-{{ $ii }}">
                    <span class="file-row-icon"><i class="bi bi-person-badge"></i></span>
                    <div class="flex-grow-1 small"><div class="fw-semibold text-navy">{{ $inv['name'] }}</div><div class="text-muted">{{ $m($inv['amount']) }} {{ $inv['country'] ? '· '.$inv['country'] : '' }}</div></div>
                    @if ($dp) <span class="pill {{ $CR::DEPLOYMENT_STATUSES[$dp->status][1] }}">{{ $CR::DEPLOYMENT_STATUSES[$dp->status][0] }}</span> @endif
                    @if (! $dp || $dp->status === 'rejected' || $dp->status === 'verified')
                        <label class="btn btn-sm btn-outline-primary mb-0"><i class="bi bi-upload me-1"></i> {{ $dp ? 'Attach new proof' : 'Attach proof of payment' }}
                            <input type="file" class="d-none" wire:model="investorFiles.{{ $ii }}" accept=".pdf,.jpg,.jpeg,.png"></label>
                    @endif
                    @error("investorFiles.$ii") <div class="text-danger small w-100">{{ $message }}</div> @enderror
                </div>
            @endforeach
        @endif

        <div class="table-responsive">
            <table class="table table-modern align-middle mb-0">
                <thead><tr><th>Investor</th><th class="text-end">Amount</th><th>Date</th><th>Proof</th><th>Status</th></tr></thead>
                <tbody>
                    @forelse ($r->deployments as $d)
                        <tr wire:key="dep-{{ $d->id }}">
                            <td class="small fw-semibold text-navy">{{ $d->investor }}</td>
                            <td class="text-end small">{{ $m($d->amount) }}<div class="text-muted">{{ $u($d->amount) }}</div></td>
                            <td class="small">{{ $d->deployed_on?->format('d M Y') }}</td>
                            <td class="small">@if ($d->evidence_path)<a href="{{ $file('deployment', $d->id) }}" class="text-decoration-none"><i class="bi bi-paperclip"></i> {{ \Illuminate\Support\Str::limit($d->evidence_name, 28) }}</a>@else — @endif</td>
                            <td class="small">
                                <span class="pill {{ $CR::DEPLOYMENT_STATUSES[$d->status][1] }}">{{ $CR::DEPLOYMENT_STATUSES[$d->status][0] }}</span>
                                @if ($d->status === 'verified')
                                    <div class="text-muted">{{ $d->verifier?->name }} · {{ $d->verified_at?->format('d M') }}{{ $d->received_account ? ' · into '.$d->received_account : '' }}{{ $d->tranche ? ' · backs '.$d->tranche->label : '' }}</div>
                                @elseif ($d->status === 'rejected' && $d->note) <div class="text-danger">{{ $d->note }}</div> @endif
                                @if ($applicant && $d->status !== 'verified' && $d->recorded_by === auth()->id())
                                    <button type="button" class="btn btn-link btn-sm p-0 text-danger" wire:click="removeDeployment({{ $d->id }})" wire:confirm="Remove this record?">Remove</button>
                                @endif
                            </td>
                        </tr>
                        @if ($d->status === 'submitted' && $this->canVerifyCapital)
                            <tr wire:key="depv-{{ $d->id }}"><td colspan="5" class="bg-light">
                                <div class="row g-2 small">
                                    <div class="col-lg-6">
                                        @foreach ($CR::DEPLOYMENT_CHECKS as $dk => $dl)
                                            <label class="form-check mb-0"><input type="checkbox" class="form-check-input" wire:model="dep.{{ $d->id }}.checks.{{ $dk }}"> <span class="form-check-label">{{ $dl }}</span></label>
                                        @endforeach
                                    </div>
                                    <div class="col-lg-6">
                                        <label class="form-label small mb-0">Received into account</label>
                                        <input type="text" class="form-control form-control-sm mb-1" wire:model="dep.{{ $d->id }}.received_account" placeholder="Account number / name">
                                        <label class="form-label small mb-0">Backs tranche</label>
                                        <select class="form-select form-select-sm mb-1" wire:model="dep.{{ $d->id }}.tranche">
                                            <option value="">— not linked yet —</option>
                                            @foreach ($r->tranches as $tt) <option value="{{ $tt->id }}">{{ $tt->label }} · {{ $m($tt->amount) }}</option> @endforeach
                                        </select>
                                        <div class="d-flex gap-2">
                                            <button type="button" class="btn btn-sm btn-brand" wire:click="verifyDeployment({{ $d->id }})"><i class="bi bi-check-lg me-1"></i> Verify</button>
                                            <input type="text" class="form-control form-control-sm" wire:model="rejectNote.{{ $d->id }}" placeholder="Reason if not accepted">
                                            <button type="button" class="btn btn-sm btn-outline-danger" wire:click="rejectDeployment({{ $d->id }})">Not accepted</button>
                                        </div>
                                        @error("dep.{$d->id}") <div class="text-danger">{{ $message }}</div> @enderror
                                        @error("rejectNote.{$d->id}") <div class="text-danger">{{ $message }}</div> @enderror
                                    </div>
                                </div>
                            </td></tr>
                        @endif
                    @empty
                        <tr><td colspan="5" class="small text-muted">No investment deployment recorded yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    {{-- ============================ STEP 3 · READINESS (§3.7.3) — staff ============================ --}}
    @unless ($applicant)
        <section class="section-card" id="g6-step3">
            <div class="section-card-head">
                <span class="section-no">3</span>
                <div class="me-auto">
                    <h5>Confirm readiness for disbursement</h5>
                    <div class="small text-muted">Consolidated evidence → programme and Finance confirmation → outcome. Not cleared while a mandatory condition is outstanding.</div>
                </div>
                @if ($outcome) <span class="pill {{ $CR::OUTCOMES[$outcome][4] }}">{{ $CR::OUTCOMES[$outcome][0] }}</span> @endif
            </div>

            <ul class="checklist mb-3">
                @foreach ($CR::READINESS_CHECKS as $rk => $rt)
                    <li class="{{ ($readiness[$rk] ?? false) ? 'is-done' : 'is-pending' }}" wire:key="rc-{{ $rk }}">
                        <label class="d-flex align-items-start gap-2 mb-0 w-100">
                            <input type="checkbox" class="form-check-input mt-1" wire:model.live="readiness.{{ $rk }}" @disabled(! $canManage)> <span>{{ $rt }}</span>
                        </label>
                    </li>
                @endforeach
            </ul>

            @if (! $r->isCleared())
                @if ($blockers)
                    <div class="callout callout-warning small mb-3">
                        <div class="fw-semibold mb-1"><i class="bi bi-list-check me-1"></i> Before the transaction can be cleared ({{ count($blockers) }})</div>
                        <ul class="evidence-list mb-0">@foreach ($blockers as $b) <li>{{ $b }}</li> @endforeach</ul>
                    </div>
                @else
                    <div class="callout callout-success small mb-3"><i class="bi bi-check2-circle me-1"></i> Everything is verified and confirmed — record <strong>Cleared for disbursement</strong>.</div>
                @endif
            @endif

            <div class="row g-2 mb-3">
                <div class="col-md-6">
                    <div class="border rounded-3 p-2 small h-100">
                        <div class="fw-semibold text-navy">Programme confirmation</div>
                        @if ($r->readiness_pmu_at) <div class="text-success"><i class="bi bi-check-circle me-1"></i>{{ $r->readinessPmu?->name }} · {{ $r->readiness_pmu_at->format('d M Y H:i') }}</div>
                        @elseif ($canManage)
                            <button type="button" class="btn btn-sm btn-outline-primary mt-1" wire:click="confirmProgramme" wire:confirm="Confirm the transaction file is complete and ready for disbursement? Finance will be asked to confirm.">Confirm & send to Finance</button>
                        @else <div class="text-muted">Pending</div> @endif
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="border rounded-3 p-2 small h-100">
                        <div class="fw-semibold text-navy">Finance confirmation</div>
                        @if ($r->readiness_finance_at) <div class="text-success"><i class="bi bi-check-circle me-1"></i>{{ $r->readinessFinance?->name }} · {{ $r->readiness_finance_at->format('d M Y H:i') }}</div>
                        @elseif ($this->isFinance && ! $r->isCleared() && $r->readiness_pmu_at)
                            <button type="button" class="btn btn-sm btn-outline-primary mt-1" wire:click="confirmFinance" wire:confirm="Confirm disbursement readiness from Finance’s side?">Confirm (Finance)</button>
                        @else <div class="text-muted">{{ $r->readiness_pmu_at ? 'Waiting for Finance' : 'After the programme confirmation' }}</div> @endif
                    </div>
                </div>
            </div>
            @error('readiness') <div class="text-danger small mb-2">{{ $message }}</div> @enderror

            @if ($canManage)
                <div class="choice-group cols-4 @error('decision.outcome') is-invalid @enderror">
                    @foreach ($CR::OUTCOME_CHOICES as $ok)
                        @php [$oLabel, $oMean, $oNext, $oIcon] = $CR::OUTCOMES[$ok]; @endphp
                        <label class="choice choice--card">
                            <input type="radio" name="g6-outcome" value="{{ $ok }}" wire:model.live="decision.outcome">
                            <span class="choice-box"><span class="choice-icon"><i class="bi {{ $oIcon }}"></i></span>
                                <span><span class="d-block fw-semibold">{{ $oLabel }}</span><span class="d-block small text-muted lh-sm mt-1">{{ $oMean }}</span>
                                <span class="d-block small text-brand mt-1"><i class="bi bi-arrow-right-short"></i>{{ $oNext }}</span></span></span>
                        </label>
                    @endforeach
                </div>
                @error('decision.outcome') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                <div class="row g-3 mt-1">
                    <div class="col-md-9">
                        <label class="form-label small fw-semibold">Comments @if (($decision['outcome'] ?? null) && $decision['outcome'] !== 'cleared') <span class="req">*</span> @endif</label>
                        <textarea rows="2" class="form-control @error('decision.comments') is-invalid @enderror" wire:model.blur="decision.comments" placeholder="What is outstanding or must be corrected, who is responsible, by when (sent to the enterprise)"></textarea>
                        @error('decision.comments') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Date</label>
                        <input type="date" class="form-control" wire:model.blur="decision.on" max="{{ now()->toDateString() }}">
                    </div>
                </div>
                <div class="d-flex mt-3 pt-3 border-top">
                    <button type="button" class="btn btn-brand ms-auto" wire:click="recordOutcome" wire:loading.attr="disabled" wire:target="recordOutcome"
                            wire:confirm="Record this Gate 6 outcome? The enterprise will be e-mailed.">
                        <i class="bi bi-send me-1"></i> Record outcome
                    </button>
                </div>
            @endif

            @if ($r->history)
                <details class="evidence mt-3">
                    <summary><i class="bi bi-clock-history me-1"></i> History ({{ count($r->history) }})</summary>
                    <div class="p-2 small">
                        @foreach (array_reverse($r->history) as $h)
                            <div class="mb-1"><strong>{{ $CR::OUTCOMES[$h['outcome']][0] ?? ucfirst(str_replace('_', ' ', $h['outcome'])) }}</strong> · {{ \Illuminate\Support\Carbon::parse($h['on'])->format('d M Y') }} · {{ $h['by'] ?? '' }}
                                @if (! empty($h['comments'])) <div class="text-muted">{{ $h['comments'] }}</div> @endif</div>
                        @endforeach
                    </div>
                </details>
            @endif
        </section>
    @endunless

    {{-- ============================ STEP 4 · ONBOARDING (§3.7.4) ============================ --}}
    <section class="section-card" id="g6-step4">
        <div class="section-card-head">
            <span class="section-no">4</span>
            <div class="me-auto">
                <h5>Onboarding &amp; obligations after disbursement</h5>
                <div class="small text-muted">The enterprise understands its obligations, the monitoring arrangements are in place and the baseline is recorded.</div>
            </div>
            <span class="pill {{ $r->onboarding_confirmed_at ? 'pill-ok' : 'pill-warn' }}">{{ $r->onboarding_confirmed_at ? 'Briefed '.$r->onboarding_confirmed_at->format('d M Y') : 'Not confirmed' }}</span>
        </div>

        <div class="row g-3">
            <div class="col-lg-6">
                <ul class="checklist mb-2">
                    @foreach ($CR::ONBOARDING_ITEMS as $ok => $ol)
                        <li class="{{ ($onboarding['items'][$ok] ?? false) ? 'is-done' : 'is-pending' }}" wire:key="ob-{{ $ok }}">
                            <label class="d-flex align-items-start gap-2 mb-0 w-100"><input type="checkbox" class="form-check-input mt-1" wire:model.live="onboarding.items.{{ $ok }}" @disabled(! $canManage)> <span>{{ $ol }}</span></label>
                        </li>
                    @endforeach
                </ul>
                <div class="small text-muted">Reporting: {{ $CR::FREQUENCIES[$onboarding['frequency']] ?? '—' }}{{ $onboarding['first_report_on'] ? ' · first report '.\Illuminate\Support\Carbon::parse($onboarding['first_report_on'])->format('d M Y') : '' }} · Contact: {{ $onboarding['contact'] ?: '—' }}</div>
            </div>
            <div class="col-lg-6">
                <div class="border rounded-3 p-3">
                    <div class="fw-semibold text-navy small mb-2"><i class="bi bi-graph-up me-1"></i> Baseline business &amp; employment</div>
                    <div class="row g-2">
                        @foreach ($CR::BASELINE_FIELDS as $bk => $bl)
                            <div class="col-6">
                                <label class="form-label small mb-0">{{ $bl }}</label>
                                <input type="number" min="0" class="form-control form-control-sm" wire:model.blur="baseline.{{ $bk }}" @disabled(! $canManage)>
                            </div>
                        @endforeach
                        <div class="col-6">
                            <label class="form-label small mb-0">As at</label>
                            <input type="date" class="form-control form-control-sm" wire:model.blur="baseline.as_of" @disabled(! $canManage)>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @error('onboarding.contact') <div class="text-danger small mt-2">{{ $message }}</div> @enderror

        <div class="d-flex flex-wrap gap-2 align-items-center mt-3 pt-3 border-top">
            @if ($r->summary_sent_at) <span class="small text-muted"><i class="bi bi-envelope-check me-1"></i> Summary of obligations sent {{ $r->summary_sent_at->format('d M Y') }}</span> @endif
            @if (! $applicant && auth()->user()->can('gate5.verify') && $this->inGate)
                <button type="button" class="btn btn-sm btn-outline-secondary ms-auto" wire:click="sendSummary" wire:confirm="E-mail the summary of obligations (onboarding record) to the enterprise?"><i class="bi bi-envelope me-1"></i> {{ $r->summary_sent_at ? 'Send summary again' : 'Send summary of obligations' }}</button>
            @endif
            @if ($this->canConfirmOnboarding)
                <button type="button" class="btn btn-sm btn-brand {{ $applicant ? 'ms-auto' : '' }}" wire:click="confirmOnboarding"
                        wire:confirm="{{ $applicant ? 'Confirm that you have been briefed on your obligations under the Grant Agreement, the reporting requirements and the monitoring arrangements?' : 'Record that the enterprise was briefed on its obligations?' }}">
                    <i class="bi bi-check2-square me-1"></i> {{ $applicant ? 'I confirm I have been briefed on my obligations' : 'Record briefing' }}
                </button>
            @endif
        </div>
    </section>

    {{-- ============================ GATE 7 · DISBURSEMENT (§3.8) ============================ --}}
    <section class="section-card" id="g7">
        <div class="section-card-head">
            <span class="section-no">G7</span>
            <div class="me-auto">
                <h5>Grant disbursement</h5>
                <div class="small text-muted">A financial control point, not a further appraisal: each payment is released only against verified, eligible private investment.</div>
            </div>
            <span class="pill {{ $r->isFullyDisbursed() ? 'pill-ok' : ($r->isCleared() ? 'pill-warn' : 'pill-muted') }}">
                {{ $r->isFullyDisbursed() ? 'Fully disbursed' : ($r->isCleared() ? $m($r->releasedAmount()).' of '.$m($grant).' released' : 'Opens when cleared') }}
            </span>
        </div>

        @unless ($applicant)
            @php $reqs = $r->disbursementRequirements(); @endphp
            <div class="row g-3 mb-3">
                <div class="col-lg-7">
                    <div class="table-responsive">
                        <table class="table table-modern table-sm align-middle mb-0">
                            <thead><tr><th>Requirement</th><th>Verification</th><th class="text-center">✓</th></tr></thead>
                            <tbody>
                                @foreach ($reqs as [$rq, $rv, $rok])
                                    <tr><td class="small fw-semibold text-navy">{{ $rq }}</td><td class="small text-muted">{{ $rv }}</td>
                                        <td class="text-center"><i class="bi {{ $rok ? 'bi-check-circle-fill text-success' : 'bi-x-circle text-danger' }}"></i></td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="border rounded-3 p-3 small h-100">
                        <div class="fw-semibold text-navy mb-2"><i class="bi bi-shield-check me-1"></i> Programme limits</div>
                        <dl class="row mb-0">
                            <dt class="col-7 fw-normal text-muted">CAFI grant received by the enterprise</dt><dd class="col-5 text-end mb-1">{{ $m($controls['enterprise_received']) }}</dd>
                            <dt class="col-7 fw-normal text-muted">Approved for the enterprise (all grants)</dt><dd class="col-5 text-end mb-1">{{ $m($controls['enterprise_approved']) }}</dd>
                            <dt class="col-7 fw-normal text-muted">Approved transaction grant</dt><dd class="col-5 text-end mb-1">{{ $m($controls['approved']) }}</dd>
                            <dt class="col-7 fw-normal text-muted">Already disbursed</dt><dd class="col-5 text-end mb-1">{{ $m($controls['disbursed']) }}</dd>
                            <dt class="col-7 fw-normal text-muted">Undisbursed balance</dt><dd class="col-5 text-end mb-1">{{ $m($controls['undisbursed']) }}</dd>
                            @foreach ($controls['investors'] as $ix)
                                <dt class="col-7 fw-normal text-muted">Investor exposure · {{ $ix['name'] }}</dt>
                                <dd class="col-5 text-end mb-1 {{ $controls['investor_cap'] !== null && $ix['exposure'] > $controls['investor_cap'] ? 'text-danger' : '' }}">{{ $m($ix['exposure']) }}</dd>
                            @endforeach
                        </dl>
                    </div>
                </div>
            </div>
        @endunless

        {{-- Tranches --}}
        <div class="table-responsive">
            <table class="table table-modern align-middle mb-2">
                <thead><tr><th style="width: 9%">Tranche</th><th style="width: 15%" class="text-end">Grant</th><th>Trigger / matching</th><th style="width: 30%">Payment</th>@if ($this->canEditTranches)<th></th>@endif</tr></thead>
                <tbody>
                    @forelse ($r->tranches as $ti => $t)
                        @php
                            $mt    = $r->matchingFor($t);
                            $stepT = $t->step();
                            $row   = collect($tranches)->search(fn ($x) => $x['id'] === $t->id);
                            $edit  = $this->canEditTranches && ! $t->isReleased() && ! $t->requested_at;
                            $oc    = $CR::TRANCHE_OUTCOMES[$t->outcome] ?? null;
                        @endphp
                        <tr wire:key="tr-{{ $t->id }}" class="{{ $stepT === 'stopped' ? 'is-flagged' : '' }}">
                            <td>@if ($edit)<input type="text" class="form-control form-control-sm" wire:model.blur="tranches.{{ $row }}.label" maxlength="20">@else <strong>{{ $t->label }}</strong> @endif</td>
                            <td class="text-end">
                                @if ($edit)<input type="number" min="0" step="1000" class="form-control form-control-sm text-end @error("tranches.$row.amount") is-invalid @enderror" wire:model.blur="tranches.{{ $row }}.amount">
                                @else {{ $m($t->amount) }} @endif
                                <div class="small text-muted">{{ $u($t->amount) }}</div>
                                @error("tranches.$row.amount") <div class="text-danger small">{{ $message }}</div> @enderror
                            </td>
                            <td class="small">
                                @if ($edit)<input type="text" class="form-control form-control-sm mb-1" wire:model.blur="tranches.{{ $row }}.trigger" maxlength="250" placeholder="Release trigger / milestone">
                                @elseif ($t->trigger) <div>{{ $t->trigger }}</div> @endif
                                @unless ($applicant)
                                    <div class="{{ $mt['ok'] ? 'text-success' : 'text-danger' }}">
                                        <i class="bi {{ $mt['ok'] ? 'bi-check-circle' : 'bi-exclamation-triangle' }} me-1"></i>
                                        Matching: grant after this {{ $m($mt['after']) }} needs {{ $m($mt['needed']) }} private capital · verified {{ $m($mt['verified']) }}
                                        @if ($mt['linked'] > 0) · {{ $m($mt['linked']) }} linked to {{ $t->label }} @endif
                                    </div>
                                @endunless
                            </td>
                            <td class="small">
                                @if ($t->isReleased())
                                    <span class="pill pill-ok"><i class="bi bi-check-lg"></i> Paid {{ $t->released_on->format('d M Y') }}</span>
                                    <div class="text-muted">Ref {{ $t->reference }}{{ $t->instruction_ref ? ' · instruction '.$t->instruction_ref : '' }}</div>
                                    @if ($t->proof_path) <a href="{{ $file('tranche', $t->id) }}" class="text-decoration-none"><i class="bi bi-file-earmark-check"></i> Confirmation of payment</a> @endif
                                    @unless ($applicant)<div class="text-muted">Requested {{ $t->requester?->name ?? '—' }} · approved {{ $t->approver?->name ?? '—' }} · paid {{ $t->releaser?->name ?? '—' }}</div>@endunless
                                @elseif ($applicant)
                                    <span class="pill {{ $oc[3] ?? 'pill-muted' }}">{{ $oc[0] ?? ($r->isCleared() ? 'Scheduled' : 'After clearance') }}</span>
                                @else
                                    @if ($oc) <span class="pill {{ $oc[3] }}">{{ $oc[0] }}</span> @if ($t->outcome_note) <div class="text-muted">{{ $t->outcome_note }}</div> @endif @endif
                                    @if ($stepT === 'requested') <div class="text-muted">Requested by {{ $t->requester?->name }} {{ $t->requested_at->diffForHumans() }} — awaiting payment approval</div> @endif
                                    @if ($stepT === 'approved') <div class="text-muted">Approved by {{ $t->approver?->name }} {{ $t->approved_at->diffForHumans() }} — Finance to pay</div> @endif

                                    {{-- PMU: request payment --}}
                                    @if ($this->canRequestPayment && in_array($stepT, ['planned', 'held'], true))
                                        <button type="button" class="btn btn-sm btn-outline-primary mt-1" wire:click="requestPayment({{ $t->id }})" wire:confirm="Matching investment verified for {{ $t->label }} — request payment approval?">
                                            <i class="bi bi-send me-1"></i> Request payment approval</button>
                                    @endif
                                    {{-- Approver --}}
                                    @if ($this->canApprovePayment && $stepT === 'requested')
                                        <div class="mt-1">
                                            <input type="text" class="form-control form-control-sm mb-1 @error("payNote.{$t->id}") is-invalid @enderror" wire:model="payNote.{{ $t->id }}" placeholder="Note (required to hold or stop)">
                                            <div class="d-flex flex-wrap gap-1">
                                                <button type="button" class="btn btn-sm btn-brand" wire:click="approvePayment({{ $t->id }})" wire:confirm="Approve payment of {{ $t->label }} ({{ $m($t->amount) }})?"><i class="bi bi-check2 me-1"></i> Approve</button>
                                                <button type="button" class="btn btn-sm btn-outline-warning" wire:click="holdPayment({{ $t->id }}, 'pending')">Hold</button>
                                                <button type="button" class="btn btn-sm btn-outline-danger" wire:click="holdPayment({{ $t->id }}, 'not_eligible')" wire:confirm="Mark not eligible? The payment is stopped and the transaction escalated.">Not eligible</button>
                                            </div>
                                            @error("payNote.{$t->id}") <div class="text-danger">{{ $message }}</div> @enderror
                                        </div>
                                    @endif
                                    {{-- Finance: instruction + confirmation --}}
                                    @if ($this->isFinance && $this->cleared && $stepT === 'approved' && isset($release[$t->id]))
                                        <div class="row g-1 mt-1">
                                            <div class="col-7"><input type="text" class="form-control form-control-sm @error("release.{$t->id}.instruction_ref") is-invalid @enderror" wire:model="release.{{ $t->id }}.instruction_ref" placeholder="Instruction ref."></div>
                                            <div class="col-5"><input type="date" class="form-control form-control-sm" wire:model="release.{{ $t->id }}.instruction_on" max="{{ now()->toDateString() }}"></div>
                                            <div class="col-7"><input type="text" class="form-control form-control-sm @error("release.{$t->id}.reference") is-invalid @enderror" wire:model="release.{{ $t->id }}.reference" placeholder="Payment ref."></div>
                                            <div class="col-5"><input type="date" class="form-control form-control-sm @error("release.{$t->id}.on") is-invalid @enderror" wire:model="release.{{ $t->id }}.on" max="{{ now()->toDateString() }}"></div>
                                            <div class="col-12"><input type="file" class="form-control form-control-sm @error("releaseProof.{$t->id}") is-invalid @enderror" wire:model="releaseProof.{{ $t->id }}" accept=".pdf,.jpg,.jpeg,.png" title="Confirmation of payment"></div>
                                            <div class="col-12 d-flex"><button type="button" class="btn btn-sm btn-brand ms-auto" wire:click="releaseTranche({{ $t->id }})" wire:loading.attr="disabled" wire:target="releaseTranche({{ $t->id }}),releaseProof.{{ $t->id }}"
                                                    wire:confirm="Record the payment of {{ $t->label }} ({{ $m($t->amount) }}) to the verified account?"><i class="bi bi-cash me-1"></i> Record payment</button></div>
                                            @foreach (["release.{$t->id}.instruction_ref", "release.{$t->id}.reference", "release.{$t->id}.on", "releaseProof.{$t->id}"] as $ek)
                                                @error($ek) <div class="col-12 text-danger">{{ $message }}</div> @enderror
                                            @endforeach
                                        </div>
                                    @endif
                                    @error("pay.{$t->id}") <div class="text-danger mt-1">{{ $message }}</div> @enderror
                                @endif
                            </td>
                            @if ($this->canEditTranches)
                                <td>@if ($edit)<button type="button" class="btn btn-icon btn-icon-danger" wire:click="removeTranche({{ $t->id }})" title="Remove"><i class="bi bi-x-lg"></i></button>@endif</td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="5" class="small text-muted">No tranches yet. {{ $applicant ? '' : 'Add the first one — the grant is linked to the corresponding investment tranche.' }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($this->canEditTranches)
            <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="addTranche"><i class="bi bi-plus-lg me-1"></i> Add tranche</button>
        @endif

        <details class="evidence mt-3">
            <summary><i class="bi bi-info-circle me-1"></i> Gate 7 outcomes</summary>
            <dl class="evidence-grid">
                @foreach ($CR::TRANCHE_OUTCOMES as [$tl, $tm, $tn]) <dt>{{ $tl }}</dt><dd>{{ $tm }} → {{ $tn }}</dd> @endforeach
            </dl>
            <div class="p-2 small text-muted">A failed or delayed payment does not end the transaction — it stays pending until the requirement is resolved.</div>
        </details>
    </section>
@endif
</div>