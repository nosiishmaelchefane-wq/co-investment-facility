<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExpressionOfInterest extends Model
{
    use HasFactory, SoftDeletes;

    /* ---------- Statuses ---------- */
    public const STATUS_DRAFT          = 'draft';
    public const STATUS_SUBMITTED      = 'submitted';
    public const STATUS_RETURNED       = 'returned';
    public const STATUS_IN_REVIEW      = 'in_review';
    public const STATUS_INVITED        = 'invited';
    public const STATUS_NOT_PROGRESSED = 'not_progressed';
    public const STATUS_WITHDRAWN      = 'withdrawn';

    /* ---------- Gate 2 ---------- */
    public const STATUS_DUE_DILIGENCE  = 'due_diligence';   // Gate 2 assessment under way
    public const STATUS_DD_HOLD        = 'dd_hold';         // Gate 2: clarification / remediation required
    public const STATUS_COMMITTEE      = 'committee';       // Gate 2 passed → Investment Committee (Gate 3)

    /* ---------- Gate 3 ---------- */
    public const STATUS_IC_DEFERRED    = 'ic_deferred';     // Committee deferred — PMU to act and resubmit
    public const STATUS_AUTHORISATION  = 'authorisation';   // Committee approved → final authorisation (Gate 4)

    /* ---------- Gate 4 ---------- */
    public const STATUS_AUTH_HOLD      = 'authorisation_hold';  // MD returned / PS asked for clarification — resolve and resubmit
    public const STATUS_AUTHORISED     = 'authorised';          // Both approvals in → Gate 5 (conditions precedent & Grant Agreement)

    /* ---------- Gate 5 ---------- */
    public const STATUS_CAPITAL_PENDING = 'capital_pending';    // conditions met, investor capital not yet verified — no CAFI disbursement
    public const STATUS_CLEARED         = 'cleared';            // cleared for disbursement → tranches released by Finance
    public const STATUS_ESCALATED       = 'escalated';          // a material condition can't be met — escalation / review

    /** Statuses at which Gate 1 has been passed */
    public const PAST_GATE1 = [
        self::STATUS_INVITED, self::STATUS_DUE_DILIGENCE, self::STATUS_DD_HOLD, self::STATUS_COMMITTEE,
        self::STATUS_IC_DEFERRED, self::STATUS_AUTHORISATION, self::STATUS_AUTH_HOLD, self::STATUS_AUTHORISED,
        self::STATUS_CAPITAL_PENDING, self::STATUS_CLEARED, self::STATUS_ESCALATED,
    ];

    /** Statuses an Investment Committee member can see (plus anything the Committee has reviewed) */
    public const COMMITTEE_VISIBLE = [self::STATUS_COMMITTEE, self::STATUS_IC_DEFERRED, self::STATUS_AUTHORISATION, self::STATUS_AUTH_HOLD, self::STATUS_AUTHORISED];

    /** Statuses at which Gate 4 has been reached */
    public const AUTHORISATION_VISIBLE = [
        self::STATUS_AUTHORISATION, self::STATUS_AUTH_HOLD, self::STATUS_AUTHORISED,
        self::STATUS_CAPITAL_PENDING, self::STATUS_CLEARED, self::STATUS_ESCALATED,
    ];

    /** Statuses at which Gate 5 is open (Finance can see these to release tranches) */
    public const GATE5_VISIBLE = [self::STATUS_AUTHORISED, self::STATUS_CAPITAL_PENDING, self::STATUS_CLEARED, self::STATUS_ESCALATED];

    /** Applicant may edit / resubmit */
    public const EDITABLE = [self::STATUS_DRAFT, self::STATUS_RETURNED];

    /** Applicant may withdraw */
    public const WITHDRAWABLE = [self::STATUS_DRAFT, self::STATUS_SUBMITTED, self::STATUS_RETURNED, self::STATUS_IN_REVIEW];

    /** Fields the applicant fills in (same keys as the drawer form) */
    public const FORM_FIELDS = [
        'business_name', 'representative_name', 'phone', 'email', 'district', 'website',
        'legal_status', 'year_commenced',
        'business_description', 'annual_turnover', 'full_time_employees',
        'investment_required_usd', 'investment_types', 'use_of_funds', 'use_of_funds_other', 'use_of_funds_description',
        'investor_status', 'investor_status_other', 'investor_amount_usd', 'investor_name', 'investor_evidence',
        'support_need', 'declaration_agree', 'declaration_name', 'declaration_date',
    ];

    protected $fillable = [
        ...self::FORM_FIELDS,
        'user_id', 'reference', 'status', 'screening_outcome', 'screening_reasons', 'submitted_at', 'withdrawn_at',
    ];

    protected $casts = [
        'investment_types'        => 'array',
        'use_of_funds'            => 'array',
        'investor_evidence'       => 'array',
        'screening_reasons'       => 'array',
        'declaration_agree'       => 'boolean',
        'declaration_date'        => 'date',
        'submitted_at'            => 'datetime',
        'withdrawn_at'            => 'datetime',
        'investment_required_usd' => 'decimal:2',
        'investor_amount_usd'     => 'decimal:2',
        'year_commenced'          => 'integer',
        'full_time_employees'     => 'integer',
    ];

    protected $attributes = [
        'status' => self::STATUS_DRAFT,
    ];

    /* ---------- Reference: EOI-2026-0001 ---------- */
    protected static function booted(): void
    {
        static::created(function (self $eoi) {
            if (! $eoi->reference) {
                $eoi->updateQuietly(['reference' => sprintf('EOI-%d-%04d', now()->year, $eoi->id)]);
            }
        });
    }

    /* ---------- Relationships ---------- */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Gate 1 screening rounds (a new round after each resubmission). */
    public function eligibilityScreenings(): HasMany
    {
        return $this->hasMany(EligibilityScreening::class);
    }

    public function latestScreening(): HasOne
    {
        return $this->hasOne(EligibilityScreening::class)->latestOfMany('round');
    }

    /** Gate 3 Investment Committee rounds */
    public function committeeReviews(): HasMany
    {
        return $this->hasMany(CommitteeReview::class);
    }

    /** Gate 5 conditions precedent, agreements & onboarding */
    public function conditionReview(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(ConditionReview::class);
    }

    /** Gate 4 final authorisation rounds */
    public function authorisations(): HasMany
    {
        return $this->hasMany(Authorisation::class);
    }

    /* ---------- Access ---------- */

    /**
     * Who can open this EOI:
     *   the applicant · staff with eoi.view-all · Investment Committee members (gate3.score)
     *   for transactions submitted to the Committee.
     */
    public function isViewableBy(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $this->user_id === $user->id
            || $user->can('eoi.view-all')
            || ($user->can('gate3.score') && (in_array($this->status, self::COMMITTEE_VISIBLE, true) || $this->committeeReviews()->exists()))
            || ($user->can('disburse.process') && in_array($this->status, self::GATE5_VISIBLE, true));
    }

    /** EOIs a user may list (same rule as isViewableBy) */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->can('eoi.view-all') && ! $user->hasRole('enterprise')) {
            return $query;
        }

        return $query->where(function ($q) use ($user) {
            $q->where('user_id', $user->id);

            if ($user->can('gate3.score')) {
                $q->orWhereIn('status', self::COMMITTEE_VISIBLE)->orWhereHas('committeeReviews');
            }
            if ($user->can('disburse.process')) {
                $q->orWhereIn('status', self::GATE5_VISIBLE);
            }
        });
    }

    /** Gate 2 due diligence rounds */
    public function dueDiligences(): HasMany
    {
        return $this->hasMany(DueDiligence::class);
    }

    public function latestDueDiligence(): HasOne
    {
        return $this->hasOne(DueDiligence::class)->latestOfMany('round');
    }

    /* ---------- Scopes ---------- */
    public function scopeOwnedBy(Builder $query, ?int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /* ---------- State helpers ---------- */
    public function isEditable(): bool
    {
        return in_array($this->status, self::EDITABLE, true);
    }

    public function isWithdrawable(): bool
    {
        return in_array($this->status, self::WITHDRAWABLE, true);
    }

    /** Data in the exact shape the drawer form expects. */
    public function toFormArray(): array
    {
        $data = $this->only(self::FORM_FIELDS);
        $data['declaration_date']        = $this->declaration_date?->toDateString();
        $data['investment_required_usd'] = $this->investment_required_usd !== null ? (float) $this->investment_required_usd : null;
        $data['investor_amount_usd']     = $this->investor_amount_usd !== null ? (float) $this->investor_amount_usd : null;

        return $data + ['id' => $this->id, 'reference' => $this->reference, 'status' => $this->status];
    }
}