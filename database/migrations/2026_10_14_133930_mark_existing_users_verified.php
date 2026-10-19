<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
| E-mail verification is now required before sign-in.
| Accounts that already exist (and active staff created by the administrator) are treated as verified,
| so nobody who could sign in yesterday is locked out today.
*/
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->whereNull('email_verified_at')->update(['email_verified_at' => now()]);
    }

    public function down(): void
    {
        // Nothing to undo
    }
};