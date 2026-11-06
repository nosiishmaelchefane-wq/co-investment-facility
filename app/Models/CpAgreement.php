<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/** Gate 6 · Grant Agreement / Investment Agreement / other documentation (§3.7.3) */
class CpAgreement extends Model
{
    protected $fillable = [
        'condition_review_id', 'kind', 'title', 'parties', 'required', 'status', 'signed_on',
        'file_path', 'file_name', 'file_size', 'uploaded_by',
    ];

    protected $casts = ['required' => 'boolean', 'signed_on' => 'date'];

    public function review(): BelongsTo { return $this->belongsTo(ConditionReview::class, 'condition_review_id'); }

    protected static function booted(): void
    {
        // A plain closure: an arrow function would return false when there is no file, and returning false CANCELS the delete
        static::deleting(function (self $a) {
            if ($a->file_path) {
                Storage::disk(ConditionReview::DISK)->delete($a->file_path);
            }
        });
    }
}