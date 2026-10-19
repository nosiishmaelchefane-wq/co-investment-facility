<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Gate 4 · Final Authorisation (CI Facility Operations Manual §3.5). One row per round. Amounts in Maloti.
 *
 *   Gate 3 recommendation → CAFI Managing Director review → MTIBD Principal Secretary final authorisation → Gate 5
 *   Sequential, not concurrent: the Principal Secretary can only act after the Managing Director approves.
 */
class Authorisation extends Model
{
    /* ---------------- §3.5.2 Managing Director ---------------- */

    /** check => text — what the Managing Director confirms */
    public const MD_CHECKS = [
        'recommendation' => 'The Investment Committee recommendation is properly documented',
        'stages'         => 'The transaction has completed the required assessment stages (Gates 1–3)',
        'grant'          => 'The proposed grant amount is consistent with the applicable co-investment parameters',
        'risks'          => 'Material risks and ESGC matters have been identified and addressed',
        'conditions'     => 'Any Committee conditions are clearly defined',
        'suitable'       => 'The transaction is suitable for progression to final authorisation',
    ];

    /** decision => [label, description, icon] */
    public const MD_DECISIONS = [
        'approve' => ['Approve & forward',        'Forward to the MTIBD Principal Secretary for final authorisation.', 'bi-send-check'],
        'return'  => ['Return for clarification', 'Material information is insufficient — return for refinement.',   'bi-arrow-return-left'],
        'decline' => ['Not approve',              'The transaction does not proceed to final authorisation.',          'bi-x-octagon'],
    ];

    /* ---------------- §3.5.3 Principal Secretary ---------------- */

    public const PS_DECISIONS = [
        'authorise' => ['Authorise',             'Final authorisation — proceed to Gate 5.',                    'bi-patch-check'],
        'clarify'   => ['Request clarification', 'Further information is needed before authorising.',           'bi-question-circle'],
        'decline'   => ['Decline',               'No final authorisation — the transaction exits the process.', 'bi-x-octagon'],
    ];

    /* ---------------- §3.5.5 Outcomes ---------------- */

    public const OUTCOME_AUTHORISED     = 'authorised';
    public const OUTCOME_CLARIFICATION  = 'clarification';
    public const OUTCOME_NOT_AUTHORISED = 'not_authorised';

    /** outcome => [label, meaning, next step, icon, pill] */
    public const OUTCOMES = [
        self::OUTCOME_AUTHORISED     => ['Authorised',                     'The required sequential approvals were received.',                 'Proceed to Gate 5',                          'bi-patch-check',       'pill-ok'],
        self::OUTCOME_CLARIFICATION  => ['Clarification / further action', 'More information or action is needed before authorisation.',      'Resolve and resubmit at the relevant stage', 'bi-arrow-return-left', 'pill-warn'],
        self::OUTCOME_NOT_AUTHORISED => ['Not authorised',                 'The required institutional approval was not received.',            'Process ends — the applicant is notified',   'bi-x-octagon',         'pill-danger'],
    ];

    public const OUTCOME_TO_EOI_STATUS = [
        self::OUTCOME_AUTHORISED     => ExpressionOfInterest::STATUS_AUTHORISED,
        self::OUTCOME_CLARIFICATION  => ExpressionOfInterest::STATUS_AUTH_HOLD,
        self::OUTCOME_NOT_AUTHORISED => ExpressionOfInterest::STATUS_NOT_PROGRESSED,
    ];

    /* ---------------- §3.5.4 Conditions ---------------- */

    /** type => [label, treatment, blocks authorisation while open] */
    public const CONDITION_TYPES = [
        'minor_doc' => ['Minor documentation matter',              'Resolve before contracting',                               false],
        'legal_doc' => ['Legal or transaction documentation',      'Address as a contracting condition',                       false],
        'esgc'      => ['ESGC mitigation',                         'Incorporate into the Grant Agreement or monitoring',       false],
        'investor'  => ['Investor commitment condition',           'Verify before grant disbursement',                         false],
        'cp'        => ['Condition precedent to grant agreement',  'Carry into Gate 5',                                        false],
        'material'  => ['Material unresolved issue',               'Return for further assessment before progression',         true],
    ];

    public const CONDITION_SOURCES = ['g2' => 'Gate 2', 'g3' => 'Gate 3', 'g4' => 'Gate 4'];

    /* ---------------- Signing outside the system ---------------- */

    /** The MD and PS do not log in — the PMU sends them the document itself (no e-mail from the system), uploads the signed copy and records the decision. */
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

    /** Conditions from the Committee record (G2 ones it accepted/amended + its own), ready to classify */
    public static function conditionsFrom(?CommitteeReview $review): array
    {
        return collect($review?->conditions ?? [])
            ->filter(fn ($c) => ($c['decision'] ?? null) !== 'removed' && filled($c['finding'] ?? null))
            ->map(fn ($c) => [
                'source'  => ($c['source'] ?? 'committee') === 'g2' ? 'g2' : 'g3',
                'finding' => (string) $c['finding'],
                'type'    => self::guessType($c['treatment'] ?? null),
                'status'  => ($c['status'] ?? 'open') === 'resolved' ? 'resolved' : 'open',
            ])->values()->all();
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

    /** Next stage shown in the decision record (§3.5.6) */
    public function nextStage(): ?string
    {
        return match ($this->outcome) {
            self::OUTCOME_AUTHORISED     => 'Gate 5 · Conditions precedent & Grant Agreement',
            self::OUTCOME_CLARIFICATION  => $this->heldBy() === 'ps' ? 'Resubmit to the Principal Secretary' : 'Resubmit to the Managing Director',
            self::OUTCOME_NOT_AUTHORISED => 'Process ends',
            default                      => $this->stage() === 'ps' ? 'Principal Secretary authorisation' : 'Managing Director review',
        };
    }
}