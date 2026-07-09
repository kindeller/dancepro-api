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

Before finalising a task, complete the project Definition of Done and follow
the local approval-gated commit workflow in [Git Workflow](Git-Workflow.md).

## Links to Related Documentation

- [Git Workflow](Git-Workflow.md)
- [Architecture](Architecture.md)
- [Testing](Testing.md)
- [Deployment](Deployment.md)

## Notes / Future Work

Add any project-specific Sail service notes once the local environment requires
more than the default Laravel setup.
