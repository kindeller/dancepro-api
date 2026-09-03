# Authentication Specification

## Purpose

DancePro uses Laravel Sanctum for token-based API authentication.

This document is kept as a specification because it describes concrete endpoint
behaviour and user state rather than only stable handbook guidance.

## Current Status

Baseline authentication endpoints are defined for login, logout, and returning
the current authenticated user.

Crew mobile authentication is also available under the versioned API.

## Endpoints

```text
POST /api/auth/login
POST /api/auth/logout
GET  /api/auth/me
POST /api/v1/auth/login
POST /api/v1/auth/logout
GET  /api/v1/auth/me
GET  /api/v1/auth/devices
DELETE /api/v1/auth/devices/{device}
```

## Login

`POST /api/auth/login` accepts:

```json
{
  "email": "staff@example.com",
  "password": "password",
  "device_name": "Admin app",
  "two_factor_code": "123456"
}
```

Only active users can receive a token. The response includes a bearer token,
its ISO 8601 `expires_at` value, and the authenticated user. The client must
send the token in the `Authorization` header.

When two-factor authentication is enabled for a configured account, API login
also requires either `two_factor_code` or `recovery_code`. The two fields are
mutually exclusive. A recovery code is consumed after one successful login.
When two-factor authentication is enforced and the account has not completed
setup, API login is refused until setup is completed through the web account.
No bearer token is created before the second factor succeeds.

API tokens expire after `SANCTUM_EXPIRATION` minutes. The default is 10,080
minutes (seven days), and production validation rejects missing, non-positive,
or greater-than-30-day values. Clients must return the user to login when a
token expires; refresh tokens are not currently issued.

```text
Authorization: Bearer <token>
```

Tokens use explicit least-privilege abilities:

- All active users receive `account:read` for `GET /api/auth/me`.
- Active admins additionally receive `competition-objects:read` and
  `download-links:manage`.
- Sensitive API routes check the relevant token ability as well as the user's
  current database permissions. No newly issued token receives the wildcard
  `*` ability.

Browser and API login submissions share two rate limits: five attempts per
minute for an email-address/IP combination and 30 attempts per minute for an IP
address. The email portion of the limiter key is hashed before it is cached.

### Crew mobile login

The `/api/v1/auth/*` endpoints are reserved for the Crew app. Login requires an
active account with Crew access and a required device name. An incomplete crew
member may authenticate so the app can provide the profile and contract steps
needed to finish onboarding. The login and current-user responses include
`onboarding_complete` and opaque `onboarding_missing` requirement keys.

Crew mobile tokens:

- contain only the `crew-mobile` ability;
- expire after `MOBILE_TOKEN_EXPIRATION` minutes, seven days by default;
- are replaced when the same account logs in again with the same device name;
- are checked against current active Crew access on every request; and
- are stored by the app only in the platform secure credential store.

Authenticated crew can list their mobile sessions without exposing bearer
tokens or internal database identifiers. Revoking a session requires the
account password, an idempotency key and an opaque session identifier. A user
cannot discover or revoke another user's session. This provides a lost-device
response without requiring database access, although an administrator can also
deactivate Crew access to invalidate every mobile request immediately.

There is intentionally no refresh-token flow. Expired credentials require a
fresh password and, where configured, two-factor login. When enforced two-factor
setup has not been completed, the user must configure it through the secure web
account before the app can issue a token.

Onboarding readiness returned to the app is derived from the current required
profile fields and active contract signatures. It can therefore become false if
a new active contract needs signing without erasing the historical timestamp
showing when initial onboarding was completed. Future non-onboarding mobile
routes must require current readiness while the profile and contract endpoints
remain accessible.

`PUT /api/v1/profile` lets an authenticated crew member complete or update only
their own permitted profile fields. The operation requires an idempotency key.
Supplying payment details additionally requires the current account password;
responses always return only redacted payment identifiers. Omitting `vehicles`
preserves the current vehicle list, while explicitly sending an empty list
removes it.

The first read-only mobile routes enforce these rules as follows:

- `GET /api/v1/profile` remains available during onboarding.
- Dashboard, assignment and directory routes require current onboarding
  readiness and return a structured `403` onboarding error otherwise.
- Assignment lookups use public UUIDs and return `404` when the assignment does
  not belong to the authenticated crew member.
- Directory responses contain active crew, studios and competitions only.
- Profile payment identifiers are redacted to their final four characters;
  administrator notes and unrelated financial data are never returned.

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
