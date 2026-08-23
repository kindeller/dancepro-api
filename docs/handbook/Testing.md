# Testing

## Purpose

Define testing expectations for DancePro V2 development.

## Current Status

Feature tests cover authentication, user access, generic download links and
signing, Competition object browsing, the Concert/media domain, public Concert
access, staff studio/concert management, local demonstration data and
maintenance behavior.

Browser playback has also been manually checked on desktop and mobile across
multiple browsers. The current playlist advances automatically without closing
the active player. Production-like S3 or CloudFront delivery still requires
separate playback, seeking and failure-state validation.

## Scope

After application code changes, run:

```bash
sail artisan test
```

If routes changed, also run:

```bash
sail artisan route:list
```

Initial baseline coverage should include:

- Successful login.
- Invalid login.
- Inactive users cannot log in.
- Authenticated users can call `/api/auth/me`.
- Unauthenticated users cannot access protected routes.

Current Concert production-readiness coverage should include:

- Staff media collection and asset authorization and validation.
- Submitted object keys cannot escape the configured collection prefix.
- Missing storage objects are handled safely.
- Playback authorization produces only short-lived signed delivery.
- Production media responses support byte-range seeking.
- Concert originals use tracked download links with expiry, revocation and
  access logging.
- Program and cover upload validation.
- Existing password, approval, availability, playlist and download behavior
  does not regress.
- Manual desktop and mobile checks for playback, seeking, fullscreen item
  transitions, final-item behavior and delivery failures.

## Links to Related Documentation

- [Development Environment](Development-Environment.md)
- [API Guidelines](API-Guidelines.md)
- [Authentication Specification](../specifications/Authentication.md)

## Notes / Future Work

Add feature-specific test expectations to the relevant specification or epic
when behaviour becomes detailed enough to warrant it.
