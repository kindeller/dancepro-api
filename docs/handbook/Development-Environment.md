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

## Local Demonstration Data

After running migrations, seed the opt-in fictional development dataset with:

```bash
sail artisan db:seed --class='Database\Seeders\LocalDevelopmentSeeder'
```

The seeder refuses to run unless `APP_ENV=local`. It is not called by
`DatabaseSeeder`, does not require S3, and uses the private `local` disk for
placeholder media files. It updates its deterministic demonstration records
when run again instead of continually adding duplicates.

The placeholder videos are intended to validate the concert page, playlist and
automatic next-item behavior only. They are served through Laravel's local
filesystem response and are not representative of production video delivery.
Depending on the browser, seeking may produce incomplete-response or content
length errors. This local limitation does not change the production design:
authorised playback will redirect to short-lived S3 or CloudFront delivery with
byte-range support.

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
