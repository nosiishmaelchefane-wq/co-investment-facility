<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/** Compose Email · one e-mail written on /compose-email (a draft, or what was sent and to whom) */
class SentEmail extends Model
{
    public const DISK = 'local';

    /** status => [label, badge class, icon] */
    public const STATUSES = [
        'draft'   => ['Draft',          'status-draft',     'bi-pencil'],
        'sent'    => ['Sent',           'status-approved',  'bi-check2-circle'],
        'partial' => ['Partly sent',    'status-referred',  'bi-exclamation-triangle'],
        'failed'  => ['Not sent',       'status-rejected',  'bi-x-circle'],
    ];

    protected $fillable = [
        'sender_id', 'expression_of_interest_id', 'subject', 'body', 'recipients_spec', 'recipient_list', 'attachments',
        'recipient_count', 'sent_count', 'failed_count', 'status', 'sent_at',
    ];

    protected $casts = [
        'recipients_spec' => 'array',
        'recipient_list'  => 'array',
        'attachments'     => 'array',
        'sent_at'         => 'datetime',
    ];

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function expressionOfInterest(): BelongsTo
    {
        return $this->belongsTo(ExpressionOfInterest::class);
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    /** Remove stored attachments with a deleted draft (sent e-mails keep theirs) */
    protected static function booted(): void
    {
        static::deleting(function (SentEmail $e) {
            foreach ((array) $e->attachments as $a) {
                if (! empty($a['path'])) {
                    Storage::disk(self::DISK)->delete($a['path']);
                }
            }
        });
    }
}