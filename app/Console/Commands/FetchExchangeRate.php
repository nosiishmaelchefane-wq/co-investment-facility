<?php

namespace App\Console\Commands;

use App\Support\ExchangeRates;
use Illuminate\Console\Command;

/**
 * php artisan cif:exchange-rate            fetch the latest US$ → Maloti rate now
 * php artisan cif:exchange-rate --set=18.25 --note="CBL rate 13 Nov"   record a rate by hand
 * php artisan cif:exchange-rate --show     show the rate in use
 * Runs automatically from the scheduler (routes/console.php).
 */
class FetchExchangeRate extends Command
{
    protected $signature = 'cif:exchange-rate {--set= : Record this rate by hand (1 US$ = x Maloti)} {--note= : Note for a manual rate} {--show : Only show the rate in use}';

    protected $description = 'Fetch the live US$ → Maloti exchange rate used across the CIF platform';

    public function handle(): int
    {
        if ($this->option('show')) {
            $this->line($this->describe());
            return self::SUCCESS;
        }

        if ($this->option('set') !== null) {
            $row = ExchangeRates::setManual((float) $this->option('set'), $this->option('note'));
            $this->info('Recorded: 1 US$ = M '.number_format($row->rate, 4).' (manual).');
            return self::SUCCESS;
        }

        $row = ExchangeRates::refresh();

        if (! $row) {
            $this->error('Could not fetch a rate from any provider. Still using: '.$this->describe());
            return self::FAILURE;
        }

        $this->info('Updated: 1 US$ = M '.number_format($row->rate, 4).' from '.$row->source
            .($row->rate_date ? ' (rate of '.$row->rate_date->format('d M Y H:i').')' : '').'.');

        return self::SUCCESS;
    }

    protected function describe(): string
    {
        $i = ExchangeRates::info();

        return '1 US$ = M '.number_format($i['rate'], 4).' · '.$i['source']
            .($i['fetched_at'] ? ' · fetched '.\Illuminate\Support\Carbon::parse($i['fetched_at'])->format('d M Y H:i') : '');
    }
}