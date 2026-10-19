<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expression_of_interests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('reference')->nullable()->unique();          
            $table->string('status', 30)->default('draft')->index();    

            // Q1 — Business (nullable: drafts may be incomplete)
            $table->string('business_name')->nullable();
            $table->string('representative_name')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('district', 50)->nullable()->index();
            $table->string('website')->nullable();

            // Q2 — Legal establishment
            $table->string('legal_status', 40)->nullable();
            $table->unsignedSmallInteger('year_commenced')->nullable();

            // Q3–Q4 — Operations
            $table->text('business_description')->nullable();
            $table->string('annual_turnover', 20)->nullable();
            $table->unsignedInteger('full_time_employees')->nullable();

            // Q5–Q6 — Investment
            $table->decimal('investment_required_usd', 15, 2)->nullable();
            $table->json('investment_types')->nullable();
            $table->json('use_of_funds')->nullable();
            $table->string('use_of_funds_other')->nullable();
            $table->text('use_of_funds_description')->nullable();

            // Q7–Q8 — Investor
            $table->string('investor_status', 30)->nullable();
            $table->string('investor_status_other')->nullable();
            $table->decimal('investor_amount_usd', 15, 2)->nullable();
            $table->string('investor_name')->nullable();
            $table->json('investor_evidence')->nullable();

            // Q9–Q10 — Support + declaration
            $table->string('support_need', 30)->nullable();
            $table->boolean('declaration_agree')->default(false);
            $table->string('declaration_name')->nullable();
            $table->date('declaration_date')->nullable();

            // Internal screening (never shown to the applicant)
            $table->string('screening_outcome', 40)->nullable()->index();
            $table->json('screening_reasons')->nullable();

            // Workflow timestamps
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('withdrawn_at')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expression_of_interests');
    }
};