<?php

namespace App\Support;

use App\Models\EnterpriseProfile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * LEHSFF Collaboration Platform — investor matchmaking → enterprise profile 8.2 Investor information.
 *
 *   CollaborationPlatform::enabled()             is the API configured (config/cif.php → collaboration)
 *   CollaborationPlatform::fetch($email)         the matchmaking result for that e-mail (array) or null
 *   CollaborationPlatform::sync($profile, $force) ask the Collaboration Platform and fill the profile's empty 8.2 answers
 *
 * The enterprise is found by the e-mail of the account that owns the profile — it must be the same e-mail the
 * enterprise registered with on the Collaboration Platform.
 *
 * Only EMPTY answers are filled, so nothing the applicant typed is overwritten. Every filled field and the value used
 * are recorded in enterprise_profiles.matchmaking, so the profile and the gates can show "From matchmaking" next to
 * them (and notice when the applicant later changed one). The investment amount is not filled — the match does not
 * say how much this investor is putting into this transaction, and the CAFI grant is worked out from that amount.
 */
class CollaborationPlatform
{
    /** Collaboration Platform investor type → profile 8.2.5 investor type */
    public const INVESTOR_TYPES = [
        'angel'         => 'Angel investor',
        'vc'            => 'Venture capital fund',
        'family_office' => 'Family office',
        'impact'        => 'Institutional investor',
        'corporate'     => 'Institutional investor',
        'government'    => 'Institutional investor',
        'accelerator'   => 'Other',
        'other'         => 'Other',
    ];

    /** Match status → profile 8.2.8 engagement status */
    public const ENGAGEMENT = [
        'proposed'    => 'initial',
        'introduced'  => 'initial',
        'in_talks'    => 'interest',
        'deal_closed' => 'agreement',
    ];

    /** Profile fields that may be filled from matchmaking (all in 8.2) */
    public const FIELDS = [
        'has_investor', 'lead_investor_name', 'lead_investor_contact_name', 'lead_investor_email', 'lead_investor_phone',
        'lead_investor_website', 'investor_type', 'lead_investor_country', 'transaction_status',
    ];

    public static function enabled(): bool
    {
        return filled(config('cif.collaboration.base_url')) && filled(config('cif.collaboration.token'));
    }

    /** The matchmaking result for an e-mail: ['found' => bool, ...] or null when the platform could not be reached */
    public static function fetch(string $email): ?array
    {
        if (! self::enabled()) {
            return null;
        }

        try {
            $res = Http::timeout((int) config('cif.collaboration.timeout', 8))
                ->retry(1, 300)
                ->withToken((string) config('cif.collaboration.token'))
                ->acceptJson()
                ->get(config('cif.collaboration.base_url').'/api/cif/enterprise-match', ['email' => $email]);
        } catch (\Throwable $e) {
            Log::warning('Collaboration Platform matchmaking API unreachable: '.$e->getMessage());
            return null;
        }

        if (! $res->ok() || ! is_array($res->json())) {
            Log::warning('Collaboration Platform matchmaking API answered '.$res->status());
            return null;
        }

        return $res->json();
    }

    /**
     * Ask the Collaboration Platform and fill the profile's empty 8.2 answers.
     * Without $force, a profile is only re-checked every config('cif.collaboration.sync_minutes') minutes.
     * Returns: 'filled' (answers were added) · 'unchanged' (match found, nothing empty to fill) · 'none' (no match)
     *          · 'skipped' (checked recently / not configured / no owner) · 'error' (platform not reachable)
     */
    public static function sync(EnterpriseProfile $p, bool $force = false): string
    {
        $email = $p->user?->email;
        if (! self::enabled() || ! $email) {
            return 'skipped';
        }
        $minutes = (int) config('cif.collaboration.sync_minutes', 360);
        if (! $force && $p->matchmaking_synced_at && $p->matchmaking_synced_at->gt(now()->subMinutes($minutes))) {
            return 'skipped';
        }

        $data = self::fetch($email);
        if ($data === null) {
            return 'error';
        }

        return self::apply($p, $data);
    }

    /**
     * Fill the profile's empty 8.2 answers from a matchmaking result — used by sync() (pulled from the
     * Collaboration Platform) and by the push API (the Collaboration Platform sends a new match here).
     * $data has the shape of GET /api/cif/enterprise-match: { found, enterprise, match, investor, source }
     * Returns: 'filled' · 'unchanged' · 'none'
     */
    public static function apply(EnterpriseProfile $p, array $data): string
    {
        if (! ($data['found'] ?? false)) {
            // Keep what was filled before (the applicant's profile is not emptied), just note the check
            $mm = (array) ($p->matchmaking ?? []);
            $mm['last_result'] = $data['reason'] ?? 'no_match';
            $mm['checked_at']  = now()->toIso8601String();
            $p->forceFill(['matchmaking' => $mm ?: null, 'matchmaking_synced_at' => now()]);
            $p->exists ? $p->saveQuietly() : null;
            return 'none';
        }

        $inv    = (array) ($data['investor'] ?? []);
        $match  = (array) ($data['match'] ?? []);
        $values = array_filter([
            'has_investor'               => true,
            'lead_investor_name'         => $inv['name'] ?? null,
            'lead_investor_contact_name' => $inv['contact_name'] ?? null,
            'lead_investor_email'        => $inv['email'] ?? null,
            'lead_investor_phone'        => $inv['phone'] ?? null,
            'lead_investor_website'      => $inv['website'] ?? null,
            'investor_type'              => self::INVESTOR_TYPES[collect($inv['types'] ?? [])->first() ?? ''] ?? null,
            'lead_investor_country'      => self::country($inv['country'] ?? null),
            'transaction_status'         => self::ENGAGEMENT[$match['status'] ?? ''] ?? null,
        ], fn ($v) => $v !== null && $v !== '');

        // Fill only what is empty; remember what came from matchmaking
        $previous = (array) (($p->matchmaking ?? [])['filled'] ?? []);
        $filled   = [];
        foreach ($values as $field => $value) {
            $current = $p->{$field};
            $empty   = $current === null || $current === '';
            $ours    = array_key_exists($field, $previous) && $current == $previous[$field];   // filled by us before and unchanged
            if ($empty || $ours) {
                $p->{$field} = $value;
                $filled[$field] = $value;
            }
        }
        // Keep the record of fields filled earlier that the applicant has not changed
        foreach ($previous as $field => $value) {
            if (! isset($filled[$field]) && $p->{$field} == $value) {
                $filled[$field] = $value;
            }
        }

        $changed = collect($filled)->contains(fn ($v, $f) => ($previous[$f] ?? null) != $v) || array_diff_key($filled, $previous);

        $p->matchmaking = [
            'match'       => $match,
            'investor'    => $inv,
            'enterprise'  => $data['enterprise'] ?? null,
            'filled'      => $filled,
            'fetched_at'  => now()->toIso8601String(),
            'last_result' => 'found',
        ];
        $p->matchmaking_synced_at = now();
        $p->save();   // the profile's saving hook keeps the older investor columns in step

        return $changed ? 'filled' : 'unchanged';
    }

    /** Match the profile's country list where possible (e.g. "south africa" → "South Africa") */
    protected static function country(?string $country): ?string
    {
        if (blank($country)) {
            return null;
        }
        $hit = collect(EnterpriseProfile::COUNTRIES)->first(fn ($c) => strcasecmp($c, trim($country)) === 0);

        return $hit ?? trim($country);
    }
}