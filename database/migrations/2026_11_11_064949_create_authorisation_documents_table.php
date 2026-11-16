<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Gate 5 · Final authorisation — several documents per step.
|   md_sent    documents the PMU sends to the CAFI Managing Director for review (Grant Agreement, memo, annexes …)
|   ps_signed  documents signed by the MTIBD Principal Secretary, uploaded by the PMU when they come back by e-mail
| The Managing Director only reviews (no signed copy). The PS signs outside the system; the PMU forwards the
| documents by e-mail and uploads the signed ones here.
| Files from the old single-file columns are copied in as rows (the columns stay for older rounds).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('authorisation_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('authorisation_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20);                 // md_sent | ps_signed
            $table->string('path');
            $table->string('name');
            $table->unsignedBigInteger('size')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['authorisation_id', 'kind']);
        });

        // Existing single files → rows
        $now = now();
        DB::table('authorisations')->orderBy('id')->each(function ($a) use ($now) {
            $rows = [];
            if ($a->md_request_path) {
                $rows[] = ['authorisation_id' => $a->id, 'kind' => 'md_sent', 'path' => $a->md_request_path,
                           'name' => $a->md_request_name ?: basename($a->md_request_path), 'size' => null,
                           'uploaded_by' => $a->md_sent_by, 'created_at' => $a->md_sent_at ?? $now, 'updated_at' => $now];
            }
            if ($a->ps_signed_path) {
                $rows[] = ['authorisation_id' => $a->id, 'kind' => 'ps_signed', 'path' => $a->ps_signed_path,
                           'name' => $a->ps_signed_name ?: basename($a->ps_signed_path), 'size' => $a->ps_signed_size,
                           'uploaded_by' => $a->ps_signed_uploaded_by, 'created_at' => $a->ps_signed_at ?? $now, 'updated_at' => $now];
            }
            if ($rows) {
                DB::table('authorisation_documents')->insert($rows);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('authorisation_documents');
    }
};