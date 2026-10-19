<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| G5 · Finance must attach proof of payment (bank confirmation / remittance advice) when releasing a tranche.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('disbursement_tranches', function (Blueprint $table) {
            $table->string('proof_path')->nullable()->after('reference');
            $table->string('proof_name')->nullable()->after('proof_path');
            $table->unsignedBigInteger('proof_size')->nullable()->after('proof_name');
        });
    }

    public function down(): void
    {
        Schema::table('disbursement_tranches', function (Blueprint $table) {
            $table->dropColumn(['proof_path', 'proof_name', 'proof_size']);
        });
    }
};