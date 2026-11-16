<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A US$ → Maloti rate fetched by App\Support\ExchangeRates (one row per fetch — the latest row is the current rate).
 */
class ExchangeRate extends Model
{
    protected $fillable = ['base', 'quote', 'rate', 'source', 'rate_date', 'fetched_at'];

    protected $casts = [
        'rate'       => 'float',
        'rate_date'  => 'datetime',
        'fetched_at' => 'datetime',
    ];

    /** Latest fetched US$ → LSL rate row (null if none yet) */
    public static function latestUsdLsl(): ?self
    {
        return static::where('base', 'USD')->where('quote', 'LSL')->latest('fetched_at')->first();
    }
}