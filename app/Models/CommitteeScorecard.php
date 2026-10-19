<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Gate 3 · Appendix F — Co-Investment Evaluation Scorecard (one per Committee member per round).
 */
class CommitteeScorecard extends Model
{
    /** group => [title, [criterion => text]] */
    public const GROUPS = [
        'selection' => ['Selection criteria', [
            'term_sheet' => 'Applicant firm has secured a term sheet for an investment by one or more eligible private investors',
            'innovative' => 'The firm is a startup, or an innovative SME demonstrating one or more types of innovation',
        ]],
        'investors' => ['Investors', [
            'credible'    => 'Investors are credible',
            'investor_dd' => 'Investors have done satisfactory due diligence',
        ]],
        'terms' => ['Investment terms', [
            'reasonable' => 'Investment terms are reasonable',
            'market'     => 'Investment terms are consistent with market / international standards',
        ]],
        'use_of_funds' => ['Use of funds', [
            'appropriate' => 'The proposed use of funds is appropriate',
        ]],
    ];

    /** Short labels for the consolidated table */
    public const SHORT = [
        'term_sheet'  => 'Term sheet secured',
        'innovative'  => 'Startup / innovative SME',
        'credible'    => 'Investors are credible',
        'investor_dd' => 'Investors have done satisfactory DD',
        'reasonable'  => 'Terms are reasonable',
        'market'      => 'Terms consistent with market standards',
        'appropriate' => 'Use of funds is appropriate',
    ];

    public const INNOVATION = [
        'product'        => 'Product innovation',
        'process'        => 'Process innovation',
        'marketing'      => 'Marketing innovation',
        'organisational' => 'Organisational innovation',
    ];

    /** coi => [label, pill, description] */
    public const COI = [
        'none'     => ['No conflict',      'pill-ok',     'I have no conflict of interest in this transaction.'],
        'declared' => ['Conflict declared', 'pill-warn',  'I have an interest to declare, but it does not prevent me from scoring.'],
        'recused'  => ['Recused',          'pill-danger', 'I have a conflict and will not score this transaction.'],
    ];

    public const RECOMMENDATIONS = ['approve' => 'Approve', 'reject' => 'Reject'];

    protected $fillable = [
        'committee_review_id', 'user_id', 'coi', 'coi_details', 'answers', 'innovation', 'comments',
        'use_of_funds_notes', 'issues', 'recommendation', 'takeaways', 'submitted_at',
    ];

    protected $casts = [
        'answers'      => 'array',
        'innovation'   => 'array',
        'comments'     => 'array',
        'submitted_at' => 'datetime',
    ];

    public function review(): BelongsTo
    {
        return $this->belongsTo(CommitteeReview::class, 'committee_review_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isSubmitted(): bool
    {
        return $this->submitted_at !== null;
    }

    public function isRecused(): bool
    {
        return $this->coi === 'recused';
    }

    public static function criteriaKeys(): array
    {
        return collect(self::GROUPS)->flatMap(fn ($g) => array_keys($g[1]))->all();
    }
}