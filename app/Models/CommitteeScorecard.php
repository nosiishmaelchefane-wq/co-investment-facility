<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Gate 3 · Appendix F — Co-Investment Evaluation Scorecard (one per Committee member per round).
 *   scores         criterion => 1–5 (SCALE). A score of 3 or more counts as "met" (answers is kept in step).
 *   pack_comments  pack item  => the member's comment on that item of the Committee information pack
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

    /** score => [label, meaning] */
    public const SCALE = [
        1 => ['Very weak',   'Not demonstrated — a serious concern'],
        2 => ['Weak',        'Partly demonstrated — significant gaps'],
        3 => ['Adequate',    'Demonstrated — meets the requirement'],
        4 => ['Strong',      'Well demonstrated and evidenced'],
        5 => ['Very strong', 'Fully demonstrated — an outstanding case'],
    ];

    /** Lowest score that counts as "met" */
    public const MET_FROM = 3;

    /** Members must comment on every item of the information pack before submitting */
    public const PACK_COMMENTS_REQUIRED = true;

    /** Guide only: an overall score at or above this % supports approval (config cif.committee.pass_pct) */
    public static function passPct(): int
    {
        return (int) config('cif.committee.pass_pct', 60);
    }

    public static function maxScore(): int
    {
        return count(self::criteriaKeys()) * max(array_keys(self::SCALE));
    }

    /** Score of one criterion (older cards: Met = 4, Not met = 2) */
    public function scoreFor(string $criterion): ?int
    {
        $s = $this->scores[$criterion] ?? null;
        if (is_numeric($s)) {
            return (int) $s;
        }

        return ['met' => 4, 'not_met' => 2][$this->answers[$criterion] ?? ''] ?? null;
    }

    /** Sum of the criterion scores */
    public function totalScore(): int
    {
        return (int) collect(self::criteriaKeys())->sum(fn ($k) => $this->scoreFor($k) ?? 0);
    }

    /** Total as a % of the maximum */
    public function scorePct(): int
    {
        return (int) round($this->totalScore() / max(self::maxScore(), 1) * 100);
    }

    /** Group subtotal: [score, max] */
    public function groupScore(string $group): array
    {
        $keys = array_keys(self::GROUPS[$group][1] ?? []);

        return [(int) collect($keys)->sum(fn ($k) => $this->scoreFor($k) ?? 0), count($keys) * max(array_keys(self::SCALE))];
    }

    /** 1–5 → met / not_met */
    public static function answerFor(?int $score): ?string
    {
        return $score === null ? null : ($score >= self::MET_FROM ? 'met' : 'not_met');
    }

    protected $fillable = [
        'committee_review_id', 'user_id', 'coi', 'coi_details', 'answers', 'scores', 'innovation', 'comments', 'pack_comments',
        'use_of_funds_notes', 'issues', 'recommendation', 'takeaways', 'submitted_at',
    ];

    protected $casts = [
        'answers'       => 'array',
        'scores'        => 'array',
        'pack_comments' => 'array',
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