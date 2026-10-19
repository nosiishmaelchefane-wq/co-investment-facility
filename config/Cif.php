<?php

/*
|--------------------------------------------------------------------------
| CIF settings
|--------------------------------------------------------------------------
*/

return [

    /*
     | Exchange rate used to show US$ amounts in Maloti (LSL).
     | 1 USD = X LSL. LSL is pegged 1:1 to the South African rand,
     | so use the current USD/ZAR rate. Update it in .env:  CIF_USD_TO_LSL=18.00
     */
    'usd_to_lsl' => (float) env('CIF_USD_TO_LSL', 18.00),

    /*
     | Co-investment rules
     |   grant_ratio       — CAFI matches this share of what the investor puts in (0.5 = 50%)
     |   min_investor_usd  — the investor must be investing at least this much (US$) to apply
     */
    'co_investment' => [
        'grant_ratio'      => (float) env('CIF_GRANT_RATIO', 0.5),
        'min_investor_usd' => (float) env('CIF_MIN_INVESTOR_USD', 50000),
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
     |   otp_enabled      — two-step sign-in (6-digit code e-mailed after the password) for the whole platform.
     |                      When on, each user can still switch it off for themselves in Account settings.
     |   otp_skip_button  — TEMPORARY "Skip code for now" button on the code screen (set false before going live)
     |   .env:  CIF_OTP_ENABLED=true   CIF_OTP_SKIP_BUTTON=false
     */
    'login' => [
        'otp_enabled'     => (bool) env('CIF_OTP_ENABLED', false),
        'otp_skip_button' => (bool) env('CIF_OTP_SKIP_BUTTON', true),
    ],

];