<?php

use App\Features\Contacts\Actions\ExportContactDirectory;
use App\Features\Contacts\Actions\ImportContactDirectory;
use App\Features\Deployment\Actions\MigratePublicUploads;
use App\Features\Deployment\Services\DatabaseBackup;
use App\Features\Deployment\Services\ProductionDependencyCheck;
use App\Features\Deployment\Services\ProductionEnvironmentValidator;
use App\Features\Downloads\Actions\PruneDownloadAccesses;
use App\Features\Operations\Actions\SecureExistingInternalDocuments;
use App\Features\Venues\Actions\ImportVenueCatalog;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('production:validate', function (ProductionEnvironmentValidator $validator): int {
    $errors = $validator->errors();

    if ($errors === []) {
        $this->info('Production environment validation passed.');

        return self::SUCCESS;
    }

    $this->error('Production environment validation failed:');
    foreach ($errors as $error) {
        $this->line(" - {$error}");
    }

    return self::FAILURE;
})->purpose('Refuse deployment when production configuration is unsafe or incomplete');

Artisan::command('database:backup {--prune}', function (DatabaseBackup $backup): int {
    $result = $backup->create((bool) $this->option('prune'));
    $this->info('Verified database backup: '.$result['path']);
    $this->line("Size: {$result['bytes']} bytes");
    $this->line('SHA-256: '.$result['sha256']);
    $this->line("Expired backups removed: {$result['removed']}");

    return self::SUCCESS;
})->purpose('Create and verify a private compressed database backup');

Artisan::command('database:backup-verify {archive}', function (DatabaseBackup $backup): int {
    $result = $backup->verify($this->argument('archive'));
    $this->info('Database backup archive verified.');
    $this->line("Size: {$result['bytes']} bytes");
    $this->line('SHA-256: '.$result['sha256']);

    return self::SUCCESS;
})->purpose('Verify the integrity and checksum of a database backup archive');

Artisan::command('production:check-dependencies', function (ProductionDependencyCheck $check): int {
    foreach ($check->run() as $dependency) {
        $this->info("OK: {$dependency}");
    }

    return self::SUCCESS;
})->purpose('Verify production database, cache, private storage, mail and queue dependencies');

Artisan::command('production:healthcheck-url', function (): int {
    $this->line((string) config('deployment.healthcheck_url'));

    return self::SUCCESS;
})->purpose('Print the configured production health-check URL');

Artisan::command('downloads:prune-accesses', function (PruneDownloadAccesses $prune): int {
    $this->info('Removed '.$prune->execute().' expired download access record(s).');

    return self::SUCCESS;
})->purpose('Remove download access records beyond the configured retention period');

Schedule::command('downloads:prune-accesses')->daily()->withoutOverlapping();

Artisan::command('uploads:migrate-public {--from=public} {--apply}', function (MigratePublicUploads $migrate): int {
    $summary = $migrate->execute((string) $this->option('from'), (bool) $this->option('apply'));
    $verb = $summary['applied'] ? 'Copied' : 'Would copy';
    $this->info("{$verb} {$summary['copied']} of {$summary['tracked']} tracked public upload(s).");
    $this->line("Already present: {$summary['already_present']}; missing: {$summary['missing']}.");
    if (! $summary['applied']) {
        $this->warn('Dry-run only. Run again with --apply after reviewing the result. Source files are retained.');
    }

    return $summary['missing'] > 0 ? self::FAILURE : self::SUCCESS;
})->purpose('Copy tracked public uploads to the configured durable disk');

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

Artisan::command('operations:secure-documents {--apply} {--force}', function (SecureExistingInternalDocuments $secure): int {
    if ($this->option('apply') && app()->environment('production') && ! $this->option('force')) {
        $this->error('Production changes require both --apply and --force after a successful dry-run.');

        return self::FAILURE;
    }

    $summary = $secure->execute((bool) $this->option('apply'));
    $verb = $summary['applied'] ? 'Secured' : 'Found';
    $this->info("{$verb} {$summary['tracked']} tracked internal document(s): {$summary['public']} public, {$summary['already_private']} already private and {$summary['missing']} missing.");
    if ($summary['applied']) {
        $this->info("Moved {$summary['moved']} document(s) to private storage and removed their verified public copies.");
    } else {
        $this->warn('Dry-run only. Run again with --apply to move files. Production also requires --force.');
    }

    return $summary['missing'] > 0 ? self::FAILURE : self::SUCCESS;
})->purpose('Move tracked operational documents out of public storage');
