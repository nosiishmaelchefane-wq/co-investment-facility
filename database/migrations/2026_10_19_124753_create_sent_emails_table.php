<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Compose Email — every e-mail written on /compose-email (drafts too).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sent_emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('expression_of_interest_id')->nullable()->constrained()->nullOnDelete();
            $table->string('subject', 200);
            $table->longText('body');
            $table->json('recipients_spec')->nullable();   // groups, stage, to, cc, bcc, individually — as chosen on the form
            $table->json('recipient_list')->nullable();    // e-mail addresses it was sent to
            $table->json('attachments')->nullable();       // [{name, path, size}]
            $table->unsignedInteger('recipient_count')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->string('status', 20)->default('draft'); // draft | sent | partial | failed
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sent_emails');
    }
};