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
| G5 · Conditions Precedent, Grant Agreement & Onboarding (CI Facility Operations Manual §3.6)
|   Used in the EOI page:  <livewire:expression-of-interest.conditions.condition-review :eoi-id="$eoi->id" />
|
|   Gate 4 → conditions precedent → Grant & Investment Agreements → verify private capital deployment
|          → disbursement readiness → onboarding → CAFI grant disbursement (tranches) → portfolio monitoring
|
|   Who does what (each action is limited to ONE role — nobody else gets the button, and the server refuses it too):
|     Enterprise (the applicant who owns the EOI)
|       gate5.evidence   uploads evidence against outstanding conditions          (only the enterprise)
|       capital.upload   attaches proof of payment for each investor on its profile (only the enterprise) —
|                        investor and amount come from the enterprise profile ("Investors in the round"), nothing is typed
|                        can remove its own deployment record until it is verified
|     PMU
|       gate5.verify     manages conditions (met / waived), agreements + signed copies, checks, budget, tranches,
|                        onboarding (e-mails the summary of obligations) and records the Gate 5 status
|       capital.verify   verifies or rejects each deployment the enterprise recorded (cannot record one itself)
|     Finance
|       disburse.process releases tranches, in order, once the transaction is cleared (payment reference + date + proof of payment)
|     Everyone else who can see the EOI: read-only.
|   Rules:
|     Starts automatically once Gate 4 = Authorised (conditions carried over from Gate 4).
|     "Cleared for disbursement" only when every blocker is resolved (see ConditionReview::clearanceBlockers()).
|     After clearance the set-up is locked; capital can still be recorded/verified and tranches released in order.
|     Tranches are released one by one: they don't have to add up to the grant before clearance (only never exceed it).
|     After clearance the PMU or Finance can add the next tranche; the page shows what is still to be disbursed.
|     Investor capital per tranche is handled outside the system — tranches have no "investor capital needed".
|   Files: downloaded through route "gate5.file" (Gate5FileController).
|
|   E-mails (sent after the change is saved; a failed e-mail never undoes it):
|     Enterprise uploads evidence       → PMU (gate5.verify): Gate5StaffAlert evidence_uploaded
|     Enterprise records capital        → PMU (capital.verify): Gate5StaffAlert capital_recorded
|     Capital verified / not accepted   → applicant: CapitalDeploymentReviewed
|     Summary of obligations sent       → applicant: ObligationsSummary
|     Gate 5 status recorded            → applicant: Gate5Outcome · cleared → Finance + PMU: Gate5StaffAlert cleared
|     Tranche released                  → applicant + PMU: TrancheReleased
*/
new class extends Component
{
    use WithFileUploads;

    #[Locked] public int $eoiId;
    #[Locked] public ?int $reviewId = null;

    /** [id, area, finding, source, status, note] */
    public array $conds = [];
    /** [id, kind, title, parties, required, status, signed_on] */
    public array $agreements = [];
    /** [id, label, amount, trigger] */
    public array $tranches = [];

    public array $checks = ['verification' => [], 'clauses' => [], 'readiness' => []];
    public array $budget = [];
    public bool $editBudget = false;   // the budget comes from the enterprise profile; the PMU opens it for editing only if needed
    public array $onboarding = [];

    /** Uploads: condition id => file, agreement id => file */
    public array $evidenceFiles = [];
    public array $agreementFiles = [];

    /** Proof of payment per profile investor (row index => file) — investor and amount come from the profile */
    public array $investorFiles = [];
    public array $rejectNote = [];

    /** Release (Finance): tranche id => [reference, on] — keyed by tranche id, filled in load() */
    public array $release = [];

    /** Release (Finance): tranche id => proof of payment (required to release) */
    public array $releaseProof = [];

    /** Outcome */
    public array $decision = ['outcome' => null, 'comments' => '', 'on' => ''];

    /** Area for a new condition */
    public string $newArea = 'legal';

    public ?string $savedAt = null;

    public function mount(int $eoiId): void
    {
        $eoi = ExpressionOfInterest::findOrFail($eoiId);
        abort_unless($eoi->isViewableBy(auth()->user()), 403);

        $this->eoiId = $eoi->id;

        // Gate 5 starts automatically once Gate 4 authorised the transaction
        if (in_array($eoi->status, ExpressionOfInterest::GATE5_VISIBLE, true) && ! $eoi->conditionReview()->exists()) {
            DB::transaction(fn () => ConditionReview::start($eoi));
        }

        // Gate 5 opened before the enterprise had a budget on its profile → take it now (once, while still empty)
        $review  = $eoi->conditionReview()->first();
        $profile = EnterpriseProfile::where('user_id', $eoi->user_id)->first();
        if ($review && $profile?->grant_budget && array_sum($review->budgetTotals()) <= 0 && ! $review->isCleared()) {
            $review->update(['budget' => $profile->budgetRows()]);
        }

        $this->load();
    }

    /** PMU: replace the Gate 5 budget with the enterprise's current profile budget */
    public function reloadBudgetFromProfile(): void
    {
        abort_unless($this->canManage && $this->profile?->grant_budget, 403);

        ConditionReview::whereKey($this->reviewId)->update(['budget' => json_encode($this->profile->budgetRows())]);
        $this->load();
        $this->editBudget = false;
        $this->saved();
        $this->dispatch('notify', type: 'success', message: 'Budget reloaded from the enterprise profile.');
    }

    /* =====================================================================
     | Data
     ===================================================================== */

    #[Computed] public function eoi(): ExpressionOfInterest
    {
        return ExpressionOfInterest::with('user:id,name,email')->findOrFail($this->eoiId);
    }

    #[Computed] public function review(): ?ConditionReview
    {
        return $this->reviewId
            ? ConditionReview::with(['conditions.verifier:id,name', 'agreements', 'deployments.verifier:id,name', 'deployments.recorder:id,name', 'tranches.releaser:id,name', 'decider:id,name'])->find($this->reviewId)
            : null;
    }

    #[Computed] public function profile(): ?EnterpriseProfile
    {
        return EnterpriseProfile::where('user_id', $this->eoi->user_id)->first();
    }

    #[Computed] public function isApplicant(): bool { return $this->eoi->user_id === auth()->id(); }

    #[Computed] public function gateOpen(): bool { return $this->reviewId !== null; }

    #[Computed] public function cleared(): bool { return (bool) $this->review?->isCleared(); }

    #[Computed] public function inGate5(): bool
    {
        return in_array($this->eoi->status, ExpressionOfInterest::GATE5_VISIBLE, true);
    }

    /** PMU set-up: conditions, agreements, checks, budget, tranches, onboarding, outcome */
    #[Computed] public function canManage(): bool
    {
        return ! $this->isApplicant && $this->inGate5 && ! $this->cleared && auth()->user()->can('gate5.verify');
    }

    /** Evidence against conditions: the enterprise only (the PMU verifies it, it doesn't upload it) */
    #[Computed] public function canUploadEvidence(): bool
    {
        return $this->isApplicant && $this->inGate5 && ! $this->cleared && auth()->user()->can('gate5.evidence');
    }

    /** PMU: verify / reject a deployment the enterprise recorded */
    #[Computed] public function canVerifyCapital(): bool
    {
        return ! $this->isApplicant && $this->inGate5 && auth()->user()->can('capital.verify');
    }

    /** Evidence of investor funds: the enterprise only */
    #[Computed] public function canRecordCapital(): bool
    {
        return $this->isApplicant && $this->inGate5
            && $this->eoi->status !== ExpressionOfInterest::STATUS_ESCALATED
            && auth()->user()->can('capital.upload');
    }

    /**
     * Tranche schedule: the PMU sets it up before clearance. After clearance the PMU or Finance can keep adding
     * the next tranche (one by one) until the whole grant is disbursed — no need to plan every tranche up front.
     */
    #[Computed] public function canEditTranches(): bool
    {
        if ($this->isApplicant || ! $this->inGate5) {
            return false;
        }
        return $this->canManage || ($this->cleared && auth()->user()->canAny(['gate5.verify', 'disburse.process']));
    }

    /** Finance only, once cleared */
    #[Computed] public function canRelease(): bool
    {
        return ! $this->isApplicant && $this->cleared && auth()->user()->can('disburse.process');
    }

    /** Which of the Gate 5 roles the signed-in user has here (for the "Who does what" panel) */
    #[Computed] public function myRoles(): array
    {
        $u = auth()->user();

        return array_keys(array_filter([
            'enterprise' => $this->isApplicant,
            'pmu'        => ! $this->isApplicant && $u->canAny(['gate5.verify', 'capital.verify']),
            'finance'    => ! $this->isApplicant && $u->can('disburse.process'),
        ]));
    }

    #[Computed] public function blockers(): array
    {
        return $this->review?->clearanceBlockers() ?? [];
    }

    /**
     * The investors to collect proof for, taken from the enterprise profile ("Investors in the round"):
     *   [index => ['name', 'country', 'amount' (Maloti), 'deployment' (latest record for this investor, or null)]]
     * No investors on the profile → the EOI investor with the approved private investment.
     */
    #[Computed] public function profileInvestors(): array
    {
        $rows = collect($this->profile?->investorRows() ?? [])->map(fn ($r) => [
            'name'    => trim((string) $r['name']),
            'country' => $r['country'] ?? null,
            'amount'  => is_numeric($r['ticket'] ?? null) ? (float) $r['ticket'] : null,
        ]);

        if ($rows->isEmpty() && filled($this->eoi->investor_name)) {
            $rows = collect([[
                'name'    => trim($this->eoi->investor_name),
                'country' => null,
                'amount'  => $this->review?->private_amount !== null ? (float) $this->review->private_amount : null,
            ]]);
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
        $r = ConditionReview::with('conditions', 'agreements', 'tranches')->where('expression_of_interest_id', $this->eoiId)->first();

        $this->reviewId = $r?->id;
        if (! $r) {
            return;
        }

        $this->conds = $r->conditions->map(fn ($c) => $c->only(['id', 'area', 'finding', 'source', 'status', 'note']))->values()->all();
        $this->agreements = $r->agreements->map(fn ($a) => [
            'id' => $a->id, 'kind' => $a->kind, 'title' => $a->title, 'parties' => (string) $a->parties,
            'required' => (bool) $a->required, 'status' => $a->status, 'signed_on' => $a->signed_on?->toDateString(),
        ])->values()->all();
        $this->tranches = $r->tranches->map(fn ($t) => [
            'id' => $t->id, 'label' => $t->label, 'amount' => $t->amount !== null ? (float) $t->amount : null,
            'trigger' => (string) $t->trigger,
        ])->values()->all();

        $map = fn (array $defs, ?array $saved) => collect($defs)->mapWithKeys(fn ($l, $k) => [$k => (bool) ($saved[$k] ?? false)])->all();
        $this->checks = [
            'verification' => $map(ConditionReview::VERIFICATION_CHECKS, $r->verification_checks),
            'clauses'      => $map(ConditionReview::GRANT_CLAUSES, $r->grant_clauses),
            'readiness'    => $map(ConditionReview::READINESS_CHECKS, $r->readiness_checks),
        ];
        $this->budget = collect($r->budget ?? [])->map(fn ($b) => [
            'category' => (string) ($b['category'] ?? ''),
            'private'  => ($b['private'] ?? null) !== null && $b['private'] !== '' ? (float) $b['private'] : null,
            'grant'    => ($b['grant'] ?? null) !== null && $b['grant'] !== '' ? (float) $b['grant'] : null,
        ])->values()->all();
        $o = (array) ($r->onboarding ?? []);
        $this->onboarding = [
            'contact'         => (string) ($o['contact'] ?? ''),
            'frequency'       => $o['frequency'] ?? 'quarterly',
            'first_report_on' => $o['first_report_on'] ?? null,
            'items'           => $map(ConditionReview::ONBOARDING_ITEMS, $o['items'] ?? []),
        ];
        $this->decision = [
            'outcome'  => $r->outcome,
            'comments' => (string) $r->outcome_comments,
            'on'       => $r->outcome_on?->toDateString() ?? now()->toDateString(),
        ];

        // Release form per unreleased tranche (pre-created so the inputs always bind; keeps what Finance already typed)
        $release = [];
        foreach ($r->tranches->whereNull('released_on') as $t) {
            $release[$t->id] = [
                'reference' => (string) ($this->release[$t->id]['reference'] ?? ''),
                'on'        => (string) (($this->release[$t->id]['on'] ?? '') ?: now()->toDateString()),
            ];
        }
        $this->release = $release;

        unset($this->review, $this->blockers, $this->cleared);
    }

    /* =====================================================================
     | Autosave (PMU set-up)
     ===================================================================== */

    public function updated(string $name): void
    {
        if (! Str::startsWith($name, ['conds', 'agreements', 'tranches', 'checks', 'budget', 'onboarding'])) {
            return;
        }
        if (Str::startsWith($name, 'tranches')) {
            $this->canEditTranches ? $this->syncTranches() : $this->load();
            $this->saved();
            return;
        }
        if (! $this->canManage) {
            $this->load();   // discard changes from someone who can't edit
            return;
        }

        $r = ConditionReview::findOrFail($this->reviewId);

        match (true) {
            Str::startsWith($name, 'conds')      => $this->syncConditions(),
            Str::startsWith($name, 'agreements') => $this->syncAgreements($name),
            Str::startsWith($name, 'tranches')   => $this->syncTranches(),
            default                              => $r->update([
                'verification_checks' => $this->checks['verification'],
                'grant_clauses'       => $this->checks['clauses'],
                'readiness_checks'    => $this->checks['readiness'],
                'budget'              => collect($this->budget)->map(fn ($b) => [
                    'category' => Str::limit(trim((string) $b['category']), 100, ''),
                    'private'  => is_numeric($b['private'] ?? null) ? max(0, (float) $b['private']) : null,
                    'grant'    => is_numeric($b['grant'] ?? null) ? max(0, (float) $b['grant']) : null,
                ])->values()->all(),
                'onboarding'          => [
                    'contact'         => Str::limit(trim($this->onboarding['contact']), 250, ''),
                    'frequency'       => array_key_exists($this->onboarding['frequency'], ConditionReview::FREQUENCIES) ? $this->onboarding['frequency'] : 'quarterly',
                    'first_report_on' => $this->onboarding['first_report_on'] ?: null,
                    'items'           => $this->onboarding['items'],
                ],
            ]),
        };

        $this->saved();
    }

    protected function syncConditions(): void
    {
        foreach ($this->conds as $row) {
            CpCondition::where('condition_review_id', $this->reviewId)->whereKey($row['id'])->update([
                'area'    => array_key_exists($row['area'], ConditionReview::AREAS) ? $row['area'] : 'transaction',
                'finding' => Str::limit(trim((string) $row['finding']), 250, '') ?: '—',
                'note'    => Str::limit(trim((string) $row['note']), 500, '') ?: null,
            ]);
        }
    }

    protected function syncAgreements(string $name): void
    {
        foreach ($this->agreements as $i => $row) {
            $a = CpAgreement::where('condition_review_id', $this->reviewId)->findOrFail($row['id']);

            // "Executed" needs the signed copy and the signing date
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

    /** Tranche label, amount and release trigger (released tranches can't change) */
    /** Grant still free to put into a tranche (grant − all tranches, optionally ignoring one) */
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
            $amount = is_numeric($row['amount'] ?? null) ? max(0, (float) $row['amount']) : null;

            // The tranches together may not go over the approved grant
            $room = $this->unscheduled($row['id']);
            if ($amount !== null && $amount > $room + 0.01) {
                $this->addError("tranches.$i.amount", 'Only M '.number_format($room).' of the grant is left — the amount was capped.');
                $amount = $room;
                $this->tranches[$i]['amount'] = $room;
            }

            DisbursementTranche::where('condition_review_id', $this->reviewId)->whereKey($row['id'])->whereNull('released_on')->update([
                'position' => $i,
                'label'    => Str::limit(trim((string) $row['label']), 20, '') ?: 'T'.($i + 1),
                'amount'   => $amount,
                'trigger'  => Str::limit(trim((string) $row['trigger']), 250, '') ?: null,
            ]);
        }
        unset($this->review, $this->blockers);
    }

    /* =====================================================================
     | §3.6.1 Conditions precedent
     ===================================================================== */

    public function addCondition(): void
    {
        abort_unless($this->canManage, 403);
        $area = $this->newArea;

        CpCondition::create([
            'condition_review_id' => $this->reviewId,
            'area'     => array_key_exists($area, ConditionReview::AREAS) ? $area : 'legal',
            'finding'  => ConditionReview::INDICATIVE[$area] ?? 'New condition',
            'source'   => 'g5',
            'position' => count($this->conds),
        ]);
        $this->load();
        $this->saved();
    }

    public function removeCondition(int $id): void
    {
        abort_unless($this->canManage, 403);

        $c = CpCondition::where('condition_review_id', $this->reviewId)->findOrFail($id);
        abort_unless($c->source === 'g5', 403, 'Conditions carried from Gates 2–4 can be waived, not removed.');
        $c->delete();

        $this->load();
        $this->saved();
    }

    /** met | waived | outstanding (PMU) */
    public function setConditionStatus(int $id, string $status): void
    {
        abort_unless($this->canManage && array_key_exists($status, ConditionReview::CONDITION_STATUSES), 403);

        $i = collect($this->conds)->search(fn ($c) => $c['id'] === $id);
        $c = CpCondition::where('condition_review_id', $this->reviewId)->findOrFail($id);

        if ($status === 'met' && ! $c->evidence_path && blank($c->note)) {
            $this->addError("conds.$i.status", 'Upload evidence or add a note before marking this met.');
            return;
        }
        if ($status === 'waived' && blank($c->note)) {
            $this->addError("conds.$i.status", 'Add a note explaining why it is waived.');
            return;
        }

        $c->update([
            'status'      => $status,
            'verified_by' => $status === 'outstanding' ? null : auth()->id(),
            'verified_at' => $status === 'outstanding' ? null : now(),
        ]);

        $this->load();
        $this->saved();
    }

    public function updatedEvidenceFiles($file, $key): void
    {
        abort_unless($this->canUploadEvidence, 403);

        $c = CpCondition::where('condition_review_id', $this->reviewId)->findOrFail((int) $key);
        abort_if($c->isDone(), 403, 'This condition is already closed.');

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
        ]);

        unset($this->evidenceFiles[$key]);
        $this->load();

        // The enterprise uploaded it → tell the PMU there is something to verify
        if ($this->isApplicant) {
            $this->staffAlert(NotifyRecipients::withPermission('gate5.verify'), 'evidence_uploaded', 'the PMU',
                ['condition' => $c->finding, 'file' => $c->evidence_name]);
        }

        $this->dispatch('notify', type: 'success', message: 'Evidence uploaded'.($this->isApplicant ? ' — the LEHSFF team will verify it.' : '.'));
    }

    /* =====================================================================
     | §3.6.3 Agreements
     ===================================================================== */

    public function addAgreement(): void
    {
        abort_unless($this->canManage, 403);
        CpAgreement::create(['condition_review_id' => $this->reviewId, 'kind' => 'other', 'title' => 'Other agreement', 'required' => false]);
        $this->load();
        $this->saved();
    }

    public function removeAgreement(int $id): void
    {
        abort_unless($this->canManage, 403);
        $a = CpAgreement::where('condition_review_id', $this->reviewId)->findOrFail($id);
        abort_unless($a->kind === 'other', 403, 'The Grant and Investment Agreements can be marked not required, but not removed.');
        $a->delete();
        $this->load();
        $this->saved();
    }

    public function updatedAgreementFiles($file, $key): void
    {
        abort_unless($this->canManage, 403);

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
        $this->load();
        $this->dispatch('notify', type: 'success', message: $a->title.' uploaded.');
    }

    /* =====================================================================
     | §3.6.2 Investor capital deployment
     ===================================================================== */

    /**
     * The enterprise attaches proof of payment for one of its profile investors.
     * Investor and amount are taken from the profile; the record is dated today and goes to the PMU to verify.
     */
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
        if (in_array($row['deployment']?->status, ['submitted', 'verified'], true)) {
            $this->addError("investorFiles.$i", 'Proof for this investor is already '.($row['deployment']->status === 'verified' ? 'verified' : 'waiting for verification').'.');
            return;
        }

        $deployment = CapitalDeployment::create([
            'condition_review_id' => $this->reviewId,
            'investor'            => $row['name'],
            'amount'              => $row['amount'],
            'deployed_on'         => now()->toDateString(),
            'evidence_path'       => $file->store("gate5/{$this->reviewId}/capital", ConditionReview::DISK),
            'evidence_name'       => $file->getClientOriginalName(),
            'evidence_size'       => $file->getSize(),
            'recorded_by'         => auth()->id(),
        ]);

        unset($this->investorFiles[$i], $this->review, $this->blockers, $this->profileInvestors);

        // Tell whoever verifies capital
        $this->staffAlert(NotifyRecipients::withPermission('capital.verify'), 'capital_recorded', 'the PMU', [
            'investor' => $deployment->investor, 'amount' => $deployment->amount, 'on' => $deployment->deployed_on?->format('d M Y'),
        ]);

        $this->dispatch('notify', type: 'success', message: 'Proof of payment for '.$deployment->investor.' attached — the LEHSFF team will verify it.');
    }

    public function verifyDeployment(int $id): void
    {
        abort_unless($this->canVerifyCapital, 403);

        $d = CapitalDeployment::where('condition_review_id', $this->reviewId)->findOrFail($id);
        abort_unless($d->status === 'submitted', 403, 'This deployment has already been reviewed.');
        $d->update(['status' => 'verified', 'verified_by' => auth()->id(), 'verified_at' => now(), 'note' => null]);

        unset($this->review, $this->blockers);
        $note = $this->emailApplicant(fn ($eoi, $r) => new CapitalDeploymentReviewed($eoi, $r, $d->fresh()));
        $this->dispatch('notify', type: 'success', message: 'Deployment verified. '.$note);
    }

    public function rejectDeployment(int $id): void
    {
        abort_unless($this->canVerifyCapital, 403);

        $this->validate(["rejectNote.$id" => ['required', 'string', 'max:500']], ["rejectNote.$id.required" => 'Say why it is not accepted.']);

        $d = CapitalDeployment::where('condition_review_id', $this->reviewId)->findOrFail($id);
        abort_unless($d->status === 'submitted', 403, 'This deployment has already been reviewed.');
        $d->update(['status' => 'rejected', 'verified_by' => auth()->id(), 'verified_at' => now(), 'note' => trim($this->rejectNote[$id])]);

        unset($this->rejectNote[$id], $this->review, $this->blockers);
        $note = $this->emailApplicant(fn ($eoi, $r) => new CapitalDeploymentReviewed($eoi, $r, $d->fresh()));
        $this->dispatch('notify', type: 'success', message: 'Deployment marked as not accepted. '.$note);
    }

    public function removeDeployment(int $id): void
    {
        // Only the enterprise that recorded it, and only before it is verified (the PMU rejects instead of deleting)
        $d = CapitalDeployment::where('condition_review_id', $this->reviewId)->findOrFail($id);
        abort_unless($this->isApplicant && $d->status !== 'verified' && $d->recorded_by === auth()->id(), 403);

        $d->delete();
        unset($this->review, $this->blockers, $this->profileInvestors);
    }

    /* =====================================================================
     | §3.6.4 Tranches
     ===================================================================== */

    public function addTranche(): void
    {
        abort_unless($this->canEditTranches, 403);

        $left = $this->unscheduled();
        if ($left <= 0.01) {
            $this->dispatch('notify', type: 'warning', message: 'The whole grant is already in tranches — there is nothing left to schedule.');
            return;
        }

        DisbursementTranche::create([
            'condition_review_id' => $this->reviewId,
            'position'            => count($this->tranches),
            'label'               => 'T'.(count($this->tranches) + 1),
            'amount'              => $left,   // pre-filled with what's left — lower it to release in smaller tranches
            'trigger'             => count($this->tranches) === 0 ? 'All conditions precedent met' : null,
        ]);
        $this->load();
        $this->syncTranches();
        $this->saved();
    }

    public function removeTranche(int $id): void
    {
        abort_unless($this->canEditTranches, 403);
        DisbursementTranche::where('condition_review_id', $this->reviewId)->whereNull('released_on')->findOrFail($id)->delete();
        $this->load();
        $this->syncTranches();
        $this->saved();
    }

    /** Finance: release a tranche (in order, once the transaction is cleared) — proof of payment required */
    public function releaseTranche(int $id): void
    {
        abort_unless($this->canRelease, 403);

        $review = $this->review;
        $t      = $review?->tranches->firstWhere('id', $id);
        abort_unless($t && ! $t->isReleased(), 404);

        $fail = function (string $message) use ($id) {
            $this->addError("release.$id.reference", $message);
            $this->dispatch('notify', type: 'error', message: $message);
        };

        $earlier = $review->tranches->where('position', '<', $t->position)->reject->isReleased();
        if ($earlier->isNotEmpty()) {
            $fail('Release '.$earlier->pluck('label')->implode(', ').' first.');
            return;
        }
        if ((float) $t->amount <= 0) {
            $fail('Enter an amount for '.$t->label.' first.');
            return;
        }
        $releasedSoFar = (float) $review->tranches->filter->isReleased()->sum('amount');
        if ($releasedSoFar + (float) $t->amount > (float) $review->grant_amount + 0.01) {
            $fail('Only M '.number_format(max(0, $review->grant_amount - $releasedSoFar)).' of the grant is still to be disbursed.');
            return;
        }

        try {
            $this->validate([
                "release.$id.reference" => ['required', 'string', 'max:100'],
                "release.$id.on"        => ['required', 'date', 'before_or_equal:today'],
                "releaseProof.$id"      => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:20480'],
            ], [
                "release.$id.reference.required" => 'Enter the payment reference.',
                "release.$id.on.required"        => 'Enter the release date.',
                "release.$id.on.before_or_equal" => 'The release date can\'t be in the future.',
                "releaseProof.$id.required"      => 'Attach the proof of payment (bank confirmation / remittance advice).',
                "releaseProof.$id.mimes"         => 'Proof of payment must be a PDF or a scan / photo (JPG, PNG).',
                "releaseProof.$id.max"           => 'Proof of payment must be 20 MB or smaller.',
            ]);
        } catch (ValidationException $e) {
            $this->dispatch('notify', type: 'error', message: $t->label.' not released: '.collect($e->errors())->flatten()->implode(' '));
            throw $e;
        }

        $file = $this->releaseProof[$id];

        $t->update([
            'reference'   => trim($this->release[$id]['reference']),
            'released_on' => $this->release[$id]['on'],
            'released_by' => auth()->id(),
            'proof_path'  => $file->store("gate5/{$this->reviewId}/tranches", ConditionReview::DISK),
            'proof_name'  => $file->getClientOriginalName(),
            'proof_size'  => $file->getSize(),
        ]);

        unset($this->release[$id], $this->releaseProof[$id], $this->review, $this->blockers);

        // Applicant + PMU (the proof of payment is attached to the e-mail)
        $notes = [$this->emailApplicant(fn ($eoi, $r) => new TrancheReleased($eoi, $r, $t->fresh()))];
        $eoi   = ExpressionOfInterest::find($this->eoiId);
        $notes[] = NotifyRecipients::send(NotifyRecipients::withPermission('gate5.verify'),
            new TrancheReleased($eoi, ConditionReview::findOrFail($this->reviewId), $t->fresh()), 'the PMU', except: auth()->id());

        $this->dispatch('gate-decided', gate: 'g5', decision: null);
        $this->dispatch('notify', type: 'success', message: "{$t->label} released. ".implode(' ', array_filter($notes)));
    }

    /* =====================================================================
     | §3.6.5 Onboarding
     ===================================================================== */

    public function sendSummary(): void
    {
        abort_unless($this->canManage, 403);

        if (blank($this->onboarding['contact']) || in_array(false, $this->onboarding['items'], true)) {
            $this->addError('onboarding.contact', 'Name the contact person and agree every arrangement before sending the summary.');
            return;
        }

        // E-mail it first — it is only recorded as sent if the e-mail went out
        $eoi       = ExpressionOfInterest::with('user')->find($this->eoiId);
        $applicant = $eoi?->user;
        if (! $applicant || blank($applicant->email)) {
            $this->addError('onboarding.contact', 'The applicant has no e-mail address — the summary could not be sent.');
            return;
        }
        try {
            $applicant->notify(new ObligationsSummary($eoi, ConditionReview::findOrFail($this->reviewId)));
        } catch (\Throwable $e) {
            report($e);
            $this->addError('onboarding.contact', 'The summary could not be e-mailed — try again.'.NotifyRecipients::debugReason($e));
            return;
        }

        ConditionReview::whereKey($this->reviewId)->update(['summary_sent_at' => now(), 'summary_sent_by' => auth()->id()]);

        unset($this->review, $this->blockers);
        $this->dispatch('notify', type: 'success', message: 'Summary of obligations e-mailed to '.$applicant->email.'.');
    }

    /* =====================================================================
     | §3.6.6 Outcome
     ===================================================================== */

    public function recordOutcome(): void
    {
        abort_unless($this->canManage, 403);

        $this->validate([
            'decision.outcome'  => ['required', Rule::in(array_keys(ConditionReview::OUTCOMES))],
            'decision.comments' => [($this->decision['outcome'] ?? null) === ConditionReview::OUTCOME_CLEARED ? 'nullable' : 'required', 'string', 'max:3000'],
            'decision.on'       => ['required', 'date', 'before_or_equal:today'],
        ], [
            'decision.outcome.required'  => 'Choose the Gate 5 status.',
            'decision.comments.required' => 'Say what is outstanding, who is responsible and by when.',
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
            $this->eoi->update(['status' => ConditionReview::OUTCOME_TO_EOI_STATUS[$outcome]]);
        });

        unset($this->eoi, $this->review, $this->cleared, $this->inGate5, $this->canManage, $this->canEditTranches, $this->canUploadEvidence, $this->canRelease, $this->blockers);

        // E-mails (after the status is saved)
        $notes = [$this->emailApplicant(fn ($eoi, $r) => new Gate5Outcome($eoi, $r))];
        if ($outcome === ConditionReview::OUTCOME_CLEARED) {
            $notes[] = $this->staffAlert(
                NotifyRecipients::finance()->merge(NotifyRecipients::withPermission('gate5.verify')),
                'cleared', 'Finance and the PMU',
            );
        }

        $this->dispatch('gate-decided', gate: 'g5', decision: $outcome);
        $this->dispatch('notify', type: 'success', message: 'Gate 5 status recorded: '.ConditionReview::OUTCOMES[$outcome][0].'. '.implode(' ', array_filter($notes)));
    }

    /* =====================================================================
     | E-mails
     ===================================================================== */

    /** Send one Gate 5 e-mail to the applicant; $make(eoi, review) builds it. Returns a note for the officer. */
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

            return 'The e-mail to the applicant could not be sent — please let them know.'.NotifyRecipients::debugReason($e);
        }
    }

    /** Staff alert (Gate5StaffAlert) to a group, leaving out the person who triggered it */
    protected function staffAlert($users, string $event, string $who, array $data = []): string
    {
        $eoi    = ExpressionOfInterest::find($this->eoiId);
        $review = $this->reviewId ? ConditionReview::find($this->reviewId) : null;

        if (! $eoi || ! $review) {
            return '';
        }

        return NotifyRecipients::send(collect($users)->unique('id')->values(), new Gate5StaffAlert($eoi, $review, $event, $data), $who, except: auth()->id());
    }

    /* =====================================================================
     | Internals
     ===================================================================== */

    protected function saved(): void
    {
        $this->savedAt = now()->format('H:i');
        unset($this->review, $this->blockers);
    }

    #[On('gate-decided')]
    public function refreshGate(): void
    {
        unset($this->eoi, $this->inGate5, $this->cleared, $this->canManage, $this->canEditTranches, $this->canUploadEvidence, $this->canVerifyCapital, $this->canRecordCapital, $this->canRelease, $this->myRoles);
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
    $canTr     = $this->canEditTranches;
    $rate      = (float) config('cif.usd_to_lsl', 18.00);
    $m         = fn ($v) => $v === null || $v === '' ? '—' : 'M '.number_format((float) $v);
    $u         = fn ($v) => $v === null || $v === '' ? '' : 'US$ '.number_format((float) $v / max($rate, 0.0001));
    $size      = fn ($b) => $b >= 1048576 ? round($b / 1048576, 1).' MB' : max(1, round($b / 1024)).' KB';
@endphp

{{-- ======================================================================
     LOCKED
     ====================================================================== --}}
@if (! $this->gateOpen)
    <section class="section-card">
        <div class="section-card-head">
            <span class="section-no">G5</span>
            <h5 class="me-auto">Conditions precedent, Grant Agreement &amp; onboarding</h5>
            <span class="pill pill-muted"><i class="bi bi-lock-fill"></i> Locked</span>
        </div>
        <p class="small text-muted mb-0">Opens once the transaction receives final authorisation at Gate 4.
            No CAFI grant is disbursed until the conditions are met, the agreements are signed and the investor's capital has been deployed and verified.</p>
    </section>
@else
@php
    $s         = $r->summary();
    $verified  = $s['capital'];
    $private   = (float) $r->private_amount;
    $grant     = (float) $r->grant_amount;
    $capPct    = $private > 0 ? min(100, (int) round($verified / $private * 100)) : 0;
    $outcome   = $r->outcome;
    $blockers  = $applicant ? [] : $this->blockers;
    $suggested = $r->suggestedOutcome();
    $released  = $r->tranches->filter->isReleased();
    $budgetT   = $r->budgetTotals();

    $state = fn (bool $done, bool $started = true) => $done ? 'done' : ($started ? 'current' : 'todo');
    $path = [
        ['Gate 4 · Authorised', 'done'],
        ['Conditions precedent', $state($s['conditions_done'] === $s['conditions_total'])],
        ['Agreements', $state($s['agreements_done'] === $s['agreements_total'])],
        ['Investor capital', $state($verified > 0, true)],
        ['Disbursement readiness', $state($r->isCleared(), $verified > 0)],
        ['Onboarding', $state((bool) $r->summary_sent_at, $verified > 0)],
        ['Disbursement & monitoring', $released->isNotEmpty() ? 'current' : 'todo'],
    ];
    $headPill = $outcome
        ? [$CR::OUTCOMES[$outcome][0], $CR::OUTCOMES[$outcome][4], $CR::OUTCOMES[$outcome][3]]
        : ['In progress', 'pill-warn', 'bi-hourglass-split'];
@endphp

    {{-- ============================ PATH (§3.6.7) ============================ --}}
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
            <span class="section-no">G5</span>
            <div class="me-auto">
                <h5>Conditions precedent, Grant Agreement &amp; onboarding</h5>
                <div class="small text-muted">
                    @if ($applicant) What still needs to happen before the CAFI grant can be paid.
                    @else Turns an authorised transaction into an implementation-ready one — the final control point before CAFI grant disbursement. @endif
                </div>
            </div>
            <span class="pill {{ $headPill[1] }}"><i class="bi {{ $headPill[2] }}"></i> {{ $headPill[0] }}</span>
        </div>

        @if ($canManage)
            <div class="small text-muted mb-3">
                <i class="bi bi-cloud-check me-1"></i> Changes are saved automatically. @if ($savedAt) <span class="text-accent">Last saved {{ $savedAt }}.</span> @endif
                <span wire:loading class="ms-1"><span class="spinner-border spinner-border-sm"></span></span>
            </div>
        @elseif ($r->isCleared() && ! $applicant)
            <div class="small text-muted mb-3"><i class="bi bi-lock me-1"></i> Cleared on {{ $r->cleared_at?->format('d M Y') }} — the set-up is locked. Investor capital can still be recorded and tranches released.</div>
        @endif

        <div class="detail-grid">
            <div><dt>Transaction reference</dt><dd><span class="chip">{{ $eoi->reference }}</span></dd></div>
            <div><dt>Enterprise</dt><dd>{{ $this->profile?->registered_name ?: ($eoi->business_name ?: '—') }}</dd></div>
            <div><dt>Authorised on</dt><dd>{{ $r->authorisation?->decided_at?->format('d M Y') ?? '—' }}</dd></div>
            <div><dt>Approved CAFI grant</dt><dd>{{ $m($grant) }} <div class="small text-muted">{{ $u($grant) }}</div></dd></div>
            <div><dt>Approved private investment</dt><dd>{{ $m($private) }} <div class="small text-muted">{{ $u($private) }}</div></dd></div>
            <div><dt>Instrument</dt><dd>{{ \App\Models\DueDiligence::INSTRUMENTS[$r->instrument ?? ''] ?? '—' }}</dd></div>
        </div>

        <div class="row g-2 mt-3">
            @foreach ([
                ['Conditions precedent', $s['conditions_done'].' / '.$s['conditions_total'].' met', 'bi-list-check',   $s['conditions_done'] === $s['conditions_total'] ? 'accent' : 'warning'],
                ['Agreements',           $s['agreements_done'].' / '.$s['agreements_total'].' executed', 'bi-pen', $s['agreements_done'] === $s['agreements_total'] ? 'accent' : 'warning'],
                ['Investor capital',     $m($verified).' of '.$m($private), 'bi-shield-check', $verified >= $private && $private > 0 ? 'accent' : 'warning'],
                ['Onboarding',           $r->summary_sent_at ? 'Summary sent' : 'Not completed', 'bi-person-check', $r->summary_sent_at ? 'accent' : 'muted'],
            ] as [$sLabel, $sValue, $sIcon, $sTone])
                <div class="col-6 col-lg-3">
                    <div class="stat-card">
                        <span class="stat-icon tone-{{ $sTone }}"><i class="bi {{ $sIcon }}"></i></span>
                        <div><div class="small text-muted">{{ $sLabel }}</div><div class="fw-bold text-navy">{{ $sValue }}</div></div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="callout small mt-3 mb-0">
            <i class="bi bi-info-circle me-1"></i>
            Final authorisation does not create an entitlement to disbursement. <strong>Private investor capital must be deployed and verified before any CAFI grant is disbursed.</strong>
        </div>
    </section>

    {{-- ============================ WHO DOES WHAT ============================ --}}
    @php
        $mine  = $this->myRoles;
        $roles = [
            'enterprise' => ['Enterprise', 'bi-shop', [
                'Uploads evidence for each outstanding condition',
                'Records the investor capital deployed, with proof of payment',
                'Receives the summary of obligations and tranche e-mails',
            ]],
            'pmu' => ['PMU', 'bi-people', [
                'Verifies evidence — marks conditions met or waived',
                'Verifies or rejects the investor capital the enterprise recorded',
                'Uploads the signed agreements; sets up the budget, tranches and onboarding',
                'Records the Gate 5 status (clears for disbursement)',
            ]],
            'finance' => ['Finance', 'bi-cash-stack', [
                'Releases each tranche, in order, after clearance',
                'Records the payment reference and date',
            ]],
        ];
    @endphp
    <details class="section-card who-does-what" @if (! $applicant && empty($mine)) open @endif>
        <summary class="d-flex align-items-center gap-2 small fw-semibold text-navy" style="cursor: pointer; list-style: none;">
            <i class="bi bi-people-fill text-brand"></i> Who does what at Gate 5
            @if ($mine)
                <span class="ms-auto small text-muted fw-normal">You act as:
                    @foreach ($mine as $mr) <span class="pill pill-ok ms-1">{{ $roles[$mr][0] }}</span> @endforeach
                </span>
            @else
                <span class="ms-auto pill pill-muted"><i class="bi bi-eye"></i> Read-only</span>
            @endif
        </summary>
        <div class="row g-3 mt-1">
            @foreach ($roles as $rk => [$rName, $rIcon, $rTasks])
                <div class="col-md-4">
                    <div class="border rounded-3 p-3 h-100 {{ in_array($rk, $mine, true) ? 'border-2 border-success' : '' }}">
                        <div class="fw-semibold text-navy small mb-2"><i class="bi {{ $rIcon }} me-1"></i>{{ $rName }}
                            @if (in_array($rk, $mine, true)) <span class="pill pill-ok ms-1">You</span> @endif</div>
                        <ul class="evidence-list small text-muted mb-0">
                            @foreach ($rTasks as $task) <li>{{ $task }}</li> @endforeach
                        </ul>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="small text-muted mt-2"><i class="bi bi-shield-lock me-1"></i> Each action is limited to its role — for example, only the enterprise can upload proof of investor funds, and only Finance can release a tranche.</div>
    </details>

    {{-- ============================ 1 · CONDITIONS PRECEDENT (§3.6.1) ============================ --}}
    @php $openConds = $s['conditions_total'] - $s['conditions_done']; @endphp
    <section class="section-card">
        <div class="section-card-head">
            <span class="section-no">1</span>
            <div class="me-auto">
                <h5>Conditions precedent</h5>
                <div class="small text-muted">
                    @if ($applicant) Upload evidence for each outstanding condition — the LEHSFF team verifies it.
                    @else Transaction-specific conditions carried from Gates 2–4, plus any added now. Each needs evidence (or a note) before it is marked met. @endif
                    <div class="mt-1"><span class="pill pill-muted"><i class="bi bi-shop"></i> Enterprise uploads evidence</span> <span class="pill pill-muted"><i class="bi bi-people"></i> PMU marks met / waived</span></div>
                </div>
            </div>
            <span class="pill {{ $openConds ? 'pill-warn' : 'pill-ok' }}">{{ $openConds ? $openConds.' outstanding' : 'All met' }}</span>
        </div>

        @forelse ($r->conditions as $i => $c)
            @php [$stL, $stP] = $CR::CONDITION_STATUSES[$c->status]; $ci = collect($conds)->search(fn ($x) => $x['id'] === $c->id); @endphp
            <div class="border rounded-3 p-3 mb-2 {{ $c->status === 'outstanding' ? '' : 'bg-light' }}" wire:key="cp-{{ $c->id }}">
                <div class="d-flex flex-wrap align-items-start gap-2">
                    <div class="flex-grow-1" style="min-width: 240px">
                        @if ($canManage && $c->source === 'g5' && $ci !== false)
                            <div class="row g-2">
                                <div class="col-md-4">
                                    <select class="form-select form-select-sm" wire:model.live="conds.{{ $ci }}.area">
                                        @foreach ($CR::AREAS as $ak => $al) <option value="{{ $ak }}">{{ $al }}</option> @endforeach
                                    </select>
                                </div>
                                <div class="col-md-8"><input type="text" class="form-control form-control-sm" wire:model.blur="conds.{{ $ci }}.finding" maxlength="250"></div>
                            </div>
                        @else
                            <div class="d-flex flex-wrap gap-1 mb-1">
                                <span class="pill pill-muted">{{ $CR::AREAS[$c->area] ?? $c->area }}</span>
                                <span class="pill pill-muted">{{ ['g2' => 'Gate 2', 'g3' => 'Gate 3', 'g4' => 'Gate 4', 'g5' => 'Gate 5'][$c->source] ?? $c->source }}</span>
                            </div>
                            <div class="fw-semibold text-navy small">{{ $c->finding }}</div>
                        @endif
                    </div>
                    <span class="pill {{ $stP }}">{{ $stL }}</span>
                </div>

                <div class="d-flex flex-wrap align-items-center gap-2 mt-2 small">
                    @if ($c->evidence_path)
                        <a href="{{ route('gate5.file', ['condition', $c->id]) }}" class="text-decoration-none"><i class="bi bi-paperclip"></i> {{ \Illuminate\Support\Str::limit($c->evidence_name, 40) }}</a>
                        <span class="text-muted">· {{ $c->uploader?->name }} {{ $c->evidence_at?->format('d M Y') }}</span>
                    @else
                        <span class="text-muted"><i class="bi bi-paperclip"></i> No evidence yet{{ $applicant ? '' : ' — waiting for the enterprise to upload it' }}</span>
                    @endif
                    @if ($this->canUploadEvidence && ! $c->isDone())
                        <span wire:loading wire:target="evidenceFiles.{{ $c->id }}" class="text-muted"><span class="spinner-border spinner-border-sm"></span> Uploading…</span>
                        <label class="btn btn-sm btn-outline-secondary mb-0" wire:loading.remove wire:target="evidenceFiles.{{ $c->id }}">
                            <i class="bi bi-upload me-1"></i>{{ $c->evidence_path ? 'Replace' : 'Upload evidence' }}
                            <input type="file" class="d-none" wire:model="evidenceFiles.{{ $c->id }}">
                        </label>
                    @endif
                    @if ($c->verified_at && $c->isDone()) <span class="text-muted ms-auto"><i class="bi bi-person-check me-1"></i>{{ $c->verifier?->name }} · {{ $c->verified_at->format('d M Y') }}</span> @endif
                </div>
                @error("evidenceFiles.{$c->id}") <div class="text-danger small mt-1">{{ $message }}</div> @enderror

                @if ($canManage && $ci !== false)
                    <div class="d-flex flex-wrap align-items-center gap-2 mt-2">
                        <input type="text" class="form-control form-control-sm flex-grow-1" style="min-width: 200px; width: auto" wire:model.blur="conds.{{ $ci }}.note" maxlength="500" placeholder="Note (needed to waive, or to mark met without a file)">
                        <div class="btn-group btn-group-sm">
                            <button type="button" class="btn {{ $c->status === 'met' ? 'btn-success' : 'btn-outline-success' }}" wire:click="setConditionStatus({{ $c->id }}, 'met')"><i class="bi bi-check-lg me-1"></i>Met</button>
                            <button type="button" class="btn {{ $c->status === 'waived' ? 'btn-secondary' : 'btn-outline-secondary' }}" wire:click="setConditionStatus({{ $c->id }}, 'waived')">Waive</button>
                            @if ($c->isDone()) <button type="button" class="btn btn-outline-warning" wire:click="setConditionStatus({{ $c->id }}, 'outstanding')">Re-open</button> @endif
                        </div>
                        @if ($c->source === 'g5') <button type="button" class="btn btn-icon btn-icon-danger" wire:click="removeCondition({{ $c->id }})" wire:confirm="Remove this condition?" title="Remove"><i class="bi bi-trash"></i></button> @endif
                    </div>
                    @error("conds.$ci.status") <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                @elseif ($c->note)
                    <div class="small text-muted mt-1"><i class="bi bi-chat-left-text me-1"></i>{{ $c->note }}</div>
                @endif
            </div>
        @empty
            <p class="small text-muted">No conditions attached.</p>
        @endforelse

        @if ($canManage)
            <div class="d-flex flex-wrap gap-2 mt-2">
                <select class="form-select form-select-sm" style="width: auto" wire:model="newArea">
                    @foreach ($CR::AREAS as $ak => $al) <option value="{{ $ak }}">{{ $al }} — {{ \Illuminate\Support\Str::limit($CR::INDICATIVE[$ak], 45) }}</option> @endforeach
                </select>
                <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="addCondition"><i class="bi bi-plus-lg me-1"></i> Add condition</button>
            </div>
        @endif
    </section>

    {{-- ============================ 2 · AGREEMENTS (§3.6.3) ============================ --}}
    <section class="section-card">
        <div class="section-card-head">
            <span class="section-no">2</span>
            <div class="me-auto">
                <h5>Grant Agreement &amp; investment documentation</h5>
                <div class="small text-muted">Execution status of each required agreement — executed copies are kept on the transaction record.
                    <div class="mt-1"><span class="pill pill-muted"><i class="bi bi-people"></i> PMU uploads signed copies</span></div></div>
            </div>
            <span class="pill {{ $s['agreements_done'] === $s['agreements_total'] ? 'pill-ok' : 'pill-warn' }}">{{ $s['agreements_done'] }} / {{ $s['agreements_total'] }} executed</span>
        </div>

        @foreach ($r->agreements as $a)
            @php $ai = collect($agreements)->search(fn ($x) => $x['id'] === $a->id); [$asL, $asP] = $CR::AGREEMENT_STATUSES[$a->status]; @endphp
            <div class="border rounded-3 p-3 mb-2" wire:key="agr-{{ $a->id }}">
                @if ($canManage && $ai !== false)
                    <div class="row g-2 align-items-end">
                        <div class="col-md-4"><label class="form-label small fw-semibold mb-1">Agreement</label>
                            <input type="text" class="form-control form-control-sm" wire:model.blur="agreements.{{ $ai }}.title" maxlength="150" @readonly($a->kind !== 'other')></div>
                        <div class="col-md-4"><label class="form-label small fw-semibold mb-1">Parties</label>
                            <input type="text" class="form-control form-control-sm" wire:model.blur="agreements.{{ $ai }}.parties" maxlength="250"></div>
                        <div class="col-md-2"><label class="form-label small fw-semibold mb-1">Signed on</label>
                            <input type="date" class="form-control form-control-sm" wire:model.blur="agreements.{{ $ai }}.signed_on" max="{{ now()->toDateString() }}"></div>
                        <div class="col-md-2"><label class="form-label small fw-semibold mb-1">Status</label>
                            <select class="form-select form-select-sm" wire:model.live="agreements.{{ $ai }}.status">
                                @foreach ($CR::AGREEMENT_STATUSES as $sk => [$sl]) <option value="{{ $sk }}">{{ $sl }}</option> @endforeach
                            </select></div>
                    </div>
                    @error("agreements.$ai.status") <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    <div class="d-flex flex-wrap align-items-center gap-2 mt-2 small">
                        <label class="form-check mb-0"><input type="checkbox" class="form-check-input" wire:model.live="agreements.{{ $ai }}.required"> <span class="form-check-label">Required for this transaction</span></label>
                        <span class="ms-auto"></span>
                        @if ($a->file_path)
                            <a href="{{ route('gate5.file', ['agreement', $a->id]) }}" class="text-decoration-none"><i class="bi bi-paperclip"></i> {{ \Illuminate\Support\Str::limit($a->file_name, 36) }} · {{ $size($a->file_size) }}</a>
                        @endif
                        <span wire:loading wire:target="agreementFiles.{{ $a->id }}" class="text-muted"><span class="spinner-border spinner-border-sm"></span></span>
                        <label class="btn btn-sm btn-outline-secondary mb-0"><i class="bi bi-upload me-1"></i>{{ $a->file_path ? 'Replace' : 'Upload signed copy' }}
                            <input type="file" class="d-none" wire:model="agreementFiles.{{ $a->id }}" accept=".pdf,.doc,.docx"></label>
                        @if ($a->kind === 'other') <button type="button" class="btn btn-icon btn-icon-danger" wire:click="removeAgreement({{ $a->id }})" wire:confirm="Remove this agreement?"><i class="bi bi-trash"></i></button> @endif
                    </div>
                    @error("agreementFiles.{$a->id}") <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                @else
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <span class="file-row-icon"><i class="bi {{ $a->status === 'executed' ? 'bi-file-earmark-check' : 'bi-file-earmark' }}"></i></span>
                        <div class="flex-grow-1 small">
                            <div class="fw-semibold text-navy">{{ $a->title }} @unless ($a->required) <span class="pill pill-muted ms-1">Not required</span> @endunless</div>
                            <div class="text-muted">{{ $a->parties ?: '—' }} @if ($a->signed_on) · signed {{ $a->signed_on->format('d M Y') }} @endif</div>
                        </div>
                        <span class="pill {{ $asP }}">{{ $asL }}</span>
                        @if ($a->file_path) <a href="{{ route('gate5.file', ['agreement', $a->id]) }}" class="btn btn-icon" title="Download"><i class="bi bi-download"></i></a> @endif
                    </div>
                @endif
            </div>
        @endforeach
        @if ($canManage)
            <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="addAgreement"><i class="bi bi-plus-lg me-1"></i> Add other agreement</button>
        @endif

        @unless ($applicant)
            <dt class="detail-label mt-3 mb-2">The Grant Agreement establishes</dt>
            <div class="row g-1">
                @foreach ($CR::GRANT_CLAUSES as $ck => $cl)
                    <div class="col-md-6" wire:key="clause-{{ $ck }}">
                        <label class="form-check small mb-1"><input type="checkbox" class="form-check-input" wire:model.live="checks.clauses.{{ $ck }}" @disabled(! $canManage)> <span class="form-check-label">{{ $cl }}</span></label>
                    </div>
                @endforeach
            </div>
        @endunless
    </section>

    {{-- ============================ 3 · INVESTOR CAPITAL (§3.6.2) ============================ --}}
    <section class="section-card">
        <div class="section-card-head">
            <span class="section-no">3</span>
            <div class="me-auto">
                <h5>Investor capital deployment</h5>
                <div class="small text-muted">CAFI grant resources follow verified private investment — they do not replace it.
                    <div class="mt-1"><span class="pill pill-muted"><i class="bi bi-shop"></i> Enterprise records, with proof</span> <span class="pill pill-muted"><i class="bi bi-people"></i> PMU verifies</span></div></div>
            </div>
            <span class="pill {{ $verified <= 0 ? 'pill-danger' : ($verified >= $private ? 'pill-ok' : 'pill-warn') }}">
                <i class="bi {{ $verified >= $private && $private > 0 ? 'bi-shield-check' : 'bi-shield-exclamation' }}"></i>
                {{ $verified <= 0 ? 'Not verified' : ($verified >= $private ? 'Fully deployed' : 'Partly deployed') }}
            </span>
        </div>

        <div class="row g-3 mb-2">
            @foreach ([['Committed (approved)', $private, ''], ['Deployed & verified', $verified, ''], ['Still to deploy', max(0, $private - $verified), 'text-warning-emphasis']] as [$kLabel, $kVal, $kCls])
                <div class="col-md-4"><div class="border rounded-3 p-3 h-100">
                    <div class="small text-muted">{{ $kLabel }}</div>
                    <div class="fw-bold {{ $kCls ?: 'text-navy' }}">{{ $m($kVal) }}</div><div class="small text-muted">{{ $u($kVal) }}</div>
                </div></div>
            @endforeach
        </div>
        <div class="progress mb-3" style="height: 8px;"><div class="progress-bar bg-accent" style="width: {{ $capPct }}%;"></div></div>

        @if ($r->deployments->isNotEmpty())
            <div class="table-responsive">
                <table class="table table-modern table-sm align-middle mb-3">
                    <thead><tr><th>Investor</th><th class="text-end">Amount</th><th>Date</th><th>Evidence</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($r->deployments as $d)
                            @php [$dsL, $dsP] = $CR::DEPLOYMENT_STATUSES[$d->status]; @endphp
                            <tr wire:key="dep-{{ $d->id }}">
                                <td class="small fw-semibold text-navy">{{ $d->investor }}<div class="text-muted fw-normal">by {{ $d->recorder?->name }}</div></td>
                                <td class="text-end small">{{ $m($d->amount) }}<div class="text-muted">{{ $u($d->amount) }}</div></td>
                                <td class="small">{{ $d->deployed_on->format('d M Y') }}</td>
                                <td class="small">@if ($d->evidence_path)<a href="{{ route('gate5.file', ['deployment', $d->id]) }}" class="text-decoration-none"><i class="bi bi-paperclip"></i> {{ \Illuminate\Support\Str::limit($d->evidence_name, 24) }}</a>@else — @endif</td>
                                <td>
                                    <span class="pill {{ $dsP }}">{{ $dsL }}</span>
                                    @if ($d->status !== 'submitted' && $d->verifier) <div class="small text-muted">{{ $d->verifier->name }}</div> @endif
                                    @if ($d->note) <div class="small text-danger">{{ $d->note }}</div> @endif
                                </td>
                                <td class="text-end" style="min-width: 150px">
                                    @if ($this->canVerifyCapital && $d->status === 'submitted')
                                        <button type="button" class="btn btn-sm btn-success mb-1" wire:click="verifyDeployment({{ $d->id }})" wire:confirm="Verify M {{ number_format((float) $d->amount) }} from {{ $d->investor }}?"><i class="bi bi-shield-check me-1"></i>Verify</button>
                                        <div class="input-group input-group-sm">
                                            <input type="text" class="form-control @error('rejectNote.'.$d->id) is-invalid @enderror" wire:model="rejectNote.{{ $d->id }}" placeholder="Reason">
                                            <button type="button" class="btn btn-outline-danger" wire:click="rejectDeployment({{ $d->id }})">Reject</button>
                                        </div>
                                        @error('rejectNote.'.$d->id) <div class="text-danger small">{{ $message }}</div> @enderror
                                    @elseif ($applicant && $d->status !== 'verified' && $d->recorded_by === auth()->id())
                                        <button type="button" class="btn btn-icon btn-icon-danger" wire:click="removeDeployment({{ $d->id }})" wire:confirm="Remove this record?"><i class="bi bi-trash"></i></button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @if (! $applicant && $r->deployments->isEmpty())
            <div class="callout small mb-3"><i class="bi bi-hourglass-split me-1"></i> No deployments yet — the enterprise records each one with proof of payment, then you verify it here.</div>
        @endif

        @if ($applicant)
            @php $invRows = $this->profileInvestors; @endphp
            <div class="border rounded-3 p-3 mb-3 bg-light">
                <div class="small fw-semibold text-navy mb-1"><i class="bi bi-paperclip me-1"></i> Proof of investor funds</div>
                <div class="small text-muted mb-3">The investors and amounts come from your enterprise profile (<em>Investors in the round</em>). Just attach the proof of payment — a bank confirmation or transfer receipt — for each one.</div>

                @forelse ($invRows as $ii => $inv)
                    @php $dep = $inv['deployment']; $st = $dep?->status; @endphp
                    <div class="d-flex flex-wrap align-items-center gap-2 py-2 {{ ! $loop->last ? 'border-bottom' : '' }}" wire:key="inv-{{ $ii }}">
                        <div class="flex-grow-1" style="min-width: 200px">
                            <div class="fw-semibold text-navy small">{{ $inv['name'] }} @if ($inv['country']) <span class="text-muted fw-normal">· {{ $inv['country'] }}</span> @endif</div>
                            <div class="small">{{ $m($inv['amount']) }} <span class="text-muted">{{ $u($inv['amount']) }}</span></div>
                        </div>

                        @if ($st === 'verified')
                            <span class="pill pill-ok"><i class="bi bi-shield-check"></i> Verified</span>
                        @elseif ($st === 'submitted')
                            <span class="pill pill-warn"><i class="bi bi-hourglass-split"></i> Waiting for verification</span>
                        @elseif (! $inv['amount'])
                            <span class="small text-danger"><i class="bi bi-exclamation-circle me-1"></i>No amount on your profile</span>
                        @elseif ($this->canRecordCapital)
                            @if ($st === 'rejected') <span class="pill pill-danger"><i class="bi bi-x-circle"></i> Not accepted — attach again</span> @endif
                            <span wire:loading wire:target="investorFiles.{{ $ii }}" class="small text-muted"><span class="spinner-border spinner-border-sm"></span> Uploading…</span>
                            <label class="btn btn-sm btn-brand mb-0" wire:loading.remove wire:target="investorFiles.{{ $ii }}">
                                <i class="bi bi-upload me-1"></i> Attach proof of payment
                                <input type="file" class="d-none" wire:model="investorFiles.{{ $ii }}" accept=".pdf,.jpg,.jpeg,.png">
                            </label>
                        @endif
                    </div>
                    @if ($st === 'rejected' && $dep->note) <div class="small text-danger mb-1"><i class="bi bi-chat-left-text me-1"></i>{{ $dep->note }}</div> @endif
                    @error("investorFiles.$ii") <div class="text-danger small mb-1">{{ $message }}</div> @enderror
                @empty
                    <div class="small text-warning-emphasis"><i class="bi bi-exclamation-triangle me-1"></i> No investors on your enterprise profile yet —
                        add them under <em>Investors in the round</em>, then come back to attach the proof.</div>
                @endforelse

                <div class="small text-muted mt-2">
                    <a href="{{ route('profile.index') }}" class="text-decoration-none"><i class="bi bi-building me-1"></i> Wrong investor or amount? Update your enterprise profile</a>
                </div>
            </div>
        @endif

        @unless ($applicant)
            <dt class="detail-label mb-2">The verification establishes</dt>
            <ul class="checklist mb-0">
                @foreach ($CR::VERIFICATION_CHECKS as $vk => $vt)
                    <li class="{{ ($checks['verification'][$vk] ?? false) ? 'is-done' : 'is-pending' }}" wire:key="ver-{{ $vk }}">
                        <label class="d-flex align-items-start gap-2 mb-0 w-100"><input type="checkbox" class="form-check-input mt-1" wire:model.live="checks.verification.{{ $vk }}" @disabled(! $canManage)> <span>{{ $vt }}</span></label>
                    </li>
                @endforeach
            </ul>
        @endunless
    </section>

    {{-- ============================ 4 · DISBURSEMENT READINESS (§3.6.4) ============================ --}}
    <section class="section-card">
        <div class="section-card-head">
            <span class="section-no">4</span>
            <div class="me-auto">
                <h5>Grant utilisation &amp; disbursement</h5>
                <div class="small text-muted">The approved use of funds, reconciled with what the Committee approved and Gate 4 authorised — and the tranches.</div>
            </div>
        </div>

        <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
            <dt class="detail-label mb-0 me-auto">Grant utilisation budget
                <span class="small text-muted fw-normal" style="text-transform: none; letter-spacing: normal">· {{ $this->profile?->grant_budget ? 'filled in by the enterprise on its profile' : 'the enterprise has not filled it in on its profile' }}</span></dt>
            @if ($canManage)
                @if ($this->profile?->grant_budget)
                    <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="reloadBudgetFromProfile" wire:confirm="Replace this budget with the enterprise's current profile budget?"><i class="bi bi-arrow-repeat me-1"></i> Reload from profile</button>
                @endif
                <button type="button" class="btn btn-sm {{ $editBudget ? 'btn-brand' : 'btn-outline-secondary' }}" wire:click="$toggle('editBudget')">
                    <i class="bi {{ $editBudget ? 'bi-check-lg' : 'bi-pencil' }} me-1"></i>{{ $editBudget ? 'Done' : 'Edit budget' }}</button>
            @endif
        </div>
        <div class="table-responsive">
            <table class="table table-modern table-sm align-middle mb-1">
                <thead><tr><th>Expenditure category</th><th class="text-end" style="width: 22%">Private investment (M)</th><th class="text-end" style="width: 22%">CAFI grant (M)</th><th class="text-end" style="width: 18%">Total</th></tr></thead>
                <tbody>
                    @foreach ($budget as $bi => $b)
                        <tr wire:key="bud-{{ $bi }}">
                            <td class="small">@if ($canManage && $editBudget)<input type="text" class="form-control form-control-sm" wire:model.blur="budget.{{ $bi }}.category">@else {{ $b['category'] }} @endif</td>
                            <td class="text-end">@if ($canManage && $editBudget)<input type="number" min="0" step="1000" class="form-control form-control-sm text-end" wire:model.blur="budget.{{ $bi }}.private">@else <span class="small">{{ $m($b['private']) }}</span> @endif</td>
                            <td class="text-end">@if ($canManage && $editBudget)<input type="number" min="0" step="1000" class="form-control form-control-sm text-end" wire:model.blur="budget.{{ $bi }}.grant">@else <span class="small">{{ $m($b['grant']) }}</span> @endif</td>
                            <td class="text-end small">{{ $m((float) ($b['private'] ?? 0) + (float) ($b['grant'] ?? 0)) }}</td>
                        </tr>
                    @endforeach
                    <tr class="fw-bold">
                        <td class="small text-navy">Total</td>
                        <td class="text-end small">{{ $m($budgetT['private']) }}</td>
                        <td class="text-end small {{ $grant > 0 && abs($budgetT['grant'] - $grant) > 0.01 ? 'text-danger' : '' }}">{{ $m($budgetT['grant']) }}</td>
                        <td class="text-end small">{{ $m($budgetT['private'] + $budgetT['grant']) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="small text-muted mb-3">Approved grant: {{ $m($grant) }} · the CAFI grant column must add up to it.
            @if ($this->profile?->document('budget')) <a href="{{ route('enterprise-documents.download', $this->profile->document('budget')) }}" class="ms-1 text-decoration-none"><i class="bi bi-paperclip"></i> Enterprise's budget template</a> @endif</div>

        @unless ($applicant)
            <dt class="detail-label mb-2">The PMU confirms</dt>
            <ul class="checklist mb-3">
                @foreach ($CR::READINESS_CHECKS as $rk => $rt)
                    <li class="{{ ($checks['readiness'][$rk] ?? false) ? 'is-done' : 'is-pending' }}" wire:key="rdy-{{ $rk }}">
                        <label class="d-flex align-items-start gap-2 mb-0 w-100"><input type="checkbox" class="form-check-input mt-1" wire:model.live="checks.readiness.{{ $rk }}" @disabled(! $canManage)> <span>{{ $rt }}</span></label>
                    </li>
                @endforeach
            </ul>
        @endunless

        <dt class="detail-label mb-2">Disbursement tranches
            <span class="pill pill-muted ms-1" style="text-transform: none; letter-spacing: normal"><i class="bi bi-people"></i> PMU sets up</span>
            <span class="pill pill-muted" style="text-transform: none; letter-spacing: normal"><i class="bi bi-cash-stack"></i> Finance releases</span></dt>
        <p class="small text-muted mb-2">Tranches are released by Finance one by one, in order, once the transaction is cleared.
            You don't need to plan the whole grant up front — after clearance the next tranche can be added when it is due, until nothing is left to disburse.
            The applicant and the PMU are e-mailed each time a tranche is released.</p>
        @if (in_array('finance', $this->myRoles, true) && ! $r->isCleared())
            {{-- Finance: say why the Release buttons aren't there yet --}}
            <div class="callout callout-info small mb-2">
                <i class="bi bi-info-circle me-1"></i>
                <strong>Release opens once the PMU records "Cleared for disbursement".</strong>
                The current Gate 5 outcome is <strong>{{ $outcome ? ($CR::OUTCOMES[$outcome][0] ?? $outcome) : 'not recorded yet' }}</strong>.
                @if (! empty($blockers))
                    Still outstanding before it can be cleared:
                    <ul class="mb-0 mt-1">@foreach ($blockers as $b)<li>{{ $b }}</li>@endforeach</ul>
                @endif
            </div>
        @endif
        <div class="table-responsive">
            <table class="table table-modern align-middle mb-2">
                <thead><tr><th style="width: 8%">Tranche</th><th class="text-end" style="width: 17%">Amount (M)</th><th>Release trigger</th><th style="width: 24%">Status</th>@if ($canTr)<th></th>@endif</tr></thead>
                <tbody>
                    @forelse ($r->tranches as $t)
                        @php
                            $ti      = collect($tranches)->search(fn ($x) => $x['id'] === $t->id);
                            $editRow = $canTr && ! $t->isReleased() && $ti !== false;
                        @endphp
                        <tr wire:key="tr-{{ $t->id }}">
                            <td class="fw-semibold text-navy">@if ($editRow)<input type="text" class="form-control form-control-sm" wire:model.blur="tranches.{{ $ti }}.label" maxlength="20">@else {{ $t->label }} @endif</td>
                            <td class="text-end">@if ($editRow)<input type="number" min="0" step="1000" class="form-control form-control-sm text-end" wire:model.blur="tranches.{{ $ti }}.amount">@error('tranches.'.$ti.'.amount')<div class="text-danger small text-start">{{ $message }}</div>@enderror @else {{ $m($t->amount) }} <div class="small text-muted">{{ $u($t->amount) }}</div> @endif</td>
                            <td class="small">@if ($editRow)<input type="text" class="form-control form-control-sm" wire:model.blur="tranches.{{ $ti }}.trigger" maxlength="250" placeholder="Milestone">@else {{ $t->trigger ?: '—' }} @endif</td>
                            <td>
                                @if ($t->isReleased())
                                    <span class="badge status-approved">Released {{ $t->released_on->format('d M Y') }}</span>
                                    <div class="small text-muted">Ref. {{ $t->reference }} · {{ $t->releaser?->name }}</div>
                                    @if ($t->proof_path)
                                        <a href="{{ route('gate5.file', ['tranche', $t->id]) }}" class="small text-decoration-none"><i class="bi bi-paperclip"></i> Proof of payment</a>
                                    @endif
                                @elseif ($this->canRelease)
                                    @php $isNext = $r->tranches->where('position', '<', $t->position)->reject->isReleased()->isEmpty(); @endphp
                                    @if (! $isNext)
                                        <span class="badge status-draft">Release earlier tranches first</span>
                                    @else
                                        <input type="text" class="form-control form-control-sm mb-1 @error('release.'.$t->id.'.reference') is-invalid @enderror" wire:model="release.{{ $t->id }}.reference" placeholder="Payment reference">
                                        <input type="date" class="form-control form-control-sm mb-1 @error('release.'.$t->id.'.on') is-invalid @enderror" wire:model="release.{{ $t->id }}.on" max="{{ now()->toDateString() }}" title="Release date">
                                        <label class="small text-muted mb-0" for="proof-{{ $t->id }}">Proof of payment <span class="text-danger">*</span></label>
                                        <input type="file" id="proof-{{ $t->id }}" class="form-control form-control-sm mb-1 @error('releaseProof.'.$t->id) is-invalid @enderror" wire:model="releaseProof.{{ $t->id }}" accept=".pdf,.jpg,.jpeg,.png">
                                        <div wire:loading wire:target="releaseProof.{{ $t->id }}" class="small text-muted"><span class="spinner-border spinner-border-sm"></span> Uploading…</div>
                                        <button type="button" class="btn btn-sm btn-brand w-100" wire:click="releaseTranche({{ $t->id }})"
                                                wire:loading.attr="disabled" wire:target="releaseTranche({{ $t->id }}), releaseProof.{{ $t->id }}"
                                                wire:confirm="Release {{ $t->label }} ({{ $m($t->amount) }})? The applicant and the PMU will be e-mailed.">
                                            <span wire:loading.remove wire:target="releaseTranche({{ $t->id }})"><i class="bi bi-cash-coin me-1"></i> Release</span>
                                            <span wire:loading wire:target="releaseTranche({{ $t->id }})"><span class="spinner-border spinner-border-sm"></span> Releasing…</span>
                                        </button>
                                        @error('release.'.$t->id.'.reference') <div class="text-danger small">{{ $message }}</div> @enderror
                                        @error('release.'.$t->id.'.on') <div class="text-danger small">{{ $message }}</div> @enderror
                                        @error('releaseProof.'.$t->id) <div class="text-danger small">{{ $message }}</div> @enderror
                                    @endif
                                @elseif ($r->isCleared())
                                    <span class="badge status-in-review">Ready for Finance</span>
                                @else
                                    <span class="badge status-draft">Awaiting clearance</span>
                                @endif
                            </td>
                            @if ($canTr)
                                <td>@unless ($t->isReleased())<button type="button" class="btn btn-icon btn-icon-danger" wire:click="removeTranche({{ $t->id }})" title="Remove"><i class="bi bi-x-lg"></i></button>@endunless</td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="5" class="small text-muted">No tranches set up yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Grant position: released so far and still to be disbursed --}}
        @php
            $relSum    = (float) $released->sum('amount');
            $trTotal   = (float) $r->tranches->sum('amount');
            $toDisb    = max(0, $grant - $relSum);              // still to be disbursed
            $scheduled = max(0, $trTotal - $relSum);            // in tranches, not yet released
            $unplanned = max(0, $grant - $trTotal);             // not yet in any tranche
            $relPct    = $grant > 0 ? min(100, (int) round($relSum / $grant * 100)) : 0;
            $schPct    = $grant > 0 ? min(100 - $relPct, (int) round($scheduled / $grant * 100)) : 0;
        @endphp
        @if ($grant > 0)
            <div class="border rounded-3 p-3 mb-2">
                <div class="row g-3 text-center text-md-start">
                    <div class="col-6 col-md-3">
                        <div class="small text-muted">Approved CAFI grant</div>
                        <div class="fw-bold text-navy">{{ $m($grant) }}</div><div class="small text-muted">{{ $u($grant) }}</div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="small text-muted">Released so far</div>
                        <div class="fw-bold text-success">{{ $m($relSum) }}</div><div class="small text-muted">{{ $u($relSum) }} · {{ $released->count() }} {{ \Illuminate\Support\Str::plural('tranche', $released->count()) }}</div>
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="small text-muted">Still to be disbursed</div>
                        <div class="fs-5 fw-bold {{ $toDisb > 0.01 ? 'text-navy' : 'text-success' }}">{{ $m($toDisb) }}</div>
                        <div class="small text-muted">{{ $u($toDisb) }}
                            @if ($toDisb > 0.01)
                                · {{ $m($scheduled) }} in tranches not yet released · {{ $m($unplanned) }} not yet in a tranche
                            @else
                                · <i class="bi bi-check-circle-fill text-success"></i> the full grant has been disbursed
                            @endif
                        </div>
                    </div>
                </div>
                <div class="progress mt-3" style="height: 8px" role="progressbar" aria-label="Grant disbursed" aria-valuenow="{{ $relPct }}" aria-valuemin="0" aria-valuemax="100">
                    <div class="progress-bar bg-success" style="width: {{ $relPct }}%"></div>
                    <div class="progress-bar bg-info" style="width: {{ $schPct }}%"></div>
                </div>
                <div class="d-flex flex-wrap gap-3 small text-muted mt-1">
                    <span><i class="bi bi-square-fill text-success"></i> Released {{ $relPct }}%</span>
                    <span><i class="bi bi-square-fill text-info"></i> In tranches</span>
                    <span><i class="bi bi-square-fill text-body-tertiary"></i> Not yet in a tranche</span>
                </div>
            </div>
        @endif
        @if ($canTr && $unplanned > 0.01)
            <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="addTranche"><i class="bi bi-plus-lg me-1"></i> Add {{ $r->tranches->isEmpty() ? 'first' : 'next' }} tranche</button>
            <span class="small text-muted ms-2">Pre-filled with the {{ $m($unplanned) }} not yet in a tranche — lower it to release in smaller steps.</span>
        @endif
    </section>

    {{-- ============================ 5 · ONBOARDING (§3.6.5) ============================ --}}
    @if (! $applicant || $r->summary_sent_at)
        <section class="section-card">
            <div class="section-card-head">
                <span class="section-no">5</span>
                <div class="me-auto">
                    <h5>{{ $applicant ? 'Your obligations' : 'Enterprise onboarding' }}</h5>
                    <div class="small text-muted">
                        @if ($applicant) What was agreed for implementation and monitoring.
                        @else Operational arrangements for implementation and monitoring — the enterprise gets a summary of its obligations. @endif
                    </div>
                </div>
                <span class="pill {{ $r->summary_sent_at ? 'pill-ok' : 'pill-muted' }}">{{ $r->summary_sent_at ? 'Summary sent '.$r->summary_sent_at->format('d M Y') : 'Not completed' }}</span>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Enterprise contact person</label>
                    @if ($canManage)
                        <input type="text" class="form-control @error('onboarding.contact') is-invalid @enderror" wire:model.blur="onboarding.contact" maxlength="250" placeholder="Name, title, phone, e-mail">
                        @error('onboarding.contact') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    @else <div class="small">{{ $onboarding['contact'] ?: '—' }}</div> @endif
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Reporting frequency</label>
                    @if ($canManage)
                        <select class="form-select" wire:model.live="onboarding.frequency">@foreach ($CR::FREQUENCIES as $fk => $fl)<option value="{{ $fk }}">{{ $fl }}</option>@endforeach</select>
                    @else <div class="small">{{ $CR::FREQUENCIES[$onboarding['frequency']] ?? '—' }}</div> @endif
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">First report due</label>
                    @if ($canManage)
                        <input type="date" class="form-control" wire:model.blur="onboarding.first_report_on">
                    @else <div class="small">{{ $onboarding['first_report_on'] ? \Illuminate\Support\Carbon::parse($onboarding['first_report_on'])->format('d M Y') : '—' }}</div> @endif
                </div>
            </div>

            <dt class="detail-label mb-2">Arrangements agreed</dt>
            <div class="row g-1 mb-3">
                @foreach ($CR::ONBOARDING_ITEMS as $ok => $ol)
                    <div class="col-md-6" wire:key="onb-{{ $ok }}">
                        <label class="form-check small mb-1"><input type="checkbox" class="form-check-input" wire:model.live="onboarding.items.{{ $ok }}" @disabled(! $canManage)> <span class="form-check-label">{{ $ol }}</span></label>
                    </div>
                @endforeach
            </div>

            @if ($canManage)
                <div class="file-row mb-0">
                    <span class="file-row-icon"><i class="bi bi-envelope-paper"></i></span>
                    <div class="flex-grow-1 small">
                        <div class="fw-semibold text-navy">Summary of obligations e-mailed to the enterprise</div>
                        <div class="text-muted">{{ $r->summary_sent_at ? 'Sent '.$r->summary_sent_at->format('d M Y H:i') : 'Not sent yet' }}</div>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="sendSummary"><i class="bi bi-send me-1"></i> {{ $r->summary_sent_at ? 'E-mail again' : 'E-mail summary' }}</button>
                </div>
            @endif
        </section>
    @endif

    {{-- ============================ 6 · OUTCOME (§3.6.6) ============================ --}}
    <section class="section-card">
        <div class="section-card-head">
            <span class="section-no">6</span>
            <div class="me-auto">
                <h5>Gate 5 status</h5>
                <div class="small text-muted">
                    @if ($r->decided_at) Last recorded by {{ $r->decider?->name ?? '—' }} on {{ $r->decided_at->format('d M Y H:i') }}.
                    @else A transaction is not onboarded just because the Grant Agreement is signed — conditions and private capital deployment must be satisfied. @endif
                </div>
            </div>
            @if ($outcome) <span class="pill {{ $CR::OUTCOMES[$outcome][4] }}"><i class="bi {{ $CR::OUTCOMES[$outcome][3] }}"></i> {{ $CR::OUTCOMES[$outcome][0] }}</span> @endif
        </div>

        @if ($applicant)
            <p class="small text-muted mb-0">
                @if ($outcome === 'cleared') Cleared for disbursement — the CAFI grant is paid in tranches as set out above.
                @elseif ($outcome === 'pending_investor') Waiting for the investor's capital to be deployed and verified. No CAFI grant can be paid before then.
                @elseif ($outcome === 'not_satisfied') The transaction is under review. The LEHSFF team will contact you.
                @else Waiting for the outstanding conditions above. @endif
            </p>
            @if ($r->outcome_comments) <div class="quote mt-3 mb-0">{{ $r->outcome_comments }}<div class="quote-meta">LEHSFF team</div></div> @endif
        @else
            @if ($canManage)
                @if ($blockers)
                    <div class="callout callout-warning small mb-3">
                        <div class="fw-semibold mb-1"><i class="bi bi-list-check me-1"></i> Before clearing for disbursement ({{ count($blockers) }})</div>
                        <ul class="evidence-list mb-0">@foreach ($blockers as $b) <li>{{ $b }}</li> @endforeach</ul>
                    </div>
                @else
                    <div class="callout callout-success small mb-3"><i class="bi bi-check2-circle me-1"></i> Everything is in place — ready to clear for disbursement.</div>
                @endif
            @endif

            <div class="choice-group cols-2 @error('decision.outcome') is-invalid @enderror">
                @foreach ($CR::OUTCOMES as $ov => [$oLabel, $oMeaning, $oNext, $oIcon])
                    <label class="choice choice--card">
                        <input type="radio" name="g5-outcome" value="{{ $ov }}" wire:model.live="decision.outcome" @disabled(! $canManage)>
                        <span class="choice-box">
                            <span class="choice-icon"><i class="bi {{ $oIcon }}"></i></span>
                            <span>
                                <span class="d-block fw-semibold">{{ $oLabel }} @if ($canManage && $suggested === $ov) <span class="pill pill-muted ms-1">Suggested</span> @endif</span>
                                <span class="d-block small text-muted lh-sm mt-1">{{ $oMeaning }}</span>
                                <span class="d-block small text-navy mt-1"><i class="bi bi-arrow-right me-1"></i>{{ $oNext }}</span>
                            </span>
                        </span>
                    </label>
                @endforeach
            </div>
            @error('decision.outcome') <div class="text-danger small mt-1">{{ $message }}</div> @enderror

            <div class="row g-3 mt-1">
                <div class="col-md-9">
                    <label class="form-label small fw-semibold">Comments @if ($canManage && ($decision['outcome'] ?? null) !== 'cleared') <span class="req">*</span> @endif</label>
                    @if ($canManage)
                        <textarea rows="3" class="form-control @error('decision.comments') is-invalid @enderror" wire:model.blur="decision.comments" placeholder="What is outstanding, who is responsible and the expected date"></textarea>
                        @error('decision.comments') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    @else
                        <div class="quote mb-0">{{ $r->outcome_comments ?: 'No comments.' }}</div>
                    @endif
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Date</label>
                    <input type="date" class="form-control @error('decision.on') is-invalid @enderror" wire:model="decision.on" max="{{ now()->toDateString() }}" @disabled(! $canManage)>
                    @error('decision.on') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>

            @if ($canManage)
                <div class="d-flex mt-4 pt-3 border-top">
                    <button type="button" class="btn btn-brand ms-auto" wire:click="recordOutcome" wire:loading.attr="disabled" wire:target="recordOutcome"
                            wire:confirm="Record this Gate 5 status? The applicant will be e-mailed.{{ ($decision['outcome'] ?? null) === 'cleared' ? ' Clearing locks the set-up and e-mails Finance.' : '' }}">
                        <i class="bi bi-send me-1"></i> Record Gate 5 status
                    </button>
                </div>
            @endif

            @if (count($r->history ?? []) > 0)
                <dt class="detail-label mt-4 mb-2">History</dt>
                @foreach (array_reverse($r->history) as $h)
                    @php [$hl, , , $hi, $hp] = $CR::OUTCOMES[$h['outcome']] ?? [$h['outcome'], '', '', 'bi-circle', 'pill-muted']; @endphp
                    <div class="file-row align-items-start">
                        <span class="file-row-icon"><i class="bi {{ $hi }}"></i></span>
                        <div class="flex-grow-1 small">
                            <span class="pill {{ $hp }}">{{ $hl }}</span>
                            <span class="text-muted ms-1">{{ \Illuminate\Support\Carbon::parse($h['on'])->format('d M Y') }} · {{ $h['by'] }}</span>
                            @if ($h['comments']) <div class="text-muted mt-1">{{ $h['comments'] }}</div> @endif
                        </div>
                    </div>
                @endforeach
            @endif
        @endif
    </section>
@endif
</div>