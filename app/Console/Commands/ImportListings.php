<?php

namespace App\Console\Commands;

use App\Services\ListingImporter;
use App\Services\RentCast;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

#[Signature('listings:import {--zip= : A 5-digit ZIP code} {--city= : A city name, e.g. Austin} {--state= : The 2-letter state code, e.g. TX} {--type=both : sale, rent or both}')]
#[Description('Import live homes for sale and rent from RentCast for a ZIP code or a city')]
class ImportListings extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(ListingImporter $importer, RentCast $rentCast): int
    {
        $zip = trim((string) $this->option('zip'));
        $city = trim((string) $this->option('city'));
        $state = strtoupper(trim((string) $this->option('state')));

        $where = match (true) {
            preg_match('/^\d{5}$/', $zip) === 1 => ['zipCode' => $zip],
            $city !== '' && isset(ListingImporter::STATES[$state]) => ['city' => $city, 'state' => $state],
            default => null,
        };

        if ($where === null) {
            $this->components->error('Give a ZIP code (--zip=78704) or a city and state (--city=Austin --state=TX).');

            return self::FAILURE;
        }

        $types = match ($this->option('type')) {
            'sale' => ['sale'],
            'rent' => ['rent'],
            default => ['sale', 'rent'],
        };

        try {
            $result = $importer->import($where, $types);
        } catch (RuntimeException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Added {$result['created']}, updated {$result['updated']}, kept {$result['kept']} edited by the admin, skipped {$result['skipped']}.");
        $this->components->twoColumnDetail('RentCast requests used this month', $rentCast->requestsThisMonth().' of '.$rentCast->monthlyLimit());

        return self::SUCCESS;
    }
}
