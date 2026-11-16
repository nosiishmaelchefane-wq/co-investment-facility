<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| G3 · Committee scorecards — the Committee secretary can correct a member's scorecard or reopen it for the member.
|   edited_by / edited_at      last change made by the secretary on the member's behalf (shown on the scorecard)
|   reopened_by / reopened_at  last time the secretary sent a submitted scorecard back to the member to edit
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('committee_scorecards', function (Blueprint $table) {
            $table->foreignId('edited_by')->nullable()->after('submitted_at')->constrained('users')->nullOnDelete();
            $table->timestamp('edited_at')->nullable()->after('edited_by');
            $table->foreignId('reopened_by')->nullable()->after('edited_at')->constrained('users')->nullOnDelete();
            $table->timestamp('reopened_at')->nullable()->after('reopened_by');
        });
    }

    public function down(): void
    {
        Schema::table('committee_scorecards', function (Blueprint $table) {
            $table->dropConstrainedForeignId('edited_by');
            $table->dropConstrainedForeignId('reopened_by');
            $table->dropColumn(['edited_at', 'reopened_at']);
        });
    }
};