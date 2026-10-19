<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Gate 5 · Conditions Precedent, Grant Agreement & Onboarding (CI Facility Operations Manual §3.6).
 * One per transaction. Amounts in Maloti.
 *
 *   Gate 4 → conditions precedent → Grant & Investment Agreements → verify private capital deployment
 *          → disbursement readiness → onboarding → CAFI grant disbursement (tranches) → portfolio monitoring
 *   Principle: authorisation lets the transaction proceed; met conditions + verified private capital let CAFI disburse.
 */
class ConditionReview extends Model
{
    /* ---------------- §3.6.1 Conditions ---------------- */

    public const AREAS = [
        'legal'        => 'Legal',
        'registration' => 'Enterprise registration',
        'tax'          => 'Tax',
        'esgc'         => 'ESGC',
        'investor'     => 'Investor',
        'transaction'  => 'Transaction',
        'financing'    => 'Financing',
        'grant'        => 'Grant utilisation',
        'reporting'    => 'Reporting',
    ];

    /** area => indicative condition */
    public const INDICATIVE = [
        'legal'        => 'Execution of required investment and grant documentation',
        'registration' => 'Completion of required registration or statutory formalities',
        'tax'          => 'Opening or confirmation of the required tax file',
        'esgc'         => 'Completion of agreed mitigation measures',
        'investor'     => 'Confirmation of investor commitment and investor-side conditions',
        'transaction'  => 'Execution of final investment documentation',
        'financing'    => 'Evidence that the required private capital has been deployed',
        'grant'        => 'Confirmation of the approved grant budget and eligible expenditure',
        'reporting'    => 'Confirmation of monitoring and reporting obligations',
    ];

    /** Gate 4 condition type → Gate 5 area */
    public const TYPE_TO_AREA = [
        'minor_doc' => 'transaction', 'legal_doc' => 'legal', 'esgc' => 'esgc',
        'investor'  => 'investor',    'cp'        => 'legal', 'material' => 'transaction',
    ];

    public const CONDITION_STATUSES = [
        'outstanding' => ['Outstanding', 'pill-warn'],
        'met'         => ['Met',         'pill-ok'],
        'waived'      => ['Waived',      'pill-muted'],
    ];

    /* ---------------- §3.6.2 Investor capital ---------------- */

    public const VERIFICATION_CHECKS = [
        'amount'      => 'The amount of private capital actually deployed',
        'identity'    => 'The identity of the investor(s) providing the capital',
        'structure'   => 'Consistency with the approved investment structure',
        'evidence'    => 'Evidence of deployment',
        'commitment'  => 'The deployment corresponds to the commitment assessed under Gate 2',
        'conditions'  => 'The conditions for CAFI disbursement have consequently been met',
    ];

    public const DEPLOYMENT_STATUSES = [
        'submitted' => ['Awaiting verification', 'pill-warn'],
        'verified'  => ['Verified',              'pill-ok'],
        'rejected'  => ['Not accepted',          'pill-danger'],
    ];

    /* ---------------- §3.6.3 Agreements ---------------- */

    public const AGREEMENT_STATUSES = [
        'drafting' => ['Drafting',          'pill-muted'],
        'sent'     => ['Sent for signature', 'pill-warn'],
        'executed' => ['Executed',          'pill-ok'],
    ];

    public const GRANT_CLAUSES = [
        'amount'        => 'Approved CAFI grant amount',
        'purpose'       => 'Purpose and permitted use of grant resources',
        'categories'    => 'Approved expenditure categories',
        'disbursement'  => 'Conditions of disbursement',
        'reporting'     => 'Reporting and monitoring obligations',
        'esgc'          => 'ESGC requirements and mitigation measures',
        'finance'       => 'Financial management and record-keeping',
        'noncompliance' => 'Provisions on non-compliance',
        'audit'         => 'Audit and verification rights',
        'specific'      => 'Transaction-specific conditions from the approval process',
    ];

    /* ---------------- §3.6.4 Readiness ---------------- */

    public const READINESS_CHECKS = [
        'amount'      => 'The approved grant amount',
        'budget'      => 'The agreed grant utilisation budget',
        'categories'  => 'Eligible expenditure categories',
        'conditions'  => 'Any expenditure conditions',
        'timing'      => 'The timing and basis of disbursement',
        'capital'     => 'The status of private capital deployment',
        'outstanding' => 'Any outstanding conditions',
    ];

    public const BUDGET_CATEGORIES = [
        'Equipment / capital expenditure', 'Working capital', 'Market expansion',
        'Technology / digitalisation', 'Product / service development', 'Other eligible growth expenditure',
    ];

    /* ---------------- §3.6.5 Onboarding ---------------- */

    public const ONBOARDING_ITEMS = [
        'contacts'      => 'Designated enterprise contact persons',
        'grant_reports' => 'Grant reporting requirements',
        'fin_reports'   => 'Financial reporting requirements',
        'esgc'          => 'ESGC obligations',
        'indicators'    => 'Monitoring indicators and reporting frequency',
        'records'       => 'Documentation and record-retention requirements',
        'escalation'    => 'Communication and escalation arrangements',
        'support'       => 'Post-investment support or facilitation arrangements',
    ];

    public const FREQUENCIES = ['monthly' => 'Monthly', 'quarterly' => 'Quarterly', 'biannual' => 'Bi-annually', 'annual' => 'Annually'];

    /* ---------------- §3.6.6 Outcomes ---------------- */

    public const OUTCOME_CLEARED          = 'cleared';
    public const OUTCOME_PENDING          = 'pending_conditions';
    public const OUTCOME_PENDING_INVESTOR = 'pending_investor';
    public const OUTCOME_NOT_SATISFIED    = 'not_satisfied';

    /** outcome => [label, meaning, next step, icon, pill] */
    public const OUTCOMES = [
        self::OUTCOME_CLEARED          => ['Cleared for disbursement',    'Conditions satisfied, agreements executed and private capital deployment verified.', 'CAFI grant disbursement',                         'bi-cash-coin',          'pill-ok'],
        self::OUTCOME_PENDING          => ['Pending conditions',          'One or more conditions remain outstanding.',                                           'On hold until resolved',                          'bi-hourglass-split',    'pill-warn'],
        self::OUTCOME_PENDING_INVESTOR => ['Pending investor deployment', 'Private investment has not yet been verified.',                                        'No CAFI disbursement — monitor the transaction',  'bi-shield-exclamation', 'pill-warn'],
        self::OUTCOME_NOT_SATISFIED    => ['Not satisfied',               'A material condition cannot be met, or circumstances have materially changed.',        'Escalation / review under programme procedures',  'bi-x-octagon',          'pill-danger'],
    ];

    public const OUTCOME_TO_EOI_STATUS = [
        self::OUTCOME_CLEARED          => ExpressionOfInterest::STATUS_CLEARED,
        self::OUTCOME_PENDING          => ExpressionOfInterest::STATUS_AUTHORISED,
        self::OUTCOME_PENDING_INVESTOR => ExpressionOfInterest::STATUS_CAPITAL_PENDING,
        self::OUTCOME_NOT_SATISFIED    => ExpressionOfInterest::STATUS_ESCALATED,
    ];

    /** Local disk folder for Gate 5 files */
    public const DISK = 'local';

    protected $fillable = [
        'expression_of_interest_id', 'authorisation_id', 'grant_amount', 'private_amount', 'instrument',
        'verification_checks', 'grant_clauses', 'readiness_checks', 'budget', 'onboarding', 'summary_sent_at', 'summary_sent_by',
        'outcome', 'outcome_comments', 'outcome_on', 'decided_by', 'decided_at', 'cleared_at', 'history',
    ];

    protected $casts = [
        'verification_checks' => 'array',
        'grant_clauses'       => 'array',
        'readiness_checks'    => 'array',
        'budget'              => 'array',
        'onboarding'          => 'array',
        'history'             => 'array',
        'summary_sent_at'     => 'datetime',
        'outcome_on'          => 'date',
        'decided_at'          => 'datetime',
        'cleared_at'          => 'datetime',
        'grant_amount'        => 'decimal:2',
        'private_amount'      => 'decimal:2',
    ];

    /* ---------------- Relations ---------------- */

    public function expressionOfInterest(): BelongsTo { return $this->belongsTo(ExpressionOfInterest::class); }
    public function authorisation(): BelongsTo        { return $this->belongsTo(Authorisation::class); }
    public function decider(): BelongsTo              { return $this->belongsTo(User::class, 'decided_by'); }
    public function conditions(): HasMany             { return $this->hasMany(CpCondition::class)->orderBy('position'); }
    public function agreements(): HasMany             { return $this->hasMany(CpAgreement::class)->orderBy('id'); }
    public function deployments(): HasMany            { return $this->hasMany(CapitalDeployment::class)->orderBy('deployed_on'); }
    public function tranches(): HasMany               { return $this->hasMany(DisbursementTranche::class)->orderBy('position'); }

    /** Grant released so far (sum of released tranches) */
    public function releasedAmount(): float
    {
        return (float) $this->tranches()->whereNotNull('released_on')->sum('amount');
    }

    /** Grant still to be disbursed (approved grant − released) */
    public function stillToDisburse(): float
    {
        return max(0, (float) $this->grant_amount - $this->releasedAmount());
    }

    public function isCleared(): bool
    {
        return $this->outcome === self::OUTCOME_CLEARED;
    }

    /* ---------------- Figures ---------------- */

    public function verifiedCapital(): float
    {
        return (float) $this->deployments->where('status', 'verified')->sum('amount');
    }

    public function budgetTotals(): array
    {
        $rows = collect($this->budget ?? []);

        return [
            'private' => (float) $rows->sum(fn ($r) => (float) ($r['private'] ?? 0)),
            'grant'   => (float) $rows->sum(fn ($r) => (float) ($r['grant'] ?? 0)),
        ];
    }

    /* ---------------- Tranches vs investor capital (matching rule) ---------------- */

    /**
     * Minimum verified investor capital before a tranche can be released: everything CAFI will have paid up to and
     * including that tranche, divided by the matching ratio (50% → twice the grant released so far).
     *   $amounts = tranche amounts in release order.
     */
    public static function minCapitalFor(array $amounts, int $index): float
    {
        $released = array_sum(array_map(fn ($a) => is_numeric($a) ? (float) $a : 0, array_slice(array_values($amounts), 0, $index + 1)));

        return round($released / max(EnterpriseProfile::grantRatio(), 0.0001), 2);
    }

    /** tranche id => minimum investor capital (Maloti) */
    public function trancheMinimums(): array
    {
        $tr      = $this->tranches->values();
        $amounts = $tr->pluck('amount')->all();

        return $tr->mapWithKeys(fn ($t, $i) => [$t->id => self::minCapitalFor($amounts, $i)])->all();
    }

    /** What must be verified before this tranche is released — never below the matching rule */
    public function requiredCapital(DisbursementTranche $t): float
    {
        return max((float) $t->capital_required, (float) ($this->trancheMinimums()[$t->id] ?? 0));
    }

    /** Counts used in the header tiles */
    public function summary(): array
    {
        $c = $this->conditions;
        $a = $this->agreements->where('required', true);

        return [
            'conditions_done'  => $c->whereIn('status', ['met', 'waived'])->count(),
            'conditions_total' => $c->count(),
            'agreements_done'  => $a->where('status', 'executed')->count(),
            'agreements_total' => $a->count(),
            'capital'          => $this->verifiedCapital(),
            'onboarded'        => $this->summary_sent_at !== null,
        ];
    }

    /** What still stands between this transaction and "Cleared for disbursement" (empty = ready) */
    public function clearanceBlockers(): array
    {
        $s       = $this->summary();
        $open    = $s['conditions_total'] - $s['conditions_done'];
        $agrOpen = $s['agreements_total'] - $s['agreements_done'];
        $verifyN = collect(self::VERIFICATION_CHECKS)->keys()->reject(fn ($k) => ! empty($this->verification_checks[$k]))->count();
        $readyN  = collect(self::READINESS_CHECKS)->keys()->reject(fn ($k) => ! empty($this->readiness_checks[$k]))->count();
        $clauseN = collect(self::GRANT_CLAUSES)->keys()->reject(fn ($k) => ! empty($this->grant_clauses[$k]))->count();
        $onbN    = collect(self::ONBOARDING_ITEMS)->keys()->reject(fn ($k) => ! empty($this->onboarding['items'][$k] ?? null))->count();
        $budget  = $this->budgetTotals();
        $grant   = (float) $this->grant_amount;
        $tr      = $this->tranches;
        $trTotal = (float) $tr->sum('amount');
        $m       = fn ($v) => 'M '.number_format((float) $v);

        return array_values(array_filter([
            $open ? "{$open} ".($open === 1 ? 'condition precedent is' : 'conditions precedent are').' outstanding.' : null,
            $agrOpen ? "{$agrOpen} required ".($agrOpen === 1 ? 'agreement is' : 'agreements are').' not executed yet.' : null,
            $clauseN ? "Confirm the Grant Agreement covers all required provisions ({$clauseN} not confirmed)." : null,
            $s['capital'] <= 0 ? 'No investor capital deployment has been verified.' : null,
            $verifyN ? "Complete the investor capital verification checks ({$verifyN} open)." : null,
            $grant > 0 && abs($budget['grant'] - $grant) > 0.01 ? 'Grant utilisation budget ('.$m($budget['grant']).') does not match the approved grant ('.$m($grant).').' : null,
            $readyN ? "Complete the disbursement readiness confirmations ({$readyN} open)." : null,
            $tr->isEmpty() ? 'Set up at least one disbursement tranche.' : null,
            // Tranches are released one by one — they need not add up to the grant yet, but may never exceed it
            $grant > 0 && $trTotal - $grant > 0.01 ? 'Tranches ('.$m($trTotal).') are more than the approved grant ('.$m($grant).').' : null,
            // Investor capital per tranche is handled outside the system — no "investor capital needed" check on tranches
            blank($this->onboarding['contact'] ?? null) ? 'Name the enterprise contact person.' : null,
            $onbN ? "Agree all onboarding arrangements ({$onbN} open)." : null,
            ! $this->summary_sent_at ? 'Send the summary of obligations to the enterprise.' : null,
        ]));
    }

    /** The outcome the current state points to */
    public function suggestedOutcome(): string
    {
        $s = $this->summary();

        return match (true) {
            $s['conditions_done'] < $s['conditions_total'] || $s['agreements_done'] < $s['agreements_total'] => self::OUTCOME_PENDING,
            $s['capital'] <= 0                                                                                 => self::OUTCOME_PENDING_INVESTOR,
            $this->clearanceBlockers() === []                                                                  => self::OUTCOME_CLEARED,
            default                                                                                            => self::OUTCOME_PENDING,
        };
    }

    /* ---------------- Start ---------------- */

    /** Create Gate 5 for an authorised transaction, carrying the Gate 4 conditions across */
    public static function start(ExpressionOfInterest $eoi): self
    {
        $auth = $eoi->authorisations()->where('outcome', Authorisation::OUTCOME_AUTHORISED)->orderByDesc('round')->first();

        $review = static::create([
            'expression_of_interest_id' => $eoi->id,
            'authorisation_id'          => $auth?->id,
            'grant_amount'              => $auth?->grant_amount,
            'private_amount'            => $auth?->private_amount,
            'instrument'                => $auth?->instrument,
            // The enterprise filled in the grant utilisation budget on its profile — start from it (the PMU edits only if needed)
            'budget'                    => $eoi->user?->enterpriseProfile?->grant_budget
                                               ? $eoi->user->enterpriseProfile->budgetRows()
                                               : collect(self::BUDGET_CATEGORIES)->map(fn ($c) => ['category' => $c, 'private' => null, 'grant' => null])->all(),
            'onboarding'                => ['contact' => '', 'frequency' => 'quarterly', 'first_report_on' => null, 'items' => []],
        ]);

        foreach (array_values($auth?->conditions ?? []) as $i => $c) {
            $review->conditions()->create([
                'area'     => self::TYPE_TO_AREA[$c['type'] ?? ''] ?? 'transaction',
                'finding'  => \Illuminate\Support\Str::limit((string) ($c['finding'] ?? ''), 250, ''),
                'source'   => $c['source'] ?? 'g4',
                'status'   => ($c['status'] ?? 'open') === 'resolved' ? 'met' : 'outstanding',
                'position' => $i,
            ]);
        }

        $investors = $eoi->user?->enterpriseProfile?->investor_name ?: $eoi->investor_name;
        $review->agreements()->createMany([
            ['kind' => 'grant',      'title' => 'CAFI Grant Agreement', 'parties' => 'CAFI · '.($eoi->business_name ?: 'Enterprise'), 'required' => true],
            ['kind' => 'investment', 'title' => 'Investment Agreement', 'parties' => trim(($investors ?: 'Investor').' · '.($eoi->business_name ?: 'Enterprise')), 'required' => true],
        ]);

        return $review;
    }
}