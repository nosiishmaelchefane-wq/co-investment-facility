<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Gate 5 → disbursement · one grant tranche (§3.6.4). Amounts in Maloti. */
class DisbursementTranche extends Model
{
    protected $fillable = [
        'condition_review_id', 'position', 'label', 'amount', 'trigger', 'capital_required', 'released_on', 'reference', 'released_by',
        'proof_path', 'proof_name', 'proof_size',   // proof of payment (required to release)
    ];

    protected $casts = ['amount' => 'decimal:2', 'capital_required' => 'decimal:2', 'released_on' => 'date'];

    public function review(): BelongsTo   { return $this->belongsTo(ConditionReview::class, 'condition_review_id'); }
    public function releaser(): BelongsTo { return $this->belongsTo(User::class, 'released_by'); }

    public function isReleased(): bool
    {
        return $this->released_on !== null;
    }
}