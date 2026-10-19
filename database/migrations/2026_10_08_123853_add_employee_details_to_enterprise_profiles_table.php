<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Enterprise profile · CAFI application form §5 — "What is the current number of your employees? (0-10 / Over 10)"
| and the on-page list of current employees (name, full/part time, position, age, gender).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enterprise_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('enterprise_profiles', 'employee_band')) {
                $table->string('employee_band', 10)->nullable()->after('employees');
            }
            if (! Schema::hasColumn('enterprise_profiles', 'employee_details')) {
                $table->json('employee_details')->nullable()->after('pt_female');
            }
        });

        // Answer the band from the current total where we already have it
        DB::table('enterprise_profiles')->whereNull('employee_band')->whereNotNull('employees')
            ->update(['employee_band' => DB::raw("CASE WHEN employees > 10 THEN 'over_10' ELSE '0_10' END")]);
    }

    public function down(): void
    {
        Schema::table('enterprise_profiles', fn (Blueprint $table) => $table->dropColumn(['employee_band', 'employee_details']));
    }
};