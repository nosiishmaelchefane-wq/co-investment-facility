<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Gate 5 · a document attached to an authorisation round.
 *   md_sent    sent by the PMU to the CAFI Managing Director for review
 *   ps_signed  signed by the MTIBD Principal Secretary (uploaded by the PMU)
 * Files are private (Authorisation::DISK) and downloaded through Gate4FileController::document.
 */
class AuthorisationDocument extends Model
{
    public const KIND_MD_SENT   = 'md_sent';
    public const KIND_PS_SIGNED = 'ps_signed';

    protected $fillable = ['authorisation_id', 'kind', 'path', 'name', 'size', 'uploaded_by'];

    public function authorisation(): BelongsTo
    {
        return $this->belongsTo(Authorisation::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function sizeLabel(): string
    {
        $kb = (int) round(($this->size ?? 0) / 1024);

        return $kb >= 1024 ? number_format($kb / 1024, 1).' MB' : max($kb, 1).' KB';
    }

    /** Delete the file — unless another row (a resubmitted round) still points at it */
    protected static function booted(): void
    {
        static::deleted(function (AuthorisationDocument $d) {
            $shared = static::where('path', $d->path)->exists()
                || Authorisation::where('md_request_path', $d->path)->orWhere('ps_signed_path', $d->path)->exists();

            if (! $shared) {
                Storage::disk(Authorisation::DISK)->delete($d->path);
            }
        });
    }
}