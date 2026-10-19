<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Staff accounts (PMU, Committee, MD, PS, Finance, Admin) have no enterprise
| and may have no phone, so these two columns must allow NULL.
| The unique index on phone stays (MySQL allows many NULLs in a unique column).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('enterprise_name')->nullable()->change();
            $table->string('phone')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('enterprise_name')->nullable(false)->change();
            $table->string('phone')->nullable(false)->change();
        });
    }
};