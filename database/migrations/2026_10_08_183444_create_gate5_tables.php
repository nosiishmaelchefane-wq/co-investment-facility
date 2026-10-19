<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Gate 5 · Conditions Precedent, Grant Agreement & Onboarding (CI Facility Operations Manual §3.6)
|   condition_reviews       one per transaction — checks, budget, onboarding, outcome (with history)
|   cp_conditions           §3.6.1 conditions precedent (evidence + verification)
|   cp_agreements           §3.6.3 Grant Agreement, Investment Agreement… (execution status + signed copy)
|   capital_deployments     §3.6.2 investor capital actually deployed (evidence + verification)
|   disbursement_tranches   §3.6.4 tranche plan — each released only when enough investor capital is verified
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('condition_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expression_of_interest_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('authorisation_id')->nullable()->constrained()->nullOnDelete();

            // What was authorised (Maloti)
            $table->decimal('grant_amount', 15, 2)->nullable();
            $table->decimal('private_amount', 15, 2)->nullable();
            $table->string('instrument', 30)->nullable();

            $table->json('verification_checks')->nullable();   // §3.6.2 {check: bool}
            $table->json('grant_clauses')->nullable();         // §3.6.3 {clause: bool}
            $table->json('readiness_checks')->nullable();      // §3.6.4 {check: bool}
            $table->json('budget')->nullable();                // [{category, private, grant}]
            $table->json('onboarding')->nullable();            // §3.6.5 {contact, frequency, first_report_on, items{}}
            $table->timestamp('summary_sent_at')->nullable();
            $table->foreignId('summary_sent_by')->nullable()->constrained('users')->nullOnDelete();

            // §3.6.6 current outcome (+ every outcome recorded, newest last)
            $table->string('outcome', 30)->nullable();         // cleared | pending_conditions | pending_investor | not_satisfied
            $table->text('outcome_comments')->nullable();
            $table->date('outcome_on')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('cleared_at')->nullable();
            $table->json('history')->nullable();

            $table->timestamps();
        });

        Schema::create('cp_conditions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('condition_review_id')->constrained()->cascadeOnDelete();
            $table->string('area', 30);                          // legal | registration | tax | esgc | investor | transaction | financing | grant | reporting
            $table->string('finding', 250);
            $table->string('source', 5)->default('g5');          // g2 | g3 | g4 | g5
            $table->string('status', 20)->default('outstanding'); // outstanding | met | waived
            $table->string('note', 500)->nullable();
            $table->string('evidence_path')->nullable();
            $table->string('evidence_name')->nullable();
            $table->unsignedBigInteger('evidence_size')->nullable();
            $table->foreignId('evidence_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('evidence_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('cp_agreements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('condition_review_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20);                          // grant | investment | other
            $table->string('title', 150);
            $table->string('parties')->nullable();
            $table->boolean('required')->default(true);
            $table->string('status', 20)->default('drafting');   // drafting | sent | executed
            $table->date('signed_on')->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('capital_deployments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('condition_review_id')->constrained()->cascadeOnDelete();
            $table->string('investor');
            $table->decimal('amount', 15, 2);                    // Maloti
            $table->date('deployed_on');
            $table->string('evidence_path')->nullable();
            $table->string('evidence_name')->nullable();
            $table->unsignedBigInteger('evidence_size')->nullable();
            $table->string('status', 20)->default('submitted');  // submitted | verified | rejected
            $table->string('note', 500)->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });

        Schema::create('disbursement_tranches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('condition_review_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->string('label', 20);                         // T1, T2…
            $table->decimal('amount', 15, 2)->nullable();        // Maloti
            $table->string('trigger', 250)->nullable();          // release milestone
            $table->decimal('capital_required', 15, 2)->nullable(); // verified investor capital needed (cumulative) before release
            $table->date('released_on')->nullable();
            $table->string('reference', 100)->nullable();        // payment reference
            $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disbursement_tranches');
        Schema::dropIfExists('capital_deployments');
        Schema::dropIfExists('cp_agreements');
        Schema::dropIfExists('cp_conditions');
        Schema::dropIfExists('condition_reviews');
    }
};