<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Gate 3 · Investment Committee Review & Recommendation (§3.4)
|   committee_reviews     one row per Committee round (a deferred transaction that is resubmitted gets a new round)
|   committee_scorecards  Appendix F — one per Committee member per round, incl. conflict-of-interest declaration
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('committee_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expression_of_interest_id')->constrained()->cascadeOnDelete();
            $table->foreignId('due_diligence_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('round')->default(1);

            // Meeting
            $table->string('meeting_ref', 50)->nullable();
            $table->date('meeting_date')->nullable();
            $table->string('venue')->nullable();
            $table->date('pack_circulated_on')->nullable();
            $table->foreignId('secretary_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resubmission_note')->nullable();         // PMU response when a deferred case comes back

            // Committee record (§3.4.2 / §3.4.3)
            $table->json('criteria')->nullable();                  // {area: {view, note}}
            $table->json('deliberation')->nullable();              // {step: {done, note}}
            $table->json('questions')->nullable();                 // [{asked_by, question, answer}]
            $table->json('conditions')->nullable();                // [{source, ref_id, finding, treatment, owner, decision}]

            // Outcome (§3.4.4)
            $table->string('outcome', 20)->nullable();             // approve | conditional | defer | reject
            $table->decimal('private_amount', 15, 2)->nullable();  // Maloti
            $table->decimal('grant_amount', 15, 2)->nullable();    // Maloti
            $table->text('rationale')->nullable();
            $table->text('actions')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->unique(['expression_of_interest_id', 'round']);
        });

        Schema::create('committee_scorecards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('committee_review_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Conflict of interest
            $table->string('coi', 20)->nullable();                 // none | declared | recused
            $table->text('coi_details')->nullable();

            // Appendix F
            $table->json('answers')->nullable();                   // {criterion: met | not_met}
            $table->json('innovation')->nullable();                // ["product", "process", ...]
            $table->json('comments')->nullable();                  // {group: text}
            $table->text('use_of_funds_notes')->nullable();
            $table->text('issues')->nullable();
            $table->string('recommendation', 10)->nullable();      // approve | reject
            $table->text('takeaways')->nullable();

            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
            $table->unique(['committee_review_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('committee_scorecards');
        Schema::dropIfExists('committee_reviews');
    }
};