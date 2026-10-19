<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Gate 3 · Investment Committee Review & Recommendation (CI Facility Operations Manual §3.4).
 * One row per Committee round. Amounts in Maloti (LSL).
 *
 * Members = active users with the "committee" role (the Facility has 3 fixed members).
 * Individual scorecards stay hidden until every (non-recused) member has submitted.
 */
class CommitteeReview extends Model
{
    public const OUTCOME_APPROVE     = 'approve';
    public const OUTCOME_CONDITIONAL = 'conditional';
    public const OUTCOME_DEFER       = 'defer';
    public const OUTCOME_REJECT      = 'reject';

    /** §3.4.4 — outcome => [label, meaning, next step, icon, pill] */
    public const OUTCOMES = [
        self::OUTCOME_APPROVE     => ['Approve',              'Sufficient basis to recommend the transaction.',         'Proceed to Gate 4',                            'bi-check2-circle', 'pill-ok'],
        self::OUTCOME_CONDITIONAL => ['Conditional approval', 'Supported, subject to defined conditions.',               'Address conditions, then Gate 4',              'bi-check2-square', 'pill-ok'],
        self::OUTCOME_DEFER       => ['Defer',                'Additional information or assessment is required.',     'Return for specified action and resubmission', 'bi-pause-circle',  'pill-warn'],
        self::OUTCOME_REJECT      => ['Reject',               'The transaction should not proceed under the Facility.', 'Close the transaction',                        'bi-x-octagon',     'pill-danger'],
    ];

    public const OUTCOME_TO_EOI_STATUS = [
        self::OUTCOME_APPROVE     => ExpressionOfInterest::STATUS_AUTHORISATION,
        self::OUTCOME_CONDITIONAL => ExpressionOfInterest::STATUS_AUTHORISATION,
        self::OUTCOME_DEFER       => ExpressionOfInterest::STATUS_IC_DEFERRED,
        self::OUTCOME_REJECT      => ExpressionOfInterest::STATUS_NOT_PROGRESSED,
    ];

    /** §3.4.2 — area => [title, key consideration] */
    public const CRITERIA = [
        'commercial'  => ['Commercial viability',    'Is the enterprise commercially credible and is the proposed transaction coherent?'],
        'transaction' => ['Transaction credibility', 'Is the investor commitment sufficiently developed and evidenced?'],
        'impact'      => ['Development impact',      'What credible outcomes are expected — jobs, revenue, exports or innovation?'],
        'risk'        => ['Risk profile',            'Are material commercial, legal, operational and financial risks understood and manageable?'],
        'esgc'        => ['ESGC',                    'Are identified ESGC risks acceptable and appropriately mitigated?'],
        'compliance'  => ['Programme compliance',    "Does the transaction meet the Facility's eligibility, matching and use-of-funds requirements?"],
        'catalytic'   => ['Catalytic value',         'Does the CAFI grant appropriately complement and mobilise private capital?'],
    ];

    /** view => [label, select class] */
    public const VIEWS = [
        'satisfied'     => ['Satisfied',     'is-complete'],
        'concern'       => ['Concern',       'is-clarification'],
        'not_satisfied' => ['Not satisfied', 'is-no'],
    ];

    /** §3.4.3 — deliberation sequence */
    public const STEPS = [
        'presentation' => 'Transaction presentation',
        'questions'    => 'Committee questions',
        'risks'        => 'Review of material risks and conditions',
        'fit'          => 'Programme fit and catalytic value',
        'recommendation' => 'Formal recommendation',
    ];

    /** Committee's treatment of each condition */
    public const CONDITION_DECISIONS = [
        'accepted' => 'Accepted',
        'amended'  => 'Amended',
        'added'    => 'Added',
        'removed'  => 'Removed',
    ];

    /** §3.4.1 — Committee information pack: key => [document, purpose, icon, G2 anchor] */
    public const PACK = [
        'tss'         => ['Transaction Summary Sheet',          'Concise overview of the transaction',            'bi-file-earmark-text',  'g2-summary'],
        'iam'         => ['Investment Appraisal Memorandum',    'Consolidated assessment and recommendation',     'bi-file-earmark-pdf',   null],
        'enterprise'  => ['Enterprise DD findings',             'Key findings on enterprise capacity and risks',  'bi-building',           'g2-area-enterprise'],
        'investor'    => ['Investor DD findings',               'Investor identity, capacity and commitment',     'bi-briefcase',          'g2-area-investor'],
        'transaction' => ['Transaction documentation',          'Evidence of transaction structure and maturity', 'bi-file-earmark-ruled', 'g2-area-transaction'],
        'financial'   => ['Financial and commercial assessment', 'Key commercial and financial findings',         'bi-graph-up',           'g2-area-financial'],
        'esgc'        => ['ESGC assessment',                    'Key ESGC risks and mitigation measures',         'bi-tree',               'g2-area-esgc'],
        'risk'        => ['Risk assessment',                    'Material risks and proposed responses',          'bi-shield-exclamation', 'g2-area-risk'],
        'calculation' => ['Co-investment calculation',          'Private capital and proposed CAFI grant',        'bi-calculator',         'g2-figures'],
        'conditions'  => ['Conditions / outstanding matters',   'Issues requiring resolution before or after approval', 'bi-list-check',  'g2-conditions'],
    ];

    protected $fillable = [
        'expression_of_interest_id', 'due_diligence_id', 'round',
        'meeting_ref', 'meeting_date', 'venue', 'pack_circulated_on', 'secretary_id', 'resubmission_note',
        'criteria', 'deliberation', 'questions', 'conditions',
        'outcome', 'private_amount', 'grant_amount', 'rationale', 'actions', 'decided_at', 'decided_by',
    ];

    protected $casts = [
        'criteria'           => 'array',
        'deliberation'       => 'array',
        'questions'          => 'array',
        'conditions'         => 'array',
        'meeting_date'       => 'date',
        'pack_circulated_on' => 'date',
        'decided_at'         => 'datetime',
        'private_amount'     => 'decimal:2',
        'grant_amount'       => 'decimal:2',
    ];

    /* ---------------- Relations ---------------- */

    public function expressionOfInterest(): BelongsTo
    {
        return $this->belongsTo(ExpressionOfInterest::class);
    }

    public function dueDiligence(): BelongsTo
    {
        return $this->belongsTo(DueDiligence::class);
    }

    public function scorecards(): HasMany
    {
        return $this->hasMany(CommitteeScorecard::class);
    }

    public function secretary(): BelongsTo
    {
        return $this->belongsTo(User::class, 'secretary_id');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /* ---------------- Members ---------------- */

    /** Committee members: active users with the "committee" role */
    public static function members(): Collection
    {
        return User::role('committee')
            ->where('status', User::STATUS_ACTIVE)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
    }

    public static function expectedSize(): int
    {
        return (int) config('cif.committee.size', 3);
    }

    /* ---------------- State ---------------- */

    public function isDecided(): bool
    {
        return $this->decided_at !== null;
    }

    public function isApproved(): bool
    {
        return $this->isDecided() && in_array($this->outcome, [self::OUTCOME_APPROVE, self::OUTCOME_CONDITIONAL], true);
    }

    /**
     * Scoring status for the current members.
     * ['members' => n, 'scoring' => n (not recused), 'submitted' => n, 'complete' => bool]
     */
    public function scoringStatus(Collection $members): array
    {
        $cards     = $this->scorecards->keyBy('user_id');
        $scoring   = $members->reject(fn ($m) => $cards->get($m->id)?->isRecused());
        $submitted = $scoring->filter(fn ($m) => $cards->get($m->id)?->isSubmitted());

        return [
            'members'   => $members->count(),
            'scoring'   => $scoring->count(),
            'submitted' => $submitted->count(),
            'complete'  => $scoring->count() > 0 && $submitted->count() === $scoring->count(),
        ];
    }

    /**
     * Consolidated view (only meaningful once complete).
     * ['criteria' => [key => ['met' => n, 'not_met' => n, 'majority' => met|not_met|tie]], 'recommendation' => [...]]
     */
    public function consolidated(): array
    {
        $cards = $this->scorecards->filter(fn ($c) => $c->isSubmitted() && ! $c->isRecused());

        $majority = fn (int $yes, int $no) => $yes > $no ? 'met' : ($no > $yes ? 'not_met' : 'tie');

        $criteria = collect(CommitteeScorecard::criteriaKeys())->mapWithKeys(function ($k) use ($cards, $majority) {
            $met = $cards->filter(fn ($c) => ($c->answers[$k] ?? null) === 'met')->count();
            $not = $cards->filter(fn ($c) => ($c->answers[$k] ?? null) === 'not_met')->count();

            return [$k => ['met' => $met, 'not_met' => $not, 'majority' => $majority($met, $not)]];
        })->all();

        $approve = $cards->where('recommendation', 'approve')->count();
        $reject  = $cards->where('recommendation', 'reject')->count();

        return [
            'criteria'       => $criteria,
            'recommendation' => [
                'approve'  => $approve,
                'reject'   => $reject,
                'majority' => $approve > $reject ? 'approve' : ($reject > $approve ? 'reject' : 'tie'),
            ],
        ];
    }

    /* ---------------- New round ---------------- */

    /** Defaults for a new Committee round, from the Gate 2 assessment */
    public static function prefill(ExpressionOfInterest $eoi, ?DueDiligence $dd): array
    {
        $form      = $dd?->toForm() ?? [];
        $validated = $dd ? DueDiligence::validated($form) : ['private' => null, 'grant' => null];

        $conditions = $dd
            ? $dd->conditions()->get()->map(fn ($c) => [
                'source'    => 'g2',
                'ref_id'    => $c->id,
                'finding'   => $c->finding,
                'treatment' => DueDiligenceCondition::TREATMENTS[$c->treatment][0] ?? $c->treatment,
                'owner'     => $c->owner,
                'status'    => $c->status,
                'decision'  => 'accepted',
            ])->values()->all()
            : [];

        return [
            'meeting_ref'        => '',
            'meeting_date'       => null,
            'venue'              => '',
            'pack_circulated_on' => now()->toDateString(),
            'criteria'           => [],
            'deliberation'       => [],
            'questions'          => [],
            'conditions'         => $conditions,
            'outcome'            => null,
            'private_amount'     => $validated['private'] ?: null,
            'grant_amount'       => $validated['grant'] ?: null,
            'rationale'          => '',
            'actions'            => '',
        ];
    }
}