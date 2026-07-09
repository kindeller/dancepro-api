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

## Related Documentation

- [Authentication Handbook](Authentication.md)
- [Authentication Specification](../specifications/Authentication.md)
- [Security](Security.md)
