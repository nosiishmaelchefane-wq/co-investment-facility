<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Gate 2 · Due Diligence & Investment Appraisal (CI Facility Operations Manual §3.3) — simplified.
 * One row per round. Amounts in Maloti (LSL).
 *
 *   1. Checklist      — 6 assessment areas: status + findings
 *   2. Key figures    — stage, instrument, private capital, CAFI grant, ESGC risk (matching rules checked)
 *   3. Conditions     — what must happen and when (DueDiligenceCondition)
 *   4. Summary + IAM  — Investment Appraisal Memorandum: 5 questions, drafted automatically from 1–3 and the
 *                       enterprise profile, editable by the PMU, downloadable as PDF (DueDiligenceController::memorandum)
 *   5. Outcome        — Proceed · Clarification · Remediation · Do not progress
 */
class DueDiligence extends Model
{
    /** Area status — null = not assessed yet */
    public const STATUSES = [
        'complete'       => ['Complete',               'is-complete'],
        'clarification'  => ['Requires clarification', 'is-clarification'],
        'not_applicable' => ['Not applicable',         'is-not_applicable'],
    ];

    /** area => [title, icon, question, what to look at] */
    public const AREAS = [
        'enterprise'  => ['Enterprise',             'bi-building',           'Is the enterprise legitimate, operational and able to deliver its growth plan?',
                          'Registration & ownership, management, operations, customers, tax & compliance, existing debt.'],
        'investor'    => ['Investor',               'bi-briefcase',          'Is the investor genuine, able to invest and committed?',
                          'Identity, capacity to invest, authority, track record, funds available. Evidence can differ for angels vs funds.'],
        'transaction' => ['Transaction',            'bi-file-earmark-ruled', 'Is there a real, well-developed deal that can reach financial close?',
                          'Stage (LOI, term sheet…), structure & terms, conditions to close, use of private capital and grant.'],
        'financial'   => ['Financial & commercial', 'bi-graph-up',           'Do the numbers and the market support the investment?',
                          'Revenue & profit trends, quality of records, demand evidence, projections.'],
        'esgc'        => ['ESGC',                   'bi-tree',               'Are environmental, social, gender and climate risks acceptable and managed?',
                          'Main ESGC risks and the actions to manage them (add actions as conditions).'],
        'risk'        => ['Risks',                  'bi-shield-exclamation', 'Are the main risks understood, and are any grounds for exclusion?',
                          'Investor, legal, execution, concentration, timing and regulatory risks — and how they are mitigated.'],
    ];

    /** stage => [label, evidence] */
    public const STAGES = [
        'interest'        => ['Investor interest',   'Meetings, correspondence'],
        'indicative'      => ['Indicative interest', 'Indicative terms, proposal'],
        'loi'             => ['Letter of Intent',    'Signed LOI'],
        'term_sheet'      => ['Term Sheet',          'Signed / agreed term sheet'],
        'due_diligence'   => ['Investor DD',         'Investor DD under way'],
        'final_docs'      => ['Final documentation', 'Draft investment agreements'],
        'financial_close' => ['Financial close',     'Executed agreements, funds deployed'],
    ];

    /** Stages that count as investor commitment (below this is only "interest") */
    public const COMMITTED_STAGES = ['loi', 'term_sheet', 'due_diligence', 'final_docs', 'financial_close'];

    public const INSTRUMENTS = ['equity' => 'Equity', 'quasi_equity' => 'Quasi-equity', 'blended' => 'Blended', 'other' => 'Other eligible structure'];

    public const RATINGS = ['low' => 'Low', 'medium' => 'Medium', 'high' => 'High'];

    /* ---------------- Outcome (§3.3.4) ---------------- */
    public const OUTCOME_PROCEED       = 'proceed';
    public const OUTCOME_CLARIFICATION = 'clarification';
    public const OUTCOME_REMEDIATION   = 'remediation';
    public const OUTCOME_DO_NOT        = 'do_not_progress';

    /** outcome => [label, description, icon, pill] */
    public const OUTCOMES = [
        self::OUTCOME_PROCEED       => ['Proceed',                'Ready for the Investment Committee.',                         'bi-check2-circle',   'pill-ok'],
        self::OUTCOME_CLARIFICATION => ['Clarification required', 'More evidence is needed before a conclusion can be reached.', 'bi-question-circle', 'pill-warn'],
        self::OUTCOME_REMEDIATION   => ['Remediation required',   'Viable, but a corrective action is needed first.',            'bi-tools',           'pill-warn'],
        self::OUTCOME_DO_NOT        => ['Do not progress',        'A fundamental issue prevents further consideration.',        'bi-x-octagon',       'pill-danger'],
    ];

    public const OUTCOME_TO_EOI_STATUS = [
        self::OUTCOME_PROCEED       => ExpressionOfInterest::STATUS_COMMITTEE,
        self::OUTCOME_CLARIFICATION => ExpressionOfInterest::STATUS_DD_HOLD,
        self::OUTCOME_REMEDIATION   => ExpressionOfInterest::STATUS_DD_HOLD,
        self::OUTCOME_DO_NOT        => ExpressionOfInterest::STATUS_NOT_PROGRESSED,
    ];

    /** Editable columns */
    public const FORM_COLUMNS = [
        'areas', 'investor_names', 'stage', 'instrument', 'private_amount', 'grant_amount', 'esgc_category',
        'summary', 'expected_outcomes', 'key_risks', 'memo', 'outcome', 'conclusion', 'assessed_on',
    ];

    /**
     * Investment Appraisal Memorandum (§3.3) — one section per enterprise profile section (the profile side bar),
     * each drafted automatically from the profile, the EOI and the officer's assessment, then verified by the officer.
     * The five appraisal questions of §3.3 are answered inside these sections:
     *   genuine transaction, investor commitment, structure & amount, use of grant → Business & financials
     *   remaining risks & conditions                                               → Attachments & declaration
     * key (= EnterpriseProfile::SECTIONS key) => [title (profile side bar), what the section confirms]
     */
    public const MEMO_QUESTIONS = [
        'company'     => ['Company & contact',         'Is the enterprise legitimate and operating — registration, stage, sector, activity and contact person?'],
        'legal'       => ['Legal status & markets',    'Are the legal form, ownership, tax status and markets in order for the Facility?'],
        'people'      => ['Employees & key people',    'Can the team deliver the growth plan, and are the jobs, management and environmental & social risks acceptable?'],
        'business'    => ['Business & financials',     'Is there a genuine, evidenced transaction with an eligible structure and amount, and a clearly defined, programme-compliant use of the CAFI grant?'],
        'attachments' => ['Attachments & declaration', 'Are the documents and declarations in order, and are the remaining risks and conditions understood well enough for Investment Committee consideration?'],
    ];

    /** The five questions used before the memorandum followed the profile sections (memos saved before the change) */
    public const LEGACY_MEMO_QUESTIONS = [
        'genuine'   => ['Genuine transaction',            'Is there a genuine investment transaction between the enterprise and the identified investor?'],
        'evidenced' => ['Investor commitment',            'Is the transaction sufficiently developed and evidenced to demonstrate credible investor commitment?'],
        'structure' => ['Structure & amount',             "Is the proposed investment structure and amount appropriate in relation to the enterprise's financing requirement and growth plan?"],
        'use'       => ['Use of private capital & grant', 'Is the proposed use of private capital and CAFI grant funding clearly defined and programme-compliant?'],
        'risks'     => ['Remaining risks & conditions',   'Are the remaining transaction risks and conditions sufficiently understood to allow the transaction to proceed to Investment Committee consideration?'],
    ];

    /** A memo saved with the earlier five questions (and none of the profile sections) */
    public static function isLegacyMemo(?array $memo): bool
    {
        $keys = array_keys((array) $memo);

        return array_intersect($keys, array_keys(self::LEGACY_MEMO_QUESTIONS)) !== [] && array_intersect($keys, array_keys(self::MEMO_QUESTIONS)) === [];
    }

    /** The sections a saved memo uses (earlier memos keep their five questions for display and the PDF) */
    public static function memoQuestions(?array $memo): array
    {
        return self::isLegacyMemo($memo) ? self::LEGACY_MEMO_QUESTIONS : self::MEMO_QUESTIONS;
    }

    /** EOI "use of funds" labels (for the memorandum) */
    protected const USE_OF_FUNDS = [
        'equipment' => 'equipment / productive assets', 'business_expansion' => 'business expansion', 'working_capital' => 'working capital',
        'market_expansion' => 'market expansion', 'technology' => 'technology / systems', 'product_development' => 'product development', 'other' => 'other',
    ];

    protected $fillable = [
        'expression_of_interest_id', 'round', 'assessor_id', 'started_on', ...self::FORM_COLUMNS,
        'iam_path', 'iam_name', 'iam_size', 'iam_uploaded_by', 'iam_uploaded_at', 'decided_at', 'decided_by',
    ];

    protected $casts = [
        'areas'           => 'array',
        'memo'            => 'array',
        'private_amount'  => 'decimal:2',
        'grant_amount'    => 'decimal:2',
        'started_on'      => 'date',
        'assessed_on'     => 'date',
        'iam_uploaded_at' => 'datetime',
        'decided_at'      => 'datetime',
    ];

    /* ---------------- Relations ---------------- */

    public function expressionOfInterest(): BelongsTo { return $this->belongsTo(ExpressionOfInterest::class); }
    public function assessor(): BelongsTo             { return $this->belongsTo(User::class, 'assessor_id'); }
    public function decider(): BelongsTo              { return $this->belongsTo(User::class, 'decided_by'); }
    public function iamUploader(): BelongsTo          { return $this->belongsTo(User::class, 'iam_uploaded_by'); }

    public function conditions(): HasMany
    {
        return $this->hasMany(DueDiligenceCondition::class)->orderBy('position');
    }

    /* ---------------- State ---------------- */

    public function isDecided(): bool
    {
        return $this->decided_at !== null;
    }

    public function isOnHold(): bool
    {
        return $this->isDecided() && in_array($this->outcome, [self::OUTCOME_CLARIFICATION, self::OUTCOME_REMEDIATION], true);
    }

    public function toForm(): array
    {
        $form = [];
        foreach (self::FORM_COLUMNS as $col) {
            $v = $this->{$col};
            $form[$col] = $v instanceof \DateTimeInterface ? $v->format('Y-m-d') : $v;
        }

        return $form;
    }

    /* ---------------- Calculations (work on the form array) ---------------- */

    /** ['private', 'grant', 'total', 'structure'] */
    public static function validated(array $form): array
    {
        $private = (float) ($form['private_amount'] ?? 0);
        $grant   = (float) ($form['grant_amount'] ?? 0);

        return [
            'private'   => $private,
            'grant'     => $grant,
            'total'     => $private + $grant,
            'structure' => self::INSTRUMENTS[$form['instrument'] ?? ''] ?? null,
        ];
    }

    /** Steps done: 6 areas + key figures + summary + memorandum ($hasIam = memorandum complete) */
    public static function progress(array $form, bool $hasIam): array
    {
        $steps = [
            ...array_map(fn ($k) => filled($form['areas'][$k]['status'] ?? null), array_keys(self::AREAS)),
            filled($form['stage'] ?? null) && (float) ($form['private_amount'] ?? 0) > 0 && (float) ($form['grant_amount'] ?? 0) > 0 && filled($form['esgc_category'] ?? null),
            filled($form['summary'] ?? null) && filled($form['key_risks'] ?? null),
            $hasIam,
        ];

        $done = count(array_filter($steps));

        return ['done' => $done, 'total' => count($steps), 'pct' => (int) round($done / count($steps) * 100)];
    }

    /** Why "Proceed" isn't possible yet (empty = ready) */
    public static function proceedBlockers(array $form, array $conditions, bool $hasIam): array
    {
        $v = self::validated($form);

        $areas = collect(self::AREAS)->map(function ($a, $k) use ($form) {
            return match ($form['areas'][$k]['status'] ?? null) {
                null, ''        => "{$a[0]}: not assessed yet.",
                'clarification' => "{$a[0]}: still requires clarification.",
                default         => null,
            };
        })->filter()->values()->all();

        return array_values(array_filter([
            ...$areas,
            blank($form['stage'] ?? null) ? 'Record the transaction stage.' : null,
            filled($form['stage'] ?? null) && ! in_array($form['stage'], self::COMMITTED_STAGES, true) ? 'The transaction is only at investor interest — a commitment (LOI or later) is needed.' : null,
            $v['private'] <= 0 || $v['grant'] <= 0 ? 'Enter the private capital and the CAFI grant.' : null,
            blank($form['esgc_category'] ?? null) ? 'Set the ESGC risk category.' : null,
            blank($form['summary'] ?? null) || blank($form['key_risks'] ?? null) ? 'Complete the summary and key risks for the Committee.' : null,
            ! $hasIam ? 'Complete and verify the Investment Appraisal Memorandum — all five sections need an answer and an officer’s verification.' : null,
            ...collect($conditions)->filter(fn ($c) => DueDiligenceCondition::blocks($c))
                ->map(fn ($c) => 'Open: '.($c['finding'] ?: 'untitled condition').' — must be resolved before the Committee.')->values()->all(),
        ]));
    }

    /* ---------------- Investment Appraisal Memorandum ---------------- */

    /**
     * Memo rows for every section:
     *   key => ['text' => string, 'edited' => bool, 'verified' => bool, 'verified_by' => ?string, 'verified_at' => ?string]
     * "edited" = written or changed by the officer (no longer redrafted automatically).
     * "verified" = an officer has checked the section; any change to its text clears it.
     */
    public static function memoRows(?array $memo, bool $current = false): array
    {
        $questions = $current ? self::MEMO_QUESTIONS : self::memoQuestions($memo);

        return collect($questions)->mapWithKeys(fn ($q, $k) => [$k => [
            'text'        => (string) ($memo[$k]['text'] ?? ''),
            'edited'      => (bool) ($memo[$k]['edited'] ?? false),
            'verified'    => (bool) ($memo[$k]['verified'] ?? false),
            'verified_by' => $memo[$k]['verified_by'] ?? null,
            'verified_at' => $memo[$k]['verified_at'] ?? null,
        ]])->all();
    }

    /** Every section has an answer */
    public static function memoComplete(?array $memo): bool
    {
        return collect(self::memoRows($memo))->every(fn ($r) => filled(trim($r['text'])));
    }

    /** Every section has an answer and has been verified by an officer */
    public static function memoVerified(?array $memo): bool
    {
        if (self::isLegacyMemo($memo)) {
            return self::memoComplete($memo);   // written before verification existed
        }

        return collect(self::memoRows($memo))->every(fn ($r) => filled(trim($r['text'])) && $r['verified']);
    }

    /** Number of verified sections */
    public static function memoVerifiedCount(?array $memo): int
    {
        return collect(self::memoRows($memo))->filter(fn ($r) => filled(trim($r['text'])) && $r['verified'])->count();
    }

    /** A row with new text: verification is cleared when the text changes */
    public static function memoRow(array $row, string $text, bool $edited): array
    {
        $changed = trim($text) !== trim((string) ($row['text'] ?? ''));

        return [
            'text'        => $text,
            'edited'      => $edited,
            'verified'    => $changed ? false : (bool) ($row['verified'] ?? false),
            'verified_by' => $changed ? null : ($row['verified_by'] ?? null),
            'verified_at' => $changed ? null : ($row['verified_at'] ?? null),
        ];
    }

    /**
     * Refresh the sections that the officer has not edited, from the assessment and the profile.
     * Edited sections are kept as they are ($force = true redrafts those too). A section whose text changes loses its verification.
     */
    public static function refreshMemo(array $form, array $conditions, ExpressionOfInterest $eoi, ?EnterpriseProfile $p, ?string $only = null, bool $force = false): array
    {
        $draft = self::draftMemo($form, $conditions, $eoi, $p);

        // An open assessment with a memo in the earlier five-question format starts again in the profile sections
        return collect(self::memoRows($form['memo'] ?? [], current: true))->map(function ($row, $k) use ($draft, $only, $force) {
            if ($only !== null && $k !== $only) {
                return $row;
            }

            return $row['edited'] && ! $force ? $row : self::memoRow($row, $draft[$k], false);
        })->all();
    }

    /**
     * Draft answers to the five questions, written from the officer's decisions (checklist status & findings,
     * transaction stage, instrument, validated amounts, ESGC risk, conditions, summary) and the enterprise profile.
     */
    public static function draftMemo(array $form, array $conditions, ExpressionOfInterest $eoi, ?EnterpriseProfile $p): array
    {
        $rate  = max((float) config('cif.usd_to_lsl', 18.00), 0.0001);
        $m     = fn ($lsl) => $lsl === null ? '—' : 'US$ '.number_format((float) $lsl / $rate).' (M '.number_format((float) $lsl).')';
        $u     = fn ($usd) => 'US$ '.number_format((float) $usd).' (M '.number_format((float) $usd * $rate).')';
        $v     = self::validated($form);
        $name  = $p?->registered_name ?: $eoi->business_name ?: 'The enterprise';
        $stage = $form['stage'] ?? null;
        $committed = $stage && in_array($stage, self::COMMITTED_STAGES, true);

        // The officer's checklist result for an area: "Assessment: complete. <findings>"
        $area = function (string $k) use ($form) {
            $st = $form['areas'][$k]['status'] ?? null;
            $fi = trim((string) ($form['areas'][$k]['finding'] ?? ''));

            return trim(match ($st) {
                'complete'       => 'The '.strtolower(self::AREAS[$k][0]).' assessment is complete.',
                'clarification'  => 'The '.strtolower(self::AREAS[$k][0]).' assessment still requires clarification.',
                'not_applicable' => 'The '.strtolower(self::AREAS[$k][0]).' assessment was marked not applicable.',
                default          => 'The '.strtolower(self::AREAS[$k][0]).' area has not been assessed yet.',
            }.($fi !== '' ? ' Findings: '.rtrim($fi, '.').'.' : ''));
        };
        $join = fn (array $parts) => trim(implode(' ', array_filter(array_map('trim', $parts))));

        /* 1 · Genuine transaction */
        // Form v2 8.2: one lead investor
        $lead         = $p?->lead_investor_name ?: $p?->investor_name;
        $leadAmount   = $p?->investorAmount();
        $leadInfo     = collect([$p?->investor_type, $p?->lead_investor_country])->filter()->implode(', ');
        $investorText = $lead
            ? $lead.($leadInfo ? " ({$leadInfo})" : '').($leadAmount ? ' — '.$m($leadAmount) : '')
            : ($form['investor_names'] ?: $eoi->investor_name);
        $track = $p ? EnterpriseInvestment::trackRecordLines($p) : [];

        $genuine = $join([
            $investorText
                ? "{$name} has identified the following lead investor: {$investorText}."
                : "{$name} has not identified a participating investor.",
            $p?->lead_investor_contact_name || $p?->lead_investor_email
                ? 'Investor contact: '.collect([$p->lead_investor_contact_name, $p->lead_investor_email, $p->lead_investor_phone])->filter()->implode(', ').'.' : '',
            $p?->has_investor === false ? 'On the enterprise profile the applicant states that it has no participating investor.' : '',
            $stage ? 'The transaction is at the '.strtolower(self::STAGES[$stage][0]).' stage.' : 'The transaction stage has not been recorded.',
            $track ? 'Investor track record on the facility: '.implode('; ', $track).'.' : '',
            $area('investor'),
            match (true) {
                ! $investorText || $p?->has_investor === false                 => 'Conclusion: a genuine transaction has not been demonstrated.',
                ($form['areas']['investor']['status'] ?? null) === 'complete' && $committed => 'Conclusion: the investor and the transaction appear genuine.',
                default                                                       => 'Conclusion: to be confirmed once the investor assessment is complete.',
            },
        ]);

        /* 2 · Developed & evidenced commitment */
        $evidence = collect((array) $eoi->investor_evidence)->reject(fn ($e) => in_array($e, ['none_available', 'not_applicable'], true))
            ->map(fn ($e) => ['loi' => 'letter of intent', 'term_sheet' => 'term sheet', 'investment_agreement' => 'investment agreement', 'other_formal' => 'other formal evidence'][$e] ?? $e)->all();
        $commitmentDoc = $p?->document('commitment');

        $evidenced = $join([
            $stage ? 'Stage: '.self::STAGES[$stage][0].' (expected evidence: '.strtolower(self::STAGES[$stage][1]).').' : '',
            $evidence ? 'The applicant reported the following evidence: '.implode(', ', $evidence).'.' : 'The applicant did not report formal evidence of commitment.',
            $commitmentDoc ? 'A term sheet / commitment document is on file ('.$commitmentDoc->original_name.').' : 'No term sheet or commitment document has been uploaded to the profile.',
            $p?->document('investor_evidence') ? 'Further evidence of investor engagement is on file ('.$p->document('investor_evidence')->original_name.').' : '',
            $p?->transaction_status ? 'Investor engagement status on the profile: '.(EnterpriseProfile::TRANSACTION_STATUSES[$p->transaction_status] ?? $p->transaction_status).'.' : '',
            $p?->investor_commitment ? 'Investor commitment (8.2.9): '.strtolower(EnterpriseProfile::COMMITMENT[$p->investor_commitment] ?? $p->investor_commitment).'.' : '',
            $p?->tx_amount_agreed !== null ? 'Investment amount agreed with the investor: '.($p->tx_amount_agreed ? 'yes' : 'no').'.' : '',
            $p?->tx_terms_agreed !== null ? 'Terms agreed: '.($p->tx_terms_agreed ? 'yes' : 'no').'.' : '',
            $p?->tx_conditions_outstanding ? 'The applicant reports material conditions still outstanding before the investment is made.' : '',
            $p?->expected_close ? 'Expected close / deployment: '.rtrim($p->expected_close, '.').'.' : '',
            $area('transaction'),
            match (true) {
                ! $stage     => 'Conclusion: the stage of the transaction must be confirmed.',
                ! $committed => 'Conclusion: this is investor interest, not commitment — a signed LOI or later is required before the Committee.',
                default      => 'Conclusion: the transaction is sufficiently developed and the investor commitment is evidenced.',
            },
        ]);

        /* 3 · Structure & amount */
        $expected   = EnterpriseProfile::matchingGrant($v['private'] ?: null);
        $grantOk    = $expected !== null && $v['grant'] > 0 && $v['grant'] <= $expected + 1;
        $minOk      = $v['private'] > 0 && EnterpriseProfile::meetsInvestorMinimum($v['private']);
        $projectCost = $p?->project_cost !== null ? (float) $p->project_cost : null;

        $structure = $join([
            'Instrument: '.(self::INSTRUMENTS[$form['instrument'] ?? ''] ?? 'not recorded')
                .($p?->participation ? ' (profile: '.(EnterpriseProfile::PARTICIPATION[$p->participation] ?? $p->participation).')' : '').'.',
            'Validated private capital: '.$m($v['private'] ?: null).'; CAFI grant: '.$m($v['grant'] ?: null).'; total transaction: '.$m($v['total'] ?: null).'.',
            $v['private'] > 0 ? ($minOk ? 'The investor amount meets' : 'The investor amount is below').' the US$ '.number_format(EnterpriseProfile::minInvestorUsd()).' minimum.' : '',
            $expected !== null ? ($grantOk
                ? 'The grant is within the CAFI grant rule ('.EnterpriseProfile::grantRatioLabel().') for the validated private capital — maximum '.$m($expected).', '.EnterpriseProfile::effectiveGrantPct($v['private']).'% of the investment.'
                : 'The grant is more than the CAFI grant rule allows ('.EnterpriseProfile::grantRatioLabel().') — maximum '.$m($expected).'.') : '',
            $leadAmount ? 'Lead investor amount on the profile: '.$m($leadAmount).'.' : '',
            $p?->valuation ? 'Valuation: US$ '.number_format((float) EnterpriseProfile::toLsl((float) $p->valuation, $p->investment_currency ?: 'LSL') / $rate)
                .(($vInfo = collect([EnterpriseProfile::VALUATION_BY[$p->valuation_by ?? ''] ?? null, EnterpriseProfile::VALUATION_METHODS[$p->valuation_method ?? ''] ?? null])->filter()->map(fn ($x) => strtolower($x))->implode(', ')) ? " ({$vInfo})" : '').'.' : '',
            $projectCost !== null ? 'Total project cost: '.$m($projectCost).($projectCost < $v['grant'] ? ' — less than the CAFI grant.' : '.') : '',
            $p?->annual_revenue ? 'Annual revenue (past year): '.$m($p->annual_revenue).'.' : '',
            $p?->company_stage ? 'Stage of the business: '.strtolower(EnterpriseProfile::STAGES[$p->company_stage] ?? $p->company_stage).'.' : '',
            in_array('debt', (array) $eoi->investment_types, true) ? 'Note: the EOI lists debt, which is not an eligible instrument.' : '',
            $area('financial'),
            match (true) {
                $v['private'] <= 0 || $v['grant'] <= 0 => 'Conclusion: the validated amounts still need to be confirmed.',
                ! $minOk || ! $grantOk                  => 'Conclusion: the amount or structure does not yet meet the facility rules.',
                default                                => 'Conclusion: the structure and amount are appropriate to the financing requirement and growth plan.',
            },
        ]);

        /* 4 · Use of private capital and grant */
        // Form v2 8.4: grant-only budget in US$ (older profiles: the Maloti grant column)
        $plan      = (array) ($p?->grant_plan ?? []);
        $planTot   = EnterpriseProfile::planTotals($plan);
        $planUsd   = $planTot['total'] > 0 ? $planTot['total'] : (($p?->budgetTotals()['grant'] ?? 0) / $rate);
        $budgetSet = $planUsd > 0;
        $budgetLines = collect($planTot['categories'])->filter(fn ($x) => $x > 0)
            ->map(fn ($x, $k) => (EnterpriseProfile::GRANT_CATEGORIES[$k][0] ?? $k).' '.$u($x)
                .(collect($plan[$k] ?? [])->pluck('item')->filter()->isNotEmpty() ? ' ('.collect($plan[$k])->pluck('item')->filter()->implode(', ').')' : ''))
            ->values()->all();
        $budgetGrantOk = $budgetSet && $v['grant'] > 0 && $planUsd * $rate <= $v['grant'] + $rate;   // within the validated grant (to US$ 1)
        $outsideUsd    = $planTot['total'] > 0 ? $planTot['outside'] : (float) ($p?->grant_outside_lesotho ?? 0) / $rate;
        $lesothoPct    = $budgetSet ? round(($planUsd - $outsideUsd) / $planUsd * 100, 1) : null;
        $minLesotho    = EnterpriseProfile::minLesothoPct();
        $uses = collect((array) $eoi->use_of_funds)->map(fn ($x) => self::USE_OF_FUNDS[$x] ?? $x)->all();
        $purpose = $p?->tx_description ?: ($p?->investment_purpose ?: $eoi->use_of_funds_description);

        $use = $join([
            $purpose ? 'Transaction: '.rtrim($purpose, '.').'.' : '',
            $uses ? 'Intended use (EOI): '.implode(', ', $uses).'.' : '',
            $budgetSet ? 'Grant budget: '.($budgetLines ? implode('; ', $budgetLines).'. ' : '').'Total '.$u($planUsd).'.'
                       : 'The grant utilisation budget has not been completed.',
            $budgetSet && ! $budgetGrantOk ? 'The grant budget is more than the validated CAFI grant.' : '',
            $lesothoPct !== null ? $lesothoPct.'% of the grant is to be spent in Lesotho'.($lesothoPct < $minLesotho ? ' — below the '.$minLesotho.'% minimum'.($p?->grant_outside_justification ? ' (reason given: '.rtrim($p->grant_outside_justification, '.').')' : '') : '').'.' : '',
            $p?->double_financing ? 'The applicant declared that part of the budget is also financed by another grant or public source'.($p->double_financing_details ? ': '.rtrim($p->double_financing_details, '.') : '').'.' : '',
            $p?->grant_growth_support ? 'How the grant supports growth: '.rtrim($p->grant_growth_support, '.').'.' : '',
            $p?->document('budget') ? 'An operational budget is on file.' : 'No operational budget has been uploaded.',
            $p?->document('quotation') ? 'Supporting quotations are on file.' : '',
            $form['esgc_category'] ? 'ESGC risk category: '.self::RATINGS[$form['esgc_category']].'.' : '',
            $area('esgc'),
            match (true) {
                ! $budgetSet                        => 'Conclusion: the use of funds is not yet clearly defined.',
                ! $budgetGrantOk                    => 'Conclusion: the grant budget must be aligned with the validated CAFI grant.',
                $lesothoPct !== null && $lesothoPct < $minLesotho && blank($p?->grant_outside_justification)
                                                    => 'Conclusion: the share of the grant spent outside Lesotho must be justified.',
                (bool) $p?->double_financing        => 'Conclusion: the declared double financing must be resolved before the Committee.',
                default                             => 'Conclusion: the use of private capital and grant funding is clearly defined and programme-compliant.',
            },
        ]);

        /* 5 · Remaining risks and conditions */
        $open    = collect($conditions)->where('status', 'open');
        $byWhen  = $open->groupBy('treatment')->map(fn ($g, $t) => (DueDiligenceCondition::TREATMENTS[$t][0] ?? $t).': '.$g->pluck('finding')->filter()->implode('; '))->values()->all();
        $blocking = $open->filter(fn ($c) => DueDiligenceCondition::blocks($c))->count();
        $esFlags = $p && $p->es_checklist ? $p->esScreening() : [];

        $risks = $join([
            filled($form['key_risks'] ?? null) ? 'Key risks: '.rtrim(trim($form['key_risks']), '.').'.' : 'Key risks have not been recorded.',
            $area('risk'),
            $esFlags ? count($esFlags).' environmental & social answer(s) were flagged for follow-up'.($p->esDisqualified() ? ', including a ground for exclusion' : '').'.' : '',
            $p?->ownership_dispute ? 'The applicant disclosed an ownership or management dispute'.($p->ownership_dispute_details ? ': '.rtrim($p->ownership_dispute_details, '.') : '').'.' : '',
            $p?->obfc_status && $p->obfc_status !== 'yes' ? 'The enterprise is not yet registered with the OBFC — no grant can be paid until it is.' : '',
            $byWhen ? 'Open conditions — '.implode(' | ', $byWhen).'.' : 'There are no open conditions.',
            match (true) {
                $p?->esDisqualified()                     => 'Conclusion: an exclusion ground was disclosed — the transaction should not proceed.',
                $blocking > 0                             => "Conclusion: {$blocking} condition(s) must be resolved before the Committee.",
                blank($form['key_risks'] ?? null)         => 'Conclusion: the risk assessment must be completed.',
                default                                   => 'Conclusion: the remaining risks and conditions are understood and the transaction can proceed to Investment Committee consideration.',
            },
        ]);

        /* ===== Sections in the order of the enterprise profile side bar ===== */
        $yn  = fn ($b) => $b === null ? null : ($b ? 'yes' : 'no');
        $P   = EnterpriseProfile::class;

        /* 1 · Company & contact */
        $months  = $p?->monthsOperating();
        $company = $join([
            $p ? $name.($p->short_name ? ' (trading as '.$p->short_name.')' : '').'.' : "{$name} has not completed an enterprise profile.",
            $p?->obfc_status ? 'Registered with the OBFC: '.strtolower($P::YES_NO_PROGRESS[$p->obfc_status] ?? $p->obfc_status)
                .($p->registration_no ? ', registration no. '.$p->registration_no : '').($p->year_registered ? ', registered in '.$p->year_registered : '').'.' : '',
            $p?->commencedLabel() ? 'Operating since '.$p->commencedLabel().($months !== null ? " ({$months} months)" : '').'.' : '',
            $p?->company_stage ? 'Stage: '.strtolower($P::STAGES[$p->company_stage] ?? $p->company_stage).'.' : '',
            $p?->sectorLabel() ? 'Sector: '.$p->sectorLabel().($p->sub_sector ? ' — '.$p->sub_sector : '').'.' : '',
            collect([$p?->address, $p?->city, $p?->district])->filter()->isNotEmpty() ? 'Address: '.collect([$p->address, $p->city, $p->district])->filter()->implode(', ').'.' : '',
            $p?->activity ? 'Activity: '.rtrim($p->activity, '.').'.' : '',
            $p?->products_services ? 'Products & services: '.rtrim($p->products_services, '.').'.' : '',
            $p?->labels($P::PRIOR_SUPPORT, $p?->prior_cafi_support) ? 'Earlier CAFI support: '.$p->labels($P::PRIOR_SUPPORT, $p->prior_cafi_support).'.' : '',
            $p?->contact_name ? 'Contact: '.collect([$p->contact_name, $p->contact_title, $p->contact_email, $p->contact_phone])->filter()->implode(', ').'.' : '',
            $area('enterprise'),
            match (true) {
                ! $p                                  => 'Conclusion: the enterprise profile must be completed.',
                $p->obfc_status && $p->obfc_status !== 'yes' => 'Conclusion: OBFC registration must be completed before any grant is paid.',
                $p->operatingUnderSixMonths()         => 'Conclusion: the enterprise has operated for less than six months — eligibility must be confirmed.',
                ($form['areas']['enterprise']['status'] ?? null) === 'complete' => 'Conclusion: the enterprise is legitimate and operating.',
                default                               => 'Conclusion: to be confirmed once the enterprise assessment is complete.',
            },
        ]);

        /* 2 · Legal status & markets */
        $shareTotal = $p?->shareholderTotal() ?? 0;
        $holders    = collect($p?->shareholderRows() ?? [])->map(fn ($r) => $r['name'].(filled($r['percent'] ?? null) ? ' '.$r['percent'].'%' : ''))->all();
        $legal = $join([
            $p?->legal_form ? 'Legal form: '.$p->legal_form.'.' : '',
            $p?->foreign_registered ? 'Also registered in '.($p->foreign_country ?: 'another country').($p->foreign_registration_no ? ' (no. '.$p->foreign_registration_no.')' : '').'.' : ($p?->foreign_registered === false ? 'Not registered in another country.' : ''),
            $holders ? 'Shareholders: '.implode(', ', $holders).(abs($shareTotal - 100) >= 0.01 ? ' — the shareholding adds up to '.rtrim(rtrim(number_format($shareTotal, 2), '0'), '.').'%, not 100%' : '').'.'
                     : ($p?->document('shareholders_list') ? 'The shareholder list is uploaded as a document.' : ''),
            $p?->ownershipLine() ? 'Ownership: '.$p->ownershipLine().'.' : '',
            $p?->foreign_owned !== null ? 'Foreign-owned: '.$yn($p->foreign_owned).($p->foreign_owned && $p->foreign_owned_pct !== null ? ' ('.$p->foreign_owned_pct.'%)' : '').'.' : '',
            $p?->primary_ops_lesotho !== null ? 'Primary operations in Lesotho: '.$yn($p->primary_ops_lesotho).'.' : '',
            $p?->rsl_tax_file ? 'Tax file with RSL: '.strtolower($P::YES_NO_PROGRESS[$p->rsl_tax_file] ?? $p->rsl_tax_file).'.' : '',
            $p?->labels($P::MARKETS, $p?->markets) ? 'Markets: '.$p->labels($P::MARKETS, $p->markets).'.' : '',
            $p?->ownership_dispute ? 'An ownership or management dispute was disclosed'.($p->ownership_dispute_details ? ': '.rtrim($p->ownership_dispute_details, '.') : '').'.' : '',
            match (true) {
                ! $p                                    => 'Conclusion: the legal status cannot be confirmed without a profile.',
                $p->primary_ops_lesotho === false       => 'Conclusion: the main operations are outside Lesotho — eligibility must be confirmed.',
                (bool) $p->ownership_dispute            => 'Conclusion: the disclosed dispute must be resolved or understood before the Committee.',
                $p->rsl_tax_file && $p->rsl_tax_file !== 'yes' => 'Conclusion: the RSL tax registration must be completed.',
                $holders && abs($shareTotal - 100) >= 0.01 => 'Conclusion: the shareholding must be confirmed.',
                default                                 => 'Conclusion: the legal status, ownership and markets are in order.',
            },
        ]);

        /* 3 · Employees & key people */
        $esFlags2 = $p && $p->es_checklist ? $p->esScreening() : [];
        $team     = collect($p?->keyPeopleRows() ?? [])->map(fn ($r) => $r['name'].(filled($r['position'] ?? null) ? ' ('.$r['position'].')' : ''))->all();
        $people = $join([
            $p?->employees !== null ? 'Employees: '.$p->employees.' now'.($p->employees_prev_year !== null ? ', '.$p->employees_prev_year.' a year ago' : '')
                .' — full-time '.((int) $p->ft_male + (int) $p->ft_female).' ('.(int) $p->ft_female.' women), part-time '.((int) $p->pt_male + (int) $p->pt_female)
                .', youth '.((int) $p->youth_male + (int) $p->youth_female).'.' : '',
            $p?->expected_jobs !== null ? 'Expected new jobs: '.$p->expected_jobs.' (women '.(int) $p->expected_jobs_women.', youth '.(int) $p->expected_jobs_youth.').' : '',
            $team ? 'Directors & key management: '.implode('; ', $team).'.' : '',
            $p ? 'Women / youth owned or managed: women '.($yn($p->women_owned_managed) ?? '—').', youth '.($yn($p->youth_owned_managed) ?? '—').'.' : '',
            $p?->es_checklist ? ($esFlags2 ? count($esFlags2).' environmental & social answer(s) need follow-up.' : 'No environmental & social issue was disclosed.') : '',
            $p?->es_records_system ? 'E&S records: '.($P::ES_RECORD_SYSTEMS[$p->es_records_system] ?? $p->es_records_system).'.' : '',
            $form['esgc_category'] ? 'ESGC risk category: '.self::RATINGS[$form['esgc_category']].'.' : '',
            $area('esgc'),
            match (true) {
                $p?->esDisqualified()                  => 'Conclusion: an exclusion ground was disclosed — the transaction should not proceed.',
                blank($form['esgc_category'] ?? null)  => 'Conclusion: the ESGC risk category must be set.',
                $form['esgc_category'] === 'high'      => 'Conclusion: high ESGC risk — mitigation must be agreed before the Committee.',
                default                                => 'Conclusion: the team, jobs and environmental & social risks are acceptable.',
            },
        ]);

        /* 4 · Business & financials — the §3.3 transaction questions */
        $business = implode("\n\n", array_filter([
            $p?->customers || $p?->annual_revenue
                ? 'Business: '.$join([$p->customers ? 'Customers: '.rtrim($p->customers, '.').'.' : '', $p->annual_revenue ? 'Annual revenue (past year): '.$m($p->annual_revenue).'.' : ''])
                : '',
            'Genuine transaction: '.$genuine,
            'Investor commitment: '.$evidenced,
            'Structure & amount: '.$structure,
            'Use of the CAFI grant: '.$use,
        ]));

        /* 5 · Attachments & declaration — documents, declarations, remaining risks */
        $missingDocs = $p ? collect($p->missingDocuments())->map(fn ($t) => EnterpriseDocument::missingLabel($t))->unique()->values()->all() : [];
        $attachments = $join([
            $p ? ($missingDocs ? 'Documents still missing: '.implode(', ', $missingDocs).'.' : 'All required documents are uploaded to the enterprise profile.') : '',
            $p ? ($p->declaration_agreed ? 'The profile declaration was signed by '.($p->declared_name ?: 'the applicant').($p->declared_on ? ' on '.$p->declared_on->format('d M Y') : '').'.' : 'The profile declaration has not been signed.') : '',
            $eoi->declaration_agree ? 'The EOI declaration was accepted'.($eoi->declaration_name ? ' by '.$eoi->declaration_name : '').'.' : 'The EOI declaration was not accepted.',
            $risks,
        ]);
        if ($p && ($missingDocs || ! $p->declaration_agreed)) {
            $attachments .= ' Documents and declarations must be completed before the Committee.';
        }

        return ['company' => $company, 'legal' => $legal, 'people' => $people, 'business' => $business, 'attachments' => $attachments];
    }

    /* ---------------- New assessment, pre-filled from the EOI / profile ---------------- */

    public static function prefill(ExpressionOfInterest $eoi, ?EnterpriseProfile $p): array
    {
        $rate = (float) config('cif.usd_to_lsl', 18.00);

        // Profile 8.2.8 engagement status (or the EOI answer) → Gate 2 transaction stage
        $stage = match ($p?->transaction_status ?? $eoi->investor_status) {
            'initial', 'discussion', 'discussions', 'interest' => 'interest',
            'indicative'                                       => 'indicative',
            'loi', 'term_sheet_negotiation'                    => 'loi',
            'term_sheet'                                       => 'term_sheet',
            'agreement', 'committed'                           => 'final_docs',
            'financial_close'                                  => 'financial_close',
            default                                            => null,
        };
        // Profile 8.3.1 instrument → Gate 2 instrument (debt is not eligible, so it is left for the officer)
        $instrument = match ($p?->participation) {
            'equity'                                                                => 'equity',
            'quasi_equity', 'convertible', 'convertible_note', 'safe', 'revenue_share' => 'quasi_equity',
            'other'                                                                 => 'other',
            default                                                                 => null,
        };

        return [
            'areas'             => [],
            'investor_names'    => (string) ($p?->lead_investor_name ?: (collect($p?->investorRows() ?? [])->pluck('name')->implode(' · ') ?: ($p?->investor_name ?: $eoi->investor_name))),
            'stage'             => $stage,
            'instrument'        => isset(self::INSTRUMENTS[$instrument ?? '']) ? $instrument : null,
            'private_amount'    => $p?->investorAmount() ?? ($eoi->investor_amount_usd !== null ? round((float) $eoi->investor_amount_usd * $rate) : null),
            'grant_amount'      => $p?->grant_requested !== null ? (float) $p->grant_requested : null,
            'esgc_category'     => null,
            'summary'           => trim(($p?->registered_name ?: $eoi->business_name).($p?->sectorLabel() ? ' — '.$p->sectorLabel() : '').($p?->activity ? '. '.rtrim($p->activity, '.').'.' : '').($p?->tx_description ? ' Transaction: '.rtrim($p->tx_description, '.').'.' : '')),
            'expected_outcomes' => '',
            'key_risks'         => '',
            'memo'              => [],
            'outcome'           => null,
            'conclusion'        => '',
            'assessed_on'       => now()->toDateString(),
        ];
    }
}