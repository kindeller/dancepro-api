# Codex Instructions - DancePro V2

## Role

You are acting as an implementation engineer for the DancePro V2 Laravel API.

Do not invent architecture unless explicitly asked. Follow the existing project structure, `AGENTS.md`, and the documentation in `docs/`.

If the requested task requires an architectural decision that is not already documented, stop and ask before implementing.

---

## Project Context

This is a fresh Laravel 13 rebuild of an older Laravel 8 DancePro API.

The old application is a reference only. Do not copy legacy patterns blindly.

The goal is to build a secure, maintainable, well-documented API using modern Laravel conventions and feature-based architecture.

---

## Environment

This project runs in WSL2 using Laravel Sail.

Always run commands from the project root.

Use Sail for Laravel, PHP, Composer, Pint and tests.

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

Avoid bare commands such as:

```bash
php artisan
composer install
composer update
vendor/bin/pint
```

unless explicitly inside the Sail container.

---

## Architecture Rules

Use feature-based architecture.

New domain code belongs under:

```text
app/Features/
```

Examples:

```text
app/Features/Auth
app/Features/Users
app/Features/Competition
app/Features/Concert
```

Shared cross-feature code belongs under:

```text
app/Shared/
```

Each feature may contain:

```text
Actions/
Controllers/
Models/
Policies/
Requests/
Resources/
Services/
Support/
```

---

## Controller Rules

Controllers must stay thin.

Controllers should only:

- authorise
- validate
- delegate
- return responses

Controllers must not contain:

- business logic
- AWS logic
- S3 logic
- CloudFront signing
- large validation arrays
- complex queries
- file processing

---

## Laravel Rules

Use:

- Form Requests for validation
- Policies for authorisation
- API Resources for response shaping
- Actions or Services for business logic
- Constructor dependency injection
- Clear model relationships
- Feature tests for API behaviour

Prefer Laravel conventions unless the project documentation says otherwise.

---

## Security Rules

Security is more important than convenience.

Never:

- expose AWS credentials
- expose CloudFront private keys
- expose raw internal IDs unnecessarily
- trust client-side validation
- return long-lived S3 or CloudFront URLs directly
- place secrets in committed files

Competition download links must be database-backed tracking links.

The public-facing link should be a Laravel URL. Laravel should validate and log access before redirecting to a short-lived signed CloudFront/S3 URL.

---

## Documentation Rules

If architecture, API behaviour, security, deployment or workflow changes, update the relevant file in `docs/`.

For major technical decisions, create or update an ADR under:

```text
docs/decisions/
```

Keep documentation practical and concise.

---

## Testing Rules

After implementation, run:

```bash
sail artisan test
```

If routes were changed, also run:

```bash
sail artisan route:list
```

If formatting was changed, run:

```bash
sail pint
```

If a command fails because of the environment, report the exact command and error. Do not silently skip verification.

---

## Definition of Done

A task is only complete when all applicable items are finished.

### Code

- Feature implemented.
- Existing architecture followed.
- Controllers remain thin.
- Business logic remains in Actions or Services.
- No unnecessary technical debt introduced.

### Documentation

- Relevant docs updated.
- Epic, specification, or handbook files updated where affected.
- ADR added only if an architectural decision was made.

### Database

If migrations were added:

- Migrations run successfully.
- Migration rollback/down behaviour reviewed where applicable.

### Testing

Run all applicable checks:

```bash
sail artisan test
```

If routes changed, also run:

```bash
sail artisan route:list
```

If formatting changed, also run:

```bash
sail pint
```

If a required check cannot be run because of the environment, report the exact command and error.

---

## Automatic Commit Workflow

After the Definition of Done is satisfied, prepare a git commit automatically unless the user explicitly asks not to commit.

Before committing:

- Review `git status`.
- Stage only files changed for the current task.
- Do not stage or revert unrelated user changes.
- Use a concise, descriptive commit message.
- Do not create a commit if required verification failed, unless the user explicitly asks for a commit and the failure is documented.

This workflow is local only. Do not add CI/CD, GitHub Actions, or deployment automation unless explicitly requested.

---

## Working Style

Before editing:

1. Read `AGENTS.md`.
2. Inspect existing patterns.
3. Follow the current feature structure.
4. Prefer small, focused changes.
5. Avoid unrelated refactors.

After editing:

1. Complete the Definition of Done.
2. Follow the automatic commit workflow unless instructed otherwise.
3. Summarise what changed.
4. List files changed.
5. Report commands run.
6. Report any commands that failed.
7. Mention any follow-up concerns.

---

## Non-Negotiables

- Do not move away from feature-based architecture.
- Do not put business logic in controllers.
- Do not add AWS logic directly to controllers.
- Do not bypass Laravel Sail commands.
- Do not introduce a new pattern when an existing project pattern already exists.
- Do not make undocumented architectural decisions.
- Do not add CI/CD, GitHub Actions, or deployment automation unless explicitly requested.
