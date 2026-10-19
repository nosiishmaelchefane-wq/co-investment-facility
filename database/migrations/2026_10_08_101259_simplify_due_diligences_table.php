<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Gate 2 · simplified due diligence.
|   - due_diligences: the detailed JSON sections are replaced by a 6-area checklist + key figures + summary.
|     Existing rows are converted (figures, stage, investors, summary) before the old columns are dropped.
|   - esap_actions: merged into due_diligence_conditions (prefixed "ESAP:") and dropped.
|   - condition treatments reduced to 4.
*/
return new class extends Migration
{
    protected array $old = [
        'target_on', 'depth', 'enterprise', 'enterprise_consistency', 'investors', 'investor_checks', 'transaction',
        'financials', 'financial_assessment', 'commercial_assessment', 'esgc', 'risks', 'risk_conclusion', 'tss',
    ];

    public function up(): void
    {
        // 1. New columns
        Schema::table('due_diligences', function (Blueprint $table) {
            $add = fn (string $col, callable $def) => Schema::hasColumn('due_diligences', $col) ?: $def($table);

            $add('areas',             fn ($t) => $t->json('areas')->nullable()->after('started_on'));
            $add('investor_names',    fn ($t) => $t->string('investor_names')->nullable()->after('areas'));
            $add('stage',             fn ($t) => $t->string('stage', 30)->nullable()->after('investor_names'));
            $add('instrument',        fn ($t) => $t->string('instrument', 30)->nullable()->after('stage'));
            $add('private_amount',    fn ($t) => $t->decimal('private_amount', 15, 2)->nullable()->after('instrument'));
            $add('grant_amount',      fn ($t) => $t->decimal('grant_amount', 15, 2)->nullable()->after('private_amount'));
            $add('summary',           fn ($t) => $t->text('summary')->nullable()->after('esgc_category'));
            $add('expected_outcomes', fn ($t) => $t->text('expected_outcomes')->nullable()->after('summary'));
            $add('key_risks',         fn ($t) => $t->text('key_risks')->nullable()->after('expected_outcomes'));
        });

        // 2. Convert existing assessments
        if (Schema::hasColumn('due_diligences', 'transaction')) {
            foreach (DB::table('due_diligences')->get() as $row) {
                $t         = json_decode($row->transaction ?? '[]', true) ?: [];
                $investors = json_decode($row->investors ?? '[]', true) ?: [];
                $tss       = json_decode($row->tss ?? '[]', true) ?: [];
                $risks     = json_decode($row->risks ?? '[]', true) ?: [];

                $private = collect($t['capital'] ?? [])
                    ->filter(fn ($c) => ($c['category'] ?? null) === 'committed' && ! empty($c['eligible']))
                    ->sum(fn ($c) => (float) ($c['amount'] ?? 0));
                $grant = collect($t['use_of_funds'] ?? [])->sum(fn ($r) => (float) ($r['grant'] ?? 0));

                DB::table('due_diligences')->where('id', $row->id)->update([
                    'areas'             => $row->decided_at ? json_encode(array_fill_keys(['enterprise', 'investor', 'transaction', 'financial', 'esgc', 'risk'], ['status' => 'complete', 'finding' => ''])) : null,
                    'investor_names'    => collect($investors)->pluck('name')->filter()->implode(' · ') ?: null,
                    'stage'             => $t['stage'] ?? null,
                    'instrument'        => $t['structure']['instrument'] ?? null ?: null,
                    'private_amount'    => $private ?: null,
                    'grant_amount'      => $grant ?: null,
                    'summary'           => $tss['enterprise_summary'] ?? null,
                    'expected_outcomes' => $tss['expected_outcomes'] ?? null,
                    'key_risks'         => ($tss['key_risks'] ?? null) ?: (collect($risks)->whereIn('rating', ['medium', 'high'])->pluck('issue')->filter()->implode(' · ') ?: null),
                ]);
            }
        }

        // 3. ESAP actions → conditions
        if (Schema::hasTable('esap_actions')) {
            foreach (DB::table('esap_actions')->get() as $a) {
                DB::table('due_diligence_conditions')->insert([
                    'due_diligence_id' => $a->due_diligence_id,
                    'finding'          => mb_substr('ESAP: '.$a->action, 0, 255),
                    'treatment'        => $a->treatment === 'monitoring' ? 'monitoring' : 'condition_precedent',
                    'owner'            => 'enterprise',
                    'status'           => $a->status === 'done' ? 'resolved' : 'open',
                    'resolved_at'      => $a->completed_at,
                    'position'         => 100 + $a->position,
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ]);
            }
            Schema::drop('esap_actions');
        }

        // 4. Fewer condition treatments
        DB::table('due_diligence_conditions')->where('treatment', 'before_gate2')->update(['treatment' => 'before_ic']);
        DB::table('due_diligence_conditions')->where('treatment', 'before_contracting')->update(['treatment' => 'condition_precedent']);

        // 5. Drop the old detailed columns
        $drop = array_values(array_filter($this->old, fn ($c) => Schema::hasColumn('due_diligences', $c)));
        if ($drop) {
            Schema::table('due_diligences', fn (Blueprint $table) => $table->dropColumn($drop));
        }
    }

    public function down(): void
    {
        Schema::table('due_diligences', function (Blueprint $table) {
            $table->date('target_on')->nullable();
            $table->string('depth')->nullable();
            foreach (['enterprise', 'investors', 'investor_checks', 'transaction', 'financials', 'esgc', 'risks', 'tss'] as $c) {
                $table->json($c)->nullable();
            }
            foreach (['enterprise_consistency', 'financial_assessment', 'commercial_assessment'] as $c) {
                $table->text($c)->nullable();
            }
            $table->string('risk_conclusion', 30)->nullable();
            $table->dropColumn(['areas', 'investor_names', 'stage', 'instrument', 'private_amount', 'grant_amount', 'summary', 'expected_outcomes', 'key_risks']);
        });

        Schema::create('esap_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('due_diligence_id')->constrained()->cascadeOnDelete();
            $table->string('action');
            $table->string('dimension', 20)->nullable();
            $table->string('treatment', 30)->default('monitoring');
            $table->string('timing')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamp('completed_at')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });
    }
};