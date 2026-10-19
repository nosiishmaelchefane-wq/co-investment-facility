<?php

use App\Models\Authorisation;
use App\Models\CommitteeReview;
use App\Models\DueDiligence;
use App\Models\EnterpriseProfile;
use App\Models\ExpressionOfInterest;
use App\Notifications\EoiAuthorisationUpdate;
use App\Notifications\EoiGate4Outcome;
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
| G4 · Final Authorisation (CI Facility Operations Manual §3.5)
|   Used in the EOI page:  <livewire:expression-of-interest.authorisation.authorise-application :eoi-id="$eoi->id" />
|
|   Gate 3 recommendation → CAFI Managing Director review → MTIBD Principal Secretary final authorisation → Gate 5
|   Sequential, not concurrent: the Principal Secretary acts only after the Managing Director approves.
|
|   gate4.md  Managing Director: confirms the 6 review checks, classifies the conditions (§3.5.4), then
|             Approve & forward · Return for clarification · Not approve
|   gate4.ps  Principal Secretary: Authorise · Request clarification · Decline
|   Outcomes (§3.5.5): authorised → Gate 5 · clarification → authorisation_hold (resubmit = new round) · not authorised → not_progressed
|   Resubmission (PMU gate2.assess or MD): a round held by the PS goes straight back to the PS; one held by the MD restarts at the MD.
|
|   Signing outside the system — the MD and PS do not log in. For each authority, the PMU (gate2.assess) or a user with gate4.md / gate4.ps:
|     1. attaches the document to be signed and records who it was sent to (the PMU sends it themselves — no e-mail from the system),
|     2. uploads the signed copy when it comes back,
|     3. records the decision written on it (checks, conditions, decision, comments, date). A signed copy is required.
|
|   E-mails (sent after the decision is saved; a failed e-mail never undoes it):
|     MD approve        → PMU: send to the Principal Secretary next
|     MD / PS return    → PMU: resolve and resubmit (internal — the applicant is not e-mailed)
|     MD / PS decline   → applicant: EoiGate4Outcome (not authorised) · PMU, Committee, MD/PS accounts: EoiAuthorisationUpdate
|     PS authorise      → applicant: EoiGate4Outcome (authorised) · PMU, Committee, Finance, MD/PS accounts: EoiAuthorisationUpdate
*/
new class extends Component
{
    use WithFileUploads;

    #[Locked] public int $eoiId;
    #[Locked] public ?int $authId = null;
    #[Locked] public int $round = 1;

    /** [source, finding, type, status] */
    public array $conditions = [];

    /** Managing Director: checks{}, decision, comments, on */
    public array $md = [];

    /** Principal Secretary: decision, comments, on */
    public array $ps = [];

    /** role => [name, email, message] — who the document is sent to for signing */
    public array $signing = [];

    /** role => document to be signed / signed copy (uploads) */
    public array $requestFile = [];
    public array $signedFile = [];

    public string $resubmissionNote = '';
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

    #[Computed] public function auth(): ?Authorisation
    {
        return $this->authId ? Authorisation::with('managingDirector:id,name', 'principalSecretary:id,name')->find($this->authId) : null;
    }

    /** The Committee recommendation being authorised (latest approve / conditional) */
    #[Computed] public function review(): ?CommitteeReview
    {
        return CommitteeReview::where('expression_of_interest_id', $this->eoiId)
            ->whereIn('outcome', [CommitteeReview::OUTCOME_APPROVE, CommitteeReview::OUTCOME_CONDITIONAL])
            ->whereNotNull('decided_at')->orderByDesc('round')->first();
    }

    /** The Gate 2 assessment that went to the Committee */
    #[Computed] public function dd(): ?DueDiligence
    {
        return DueDiligence::where('expression_of_interest_id', $this->eoiId)
            ->where('outcome', DueDiligence::OUTCOME_PROCEED)->whereNotNull('decided_at')
            ->orderByDesc('round')->first();
    }

    #[Computed] public function profile(): ?EnterpriseProfile
    {
        return EnterpriseProfile::with('documents')->where('user_id', $this->eoi->user_id)->first();
    }

    #[Computed] public function history()
    {
        return Authorisation::with('managingDirector:id,name', 'principalSecretary:id,name')
            ->where('expression_of_interest_id', $this->eoiId)->whereNotNull('decided_at')
            ->when($this->authId, fn ($q) => $q->whereKeyNot($this->authId))
            ->orderByDesc('round')->get();
    }

    #[Computed] public function isApplicant(): bool
    {
        return $this->eoi->user_id === auth()->id();
    }

    #[Computed] public function gateOpen(): bool
    {
        return in_array($this->eoi->status, ExpressionOfInterest::AUTHORISATION_VISIBLE, true) || $this->authId !== null;
    }

    /** md | ps | decided */
    #[Computed] public function stage(): string
    {
        return $this->auth?->stage() ?? 'md';
    }

    #[Computed] public function isOpen(): bool
    {
        return $this->eoi->status === ExpressionOfInterest::STATUS_AUTHORISATION && ! ($this->auth?->isDecided());
    }

    #[Computed] public function canMd(): bool
    {
        return ! $this->isApplicant && $this->isOpen && $this->stage === 'md' && auth()->user()->canAny(['gate4.md', 'gate2.assess']);
    }

    #[Computed] public function canPs(): bool
    {
        return ! $this->isApplicant && $this->isOpen && $this->stage === 'ps' && auth()->user()->canAny(['gate4.ps', 'gate2.assess']);
    }

    #[Computed] public function canResubmit(): bool
    {
        return ! $this->isApplicant
            && $this->eoi->status === ExpressionOfInterest::STATUS_AUTH_HOLD
            && $this->auth?->outcome === Authorisation::OUTCOME_CLARIFICATION
            && (auth()->user()->can('gate2.assess') || auth()->user()->can('gate4.md'));
    }

    /** Why the Managing Director can't approve yet */
    #[Computed] public function blockers(): array
    {
        return Authorisation::approveBlockers($this->md['checks'] ?? [], $this->conditions);
    }

    /** §3.5.1 transaction file: key => [document, purpose, icon, present, links[[label, url]]] */
    #[Computed] public function file(): array
    {
        $eoi = $this->eoi; $dd = $this->dd; $p = $this->profile; $review = $this->review;
        $tab = fn (string $t, string $anchor = '') => route('expression-of-interest.show', $eoi->id).'?tab='.$t.($anchor ? '#'.$anchor : '');
        $doc = fn (string $type) => $p?->document($type);
        $dl  = fn ($d, $label) => $d ? [[$label, route('enterprise-documents.download', $d)]] : [];

        $txDocs = collect(['commitment' => 'Term sheet', 'cap_table' => 'Cap table', 'shareholders_list' => 'Shareholders list', 'registration_cert' => 'Registration'])
            ->map(fn ($label, $type) => $doc($type) ? [$label, route('enterprise-documents.download', $doc($type))] : null)
            ->filter()->values()->all();

        return [
            'recommendation' => ['Investment Committee recommendation', 'Formal recommendation and decision rationale',          'bi-people',            (bool) $review,             $review ? [['Open Gate 3', $tab('g3')]] : []],
            'iam'            => ['Investment Appraisal Memorandum',     'Consolidated transaction assessment',                    'bi-file-earmark-pdf',  (bool) $dd?->iam_path,      $dd?->iam_path ? [['Download', route('due-diligence.memorandum', $dd)]] : []],
            'tss'            => ['Transaction Summary Sheet',           'Key transaction and financing information',              'bi-file-earmark-text', (bool) $dd,                 $dd ? [['Open', $tab('g2', 'g2-summary')]] : []],
            'dd'             => ['Due diligence findings',              'Enterprise, investor and transaction assessment',        'bi-search',            (bool) $dd,                 $dd ? [['Open', $tab('g2', 'g2-checklist')]] : []],
            'esgc'           => ['ESGC assessment',                     'Risk classification and mitigation measures',            'bi-tree',              (bool) $dd?->esgc_category, $dd ? [['Open', $tab('g2', 'g2-area-esgc')]] : []],
            'calculation'    => ['Co-investment calculation',           'Private investment and proposed CAFI grant',             'bi-calculator',        (bool) $dd,                 $dd ? [['Open', $tab('g2', 'g2-figures')]] : []],
            'use_of_funds'   => ['Use-of-funds plan',                   'Operational budget, including the use of the grant',     'bi-pie-chart',         (bool) $doc('budget'),      $dl($doc('budget'), 'Budget')],
            'conditions'     => ['Conditions identified',               'Matters to be addressed before contracting/disbursement','bi-list-check',        true,                       [['See below', '#g4-conditions']]],
            'documents'      => ['Relevant transaction documents',      'Evidence supporting the proposed investment',            'bi-paperclip',         (bool) $txDocs,             $txDocs],
        ];
    }

    /* =====================================================================
     | Load
     ===================================================================== */

    protected function load(): void
    {
        $a = Authorisation::where('expression_of_interest_id', $this->eoiId)->orderByDesc('round')->first();

        $this->authId     = $a?->id;
        $this->round      = $a?->round ?? 1;
        $this->conditions = $a ? array_values($a->conditions ?? []) : Authorisation::conditionsFrom($this->review);

        $checks   = (array) ($a?->md_checks ?? []);
        $this->md = [
            'checks'   => collect(Authorisation::MD_CHECKS)->mapWithKeys(fn ($t, $k) => [$k => (bool) ($checks[$k] ?? false)])->all(),
            'decision' => $a?->md_decision,
            'comments' => (string) $a?->md_comments,
            'on'       => $a?->md_on?->toDateString() ?? now()->toDateString(),
        ];
        $this->ps = [
            'decision' => $a?->ps_decision,
            'comments' => (string) $a?->ps_comments,
            'on'       => $a?->ps_on?->toDateString() ?? now()->toDateString(),
        ];

        foreach (['md', 'ps'] as $r) {
            // Typed in by hand on the page; a new round starts from the signer used in an earlier round
            $prev = $a?->{"{$r}_signer_email"} ? $a : Authorisation::where('expression_of_interest_id', $this->eoiId)
                ->whereNotNull("{$r}_signer_email")->orderByDesc('round')->first();
            $this->signing[$r] = [
                'name'       => (string) $prev?->{"{$r}_signer_name"},
                'email'      => (string) $prev?->{"{$r}_signer_email"},
                'message'    => (string) $a?->{"{$r}_request_message"},
            ];
        }

        unset($this->auth, $this->stage, $this->history, $this->blockers);
    }

    /* =====================================================================
     | Autosave
     ===================================================================== */

    public function updated(string $name): void
    {
        if (Str::startsWith($name, ['conditions', 'md'])) {
            if (! $this->canMd) {
                $this->load();   // discard changes from someone who can't edit this stage
                return;
            }
            $this->persist();
        } elseif (Str::startsWith($name, 'ps')) {
            if (! $this->canPs) {
                $this->load();
                return;
            }
            $this->persist();
        }

        unset($this->blockers);
    }

    public function addCondition(): void
    {
        abort_unless($this->canMd, 403);
        $this->conditions[] = ['source' => 'g4', 'finding' => '', 'type' => null, 'status' => 'open'];
        $this->persist();
    }

    public function removeCondition(int $i): void
    {
        abort_unless($this->canMd, 403);
        abort_unless(($this->conditions[$i]['source'] ?? null) === 'g4', 403, 'Conditions from Gates 2–3 can be resolved but not removed here.');
        unset($this->conditions[$i]);
        $this->conditions = array_values($this->conditions);
        $this->persist();
    }

    /* =====================================================================
     | Signing outside the system (MD / PS)
     ===================================================================== */

    protected function canRole(string $role): bool
    {
        return match ($role) { 'md' => $this->canMd, 'ps' => $this->canPs, default => false };
    }

    /** Attach the document to be signed and record who it was sent to (the PMU sends it themselves) */
    public function markSent(string $role): void
    {
        abort_unless($this->canRole($role), 403);

        $a       = $this->authId ? Authorisation::find($this->authId) : null;
        $hasFile = (bool) $a?->{"{$role}_request_path"};

        $this->validate([
            "signing.$role.name"    => ['required', 'string', 'max:255'],
            "signing.$role.email"   => ['required', 'email', 'max:255'],
            "signing.$role.message" => ['nullable', 'string', 'max:3000'],
            "requestFile.$role"     => [$hasFile ? 'nullable' : 'required', 'file', 'mimes:'.Authorisation::REQUEST_MIMES, 'max:'.Authorisation::MAX_KB],
        ], [
            "requestFile.$role.required" => 'Attach the document to be signed.',
            "requestFile.$role.mimes"    => 'Attach a PDF or Word document.',
            "requestFile.$role.max"      => 'The document must be '.(Authorisation::MAX_KB / 1024).' MB or smaller.',
        ], [
            "signing.$role.name" => 'name', "signing.$role.email" => 'e-mail address', "signing.$role.message" => 'note',
        ]);

        $a = $this->persist();
        $s = $this->signing[$role];

        if ($file = $this->requestFile[$role] ?? null) {
            if ($a->{"{$role}_request_path"}) {
                Storage::disk(Authorisation::DISK)->delete($a->{"{$role}_request_path"});
            }
            $a->forceFill([
                "{$role}_request_path" => $file->store("authorisations/{$a->id}", Authorisation::DISK),
                "{$role}_request_name" => $file->getClientOriginalName(),
            ]);
        }

        $a->forceFill([
            "{$role}_signer_name"     => trim($s['name']),
            "{$role}_signer_email"    => trim($s['email']),
            "{$role}_request_message" => trim((string) $s['message']) ?: null,
            "{$role}_sent_at"         => now(),
            "{$role}_sent_by"         => auth()->id(),
        ])->save();

        unset($this->requestFile[$role], $this->auth);
        $this->dispatch('notify', type: 'success', message: 'Recorded as sent to '.$a->{"{$role}_signer_name"}.' for signing. Download the document and send it to them.');
    }

    /** The signed copy came back → upload it (saved straight away) */
    public function updatedSignedFile($file, string $role): void
    {
        abort_unless(in_array($role, ['md', 'ps'], true) && $this->canRole($role), 403);

        $this->validate(
            ["signedFile.$role" => ['required', 'file', 'mimes:'.Authorisation::SIGNED_MIMES, 'max:'.Authorisation::MAX_KB]],
            ["signedFile.$role.mimes" => 'Upload a PDF or a scan / photo (JPG, PNG).', "signedFile.$role.max" => 'The file must be '.(Authorisation::MAX_KB / 1024).' MB or smaller.'],
        );

        $a = $this->persist();
        if ($a->{"{$role}_signed_path"}) {
            Storage::disk(Authorisation::DISK)->delete($a->{"{$role}_signed_path"});
        }
        $a->forceFill([
            "{$role}_signed_path"        => $file->store("authorisations/{$a->id}", Authorisation::DISK),
            "{$role}_signed_name"        => $file->getClientOriginalName(),
            "{$role}_signed_size"        => $file->getSize(),
            "{$role}_signed_at"          => now(),
            "{$role}_signed_uploaded_by" => auth()->id(),
            // If it was sent outside the system, keep at least who signed
            "{$role}_signer_name"        => $a->{"{$role}_signer_name"} ?: (trim($this->signing[$role]['name'] ?? '') ?: null),
        ])->save();

        unset($this->signedFile[$role], $this->auth);
        $this->dispatch('notify', type: 'success', message: 'Signed copy uploaded. Now record the decision written on it.');
    }

    public function removeSigned(string $role): void
    {
        abort_unless($this->canRole($role) && $this->authId, 403);

        $a = Authorisation::findOrFail($this->authId);
        if ($a->{"{$role}_signed_path"}) {
            Storage::disk(Authorisation::DISK)->delete($a->{"{$role}_signed_path"});
        }
        $a->forceFill(["{$role}_signed_path" => null, "{$role}_signed_name" => null, "{$role}_signed_size" => null, "{$role}_signed_at" => null, "{$role}_signed_uploaded_by" => null])->save();
        unset($this->auth);
    }

    /* =====================================================================
     | §3.5.2 Managing Director decision
     ===================================================================== */

    public function recordMd(): void
    {
        abort_unless($this->canMd, 403, 'The Managing Director review is not open.');

        $this->validate([
            'md.decision'           => ['required', Rule::in(array_keys(Authorisation::MD_DECISIONS))],
            'md.comments'           => ['required', 'string', 'max:5000'],
            'md.on'                 => ['required', 'date', 'before_or_equal:today'],
            'conditions.*.finding'  => ['required', 'string', 'max:250'],
            'conditions.*.type'     => ['nullable', Rule::in(array_keys(Authorisation::CONDITION_TYPES))],
        ], [
            'md.decision.required'          => 'Choose a decision.',
            'md.comments.required'          => 'Record the basis for the decision.',
            'conditions.*.finding.required' => 'Describe the condition (or remove it).',
        ]);

        $decision = $this->md['decision'];

        if (! $this->auth?->hasSignedCopy('md')) {
            $this->addError('md.decision', 'Upload the signed copy from the Managing Director first (step 2).');
            return;
        }

        if ($decision === 'approve' && $this->blockers) {
            $this->addError('md.decision', 'Not ready to approve — see the list above.');
            return;
        }

        DB::transaction(function () use ($decision) {
            $a = $this->persist(updateEoi: false);
            $a->update(['md_by' => auth()->id(), 'md_decided_at' => now()]);

            if ($decision !== 'approve') {
                $outcome = $decision === 'return' ? Authorisation::OUTCOME_CLARIFICATION : Authorisation::OUTCOME_NOT_AUTHORISED;
                $a->update(['outcome' => $outcome, 'decided_at' => now()]);
                $this->eoi->update(['status' => Authorisation::OUTCOME_TO_EOI_STATUS[$outcome]]);
            }
        });

        // E-mails (after the decision is saved)
        $notes = $this->emailDecision('md');

        $this->afterDecision(($decision === 'approve'
            ? 'Approved and forwarded to the MTIBD Principal Secretary.'
            : 'Managing Director decision recorded: '.Authorisation::MD_DECISIONS[$decision][0].'.').' '.$notes);
    }

    /* =====================================================================
     | §3.5.3 Principal Secretary final authorisation
     ===================================================================== */

    public function recordPs(): void
    {
        abort_unless($this->canPs, 403, 'Final authorisation is not open.');

        $this->validate([
            'ps.decision' => ['required', Rule::in(array_keys(Authorisation::PS_DECISIONS))],
            'ps.comments' => [($this->ps['decision'] ?? null) === 'authorise' ? 'nullable' : 'required', 'string', 'max:5000'],
            'ps.on'       => ['required', 'date', 'before_or_equal:today'],
        ], [
            'ps.decision.required' => 'Choose a decision.',
            'ps.comments.required' => ($this->ps['decision'] ?? null) === 'clarify' ? 'Say what further information is required.' : 'Record the reason for declining.',
        ]);

        if (! $this->auth?->hasSignedCopy('ps')) {
            $this->addError('ps.decision', 'Upload the signed copy from the Principal Secretary first (step 2).');
            return;
        }

        $outcome = [
            'authorise' => Authorisation::OUTCOME_AUTHORISED,
            'clarify'   => Authorisation::OUTCOME_CLARIFICATION,
            'decline'   => Authorisation::OUTCOME_NOT_AUTHORISED,
        ][$this->ps['decision']];

        DB::transaction(function () use ($outcome) {
            $this->persist(updateEoi: false)->update([
                'ps_by' => auth()->id(), 'ps_decided_at' => now(), 'outcome' => $outcome, 'decided_at' => now(),
            ]);
            $this->eoi->update(['status' => Authorisation::OUTCOME_TO_EOI_STATUS[$outcome]]);
        });

        // E-mails (after the decision is saved)
        $notes = $this->emailDecision('ps');

        $this->afterDecision('Final authorisation recorded: '.Authorisation::OUTCOMES[$outcome][0].'. '.$notes);
    }

    /* =====================================================================
     | Resubmission after a clarification (new round)
     ===================================================================== */

    public function resubmit(): void
    {
        abort_unless($this->canResubmit, 403);

        $this->validate(['resubmissionNote' => ['required', 'string', 'max:3000']], ['resubmissionNote.required' => 'Explain how the clarification was addressed.']);

        $old    = $this->auth;
        $backTo = $old->heldBy();   // 'ps' → the Managing Director's approval stands

        DB::transaction(function () use ($old, $backTo) {
            Authorisation::create([
                'expression_of_interest_id' => $this->eoiId,
                'committee_review_id'       => $old->committee_review_id,
                'round'                     => $old->round + 1,
                'resubmission_note'         => trim($this->resubmissionNote),
                'private_amount'            => $old->private_amount,
                'grant_amount'              => $old->grant_amount,
                'instrument'                => $old->instrument,
                'conditions'                => $old->conditions,
                'md_checks'                 => $old->md_checks,
            ] + ($backTo === 'ps' ? [
                'md_decision'   => $old->md_decision,
                'md_comments'   => $old->md_comments,
                'md_on'         => $old->md_on,
                'md_by'         => $old->md_by,
                'md_decided_at' => $old->md_decided_at,
                'md_signer_name' => $old->md_signer_name, 'md_signer_email' => $old->md_signer_email,
                'md_request_path' => $old->md_request_path, 'md_request_name' => $old->md_request_name, 'md_sent_at' => $old->md_sent_at, 'md_sent_by' => $old->md_sent_by,
                'md_signed_path' => $old->md_signed_path, 'md_signed_name' => $old->md_signed_name, 'md_signed_size' => $old->md_signed_size,
                'md_signed_at' => $old->md_signed_at, 'md_signed_uploaded_by' => $old->md_signed_uploaded_by,
            ] : []));

            $this->eoi->update(['status' => ExpressionOfInterest::STATUS_AUTHORISATION]);
        });

        $this->reset('resubmissionNote');
        unset($this->eoi);
        $this->load();
        unset($this->isOpen, $this->canMd, $this->canPs, $this->canResubmit);

        $this->dispatch('gate-decided', gate: 'g4', decision: null);
        $this->dispatch('notify', type: 'success', message: 'Resubmitted to the '.($backTo === 'ps' ? 'Principal Secretary' : 'Managing Director')." — round {$this->round}.");
    }

    /* =====================================================================
     | E-mails
     ===================================================================== */

    /**
     * After an MD or PS decision is recorded. Returns notes for the officer.
     *   Applicant: only when the round ends authorised or not authorised (a clarification is internal).
     *   Staff: PMU always; Committee + MD/PS accounts on a final outcome; Finance when authorised.
     */
    protected function emailDecision(string $role): string
    {
        $eoi = ExpressionOfInterest::with('user')->find($this->eoiId);
        $a   = $this->authId ? Authorisation::find($this->authId) : null;

        if (! $eoi || ! $a) {
            return '';
        }

        $outcome = $a->outcome;   // null while the MD has approved and the PS is still to sign
        $final   = in_array($outcome, [Authorisation::OUTCOME_AUTHORISED, Authorisation::OUTCOME_NOT_AUTHORISED], true);
        $notes   = [];

        // Applicant
        if ($final) {
            $applicant = $eoi->user;
            if (! $applicant || blank($applicant->email)) {
                $notes[] = 'The applicant has no e-mail address — please let them know.';
            } else {
                try {
                    $applicant->notify(new EoiGate4Outcome($eoi, $a));
                    $notes[] = 'The applicant was e-mailed ('.$applicant->email.').';
                } catch (\Throwable $e) {
                    report($e);
                    $notes[] = 'The e-mail to the applicant could not be sent — please let them know.'.NotifyRecipients::debugReason($e);
                }
            }
        }

        // Staff
        $staff = NotifyRecipients::pmu();
        $who   = 'the PMU';
        if ($final) {
            $staff = $staff->merge(NotifyRecipients::committee())
                ->merge(NotifyRecipients::anyOf(['gate4.md', 'gate4.ps']));
            $who = 'the PMU, Committee and authorities';
        }
        if ($outcome === Authorisation::OUTCOME_AUTHORISED) {
            $staff = $staff->merge(NotifyRecipients::finance());
            $who   = 'the PMU, Committee, authorities and Finance';
        }

        $notes[] = NotifyRecipients::send($staff->unique('id')->values(), new EoiAuthorisationUpdate($eoi, $a, $role), $who, except: auth()->id());

        return implode(' ', array_filter($notes));
    }

    /* =====================================================================
     | Internals
     ===================================================================== */

    protected function persist(bool $updateEoi = true): Authorisation
    {
        $review = $this->review;

        $data = [
            'conditions'  => collect($this->conditions)->map(fn ($c) => [
                'source'  => $c['source'] ?? 'g4',
                'finding' => Str::limit(trim((string) ($c['finding'] ?? '')), 250, ''),
                'type'    => ($c['type'] ?? null) ?: null,
                'status'  => ($c['status'] ?? 'open') === 'resolved' ? 'resolved' : 'open',
            ])->values()->all(),
            'md_checks'   => $this->md['checks'],
            'md_decision' => $this->md['decision'] ?: null,
            'md_comments' => trim($this->md['comments']) ?: null,
            'md_on'       => $this->md['on'] ?: null,
        ];
        if ($this->stage === 'ps') {
            // The Managing Director's part is closed — only the Principal Secretary's fields change
            $data = [
                'ps_decision' => $this->ps['decision'] ?: null,
                'ps_comments' => trim($this->ps['comments']) ?: null,
                'ps_on'       => $this->ps['on'] ?: null,
            ];
        }

        if ($this->authId) {
            $a = Authorisation::findOrFail($this->authId);
            $a->update($data);
        } else {
            $a = Authorisation::create($data + [
                'expression_of_interest_id' => $this->eoiId,
                'committee_review_id'       => $review?->id,
                'round'                     => $this->round,
                'private_amount'            => $review?->private_amount,
                'grant_amount'              => $review?->grant_amount,
                'instrument'                => $this->dd?->instrument,
            ]);
            $this->authId = $a->id;
        }

        $this->savedAt = now()->format('H:i');
        unset($this->auth, $this->stage);

        return $a;
    }

    protected function afterDecision(string $message): void
    {
        unset($this->eoi, $this->auth, $this->stage, $this->isOpen, $this->canMd, $this->canPs, $this->canResubmit, $this->history);

        $this->dispatch('gate-decided', gate: 'g4', decision: $this->auth?->outcome);
        $this->dispatch('notify', type: 'success', message: trim($message));
    }

    #[On('gate-decided')]
    public function refreshGate(): void
    {
        unset($this->eoi, $this->gateOpen, $this->isOpen, $this->canMd, $this->canPs, $this->canResubmit);
    }
};
?>

<div>
@php
    $A        = \App\Models\Authorisation::class;
    $CR       = \App\Models\CommitteeReview::class;
    $DD       = \App\Models\DueDiligence::class;
    $eoi      = $this->eoi;
    $a        = $this->auth;
    $review   = $this->review;
    $stage    = $this->stage;
    $decided  = (bool) $a?->isDecided();
    $outcome  = $a?->outcome;
    $canMd    = $this->canMd;
    $canPs    = $this->canPs;
    $p        = $this->isApplicant ? null : $this->profile;

    $rate = (float) config('cif.usd_to_lsl', 18.00);
    $m    = fn ($v) => $v === null || $v === '' ? '—' : 'M '.number_format((float) $v);
    $u    = fn ($v) => $v === null || $v === '' ? '' : 'US$ '.number_format((float) $v / max($rate, 0.0001));

    $private    = $a?->private_amount ?? $review?->private_amount;
    $grant      = $a?->grant_amount ?? $review?->grant_amount;
    $instrument = $a?->instrument ?? $this->dd?->instrument;
    $mdDone     = $a?->md_decided_at !== null;
    $psDone     = $a?->ps_decided_at !== null;
    $openConds  = collect($conditions)->where('status', 'open')->count();

    // Path: done | current | failed | returned | todo
    $mdState = match (true) {
        $mdDone && $a->md_decision === 'approve' => 'done',
        $mdDone && $a->md_decision === 'return'  => 'returned',
        $mdDone && $a->md_decision === 'decline' => 'failed',
        default                                  => 'current',
    };
    $psState = match (true) {
        $psDone && $a->ps_decision === 'authorise' => 'done',
        $psDone && $a->ps_decision === 'clarify'   => 'returned',
        $psDone && $a->ps_decision === 'decline'   => 'failed',
        $stage === 'ps'                            => 'current',
        default                                    => 'todo',
    };
    $path = [
        ['Gate 3 · Committee recommendation', 'done'],
        ['Managing Director review', $mdState],
        ['Principal Secretary authorisation', $psState],
        ['Gate 5 · Conditions precedent & Grant Agreement', $outcome === 'authorised' ? 'current' : 'todo'],
    ];
    $pathIcon = ['done' => 'bi-check-lg', 'current' => 'bi-hourglass-split', 'returned' => 'bi-arrow-return-left', 'failed' => 'bi-x-lg', 'todo' => 'bi-circle'];

    $headPill = match (true) {
        $decided            => [$A::OUTCOMES[$outcome][0], $A::OUTCOMES[$outcome][4], $A::OUTCOMES[$outcome][3]],
        $stage === 'ps'     => ['Awaiting Principal Secretary', 'pill-warn', 'bi-hourglass-split'],
        default             => ['Awaiting Managing Director', 'pill-warn', 'bi-hourglass-split'],
    };
@endphp

{{-- ======================================================================
     LOCKED
     ====================================================================== --}}
@if (! $this->gateOpen)
    <section class="section-card">
        <div class="section-card-head">
            <span class="section-no">G4</span>
            <h5 class="me-auto">Final authorisation</h5>
            <span class="pill pill-muted"><i class="bi bi-lock-fill"></i> Locked</span>
        </div>
        <p class="small text-muted mb-0">Opens once the Investment Committee recommends the transaction (Approve or Conditional approval at Gate 3).
            The recommendation is then reviewed by the CAFI Managing Director and authorised by the MTIBD Principal Secretary.</p>
    </section>

{{-- ======================================================================
     APPLICANT
     ====================================================================== --}}
@elseif ($this->isApplicant)
    <section class="section-card">
        <div class="section-card-head">
            <span class="section-no">G4</span>
            <h5 class="me-auto">Final authorisation</h5>
            <span class="pill {{ $decided ? $A::OUTCOMES[$outcome][4] : 'pill-warn' }}"><i class="bi {{ $decided ? $A::OUTCOMES[$outcome][3] : 'bi-hourglass-split' }}"></i> {{ $decided ? $A::OUTCOMES[$outcome][0] : 'In progress' }}</span>
        </div>
        <p class="small text-muted mb-0">
            @if (! $decided) The Investment Committee's recommendation is with CAFI management for final authorisation.
            @elseif ($outcome === 'authorised') Your transaction has been authorised. The next step is meeting the conditions precedent and signing the Grant Agreement. Authorisation does not itself trigger payment.
            @elseif ($outcome === 'clarification') Further information is being prepared before authorisation can be completed. The LEHSFF team will contact you if anything is needed from you.
            @else Your transaction did not receive final authorisation and will not proceed further.
            @endif
        </p>
        @if ($outcome === 'authorised' && $openConds)
            <dt class="detail-label mt-3 mb-2">Conditions still to be met</dt>
            <ul class="checklist mb-0">
                @foreach (collect($conditions)->where('status', 'open') as $c)
                    <li class="is-pending"><i class="bi bi-exclamation-circle"></i>
                        <div><div class="fw-semibold text-navy">{{ $c['finding'] }}</div><div class="small text-muted">{{ $A::CONDITION_TYPES[$c['type'] ?? ''][1] ?? '' }}</div></div></li>
                @endforeach
            </ul>
        @endif
    </section>

{{-- ======================================================================
     STAFF
     ====================================================================== --}}
@else
    {{-- ============================ PATH ============================ --}}
    <div class="g2-path mb-3">
        @foreach ($path as [$pLabel, $pState])
            <span class="g2-path-step {{ in_array($pState, ['done', 'current'], true) ? 'is-'.$pState : '' }} {{ $pState === 'failed' ? 'text-danger' : '' }} {{ $pState === 'returned' ? 'text-warning-emphasis' : '' }}">
                <i class="bi {{ $pathIcon[$pState] }}"></i> {{ $pLabel }}
            </span>
        @endforeach
    </div>

    {{-- ============================ G4 · AUTHORISATION DETAILS (§3.5.6) ============================ --}}
    <section class="section-card">
        <div class="section-card-head">
            <span class="section-no">G4</span>
            <div class="me-auto">
                <h5>Final authorisation @if ($round > 1) <span class="pill pill-muted ms-1">Round {{ $round }}</span> @endif</h5>
                <div class="small text-muted">Has the transaction been properly assessed and recommended within the CAFI framework, so the grant can proceed to contracting?</div>
            </div>
            <span class="pill {{ $headPill[1] }}"><i class="bi {{ $headPill[2] }}"></i> {{ $headPill[0] }}</span>
        </div>

        @if ($canMd || $canPs)
            <div class="small text-muted mb-3">
                <i class="bi bi-cloud-check me-1"></i> Changes are saved automatically. @if ($savedAt) <span class="text-accent">Last saved {{ $savedAt }}.</span> @endif
                <span wire:loading class="ms-1"><span class="spinner-border spinner-border-sm"></span></span>
            </div>
        @elseif (! $decided && $eoi->status === 'authorisation' && ! auth()->user()->canAny(['gate4.md', 'gate4.ps', 'gate2.assess']))
            <div class="small text-muted mb-3"><i class="bi bi-eye me-1"></i> Read-only — the PMU sends the authorisation documents for signing and records the signed decisions.</div>
        @endif

        <div class="callout small mb-3">
            <i class="bi bi-envelope-paper me-1"></i>
            The Managing Director and the Principal Secretary sign outside the system: for each of them, <strong>1</strong> attach the document and send it to them yourself,
            <strong>2</strong> upload the signed copy when it comes back, <strong>3</strong> record the decision written on it. The Principal Secretary's step opens once the Managing Director has approved.
            Recording a decision e-mails the PMU (and, on a final outcome, the applicant, the Committee and Finance).
        </div>

        @if ($a?->resubmission_note)
            <div class="callout small mb-3">
                <div class="fw-semibold mb-1"><i class="bi bi-arrow-repeat me-1"></i> Resubmitted after clarification</div>
                {{ $a->resubmission_note }}
            </div>
        @endif

        <div class="detail-grid">
            <div><dt>Transaction reference</dt><dd><span class="chip">{{ $eoi->reference }}</span></dd></div>
            <div><dt>Enterprise</dt><dd>{{ $p?->registered_name ?: ($eoi->business_name ?: '—') }}</dd></div>
            <div><dt>Investor(s)</dt><dd>{{ $this->dd?->investor_names ?: ($p?->investor_name ?: ($eoi->investor_name ?: '—')) }}</dd></div>
            <div><dt>Committee outcome</dt>
                <dd>@if ($review)
                        <span class="pill {{ $CR::OUTCOMES[$review->outcome][4] }}"><i class="bi {{ $CR::OUTCOMES[$review->outcome][3] }}"></i> {{ $CR::OUTCOMES[$review->outcome][0] }}</span>
                        <span class="small text-muted">· {{ $review->decided_at->format('d M Y') }}</span>
                    @else — @endif</dd></div>
            <div><dt>Approved private investment</dt><dd>{{ $m($private) }} <div class="small text-muted">{{ $u($private) }}</div></dd></div>
            <div><dt>Approved CAFI grant</dt><dd>{{ $m($grant) }} <div class="small text-muted">{{ $u($grant) }}</div></dd></div>
            <div><dt>Instrument</dt><dd>{{ $DD::INSTRUMENTS[$instrument ?? ''] ?? '—' }}</dd></div>
            <div><dt>Authorised next stage</dt><dd class="{{ $outcome ? '' : 'text-faint' }}">{{ $a?->nextStage() ?? 'Managing Director review' }}</dd></div>
        </div>

        <div class="callout small mt-3 mb-0">
            <i class="bi bi-info-circle me-1"></i>
            Gate 4 confirms institutional authorisation of a transaction already assessed (Gate 2) and recommended (Gate 3) — it does not reopen the technical assessment.
            Authorisation allows contracting and conditions precedent; it does <strong>not</strong> trigger payment.
        </div>
    </section>

    {{-- ============================ TRANSACTION FILE (§3.5.1) ============================ --}}
    @php
        $file     = $this->file;
        $included = collect($file)->filter(fn ($f) => $f[3])->count();
    @endphp
    <section class="section-card">
        <div class="section-card-head">
            <span class="section-no"><i class="bi bi-folder2-open"></i></span>
            <div class="me-auto">
                <h5>Transaction file</h5>
                <div class="small text-muted">What the authorising authorities receive — evidence that the transaction passed Gates 1–3.</div>
            </div>
            <span class="pill {{ $included === count($file) ? 'pill-ok' : 'pill-warn' }}">{{ $included }} / {{ count($file) }} included</span>
        </div>

        @foreach ($file as $key => [$doc, $purpose, $icon, $present, $links])
            <div class="file-row flex-wrap" wire:key="file-{{ $key }}">
                <span class="file-row-icon"><i class="bi {{ $icon }}"></i></span>
                <div class="flex-grow-1 small overflow-hidden" style="min-width: 200px">
                    <div class="fw-semibold text-navy">{{ $doc }} @if (in_array($key, ['recommendation', 'iam'], true)) <span class="pill pill-muted ms-1">Key document</span> @endif</div>
                    <div class="text-muted text-truncate">{{ $purpose }} @if ($key === 'conditions') · {{ $openConds }} open @endif</div>
                </div>
                @if ($present)
                    <span class="pill pill-ok"><i class="bi bi-check-lg"></i> Included</span>
                @else
                    <span class="pill pill-danger">Missing</span>
                @endif
                @foreach ($links as [$lLabel, $lUrl])
                    <a href="{{ $lUrl }}" @if (! \Illuminate\Support\Str::startsWith($lUrl, '#')) target="_blank" @endif class="btn btn-sm btn-outline-secondary">
                        <i class="bi {{ \Illuminate\Support\Str::contains($lUrl, 'download') || $lLabel === 'Download' ? 'bi-download' : (\Illuminate\Support\Str::startsWith($lUrl, '#') ? 'bi-arrow-down' : 'bi-box-arrow-up-right') }} me-1"></i>{{ $lLabel }}
                    </a>
                @endforeach
            </div>
        @endforeach
    </section>

    {{-- ============================ 1 · MANAGING DIRECTOR REVIEW (§3.5.2) ============================ --}}
    <section class="section-card">
        <div class="section-card-head">
            <span class="section-no">1</span>
            <div class="me-auto">
                <h5>CAFI Managing Director review</h5>
                <div class="small text-muted">
                    @if ($mdDone) Signed by {{ $a->signerName('md') }} on {{ $a->md_on?->format('d M Y') }} · recorded by {{ $a->managingDirector?->name ?? '—' }} on {{ $a->md_decided_at->format('d M Y H:i') }}.
                    @else Attach the document and send it for signing, upload the signed copy, then record what the Managing Director decided. @endif
                </div>
            </div>
            @if ($mdDone)
                @php [$mdL, , $mdI] = $A::MD_DECISIONS[$a->md_decision]; @endphp
                <span class="pill {{ ['approve' => 'pill-ok', 'return' => 'pill-warn', 'decline' => 'pill-danger'][$a->md_decision] }}"><i class="bi {{ $mdI }}"></i> {{ $mdL }}</span>
            @else
                <span class="pill pill-warn"><i class="bi bi-hourglass-split"></i> In review</span>
            @endif
        </div>

        @include('expression-of-interest.authorisation.partials.signing', ['role' => 'md', 'a' => $a, 'can' => $canMd, 'signing' => $signing, 'decidedRole' => $mdDone])

        @if ($canMd && ! $a?->hasSignedCopy('md'))
            <div class="small text-muted mb-2"><i class="bi bi-info-circle me-1"></i> You can fill in step 3 while waiting; the decision can only be recorded once the signed copy is uploaded.</div>
        @endif

        <div class="d-flex align-items-center gap-2 mb-2">
            <span class="pill {{ $mdDone ? 'pill-ok' : 'pill-warn' }}">3</span>
            <span class="fw-semibold text-navy small">Record the Managing Director's decision, as written on the signed document</span>
        </div>

        <dt class="detail-label mb-2">The review confirms that</dt>
        <ul class="checklist mb-3">
            @foreach ($A::MD_CHECKS as $ck => $ctext)
                @php $ok = $md['checks'][$ck] ?? false; @endphp
                <li class="{{ $ok ? 'is-done' : 'is-pending' }}" wire:key="mdc-{{ $ck }}">
                    <label class="d-flex align-items-start gap-2 mb-0 w-100">
                        <input type="checkbox" class="form-check-input mt-1" wire:model.live="md.checks.{{ $ck }}" @disabled(! $canMd)>
                        <span>{{ $ctext }}</span>
                    </label>
                </li>
            @endforeach
        </ul>

        @if ($canMd)
            @if ($this->blockers)
                <div class="callout callout-warning small mb-3">
                    <div class="fw-semibold mb-1"><i class="bi bi-list-check me-1"></i> Before you can approve ({{ count($this->blockers) }})</div>
                    <ul class="evidence-list mb-0">@foreach ($this->blockers as $b) <li>{{ $b }}</li> @endforeach</ul>
                </div>
            @else
                <div class="callout callout-success small mb-3"><i class="bi bi-check2-circle me-1"></i> Ready to approve and forward to the Principal Secretary.</div>
            @endif
        @endif

        <label class="form-label small fw-semibold">Decision @if ($canMd) <span class="req">*</span> @endif</label>
        <div class="choice-group cols-3 @error('md.decision') is-invalid @enderror">
            @foreach ($A::MD_DECISIONS as $v => [$dLabel, $dDesc, $dIcon])
                <label class="choice choice--card">
                    <input type="radio" name="md-decision" value="{{ $v }}" wire:model.live="md.decision" @disabled(! $canMd)>
                    <span class="choice-box">
                        <span class="choice-icon"><i class="bi {{ $dIcon }}"></i></span>
                        <span><span class="d-block fw-semibold">{{ $dLabel }}</span><span class="d-block small text-muted lh-sm mt-1">{{ $dDesc }}</span></span>
                    </span>
                </label>
            @endforeach
        </div>
        @error('md.decision') <div class="text-danger small mt-1">{{ $message }}</div> @enderror

        <div class="row g-3 mt-1">
            <div class="col-12">
                <label class="form-label small fw-semibold">Comments / rationale @if ($canMd) <span class="req">*</span> @endif</label>
                @if ($canMd)
                    <textarea rows="3" class="form-control @error('md.comments') is-invalid @enderror" wire:model.blur="md.comments"
                              placeholder="{{ ($md['decision'] ?? null) === 'return' ? 'What must be clarified or refined, and by whom' : 'Basis for the decision, and anything the Principal Secretary should note' }}"></textarea>
                    @error('md.comments') <div class="invalid-feedback">{{ $message }}</div> @enderror
                @else
                    <div class="quote mb-0">{{ $md['comments'] ?: 'No comments yet.' }}</div>
                @endif
            </div>
            <div class="col-md-6">
                <label class="form-label small fw-semibold">Signed by (Managing Director)</label>
                <input type="text" class="form-control bg-light" readonly value="{{ $a?->md_signer_name ?: ($signing['md']['name'] ?? '') ?: '—' }}">
            </div>
            <div class="col-md-6">
                <label class="form-label small fw-semibold">Date signed</label>
                <input type="date" class="form-control @error('md.on') is-invalid @enderror" wire:model.blur="md.on" max="{{ now()->toDateString() }}" @disabled(! $canMd)>
                @error('md.on') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        </div>

        @if ($canMd)
            <div class="d-flex flex-wrap gap-2 mt-4 pt-3 border-top">
                <button type="button" class="btn btn-brand ms-auto" wire:click="recordMd" wire:loading.attr="disabled" wire:target="recordMd"
                        wire:confirm="Record the Managing Director's signed decision? It can't be changed afterwards. The PMU will be e-mailed — and the applicant too if it is Not approve.">
                    <span wire:loading.remove wire:target="recordMd"><i class="bi bi-send me-1"></i> Record Managing Director decision</span>
                    <span wire:loading wire:target="recordMd"><span class="spinner-border spinner-border-sm me-1"></span> Recording…</span>
                </button>
            </div>
        @endif
    </section>

    {{-- ============================ 2 · PRINCIPAL SECRETARY AUTHORISATION (§3.5.3) ============================ --}}
    <section class="section-card">
        <div class="section-card-head">
            <span class="section-no">2</span>
            <div class="me-auto">
                <h5>MTIBD Principal Secretary — final authorisation</h5>
                <div class="small text-muted">
                    @if ($psDone) Signed by {{ $a->signerName('ps') }} on {{ $a->ps_on?->format('d M Y') }} · recorded by {{ $a->principalSecretary?->name ?? '—' }} on {{ $a->ps_decided_at->format('d M Y H:i') }}.
                    @else The final institutional approval in the CAFI governance chain — signed outside the system. @endif
                </div>
            </div>
            @if ($psDone)
                @php [$psL, , $psI] = $A::PS_DECISIONS[$a->ps_decision]; @endphp
                <span class="pill {{ ['authorise' => 'pill-ok', 'clarify' => 'pill-warn', 'decline' => 'pill-danger'][$a->ps_decision] }}"><i class="bi {{ $psI }}"></i> {{ $psL }}</span>
            @elseif ($stage === 'ps')
                <span class="pill pill-warn"><i class="bi bi-hourglass-split"></i> Awaiting decision</span>
            @else
                <span class="pill pill-muted"><i class="bi bi-lock-fill"></i> Locked</span>
            @endif
        </div>

        @if (! $psDone && $stage !== 'ps')
            <div class="g1-locked">
                <i class="bi bi-lock"></i>
                <div>
                    @if ($mdDone && $a->md_decision !== 'approve') Not reached — the Managing Director did not forward the transaction.
                    @else Opens once the Managing Director has approved and forwarded the transaction. The reviews are sequential, not concurrent. @endif
                </div>
            </div>
        @else
            @if ($mdDone && $a->md_comments)
                <div class="quote mb-3">{{ $a->md_comments }}<div class="quote-meta">Managing Director ({{ $a->signerName('md') }}) · {{ $a->md_on?->format('d M Y') }}</div></div>
            @endif

            @include('expression-of-interest.authorisation.partials.signing', ['role' => 'ps', 'a' => $a, 'can' => $canPs, 'signing' => $signing, 'decidedRole' => $psDone])

            <div class="d-flex align-items-center gap-2 mb-2">
                <span class="pill {{ $psDone ? 'pill-ok' : 'pill-warn' }}">3</span>
                <span class="fw-semibold text-navy small">Record the Principal Secretary's decision, as written on the signed document</span>
            </div>

            <div class="choice-group cols-3 @error('ps.decision') is-invalid @enderror">
                @foreach ($A::PS_DECISIONS as $v => [$dLabel, $dDesc, $dIcon])
                    <label class="choice choice--card">
                        <input type="radio" name="ps-decision" value="{{ $v }}" wire:model.live="ps.decision" @disabled(! $canPs)>
                        <span class="choice-box">
                            <span class="choice-icon"><i class="bi {{ $dIcon }}"></i></span>
                            <span><span class="d-block fw-semibold">{{ $dLabel }}</span><span class="d-block small text-muted lh-sm mt-1">{{ $dDesc }}</span></span>
                        </span>
                    </label>
                @endforeach
            </div>
            @error('ps.decision') <div class="text-danger small mt-1">{{ $message }}</div> @enderror

            <div class="row g-3 mt-1">
                <div class="col-12">
                    <label class="form-label small fw-semibold">Comments @if ($canPs && ($ps['decision'] ?? null) && $ps['decision'] !== 'authorise') <span class="req">*</span> @endif</label>
                    @if ($canPs)
                        <textarea rows="3" class="form-control @error('ps.comments') is-invalid @enderror" wire:model.blur="ps.comments"
                                  placeholder="{{ ($ps['decision'] ?? null) === 'clarify' ? 'What further information is required' : (($ps['decision'] ?? null) === 'decline' ? 'Reason authorisation is declined' : 'Optional') }}"></textarea>
                        @error('ps.comments') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    @else
                        <div class="quote mb-0">{{ $ps['comments'] ?: 'No comments.' }}</div>
                    @endif
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Signed by (Principal Secretary)</label>
                    <input type="text" class="form-control bg-light" readonly value="{{ $a?->ps_signer_name ?: ($signing['ps']['name'] ?? '') ?: '—' }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Date signed</label>
                    <input type="date" class="form-control @error('ps.on') is-invalid @enderror" wire:model.blur="ps.on" max="{{ now()->toDateString() }}" @disabled(! $canPs)>
                    @error('ps.on') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>

            @if ($canPs)
                <div class="d-flex flex-wrap gap-2 mt-4 pt-3 border-top">
                    <button type="button" class="btn btn-brand ms-auto" wire:click="recordPs" wire:loading.attr="disabled" wire:target="recordPs"
                            wire:confirm="Record the Principal Secretary's signed decision? It can't be changed afterwards. The PMU will be e-mailed — and, if authorised or declined, the applicant, the Committee and Finance too.">
                        <span wire:loading.remove wire:target="recordPs"><i class="bi bi-patch-check me-1"></i> Record final authorisation</span>
                        <span wire:loading wire:target="recordPs"><span class="spinner-border spinner-border-sm me-1"></span> Recording…</span>
                    </button>
                </div>
            @endif
        @endif
    </section>

    {{-- ============================ 3 · CONDITIONS (§3.5.4) ============================ --}}
    <section class="section-card" id="g4-conditions">
        <div class="section-card-head">
            <span class="section-no">3</span>
            <div class="me-auto">
                <h5>Conditions attached</h5>
                <div class="small text-muted">Each condition is classified and assigned to the stage where it must be met — authorisation does not mean they are already satisfied.</div>
            </div>
            <span class="pill {{ $openConds ? 'pill-warn' : 'pill-ok' }}">{{ $openConds }} open</span>
        </div>

        <div class="table-responsive">
            <table class="table table-modern align-middle mb-2">
                <thead>
                    <tr><th>Condition</th><th style="width: 27%">Type</th><th style="width: 22%">Treatment</th><th style="width: 9%">Source</th><th style="width: 12%">Status</th>@if ($canMd)<th></th>@endif</tr>
                </thead>
                <tbody>
                    @forelse ($conditions as $i => $c)
                        @php $t = $A::CONDITION_TYPES[$c['type'] ?? ''] ?? null; @endphp
                        <tr wire:key="g4c-{{ $i }}" class="{{ ($c['type'] ?? null) === 'material' && $c['status'] === 'open' ? 'is-flagged' : '' }}">
                            <td class="small">
                                @if ($canMd && $c['source'] === 'g4')
                                    <input type="text" class="form-control form-control-sm @error('conditions.'.$i.'.finding') is-invalid @enderror" wire:model.blur="conditions.{{ $i }}.finding" maxlength="250" placeholder="Describe the condition">
                                @else
                                    {{ $c['finding'] }}
                                @endif
                            </td>
                            <td>
                                @if ($canMd)
                                    <select class="form-select form-select-sm {{ blank($c['type'] ?? null) ? 'border-warning' : '' }}" wire:model.live="conditions.{{ $i }}.type">
                                        <option value="">Classify…</option>
                                        @foreach ($A::CONDITION_TYPES as $tk => [$tl]) <option value="{{ $tk }}">{{ $tl }}</option> @endforeach
                                    </select>
                                @else
                                    <span class="small">{{ $t[0] ?? '—' }}</span>
                                @endif
                            </td>
                            <td class="small text-muted">{{ $t[1] ?? '—' }}</td>
                            <td><span class="pill pill-muted">{{ $A::CONDITION_SOURCES[$c['source']] ?? $c['source'] }}</span></td>
                            <td>
                                @if ($canMd)
                                    <select class="form-select form-select-sm" wire:model.live="conditions.{{ $i }}.status">
                                        <option value="open">Open</option><option value="resolved">Resolved</option>
                                    </select>
                                @else
                                    <span class="pill {{ $c['status'] === 'open' ? 'pill-warn' : 'pill-ok' }}">{{ ucfirst($c['status']) }}</span>
                                @endif
                            </td>
                            @if ($canMd)
                                <td>@if ($c['source'] === 'g4')<button type="button" class="btn btn-icon btn-icon-danger" wire:click="removeCondition({{ $i }})" title="Remove"><i class="bi bi-x-lg"></i></button>@endif</td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="6" class="small text-muted">No conditions attached.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($canMd)
            <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="addCondition"><i class="bi bi-plus-lg me-1"></i> Add condition</button>
        @endif

        <details class="evidence mt-3">
            <summary><i class="bi bi-info-circle me-1"></i> How conditions are treated</summary>
            <dl class="evidence-grid">
                @foreach ($A::CONDITION_TYPES as [$tl, $tt, $tBlocks])
                    <dt>{{ $tl }}</dt><dd>{{ $tt }} @if ($tBlocks) <span class="pill pill-danger ms-1">Blocks approval</span> @endif</dd>
                @endforeach
            </dl>
        </details>
    </section>

    {{-- ============================ 4 · OUTCOME & HANDOVER (§3.5.5 · §3.5.6) ============================ --}}
    <section class="section-card">
        <div class="section-card-head">
            <span class="section-no">4</span>
            <div class="me-auto">
                <h5>Gate 4 outcome &amp; handover</h5>
                <div class="small text-muted">
                    @if ($decided) Round {{ $round }} closed on {{ $a->decided_at->format('d M Y H:i') }}.
                    @else Recorded once both approvals are in — or as soon as either authority returns or declines. @endif
                </div>
            </div>
            @if ($decided) <span class="pill {{ $A::OUTCOMES[$outcome][4] }}"><i class="bi {{ $A::OUTCOMES[$outcome][3] }}"></i> {{ $A::OUTCOMES[$outcome][0] }}</span> @endif
        </div>

        <div class="row g-2 mb-3">
            @foreach ($A::OUTCOMES as $ok => [$oLabel, $oMeaning, $oNext, $oIcon, $oPill])
                <div class="col-md-4">
                    <div class="border rounded-3 p-3 h-100 {{ $outcome === $ok ? 'border-2 border-primary' : ($decided ? 'opacity-50' : '') }}">
                        <span class="pill {{ $oPill }} mb-2"><i class="bi {{ $oIcon }}"></i> {{ $oLabel }}</span>
                        <div class="small text-muted">{{ $oMeaning }}</div>
                        <div class="small fw-semibold text-navy mt-1"><i class="bi bi-arrow-right me-1"></i>{{ $oNext }}</div>
                    </div>
                </div>
            @endforeach
        </div>

        <dt class="detail-label mb-2">Decision record</dt>
        <div class="table-responsive">
            <table class="table table-modern table-sm align-middle mb-3">
                <thead><tr><th>Authority</th><th>Decision</th><th>Date signed</th><th>Comments</th><th>Signed copy</th></tr></thead>
                <tbody>
                    <tr>
                        <td class="small fw-semibold text-navy">CAFI Managing Director <div class="text-muted fw-normal">{{ $a?->md_signer_name }}</div></td>
                        <td>@if ($mdDone)<span class="pill {{ ['approve' => 'pill-ok', 'return' => 'pill-warn', 'decline' => 'pill-danger'][$a->md_decision] }}">{{ $A::MD_DECISIONS[$a->md_decision][0] }}</span>@else<span class="pill pill-warn">Pending</span>@endif</td>
                        <td class="small {{ $mdDone ? '' : 'text-faint' }}">{{ $mdDone ? $a->md_on?->format('d M Y') : '—' }}</td>
                        <td class="small {{ $mdDone ? '' : 'text-faint' }}">{{ $mdDone ? \Illuminate\Support\Str::limit($a->md_comments, 120) : '—' }}</td>
                        <td class="small">@if ($a?->hasSignedCopy('md'))<a href="{{ route('gate4.file', [$a, 'md_signed']) }}" class="text-decoration-none"><i class="bi bi-file-earmark-check"></i> Download</a>@else<span class="text-faint">—</span>@endif</td>
                    </tr>
                    <tr>
                        <td class="small fw-semibold text-navy">MTIBD Principal Secretary <div class="text-muted fw-normal">{{ $a?->ps_signer_name }}</div></td>
                        <td>@if ($psDone)<span class="pill {{ ['authorise' => 'pill-ok', 'clarify' => 'pill-warn', 'decline' => 'pill-danger'][$a->ps_decision] }}">{{ $A::PS_DECISIONS[$a->ps_decision][0] }}</span>@elseif ($stage === 'ps')<span class="pill pill-warn">Pending</span>@else<span class="pill pill-muted">Not started</span>@endif</td>
                        <td class="small {{ $psDone ? '' : 'text-faint' }}">{{ $psDone ? $a->ps_on?->format('d M Y') : '—' }}</td>
                        <td class="small {{ $psDone ? '' : 'text-faint' }}">{{ $psDone ? (\Illuminate\Support\Str::limit($a->ps_comments, 120) ?: '—') : '—' }}</td>
                        <td class="small">@if ($a?->hasSignedCopy('ps'))<a href="{{ route('gate4.file', [$a, 'ps_signed']) }}" class="text-decoration-none"><i class="bi bi-file-earmark-check"></i> Download</a>@else<span class="text-faint">—</span>@endif</td>
                    </tr>
                </tbody>
            </table>
        </div>

        @if ($outcome === 'authorised')
            <div class="callout callout-success small mb-0">
                <i class="bi bi-box-arrow-in-right me-1"></i> Handed over to <strong>Gate 5</strong> for conditions precedent and the Grant Agreement
                @if ($openConds) — {{ $openConds }} {{ \Illuminate\Support\Str::plural('condition', $openConds) }} to be met. @else. @endif
                Payment is only made after Gate 5.
            </div>
        @elseif ($outcome === 'not_authorised')
            <div class="callout callout-warning small mb-0"><i class="bi bi-x-octagon me-1"></i> The transaction exits the process and does not proceed to contracting or disbursement. The applicant has been notified by e-mail.</div>
        @elseif ($outcome === 'clarification')
            <div class="callout callout-warning small mb-3">
                <i class="bi bi-arrow-return-left me-1"></i>
                Returned by the {{ $a->heldBy() === 'ps' ? 'Principal Secretary' : 'Managing Director' }}.
                Resolve the matters raised and resubmit — it goes back to the {{ $a->heldBy() === 'ps' ? 'Principal Secretary (the Managing Director\'s approval stands)' : 'Managing Director' }}.
            </div>
            @if ($this->canResubmit)
                <label class="form-label small fw-semibold">How the clarification was addressed <span class="req">*</span></label>
                <textarea rows="3" class="form-control @error('resubmissionNote') is-invalid @enderror" wire:model.blur="resubmissionNote"></textarea>
                @error('resubmissionNote') <div class="invalid-feedback">{{ $message }}</div> @enderror
                <div class="d-flex mt-3">
                    <button type="button" class="btn btn-outline-primary ms-auto" wire:click="resubmit" wire:confirm="Resubmit for authorisation as round {{ $round + 1 }}?">
                        <i class="bi bi-arrow-repeat me-1"></i> Resubmit for authorisation
                    </button>
                </div>
            @endif
        @else
            <div class="empty-state py-4">
                <div class="empty-icon"><i class="bi bi-box-arrow-in-right"></i></div>
                <p class="small text-muted mb-0">Once both approvals are recorded, the transaction is handed over to <strong>Gate 5</strong> for conditions precedent and the Grant Agreement.</p>
            </div>
        @endif
    </section>

    {{-- ============================ EARLIER ROUNDS ============================ --}}
    @if ($this->history->isNotEmpty())
        <section class="section-card">
            <div class="section-card-head"><span class="section-no"><i class="bi bi-clock-history"></i></span><h5>Earlier rounds</h5></div>
            @foreach ($this->history as $h)
                @php [$hl, , , $hi, $hp] = $A::OUTCOMES[$h->outcome] ?? [$h->outcome, '', '', 'bi-circle', 'pill-muted']; @endphp
                <div class="file-row align-items-start">
                    <span class="file-row-icon"><i class="bi {{ $hi }}"></i></span>
                    <div class="flex-grow-1 small">
                        <strong class="text-navy">Round {{ $h->round }}</strong> <span class="pill {{ $hp }} ms-1">{{ $hl }}</span>
                        <span class="text-muted ms-1">{{ $h->decided_at->format('d M Y') }} · by the {{ $h->ps_decided_at ? 'Principal Secretary' : 'Managing Director' }}</span>
                        @php $hc = $h->ps_decided_at ? $h->ps_comments : $h->md_comments; @endphp
                        @if ($hc) <div class="text-muted mt-1">{{ $hc }}</div> @endif
                    </div>
                </div>
            @endforeach
        </section>
    @endif
@endif
</div>