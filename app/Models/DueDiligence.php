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
     * Investment Appraisal Memorandum (§3.3) — the five questions the transaction appraisal answers.
     * key => [short title, question]
     */
    public const MEMO_QUESTIONS = [
        'genuine'   => ['Genuine transaction',            'Is there a genuine investment transaction between the enterprise and the identified investor?'],
        'evidenced' => ['Investor commitment',            'Is the transaction sufficiently developed and evidenced to demonstrate credible investor commitment?'],
        'structure' => ['Structure & amount',             "Is the proposed investment structure and amount appropriate in relation to the enterprise's financing requirement and growth plan?"],
        'use'       => ['Use of private capital & grant', 'Is the proposed use of private capital and CAFI grant funding clearly defined and programme-compliant?'],
        'risks'     => ['Remaining risks & conditions',   'Are the remaining transaction risks and conditions sufficiently understood to allow the transaction to proceed to Investment Committee consideration?'],
    ];

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
            ! $hasIam ? 'Complete the Investment Appraisal Memorandum — all five questions need an answer.' : null,
            ...collect($conditions)->filter(fn ($c) => DueDiligenceCondition::blocks($c))
                ->map(fn ($c) => 'Open: '.($c['finding'] ?: 'untitled condition').' — must be resolved before the Committee.')->values()->all(),
        ]));
    }

    /* ---------------- Investment Appraisal Memorandum ---------------- */

    /** Memo rows for every question: key => ['text' => string, 'edited' => bool] */
    public static function memoRows(?array $memo): array
    {
        return collect(self::MEMO_QUESTIONS)->mapWithKeys(fn ($q, $k) => [$k => [
            'text'   => (string) ($memo[$k]['text'] ?? ''),
            'edited' => (bool) ($memo[$k]['edited'] ?? false),
        ]])->all();
    }

    /** All five questions answered */
    public static function memoComplete(?array $memo): bool
    {
        return collect(self::memoRows($memo))->every(fn ($r) => filled(trim($r['text'])));
    }

    /**
     * Refresh the answers that the officer has not edited, from the assessment and the profile.
     * Edited answers are kept as they are ($force = true redrafts those too).
     */
    public static function refreshMemo(array $form, array $conditions, ExpressionOfInterest $eoi, ?EnterpriseProfile $p, ?string $only = null, bool $force = false): array
    {
        $draft = self::draftMemo($form, $conditions, $eoi, $p);

        return collect(self::memoRows($form['memo'] ?? []))->map(function ($row, $k) use ($draft, $only, $force) {
            if ($only !== null && $k !== $only) {
                return $row;
            }

            return $row['edited'] && ! $force ? $row : ['text' => $draft[$k], 'edited' => false];
        })->all();
    }

    /**
     * Draft answers to the five questions, written from the officer's decisions (checklist status & findings,
     * transaction stage, instrument, validated amounts, ESGC risk, conditions, summary) and the enterprise profile.
     */
    public static function draftMemo(array $form, array $conditions, ExpressionOfInterest $eoi, ?EnterpriseProfile $p): array
    {
        $rate  = max((float) config('cif.usd_to_lsl', 18.00), 0.0001);
        $m     = fn ($lsl) => $lsl === null ? '—' : 'M '.number_format((float) $lsl).' (US$ '.number_format((float) $lsl / $rate).')';
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
        $investors = collect($p?->investorRows() ?? [])->map(fn ($r) => $r['name']
            .(filled($r['id_number'] ?? null) ? ' (ID/Reg. '.$r['id_number'].')' : '')
            .(is_numeric($r['ticket'] ?? null) && (float) $r['ticket'] > 0 ? ' — '.$m($r['ticket']) : ''))->all();
        $investorText = $investors ? implode('; ', $investors) : ($form['investor_names'] ?: $eoi->investor_name);
        $track = $p ? EnterpriseInvestment::trackRecordLines($p) : [];

        $genuine = $join([
            $investorText
                ? "{$name} has identified the following investor(s) for this round: {$investorText}."
                : "{$name} has not identified a participating investor.",
            $p?->investor_type ? 'Lead investor type: '.$p->investor_type.'.' : '',
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
            $p?->transaction_status ? 'Deal status on the profile: '.(EnterpriseProfile::TRANSACTION_STATUSES[$p->transaction_status] ?? $p->transaction_status).'.' : '',
            $area('transaction'),
            match (true) {
                ! $stage     => 'Conclusion: the stage of the transaction must be confirmed.',
                ! $committed => 'Conclusion: this is investor interest, not commitment — a signed LOI or later is required before the Committee.',
                default      => 'Conclusion: the transaction is sufficiently developed and the investor commitment is evidenced.',
            },
        ]);

        /* 3 · Structure & amount */
        $ratio      = EnterpriseProfile::grantRatio();
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
                ? 'The grant is within '.EnterpriseProfile::grantRatioLabel().' of the validated private capital (maximum '.$m($expected).').'
                : 'The grant does not match the '.EnterpriseProfile::grantRatioLabel().' matching rule (maximum '.$m($expected).').') : '',
            $p?->round_amount ? 'Current investment round: '.$m($p->round_amount).'.' : '',
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
        $budget    = $p?->budgetRows() ?? [];
        $totals    = EnterpriseProfile::sumBudget($budget);
        $budgetSet = $totals['private'] > 0 || $totals['grant'] > 0;
        $budgetLines = collect($budget)->filter(fn ($r) => (float) ($r['private'] ?? 0) + (float) ($r['grant'] ?? 0) > 0)
            ->map(fn ($r) => $r['category'].' — investor M '.number_format((float) ($r['private'] ?? 0)).', grant M '.number_format((float) ($r['grant'] ?? 0)))->all();
        $budgetPrivOk  = $budgetSet && abs($totals['private'] - $v['private']) < 1;
        $budgetGrantOk = $budgetSet && abs($totals['grant'] - $v['grant']) < 1;
        $uses = collect((array) $eoi->use_of_funds)->map(fn ($u) => self::USE_OF_FUNDS[$u] ?? $u)->all();

        $use = $join([
            $p?->investment_purpose ? 'Purpose: '.rtrim($p->investment_purpose, '.').'.' : ($eoi->use_of_funds_description ? 'Purpose: '.rtrim($eoi->use_of_funds_description, '.').'.' : ''),
            $uses ? 'Intended use (EOI): '.implode(', ', $uses).'.' : '',
            $budgetSet ? 'Grant utilisation budget: '.implode('; ', $budgetLines).'. Totals: investor M '.number_format($totals['private']).', CAFI grant M '.number_format($totals['grant']).'.'
                       : 'The grant utilisation budget has not been completed.',
            $budgetSet && ! $budgetPrivOk ? 'The investor column of the budget does not equal the validated private capital.' : '',
            $budgetSet && ! $budgetGrantOk ? 'The grant column of the budget does not equal the validated CAFI grant.' : '',
            $p?->document('budget') ? 'An operational budget is on file.' : 'No operational budget has been uploaded.',
            $form['esgc_category'] ? 'ESGC risk category: '.self::RATINGS[$form['esgc_category']].'.' : '',
            $area('esgc'),
            match (true) {
                ! $budgetSet                        => 'Conclusion: the use of funds is not yet clearly defined.',
                ! $budgetPrivOk || ! $budgetGrantOk => 'Conclusion: the budget must be aligned with the validated amounts.',
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

        return ['genuine' => $genuine, 'evidenced' => $evidenced, 'structure' => $structure, 'use' => $use, 'risks' => $risks];
    }

    /* ---------------- New assessment, pre-filled from the EOI / profile ---------------- */

    public static function prefill(ExpressionOfInterest $eoi, ?EnterpriseProfile $p): array
    {
        $rate = (float) config('cif.usd_to_lsl', 18.00);

        $stage = match ($p?->transaction_status ?? $eoi->investor_status) {
            'discussion', 'discussions', 'interest' => 'interest',
            'loi'                                   => 'loi',
            'term_sheet'                            => 'term_sheet',
            'agreement', 'committed'                => 'final_docs',
            default                                 => null,
        };
        // Profile financing type → Gate 2 instrument
        $instrument = match ($p?->participation) {
            'equity'                                    => 'equity',
            'convertible_note', 'safe', 'revenue_share' => 'quasi_equity',
            'other'                                     => 'other',
            default                                     => null,
        };

        return [
            'areas'             => [],
            'investor_names'    => (string) (collect($p?->investorRows() ?? [])->pluck('name')->implode(' · ') ?: ($p?->investor_name ?: $eoi->investor_name)),
            'stage'             => $stage,
            'instrument'        => isset(self::INSTRUMENTS[$instrument ?? '']) ? $instrument : null,
            'private_amount'    => $p?->investorAmount() ?? ($eoi->investor_amount_usd !== null ? round((float) $eoi->investor_amount_usd * $rate) : null),
            'grant_amount'      => $p?->grant_requested !== null ? (float) $p->grant_requested : null,
            'esgc_category'     => null,
            'summary'           => trim(($p?->registered_name ?: $eoi->business_name).($p?->sectorLabel() ? ' — '.$p->sectorLabel() : '').($p?->activity ? '. '.$p->activity : '')),
            'expected_outcomes' => '',
            'key_risks'         => '',
            'memo'              => [],
            'outcome'           => null,
            'conclusion'        => '',
            'assessed_on'       => now()->toDateString(),
        ];
    }
}