<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Enterprise profile — the 11 application components from the ToR.
| One profile per enterprise user. Repeatable rows are stored as JSON.
| All money columns are in Maloti (LSL).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enterprise_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            // 1. Enterprise profile
            $table->string('registered_name')->nullable();
            $table->string('trading_name')->nullable();
            $table->string('registration_no', 60)->nullable();
            $table->string('legal_form', 60)->nullable();
            $table->date('registered_on')->nullable();
            $table->string('district', 40)->nullable();
            $table->string('address')->nullable();
            $table->string('sector', 60)->nullable();
            $table->unsignedInteger('employees')->nullable();
            $table->unsignedInteger('women_employees')->nullable();
            $table->unsignedTinyInteger('basotho_owned_pct')->nullable();
            $table->unsignedTinyInteger('women_owned_pct')->nullable();
            $table->unsignedTinyInteger('youth_owned_pct')->nullable();
            $table->text('activity')->nullable();

            // 2. Commercial profile
            $table->json('products')->nullable();            // ["Dried peaches", ...]
            $table->text('customers')->nullable();
            $table->json('markets')->nullable();             // ["Lesotho", "South Africa"]
            $table->decimal('annual_revenue', 15, 2)->nullable();
            $table->decimal('revenue_growth', 6, 2)->nullable();
            $table->text('traction')->nullable();
            $table->text('growth_opportunity')->nullable();

            // 3. Management & governance
            $table->json('shareholders')->nullable();        // [{name, type, percent}]
            $table->json('management_team')->nullable();     // [{name, role, years}]
            $table->unsignedTinyInteger('board_size')->nullable();
            $table->unsignedTinyInteger('independent_directors')->nullable();
            $table->string('board_meetings', 30)->nullable();
            $table->boolean('audited_accounts')->default(false);

            // 4. Investment requirement
            $table->decimal('amount_sought', 15, 2)->nullable();
            $table->decimal('project_cost', 15, 2)->nullable();
            $table->decimal('own_contribution', 15, 2)->nullable();
            $table->decimal('investor_contribution', 15, 2)->nullable();
            $table->decimal('grant_requested', 15, 2)->nullable();
            $table->text('investment_purpose')->nullable();
            $table->json('use_of_funds')->nullable();        // [{item, amount}]

            // 5. Investor profile
            $table->string('investor_name')->nullable();
            $table->string('investor_type', 60)->nullable();
            $table->string('investor_country', 60)->nullable();
            $table->string('investor_contact')->nullable();
            $table->string('participation', 20)->nullable(); // equity | quasi_equity | debt
            $table->json('investor_eligibility')->nullable();// {key: bool}

            // 6. Transaction details
            $table->string('instrument', 20)->nullable();
            $table->string('structure')->nullable();
            $table->decimal('stake_pct', 5, 2)->nullable();
            $table->decimal('valuation', 15, 2)->nullable();
            $table->string('transaction_status', 30)->nullable();
            $table->text('key_terms')->nullable();

            // 8. Financial information
            $table->json('financials')->nullable();          // [{year, revenue, gross_profit, net_profit, total_assets}]
            $table->json('statutory')->nullable();           // {key: bool}

            // 9. Grant utilisation
            $table->json('grant_allocations')->nullable();   // [{item, amount, timing}]

            // 11. Declarations
            $table->json('declarations')->nullable();        // {key: bool}
            $table->string('declared_name')->nullable();
            $table->string('declared_position')->nullable();
            $table->date('declared_on')->nullable();

            $table->string('status', 20)->default('draft');  // draft | complete
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        // 7. Investor commitment evidence + 10. Supporting documents
        Schema::create('enterprise_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enterprise_profile_id')->constrained()->cascadeOnDelete();
            $table->string('type', 60);                      // key from EnterpriseDocument::TYPES
            $table->string('path');
            $table->string('original_name');
            $table->string('mime', 100)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->date('document_date')->nullable();       // e.g. date a term sheet was signed
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['enterprise_profile_id', 'type']);   // one current file per type
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enterprise_documents');
        Schema::dropIfExists('enterprise_profiles');
    }
};