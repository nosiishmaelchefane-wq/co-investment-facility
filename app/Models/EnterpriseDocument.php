<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class EnterpriseDocument extends Model
{
    /** Files are private: stored on the local disk, downloaded through a controller. */
    public const DISK = 'local';

    /** Upload limit in KB (archives of IDs / CVs can be large) */
    public const MAX_KB = 20480;

    /**
     * type => [label, hint, profile section, when required]
     *   when required: true | false | 'registered' (OBFC-registered) | 'not_registered' | 'foreign' (registered in another country)
     * One current file per type. Items follow the CAFI Co-Investment Facility Application Form.
     */
    public const TYPES = [
        // 1 · Company profile
        'logo'                     => ['Company logo',                                  'Optional — JPG or PNG',                                                                 'company',     false],
        'shareholders_list'        => ['List of shareholders',                          'Each shareholder with ID / registration number, nationality, gender, age and % shares held', 'company', true],
        // 2 · Legal status
        'registration_cert'        => ['Company registration (OBFC)',                   'Issued by the Government of Lesotho, showing the shareholders and their shareholding', 'legal',       'registered'],
        'registration_undertaking' => ['Signed undertaking to register',                'A signed statement that you are willing and able to complete your registration',       'legal',       'not_registered'],
        'foreign_registration'     => ['Registration in the other country',             'Copy of the registration certificate from the other country',                          'legal',       'foreign'],
        // 5 · Employees
        'employee_list'            => ['Detailed list of employees',                    'Optional — name, full/part time, position, age and gender for each employee',          'people',      false],
        // 6 · Key individuals
        'key_individuals'          => ['Key individuals profile sheet',                 'Owners, board directors, executives and managers',                                      'people',      true],
        'ids'                      => ['IDs of directors, owners & key individuals',    'All IDs in one zipped file',                                                            'people',      true],
        // 9 · Attachments
        'financials'               => ['Financial statements',                          'Up to two years for established firms; at least three months for early-stage firms',   'attachments', true],
        'projections'              => ['Two-year financial projections',                'Spreadsheet or PDF',                                                                    'attachments', true],
        'commitment'               => ['Term sheet from your investor(s)',              'Signed term sheet (or LOI / investment agreement)',                                     'attachments', true],
        'team_resumes'             => ['Management team CVs & organisation chart',      'All files compressed in one archive',                                                   'attachments', true],
        'business_plan'            => ['Business plan or investor deck',                'PDF or presentation',                                                                   'attachments', true],
        'cap_table'                => ['Cap table',                                     "The company's capitalisation table",                                                    'attachments', true],
        'budget'                   => ['Operational budget incl. use of the grant',     'Use the budget template — full operational budget in LSL, including the grant',        'attachments', true],
        'tax_clearance'            => ['Tax clearance certificate',                     'Optional — from Revenue Services Lesotho',                                              'attachments', false],
        'other'                    => ['Other supporting document',                     'Optional — anything else that helps',                                                   'attachments', false],
    ];

    /** Accepted file extensions per type (default: PDF and images) */
    public const MIMES = [
        'logo'            => 'jpg,jpeg,png,webp',
        'shareholders_list' => 'pdf,xlsx,xls,csv,docx,doc',
        'employee_list'   => 'pdf,xlsx,xls,csv,docx,doc',
        'key_individuals' => 'pdf,xlsx,xls,docx,doc',
        'ids'             => 'zip,pdf',
        'projections'     => 'pdf,xlsx,xls,csv',
        'team_resumes'    => 'zip,pdf',
        'business_plan'   => 'pdf,pptx,ppt,docx,doc',
        'cap_table'       => 'pdf,xlsx,xls,csv',
        'budget'          => 'pdf,xlsx,xls,csv',
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
        static::deleting(fn (EnterpriseDocument $doc) => Storage::disk(self::DISK)->delete($doc->path));
    }
}