<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Account settings · each user can switch two-step sign-in (e-mailed code) off for themselves.
| On by default — it only takes effect when CIF_OTP_ENABLED=true.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('two_factor_enabled')->default(true)->after('password');
            $table->timestamp('two_factor_changed_at')->nullable()->after('two_factor_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['two_factor_enabled', 'two_factor_changed_at']);
        });
    }
};