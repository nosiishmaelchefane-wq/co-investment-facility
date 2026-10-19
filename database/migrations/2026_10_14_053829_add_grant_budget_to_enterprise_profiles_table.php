<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/* Grant utilisation budget, filled in by the enterprise on its profile: [{category, private, grant}] in Maloti. Gate 5 starts from it. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enterprise_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('enterprise_profiles', 'grant_budget')) {
                $table->json('grant_budget')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('enterprise_profiles', function (Blueprint $table) {
            $table->dropColumn('grant_budget');
        });
    }
};