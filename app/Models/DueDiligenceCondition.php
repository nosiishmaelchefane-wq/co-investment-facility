<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Gate 2 · a condition or action found during due diligence (ESAP actions included).
 * Carried forward to the Investment Committee, contracting and Gate 5.
 */
class DueDiligenceCondition extends Model
{
    /** treatment => [label, blocks Gate 2 "Proceed" while open] */
    public const TREATMENTS = [
        'before_ic'           => ['Resolve before the Committee',       true],
        'condition_precedent' => ['Condition precedent (before contract)', false],
        'before_disbursement' => ['Before disbursement',                false],
        'monitoring'          => ['Monitor after investment',           false],
    ];

    public const OWNERS = ['enterprise' => 'Enterprise', 'investor' => 'Investor', 'pmu' => 'PMU'];

    public const STATUSES = ['open' => ['Open', 'pill-warn'], 'resolved' => ['Resolved', 'pill-ok']];

    protected $fillable = ['due_diligence_id', 'finding', 'treatment', 'owner', 'status', 'resolved_at', 'position'];

    protected $casts = ['resolved_at' => 'datetime'];

    public function dueDiligence(): BelongsTo
    {
        return $this->belongsTo(DueDiligence::class);
    }

    public static function blocks(array $row): bool
    {
        return ($row['status'] ?? 'open') === 'open' && (self::TREATMENTS[$row['treatment'] ?? ''][1] ?? false);
    }
}