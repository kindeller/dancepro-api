# AGENTS.md

# DancePro V2 - Repository Instructions

## Purpose

This repository contains the Laravel 13 API for DancePro V2.

This is a complete rebuild of the previous Laravel 8 application.

The goal is to build a clean, secure and maintainable platform using
modern Laravel conventions.

The old Laravel application should be treated as a reference
implementation only.

------------------------------------------------------------------------

# Core Principles

Every change should improve the project.

Optimise for:

-   Readability
-   Maintainability
-   Security
-   Simplicity

Do not optimise for writing the least amount of code.

Controllers should remain small.

Business logic belongs elsewhere.

------------------------------------------------------------------------

# Project Architecture

The project uses **feature-based architecture**.

Do not organise new code using Laravel's default application structure.

New functionality belongs inside:

``` text
app/Features/
```

Example:

``` text
app/
    Features/
        Auth/
        Users/
        Competition/
        Concert/
```

Each feature should contain:

-   Actions
-   Controllers
-   Models
-   Policies
-   Requests
-   Resources
-   Services
-   Support

Only shared functionality belongs in:

``` text
app/Shared/
```

------------------------------------------------------------------------

# Development Environment

This project uses:

-   WSL2
-   Ubuntu 24.04
-   Docker Desktop
-   Laravel Sail
-   VS Code

Always assume commands are executed from the project root.

Laravel, Composer and testing commands should always be executed through
**Laravel Sail**.

## Correct

``` bash
sail up -d
sail artisan migrate
sail artisan test
sail artisan route:list
sail composer install
sail composer update
sail pint
```

## Avoid

``` bash
php artisan
composer install
composer update
vendor/bin/pint
```

unless explicitly working inside the Sail container.

------------------------------------------------------------------------

# Controllers

Controllers should only:

-   Authorise
-   Validate
-   Delegate

Controllers should **not** contain:

-   AWS logic
-   Business logic
-   Large validation blocks
-   Complex database queries
-   File processing
-   CloudFront signing
-   S3 operations

------------------------------------------------------------------------

# Business Logic

Business logic belongs in:

-   Actions
-   Services

Controllers should delegate to these classes.

------------------------------------------------------------------------

# Validation

Always use Form Requests.

Avoid placing validation arrays directly inside controllers.

------------------------------------------------------------------------

# API Responses

Maintain a consistent API response structure.

Preferred format:

``` json
{
    "success": true,
    "message": "",
    "data": {}
}
```

Create shared response helpers where appropriate.

------------------------------------------------------------------------

# Security

Always assume client input is untrusted.

Never:

-   Expose AWS credentials.
-   Expose internal database IDs unnecessarily.
-   Trust client-side validation.
-   Embed S3 logic in controllers.

Signed URLs must always be generated server-side.

CloudFront private keys remain server-side only.

------------------------------------------------------------------------

# Competition Downloads

Competition downloads use **database-backed tracking**.

Workflow:

``` text
Client
    ↓
Laravel Tracking Link
    ↓
Database Logging
    ↓
Generate CloudFront Signed URL
    ↓
302 Redirect
```

Never return long-lived CloudFront URLs directly.

The Laravel tracking URL is the public-facing URL.

------------------------------------------------------------------------

# Documentation

When architecture changes:

Update the appropriate documentation in:

``` text
docs/
```

When an important architectural decision is made:

Create an ADR in:

``` text
docs/decisions/
```

------------------------------------------------------------------------

# Testing

After completing work, always run:

``` bash
sail artisan test
```

If routes have changed, also run:

``` bash
sail artisan route:list
```

New features should include feature tests where practical.

------------------------------------------------------------------------

# Definition of Done

A task is only complete when all applicable items are finished.

## Code

-   Feature implemented.
-   Existing architecture followed.
-   Controllers remain thin.
-   Business logic remains in Actions or Services.
-   No unnecessary technical debt introduced.

## Documentation

-   Relevant docs updated.
-   Epic, specification, or handbook files updated where affected.
-   ADR added only if an architectural decision was made.

## Database

If migrations were added:

-   Migrations run successfully.
-   Migration rollback/down behaviour reviewed where applicable.

## Testing

Run all applicable checks:

``` bash
sail artisan test
```

If routes changed, also run:

``` bash
sail artisan route:list
```

If formatting changed, also run:

``` bash
sail pint
```

If a required check cannot be run because of the local environment, report the
exact command and reason.

------------------------------------------------------------------------

# Automatic Commit Workflow

After the Definition of Done is satisfied, AI agents should prepare a git
commit automatically unless the user explicitly asks not to commit.

Before committing:

-   Review `git status`.
-   Stage only files changed for the current task.
-   Do not stage or revert unrelated user changes.
-   Use a concise, descriptive commit message.
-   Do not create a commit if required verification failed, unless the user
    explicitly asks for a commit and the failure is documented.

This workflow is local only. Do not add CI/CD, GitHub Actions, or deployment
automation unless explicitly requested.

------------------------------------------------------------------------

# Coding Standards

Prefer:

-   Clear naming
-   Small methods
-   Constructor dependency injection
-   Laravel conventions
-   Readability over cleverness

Avoid:

-   Duplicate code
-   Large controllers
-   Premature optimisation
-   Unnecessary abstractions

------------------------------------------------------------------------

# AI Working Style

When implementing work:

1.  Read this document first.
2.  Follow existing project conventions.
3.  Reuse established patterns before introducing new ones.
4.  If a significant architectural decision is required, prefer
    documenting it rather than inventing a new pattern.
5.  Minimise technical debt with every change.
6.  Complete the Definition of Done before finalising the task.
7.  Follow the automatic commit workflow unless instructed otherwise.

------------------------------------------------------------------------

# Long-Term Goal

Build a platform that is:

-   Secure
-   Documented
-   Well-tested
-   Easy to maintain
-   Easy to extend

Every feature should leave the project cleaner than it was before.
