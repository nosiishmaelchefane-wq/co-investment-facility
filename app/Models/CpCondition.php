<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/** Gate 6 · an item on the conditions precedent checklist (§3.7.1): requirement, evidence, who provides / verifies, timing */
class CpCondition extends Model
{
    protected $fillable = [
        'condition_review_id', 'area', 'finding', 'evidence_required', 'responsible', 'verifier', 'timing', 'source', 'status', 'note', 'corrective_action',
        'evidence_path', 'evidence_name', 'evidence_size', 'evidence_by', 'evidence_at', 'verified_by', 'verified_at', 'position',
    ];

    protected $casts = ['evidence_at' => 'datetime', 'verified_at' => 'datetime'];

    public function review(): BelongsTo   { return $this->belongsTo(ConditionReview::class, 'condition_review_id'); }
    /** Who verified it. (Not "verifier": that column holds the verifying party — pmu, finance … — and would hide the relation.) */
    public function verifiedBy(): BelongsTo { return $this->belongsTo(User::class, 'verified_by'); }
    public function uploader(): BelongsTo { return $this->belongsTo(User::class, 'evidence_by'); }

    public function isDone(): bool
    {
        return in_array($this->status, ['met', 'waived'], true);
    }

    /** Must be met before disbursement (ongoing obligations are followed up after release) */
    public function isMandatory(): bool
    {
        return ($this->timing ?? 'before_disbursement') !== 'ongoing';
    }

    protected static function booted(): void
    {
        // A plain closure: an arrow function would return false when there is no evidence, and returning false CANCELS the delete
        static::deleting(function (self $c) {
            if ($c->evidence_path) {
                Storage::disk(ConditionReview::DISK)->delete($c->evidence_path);
            }
        });
    }
}