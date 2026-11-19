<?php

/*
|--------------------------------------------------------------------------
| CIF settings
|--------------------------------------------------------------------------
*/

return [

    /*
     | Exchange rate US$ → Maloti (LSL): 1 USD = X LSL.
     | The platform fetches the live rate automatically (App\Support\ExchangeRates, scheduled in
     | routes/console.php) and uses the latest fetched rate everywhere. The value below is only the
     | FALLBACK used until the first rate is fetched:  .env  CIF_USD_TO_LSL=18.00
     |   fx.cache_minutes — how long the current rate is cached between database reads
     |   fx.refresh_cron  — when to fetch a new rate (default 06:15 and 14:15 every day)
     */
    'usd_to_lsl' => (float) env('CIF_USD_TO_LSL', 18.00),

    /*
     | LEHSFF Collaboration Platform — investor matchmaking (App\Support\CollaborationPlatform)
     | The enterprise profile's 8.2 Investor information is filled in from the matchmaking result, found by the
     | e-mail the enterprise registered with on both platforms. Same token on both platforms (CIF_API_TOKEN there).
     |   .env  COLLAB_API_URL=https://collaboration.example.org   COLLAB_API_TOKEN=...
     */
    'collaboration' => [
        'base_url'     => rtrim((string) env('COLLAB_API_URL', ''), '/'),
        'token'        => env('COLLAB_API_TOKEN'),
        'timeout'      => (int) env('COLLAB_API_TIMEOUT', 8),
        'sync_minutes' => (int) env('COLLAB_SYNC_MINUTES', 360),   // how often a profile is re-checked when opened

        // Public website of the Collaboration Platform — the landing page's "Find an investor" link.
        // Falls back to COLLAB_API_URL when the website and the API are on the same address.
        'public_url'    => rtrim((string) env('COLLAB_PUBLIC_URL', env('COLLAB_API_URL', '')), '/'),
        'register_path' => env('COLLAB_REGISTER_PATH', '/register'),
    ],

    'fx' => [
        'cache_minutes' => (int) env('CIF_FX_CACHE_MINUTES', 60),
        'refresh_cron'  => env('CIF_FX_REFRESH_CRON', '15 6,14 * * *'),
    ],

    /*
     | Co-investment rules (CAFI investment model)
     |   grant_tiers       — the CAFI grant is worked out on the investor's amount (US$) in tiers:
     |                         50% of the first US$ 300,000 + 33% of everything above US$ 300,000
     |                       each tier: up_to_usd (top of the tier; null = no top) => rate
     |   max_grant_usd     — the grant for one transaction is never more than this (reached at ≈ US$ 906,061)
     |   min_investor_usd  — the investor must be investing at least this much (US$) to apply
     |   cumulative_cap_usd — total CAFI grants one enterprise may receive over its lifetime (all transactions)
     */
    'co_investment' => [
        'grant_tiers'      => [
            ['up_to_usd' => (float) env('CIF_GRANT_TIER1_TOP_USD', 300000), 'rate' => (float) env('CIF_GRANT_TIER1_RATE', 0.50)],
            ['up_to_usd' => null,                                          'rate' => (float) env('CIF_GRANT_TIER2_RATE', 0.33)],
        ],
        'max_grant_usd'    => (float) env('CIF_MAX_GRANT_USD', 350000),
        'min_investor_usd' => (float) env('CIF_MIN_INVESTOR_USD', 50000),
        // Gate 4 · Grant readiness: total CAFI grants one enterprise may receive, and the share to be spent in Lesotho
        'cumulative_cap_usd' => (float) env('CIF_CUMULATIVE_GRANT_CAP_USD', 350000),
        'min_lesotho_pct'    => (float) env('CIF_MIN_LESOTHO_PCT', 70),
    ],

    /*
     | Investment Committee (Gate 3) — members are active users with the "committee" role.
     |   size — expected number of members (the Facility has 3 fixed members)
     */
    'committee' => [
        'size' => (int) env('CIF_COMMITTEE_SIZE', 3),
    ],

    /*
     | Sign-in
     |   otp_enabled      — master switch for two-step sign-in (6-digit code e-mailed after the password).
     |                      ON (default): each user's own setting in Account settings decides (on unless they turned it off).
     |                      OFF: nobody is asked for a code, whatever their own setting says.
     |   otp_skip_button  — TEMPORARY "Skip code for now" button on the code screen (set false before going live)
     |   .env:  CIF_OTP_ENABLED=true   CIF_OTP_SKIP_BUTTON=false   (then: php artisan config:clear)
     */
    'login' => [
        'otp_enabled'     => (bool) env('CIF_OTP_ENABLED', true),
        'otp_skip_button' => (bool) env('CIF_OTP_SKIP_BUTTON', false),
    ],

];