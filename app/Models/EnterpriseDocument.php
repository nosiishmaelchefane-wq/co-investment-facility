<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class EnterpriseDocument extends Model
{
    /** Files are private: stored on the local disk, downloaded through a controller. */
    public const DISK = 'local';

    /** Upload limit in KB — 3 MB per attachment */
    public const MAX_KB = 3072;

    public const SIZE_NOTE = 'Each attachment can be at most 3 MB. Compress large PDFs or scans before uploading.';

    /**
     * type => [label, hint, profile section, when required]
     *   when required: true | false
     *     'registered'     — company is OBFC-registered
     *     'not_registered' — company is not OBFC-registered
     *     'foreign'        — company is registered in another country
     *     'shareholders'   — only when the shareholders table on the profile is empty
     *     'plan_or_deck'   — business plan OR pitch deck: one of the two is required
     * One current file per type. Items follow the CAFI Co-Investment Facility Application Form.
     */
    public const TYPES = [
        // 1 · Company profile
        'logo'                     => ['Company logo',                                  'Optional — JPG or PNG',                                                                 'company',     false],
        'shareholders_list'        => ['List of shareholders',                          'Only if you did not fill in the shareholders table above',                              'company',     'shareholders'],
        // 2 · Legal status
        'registration_cert'        => ['Company registration (OBFC)',                   'Issued by the Government of Lesotho, showing the shareholders and their shareholding', 'legal',       'registered'],
        'registration_undertaking' => ['Signed undertaking to register',                'A signed statement that you are willing and able to complete your registration',       'legal',       'not_registered'],
        'foreign_registration'     => ['Registration in the other country',             'Copy of the registration certificate from the other country',                          'legal',       'foreign'],
        'good_standing'            => ['Letters / certificates of good standing',      'Optional now — can be submitted at the due diligence stage',                           'legal',       false],
        // 5 · Employees
        'employee_list'            => ['List of current employees',                     'Name, full-time / part-time, position, age and gender for each employee',              'people',      true],
        'ids'                      => ['IDs of shareholders & key people',              'All IDs in one zipped file',                                                            'people',      true],
        // 9 · Attachments
        'business_extract'         => ['Business extract',                              'Current company extract from the OBFC',                                                 'attachments', 'registered'],
        'business_plan'            => ['Business plan',                                 'Upload a business plan or a pitch deck — one of the two is required',                   'attachments', 'plan_or_deck'],
        'pitch_deck'               => ['Pitch deck',                                    'Upload a pitch deck or a business plan — one of the two is required',                   'attachments', 'plan_or_deck'],
        'financials'               => ['Financial statements',                          'Up to two years for established firms; at least three months for early-stage firms',   'attachments', true],
        'projections'              => ['Two-year financial projections',                'Spreadsheet or PDF',                                                                    'attachments', true],
        'commitment'               => ['Term sheet from your investor(s)',              'Signed term sheet (or LOI / investment agreement)',                                     'attachments', true],
        'team_resumes'             => ['Management team CVs & organisation chart',      'All files compressed in one archive',                                                   'attachments', true],
        'cap_table'                => ['Cap table',                                     "The company's capitalisation table",                                                    'attachments', true],
        'budget'                   => ['Operational budget incl. use of the grant',     'Use the budget template — full operational budget in LSL, including the grant',        'attachments', true],
        'bank_confirmation'        => ['Bank confirmation letter',                      'A letter or stamped statement from your bank showing the account name and number',     'business',    'investor'],
        'tax_clearance'            => ['Tax clearance certificate',                     'Optional — from Revenue Services Lesotho',                                              'attachments', false],
        'other'                    => ['Other supporting document',                     'Optional — anything else that helps',                                                   'attachments', false],
    ];

    /** Business plan / pitch deck — one of these is required (the first is the one listed as missing) */
    public const PLAN_OR_DECK = ['business_plan', 'pitch_deck'];

    /** Hover help (ⓘ) for each document: what it is, in plain language */
    public const HELP = [
        'logo'                     => 'Your company logo, used on your profile. Optional.',
        'shareholders_list'        => 'A document listing every shareholder with their ID / passport number (or company registration number), nationality, gender, age and % of shares held. Not needed if you filled in the shareholders table.',
        'registration_cert'        => 'The certificate of incorporation or registration issued by the One-Stop Business Facilitation Centre (OBFC), Ministry of Trade, Industry and Business Development.',
        'registration_undertaking' => 'A short letter, signed by the owner or a director, confirming that the company is willing and able to register with the OBFC. No grant can be paid until you are registered.',
        'foreign_registration'     => 'The registration certificate from the other country where the company is registered (e.g. CIPC certificate in South Africa).',
        'employee_list'            => 'A table of your current employees: name, full-time or part-time, position, age and gender. Reviewers use it to check the employee numbers.',
        'good_standing'            => 'Letters or certificates confirming that your shareholders are in good standing (e.g. from their bank or a professional body). You can upload them now or at the due diligence stage.',
        'ids'                      => 'Copies of the national ID or passport of every shareholder and of the key people running the company, zipped into one file.',
        'business_extract'         => 'An up-to-date company extract from the OBFC. It shows the company\'s current registered details, directors and shareholders — reviewers use it to confirm who owns and runs the company. You can request it from the OBFC.',
        'business_plan'            => 'A written plan explaining the business: what you sell, your market and customers, how you will grow, your team and your financial plan. Upload this OR a pitch deck.',
        'pitch_deck'               => 'A short presentation (usually 10–20 slides) of the kind you show investors: the problem, your solution, market, business model, traction, team, financials and what the investment is for. Upload this OR a business plan.',
        'financials'               => 'Your income statement (profit & loss), balance sheet and, if available, cash-flow statement. Established firms: the last two years. Early-stage firms: at least the last three months (management accounts are fine).',
        'projections'              => 'Your expected income, costs and cash flow for the next two years, showing the effect of the investment and the CAFI grant.',
        'commitment'               => 'A document from your investor showing they are committed: a signed term sheet, a letter of intent (LOI) or the investment agreement. It should state the amount and the terms.',
        'team_resumes'             => 'CVs of the people running the company and a simple organisation chart showing who reports to whom, compressed into one archive (.zip).',
        'cap_table'                => 'A capitalisation ("cap") table is a simple table showing who owns the company: each shareholder, how many shares they hold and their % ownership — before and after the new investment. A spreadsheet is fine.',
        'budget'                   => 'Your full operational budget in Maloti, showing how the investor\'s money and the CAFI grant will be spent. Please use the budget template.',
        'bank_confirmation'        => 'A letter from your bank (or a recent bank statement with the bank stamp) showing the account holder\'s name and the account number. The LEHSFF team checks your bank details against it before the grant is paid.',
        'tax_clearance'            => 'A tax clearance certificate from the Revenue Services Lesotho (RSL) showing your tax affairs are in order. Optional at this stage.',
        'other'                    => 'Anything else that supports your application, e.g. contracts with key customers, awards, licences or product certifications.',
    ];

    /** Accepted file extensions per type (default: PDF and images) */
    public const MIMES = [
        'logo'              => 'jpg,jpeg,png,webp',
        'shareholders_list' => 'pdf,xlsx,xls,csv,docx,doc',
        'employee_list'     => 'pdf,xlsx,xls,csv,docx,doc',
        'good_standing'     => 'pdf,jpg,jpeg,png,zip',
        'ids'               => 'zip,pdf',
        'projections'       => 'pdf,xlsx,xls,csv',
        'team_resumes'      => 'zip,pdf',
        'business_plan'     => 'pdf,docx,doc',
        'pitch_deck'        => 'pdf,pptx,ppt',
        'cap_table'         => 'pdf,xlsx,xls,csv',
        'budget'            => 'pdf,xlsx,xls,csv',
    ];

    public const LANGUAGE_NOTE = 'All attachments should be in English; other languages will not be accepted.';

    protected $fillable = [
        'enterprise_profile_id', 'type', 'path', 'original_name', 'mime', 'size', 'document_date', 'uploaded_by',
    ];

    protected $casts = [
        'document_date' => 'date',
        'size'          => 'integer',
    ];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(EnterpriseProfile::class, 'enterprise_profile_id');
    }

    /** Types shown in a profile section */
    public static function forSection(string $section): array
    {
        return array_filter(self::TYPES, fn ($t) => $t[2] === $section);
    }

    public static function mimesFor(string $type): string
    {
        return self::MIMES[$type] ?? 'pdf,jpg,jpeg,png';
    }

    /** ".pdf,.xlsx" for the file input's accept attribute */
    public static function acceptFor(string $type): string
    {
        return collect(explode(',', self::mimesFor($type)))->map(fn ($e) => '.'.$e)->implode(',');
    }

    /** Label used in "still needed" lists (business plan / pitch deck is listed once) */
    public static function missingLabel(string $type): string
    {
        return in_array($type, self::PLAN_OR_DECK, true) ? 'Business plan or pitch deck' : (self::TYPES[$type][0] ?? $type);
    }

    public function getLabelAttribute(): string
    {
        return self::TYPES[$this->type][0] ?? $this->original_name;
    }

    public function getSizeLabelAttribute(): string
    {
        return $this->size >= 1048576 ? round($this->size / 1048576, 1).' MB' : max(1, round($this->size / 1024)).' KB';
    }

    /** Remove the file from disk when the record is deleted. */
    protected static function booted(): void
    {
        // A plain closure: Storage::delete() returns false for a missing file, and returning false CANCELS the delete
        static::deleting(function (EnterpriseDocument $doc) {
            if ($doc->path) {
                Storage::disk(self::DISK)->delete($doc->path);
            }
        });
    }
}