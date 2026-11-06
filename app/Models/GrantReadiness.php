<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Gate 4 · Grant Readiness Review (CI Facility Operations Manual §3.5). One row per round. Amounts in Maloti.
 *
 *   Gate 3 recommendation → 1 Grants Management review → 2 Hub Manager → 3 PMU Director of Implementation → Gate 5
 *   Internal control point: grant compliance, budget, disbursement readiness, completeness of the file.
 *   It does not repeat the Investment Committee's assessment, and it is not authorisation.
 */
class GrantReadiness extends Model
{
    protected $table = 'grant_readinesses';

    public const DISK   = 'local';
    public const MAX_KB = 20480;

    public const AGREEMENT_MIMES = 'pdf,docx,doc';
    public const EVIDENCE_MIMES  = 'pdf,jpg,jpeg,png';

    /* ---------------- §3.5.1 Grants Management review ---------------- */

    /** check => [short title, what the review confirms] */
    public const CHECKS = [
        'grant_amount' => ['Grant amount',
            'The proposed grant amount is consistent with the Investment Committee recommendation, the matching formula and the Facility’s cumulative grant limit.'],
        'eligible'     => ['Eligible expenditure',
            'Proposed expenditure falls within eligible grant categories, is adequately justified and aligns with the enterprise’s growth activities.'],
        'budget'       => ['Budget & spending in Lesotho',
            'The budget is sufficiently detailed and reasonable, and at least 70% of grant funds are allocated to expenditure in Lesotho. Any expenditure outside Lesotho is justified.'],
        'documents'    => ['Supporting documentation',
            'Required supporting documentation is available and consistent with the information reviewed at earlier stages.'],
        'bank'         => ['Bank account',
            'The bank account is verified against supporting documentation, held in the enterprise’s name and suitable for grant disbursements. Discrepancies are resolved.'],
        'outstanding'  => ['Outstanding matters',
            'Outstanding conditions, clarifications or documentation requirements are identified and recorded, with clear responsibility for follow-up.'],
    ];

    /** Each check is recorded as a concise confirmation, exception or action required. status => [label, pill, icon] */
    public const CHECK_STATUSES = [
        'confirmed' => ['Confirmed',       'pill-ok',     'bi-check-circle'],
        'exception' => ['Exception',       'pill-warn',   'bi-exclamation-circle'],
        'action'    => ['Action required', 'pill-danger', 'bi-x-circle'],
    ];

    public const ACCOUNT_TYPES = ['current' => 'Current / cheque', 'savings' => 'Savings', 'business' => 'Business account', 'other' => 'Other'];

    /** What the bank details were verified against */
    public const BANK_EVIDENCE = [
        'bank_letter'     => 'Bank confirmation letter',
        'bank_statement'  => 'Recent bank statement',
        'cancelled_cheque'=> 'Cancelled cheque / deposit slip',
        'other'           => 'Other document',
    ];

    public const RECOMMENDATIONS = [
        'ready'     => ['Ready to Proceed',     'pill-ok',     'bi-check2-circle'],
        'not_ready' => ['Not Ready to Proceed', 'pill-danger', 'bi-x-octagon'],
    ];

    public const PARTIES = ['enterprise' => 'Enterprise', 'investor' => 'Investor', 'grants' => 'Grants Management', 'pmu' => 'PMU', 'legal' => 'Legal'];

    /* ---------------- §3.5.2 / §3.5.3 Approvals ---------------- */

    /** decision => [label, description, icon, pill] */
    public const HUB_DECISIONS = [
        'approved' => ['Approved for submission',  'The file goes to the PMU Director of Implementation.',              'bi-send-check',        'pill-ok'],
        'returned' => ['Returned for further action', 'Back to Grants Management (or the responsible party) to resolve.', 'bi-arrow-return-left', 'pill-warn'],
    ];

    public const DIRECTOR_DECISIONS = [
        'approved' => ['Approved to proceed',      'Cleared to advance to Gate 5 – Final Authorisation.',                'bi-patch-check',       'pill-ok'],
        'returned' => ['Returned for clarification or corrective action', 'Back to the responsible party before resubmission.', 'bi-arrow-return-left', 'pill-warn'],
    ];

    public const OUTCOME_APPROVED = 'approved';
    public const OUTCOME_RETURNED = 'returned';

    /** stage => [title, who] */
    public const STAGES = [
        'grants'   => ['Grants Management review', 'Grants Management Representative'],
        'hub'      => ['Hub Manager review',       'Hub Manager'],
        'director' => ['PMU Director of Implementation review', 'PMU Director of Implementation'],
        'decided'  => ['Complete',                 ''],
    ];

    /** Permission needed to act at each stage */
    public const STAGE_PERMISSION = ['grants' => 'readiness.review', 'hub' => 'readiness.hub', 'director' => 'readiness.director'];

    protected $fillable = [
        'expression_of_interest_id', 'committee_review_id', 'round', 'resubmission_note', 'private_amount', 'grant_amount',
        'checks', 'lesotho_pct', 'bank', 'bank_evidence_path', 'bank_evidence_name',
        'agreement_path', 'agreement_name', 'agreement_size', 'agreement_uploaded_at',
        'issues', 'recommendation', 'justification', 'gm_by', 'gm_on', 'gm_submitted_at',
        'hub_decision', 'hub_comments', 'hub_on', 'hub_by', 'hub_decided_at',
        'director_decision', 'director_comments', 'director_on', 'director_by', 'director_decided_at',
        'outcome', 'decided_at',
    ];

    protected $casts = [
        'checks'                => 'array',
        'bank'                  => 'array',
        'issues'                => 'array',
        'lesotho_pct'           => 'decimal:2',
        'private_amount'        => 'decimal:2',
        'grant_amount'          => 'decimal:2',
        'gm_on'                 => 'date',
        'hub_on'                => 'date',
        'director_on'           => 'date',
        'gm_submitted_at'       => 'datetime',
        'hub_decided_at'        => 'datetime',
        'director_decided_at'   => 'datetime',
        'agreement_uploaded_at' => 'datetime',
        'decided_at'            => 'datetime',
    ];

    /* ---------------- Relations ---------------- */

    public function expressionOfInterest(): BelongsTo { return $this->belongsTo(ExpressionOfInterest::class); }
    public function committeeReview(): BelongsTo      { return $this->belongsTo(CommitteeReview::class); }
    public function grantsOfficer(): BelongsTo        { return $this->belongsTo(User::class, 'gm_by'); }
    public function hubManager(): BelongsTo           { return $this->belongsTo(User::class, 'hub_by'); }
    public function director(): BelongsTo             { return $this->belongsTo(User::class, 'director_by'); }

    /* ---------------- State ---------------- */

    public function isDecided(): bool
    {
        return $this->decided_at !== null;
    }

    public function isApproved(): bool
    {
        return $this->outcome === self::OUTCOME_APPROVED;
    }

    /** grants | hub | director | decided */
    public function stage(): string
    {
        return match (true) {
            $this->isDecided()                                         => 'decided',
            $this->hub_decision === 'approved' && $this->hub_decided_at => 'director',
            $this->gm_submitted_at !== null                            => 'hub',
            default                                                    => 'grants',
        };
    }

    /** Who returned the round ('hub' | 'director' | null) */
    public function returnedBy(): ?string
    {
        return match (true) {
            $this->outcome !== self::OUTCOME_RETURNED => null,
            $this->director_decision === 'returned'   => 'director',
            default                                   => 'hub',
        };
    }

    public function returnComment(): ?string
    {
        return match ($this->returnedBy()) {
            'director' => $this->director_comments,
            'hub'      => $this->hub_comments,
            default    => null,
        };
    }

    /* ---------------- Facility rules (shown to Grants Management, used in the memo) ---------------- */

    public static function cumulativeCapLsl(): float
    {
        return (float) config('cif.co_investment.cumulative_cap_usd', 350000) * max((float) config('cif.usd_to_lsl', 18.00), 0.0001);
    }

    public static function minLesothoPct(): float
    {
        return (float) config('cif.co_investment.min_lesotho_pct', 70);
    }

    /** CAFI grants already approved for the same enterprise (other applications), in Maloti */
    public static function previousGrants(ExpressionOfInterest $eoi): float
    {
        $eoiIds = ExpressionOfInterest::where('user_id', $eoi->user_id)->whereKeyNot($eoi->id)->pluck('id');

        return (float) Authorisation::whereIn('expression_of_interest_id', $eoiIds)
            ->where('outcome', Authorisation::OUTCOME_AUTHORISED)
            ->sum('grant_amount');
    }

    /**
     * Facts for the checks: [matching max, matching ok, previous grants, cumulative, cap, cap ok, lesotho ok]
     */
    public function facts(): array
    {
        $eoi      = $this->expressionOfInterest;
        $max      = EnterpriseProfile::matchingGrant((float) $this->private_amount ?: null);
        $previous = $eoi ? self::previousGrants($eoi) : 0.0;
        $total    = $previous + (float) $this->grant_amount;
        $cap      = self::cumulativeCapLsl();

        return [
            'matching_max' => $max,
            'matching_ok'  => $max !== null && (float) $this->grant_amount <= $max + 1,
            'previous'     => $previous,
            'cumulative'   => $total,
            'cap'          => $cap,
            'cap_ok'       => $total <= $cap + 1,
            'lesotho_ok'   => $this->lesotho_pct !== null && (float) $this->lesotho_pct >= self::minLesothoPct(),
        ];
    }

    /** Why Grants Management can't submit to the Hub Manager yet (empty = ready). Works on the form arrays. */
    public static function submitBlockers(array $form): array
    {
        $checks  = $form['checks'] ?? [];
        $bank    = $form['bank'] ?? [];
        $missing = collect(self::CHECKS)->keys()->reject(fn ($k) => filled($checks[$k]['status'] ?? null))->count();
        $action  = collect(self::CHECKS)->filter(fn ($c, $k) => ($checks[$k]['status'] ?? null) === 'action')->map(fn ($c) => $c[0])->values();
        $noNote  = collect(self::CHECKS)->filter(fn ($c, $k) => ($checks[$k]['status'] ?? null) === 'exception' && blank($checks[$k]['note'] ?? null))->count();

        return array_values(array_filter([
            $missing ? "Record every check ({$missing} not recorded)." : null,
            $action->isNotEmpty() ? 'Resolve the checks marked "Action required": '.$action->implode(', ').'.' : null,
            $noNote ? 'Explain each exception in a short note.' : null,
            ($checks['bank']['status'] ?? null) !== 'confirmed' ? 'The bank account must be verified (Confirmed) before the file proceeds.' : null,
            collect(['bank', 'account_name', 'account_number'])->contains(fn ($k) => blank($bank[$k] ?? null)) ? 'The enterprise has not entered its bank, account name and account number in its profile (Business → Bank account for the grant).' : null,
            empty($form['has_bank_evidence']) ? 'No bank evidence: the enterprise has not uploaded a bank confirmation letter — ask for it, or upload the document you verified against.' : null,
            ! is_numeric($form['lesotho_pct'] ?? null)
                ? 'The enterprise has not yet said how much of the grant is spent outside Lesotho (enterprise profile → Business, after the budget). Ask them to complete it.' : null,
            is_numeric($form['lesotho_pct'] ?? null) && (float) $form['lesotho_pct'] < self::minLesothoPct()
                && blank($form['lesotho_justification'] ?? null) && blank($checks['budget']['note'] ?? null)
                ? 'Less than '.(int) self::minLesothoPct().'% of the grant is spent in Lesotho and the enterprise has not justified it — ask them to, or record the justification in the budget check note.' : null,
            collect($form['issues'] ?? [])->contains(fn ($i) => blank($i['issue'] ?? null) || blank($i['party'] ?? null))
                ? 'Each outstanding issue needs a description and a responsible party.' : null,
            empty($form['has_agreement']) ? 'Upload the Draft Grant Agreement.' : null,
            ($form['recommendation'] ?? null) !== 'ready' ? 'The recommendation must be "Ready to Proceed" to submit — a Not Ready file stays with Grants Management for corrective action.' : null,
            blank($form['justification'] ?? null) ? 'Write a brief justification for the recommendation.' : null,
        ]));
    }

    /** Remove stored files when a round is deleted */
    protected static function booted(): void
    {
        static::deleting(function (GrantReadiness $g) {
            foreach (['agreement_path', 'bank_evidence_path'] as $col) {
                if ($g->{$col} && ! self::where($col, $g->{$col})->whereKeyNot($g->id)->exists()) {
                    Storage::disk(self::DISK)->delete($g->{$col});
                }
            }
        });
    }
}