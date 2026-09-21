# Testing

## Purpose

Define testing expectations for DancePro V2 development.

## Current Status

Feature tests cover authentication, user access, generic download links and
signing, Competition object browsing, the Concert/media domain, public Concert
access, staff studio/concert management, local demonstration data and
maintenance behavior.

Playwright regression tests exercise the public booking form, Concert playlist,
admin and crew login boundaries, Admin-to-My-Hub navigation and booking contact
review in desktop and mobile Chromium. They use Sail's dedicated `testing` MySQL
database, which is recreated and populated with fictional local-development data
before each run. The normal development database is not modified. Production-like
S3 or CloudFront delivery still requires separate playback, seeking and
failure-state validation.

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

- Active staff/admin media abilities are enforced; customer, inactive, expired
  and unrelated tokens cannot request uploads.
- Staff media collection and asset authorization and validation.
- Client relative paths and presigned requests cannot escape the reserved asset
  prefix.
- Reservation, import and finalisation remain idempotent across retries.
- Large multipart MP4 uploads can resume and fail safely on invalid checksums.
- Multipart completion reconciles an existing object after a lost S3 response
  or rolled-back database write, checking both size and CRC64NVME before success.
- Old staff wildcard tokens retain competition/download access but cannot use
  media endpoints; explicit expiry, revocation and account restrictions remain.
- Reservations and all upload operations reject legacy disks and aliases of the
  legacy bucket before contacting storage.
- Legacy listing passes a slash-terminated prefix and excludes neighbouring keys.
- HLS finalisation rejects empty/invalid masters, missing requested renditions,
  invalid VOD playlists, absent initialization maps and empty segments. A large
  fixture verifies paginated listing without per-segment HEAD requests.
- HLS package validation rejects missing child objects, absolute references and
  path traversal, and uploads the master manifest last.
- A compressed fallback-only MP4 finalises and plays without HLS.
- Legacy imports remain inside the server-known collection prefix.
- Missing storage objects are handled safely.
- Playback authorization produces only short-lived signed delivery.
- Production media responses support byte-range seeking.
- Concert originals use tracked download links with expiry, revocation and
  access logging.
- Program and cover upload validation.
- Existing password, approval, availability, playlist and download behavior
  does not regress.
- Automated desktop and mobile Chromium checks for booking-form conditional
  fields, Concert playlist selection, role-based hub access and booking contact
  review.
- Manual checks for playback, seeking, fullscreen item transitions, final-item
  behavior and production delivery failures.

## Links to Related Documentation

- [Development Environment](Development-Environment.md)
- [API Guidelines](API-Guidelines.md)
- [Authentication Specification](../specifications/Authentication.md)
- [Flutter Desktop Media Ingest API](../specifications/Flutter-Desktop-Media-Ingest-API.md)

## Notes / Future Work

Add feature-specific test expectations to the relevant specification or epic
when behaviour becomes detailed enough to warrant it.
