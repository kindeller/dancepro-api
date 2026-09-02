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

Browser and API login submissions share two rate limits: five attempts per
minute for an email-address/IP combination and 30 attempts per minute for an IP
address. The email portion of the limiter key is hashed before it is cached.

## Password Reset

Browser password-reset link requests are limited to three per minute for an
email-address/IP combination and ten per hour for an IP address. Reset
submissions are limited to five per minute for an email-address/IP combination
and 20 per hour for an IP address. These limits supplement the password
broker's token protections and apply equally to known and unknown email
addresses.

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
