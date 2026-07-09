# Authentication Specification

## Purpose

DancePro uses Laravel Sanctum for token-based API authentication.

This document is kept as a specification because it describes concrete endpoint
behaviour and user state rather than only stable handbook guidance.

## Current Status

Baseline authentication endpoints are defined for login, logout, and returning
the current authenticated user.

## Endpoints

```text
POST /api/auth/login
POST /api/auth/logout
GET  /api/auth/me
```

## Login

`POST /api/auth/login` accepts:

```json
{
  "email": "staff@example.com",
  "password": "password",
  "device_name": "Admin app"
}
```

Only active users can receive a token. The response includes a bearer token that the client must send in the `Authorization` header.

```text
Authorization: Bearer <token>
```

## Logout

`POST /api/auth/logout` revokes the current Sanctum token only.

## User State

The baseline user fields are:

```text
id
name
email
type
is_active
email_verified_at
password
last_login_at
last_seen_at
created_at
updated_at
deleted_at
```

## Links to Related Documentation

- [Authentication Handbook](../handbook/Authentication.md)
- [API Guidelines](../handbook/API-Guidelines.md)
- [Security](../handbook/Security.md)
- [Foundation Epic](../epics/Foundation.md)

## Notes / Future Work

Future authentication work may include refresh, forgot-password, and
reset-password endpoints. Add those behaviours here once they are planned.
