<?php

use App\Models\Authorisation;
use App\Models\AuthorisationDocument;
use App\Models\CommitteeReview;
use App\Models\DueDiligence;
use App\Models\EnterpriseProfile;
use App\Models\ExpressionOfInterest;
use App\Models\GrantReadiness;
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
| G5 · Final Authorisation (CI Facility Operations Manual §3.6)  — tab key "g4" kept for existing links
|   Used in the EOI page:  <livewire:expression-of-interest.authorisation.authorise-application :eoi-id="$eoi->id" />
|
|   Gate 4 Grant readiness → CAFI Managing Director review → MTIBD Principal Secretary signature (outside the system) → Gate 6
|   Inputs: Grant Readiness Memo + Draft Grant Agreement (Gate 4), Investment Committee recommendation, transaction file.
|   Opens once the PMU Director of Implementation has approved Gate 4 (older transactions without a Gate 4 record still open).
|
|   1 · Managing Director review (gate4.md or the PMU, gate2.assess) — the MD does not log in and does not sign:
|       the PMU sends the documents to the MD itself (several files; default = the Gate 4 Draft Grant Agreement)
|       and records who they went to, then records the MD's review: checks, conditions, and
|       Satisfied — forward to the PS · Return for clarification or corrective action · Not approve
|   2 · Principal Secretary signature (gate4.ps or the PMU) — no review screen: when the MD is satisfied the PMU forwards
|       the documents to the PS by e-mail, outside the system, and uploads the signed documents when they come back
|       (several files) → formally approved → Gate 6.
|   Outcomes: formally approved → Gate 6 · MD returns → authorisation_hold (resubmit = new round, back to the MD) · MD declines → not_progressed
|   Older rounds may still hold a PS 'clarify' / 'decline' decision; they are shown as before.
|
|   E-mails (sent after the decision is saved; a failed e-mail never undoes it):
|     MD satisfied      → PMU: forward to the Principal Secretary by e-mail, then upload the signed documents
|     MD return         → PMU: resolve and resubmit (internal — the applicant is not e-mailed)
|     MD decline        → applicant: EoiGate4Outcome (not authorised) · PMU, Committee, MD/PS accounts: EoiAuthorisationUpdate
|     PS signed         → applicant: EoiGate4Outcome (authorised) · PMU, Committee, Finance, MD/PS accounts: EoiAuthorisationUpdate
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

    /** Principal Secretary: decision, comments, on (date signed) */
    public array $ps = [];

    /** role => [name, email, message] — md: who the documents were sent to for review · ps: who signed */
    public array $signing = [];

    /** Documents for the MD's review — picked one (or several) at a time, listed, saved by sendToMd() */
    public array $mdFiles = [];

    /** The file input for the MD's documents; each pick is added to $mdFiles */
    public array $mdPick = [];

    /** Documents signed by the PS — saved as soon as they upload */
    public array $psFiles = [];

    /** Bumped after each pick so the file inputs come back empty, ready for the next file */
    public int $pickKey = 0;

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

    /** Gate 4 · the approved grant readiness review (Grant Readiness Memo + Draft Grant Agreement) */
    #[Computed] public function readiness(): ?GrantReadiness
    {
        return GrantReadiness::where('expression_of_interest_id', $this->eoiId)
            ->where('outcome', GrantReadiness::OUTCOME_APPROVED)->orderByDesc('round')->first();
    }

    /** Gate 4 approved — or an older transaction that reached authorisation before Gate 4 existed */
    #[Computed] public function readinessOk(): bool
    {
        return $this->readiness !== null || ! GrantReadiness::where('expression_of_interest_id', $this->eoiId)->exists();
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

    /** Documents sent to the Managing Director for review (this round) */
    #[Computed] public function mdDocs()
    {
        return $this->authId
            ? AuthorisationDocument::where('authorisation_id', $this->authId)->where('kind', AuthorisationDocument::KIND_MD_SENT)->orderBy('id')->get()
            : collect();
    }

    /** Documents signed by the Principal Secretary (this round) */
    #[Computed] public function psDocs()
    {
        return $this->authId
            ? AuthorisationDocument::where('authorisation_id', $this->authId)->where('kind', AuthorisationDocument::KIND_PS_SIGNED)->orderBy('id')->get()
            : collect();
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
        return ! $this->isApplicant && $this->isOpen && $this->readinessOk && $this->stage === 'md' && auth()->user()->canAny(['gate4.md', 'gate2.assess']);
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

        $gr = $this->readiness;

        return [
            'readiness_memo' => ['Grant Readiness Memo',                'Readiness checks, grant amount, bank verification and outstanding matters (Gate 4)', 'bi-clipboard-check', (bool) $gr, $gr ? [['Download PDF', route('grant-readiness.memo', $gr)], ['Open Gate 4', $tab('readiness')]] : []],
            'agreement'      => ['Draft Grant Agreement',               'Proposed grant amount, approved grant utilisation budget and terms (Gate 4)',       'bi-file-earmark-text', (bool) $gr?->agreement_path, $gr?->agreement_path ? [['Download', route('grant-readiness.file', [$gr, 'agreement'])]] : []],
            'recommendation' => ['Investment Committee recommendation', 'Formal recommendation and decision rationale',          'bi-people',            (bool) $review,             $review ? [['Open Gate 3', $tab('g3')]] : []],
            'iam'            => ['Investment Appraisal Memorandum',     'Consolidated transaction assessment',                    'bi-file-earmark-pdf',  (bool) ($dd && (\App\Models\DueDiligence::memoComplete($dd->memo) || $dd->iam_path)),      $dd && (\App\Models\DueDiligence::memoComplete($dd->memo) || $dd->iam_path) ? [['Download PDF', route('due-diligence.memorandum', $dd)]] : []],
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
        $this->conditions = $a ? array_values($a->conditions ?? []) : Authorisation::conditionsFrom($this->review, $this->readiness);

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
                'name'    => (string) $prev?->{"{$r}_signer_name"},
                'email'   => (string) $prev?->{"{$r}_signer_email"},
                'message' => (string) $a?->{"{$r}_request_message"},
            ];
        }

        unset($this->auth, $this->stage, $this->history, $this->blockers, $this->mdDocs, $this->psDocs);
    }

    /* =====================================================================
     | Autosave
     ===================================================================== */

    public function updated(string $name): void
    {
        if (Str::startsWith($name, ['mdFiles', 'mdPick', 'psFiles', 'signing'])) {
            return;   // uploads and the send / sign forms are saved by their own actions
        }

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
        abort_unless(($this->conditions[$i]['source'] ?? null) === 'g4', 403, 'Conditions from Gates 2–4 can be resolved but not removed here.');
        unset($this->conditions[$i]);
        $this->conditions = array_values($this->conditions);
        $this->persist();
    }

    /* =====================================================================
     | Documents (sent to the MD for review · signed by the PS)
     ===================================================================== */

    protected function canRole(string $role): bool
    {
        return match ($role) { 'md' => $this->canMd, 'ps' => $this->canPs, default => false };
    }

    /** 1 · Attach the documents for the Managing Director's review and record who they went to (the PMU sends them itself) */
    public function sendToMd(): void
    {
        abort_unless($this->canMd, 403);

        $existing = $this->mdDocs->count();
        $default  = ($existing || $this->mdFiles) ? null : $this->defaultDocument('md');
        $room     = max(0, Authorisation::MAX_FILES - $existing);

        $this->validate([
            'signing.md.name'    => ['required', 'string', 'max:255'],
            'signing.md.email'   => ['required', 'email', 'max:255'],
            'signing.md.message' => ['nullable', 'string', 'max:3000'],
            'mdFiles'            => [($existing || $default) ? 'nullable' : 'required', 'array', 'max:'.$room],
            'mdFiles.*'          => ['file', 'mimes:'.Authorisation::REQUEST_MIMES, 'max:'.Authorisation::MAX_KB],
        ], [
            'mdFiles.required' => 'Attach the documents for the Managing Director to review.',
            'mdFiles.max'      => 'You can attach up to '.Authorisation::MAX_FILES.' documents ('.$room.' more).',
            'mdFiles.*.mimes'  => 'Attach PDF, Word, Excel, PowerPoint or image files.',
            'mdFiles.*.max'    => 'Each document must be '.(Authorisation::MAX_KB / 1024).' MB or smaller.',
        ], [
            'signing.md.name' => 'name', 'signing.md.email' => 'e-mail address', 'signing.md.message' => 'note',
        ]);

        $a     = $this->persist();
        $added = $this->storeFiles($a, AuthorisationDocument::KIND_MD_SENT, $this->mdFiles);

        if (! $existing && ! $added && $default) {
            // Nothing attached: send the Gate 4 Draft Grant Agreement — a copy is kept with this round
            [$disk, $path, $name] = $default;
            $copy = "authorisations/{$a->id}/".Str::random(8).'-'.basename($path);
            Storage::disk(Authorisation::DISK)->put($copy, Storage::disk($disk)->get($path));
            AuthorisationDocument::create([
                'authorisation_id' => $a->id, 'kind' => AuthorisationDocument::KIND_MD_SENT, 'path' => $copy, 'name' => $name,
                'size' => Storage::disk(Authorisation::DISK)->size($copy), 'uploaded_by' => auth()->id(),
            ]);
        }

        $s     = $this->signing['md'];
        $first = $a->md_sent_at === null;
        $a->forceFill([
            'md_signer_name'     => trim($s['name']),
            'md_signer_email'    => trim($s['email']),
            'md_request_message' => trim((string) $s['message']) ?: null,
            'md_sent_at'         => $a->md_sent_at ?? now(),
            'md_sent_by'         => $a->md_sent_by ?? auth()->id(),
        ])->save();

        $this->mdFiles = [];
        $this->pickKey++;
        unset($this->auth, $this->mdDocs);
        $this->dispatch('notify', type: 'success', message: $first
            ? 'Recorded as sent to '.$a->md_signer_name.' for review. Download the documents and send them to the Managing Director.'
            : 'Updated.'.($added ? ' '.$added.' '.Str::plural('document', $added).' added.' : ''));
    }

    /** A file (or several) was picked for the MD → add it to the list; nothing is saved until "Save & mark as sent" */
    public function updatedMdPick(): void
    {
        abort_unless($this->canMd, 403);

        $room = max(0, Authorisation::MAX_FILES - $this->mdDocs->count() - count($this->mdFiles));

        $this->validate([
            'mdPick'   => ['array', 'max:'.$room],
            'mdPick.*' => ['file', 'mimes:'.Authorisation::REQUEST_MIMES, 'max:'.Authorisation::MAX_KB],
        ], [
            'mdPick.max'     => $room ? 'You can add '.$room.' more '.Str::plural('document', $room).' (up to '.Authorisation::MAX_FILES.').' : 'The limit of '.Authorisation::MAX_FILES.' documents is reached.',
            'mdPick.*.mimes' => 'Attach PDF, Word, Excel, PowerPoint or image files.',
            'mdPick.*.max'   => 'Each document must be '.(Authorisation::MAX_KB / 1024).' MB or smaller.',
        ]);

        // Skip a file that is already in the list (same name and size)
        $have = collect($this->mdFiles)->map(fn ($f) => $f->getClientOriginalName().'|'.$f->getSize());
        foreach ($this->mdPick as $file) {
            if ($file && ! $have->contains($file->getClientOriginalName().'|'.$file->getSize())) {
                $this->mdFiles[] = $file;
            }
        }

        $this->mdPick = [];
        $this->pickKey++;
        $this->resetValidation(['mdFiles', 'mdFiles.*']);
    }

    /** Take a picked file off the list before it is saved */
    public function removePicked(int $i): void
    {
        abort_unless($this->canMd, 403);

        if (isset($this->mdFiles[$i])) {
            try { $this->mdFiles[$i]->delete(); } catch (\Throwable) { /* temporary file already gone */ }
            unset($this->mdFiles[$i]);
            $this->mdFiles = array_values($this->mdFiles);
        }
    }

    /** The document sent when nothing is attached: [disk, path, name] or null — the Draft Grant Agreement from Gate 4 (MD only) */
    public function defaultDocument(string $role = 'md', ?Authorisation $a = null): ?array
    {
        if ($role === 'md' && ($gr = $this->readiness) && $gr->agreement_path && Storage::disk(GrantReadiness::DISK)->exists($gr->agreement_path)) {
            return [GrantReadiness::DISK, $gr->agreement_path, $gr->agreement_name ?: 'Draft Grant Agreement'];
        }

        return null;
    }

    /** 2 · The documents signed by the Principal Secretary came back by e-mail → upload them (saved straight away) */
    public function updatedPsFiles(): void
    {
        abort_unless($this->canPs, 403);

        $room = max(0, Authorisation::MAX_FILES - $this->psDocs->count());

        $this->validate([
            'psFiles'   => ['array', 'max:'.$room],
            'psFiles.*' => ['file', 'mimes:'.Authorisation::SIGNED_MIMES, 'max:'.Authorisation::MAX_KB],
        ], [
            'psFiles.max'     => 'You can upload up to '.Authorisation::MAX_FILES.' signed documents ('.$room.' more).',
            'psFiles.*.mimes' => 'Upload PDF or Word files, or clear scans / photos (JPG, PNG).',
            'psFiles.*.max'   => 'Each file must be '.(Authorisation::MAX_KB / 1024).' MB or smaller.',
        ]);

        $n = $this->storeFiles($this->persist(), AuthorisationDocument::KIND_PS_SIGNED, $this->psFiles);

        $this->psFiles = [];
        $this->pickKey++;
        unset($this->auth, $this->psDocs);
        if ($n) {
            $this->dispatch('notify', type: 'success', message: $n.' signed '.Str::plural('document', $n).' uploaded.');
        }
    }

    /** Remove one document (while its step is still open) */
    public function removeDocument(int $id): void
    {
        abort_unless($this->authId, 403);

        $d = AuthorisationDocument::where('authorisation_id', $this->authId)->findOrFail($id);
        abort_unless($this->canRole($d->kind === AuthorisationDocument::KIND_MD_SENT ? 'md' : 'ps'), 403);

        // Forget the old single-file columns that point at the same file, so the file can be deleted
        $a = Authorisation::findOrFail($this->authId);
        foreach (['md_request', 'ps_signed'] as $col) {
            if ($a->{"{$col}_path"} === $d->path) {
                $a->forceFill(["{$col}_path" => null, "{$col}_name" => null])->save();
            }
        }

        $d->delete();
        unset($this->auth, $this->mdDocs, $this->psDocs);
    }

    /** @param \Livewire\Features\SupportFileUploads\TemporaryUploadedFile[] $files */
    protected function storeFiles(Authorisation $a, string $kind, array $files): int
    {
        $n = 0;
        foreach ($files as $file) {
            if (! $file) {
                continue;
            }
            AuthorisationDocument::create([
                'authorisation_id' => $a->id,
                'kind'             => $kind,
                'path'             => $file->store("authorisations/{$a->id}", Authorisation::DISK),
                'name'             => $file->getClientOriginalName(),
                'size'             => $file->getSize(),
                'uploaded_by'      => auth()->id(),
            ]);
            $n++;
        }

        return $n;
    }

    /* =====================================================================
     | §3.5.2 Managing Director decision
     ===================================================================== */

    public function recordMd(): void
    {
        abort_unless($this->canMd, 403, 'The Managing Director review is not open.');

        $this->validate([
            'md.decision'           => ['required', Rule::in(array_keys(Authorisation::MD_DECISIONS))],
            'md.comments'           => ['nullable', 'string', 'max:5000'],
            'md.on'                 => ['required', 'date', 'before_or_equal:today'],
            'conditions.*.finding'  => ['required', 'string', 'max:250'],
            'conditions.*.type'     => ['nullable', Rule::in(array_keys(Authorisation::CONDITION_TYPES))],
        ], [
            'md.decision.required'          => 'Choose a decision.',
            'conditions.*.finding.required' => 'Describe the condition (or remove it).',
        ]);

        $decision = $this->md['decision'];

        if (! $this->auth?->sentForSigning('md') || $this->mdDocs->isEmpty()) {
            $this->addError('md.decision', 'Send the documents to the Managing Director for review first (step 1).');
            return;
        }

        if ($decision === 'approve' && $this->blockers) {
            $this->addError('md.decision', 'Not ready to forward — see the list above.');
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
            ? 'Managing Director satisfied. Forward the documents to the MTIBD Principal Secretary by e-mail, then upload the signed documents here.'
            : 'Managing Director decision recorded: '.Authorisation::MD_DECISIONS[$decision][0].'.').' '.$notes);
    }

    /* =====================================================================
     | §3.5.3 Principal Secretary signature — recorded from the signed documents
     ===================================================================== */

    public function recordPs(): void
    {
        abort_unless($this->canPs, 403, 'The Principal Secretary signature is not open.');

        $this->validate([
            'signing.ps.name'  => ['required', 'string', 'max:255'],
            'signing.ps.email' => ['nullable', 'email', 'max:255'],
            'ps.on'            => ['required', 'date', 'before_or_equal:today'],
            'ps.comments'      => ['nullable', 'string', 'max:5000'],
        ], [
            'signing.ps.name.required' => 'Enter the name of the Principal Secretary who signed.',
            'ps.on.required'           => 'Enter the date the documents were signed.',
        ], [
            'signing.ps.name' => 'name', 'signing.ps.email' => 'e-mail address', 'ps.on' => 'date signed', 'ps.comments' => 'note',
        ]);

        if ($this->psDocs->isEmpty()) {
            $this->addError('psFiles', 'Upload the documents signed by the Principal Secretary first.');
            return;
        }

        $this->ps['decision'] = 'authorise';

        DB::transaction(function () {
            $this->persist(updateEoi: false)->update([
                'ps_signer_name'  => trim($this->signing['ps']['name']),
                'ps_signer_email' => trim((string) ($this->signing['ps']['email'] ?? '')) ?: null,
                'ps_by'           => auth()->id(),
                'ps_decided_at'   => now(),
                'outcome'         => Authorisation::OUTCOME_AUTHORISED,
                'decided_at'      => now(),
            ]);
            $this->eoi->update(['status' => Authorisation::OUTCOME_TO_EOI_STATUS[Authorisation::OUTCOME_AUTHORISED]]);
        });

        // E-mails (after the decision is saved)
        $notes = $this->emailDecision('ps');

        $this->afterDecision('Signed by the Principal Secretary — the grant is formally approved and handed over to Gate 6. '.$notes);
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
            $new = Authorisation::create([
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

            // Start from the documents sent last time — the PMU can remove, add and re-send them (same files, shared)
            foreach ($old->mdDocuments()->get() as $d) {
                AuthorisationDocument::create(['authorisation_id' => $new->id] + $d->only('kind', 'path', 'name', 'size', 'uploaded_by'));
            }

            $this->eoi->update(['status' => ExpressionOfInterest::STATUS_AUTHORISATION]);
        });

        $this->reset('resubmissionNote');
        unset($this->eoi);
        $this->load();
        unset($this->isOpen, $this->canMd, $this->canPs, $this->canResubmit);

        $this->dispatch('gate-decided', gate: 'g4', decision: null);
        $this->dispatch('notify', type: 'success', message: ($backTo === 'ps'
            ? 'Resubmitted for the Principal Secretary\'s signature'
            : 'Resubmitted to the Managing Director — check the documents and send them again')." (round {$this->round}).");
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
    $mdSent     = (bool) $a?->sentForSigning('md');
    $mdDocs     = $this->mdDocs;
    $psDocs     = $this->psDocs;
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
        ['Gate 4 · Grant readiness', $this->readiness ? 'done' : ($this->readinessOk ? 'done' : 'current')],
        ['Managing Director review', $mdState],
        ['Principal Secretary signature', $psState],
        ['Gate 6 · Conditions precedent & onboarding', $outcome === 'authorised' ? 'current' : 'todo'],
    ];
    $pathIcon = ['done' => 'bi-check-lg', 'current' => 'bi-hourglass-split', 'returned' => 'bi-arrow-return-left', 'failed' => 'bi-x-lg', 'todo' => 'bi-circle'];

    $headPill = match (true) {
        $decided            => [$A::OUTCOMES[$outcome][0], $A::OUTCOMES[$outcome][4], $A::OUTCOMES[$outcome][3]],
        ! $this->readinessOk => ['Waiting for Gate 4', 'pill-muted', 'bi-lock'],
        $stage === 'ps'     => ['Awaiting Principal Secretary signature', 'pill-warn', 'bi-hourglass-split'],
        default             => [$mdSent ? 'With the Managing Director for review' : 'Send to the Managing Director', 'pill-warn', 'bi-hourglass-split'],
    };
@endphp

{{-- ======================================================================
     LOCKED
     ====================================================================== --}}
@if (! $this->gateOpen)
    <section class="section-card">
        <div class="section-card-head">
            <span class="section-no">G5</span>
            <h5 class="me-auto">Final authorisation</h5>
            <span class="pill pill-muted"><i class="bi bi-lock-fill"></i> Locked</span>
        </div>
        <p class="small text-muted mb-0">Opens once Gate 4 · Grant readiness is approved by the PMU Director of Implementation.
            The documents are then reviewed by the CAFI Managing Director and, once the Managing Director is satisfied, signed by the MTIBD Principal Secretary — the Principal Secretary's signature constitutes formal approval of the grant.</p>
    </section>

{{-- ======================================================================
     APPLICANT
     ====================================================================== --}}
@elseif ($this->isApplicant)
    <section class="section-card">
        <div class="section-card-head">
            <span class="section-no">G5</span>
            <h5 class="me-auto">Final authorisation</h5>
            <span class="pill {{ $decided ? $A::OUTCOMES[$outcome][4] : 'pill-warn' }}"><i class="bi {{ $decided ? $A::OUTCOMES[$outcome][3] : 'bi-hourglass-split' }}"></i> {{ $decided ? $A::OUTCOMES[$outcome][0] : 'In progress' }}</span>
        </div>
        <p class="small text-muted mb-0">
            @if (! $decided) Your Grant Agreement is with CAFI management for review and signature.
            @elseif ($outcome === 'authorised') Your grant has been formally approved — the Grant Agreement has been reviewed by the CAFI Managing Director and signed by the MTIBD Principal Secretary. The next step is meeting the conditions precedent and onboarding. Approval does not itself trigger payment.
            @elseif ($outcome === 'clarification') Further information is being prepared before authorisation can be completed. The LEHSFF team will contact you if anything is needed from you.
            @else Your grant was not approved and will not proceed further.
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

    {{-- ============================ G5 · AUTHORISATION DETAILS — decision recording ============================ --}}
    <section class="section-card">
        <div class="section-card-head">
            <span class="section-no">G5</span>
            <div class="me-auto">
                <h5>Final authorisation @if ($round > 1) <span class="pill pill-muted ms-1">Round {{ $round }}</span> @endif</h5>
                <div class="small text-muted">Review of the documents by the CAFI Managing Director, then signature of the Grant Agreement by the MTIBD Principal Secretary (formal approval).</div>
            </div>
            <span class="pill {{ $headPill[1] }}"><i class="bi {{ $headPill[2] }}"></i> {{ $headPill[0] }}</span>
        </div>

        @if ($canMd || $canPs)
            <div class="small text-muted mb-3">
                <i class="bi bi-cloud-check me-1"></i> Changes are saved automatically. @if ($savedAt) <span class="text-accent">Last saved {{ $savedAt }}.</span> @endif
                <span wire:loading class="ms-1"><span class="spinner-border spinner-border-sm"></span></span>
            </div>
        @elseif (! $decided && $eoi->status === 'authorisation' && ! auth()->user()->canAny(['gate4.md', 'gate4.ps', 'gate2.assess']))
            <div class="small text-muted mb-3"><i class="bi bi-eye me-1"></i> Read-only — the PMU sends the documents for review and signature and records the outcome.</div>
        @endif

        @if (! $this->readinessOk && ! $decided)
            <div class="callout callout-warning small mb-3">
                <i class="bi bi-lock me-1"></i> Waiting for <strong>Gate 4 · Grant readiness</strong> — the Grant Readiness Memo and Draft Grant Agreement must be approved by the Hub Manager and the PMU Director of Implementation first.
                <a href="{{ route('expression-of-interest.show', $eoi->id) }}?tab=readiness" class="ms-1">Open Gate 4</a>
            </div>
        @endif

        <div class="callout small mb-3">
            <i class="bi bi-envelope-paper me-1"></i>
            Both steps happen outside the system — nobody needs to log in.
            <strong>1</strong> Send the documents to the <strong>CAFI Managing Director</strong> for review yourself (attach as many as needed) and record the outcome of the review.
            <strong>2</strong> When the Managing Director is satisfied, forward the documents to the <strong>MTIBD Principal Secretary</strong> by e-mail for signature,
            then upload the signed documents here to complete the authorisation.
            Recording an outcome e-mails the PMU (and, on a final outcome, the applicant, the Committee and Finance).
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
            <div><dt>Gate 4 · readiness</dt>
                <dd>@if ($this->readiness)
                        <span class="pill pill-ok"><i class="bi bi-check2-circle"></i> Approved to proceed</span>
                        <span class="small text-muted">· {{ $this->readiness->decided_at->format('d M Y') }}</span>
                    @elseif ($this->readinessOk) <span class="small text-muted">Not required (before Gate 4 was introduced)</span>
                    @else <span class="pill pill-warn">Pending</span> @endif</dd></div>
            <div><dt>Committee outcome</dt>
                <dd>@if ($review)
                        <span class="pill {{ $CR::OUTCOMES[$review->outcome][4] }}"><i class="bi {{ $CR::OUTCOMES[$review->outcome][3] }}"></i> {{ $CR::OUTCOMES[$review->outcome][0] }}</span>
                        <span class="small text-muted">· {{ $review->decided_at->format('d M Y') }}</span>
                    @else — @endif</dd></div>
            <div><dt>Approved private investment</dt><dd>{{ $m($private) }} <div class="small text-muted">{{ $u($private) }}</div></dd></div>
            <div><dt>Approved CAFI grant</dt><dd>{{ $m($grant) }} <div class="small text-muted">{{ $u($grant) }}</div></dd></div>
            <div><dt>Instrument</dt><dd>{{ $DD::INSTRUMENTS[$instrument ?? ''] ?? '—' }}</dd></div>
            <div><dt>Outstanding actions</dt><dd>{{ $openConds ? $openConds.' open '.\Illuminate\Support\Str::plural('condition', $openConds) : 'None' }} <a href="#g4-conditions" class="small ms-1">See conditions</a></dd></div>
            <div><dt>Authorised next stage</dt><dd class="{{ $outcome ? '' : 'text-faint' }}">{{ $a?->nextStage() ?? 'Managing Director review' }}</dd></div>
        </div>

        <div class="callout small mt-3 mb-0">
            <i class="bi bi-info-circle me-1"></i>
            Gate 5 confirms institutional authorisation of a transaction already assessed (Gate 2), recommended (Gate 3) and prepared (Gate 4) — it does not reopen the technical assessment.
            Formal approval leads to conditions precedent and onboarding (Gate 6); it does <strong>not</strong> trigger payment.
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
                <div class="small text-muted">What you can send to the Managing Director: the Grant Readiness Memo, the Draft Grant Agreement, the Committee recommendation and supporting documents.</div>
            </div>
            <span class="pill {{ $included === count($file) ? 'pill-ok' : 'pill-warn' }}">{{ $included }} / {{ count($file) }} included</span>
        </div>

        @foreach ($file as $key => [$doc, $purpose, $icon, $present, $links])
            <div class="file-row flex-wrap" wire:key="file-{{ $key }}">
                <span class="file-row-icon"><i class="bi {{ $icon }}"></i></span>
                <div class="flex-grow-1 small overflow-hidden" style="min-width: 200px">
                    <div class="fw-semibold text-navy">{{ $doc }} @if (in_array($key, ['readiness_memo', 'agreement', 'recommendation'], true)) <span class="pill pill-muted ms-1">Key input</span> @endif</div>
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
                    @if ($mdDone) Reviewed by {{ $a->signerName('md') }} on {{ $a->md_on?->format('d M Y') }} · recorded by {{ $a->managingDirector?->name ?? '—' }} on {{ $a->md_decided_at->format('d M Y H:i') }}.
                    @else Send the documents to the Managing Director for review, then record the outcome. The Managing Director reviews only — the Principal Secretary signs. @endif
                </div>
            </div>
            @if ($mdDone)
                @php [$mdL, , $mdI] = $A::MD_DECISIONS[$a->md_decision]; @endphp
                <span class="pill {{ ['approve' => 'pill-ok', 'return' => 'pill-warn', 'decline' => 'pill-danger'][$a->md_decision] }}"><i class="bi {{ $mdI }}"></i> {{ $mdL }}</span>
            @elseif ($mdSent)
                <span class="pill pill-warn"><i class="bi bi-hourglass-split"></i> In review</span>
            @else
                <span class="pill pill-muted"><i class="bi bi-envelope"></i> Not sent</span>
            @endif
        </div>

        {{-- ---------- Step 1 · send the documents ---------- --}}
        <div class="border rounded-3 p-3 mb-3 bg-light">
            <div class="d-flex align-items-center gap-2 mb-2">
                <span class="pill {{ $mdSent ? 'pill-ok' : 'pill-warn' }}"><i class="bi {{ $mdSent ? 'bi-envelope-check' : 'bi-envelope' }}"></i> 1</span>
                <span class="fw-semibold text-navy small">Send the documents to the Managing Director for review</span>
            </div>

            @if ($mdSent)
                <div class="small mb-2">
                    Sent to <strong>{{ $a->md_signer_name }}</strong> ({{ $a->md_signer_email }}) on {{ $a->md_sent_at->format('d M Y') }}@if ($a->mdSender) by {{ $a->mdSender->name }}@endif.
                    @if ($a->md_request_message) <div class="text-muted mt-1"><i class="bi bi-chat-left-text me-1"></i>{{ $a->md_request_message }}</div> @endif
                </div>
            @elseif (! $canMd)
                <div class="small text-muted mb-2">Not sent yet.</div>
            @endif

            @include('expression-of-interest.authorisation.partials.documents', ['docs' => $mdDocs, 'canRemove' => $canMd, 'empty' => $canMd ? null : 'No documents.'])

            @if ($canMd)
                @php $mdDefault = $mdDocs->isEmpty() ? $this->defaultDocument('md') : null; @endphp
                <div class="row g-2 mt-1">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold mb-1">Managing Director's name <span class="req">*</span></label>
                        <input type="text" class="form-control form-control-sm @error('signing.md.name') is-invalid @enderror" wire:model="signing.md.name" placeholder="{{ $A::AUTHORITIES['md'][0] }}">
                        @error('signing.md.name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold mb-1">E-mail <span class="req">*</span></label>
                        <input type="email" class="form-control form-control-sm @error('signing.md.email') is-invalid @enderror" wire:model="signing.md.email">
                        @error('signing.md.email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-semibold mb-1">
                            {{ $mdDocs->isEmpty() ? 'Documents for review' : 'Add more documents' }}
                            @if ($mdDocs->isEmpty() && ! $mdDefault) <span class="req">*</span> @endif
                        </label>

                        {{-- Picked, not saved yet --}}
                        @if (count($mdFiles))
                            <div class="mb-2">
                                @foreach ($mdFiles as $i => $f)
                                    <div class="file-row" wire:key="md-picked-{{ $i }}-{{ $f->getFilename() }}">
                                        <span class="file-row-icon"><i class="bi bi-file-earmark-plus"></i></span>
                                        <div class="flex-grow-1 small overflow-hidden">
                                            <div class="fw-semibold text-navy text-truncate">{{ $f->getClientOriginalName() }}</div>
                                            <div class="text-muted">{{ number_format(max(1, $f->getSize() / 1024)) }} KB · not saved yet</div>
                                        </div>
                                        <button type="button" class="btn btn-icon btn-icon-danger" title="Remove" wire:click="removePicked({{ $i }})"><i class="bi bi-x-lg"></i></button>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        <div wire:key="md-pick-{{ $pickKey }}">
                            <input type="file" multiple class="form-control form-control-sm @error('mdPick') is-invalid @enderror @error('mdPick.*') is-invalid @enderror @error('mdFiles') is-invalid @enderror"
                                   wire:model="mdPick" accept=".pdf,.doc,.docx,.xls,.xlsx,.pptx,.jpg,.jpeg,.png">
                        </div>
                        <div class="form-text">
                            <span wire:loading wire:target="mdPick"><span class="spinner-border spinner-border-sm me-1"></span> Uploading…</span>
                            <span wire:loading.remove wire:target="mdPick">
                                @if (count($mdFiles)) {{ count($mdFiles) }} {{ \Illuminate\Support\Str::plural('document', count($mdFiles)) }} ready — choose another file to add it, or save.
                                @elseif ($mdDocs->isEmpty() && $mdDefault) Choose files one at a time (or several at once). Leave empty to send <strong>{{ $mdDefault[2] }}</strong> (the Draft Grant Agreement from Gate 4).
                                @else Choose files one at a time (or several at once) — each is added to the list. PDF, Word, Excel, PowerPoint or images, up to {{ $A::MAX_KB / 1024 }} MB each. @endif
                            </span>
                        </div>
                        @error('mdPick') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        @error('mdPick.*') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        @error('mdFiles') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        @error('mdFiles.*') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-semibold mb-1">Note (optional)</label>
                        <textarea rows="2" class="form-control form-control-sm" wire:model="signing.md.message" placeholder="e.g. e-mailed with the Grant Readiness Memo, follow up on Friday"></textarea>
                    </div>
                    <div class="col-12 d-flex">
                        <button type="button" class="btn btn-sm {{ $mdSent ? 'btn-outline-secondary' : 'btn-brand' }} ms-auto" wire:click="sendToMd"
                                wire:loading.attr="disabled" wire:target="sendToMd,mdPick">
                            <span wire:loading.remove wire:target="sendToMd"><i class="bi bi-check2-square me-1"></i>{{ $mdSent ? (count($mdFiles) ? 'Save the added documents' : 'Save changes') : 'Save & mark as sent for review' }}</span>
                            <span wire:loading wire:target="sendToMd"><span class="spinner-border spinner-border-sm me-1"></span> Saving…</span>
                        </button>
                    </div>
                </div>
            @endif
        </div>

        {{-- ---------- Step 2 · record the review ---------- --}}
        <div class="d-flex align-items-center gap-2 mb-2">
            <span class="pill {{ $mdDone ? 'pill-ok' : 'pill-warn' }}">2</span>
            <span class="fw-semibold text-navy small">Record the outcome of the Managing Director's review</span>
        </div>

        @if ($canMd && ! $mdSent)
            <div class="small text-muted mb-2"><i class="bi bi-info-circle me-1"></i> You can fill this in while waiting; the outcome can be recorded once the documents have been sent (step 1).</div>
        @endif

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
                    <div class="fw-semibold mb-1"><i class="bi bi-list-check me-1"></i> Before the documents can go to the Principal Secretary ({{ count($this->blockers) }})</div>
                    <ul class="evidence-list mb-0">@foreach ($this->blockers as $b) <li>{{ $b }}</li> @endforeach</ul>
                </div>
            @else
                <div class="callout callout-success small mb-3"><i class="bi bi-check2-circle me-1"></i> Ready to forward to the Principal Secretary for signature.</div>
            @endif
        @endif

        <label class="form-label small fw-semibold">Outcome of the review @if ($canMd) <span class="req">*</span> @endif</label>
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
                <label class="form-label small fw-semibold">Comments / rationale <span class="text-muted fw-normal">(optional)</span></label>
                @if ($canMd)
                    <textarea rows="3" class="form-control @error('md.comments') is-invalid @enderror" wire:model.blur="md.comments"
                              placeholder="{{ ($md['decision'] ?? null) === 'return' ? 'What must be clarified or refined, and by whom' : 'Basis for the outcome, and anything the Principal Secretary should note' }}"></textarea>
                    @error('md.comments') <div class="invalid-feedback">{{ $message }}</div> @enderror
                @else
                    <div class="quote mb-0">{{ $md['comments'] ?: 'No comments yet.' }}</div>
                @endif
            </div>
            <div class="col-md-6">
                <label class="form-label small fw-semibold">Reviewed by (Managing Director)</label>
                <input type="text" class="form-control bg-light" readonly value="{{ $a?->md_signer_name ?: ($signing['md']['name'] ?? '') ?: '—' }}">
            </div>
            <div class="col-md-6">
                <label class="form-label small fw-semibold">Date of the review</label>
                <input type="date" class="form-control @error('md.on') is-invalid @enderror" wire:model.blur="md.on" max="{{ now()->toDateString() }}" @disabled(! $canMd)>
                @error('md.on') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        </div>

        @if ($canMd)
            <div class="d-flex flex-wrap gap-2 mt-4 pt-3 border-top">
                <button type="button" class="btn btn-brand ms-auto" wire:click="recordMd" wire:loading.attr="disabled" wire:target="recordMd"
                        wire:confirm="Record the outcome of the Managing Director's review? It can't be changed afterwards. The PMU will be e-mailed — and the applicant too if it is Not approve.">
                    <span wire:loading.remove wire:target="recordMd"><i class="bi bi-send me-1"></i> Record review outcome</span>
                    <span wire:loading wire:target="recordMd"><span class="spinner-border spinner-border-sm me-1"></span> Recording…</span>
                </button>
            </div>
        @endif
    </section>

    {{-- ============================ 2 · CONDITIONS (§3.5.4) ============================ --}}
    <section class="section-card" id="g4-conditions">
        <div class="section-card-head">
            <span class="section-no">2</span>
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

    {{-- ============================ 3 · PRINCIPAL SECRETARY SIGNATURE, OUTCOME & HANDOVER (§3.5.3 · §3.5.5 · §3.5.6) ============================ --}}
    <section class="section-card">
        <div class="section-card-head">
            <span class="section-no">3</span>
            <div class="me-auto">
                <h5>Principal Secretary signature &amp; handover</h5>
                <div class="small text-muted">
                    @if ($decided) Round {{ $round }} closed on {{ $a->decided_at->format('d M Y H:i') }}.
                    @else The PMU forwards the documents to the MTIBD Principal Secretary by e-mail and uploads the signed documents here. @endif
                </div>
            </div>
            @if ($decided) <span class="pill {{ $A::OUTCOMES[$outcome][4] }}"><i class="bi {{ $A::OUTCOMES[$outcome][3] }}"></i> {{ $A::OUTCOMES[$outcome][0] }}</span>
            @elseif ($stage === 'ps') <span class="pill pill-warn"><i class="bi bi-hourglass-split"></i> Awaiting signature</span>
            @else <span class="pill pill-muted"><i class="bi bi-lock-fill"></i> Locked</span> @endif
        </div>

        @if (! $decided && $stage === 'ps')
            {{-- ---------- Forward by e-mail, upload the signed documents ---------- --}}
            <div class="callout small mb-3">
                <i class="bi bi-envelope-arrow-up me-1"></i>
                The Managing Director is satisfied. <strong>Forward the documents to the MTIBD Principal Secretary by e-mail</strong> for signature (outside the system).
                When they come back signed, upload them below and record the signature.
                @if ($mdDone && $a->md_comments) <div class="quote mt-2 mb-0">{{ $a->md_comments }}<div class="quote-meta">Managing Director ({{ $a->signerName('md') }}) · {{ $a->md_on?->format('d M Y') }}</div></div> @endif
            </div>

            @if ($mdDocs->isNotEmpty())
                <dt class="detail-label mb-2">Documents to forward</dt>
                @include('expression-of-interest.authorisation.partials.documents', ['docs' => $mdDocs, 'canRemove' => false, 'empty' => null])
            @endif

            <dt class="detail-label mt-3 mb-2">Signed documents from the Principal Secretary @if ($canPs) <span class="req">*</span> @endif</dt>
            @include('expression-of-interest.authorisation.partials.documents', ['docs' => $psDocs, 'canRemove' => $canPs, 'empty' => 'No signed documents uploaded yet.'])

            @if ($canPs)
                <div class="mt-2">
                    <span wire:loading wire:target="psFiles" class="small text-muted"><span class="spinner-border spinner-border-sm me-1"></span> Uploading…</span>
                    <div wire:loading.remove wire:target="psFiles" wire:key="ps-pick-{{ $pickKey }}">
                        <input type="file" multiple class="form-control form-control-sm @error('psFiles') is-invalid @enderror @error('psFiles.*') is-invalid @enderror"
                               wire:model="psFiles" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                        <div class="form-text">Choose the signed files one at a time (or several at once) — the signed Grant Agreement and any signed annexes. Each file is saved and added to the list above as soon as it uploads; remove one with <i class="bi bi-trash"></i>. PDF, Word, or clear scans / photos, up to {{ $A::MAX_KB / 1024 }} MB each.</div>
                        @error('psFiles') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        @error('psFiles.*') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                    </div>
                </div>

                <div class="row g-3 mt-1">
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Signed by (Principal Secretary) <span class="req">*</span></label>
                        <input type="text" class="form-control @error('signing.ps.name') is-invalid @enderror" wire:model="signing.ps.name" placeholder="{{ $A::AUTHORITIES['ps'][0] }}">
                        @error('signing.ps.name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">E-mail it was sent to</label>
                        <input type="email" class="form-control @error('signing.ps.email') is-invalid @enderror" wire:model="signing.ps.email">
                        @error('signing.ps.email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Date signed <span class="req">*</span></label>
                        <input type="date" class="form-control @error('ps.on') is-invalid @enderror" wire:model.blur="ps.on" max="{{ now()->toDateString() }}">
                        @error('ps.on') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-semibold">Note (optional)</label>
                        <textarea rows="2" class="form-control @error('ps.comments') is-invalid @enderror" wire:model.blur="ps.comments" placeholder="Anything noted by the Principal Secretary"></textarea>
                        @error('ps.comments') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>

                <div class="d-flex flex-wrap gap-2 mt-4 pt-3 border-top">
                    <button type="button" class="btn btn-brand ms-auto" wire:click="recordPs" wire:loading.attr="disabled" wire:target="recordPs,psFiles"
                            wire:confirm="Record the Principal Secretary's signature? The grant is formally approved and handed over to Gate 6. The applicant, PMU, Committee and Finance will be e-mailed.">
                        <span wire:loading.remove wire:target="recordPs"><i class="bi bi-patch-check me-1"></i> Record signature — formally approve</span>
                        <span wire:loading wire:target="recordPs"><span class="spinner-border spinner-border-sm me-1"></span> Recording…</span>
                    </button>
                </div>
            @endif

            <hr class="my-4">
        @elseif (! $decided)
            <div class="g1-locked mb-3">
                <i class="bi bi-lock"></i>
                <div>Opens once the Managing Director is satisfied with the documents. The PMU then forwards them to the Principal Secretary by e-mail and uploads the signed documents here.</div>
            </div>
        @endif

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
                <thead><tr><th>Authority</th><th>Outcome</th><th>Date</th><th>Comments</th><th>Documents</th></tr></thead>
                <tbody>
                    <tr>
                        <td class="small fw-semibold text-navy">CAFI Managing Director <div class="text-muted fw-normal">Review · {{ $a?->md_signer_name }}</div></td>
                        <td>@if ($mdDone)<span class="pill {{ ['approve' => 'pill-ok', 'return' => 'pill-warn', 'decline' => 'pill-danger'][$a->md_decision] }}">{{ $A::MD_DECISIONS[$a->md_decision][0] }}</span>@else<span class="pill pill-warn">Pending</span>@endif</td>
                        <td class="small {{ $mdDone ? '' : 'text-faint' }}">{{ $mdDone ? $a->md_on?->format('d M Y') : '—' }}</td>
                        <td class="small {{ $mdDone ? '' : 'text-faint' }}">{{ $mdDone ? \Illuminate\Support\Str::limit($a->md_comments, 120) : '—' }}</td>
                        <td class="small">
                            @forelse ($mdDocs as $d)
                                <a href="{{ route('gate4.document', $d) }}" class="d-block text-decoration-none text-truncate" style="max-width: 220px"><i class="bi bi-paperclip"></i> {{ $d->name }}</a>
                            @empty
                                @if ($a?->md_signed_path) <a href="{{ route('gate4.file', [$a, 'md_signed']) }}" class="text-decoration-none"><i class="bi bi-file-earmark-check"></i> Signed copy</a> @else <span class="text-faint">—</span> @endif
                            @endforelse
                        </td>
                    </tr>
                    <tr>
                        <td class="small fw-semibold text-navy">MTIBD Principal Secretary <div class="text-muted fw-normal">Signature · {{ $a?->ps_signer_name }}</div></td>
                        <td>@if ($psDone)<span class="pill {{ ['authorise' => 'pill-ok', 'clarify' => 'pill-warn', 'decline' => 'pill-danger'][$a->ps_decision] }}">{{ $A::PS_DECISIONS[$a->ps_decision][0] }}</span>@elseif ($stage === 'ps')<span class="pill pill-warn">Pending</span>@else<span class="pill pill-muted">Not started</span>@endif</td>
                        <td class="small {{ $psDone ? '' : 'text-faint' }}">{{ $psDone ? $a->ps_on?->format('d M Y') : '—' }}</td>
                        <td class="small {{ $psDone ? '' : 'text-faint' }}">{{ $psDone ? (\Illuminate\Support\Str::limit($a->ps_comments, 120) ?: '—') : '—' }}</td>
                        <td class="small">
                            @forelse ($psDocs as $d)
                                <a href="{{ route('gate4.document', $d) }}" class="d-block text-decoration-none text-truncate" style="max-width: 220px"><i class="bi bi-file-earmark-check"></i> {{ $d->name }}</a>
                            @empty
                                <span class="text-faint">—</span>
                            @endforelse
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        @if ($outcome === 'authorised')
            <div class="callout callout-success small mb-0">
                <i class="bi bi-box-arrow-in-right me-1"></i> Grant Agreement signed by the Principal Secretary — handed over to <strong>Gate 6</strong> for conditions precedent and onboarding
                @if ($openConds) — {{ $openConds }} {{ \Illuminate\Support\Str::plural('condition', $openConds) }} to be met. @else. @endif
                Disbursement remains subject to the conditions precedent and financial controls.
            </div>
        @elseif ($outcome === 'not_authorised')
            <div class="callout callout-warning small mb-0"><i class="bi bi-x-octagon me-1"></i> The grant was not approved and cannot proceed. The applicant has been notified by e-mail.</div>
        @elseif ($outcome === 'clarification')
            <div class="callout callout-warning small mb-3">
                <i class="bi bi-arrow-return-left me-1"></i>
                Returned by the {{ $a->heldBy() === 'ps' ? 'Principal Secretary' : 'Managing Director' }}.
                Resolve the matters raised and resubmit — it goes back to the {{ $a->heldBy() === 'ps' ? 'Principal Secretary (the Managing Director\'s review stands)' : 'Managing Director, starting from the documents sent last time' }}.
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
        @elseif ($stage !== 'ps')
            <div class="empty-state py-4">
                <div class="empty-icon"><i class="bi bi-box-arrow-in-right"></i></div>
                <p class="small text-muted mb-0">Once the Principal Secretary's signed documents are uploaded, the transaction is handed over to <strong>Gate 6</strong> for conditions precedent and onboarding.</p>
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