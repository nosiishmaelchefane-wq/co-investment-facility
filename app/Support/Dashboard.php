<?php

namespace App\Support;

use App\Models\Authorisation;
use App\Models\CapitalDeployment;
use App\Models\CommitteeReview;
use App\Models\ConditionReview;
use App\Models\CpCondition;
use App\Models\DisbursementTranche;
use App\Models\ExpressionOfInterest as EOI;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Data for the staff dashboard (resources/views/components/dashboard/*).
 * One place for the queries so every dashboard widget counts the same way.
 *
 *   $d = new Dashboard(auth()->user());
 *   $d->statusCounts(); $d->money(); $d->tasks(); …
 *
 * Everything is limited to what the user may see (ExpressionOfInterest::visibleTo()),
 * and each "needs your attention" task only appears for the permission that acts on it.
 * Results are cached for the lifetime of the object (one request).
 */
class Dashboard
{
    /** status => [label, badge class, icon] — same as the EOI list */
    public const STATUSES = [
        'draft'              => ['Draft',                     'status-draft',     'bi-pencil'],
        'submitted'          => ['Submitted',                 'status-submitted', 'bi-send'],
        'returned'           => ['Returned',                  'status-referred',  'bi-arrow-return-left'],
        'in_review'          => ['In review',                 'status-in-review', 'bi-hourglass-split'],
        'invited'            => ['Invited to CI',             'status-approved',  'bi-patch-check'],
        'due_diligence'      => ['Due diligence',             'status-in-review', 'bi-search'],
        'dd_hold'            => ['DD on hold',                'status-referred',  'bi-pause-circle'],
        'committee'          => ['Investment Committee',      'status-approved',  'bi-people'],
        'ic_deferred'        => ['Deferred by IC',            'status-referred',  'bi-pause-circle'],
        'authorisation'      => ['Final authorisation',       'status-approved',  'bi-patch-check'],
        'authorisation_hold' => ['Authorisation on hold',     'status-referred',  'bi-pause-circle'],
        'authorised'         => ['Authorised',                'status-approved',  'bi-patch-check-fill'],
        'capital_pending'    => ['Awaiting investor capital', 'status-referred',  'bi-shield-exclamation'],
        'cleared'            => ['Cleared for disbursement',  'status-approved',  'bi-cash-coin'],
        'escalated'          => ['Escalated',                 'status-rejected',  'bi-exclamation-octagon'],
        'not_progressed'     => ['Not progressed',            'status-rejected',  'bi-x-circle'],
        'withdrawn'          => ['Withdrawn',                 'status-draft',     'bi-slash-circle'],
    ];

    /** Pipeline stage => [label, statuses, icon] */
    public const STAGES = [
        'g1'         => ['G1 · Screening',     ['submitted', 'in_review', 'returned'],         'bi-funnel'],
        'g2'         => ['G2 · Due diligence', ['invited', 'due_diligence', 'dd_hold'],        'bi-search'],
        'g3'         => ['G3 · Committee',     ['committee', 'ic_deferred'],                   'bi-people'],
        'g4'         => ['G4 · Authorisation', ['authorisation', 'authorisation_hold'],        'bi-patch-check'],
        'g5'         => ['G5 · Conditions',    ['authorised', 'capital_pending', 'escalated'], 'bi-list-check'],
        'disbursing' => ['Disbursing',         ['cleared'],                                    'bi-cash-coin'],
    ];

    public const EXITED = ['not_progressed', 'withdrawn'];

    /** Who may see grant / disbursement figures */
    public const MONEY_PERMISSIONS = ['eoi.view-all', 'gate5.verify', 'disburse.process', 'portfolio.monitor', 'reports.view'];

    protected array $cache = [];

    public function __construct(public User $user) {}

    /* =====================================================================
     | Helpers
     ===================================================================== */

    protected function remember(string $key, callable $fn): mixed
    {
        return $this->cache[$key] ??= $fn();
    }

    public static function rate(): float
    {
        return (float) config('cif.usd_to_lsl', 18.00);
    }

    /** "M 450,000" */
    public static function m($v): string
    {
        return 'M '.number_format((float) $v);
    }

    /** "US$ 25,000" */
    public static function u($v): string
    {
        return 'US$ '.number_format((float) $v / max(self::rate(), 0.0001));
    }

    public static function eoiUrl(int $id, string $tab = 'overview'): string
    {
        return route('expression-of-interest.show', ['id' => $id]).'?tab='.$tab;
    }

    public function roleLabel(): string
    {
        return $this->user->loadMissing('roles')->primary_role?->display_name ?? 'User';
    }

    public function canSeeMoney(): bool
    {
        return $this->user->canAny(self::MONEY_PERMISSIONS);
    }

    /** EOIs this user may see */
    public function visible(): Builder
    {
        return EOI::query()->visibleTo($this->user);
    }

    /** Gate 5 records for the EOIs this user may see */
    public function reviews(): Builder
    {
        return ConditionReview::query()->whereIn('expression_of_interest_id', $this->visible()->select('id'));
    }

    protected function row(?EOI $e, string $tab, ?string $meta = null): ?array
    {
        return $e ? [
            'ref'  => $e->reference,
            'name' => $e->business_name ?: ($e->user?->name ?? '—'),
            'url'  => self::eoiUrl($e->id, $tab),
            'meta' => $meta,
        ] : null;
    }

    /* =====================================================================
     | Counts
     ===================================================================== */

    /** status => count */
    public function statusCounts(): array
    {
        return $this->remember('counts', fn () => $this->visible()->toBase()
            ->selectRaw('status, COUNT(*) as c')->groupBy('status')
            ->pluck('c', 'status')->map(fn ($c) => (int) $c)->all());
    }

    /** Sum of the counts for these statuses */
    public function count(string ...$statuses): int
    {
        $c = $this->statusCounts();

        return collect($statuses)->sum(fn ($s) => $c[$s] ?? 0);
    }

    /** stage key => count */
    public function stageCounts(): array
    {
        return collect(self::STAGES)->map(fn ($s) => $this->count(...$s[1]))->all();
    }

    public function totals(): array
    {
        $active = array_sum($this->stageCounts());

        return [
            'applications' => array_sum($this->statusCounts()) - $this->count('draft'),
            'active'       => $active,
            'drafts'       => $this->count('draft'),
            'exited'       => $this->count(...self::EXITED),
            'screening'    => $this->count('submitted', 'in_review'),
        ];
    }

    /* =====================================================================
     | Money (Maloti)
     ===================================================================== */

    public function money(): array
    {
        return $this->remember('money', function () {
            $reviews = $this->reviews()->get(['id', 'grant_amount', 'private_amount', 'outcome']);
            $ids     = $reviews->pluck('id');

            $grant     = (float) $reviews->sum('grant_amount');
            $private   = (float) $reviews->sum('private_amount');
            $released  = (float) DisbursementTranche::whereIn('condition_review_id', $ids)->whereNotNull('released_on')->sum('amount');
            $scheduled = (float) DisbursementTranche::whereIn('condition_review_id', $ids)->whereNull('released_on')->sum('amount');
            $verified  = (float) CapitalDeployment::whereIn('condition_review_id', $ids)->where('status', 'verified')->sum('amount');

            $scheduled = min($scheduled, max(0, $grant - $released));

            return [
                'transactions' => $reviews->count(),
                'cleared'      => $reviews->where('outcome', ConditionReview::OUTCOME_CLEARED)->count(),
                'grant'        => $grant,
                'private'      => $private,
                'released'     => $released,
                'scheduled'    => $scheduled,                                   // in tranches, not yet released
                'unscheduled'  => max(0, $grant - $released - $scheduled),      // not yet in any tranche
                'toDisburse'   => max(0, $grant - $released),
                'verified'     => $verified,
                'releasedPct'  => $grant > 0 ? (int) round($released / $grant * 100) : 0,
                'leverage'     => $released > 0 ? round($verified / $released, 1) : null,
            ];
        });
    }

    /** Last 12 months: applications submitted and grant released */
    public function monthly(): array
    {
        return $this->remember('monthly', function () {
            $start  = now()->startOfMonth()->subMonths(11);
            $months = collect(range(0, 11))->map(fn ($i) => $start->copy()->addMonths($i));

            $submitted = $this->visible()->whereNotNull('submitted_at')->where('submitted_at', '>=', $start)
                ->pluck('submitted_at')->countBy(fn ($d) => Carbon::parse($d)->format('Y-m'));

            $released = DisbursementTranche::whereIn('condition_review_id', $this->reviews()->select('id'))
                ->whereNotNull('released_on')->where('released_on', '>=', $start)
                ->get(['released_on', 'amount'])
                ->groupBy(fn ($t) => $t->released_on->format('Y-m'))->map->sum('amount');

            return [
                'labels'    => $months->map(fn ($m) => $m->format('M y'))->all(),
                'submitted' => $months->map(fn ($m) => (int) ($submitted[$m->format('Y-m')] ?? 0))->all(),
                'released'  => $months->map(fn ($m) => round((float) ($released[$m->format('Y-m')] ?? 0)))->all(),
            ];
        });
    }

    /* =====================================================================
     | Needs your attention
     |   [title, hint, icon, tone, role, items: [ref, name, url, meta]]
     ===================================================================== */

    public function tasks(): array
    {
        return $this->remember('tasks', fn () => $this->buildTasks());
    }

    public function taskCount(): int
    {
        return collect($this->tasks())->sum(fn ($t) => $t['items']->count());
    }

    protected function buildTasks(): array
    {
        $u     = $this->user;
        $tasks = [];
        $notCleared = fn ($q) => $q->where(fn ($w) => $w->where('outcome', '!=', ConditionReview::OUTCOME_CLEARED)->orWhereNull('outcome'));

        $add = function (string $title, string $hint, string $icon, string $tone, string $role, Collection $items) use (&$tasks) {
            $items = $items->filter()->values();
            if ($items->isNotEmpty()) {
                $tasks[] = compact('title', 'hint', 'icon', 'tone', 'role', 'items');
            }
        };
        $eois = fn (array $statuses) => $this->visible()->with('user:id,name')->whereIn('status', $statuses)->latest('updated_at')->get();

        /* ---------- Gate 1 · PMU ---------- */
        if ($u->can('gate1.decide')) {
            $add('Screen new applications', 'Check completeness and eligibility (Gate 1).', 'bi-funnel', 'warning', 'PMU',
                $eois(['submitted', 'in_review'])->map(fn ($e) => $this->row($e, 'g1', 'Submitted '.$e->submitted_at?->diffForHumans())));
        }

        /* ---------- Gate 2 · PMU ---------- */
        if ($u->can('gate2.assess')) {
            $add('Due diligence', 'Assess the enterprise, investor and transaction (Gate 2).', 'bi-search', 'primary', 'PMU',
                $eois(['invited', 'due_diligence'])->map(fn ($e) => $this->row($e, 'g2', self::STATUSES[$e->status][0])));

            $add('Deferred by the Committee', "Act on the Committee's request and resubmit.", 'bi-pause-circle', 'warning', 'PMU',
                $eois(['ic_deferred'])->map(fn ($e) => $this->row($e, 'g3')));

            $add('Waiting on the applicant', 'Clarification / remediation requested at Gate 2.', 'bi-hourglass', 'muted', 'PMU',
                $eois(['dd_hold'])->map(fn ($e) => $this->row($e, 'g2', 'On hold since '.$e->updated_at?->format('d M Y'))));
        }

        /* ---------- Gate 3 · Committee ---------- */
        if ($u->canAny(['gate3.score', 'gate3.decide'])) {
            $open = CommitteeReview::whereNull('decided_at')
                ->whereHas('expressionOfInterest', fn ($q) => $q->where('status', EOI::STATUS_COMMITTEE))
                ->with(['expressionOfInterest.user:id,name', 'scorecards'])->get();

            if ($u->can('gate3.score')) {
                $add('Submit your scorecard', 'Declare any conflict of interest and score the transaction.', 'bi-clipboard2-check', 'warning', 'Committee',
                    $open->reject(fn ($r) => $r->scorecards->first(fn ($c) => $c->user_id === $u->id && ($c->isSubmitted() || $c->isRecused())))
                        ->map(fn ($r) => $this->row($r->expressionOfInterest, 'g3', $r->meeting_date ? 'Meeting '.$r->meeting_date->format('d M Y') : 'Round '.$r->round)));
            }
            if ($u->can('gate3.decide')) {
                $members = CommitteeReview::members();
                $add('Record the Committee decision', 'Every member has scored — record the recommendation.', 'bi-hammer', 'accent', 'Committee',
                    $open->filter(fn ($r) => $r->scoringStatus($members)['complete'])
                        ->map(fn ($r) => $this->row($r->expressionOfInterest, 'g3', 'All scorecards in')));
            }
        }

        /* ---------- Gate 4 · MD / PS (recorded by the PMU) ---------- */
        if ($u->canAny(['gate4.md', 'gate4.ps', 'gate5.verify'])) {
            $rounds = Authorisation::whereNull('decided_at')
                ->whereHas('expressionOfInterest', fn ($q) => $q->where('status', EOI::STATUS_AUTHORISATION))
                ->with('expressionOfInterest.user:id,name')->get();

            $meta = fn ($a, $role) => $a->hasSignedCopy($role) ? 'Signed copy uploaded — record the decision'
                : ($a->sentForSigning($role) ? 'Sent for signing '.$a->{"{$role}_sent_at"}->diffForHumans() : 'Not yet sent for signing');

            if ($u->canAny(['gate4.md', 'gate5.verify'])) {
                $add('Managing Director approval', 'Gate 4 — first approval.', 'bi-person-badge', 'primary', 'MD · PMU',
                    $rounds->filter(fn ($a) => $a->stage() === 'md')->map(fn ($a) => $this->row($a->expressionOfInterest, 'g4', $meta($a, 'md'))));
            }
            if ($u->canAny(['gate4.ps', 'gate5.verify'])) {
                $add('Principal Secretary authorisation', 'Gate 4 — final authorisation after the MD.', 'bi-bank', 'primary', 'PS · PMU',
                    $rounds->filter(fn ($a) => $a->stage() === 'ps')->map(fn ($a) => $this->row($a->expressionOfInterest, 'g4', $meta($a, 'ps'))));
            }
            if ($u->can('gate5.verify')) {
                $add('Authorisation on hold', 'Resolve the clarification and resubmit.', 'bi-pause-circle', 'warning', 'PMU',
                    $eois(['authorisation_hold'])->map(fn ($e) => $this->row($e, 'g4')));
            }
        }

        /* ---------- Gate 5 · PMU ---------- */
        if ($u->can('gate5.verify')) {
            $add('Condition evidence to verify', 'The enterprise uploaded evidence — mark the condition met.', 'bi-file-earmark-check', 'warning', 'PMU',
                CpCondition::where('status', 'outstanding')->whereNotNull('evidence_path')
                    ->whereHas('review.expressionOfInterest', fn ($q) => $q->whereIn('status', EOI::GATE5_VISIBLE))
                    ->with('review.expressionOfInterest.user:id,name')->latest('evidence_at')->get()
                    ->map(fn ($c) => $this->row($c->review?->expressionOfInterest, 'g5', Str::limit($c->finding, 80))));

            $add('Gate 5 in progress', 'Still to do before the transaction can be cleared for disbursement.', 'bi-list-check', 'primary', 'PMU',
                $notCleared($this->reviews())
                    ->whereHas('expressionOfInterest', fn ($q) => $q->whereIn('status', [EOI::STATUS_AUTHORISED, EOI::STATUS_CAPITAL_PENDING]))
                    ->with('expressionOfInterest.user:id,name')->get()
                    ->map(function ($r) {
                        $n = count($r->clearanceBlockers());
                        return $this->row($r->expressionOfInterest, 'g5', $n ? $n.' '.Str::plural('item', $n).' outstanding' : 'Ready to clear');
                    }));
        }
        if ($u->can('capital.verify')) {
            $add('Investor capital to verify', 'Proof of investor payment waiting for verification.', 'bi-shield-check', 'warning', 'PMU',
                CapitalDeployment::where('status', 'submitted')
                    ->whereHas('review.expressionOfInterest', fn ($q) => $q->whereIn('status', EOI::GATE5_VISIBLE))
                    ->with('review.expressionOfInterest.user:id,name')->latest()->get()
                    ->map(fn ($d) => $this->row($d->review?->expressionOfInterest, 'g5', $d->investor.' · '.self::m($d->amount))));
        }

        /* ---------- Finance ---------- */
        if ($u->canAny(['disburse.process', 'gate5.verify'])) {
            $cleared = $this->reviews()->where('outcome', ConditionReview::OUTCOME_CLEARED)
                ->with(['expressionOfInterest.user:id,name', 'tranches'])->get();

            if ($u->can('disburse.process')) {
                $add('Tranches ready to release', 'Release with the payment reference, date and proof of payment.', 'bi-cash-coin', 'accent', 'Finance',
                    $cleared->map(function ($r) {
                        $next = $r->tranches->whereNull('released_on')->sortBy('position')->first();
                        return $next ? $this->row($r->expressionOfInterest, 'g5', $next->label.' · '.self::m($next->amount)) : null;
                    }));
            }
            $add('Schedule the next tranche', 'Nothing left in tranches, but part of the grant is still to be disbursed.', 'bi-calendar-plus', 'muted', 'Finance · PMU',
                $cleared->map(function ($r) {
                    $left    = max(0, (float) $r->grant_amount - (float) $r->tranches->whereNotNull('released_on')->sum('amount'));
                    $pending = $r->tranches->whereNull('released_on')->isNotEmpty();
                    return ! $pending && $left > 0.01 ? $this->row($r->expressionOfInterest, 'g5', self::m($left).' still to be disbursed') : null;
                }));
        }

        return $tasks;
    }

    /* =====================================================================
     | Tables
     ===================================================================== */

    /** Gate 5 / disbursing transactions with their grant position (most still to disburse first) */
    public function disbursements(): Collection
    {
        return $this->remember('disbursements', fn () => $this->reviews()
            ->whereHas('expressionOfInterest', fn ($q) => $q->whereIn('status', EOI::GATE5_VISIBLE))
            ->with(['expressionOfInterest.user:id,name', 'tranches'])
            ->get()
            ->map(function ($r) {
                $grant    = (float) $r->grant_amount;
                $released = (float) $r->tranches->whereNotNull('released_on')->sum('amount');

                return (object) [
                    'eoi'      => $r->expressionOfInterest,
                    'cleared'  => $r->outcome === ConditionReview::OUTCOME_CLEARED,
                    'grant'    => $grant,
                    'released' => $released,
                    'left'     => max(0, $grant - $released),
                    'pct'      => $grant > 0 ? min(100, (int) round($released / $grant * 100)) : 0,
                    'tranches' => $r->tranches->count(),
                    'paid'     => $r->tranches->whereNotNull('released_on')->count(),
                    'next'     => $r->tranches->whereNull('released_on')->sortBy('position')->first(),
                    'last'     => $r->tranches->whereNotNull('released_on')->sortByDesc('released_on')->first()?->released_on,
                ];
            })
            ->filter(fn ($d) => $d->eoi)
            ->sortByDesc('left')
            ->values());
    }

    /** Most recently updated applications (drafts left out) */
    public function recent(int $limit = 8): Collection
    {
        return $this->visible()->with('user:id,name')
            ->where('status', '!=', EOI::STATUS_DRAFT)
            ->latest('updated_at')->limit($limit)->get();
    }
}