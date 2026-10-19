<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Gate 1 · §3.2.1 Administrative completeness review — a distinct checkpoint before eligibility screening.
|   completeness            {check: {answer: yes|no, note: "..."}}  — officer's confirmation of the 7 checks
|   completeness_confirmed_* set when the officer confirms the application is complete → screening may start
|   decision gains 'incomplete' (returned at the completeness checkpoint)
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eligibility_screenings', function (Blueprint $table) {
            if (! Schema::hasColumn('eligibility_screenings', 'completeness')) {
                $table->json('completeness')->nullable()->after('screened_by');
            }
            if (! Schema::hasColumn('eligibility_screenings', 'completeness_confirmed_at')) {
                $table->timestamp('completeness_confirmed_at')->nullable()->after('completeness');
            }
            if (! Schema::hasColumn('eligibility_screenings', 'completeness_confirmed_by')) {
                $table->foreignId('completeness_confirmed_by')->nullable()->after('completeness_confirmed_at')
                      ->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('eligibility_screenings', function (Blueprint $table) {
            if (Schema::hasColumn('eligibility_screenings', 'completeness_confirmed_by')) {
                $table->dropConstrainedForeignId('completeness_confirmed_by');
            }
            $table->dropColumn(array_filter(
                ['completeness', 'completeness_confirmed_at'],
                fn ($c) => Schema::hasColumn('eligibility_screenings', $c)
            ));
        });
    }
};