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
 *   4. Summary + IAM  — for the Investment Committee
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
        'summary', 'expected_outcomes', 'key_risks', 'outcome', 'conclusion', 'assessed_on',
    ];

    protected $fillable = [
        'expression_of_interest_id', 'round', 'assessor_id', 'started_on', ...self::FORM_COLUMNS,
        'iam_path', 'iam_name', 'iam_size', 'iam_uploaded_by', 'iam_uploaded_at', 'decided_at', 'decided_by',
    ];

    protected $casts = [
        'areas'           => 'array',
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

    /** Steps done: 6 areas + key figures + summary + memorandum */
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
            ! $hasIam ? 'Upload the Investment Appraisal Memorandum.' : null,
            ...collect($conditions)->filter(fn ($c) => DueDiligenceCondition::blocks($c))
                ->map(fn ($c) => 'Open: '.($c['finding'] ?: 'untitled condition').' — must be resolved before the Committee.')->values()->all(),
        ]));
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
            'investor_names'    => (string) ($p?->investor_name ?: $eoi->investor_name),
            'stage'             => $stage,
            'instrument'        => isset(self::INSTRUMENTS[$instrument ?? '']) ? $instrument : null,
            'private_amount'    => ($p?->investor_contribution ?? $p?->round_amount) !== null ? (float) ($p->investor_contribution ?: $p->round_amount)
                                 : ($eoi->investor_amount_usd !== null ? round((float) $eoi->investor_amount_usd * $rate) : null),
            'grant_amount'      => $p?->grant_requested !== null ? (float) $p->grant_requested : null,
            'esgc_category'     => null,
            'summary'           => trim(($p?->registered_name ?: $eoi->business_name).($p?->sector ? ' — '.$p->sector : '').($p?->activity ? '. '.$p->activity : '')),
            'expected_outcomes' => '',
            'key_risks'         => '',
            'outcome'           => null,
            'conclusion'        => '',
            'assessed_on'       => now()->toDateString(),
        ];
    }
}