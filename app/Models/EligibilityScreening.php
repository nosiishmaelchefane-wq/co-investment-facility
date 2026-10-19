<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Gate 1 · Administrative review & eligibility screening (CI Facility Operations Manual §3.2).
 *
 * One row per round. Each round has three stages:
 *   1. completeness — §3.2.1 administrative completeness review (7 checks, auto-checked by the platform)
 *   2. screening    — §3.2.2 eligibility screening (8 CAFI checklist criteria, grouped into the 6 screening areas)
 *   3. decided      — §3.2.3 Gate 1 decision: Pass · Clarification required · Not eligible
 *                     (or "incomplete" if returned at the completeness checkpoint)
 *
 * A returned EOI (incomplete or clarification) that is resubmitted starts a new round.
 */
class EligibilityScreening extends Model
{
    public const ANSWER_YES     = 'yes';
    public const ANSWER_NO      = 'no';
    public const ANSWER_CLARIFY = 'clarify';

    public const STAGE_COMPLETENESS = 'completeness';
    public const STAGE_SCREENING    = 'screening';
    public const STAGE_DECIDED      = 'decided';

    /** Gate 1 decisions (key 'eligible' is shown as "Pass") */
    public const DECISION_ELIGIBLE      = 'eligible';
    public const DECISION_CLARIFICATION = 'clarification';
    public const DECISION_NOT_ELIGIBLE  = 'not_eligible';

    /** Returned at the completeness checkpoint — before screening */
    public const DECISION_INCOMPLETE    = 'incomplete';

    /** §3.2.1 — check => [title, illustrative requirement] */
    public const COMPLETENESS = [
        'application'  => ['Application information',  'Mandatory enterprise and transaction fields completed'],
        'documents'    => ['Supporting documentation', 'Required enterprise, investor and transaction documents uploaded'],
        'investor'     => ['Investor information',     'Investor identity and participation information provided'],
        'transaction'  => ['Transaction information',  'Proposed investment and transaction details provided'],
        'use_of_funds' => ['Use of funds',             'Intended use of private investment and CI grant defined'],
        'declarations' => ['Declarations',             'Required applicant declarations completed'],
        'submission'   => ['Submission',               'Application formally submitted by an authorised enterprise representative'],
    ];

    /** §3.2.2 — screening area => [title, key question, icon] */
    public const AREAS = [
        'enterprise'  => ['Enterprise eligibility',  'Does the enterprise meet the applicable enterprise, operational and sector requirements?',                        'bi-building'],
        'investor'    => ['Investor participation',  'Is there a demonstrable and eligible private investor participating in the transaction?',                         'bi-briefcase'],
        'transaction' => ['Transaction eligibility', 'Does the proposed investment constitute an eligible private investment transaction?',                              'bi-file-earmark-ruled'],
        'funding'     => ['Funding parameters',      "Is the proposed investment and associated CI request within the Facility's applicable financing and matching parameters?", 'bi-cash-coin'],
        'alignment'   => ['Programme alignment',     'Does the transaction contribute to the objectives of the Facility?',                                               'bi-bullseye'],
        'exclusions'  => ['Exclusions',              'Are there any identified activities or circumstances that prevent the transaction from progressing?',              'bi-slash-circle'],
    ];

    /** key => [title, description, screening area] — the 8 criteria from the CAFI checklist, grouped by §3.2.2 area */
    public const CRITERIA = [
        'enterprise'   => ['Eligible enterprise',   "The enterprise meets the Facility's requirements regarding registration, operations, enterprise type and sector.",            'enterprise'],
        'investor'     => ['Eligible investor',     "A private investor participating in the transaction meets the Facility's investor requirements.",                              'investor'],
        'commitment'   => ['Investor commitment',   'There is demonstrable investor commitment to the proposed transaction.',                                                      'investor'],
        'transaction'  => ['Eligible transaction',  'The proposed investment and instrument are eligible under the Facility.',                                                     'transaction'],
        'use_of_funds' => ['Eligible use of funds', 'The proposed use of the private investment and CI grant is consistent with programme requirements.',                          'transaction'],
        'funding'      => ['Funding parameters',    'The proposed private investment and CI grant request fall within the applicable Facility limits and matching parameters.',   'funding'],
        'alignment'    => ['Programme alignment',   'The transaction is consistent with the objectives and requirements of the CI Facility.',                                      'alignment'],
        'exclusions'   => ['Exclusions',            'No programme exclusion or other fundamental eligibility issue has been identified.',                                         'exclusions'],
    ];

    /** Criteria in a screening area */
    public static function criteriaFor(string $area): array
    {
        return array_filter(self::CRITERIA, fn ($c) => $c[2] === $area);
    }

    /** §3.2.3 — decision => [label, treatment, icon, pill class] */
    public const DECISIONS = [
        self::DECISION_ELIGIBLE      => ['Pass',                   'Application progresses to Due Diligence and Investment Appraisal.',          'bi-check2-circle',     'pill-ok'],
        self::DECISION_CLARIFICATION => ['Clarification required', 'Returned through the platform with specific information requirements.',       'bi-arrow-return-left', 'pill-warn'],
        self::DECISION_NOT_ELIGIBLE  => ['Not eligible',           'Does not progress. The applicant is notified of the basis for the decision.', 'bi-x-octagon',         'pill-danger'],
    ];

    /** Every recorded outcome, including the completeness return */
    public const OUTCOMES = self::DECISIONS + [
        self::DECISION_INCOMPLETE => ['Returned — incomplete', 'Returned at the completeness review with the items requiring attention.', 'bi-arrow-return-left', 'pill-warn'],
    ];

    /** EOI status each outcome moves the application to */
    public const DECISION_TO_EOI_STATUS = [
        self::DECISION_ELIGIBLE      => ExpressionOfInterest::STATUS_INVITED,
        self::DECISION_CLARIFICATION => ExpressionOfInterest::STATUS_RETURNED,
        self::DECISION_NOT_ELIGIBLE  => ExpressionOfInterest::STATUS_NOT_PROGRESSED,
        self::DECISION_INCOMPLETE    => ExpressionOfInterest::STATUS_RETURNED,
    ];

    protected $fillable = [
        'expression_of_interest_id', 'round', 'screened_by',
        'completeness', 'completeness_confirmed_at', 'completeness_confirmed_by',
        'answers', 'decision', 'comments', 'screened_on', 'decided_at',
    ];

    protected $casts = [
        'completeness'              => 'array',
        'answers'                   => 'array',
        'completeness_confirmed_at' => 'datetime',
        'screened_on'               => 'date',
        'decided_at'                => 'datetime',
    ];

    /* ---------------- Relations ---------------- */

    public function expressionOfInterest(): BelongsTo
    {
        return $this->belongsTo(ExpressionOfInterest::class);
    }

    public function screener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'screened_by');
    }

    public function completenessConfirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completeness_confirmed_by');
    }

    /* ---------------- State ---------------- */

    public function isDecided(): bool
    {
        return $this->decided_at !== null;
    }

    public function stage(): string
    {
        return match (true) {
            $this->isDecided()                        => self::STAGE_DECIDED,
            $this->completeness_confirmed_at !== null => self::STAGE_SCREENING,
            default                                   => self::STAGE_COMPLETENESS,
        };
    }

    /** Returned to the applicant (completeness or clarification) — resubmission opens a new round */
    public function isReturned(): bool
    {
        return $this->isDecided() && in_array($this->decision, [self::DECISION_INCOMPLETE, self::DECISION_CLARIFICATION], true);
    }

    /* ---------------- Tallies ---------------- */

    /** ['yes' => n, 'no' => n, 'clarify' => n, 'unanswered' => n] over the given keys (default: screening areas) */
    public static function tally(array $answers, ?array $keys = null): array
    {
        $values = collect($keys ?? array_keys(self::CRITERIA))->map(fn ($k) => $answers[$k]['answer'] ?? null);

        return [
            self::ANSWER_YES     => $values->filter(fn ($v) => $v === self::ANSWER_YES)->count(),
            self::ANSWER_NO      => $values->filter(fn ($v) => $v === self::ANSWER_NO)->count(),
            self::ANSWER_CLARIFY => $values->filter(fn ($v) => $v === self::ANSWER_CLARIFY)->count(),
            'unanswered'         => $values->filter(fn ($v) => ! in_array($v, [self::ANSWER_YES, self::ANSWER_NO, self::ANSWER_CLARIFY], true))->count(),
        ];
    }

    /** Decision the screening answers point to (null until every area is answered). */
    public static function recommend(array $answers): ?string
    {
        $t = self::tally($answers);

        return match (true) {
            $t['unanswered'] > 0          => null,
            $t[self::ANSWER_NO] > 0       => self::DECISION_NOT_ELIGIBLE,
            $t[self::ANSWER_CLARIFY] > 0  => self::DECISION_CLARIFICATION,
            default                       => self::DECISION_ELIGIBLE,
        };
    }

    /* ---------------- §3.2.1 Automated completeness check ---------------- */

    /**
     * What the platform can check automatically.
     * check => ['met' => bool, 'missing' => [string, ...]]
     */
    public static function autoCompleteness(ExpressionOfInterest $eoi, ?EnterpriseProfile $p): array
    {
        $sections  = $p?->sectionStatus() ?? [];
        $noProfile = $p ? [] : ['Enterprise profile has not been started'];

        $eoiMissing = fn (array $fields) => collect($fields)
            ->filter(fn ($label, $field) => blank($eoi->{$field}))
            ->map(fn ($label) => "EOI: {$label} not provided")
            ->values()->all();

        $sectionMissing = fn (array $keys) => $p
            ? collect($keys)->reject(fn ($k) => $sections[$k] ?? false)
                ->map(fn ($k) => 'Enterprise profile: '.EnterpriseProfile::SECTIONS[$k][0].' incomplete')
                ->values()->all()
            : [];

        $docMissing = fn () => $p
            ? collect($p->missingDocuments())->map(fn ($t) => (EnterpriseDocument::TYPES[$t][0] ?? $t).' not uploaded')->all()
            : [];
        $profileBlank = fn (array $fields) => $p
            ? collect($fields)->filter(fn ($label, $f) => blank($p->{$f}))->map(fn ($label) => "Enterprise profile: {$label} not provided")->values()->all()
            : [];

        $checks = [
            'application' => [
                ...$eoiMissing([
                    'business_name' => 'business name', 'representative_name' => 'authorised representative', 'phone' => 'telephone',
                    'email' => 'email', 'district' => 'district', 'legal_status' => 'legal status',
                    'business_description' => 'business description', 'annual_turnover' => 'annual turnover',
                    'full_time_employees' => 'number of employees',
                ]),
                ...$noProfile,
                ...$sectionMissing(['company', 'legal', 'people', 'business']),
            ],
            'documents' => [
                ...$noProfile,
                ...$docMissing(),
            ],
            'investor' => [
                ...$eoiMissing(['investor_name' => 'investor name', 'investor_status' => 'investor engagement status']),
                ...($eoi->investor_status === 'none' ? ['EOI: no participating investor identified'] : []),
                ...($eoi->investor_status !== 'none' && $eoi->investor_amount_usd !== null && (float) $eoi->investor_amount_usd < EnterpriseProfile::minInvestorUsd()
                    ? ['EOI: investor amount (US$ '.number_format((float) $eoi->investor_amount_usd).') is below the US$ '.number_format(EnterpriseProfile::minInvestorUsd()).' minimum']
                    : []),
                ...$noProfile,
                ...$profileBlank(['investor_name' => 'participating investors', 'participation' => 'financing type']),
            ],
            'transaction' => [
                ...$eoiMissing(['investment_required_usd' => 'total investment required', 'investment_types' => 'type of investment']),
                ...$noProfile,
                ...$profileBlank(['round_amount' => 'current investment round', 'participation' => 'financing type']),
            ],
            'use_of_funds' => [
                ...$eoiMissing(['use_of_funds' => 'use of investment', 'use_of_funds_description' => 'planned use of the investment']),
                ...$noProfile,
                ...($p && ! $p->document('budget') ? ['Enterprise profile: operational budget (incl. use of the grant) not uploaded'] : []),
            ],
            'declarations' => [
                ...(! $eoi->declaration_agree ? ['EOI: declaration not accepted'] : []),
                ...$eoiMissing(['declaration_name' => 'name of person making the declaration', 'declaration_date' => 'declaration date']),
                ...$noProfile,
                ...($p && ! $p->declaration_agreed ? ['Enterprise profile: declaration not signed'] : []),
            ],
            'submission' => [
                ...($eoi->submitted_at ? [] : ['EOI has not been formally submitted']),
                ...($eoi->user && ! $eoi->user->hasRole('enterprise') ? ['Submitted from an account that is not an enterprise account'] : []),
                ...($eoi->user && $eoi->user->status !== User::STATUS_ACTIVE ? ['Submitting account is not active'] : []),
            ],
        ];

        return collect($checks)->map(fn ($missing) => [
            'met'     => empty($missing),
            'missing' => array_values(array_unique($missing)),
        ])->all();
    }

    /* ---------------- What the applicant must act on ---------------- */

    /** [['title' => ..., 'note' => ...]] — completeness items (incomplete) or screening areas (clarification) */
    public function outstandingItems(): array
    {
        [$defs, $source, $match] = match ($this->decision) {
            self::DECISION_INCOMPLETE    => [self::COMPLETENESS, $this->completeness ?? [], self::ANSWER_NO],
            self::DECISION_CLARIFICATION => [self::CRITERIA,     $this->answers ?? [],      self::ANSWER_CLARIFY],
            default                      => [[], [], null],
        };

        return collect($defs)
            ->filter(fn ($d, $k) => ($source[$k]['answer'] ?? null) === $match)
            ->map(fn ($d, $k) => ['title' => $d[0], 'note' => $source[$k]['note'] ?? ''])
            ->values()->all();
    }

    /** Screening areas answered "No" — the basis for a Not eligible decision */
    public function failedItems(): array
    {
        return collect(self::CRITERIA)
            ->filter(fn ($d, $k) => ($this->answers[$k]['answer'] ?? null) === self::ANSWER_NO)
            ->map(fn ($d, $k) => ['title' => $d[0], 'note' => $this->answers[$k]['note'] ?? ''])
            ->values()->all();
    }

    /** @deprecated use outstandingItems() */
    public function clarificationItems(): array
    {
        return $this->outstandingItems();
    }
}