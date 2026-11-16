<?php

use App\Models\CommitteeReview;
use App\Models\EnterpriseDocument;
use App\Models\EnterpriseProfile;
use App\Models\ExpressionOfInterest;
use App\Models\GrantReadiness;
use App\Notifications\EoiBankDetailsRequested;
use App\Notifications\EoiBankDetailsSubmitted;
use App\Notifications\EoiGrantReadinessUpdate;
use App\Support\NotifyRecipients;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

/*
| G4 · Grant Readiness Review (CI Facility Operations Manual §3.5)
|   Used in the EOI page:  <livewire:expression-of-interest.readiness.grant-readiness :eoi-id="$eoi->id" />
|
|   Gate 3 recommendation → 1 Grants Management review → 2 Hub Manager → 3 PMU Director of Implementation → Gate 5
|
|   readiness.review    Grants Management Representative: 6 checks (Confirmed / Exception / Action required + note),
|                       spending in Lesotho, bank account verification, outstanding issues, Draft Grant Agreement (upload),
|                       recommendation Ready / Not Ready → Grant Readiness Memo (PDF, generated) → submit to the Hub Manager
|   readiness.hub       Hub Manager: review checklist (GrantReadiness::HUB_CHECKS) → Approved for submission · Returned
|   readiness.director  PMU Director of Implementation: review checklist (DIRECTOR_CHECKS) → Approved to proceed
|                       (→ EOI "authorisation", Gate 5) · Returned
|                       Both checklists start with reviewing the Investment Appraisal Memorandum, the Grant Readiness Memo
|                       and the Draft Grant Agreement (each opens from the checklist). Approving needs every item ticked.
|   A return closes the round and opens the next one at Grants Management with everything carried over.
|   Internal control point — the applicant only sees that the file is being prepared, except for:
|
|   Bank account for the grant (no longer on the enterprise profile):
|     Grants Management clicks "Request bank details" → the applicant is e-mailed a link to this tab (#g4-bank).
|     While the review is with Grants Management, the applicant sees ONLY a form: bank, branch, account type,
|     account name, account number + bank confirmation letter → "Submit bank details" (e-mails Grants Management + PMU).
|     Stored on enterprise_profiles.bank_* and as the enterprise document "bank_confirmation"; Grants Management
|     verifies them as before.
|
|   E-mails: submitted → Hub Manager · Hub approved → PMU Director · returned → Grants Management + PMU
|            approved → PMU + Managing Director / Principal Secretary accounts
*/
new class extends Component
{
    use WithFileUploads;

    #[Locked] public int $eoiId;
    #[Locked] public ?int $grId = null;
    #[Locked] public int $round = 1;

    /** Grants Management: checks{}, lesotho_pct, bank{}, issues[], recommendation, justification, gm_on */
    public array $form = [];

    /** Hub Manager / PMU Director: decision, comments, on, checks{key: {done, note, at}} */
    public array $hub = [];
    public array $director = [];

    /** Uploads */
    public $agreementFile = null;
    public $bankEvidenceFile = null;

    /** Applicant: bank account for the grant (bank_name, bank_branch, bank_account_type, bank_account_name, bank_account_number) */
    public array $applicantBank = [];
    public $bankLetterFile = null;

    public ?string $savedAt = null;

    public function mount(int $eoiId): void
    {
        $eoi = ExpressionOfInterest::findOrFail($eoiId);
        abort_unless($eoi->isViewableBy(auth()->user()), 403);

        $this->eoiId = $eoi->id;
        $this->load();
    }

    /* =====================================================================
     | Data
     ===================================================================== */

    #[Computed] public function eoi(): ExpressionOfInterest
    {
        return ExpressionOfInterest::with('user:id,name,email')->findOrFail($this->eoiId);
    }

    #[Computed] public function gr(): ?GrantReadiness
    {
        return $this->grId ? GrantReadiness::with('grantsOfficer:id,name', 'hubManager:id,name', 'director:id,name')->find($this->grId) : null;
    }

    /** The Committee recommendation (latest approve / conditional) */
    #[Computed] public function review(): ?CommitteeReview
    {
        return CommitteeReview::where('expression_of_interest_id', $this->eoiId)
            ->whereIn('outcome', [CommitteeReview::OUTCOME_APPROVE, CommitteeReview::OUTCOME_CONDITIONAL])
            ->whereNotNull('decided_at')->orderByDesc('round')->first();
    }

    /** The Gate 2 assessment whose Investment Appraisal Memorandum went to the Committee */
    #[Computed] public function dd(): ?\App\Models\DueDiligence
    {
        return \App\Models\DueDiligence::where('expression_of_interest_id', $this->eoiId)
            ->where('outcome', \App\Models\DueDiligence::OUTCOME_PROCEED)->whereNotNull('decided_at')
            ->orderByDesc('round')->first();
    }

    #[Computed] public function profile(): ?EnterpriseProfile
    {
        return EnterpriseProfile::with('documents')->where('user_id', $this->eoi->user_id)->first();
    }

    #[Computed] public function history()
    {
        return GrantReadiness::with('hubManager:id,name', 'director:id,name')
            ->where('expression_of_interest_id', $this->eoiId)->whereNotNull('decided_at')
            ->when($this->grId, fn ($q) => $q->whereKeyNot($this->grId))
            ->orderByDesc('round')->get();
    }

    #[Computed] public function isApplicant(): bool
    {
        return $this->eoi->user_id === auth()->id();
    }

    #[Computed] public function gateOpen(): bool
    {
        return in_array($this->eoi->status, ExpressionOfInterest::READINESS_VISIBLE, true) || $this->grId !== null;
    }

    /** grants | hub | director | decided */
    #[Computed] public function stage(): string
    {
        return $this->gr?->stage() ?? 'grants';
    }

    #[Computed] public function isOpen(): bool
    {
        return $this->eoi->status === ExpressionOfInterest::STATUS_GRANT_READINESS && ! ($this->gr?->isDecided());
    }

    protected function canAt(string $stage): bool
    {
        return ! $this->isApplicant && $this->isOpen && $this->stage === $stage
            && auth()->user()->can(GrantReadiness::STAGE_PERMISSION[$stage]);
    }

    /** The applicant may enter the bank details: requested by Grants Management, and the review is still with them */
    #[Computed] public function canApplicantBank(): bool
    {
        return $this->isApplicant && $this->isOpen && $this->stage === 'grants' && $this->gr?->bank_requested_at !== null;
    }

    #[Computed] public function canGrants(): bool   { return $this->canAt('grants'); }
    #[Computed] public function canHub(): bool      { return $this->canAt('hub'); }
    #[Computed] public function canDirector(): bool { return $this->canAt('director'); }

    /** Why a staff member sees the review read-only, in plain words (null = they can edit, or it's simply finished) */
    #[Computed] public function lockReason(): ?string
    {
        if ($this->isApplicant || $this->canGrants || $this->canHub || $this->canDirector) {
            return null;
        }
        $eoi = $this->eoi;
        $who = GrantReadiness::STAGES[$this->stage][1] ?? 'the reviewer';

        if ($eoi->status !== ExpressionOfInterest::STATUS_GRANT_READINESS) {
            return $this->gr?->isApproved()
                ? null
                : 'This application has moved on from grant readiness (it is now at "'.Str::headline($eoi->status).'"), so this review can no longer be changed.';
        }
        if ($this->gr?->isDecided()) {
            return 'This round is closed.';
        }

        $perm = GrantReadiness::STAGE_PERMISSION[$this->stage] ?? null;
        if ($perm && ! auth()->user()->can($perm)) {
            $roles = auth()->user()->roles
                ->map(fn ($r) => $r->label ?: Str::headline($r->name))
                ->filter()->unique()->values();

            return 'It is with the '.$who.' at the moment — only they can work on this step. '
                .($roles->isNotEmpty() ? 'You are signed in as '.$roles->implode(', ', ' and ').', so you can view it but not make changes.' : 'You can view it but not make changes.');
        }

        return null;
    }

    /** Matching, cumulative limit and Lesotho share — worked out from the form */
    #[Computed] public function facts(): array
    {
        $g = new GrantReadiness([
            'private_amount' => $this->gr?->private_amount ?? $this->review?->private_amount,
            'grant_amount'   => $this->gr?->grant_amount ?? $this->review?->grant_amount,
            'lesotho_pct'    => is_numeric($this->form['lesotho_pct'] ?? null) ? $this->form['lesotho_pct'] : null,
        ]);
        $g->setRelation('expressionOfInterest', $this->eoi);

        return $g->facts();
    }

    /** Why Grants Management can't submit yet */
    #[Computed] public function blockers(): array
    {
        return GrantReadiness::submitBlockers($this->form + [
            'lesotho_justification' => $this->profile?->grant_outside_justification,
            'has_agreement'     => (bool) $this->gr?->agreement_path,
            'has_bank_evidence' => (bool) $this->gr?->bank_evidence_path || (bool) $this->profile?->document('bank_confirmation'),
        ]);
    }

    /* =====================================================================
     | Load
     ===================================================================== */

    protected function load(): void
    {
        $g = GrantReadiness::where('expression_of_interest_id', $this->eoiId)->orderByDesc('round')->first();

        $this->grId  = $g?->id;
        $this->round = $g?->round ?? 1;

        $checks = (array) ($g?->checks ?? []);
        $bank   = (array) ($g?->bank ?? []);

        $this->form = [
            'checks'         => collect(GrantReadiness::CHECKS)->mapWithKeys(fn ($c, $k) => [$k => [
                'status' => $checks[$k]['status'] ?? null,
                'note'   => (string) ($checks[$k]['note'] ?? ''),
            ]])->all(),
            'lesotho_pct'    => $this->profileLesothoPct() ?? ($g?->lesotho_pct !== null ? (float) $g->lesotho_pct : null),
            'bank'           => $this->bankFromProfile($bank),
            'issues'         => $g ? array_values($g->issues ?? []) : $this->issuesFromCommittee(),
            'recommendation' => $g?->recommendation,
            'justification'  => (string) $g?->justification,
            'gm_on'          => $g?->gm_on?->toDateString() ?? now()->toDateString(),
        ];

        $p = $this->profile;
        $this->applicantBank = [
            'bank_name'           => (string) $p?->bank_name,
            'bank_branch'         => (string) $p?->bank_branch,
            'bank_account_type'   => $p?->bank_account_type,
            'bank_account_name'   => (string) ($p?->bank_account_name ?: $p?->registered_name),
            'bank_account_number' => (string) $p?->bank_account_number,
        ];
        $checklist = fn (array $defs, ?array $saved) => collect($defs)->mapWithKeys(fn ($d, $k) => [$k => [
            'done' => (bool) ($saved[$k]['done'] ?? false),
            'note' => (string) ($saved[$k]['note'] ?? ''),
            'at'   => $saved[$k]['at'] ?? null,
        ]])->all();
        $this->hub = [
            'decision' => $g?->hub_decision,
            'comments' => (string) $g?->hub_comments,
            'on'       => $g?->hub_on?->toDateString() ?? now()->toDateString(),
            'checks'   => $checklist(GrantReadiness::HUB_CHECKS, $g?->hub_checks),
        ];
        $this->director = [
            'decision' => $g?->director_decision,
            'comments' => (string) $g?->director_comments,
            'on'       => $g?->director_on?->toDateString() ?? now()->toDateString(),
            'checks'   => $checklist(GrantReadiness::DIRECTOR_CHECKS, $g?->director_checks),
        ];

        unset($this->gr, $this->stage, $this->history, $this->blockers, $this->facts, $this->canApplicantBank);
    }

    /**
     * Bank details as entered by the applicant at this step (stored on the enterprise profile) (Business → Bank account for the grant).
     * Grants Management only records what they were verified against. Falls back to what is stored on the round.
     */
    protected function bankFromProfile(array $stored = []): array
    {
        $p    = $this->profile;
        $from = $p?->hasBankDetails() ? $p->bankDetails() : [
            'bank'           => (string) ($stored['bank'] ?? ''),
            'branch'         => (string) ($stored['branch'] ?? ''),
            'account_name'   => (string) ($stored['account_name'] ?? ''),
            'account_number' => (string) ($stored['account_number'] ?? ''),
            'account_type'   => $stored['account_type'] ?? null,
        ];

        return $from + [
            'verified_against' => $stored['verified_against'] ?? ($this->form['bank']['verified_against'] ?? null),
            'source'           => $p?->hasBankDetails() ? 'profile' : ($stored['source'] ?? null),
        ];
    }

    /** % of the grant spent in Lesotho, as declared by the enterprise in its profile (Business → after the budget) */
    protected function profileLesothoPct(): ?float
    {
        return $this->profile?->lesothoSharePct();
    }

    /** Open Committee conditions → outstanding issues (responsible party from the condition owner) */
    protected function issuesFromCommittee(): array
    {
        return collect($this->review?->conditions ?? [])
            ->filter(fn ($c) => ($c['decision'] ?? null) !== 'removed' && ($c['status'] ?? 'open') !== 'resolved' && filled($c['finding'] ?? null))
            ->map(fn ($c) => [
                'issue'  => Str::limit((string) $c['finding'], 250, ''),
                'party'  => in_array($c['owner'] ?? null, ['enterprise', 'investor', 'pmu'], true) ? $c['owner'] : 'enterprise',
                'action' => (string) ($c['treatment'] ?? ''),
                'status' => 'open',
            ])->values()->all();
    }

    /* =====================================================================
     | Autosave
     ===================================================================== */

    public function updated(string $name): void
    {
        $who = match (true) {
            Str::startsWith($name, 'form')     => 'grants',
            Str::startsWith($name, 'hub')      => 'hub',
            Str::startsWith($name, 'director') => 'director',
            default                            => null,
        };
        if (! $who) {
            return;
        }
        if (! $this->canAt($who)) {
            $this->load();   // discard changes from someone who can't edit this step
            return;
        }

        $this->persist();
        unset($this->blockers, $this->facts);
    }

    public function addIssue(): void
    {
        abort_unless($this->canGrants, 403);
        $this->form['issues'][] = ['issue' => '', 'party' => 'enterprise', 'action' => '', 'status' => 'open'];
        $this->persist();
    }

    public function removeIssue(int $i): void
    {
        abort_unless($this->canGrants, 403);
        unset($this->form['issues'][$i]);
        $this->form['issues'] = array_values($this->form['issues']);
        $this->persist();
        unset($this->blockers);
    }

    /* =====================================================================
     | Uploads (Grants Management)
     ===================================================================== */

    public function updatedAgreementFile(): void
    {
        abort_unless($this->canGrants, 403);
        $this->validate(['agreementFile' => ['required', 'file', 'mimes:'.GrantReadiness::AGREEMENT_MIMES, 'max:'.GrantReadiness::MAX_KB]],
            ['agreementFile.mimes' => 'Upload the Draft Grant Agreement as PDF or Word.'], ['agreementFile' => 'Draft Grant Agreement']);

        $g = $this->persist();
        $this->replaceFile($g, 'agreement', $this->agreementFile);
        $g->forceFill(['agreement_size' => $this->agreementFile->getSize(), 'agreement_uploaded_at' => now()])->save();

        $this->reset('agreementFile');
        unset($this->gr, $this->blockers);
        $this->dispatch('notify', type: 'success', message: 'Draft Grant Agreement uploaded.');
    }

    public function updatedBankEvidenceFile(): void
    {
        abort_unless($this->canGrants, 403);
        $this->validate(['bankEvidenceFile' => ['required', 'file', 'mimes:'.GrantReadiness::EVIDENCE_MIMES, 'max:'.GrantReadiness::MAX_KB]],
            ['bankEvidenceFile.mimes' => 'Upload a PDF or a clear scan / photo (JPG, PNG).'], ['bankEvidenceFile' => 'bank verification document']);

        $g = $this->persist();
        $this->replaceFile($g, 'bank_evidence', $this->bankEvidenceFile);

        $this->reset('bankEvidenceFile');
        unset($this->gr, $this->blockers);
        $this->dispatch('notify', type: 'success', message: 'Bank verification document uploaded.');
    }

    /** Store a new file; delete the old one unless an earlier round still uses it */
    protected function replaceFile(GrantReadiness $g, string $col, $file): void
    {
        $old = $g->{"{$col}_path"};
        if ($old && ! GrantReadiness::where("{$col}_path", $old)->whereKeyNot($g->id)->exists()) {
            Storage::disk(GrantReadiness::DISK)->delete($old);
        }
        $g->forceFill([
            "{$col}_path" => $file->store("grant-readiness/{$g->expression_of_interest_id}", GrantReadiness::DISK),
            "{$col}_name" => $file->getClientOriginalName(),
        ])->save();
    }

    /* =====================================================================
     | Bank account for the grant — requested from and entered by the applicant
     ===================================================================== */

    /** Grants Management e-mails the applicant a link to enter the bank details here */
    public function requestBankDetails(): void
    {
        abort_unless($this->canGrants, 403);

        $g        = $this->persist();
        $reminder = $g->bank_requested_at !== null;
        $g->forceFill(['bank_requested_at' => now(), 'bank_requested_by' => auth()->id()])->save();

        $applicant = $this->eoi->user;
        if (! $applicant || blank($applicant->email)) {
            $this->after('Requested — but the applicant has no e-mail address. Ask them to open the Grant readiness tab of their application.');
            return;
        }

        try {
            $applicant->notify(new EoiBankDetailsRequested($this->eoi, $reminder));
            $msg = ($reminder ? 'Reminder sent' : 'Request sent').' to '.$applicant->email.'. They can now enter the bank details in the Grant readiness tab.';
        } catch (\Throwable $e) {
            report($e);
            $msg = 'Recorded, but the e-mail could not be sent — ask the applicant to open the Grant readiness tab of their application.'.NotifyRecipients::debugReason($e);
        }

        unset($this->canApplicantBank);
        $this->after($msg);
    }

    /** Applicant: bank confirmation letter (replaces an earlier one) */
    public function updatedBankLetterFile(): void
    {
        abort_unless($this->canApplicantBank, 403);

        $mimes = EnterpriseDocument::mimesFor('bank_confirmation');
        $this->validate(
            ['bankLetterFile' => ['required', 'file', "mimes:$mimes", 'max:'.EnterpriseDocument::MAX_KB]],
            [
                'bankLetterFile.mimes' => 'Upload a PDF or a clear scan / photo ('.strtoupper(str_replace(',', ', ', $mimes)).').',
                'bankLetterFile.max'   => 'The file is too big — it can be at most '.(EnterpriseDocument::MAX_KB / 1024).' MB.',
            ],
            ['bankLetterFile' => 'bank confirmation letter'],
        );

        $p = $this->profile;
        abort_unless($p, 404);
        $file = $this->bankLetterFile;

        DB::transaction(function () use ($p, $file) {
            $p->documents()->where('type', 'bank_confirmation')->get()->each->delete();   // also removes the old file
            $p->documents()->create([
                'type'          => 'bank_confirmation',
                'path'          => $file->store("enterprise-documents/{$p->id}", EnterpriseDocument::DISK),
                'original_name' => $file->getClientOriginalName(),
                'mime'          => $file->getMimeType(),
                'size'          => $file->getSize(),
                'uploaded_by'   => auth()->id(),
            ]);
        });

        $this->reset('bankLetterFile');
        unset($this->profile);
        $this->dispatch('notify', type: 'success', message: 'Bank confirmation letter uploaded.');
    }

    public function removeBankLetter(): void
    {
        abort_unless($this->canApplicantBank, 403);
        $this->profile?->documents()->where('type', 'bank_confirmation')->get()->each->delete();
        unset($this->profile);
    }

    /** Applicant: save the bank details and let Grants Management know */
    public function submitBankDetails(): void
    {
        abort_unless($this->canApplicantBank, 403);

        $this->validate([
            'applicantBank.bank_name'           => ['required', 'string', 'max:120'],
            'applicantBank.bank_branch'         => ['nullable', 'string', 'max:120'],
            'applicantBank.bank_account_type'   => ['nullable', Rule::in(array_keys(GrantReadiness::ACCOUNT_TYPES))],
            'applicantBank.bank_account_name'   => ['required', 'string', 'max:200'],
            'applicantBank.bank_account_number' => ['required', 'string', 'max:40', 'regex:/^[0-9 \-]+$/'],
        ], [
            'applicantBank.bank_name.required'           => 'Enter the name of your bank.',
            'applicantBank.bank_account_name.required'   => 'Enter the account name, exactly as it appears on the account.',
            'applicantBank.bank_account_number.required' => 'Enter the account number.',
            'applicantBank.bank_account_number.regex'    => 'The account number can only contain digits, spaces and dashes.',
        ], [
            'applicantBank.bank_name' => 'bank', 'applicantBank.bank_branch' => 'branch', 'applicantBank.bank_account_type' => 'account type',
            'applicantBank.bank_account_name' => 'account name', 'applicantBank.bank_account_number' => 'account number',
        ]);

        $p = $this->profile;
        abort_unless($p, 404);

        if (! $p->document('bank_confirmation')) {
            $this->addError('bankLetterFile', 'Upload your bank confirmation letter.');
            return;
        }

        $first = $this->gr?->bank_submitted_at === null;

        DB::transaction(function () use ($p) {
            $p->forceFill(collect($this->applicantBank)->map(fn ($v) => is_string($v) ? (trim($v) ?: null) : ($v ?: null))->all())->save();
            GrantReadiness::whereKey($this->grId)->update(['bank_submitted_at' => now()]);
        });

        $note = NotifyRecipients::send(
            NotifyRecipients::withPermission('readiness.review')->merge(NotifyRecipients::pmu())->unique('id')->values(),
            new EoiBankDetailsSubmitted($this->eoi, $p->fresh()),
            'the LEHSFF team', except: auth()->id(),
        );

        unset($this->gr, $this->profile);
        $this->dispatch('notify', type: 'success', message: ($first ? 'Thank you — your bank details have been sent to the LEHSFF team.' : 'Your bank details have been updated.').' You can still correct them until the team completes its review.');
        unset($note);
    }

    /* =====================================================================
     | Step 1 · Grants Management → Hub Manager
     ===================================================================== */

    public function submitToHub(): void
    {
        abort_unless($this->canGrants, 403);

        $this->validate([
            'form.lesotho_pct'          => ['required', 'numeric', 'min:0', 'max:100'],
            'form.bank.bank'            => ['required', 'string', 'max:120'],
            'form.bank.account_name'    => ['required', 'string', 'max:200'],
            'form.bank.account_number'  => ['required', 'string', 'max:40'],
            'form.bank.verified_against'=> ['required', Rule::in(array_keys(GrantReadiness::BANK_EVIDENCE))],
            'form.recommendation'       => ['required', Rule::in(array_keys(GrantReadiness::RECOMMENDATIONS))],
            'form.justification'        => ['required', 'string', 'max:2000'],
            'form.gm_on'                => ['required', 'date', 'before_or_equal:today'],
        ], [
            'form.bank.bank.required'             => 'The applicant has not entered their bank yet — use "Request bank details".',
            'form.bank.account_name.required'     => 'The applicant has not entered the account name yet.',
            'form.bank.account_number.required'   => 'The applicant has not entered the account number yet.',
            'form.lesotho_pct.required'           => 'The enterprise has not yet said how much of the grant is spent outside Lesotho (profile → Business, after the budget).',
            'form.bank.verified_against.required' => 'Say what the bank details were verified against.',
            'form.recommendation.required'        => 'Record the readiness recommendation.',
            'form.justification.required'         => 'Give a brief justification for the recommendation.',
        ], [
            'form.lesotho_pct' => 'share spent in Lesotho', 'form.bank.bank' => 'bank', 'form.bank.account_name' => 'account name',
            'form.bank.account_number' => 'account number', 'form.gm_on' => 'date',
        ]);

        if ($this->blockers) {
            $this->addError('form.recommendation', 'Not ready to submit — see the list above.');
            return;
        }

        $g = $this->persist();
        $g->update(['gm_by' => auth()->id(), 'gm_submitted_at' => now()]);

        $note = NotifyRecipients::send(
            NotifyRecipients::withPermission('readiness.hub'),
            new EoiGrantReadinessUpdate($this->eoi, $g->fresh(), 'submitted'),
            'the Hub Manager', except: auth()->id(),
        );

        $this->after('Submitted to the Hub Manager. '.$note);
    }

    /* =====================================================================
     | Step 2 · Hub Manager   ·   Step 3 · PMU Director of Implementation
     ===================================================================== */

    public function recordHub(): void
    {
        abort_unless($this->canHub, 403);
        $this->validateDecision('hub', GrantReadiness::HUB_DECISIONS);

        $g = $this->persist();
        $g->update(['hub_by' => auth()->id(), 'hub_decided_at' => now()]);

        if ($this->hub['decision'] === 'returned') {
            $note = $this->returnRound($g);
            $this->after('Returned to Grants Management — round '.$this->round.' opened. '.$note);
            return;
        }

        $note = NotifyRecipients::send(
            NotifyRecipients::withPermission('readiness.director'),
            new EoiGrantReadinessUpdate($this->eoi, $g->fresh(), 'hub'),
            'the PMU Director of Implementation', except: auth()->id(),
        );
        $this->after('Approved for submission to the PMU Director of Implementation. '.$note);
    }

    public function recordDirector(): void
    {
        abort_unless($this->canDirector, 403);
        $this->validateDecision('director', GrantReadiness::DIRECTOR_DECISIONS);

        $g = $this->persist();
        $g->update(['director_by' => auth()->id(), 'director_decided_at' => now()]);

        if ($this->director['decision'] === 'returned') {
            $note = $this->returnRound($g);
            $this->after('Returned to Grants Management — round '.$this->round.' opened. '.$note);
            return;
        }

        DB::transaction(function () use ($g) {
            $g->update(['outcome' => GrantReadiness::OUTCOME_APPROVED, 'decided_at' => now()]);
            $this->eoi->update(['status' => ExpressionOfInterest::STATUS_AUTHORISATION]);
        });

        $note = NotifyRecipients::send(
            NotifyRecipients::pmu()->merge(NotifyRecipients::anyOf(['gate4.md', 'gate4.ps', 'readiness.review']))->unique('id')->values(),
            new EoiGrantReadinessUpdate($this->eoi, $g->fresh(), 'approved'),
            'the PMU and the signing authorities', except: auth()->id(),
        );

        $this->dispatch('gate-decided', gate: 'readiness', decision: 'approved');
        $this->after('Approved to proceed to Gate 5 · Final authorisation. '.$note);
    }

    /** Checklist answers to store: done, note, and when each item was ticked */
    protected function checksToSave(string $who, array $defs): array
    {
        $saved = (array) ($who === 'hub' ? $this->gr?->hub_checks : $this->gr?->director_checks);

        return collect($defs)->mapWithKeys(function ($d, $k) use ($who, $saved) {
            $done = (bool) ($this->{$who}['checks'][$k]['done'] ?? false);
            $at   = $done ? ($saved[$k]['at'] ?? now()->toDateTimeString()) : null;
            $this->{$who}['checks'][$k]['at'] = $at;

            return [$k => [
                'done' => $done,
                'note' => Str::limit(trim((string) ($this->{$who}['checks'][$k]['note'] ?? '')), 500, ''),
                'at'   => $at,
            ]];
        })->all();
    }

    protected function validateDecision(string $who, array $decisions): void
    {
        // Approving needs every checklist item ticked (the three documents and the review criteria)
        if (($this->{$who}['decision'] ?? null) === 'approved') {
            $defs    = $who === 'hub' ? GrantReadiness::HUB_CHECKS : GrantReadiness::DIRECTOR_CHECKS;
            $missing = collect($defs)->reject(fn ($d, $k) => $this->{$who}['checks'][$k]['done'] ?? false);
            if ($missing->isNotEmpty()) {
                foreach ($missing as $k => $d) {
                    $this->addError("$who.checks.$k", 'Tick this once reviewed.');
                }
                $this->addError("$who.decision", 'Complete the review checklist before approving: '.$missing->map(fn ($d) => $d[0])->implode('; ').'.');
                throw \Illuminate\Validation\ValidationException::withMessages(["$who.decision" => 'Complete the review checklist before approving.']);
            }
        }

        $this->validate([
            "$who.decision" => ['required', Rule::in(array_keys($decisions))],
            "$who.comments" => [($this->{$who}['decision'] ?? null) === 'returned' ? 'required' : 'nullable', 'string', 'max:3000'],
            "$who.on"       => ['required', 'date', 'before_or_equal:today'],
        ], [
            "$who.decision.required" => 'Choose a decision.',
            "$who.comments.required" => 'Say what must be done before resubmission, and by whom.',
        ], ["$who.on" => 'date']);
    }

    /** Close the round as returned, open the next one at Grants Management (everything carried over) */
    protected function returnRound(GrantReadiness $g): string
    {
        DB::transaction(function () use ($g) {
            $g->update(['outcome' => GrantReadiness::OUTCOME_RETURNED, 'decided_at' => now()]);

            $new = GrantReadiness::create($g->only([
                'expression_of_interest_id', 'committee_review_id', 'private_amount', 'grant_amount',
                'checks', 'lesotho_pct', 'bank', 'bank_evidence_path', 'bank_evidence_name',
                'bank_requested_at', 'bank_requested_by', 'bank_submitted_at',
                'agreement_path', 'agreement_name', 'agreement_size', 'agreement_uploaded_at',
                'issues', 'recommendation', 'justification',
            ]) + [
                'round'             => $g->round + 1,
                'resubmission_note' => $g->fresh()->returnComment(),
            ]);

            $this->grId  = $new->id;
            $this->round = $new->round;
        });

        $note = NotifyRecipients::send(
            NotifyRecipients::withPermission('readiness.review')->merge(NotifyRecipients::pmu())->unique('id')->values(),
            new EoiGrantReadinessUpdate($this->eoi, $g->fresh(), 'returned'),
            'Grants Management and the PMU', except: auth()->id(),
        );

        $this->load();

        return $note;
    }

    /* =====================================================================
     | Internals
     ===================================================================== */

    protected function persist(): GrantReadiness
    {
        $data = match ($this->stage) {
            'hub' => [
                'hub_decision' => $this->hub['decision'] ?: null,
                'hub_comments' => trim((string) $this->hub['comments']) ?: null,
                'hub_on'       => $this->hub['on'] ?: null,
                'hub_checks'   => $this->checksToSave('hub', GrantReadiness::HUB_CHECKS),
            ],
            'director' => [
                'director_decision' => $this->director['decision'] ?: null,
                'director_comments' => trim((string) $this->director['comments']) ?: null,
                'director_on'       => $this->director['on'] ?: null,
                'director_checks'   => $this->checksToSave('director', GrantReadiness::DIRECTOR_CHECKS),
            ],
            default => [
                'checks'         => collect($this->form['checks'])->map(fn ($c) => [
                    'status' => isset(GrantReadiness::CHECK_STATUSES[$c['status'] ?? '']) ? $c['status'] : null,
                    'note'   => Str::limit(trim((string) ($c['note'] ?? '')), 500, ''),
                ])->all(),
                'lesotho_pct'    => $this->form['lesotho_pct'] = $this->profileLesothoPct() ?? (is_numeric($this->form['lesotho_pct']) ? (float) $this->form['lesotho_pct'] : null),
                'bank'           => $this->form['bank'] = $this->bankFromProfile(['verified_against' => $this->form['bank']['verified_against'] ?? null] + (array) ($this->gr?->bank ?? [])),
                'issues'         => collect($this->form['issues'])->map(fn ($i) => [
                    'issue'  => Str::limit(trim((string) ($i['issue'] ?? '')), 250, ''),
                    'party'  => isset(GrantReadiness::PARTIES[$i['party'] ?? '']) ? $i['party'] : 'enterprise',
                    'action' => Str::limit(trim((string) ($i['action'] ?? '')), 250, ''),
                    'status' => ($i['status'] ?? 'open') === 'resolved' ? 'resolved' : 'open',
                ])->values()->all(),
                'recommendation' => $this->form['recommendation'] ?: null,
                'justification'  => trim((string) $this->form['justification']) ?: null,
                'gm_on'          => $this->form['gm_on'] ?: null,
            ],
        };

        if ($this->grId) {
            $g = GrantReadiness::findOrFail($this->grId);
            $g->update($data);
        } else {
            $review = $this->review;
            $g = GrantReadiness::create($data + [
                'expression_of_interest_id' => $this->eoiId,
                'committee_review_id'       => $review?->id,
                'round'                     => $this->round,
                'private_amount'            => $review?->private_amount,
                'grant_amount'              => $review?->grant_amount,
            ]);
            $this->grId = $g->id;
        }

        $this->savedAt = now()->format('H:i');
        unset($this->gr, $this->stage);

        return $g;
    }

    protected function after(string $message): void
    {
        unset($this->eoi, $this->gr, $this->stage, $this->isOpen, $this->canGrants, $this->canHub, $this->canDirector, $this->canApplicantBank, $this->lockReason, $this->history, $this->blockers);
        $this->dispatch('notify', type: 'success', message: trim($message));
    }

    #[On('gate-decided')]
    public function refreshGate(): void
    {
        unset($this->eoi, $this->gateOpen, $this->isOpen, $this->canGrants, $this->canHub, $this->canDirector, $this->lockReason);
    }
};
?>

<div>
@php
    $G        = \App\Models\GrantReadiness::class;
    $CR       = \App\Models\CommitteeReview::class;
    $P        = \App\Models\EnterpriseProfile::class;
    $eoi      = $this->eoi;
    $g        = $this->gr;
    $review   = $this->review;
    $stage    = $this->stage;
    $decided  = (bool) $g?->isDecided();
    $canG     = $this->canGrants;
    $canH     = $this->canHub;
    $canD     = $this->canDirector;
    $p        = $this->isApplicant ? null : $this->profile;
    $facts    = $this->isApplicant || ! $this->gateOpen ? [] : $this->facts;

    $rate = max((float) config('cif.usd_to_lsl', 18.00), 0.0001);
    $m    = fn ($v) => $v === null || $v === '' ? '—' : 'M '.number_format((float) $v);
    $u    = fn ($v) => $v === null || $v === '' ? '' : 'US$ '.number_format((float) $v / $rate);

    $private = $g?->private_amount ?? $review?->private_amount;
    $grant   = $g?->grant_amount ?? $review?->grant_amount;

    $gmDone  = (bool) $g?->gm_submitted_at;
    $hubDone = (bool) $g?->hub_decided_at;
    $dirDone = (bool) $g?->director_decided_at;
    $stepState = fn (bool $done, ?string $decision, bool $current) => match (true) {
        $done && $decision === 'returned' => 'returned',
        $done                             => 'done',
        $current                          => 'current',
        default                           => 'todo',
    };
    $path = [
        ['Gate 3 · Committee recommendation', 'done'],
        ['Grants Management review', $stepState($gmDone, null, $stage === 'grants')],
        ['Hub Manager', $stepState($hubDone, $g?->hub_decision, $stage === 'hub')],
        ['PMU Director of Implementation', $stepState($dirDone, $g?->director_decision, $stage === 'director')],
        ['Gate 5 · Final authorisation', $g?->isApproved() ? 'current' : 'todo'],
    ];
    $pathIcon = ['done' => 'bi-check-lg', 'current' => 'bi-hourglass-split', 'returned' => 'bi-arrow-return-left', 'todo' => 'bi-circle'];

    $headPill = match (true) {
        (bool) $g?->isApproved() => ['Approved to proceed', 'pill-ok', 'bi-patch-check'],
        $stage === 'hub'         => ['With the Hub Manager', 'pill-warn', 'bi-hourglass-split'],
        $stage === 'director'    => ['With the PMU Director', 'pill-warn', 'bi-hourglass-split'],
        default                  => ['Grants Management review', 'pill-warn', 'bi-hourglass-split'],
    };
    $budget  = $p?->budgetRows() ?? [];
    $btotals = $P::sumBudget($budget);
@endphp

{{-- ======================================================================
     LOCKED
     ====================================================================== --}}
@if (! $this->gateOpen)
    <section class="section-card">
        <div class="section-card-head">
            <span class="section-no">G4</span>
            <h5 class="me-auto">Grant readiness review</h5>
            <span class="pill pill-muted"><i class="bi bi-lock-fill"></i> Locked</span>
        </div>
        <p class="small text-muted mb-2">Opens once the Investment Committee recommends the transaction (Approve or Conditional approval at Gate 3). An internal control point before final authorisation:</p>
        <ul class="evidence-list small text-muted mb-0">
            <li><strong class="text-navy">Grants Management Representative</strong> — budget, eligible expenditure, bank account and documents; Grant Readiness Memo and Draft Grant Agreement</li>
            <li><strong class="text-navy">Hub Manager</strong> — approves the file for submission</li>
            <li><strong class="text-navy">PMU Director of Implementation</strong> — approves it to proceed to Gate 5 · Final authorisation</li>
        </ul>
    </section>

{{-- ======================================================================
     APPLICANT — internal step, no details
     ====================================================================== --}}
@elseif ($this->isApplicant)
    @php
        $ap      = $this->profile;
        $apLetter = $ap?->document('bank_confirmation');
        $apSent  = $g?->bank_submitted_at;
    @endphp
    @if ($this->canApplicantBank)
        {{-- The applicant sees only this: the bank account for the grant --}}
        <section class="section-card" id="g4-bank">
            <div class="section-card-head">
                <span class="section-no"><i class="bi bi-bank"></i></span>
                <div class="me-auto">
                    <h5>Bank account for the grant</h5>
                    <div class="small text-muted">The account your CAFI grant will be paid into. It must be held in your company's registered name{{ $ap?->registered_name ? ' ('.$ap->registered_name.')' : '' }} — not a personal account.</div>
                </div>
                @if ($apSent)
                    <span class="pill pill-ok"><i class="bi bi-check2-circle"></i> Sent {{ $apSent->format('d M Y') }}</span>
                @else
                    <span class="pill pill-warn"><i class="bi bi-hourglass-split"></i> Needed</span>
                @endif
            </div>

            @if ($apSent)
                <div class="callout callout-success small mb-3"><i class="bi bi-check2-circle me-1"></i>
                    The LEHSFF team has your bank details and is checking them against your letter. You can still correct them below until the check is complete.</div>
            @endif

            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label small fw-semibold mb-1" for="ab_bank">Bank <span class="req">*</span></label>
                    <input type="text" id="ab_bank" maxlength="120" class="form-control @error('applicantBank.bank_name') is-invalid @enderror" wire:model.blur="applicantBank.bank_name" placeholder="e.g. Standard Lesotho Bank">
                    @error('applicantBank.bank_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold mb-1" for="ab_branch">Branch</label>
                    <input type="text" id="ab_branch" maxlength="120" class="form-control @error('applicantBank.bank_branch') is-invalid @enderror" wire:model.blur="applicantBank.bank_branch" placeholder="e.g. Kingsway, Maseru">
                    @error('applicantBank.bank_branch') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold mb-1" for="ab_type">Account type</label>
                    <select id="ab_type" class="form-select @error('applicantBank.bank_account_type') is-invalid @enderror" wire:model="applicantBank.bank_account_type">
                        <option value="">Select…</option>
                        @foreach ($G::ACCOUNT_TYPES as $atk => $atl) <option value="{{ $atk }}">{{ $atl }}</option> @endforeach
                    </select>
                    @error('applicantBank.bank_account_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold mb-1" for="ab_aname">Account name <span class="req">*</span></label>
                    <input type="text" id="ab_aname" maxlength="200" class="form-control @error('applicantBank.bank_account_name') is-invalid @enderror" wire:model.blur="applicantBank.bank_account_name">
                    <div class="form-text">Exactly as it appears on the account.</div>
                    @error('applicantBank.bank_account_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold mb-1" for="ab_anum">Account number <span class="req">*</span></label>
                    <input type="text" id="ab_anum" maxlength="40" inputmode="numeric" class="form-control @error('applicantBank.bank_account_number') is-invalid @enderror" wire:model.blur="applicantBank.bank_account_number">
                    @error('applicantBank.bank_account_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="col-12">
                    <label class="form-label small fw-semibold mb-1">Bank confirmation letter <span class="req">*</span></label>
                    <div class="form-text mt-0 mb-2">A letter from your bank, or a recent bank statement with the bank's stamp, showing the account name and number. PDF or a clear photo / scan, up to {{ \App\Models\EnterpriseDocument::MAX_KB / 1024 }} MB.</div>
                    @if ($apLetter)
                        <div class="file-row mb-2">
                            <span class="file-row-icon"><i class="bi bi-file-earmark-check"></i></span>
                            <div class="flex-grow-1 small overflow-hidden">
                                <a href="{{ route('enterprise-documents.download', $apLetter) }}" class="fw-semibold text-navy text-decoration-none text-truncate d-block">{{ $apLetter->original_name }}</a>
                                <div class="text-muted">{{ $apLetter->size_label }} · uploaded {{ $apLetter->created_at?->format('d M Y') }}</div>
                            </div>
                            <label class="btn btn-sm btn-outline-secondary mb-0">Replace <input type="file" class="d-none" wire:model="bankLetterFile" accept="{{ \App\Models\EnterpriseDocument::acceptFor('bank_confirmation') }}"></label>
                            <button type="button" class="btn btn-icon btn-icon-danger" title="Remove" wire:click="removeBankLetter" wire:confirm="Remove the bank confirmation letter?"><i class="bi bi-trash"></i></button>
                        </div>
                    @else
                        <input type="file" class="form-control @error('bankLetterFile') is-invalid @enderror" wire:model="bankLetterFile" accept="{{ \App\Models\EnterpriseDocument::acceptFor('bank_confirmation') }}">
                    @endif
                    <span wire:loading wire:target="bankLetterFile" class="small text-muted"><span class="spinner-border spinner-border-sm me-1"></span> Uploading…</span>
                    @error('bankLetterFile') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="d-flex mt-4 pt-3 border-top">
                <button type="button" class="btn btn-brand ms-auto" wire:click="submitBankDetails" wire:loading.attr="disabled" wire:target="submitBankDetails,bankLetterFile">
                    <span wire:loading.remove wire:target="submitBankDetails"><i class="bi bi-send me-1"></i> {{ $apSent ? 'Update bank details' : 'Submit bank details' }}</span>
                    <span wire:loading wire:target="submitBankDetails"><span class="spinner-border spinner-border-sm me-1"></span> Sending…</span>
                </button>
            </div>
        </section>
    @else
        <section class="section-card">
            <div class="section-card-head">
                <span class="section-no">G4</span>
                <h5 class="me-auto">Grant readiness</h5>
                <span class="pill {{ $g?->isApproved() ? 'pill-ok' : 'pill-warn' }}"><i class="bi {{ $g?->isApproved() ? 'bi-check2-circle' : 'bi-hourglass-split' }}"></i> {{ $g?->isApproved() ? 'Complete' : 'In progress' }}</span>
            </div>
            <p class="small text-muted mb-0">
                @if ($g?->isApproved()) Your grant file has been prepared and is with CAFI management for final authorisation.
                @else The LEHSFF team is preparing your grant file: reviewing the grant budget and your bank account details, and drafting the Grant Agreement. They may contact you if anything is needed. @endif
            </p>
            @if ($apSent)
                <div class="small text-success mt-2"><i class="bi bi-check2-circle me-1"></i> Your bank details were received on {{ $apSent->format('d M Y') }}.</div>
            @endif
        </section>
    @endif

{{-- ======================================================================
     STAFF
     ====================================================================== --}}
@else
    <div class="g2-path mb-3">
        @foreach ($path as [$pLabel, $pState])
            <span class="g2-path-step {{ in_array($pState, ['done', 'current'], true) ? 'is-'.$pState : '' }} {{ $pState === 'returned' ? 'text-warning-emphasis' : '' }}">
                <i class="bi {{ $pathIcon[$pState] }}"></i> {{ $pLabel }}
            </span>
        @endforeach
    </div>

    {{-- ============================ HEADER ============================ --}}
    <section class="section-card">
        <div class="section-card-head">
            <span class="section-no">G4</span>
            <div class="me-auto">
                <h5>Grant readiness review @if ($round > 1) <span class="pill pill-muted ms-1">Round {{ $round }}</span> @endif</h5>
                <div class="small text-muted">Is the transaction sufficiently prepared — budget, bank account and documents — to proceed to final authorisation?</div>
            </div>
            <span class="pill {{ $headPill[1] }}"><i class="bi {{ $headPill[2] }}"></i> {{ $headPill[0] }}</span>
        </div>

        @if ($canG || $canH || $canD)
            <div class="small text-muted mb-3">
                <i class="bi bi-cloud-check me-1"></i> Changes are saved automatically. @if ($savedAt) <span class="text-accent">Last saved {{ $savedAt }}.</span> @endif
                <span wire:loading class="ms-1"><span class="spinner-border spinner-border-sm"></span></span>
            </div>
        @elseif ($this->lockReason)
            <div class="callout callout-warning small mb-3"><i class="bi bi-lock me-1"></i> <strong>Read-only.</strong> {{ $this->lockReason }}</div>
        @elseif (! $decided && $eoi->status === 'grant_readiness')
            <div class="small text-muted mb-3"><i class="bi bi-eye me-1"></i> Read-only — {{ $G::STAGES[$stage][1] ?? 'the reviewer' }} is working on this step.</div>
        @endif

        @if ($g?->resubmission_note)
            <div class="callout callout-warning small mb-3">
                <div class="fw-semibold mb-1"><i class="bi bi-arrow-return-left me-1"></i> Returned in round {{ $round - 1 }} — to resolve</div>
                {{ $g->resubmission_note }}
            </div>
        @endif

        <div class="detail-grid">
            <div><dt>Transaction reference</dt><dd><span class="chip">{{ $eoi->reference }}</span></dd></div>
            <div><dt>Enterprise</dt><dd>{{ $p?->registered_name ?: ($eoi->business_name ?: '—') }}</dd></div>
            <div><dt>Sector</dt><dd>{{ $p?->sectorLabel() ?? '—' }}</dd></div>
            <div><dt>Committee decision</dt>
                <dd>@if ($review)
                        <span class="pill {{ $CR::OUTCOMES[$review->outcome][4] }}">{{ $CR::OUTCOMES[$review->outcome][0] }}</span>
                        <span class="small text-muted">· {{ $review->decided_at->format('d M Y') }}</span>
                    @else — @endif</dd></div>
            <div><dt>Private investment</dt><dd>{{ $m($private) }} <div class="small text-muted">{{ $u($private) }}</div></dd></div>
            <div><dt>Proposed CAFI grant</dt><dd>{{ $m($grant) }} <div class="small text-muted">{{ $u($grant) }}</div></dd></div>
        </div>

        <div class="d-flex flex-wrap gap-2 mt-3">
            @if ($g)
                <a href="{{ route('grant-readiness.memo', $g) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-file-earmark-pdf me-1"></i> Grant Readiness Memo (PDF)</a>
            @endif
            @if ($g?->agreement_path)
                <a href="{{ route('grant-readiness.file', [$g, 'agreement']) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-file-earmark-text me-1"></i> Draft Grant Agreement</a>
            @endif
        </div>

        <div class="callout small mt-3 mb-0">
            <i class="bi bi-info-circle me-1"></i>
            An internal control point: grant compliance, budget acceptability, disbursement readiness and a complete file. It does not repeat the
            Investment Committee's assessment, and completing it is <strong>not</strong> grant authorisation or approval for payment.
        </div>
    </section>

    {{-- ============================ 1 · GRANTS MANAGEMENT REVIEW (§3.5.1) ============================ --}}
    <section class="section-card" id="g4-grants">
        <div class="section-card-head">
            <span class="section-no">1</span>
            <div class="me-auto">
                <h5>Grants Management review</h5>
                <div class="small text-muted">
                    @if ($gmDone) Submitted by {{ $g->grantsOfficer?->name ?? '—' }} on {{ $g->gm_submitted_at->format('d M Y H:i') }}.
                    @else Grants Management Representative — record each check as a concise confirmation, exception or action required. Detail stays in the transaction file. @endif
                </div>
            </div>
            @if ($gmDone)
                <span class="pill pill-ok"><i class="bi bi-check-lg"></i> Submitted</span>
            @else
                <span class="pill pill-warn"><i class="bi bi-hourglass-split"></i> In review</span>
            @endif
        </div>

        {{-- Facility rules --}}
        <div class="row g-2 mb-3">
            <div class="col-md-4">
                <div class="border rounded-3 p-2 h-100 small">
                    <div class="detail-label mb-1">Matching formula</div>
                    Max under the grant rule ({{ $P::grantRatioLabel() }}) = <strong>{{ $m($facts['matching_max'] ?? null) }}</strong>
                    <div class="{{ ($facts['matching_ok'] ?? false) ? 'text-success' : 'text-danger' }}"><i class="bi {{ ($facts['matching_ok'] ?? false) ? 'bi-check-circle' : 'bi-x-circle' }} me-1"></i>{{ ($facts['matching_ok'] ?? false) ? 'Grant complies' : 'Grant above the maximum' }}</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="border rounded-3 p-2 h-100 small">
                    <div class="detail-label mb-1">Cumulative grant limit</div>
                    Earlier grants {{ $m($facts['previous'] ?? 0) }} + this {{ $m($grant) }} = <strong>{{ $m($facts['cumulative'] ?? null) }}</strong> of {{ $m($facts['cap'] ?? null) }}
                    <div class="text-muted">{{ $u($facts['cap'] ?? null) }} limit</div>
                    <div class="{{ ($facts['cap_ok'] ?? false) ? 'text-success' : 'text-danger' }}"><i class="bi {{ ($facts['cap_ok'] ?? false) ? 'bi-check-circle' : 'bi-x-circle' }} me-1"></i>{{ ($facts['cap_ok'] ?? false) ? 'Within the limit' : 'Exceeds the limit' }}</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="border rounded-3 p-2 h-100 small">
                    @php
                        $lsPct  = $form['lesotho_pct'] ?? null;
                        $lsOut  = $p?->grant_outside_lesotho;
                        $lsWhy  = $p?->grant_outside_justification;
                        $lsMin  = (int) $G::minLesothoPct();
                    @endphp
                    <div class="detail-label mb-1">Spending in Lesotho <span class="text-muted fw-normal">(from the enterprise profile)</span></div>
                    @if (is_numeric($lsPct))
                        <div><strong>{{ rtrim(rtrim(number_format((float) $lsPct, 1), '0'), '.') }}%</strong> of the grant in Lesotho · {{ $m($lsOut) }} outside</div>
                        <div class="{{ ($facts['lesotho_ok'] ?? false) ? 'text-success' : 'text-danger' }}">
                            <i class="bi {{ ($facts['lesotho_ok'] ?? false) ? 'bi-check-circle' : 'bi-exclamation-triangle' }} me-1"></i>
                            {{ ($facts['lesotho_ok'] ?? false) ? 'Meets' : 'Below' }} the {{ $lsMin }}% minimum
                        </div>
                        @if ($lsWhy)
                            <details class="mt-1"><summary class="text-primary" style="cursor:pointer">Enterprise's justification</summary>
                                <div class="text-muted mt-1" style="white-space: pre-line">{{ $lsWhy }}</div></details>
                        @elseif (! ($facts['lesotho_ok'] ?? false))
                            <div class="text-danger mt-1">Not justified by the enterprise yet.</div>
                        @endif
                    @else
                        <div class="text-muted">Not declared yet — the enterprise enters it in its profile (Business section, after the budget). Minimum {{ $lsMin }}% in Lesotho.</div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Grant utilisation budget from the profile --}}
        @if ($btotals['grant'] > 0)
            <details class="evidence mb-3">
                <summary><i class="bi bi-pie-chart me-1"></i> Grant utilisation budget (enterprise profile) — CAFI grant {{ $m($btotals['grant']) }}</summary>
                <div class="table-responsive">
                    <table class="table table-modern table-sm align-middle mb-0">
                        <thead><tr><th>Category</th><th class="text-end">CAFI grant (M)</th></tr></thead>
                        <tbody>
                            @foreach ($budget as $row)
                                @continue((float) ($row['grant'] ?? 0) <= 0)
                                <tr><td class="small">{{ $row['category'] }}</td>
                                    <td class="text-end small">{{ number_format((float) ($row['grant'] ?? 0)) }}</td></tr>
                                @foreach ($row['items'] ?? [] as $bit)
                                    <tr><td class="small text-muted ps-4">↳ {{ $bit['name'] ?? '' }}</td><td class="text-end small text-muted">{{ number_format((float) ($bit['grant'] ?? 0)) }}</td></tr>
                                @endforeach
                            @endforeach
                        </tbody>
                        <tfoot><tr class="fw-semibold"><td>Total</td><td class="text-end">{{ number_format($btotals['grant']) }}</td></tr></tfoot>
                    </table>
                </div>
                @if ($p?->document('budget'))
                    <div class="p-2 small"><a href="{{ route('enterprise-documents.download', $p->document('budget')) }}" class="text-decoration-none"><i class="bi bi-download me-1"></i>Operational budget (uploaded)</a></div>
                @endif
            </details>
        @endif

        {{-- Checks --}}
        <div class="table-responsive">
            <table class="table table-modern align-middle mb-3 dd-table">
                <thead><tr><th>The review confirms that</th><th style="width: 19%">Result</th><th style="width: 32%">Note (required for an exception)</th></tr></thead>
                <tbody>
                    @foreach ($G::CHECKS as $ck => [$cTitle, $cText])
                        @php $cs = $form['checks'][$ck]['status'] ?? null; @endphp
                        <tr wire:key="grc-{{ $ck }}" class="{{ $cs === 'action' ? 'is-flagged' : '' }}">
                            <td class="small"><div class="fw-semibold text-navy">{{ $cTitle }}</div><div class="text-muted">{{ $cText }}</div></td>
                            <td>
                                <select class="form-select form-select-sm {{ ['confirmed' => 'is-complete', 'exception' => 'is-clarification', 'action' => 'is-no'][$cs] ?? '' }} status-select" wire:model.live="form.checks.{{ $ck }}.status" @disabled(! $canG)>
                                    <option value="">—</option>
                                    @foreach ($G::CHECK_STATUSES as $sk => [$sl]) <option value="{{ $sk }}">{{ $sl }}</option> @endforeach
                                </select>
                            </td>
                            <td><input type="text" class="form-control form-control-sm" wire:model.blur="form.checks.{{ $ck }}.note" maxlength="500"
                                       placeholder="{{ $ck === 'budget' ? 'Justify any spending outside Lesotho' : 'Short note' }}" @disabled(! $canG)></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Bank account --}}
        <div class="g2-sub">
            <div class="g2-sub-head" id="g4-bank"><i class="bi bi-bank me-1"></i> Bank account verification <span class="text-muted fw-normal">— entered by the applicant at this step; check it against their bank confirmation letter</span></div>

            {{-- Request from the applicant --}}
            @php
                $bkAsked = $g?->bank_requested_at;
                $bkSent  = $g?->bank_submitted_at;
                $bkAsker = $g?->bank_requested_by ? \App\Models\User::find($g->bank_requested_by)?->name : null;
            @endphp
            <div class="d-flex flex-wrap align-items-center gap-2 border rounded-3 p-2 mb-3 {{ $bkSent ? 'bg-success-subtle' : 'bg-light' }}">
                <i class="bi {{ $bkSent ? 'bi-check2-circle text-success' : ($bkAsked ? 'bi-envelope-check text-primary' : 'bi-envelope') }} fs-5 ms-1"></i>
                <div class="small me-auto">
                    @if ($bkSent)
                        <strong>Received from the applicant</strong> on {{ $bkSent->format('d M Y H:i') }}.
                        <span class="text-muted">They can still correct them until you submit to the Hub Manager.</span>
                    @elseif ($bkAsked)
                        <strong>Requested</strong> {{ $bkAsked->diffForHumans() }}{{ $bkAsker ? ' by '.$bkAsker : '' }} — waiting for the applicant.
                    @else
                        The applicant enters the bank details and uploads the letter themselves. Send them a link by e-mail.
                    @endif
                </div>
                @if ($canG)
                    <button type="button" class="btn btn-sm {{ $bkAsked ? 'btn-outline-secondary' : 'btn-brand' }}" wire:click="requestBankDetails" wire:loading.attr="disabled" wire:target="requestBankDetails"
                            wire:confirm="{{ $bkAsked ? 'Send the applicant a reminder by e-mail?' : 'E-mail the applicant a link to enter their bank details and upload a bank confirmation letter?' }}">
                        <span wire:loading.remove wire:target="requestBankDetails"><i class="bi bi-envelope-arrow-up me-1"></i>{{ $bkAsked ? 'Send reminder' : 'Request bank details' }}</span>
                        <span wire:loading wire:target="requestBankDetails"><span class="spinner-border spinner-border-sm me-1"></span> Sending…</span>
                    </button>
                @endif
            </div>
            @php
                $bk        = $form['bank'] ?? [];
                $bkFilled  = filled($bk['bank'] ?? null) && filled($bk['account_name'] ?? null) && filled($bk['account_number'] ?? null);
                $bankDoc   = $p?->document('bank_confirmation');
                $nameMatch = $p?->registered_name && filled($bk['account_name'] ?? null)
                    && \Illuminate\Support\Str::lower(trim($bk['account_name'])) === \Illuminate\Support\Str::lower(trim($p->registered_name));
            @endphp
            @if ($bkFilled)
                <div class="detail-grid mb-2">
                    <div><dt>Bank</dt><dd>{{ $bk['bank'] }}{{ filled($bk['branch'] ?? null) ? ' · '.$bk['branch'] : '' }}</dd></div>
                    <div><dt>Account name</dt><dd>{{ $bk['account_name'] }}
                        @if ($p?->registered_name)
                            <div class="small {{ $nameMatch ? 'text-success' : 'text-warning' }}"><i class="bi {{ $nameMatch ? 'bi-check-circle' : 'bi-exclamation-triangle' }} me-1"></i>{{ $nameMatch ? 'Matches the registered name' : 'Differs from the registered name ('.$p->registered_name.') — check it is held in the enterprise’s name' }}</div>
                        @endif</dd></div>
                    <div><dt>Account number</dt><dd><span class="font-monospace">{{ $bk['account_number'] }}</span></dd></div>
                    <div><dt>Account type</dt><dd>{{ $G::ACCOUNT_TYPES[$bk['account_type'] ?? ''] ?? '—' }}</dd></div>
                </div>
            @else
                <div class="callout callout-warning small mb-2"><i class="bi bi-exclamation-triangle me-1"></i>
                    The applicant has not entered their bank details yet.{{ $bkAsked ? '' : ' Use Request bank details above to e-mail them the link.' }}</div>
            @endif

            <div class="row g-2">
                <div class="col-md-6">
                    <label class="form-label small fw-semibold mb-1">Evidence from the applicant</label>
                    @if ($bankDoc)
                        <div class="small"><a href="{{ route('enterprise-documents.download', $bankDoc) }}" class="text-decoration-none"><i class="bi bi-paperclip"></i> {{ \Illuminate\Support\Str::limit($bankDoc->original_name, 45) }}</a>
                            <span class="text-muted">· bank confirmation letter</span></div>
                    @else
                        <div class="small text-muted">No bank confirmation letter uploaded yet.</div>
                    @endif
                    <label class="form-label small fw-semibold mb-1 mt-2">Other verification document <span class="text-muted fw-normal">(optional{{ $bankDoc ? '' : ' — needed if the applicant has not uploaded a letter' }})</span></label>
                    @if ($g?->bank_evidence_path)
                        <div class="d-flex align-items-center gap-2 small">
                            <a href="{{ route('grant-readiness.file', [$g, 'bank_evidence']) }}" class="text-decoration-none text-truncate"><i class="bi bi-paperclip"></i> {{ $g->bank_evidence_name }}</a>
                            @if ($canG) <label class="btn btn-sm btn-outline-secondary mb-0 ms-auto">Replace <input type="file" class="d-none" wire:model="bankEvidenceFile" accept=".pdf,.jpg,.jpeg,.png"></label> @endif
                        </div>
                    @elseif ($canG)
                        <input type="file" class="form-control form-control-sm @error('bankEvidenceFile') is-invalid @enderror" wire:model="bankEvidenceFile" accept=".pdf,.jpg,.jpeg,.png">
                    @else
                        <div class="small text-muted">—</div>
                    @endif
                    <span wire:loading wire:target="bankEvidenceFile" class="small text-muted"><span class="spinner-border spinner-border-sm me-1"></span> Uploading…</span>
                    @error('bankEvidenceFile') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold mb-1">Verified against <span class="req">*</span></label>
                    <select class="form-select form-select-sm @error('form.bank.verified_against') is-invalid @enderror" wire:model.live="form.bank.verified_against" @disabled(! $canG)>
                        <option value="">Select…</option>
                        @foreach ($G::BANK_EVIDENCE as $ek => $el) <option value="{{ $ek }}">{{ $el }}</option> @endforeach
                    </select>
                    @error('form.bank.verified_against') <div class="text-danger small">{{ $message }}</div> @enderror
                    <div class="form-text">Then record the result in the <strong>Bank account</strong> check above: Confirmed if the details match the evidence, or Action required if the applicant must correct them (they can, until you submit).</div>
                </div>
            </div>
        </div>

        {{-- Outstanding issues --}}
        <div class="g2-sub">
            <div class="g2-sub-head"><i class="bi bi-list-task me-1"></i> Outstanding conditions, clarifications or documents <span class="text-muted fw-normal">— with who follows up</span></div>
            <div class="table-responsive">
                <table class="table table-modern align-middle mb-2 dd-table">
                    <thead><tr><th>Issue</th><th style="width: 17%">Responsible</th><th style="width: 28%">Required action</th><th style="width: 12%">Status</th>@if ($canG)<th></th>@endif</tr></thead>
                    <tbody>
                        @forelse ($form['issues'] as $i => $iss)
                            <tr wire:key="gri-{{ $i }}">
                                <td><input type="text" class="form-control form-control-sm" wire:model.blur="form.issues.{{ $i }}.issue" maxlength="250" @disabled(! $canG)></td>
                                <td><select class="form-select form-select-sm" wire:model.live="form.issues.{{ $i }}.party" @disabled(! $canG)>
                                    @foreach ($G::PARTIES as $pk => $pl) <option value="{{ $pk }}">{{ $pl }}</option> @endforeach</select></td>
                                <td><input type="text" class="form-control form-control-sm" wire:model.blur="form.issues.{{ $i }}.action" maxlength="250" @disabled(! $canG)></td>
                                <td><select class="form-select form-select-sm" wire:model.live="form.issues.{{ $i }}.status" @disabled(! $canG)>
                                    <option value="open">Open</option><option value="resolved">Resolved</option></select></td>
                                @if ($canG)<td><button type="button" class="btn btn-icon btn-icon-danger" wire:click="removeIssue({{ $i }})" title="Remove"><i class="bi bi-x-lg"></i></button></td>@endif
                            </tr>
                        @empty
                            <tr><td colspan="5" class="small text-muted">None identified.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($canG)
                <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="addIssue"><i class="bi bi-plus-lg me-1"></i> Add issue</button>
            @endif
        </div>

        {{-- Outputs --}}
        <div class="g2-sub">
            <div class="g2-sub-head"><i class="bi bi-file-earmark-text me-1"></i> Outputs</div>
            <div class="row g-2">
                <div class="col-md-6">
                    <div class="border rounded-3 p-2 h-100 small">
                        <div class="fw-semibold text-navy mb-1">Draft Grant Agreement <span class="req">*</span></div>
                        <div class="text-muted mb-2">Reflects the proposed grant amount, approved grant utilisation budget and applicable terms. PDF or Word.</div>
                        @if ($g?->agreement_path)
                            <div class="d-flex align-items-center gap-2">
                                <a href="{{ route('grant-readiness.file', [$g, 'agreement']) }}" class="text-decoration-none text-truncate"><i class="bi bi-file-earmark-check"></i> {{ $g->agreement_name }}</a>
                                @if ($canG) <label class="btn btn-sm btn-outline-secondary mb-0 ms-auto">Replace <input type="file" class="d-none" wire:model="agreementFile" accept=".pdf,.docx,.doc"></label> @endif
                            </div>
                        @elseif ($canG)
                            <input type="file" class="form-control form-control-sm @error('agreementFile') is-invalid @enderror" wire:model="agreementFile" accept=".pdf,.docx,.doc">
                        @else
                            <span class="text-muted">Not uploaded.</span>
                        @endif
                        <span wire:loading wire:target="agreementFile" class="text-muted"><span class="spinner-border spinner-border-sm me-1"></span> Uploading…</span>
                        @error('agreementFile') <div class="text-danger">{{ $message }}</div> @enderror
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="border rounded-3 p-2 h-100 small">
                        <div class="fw-semibold text-navy mb-1">Grant Readiness Memo</div>
                        <div class="text-muted mb-2">One page, generated from this review: transaction, budget & compliance, bank verification, documents, outstanding issues, recommendation and the approval record.</div>
                        @if ($g)
                            <a href="{{ route('grant-readiness.memo', $g) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-file-earmark-pdf me-1"></i> Download PDF</a>
                        @else
                            <span class="text-muted">Available once you start the review.</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Recommendation --}}
        <label class="form-label small fw-semibold">Readiness recommendation @if ($canG) <span class="req">*</span> @endif</label>
        <div class="choice-group cols-2 @error('form.recommendation') is-invalid @enderror">
            @foreach ($G::RECOMMENDATIONS as $rk => [$rl, , $ri])
                <label class="choice">
                    <input type="radio" name="gr-rec" value="{{ $rk }}" wire:model.live="form.recommendation" @disabled(! $canG)>
                    <span class="choice-box"><span class="choice-radio"></span><i class="bi {{ $ri }} {{ $rk === 'ready' ? 'text-accent' : 'text-danger' }}"></i> {{ $rl }}</span>
                </label>
            @endforeach
        </div>
        @error('form.recommendation') <div class="text-danger small mt-1">{{ $message }}</div> @enderror

        <div class="row g-3 mt-1">
            <div class="col-md-9">
                <label class="form-label small fw-semibold">Brief justification and any material qualifications @if ($canG) <span class="req">*</span> @endif</label>
                <textarea rows="2" class="form-control @error('form.justification') is-invalid @enderror" wire:model.blur="form.justification" maxlength="2000" @disabled(! $canG)></textarea>
                @error('form.justification') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Date</label>
                <input type="date" class="form-control @error('form.gm_on') is-invalid @enderror" wire:model.blur="form.gm_on" max="{{ now()->toDateString() }}" @disabled(! $canG)>
            </div>
        </div>

        @if ($canG)
            @if ($this->blockers)
                <div class="callout callout-warning small mt-3 mb-0">
                    <div class="fw-semibold mb-1"><i class="bi bi-list-check me-1"></i> Before you can submit to the Hub Manager ({{ count($this->blockers) }})</div>
                    <ul class="evidence-list mb-0">@foreach ($this->blockers as $b) <li>{{ $b }}</li> @endforeach</ul>
                </div>
            @else
                <div class="callout callout-success small mt-3 mb-0"><i class="bi bi-check2-circle me-1"></i> Ready to submit to the Hub Manager.</div>
            @endif
            <div class="d-flex mt-3 pt-3 border-top">
                <button type="button" class="btn btn-brand ms-auto" wire:click="submitToHub" wire:loading.attr="disabled" wire:target="submitToHub"
                        wire:confirm="Submit the Grant Readiness Memo and Draft Grant Agreement to the Hub Manager? Your part is locked afterwards.">
                    <span wire:loading.remove wire:target="submitToHub"><i class="bi bi-send me-1"></i> Submit to the Hub Manager</span>
                    <span wire:loading wire:target="submitToHub"><span class="spinner-border spinner-border-sm me-1"></span> Submitting…</span>
                </button>
            </div>
        @endif
    </section>

    {{-- ============================ 2 · HUB MANAGER · 3 · PMU DIRECTOR (§3.5.2 · §3.5.3) ============================ --}}
    @foreach ([
        ['hub', 2, 'Hub Manager review and approval', 'Consistent with growth objectives and Facility requirements, budget and documents satisfactory, earlier issues addressed?', $G::HUB_DECISIONS, $canH, $hubDone, $g?->hubManager, $g?->hub_decided_at, 'recordHub', 'Record Hub Manager decision'],
        ['director', 3, 'PMU Director of Implementation review and approval', 'Internal steps complete, memo and draft agreement available, grant amount and key conditions documented, material issues resolved?', $G::DIRECTOR_DECISIONS, $canD, $dirDone, $g?->director, $g?->director_decided_at, 'recordDirector', 'Record PMU Director decision'],
    ] as [$who, $no, $title, $question, $decisions, $can, $done, $byUser, $decidedAt, $action, $button])
        @php
            $open   = $stage === $who;
            $before = ($who === 'hub' && in_array($stage, ['grants'], true)) || ($who === 'director' && in_array($stage, ['grants', 'hub'], true));
            $val    = $who === 'hub' ? $hub : $director;
        @endphp
        <section class="section-card" id="g4-{{ $who }}">
            <div class="section-card-head">
                <span class="section-no">{{ $no }}</span>
                <div class="me-auto">
                    <h5>{{ $title }}</h5>
                    <div class="small text-muted">
                        @if ($done) {{ $byUser?->name ?? '—' }} · {{ $decidedAt?->format('d M Y H:i') }} @else {{ $question }} @endif
                    </div>
                </div>
                @if ($done)
                    @php [$dl, , $di, $dp] = $decisions[$val['decision']] ?? ['—', '', 'bi-circle', 'pill-muted']; @endphp
                    <span class="pill {{ $dp }}"><i class="bi {{ $di }}"></i> {{ $dl }}</span>
                @elseif ($open)
                    <span class="pill pill-warn"><i class="bi bi-hourglass-split"></i> Awaiting decision</span>
                @else
                    <span class="pill pill-muted"><i class="bi bi-lock-fill"></i> {{ $before ? 'Locked' : '—' }}</span>
                @endif
            </div>

            @if ($before && ! $done)
                <div class="g1-locked"><i class="bi bi-lock"></i>
                    <div>Opens once {{ $who === 'hub' ? 'Grants Management has submitted the file' : 'the Hub Manager has approved the file for submission' }}. The reviews are sequential.</div>
                </div>
            @else
                {{-- Review checklist: the three documents, then the criteria of this step --}}
                @php
                    $defs     = $who === 'hub' ? $G::HUB_CHECKS : $G::DIRECTOR_CHECKS;
                    $ticked   = collect($defs)->filter(fn ($d, $k) => $val['checks'][$k]['done'] ?? false)->count();
                    $ddMemo   = $this->dd;
                    $docLink  = fn (?string $doc) => match ($doc) {
                        'iam'       => $ddMemo ? [route('due-diligence.memorandum', $ddMemo), 'Open memorandum', 'bi-file-earmark-pdf'] : null,
                        'memo'      => $g ? [route('grant-readiness.memo', $g), 'Open memo', 'bi-file-earmark-pdf'] : null,
                        'agreement' => $g?->agreement_path ? [route('grant-readiness.file', [$g, 'agreement']), 'Open draft agreement', 'bi-file-earmark-text'] : null,
                        default     => null,
                    };
                @endphp
                <div class="g2-sub mb-3">
                    <div class="g2-sub-head d-flex align-items-center">
                        <span class="me-auto"><i class="bi bi-list-check me-1"></i> Review checklist</span>
                        <span class="pill {{ $ticked === count($defs) ? 'pill-ok' : 'pill-warn' }}">{{ $ticked }} / {{ count($defs) }} done</span>
                    </div>
                    <div class="small text-muted mb-2">Open each document, review it, then tick it. Every item must be ticked before you can approve.</div>
                    <div class="d-flex flex-column gap-2">
                        @foreach ($defs as $ck => [$cLabel, $cHint, $cDoc])
                            @php $row = $val['checks'][$ck] ?? ['done' => false, 'note' => '', 'at' => null]; $link = $docLink($cDoc); @endphp
                            <div class="border rounded-3 p-2 {{ $row['done'] ? 'border-success' : '' }} @error("$who.checks.$ck") border-danger @enderror" wire:key="{{ $who }}-chk-{{ $ck }}">
                                <div class="d-flex flex-wrap align-items-start gap-2">
                                    <div class="form-check mb-0 flex-grow-1">
                                        <input type="checkbox" class="form-check-input" id="{{ $who }}-chk-{{ $ck }}" wire:model.live="{{ $who }}.checks.{{ $ck }}.done" @disabled(! $can)>
                                        <label class="form-check-label" for="{{ $who }}-chk-{{ $ck }}">
                                            <span class="fw-semibold text-navy small">{{ $cLabel }}</span>
                                            <span class="d-block small text-muted">{{ $cHint }}</span>
                                            @if ($row['done'] && $row['at'])
                                                <span class="d-block small text-success"><i class="bi bi-check2"></i> Ticked {{ \Illuminate\Support\Carbon::parse($row['at'])->format('d M Y H:i') }}</span>
                                            @endif
                                        </label>
                                    </div>
                                    @if ($cDoc)
                                        @if ($link)
                                            <a href="{{ $link[0] }}" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="bi {{ $link[2] }} me-1"></i> {{ $link[1] }}</a>
                                        @else
                                            <span class="pill pill-warn"><i class="bi bi-exclamation-triangle"></i> Not available</span>
                                        @endif
                                    @endif
                                </div>
                                @if ($can)
                                    <input type="text" class="form-control form-control-sm mt-2" wire:model.blur="{{ $who }}.checks.{{ $ck }}.note" placeholder="Note (optional)">
                                @elseif (filled($row['note']))
                                    <div class="small text-muted mt-1 fst-italic">“{{ $row['note'] }}”</div>
                                @endif
                                @error("$who.checks.$ck") <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="choice-group cols-2 @error("$who.decision") is-invalid @enderror">
                    @foreach ($decisions as $v => [$dLabel, $dDesc, $dIcon])
                        <label class="choice choice--card">
                            <input type="radio" name="gr-{{ $who }}" value="{{ $v }}" wire:model.live="{{ $who }}.decision" @disabled(! $can)>
                            <span class="choice-box">
                                <span class="choice-icon"><i class="bi {{ $dIcon }}"></i></span>
                                <span><span class="d-block fw-semibold">{{ $dLabel }}</span><span class="d-block small text-muted lh-sm mt-1">{{ $dDesc }}</span></span>
                            </span>
                        </label>
                    @endforeach
                </div>
                @error("$who.decision") <div class="text-danger small mt-1">{{ $message }}</div> @enderror

                <div class="row g-3 mt-1">
                    <div class="col-md-9">
                        <label class="form-label small fw-semibold">Comments @if ($can && ($val['decision'] ?? null) === 'returned') <span class="req">*</span> @endif</label>
                        @if ($can)
                            <textarea rows="2" class="form-control @error("$who.comments") is-invalid @enderror" wire:model.blur="{{ $who }}.comments"
                                      placeholder="{{ ($val['decision'] ?? null) === 'returned' ? 'What must be resolved, and by whom' : 'Optional' }}"></textarea>
                            @error("$who.comments") <div class="invalid-feedback">{{ $message }}</div> @enderror
                        @else
                            <div class="quote mb-0">{{ $val['comments'] ?: 'No comments.' }}</div>
                        @endif
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Date</label>
                        <input type="date" class="form-control @error("$who.on") is-invalid @enderror" wire:model.blur="{{ $who }}.on" max="{{ now()->toDateString() }}" @disabled(! $can)>
                    </div>
                </div>

                @if ($can)
                    <div class="d-flex mt-3 pt-3 border-top">
                        <button type="button" class="btn btn-brand ms-auto" wire:click="{{ $action }}" wire:loading.attr="disabled" wire:target="{{ $action }}"
                                wire:confirm="Record this decision? It can't be changed afterwards.">
                            <span wire:loading.remove wire:target="{{ $action }}"><i class="bi bi-send me-1"></i> {{ $button }}</span>
                            <span wire:loading wire:target="{{ $action }}"><span class="spinner-border spinner-border-sm me-1"></span> Recording…</span>
                        </button>
                    </div>
                @endif
            @endif
        </section>
    @endforeach

    {{-- ============================ APPROVAL RECORD ============================ --}}
    <section class="section-card">
        <div class="section-card-head">
            <span class="section-no"><i class="bi bi-pen"></i></span>
            <div class="me-auto">
                <h5>Approval record</h5>
                <div class="small text-muted">The transaction proceeds to Gate 5 only after all three steps are complete.</div>
            </div>
            @if ($g?->isApproved()) <span class="pill pill-ok"><i class="bi bi-patch-check"></i> Approved to proceed</span> @endif
        </div>
        <div class="table-responsive">
            <table class="table table-modern table-sm align-middle mb-0">
                <thead><tr><th>Review stage</th><th>Name</th><th>Date</th><th>Decision</th></tr></thead>
                <tbody>
                    <tr>
                        <td class="small fw-semibold text-navy">Grants Management Representative</td>
                        <td class="small">{{ $g?->grantsOfficer?->name ?? '—' }}</td>
                        <td class="small">{{ $gmDone ? ($g->gm_on ?? $g->gm_submitted_at)->format('d M Y') : '—' }}</td>
                        <td>@if ($gmDone && $g->recommendation) @php [$rl, $rp] = $G::RECOMMENDATIONS[$g->recommendation]; @endphp <span class="pill {{ $rp }}">{{ $rl }}</span> @else <span class="pill pill-warn">Pending</span> @endif</td>
                    </tr>
                    <tr>
                        <td class="small fw-semibold text-navy">Hub Manager</td>
                        <td class="small">{{ $g?->hubManager?->name ?? '—' }}</td>
                        <td class="small">{{ $hubDone ? $g->hub_on?->format('d M Y') : '—' }}</td>
                        <td>@if ($hubDone) <span class="pill {{ $G::HUB_DECISIONS[$g->hub_decision][3] }}">{{ $G::HUB_DECISIONS[$g->hub_decision][0] }}</span> @else <span class="pill pill-muted">Pending</span> @endif</td>
                    </tr>
                    <tr>
                        <td class="small fw-semibold text-navy">PMU Director of Implementation</td>
                        <td class="small">{{ $g?->director?->name ?? '—' }}</td>
                        <td class="small">{{ $dirDone ? $g->director_on?->format('d M Y') : '—' }}</td>
                        <td>@if ($dirDone) <span class="pill {{ $G::DIRECTOR_DECISIONS[$g->director_decision][3] }}">{{ $G::DIRECTOR_DECISIONS[$g->director_decision][0] }}</span> @else <span class="pill pill-muted">Pending</span> @endif</td>
                    </tr>
                </tbody>
            </table>
        </div>

        @if ($g?->isApproved())
            <div class="callout callout-success small mt-3 mb-0">
                <i class="bi bi-box-arrow-in-right me-1"></i> Cleared to advance to <strong>Gate 5 · Final authorisation</strong> — the Grant Agreement is signed by the CAFI Managing Director, then the MTIBD Principal Secretary.
            </div>
        @endif
    </section>

    {{-- ============================ EARLIER ROUNDS ============================ --}}
    @if ($this->history->isNotEmpty())
        <section class="section-card">
            <div class="section-card-head"><span class="section-no"><i class="bi bi-clock-history"></i></span><h5>Earlier rounds</h5></div>
            @foreach ($this->history as $h)
                <div class="file-row align-items-start">
                    <span class="file-row-icon"><i class="bi {{ $h->isApproved() ? 'bi-patch-check' : 'bi-arrow-return-left' }}"></i></span>
                    <div class="flex-grow-1 small">
                        <strong class="text-navy">Round {{ $h->round }}</strong>
                        <span class="pill {{ $h->isApproved() ? 'pill-ok' : 'pill-warn' }} ms-1">{{ $h->isApproved() ? 'Approved to proceed' : 'Returned' }}</span>
                        <span class="text-muted ms-1">{{ $h->decided_at->format('d M Y') }} · by the {{ $G::STAGES[$h->returnedBy() ?? 'director'][1] }}</span>
                        @if ($h->returnComment()) <div class="text-muted mt-1">{{ $h->returnComment() }}</div> @endif
                    </div>
                    <a href="{{ route('grant-readiness.memo', $h) }}" class="btn btn-icon" title="Memo — round {{ $h->round }}"><i class="bi bi-file-earmark-pdf"></i></a>
                </div>
            @endforeach
        </section>
    @endif
@endif
</div>