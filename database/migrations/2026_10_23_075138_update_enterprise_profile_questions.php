<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Enterprise profile — updated questions
|   products_services  Products & services (customers is now its own question)
|   year_registered    Year of registration (next to year of establishment)
|   sector_other       Sector when "Other" is chosen
|   business_models    Business model — one or more (was a single business_model)
|   shareholder_list   Shareholders table: [{name, id_number, nationality, gender, age, percent}]
|   has_investor       "Do you have an investor?"
| Existing answers are carried over where possible.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enterprise_profiles', function (Blueprint $table) {
            $add = fn (string $col, callable $define) => Schema::hasColumn('enterprise_profiles', $col) ? null : $define($table);

            $add('products_services', fn ($t) => $t->text('products_services')->nullable());
            $add('year_registered',   fn ($t) => $t->unsignedSmallInteger('year_registered')->nullable());
            $add('sector_other',      fn ($t) => $t->string('sector_other', 120)->nullable());
            $add('business_models',   fn ($t) => $t->json('business_models')->nullable());
            $add('shareholder_list',  fn ($t) => $t->json('shareholder_list')->nullable());
            $add('has_investor',      fn ($t) => $t->boolean('has_investor')->nullable());
        });

        // Carry over: single business model → list; investors listed → "has investor"
        foreach (DB::table('enterprise_profiles')->get(['id', 'business_model', 'round_investors', 'investor_contribution']) as $p) {
            $investors = json_decode($p->round_investors ?? '[]', true) ?: [];

            DB::table('enterprise_profiles')->where('id', $p->id)->update([
                'business_models' => filled($p->business_model) ? json_encode([$p->business_model]) : null,
                'has_investor'    => ($investors !== [] || (float) $p->investor_contribution > 0) ? true : null,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('enterprise_profiles', function (Blueprint $table) {
            $table->dropColumn(['products_services', 'year_registered', 'sector_other', 'business_models', 'shareholder_list', 'has_investor']);
        });
    }
};