<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * One investment made into an enterprise. Amounts in Maloti (LSL).
 *   Declared by the enterprise on its profile, or recorded by a reviewer on the application's Investments tab.
 *   Reviewers verify or reject each investment and use the history (how often, how much, by whom) at eligibility.
 */
class EnterpriseInvestment extends Model
{
    public const DISK = 'local';

    public const MAX_KB = 20480;

    public const PROOF_MIMES = 'pdf,jpg,jpeg,png,docx,doc,xlsx,xls';

    public const STATUS_DECLARED = 'declared';
    public const STATUS_VERIFIED = 'verified';
    public const STATUS_REJECTED = 'rejected';

    /** status => [label, pill, icon] */
    public const STATUSES = [
        self::STATUS_DECLARED => ['Not yet verified', 'pill-warn',   'bi-hourglass-split'],
        self::STATUS_VERIFIED => ['Verified',         'pill-ok',     'bi-patch-check'],
        self::STATUS_REJECTED => ['Rejected',         'pill-danger', 'bi-x-octagon'],
    ];

    public const INSTRUMENTS = [
        'equity'           => 'Equity',
        'convertible_note' => 'Convertible Note',
        'safe'             => 'SAFE',
        'revenue_share'    => 'Revenue Share',
        'loan'             => 'Loan',
        'other'            => 'Other',
    ];

    public const SOURCES = ['enterprise' => 'Declared by the enterprise', 'reviewer' => 'Recorded by a reviewer'];

    protected $fillable = [
        'enterprise_profile_id', 'investor_name', 'investor_type', 'investor_country', 'amount', 'invested_on',
        'instrument', 'round_label', 'notes', 'proof_path', 'proof_name', 'proof_size', 'source', 'recorded_by',
        'status', 'review_note', 'reviewed_by', 'reviewed_at',
    ];

    protected $casts = [
        'amount'      => 'decimal:2',
        'invested_on' => 'date',
        'reviewed_at' => 'datetime',
        'proof_size'  => 'integer',
    ];

    protected static function booted(): void
    {
        static::deleting(function (EnterpriseInvestment $i) {
            if ($i->proof_path) {
                Storage::disk(self::DISK)->delete($i->proof_path);
            }
        });
    }

    /* ---------------- Relations ---------------- */

    public function profile(): BelongsTo
    {
        return $this->belongsTo(EnterpriseProfile::class, 'enterprise_profile_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /* ---------------- State ---------------- */

    public function isVerified(): bool
    {
        return $this->status === self::STATUS_VERIFIED;
    }

    /** The enterprise may change or remove its own declarations until a reviewer has verified them */
    public function isEditableByEnterprise(): bool
    {
        return $this->source === 'enterprise' && $this->status !== self::STATUS_VERIFIED;
    }

    public function getProofSizeLabelAttribute(): ?string
    {
        if (! $this->proof_size) {
            return null;
        }

        return $this->proof_size >= 1048576 ? round($this->proof_size / 1048576, 1).' MB' : max(1, round($this->proof_size / 1024)).' KB';
    }

    /* ---------------- Investor matching ---------------- */

    /** "Basotho Capital (Pty) Ltd" → "basotho capital" — used to spot the same investor across records */
    public static function investorKey(?string $name): string
    {
        $n = Str::of((string) $name)->lower()->replaceMatches('/[^a-z0-9 ]+/', ' ')->squish()->toString();
        $n = preg_replace('/\b(pty|ltd|limited|inc|llc|plc|co|company|proprietary)\b/', '', $n);

        return trim(preg_replace('/\s+/', ' ', $n));
    }

    /**
     * Investments of one enterprise grouped by investor (newest investor activity first).
     * Each group: name, key, type, country, count, total, verified, first, last, items.
     */
    public static function byInvestor(Collection $investments): Collection
    {
        return $investments
            ->groupBy(fn (self $i) => self::investorKey($i->investor_name))
            ->map(function (Collection $items, string $key) {
                $counted = $items->where('status', '!=', self::STATUS_REJECTED);

                return [
                    'key'      => $key,
                    'name'     => $items->sortByDesc('invested_on')->first()->investor_name,
                    'type'     => $items->pluck('investor_type')->filter()->last(),
                    'country'  => $items->pluck('investor_country')->filter()->last(),
                    'count'    => $counted->count(),
                    'total'    => (float) $counted->sum('amount'),
                    'verified' => (float) $items->where('status', self::STATUS_VERIFIED)->sum('amount'),
                    'first'    => $items->min('invested_on'),
                    'last'     => $items->max('invested_on'),
                    'items'    => $items->sortByDesc('invested_on')->values(),
                ];
            })
            ->sortByDesc(fn ($g) => $g['last'])
            ->values();
    }

    /**
     * Each investor's record across the whole facility (every enterprise, including the current one).
     *   investorKey => [
     *     'investments' => number of investments (rejected ones not counted),
     *     'enterprises' => number of enterprises they have invested in,
     *     'total' / 'verified' => Maloti,
     *     'committing'  => number of other enterprises where they are in the current round,
     *     'per' => [[ 'id', 'name', 'current' (bool), 'count', 'total', 'verified', 'first', 'last', 'committed' (current-round ticket or null) ], …]
     *   ]
     */
    public static function facilityRecord(array $keys, int $currentProfileId): array
    {
        $keys = array_values(array_filter(array_unique($keys)));
        if (! $keys) {
            return [];
        }

        $label = fn (?EnterpriseProfile $p) => $p ? ($p->short_name ?: $p->registered_name ?: 'Enterprise #'.$p->id) : 'Unknown enterprise';
        $per   = [];   // key => profileId => row
        $blank = fn (int $id, string $name) => ['id' => $id, 'name' => $name, 'current' => $id === $currentProfileId,
            'count' => 0, 'total' => 0.0, 'verified' => 0.0, 'first' => null, 'last' => null, 'committed' => null];

        // Investments made (all enterprises)
        self::query()->with('profile:id,registered_name,short_name')
            ->where('status', '!=', self::STATUS_REJECTED)
            ->get(['id', 'enterprise_profile_id', 'investor_name', 'amount', 'invested_on', 'status'])
            ->each(function (self $i) use ($keys, &$per, $label, $blank) {
                $k = self::investorKey($i->investor_name);
                if (! in_array($k, $keys, true)) {
                    return;
                }
                $pid = $i->enterprise_profile_id;
                $row = $per[$k][$pid] ?? $blank($pid, $label($i->profile));

                $row['count']++;
                $row['total']    += (float) $i->amount;
                $row['verified'] += $i->status === self::STATUS_VERIFIED ? (float) $i->amount : 0;
                $row['first']     = $row['first'] && $row['first']->lte($i->invested_on) ? $row['first'] : $i->invested_on;
                $row['last']      = $row['last'] && $row['last']->gte($i->invested_on) ? $row['last'] : $i->invested_on;

                $per[$k][$pid] = $row;
            });

        // Current-round commitments (from each enterprise's profile)
        EnterpriseProfile::query()->whereNotNull('round_investors')
            ->get(['id', 'registered_name', 'short_name', 'round_investors'])
            ->each(function (EnterpriseProfile $p) use ($keys, &$per, $label, $blank) {
                foreach ($p->investorRows() as $r) {
                    $k = self::investorKey($r['name'] ?? '');
                    if (! in_array($k, $keys, true)) {
                        continue;
                    }
                    $row = $per[$k][$p->id] ?? $blank($p->id, $label($p));
                    $row['committed'] = (float) ($row['committed'] ?? 0) + (float) ($r['ticket'] ?? 0);
                    $per[$k][$p->id] = $row;
                }
            });

        return collect($per)->map(function (array $rows) {
            $rows     = collect($rows)->sortByDesc(fn ($r) => [$r['current'] ? 1 : 0, $r['count'], $r['total']])->values();
            $invested = $rows->where('count', '>', 0);

            return [
                'investments' => (int) $invested->sum('count'),
                'enterprises' => $invested->count(),
                'total'       => (float) $invested->sum('total'),
                'verified'    => (float) $invested->sum('verified'),
                'committing'  => $rows->where('current', false)->whereNotNull('committed')->count(),
                'per'         => $rows->all(),
            ];
        })->all();
    }

    /** "Basotho Capital: 5 investments in 3 enterprises (M 4,200,000)" — one line per investor, for the gates */
    public static function trackRecordLines(EnterpriseProfile $profile): array
    {
        $names = collect($profile->investments)->where('status', '!=', self::STATUS_REJECTED)->pluck('investor_name')
            ->merge(collect($profile->investorRows())->pluck('name'))
            ->unique(fn ($n) => self::investorKey($n))->values();

        $record = self::facilityRecord($names->map(fn ($n) => self::investorKey($n))->all(), $profile->id);

        return $names->map(function ($name) use ($record) {
            $r = $record[self::investorKey($name)] ?? null;
            if (! $r || $r['investments'] === 0) {
                return "{$name}: no recorded investments in the facility yet";
            }

            return "{$name}: {$r['investments']} ".Str::plural('investment', $r['investments'])
                ." in {$r['enterprises']} ".Str::plural('enterprise', $r['enterprises'])
                .' (M '.number_format($r['total']).', M '.number_format($r['verified']).' verified)';
        })->all();
    }

    /** "6 investments · M 2,400,000 · 3 investors (1 repeat) · 2 verified" — one-line summary for the gates */
    public static function summaryLine(Collection $investments): ?string
    {
        $counted = $investments->where('status', '!=', self::STATUS_REJECTED);
        if ($counted->isEmpty()) {
            return null;
        }

        $groups  = self::byInvestor($counted);
        $repeat  = $groups->where('count', '>', 1)->count();
        $verified = $counted->where('status', self::STATUS_VERIFIED)->count();

        return collect([
            $counted->count().' '.Str::plural('investment', $counted->count()),
            'M '.number_format((float) $counted->sum('amount')),
            $groups->count().' '.Str::plural('investor', $groups->count()).($repeat ? " ({$repeat} repeat)" : ''),
            "{$verified} verified",
        ])->implode(' · ');
    }
}