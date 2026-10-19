<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| The users table has a misspelled, NOT NULL column "delleted_at", so every insert into users fails
| ("Field 'delleted_at' doesn't have a default value"). Replace it with Laravel's standard nullable
| "deleted_at" (soft deletes).
*/
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'delleted_at')) {
            return;
        }

        if (Schema::hasColumn('users', 'deleted_at')) {
            // The correct column already exists — just remove the misspelled one
            Schema::table('users', fn (Blueprint $table) => $table->dropColumn('delleted_at'));
            return;
        }

        Schema::table('users', fn (Blueprint $table) => $table->renameColumn('delleted_at', 'deleted_at'));
        Schema::table('users', fn (Blueprint $table) => $table->timestamp('deleted_at')->nullable()->default(null)->change());
    }

    public function down(): void
    {
        // Not reversible on purpose: the misspelled NOT NULL column breaks user registration
    }
};