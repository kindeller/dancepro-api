# Testing

## Purpose

Define testing expectations for DancePro V2 development.

## Current Status

Feature tests cover authentication, user access, generic download links and
signing, Competition object browsing, the Concert/media domain, public Concert
access, staff studio/concert management, local demonstration data and
maintenance behavior.

Playwright regression tests exercise the public booking form and Concert
playlist in desktop and mobile Chromium. They use Sail's dedicated `testing`
MySQL database, which is recreated and populated with fictional
local-development data before each run. The normal development database is not
modified. Production-like S3 or CloudFront delivery still requires separate
playback, seeking and failure-state validation.

## Scope

After application code changes, run:

```bash
sail artisan test
```

If routes changed, also run:

```bash
sail artisan route:list
```

For JavaScript-heavy browser workflows, install Chromium once and run:

```bash
sail npm run test:browser:install
sail npm run test:browser
```

The browser suite builds production assets before starting Laravel and does
not use the normal application database. Add focused browser coverage when a
workflow depends materially on client-side state or event handling.

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
- Automated desktop and mobile Chromium checks for booking-form conditional
  fields and Concert playlist selection.
- Manual checks for playback, seeking, fullscreen item transitions, final-item
  behavior and production delivery failures.

## Links to Related Documentation

- [Development Environment](Development-Environment.md)
- [API Guidelines](API-Guidelines.md)
- [Authentication Specification](../specifications/Authentication.md)

## Notes / Future Work

Add feature-specific test expectations to the relevant specification or epic
when behaviour becomes detailed enough to warrant it.
