<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Gate 2 · Investment Appraisal Memorandum written on the platform instead of uploaded.
| memo = { genuine|evidenced|structure|use|risks: { text, edited } }
| Answers are drafted automatically from the assessment and the enterprise profile; "edited" answers are
| no longer redrafted. The old iam_* upload columns are kept so earlier uploaded memoranda stay downloadable.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('due_diligences', function (Blueprint $table) {
            if (! Schema::hasColumn('due_diligences', 'memo')) {
                $table->json('memo')->nullable()->after('key_risks');
            }
        });
    }

    public function down(): void
    {
        Schema::table('due_diligences', function (Blueprint $table) {
            $table->dropColumn('memo');
        });
    }
};