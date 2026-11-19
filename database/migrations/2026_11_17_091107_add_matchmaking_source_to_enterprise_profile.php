<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Enterprise profile · 8.3.8 "When is the investment expected to close or be deployed?" becomes a real date
| (it was free text such as "March 2027" or "within 3 months").
|
| Existing answers are converted where they can be read as a date ("March 2027" → 2027-03-01,
| "15/03/2027" → 2027-03-15). Answers that cannot be read as a date ("within 3 months") are cleared,
| so the applicant is asked for a date the next time the profile is saved.
*/
return new class extends Migration
{
    public function up(): void
    {
        DB::table('enterprise_profiles')->whereNotNull('expected_close')->orderBy('id')
            ->each(function ($row) {
                $date = null;
                $text = trim((string) $row->expected_close);
                if ($text !== '') {
                    try {
                        $date = Carbon::parse(str_replace('/', '-', $text))->toDateString();
                    } catch (\Throwable) {
                        $date = null;
                    }
                }
                DB::table('enterprise_profiles')->where('id', $row->id)->update(['expected_close' => $date]);
            });

        Schema::table('enterprise_profiles', function (Blueprint $table) {
            $table->date('expected_close')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('enterprise_profiles', function (Blueprint $table) {
            $table->string('expected_close', 120)->nullable()->change();
        });
    }
};