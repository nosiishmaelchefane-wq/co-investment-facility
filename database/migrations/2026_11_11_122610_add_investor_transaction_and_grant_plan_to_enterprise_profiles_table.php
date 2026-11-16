<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Enterprise profile · Business & financials — CAFI application form v2:
|   8.2 Investor information   one lead investor (replaces the list of participating investors)
|   8.3 Transaction information
|   8.4 Proposed use of the CAFI co-investment grant — a grant-only budget in US$, line items per approved
|       category (quantity × unit cost or a lump sum, Lesotho / outside Lesotho), plus the C declarations.
|
| Kept in step automatically by EnterpriseProfile (so Gates 1–7 keep working unchanged):
|   round_investors / round_amount / investor_name / investor_contribution  ← the lead investor and amount
|   transaction_status ← 8.2.8 engagement status · participation ← 8.3.1 instrument · investor_type ← 8.2.5
|   grant_budget (Maloti, grant column only) and grant_outside_lesotho ← grant_plan
|   investment_purpose ← 8.3.10 transaction description
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enterprise_profiles', function (Blueprint $table) {
            // 8.2 Investor information
            $table->string('lead_investor_name')->nullable();
            $table->string('lead_investor_contact_name')->nullable();
            $table->string('lead_investor_email')->nullable();
            $table->string('lead_investor_phone', 40)->nullable();
            $table->string('lead_investor_website')->nullable();
            $table->string('lead_investor_country', 80)->nullable();
            $table->string('investment_currency', 3)->nullable();          // LSL | ZAR | USD — for 8.2.7, 8.3.3, 8.3.5
            $table->decimal('lead_investor_amount', 15, 2)->nullable();    // 8.2.7
            $table->string('investor_commitment', 20)->nullable();         // 8.2.9 yes | no | indicative

            // 8.3 Transaction information
            $table->boolean('tx_amount_agreed')->nullable();               // 8.3.2
            $table->decimal('tx_amount', 15, 2)->nullable();               // 8.3.3
            $table->boolean('tx_terms_agreed')->nullable();                // 8.3.4
            $table->decimal('valuation', 15, 2)->nullable();               // 8.3.5
            $table->string('valuation_by', 20)->nullable();                // 8.3.6
            $table->string('valuation_method', 20)->nullable();            // 8.3.7
            $table->string('expected_close', 120)->nullable();             // 8.3.8 date or timeframe
            $table->boolean('tx_conditions_outstanding')->nullable();      // 8.3.9
            $table->text('tx_description')->nullable();                    // 8.3.10

            // 8.4 Proposed use of the CAFI grant
            $table->json('grant_plan')->nullable();                        // {category: [{item, description, quantity, unit_cost, lump_sum, location, country}]} in US$
            $table->string('grant_lesotho_70', 10)->nullable();            // C2 yes | no | unsure
            $table->boolean('double_financing')->nullable();               // C3
            $table->text('double_financing_details')->nullable();
            $table->text('grant_growth_support')->nullable();              // C4 (max 150 words)
        });

        // Existing profiles: the first participating investor becomes the lead investor (amounts were in Maloti)
        DB::table('enterprise_profiles')->whereNotNull('round_investors')->orderBy('id')->each(function ($p) {
            $rows = collect(json_decode($p->round_investors ?? '[]', true) ?: [])->filter(fn ($r) => filled($r['name'] ?? null))->values();
            if ($rows->isEmpty()) {
                return;
            }
            $tickets = $rows->sum(fn ($r) => is_numeric($r['ticket'] ?? null) ? (float) $r['ticket'] : 0);
            DB::table('enterprise_profiles')->where('id', $p->id)->update([
                'lead_investor_name'    => $rows->pluck('name')->implode(', '),
                'lead_investor_country' => $rows->first()['country'] ?? null,
                'investment_currency'   => 'LSL',
                'lead_investor_amount'  => $tickets > 0 ? $tickets : ($p->round_amount ?: null),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('enterprise_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'lead_investor_name', 'lead_investor_contact_name', 'lead_investor_email', 'lead_investor_phone', 'lead_investor_website',
                'lead_investor_country', 'investment_currency', 'lead_investor_amount', 'investor_commitment',
                'tx_amount_agreed', 'tx_amount', 'tx_terms_agreed', 'valuation', 'valuation_by', 'valuation_method',
                'expected_close', 'tx_conditions_outstanding', 'tx_description',
                'grant_plan', 'grant_lesotho_70', 'double_financing', 'double_financing_details', 'grant_growth_support',
            ]);
        });
    }
};