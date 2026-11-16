<?php

use App\Models\EnterpriseProfile;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| EOI — answers from the enterprise profile (application form v2) that each application now keeps for itself:
|   registration_no                       1.4 OBFC registration number
|   investor_type / investor_country      8.2.5 / 8.2.6 lead investor
|   investor_contact_name / _email        8.2.2 / 8.2.3 lead investor contact
|   expected_close                        8.3.8 expected close / deployment
|   grant_requested_usd                   8.4 total CAFI grant requested (US$)
|   grant_outside_lesotho_usd             8.4 part of the grant spent outside Lesotho (US$)
|   grant_budget                          8.4 grant budget per category {category: US$}
| Existing applications are filled in from their enterprise profile where the answer is empty.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expression_of_interests', function (Blueprint $table) {
            $table->string('registration_no', 100)->nullable()->after('legal_status');
            $table->string('investor_type', 80)->nullable()->after('investor_name');
            $table->string('investor_country', 80)->nullable()->after('investor_type');
            $table->string('investor_contact_name')->nullable()->after('investor_country');
            $table->string('investor_contact_email')->nullable()->after('investor_contact_name');
            $table->string('expected_close', 120)->nullable()->after('investor_contact_email');
            $table->decimal('grant_requested_usd', 15, 2)->nullable()->after('use_of_funds_description');
            $table->decimal('grant_outside_lesotho_usd', 15, 2)->nullable()->after('grant_requested_usd');
            $table->json('grant_budget')->nullable()->after('grant_outside_lesotho_usd');
        });

        // Backfill from the applicant's enterprise profile
        $profiles = DB::table('enterprise_profiles')->get()->keyBy('user_id');

        DB::table('expression_of_interests')->orderBy('id')->each(function ($eoi) use ($profiles) {
            $p = $profiles[$eoi->user_id] ?? null;
            if (! $p) {
                return;
            }

            $plan   = json_decode($p->grant_plan ?? '[]', true) ?: [];
            $totals = EnterpriseProfile::planTotals($plan);
            $cats   = array_filter($totals['categories'], fn ($v) => $v > 0);

            DB::table('expression_of_interests')->where('id', $eoi->id)->update([
                'registration_no'           => $p->registration_no ?? null,
                'investor_type'             => $p->investor_type ?? null,
                'investor_country'          => $p->lead_investor_country ?? null,
                'investor_contact_name'     => $p->lead_investor_contact_name ?? null,
                'investor_contact_email'    => $p->lead_investor_email ?? null,
                'expected_close'            => $p->expected_close ?? null,
                'grant_requested_usd'       => $totals['total'] > 0 ? round($totals['total'], 2) : null,
                'grant_outside_lesotho_usd' => $totals['total'] > 0 ? round($totals['outside'], 2) : null,
                'grant_budget'              => $cats ? json_encode(array_map(fn ($v) => round($v, 2), $cats)) : null,
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('expression_of_interests', function (Blueprint $table) {
            $table->dropColumn([
                'registration_no', 'investor_type', 'investor_country', 'investor_contact_name', 'investor_contact_email',
                'expected_close', 'grant_requested_usd', 'grant_outside_lesotho_usd', 'grant_budget',
            ]);
        });
    }
};