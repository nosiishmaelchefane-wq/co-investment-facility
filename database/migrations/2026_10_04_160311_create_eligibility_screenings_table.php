<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Gate 1 · Eligibility screening (CAFI Co-Investment Eligibility Screening Checklist).
| One row per screening round. A new round starts when a returned EOI is resubmitted.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eligibility_screenings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expression_of_interest_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('round')->default(1);
            $table->foreignId('screened_by')->nullable()->constrained('users')->nullOnDelete();

            $table->json('answers')->nullable();           // {criterion: {answer: yes|no|clarify, note: "..."}}
            $table->string('decision', 20)->nullable();    // eligible | clarification | not_eligible
            $table->text('comments')->nullable();
            $table->date('screened_on')->nullable();

            $table->timestamp('decided_at')->nullable();   // set when the Gate 1 decision is recorded (then read-only)
            $table->timestamps();

            $table->unique(['expression_of_interest_id', 'round']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eligibility_screenings');
    }
};