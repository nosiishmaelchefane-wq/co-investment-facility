<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Gate 4 · Grant readiness — bank details come from the applicant at this step, not from the enterprise profile.
|   bank_requested_at / _by   Grants Management e-mailed the applicant a link to the Grant readiness tab
|   bank_submitted_at         the applicant saved their bank details and bank confirmation letter
| The details themselves are still stored on enterprise_profiles (bank_* columns) and the letter as the
| enterprise document "bank_confirmation" — they are just no longer entered on the profile screen.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grant_readinesses', function (Blueprint $table) {
            $table->timestamp('bank_requested_at')->nullable()->after('bank_evidence_name');
            $table->foreignId('bank_requested_by')->nullable()->after('bank_requested_at')->constrained('users')->nullOnDelete();
            $table->timestamp('bank_submitted_at')->nullable()->after('bank_requested_by');
        });
    }

    public function down(): void
    {
        Schema::table('grant_readinesses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bank_requested_by');
            $table->dropColumn(['bank_requested_at', 'bank_submitted_at']);
        });
    }
};