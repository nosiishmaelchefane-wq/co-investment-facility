<?php

namespace App\Support;

use App\Models\CommitteeReview;
use App\Models\ExpressionOfInterest as EOI;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Compose Email · who an e-mail goes to, and the merge fields.
 *
 *   Recipient spec (what the form holds):
 *     ['groups' => ['applicants', 'stage', 'applicant', 'committee', 'pmu', 'finance'],
 *      'stage' => 'g2', 'application_id' => 9, 'to' => 'a@x.ls, b@y.ls', 'cc' => '', 'bcc' => '', 'individually' => true]
 *
 *   resolve($spec) → [email => ['email', 'name', 'eoi' => ?EOI]]   (deduplicated, inactive accounts left out)
 *   merge($text, $recipient, $linkedEoi, html: true) → {name} {business} {reference} {status} {link} filled in
 */
class EmailRecipients
{
    /** group => [label, hint, icon] */
    public const GROUPS = [
        'applicants' => ['All applicants',         'Enterprises with a submitted application', 'bi-shop'],
        'stage'      => ['Applicants at a gate',   'Pick the gate below',                      'bi-diagram-3'],
        'applicant'  => ['This application',       'The applicant of the linked application',  'bi-person-check'],
        'committee'  => ['Investment Committee',   'All active Committee members',             'bi-people'],
        'pmu'        => ['PMU',                    'Programme Management Unit',                'bi-person-gear'],
        'finance'    => ['Finance',                'Disbursement team',                        'bi-cash-stack'],
    ];

    /** Merge fields shown as buttons on the form */
    public const MERGE_FIELDS = ['{name}', '{business}', '{reference}', '{status}', '{link}'];

    /** Most people one e-mail may go to */
    public const MAX_RECIPIENTS = 500;

    public static function defaults(): array
    {
        return ['groups' => [], 'stage' => '', 'application_id' => null, 'to' => '', 'cc' => '', 'bcc' => '', 'individually' => true];
    }

    /** Staff only — the PMU and administrators */
    public static function canCompose(?User $user): bool
    {
        return $user && ! $user->hasRole('enterprise') && $user->canAny(['eoi.view-all', 'users.manage']);
    }

    /** stage key => [label, statuses] (same gates as the dashboard) */
    public static function stages(): array
    {
        return collect(Dashboard::STAGES)->map(fn ($s) => [$s[0], $s[1]])->all();
    }

    /* =====================================================================
     | Groups
     ===================================================================== */

    protected static function usable(?User $u): bool
    {
        return $u && filled($u->email) && $u->status !== User::STATUS_INACTIVE;
    }

    /** One row per applicant (their most recent application in these statuses) */
    protected static function applicantsIn(?array $statuses): Collection
    {
        return EOI::with('user:id,name,email,status')
            ->when($statuses, fn ($q) => $q->whereIn('status', $statuses), fn ($q) => $q->where('status', '!=', EOI::STATUS_DRAFT))
            ->latest('updated_at')->get()
            ->filter(fn ($e) => self::usable($e->user))
            ->unique('user_id')
            ->map(fn ($e) => ['email' => $e->user->email, 'name' => $e->user->name, 'eoi' => $e]);
    }

    protected static function usersWithRole(string $role): Collection
    {
        $users = rescue(fn () => User::role($role)->get(['id', 'name', 'email', 'status']), collect(), false);

        return $users->filter(fn ($u) => self::usable($u))->map(fn ($u) => ['email' => $u->email, 'name' => $u->name, 'eoi' => null]);
    }

    /** Rows for one group */
    public static function group(string $group, array $spec = []): Collection
    {
        return match ($group) {
            'applicants' => self::applicantsIn(null),
            'stage'      => filled($spec['stage'] ?? null) && isset(self::stages()[$spec['stage']]) ? self::applicantsIn(self::stages()[$spec['stage']][1]) : collect(),
            'applicant'  => ($e = filled($spec['application_id'] ?? null) ? EOI::with('user:id,name,email,status')->find($spec['application_id']) : null) && self::usable($e->user)
                                ? collect([['email' => $e->user->email, 'name' => $e->user->name, 'eoi' => $e]]) : collect(),
            'committee'  => CommitteeReview::members()->filter(fn ($u) => filled($u->email))->map(fn ($u) => ['email' => $u->email, 'name' => $u->name, 'eoi' => null]),
            'pmu'        => self::usersWithRole('pmu'),
            'finance'    => self::usersWithRole('finance'),
            default      => collect(),
        };
    }

    /** group => number of people (for the tiles) */
    public static function groupCounts(): array
    {
        return collect(array_keys(self::GROUPS))
            ->reject(fn ($g) => in_array($g, ['stage', 'applicant'], true))
            ->mapWithKeys(fn ($g) => [$g => self::group($g)->count()])->all();
    }

    /** stage => number of applicants (for the gate list) */
    public static function stageCounts(): array
    {
        return collect(self::stages())->map(fn ($s, $key) => self::applicantsIn($s[1])->count())->all();
    }

    /* =====================================================================
     | Resolve the whole spec
     ===================================================================== */

    /** [email => ['email', 'name', 'eoi']] — groups + typed "To" addresses, no duplicates */
    public static function resolve(array $spec): Collection
    {
        $spec = array_merge(self::defaults(), $spec);
        $rows = collect();

        foreach ((array) $spec['groups'] as $g) {
            $rows = $rows->merge(self::group($g, $spec)->values());
        }

        // Typed addresses: use the account's name if we know the address
        $typed = self::parse($spec['to']);
        if ($typed) {
            $known = User::whereIn('email', $typed)->get(['name', 'email'])->keyBy(fn ($u) => Str::lower($u->email));
            foreach ($typed as $email) {
                $rows->push(['email' => $email, 'name' => $known[Str::lower($email)]->name ?? null, 'eoi' => null]);
            }
        }

        return $rows->keyBy(fn ($r) => Str::lower($r['email']));
    }

    /** Valid addresses from "a@x.ls, b@y.ls; c@z.ls" */
    public static function parse(?string $list): array
    {
        return collect(preg_split('/[\s,;]+/', (string) $list))
            ->map(fn ($e) => trim($e))->filter(fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL))
            ->unique(fn ($e) => Str::lower($e))->values()->all();
    }

    /** Entries in the list that are not e-mail addresses */
    public static function invalid(?string $list): array
    {
        return collect(preg_split('/[\s,;]+/', (string) $list))
            ->map(fn ($e) => trim($e))->filter()->reject(fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL))->values()->all();
    }

    /* =====================================================================
     | Merge fields
     ===================================================================== */

    /**
     * Fill {name} {business} {reference} {status} {link}.
     * The linked application (if any) wins over the recipient's own application.
     */
    public static function merge(string $text, ?array $recipient, ?EOI $linked = null, bool $html = true): string
    {
        $eoi  = $linked ?? ($recipient['eoi'] ?? null);
        $url  = $eoi ? Dashboard::eoiUrl($eoi->id) : url('/');
        $safe = fn ($v) => $html ? e((string) $v) : (string) $v;

        return strtr($text, [
            '{name}'      => $safe(($recipient['name'] ?? null) ?: 'Sir / Madam'),
            '{business}'  => $safe($eoi?->business_name ?: 'your enterprise'),
            '{reference}' => $safe($eoi?->reference ?? ''),
            '{status}'    => $safe($eoi ? (Dashboard::STATUSES[$eoi->status][0] ?? $eoi->status) : ''),
            '{link}'      => $html ? '<a href="'.e($url).'">'.e($eoi ? 'View application '.$eoi->reference : 'Open the platform').'</a>' : $url,
        ]);
    }

    /** Keep only safe formatting from the editor (no scripts, styles or event handlers) */
    public static function clean(string $html): string
    {
        if (function_exists('clean')) {          // mews/purifier, if installed
            return clean($html);
        }

        $html = strip_tags($html, '<p><br><strong><b><em><i><u><s><ul><ol><li><a><h1><h2><h3><h4><blockquote><span>');
        $html = preg_replace('/\s(on\w+|style|class)\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);

        return preg_replace('/href\s*=\s*(["\']?)\s*javascript:[^"\'>\s]*\1/i', 'href="#"', $html);
    }
}