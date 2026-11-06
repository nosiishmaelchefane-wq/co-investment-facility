<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| ESG checklist (CAFI Co-Investment application form)
|   es_checklist       — answers: yes | no | dont_know | na  (existing column, new 13 questions)
|   es_notes           — follow-up explanations per question {key: text}
|   es_records_system  — Q10 "Yes": fms | spreadsheet
|   es_confirmed       — applicant confirmed the answers
| Answers to the old questions stay in es_checklist but are no longer shown; enterprises answer the new questions.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enterprise_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('enterprise_profiles', 'es_notes')) {
                $table->json('es_notes')->nullable();
            }
            if (! Schema::hasColumn('enterprise_profiles', 'es_records_system')) {
                $table->string('es_records_system', 20)->nullable();
            }
            if (! Schema::hasColumn('enterprise_profiles', 'es_confirmed')) {
                $table->boolean('es_confirmed')->default(false);
            }
        });
    }

    public function down(): void
    {
        Schema::table('enterprise_profiles', function (Blueprint $table) {
            $table->dropColumn(['es_notes', 'es_records_system', 'es_confirmed']);
        });
    }
};