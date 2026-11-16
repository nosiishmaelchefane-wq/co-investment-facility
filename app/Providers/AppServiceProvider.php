<?php

namespace App\Providers;

use App\Models\Role;
use App\Models\User;
use App\Support\ExchangeRates;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /*
         | Super Administrator passes every permission check:
         | @can, $user->can(), Gate::allows() and the `permission:` route middleware.
         | Returning null (not false) lets everyone else fall through to their normal permissions.
         */
        Gate::before(function (User $user, string $ability) {
            return $user->hasRole(Role::SUPER_ADMIN) ? true : null;
        });

        /*
         | Live US$ → Maloti exchange rate. Everything in the platform reads config('cif.usd_to_lsl'),
         | so it is set here from the latest fetched rate (cached for an hour). The value from .env
         | (CIF_USD_TO_LSL) is kept as the fallback for when no rate has been fetched yet.
         */
        config(['cif.usd_to_lsl_fallback' => (float) config('cif.usd_to_lsl', 18.00)]);
        try {
            config(['cif.usd_to_lsl' => ExchangeRates::usdToLsl()]);
        } catch (\Throwable $e) {
            report($e);   // never block the app over the exchange rate — the configured rate stays in use
        }
    }
}