<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Enterprise profile · Business section — captured by the enterprise, shown and verified at Gate 4 (Grant Readiness):
|   grant_outside_lesotho        how much of the CAFI grant (M) will be spent outside Lesotho (0 = all in Lesotho)
|   grant_outside_justification  required when less than cif.co_investment.min_lesotho_pct (70%) is spent in Lesotho
|   bank_*                       the account the grant will be paid into (+ document "bank_confirmation")
| Grants Management no longer types these in at Gate 4 — it checks them against the evidence and confirms them.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enterprise_profiles', function (Blueprint $table) {
            $table->decimal('grant_outside_lesotho', 15, 2)->nullable()->after('grant_budget');
            $table->text('grant_outside_justification')->nullable()->after('grant_outside_lesotho');
            $table->string('bank_name', 120)->nullable()->after('grant_outside_justification');
            $table->string('bank_branch', 120)->nullable()->after('bank_name');
            $table->string('bank_account_name', 200)->nullable()->after('bank_branch');
            $table->string('bank_account_number', 40)->nullable()->after('bank_account_name');
            $table->string('bank_account_type', 20)->nullable()->after('bank_account_number');
        });
    }

    public function down(): void
    {
        Schema::table('enterprise_profiles', function (Blueprint $table) {
            $table->dropColumn(['grant_outside_lesotho', 'grant_outside_justification',
                'bank_name', 'bank_branch', 'bank_account_name', 'bank_account_number', 'bank_account_type']);
        });
    }
};