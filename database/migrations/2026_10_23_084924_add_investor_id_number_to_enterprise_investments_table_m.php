<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Investor ID / passport number (or company registration number) on each investment.
| Used to recognise the same investor across enterprises, so the facility can see how much
| each investor has put into each enterprise and monitor their returns.
| Existing rows are filled in from the enterprise's investor list where the names match.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enterprise_investments', function (Blueprint $table) {
            if (! Schema::hasColumn('enterprise_investments', 'investor_id_number')) {
                $table->string('investor_id_number', 40)->nullable()->after('investor_name')->index();
            }
        });

        $key = fn ($name) => trim(preg_replace('/\s+/', ' ', preg_replace('/\b(pty|ltd|limited|inc|llc|plc|co|company|proprietary)\b/', '',
            trim(preg_replace('/\s+/', ' ', preg_replace('/[^a-z0-9 ]+/', ' ', strtolower((string) $name)))))));

        foreach (DB::table('enterprise_profiles')->whereNotNull('round_investors')->get(['id', 'round_investors']) as $p) {
            foreach (json_decode($p->round_investors, true) ?: [] as $r) {
                if (blank($r['name'] ?? null) || blank($r['id_number'] ?? null)) {
                    continue;
                }
                DB::table('enterprise_investments')
                    ->where('enterprise_profile_id', $p->id)
                    ->whereNull('investor_id_number')
                    ->get(['id', 'investor_name'])
                    ->filter(fn ($i) => $key($i->investor_name) === $key($r['name']))
                    ->each(fn ($i) => DB::table('enterprise_investments')->where('id', $i->id)->update(['investor_id_number' => $r['id_number']]));
            }
        }
    }

    public function down(): void
    {
        Schema::table('enterprise_investments', function (Blueprint $table) {
            $table->dropIndex(['investor_id_number']);
            $table->dropColumn('investor_id_number');
        });
    }
};