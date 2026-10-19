<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/** Gate 5 · a condition precedent (§3.6.1) */
class CpCondition extends Model
{
    protected $fillable = [
        'condition_review_id', 'area', 'finding', 'source', 'status', 'note',
        'evidence_path', 'evidence_name', 'evidence_size', 'evidence_by', 'evidence_at', 'verified_by', 'verified_at', 'position',
    ];

    protected $casts = ['evidence_at' => 'datetime', 'verified_at' => 'datetime'];

    public function review(): BelongsTo   { return $this->belongsTo(ConditionReview::class, 'condition_review_id'); }
    public function verifier(): BelongsTo { return $this->belongsTo(User::class, 'verified_by'); }
    public function uploader(): BelongsTo { return $this->belongsTo(User::class, 'evidence_by'); }

    public function isDone(): bool
    {
        return in_array($this->status, ['met', 'waived'], true);
    }

    protected static function booted(): void
    {
        static::deleting(fn (self $c) => $c->evidence_path && Storage::disk(ConditionReview::DISK)->delete($c->evidence_path));
    }
}