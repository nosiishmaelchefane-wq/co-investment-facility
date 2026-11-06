<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/** Gate 6/7 · private investment actually deployed (§3.7.2 · §3.8.2), and the tranche it backs. Amount in Maloti. */
class CapitalDeployment extends Model
{
    protected $fillable = [
        'condition_review_id', 'investor', 'amount', 'deployed_on', 'received_account', 'checks', 'disbursement_tranche_id',
        'evidence_path', 'evidence_name', 'evidence_size',
        'status', 'note', 'recorded_by', 'verified_by', 'verified_at',
    ];

    protected $casts = ['amount' => 'decimal:2', 'deployed_on' => 'date', 'verified_at' => 'datetime', 'checks' => 'array'];

    public function review(): BelongsTo   { return $this->belongsTo(ConditionReview::class, 'condition_review_id'); }
    public function recorder(): BelongsTo { return $this->belongsTo(User::class, 'recorded_by'); }
    public function verifier(): BelongsTo { return $this->belongsTo(User::class, 'verified_by'); }
    public function tranche(): BelongsTo  { return $this->belongsTo(DisbursementTranche::class, 'disbursement_tranche_id'); }

    protected static function booted(): void
    {
        // A plain closure: an arrow function would return false when there is no file, and returning false CANCELS the delete
        static::deleting(function (self $d) {
            if ($d->evidence_path) {
                Storage::disk(ConditionReview::DISK)->delete($d->evidence_path);
            }
        });
    }
}