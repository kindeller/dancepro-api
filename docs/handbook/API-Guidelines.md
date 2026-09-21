# API Guidelines

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

Create, import, upload-completion and finalisation requests must support an
`Idempotency-Key` so a desktop client can retry after an uncertain network
result without duplicating business records or completing an operation twice.

The API allocates UUIDs and authoritative object keys. A client may submit only
validated relative paths within its reserved media asset. Presigned upload URLs
and their query strings are sensitive bearer capabilities and must not be
logged.

## Idempotency

Mobile state-changing routes that may be retried require an `Idempotency-Key`
UUID. The server scopes the key to the authenticated user and fingerprints the
HTTP method, request target and exact request body.

- An identical retry within the retention window returns the original status
  and JSON body with `Idempotency-Replayed: true`.
- Reusing a key for different request input returns HTTP 409.
- A duplicate received while the first request is still running returns HTTP
  409 with `Retry-After: 2`.
- Failed requests that do not commit a response can be retried.
- Stored response bodies and headers are encrypted with `APP_KEY` because they
  may contain private profile or operational data.
- Records expire after `API_IDEMPOTENCY_RETENTION_HOURS` (24 hours by default)
  and the hourly `api:prune-idempotency` task removes expired records.

The native client must generate a new key for each intended operation and retain
that same key until the operation either succeeds or is deliberately abandoned.

## Read Requests

`GET` requests must not create records or advance workflow state. For example,
`GET /api/v1/training/{course}` only returns course content and current progress;
the client uses `POST /api/v1/training/{course}/start` to begin the course.

## Related Documentation

- [Authentication Handbook](Authentication.md)
- [Authentication Specification](../specifications/Authentication.md)
- [Security](Security.md)
- [Flutter Desktop Media Ingest API](../specifications/Flutter-Desktop-Media-Ingest-API.md)
