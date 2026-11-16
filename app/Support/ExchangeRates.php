<?php

namespace App\Support;

use App\Models\ExchangeRate;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Live US$ → Maloti (LSL) exchange rate.
 *
 *   ExchangeRates::usdToLsl()   the rate the whole system uses (cached; latest fetched rate, else config fallback)
 *   ExchangeRates::refresh()    fetch a new rate from the providers and store it (run by the scheduler)
 *   ExchangeRates::info()       ['rate', 'source', 'rate_date', 'fetched_at', 'live' => bool] — for showing on screen
 *
 * Providers (free, no API key), tried in order:
 *   1. ExchangeRate-API open access  https://open.er-api.com/v6/latest/USD   → rates.LSL (updated daily)
 *   2. Frankfurter (ECB rates)        https://api.frankfurter.app/latest?from=USD&to=ZAR
 *      → the Maloti is pegged 1:1 to the South African rand, so USD→ZAR is used as USD→LSL
 * If both fail, the last stored rate keeps being used; with no stored rate, config('cif.usd_to_lsl') (.env CIF_USD_TO_LSL).
 *
 * AppServiceProvider puts the current rate into config('cif.usd_to_lsl') on every request, so every screen,
 * PDF, e-mail and calculation that reads that setting uses the live rate without further changes.
 */
class ExchangeRates
{
    public const CACHE_KEY = 'cif.fx.usd_lsl';

    /** A fetched rate outside this range is treated as an error and not stored */
    public const MIN_SANE = 5.0;
    public const MAX_SANE = 60.0;

    /** Fallback rate from config (.env CIF_USD_TO_LSL) */
    public static function fallback(): float
    {
        return (float) config('cif.usd_to_lsl_fallback', config('cif.usd_to_lsl', 18.00));
    }

    /** The rate to use now: 1 US$ = x Maloti */
    public static function usdToLsl(): float
    {
        return (float) self::info()['rate'];
    }

    /** Current rate with where it came from */
    public static function info(): array
    {
        return Cache::remember(self::CACHE_KEY, now()->addMinutes((int) config('cif.fx.cache_minutes', 60)), function () {
            try {
                $row = Schema::hasTable('exchange_rates') ? ExchangeRate::latestUsdLsl() : null;
            } catch (\Throwable $e) {
                $row = null;   // database not reachable yet (e.g. during install)
            }

            return $row
                ? ['rate' => (float) $row->rate, 'source' => $row->source, 'rate_date' => $row->rate_date?->toIso8601String(),
                   'fetched_at' => $row->fetched_at?->toIso8601String(), 'live' => true]
                : ['rate' => self::fallback(), 'source' => 'configured rate (CIF_USD_TO_LSL)', 'rate_date' => null,
                   'fetched_at' => null, 'live' => false];
        });
    }

    /**
     * Fetch the latest rate and store it. Returns the stored row, or null if every provider failed
     * (the previous rate then stays in use).
     */
    public static function refresh(): ?ExchangeRate
    {
        foreach ([fn () => self::fromExchangeRateApi(), fn () => self::fromFrankfurter()] as $provider) {
            try {
                $result = $provider();
            } catch (\Throwable $e) {
                Log::warning('Exchange rate provider failed: '.$e->getMessage());
                $result = null;
            }

            if ($result && $result['rate'] >= self::MIN_SANE && $result['rate'] <= self::MAX_SANE) {
                $row = ExchangeRate::create([
                    'base'       => 'USD',
                    'quote'      => 'LSL',
                    'rate'       => round($result['rate'], 6),
                    'source'     => $result['source'],
                    'rate_date'  => $result['rate_date'],
                    'fetched_at' => now(),
                ]);
                Cache::forget(self::CACHE_KEY);

                return $row;
            }

            if ($result) {
                Log::warning('Exchange rate rejected as implausible', $result);
            }
        }

        Log::error('No exchange rate provider answered — the previous US$/Maloti rate stays in use.');

        return null;
    }

    /** ExchangeRate-API open access: USD base, includes LSL */
    protected static function fromExchangeRateApi(): ?array
    {
        $res = Http::timeout(10)->retry(2, 500)->acceptJson()->get('https://open.er-api.com/v6/latest/USD');
        if (! $res->ok() || $res->json('result') !== 'success') {
            return null;
        }

        $rate = $res->json('rates.LSL') ?? $res->json('rates.ZAR');   // LSL = ZAR 1:1
        if (! is_numeric($rate)) {
            return null;
        }

        $updated = $res->json('time_last_update_unix');

        return [
            'rate'      => (float) $rate,
            'source'    => 'open.er-api.com'.($res->json('rates.LSL') ? '' : ' (ZAR)'),
            'rate_date' => is_numeric($updated) ? Carbon::createFromTimestamp((int) $updated) : now(),
        ];
    }

    /** Frankfurter (European Central Bank reference rates): USD → ZAR, used for LSL (pegged 1:1) */
    protected static function fromFrankfurter(): ?array
    {
        $res = Http::timeout(10)->retry(2, 500)->acceptJson()->get('https://api.frankfurter.app/latest', ['from' => 'USD', 'to' => 'ZAR']);
        $rate = $res->ok() ? $res->json('rates.ZAR') : null;

        return is_numeric($rate)
            ? ['rate' => (float) $rate, 'source' => 'frankfurter.app (ZAR, ECB)', 'rate_date' => $res->json('date') ? Carbon::parse($res->json('date')) : now()]
            : null;
    }

    /** Record a rate typed in by an administrator (e.g. the Central Bank of Lesotho rate on a given day) */
    public static function setManual(float $rate, ?string $note = null): ExchangeRate
    {
        abort_unless($rate >= self::MIN_SANE && $rate <= self::MAX_SANE, 422, 'That exchange rate is not plausible.');

        $row = ExchangeRate::create([
            'base' => 'USD', 'quote' => 'LSL', 'rate' => round($rate, 6),
            'source' => 'manual'.($note ? ' — '.$note : ''), 'rate_date' => now(), 'fetched_at' => now(),
        ]);
        Cache::forget(self::CACHE_KEY);

        return $row;
    }
}