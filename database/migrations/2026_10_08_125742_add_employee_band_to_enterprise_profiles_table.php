<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Enterprise profile · CAFI application form §5 — "What is the current number of your employees? (0-10 / Over 10)".
| (The detailed employee list is an attachment — EnterpriseDocument type 'employee_list'.)
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enterprise_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('enterprise_profiles', 'employee_band')) {
                $table->string('employee_band', 10)->nullable()->after('employees');
            }
        });

        // Answer the band from the current total where we already have it
        DB::table('enterprise_profiles')->whereNull('employee_band')->whereNotNull('employees')
            ->update(['employee_band' => DB::raw("CASE WHEN employees > 10 THEN 'over_10' ELSE '0_10' END")]);
    }

    public function down(): void
    {
        Schema::table('enterprise_profiles', function (Blueprint $table) {
            $table->dropColumn(array_values(array_filter(['employee_band', 'employee_details'], fn ($c) => Schema::hasColumn('enterprise_profiles', $c))));
        });
    }
};