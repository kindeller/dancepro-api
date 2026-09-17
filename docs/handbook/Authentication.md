# Authentication

## Purpose

Document the stable authentication approach used by the DancePro V2 API.

## Current Status

DancePro V2 uses Laravel Sanctum bearer tokens for API authentication.

The macOS Flutter media tool uses the same Laravel identity boundary. It must
store its token in the macOS Keychain and must never store AWS credentials.
Media upload routes require an active staff/admin account plus explicit
`concert-media:*` token abilities.

Detailed endpoint behaviour belongs in the authentication specification.

## Scope

- Protected API routes should use `auth:sanctum`.
- Login must only issue tokens to active users.
- Logout should revoke the current token.
- Desktop device tokens should expire under an explicit production policy and
  be limited to the abilities selected by the server for that client.
- Clients must not be allowed to request or expand their own token abilities.
- Customer accounts must not receive staff media abilities.
- Use named staff accounts for auditability. Before external client staff use
  the uploader, add and enforce explicit studio/concert assignments; the current
  staff role is global.
- Login and upload-signing endpoints must be rate limited.
- Future roles and permissions should be introduced through policies and
  explicit permission checks, not controller conditionals.

## Current implementation gap

API login currently issues a wildcard `*` token after checking credentials and
active status. It does not enforce the controller's stated staff/admin-only
intent. Narrow token issuance and explicit account-type checks are required
before the Flutter media API is enabled.

## Links to Related Documentation

- [Authentication Specification](../specifications/Authentication.md)
- [API Guidelines](API-Guidelines.md)
- [Security](Security.md)
- [Architecture](Architecture.md)
- [Flutter Desktop Media Ingest API](../specifications/Flutter-Desktop-Media-Ingest-API.md)

## Notes / Future Work

Keep this page focused on stable authentication guidance. Add detailed request
and response behaviour to the specification instead.
