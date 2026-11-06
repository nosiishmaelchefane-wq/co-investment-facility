<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Gate 5 · Final Authorisation (CI Facility Operations Manual §3.6). One row per round. Amounts in Maloti.
 *
 *   Gate 4 Grant readiness → CAFI Managing Director signs the Grant Agreement → MTIBD Principal Secretary signs → Gate 6
 *   Sequential: the Principal Secretary only acts after the Managing Director has signed. Both signatures = formal approval.
 *   Inputs: Grant Readiness Memo, Draft Grant Agreement, Investment Committee recommendation and the transaction file.
 *   (Table / permission names keep "gate4" from before Gate 4 Grant readiness was added.)
 */
class Authorisation extends Model
{
    /* ---------------- §3.6.1 Managing Director review and signature ---------------- */

    /** check => text — what the Managing Director confirms before signing */
    public const MD_CHECKS = [
        'readiness'      => 'Gate 4 is complete: the Grant Readiness Memo recommends Ready to Proceed, approved by the Hub Manager and the PMU Director of Implementation',
        'memo'           => 'The Grant Readiness Memo, Draft Grant Agreement and supporting transaction file have been reviewed',
        'process'        => 'The prescribed review process has been completed (Gates 1–4)',
        'recommendation' => 'The Investment Committee recommendation is properly documented',
        'terms'          => 'The proposed grant amount and terms in the Grant Agreement are consistent with the Facility’s requirements',
        'conditions'     => 'Conditions attached and outstanding matters are clearly documented',
    ];

    /** decision => [label, description, icon] */
    public const MD_DECISIONS = [
        'approve' => ['Sign & forward',                              'Signs the Grant Agreement and forwards it to the MTIBD Principal Secretary.', 'bi-pen'],
        'return'  => ['Return for clarification or corrective action', 'Material issues — returned before proceeding.',                               'bi-arrow-return-left'],
        'decline' => ['Not approve',                                 'The Managing Director does not sign — the grant cannot proceed.',              'bi-x-octagon'],
    ];

    /* ---------------- §3.6.2 Principal Secretary review and signature ---------------- */

    public const PS_DECISIONS = [
        'authorise' => ['Sign — formally approve',                       'Both signatures complete: the grant is formally approved → Gate 6.', 'bi-patch-check'],
        'clarify'   => ['Return for clarification or corrective action', 'Material issues to resolve before formal approval.',                 'bi-arrow-return-left'],
        'decline'   => ['Not approve',                                   'The Principal Secretary does not sign — the grant cannot proceed.',  'bi-x-octagon'],
    ];

    /* ---------------- Possible outcomes ---------------- */

    public const OUTCOME_AUTHORISED     = 'authorised';
    public const OUTCOME_CLARIFICATION  = 'clarification';
    public const OUTCOME_NOT_AUTHORISED = 'not_authorised';

    /** outcome => [label, meaning, next step, icon, pill] */
    public const OUTCOMES = [
        self::OUTCOME_AUTHORISED     => ['Formally approved',                              'The CAFI Managing Director and the MTIBD Principal Secretary have both signed the Grant Agreement.', 'Proceed to Gate 6 · Conditions precedent & onboarding', 'bi-patch-check',       'pill-ok'],
        self::OUTCOME_CLARIFICATION  => ['Returned for clarification or corrective action', 'A signatory identified issues that must be resolved before the agreement can proceed.',          'Resolve and resubmit to the signatory',                 'bi-arrow-return-left', 'pill-warn'],
        self::OUTCOME_NOT_AUTHORISED => ['Not approved',                                   'The grant did not receive the required signatures and cannot proceed.',                           'Process ends — the applicant is notified',              'bi-x-octagon',         'pill-danger'],
    ];

    public const OUTCOME_TO_EOI_STATUS = [
        self::OUTCOME_AUTHORISED     => ExpressionOfInterest::STATUS_AUTHORISED,
        self::OUTCOME_CLARIFICATION  => ExpressionOfInterest::STATUS_AUTH_HOLD,
        self::OUTCOME_NOT_AUTHORISED => ExpressionOfInterest::STATUS_NOT_PROGRESSED,
    ];

    /* ---------------- Conditions attached ---------------- */

    /** type => [label, treatment, blocks authorisation while open] */
    public const CONDITION_TYPES = [
        'minor_doc' => ['Minor documentation matter',              'Resolve before contracting',                               false],
        'legal_doc' => ['Legal or transaction documentation',      'Address as a contracting condition',                       false],
        'esgc'      => ['ESGC mitigation',                         'Incorporate into the Grant Agreement or monitoring',       false],
        'investor'  => ['Investor commitment condition',           'Verify before grant disbursement',                         false],
        'cp'        => ['Condition precedent to grant agreement',  'Carry into Gate 6',                                        false],
        'material'  => ['Material unresolved issue',               'Return for further assessment before progression',         true],
    ];

    /** Source of a condition. 'g4' = added at final authorisation (shown as Gate 5); 'readiness' = Gate 4 outstanding issue */
    public const CONDITION_SOURCES = ['g2' => 'Gate 2', 'g3' => 'Gate 3', 'readiness' => 'Gate 4', 'g4' => 'Gate 5'];

    /* ---------------- Signing outside the system ---------------- */

    /** The MD and PS do not log in — the PMU sends them the Grant Agreement itself (no e-mail from the system), uploads the signed copy and records the decision. */
    public const DISK = 'local';

    public const MAX_KB = 20480;

    /** Accepted files: what is sent for signing, and the signed copy (scans / photos allowed) */
    public const REQUEST_MIMES = 'pdf,docx,doc';
    public const SIGNED_MIMES  = 'pdf,jpg,jpeg,png';

    /** role => [authority title, short label] */
    public const AUTHORITIES = [
        'md' => ['CAFI Managing Director', 'Managing Director'],
        'ps' => ['MTIBD Principal Secretary', 'Principal Secretary'],
    ];

    protected $fillable = [
        'expression_of_interest_id', 'committee_review_id', 'round', 'resubmission_note',
        'private_amount', 'grant_amount', 'instrument', 'conditions',
        'md_checks', 'md_decision', 'md_comments', 'md_on', 'md_by', 'md_decided_at',
        'ps_decision', 'ps_comments', 'ps_on', 'ps_by', 'ps_decided_at',
        'outcome', 'decided_at',
        'md_signer_name', 'md_signer_email', 'md_request_path', 'md_request_name', 'md_request_message', 'md_sent_at', 'md_sent_by',
        'md_signed_path', 'md_signed_name', 'md_signed_size', 'md_signed_at', 'md_signed_uploaded_by',
        'ps_signer_name', 'ps_signer_email', 'ps_request_path', 'ps_request_name', 'ps_request_message', 'ps_sent_at', 'ps_sent_by',
        'ps_signed_path', 'ps_signed_name', 'ps_signed_size', 'ps_signed_at', 'ps_signed_uploaded_by',
    ];

    protected $casts = [
        'conditions'     => 'array',
        'md_checks'      => 'array',
        'md_on'          => 'date',
        'ps_on'          => 'date',
        'md_decided_at'  => 'datetime',
        'ps_decided_at'  => 'datetime',
        'decided_at'     => 'datetime',
        'md_sent_at'     => 'datetime',
        'md_signed_at'   => 'datetime',
        'ps_sent_at'     => 'datetime',
        'ps_signed_at'   => 'datetime',
        'private_amount' => 'decimal:2',
        'grant_amount'   => 'decimal:2',
    ];

    /* ---------------- Relations ---------------- */

    public function expressionOfInterest(): BelongsTo
    {
        return $this->belongsTo(ExpressionOfInterest::class);
    }

    public function committeeReview(): BelongsTo
    {
        return $this->belongsTo(CommitteeReview::class);
    }

    public function managingDirector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'md_by');
    }

    public function principalSecretary(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ps_by');
    }

    public function mdSender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'md_sent_by');
    }

    public function psSender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ps_sent_by');
    }

    /* ---------------- Signing ---------------- */

    public function sentForSigning(string $role): bool
    {
        return $this->{"{$role}_sent_at"} !== null;
    }

    public function hasSignedCopy(string $role): bool
    {
        return filled($this->{"{$role}_signed_path"});
    }

    /** Who signed (falls back to the authority's title) */
    public function signerName(string $role): string
    {
        return $this->{"{$role}_signer_name"} ?: self::AUTHORITIES[$role][0];
    }

    /** Remove stored files when a round is deleted */
    protected static function booted(): void
    {
        static::deleting(function (Authorisation $a) {
            foreach (['md', 'ps'] as $r) {
                foreach (["{$r}_request_path", "{$r}_signed_path"] as $col) {
                    if ($a->{$col}) {
                        \Illuminate\Support\Facades\Storage::disk(self::DISK)->delete($a->{$col});
                    }
                }
            }
        });
    }

    /* ---------------- State ---------------- */

    public function isDecided(): bool
    {
        return $this->decided_at !== null;
    }

    /** md | ps | decided */
    public function stage(): string
    {
        return match (true) {
            $this->isDecided()                                                  => 'decided',
            $this->md_decided_at !== null && $this->md_decision === 'approve'   => 'ps',
            default                                                             => 'md',
        };
    }

    /** Which authority closed the round with a clarification ('md' | 'ps' | null) */
    public function heldBy(): ?string
    {
        return match (true) {
            $this->outcome !== self::OUTCOME_CLARIFICATION => null,
            $this->ps_decision === 'clarify'               => 'ps',
            default                                        => 'md',
        };
    }

    /* ---------------- Helpers ---------------- */

    /** Default type for a condition, from its Gate 2 treatment or Gate 3 text */
    public static function guessType(?string $treatment): ?string
    {
        $t = strtolower((string) $treatment);

        return match (true) {
            str_contains($t, 'disburse')                                   => 'investor',
            str_contains($t, 'precedent'), str_contains($t, 'before contract') => 'cp',
            str_contains($t, 'monitor'), str_contains($t, 'esgc'), str_contains($t, 'esap') => 'esgc',
            default                                                        => null,
        };
    }

    /** Conditions from the Committee record (G2 ones it accepted/amended + its own) and Gate 4's open issues, ready to classify */
    public static function conditionsFrom(?CommitteeReview $review, ?GrantReadiness $readiness = null): array
    {
        $gate4 = collect($readiness?->issues ?? [])
            ->filter(fn ($i) => ($i['status'] ?? 'open') !== 'resolved' && filled($i['issue'] ?? null))
            ->map(fn ($i) => [
                'source'  => 'readiness',
                'finding' => (string) $i['issue'].(filled($i['action'] ?? null) ? ' — '.$i['action'] : ''),
                'type'    => null,
                'status'  => 'open',
            ]);

        return collect($review?->conditions ?? [])
            ->filter(fn ($c) => ($c['decision'] ?? null) !== 'removed' && filled($c['finding'] ?? null))
            ->map(fn ($c) => [
                'source'  => ($c['source'] ?? 'committee') === 'g2' ? 'g2' : 'g3',
                'finding' => (string) $c['finding'],
                'type'    => self::guessType($c['treatment'] ?? null),
                'status'  => ($c['status'] ?? 'open') === 'resolved' ? 'resolved' : 'open',
            ])->merge($gate4)->values()->all();
    }

    /** Why the Managing Director can't approve yet (empty = ready) */
    public static function approveBlockers(array $checks, array $conditions): array
    {
        $unticked     = collect(self::MD_CHECKS)->keys()->reject(fn ($k) => ! empty($checks[$k]))->count();
        $unclassified = collect($conditions)->filter(fn ($c) => blank($c['type'] ?? null))->count();
        $material     = collect($conditions)->filter(fn ($c) => ($c['type'] ?? null) === 'material' && ($c['status'] ?? 'open') === 'open')->count();

        return array_values(array_filter([
            $unticked ? "Confirm all review checks ({$unticked} not ticked)." : null,
            $unclassified ? "Classify every condition ({$unclassified} without a type)." : null,
            $material ? "{$material} material unresolved ".($material === 1 ? 'issue must' : 'issues must').' be returned for further assessment, not approved.' : null,
        ]));
    }

    /** Authorised next stage shown in the decision record */
    public function nextStage(): ?string
    {
        return match ($this->outcome) {
            self::OUTCOME_AUTHORISED     => 'Gate 6 · Conditions precedent & onboarding',
            self::OUTCOME_CLARIFICATION  => $this->heldBy() === 'ps' ? 'Resubmit to the Principal Secretary' : 'Resubmit to the Managing Director',
            self::OUTCOME_NOT_AUTHORISED => 'Process ends',
            default                      => $this->stage() === 'ps' ? 'Principal Secretary signature' : 'Managing Director signature',
        };
    }
}