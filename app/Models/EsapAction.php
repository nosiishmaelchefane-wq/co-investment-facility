<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Gate 2 · Environmental & Social Action Plan item. Carried forward to contracting and monitoring.
 */
class EsapAction extends Model
{
    public const TREATMENTS = [
        'condition_precedent' => 'Condition precedent',
        'before_contracting'  => 'Before contracting',
        'monitoring'          => 'Monitoring',
    ];

    public const STATUSES = ['pending' => 'Pending', 'done' => 'Done'];

    protected $fillable = ['due_diligence_id', 'action', 'dimension', 'treatment', 'timing', 'status', 'completed_at', 'position'];

    protected $casts = ['completed_at' => 'datetime'];

    public function dueDiligence(): BelongsTo
    {
        return $this->belongsTo(DueDiligence::class);
    }
}