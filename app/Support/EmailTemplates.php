<?php

namespace App\Support;

/**
 * Compose Email · ready-made messages. Add a template by adding an entry here.
 *   key => [title, hint, subject, body (HTML)]
 * {name} {business} {reference} {status} {link} are filled in for each recipient;
 * anything else in [square brackets] is for the sender to fill in before sending.
 */
class EmailTemplates
{
    protected const SIGN = '<p>Kind regards,<br>LEHSFF Co-Investment Facility</p>';

    public static function all(): array
    {
        return [
            'documents' => [
                'Request outstanding documents', 'Ask an applicant for missing documents.',
                'Documents needed for your application {reference}',
                '<p>Dear {name},</p><p>Thank you for your application for <strong>{business}</strong> ({reference}). To continue the assessment we still need the following documents:</p><ul><li>[document]</li><li>[document]</li></ul><p>Please upload them on your application page: {link}</p>'.self::SIGN,
            ],
            'meeting' => [
                'Committee meeting notice', 'Date, venue and the pack for the Investment Committee.',
                'Investment Committee meeting — [date]',
                '<p>Dear {name},</p><p>The next Investment Committee meeting will take place on <strong>[date]</strong> at <strong>[venue]</strong>.</p><p>The information pack for the transactions to be reviewed is available on the platform. Please declare any conflict of interest before the meeting.</p>'.self::SIGN,
            ],
            'reminder' => [
                'Reporting reminder', 'Remind enterprises that a progress report is due.',
                'Reminder: progress report due for {reference}',
                '<p>Dear {name},</p><p>This is a reminder that the progress report for <strong>{business}</strong> ({reference}) is due by <strong>[date]</strong>.</p><p>You can submit it on the platform: {link}</p>'.self::SIGN,
            ],
            'status' => [
                'Application status update', 'Tell applicants where their application stands.',
                'Update on your application {reference}',
                '<p>Dear {name},</p><p>Your application for <strong>{business}</strong> ({reference}) is currently at the stage: <strong>{status}</strong>.</p><p>[Add any next steps here.]</p><p>You can follow its progress on the platform: {link}</p>'.self::SIGN,
            ],
            'call' => [
                'New call for applications', 'Announce a call to all applicants.',
                'New call for applications — LEHSFF Co-Investment Facility',
                '<p>Dear {name},</p><p>The LEHSFF Co-Investment Facility has opened a new call for applications.</p><p>Read the requirements and apply on the platform before <strong>[closing date]</strong>.</p>'.self::SIGN,
            ],
        ];
    }

    public static function find(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }
}