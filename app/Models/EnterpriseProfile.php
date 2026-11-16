<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Enterprise profile — follows the CAFI Co-Investment Facility Application Form, grouped into 5 sections.
 * Money is stored in Maloti (LSL); on the profile screen US$ is the main currency (entered in US$, Maloti shown underneath).
 *   1 Company & contact      (form v2 §1 Company profile 1.1–1.14, in order, + §4 Applicant contact)
 *   2 Legal status & markets (form v2 §2 Company legal status 2.1–2.8, incl. the shareholders table + current markets)
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
        'company'     => ['Company & contact',         'bi-building',     'Company profile: name, OBFC registration, stage, address, sector, summary, earlier CAFI support — and who we should contact'],
        'legal'       => ['Legal status & markets',    'bi-bank',         'Legal form, registration in another country, shareholders, foreign ownership, operations, tax file and markets'],
        'people'      => ['Employees & key people',    'bi-people',       'Staff numbers, environmental & social checklist and the people running the company'],
        'business'    => ['Business & financials',     'bi-graph-up',     'Customers and competition, revenue, the investment round and funding to date'],
        'attachments' => ['Attachments & declaration', 'bi-folder2-open', 'Supporting documents and your declaration'],
    ];

    /**
     * Fields edited in each section => required.
     * Some are only required in certain cases — see requiredFields().
     */
    public const FIELDS = [
        'company' => [
            // Form v2 §1 Company profile, in order: 1.1–1.14
            'registered_name' => true, 'short_name' => false, 'month_commenced' => true, 'year_established' => true,
            'obfc_status' => true, 'year_registered' => true, 'registration_no' => false,
            'company_stage' => true,
            'address' => true, 'city' => true, 'district' => true,
            'sector' => true, 'sector_other' => true, 'sub_sector' => true,
            'activity' => true, 'products_services' => false,
            'prior_cafi_support' => true, 'prior_support_details' => true,
            // Additional company information
            'website' => false, 'business_models' => true,
            'women_owned_managed' => true, 'youth_owned_managed' => true,
            'basotho_owned_pct' => false, 'women_owned_pct' => false, 'youth_owned_pct' => false,
            // Form v2 §4 Applicant contact information
            'contact_name' => true, 'contact_title' => true, 'contact_phone' => true, 'contact_email' => true,
            'authorised_rep' => true, 'alternative_contact' => false,
            'secondary_name' => true, 'secondary_title' => true, 'secondary_email' => true, 'secondary_phone' => true,
        ],
        'legal' => [
            // Form v2 §2 Company legal status: 2.1–2.8
            'legal_form' => true,
            'foreign_registered' => true, 'foreign_country' => true, 'foreign_registration_no' => true,
            'shareholder_list' => true,        // 2.4 — the table OR the uploaded list (see isAnswered)
            'foreign_owned' => true, 'foreign_owned_pct' => true, 'primary_ops_lesotho' => true, 'rsl_tax_file' => true,
            'markets' => true,
        ],
        'people' => [
            'employees_prev_year' => true, 'employees' => true,
            'ft_male' => true, 'ft_female' => true, 'pt_male' => true, 'pt_female' => true,
            'youth_male' => true, 'youth_female' => true,
            'expected_jobs' => true, 'expected_jobs_women' => true, 'expected_jobs_youth' => true,
            'es_checklist' => true, 'es_notes' => true, 'es_records_system' => true, 'es_confirmed' => true,
            'key_people' => true, 'ownership_dispute' => true, 'ownership_dispute_details' => true,
            'management' => false,
        ],
        'business' => [
            // Investor questions are only required when the applicant has an investor (see requiredFields)
            // Company summary, stage and products/services moved to Company profile (form v2 §1.7, 1.10, 1.11)
            'customers' => true,
            'usp' => false, 'marketing_strategy' => false, 'competition' => false,
            'annual_revenue' => true,
            // Form v2 8.2 Investor information — one lead investor
            'has_investor' => true,
            'lead_investor_name' => true, 'lead_investor_contact_name' => true, 'lead_investor_email' => true, 'lead_investor_phone' => false,
            'lead_investor_website' => false, 'investor_type' => true, 'lead_investor_country' => true,
            'investment_currency' => false, 'lead_investor_amount' => true,   // amounts in US$ (8.2.7 / 8.3.5)
            'transaction_status' => true, 'investor_commitment' => true,      // 8.2.8 / 8.2.9
            // Form v2 8.3 Transaction information
            'participation' => true, 'tx_amount_agreed' => true, 'tx_amount' => false /* = 8.2.7 once agreed */, 'tx_terms_agreed' => true,
            'valuation' => false, 'valuation_by' => false, 'valuation_method' => false,
            'expected_close' => true, 'tx_conditions_outstanding' => true, 'tx_description' => true,
            'project_cost' => true,
            // Form v2 8.4 Proposed use of the CAFI grant (grant only — no private investment), US$
            'grant_plan' => true, 'grant_outside_justification' => true,
            'grant_lesotho_70' => true, 'double_financing' => true, 'double_financing_details' => true, 'grant_growth_support' => true,
            // Bank account for the grant: entered by the applicant at Gate 4 (Grant readiness), not on the profile
            'investment_history' => false,
        ],
        'attachments' => [
            'declaration_agreed' => true, 'declared_name' => true, 'declared_on' => true,
        ],
    ];

    /** Short question labels (used to list what is still unanswered) */
    public const LABELS = [
        'registered_name' => 'Company official name', 'short_name' => 'Trading name', 'year_established' => 'Year of establishment',
        'year_registered' => 'Year of registration', 'month_commenced' => 'Month operations commenced', 'sub_sector' => 'Sub-sector',
        'authorised_rep' => 'Authorised representative', 'alternative_contact' => 'Alternative contact details',
        'secondary_name' => 'Secondary contact name', 'secondary_title' => 'Secondary contact title',
        'secondary_email' => 'Secondary contact e-mail', 'secondary_phone' => 'Secondary contact phone',
        'obfc_status' => 'Registered at OBFC', 'foreign_registration_no' => 'Foreign registration number',
        'foreign_owned' => 'Foreign ownership', 'foreign_owned_pct' => 'Foreign ownership %',
        'primary_ops_lesotho' => 'Primary operations in Lesotho', 'rsl_tax_file' => 'RSL tax file',
        'youth_male' => 'Youth employees (male)', 'youth_female' => 'Youth employees (female)',
        'expected_jobs' => 'Expected additional jobs', 'expected_jobs_women' => 'Expected jobs for women', 'expected_jobs_youth' => 'Expected jobs for youth',
        'key_people' => 'Directors & key management', 'ownership_dispute' => 'Ownership dispute / litigation', 'ownership_dispute_details' => 'Dispute details',
        'website' => 'Website', 'address' => 'Address', 'city' => 'City / town', 'district' => 'District', 'sector' => 'Sector',
        'sector_other' => 'Other sector', 'business_models' => 'Business model', 'shareholder_list' => 'Shareholders', 'women_owned_managed' => 'Women owned / managed', 'youth_owned_managed' => 'Youth owned / managed',
        'basotho_owned_pct' => '% shares held by Basotho', 'women_owned_pct' => '% shares held by women', 'youth_owned_pct' => '% shares held by youth',
        'prior_cafi_support' => 'Support from a CAFI-funded programme', 'prior_support_details' => 'Support received',
        'contact_name' => 'Contact name', 'contact_title' => 'Contact title', 'contact_phone' => 'Contact phone', 'contact_email' => 'Contact e-mail',
        'obfc_registered' => 'Registered at OBFC', 'legal_form' => 'Type of registration', 'registration_no' => 'Registration number',
        'foreign_registered' => 'Registered in another country', 'foreign_country' => 'Country of registration', 'markets' => 'Current markets',
        'employees_prev_year' => 'Employees (previous year)', 'employees' => 'Employees (current year)',
        'ft_male' => 'Full-time males', 'ft_female' => 'Full-time females', 'pt_male' => 'Part-time males', 'pt_female' => 'Part-time females',
        'es_checklist' => 'ESG checklist', 'es_notes' => 'ESG checklist explanations', 'es_records_system' => 'Financial records system',
        'es_confirmed' => 'ESG checklist confirmation', 'management' => 'Management summary',
        'activity' => 'Company summary', 'company_stage' => 'Business lifecycle stage',
        'products_services' => 'Products & services', 'customers' => 'Customers', 'has_investor' => 'Do you have an investor?',
        'usp' => 'Unique selling proposition', 'marketing_strategy' => 'Marketing approach', 'competition' => 'Competition',
        'annual_revenue' => 'Annual revenue', 'round_amount' => 'Current investment round', 'round_investors' => 'Participating investors',
        'lead_investor_name' => 'Lead investor name', 'lead_investor_contact_name' => 'Lead investor contact name', 'lead_investor_email' => 'Lead investor e-mail',
        'lead_investor_phone' => 'Lead investor telephone', 'lead_investor_website' => 'Investor website', 'lead_investor_country' => 'Investor country',
        'investment_currency' => 'Currency', 'lead_investor_amount' => 'Investment amount from the investor', 'investor_commitment' => 'Investor commitment',
        'tx_amount_agreed' => 'Investment amount agreed', 'tx_amount' => 'Agreed investment amount', 'tx_terms_agreed' => 'Principal terms agreed',
        'valuation' => 'Valuation', 'valuation_by' => 'Who developed the valuation', 'valuation_method' => 'Valuation method',
        'expected_close' => 'Expected close / deployment', 'tx_conditions_outstanding' => 'Material conditions outstanding', 'tx_description' => 'Transaction description',
        'grant_plan' => 'Proposed grant utilisation budget', 'grant_lesotho_70' => 'At least 70% spent in Lesotho',
        'double_financing' => 'Financed by another grant', 'double_financing_details' => 'Other funding details', 'grant_growth_support' => 'How the grant supports the growth plan',
        'participation' => 'Investment instrument', 'investor_type' => 'Investor type', 'transaction_status' => 'Investor engagement status',
        'project_cost' => 'Total project cost', 'grant_requested' => 'CAFI matching grant', 'investment_purpose' => 'What the money is for',
        'investment_history' => 'Investment to date', 'grant_budget' => 'Grant utilisation budget',
        'grant_outside_lesotho' => 'Grant spent outside Lesotho', 'grant_outside_justification' => 'Why grant money is spent outside Lesotho',
        'bank_name' => 'Bank', 'bank_branch' => 'Branch', 'bank_account_name' => 'Account name', 'bank_account_number' => 'Account number', 'bank_account_type' => 'Account type',
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
        'short_name'            => 'The name you trade under, if it is different from the official registered name.',
        'year_established'      => 'When the business started operating — not necessarily when it was registered. Enterprises operating for less than 6 months are flagged for review.',
        'month_commenced'       => 'The month the business started operating.',
        'sub_sector'            => 'A more specific description within your sector, e.g. "Fruit and vegetable processing" within Agro-processing.',
        'authorised_rep'        => 'The person legally allowed to sign for the company (usually a director), with their position, e.g. "Palesa M., Managing Director".',
        'alternative_contact'   => 'Any other way to reach the company if the contacts below are unavailable (optional).',
        'secondary_contact'     => 'A second person we can contact if the primary contact is not available.',
        'foreign_registration_no' => 'The company\'s registration number in the other country.',
        'foreign_owned'         => 'Yes if any shareholder is not a Lesotho citizen or Lesotho-registered company.',
        'foreign_owned_pct'     => 'The total percentage of shares held by foreign shareholders.',
        'primary_ops_lesotho'   => 'Yes if most of your operations (staff, production, sales) are in Lesotho.',
        'rsl_tax_file'          => 'An active tax file with the Revenue Services Lesotho (RSL). A tax file must be opened before the Grant Agreement is signed.',
        'youth_employees'       => 'Employees aged 18 to 35, by gender. They are also counted in the full-time / part-time figures above.',
        'expected_jobs'         => 'How many additional jobs you expect the investment to create over the next 24 months. These figures become the baseline for the quarterly and close-out reports.',
        'key_people'            => 'The directors and key managers: name, position, years of service with the company and nationality.',
        'ownership_dispute'     => 'Any known dispute over who owns the company, any material court case, or any conflict that could affect this investment.',
        'year_registered'       => 'The year the company was registered with the OBFC (shown on your registration certificate).',
        'website'               => 'Your website or main social media page, if you have one. Include https://',
        'address'               => 'The physical address where the business operates (plot number, street, village or area). A P.O. Box alone is not enough.',
        'city'                  => 'The town or city of your main place of business.',
        'district'              => 'The district of your main place of business.',
        'sector'                => 'The sector your main products or services fall under. Choose the closest match; use "Other" only if nothing fits, then type your sector.',
        'sector_other'          => 'Your sector in a few words, e.g. "Renewable energy" or "Waste recycling".',
        'business_models'       => 'Who pays you: other businesses (B2B), individual consumers (B2C) and/or government (B2G). Tick all that apply.',
        'shareholder_list'      => 'Everyone who owns shares in the company: name, ID / passport number (or company registration number), nationality, gender, age and the % of shares they hold. Fill in the table, or upload the list as a document instead.',
        'women_youth_role'      => '"Owned or managed" means women (or young people aged 35 or under) hold a majority of the shares, or hold the top management position.',
        'women_owned_managed'   => 'Yes if women hold more than half the shares, or a woman is the managing director / CEO.',
        'youth_owned_managed'   => 'Yes if young people (18–35) hold more than half the shares, or the managing director / CEO is aged 18–35.',
        'basotho_owned_pct'     => 'The percentage of the company\'s shares held by Lesotho citizens (0–100).',
        'women_owned_pct'       => 'The percentage of the company\'s shares held by women (0–100). A shareholder can count in more than one group.',
        'youth_owned_pct'       => 'The percentage of the company\'s shares held by people aged 35 or under (0–100).',
        'prior_cafi_support'    => 'Whether the enterprise has been supported before by a CAFI-funded programme — the Incubation Hub, LEAP or the Horticulture Center. If yes, tick each one and explain the support.',
        'prior_support_details' => 'Which project supported you, roughly when, how much you received and what it was used for.',
        'contact'               => 'The person LEHSFF/CAFI should contact about this application. They should be able to answer questions about the business and the investment.',
        'contact_name'          => 'Full name of the contact person.',
        'contact_title'         => 'Their position in the company, e.g. Managing Director, Founder, Finance Manager.',
        'contact_phone'         => 'A number where the contact can be reached during working hours, with country code (e.g. +266 …).',
        'contact_email'         => 'An e-mail address that is checked regularly — all notices about the application are sent here.',

        // 2 · Legal status & markets
        'obfc_status'           => 'The One-Stop Business Facilitation Centre (Ministry of Trade, Industry and Business Development) registers companies in Lesotho. Choose "In progress" if you have applied. Registration must be completed before the Grant Agreement is signed.',
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
        'es_checklist'          => 'Environmental, social and governance (ESG) questions from the project\'s ESMF, for your current business and the activities the grant will fund. Answer honestly — a gap does not disqualify you; it tells us where clarification or support is needed. Only work by someone under 18 and activities on the exclusion list (question 13) disqualify an application.',
        'es_records_system'     => 'The tool you use to keep your financial records.',
        'management'            => 'The people who run the company day to day: their names, positions, responsibilities and relevant experience (e.g. "Thabo M. — Managing Director, 12 years in food processing"). Shareholders go in the shareholders table instead.',

        // 4 · Business & financials
        'activity'              => 'In up to 100 words: what problem you solve, what you sell, to whom, and what makes the business work. Write it so someone who has never heard of you understands it.',
        'company_stage'         => 'Ideation: idea only. Development & Launch: building or just launched. Early stage: earning revenue but not yet stable. Growth: growing sales and expanding. Maturity: stable and profitable.',
        'products_services'     => 'What you sell: your main products and/or services, with a short description of each.',
        'customers'             => 'Who buys from you: the type and number of customers, any key buyers or contracts, and where they are.',
        'has_investor'          => 'The Co-Investment Facility matches money from a private investor, so you need at least one investor to receive a CAFI grant. If you do not have one yet you can still save your profile.',
        'usp'                   => 'Your unique selling proposition: the main reason customers choose you over alternatives — e.g. price, quality, local sourcing, speed, technology, a patent or an exclusive contract. Be specific.',
        'marketing_strategy'    => 'How you find and keep customers: sales channels (shops, distributors, online, direct), pricing, promotion and partnerships — and what will change with the new investment.',
        'competition'           => 'Who else sells the same or similar products to your customers (local and imported), their rough market share or prices, and how you compare.',
        'annual_revenue'        => 'Total sales for the last full financial year, before costs, in US dollars (the Maloti equivalent is shown underneath). Use the figure from your financial statements.',
        'round_amount'          => 'The total amount you are raising in the current investment round, in Maloti (all investors together).',
        'round_investors'       => 'Each investor taking part in this round and the amount (ticket size) they have committed, in Maloti. At least one investor with a ticket size is required. For each investor give their national ID or passport number (or, for a company or fund, its registration number) so reviewers can verify them. The CAFI grant is calculated from these amounts.',
        'participation'         => 'How the investor is putting money in: shares (Equity), a loan that converts to shares (Convertible Note / SAFE), a share of future revenue, or other.',
        'investor_type'         => 'The kind of organisation leading the round, e.g. angel investor, venture capital fund, development finance institution.',
        'transaction_status'    => 'How far the deal with your investor has gone: still talking, letter of intent (LOI) received, term sheet signed, or investment agreement signed.',
        'grant_requested'       => 'The CAFI matching grant is a non-repayable grant from the CAFI project. It is worked out from what your investor puts in — 50% of the first US$ 300,000 plus 33% of any amount above that, up to US$ 350,000 — so you do not type it. It is paid in tranches once the conditions in the Grant Agreement are met, and may only be spent on the items in your grant utilisation budget.',
        'project_cost'          => 'The total cost of the project the investment and grant will pay for, in US dollars (the Maloti equivalent is shown underneath), including your own contribution. It cannot be less than the indicative CAFI grant.',
        'investment_purpose'    => 'The main things the investor\'s money and the CAFI grant will pay for, with rough amounts (e.g. equipment M 300,000; working capital M 150,000).',
        'investment_history'    => 'All the money put into the company since it started, by source, with the date of the most recent amount from each source.',
        'grant_budget'          => 'How the investor\'s money and the CAFI grant will be spent, by category, in Maloti. The "Should add up to" row shows the targets: the private column must add up to what your investor(s) put in, and the CAFI grant column to your matching grant. This budget is carried into the Grant Agreement.',
        'grant_outside_lesotho' => 'How much of the CAFI grant (in Maloti) will pay for goods, services or staff outside Lesotho — for example equipment bought from a South African supplier. Enter 0 if everything is spent in Lesotho. At least 70% of the grant must be spent in Lesotho.',
        'section.bank'          => 'The bank account the CAFI grant will be paid into. It must be in the company\'s registered name. The LEHSFF team checks these details against your bank confirmation letter before any payment.',
        'bank_account_name'     => 'Exactly as it appears on the bank account — it must be the company\'s registered name, not a personal account.',
        'grant_outside_justification' => 'Explain why this part of the grant has to be spent outside Lesotho, e.g. the equipment is not available locally. Needed when less than 70% of the grant is spent in Lesotho.',
        // 8.2 · 8.3 · 8.4 (form v2)
        'section.investor'      => 'The private investor who is leading this investment. The CAFI grant matches what this investor puts in.',
        'lead_investor_name'    => 'The name of the person, company or fund leading the investment.',
        'lead_investor_contact' => 'The person at the investor we can contact to confirm the investment.',
        'lead_investor_country' => 'The country the investor is based in.',
        'lead_investor_amount'  => 'How much the investor will put into the enterprise in this transaction, in US dollars (the Maloti equivalent is shown underneath). The indicative CAFI grant is calculated from this amount.',
        'investor_commitment'   => 'Whether the investor has confirmed in writing (Yes), only indicated interest (Indicative only), or not yet (No).',
        'section.transaction'   => 'The terms of the investment: the instrument, the agreed amount, the valuation and when the investment is expected to close.',
        'tx_amount'             => 'The amount you will receive from the investor, as agreed. When given, the indicative CAFI grant is calculated from it.',
        'valuation'             => 'The value of the enterprise used for this investment (for equity or convertible instruments). Leave empty if there is no valuation yet.',
        'expected_close'        => 'A date or timeframe, e.g. "March 2027" or "within 3 months".',
        'tx_description'        => 'In up to 100 words: what the investor is investing in, on what terms, and the business outcome you expect.',
        'section.grant_use'     => 'An indicative budget for the CAFI grant only — not for the investor\'s money. Add the items or activities the grant will pay for under each approved category.',
        'grant_plan'            => 'Add each item or activity the grant will pay for: what it is, why it is needed, quantity × unit cost (or a lump sum) in US$, and whether the money is spent in Lesotho or outside. The total cannot be more than your indicative CAFI grant.',
        'grant_lesotho_70'      => 'At least 70% of the CAFI grant must be spent in Lesotho. The platform works out the share from your budget; answer what you expect.',
        'double_financing'      => 'Whether any item in this budget is also paid for by another grant or public funding source. The same expenditure cannot be financed twice.',
        'grant_growth_support'  => 'In up to 150 words: how the grant-funded items help the enterprise grow — more production, new markets, jobs.',
        'investments'           => 'Each separate investment the company has received from an investor, with the date and amount. List every one — reviewers look at how often and how much each investor has invested.',

        // 5 · Attachments & declaration
        'declaration_agreed'    => 'By ticking, you confirm the information is true and complete and allow LEHSFF/CAFI to verify it. Giving false information can disqualify the application.',
        'declared_name'         => 'Full name and position of the person signing — normally a director or the managing director.',
        'declared_on'           => 'The date you are signing the declaration.',
    ];

    /* ---------------- Options (from the application form) ---------------- */

    public const PRIOR_SUPPORT = [
        'incubation_hub' => 'Incubation Hub', 'leap' => 'LEAP', 'horticulture' => 'Horticulture Center',
        'pre_investment' => 'Pre-Investment / Catalytic Funding Grant', 'co_investment' => 'Co-Investment Grant', 'other' => 'Other',
        'none' => 'None',
    ];

    /** Form v2 1.13 — the programmes offered on the form (the other keys above stay for older answers) */
    public const PRIOR_SUPPORT_CHOICES = ['incubation_hub', 'leap', 'horticulture'];

    /** Yes / No / In progress (OBFC registration, RSL tax file) */
    public const YES_NO_PROGRESS = ['yes' => 'Yes', 'no' => 'No', 'in_progress' => 'In progress'];

    public const MONTHS = [1 => 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

    public const BLANK_KEY_PERSON = ['name' => '', 'position' => '', 'years' => '', 'nationality' => ''];

    public const DISTRICTS = ['Berea', 'Butha-Buthe', 'Leribe', 'Mafeteng', 'Maseru', "Mohale's Hoek", 'Mokhotlong', "Qacha's Nek", 'Quthing', 'Thaba-Tseka'];

    public const SECTORS = ['Agro-processing', 'Agriculture', 'Manufacturing', 'Tourism & hospitality', 'ICT', 'Creative industries', 'Construction', 'Services', 'Other'];

    public const BUSINESS_MODELS = ['b2b' => 'Business to Business', 'b2c' => 'Business to Consumers', 'b2g' => 'Business to Government', 'other' => 'Other'];

    public const LEGAL_FORMS = ['Private Shareholding', 'Public Shareholding', 'General Partnership', 'Limited Partnership', 'Limited Liability', 'Others'];

    public const MARKETS = ['lesotho' => 'Lesotho', 'south_africa' => 'South Africa', 'regional' => 'Regional and International', 'none' => 'None'];

    /** Employee size band (derived from the current number of employees) */
    public const EMPLOYEE_BANDS = ['0_10' => '0-10', 'over_10' => 'Over 10'];

    /** Shareholders table */
    public const GENDERS = ['Male', 'Female'];

    public const BLANK_SHAREHOLDER = ['name' => '', 'id_number' => '', 'nationality' => '', 'gender' => '', 'age' => '', 'percent' => ''];

    /** key => [question, required] */

    /**
     * Environmental, Social & Governance checklist — CAFI Co-Investment application form.
     * key => [area, question]. Every question is answered Yes / No / Do not know / Not applicable.
     * Answers flag issues for review; they do not disqualify on their own, except the two marked below.
     */
    public const ES_CHECKLIST = [
        'permits'        => ['Environmental',            'Does your business have all the environmental permits or approvals required for its activities?'],
        'pollution'      => ['Environmental',            'Does your business produce waste, wastewater, smoke, dust or noise that could harm people or the environment?'],
        'hazardous'      => ['Environmental',            'Does your business use or store chemicals, pesticides, fuel or other hazardous materials?'],
        'land'           => ['Environmental and social', 'Will the grant-funded activities involve construction, new land, vegetation clearance, or changes to other people\'s access to land or water?'],
        'pay'            => ['Social',                   'Are workers informed of their pay, working hours and duties, and paid on time?'],
        'safety'         => ['Social',                   'Are measures in place to protect workers from injury, including appropriate equipment and safety instructions?'],
        'under_18'       => ['Social',                   'Does anyone under 18 work in the business, including temporary workers or unpaid family members?'],
        'free_to_leave'  => ['Social',                   'Can workers freely leave their employment and raise concerns about working conditions or harassment, sexual or physical, without threats or punishment?'],
        'incidents'      => ['Environmental and social', 'In the past three years, has the business had a serious accident, major pollution incident, regulatory penalty, or unresolved complaint from workers or the community?'],
        'records'        => ['Governance',               'Does your business maintain records of its income, expenditure and payments (Financial Management System)?'],
        'grant_tracking' => ['Governance',               'Can your business separately identify and account for how the grant funds are used?'],
        'relationship'   => ['Governance',               'Is there any ownership, family or business relationship between your enterprise and anyone involved in selecting applicants or approving grants under this CAFI project, i.e. the Hub or CAFI?'],
        'excluded'       => ['Environmental and social', 'Do any of your current or proposed activities involve any of the following: tobacco; alcohol, wine or spirits; mining; logging; property development; adult entertainment; gambling; firearms; works in nature reserves or alteration of rivers or other natural environments; or industrial or construction activities that generate pollution?'],
    ];

    public const ES_ANSWERS = ['yes' => 'Yes', 'no' => 'No', 'dont_know' => 'Do not know', 'na' => 'Not applicable'];

    /** "Yes" → "What measures do you use to manage these risks?" (hazard present; controls are reviewed) */
    public const ES_MEASURES_IF_YES = ['pollution', 'hazardous'];

    /** "Yes" → "Please briefly explain." (targeted due diligence) */
    public const ES_EXPLAIN_IF_YES = ['land', 'under_18', 'incidents', 'relationship', 'excluded'];

    /** "No" → "Please briefly explain the gap or support needed." (clarification or improvement measure) */
    public const ES_GAP_IF_NO = ['permits', 'pay', 'safety', 'free_to_leave', 'records', 'grant_tracking'];

    /** "Yes" → immediate disqualification (work by someone under 18; activity on the exclusion list) */
    public const ES_DISQUALIFY_IF_YES = ['under_18', 'excluded'];

    /** Question 10 "Yes" → "What do you use?" */
    public const ES_RECORD_SYSTEMS = [
        'fms'         => 'Financial Management System (e.g. QuickBooks, Tally)',
        'spreadsheet' => 'Excel or equivalent (e.g. Google Sheets)',
    ];

    public const ES_INTRO = 'Please answer for your current business and the activities proposed under this grant. Your answers will help us identify any issues requiring clarification or support. Identifying a gap does not necessarily disqualify your application.';

    public const ES_CONFIRMATION = 'I confirm that these answers are accurate to the best of my knowledge. I understand that the project may request clarification or supporting information during due diligence.';

    public const ES_NOTE = 'Enterprises that do not currently meet any of the above ESMF requirements must comply with them after the Co-Investment grant is awarded.';

    public const STAGES = [
        'ideation'    => 'Ideation',
        'development' => 'Development & Launch',
        'early'       => 'Early stage (revenue generating)',
        'growth'      => 'Growth & Expansion',
        'maturity'    => 'Maturity (sustainable profits)',
    ];

    /** Form v2 8.3.1 — proposed investment instrument */
    public const INSTRUMENTS = ['equity' => 'Equity', 'quasi_equity' => 'Quasi-equity', 'convertible' => 'Convertible instrument', 'debt' => 'Debt'];

    /** Instrument labels, including answers given on the earlier form */
    public const PARTICIPATION = self::INSTRUMENTS + ['convertible_note' => 'Convertible Note', 'safe' => 'SAFE', 'revenue_share' => 'Revenue Share', 'other' => 'Other'];

    /** Form v2 8.2.5 — investor type */
    public const LEAD_INVESTOR_TYPES = ['Angel investor', 'Venture capital fund', 'Family office', 'Institutional investor', 'Other'];

    /** Investor types (form v2 first, then the earlier list — still used for investments received) */
    public const INVESTOR_TYPES = ['Angel investor', 'Venture capital fund', 'Family office', 'Institutional investor', 'Private equity fund', 'Corporate investor', 'Development finance institution', 'Commercial bank', 'Other'];

    /** Form v2 8.2.8 — current investor engagement status (stored in transaction_status) */
    public const ENGAGEMENT_STATUSES = [
        'initial'                => 'Initial discussions',
        'interest'               => 'Investor interest expressed',
        'indicative'             => 'Indicative commitment',
        'loi'                    => 'Letter of Intent',
        'term_sheet_negotiation' => 'Term Sheet under negotiation',
        'term_sheet'             => 'Term Sheet signed / executed',
        'agreement'              => 'Investment Agreement',
        'financial_close'        => 'Financial close',
    ];

    /** Engagement labels, including answers given on the earlier form */
    public const TRANSACTION_STATUSES = self::ENGAGEMENT_STATUSES + ['discussion' => 'In discussion'];

    /** Form v2 8.2.9 */
    public const COMMITMENT = ['yes' => 'Yes', 'no' => 'No', 'indicative' => 'Indicative only'];

    /** Form v2 8.3.6 / 8.3.7 */
    public const VALUATION_BY      = ['internal' => 'Internally developed', 'investor' => 'Investor developed', 'both' => 'Agreed upon by both'];
    public const VALUATION_METHODS = ['income' => 'Income approach', 'market' => 'Market approach', 'asset' => 'Asset-based approach', 'not_sure' => 'Not sure'];

    /** Currency of the investor / transaction amounts (8.2.7, 8.3.3, 8.3.5) => [label, symbol] */
    public const CURRENCIES = ['LSL' => ['Maloti', 'M'], 'ZAR' => ['Rand', 'R'], 'USD' => ['US dollar', 'US$']];

    /** Form v2 8.2.6 — investor country */
    public const COUNTRIES = [
        'Lesotho', 'South Africa', 'Botswana', 'Eswatini', 'Namibia', 'Zimbabwe', 'Mozambique', 'Zambia', 'Malawi', 'Angola', 'Tanzania',
        'Madagascar', 'Mauritius', 'Democratic Republic of the Congo', 'Kenya', 'Uganda', 'Rwanda', 'Ethiopia', 'Ghana', 'Nigeria', 'Egypt', 'Morocco',
        'United Kingdom', 'Ireland', 'Netherlands', 'Germany', 'France', 'Belgium', 'Switzerland', 'Luxembourg', 'Sweden', 'Norway', 'Denmark', 'Italy', 'Spain', 'Portugal',
        'United States', 'Canada', 'United Arab Emirates', 'Saudi Arabia', 'Qatar', 'India', 'China', 'Hong Kong', 'Singapore', 'Japan', 'Australia', 'Brazil', 'Other',
    ];

    /** Form v2 8.4 B — approved expenditure categories: key => [label, example item, what to describe] */
    public const GRANT_CATEGORIES = [
        'productive_assets' => ['Productive assets and equipment', 'e.g. Processing equipment',                'Specify the equipment and explain how it supports the enterprise\'s growth plan.'],
        'technology'        => ['Technology and digital systems',  'e.g. Inventory management system',         'Describe the system, its purpose and expected business benefit.'],
        'working_capital'   => ['Working capital',                 'e.g. Raw materials or inventory',          'Identify the proposed expenditure and explain its link to increased production or sales.'],
        'product_dev'       => ['Product or service development',  'e.g. Product testing or development',      'Describe the activity and the intended improvement to the product or service.'],
        'market_expansion'  => ['Market expansion',                'e.g. Market entry and distribution costs', 'Identify the target market and the expenditure required to access it.'],
        'other'             => ['Other eligible expenditure',      'Specify item / activity',                  'Explain the purpose and why the expenditure is necessary for the proposed growth activity.'],
    ];

    /** Expenditure location of a budget line */
    public const LOCATIONS = ['lesotho' => 'Lesotho', 'outside' => 'Outside Lesotho'];

    /** Form v2 8.4 C2 */
    public const LESOTHO_70 = ['yes' => 'Yes', 'no' => 'No', 'unsure' => 'Unsure'];

    public const BLANK_PLAN_ITEM = ['item' => '', 'description' => '', 'quantity' => '', 'unit_cost' => '', 'lump_sum' => false, 'amount' => '', 'location' => 'lesotho', 'country' => ''];

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
        'has_investor'          => 'boolean',
        'tx_amount_agreed'      => 'boolean',
        'tx_terms_agreed'       => 'boolean',
        'tx_conditions_outstanding' => 'boolean',
        'double_financing'      => 'boolean',
        'grant_plan'            => 'array',
        'lead_investor_amount'  => 'decimal:2',
        'tx_amount'             => 'decimal:2',
        'valuation'             => 'decimal:2',
        'foreign_owned'         => 'boolean',
        'primary_ops_lesotho'   => 'boolean',
        'ownership_dispute'     => 'boolean',
        'key_people'            => 'array',
        'foreign_owned_pct'     => 'decimal:2',
        'business_models'       => 'array',
        'shareholder_list'      => 'array',
        'prior_cafi_support'    => 'array',
        'markets'               => 'array',
        'es_checklist'          => 'array',
        'es_notes'              => 'array',
        'es_confirmed'          => 'boolean',
        'round_investors'       => 'array',
        'investment_history'    => 'array',
        'grant_budget'          => 'array',
        'grant_outside_lesotho' => 'decimal:2',
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
            // Form v2: one lead investor → the investor list / round amount the gates read
            if ($p->isDirty(['has_investor', 'lead_investor_name', 'lead_investor_country', 'lead_investor_amount', 'tx_amount', 'tx_amount_agreed', 'investment_currency'])) {
                if ($p->has_investor === false) {
                    $p->round_investors = [];
                    $p->round_amount    = null;
                } elseif (filled($p->lead_investor_name)) {
                    $amount = $p->leadInvestmentLsl();
                    $p->round_investors = [['name' => trim($p->lead_investor_name), 'id_number' => '', 'country' => (string) $p->lead_investor_country, 'ticket' => $amount]];
                    $p->round_amount    = $amount;
                }
            }

            // 8.3.10 transaction description doubles as "what the money is for" on the gate screens
            if ($p->isDirty('tx_description') && filled($p->tx_description)) {
                $p->investment_purpose = $p->tx_description;
            }

            // 8.4 grant plan (US$ line items) → grant budget in Maloti (grant column only) + amount spent outside Lesotho
            if ($p->isDirty('grant_plan')) {
                $rate  = self::usdRate();
                $plan  = (array) ($p->grant_plan ?? []);
                $rows  = [];
                $out   = 0.0;
                foreach (self::GRANT_CATEGORIES as $key => [$label]) {
                    $items = collect($plan[$key] ?? [])->map(fn ($it) => $it + ['total' => self::planItemTotal($it)])->filter(fn ($it) => filled($it['item'] ?? null) || $it['total'] > 0);
                    if ($items->isEmpty()) {
                        continue;
                    }
                    $out   += $items->where('location', 'outside')->sum('total') * $rate;
                    $rows[] = [
                        'category' => $label,
                        'private'  => null,
                        'grant'    => round($items->sum('total') * $rate, 2),
                        'items'    => $items->map(fn ($it) => ['name' => (string) $it['item'], 'private' => null, 'grant' => round($it['total'] * $rate, 2)])->values()->all(),
                    ];
                }
                $p->grant_budget          = $rows ?: null;
                $p->grant_outside_lesotho = $rows ? round($out, 2) : null;
            }

            if ($p->isDirty('round_investors')) {
                $rows = $p->investorRows();
                $p->investor_name         = $rows ? collect($rows)->pluck('name')->implode(', ') : null;
                $tickets = collect($rows)->pluck('ticket')->filter(fn ($t) => $t !== null && $t !== '');
                $p->investor_contribution = $tickets->isNotEmpty() ? $tickets->sum(fn ($t) => (float) $t) : null;
            }

            // obfc_registered (used across the gates) is true only when registration is complete
            if ($p->isDirty('obfc_status')) {
                $p->obfc_registered = $p->obfc_status === null ? null : $p->obfc_status === 'yes';
            }

            // Older screens read the single business_model column — keep it as the first selected model
            if ($p->isDirty('business_models')) {
                $p->business_model = collect($p->business_models ?? [])->first();
            }

            // The CAFI grant is not entered by the applicant — it follows the grant rule on what the investor puts in
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
        if ($section === 'people' && ($this->es_checklist['records'] ?? null) !== 'yes') {
            $skip[] = 'es_records_system';
        }
        if ($section === 'people' && $this->ownership_dispute !== true) {
            $skip[] = 'ownership_dispute_details';
        }
        if ($section === 'legal' && $this->foreign_registered !== true) {
            $skip[] = 'foreign_registration_no';
        }
        if ($section === 'legal' && $this->foreign_owned !== true) {
            $skip[] = 'foreign_owned_pct';
        }
        if ($section === 'company' && $this->obfc_registered !== true) {
            $skip[] = 'year_registered';
        }
        if ($section === 'company' && $this->sector !== 'Other') {
            $skip[] = 'sector_other';
        }
        if ($section === 'business' && $this->has_investor === false) {
            array_push($skip, 'grant_plan', 'grant_outside_justification', 'grant_lesotho_70', 'double_financing', 'double_financing_details', 'grant_growth_support', ...self::INVESTOR_FIELDS);
        }
        if ($section === 'business' && $this->tx_amount_agreed !== true) {
            $skip[] = 'tx_amount';
        }
        if ($section === 'business' && $this->double_financing !== true) {
            $skip[] = 'double_financing_details';
        }
        if ($section === 'legal' && $this->foreign_registered !== true) {
            $skip[] = 'foreign_country';
        }
        if ($section === 'business' && $this->investorAmount() === null) {
            array_push($skip, 'grant_plan', 'grant_outside_justification', 'grant_lesotho_70');   // filled in after the investor amount is known
        }
        if ($section === 'business' && ! $this->spendsOutsideLesotho()) {
            $skip[] = 'grant_outside_justification';
        }

        return array_values(array_diff($fields, $skip));
    }

    /** Whether a single field has an acceptable answer */
    public function isAnswered(string $field): bool
    {
        $v = $this->{$field};

        return match ($field) {
            'declaration_agreed' => $v === true,
            'es_checklist'       => collect(self::ES_CHECKLIST)->keys()->every(fn ($k) => isset(self::ES_ANSWERS[$v[$k] ?? ''])),
            'es_notes'           => collect(self::ES_CHECKLIST)->keys()
                                        ->every(fn ($k) => ! (self::esFollowUp($k, $this->es_checklist[$k] ?? null)[1] ?? false) || filled($v[$k] ?? null)),
            'es_confirmed'       => $v === true,
            'round_investors'    => collect($this->investorRows())->contains(fn ($r) => (float) ($r['ticket'] ?? 0) > 0),
            'shareholder_list'   => $this->shareholderRows() !== [] || (bool) $this->document('shareholders_list'),
            'business_models'    => $this->businessModels() !== [],
            'has_investor'       => $v !== null,
            'key_people'         => $this->keyPeopleRows() !== [],
            'employees'          => $v !== null && ($this->employeeBreakdownTotal() === null || (int) $v === $this->employeeBreakdownTotal()),
            'grant_budget'       => $this->budgetTotals()['private'] + $this->budgetTotals()['grant'] > 0,
            'grant_plan'         => $this->planTotalUsd() > 0,
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
        $planOrDeck = ! collect(EnterpriseDocument::PLAN_OR_DECK)->contains(fn ($type) => (bool) $this->document($type));

        return array_keys(array_filter(EnterpriseDocument::TYPES, fn ($t) => match ($t[3]) {
            true             => true,
            'shareholders'   => $this->shareholderRows() === [],     // the table can replace the upload
            'plan_or_deck'   => $planOrDeck,                         // one of the two is enough
            'registered'     => $this->obfc_registered === true,
            'not_registered' => $this->obfc_registered === false,
            'foreign'        => $this->foreign_registered === true,
            'investor'       => $this->has_investor === true,   // bank confirmation letter
            default          => false,
        }));
    }

    /** Required document types not uploaded yet */
    public function missingDocuments(): array
    {
        $missing = array_values(array_filter($this->requiredDocumentTypes(), fn ($t) => ! $this->document($t)));

        // Business plan / pitch deck: list once ("Business plan or pitch deck")
        $deck = array_values(array_diff(EnterpriseDocument::PLAN_OR_DECK, [EnterpriseDocument::PLAN_OR_DECK[0]]));

        return array_values(array_diff($missing, $deck));
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

    /* ---------------- CAFI grant rule (investment model) ----------------
     | Grant = 50% of the first US$ 300,000 of the investment + 33% of the investment above US$ 300,000,
     | never more than US$ 350,000 for one transaction (config/cif.php co_investment.grant_tiers / max_grant_usd).
     | The enterprise's lifetime total is limited separately (cumulative_cap_usd, checked at Gate 4).
     */

    /** [['up_to_usd' => ?float, 'rate' => float], ...] in order */
    public static function grantTiers(): array
    {
        return (array) config('cif.co_investment.grant_tiers', [
            ['up_to_usd' => 300000, 'rate' => 0.50],
            ['up_to_usd' => null,   'rate' => 0.33],
        ]);
    }

    /** Most grant for one transaction, US$ */
    public static function maxGrantUsd(): float
    {
        return (float) config('cif.co_investment.max_grant_usd', 350000);
    }

    public static function usdRateStatic(): float
    {
        return max((float) config('cif.usd_to_lsl', 18.00), 0.0001);
    }

    /** CAFI grant (US$) for an investor amount (US$): tiered, capped */
    public static function matchingGrantUsd(?float $investorUsd): ?float
    {
        if ($investorUsd === null || $investorUsd <= 0) {
            return null;
        }

        $grant = 0.0;
        $floor = 0.0;
        foreach (self::grantTiers() as $tier) {
            $top    = $tier['up_to_usd'] ?? null;
            $inTier = $top === null ? max(0, $investorUsd - $floor) : max(0, min($investorUsd, (float) $top) - $floor);
            $grant += $inTier * (float) $tier['rate'];
            if ($top === null || $investorUsd <= (float) $top) {
                break;
            }
            $floor = (float) $top;
        }

        return round(min($grant, self::maxGrantUsd()), 2);
    }

    /** Investor amount (US$) needed for a given grant (US$) — the rule worked backwards (null if above the maximum) */
    public static function investorNeededUsd(float $grantUsd): ?float
    {
        if ($grantUsd <= 0) {
            return 0.0;
        }
        if ($grantUsd > self::maxGrantUsd() + 0.005) {
            return null;
        }

        $floor = 0.0;
        $base  = 0.0;   // grant earned below this tier
        foreach (self::grantTiers() as $tier) {
            $top  = $tier['up_to_usd'] ?? null;
            $rate = max((float) $tier['rate'], 0.0001);
            $tierGrant = $top === null ? INF : ((float) $top - $floor) * $rate;
            if ($grantUsd <= $base + $tierGrant + 0.005) {
                return round($floor + ($grantUsd - $base) / $rate, 2);
            }
            $base += $tierGrant;
            $floor = (float) $top;
        }

        return null;
    }

    /** Effective grant as a share of the investment, e.g. 48 (%) */
    public static function effectiveGrantPct(?float $investorLsl): ?float
    {
        $grant = self::matchingGrant($investorLsl);

        return $grant !== null && $investorLsl > 0 ? round($grant / $investorLsl * 100, 1) : null;
    }

    /** First-tier rate (0.5). Kept for older code — the grant itself always comes from matchingGrant(). */
    public static function grantRatio(): float
    {
        return (float) (self::grantTiers()[0]['rate'] ?? 0.5);
    }

    /** "50% of the first US$ 300,000 + 33% above, up to US$ 350,000" */
    public static function grantRatioLabel(): string
    {
        $pct   = fn ($r) => rtrim(rtrim(number_format($r * 100, 1), '0'), '.').'%';
        $tiers = self::grantTiers();
        $parts = [];
        foreach ($tiers as $i => $t) {
            $parts[] = $i === 0
                ? $pct($t['rate']).($t['up_to_usd'] ? ' of the first US$ '.number_format($t['up_to_usd']) : '')
                : $pct($t['rate']).' above'.($t['up_to_usd'] ? ' (to US$ '.number_format($t['up_to_usd']).')' : '');
        }

        return implode(' + ', $parts).', up to US$ '.number_format(self::maxGrantUsd());
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

    /** CAFI grant (Maloti) for a given investor amount (Maloti) — the tiered rule, worked out in US$ */
    public static function matchingGrant(?float $investorLsl): ?float
    {
        if ($investorLsl === null || $investorLsl <= 0) {
            return null;
        }
        $rate = self::usdRateStatic();

        return round(self::matchingGrantUsd($investorLsl / $rate) * $rate, 2);
    }

    /** Investor amount (Maloti) needed to support a grant (Maloti) */
    public static function investorNeededFor(float $grantLsl): ?float
    {
        $rate = self::usdRateStatic();
        $usd  = self::investorNeededUsd($grantLsl / $rate);

        return $usd === null ? null : round($usd * $rate, 2);
    }

    /** Whether an investor amount (Maloti) reaches the minimum */
    public static function meetsInvestorMinimum(?float $investorLsl): bool
    {
        return $investorLsl !== null && $investorLsl + 0.005 >= self::minInvestorLsl();
    }

    /** What the investor is putting in (Maloti): the lead investor (form v2), otherwise the earlier investor list / round */
    public function investorAmount(): ?float
    {
        if ($this->has_investor === false) {
            return null;
        }

        $amount = $this->leadInvestmentLsl() ?? ((float) $this->investor_contribution ?: (float) $this->round_amount);

        return $amount > 0 ? $amount : null;
    }

    /* ---------------- Form v2 8.2 / 8.3 — lead investor and transaction ---------------- */

    /** Investor fields, skipped when there is no investor */
    public const INVESTOR_FIELDS = [
        'lead_investor_name', 'lead_investor_contact_name', 'lead_investor_email', 'lead_investor_phone', 'lead_investor_website',
        'investor_type', 'lead_investor_country', 'investment_currency', 'lead_investor_amount', 'transaction_status', 'investor_commitment',
        'participation', 'tx_amount_agreed', 'tx_amount', 'tx_terms_agreed', 'valuation', 'valuation_by', 'valuation_method',
        'expected_close', 'tx_conditions_outstanding', 'tx_description',
    ];

    public static function usdRate(): float
    {
        return max((float) config('cif.usd_to_lsl', 18.00), 0.0001);
    }

    /** An amount in the given currency → Maloti (Rand is at par with the Maloti) */
    public static function toLsl(?float $amount, ?string $currency): ?float
    {
        if ($amount === null) {
            return null;
        }

        return round(strtoupper((string) $currency) === 'USD' ? $amount * self::usdRate() : $amount, 2);
    }

    /** "M 1,000" / "US$ 1,000" */
    public static function money(?float $amount, ?string $currency = 'LSL'): string
    {
        return $amount === null ? '—' : (self::CURRENCIES[strtoupper((string) $currency)][1] ?? 'M').' '.number_format($amount);
    }

    /** The eligible private investment (8.3.3 once agreed, otherwise 8.2.7), in Maloti */
    public function leadInvestmentLsl(): ?float
    {
        $amount = $this->tx_amount_agreed === true && $this->tx_amount !== null ? (float) $this->tx_amount
            : ($this->lead_investor_amount !== null ? (float) $this->lead_investor_amount : null);

        return $amount !== null && $amount > 0 ? self::toLsl($amount, $this->investment_currency ?: 'LSL') : null;
    }

    /* ---------------- Form v2 8.4 — grant plan (US$) ---------------- */

    /** Total of one budget line in US$: quantity × unit cost, or the lump sum */
    public static function planItemTotal(array $it): float
    {
        if (! empty($it['lump_sum'])) {
            return is_numeric($it['amount'] ?? null) ? max(0, (float) $it['amount']) : 0.0;
        }

        return is_numeric($it['quantity'] ?? null) && is_numeric($it['unit_cost'] ?? null) ? max(0, (float) $it['quantity'] * (float) $it['unit_cost']) : 0.0;
    }

    /** ['total' => US$, 'outside' => US$, 'categories' => key => US$] for a plan array */
    public static function planTotals(?array $plan): array
    {
        $cats = [];
        $out  = 0.0;
        foreach (self::GRANT_CATEGORIES as $key => $c) {
            $items = collect($plan[$key] ?? []);
            $cats[$key] = (float) $items->sum(fn ($it) => self::planItemTotal((array) $it));
            $out += (float) $items->filter(fn ($it) => ($it['location'] ?? 'lesotho') === 'outside')->sum(fn ($it) => self::planItemTotal((array) $it));
        }

        return ['total' => array_sum($cats), 'outside' => $out, 'categories' => $cats];
    }

    public function planTotalUsd(): float
    {
        return self::planTotals((array) ($this->grant_plan ?? []))['total'];
    }

    /** Any grant budget line spent outside Lesotho */
    public function spendsOutsideLesotho(): bool
    {
        return self::planTotals((array) ($this->grant_plan ?? []))['outside'] > 0 || (float) $this->grant_outside_lesotho > 0;
    }

    /** CAFI grants already approved for this enterprise (all its applications), in Maloti */
    public function previousGrantsLsl(): float
    {
        if (! $this->user_id) {
            return 0.0;
        }
        $eoiIds = ExpressionOfInterest::where('user_id', $this->user_id)->pluck('id');

        return (float) Authorisation::whereIn('expression_of_interest_id', $eoiIds)->where('outcome', Authorisation::OUTCOME_AUTHORISED)->sum('grant_amount');
    }

    /** Lifetime cap (US$ 350,000) less grants already approved, in Maloti */
    public function availableCumulativeLsl(): float
    {
        return max(0, GrantReadiness::cumulativeCapLsl() - $this->previousGrantsLsl());
    }

    /* ---------------- Grant utilisation budget ---------------- */

    /** Approved expenditure categories (form v2 8.4 B) */
    public const BUDGET_CATEGORIES = ['Productive assets and equipment', 'Technology and digital systems', 'Working capital', 'Product or service development', 'Market expansion', 'Other eligible expenditure'];

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

    /* ---------------- Bank account for the grant — entered by the applicant at Gate 4 (Grant readiness) ---------------- */

    public const BANK_FIELDS = ['bank_name', 'bank_branch', 'bank_account_name', 'bank_account_number', 'bank_account_type'];

    public const ACCOUNT_TYPES = GrantReadiness::ACCOUNT_TYPES;

    /** Bank details in the Gate 4 shape: bank, branch, account_name, account_number, account_type */
    public function bankDetails(): array
    {
        return [
            'bank'           => (string) $this->bank_name,
            'branch'         => (string) $this->bank_branch,
            'account_name'   => (string) $this->bank_account_name,
            'account_number' => (string) $this->bank_account_number,
            'account_type'   => $this->bank_account_type,
        ];
    }

    public function hasBankDetails(): bool
    {
        return filled($this->bank_name) && filled($this->bank_account_name) && filled($this->bank_account_number);
    }

    /* ---------------- Spending in Lesotho (at least 70% of the grant) ---------------- */

    public static function minLesothoPct(): float
    {
        return (float) config('cif.co_investment.min_lesotho_pct', 70);
    }

    /** % of the grant spent in Lesotho, from the grant total and the amount spent outside (null = unknown) */
    public static function lesothoShare(?float $grant, ?float $outside): ?float
    {
        if ($outside === null || ! $grant || $grant <= 0) {
            return null;
        }

        return round(max(0, min(100, ($grant - $outside) / $grant * 100)), 2);
    }

    /** The grant the share is worked out on: the budget's grant column, otherwise the matching grant */
    public function grantForLesothoShare(): ?float
    {
        $budget = $this->budgetTotals()['grant'];

        return $budget > 0 ? $budget : ($this->grant_requested !== null ? (float) $this->grant_requested : null);
    }

    /** % of the grant spent in Lesotho (null until the enterprise has answered) */
    public function lesothoSharePct(): ?float
    {
        return self::lesothoShare($this->grantForLesothoShare(), $this->grant_outside_lesotho !== null ? (float) $this->grant_outside_lesotho : null);
    }

    /** Less than the minimum spent in Lesotho → the enterprise must justify it */
    public function needsOutsideJustification(): bool
    {
        $pct = $this->lesothoSharePct();

        return $pct !== null && $pct < self::minLesothoPct();
    }

    /** Whether the proposed grant utilisation is within the indicative grant (to the nearest Maloti) */
    public function budgetMatchesGrant(): bool
    {
        return $this->grant_requested !== null && $this->budgetTotals()['grant'] <= (float) $this->grant_requested + 1;
    }

    /** "0-10" or "Over 10" — the applicant's answer */
    public function employeeBand(): ?string
    {
        if ($this->employees !== null) {
            return self::EMPLOYEE_BANDS[(int) $this->employees > 10 ? 'over_10' : '0_10'];
        }

        return self::EMPLOYEE_BANDS[$this->employee_band ?? ''] ?? null;
    }

    /** Selected business models (falls back to the old single answer) */
    public function businessModels(): array
    {
        $models = (array) ($this->business_models ?? []);

        return $models !== [] ? array_values($models) : array_values(array_filter([$this->business_model]));
    }

    /** "Business to Business, Business to Consumers" */
    public function businessModelLabel(): ?string
    {
        return $this->labels(self::BUSINESS_MODELS, $this->businessModels());
    }

    /** "Services" or "Other — Renewable energy" */
    public function sectorLabel(): ?string
    {
        if ($this->sector === 'Other' && filled($this->sector_other)) {
            return 'Other — '.$this->sector_other;
        }

        return $this->sector;
    }

    /** Directors & key management rows that have a name */
    public function keyPeopleRows(): array
    {
        return array_values(array_filter((array) ($this->key_people ?? []), fn ($r) => filled($r['name'] ?? null)));
    }

    /** Months the business has been operating (from month + year commenced), or null */
    public function monthsOperating(): ?int
    {
        if (! $this->year_established) {
            return null;
        }

        $start = \Illuminate\Support\Carbon::create((int) $this->year_established, (int) ($this->month_commenced ?: 1), 1);

        return $start->isFuture() ? 0 : (int) $start->diffInMonths(now());
    }

    /** CAFI form 1.3: flag enterprises operating for less than 6 months */
    public function operatingUnderSixMonths(): bool
    {
        $m = $this->monthsOperating();

        return $m !== null && $m < 6;
    }

    /** "May 2021" */
    public function commencedLabel(): ?string
    {
        return $this->year_established
            ? trim(($this->month_commenced ? self::MONTHS[(int) $this->month_commenced] ?? '' : '').' '.$this->year_established)
            : null;
    }

    /** Shareholder rows that have at least a name */
    public function shareholderRows(): array
    {
        return array_values(array_filter((array) ($this->shareholder_list ?? []), fn ($r) => filled($r['name'] ?? null)));
    }

    /** Total % of shares in the table */
    public function shareholderTotal(): float
    {
        return (float) collect($this->shareholderRows())->sum(fn ($r) => is_numeric($r['percent'] ?? null) ? (float) $r['percent'] : 0);
    }


    /** Sum of the full-time / part-time breakdown */
    public function employeeBreakdownTotal(): ?int
    {
        $parts = [$this->ft_male, $this->ft_female, $this->pt_male, $this->pt_female];

        return collect($parts)->every(fn ($v) => $v === null) ? null : (int) collect($parts)->sum();
    }

    /**
     * Follow-up question for an answer: [prompt, required] or null.
     *   Q2/Q3 "Yes" → measures · Q4/7/9/12/13 "Yes" → explain · Q1/5/6/8/10/11 "No" → gap · "Do not know" / "N/A" → optional note
     */
    public static function esFollowUp(string $key, ?string $answer): ?array
    {
        return match (true) {
            $answer === 'yes' && in_array($key, self::ES_MEASURES_IF_YES, true) => ['What measures do you use to manage these risks?', true],
            $answer === 'yes' && in_array($key, self::ES_EXPLAIN_IF_YES, true)  => ['Please briefly explain.', true],
            $answer === 'no' && in_array($key, self::ES_GAP_IF_NO, true)        => ['Please briefly explain the gap or support needed.', true],
            in_array($answer, ['dont_know', 'na'], true)                         => ['Short explanation (optional)', false],
            default                                                              => null,
        };
    }

    /**
     * Screening treatment of the ESG answers (CAFI application form):
     *   disqualify — "Yes" to Q7 (under 18) or Q13 (exclusion list)
     *   dd         — "Yes" to Q4, Q9 or Q12 → targeted due diligence
     *   review     — "Yes" to Q2 or Q3 → review the stated controls (a hazard alone is not a negative finding)
     *   clarify    — "No" to Q1/5/6/8/10/11, "Do not know", or "Not applicable" without an explanation
     * Returns [[level, key, message], …]; empty = "No issue disclosed" (not "ESG compliant").
     */
    public function esScreening(): array
    {
        $answers = (array) ($this->es_checklist ?? []);
        $notes   = (array) ($this->es_notes ?? []);
        $flags   = [];

        foreach (self::ES_CHECKLIST as $k => [$area, $q]) {
            $a  = $answers[$k] ?? null;
            $no = array_search($k, array_keys(self::ES_CHECKLIST), true) + 1;

            if ($a === 'yes' && in_array($k, self::ES_DISQUALIFY_IF_YES, true)) {
                $flags[] = ['disqualify', $k, "Q{$no}: ".($k === 'under_18' ? 'work by someone under 18' : 'activity on the exclusion list').' — immediate disqualification.'];
            } elseif ($a === 'yes' && in_array($k, self::ES_EXPLAIN_IF_YES, true)) {
                $flags[] = ['dd', $k, "Q{$no}: route for targeted due diligence."];
            } elseif ($a === 'yes' && in_array($k, self::ES_MEASURES_IF_YES, true)) {
                $flags[] = ['review', $k, "Q{$no}: review the stated controls."];
            } elseif ($a === 'no' && in_array($k, self::ES_GAP_IF_NO, true)) {
                $flags[] = ['clarify', $k, "Q{$no}: clarification or an improvement measure needed."];
            } elseif ($a === 'dont_know') {
                $flags[] = ['clarify', $k, "Q{$no}: answered \"Do not know\" — clarify."];
            } elseif ($a === 'na' && blank($notes[$k] ?? null)) {
                $flags[] = ['clarify', $k, "Q{$no}: \"Not applicable\" without an explanation — clarify."];
            }
        }

        return $flags;
    }

    /** Whether any answer means immediate disqualification */
    public function esDisqualified(): bool
    {
        return collect($this->esScreening())->contains(fn ($f) => $f[0] === 'disqualify');
    }

    /** Kept for older screens: keys of answers that need attention */
    public function esGaps(): array
    {
        return collect($this->esScreening())->pluck(1)->unique()->values()->all();
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