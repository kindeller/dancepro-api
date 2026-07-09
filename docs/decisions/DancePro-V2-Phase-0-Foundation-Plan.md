
# DancePro V2 - Phase 0 Foundation Plan

## Purpose

Phase 0 establishes the platform foundation before rebuilding any major DancePro features.

The goal is to create a secure, maintainable Laravel 13 API with modern structure, authentication, user management, documentation and conventions in place before migrating competition or concert functionality.

This is not a direct Laravel 8 upgrade. The old application is treated as a reference only.

## Key Decisions

- Use a fresh Laravel 13 application.
- Use PHP 8.3.
- Use Docker/Sail for local development.
- Use feature-based architecture instead of traditional Laravel-type grouping.
- Do not use `/api/v2` if this application is replacing the whole API.
- Use clean endpoint names from the start, for example `/api/competition/...`.
- Keep the old Laravel 8 application running separately during migration.
- Build security and documentation into the foundation rather than adding them later.

## Proposed Application Structure

```text
app/
    Features/
        Auth/
            Actions/
            Controllers/
            Models/
            Policies/
            Requests/
            Resources/
            Services/
            Support/

        Users/
            Actions/
            Controllers/
            Models/
            Policies/
            Requests/
            Resources/
            Services/
            Support/

        Competition/
            Actions/
            Controllers/
            Models/
            Policies/
            Requests/
            Resources/
            Services/
            Support/

        Concert/
            Actions/
            Controllers/
            Models/
            Policies/
            Requests/
            Resources/
            Services/
            Support/

    Shared/
        Actions/
        DTOs/
        Exceptions/
        Http/
        Responses/
        Services/
        Support/
```

Laravel's default folders can still exist where useful, but new domain code should live inside `app/Features`.

## Phase 0 Priorities

### 1. Project Conventions

Define and document:

- Folder structure.
- Naming conventions.
- API response format.
- Error response format.
- Authentication approach.
- Authorisation approach.
- Testing expectations.
- Documentation expectations.
- Git workflow.
- Deployment assumptions.

Suggested docs:

```text
docs/
    README.md
    handbook/
        Architecture.md
        API-Guidelines.md
        Authentication.md
        Security.md
        Deployment.md
    specifications/
        Authentication.md
    decisions/
```

### 2. Authentication

Use Laravel Sanctum for API authentication.

Initial focus:

- Staff/admin app authentication.
- Secure API token issuing.
- Logout/revoke token.
- Authenticated user endpoint.
- Future compatibility with roles, permissions, customers and payments.

Suggested endpoints:

```text
POST   /api/auth/login
POST   /api/auth/logout
GET    /api/auth/me
```

Future endpoints may include:

```text
POST   /api/auth/refresh
POST   /api/auth/forgot-password
POST   /api/auth/reset-password
```

### 3. User Model

Start simple but future-aware.

Suggested `users` fields:

```text
id
name
email
password
is_active
last_login_at
created_at
updated_at
deleted_at
```

Optional but useful:

```text
type
email_verified_at
last_seen_at
```

Do not build full RBAC immediately unless needed. Design for it by using policies and clean permission checks.

### 4. Future RBAC Planning

Do not overbuild RBAC during Phase 0.

However, avoid hard-coding permission logic into controllers.

Use policies from the start:

```text
CompetitionPolicy
DownloadLinkPolicy
UserPolicy
```

Later, full roles and permissions can be introduced without rewriting controllers.

Possible future tables:

```text
roles
permissions
role_user
permission_role
```

For now, `is_active` and basic admin-level access may be enough.

### 5. API Response Standard

Every endpoint should return a predictable structure.

Example success response:

```json
{
  "success": true,
  "message": "Competition files returned.",
  "data": {}
}
```

Example error response:

```json
{
  "success": false,
  "message": "Unauthorised.",
  "errors": {}
}
```

Consider creating a shared response helper/service:

```text
app/Shared/Responses/ApiResponse.php
```

### 6. Validation

Use Form Requests for all non-trivial input.

Example:

```text
app/Features/Auth/Requests/LoginRequest.php
app/Features/Competition/Requests/GenerateDownloadLinkRequest.php
```

Controllers should not contain large validation blocks.

### 7. Exception Handling

Centralise API exception responses.

Focus on:

- Authentication failures.
- Authorisation failures.
- Validation errors.
- Missing records.
- AWS/S3 failures.
- Expired/revoked links.
- Unexpected server errors.

### 8. Security Baseline

Phase 0 should establish:

- `.env` secrets only, never committed.
- Sanctum authentication.
- Password hashing.
- Active/inactive user checks.
- Request validation.
- Policies for protected actions.
- Private S3 buckets.
- CloudFront/S3 signing only server-side.
- No direct AWS credentials in any client app.
- No raw database IDs exposed in public download links.

### 9. Testing Baseline

Set up basic feature tests early.

Initial tests:

```text
Auth/LoginTest
Auth/LogoutTest
Auth/MeTest
Users/UserAccessTest
```

Test:

- Successful login.
- Invalid login.
- Inactive user cannot login.
- Authenticated user can call `/api/auth/me`.
- Unauthenticated user cannot access protected routes.

### 10. GitHub / CI Preparation

Initial CI checks should eventually include:

```text
composer install
php artisan test
php artisan pint --test
php artisan route:list
```

Later additions:

```text
PHPStan/Larastan
Security dependency checks
Deployment to staging
```

## Phase 0 Deliverables

- Laravel 13 app running in Docker/Sail.
- Feature-based folder structure created.
- Auth feature implemented.
- User foundation implemented.
- Sanctum configured.
- API response conventions documented.
- Security baseline documented.
- Basic auth tests passing.
- GitHub repository initialised.
- Initial CI workflow added or planned.

## Guiding Principle

Phase 0 should make the rest of the project easier.

Do not rush into Competition, Concert or media endpoints until authentication, structure, naming and documentation are in place.
