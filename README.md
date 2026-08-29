# DancePro API

DancePro API is the Laravel 13 backend for DancePro V2. It is a clean rebuild
of the previous Laravel 8 application and uses feature-based architecture under
`app/Features/`.

## Start Here

Before making changes, read:

- [Repository and AI-agent instructions](AGENTS.md)
- [Development environment](docs/handbook/Development-Environment.md)
- [Git workflow](docs/handbook/Git-Workflow.md)
- [Architecture](docs/handbook/Architecture.md)
- [Testing](docs/handbook/Testing.md)

## Collaboration Rules

- Treat the canonical branch as protected. Direct pushes are reserved for the
  repository owner.
- Create a branch named `<developer-id>-<work-description>` for every change,
  for example `alex-competition-download-audit`.
- Open a Pull Request for all completed work. The repository owner must review
  and approve it before merge.
- Do not merge your own PR or bypass repository protections.
- Keep changes focused, preserve unrelated work, and never commit secrets.

The canonical and protected branch is `master`. See the
[Git workflow](docs/handbook/Git-Workflow.md) for the complete process.

## Local Development

The supported environment is WSL2 with Ubuntu 24.04, Docker Desktop, Laravel
Sail, and VS Code. Run framework and dependency commands through Sail from the
project root:

```bash
sail up -d
sail composer install
sail artisan migrate
sail artisan test
```

Do not use bare `php artisan` or `composer` commands unless you are explicitly
working inside the Sail container.

## Documentation

Project documentation lives in [docs](docs/README.md). Update the relevant
handbook, epic, specification, or milestone whenever behavior or architecture
changes.
