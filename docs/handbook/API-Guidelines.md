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

Protected API routes should use `auth:sanctum`. Token abilities may be added later where a route needs more specific permission checks.

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

## Related Documentation

- [Authentication Handbook](Authentication.md)
- [Authentication Specification](../specifications/Authentication.md)
- [Security](Security.md)
