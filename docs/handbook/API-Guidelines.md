# API Guidelines

## Consumer Contract

The central API reference is [docs/api/README.md](../api/README.md), with typed
schemas and complete examples in [openapi.json](../api/openapi.json). Hand both
files to consuming app developers; feature handoffs explain workflows but do
not replace this contract.

Every endpoint must include method/path, request schema and example (or an
explicit no-body declaration), parameters/headers, authentication/abilities,
success status, full response example, field types/nullability, pagination and
relevant error schemas/examples. Keep both reference files current in the same
change as any route, validation, controller, resource or response change.
Document conditional omissions separately from fields that return null. Use
fictional examples and redacted capabilities; never copy production credentials
or personal responses. `ApiDocumentationTest` checks route coverage and examples;
it does not replace behaviour tests or actual AWS integration verification.

## Route Shape

The V2 API replaces the old API, so endpoints should use clean resource names instead of a version prefix.

Current authentication endpoints:

```text
POST /api/auth/login
POST /api/auth/logout
GET  /api/auth/me
```

## Response Format

All API endpoints should return the shared JSON envelope from `App\Shared\Responses\ApiResponse`.

Success:

```json
{
  "success": true,
  "message": "Authenticated user returned.",
  "data": {}
}
```

Error:

```json
{
  "success": false,
  "message": "Unauthenticated.",
  "errors": {}
}
```

## Validation

Use Laravel Form Requests for request validation once input is more than trivial. Controllers should receive already-validated input and stay focused on application flow.

## Authentication

Protected API routes should use `auth:sanctum`. Routes that perform privileged
or client-specific operations must also enforce explicit token abilities and
domain policies.

Privileged media routes are the first required use of explicit abilities. They
must check both an active staff/admin account and the relevant
`concert-media:read`, `concert-media:upload` or `concert-media:update` ability.
Possession of a valid wildcard or unrelated token must not substitute for a
media policy decision.

For compatibility, existing wildcard tokens may satisfy only
`competition-objects:read` and `download-links:manage`; account policies still
apply. New login tokens receive explicit abilities and an individual expiry
configured by `STAFF_API_TOKEN_TTL_MINUTES` (30 days by default). The optional
`SANCTUM_EXPIRATION` global limit is not newly imposed on old tokens.

Collection creation, asset reservation, upload URL batches, multipart initiation
and legacy imports require a UUID `Idempotency-Key`. Reuse it for an unchanged
retry after an uncertain result. Completion and finalisation are retryable by
their existing resource/upload identifiers and do not require a new header.
Generic download-link creation is not idempotent; do not assume this behaviour
applies to every POST request.

The API allocates UUIDs and authoritative object keys. A client may submit only
validated relative paths within its reserved media asset. Presigned upload URLs
and their query strings are sensitive bearer capabilities and must not be
logged.

## Related Documentation

- [Authentication Handbook](Authentication.md)
- [Authentication Specification](../specifications/Authentication.md)
- [Security](Security.md)
- [Flutter Desktop Media Ingest API](../specifications/Flutter-Desktop-Media-Ingest-API.md)
