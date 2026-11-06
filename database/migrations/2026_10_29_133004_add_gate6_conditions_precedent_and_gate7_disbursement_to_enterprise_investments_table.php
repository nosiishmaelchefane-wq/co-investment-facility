<?php

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Gate 6 · Conditions Precedent & Onboarding (§3.7) and Gate 7 · Grant Disbursement (§3.8) — one tab.
|
| condition_reviews
|   approval_sent_*        Step 1 · grant approval communicated + onboarding initiated (executed agreement sent)
|   verification           Step 2 · the 7 verification areas {area: {status, note}}
|   bank / bank_*          enterprise bank account used for payment (from Gate 4) + independent revalidation of any change
|   readiness_*            Step 3 · programme and finance confirmation of disbursement readiness
|   baseline, onboarding_* Step 4 · baseline business & employment data, enterprise confirms it was briefed
| cp_conditions            checklist: evidence needed, responsible / verifying party, before disbursement vs ongoing,
|                          corrective action (source widened for "readiness")
| capital_deployments      Gate 7 · matching investment: account received into, verification checks, tranche it backs
| disbursement_tranches    Gate 7 · matching calculation, payment request → approval → instruction → confirmation, outcome
|
| Also runs the roles seeder (adds disburse.approve — payment approval — for the PMU Director of Implementation).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('condition_reviews', function (Blueprint $table) {
            $table->timestamp('approval_sent_at')->nullable()->after('onboarding');
            $table->foreignId('approval_sent_by')->nullable()->after('approval_sent_at')->constrained('users')->nullOnDelete();
            $table->json('verification')->nullable()->after('approval_sent_by');
            $table->json('bank')->nullable()->after('verification');
            $table->timestamp('bank_revalidated_at')->nullable()->after('bank');
            $table->foreignId('bank_revalidated_by')->nullable()->after('bank_revalidated_at')->constrained('users')->nullOnDelete();
            $table->string('bank_revalidation_note', 500)->nullable()->after('bank_revalidated_by');
            $table->foreignId('readiness_pmu_by')->nullable()->after('readiness_checks')->constrained('users')->nullOnDelete();
            $table->timestamp('readiness_pmu_at')->nullable()->after('readiness_pmu_by');
            $table->foreignId('readiness_finance_by')->nullable()->after('readiness_pmu_at')->constrained('users')->nullOnDelete();
            $table->timestamp('readiness_finance_at')->nullable()->after('readiness_finance_by');
            $table->json('baseline')->nullable()->after('onboarding');
            $table->timestamp('onboarding_confirmed_at')->nullable()->after('baseline');
            $table->foreignId('onboarding_confirmed_by')->nullable()->after('onboarding_confirmed_at')->constrained('users')->nullOnDelete();
        });

        Schema::table('cp_conditions', function (Blueprint $table) {
            $table->string('source', 12)->default('g6')->change();
            $table->string('evidence_required', 250)->nullable()->after('finding');
            $table->string('responsible', 20)->default('enterprise')->after('evidence_required');
            $table->string('verifier', 20)->default('pmu')->after('responsible');
            $table->string('timing', 20)->default('before_disbursement')->after('verifier');   // before_disbursement | tranche | ongoing
            $table->string('corrective_action', 500)->nullable()->after('note');
        });

        Schema::table('capital_deployments', function (Blueprint $table) {
            $table->string('received_account', 150)->nullable()->after('deployed_on');
            $table->json('checks')->nullable()->after('received_account');
            $table->foreignId('disbursement_tranche_id')->nullable()->after('checks')->constrained('disbursement_tranches')->nullOnDelete();
        });

        Schema::table('disbursement_tranches', function (Blueprint $table) {
            $table->decimal('capital_verified', 15, 2)->nullable()->after('capital_required');   // cumulative verified private capital at request
            $table->foreignId('requested_by')->nullable()->after('capital_verified')->constrained('users')->nullOnDelete();
            $table->timestamp('requested_at')->nullable()->after('requested_by');
            $table->foreignId('approved_by')->nullable()->after('requested_at')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->string('approval_note', 500)->nullable()->after('approved_at');
            $table->string('instruction_ref', 100)->nullable()->after('approval_note');
            $table->date('instruction_on')->nullable()->after('instruction_ref');
            $table->string('outcome', 20)->nullable()->after('instruction_on');                  // approved | pending | partial | not_eligible
            $table->string('outcome_note', 500)->nullable()->after('outcome');
        });

        (new RolesAndPermissionsSeeder)->run();
    }

    public function down(): void
    {
        Schema::table('disbursement_tranches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('requested_by');
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn(['capital_verified', 'requested_at', 'approved_at', 'approval_note', 'instruction_ref', 'instruction_on', 'outcome', 'outcome_note']);
        });
        Schema::table('capital_deployments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('disbursement_tranche_id');
            $table->dropColumn(['received_account', 'checks']);
        });
        Schema::table('cp_conditions', function (Blueprint $table) {
            $table->dropColumn(['evidence_required', 'responsible', 'verifier', 'timing', 'corrective_action']);
        });
        Schema::table('condition_reviews', function (Blueprint $table) {
            foreach (['approval_sent_by', 'bank_revalidated_by', 'readiness_pmu_by', 'readiness_finance_by', 'onboarding_confirmed_by'] as $fk) {
                $table->dropConstrainedForeignId($fk);
            }
            $table->dropColumn(['approval_sent_at', 'verification', 'bank', 'bank_revalidated_at', 'bank_revalidation_note',
                'readiness_pmu_at', 'readiness_finance_at', 'baseline', 'onboarding_confirmed_at']);
        });
    }
};