# Development Environment

## Purpose

Define the expected local development environment for DancePro V2.

## Current Status

The project assumes development from WSL2 on Ubuntu 24.04 using Docker Desktop,
Laravel Sail, and VS Code.

## Scope

Run Laravel, Composer, Pint, migrations, route inspection, and tests through
Sail from the project root.

Preferred commands:

```bash
sail up -d
sail artisan migrate
sail artisan test
sail artisan route:list
sail composer install
sail composer update
sail pint
```

Avoid bare `php artisan`, `composer`, or `vendor/bin/pint` commands unless
explicitly working inside the Sail container.

## Local media and production isolation

With `APP_ENV=local`, all AWS-named filesystem disks are backed by separate
private folders under `storage/app/local-aws/`. The database continues to use
the logical disk names (for example `s3_concerts`), so media uploaded locally
cannot be sent to S3 even if AWS credentials are present in `.env`. Do not copy
production database records into local development: their object keys will
refer to files that are absent from the isolated local folders.

Managed concert video uploads use short-lived, signed local PUT URLs. They
retain the same SHA-256 and CRC64NVME verification and multipart completion
contract as production. Local playback and downloads are served by Laravel;
video responses support byte-range requests. Covers and programs are also
served by Laravel. CloudFront signing is disabled in local mode, even if its
settings were copied into `.env`. `APP_ENV=testing` retains the existing fake
storage/test configuration; production continues to require private S3 and
CloudFront.

External cover, program and gallery URLs retained in local database records
are suppressed in local pages and the public catalogue API, without changing
the saved records. This prevents imported links from silently loading live
media in a browser. Use the fictional local seeder rather than copying a
production database for testing.

Local startup refuses remote database, mail and queue configuration, or a
cached AWS disk driver. Keep the database on SQLite or the local Sail database,
mail on `log`, `array` or local Mailpit, and queues on a local driver. Push
notifications are not sent to a configured gateway in local mode, and Redis
is restricted to local hosts. When switching between environments, clear
cached configuration before starting the local application
(`sail artisan config:clear`). The local upload routes
exist in other environments for stable route registration but reject requests
unless the app is actually running in local mode with a valid signed URL.

The `APP_URL` host in `.env` must be local/private and reachable by the browser
or desktop media client because the local signed PUT URLs point back to Laravel.
Local media files are not shared with production and should not be treated as
backups.

## Local Demonstration Data

After running migrations, seed the opt-in fictional development dataset with:

```bash
sail artisan db:seed --class='Database\Seeders\LocalDevelopmentSeeder'
```

The seeder refuses to run unless `APP_ENV=local`. It is not called by
`DatabaseSeeder`, does not require S3, and uses the private `local` disk for
placeholder media files. It updates its deterministic demonstration records
when run again instead of continually adding duplicates.

The older fictional placeholder videos use the separate private `local` disk
and are intended to validate the concert page, playlist and automatic
next-item behavior only. They now use the same local byte-range file response
as new managed media. Production authorised playback uses short-lived
CloudFront delivery with byte-range support.

Automatic next-item playback has been manually tested successfully on desktop
and mobile across multiple browsers. Retest it when the production media
delivery path replaces the local placeholder response.

Fictional local accounts use `local-demo-password`. The protected “Paper Wings”
concert uses `paper-wings-demo`. These credentials are for local development
only and must not be reused in a deployed environment.

Before finalising a task, complete the project Definition of Done and follow
the local approval-gated commit workflow in [Git Workflow](Git-Workflow.md).

## Links to Related Documentation

- [Remote Development](Remote-Development.md)
- [Remote Development Cheat Sheet](Remote-Development-Cheat-Sheet.md)
- [Git Workflow](Git-Workflow.md)
- [Architecture](Architecture.md)
- [Testing](Testing.md)
- [Deployment](Deployment.md)

## Notes / Future Work

Add any project-specific Sail service notes once the local environment requires
more than the default Laravel setup.
