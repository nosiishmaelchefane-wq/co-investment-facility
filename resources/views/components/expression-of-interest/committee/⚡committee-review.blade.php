<?php

use App\Models\CommitteeReview;
use App\Models\CommitteeScorecard;
use App\Models\DueDiligence;
use App\Models\EnterpriseDocument;
use App\Models\EnterpriseProfile;
use App\Models\ExpressionOfInterest;
use App\Notifications\EoiCommitteeDecisionRecorded;
use App\Notifications\EoiGate3Outcome;
use App\Notifications\EoiReadyForCommittee;
use App\Notifications\EoiScorecardsComplete;
use App\Support\NotifyRecipients;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/*
| G3 · Investment Committee Review & Recommendation (CI Facility Operations Manual §3.4)
|   Used in the EOI page:  <livewire:expression-of-interest.committee.committee-review :eoi-id="$eoi->id" />
|
|   Who:
|     Investment Committee role — gate3.score: declares conflicts and submits their Appendix F scorecard
|                                gate3.decide: keeps the meeting record and records the Committee outcome
|     Other staff (eoi.view-all): read-only.   Applicant: outcome and the conditions they own.
|   Rules:
|     Opens once Gate 2 = Proceed (EOI status "committee").
|     Members = active users with the "committee" role (3 fixed members — config cif.committee.size).
|     Scorecards stay hidden until every non-recused member has submitted; then they're consolidated.
|     The outcome can only be recorded once scoring is complete.
|     Approve / Conditional → authorisation (Gate 4) · Defer → ic_deferred (PMU resubmits → new round) · Reject → not_progressed
|   Templates: resources/views/expression-of-interest/committee/partials/*
|
|   E-mails (sent after the change is saved; a failed e-mail never undoes it):
|     Last scorecard submitted → gate3.decide: EoiScorecardsComplete
|     Outcome recorded         → applicant: EoiGate3Outcome
|                              → PMU (gate2.assess) + Committee members: EoiCommitteeDecisionRecorded
|     Deferred case resubmitted → Committee members: EoiReadyForCommittee (round n, with the PMU's response)
*/
new class extends Component
{
    #[Locked]
    public int $eoiId;

    #[Locked]
    public ?int $reviewId = null;

    #[Locked]
    public int $round = 1;

    /** Meeting & Committee record (gate3.decide) */
    public array $meeting = [];        // meeting_ref, meeting_date, venue, pack_circulated_on
    public array $criteria = [];       // area => [view, note]
    public array $deliberation = [];   // step => [done, note]
    public array $questions = [];      // [asked_by, question, answer]
    public array $conditions = [];     // [source, ref_id, finding, treatment, owner, status, decision]
    public array $decision = [];       // outcome, private_amount, grant_amount, rationale, actions

    /** New question being logged */
    public array $newQuestion = ['asked_by' => '', 'question' => '', 'answer' => ''];

    /** My Appendix F scorecard (gate3.score) */
    public array $card = [];

    /** PMU's response when resubmitting a deferred case */
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

    #[Computed]
    public function eoi(): ExpressionOfInterest
    {
        return ExpressionOfInterest::with('user:id,name,email')->findOrFail($this->eoiId);
    }

    #[Computed]
    public function review(): ?CommitteeReview
    {
        return $this->reviewId
            ? CommitteeReview::with('scorecards.user:id,name', 'secretary:id,name', 'decider:id,name')->find($this->reviewId)
            : null;
    }

    /** The Gate 2 assessment that went to the Committee */
    #[Computed]
    public function dd(): ?DueDiligence
    {
        return DueDiligence::with('conditions')
            ->where('expression_of_interest_id', $this->eoiId)
            ->where('outcome', DueDiligence::OUTCOME_PROCEED)
            ->whereNotNull('decided_at')
            ->orderByDesc('round')->first();
    }

    /** The applicant's enterprise profile (with its documents) */
    #[Computed]
    public function profile(): ?EnterpriseProfile
    {
        return EnterpriseProfile::with('documents')->where('user_id', $this->eoi->user_id)->first();
    }

    /**
     * What the applicant submitted — key facts (EOI + profile) and the documents that apply to them.
     *   ['facts' => [group => [[label, value, source]]], 'docs' => [section => [[label, ?EnterpriseDocument, required]]]]
     */
    #[Computed]
    public function submission(): array
    {
        $eoi  = $this->eoi;
        $p    = $this->profile;
        $P    = EnterpriseProfile::class;
        $rate = (float) config('cif.usd_to_lsl', 18.00);
        $lsl  = fn ($v) => $v === null || $v === '' ? null : 'M '.number_format((float) $v).' (≈ US$ '.number_format((float) $v / max($rate, 0.0001)).')';
        $usd  = fn ($v) => $v === null || $v === '' ? null : 'M '.number_format((float) $v * $rate).' (≈ US$ '.number_format((float) $v).')';
        $yn   = fn ($v) => $v === null ? null : ($v ? 'Yes' : 'No');

        $facts = [
            'Enterprise' => array_filter([
                ['Registered name', $p?->registered_name ?: $eoi->business_name, $p ? 'Profile' : 'EOI'],
                $p ? ['OBFC registration', $p->obfc_registered === null ? null : ($p->obfc_registered ? 'Registered'.($p->registration_no ? ' · '.$p->registration_no : '') : 'Not registered'), 'Profile'] : null,
                $p ? ['Type of registration', $p->legal_form, 'Profile'] : null,
                ['Location', $p ? collect([$p->city, $p->district])->filter()->implode(', ') : $eoi->district, $p ? 'Profile' : 'EOI'],
                $p ? ['Sector · business model', collect([$p->sector, $P::BUSINESS_MODELS[$p->business_model ?? ''] ?? null])->filter()->implode(' · '), 'Profile'] : null,
                $p ? ['Established · stage', collect([$p->year_established, $P::STAGES[$p->company_stage ?? ''] ?? null])->filter()->implode(' · '), 'Profile'] : null,
                $p ? ['Employees', $p->employees !== null ? $p->employees.' current · '.($p->employees_prev_year ?? '—').' previous year' : null, 'Profile'] : null,
                $p ? ['Women / youth owned or managed', 'Women: '.($yn($p->women_owned_managed) ?? '—').' · Youth: '.($yn($p->youth_owned_managed) ?? '—'), 'Profile'] : null,
                $p ? ['Company summary', $p->activity, 'Profile'] : ['Business description', $eoi->business_description, 'EOI'],
                $p ? ['Unique selling proposition', $p->usp, 'Profile'] : null,
                $p ? ['Marketing approach', $p->marketing_strategy, 'Profile'] : null,
                $p ? ['Competition', $p->competition, 'Profile'] : null,
            ]),
            'Investment' => array_filter([
                $p ? ['Investors in the round', collect($p->investorRows())->map(fn ($r) => $r['name'].(filled($r['ticket'] ?? null) ? ' — M '.number_format((float) $r['ticket']) : ''))->implode('; ') ?: $eoi->investor_name, 'Profile'] : ['Investor', $eoi->investor_name, 'EOI'],
                $p ? ['Financing type', $P::PARTICIPATION[$p->participation ?? ''] ?? null, 'Profile'] : null,
                $p ? ['Current investment round', $lsl($p->round_amount), 'Profile'] : ['Investor amount', $usd($eoi->investor_amount_usd), 'EOI'],
                $p ? ['CAFI matching grant ('.$P::grantRatioLabel().')', $lsl($p->grant_requested), 'Profile'] : null,
                $p ? ['Investment history', \App\Models\EnterpriseInvestment::summaryLine($p->investments), 'Profile'] : null,
                $p ? ['Investor track record (whole facility)', implode('; ', \App\Models\EnterpriseInvestment::trackRecordLines($p)) ?: null, 'Facility'] : null,
                ['Total investment required', $usd($eoi->investment_required_usd), 'EOI'],
                ['Planned use of the investment', $eoi->use_of_funds_description, 'EOI'],
                $p ? ['Annual revenue (past year)', $lsl($p->annual_revenue), 'Profile'] : null,
            ]),
        ];

        // Documents that apply to this applicant, grouped by profile section
        $docs = [];
        if ($p) {
            $required = $p->requiredDocumentTypes();
            foreach (EnterpriseDocument::TYPES as $type => [$label, , $section, $when]) {
                if (! is_bool($when) && ! in_array($type, $required, true)) {
                    continue;   // registration document that doesn't apply to this applicant
                }
                if ($type === 'logo') {
                    continue;
                }
                $docs[$P::SECTIONS[$section][0] ?? $section][] = [$label, $p->document($type), in_array($type, $required, true)];
            }
        }

        return ['facts' => $facts, 'docs' => $docs];
    }

    #[Computed]
    public function members()
    {
        return CommitteeReview::members();
    }

    #[Computed]
    public function history()
    {
        return CommitteeReview::with('decider:id,name')
            ->where('expression_of_interest_id', $this->eoiId)
            ->whereNotNull('decided_at')
            ->when($this->reviewId, fn ($q) => $q->whereKeyNot($this->reviewId))
            ->orderByDesc('round')->get();
    }

    #[Computed]
    public function isApplicant(): bool
    {
        return $this->eoi->user_id === auth()->id();
    }

    /** Gate 3 reached (submitted to the Committee now or before) */
    #[Computed]
    public function gateOpen(): bool
    {
        return in_array($this->eoi->status, ExpressionOfInterest::COMMITTEE_VISIBLE, true) || $this->reviewId !== null;
    }

    /** The current round can still be worked on */
    #[Computed]
    public function isOpen(): bool
    {
        return $this->eoi->status === ExpressionOfInterest::STATUS_COMMITTEE && ! ($this->review?->isDecided());
    }

    /** Logged-in user is one of the Committee members */
    #[Computed]
    public function isMember(): bool
    {
        return auth()->user()->can('gate3.score') && $this->members->contains('id', auth()->id());
    }

    #[Computed]
    public function myCard(): ?CommitteeScorecard
    {
        return $this->review?->scorecards->firstWhere('user_id', auth()->id());
    }

    #[Computed]
    public function canScore(): bool
    {
        return ! $this->isApplicant && $this->isOpen && $this->isMember && ! ($this->myCard?->isSubmitted());
    }

    #[Computed]
    public function canRecord(): bool
    {
        return ! $this->isApplicant && $this->isOpen && auth()->user()->can('gate3.decide');
    }

    #[Computed]
    public function status(): array
    {
        return $this->review
            ? $this->review->scoringStatus($this->members)
            : ['members' => $this->members->count(), 'scoring' => $this->members->count(), 'submitted' => 0, 'complete' => false];
    }

    /** Individual scores are visible once complete (or decided) */
    #[Computed]
    public function revealed(): bool
    {
        return $this->status['complete'] || (bool) $this->review?->isDecided();
    }

    /** Reasons the outcome can't be recorded yet */
    #[Computed]
    public function blockers(): array
    {
        $s    = $this->status;
        $size = CommitteeReview::expectedSize();

        return array_values(array_filter([
            $s['members'] < $size ? "Quorum not met — {$s['members']} of {$size} Committee members are set up as active users." : null,
            ! $s['complete'] ? "Scorecards submitted: {$s['submitted']} of {$s['scoring']}." : null,
            collect(CommitteeReview::CRITERIA)->keys()->contains(fn ($k) => blank($this->criteria[$k]['view'] ?? null)) ? 'Record the Committee view on every review criterion.' : null,
        ]));
    }

    /* =====================================================================
     | Load
     ===================================================================== */

    protected function load(): void
    {
        $review = CommitteeReview::where('expression_of_interest_id', $this->eoiId)->orderByDesc('round')->first();

        if ($review) {
            $this->reviewId = $review->id;
            $this->round    = $review->round;
            $data           = $review->only(['meeting_ref', 'venue', 'criteria', 'deliberation', 'questions', 'conditions', 'outcome', 'private_amount', 'grant_amount', 'rationale', 'actions']);
            $data['meeting_date']       = $review->meeting_date?->toDateString();
            $data['pack_circulated_on'] = $review->pack_circulated_on?->toDateString();
        } else {
            $this->reviewId = null;
            $this->round    = 1;
            $data           = CommitteeReview::prefill($this->eoi, $this->dd);
        }

        $this->fillRecord($data);
        $this->loadCard();

        unset($this->review, $this->myCard, $this->status, $this->revealed);
    }

    protected function fillRecord(array $data): void
    {
        $this->meeting = [
            'meeting_ref'        => $data['meeting_ref'] ?? '',
            'meeting_date'       => $data['meeting_date'] ?? null,
            'venue'              => $data['venue'] ?? '',
            'pack_circulated_on' => $data['pack_circulated_on'] ?? null,
        ];
        $this->criteria = collect(CommitteeReview::CRITERIA)->mapWithKeys(fn ($c, $k) => [$k => [
            'view' => $data['criteria'][$k]['view'] ?? null,
            'note' => $data['criteria'][$k]['note'] ?? '',
        ]])->all();
        $this->deliberation = collect(CommitteeReview::STEPS)->mapWithKeys(fn ($s, $k) => [$k => [
            'done' => (bool) ($data['deliberation'][$k]['done'] ?? false),
            'note' => $data['deliberation'][$k]['note'] ?? '',
        ]])->all();
        $this->questions  = array_values($data['questions'] ?? []);
        $this->conditions = collect($data['conditions'] ?? [])->map(fn ($c) => array_merge(
            ['source' => 'committee', 'ref_id' => null, 'finding' => '', 'treatment' => '', 'owner' => 'enterprise', 'status' => 'open', 'decision' => 'added'], $c
        ))->values()->all();
        $this->decision = [
            'outcome'        => $data['outcome'] ?? null,
            'private_amount' => ($data['private_amount'] ?? null) !== null ? (float) $data['private_amount'] : null,
            'grant_amount'   => ($data['grant_amount'] ?? null) !== null ? (float) $data['grant_amount'] : null,
            'rationale'      => (string) ($data['rationale'] ?? ''),
            'actions'        => (string) ($data['actions'] ?? ''),
        ];
    }

    protected function loadCard(): void
    {
        $c = $this->reviewId
            ? CommitteeScorecard::where('committee_review_id', $this->reviewId)->where('user_id', auth()->id())->first()
            : null;

        $this->card = [
            'coi'                => $c?->coi,
            'coi_details'        => (string) $c?->coi_details,
            'answers'            => collect(CommitteeScorecard::criteriaKeys())->mapWithKeys(fn ($k) => [$k => $c?->answers[$k] ?? null])->all(),
            'innovation'         => collect(CommitteeScorecard::INNOVATION)->mapWithKeys(fn ($l, $k) => [$k => in_array($k, $c?->innovation ?? [], true)])->all(),
            'comments'           => collect(CommitteeScorecard::GROUPS)->mapWithKeys(fn ($g, $k) => [$k => $c?->comments[$k] ?? ''])->all(),
            'use_of_funds_notes' => (string) $c?->use_of_funds_notes,
            'issues'             => (string) $c?->issues,
            'recommendation'     => $c?->recommendation,
            'takeaways'          => (string) $c?->takeaways,
        ];
    }

    /* =====================================================================
     | Autosave
     ===================================================================== */

    public function updated(string $name): void
    {
        if (Str::startsWith($name, 'card')) {
            if ($this->canScore) {
                $this->saveCard();
            }
            return;
        }

        if (Str::startsWith($name, ['meeting', 'criteria', 'deliberation', 'conditions', 'decision'])) {
            if (! $this->canRecord) {
                $this->load();   // discard changes from a read-only viewer
                return;
            }
            $this->persistReview();
            unset($this->blockers);
        }
    }

    /* =====================================================================
     | Scorecard (gate3.score)
     ===================================================================== */

    protected function saveCard(bool $submit = false): CommitteeScorecard
    {
        $review = $this->ensureReview();

        $card = CommitteeScorecard::firstOrNew(['committee_review_id' => $review->id, 'user_id' => auth()->id()]);
        $card->fill([
            'coi'                => $this->card['coi'] ?: null,
            'coi_details'        => trim($this->card['coi_details']) ?: null,
            'answers'            => array_filter($this->card['answers']),
            'innovation'         => array_keys(array_filter($this->card['innovation'])),
            'comments'           => array_filter($this->card['comments'], 'filled'),
            'use_of_funds_notes' => trim($this->card['use_of_funds_notes']) ?: null,
            'issues'             => trim($this->card['issues']) ?: null,
            'recommendation'     => $this->card['recommendation'] ?: null,
            'takeaways'          => trim($this->card['takeaways']) ?: null,
            'submitted_at'       => $submit ? now() : null,
        ])->save();

        $this->savedAt = now()->format('H:i');
        unset($this->review, $this->myCard, $this->status, $this->revealed, $this->blockers);

        return $card;
    }

    public function submitScorecard(): void
    {
        abort_unless($this->canScore, 403, 'You cannot submit a scorecard for this transaction.');

        $recused = $this->card['coi'] === 'recused';

        $rules = [
            'card.coi'         => ['required', Rule::in(array_keys(CommitteeScorecard::COI))],
            'card.coi_details' => [in_array($this->card['coi'], ['declared', 'recused'], true) ? 'required' : 'nullable', 'string', 'max:2000'],
        ];
        if (! $recused) {
            foreach (CommitteeScorecard::criteriaKeys() as $k) {
                $rules["card.answers.$k"] = ['required', Rule::in(['met', 'not_met'])];
            }
            $rules['card.recommendation'] = ['required', Rule::in(array_keys(CommitteeScorecard::RECOMMENDATIONS))];
            $rules['card.takeaways']      = ['required', 'string', 'max:3000'];
        }

        $this->validate($rules, [
            'card.coi.required'            => 'Declare whether you have a conflict of interest.',
            'card.coi_details.required'    => 'Describe the conflict of interest.',
            'card.answers.*.required'      => 'Mark this criterion as Met or Not met.',
            'card.recommendation.required' => 'Choose Approve or Reject.',
            'card.takeaways.required'      => 'Add your key take-aways and rationale.',
        ]);

        if (! $recused && ($this->card['answers']['innovative'] ?? null) === 'met' && ! array_filter($this->card['innovation'])) {
            $this->addError('card.innovation', 'Tick at least one type of innovation.');
            return;
        }

        $wasComplete = $this->status['complete'];

        $this->saveCard(submit: true);

        $this->dispatch('notify', type: 'success', message: $recused ? 'Recusal recorded.' : 'Scorecard submitted.');

        // This was the last scorecard → tell whoever records the outcome
        if (! $wasComplete && $this->status['complete']) {
            $note = NotifyRecipients::send(
                NotifyRecipients::deciders(),
                new EoiScorecardsComplete($this->eoi, (int) $this->status['submitted'], $this->round),
                'the Committee secretary',
                except: auth()->id(),
            );
            $this->dispatch('notify', type: 'info', message: 'All scorecards are in — the consolidated scorecard is now visible. '.$note);
        }
    }

    /* =====================================================================
     | Committee record (gate3.decide)
     ===================================================================== */

    public function addQuestion(): void
    {
        abort_unless($this->canRecord, 403);

        $this->validate([
            'newQuestion.asked_by' => ['required', 'string', 'max:100'],
            'newQuestion.question' => ['required', 'string', 'max:1000'],
            'newQuestion.answer'   => ['nullable', 'string', 'max:2000'],
        ], ['newQuestion.asked_by.required' => 'Who asked?', 'newQuestion.question.required' => 'Enter the question.']);

        $this->questions[] = $this->newQuestion;
        $this->reset('newQuestion');
        $this->persistReview();
    }

    public function removeQuestion(int $i): void
    {
        abort_unless($this->canRecord, 403);
        unset($this->questions[$i]);
        $this->questions = array_values($this->questions);
        $this->persistReview();
    }

    public function addCondition(): void
    {
        abort_unless($this->canRecord, 403);
        $this->conditions[] = ['source' => 'committee', 'ref_id' => null, 'finding' => '', 'treatment' => 'Condition of approval', 'owner' => 'enterprise', 'status' => 'open', 'decision' => 'added'];
        $this->persistReview();
    }

    public function removeCondition(int $i): void
    {
        abort_unless($this->canRecord, 403);
        abort_unless(($this->conditions[$i]['source'] ?? null) === 'committee', 403, 'Gate 2 conditions can be marked "Removed" but not deleted.');
        unset($this->conditions[$i]);
        $this->conditions = array_values($this->conditions);
        $this->persistReview();
    }

    public function recordDecision(): void
    {
        abort_unless($this->canRecord, 403);

        if ($this->blockers) {
            $this->addError('decision.outcome', 'The Committee outcome can\'t be recorded yet — see the items listed.');
            return;
        }

        $this->validate([
            'decision.outcome'        => ['required', Rule::in(array_keys(CommitteeReview::OUTCOMES))],
            'decision.private_amount' => ['required', 'numeric', 'min:0'],
            'decision.grant_amount'   => [in_array($this->decision['outcome'], ['approve', 'conditional'], true) ? 'required' : 'nullable', 'numeric', 'min:0'],
            'decision.rationale'      => ['required', 'string', 'max:5000'],
            'decision.actions'        => [$this->decision['outcome'] === 'defer' ? 'required' : 'nullable', 'string', 'max:3000'],
            'meeting.meeting_date'    => ['required', 'date', 'before_or_equal:today'],
        ], [
            'decision.outcome.required'      => 'Choose the Committee outcome.',
            'decision.rationale.required'    => 'Record the rationale for the decision.',
            'decision.actions.required'      => 'Specify what must be done before resubmission.',
            'meeting.meeting_date.required'  => 'Enter the meeting date.',
        ]);

        $outcome = $this->decision['outcome'];

        if ($outcome === 'conditional' && ! collect($this->conditions)->contains(fn ($c) => $c['decision'] !== 'removed' && filled($c['finding']))) {
            $this->addError('decision.outcome', 'Conditional approval needs at least one defined condition.');
            return;
        }

        DB::transaction(function () use ($outcome) {
            $this->persistReview()->update([
                'decided_at' => now(),
                'decided_by' => auth()->id(),
            ]);
            $this->eoi->update(['status' => CommitteeReview::OUTCOME_TO_EOI_STATUS[$outcome]]);
        });

        // E-mails (after the decision is saved)
        $notes = $this->emailDecision();

        unset($this->eoi, $this->review, $this->isOpen, $this->canScore, $this->canRecord, $this->revealed, $this->history);

        $this->dispatch('gate-decided', gate: 'g3', decision: $outcome);
        $this->dispatch('notify', type: 'success',
            message: 'Committee decision recorded: '.CommitteeReview::OUTCOMES[$outcome][0].'. '.$notes);
    }

    /** Deferred → PMU resubmits to the Committee (new round, fresh scorecards) */
    public function resubmit(): void
    {
        abort_unless(
            ! $this->isApplicant && auth()->user()->can('gate2.assess')
            && $this->eoi->status === ExpressionOfInterest::STATUS_IC_DEFERRED
            && $this->review?->outcome === CommitteeReview::OUTCOME_DEFER,
            403
        );

        $this->validate(['resubmissionNote' => ['required', 'string', 'max:3000']], ['resubmissionNote.required' => 'Explain how the Committee\'s requests were addressed.']);

        $old  = $this->review;
        $note = trim($this->resubmissionNote);

        DB::transaction(function () use ($old, $note) {
            CommitteeReview::create(array_merge(CommitteeReview::prefill($this->eoi, $this->dd), [
                'expression_of_interest_id' => $this->eoiId,
                'due_diligence_id'          => $this->dd?->id,
                'round'                     => $old->round + 1,
                'resubmission_note'         => $note,
                'conditions'                => $old->conditions,   // keep the Committee's conditions
            ]));
            $this->eoi->update(['status' => ExpressionOfInterest::STATUS_COMMITTEE]);
        });

        $this->reset('resubmissionNote');
        unset($this->eoi);
        $this->load();
        unset($this->isOpen, $this->canScore, $this->canRecord, $this->history, $this->blockers);

        // E-mail the Committee: new round to score
        $emailNote = '';
        if ($dd = $this->dd) {
            $emailNote = NotifyRecipients::send(
                NotifyRecipients::committee(),
                new EoiReadyForCommittee($this->eoi, $dd, $this->round, $note),
                'the Investment Committee',
                except: auth()->id(),
            );
        }

        $this->dispatch('gate-decided', gate: 'g3', decision: null);
        $this->dispatch('notify', type: 'success', message: "Resubmitted to the Investment Committee — round {$this->round}. {$emailNote}");
    }

    /* =====================================================================
     | E-mails
     ===================================================================== */

    /** Applicant + PMU + Committee members, after the outcome is recorded. Returns notes for the officer. */
    protected function emailDecision(): string
    {
        $eoi    = ExpressionOfInterest::with('user')->find($this->eoiId);
        $review = CommitteeReview::find($this->reviewId);

        if (! $eoi || ! $review || ! $review->outcome) {
            return '';
        }

        $notes = [];

        // Applicant
        $applicant = $eoi->user;
        if (! $applicant || blank($applicant->email)) {
            $notes[] = 'The applicant has no e-mail address — please let them know.';
        } else {
            try {
                $applicant->notify(new EoiGate3Outcome($eoi, $review));
                $notes[] = 'The applicant was e-mailed ('.$applicant->email.').';
            } catch (\Throwable $e) {
                report($e);
                $notes[] = 'The e-mail to the applicant could not be sent — please let them know.'.NotifyRecipients::debugReason($e);
            }
        }

        // PMU (next step is theirs: Gate 4, resubmission or closing) + the Committee members
        $staff = NotifyRecipients::pmu()->merge(NotifyRecipients::committee())->unique('id')->values();
        $notes[] = NotifyRecipients::send($staff, new EoiCommitteeDecisionRecorded($eoi, $review), 'the PMU and Committee', except: auth()->id());

        return implode(' ', array_filter($notes));
    }

    /* =====================================================================
     | Internals
     ===================================================================== */

    protected function ensureReview(): CommitteeReview
    {
        if ($this->reviewId) {
            return CommitteeReview::findOrFail($this->reviewId);
        }

        $review = CommitteeReview::create(array_merge(CommitteeReview::prefill($this->eoi, $this->dd), $this->recordData(), [
            'expression_of_interest_id' => $this->eoiId,
            'due_diligence_id'          => $this->dd?->id,
            'round'                     => $this->round,
            'secretary_id'              => auth()->user()->can('gate3.decide') ? auth()->id() : null,
        ]));

        $this->reviewId = $review->id;

        return $review;
    }

    protected function recordData(): array
    {
        return [
            'meeting_ref'        => trim((string) $this->meeting['meeting_ref']) ?: null,
            'meeting_date'       => $this->meeting['meeting_date'] ?: null,
            'venue'              => trim((string) $this->meeting['venue']) ?: null,
            'pack_circulated_on' => $this->meeting['pack_circulated_on'] ?: null,
            'criteria'           => $this->criteria,
            'deliberation'       => $this->deliberation,
            'questions'          => $this->questions,
            'conditions'         => collect($this->conditions)->map(fn ($c) => array_merge($c, ['finding' => Str::limit((string) $c['finding'], 250, '')]))->all(),
            'outcome'            => $this->decision['outcome'] ?: null,
            'private_amount'     => $this->decision['private_amount'] === '' ? null : $this->decision['private_amount'],
            'grant_amount'       => $this->decision['grant_amount'] === '' ? null : $this->decision['grant_amount'],
            'rationale'          => trim($this->decision['rationale']) ?: null,
            'actions'            => trim($this->decision['actions']) ?: null,
        ];
    }

    protected function persistReview(): CommitteeReview
    {
        $review = $this->ensureReview();
        $review->update($this->recordData() + ['secretary_id' => $review->secretary_id ?? auth()->id()]);

        $this->savedAt = now()->format('H:i');
        unset($this->review);

        return $review;
    }

    #[On('gate-decided')]
    public function refreshGate(): void
    {
        unset($this->eoi, $this->gateOpen, $this->isOpen, $this->canScore, $this->canRecord);
    }
};
?>

<div>
@php
    $CR        = \App\Models\CommitteeReview::class;
    $SC        = \App\Models\CommitteeScorecard::class;
    $DD        = \App\Models\DueDiligence::class;
    $eoi       = $this->eoi;
    $review    = $this->review;
    $dd        = $this->dd;
    $members   = $this->members;
    $status    = $this->status;
    $revealed  = $this->revealed;
    $canScore  = $this->canScore;
    $canRecord = $this->canRecord;
    $isMember  = $this->isMember;
    $decided   = (bool) $review?->isDecided();
    $cards     = $review?->scorecards->keyBy('user_id') ?? collect();
    $myCard    = $this->myCard;
    $blockers  = $this->isApplicant ? [] : $this->blockers;
    $history   = $this->isApplicant ? collect() : $this->history;
    $submission = $this->isApplicant || ! $this->gateOpen ? ['facts' => [], 'docs' => []] : $this->submission;
    $profile    = $this->isApplicant ? null : $this->profile;

    // Figures from Gate 2
    $ddForm    = $dd?->toForm() ?? [];
    $validated = $dd ? $DD::validated($ddForm) : ['total' => 0, 'private' => 0, 'grant' => 0, 'structure' => null];

    $rate = (float) config('cif.usd_to_lsl', 18.00);
    $m    = fn ($v) => $v === null || $v === '' ? '—' : 'M '.number_format((float) $v);
    $u    = fn ($v) => $v === null || $v === '' ? '' : 'US$ '.number_format((float) $v / max($rate, 0.0001));

    // Gate 3 path
    $path = [
        ['Gate 2 passed', 'done'],
        ['TSS + Investment Appraisal Memorandum', $dd ? 'done' : 'todo'],
        ['Investment Committee review', $decided ? 'done' : 'current'],
        ['Approve / Conditional / Defer / Reject', $decided ? 'done' : 'todo'],
        ['Gate 4', $review?->isApproved() ? 'current' : 'todo'],
    ];
@endphp

{{-- ======================================================================
     LOCKED — not submitted to the Committee yet
     ====================================================================== --}}
@if (! $this->gateOpen)
    <section class="section-card">
        <div class="section-card-head">
            <span class="section-no">G3</span>
            <h5 class="me-auto">Investment Committee review &amp; recommendation</h5>
            <span class="pill pill-muted"><i class="bi bi-lock-fill"></i> Locked</span>
        </div>
        <p class="small text-muted mb-2">Only transactions that pass Gate 2 (Proceed) go to the Investment Committee. The Committee reviews:</p>
        <ul class="evidence-list small text-muted mb-0">
            @foreach ($CR::CRITERIA as [$cTitle, $cQ]) <li><strong class="text-navy">{{ $cTitle }}</strong> — {{ $cQ }}</li> @endforeach
        </ul>
    </section>

{{-- ======================================================================
     APPLICANT
     ====================================================================== --}}
@elseif ($this->isApplicant)
    @include('expression-of-interest.committee.partials.applicant')

{{-- ======================================================================
     COMMITTEE / STAFF
     ====================================================================== --}}
@else
    <div class="g2-path mb-3">
        @foreach ($path as [$pLabel, $pState])
            <span class="g2-path-step {{ $pState !== 'todo' ? 'is-'.$pState : '' }}">
                <i class="bi {{ ['done' => 'bi-check-lg', 'current' => 'bi-hourglass-split', 'todo' => 'bi-circle'][$pState] }}"></i> {{ $pLabel }}
            </span>
        @endforeach
    </div>

    {{-- ============================ HEADER / MEETING ============================ --}}
    <section class="section-card">
        <div class="section-card-head">
            <span class="section-no">G3</span>
            <div class="me-auto">
                <h5>Investment Committee review &amp; recommendation @if ($round > 1) <span class="pill pill-muted ms-1">Round {{ $round }}</span> @endif</h5>
                <div class="small text-muted">Does the transaction, as presented, satisfy the Facility's requirements and justify the use of public grant resources?</div>
            </div>
            @if ($decided)
                @php [$oL, , , $oI, $oP] = $CR::OUTCOMES[$review->outcome]; @endphp
                <span class="pill {{ $oP }}"><i class="bi {{ $oI }}"></i> {{ $oL }}</span>
            @elseif ($eoi->status === 'committee')
                <span class="pill pill-warn"><i class="bi bi-hourglass-split"></i> Under review</span>
            @endif
        </div>

        @if ($canRecord || $canScore)
            <div class="small text-muted mb-3">
                <i class="bi bi-cloud-check me-1"></i> Changes are saved automatically.
                @if ($savedAt) <span class="text-accent">Last saved {{ $savedAt }}.</span> @endif
                <span wire:loading class="ms-1"><span class="spinner-border spinner-border-sm"></span> Saving…</span>
            </div>
        @elseif (! $decided && ! $isMember && ! auth()->user()->can('gate3.decide'))
            <div class="small text-muted mb-3"><i class="bi bi-eye me-1"></i> Read-only — Gate 3 is carried out by the Investment Committee.</div>
        @endif

        @if ($review?->resubmission_note)
            <div class="callout small mb-3">
                <div class="fw-semibold mb-1"><i class="bi bi-arrow-repeat me-1"></i> Resubmitted after deferral</div>
                {{ $review->resubmission_note }}
            </div>
        @endif

        <div class="row g-3">
            <div class="col-md-6">
                <dt class="detail-label">Enterprise</dt>
                <dd class="mb-0 text-navy">{{ $eoi->business_name ?: '—' }} <span class="chip ms-1">{{ $eoi->reference }}</span></dd>
            </div>
            <div class="col-md-6">
                <dt class="detail-label">Committee secretary</dt>
                <dd class="mb-0 text-navy">{{ $review?->secretary?->name ?? ($canRecord ? auth()->user()->name : '—') }}</dd>
            </div>
            <div class="col-md-3">
                <label class="detail-label" for="ic_ref">Meeting ref.</label>
                <input type="text" id="ic_ref" class="form-control form-control-sm" wire:model.blur="meeting.meeting_ref" maxlength="50" placeholder="IC-2026-11" @disabled(! $canRecord)>
            </div>
            <div class="col-md-3">
                <label class="detail-label" for="ic_date">Meeting date</label>
                <input type="date" id="ic_date" class="form-control form-control-sm @error('meeting.meeting_date') is-invalid @enderror" wire:model.blur="meeting.meeting_date" @disabled(! $canRecord)>
                @error('meeting.meeting_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-3">
                <label class="detail-label" for="ic_venue">Venue</label>
                <input type="text" id="ic_venue" class="form-control form-control-sm" wire:model.blur="meeting.venue" maxlength="250" placeholder="Boardroom / online" @disabled(! $canRecord)>
            </div>
            <div class="col-md-3">
                <label class="detail-label" for="ic_pack">Pack circulated</label>
                <input type="date" id="ic_pack" class="form-control form-control-sm" wire:model.blur="meeting.pack_circulated_on" @disabled(! $canRecord)>
            </div>
        </div>

        <div class="callout small mt-3 mb-0">
            <i class="bi bi-info-circle me-1"></i>
            The Committee relies on the Gate 1 and Gate 2 assessments — it does not re-perform eligibility or due diligence checks,
            value the business or replace the investor's commercial decision.
        </div>
    </section>

    @include('expression-of-interest.committee.partials.members')
    @include('expression-of-interest.committee.partials.pack')
    @include('expression-of-interest.committee.partials.submission')
    @include('expression-of-interest.committee.partials.glance')
    @if ($isMember && ($review === null || ! $decided || $myCard))
        @include('expression-of-interest.committee.partials.scorecard')
    @endif
    @include('expression-of-interest.committee.partials.consolidated')
    @include('expression-of-interest.committee.partials.record')
    @include('expression-of-interest.committee.partials.outcome')
@endif
</div>