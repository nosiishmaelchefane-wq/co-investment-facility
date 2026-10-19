<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/* Business section: unique selling proposition, marketing approach and competition */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enterprise_profiles', function (Blueprint $table) {
            foreach (['usp', 'marketing_strategy', 'competition'] as $column) {
                if (! Schema::hasColumn('enterprise_profiles', $column)) {
                    $table->text($column)->nullable();
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('enterprise_profiles', function (Blueprint $table) {
            $table->dropColumn(['usp', 'marketing_strategy', 'competition']);
        });
    }
};