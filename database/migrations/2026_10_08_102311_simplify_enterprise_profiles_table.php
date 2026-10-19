<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/*
| Enterprise profile · simplified to 4 sections.
|   - new: management (text), declaration_agreed (bool)
|   - existing details are folded into the remaining text fields before the old columns are dropped
|   - documents reduced to 6 types (4 required); duplicates after merging are removed
*/
return new class extends Migration
{
    protected array $old = [
        'trading_name', 'registered_on', 'address', 'women_employees', 'products', 'markets', 'revenue_growth', 'traction',
        'growth_opportunity', 'shareholders', 'management_team', 'board_size', 'independent_directors', 'board_meetings',
        'audited_accounts', 'amount_sought', 'own_contribution', 'use_of_funds', 'investor_country', 'investor_contact',
        'investor_eligibility', 'instrument', 'structure', 'stake_pct', 'valuation', 'key_terms', 'financials', 'statutory',
        'grant_allocations', 'declarations', 'declared_position',
    ];

    public function up(): void
    {
        Schema::table('enterprise_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('enterprise_profiles', 'management')) {
                $table->text('management')->nullable()->after('customers');
            }
            if (! Schema::hasColumn('enterprise_profiles', 'declaration_agreed')) {
                $table->boolean('declaration_agreed')->default(false)->after('declarations');
            }
        });

        // Fold old details into the remaining fields
        if (Schema::hasColumn('enterprise_profiles', 'shareholders')) {
            $j = fn ($v) => json_decode($v ?? '[]', true) ?: [];

            foreach (DB::table('enterprise_profiles')->get() as $p) {
                $products = implode(', ', $j($p->products));
                $markets  = implode(', ', $j($p->markets));
                $owners   = collect($j($p->shareholders))->map(fn ($s) => trim(($s['name'] ?? '').' '.(($s['percent'] ?? '') !== '' ? '('.$s['percent'].'%)' : '')))->filter()->implode(', ');
                $team     = collect($j($p->management_team))->map(fn ($m) => trim(($m['name'] ?? '').(filled($m['role'] ?? null) ? ' — '.$m['role'] : '')))->filter()->implode(', ');
                $uses     = collect($j($p->use_of_funds))->map(fn ($r) => ($r['item'] ?? '').' M '.number_format((float) ($r['amount'] ?? 0)))->implode('; ');
                $grants   = collect($j($p->grant_allocations))->map(fn ($r) => ($r['item'] ?? '').' M '.number_format((float) ($r['amount'] ?? 0)))->implode('; ');
                $decl     = $j($p->declarations);

                DB::table('enterprise_profiles')->where('id', $p->id)->update([
                    'customers'          => trim(implode("\n", array_filter([
                        $products ? "Products: {$products}" : null, $p->customers, $markets ? "Markets: {$markets}" : null,
                    ]))) ?: null,
                    'management'         => trim(implode("\n", array_filter([
                        $owners ? "Shareholders: {$owners}" : null, $team ? "Management: {$team}" : null,
                    ]))) ?: null,
                    'participation'      => $p->participation ?: $p->instrument,
                    'investment_purpose' => trim(implode("\n", array_filter([
                        $p->investment_purpose, $uses ? "Private investment: {$uses}" : null, $grants ? "CI grant: {$grants}" : null,
                    ]))) ?: null,
                    'declaration_agreed' => $decl && count(array_filter($decl)) === count($decl),
                ]);
            }
        }

        // Documents → 6 types (keep the most relevant one per profile when several merge)
        $map = [
            'audited_financials'   => ['financials', 2], 'management_accounts' => ['financials', 1],
            'investment_agreement' => ['commitment', 4], 'final_term_sheet'     => ['commitment', 3],
            'indicative_term_sheet'=> ['commitment', 2], 'loi'                  => ['commitment', 1],
            'shareholder_register' => ['other', 2],      'esg_self_assessment'  => ['other', 1],
        ];
        $docs = DB::table('enterprise_documents')->whereIn('type', array_keys($map))->get()
            ->groupBy(fn ($d) => $d->enterprise_profile_id.'|'.$map[$d->type][0]);

        foreach ($docs as $key => $group) {
            [$profileId, $newType] = explode('|', $key);
            if (DB::table('enterprise_documents')->where('enterprise_profile_id', $profileId)->where('type', $newType)->exists()) {
                $keep = null;   // already has one of the new type
            } else {
                $keep = $group->sortByDesc(fn ($d) => $map[$d->type][1])->first();
                DB::table('enterprise_documents')->where('id', $keep->id)->update(['type' => $newType]);
            }
            foreach ($group as $d) {
                if (! $keep || $d->id !== $keep->id) {
                    Storage::disk('local')->delete($d->path);
                    DB::table('enterprise_documents')->where('id', $d->id)->delete();
                }
            }
        }

        $drop = array_values(array_filter($this->old, fn ($c) => Schema::hasColumn('enterprise_profiles', $c)));
        if ($drop) {
            Schema::table('enterprise_profiles', fn (Blueprint $table) => $table->dropColumn($drop));
        }
    }

    public function down(): void
    {
        Schema::table('enterprise_profiles', function (Blueprint $table) {
            foreach (['trading_name', 'address', 'investor_country', 'investor_contact', 'instrument', 'structure', 'declared_position', 'board_meetings'] as $c) {
                $table->string($c)->nullable();
            }
            foreach (['products', 'markets', 'shareholders', 'management_team', 'use_of_funds', 'investor_eligibility', 'financials', 'statutory', 'grant_allocations', 'declarations'] as $c) {
                $table->json($c)->nullable();
            }
            foreach (['traction', 'growth_opportunity', 'key_terms'] as $c) {
                $table->text($c)->nullable();
            }
            foreach (['revenue_growth', 'amount_sought', 'own_contribution', 'valuation', 'stake_pct'] as $c) {
                $table->decimal($c, 15, 2)->nullable();
            }
            $table->date('registered_on')->nullable();
            $table->unsignedInteger('women_employees')->nullable();
            $table->unsignedTinyInteger('board_size')->nullable();
            $table->unsignedTinyInteger('independent_directors')->nullable();
            $table->boolean('audited_accounts')->default(false);
            $table->dropColumn(['management', 'declaration_agreed']);
        });
    }
};