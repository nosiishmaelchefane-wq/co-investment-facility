<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Enterprise profile · investor information received from the LEHSFF Collaboration Platform matchmaking.
|   matchmaking            the last matchmaking result (match, investor, which fields were filled)
|   matchmaking_synced_at  when it was last received / checked
| Adds the columns only if they are missing (an earlier migration with this purpose ran without creating them).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enterprise_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('enterprise_profiles', 'matchmaking')) {
                $table->json('matchmaking')->nullable();
            }
            if (! Schema::hasColumn('enterprise_profiles', 'matchmaking_synced_at')) {
                $table->timestamp('matchmaking_synced_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('enterprise_profiles', function (Blueprint $table) {
            foreach (['matchmaking', 'matchmaking_synced_at'] as $column) {
                if (Schema::hasColumn('enterprise_profiles', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};