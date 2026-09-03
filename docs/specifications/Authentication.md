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
