<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Adds CIF display fields to Spatie's tables.
| Must run AFTER Spatie's "create_permission_tables" migration
| (the file name date makes sure it sorts after it).
*/
return new class extends Migration
{
    public function up(): void
    {
        $tables = config('permission.table_names');

        Schema::table($tables['roles'], function (Blueprint $table) {
            $table->string('label')->nullable()->after('name');
            $table->string('description', 500)->nullable()->after('label');
            $table->string('type', 20)->default('internal')->after('description');   // internal | external
            $table->string('icon', 50)->default('bi-person-badge')->after('type');
            $table->boolean('is_system')->default(false)->after('icon');
        });

        Schema::table($tables['permissions'], function (Blueprint $table) {
            $table->string('label')->nullable()->after('name');
            $table->string('module', 50)->nullable()->index()->after('label');
            $table->unsignedSmallInteger('sort')->default(0)->after('module');
        });
    }

    public function down(): void
    {
        $tables = config('permission.table_names');

        Schema::table($tables['roles'], function (Blueprint $table) {
            $table->dropColumn(['label', 'description', 'type', 'icon', 'is_system']);
        });

        Schema::table($tables['permissions'], function (Blueprint $table) {
            $table->dropIndex([ 'module' ]);
            $table->dropColumn(['label', 'module', 'sort']);
        });
    }
};