<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Gate 4 · Final Authorisation (CI Facility Operations Manual §3.5)
|   One row per authorisation round. Sequential: CAFI Managing Director → MTIBD Principal Secretary.
|   A round returned for clarification is closed; the resubmission starts a new round.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('authorisations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expression_of_interest_id')->constrained()->cascadeOnDelete();
            $table->foreignId('committee_review_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('round')->default(1);
            $table->text('resubmission_note')->nullable();

            // What is being authorised (from the Committee recommendation) — Maloti
            $table->decimal('private_amount', 15, 2)->nullable();
            $table->decimal('grant_amount', 15, 2)->nullable();
            $table->string('instrument', 30)->nullable();

            // §3.5.4 conditions, classified: [{source, finding, type, status}]
            $table->json('conditions')->nullable();

            // §3.5.2 Managing Director review
            $table->json('md_checks')->nullable();                    
            $table->string('md_decision', 20)->nullable();            
            $table->text('md_comments')->nullable();
            $table->date('md_on')->nullable();
            $table->foreignId('md_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('md_decided_at')->nullable();

            // §3.5.3 Principal Secretary final authorisation
            $table->string('ps_decision', 20)->nullable();            
            $table->text('ps_comments')->nullable();
            $table->date('ps_on')->nullable();
            $table->foreignId('ps_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('ps_decided_at')->nullable();

            // §3.5.5 outcome of the round
            $table->string('outcome', 20)->nullable();              
            $table->timestamp('decided_at')->nullable();

            $table->timestamps();
            $table->unique(['expression_of_interest_id', 'round']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('authorisations');
    }
};