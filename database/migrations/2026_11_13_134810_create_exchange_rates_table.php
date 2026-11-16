<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Live US$ → Maloti exchange rates (App\Support\ExchangeRates).
| One row per successful fetch, so there is a history of the rate the system used and when.
| The latest row is the current rate; config('cif.usd_to_lsl') is only the fallback if no rate has been fetched yet.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->string('base', 3)->default('USD');
            $table->string('quote', 3)->default('LSL');
            $table->decimal('rate', 12, 6);                       // 1 base = rate quote, e.g. 1 USD = 18.234500 LSL
            $table->string('source', 60);                         // open.er-api.com · frankfurter.app (ZAR) · manual
            $table->timestamp('rate_date')->nullable();           // when the provider last updated the rate
            $table->timestamp('fetched_at');
            $table->timestamps();

            $table->index(['base', 'quote', 'fetched_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
    }
};