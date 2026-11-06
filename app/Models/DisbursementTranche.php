<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Gate 7 · one grant disbursement / tranche (§3.8). Amounts in Maloti. */
class DisbursementTranche extends Model
{
    protected $fillable = [
        'condition_review_id', 'position', 'label', 'amount', 'trigger', 'capital_required', 'released_on', 'reference', 'released_by',
        'proof_path', 'proof_name', 'proof_size',   // confirmation of payment (required to release)
        'capital_verified', 'requested_by', 'requested_at', 'approved_by', 'approved_at', 'approval_note',
        'instruction_ref', 'instruction_on', 'outcome', 'outcome_note',
    ];

    protected $casts = [
        'amount' => 'decimal:2', 'capital_required' => 'decimal:2', 'capital_verified' => 'decimal:2', 'released_on' => 'date',
        'requested_at' => 'datetime', 'approved_at' => 'datetime', 'instruction_on' => 'date',
    ];

    public function review(): BelongsTo   { return $this->belongsTo(ConditionReview::class, 'condition_review_id'); }
    public function releaser(): BelongsTo  { return $this->belongsTo(User::class, 'released_by'); }
    public function requester(): BelongsTo { return $this->belongsTo(User::class, 'requested_by'); }
    public function approver(): BelongsTo  { return $this->belongsTo(User::class, 'approved_by'); }

    /** planned → requested (matching investment verified) → approved (payment approval) → released (instruction + confirmation) · held / stopped */
    public function step(): string
    {
        return match (true) {
            $this->isReleased()                 => 'released',
            $this->outcome === 'not_eligible'   => 'stopped',
            $this->outcome === 'pending'        => 'held',
            $this->approved_at !== null         => 'approved',
            $this->requested_at !== null        => 'requested',
            default                             => 'planned',
        };
    }

    public function isReleased(): bool
    {
        return $this->released_on !== null;
    }
}