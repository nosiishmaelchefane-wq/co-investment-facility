<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Enterprise profile · aligned with the CAFI Co-Investment Facility Application Form (2026-09-25).
|   Adds the form's questions that the profile did not have yet. Existing fields are kept.
|   Existing data is carried over where it maps (legal form, financing type, investor, contact).
*/
return new class extends Migration
{
    /** column => definition */
    protected function columns(): array
    {
        return [
            // 1 · Company profile
            'prior_cafi_support'    => fn (Blueprint $t) => $t->json('prior_cafi_support')->nullable(),
            'prior_support_details' => fn (Blueprint $t) => $t->text('prior_support_details')->nullable(),
            'short_name'            => fn (Blueprint $t) => $t->string('short_name', 120)->nullable(),
            'year_established'      => fn (Blueprint $t) => $t->unsignedSmallInteger('year_established')->nullable(),
            'website'               => fn (Blueprint $t) => $t->string('website')->nullable(),
            'address'               => fn (Blueprint $t) => $t->string('address')->nullable(),
            'city'                  => fn (Blueprint $t) => $t->string('city', 80)->nullable(),
            'business_model'        => fn (Blueprint $t) => $t->string('business_model', 20)->nullable(),
            'women_owned_managed'   => fn (Blueprint $t) => $t->boolean('women_owned_managed')->nullable(),
            'youth_owned_managed'   => fn (Blueprint $t) => $t->boolean('youth_owned_managed')->nullable(),
            // 4 · Contact
            'contact_name'          => fn (Blueprint $t) => $t->string('contact_name')->nullable(),
            'contact_title'         => fn (Blueprint $t) => $t->string('contact_title', 120)->nullable(),
            'contact_phone'         => fn (Blueprint $t) => $t->string('contact_phone', 30)->nullable(),
            'contact_email'         => fn (Blueprint $t) => $t->string('contact_email')->nullable(),
            // 2 · Legal status
            'obfc_registered'       => fn (Blueprint $t) => $t->boolean('obfc_registered')->nullable(),
            'foreign_registered'    => fn (Blueprint $t) => $t->boolean('foreign_registered')->nullable(),
            'foreign_country'       => fn (Blueprint $t) => $t->string('foreign_country', 80)->nullable(),
            // 3 · Market
            'markets'               => fn (Blueprint $t) => $t->json('markets')->nullable(),
            // 5 · Employees + E&S checklist
            'employees_prev_year'   => fn (Blueprint $t) => $t->unsignedInteger('employees_prev_year')->nullable(),
            'ft_male'               => fn (Blueprint $t) => $t->unsignedInteger('ft_male')->nullable(),
            'ft_female'             => fn (Blueprint $t) => $t->unsignedInteger('ft_female')->nullable(),
            'pt_male'               => fn (Blueprint $t) => $t->unsignedInteger('pt_male')->nullable(),
            'pt_female'             => fn (Blueprint $t) => $t->unsignedInteger('pt_female')->nullable(),
            'es_checklist'          => fn (Blueprint $t) => $t->json('es_checklist')->nullable(),
            // 7 · Business description
            'company_stage'         => fn (Blueprint $t) => $t->string('company_stage', 20)->nullable(),
            // 8 · Financials
            'round_amount'          => fn (Blueprint $t) => $t->decimal('round_amount', 15, 2)->nullable(),
            'round_investors'       => fn (Blueprint $t) => $t->json('round_investors')->nullable(),
            'investment_history'    => fn (Blueprint $t) => $t->json('investment_history')->nullable(),
        ];
    }

    public function up(): void
    {
        Schema::table('enterprise_profiles', function (Blueprint $table) {
            foreach ($this->columns() as $column => $define) {
                if (! Schema::hasColumn('enterprise_profiles', $column)) {
                    $define($table);
                }
            }
        });

        // Carry existing answers over to the form's options
        $legalForms = [
            'Private company (Pty) Ltd' => 'Private Shareholding', 'Public company' => 'Public Shareholding',
            'Partnership' => 'General Partnership', 'Sole proprietor' => 'Others', 'Co-operative' => 'Others',
        ];
        $financing = ['quasi_equity' => 'convertible_note', 'debt' => 'other'];

        $rows = DB::table('enterprise_profiles')
            ->leftJoin('users', 'users.id', '=', 'enterprise_profiles.user_id')
            ->select('enterprise_profiles.*', 'users.name as u_name', 'users.email as u_email', 'users.phone as u_phone')
            ->get();

        foreach ($rows as $p) {
            $update = [
                'legal_form'      => $legalForms[$p->legal_form] ?? $p->legal_form,
                'participation'   => $financing[$p->participation] ?? $p->participation,
                'obfc_registered' => $p->obfc_registered ?? (filled($p->registration_no) ? true : null),
                'contact_name'    => $p->contact_name ?? ($p->declared_name ?: $p->u_name),
                'contact_email'   => $p->contact_email ?? $p->u_email,
                'contact_phone'   => $p->contact_phone ?? $p->u_phone,
            ];

            if ($p->round_investors === null && filled($p->investor_name)) {
                $update['round_investors'] = json_encode([[
                    'name' => $p->investor_name, 'country' => '', 'ticket' => $p->investor_contribution !== null ? (float) $p->investor_contribution : null,
                ]]);
            }
            if ($p->round_amount === null && $p->investor_contribution !== null) {
                $update['round_amount'] = $p->investor_contribution;
            }

            DB::table('enterprise_profiles')->where('id', $p->id)->update($update);
        }

        // New required items mean existing profiles must be re-checked
        DB::table('enterprise_profiles')->update(['status' => 'draft', 'completed_at' => null]);
    }

    public function down(): void
    {
        $drop = array_values(array_filter(array_keys($this->columns()), fn ($c) => Schema::hasColumn('enterprise_profiles', $c)));

        Schema::table('enterprise_profiles', fn (Blueprint $table) => $table->dropColumn($drop));
    }
};