<?php

use App\Features\Venues\Actions\ImportVenueCatalog;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('venues:import {source?}', function (ImportVenueCatalog $import): int {
    $source = $this->argument('source') ?: storage_path('app/private/imports/venue-maps');
    $summary = $import->execute($source);

    $this->info("Imported {$summary['venues']} venues, {$summary['maps']} maps and {$summary['references']} reference image(s).");
    $this->info("Removed {$summary['removed']} unmatched placeholder venue(s).");

    return self::SUCCESS;
})->purpose('Import the reconciled DancePro venue catalog and supplied maps');
