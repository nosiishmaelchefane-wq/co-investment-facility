<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| G4 · Grant readiness — review checklists for the Hub Manager (step 2) and the PMU Director of Implementation (step 3).
|   Each reviewer confirms they reviewed the Investment Appraisal Memorandum, the Grant Readiness Memo and the
|   Draft Grant Agreement, plus the review criteria of their step (GrantReadiness::HUB_CHECKS / DIRECTOR_CHECKS).
|   {key: {done: bool, note: string, at: datetime}}
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grant_readinesses', function (Blueprint $table) {
            $table->json('hub_checks')->nullable()->after('hub_comments');
            $table->json('director_checks')->nullable()->after('director_comments');
        });
    }

    public function down(): void
    {
        Schema::table('grant_readinesses', function (Blueprint $table) {
            $table->dropColumn(['hub_checks', 'director_checks']);
        });
    }
};