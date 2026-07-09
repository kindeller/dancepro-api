# Authentication

## Purpose

Document the stable authentication approach used by the DancePro V2 API.

## Current Status

DancePro V2 uses Laravel Sanctum bearer tokens for API authentication.

Detailed endpoint behaviour belongs in the authentication specification.

## Scope

- Protected API routes should use `auth:sanctum`.
- Login must only issue tokens to active users.
- Logout should revoke the current token.
- Future roles and permissions should be introduced through policies and
  explicit permission checks, not controller conditionals.

## Links to Related Documentation

- [Authentication Specification](../specifications/Authentication.md)
- [API Guidelines](API-Guidelines.md)
- [Security](Security.md)
- [Architecture](Architecture.md)

## Notes / Future Work

Keep this page focused on stable authentication guidance. Add detailed request
and response behaviour to the specification instead.
