<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Investments made into an enterprise — one row per investment (an investor can invest many times).
| Declared by the enterprise on its profile, or recorded by a reviewer; reviewers verify or reject each one.
| Amounts in Maloti (LSL).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enterprise_investments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enterprise_profile_id')->constrained()->cascadeOnDelete();

            $table->string('investor_name');
            $table->string('investor_type', 60)->nullable();
            $table->string('investor_country', 80)->nullable();
            $table->decimal('amount', 15, 2);
            $table->date('invested_on');
            $table->string('instrument', 30)->nullable();      // EnterpriseInvestment::INSTRUMENTS
            $table->string('round_label', 60)->nullable();     // e.g. Pre-seed, Seed, Series A
            $table->string('notes', 1000)->nullable();

            $table->string('proof_path')->nullable();
            $table->string('proof_name')->nullable();
            $table->unsignedBigInteger('proof_size')->nullable();

            $table->string('source', 20)->default('enterprise');   // enterprise | reviewer
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('status', 20)->default('declared');     // declared | verified | rejected
            $table->string('review_note', 1000)->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();
            $table->index(['enterprise_profile_id', 'invested_on']);
            $table->index('investor_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enterprise_investments');
    }
};