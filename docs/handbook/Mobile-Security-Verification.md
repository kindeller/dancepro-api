# Mobile Security Verification

This checklist separates controls that can be verified in the Laravel API now
from controls that require the deployed service and the native application.

## API Controls Verified Locally

- Every Crew mobile route requires a valid Sanctum bearer token with the
  `crew-mobile` ability. Direct unauthenticated requests are rejected.
- Active Crew access is rechecked on each request, so deactivation blocks an
  already-issued token.
- Tokens expire, logout revokes the current token, and signing in again with
  the same device name replaces that device's earlier token.
- A crew member can list and revoke only their own mobile sessions. Session
  identifiers are opaque, revocation is rate limited and requires both an
  idempotency key and password confirmation.
- Cross-crew records are scoped to the authenticated crew member and use
  not-found responses to avoid disclosing that another user's record exists.
- Private documents require authentication as well as a signature. Download
  signatures expire after five minutes, active status is rechecked at download
  time, storage paths are hidden, and responses prohibit caching.
- Versioned mobile API responses prohibit storage by shared HTTP caches.
- Bank identifiers returned to mobile clients are redacted. Contract signing
  requires password confirmation and produces an audit record.
- Login, password-confirmed actions and public submission endpoints are rate
  limited. Non-trivial input is server validated.

These controls are covered by feature tests and must remain part of the normal
`sail artisan test` regression suite.

## Native App Requirements

- Store bearer tokens only in iOS Keychain or Android Keystore-backed secure
  storage. Never place them in preferences, AsyncStorage, logs or analytics.
- On logout, revocation, an authentication failure, or Crew deactivation,
  delete tokens and all private offline files, thumbnails, database rows,
  temporary files and in-memory caches associated with the account.
- Exclude private offline content from device and cloud backups. Confirm cache
  deletion by inspecting the app container after logout and forced revocation.
- Use HTTPS only. Do not disable certificate or hostname validation in release
  builds. Record a deliberate decision before introducing certificate pinning.
- Keep push-notification payloads minimal; fetch sensitive message content only
  after authenticated app launch and respect lock-screen privacy settings.
- Do not offer high-sensitivity fields such as bank, identity, medical, password
  or token values as shareable chat content, and warn users not to send them.
- Do not place bank, invoice, chat, contract or contact data in crash reports,
  notification previews, URLs, screenshots, clipboard history or debug logs.
- A biometric app lock may protect local access but does not replace server
  authentication, expiry, password confirmation or remote revocation.

## Required Before Production Mobile Release

The following cannot be completed meaningfully until the production-like API
and installable native builds exist:

1. Test TLS, DNS, proxy and production security-header configuration.
2. Perform authenticated penetration and object-authorization testing against
   every mobile operation, including concurrency and rate-limit bypass tests.
3. Proxy iOS and Android release builds to verify no secrets or sensitive data
   leak through requests, logs, analytics or third-party SDKs.
4. Test offline database and document extraction, backup restoration,
   screenshots, clipboard behavior, rooted/jailbroken devices and app logs.
5. Run the lost-device flow end to end and verify the revoked device removes
   all local private content when its next request is rejected.
6. Review push-notification privacy and App Store/Play privacy declarations.
7. Arrange an independent penetration test before launch and after material
   authentication, payment, chat or document-storage changes.

Any failure in this production/mobile phase is a release blocker even when the
Laravel feature suite passes.
