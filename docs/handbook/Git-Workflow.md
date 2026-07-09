# Git Workflow

## Purpose

Define the project-wide Definition of Done and local commit expectations for
DancePro V2 development.

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

If a required check cannot be run because of the local environment, record the
exact command and reason in the work summary.

## Automatic Commit Workflow

After the Definition of Done is satisfied, AI-assisted work should be committed
automatically unless the user explicitly asks not to commit.

Before committing:

- Review `git status`.
- Stage only files changed for the current task.
- Do not stage or revert unrelated user changes.
- Use a concise, descriptive commit message.
- Do not create a commit if required verification failed, unless the user
  explicitly asks for a commit and the failure is documented.

This is a local development workflow only. Do not add CI/CD, GitHub Actions, or
deployment automation unless explicitly requested.

## Related Documentation

- [Development Environment](Development-Environment.md)
- [Testing](Testing.md)
- [Architecture](Architecture.md)
