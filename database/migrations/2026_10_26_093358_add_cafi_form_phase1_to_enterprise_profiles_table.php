<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| CAFI Co-Investment application form — phase 1 (company, legal, contacts, employees, key individuals)
|   month_commenced          1.3  month operations commenced (with year_established)
|   obfc_status              1.4  yes | no | in_progress   (obfc_registered stays in step: true only for "yes")
|   sub_sector               1.9
|   foreign_registration_no  2.3
|   foreign_owned / foreign_owned_pct   2.5 / 2.6
|   primary_ops_lesotho      2.7
|   rsl_tax_file             2.8  yes | no | in_progress
|   authorised_rep, alternative_contact, secondary_name/title/email/phone   Section 4
|   youth_male, youth_female, expected_jobs, expected_jobs_women, expected_jobs_youth   Section 5
|   key_people [{name, position, years, nationality}], ownership_dispute, ownership_dispute_details   Section 7
*/
return new class extends Migration
{
    protected function columns(): array
    {
        return [
            'month_commenced'           => fn (Blueprint $t) => $t->unsignedTinyInteger('month_commenced')->nullable(),
            'obfc_status'               => fn (Blueprint $t) => $t->string('obfc_status', 20)->nullable(),
            'sub_sector'                => fn (Blueprint $t) => $t->string('sub_sector', 120)->nullable(),
            'foreign_registration_no'   => fn (Blueprint $t) => $t->string('foreign_registration_no', 60)->nullable(),
            'foreign_owned'             => fn (Blueprint $t) => $t->boolean('foreign_owned')->nullable(),
            'foreign_owned_pct'         => fn (Blueprint $t) => $t->decimal('foreign_owned_pct', 5, 2)->nullable(),
            'primary_ops_lesotho'       => fn (Blueprint $t) => $t->boolean('primary_ops_lesotho')->nullable(),
            'rsl_tax_file'              => fn (Blueprint $t) => $t->string('rsl_tax_file', 20)->nullable(),
            'authorised_rep'            => fn (Blueprint $t) => $t->string('authorised_rep')->nullable(),
            'alternative_contact'       => fn (Blueprint $t) => $t->string('alternative_contact', 500)->nullable(),
            'secondary_name'            => fn (Blueprint $t) => $t->string('secondary_name')->nullable(),
            'secondary_title'           => fn (Blueprint $t) => $t->string('secondary_title', 120)->nullable(),
            'secondary_email'           => fn (Blueprint $t) => $t->string('secondary_email')->nullable(),
            'secondary_phone'           => fn (Blueprint $t) => $t->string('secondary_phone', 30)->nullable(),
            'youth_male'                => fn (Blueprint $t) => $t->unsignedInteger('youth_male')->nullable(),
            'youth_female'              => fn (Blueprint $t) => $t->unsignedInteger('youth_female')->nullable(),
            'expected_jobs'             => fn (Blueprint $t) => $t->unsignedInteger('expected_jobs')->nullable(),
            'expected_jobs_women'       => fn (Blueprint $t) => $t->unsignedInteger('expected_jobs_women')->nullable(),
            'expected_jobs_youth'       => fn (Blueprint $t) => $t->unsignedInteger('expected_jobs_youth')->nullable(),
            'key_people'                => fn (Blueprint $t) => $t->json('key_people')->nullable(),
            'ownership_dispute'         => fn (Blueprint $t) => $t->boolean('ownership_dispute')->nullable(),
            'ownership_dispute_details' => fn (Blueprint $t) => $t->text('ownership_dispute_details')->nullable(),
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

        // OBFC: carry the old yes/no answer over
        DB::table('enterprise_profiles')->whereNull('obfc_status')->where('obfc_registered', true)->update(['obfc_status' => 'yes']);
        DB::table('enterprise_profiles')->whereNull('obfc_status')->where('obfc_registered', false)->update(['obfc_status' => 'no']);
    }

    public function down(): void
    {
        Schema::table('enterprise_profiles', function (Blueprint $table) {
            $table->dropColumn(array_values(array_filter(array_keys($this->columns()), fn ($c) => Schema::hasColumn('enterprise_profiles', $c))));
        });
    }
};