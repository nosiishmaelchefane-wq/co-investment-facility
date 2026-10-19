<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Gate 2 · Due Diligence, Investment Appraisal & ESGC Assessment (§3.3)
|   due_diligences             one row per round (a new round when an on-hold assessment is resumed)
|   due_diligence_conditions   §3.3.3 I — findings and how they're treated (carried to contracting / G5)
|   esap_actions               Environmental & Social Action Plan items (carried to monitoring)
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('due_diligences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expression_of_interest_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('round')->default(1);
            $table->foreignId('assessor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('started_on')->nullable();
            $table->date('target_on')->nullable();
            $table->string('depth')->nullable();                     // proportionate depth of assessment

            // 1 · Enterprise DD          {area: {status, evidence, finding, gap}}
            $table->json('enterprise')->nullable();
            $table->text('enterprise_consistency')->nullable();

            // 2 · Investor DD            [{name, type, country, role, route, evidence: {area: text}}] + {area: {status, note}}
            $table->json('investors')->nullable();
            $table->json('investor_checks')->nullable();

            // 3 · Transaction DD (A–H)   {stage, stage_date, stage_evidence, commitment, capital, structure, fit,
            //                             use_of_funds, uof_checks, recon_docs, readiness}
            $table->json('transaction')->nullable();

            // 4 · Financial & commercial
            $table->json('financials')->nullable();                  // [{year, projected, revenue, gross_margin, net_profit, debt_equity, jobs}]
            $table->text('financial_assessment')->nullable();
            $table->text('commercial_assessment')->nullable();

            // 5 · ESGC                   {dimension: {rating, status, note}}
            $table->json('esgc')->nullable();
            $table->string('esgc_category', 20)->nullable();        // low | medium | high

            // 6 · Risk register          [{category, issue, rating, mitigation, treatment}]
            $table->json('risks')->nullable();
            $table->string('risk_conclusion', 30)->nullable();

            // 7 · Investment appraisal
            $table->json('tss')->nullable();                         // manual Transaction Summary Sheet fields
            $table->string('iam_path')->nullable();
            $table->string('iam_name')->nullable();
            $table->unsignedBigInteger('iam_size')->nullable();
            $table->foreignId('iam_uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('iam_uploaded_at')->nullable();

            // Gate 2 outcome (§3.3.4)
            $table->string('outcome', 30)->nullable();               // proceed | clarification | remediation | do_not_progress
            $table->text('conclusion')->nullable();
            $table->date('assessed_on')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->unique(['expression_of_interest_id', 'round']);
        });

        Schema::create('due_diligence_conditions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('due_diligence_id')->constrained()->cascadeOnDelete();
            $table->string('finding');
            $table->string('treatment', 30);                         // key from DueDiligenceCondition::TREATMENTS
            $table->string('owner', 20)->default('enterprise');      // enterprise | investor | pmu
            $table->string('status', 20)->default('open');           // open | resolved
            $table->timestamp('resolved_at')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('esap_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('due_diligence_id')->constrained()->cascadeOnDelete();
            $table->string('action');
            $table->string('dimension', 20)->nullable();             // environmental | social | gender | governance | climate
            $table->string('treatment', 30)->default('monitoring');  // condition_precedent | before_contracting | monitoring
            $table->string('timing')->nullable();                    // e.g. "Before first disbursement", "Within 6 months"
            $table->string('status', 20)->default('pending');        // pending | done
            $table->timestamp('completed_at')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('esap_actions');
        Schema::dropIfExists('due_diligence_conditions');
        Schema::dropIfExists('due_diligences');
    }
};