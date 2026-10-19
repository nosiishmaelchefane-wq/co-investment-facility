<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Account fields used by User management:
|   status         active | invited | inactive
|   organisation   CAFI / LEHSFF PMU / MTIBD / enterprise name
|   invited_at     when the last invitation was sent
|   last_login_at  updated on every successful login
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('status', 20)->default('active')->index()->after('password');
            $table->string('organisation')->nullable()->after('enterprise_name');
            $table->timestamp('invited_at')->nullable()->after('status');
            $table->timestamp('last_login_at')->nullable()->after('invited_at');
        });

        // Existing enterprise users: copy enterprise_name into organisation
        DB::table('users')->whereNull('organisation')->whereNotNull('enterprise_name')
            ->update(['organisation' => DB::raw('enterprise_name')]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn(['status', 'organisation', 'invited_at', 'last_login_at']);
        });
    }
};