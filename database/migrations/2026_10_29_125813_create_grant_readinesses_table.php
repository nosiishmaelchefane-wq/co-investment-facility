<?php

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Gate 4 · Grant Readiness Review (CI Facility Operations Manual §3.5) — one row per round.
|   Step 1 Grants Management Representative: checks, bank account, outstanding issues, recommendation,
|          Grant Readiness Memo (generated PDF) + Draft Grant Agreement (uploaded)
|   Step 2 Hub Manager: Approved for submission / Returned
|   Step 3 PMU Director of Implementation: Approved to proceed (→ Gate 5 Final authorisation) / Returned
|   A return closes the round; a new round starts at Grants Management with everything carried over.
|
| Also runs the roles seeder: adds the Grants Management Representative, Hub Manager and
| PMU Director of Implementation roles and the readiness.* permissions.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grant_readinesses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expression_of_interest_id')->constrained()->cascadeOnDelete();
            $table->foreignId('committee_review_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('round')->default(1);
            $table->text('resubmission_note')->nullable();          // why the previous round was returned

            // Amounts from the Committee recommendation (Maloti)
            $table->decimal('private_amount', 15, 2)->nullable();
            $table->decimal('grant_amount', 15, 2)->nullable();

            // Step 1 · Grants Management Representative
            $table->json('checks')->nullable();                     // {check: {status: confirmed|exception|action, note}}
            $table->decimal('lesotho_pct', 5, 2)->nullable();       // share of the grant spent in Lesotho
            $table->json('bank')->nullable();                       // {bank, branch, account_name, account_number, account_type, verified_against}
            $table->string('bank_evidence_path')->nullable();
            $table->string('bank_evidence_name')->nullable();
            $table->string('agreement_path')->nullable();           // Draft Grant Agreement
            $table->string('agreement_name')->nullable();
            $table->unsignedBigInteger('agreement_size')->nullable();
            $table->timestamp('agreement_uploaded_at')->nullable();
            $table->json('issues')->nullable();                     // [{issue, party, action, status}]
            $table->string('recommendation', 20)->nullable();       // ready | not_ready
            $table->text('justification')->nullable();
            $table->foreignId('gm_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('gm_on')->nullable();
            $table->timestamp('gm_submitted_at')->nullable();

            // Step 2 · Hub Manager
            $table->string('hub_decision', 20)->nullable();         // approved | returned
            $table->text('hub_comments')->nullable();
            $table->date('hub_on')->nullable();
            $table->foreignId('hub_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('hub_decided_at')->nullable();

            // Step 3 · PMU Director of Implementation
            $table->string('director_decision', 20)->nullable();    // approved | returned
            $table->text('director_comments')->nullable();
            $table->date('director_on')->nullable();
            $table->foreignId('director_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('director_decided_at')->nullable();

            // Round result
            $table->string('outcome', 20)->nullable();              // approved | returned
            $table->timestamp('decided_at')->nullable();

            $table->timestamps();
            $table->unique(['expression_of_interest_id', 'round']);
        });

        (new RolesAndPermissionsSeeder)->run();
    }

    public function down(): void
    {
        Schema::dropIfExists('grant_readinesses');
    }
};