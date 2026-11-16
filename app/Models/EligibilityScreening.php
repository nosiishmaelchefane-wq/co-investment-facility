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
        'investor'     => ['Investor information',     'Lead investor, investment amount and engagement status provided (profile 8.2)'],
        'transaction'  => ['Transaction information',  'Instrument, terms, expected close and transaction description provided (profile 8.3)'],
        'use_of_funds' => ['Use of funds',             'Grant-only budget and use-of-grant declarations completed (profile 8.4)'],
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
     *
     * Profile answers are checked with the profile's own rules (EnterpriseProfile::requiredFields / isAnswered /
     * missingDocuments), so Gate 1 asks for exactly what the profile form v2 asks for — including questions that
     * only apply in some cases (e.g. the investor and grant questions when the enterprise has an investor).
     * Each missing profile answer is grouped under the completeness check it belongs to:
     *   application  §1 Company & contact · §2 Legal status & markets · employees & key people · customers, revenue
     *   investor     8.2 Investor information        transaction  8.3 Transaction information + total project cost
     *   use_of_funds 8.4 Proposed use of the grant   declarations E&S confirmation + profile declaration
     *   documents    required uploads (Attachments and the uploads in each section)
     * Completeness is about missing information only. Answers that need judgement (e.g. a declared double financing)
     * are shown to the officer in the eligibility screening, not listed here as missing.
     */
    public const PROFILE_GROUPS = [
        'investor' => [
            'has_investor', 'lead_investor_name', 'lead_investor_contact_name', 'lead_investor_email', 'lead_investor_phone',
            'lead_investor_website', 'investor_type', 'lead_investor_country', 'investment_currency', 'lead_investor_amount',
            'transaction_status', 'investor_commitment',
        ],
        'transaction' => [
            'participation', 'tx_amount_agreed', 'tx_amount', 'tx_terms_agreed', 'valuation', 'valuation_by', 'valuation_method',
            'expected_close', 'tx_conditions_outstanding', 'tx_description', 'project_cost',
        ],
        'use_of_funds' => [
            'grant_plan', 'grant_outside_justification', 'grant_lesotho_70', 'double_financing', 'double_financing_details', 'grant_growth_support',
        ],
        'declarations' => ['es_confirmed', 'declaration_agreed', 'declared_name', 'declared_on'],
    ];

    public static function autoCompleteness(ExpressionOfInterest $eoi, ?EnterpriseProfile $p): array
    {
        $noProfile = $p ? [] : ['Enterprise profile has not been started'];

        $eoiMissing = fn (array $fields) => collect($fields)
            ->filter(fn ($label, $field) => is_array($eoi->{$field}) ? $eoi->{$field} === [] : blank($eoi->{$field}))
            ->map(fn ($label) => "EOI: {$label} not provided")
            ->values()->all();

        // Every required profile answer that is still empty: field => section
        $unanswered = $p
            ? collect(array_keys(EnterpriseProfile::SECTIONS))
                ->flatMap(fn ($section) => collect($p->missingFields($section))->mapWithKeys(fn ($f) => [$f => $section]))
                ->all()
            : [];
        $grouped  = collect(self::PROFILE_GROUPS)->flatten()->all();
        $label    = fn (string $f) => EnterpriseProfile::LABELS[$f] ?? ucfirst(str_replace('_', ' ', $f));
        $notAnswered = fn (array $fields) => collect($fields)->map(fn ($f) => 'Enterprise profile: '.$label($f).' not answered')->values()->all();
        $profileGroup = fn (string $group) => $notAnswered(array_values(array_intersect(array_keys($unanswered), self::PROFILE_GROUPS[$group])));
        $profileOther = $notAnswered(array_values(array_diff(array_keys($unanswered), $grouped)));

        $rate     = EnterpriseProfile::usdRate();
        $grantUsd = $p && $p->grant_requested !== null ? (float) $p->grant_requested / $rate : null;

        $checks = [
            'application' => [
                ...$eoiMissing([
                    'business_name' => 'business name', 'representative_name' => 'authorised representative', 'phone' => 'telephone',
                    'email' => 'email', 'district' => 'district', 'legal_status' => 'legal status',
                    'business_description' => 'business description', 'annual_turnover' => 'annual turnover',
                    'full_time_employees' => 'number of employees',
                ]),
                ...$noProfile,
                ...$profileOther,      // §1 company & contact, §2 legal, employees & key people, customers, revenue
            ],
            'documents' => [
                ...$noProfile,
                ...($p ? collect($p->missingDocuments())->map(fn ($t) => 'Enterprise profile: '.EnterpriseDocument::missingLabel($t).' not uploaded')->unique()->values()->all() : []),
            ],
            'investor' => [
                ...$eoiMissing(['investor_name' => 'investor name', 'investor_status' => 'investor engagement status']),
                ...($eoi->investor_status === 'none' ? ['EOI: no participating investor identified'] : []),
                ...($eoi->investor_status !== 'none' && $eoi->investor_amount_usd !== null && (float) $eoi->investor_amount_usd < EnterpriseProfile::minInvestorUsd()
                    ? ['EOI: investor amount (US$ '.number_format((float) $eoi->investor_amount_usd).') is below the US$ '.number_format(EnterpriseProfile::minInvestorUsd()).' minimum']
                    : []),
                ...$noProfile,
                ...($p && $p->has_investor === false ? ['Enterprise profile: the enterprise says it has no participating investor (8.2)'] : []),
                ...$profileGroup('investor'),
                ...($p && $p->investorAmount() !== null && ! EnterpriseProfile::meetsInvestorMinimum($p->investorAmount())
                    ? ['Enterprise profile: investor amount (US$ '.number_format($p->investorAmount() / $rate).') is below the US$ '.number_format(EnterpriseProfile::minInvestorUsd()).' minimum'] : []),
            ],
            'transaction' => [
                ...$eoiMissing(['investment_required_usd' => 'total investment required', 'investment_types' => 'type of investment']),
                ...$noProfile,
                ...$profileGroup('transaction'),
                ...(in_array('debt', (array) $eoi->investment_types, true) ? ['EOI: debt is not an eligible instrument for co-investment'] : []),
                ...($p && $p->participation === 'debt' ? ['Enterprise profile: the investment instrument (8.3.1) is debt, which is not eligible for co-investment'] : []),
                ...($p && $p->project_cost !== null && $p->grant_requested && (float) $p->project_cost < (float) $p->grant_requested
                    ? ['Enterprise profile: total project cost is less than the CAFI matching grant'] : []),
            ],
            'use_of_funds' => [
                ...$eoiMissing(['use_of_funds' => 'use of investment', 'use_of_funds_description' => 'planned use of the investment']),
                ...$noProfile,
                ...$profileGroup('use_of_funds'),
                ...$p && $p->has_investor !== false ? (function () use ($p, $eoi, $rate, $grantUsd) {
                    // 8.4 grant-only budget, in US$ (older profiles: the Maloti budget entered before form v2)
                    $t      = EnterpriseProfile::planTotals((array) ($p->grant_plan ?? []));
                    $legacy = $p->budgetTotals()['grant'] / $rate;
                    $total  = $t['total'] > 0 ? $t['total'] : $legacy;
                    if ($total <= 0) {
                        return [];   // already listed above as "Proposed grant utilisation budget not answered"
                    }

                    $outside = $t['total'] > 0 ? $t['outside'] : (float) $p->grant_outside_lesotho / $rate;
                    $pct     = round(($total - $outside) / $total * 100, 1);
                    $min     = EnterpriseProfile::minLesothoPct();

                    return array_values(array_filter([
                        $grantUsd !== null && $total > $grantUsd + 1
                            ? 'Enterprise profile: grant budget (US$ '.number_format($total).') is more than the CAFI matching grant (US$ '.number_format($grantUsd).')' : null,
                        $eoi->grant_requested_usd !== null && $grantUsd !== null && (float) $eoi->grant_requested_usd > $grantUsd + 1
                            ? 'EOI: CAFI grant requested (US$ '.number_format((float) $eoi->grant_requested_usd).') is more than the matching grant (US$ '.number_format($grantUsd).')' : null,
                        $pct < $min && blank($p->grant_outside_justification)
                            ? 'Enterprise profile: only '.$pct.'% of the grant is spent in Lesotho (minimum '.$min.'%) and no reason is given' : null,
                    ]));
                })() : [],
            ],
            'declarations' => [
                ...(! $eoi->declaration_agree ? ['EOI: declaration not accepted'] : []),
                ...$eoiMissing(['declaration_name' => 'name of person making the declaration', 'declaration_date' => 'declaration date']),
                ...$noProfile,
                ...$profileGroup('declarations'),
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