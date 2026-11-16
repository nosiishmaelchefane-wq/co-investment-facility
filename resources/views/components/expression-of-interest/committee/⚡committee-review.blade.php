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
use Illuminate\Validation\ValidationException;
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
|     Committee secretary       — ONE of the members, ticked in user management (gate3.decide, given directly):
|                                 records for the whole Committee — review criteria, deliberation, questions log,
|                                 material risks & conditions, and the Committee outcome and rationale
|     Other staff (eoi.view-all): read-only.   Applicant: outcome and the conditions they own.
|   Rules:
|     Opens once Gate 2 = Proceed (EOI status "committee").
|     Members = active users with the "committee" role (3 fixed members — config cif.committee.size).
|     Information pack: built from the Gate 2 assessment, incl. the Investment Appraisal Memorandum (5 questions).
|       Every member comments on each pack item (part of their scorecard).
|     Scorecard (Appendix F): each criterion scored 1–5 (3+ = met) → total, % and the member's Approve / Reject.
|     Scorecards and pack comments stay hidden until every non-recused member has submitted; then they're consolidated.
|     The outcome can only be recorded once scoring is complete.
|     Approve / Conditional → grant_readiness (Gate 4 · Grant readiness) · Defer → ic_deferred (PMU resubmits → new round) · Reject → not_progressed
|   Secretary (gate3.decide) — each member's card has two buttons while the round is open:
|     Edit     → the secretary corrects that member's scorecard (autosaved; its submitted state is kept;
|                "Edited by <secretary>" is shown on it)
|     Reopen   → a submitted scorecard goes back to draft so the member can change and resubmit it
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

    /** My Appendix F scorecard (gate3.score) — or, while the secretary edits one, that member's */
    public array $card = [];

    /** The member whose scorecard the secretary is editing (null = my own) */
    #[Locked]
    public ?int $editingMemberId = null;

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
            ? CommitteeReview::with('scorecards.user:id,name', 'scorecards.editor:id,name', 'secretary:id,name', 'decider:id,name')->find($this->reviewId)
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
        return EnterpriseProfile::with('documents', 'investments')->where('user_id', $this->eoi->user_id)->first();
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
        // US$ first, Maloti in brackets ($lsl: a Maloti amount · $usd: a US$ amount)
        $lsl  = fn ($v) => $v === null || $v === '' ? null : 'US$ '.number_format((float) $v / max($rate, 0.0001)).' (≈ M '.number_format((float) $v).')';
        $usd  = fn ($v) => $v === null || $v === '' ? null : 'US$ '.number_format((float) $v).' (≈ M '.number_format((float) $v * $rate).')';
        $plan = $p ? EnterpriseProfile::planTotals((array) ($p->grant_plan ?? [])) : ['total' => 0, 'outside' => 0, 'categories' => []];
        $yn   = fn ($v) => $v === null ? null : ($v ? 'Yes' : 'No');

        $facts = [
            'Enterprise' => array_filter([
                ['Registered name', $p?->registered_name ?: $eoi->business_name, $p ? 'Profile' : 'EOI'],
                $p ? ['OBFC registration', ($P::YES_NO_PROGRESS[$p->obfc_status ?? ''] ?? ($p->obfc_registered === null ? null : ($p->obfc_registered ? 'Yes' : 'No'))).($p->registration_no ? ' · '.$p->registration_no : ''), 'Profile'] : null,
                $p ? ['Type of registration', $p->legal_form, 'Profile'] : null,
                ['Location', $p ? collect([$p->city, $p->district])->filter()->implode(', ') : $eoi->district, $p ? 'Profile' : 'EOI'],
                $p ? ['Sector', collect([$p->sectorLabel(), $p->sub_sector])->filter()->implode(' · '), 'Profile'] : null,
                $p ? ['Business model(s)', $p->businessModelLabel(), 'Profile'] : null,
                $p ? ['Operating since · stage', collect([$p->commencedLabel(), $P::STAGES[$p->company_stage ?? ''] ?? null])->filter()->implode(' · '), 'Profile'] : null,
                $p ? ['Employees', $p->employees !== null ? $p->employees.' current · '.($p->employees_prev_year ?? '—').' previous year'.($p->expected_jobs ? ' · '.$p->expected_jobs.' new jobs expected' : '') : null, 'Profile'] : null,
                $p ? ['Shareholders', collect($p->shareholderRows())->map(fn ($r) => $r['name'].(filled($r['percent'] ?? null) ? ' ('.$r['percent'].'%)' : ''))->implode('; ') ?: null, 'Profile'] : null,
                $p ? ['Directors & key management', collect($p->keyPeopleRows())->map(fn ($r) => $r['name'].(filled($r['position'] ?? null) ? ' — '.$r['position'] : ''))->implode('; ') ?: null, 'Profile'] : null,
                $p ? ['Products & services', $p->products_services, 'Profile'] : null,
                $p ? ['Customers', $p->customers, 'Profile'] : null,
                $p ? ['Women / youth owned or managed', 'Women: '.($yn($p->women_owned_managed) ?? '—').' · Youth: '.($yn($p->youth_owned_managed) ?? '—'), 'Profile'] : null,
                $p ? ['Company summary', $p->activity, 'Profile'] : ['Business description', $eoi->business_description, 'EOI'],
                $p ? ['Unique selling proposition', $p->usp, 'Profile'] : null,
                $p ? ['Marketing approach', $p->marketing_strategy, 'Profile'] : null,
                $p ? ['Competition', $p->competition, 'Profile'] : null,
            ]),
            'Investment' => array_filter([
                $p ? ['Lead investor', ($p->lead_investor_name ?: $p->investor_name ?: $eoi->investor_name)
                        .(collect([$p->investor_type, $p->lead_investor_country])->filter()->isNotEmpty() ? ' ('.collect([$p->investor_type, $p->lead_investor_country])->filter()->implode(', ').')' : ''), 'Profile']
                   : ['Investor', $eoi->investor_name, 'EOI'],
                $p ? ['Investment instrument', $P::PARTICIPATION[$p->participation ?? ''] ?? null, 'Profile'] : null,
                $p ? ['Investor engagement · commitment', collect([$P::TRANSACTION_STATUSES[$p->transaction_status ?? ''] ?? null, isset($P::COMMITMENT[$p->investor_commitment ?? '']) ? 'committed: '.strtolower($P::COMMITMENT[$p->investor_commitment]) : null])->filter()->implode(' · ') ?: null, 'Profile'] : null,
                $p ? ['Investment amount from the investor', $lsl($p->investorAmount()), 'Profile'] : ['Investor amount', $usd($eoi->investor_amount_usd), 'EOI'],
                $p ? ['CAFI matching grant ('.$P::grantRatioLabel().')', $lsl($p->grant_requested), 'Profile'] : null,
                $eoi->grant_requested_usd !== null ? ['CAFI grant requested', $usd($eoi->grant_requested_usd), 'EOI'] : null,
                $p && $plan['total'] > 0 ? ['Grant budget (8.4)', collect($plan['categories'])->filter(fn ($x) => $x > 0)->map(fn ($x, $k) => ($P::GRANT_CATEGORIES[$k][0] ?? $k).' '.$usd($x))->implode('; ').' — total '.$usd($plan['total']), 'Profile'] : null,
                $p?->expected_close ? ['Expected close', $p->expected_close, 'Profile'] : null,
                $p ? ['Investment history', \App\Models\EnterpriseInvestment::summaryLine($p->investments), 'Profile'] : null,
                $p ? ['Investor track record (whole facility)', implode('; ', \App\Models\EnterpriseInvestment::trackRecordLines($p)) ?: null, 'Facility'] : null,
                ['Total investment required', $usd($eoi->investment_required_usd), 'EOI'],
                ['Transaction description', $p?->tx_description ?: ($p?->investment_purpose ?: $eoi->use_of_funds_description), ($p?->tx_description || $p?->investment_purpose) ? 'Profile' : 'EOI'],
                $p ? ['Total project cost', $lsl($p->project_cost), 'Profile'] : null,
                $p ? ['Annual revenue (past year)', $lsl($p->annual_revenue), 'Profile'] : null,
            ]),
        ];

        // Documents that apply to this applicant, grouped by profile section
        $docs = [];
        if ($p) {
            $required = $p->requiredDocumentTypes();
            $planShown = collect(EnterpriseDocument::PLAN_OR_DECK)->first(fn ($t) => (bool) $p->document($t)) ?? EnterpriseDocument::PLAN_OR_DECK[0];
            foreach (EnterpriseDocument::TYPES as $type => [$label, , $section, $when]) {
                if (in_array($type, EnterpriseDocument::PLAN_OR_DECK, true)) {
                    if ($type !== $planShown) {
                        continue;   // business plan OR pitch deck — show one
                    }
                    $docs[$P::SECTIONS[$section][0] ?? $section][] = [$p->document($type) ? $label : EnterpriseDocument::missingLabel($type), $p->document($type), true];
                    continue;
                }
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

    /**
     * Committee information pack (§3.4.1), built from the Gate 2 assessment that went to the Committee.
     *   key => ['present' => bool, 'status' => [label, pill] | null, 'lines' => [[label, text]], 'link' => url | null]
     */
    #[Computed]
    public function pack(): array
    {
        $dd = $this->dd;
        if (! $dd) {
            return [];
        }

        $f    = $dd->toForm();
        $v    = DueDiligence::validated($f);
        $rate = max((float) config('cif.usd_to_lsl', 18.00), 0.0001);
        $m    = fn ($x) => (float) $x > 0 ? 'US$ '.number_format((float) $x / $rate).' (M '.number_format((float) $x).')' : '—';
        $memo = DueDiligence::memoRows($dd->memo);
        $pdf  = route('due-diligence.memorandum', $dd);

        $area = function (string $k) use ($f) {
            $st = $f['areas'][$k]['status'] ?? null;

            return [
                'present' => true,
                'status'  => [DueDiligence::STATUSES[$st][0] ?? 'Not assessed', ['complete' => 'pill-ok', 'clarification' => 'pill-warn'][$st] ?? 'pill-muted'],
                'lines'   => [['Findings', trim((string) ($f['areas'][$k]['finding'] ?? '')) ?: 'No findings recorded.']],
                'link'    => null,
            ];
        };

        $expected = EnterpriseProfile::matchingGrant($v['private'] ?: null);

        return [
            'tss' => ['present' => true, 'status' => null, 'link' => null, 'lines' => array_values(array_filter([
                ['Enterprise', $f['summary'] ?: ($this->eoi->business_name ?? '—')],
                ['Investors', $f['investor_names'] ?: '—'],
                ['Stage · structure', collect([DueDiligence::STAGES[$f['stage'] ?? ''][0] ?? null, $v['structure']])->filter()->implode(' · ') ?: '—'],
                ['Expected outcomes', $f['expected_outcomes'] ?: '—'],
                ['Key risks', $f['key_risks'] ?: '—'],
            ]))],
            'iam' => [
                'present' => DueDiligence::memoComplete($dd->memo) || (bool) $dd->iam_path,
                'status'  => DueDiligence::memoComplete($dd->memo)
                    ? (DueDiligence::isLegacyMemo($dd->memo) ? ['5 / 5 answered', 'pill-ok'] : [DueDiligence::memoVerifiedCount($dd->memo).' / 5 verified', DueDiligence::memoVerified($dd->memo) ? 'pill-ok' : 'pill-warn'])
                    : ($dd->iam_path ? ['Uploaded document', 'pill-muted'] : ['Incomplete', 'pill-warn']),
                'lines'   => collect(DueDiligence::memoQuestions($dd->memo))->map(fn ($q, $k) => [$q[0], trim($memo[$k]['text'] ?? '') ?: 'Not answered.', $q[1]])->values()->all(),
                'link'    => $pdf,
            ],
            'enterprise'  => $area('enterprise'),
            'investor'    => $area('investor'),
            'transaction' => $area('transaction'),
            'financial'   => $area('financial'),
            'esgc'        => array_merge($area('esgc'), ['status' => filled($f['esgc_category'] ?? null)
                ? ['ESGC risk: '.DueDiligence::RATINGS[$f['esgc_category']], ['low' => 'pill-ok', 'medium' => 'pill-warn', 'high' => 'pill-danger'][$f['esgc_category']] ?? 'pill-muted']
                : $area('esgc')['status']]),
            'risk'        => $area('risk'),
            'calculation' => ['present' => true, 'status' => null, 'link' => null, 'lines' => [
                ['Private capital (validated)', $m($v['private'])],
                ['CAFI grant', $m($v['grant']).($expected ? ' — maximum under the grant rule ('.EnterpriseProfile::grantRatioLabel().') = '.$m($expected) : '')],
                ['Total transaction', $m($v['total'])],
            ]],
            'conditions'  => ['present' => true, 'status' => [$dd->conditions->where('status', 'open')->count().' open', $dd->conditions->where('status', 'open')->count() ? 'pill-warn' : 'pill-ok'], 'link' => null,
                'lines' => $dd->conditions->isEmpty()
                    ? [['Conditions', 'None recorded.']]
                    : $dd->conditions->map(fn ($c) => [
                        (\App\Models\DueDiligenceCondition::STATUSES[$c->status][0] ?? $c->status).' · '.(\App\Models\DueDiligenceCondition::TREATMENTS[$c->treatment][0] ?? $c->treatment),
                        $c->finding ?: '—',
                    ])->values()->all()],
        ];
    }

    /** The Committee secretary (records the Committee's decisions), or null if none has been set */
    #[Computed]
    public function secretary(): ?\App\Models\User
    {
        return \App\Models\User::committeeSecretary();
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

    /** The secretary is editing a member's scorecard */
    #[Computed]
    public function canEditMember(): bool
    {
        return $this->editingMemberId !== null && $this->canRecord;
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
        $this->decision['private_usd'] = $this->decision['private_amount'] !== null ? (string) round($this->decision['private_amount'] / $this->usdRate()) : '';
        $this->decision['grant_usd']   = $this->decision['grant_amount'] !== null ? (string) round($this->decision['grant_amount'] / $this->usdRate()) : '';
    }

    protected function loadCard(?int $userId = null): void
    {
        $c = $this->reviewId
            ? CommitteeScorecard::where('committee_review_id', $this->reviewId)->where('user_id', $userId ?? auth()->id())->first()
            : null;

        $this->card = [
            'coi'                => $c?->coi,
            'coi_details'        => (string) $c?->coi_details,
            'scores'             => collect(CommitteeScorecard::criteriaKeys())->mapWithKeys(fn ($k) => [$k => $c?->scoreFor($k)])->all(),
            'pack_comments'      => collect(CommitteeReview::PACK)->mapWithKeys(fn ($p, $k) => [$k => (string) ($c?->pack_comments[$k] ?? '')])->all(),
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
            if ($this->canEditMember || ($this->editingMemberId === null && $this->canScore)) {
                $this->saveCard();
            }
            return;
        }

        // Decision amounts are typed in US$ and stored in Maloti (read by Gates 4–7)
        if (in_array($name, ['decision.private_usd', 'decision.grant_usd'], true) && $this->canRecord) {
            $col = $name === 'decision.private_usd' ? 'private_amount' : 'grant_amount';
            $usd = $this->decision[$name === 'decision.private_usd' ? 'private_usd' : 'grant_usd'] ?? '';
            $this->decision[$col] = is_numeric($usd) ? round((float) $usd * $this->usdRate(), 2) : null;
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

    /** Maloti per US dollar */
    protected function usdRate(): float
    {
        return max((float) config('cif.usd_to_lsl', 18.00), 0.0001);
    }

    /* =====================================================================
     | Scorecard (gate3.score)
     ===================================================================== */

    protected function saveCard(bool $submit = false): CommitteeScorecard
    {
        $review   = $this->ensureReview();
        $onBehalf = $this->canEditMember;                       // the secretary correcting a member's scorecard
        $userId   = $onBehalf ? $this->editingMemberId : auth()->id();

        $card = CommitteeScorecard::firstOrNew(['committee_review_id' => $review->id, 'user_id' => $userId]);
        $card->fill([
            'coi'                => $this->card['coi'] ?: null,
            'coi_details'        => trim($this->card['coi_details']) ?: null,
            'scores'             => $scores = collect($this->card['scores'])->filter(fn ($v) => is_numeric($v) && isset(CommitteeScorecard::SCALE[(int) $v]))->map(fn ($v) => (int) $v)->all(),
            'answers'            => collect($scores)->map(fn ($v) => CommitteeScorecard::answerFor($v))->all(),
            'pack_comments'      => collect($this->card['pack_comments'])->map(fn ($t) => trim((string) $t))->filter()->map(fn ($t) => Str::limit($t, 2000, ''))->all(),
            'innovation'         => array_keys(array_filter($this->card['innovation'])),
            'comments'           => array_filter($this->card['comments'], 'filled'),
            'use_of_funds_notes' => trim($this->card['use_of_funds_notes']) ?: null,
            'issues'             => trim($this->card['issues']) ?: null,
            'recommendation'     => $this->card['recommendation'] ?: null,
            'takeaways'          => trim($this->card['takeaways']) ?: null,
            // The secretary's corrections keep the member's submitted state; the member's own save sets it
            'submitted_at'       => $onBehalf ? $card->submitted_at : ($submit ? now() : null),
        ]);
        if ($onBehalf) {
            $card->edited_by = auth()->id();
            $card->edited_at = now();
        }
        $card->save();

        $this->savedAt = now()->format('H:i');
        unset($this->review, $this->myCard, $this->status, $this->revealed, $this->blockers);

        return $card;
    }

    public function submitScorecard(): void
    {
        abort_unless($this->canScore, 403, 'You cannot submit a scorecard for this transaction.');

        if ($this->editingMemberId !== null) {   // finish editing a member's scorecard first
            $this->cancelMemberEdit();
            return;
        }

        $recused = $this->card['coi'] === 'recused';

        $rules = [
            'card.coi'         => ['required', Rule::in(array_keys(CommitteeScorecard::COI))],
            'card.coi_details' => [in_array($this->card['coi'], ['declared', 'recused'], true) ? 'required' : 'nullable', 'string', 'max:2000'],
        ];
        if (! $recused) {
            foreach (CommitteeScorecard::criteriaKeys() as $k) {
                $rules["card.scores.$k"] = ['required', 'integer', Rule::in(array_keys(CommitteeScorecard::SCALE))];
            }
            if (CommitteeScorecard::PACK_COMMENTS_REQUIRED && $this->dd) {
                foreach (array_keys(CommitteeReview::PACK) as $k) {
                    $rules["card.pack_comments.$k"] = ['required', 'string', 'max:2000'];
                }
            }
            $rules['card.recommendation'] = ['required', Rule::in(array_keys(CommitteeScorecard::RECOMMENDATIONS))];
            $rules['card.takeaways']      = ['required', 'string', 'max:3000'];
        }

        try {
            $this->validate($rules, [
            'card.coi.required'            => 'Declare whether you have a conflict of interest.',
            'card.coi_details.required'    => 'Describe the conflict of interest.',
            'card.scores.*.required'        => 'Give this criterion a score from 1 to 5.',
            'card.pack_comments.*.required' => 'Add your comment on this item of the information pack.',
            'card.recommendation.required' => 'Choose Approve or Reject.',
            'card.takeaways.required'      => 'Add your key take-aways and rationale.',
            ]);
        } catch (ValidationException $e) {
            // Say what is missing — some fields (the pack comments) are further up the page
            $this->dispatch('notify', type: 'error', message: 'Your scorecard is not complete: '.$this->missingSummary(array_keys($e->errors())).'.');
            $this->dispatch('scorecard-invalid');
            throw $e;
        }

        if (! $recused && (int) ($this->card['scores']['innovative'] ?? 0) >= CommitteeScorecard::MET_FROM && ! array_filter($this->card['innovation'])) {
            $this->addError('card.innovation', 'Tick at least one type of innovation.');
            $this->dispatch('notify', type: 'error', message: 'Your scorecard is not complete: tick at least one type of innovation.');
            $this->dispatch('scorecard-invalid');
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
     | Secretary: correct or reopen a member's scorecard (gate3.decide)
     ===================================================================== */

    /** Open a member's scorecard for the secretary to correct */
    public function editMemberCard(int $userId): void
    {
        abort_unless($this->canRecord && $this->members->contains('id', $userId), 403);

        $this->resetValidation();
        $this->editingMemberId = $userId;
        $this->loadCard($userId);
        unset($this->canEditMember);
        $this->dispatch('scroll-to', target: '#g3-scorecard');
    }

    /** Stop editing the member's scorecard (changes are already saved) and go back to my own */
    public function cancelMemberEdit(): void
    {
        $this->editingMemberId = null;
        $this->resetValidation();
        $this->loadCard();
        unset($this->canEditMember, $this->review, $this->status, $this->revealed, $this->blockers);
    }

    public function finishMemberEdit(): void
    {
        $name = $this->members->firstWhere('id', $this->editingMemberId)?->name ?? 'the member';
        $this->cancelMemberEdit();
        $this->dispatch('notify', type: 'success', message: "Changes to {$name}'s scorecard are saved.");
    }

    /** Send a submitted scorecard back to the member so they can change and resubmit it */
    public function reopenMemberCard(int $userId): void
    {
        abort_unless($this->canRecord && $this->members->contains('id', $userId), 403);

        $card = CommitteeScorecard::where('committee_review_id', $this->reviewId)->where('user_id', $userId)->first();
        if (! $card?->isSubmitted()) {
            $this->dispatch('notify', type: 'warning', message: 'That scorecard has not been submitted, so the member can already edit it.');
            return;
        }

        $card->update(['submitted_at' => null, 'reopened_by' => auth()->id(), 'reopened_at' => now()]);

        if ($userId === auth()->id() || $this->editingMemberId === $userId) {
            $this->editingMemberId = null;
            $this->loadCard();
        }
        unset($this->review, $this->myCard, $this->status, $this->revealed, $this->blockers, $this->canScore, $this->canEditMember);

        $name = $this->members->firstWhere('id', $userId)?->name ?? 'The member';
        $this->dispatch('notify', type: 'success', message: "{$name}'s scorecard is open again — they can change it and submit it again.");
    }

    /** "3 information pack comments, 2 scores, your recommendation" — from the failed field names */
    public function missingSummary(array $fields): string
    {
        $count = fn (string $prefix) => count(array_filter($fields, fn ($f) => str_starts_with($f, $prefix)));
        $plural = fn (int $n, string $one, string $many) => $n.' '.($n === 1 ? $one : $many);

        return collect([
            in_array('card.coi', $fields, true) ? 'the conflict-of-interest declaration' : null,
            in_array('card.coi_details', $fields, true) ? 'details of the conflict of interest' : null,
            ($n = $count('card.pack_comments.')) ? $plural($n, 'information pack comment (see the pack above)', 'information pack comments (see the pack above)') : null,
            ($n = $count('card.scores.')) ? $plural($n, 'score', 'scores') : null,
            in_array('card.recommendation', $fields, true) ? 'your overall recommendation' : null,
            in_array('card.takeaways', $fields, true) ? 'your key take-aways' : null,
        ])->filter()->implode(', ') ?: 'please check the highlighted fields';
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
            'decision.private_usd'    => ['required', 'numeric', 'min:0'],
            'decision.grant_usd'      => [in_array($this->decision['outcome'], ['approve', 'conditional'], true) ? 'required' : 'nullable', 'numeric', 'min:0'],
            'decision.rationale'      => ['required', 'string', 'max:5000'],
            'decision.actions'        => [$this->decision['outcome'] === 'defer' ? 'required' : 'nullable', 'string', 'max:3000'],
            'meeting.meeting_date'    => ['required', 'date', 'before_or_equal:today'],
        ], [
            'decision.outcome.required'      => 'Choose the Committee outcome.',
            'decision.private_usd.required'  => 'Enter the private investment in US$.',
            'decision.grant_usd.required'    => 'Enter the recommended CAFI grant in US$.',
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
        // Approved → Grants Management starts the Gate 4 grant readiness review
        $staff = NotifyRecipients::pmu()->merge(NotifyRecipients::committee())
            ->when($review->isApproved(), fn ($c) => $c->merge(NotifyRecipients::withPermission('readiness.review')))
            ->unique('id')->values();
        $notes[] = NotifyRecipients::send($staff, new EoiCommitteeDecisionRecorded($eoi, $review),
            $review->isApproved() ? 'the PMU, Committee and Grants Management' : 'the PMU and Committee', except: auth()->id());

        return implode(' ', array_filter($notes));
    }

    /* =====================================================================
     | Internals
     ===================================================================== */

    /**
     * The Committee record for this round, created on first use.
     * Another user (a member scoring, or the secretary) may have created it after this page was opened,
     * so look it up first, and if two saves race, use the row the other one created
     * (expression_of_interest_id + round is unique).
     */
    protected function ensureReview(): CommitteeReview
    {
        if ($this->reviewId && ($review = CommitteeReview::find($this->reviewId))) {
            return $review;
        }

        $existing = fn () => CommitteeReview::where('expression_of_interest_id', $this->eoiId)->where('round', $this->round)->first();

        $review = $existing();
        if (! $review) {
            try {
                $review = CommitteeReview::create(array_merge(CommitteeReview::prefill($this->eoi, $this->dd), $this->recordData(), [
                    'expression_of_interest_id' => $this->eoiId,
                    'due_diligence_id'          => $this->dd?->id,
                    'round'                     => $this->round,
                    'secretary_id'              => $this->secretary?->id ?? (auth()->user()->can('gate3.decide') ? auth()->id() : null),
                ]));
            } catch (\Illuminate\Database\QueryException $e) {
                // 23000 = duplicate key: created by someone else a moment ago
                if (($e->errorInfo[0] ?? null) !== '23000' || ! ($review = $existing())) {
                    throw $e;
                }
            }
        }

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

    // Secretary correcting a member's scorecard: the scorecard and pack comments show that member's card
    $editingOther = $this->canEditMember;
    $cardOwner    = $editingOther ? $members->firstWhere('id', $this->editingMemberId) : auth()->user();
    if ($editingOther) {
        $myCard   = $cards->get($this->editingMemberId);
        $canScore = true;
    }
    $blockers  = $this->isApplicant ? [] : $this->blockers;
    $history   = $this->isApplicant ? collect() : $this->history;
    $submission = $this->isApplicant || ! $this->gateOpen ? ['facts' => [], 'docs' => []] : $this->submission;
    $profile    = $this->isApplicant ? null : $this->profile;

    // Figures from Gate 2
    $ddForm    = $dd?->toForm() ?? [];
    $validated = $dd ? $DD::validated($ddForm) : ['total' => 0, 'private' => 0, 'grant' => 0, 'structure' => null];

    $rate = (float) config('cif.usd_to_lsl', 18.00);
    // Amounts are stored in Maloti: $m = US$ (main) · $u = Maloti equivalent
    $m    = fn ($v) => $v === null || $v === '' ? '—' : 'US$ '.number_format((float) $v / max($rate, 0.0001));
    $u    = fn ($v) => $v === null || $v === '' ? '' : '≈ M '.number_format((float) $v);

    // Gate 3 path
    $path = [
        ['Gate 2 passed', 'done'],
        ['TSS + Investment Appraisal Memorandum', $dd ? 'done' : 'todo'],
        ['Investment Committee review', $decided ? 'done' : 'current'],
        ['Approve / Conditional / Defer / Reject', $decided ? 'done' : 'todo'],
        ['Gate 4 · Grant readiness', $review?->isApproved() ? 'current' : 'todo'],
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

        @if (! $decided && ! $this->secretary)
            <div class="callout callout-warning small mb-3">
                <i class="bi bi-exclamation-triangle me-1"></i>
                No <strong>Committee secretary</strong> has been set, so nobody can record the Committee's decisions.
                In user management, open one of the Committee members and tick <em>Committee secretary</em>.
            </div>
        @elseif (! $decided && $isMember && ! $canRecord)
            <div class="callout small mb-3">
                <i class="bi bi-journal-check me-1"></i>
                <strong>{{ $this->secretary->name }}</strong> (Committee secretary) records the Committee's decisions —
                review criteria, deliberation, questions, conditions and the outcome. Your part is your conflict declaration, pack comments and scorecard.
            </div>
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
                <dd class="mb-0 text-navy">
                    {{ $decided ? ($review->secretary?->name ?? $this->secretary?->name ?? '—') : ($this->secretary?->name ?? 'Not set') }}
                    @if (! $decided && $this->secretary?->id === auth()->id()) <span class="pill pill-muted ms-1">You</span> @endif
                </dd>
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
    @if ($editingOther || ($isMember && ($review === null || ! $decided || $myCard)))
        @include('expression-of-interest.committee.partials.scorecard')
    @endif
    @include('expression-of-interest.committee.partials.consolidated')
    @include('expression-of-interest.committee.partials.record')
    @include('expression-of-interest.committee.partials.outcome')
@endif
</div>