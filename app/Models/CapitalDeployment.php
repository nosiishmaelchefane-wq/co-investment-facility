<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/** Gate 5 · private investor capital actually deployed (§3.6.2). Amount in Maloti. */
class CapitalDeployment extends Model
{
    protected $fillable = [
        'condition_review_id', 'investor', 'amount', 'deployed_on', 'evidence_path', 'evidence_name', 'evidence_size',
        'status', 'note', 'recorded_by', 'verified_by', 'verified_at',
    ];

    protected $casts = ['amount' => 'decimal:2', 'deployed_on' => 'date', 'verified_at' => 'datetime'];

    public function review(): BelongsTo   { return $this->belongsTo(ConditionReview::class, 'condition_review_id'); }
    public function recorder(): BelongsTo { return $this->belongsTo(User::class, 'recorded_by'); }
    public function verifier(): BelongsTo { return $this->belongsTo(User::class, 'verified_by'); }

    protected static function booted(): void
    {
        static::deleting(fn (self $d) => $d->evidence_path && Storage::disk(ConditionReview::DISK)->delete($d->evidence_path));
    }
}