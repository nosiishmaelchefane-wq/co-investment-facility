<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Gate 4 signing by e-mail.
|   The Managing Director and Principal Secretary do not log in: the PMU e-mails each of them the authorisation
|   document to sign, uploads the signed copy, and records the decision written on it.
|   For each authority (md_ / ps_): who signs, what was sent and when, and the signed copy.
|   md_by / ps_by now hold the staff member who recorded the decision.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('authorisations', function (Blueprint $table) {
            foreach (['md', 'ps'] as $r) {
                $table->string("{$r}_signer_name")->nullable();
                $table->string("{$r}_signer_email")->nullable();
                $table->string("{$r}_request_path")->nullable();       // document e-mailed for signing
                $table->string("{$r}_request_name")->nullable();
                $table->text("{$r}_request_message")->nullable();
                $table->timestamp("{$r}_sent_at")->nullable();
                $table->foreignId("{$r}_sent_by")->nullable()->constrained('users')->nullOnDelete();
                $table->string("{$r}_signed_path")->nullable();        // signed copy, uploaded on return
                $table->string("{$r}_signed_name")->nullable();
                $table->unsignedBigInteger("{$r}_signed_size")->nullable();
                $table->timestamp("{$r}_signed_at")->nullable();
                $table->foreignId("{$r}_signed_uploaded_by")->nullable()->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('authorisations', function (Blueprint $table) {
            foreach (['md', 'ps'] as $r) {
                $table->dropConstrainedForeignId("{$r}_sent_by");
                $table->dropConstrainedForeignId("{$r}_signed_uploaded_by");
                $table->dropColumn([
                    "{$r}_signer_name", "{$r}_signer_email", "{$r}_request_path", "{$r}_request_name", "{$r}_request_message",
                    "{$r}_sent_at", "{$r}_signed_path", "{$r}_signed_name", "{$r}_signed_size", "{$r}_signed_at",
                ]);
            }
        });
    }
};