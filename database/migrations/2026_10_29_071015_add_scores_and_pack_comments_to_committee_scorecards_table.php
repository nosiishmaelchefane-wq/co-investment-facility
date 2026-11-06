<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Gate 3 · Committee scorecards
|   scores         {criterion: 1–5}   — replaces Met / Not met (answers is still filled: 3 or more = met)
|   pack_comments  {pack item: text}  — each member's comment on every item of the Committee information pack
| Existing scorecards: Met → 4, Not met → 2, so the old cards still total up.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('committee_scorecards', function (Blueprint $table) {
            if (! Schema::hasColumn('committee_scorecards', 'scores')) {
                $table->json('scores')->nullable()->after('answers');
            }
            if (! Schema::hasColumn('committee_scorecards', 'pack_comments')) {
                $table->json('pack_comments')->nullable()->after('comments');
            }
        });

        foreach (DB::table('committee_scorecards')->whereNotNull('answers')->whereNull('scores')->get(['id', 'answers']) as $row) {
            $scores = collect(json_decode($row->answers, true) ?: [])
                ->map(fn ($a) => ['met' => 4, 'not_met' => 2][$a] ?? null)->filter()->all();
            DB::table('committee_scorecards')->where('id', $row->id)->update(['scores' => $scores ? json_encode($scores) : null]);
        }
    }

    public function down(): void
    {
        Schema::table('committee_scorecards', function (Blueprint $table) {
            $table->dropColumn(['scores', 'pack_comments']);
        });
    }
};