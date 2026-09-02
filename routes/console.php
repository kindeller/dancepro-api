<?php

use App\Features\Contacts\Actions\ExportContactDirectory;
use App\Features\Contacts\Actions\ImportContactDirectory;
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

Artisan::command('contacts:export {archive=imports/contact-directory.zip}', function (ExportContactDirectory $export): int {
    $summary = $export->execute($this->argument('archive'));

    $this->info("Exported {$summary['studios']} studios, {$summary['studio_contacts']} studio contacts, {$summary['competitions']} competitions and {$summary['competition_staff']} competition staff.");
    $this->info("Included {$summary['logos']} logos in {$summary['path']}.");
    $this->warn('This private archive contains personal contact information. Transfer and retain it securely.');

    return self::SUCCESS;
})->purpose('Export studio and competition contacts with logos to a private transfer archive');

Artisan::command('contacts:import {archive=imports/contact-directory.zip} {--apply} {--force}', function (ImportContactDirectory $import): int {
    if ($this->option('apply') && app()->environment('production') && ! $this->option('force')) {
        $this->error('Production imports require both --apply and --force after a successful dry-run.');

        return self::FAILURE;
    }

    $summary = $import->execute($this->argument('archive'), (bool) $this->option('apply'));
    $verb = $summary['applied'] ? 'Imported' : 'Validated';
    $this->info("{$verb} {$summary['studios']} studios, {$summary['studio_contacts']} studio contacts, {$summary['competitions']} competitions, {$summary['competition_staff']} competition staff and {$summary['logos']} logos.");
    $this->line("Studios: {$summary['new_studios']} new, {$summary['updated_studios']} updates. Competitions: {$summary['new_competitions']} new, {$summary['updated_competitions']} updates.");
    if (! $summary['applied']) {
        $this->warn('Dry-run only. Run again with --apply to write the validated directory. Production also requires --force.');
    }

    return self::SUCCESS;
})->purpose('Validate or import a private studio and competition contact archive');
