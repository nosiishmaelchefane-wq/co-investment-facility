<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Enterprise profile — follows the CAFI Co-Investment Facility Application Form, grouped into 5 sections.
 * Money in Maloti (LSL).
 *   1 Company & contact      (form §1 Company profile, §4 Applicant contact)
 *   2 Legal status & markets (form §2 Legal status, §3 The market)
 *   3 Employees & key people (form §5 Employees + E&S checklist, §6 Key individuals)
 *   4 Business & financials  (form §7 Business description, §8 Financials)
 *   5 Attachments & declaration (form §9 Attachments)
 */
class EnterpriseProfile extends Model
{
    public const STATUS_DRAFT    = 'draft';
    public const STATUS_COMPLETE = 'complete';

    /** key => [title, icon, description] */
    public const SECTIONS = [
        'company'     => ['Company & contact',         'bi-building',     'Company details, business model, ownership and who we should contact'],
        'legal'       => ['Legal status & markets',    'bi-bank',         'Is the company registered with OBFC in Lesotho (or in another country), and which markets do you sell in?'],
        'people'      => ['Employees & key people',    'bi-people',       'Staff numbers, environmental & social checklist and key individuals'],
        'business'    => ['Business & financials',     'bi-graph-up',     'What you do, your stage, the investment round and funding to date'],
        'attachments' => ['Attachments & declaration', 'bi-folder2-open', 'Supporting documents and your declaration'],
    ];

    /**
     * Fields edited in each section => required.
     * Some are only required in certain cases — see requiredFields().
     */
    public const FIELDS = [
        'company' => [
            'registered_name' => true, 'short_name' => false, 'year_established' => true, 'website' => false,
            'address' => true, 'city' => true, 'district' => true, 'sector' => true, 'business_model' => true,
            'women_owned_managed' => true, 'youth_owned_managed' => true,
            'basotho_owned_pct' => false, 'women_owned_pct' => false, 'youth_owned_pct' => false,
            'prior_cafi_support' => true, 'prior_support_details' => true,
            'contact_name' => true, 'contact_title' => true, 'contact_phone' => true, 'contact_email' => true,
        ],
        'legal' => [
            'obfc_registered' => true, 'legal_form' => true, 'registration_no' => true,
            'foreign_registered' => true, 'foreign_country' => true, 'markets' => true,
        ],
        'people' => [
            'employees_prev_year' => true, 'employees' => true,
            'ft_male' => true, 'ft_female' => true, 'pt_male' => true, 'pt_female' => true,
            'employee_band' => true,
            'es_checklist' => true, 'management' => false,
        ],
        'business' => [
            // Required = marked * on the CAFI form. The extra questions (products/customers, deal status, project cost,
            // what the money is for, lead investor type) are optional.
            'activity' => true, 'company_stage' => true, 'customers' => false, 'usp' => false, 'marketing_strategy' => false, 'competition' => false,
            'annual_revenue' => true,
            'round_amount' => true, 'round_investors' => false, 'participation' => true, 'investor_type' => false,
            'transaction_status' => false, 'project_cost' => false, 'investment_purpose' => false,
            'grant_budget' => true,            // required once the investor amount is known (see requiredFields)
            'investment_history' => false,
        ],
        'attachments' => [
            'declaration_agreed' => true, 'declared_name' => true, 'declared_on' => true,
        ],
    ];

    /** Short question labels (used to list what is still unanswered) */
    public const LABELS = [
        'registered_name' => 'Company official name', 'short_name' => 'Short name', 'year_established' => 'Year of establishment',
        'website' => 'Website', 'address' => 'Address', 'city' => 'City / town', 'district' => 'District', 'sector' => 'Sector',
        'business_model' => 'Main business model', 'women_owned_managed' => 'Women owned / managed', 'youth_owned_managed' => 'Youth owned / managed',
        'basotho_owned_pct' => '% shares held by Basotho', 'women_owned_pct' => '% shares held by women', 'youth_owned_pct' => '% shares held by youth',
        'prior_cafi_support' => 'Prior CAFI support', 'prior_support_details' => 'Support received',
        'contact_name' => 'Contact name', 'contact_title' => 'Contact title', 'contact_phone' => 'Contact phone', 'contact_email' => 'Contact e-mail',
        'obfc_registered' => 'Registered at OBFC', 'legal_form' => 'Type of registration', 'registration_no' => 'Registration number',
        'foreign_registered' => 'Registered in another country', 'foreign_country' => 'Country of registration', 'markets' => 'Current markets',
        'employees_prev_year' => 'Employees (previous year)', 'employees' => 'Employees (current year)',
        'ft_male' => 'Full-time males', 'ft_female' => 'Full-time females', 'pt_male' => 'Part-time males', 'pt_female' => 'Part-time females',
        'employee_band' => 'Current number of employees (0-10 / over 10)',
        'es_checklist' => 'Environmental & social checklist', 'management' => 'Ownership & management summary',
        'activity' => 'Company summary', 'company_stage' => 'Stage of development', 'customers' => 'Products, customers & markets',
        'usp' => 'Unique selling proposition', 'marketing_strategy' => 'Marketing approach', 'competition' => 'Competition',
        'annual_revenue' => 'Annual revenue', 'round_amount' => 'Current investment round', 'round_investors' => 'Participating investors',
        'participation' => 'Financing type', 'investor_type' => 'Lead investor type', 'transaction_status' => 'Status of the deal',
        'project_cost' => 'Total project cost', 'grant_requested' => 'CAFI matching grant', 'investment_purpose' => 'What the money is for',
        'investment_history' => 'Investment to date', 'grant_budget' => 'Grant utilisation budget',
        'declaration_agreed' => 'Declaration', 'declared_name' => 'Declaration name', 'declared_on' => 'Declaration date',
    ];

    /**
     * Hover help (ⓘ next to each section and question). Keys are field names, "section.<key>" or extra headings.
     * Keep them plain-language: what we mean, what a good answer looks like, and why we ask.
     */
    public const HELP = [
        // Sections
        'section.company'     => 'Who you are: the company\'s official details, how it makes money, who owns and runs it, any earlier CAFI support, and the person we should contact about this application.',
        'section.legal'       => 'Whether the company is legally registered with the One-Stop Business Facilitation Centre (OBFC) in Lesotho or in another country, and where it currently sells. Grants can only be paid to OBFC-registered companies.',
        'section.people'      => 'How many people you employ, the environmental and social (E&S) practices required by the project\'s ESMF, and the owners, directors and managers behind the business.',
        'section.business'    => 'What the business does, how it competes, its revenue, the current investment round and who is investing. The CAFI grant is calculated from what your investor(s) put in.',
        'section.attachments' => 'The supporting documents reviewers need to verify your answers, and your signed declaration. All attachments must be in English.',

        // 1 · Company & contact
        'registered_name'       => 'The company name exactly as it appears on your registration certificate.',
        'short_name'            => 'The name customers know you by, if different from the registered name (e.g. a trading name or abbreviation).',
        'year_established'      => 'The year the business started operating — not necessarily the year it was registered.',
        'website'               => 'Your website or main social media page, if you have one. Include https://',
        'address'               => 'The physical address where the business operates (plot number, street, village or area). A P.O. Box alone is not enough.',
        'city'                  => 'The town or city of your main place of business.',
        'district'              => 'The district of your main place of business.',
        'sector'                => 'The sector your main products or services fall under. Choose the closest match; use "Other" only if nothing fits.',
        'business_model'        => 'Who mainly pays you: other businesses (B2B), individual consumers (B2C), or government (B2G).',
        'women_youth_role'      => '"Owned or managed" means women (or young people aged 35 or under) hold a majority of the shares, or hold the top management position.',
        'women_owned_managed'   => 'Yes if women hold more than half the shares, or a woman is the managing director / CEO.',
        'youth_owned_managed'   => 'Yes if people aged 35 or under hold more than half the shares, or the managing director / CEO is 35 or under.',
        'basotho_owned_pct'     => 'The percentage of the company\'s shares held by Lesotho citizens (0–100).',
        'women_owned_pct'       => 'The percentage of the company\'s shares held by women (0–100). A shareholder can count in more than one group.',
        'youth_owned_pct'       => 'The percentage of the company\'s shares held by people aged 35 or under (0–100).',
        'prior_cafi_support'    => 'Tick every CAFI-funded project you have received a grant from before. Choose "None" if you have not received any.',
        'prior_support_details' => 'Which project supported you, roughly when, how much you received and what it was used for.',
        'contact'               => 'The person LEHSFF/CAFI should contact about this application. They should be able to answer questions about the business and the investment.',
        'contact_name'          => 'Full name of the contact person.',
        'contact_title'         => 'Their position in the company, e.g. Managing Director, Founder, Finance Manager.',
        'contact_phone'         => 'A number where the contact can be reached during working hours, with country code (e.g. +266 …).',
        'contact_email'         => 'An e-mail address that is checked regularly — all notices about the application are sent here.',

        // 2 · Legal status & markets
        'obfc_registered'       => 'The One-Stop Business Facilitation Centre (Ministry of Trade, Industry and Business Development) registers companies in Lesotho. You can apply before registering, but no grant can be paid until you are registered.',
        'legal_form'            => 'The legal type shown on your registration certificate, e.g. Private Shareholding (Pty) Ltd.',
        'registration_no'       => 'The company registration number on your OBFC certificate.',
        'foreign_registered'    => 'Yes if the company is also registered in a country other than Lesotho (e.g. South Africa).',
        'foreign_country'       => 'The other country where the company is registered.',
        'markets'               => 'Where you sell today — not where you plan to sell. Tick all that apply.',

        // 3 · Employees & key people
        'employees_prev_year'   => 'Total number of people employed at the end of last year (full-time and part-time).',
        'employees'             => 'Total number of people employed today (full-time and part-time).',
        'employee_details'      => 'Split of your current staff by full-time / part-time and gender. The four numbers should add up to your current total.',
        'ft_male'               => 'Men employed full-time (regular working hours, ongoing contract).',
        'ft_female'             => 'Women employed full-time (regular working hours, ongoing contract).',
        'pt_male'               => 'Men employed part-time, casual or seasonal.',
        'pt_female'             => 'Women employed part-time, casual or seasonal.',
        'employee_band'         => 'Your current number of employees, as a range.',
        'es_checklist'          => 'Environmental and social requirements from the project\'s ESMF. Answer honestly — if you do not meet one yet, you will be required to meet it after the grant is awarded.',
        'management'            => 'A short summary of who owns the company and who runs it day to day: main shareholders and their %, directors, and key managers with their experience.',

        // 4 · Business & financials
        'activity'              => 'In up to 100 words: what problem you solve, what you sell, to whom, and what makes the business work. Write it so someone who has never heard of you understands it.',
        'company_stage'         => 'Ideation: idea only. Development & Launch: building or just launched. Early stage: earning revenue but not yet stable. Growth: growing sales and expanding. Maturity: stable and profitable.',
        'customers'             => 'Your main products or services, who your customers are (type, number, any key buyers or contracts) and where they are.',
        'usp'                   => 'Your unique selling proposition: the main reason customers choose you over alternatives — e.g. price, quality, local sourcing, speed, technology, a patent or an exclusive contract. Be specific.',
        'marketing_strategy'    => 'How you find and keep customers: sales channels (shops, distributors, online, direct), pricing, promotion and partnerships — and what will change with the new investment.',
        'competition'           => 'Who else sells the same or similar products to your customers (local and imported), their rough market share or prices, and how you compare.',
        'annual_revenue'        => 'Total sales for the last full financial year, in Maloti, before costs. Use the figure from your financial statements.',
        'round_amount'          => 'The total amount you are raising in the current investment round, in Maloti (all investors together).',
        'round_investors'       => 'Each investor taking part in this round and the amount (ticket size) they have committed, in Maloti. The CAFI grant is calculated from these amounts.',
        'participation'         => 'How the investor is putting money in: shares (Equity), a loan that converts to shares (Convertible Note / SAFE), a share of future revenue, or other.',
        'investor_type'         => 'The kind of organisation leading the round, e.g. angel investor, venture capital fund, development finance institution.',
        'transaction_status'    => 'How far the deal with your investor has gone: still talking, letter of intent (LOI) received, term sheet signed, or investment agreement signed.',
        'grant_requested'       => 'You do not enter this. CAFI matches a fixed share of what your investor(s) put in; it is calculated from the investor amounts above.',
        'project_cost'          => 'The total cost of the project the investment and grant will pay for, in Maloti, including your own contribution.',
        'investment_purpose'    => 'The main things the investor\'s money and the CAFI grant will pay for, with rough amounts (e.g. equipment M 300,000; working capital M 150,000).',
        'investment_history'    => 'All the money put into the company since it started, by source, with the date of the most recent amount from each source.',
        'grant_budget'          => 'How the investor\'s money and the CAFI grant will be spent, by category, in Maloti. Fill it in once you know what your investor(s) are putting in. The CAFI grant column must add up to the calculated matching grant. This budget is carried into the Grant Agreement.',
        'investments'           => 'Each separate investment the company has received from an investor, with the date and amount. List every one — reviewers look at how often and how much each investor has invested.',

        // 5 · Attachments & declaration
        'declaration_agreed'    => 'By ticking, you confirm the information is true and complete and allow LEHSFF/CAFI to verify it. Giving false information can disqualify the application.',
        'declared_name'         => 'Full name and position of the person signing — normally a director or the managing director.',
        'declared_on'           => 'The date you are signing the declaration.',
    ];

    /* ---------------- Options (from the application form) ---------------- */

    public const PRIOR_SUPPORT = ['incubation_hub' => 'Incubation Hub', 'leap' => 'LEAP', 'horticulture' => 'Horticulture Center', 'none' => 'None'];

    public const DISTRICTS = ['Berea', 'Butha-Buthe', 'Leribe', 'Mafeteng', 'Maseru', "Mohale's Hoek", 'Mokhotlong', "Qacha's Nek", 'Quthing', 'Thaba-Tseka'];

    public const SECTORS = ['Agro-processing', 'Agriculture', 'Manufacturing', 'Tourism & hospitality', 'ICT', 'Creative industries', 'Construction', 'Services', 'Other'];

    public const BUSINESS_MODELS = ['b2b' => 'Business to Business', 'b2c' => 'Business to Consumers', 'b2g' => 'Business to Government', 'other' => 'Other'];

    public const LEGAL_FORMS = ['Private Shareholding', 'Public Shareholding', 'General Partnership', 'Limited Partnership', 'Limited Liability', 'Others'];

    public const MARKETS = ['lesotho' => 'Lesotho', 'south_africa' => 'South Africa', 'regional' => 'Regional and International', 'none' => 'None'];

    /** key => [question, required] */
    /** "What is the current number of your employees?" */
    public const EMPLOYEE_BANDS = ['0_10' => '0-10', 'over_10' => 'Over 10'];

    public const ES_CHECKLIST = [
        'hr_policy'        => ['Does your company have a Human Resources Policy known by all workers?', true],
        'contracts'        => ['Does your company have employment contracts for all workers?', true],
        'under_18'         => ['Do you employ any worker under the age of 18?', true],
        'injury_insurance' => ['Does your company have a valid insurance against work injuries for all workers?', true],
        'lease'            => ['If your company operates from a rented office, do you have a valid lease contract?', true],
        'code_of_conduct'  => ['Does your company have a Code of Conduct to be applied for workers?', true],
        'code_signed'      => ['Have all workers understood and signed this Code of Conduct?', false],
        'grm'              => ['Does your company have an effective grievance redress mechanism (GRM) to workers and community?', true],
        'grm_known'        => ['Do workers and community know this GRM?', true],
        'rsl_tax_file'     => ['Does your company have an open tax file with the RSL?', true],
        'women_youth_jobs' => ['Will your proposed project provide local employment for women or/and youth?', true],
    ];

    /** Questions that can also be answered "Not applicable" */
    public const ES_ALLOW_NA = ['lease'];

    /** The answer that meets the requirement (used to flag gaps) */
    public const ES_EXPECTED = ['under_18' => 'no'];

    public const ES_NOTE = 'Enterprises that do not currently meet any of the above ESMF requirements must comply with them after the Co-Investment grant is awarded.';

    public const STAGES = [
        'ideation'    => 'Ideation',
        'development' => 'Development & Launch',
        'early'       => 'Early stage (revenue generating)',
        'growth'      => 'Growth & Expansion',
        'maturity'    => 'Maturity (sustainable profits)',
    ];

    /** Investment round financing type */
    public const PARTICIPATION = ['equity' => 'Equity', 'convertible_note' => 'Convertible Note', 'safe' => 'SAFE', 'revenue_share' => 'Revenue Share', 'other' => 'Other'];

    public const INVESTOR_TYPES = ['Private equity fund', 'Venture capital fund', 'Angel investor', 'Corporate investor', 'Development finance institution', 'Commercial bank', 'Other'];

    public const TRANSACTION_STATUSES = ['discussion' => 'In discussion', 'loi' => 'LOI received', 'term_sheet' => 'Term sheet signed', 'agreement' => 'Investment agreement signed'];

    /** Total amount of investment in the company to date — source => label */
    public const FUNDING_SOURCES = [
        'owners' => 'Owners', 'crowdfunding' => 'Crowd funding', 'angel' => 'Angel investor', 'vc' => 'Venture capital',
        'incubators' => 'Incubators / accelerators', 'loans' => 'Loans', 'grants' => 'Grants from donors', 'others' => 'Others',
    ];

    public const DISCLAIMERS = [
        'Submitting this grant application does not guarantee the award of a grant. All grant awards are contingent upon the applicant firm meeting all eligibility requirements, selection criteria, and the availability of budgeted funds.',
        'The Competitiveness and Financial Inclusion (CAFI) Project reserves the right to review, approve, defer back or deny any application based on these factors. Any communication or indication of eligibility or selection prior to the formal award notice should not be construed as a guarantee of funding.',
    ];

    public const REGISTRATION_NOTE = 'Your application can be processed now, but you will not be able to get any grant support until you are registered with the OBFC.';

    public const DECLARATION = 'I confirm that the information provided is true and complete, that the enterprise is not insolvent and no director has been convicted of fraud or corruption, and I consent to LEHSFF/CAFI verifying it and sharing it with the Investment Committee. I will report any material change.';

    protected $guarded = ['id', 'user_id', 'status', 'completed_at'];

    protected $casts = [
        'declared_on'           => 'date',
        'completed_at'          => 'datetime',
        'declaration_agreed'    => 'boolean',
        'women_owned_managed'   => 'boolean',
        'youth_owned_managed'   => 'boolean',
        'obfc_registered'       => 'boolean',
        'foreign_registered'    => 'boolean',
        'prior_cafi_support'    => 'array',
        'markets'               => 'array',
        'es_checklist'          => 'array',
        'round_investors'       => 'array',
        'investment_history'    => 'array',
        'grant_budget'          => 'array',
        'annual_revenue'        => 'decimal:2',
        'round_amount'          => 'decimal:2',
        'project_cost'          => 'decimal:2',
        'investor_contribution' => 'decimal:2',
        'grant_requested'       => 'decimal:2',
    ];

    protected static function booted(): void
    {
        // Keep the single investor name / amount (used by the EOI, Gate 1 and Gate 2) in step with the investor list
        static::saving(function (EnterpriseProfile $p) {
            if ($p->isDirty('round_investors')) {
                $rows = $p->investorRows();
                $p->investor_name         = $rows ? collect($rows)->pluck('name')->implode(', ') : null;
                $tickets = collect($rows)->pluck('ticket')->filter(fn ($t) => $t !== null && $t !== '');
                $p->investor_contribution = $tickets->isNotEmpty() ? $tickets->sum(fn ($t) => (float) $t) : null;
            }

            // The CAFI grant is not entered by the applicant — it is a fixed share of what the investor puts in
            $p->grant_requested = self::matchingGrant($p->investorAmount());
        });
    }

    /* ---------------- Relations ---------------- */

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(EnterpriseDocument::class);
    }

    /** Investments received (each one recorded separately) */
    public function investments(): HasMany
    {
        return $this->hasMany(EnterpriseInvestment::class)->orderByDesc('invested_on')->orderByDesc('id');
    }

    /** Profile of a user, or a fresh unsaved one pre-filled from the account. */
    public static function forUser(User $user): self
    {
        $profile = static::with('documents')->where('user_id', $user->id)->first();

        if (! $profile) {
            // user_id is guarded, so set it with forceFill rather than firstOrNew()
            $profile = (new static([
                'registered_name' => $user->organisation_name,
                'contact_name'    => $user->name,
                'contact_email'   => $user->email,
                'contact_phone'   => $user->phone ?? null,
            ]))->forceFill(['user_id' => $user->id]);
            $profile->setRelation('documents', new Collection());
        }

        return $profile;
    }

    /* ---------------- Completeness ---------------- */

    /** Required fields of a section, taking the applicant's answers into account */
    public function requiredFields(string $section): array
    {
        $fields = array_keys(array_filter(self::FIELDS[$section] ?? []));

        $skip = [];
        if ($section === 'company' && $this->noPriorSupport()) {
            $skip[] = 'prior_support_details';
        }
        if ($section === 'legal' && $this->obfc_registered !== true) {
            $skip[] = 'registration_no';
        }
        if ($section === 'legal' && $this->foreign_registered !== true) {
            $skip[] = 'foreign_country';
        }
        if ($section === 'business' && $this->investorAmount() === null) {
            $skip[] = 'grant_budget';   // filled in after the investor amount is known
        }

        return array_values(array_diff($fields, $skip));
    }

    /** Whether a single field has an acceptable answer */
    public function isAnswered(string $field): bool
    {
        $v = $this->{$field};

        return match ($field) {
            'declaration_agreed' => $v === true,
            'es_checklist'       => collect(self::ES_CHECKLIST)->filter(fn ($q) => $q[1])->keys()
                                        ->every(fn ($k) => in_array($v[$k] ?? null, ['yes', 'no', 'na'], true)),
            'round_investors'    => $this->investorRows() !== [],
            'grant_budget'       => $this->budgetTotals()['private'] + $this->budgetTotals()['grant'] > 0,
            default              => is_array($v) ? $v !== [] : ($v !== null && $v !== ''),
        };
    }

    /** Required questions of a section that are not answered yet */
    public function missingFields(string $section): array
    {
        return array_values(array_filter($this->requiredFields($section), fn ($f) => ! $this->isAnswered($f)));
    }

    /** Required documents of a section that are not uploaded yet */
    public function missingSectionDocuments(string $section): array
    {
        return array_values(array_filter($this->missingDocuments(), fn ($t) => EnterpriseDocument::TYPES[$t][2] === $section));
    }

    /** key => bool for each section */
    public function sectionStatus(): array
    {
        return collect(self::SECTIONS)->map(function ($s, $section) {
            $fieldsOk = collect($this->requiredFields($section))->every(fn ($f) => $this->isAnswered($f));
            $docsOk   = collect($this->requiredDocumentTypes())
                ->filter(fn ($t) => EnterpriseDocument::TYPES[$t][2] === $section)
                ->every(fn ($t) => (bool) $this->document($t));

            return $fieldsOk && $docsOk;
        })->all();
    }

    /** Document types required for this profile (depends on registration answers) */
    public function requiredDocumentTypes(): array
    {
        return array_keys(array_filter(EnterpriseDocument::TYPES, fn ($t) => match ($t[3]) {
            true             => true,
            'registered'     => $this->obfc_registered === true,
            'not_registered' => $this->obfc_registered === false,
            'foreign'        => $this->foreign_registered === true,
            default          => false,
        }));
    }

    /** Required document types not uploaded yet */
    public function missingDocuments(): array
    {
        return array_values(array_filter($this->requiredDocumentTypes(), fn ($t) => ! $this->document($t)));
    }

    public function completedCount(): int
    {
        return count(array_filter($this->sectionStatus()));
    }

    public function completeness(): int
    {
        return (int) round($this->completedCount() / count(self::SECTIONS) * 100);
    }

    public function isComplete(): bool
    {
        return $this->completedCount() === count(self::SECTIONS);
    }

    public function refreshStatus(): void
    {
        $complete = $this->isComplete();

        $this->forceFill([
            'status'       => $complete ? self::STATUS_COMPLETE : self::STATUS_DRAFT,
            'completed_at' => $complete ? ($this->completed_at ?? now()) : null,
        ])->saveQuietly();
    }

    /* ---------------- Helpers ---------------- */

    public function document(string $type): ?EnterpriseDocument
    {
        return ($this->relationLoaded('documents') ? $this->documents : $this->documents()->get())->firstWhere('type', $type);
    }

    public function noPriorSupport(): bool
    {
        $s = (array) ($this->prior_cafi_support ?? []);

        return $s === [] || $s === ['none'];
    }

    /** Investor rows that have at least a name */
    public function investorRows(): array
    {
        return array_values(array_filter((array) ($this->round_investors ?? []), fn ($r) => filled($r['name'] ?? null)));
    }

    /* ---------------- Co-investment (config/cif.php → co_investment) ---------------- */

    /** Share of the investor's money that CAFI matches (0.5 = 50%) */
    public static function grantRatio(): float
    {
        return (float) config('cif.co_investment.grant_ratio', 0.5);
    }

    /** "50%" */
    public static function grantRatioLabel(): string
    {
        return rtrim(rtrim(number_format(self::grantRatio() * 100, 1), '0'), '.').'%';
    }

    /** Minimum the investor must be investing, in US$ */
    public static function minInvestorUsd(): float
    {
        return (float) config('cif.co_investment.min_investor_usd', 50000);
    }

    /** Same minimum in Maloti, at the configured exchange rate */
    public static function minInvestorLsl(): float
    {
        return self::minInvestorUsd() * max((float) config('cif.usd_to_lsl', 18.00), 0.0001);
    }

    /** CAFI grant (Maloti) for a given investor amount (Maloti) */
    public static function matchingGrant(?float $investorLsl): ?float
    {
        return $investorLsl !== null && $investorLsl > 0 ? round($investorLsl * self::grantRatio(), 2) : null;
    }

    /** Whether an investor amount (Maloti) reaches the minimum */
    public static function meetsInvestorMinimum(?float $investorLsl): bool
    {
        return $investorLsl !== null && $investorLsl + 0.005 >= self::minInvestorLsl();
    }

    /** What the investor(s) are putting in (Maloti): the investor tickets, otherwise the current round */
    public function investorAmount(): ?float
    {
        $amount = (float) $this->investor_contribution ?: (float) $this->round_amount;

        return $amount > 0 ? $amount : null;
    }

    /* ---------------- Grant utilisation budget ---------------- */

    /** Same categories as Gate 5 (§3.6.4) */
    public const BUDGET_CATEGORIES = ConditionReview::BUDGET_CATEGORIES;

    /** Saved budget rows, or the empty categories */
    public function budgetRows(): array
    {
        $rows = collect($this->grant_budget ?? [])->filter(fn ($r) => filled($r['category'] ?? null));

        return $rows->isNotEmpty()
            ? $rows->values()->all()
            : collect(self::BUDGET_CATEGORIES)->map(fn ($c) => ['category' => $c, 'private' => null, 'grant' => null])->all();
    }

    /** ['private' => M, 'grant' => M] */
    public static function sumBudget(?array $rows): array
    {
        $rows = collect($rows ?? []);

        return [
            'private' => (float) $rows->sum(fn ($r) => is_numeric($r['private'] ?? null) ? (float) $r['private'] : 0),
            'grant'   => (float) $rows->sum(fn ($r) => is_numeric($r['grant'] ?? null) ? (float) $r['grant'] : 0),
        ];
    }

    public function budgetTotals(): array
    {
        return self::sumBudget($this->grant_budget);
    }

    /** Whether the grant column matches the calculated matching grant (to the nearest Maloti) */
    public function budgetMatchesGrant(): bool
    {
        return $this->grant_requested !== null && abs($this->budgetTotals()['grant'] - (float) $this->grant_requested) < 1;
    }

    /** "0-10" or "Over 10" — the applicant's answer */
    public function employeeBand(): ?string
    {
        return self::EMPLOYEE_BANDS[$this->employee_band ?? ''] ?? null;
    }


    /** Sum of the full-time / part-time breakdown */
    public function employeeBreakdownTotal(): ?int
    {
        $parts = [$this->ft_male, $this->ft_female, $this->pt_male, $this->pt_female];

        return collect($parts)->every(fn ($v) => $v === null) ? null : (int) collect($parts)->sum();
    }

    /** E&S answers that do not meet the requirement yet */
    public function esGaps(): array
    {
        return collect($this->es_checklist ?? [])
            ->filter(fn ($answer, $key) => isset(self::ES_CHECKLIST[$key]) && $answer === ((self::ES_EXPECTED[$key] ?? 'yes') === 'yes' ? 'no' : 'yes'))
            ->keys()->all();
    }

    /** "51% Basotho · 40% women · 20% youth" (share ownership) */
    public function ownershipLine(): ?string
    {
        return collect(['Basotho' => $this->basotho_owned_pct, 'women' => $this->women_owned_pct, 'youth' => $this->youth_owned_pct])
            ->filter(fn ($v) => $v !== null)->map(fn ($v, $k) => "{$v}% held by {$k}")->implode(' · ') ?: null;
    }

    /** "Incubation Hub, LEAP" */
    public function labels(array $map, ?array $keys): ?string
    {
        return collect($keys ?? [])->map(fn ($k) => $map[$k] ?? $k)->implode(', ') ?: null;
    }

    public function getInitialAttribute(): string
    {
        return mb_strtoupper(mb_substr($this->short_name ?: ($this->registered_name ?: 'E'), 0, 1));
    }
}