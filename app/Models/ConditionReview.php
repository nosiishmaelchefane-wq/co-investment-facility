<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Gate 6 · Conditions Precedent & Onboarding (§3.7) and Gate 7 · Grant Disbursement (§3.8) — one record per transaction.
 * Amounts in Maloti.
 *
 *   Gate 5 (both signatures) → 1 communicate approval & initiate onboarding (CP checklist)
 *                            → 2 verify conditions precedent (7 areas, documentary evidence)
 *                            → 3 confirm readiness for disbursement (programme + Finance) → Cleared / Pending / Returned
 *                            → 4 onboarding & post-disbursement obligations (baseline, enterprise briefed)
 *                            → Gate 7: per tranche — verify matching investment → payment approval → instruction → confirmation
 *   Principles: formal approval does not authorise payment; CAFI grant follows verified private capital.
 */
class ConditionReview extends Model
{
    /** Local disk folder for Gate 6/7 files */
    public const DISK = 'local';

    /* =====================================================================
     | Step 1 · Conditions precedent checklist
     ===================================================================== */

    /** Condition category (= verification area) + ongoing reporting obligations */
    public const AREAS = [
        'legal'       => 'Legal & registration',
        'agreement'   => 'Grant Agreement & approval record',
        'budget'      => 'Grant utilisation budget',
        'bank'        => 'Enterprise bank account',
        'investment'  => 'Private investment deployment',
        'contractual' => 'Other contractual conditions',
        'payment'     => 'Financial & payment controls',
        'reporting'   => 'Reporting & monitoring',
    ];

    /** Older condition categories (shown for records created before this version) */
    public const LEGACY_AREAS = [
        'registration' => 'Enterprise registration', 'tax' => 'Tax', 'esgc' => 'ESGC', 'investor' => 'Investor',
        'transaction' => 'Transaction', 'financing' => 'Financing', 'grant' => 'Grant utilisation',
    ];

    /** Gate 5 condition type → area */
    public const TYPE_TO_AREA = [
        'minor_doc' => 'contractual', 'legal_doc' => 'legal', 'esgc' => 'contractual',
        'investor'  => 'investment',  'cp'        => 'contractual', 'material' => 'contractual',
    ];

    /** Standard checklist items: [area, requirement, evidence needed, responsible, verifier, timing] */
    public const STANDARD_CONDITIONS = [
        ['legal',      'Registration and tax requirements in the Grant Agreement are met',  'OBFC registration certificate / extract; tax clearance or RSL tax file confirmation', 'enterprise', 'pmu',     'before_disbursement'],
        ['agreement',  'Executed Grant Agreement signed by the MD and the PS',              'Fully executed Grant Agreement (Gate 5)',                                          'pmu',        'grants',  'before_disbursement'],
        ['budget',     'Budget used for implementation matches the executed agreement',     'Approved grant utilisation budget annexed to the Grant Agreement',                  'grants',     'pmu',     'before_disbursement'],
        ['bank',       'Bank account in the enterprise’s name, as verified at Gate 4',      'Bank confirmation letter or statement',                                            'enterprise', 'finance', 'before_disbursement'],
        ['investment', 'Eligible private investment deployed',                              'Bank records / investor remittance confirmation',                                   'enterprise', 'pmu',     'tranche'],
        ['reporting',  'Reporting and monitoring obligations',                              'Reports as set out in the Grant Agreement',                                        'enterprise', 'pmu',     'ongoing'],
    ];

    public const TIMINGS = [
        'before_disbursement' => ['Before disbursement', 'Mandatory — must be met before the transaction is cleared'],
        'tranche'             => ['Before a tranche',    'Verified for each relevant tranche (Gate 7)'],
        'ongoing'             => ['Ongoing obligation',  'Continues after the grant is released — followed up, not a pre-disbursement condition'],
    ];

    public const PARTIES = [
        'enterprise' => 'Enterprise', 'investor' => 'Investor', 'pmu' => 'Programme team (PMU)',
        'grants' => 'Grants Management', 'finance' => 'Finance', 'legal' => 'Legal',
    ];

    public const CONDITION_STATUSES = [
        'outstanding' => ['Outstanding',    'pill-warn'],
        'met'         => ['Verified — met', 'pill-ok'],
        'not_met'     => ['Not satisfied',  'pill-danger'],
        'waived'      => ['Waived / N/A',   'pill-muted'],
    ];

    /* =====================================================================
     | Step 2 · Verification areas (§3.7.2)
     ===================================================================== */

    /** area => [title, required checks and evidence] */
    public const VERIFICATION_AREAS = [
        'legal'       => ['Legal and registration requirements',
            'Eligibility and registration requirements met; registration documents, tax documentation and other legal records in the Grant Agreement verified.'],
        'agreement'   => ['Grant Agreement and approval record',
            'Signed by both the CAFI Managing Director and the MTIBD Principal Secretary; the executed version is complete; grant amount, budget and conditions match the final authorisation record.'],
        'budget'      => ['Approved grant utilisation budget',
            'The budget used for implementation corresponds to the budget in the executed Grant Agreement. Changes go through change control first.'],
        'bank'        => ['Enterprise bank account',
            'Details match those verified at Gate 4 and the account is in the enterprise’s name. Any change after verification is independently revalidated and documented.'],
        'investment'  => ['Private investment deployment',
            'Documentary evidence (bank records, investor remittance confirmations) that eligible private investment was deployed; amount and timing reconciled against the approved transaction.'],
        'contractual' => ['Other contractual conditions',
            'Additional conditions in the Grant Agreement or approval record are fulfilled — only those that apply to this transaction.'],
        'payment'     => ['Financial and payment controls',
            'Proposed payment within the approved grant and funding limits; recipient matches the verified bank account; supporting documents and internal payment approvals in place.'],
    ];

    /** status => [label, pill, select class] */
    public const VERIFY_STATUSES = [
        'verified'       => ['Verified',       'pill-ok',     'is-complete'],
        'issue'          => ['Issue found',    'pill-danger', 'is-no'],
        'not_applicable' => ['Not applicable', 'pill-muted',  'is-not_applicable'],
    ];

    /* =====================================================================
     | Step 3 · Readiness for disbursement (§3.7.3)
     ===================================================================== */

    public const READINESS_CHECKS = [
        'conditions' => 'All mandatory conditions precedent are fulfilled and supported by adequate evidence',
        'agreement'  => 'The executed Grant Agreement and approved grant utilisation budget are consistent with the proposed payment',
        'investment' => 'The eligible private investment deployment requirement is satisfied under the Facility’s rules and the transaction arrangements',
        'bank'       => 'The bank account is verified and the payment details are consistent across the supporting documents',
        'limits'     => 'The proposed payment complies with the approved grant amount, cumulative funding limits and financial controls',
        'file'       => 'The disbursement file is complete and the required internal payment authorisations can be obtained',
    ];

    public const OUTCOME_CLEARED          = 'cleared';
    public const OUTCOME_PENDING          = 'pending_conditions';
    public const OUTCOME_RETURNED         = 'returned';
    public const OUTCOME_NOT_SATISFIED    = 'not_satisfied';      // escalated: material discrepancy / suspected misrepresentation
    public const OUTCOME_PENDING_INVESTOR = 'pending_investor';   // older records only

    /** outcome => [label, meaning, next step, icon, pill] */
    public const OUTCOMES = [
        self::OUTCOME_CLEARED          => ['Cleared for disbursement', 'All applicable conditions fulfilled and verified.',                                            'Gate 7 · payment processing',                  'bi-cash-coin',           'pill-ok'],
        self::OUTCOME_PENDING          => ['Pending conditions',       'One or more mandatory requirements remain outstanding.',                                       'Enterprise informed of the actions — on hold', 'bi-hourglass-split',     'pill-warn'],
        self::OUTCOME_RETURNED         => ['Returned for clarification or corrective action', 'Inconsistencies, incomplete documents or control concerns to resolve.', 'Resolve and re-verify',                       'bi-arrow-return-left',   'pill-warn'],
        self::OUTCOME_NOT_SATISFIED    => ['Escalated',                'Material discrepancy, suspected misrepresentation or a potential breach of programme rules.', 'Governance & financial control escalation',   'bi-exclamation-octagon', 'pill-danger'],
        self::OUTCOME_PENDING_INVESTOR => ['Pending investor deployment', 'Private investment not yet verified.',                                                      'No CAFI disbursement',                         'bi-shield-exclamation',  'pill-warn'],
    ];

    /** Outcomes that can be recorded now (pending_investor is kept for older records) */
    public const OUTCOME_CHOICES = [self::OUTCOME_CLEARED, self::OUTCOME_PENDING, self::OUTCOME_RETURNED, self::OUTCOME_NOT_SATISFIED];

    public const OUTCOME_TO_EOI_STATUS = [
        self::OUTCOME_CLEARED          => ExpressionOfInterest::STATUS_CLEARED,
        self::OUTCOME_PENDING          => ExpressionOfInterest::STATUS_AUTHORISED,
        self::OUTCOME_RETURNED         => ExpressionOfInterest::STATUS_AUTHORISED,
        self::OUTCOME_PENDING_INVESTOR => ExpressionOfInterest::STATUS_CAPITAL_PENDING,
        self::OUTCOME_NOT_SATISFIED    => ExpressionOfInterest::STATUS_ESCALATED,
    ];

    /* =====================================================================
     | Step 4 · Onboarding & post-disbursement obligations (§3.7.4)
     ===================================================================== */

    public const ONBOARDING_ITEMS = [
        'budget'     => 'Approved grant utilisation budget explained',
        'records'    => 'Financial record-keeping and expenditure reporting requirements',
        'reporting'  => 'Reporting frequency and first report date',
        'indicators' => 'Business performance indicators to be reported',
        'esg'        => 'Environmental, social and governance commitments (where applicable)',
        'changes'    => 'Procedure for notifying material changes to the enterprise or the use of grant funds',
    ];

    public const FREQUENCIES = ['monthly' => 'Monthly', 'quarterly' => 'Quarterly', 'biannual' => 'Bi-annually', 'annual' => 'Annually'];

    /** Baseline business & employment information confirmed at onboarding */
    public const BASELINE_FIELDS = [
        'employees'       => 'Total employees',
        'women'           => 'Women employed',
        'youth'           => 'Youth employed (18–35)',
        'annual_revenue'  => 'Annual revenue (M)',
        'monthly_revenue' => 'Average monthly revenue (M)',
    ];

    public const BUDGET_CATEGORIES = [
        'Equipment / capital expenditure', 'Working capital', 'Market expansion',
        'Technology / digitalisation', 'Product / service development', 'Other eligible growth expenditure',
    ];

    /* =====================================================================
     | Gate 7 · Grant disbursement (§3.8)
     ===================================================================== */

    /** Matching investment verification per deployment (§3.8.2) */
    public const DEPLOYMENT_CHECKS = [
        'amount'    => 'Amount actually invested',
        'date'      => 'Date of investment',
        'investor'  => 'Investor providing the capital',
        'account'   => 'Received into the enterprise’s verified account',
        'structure' => 'Consistent with the approved investment structure',
    ];

    public const DEPLOYMENT_STATUSES = [
        'submitted' => ['Awaiting verification', 'pill-warn'],
        'verified'  => ['Verified',              'pill-ok'],
        'rejected'  => ['Not accepted',          'pill-danger'],
    ];

    /** Tranche outcome (§3.8.5) => [label, meaning, next step, pill] */
    public const TRANCHE_OUTCOMES = [
        'approved'     => ['Disbursement approved',          'All requirements verified and payment authorised.',  'Grant released',                          'pill-ok'],
        'partial'      => ['Partial / tranche disbursement', 'Requirements satisfied for this investment tranche.', 'Corresponding grant released',            'pill-ok'],
        'pending'      => ['Pending verification',           'Required evidence is incomplete.',                     'Payment held',                            'pill-warn'],
        'not_eligible' => ['Not eligible for disbursement',  'A material requirement has not been satisfied.',     'Payment stopped — transaction escalated', 'pill-danger'],
    ];

    /** Agreement statuses (Grant / Investment / other) */
    public const AGREEMENT_STATUSES = [
        'drafting' => ['Drafting',           'pill-muted'],
        'sent'     => ['Sent for signature', 'pill-warn'],
        'executed' => ['Executed',           'pill-ok'],
    ];

    protected $fillable = [
        'expression_of_interest_id', 'authorisation_id', 'grant_amount', 'private_amount', 'instrument',
        'verification_checks', 'grant_clauses', 'readiness_checks', 'budget', 'onboarding', 'summary_sent_at', 'summary_sent_by',
        'approval_sent_at', 'approval_sent_by', 'verification', 'bank', 'bank_revalidated_at', 'bank_revalidated_by', 'bank_revalidation_note',
        'readiness_pmu_by', 'readiness_pmu_at', 'readiness_finance_by', 'readiness_finance_at',
        'baseline', 'onboarding_confirmed_at', 'onboarding_confirmed_by',
        'outcome', 'outcome_comments', 'outcome_on', 'decided_by', 'decided_at', 'cleared_at', 'history',
    ];

    protected $casts = [
        'verification_checks'     => 'array',
        'grant_clauses'           => 'array',
        'readiness_checks'        => 'array',
        'budget'                  => 'array',
        'onboarding'              => 'array',
        'verification'            => 'array',
        'bank'                    => 'array',
        'baseline'                => 'array',
        'history'                 => 'array',
        'summary_sent_at'         => 'datetime',
        'approval_sent_at'        => 'datetime',
        'bank_revalidated_at'     => 'datetime',
        'readiness_pmu_at'        => 'datetime',
        'readiness_finance_at'    => 'datetime',
        'onboarding_confirmed_at' => 'datetime',
        'outcome_on'              => 'date',
        'decided_at'              => 'datetime',
        'cleared_at'              => 'datetime',
        'grant_amount'            => 'decimal:2',
        'private_amount'          => 'decimal:2',
    ];

    /* ---------------- Relations ---------------- */

    public function expressionOfInterest(): BelongsTo { return $this->belongsTo(ExpressionOfInterest::class); }
    public function authorisation(): BelongsTo        { return $this->belongsTo(Authorisation::class); }
    public function decider(): BelongsTo              { return $this->belongsTo(User::class, 'decided_by'); }
    public function readinessPmu(): BelongsTo         { return $this->belongsTo(User::class, 'readiness_pmu_by'); }
    public function readinessFinance(): BelongsTo     { return $this->belongsTo(User::class, 'readiness_finance_by'); }
    public function conditions(): HasMany             { return $this->hasMany(CpCondition::class)->orderBy('position'); }
    public function agreements(): HasMany             { return $this->hasMany(CpAgreement::class)->orderBy('id'); }
    public function deployments(): HasMany            { return $this->hasMany(CapitalDeployment::class)->orderBy('deployed_on'); }
    public function tranches(): HasMany               { return $this->hasMany(DisbursementTranche::class)->orderBy('position'); }

    public static function areaLabel(?string $area): string
    {
        return self::AREAS[$area ?? ''] ?? self::LEGACY_AREAS[$area ?? ''] ?? ucfirst((string) $area);
    }

    /* ---------------- State & figures ---------------- */

    public function isCleared(): bool
    {
        return $this->outcome === self::OUTCOME_CLEARED;
    }

    public function releasedAmount(): float
    {
        return (float) $this->tranches()->whereNotNull('released_on')->sum('amount');
    }

    public function stillToDisburse(): float
    {
        return max(0, (float) $this->grant_amount - $this->releasedAmount());
    }

    public function isFullyDisbursed(): bool
    {
        return $this->isCleared() && (float) $this->grant_amount > 0 && $this->stillToDisburse() <= 0.01;
    }

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

    /** Pre-disbursement conditions only — ongoing obligations are not conditions precedent */
    public function mandatoryConditions()
    {
        return $this->conditions->filter(fn ($c) => ($c->timing ?? 'before_disbursement') !== 'ongoing');
    }

    /* ---------------- Matching (CAFI follows verified private capital) ---------------- */

    /** Most grant that may have been released once this much private capital is verified */
    public static function grantAllowedFor(float $verifiedCapital): float
    {
        return round($verifiedCapital * EnterpriseProfile::grantRatio(), 2);
    }

    /** Minimum verified capital for the grant released up to and including tranche $index */
    public static function minCapitalFor(array $amounts, int $index): float
    {
        $released = array_sum(array_map(fn ($a) => is_numeric($a) ? (float) $a : 0, array_slice(array_values($amounts), 0, $index + 1)));

        return round($released / max(EnterpriseProfile::grantRatio(), 0.0001), 2);
    }

    public function trancheMinimums(): array
    {
        $tr      = $this->tranches->values();
        $amounts = $tr->pluck('amount')->all();

        return $tr->mapWithKeys(fn ($t, $i) => [$t->id => self::minCapitalFor($amounts, $i)])->all();
    }

    public function requiredCapital(DisbursementTranche $t): float
    {
        return (float) ($this->trancheMinimums()[$t->id] ?? 0);
    }

    /**
     * Matching calculation for a tranche (§3.8.3): grant released before + this tranche ≤ ratio × verified private capital.
     * ['verified', 'allowed', 'released_before', 'after', 'ok', 'linked' (verified capital tied to this tranche), 'needed']
     */
    public function matchingFor(DisbursementTranche $t): array
    {
        $verified = $this->verifiedCapital();
        $before   = (float) $this->tranches->filter(fn ($x) => $x->isReleased() && $x->id !== $t->id)->sum('amount');
        $after    = $before + (float) $t->amount;
        $allowed  = self::grantAllowedFor($verified);

        return [
            'verified'        => $verified,
            'allowed'         => $allowed,
            'released_before' => $before,
            'after'           => $after,
            'ok'              => $after <= $allowed + 0.01,
            'linked'          => (float) $this->deployments->where('status', 'verified')->where('disbursement_tranche_id', $t->id)->sum('amount'),
            'needed'          => round($after / max(EnterpriseProfile::grantRatio(), 0.0001), 2),
        ];
    }

    /* ---------------- Facility controls (§3.8.4) ---------------- */

    /**
     * Maloti: ['enterprise_received', 'enterprise_approved', 'lifetime_cap', 'lifetime_remaining', 'approved', 'disbursed',
     *          'undisbursed', 'investor_cap' (null = none set), 'investors' => [[name, key, exposure]]]
     */
    public function controls(): array
    {
        $eoi    = $this->expressionOfInterest;
        $eoiIds = ExpressionOfInterest::where('user_id', $eoi->user_id)->pluck('id');

        $received = (float) DisbursementTranche::whereNotNull('released_on')
            ->whereHas('review', fn ($q) => $q->whereIn('expression_of_interest_id', $eoiIds))->sum('amount');
        $approved = (float) Authorisation::whereIn('expression_of_interest_id', $eoiIds)
            ->where('outcome', Authorisation::OUTCOME_AUTHORISED)->sum('grant_amount');
        $cap  = GrantReadiness::cumulativeCapLsl();
        $rate = max((float) config('cif.usd_to_lsl', 18.00), 0.0001);

        // Investor exposure: CAFI grants approved across the facility in transactions with the same investor (ID / name)
        $profile   = EnterpriseProfile::where('user_id', $eoi->user_id)->first();
        $investors = collect($profile?->investorRows() ?? [])->map(fn ($r) => [
            'name' => $r['name'],
            'key'  => EnterpriseInvestment::matchKey($r['id_number'] ?? null, $r['name'] ?? ''),
        ]);
        $exposure = [];
        if ($investors->isNotEmpty()) {
            $byUser = EnterpriseProfile::whereNotNull('round_investors')->get(['id', 'user_id', 'round_investors'])
                ->mapWithKeys(fn ($p) => [$p->user_id => collect($p->investorRows())
                    ->map(fn ($r) => EnterpriseInvestment::matchKey($r['id_number'] ?? null, $r['name'] ?? ''))->all()]);
            $grants = Authorisation::where('outcome', Authorisation::OUTCOME_AUTHORISED)
                ->with('expressionOfInterest:id,user_id')->get(['id', 'expression_of_interest_id', 'grant_amount']);

            foreach ($investors as $inv) {
                $exposure[] = $inv + ['exposure' => (float) $grants
                    ->filter(fn ($a) => in_array($inv['key'], $byUser[$a->expressionOfInterest?->user_id] ?? [], true))
                    ->sum('grant_amount')];
            }
        }

        $investorCapUsd = config('cif.co_investment.investor_cap_usd');

        return [
            'enterprise_received' => $received,
            'enterprise_approved' => $approved,
            'lifetime_cap'        => $cap,
            'lifetime_remaining'  => max(0, $cap - $approved),
            'approved'            => (float) $this->grant_amount,
            'disbursed'           => $this->releasedAmount(),
            'undisbursed'         => $this->stillToDisburse(),
            'investor_cap'        => $investorCapUsd ? (float) $investorCapUsd * $rate : null,
            'investors'           => $exposure,
        ];
    }

    /** Gate 7 · §3.8.1 disbursement requirements: key => [requirement, verification, ok] */
    public function disbursementRequirements(): array
    {
        $grantAgr = $this->agreements->firstWhere('kind', 'grant');
        $invAgr   = $this->agreements->firstWhere('kind', 'investment');
        $budget   = $this->budgetTotals();
        $c        = $this->controls();
        $esg      = $this->conditions->filter(fn ($x) => $x->area === 'esgc' || str_contains(strtolower((string) $x->finding), 'esg'));

        return [
            'authorisation'  => ['Final authorisation',   'Gate 5 approval recorded',                                         $this->authorisation?->outcome === Authorisation::OUTCOME_AUTHORISED],
            'conditions'     => ['Conditions precedent',  'All applicable conditions satisfied (Gate 6 cleared)',            $this->isCleared()],
            'grant_agr'      => ['Grant Agreement',       'Agreement duly executed',                                          $grantAgr?->status === 'executed'],
            'investment_agr' => ['Investment Agreement',  'Executed where applicable',                                        ! $invAgr || ! $invAgr->required || $invAgr->status === 'executed'],
            'capital'        => ['Investor capital',      'Matching private investment deployed and verified',                $this->verifiedCapital() > 0],
            'amount'         => ['Grant amount',          'Consistent with the approved transaction and matching calculation', (float) $this->grant_amount <= self::grantAllowedFor((float) $this->private_amount) + 0.01],
            'budget'         => ['Grant budget',          'Approved use of funds confirmed',                                  abs($budget['grant'] - (float) $this->grant_amount) <= 0.01],
            'payment_docs'   => ['Payment documentation', 'Payment and banking documentation complete',                      filled($this->bank['account_number'] ?? null) && ($this->verification['bank']['status'] ?? null) === 'verified'],
            'esgc'           => ['ESGC requirements',     'Applicable measures incorporated and/or satisfied',                $esg->every(fn ($x) => $x->isDone() || $x->timing === 'ongoing')],
            'exposure'       => ['Exposure limits',       'Enterprise and investor cumulative limits verified',               $c['enterprise_approved'] <= $c['lifetime_cap'] + 1
                && ($c['investor_cap'] === null || collect($c['investors'])->every(fn ($i) => $i['exposure'] <= $c['investor_cap'] + 1))],
        ];
    }

    /* ---------------- Clearance (Gate 6) ---------------- */

    public function summary(): array
    {
        $mand = $this->mandatoryConditions();

        return [
            'conditions_done'  => $mand->whereIn('status', ['met', 'waived'])->count(),
            'conditions_total' => $mand->count(),
            'ongoing'          => $this->conditions->where('timing', 'ongoing')->count(),
            'verified_areas'   => collect(self::VERIFICATION_AREAS)->keys()->filter(fn ($k) => in_array($this->verification[$k]['status'] ?? null, ['verified', 'not_applicable'], true))->count(),
            'capital'          => $this->verifiedCapital(),
            'onboarded'        => $this->onboarding_confirmed_at !== null,
        ];
    }

    /** What still stands between this transaction and "Cleared for disbursement" (empty = ready) */
    public function clearanceBlockers(): array
    {
        $s      = $this->summary();
        $open   = $s['conditions_total'] - $s['conditions_done'];
        $issues = collect(self::VERIFICATION_AREAS)->filter(fn ($a, $k) => ($this->verification[$k]['status'] ?? null) === 'issue');
        $notYet = collect(self::VERIFICATION_AREAS)->filter(fn ($a, $k) => blank($this->verification[$k]['status'] ?? null));
        $readyN = collect(self::READINESS_CHECKS)->keys()->reject(fn ($k) => ! empty($this->readiness_checks[$k]))->count();
        $grant  = (float) $this->grant_amount;
        $budget = $this->budgetTotals();
        $agr    = $this->agreements->firstWhere('kind', 'grant');
        $m      = fn ($v) => 'M '.number_format((float) $v);

        return array_values(array_filter([
            ! $this->approval_sent_at ? 'Step 1: communicate the grant approval and send the executed Grant Agreement to the enterprise.' : null,
            $open ? "{$open} mandatory ".($open === 1 ? 'condition precedent is' : 'conditions precedent are').' still outstanding.' : null,
            $agr?->status !== 'executed' ? 'The executed Grant Agreement (both signatures) is not on file.' : null,
            ($unsigned = $this->agreements->filter(fn ($x) => $x->kind !== 'grant' && $x->required && $x->status !== 'executed'))->isNotEmpty()
                ? 'Not executed yet: '.$unsigned->pluck('title')->implode(', ').' — upload the signed copy and mark it Executed, or untick "Required before disbursement" if it does not apply.' : null,
            $issues->isNotEmpty() ? 'Step 2: resolve the issues found in: '.$issues->map(fn ($a) => $a[0])->implode(', ').'.' : null,
            $notYet->isNotEmpty() ? 'Step 2: verify every area ('.$notYet->count().' not verified yet).' : null,
            $grant > 0 && abs($budget['grant'] - $grant) > 0.01 ? 'The grant utilisation budget ('.$m($budget['grant']).') does not match the approved grant ('.$m($grant).').' : null,
            ! empty($this->bank['changed']) && ! $this->bank_revalidated_at ? 'The bank account changed after Gate 4 — revalidate it independently.' : null,
            $s['capital'] <= 0 ? 'No eligible private investment deployment has been verified yet.' : null,
            $readyN ? "Step 3: complete the readiness confirmations ({$readyN} open)." : null,
            ! $this->readiness_pmu_at ? 'Step 3: programme confirmation of disbursement readiness.' : null,
            ! $this->readiness_finance_at ? 'Step 3: Finance confirmation of disbursement readiness.' : null,
            ! $this->onboarding_confirmed_at ? 'Step 4: the enterprise confirms it was briefed on its obligations.' : null,
        ]));
    }

    /** Clearance needs everything except the two Step 3 confirmations (they confirm this list is empty) */
    public function blockersBeforeConfirmation(): array
    {
        return array_values(array_filter($this->clearanceBlockers(), fn ($b) => ! str_contains($b, 'confirmation of disbursement readiness')));
    }

    public function suggestedOutcome(): string
    {
        return $this->clearanceBlockers() === [] ? self::OUTCOME_CLEARED : self::OUTCOME_PENDING;
    }

    /* ---------------- Start ---------------- */

    /** Create Gate 6 for a formally approved transaction: CP checklist, budget, Gate 4 bank details, executed agreement */
    public static function start(ExpressionOfInterest $eoi): self
    {
        $auth      = $eoi->authorisations()->where('outcome', Authorisation::OUTCOME_AUTHORISED)->orderByDesc('round')->first();
        $readiness = GrantReadiness::where('expression_of_interest_id', $eoi->id)->where('outcome', GrantReadiness::OUTCOME_APPROVED)->orderByDesc('round')->first();
        $profile   = $eoi->user?->enterpriseProfile;

        $review = static::create([
            'expression_of_interest_id' => $eoi->id,
            'authorisation_id'          => $auth?->id,
            'grant_amount'              => $auth?->grant_amount,
            'private_amount'            => $auth?->private_amount,
            'instrument'                => $auth?->instrument,
            'budget'                    => $profile?->grant_budget
                                               ? $profile->budgetRows()
                                               : collect(self::BUDGET_CATEGORIES)->map(fn ($c) => ['category' => $c, 'private' => null, 'grant' => null])->all(),
            'bank'                      => $readiness?->bank ? array_merge($readiness->bank, ['source' => 'gate4', 'changed' => false]) : null,
            'onboarding'                => ['contact' => '', 'frequency' => 'quarterly', 'first_report_on' => null, 'items' => []],
            'baseline'                  => [
                'employees'       => $profile?->employees,
                'women'           => $profile ? (((int) $profile->ft_female + (int) $profile->pt_female) ?: null) : null,
                'youth'           => $profile ? (((int) $profile->youth_male + (int) $profile->youth_female) ?: null) : null,
                'annual_revenue'  => $profile?->annual_revenue,
                'monthly_revenue' => null,
                'as_of'           => now()->toDateString(),
            ],
        ]);

        // Checklist: standard items + conditions from the approval record (Gates 2–5)
        $pos = 0;
        foreach (self::STANDARD_CONDITIONS as [$area, $req, $evidence, $resp, $verifier, $timing]) {
            $review->conditions()->create([
                'area' => $area, 'finding' => $req, 'evidence_required' => $evidence, 'responsible' => $resp,
                'verifier' => $verifier, 'timing' => $timing, 'source' => 'g6', 'position' => $pos++,
            ]);
        }
        foreach (array_values($auth?->conditions ?? []) as $c) {
            $review->conditions()->create([
                'area'        => self::TYPE_TO_AREA[$c['type'] ?? ''] ?? 'contractual',
                'finding'     => Str::limit((string) ($c['finding'] ?? ''), 250, ''),
                'source'      => $c['source'] ?? 'g4',
                'responsible' => 'enterprise',
                'verifier'    => 'pmu',
                'timing'      => ($c['type'] ?? null) === 'esgc' ? 'ongoing' : 'before_disbursement',
                'status'      => ($c['status'] ?? 'open') === 'resolved' ? 'met' : 'outstanding',
                'position'    => $pos++,
            ]);
        }

        // Agreements: the executed Grant Agreement is the Gate 5 copy signed by the Principal Secretary
        $investors = $profile?->investor_name ?: $eoi->investor_name;
        $grant = $review->agreements()->create([
            'kind' => 'grant', 'title' => 'CAFI Grant Agreement', 'parties' => 'CAFI · MTIBD · '.($eoi->business_name ?: 'Enterprise'), 'required' => true,
        ]);
        if ($auth?->ps_signed_path && Storage::disk(Authorisation::DISK)->exists($auth->ps_signed_path)) {
            $copy = "gate5/{$review->id}/agreements/".Str::random(8).'-'.basename($auth->ps_signed_path);
            Storage::disk(self::DISK)->put($copy, Storage::disk(Authorisation::DISK)->get($auth->ps_signed_path));
            $grant->update([
                'status'    => 'executed',
                'signed_on' => $auth->ps_on ?? $auth->ps_decided_at,
                'file_path' => $copy,
                'file_name' => 'Executed Grant Agreement - '.($auth->ps_signed_name ?: basename($auth->ps_signed_path)),
            ]);
        }
        $review->agreements()->create([
            'kind' => 'investment', 'title' => 'Investment Agreement', 'parties' => trim(($investors ?: 'Investor').' · '.($eoi->business_name ?: 'Enterprise')), 'required' => true,
        ]);

        return $review;
    }
}