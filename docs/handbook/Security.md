# Security

Phase 0 establishes the security defaults for later DancePro features.

- Secrets must stay in `.env` and must not be committed.
- API authentication uses Laravel Sanctum bearer tokens.
- Sanctum tokens use explicit abilities; wildcard abilities are not issued.
- Sensitive API routes enforce token abilities in addition to current user permissions.
- Versioned mobile API responses use `Cache-Control: no-store, private` and
  `Pragma: no-cache` to prevent shared HTTP caches retaining crew data.
- Account passwords are hashed through Laravel's password hashing cast/factory helpers.
- Concert shared access codes are hashed for verification and also encrypted at
  rest so authorised staff can view and share them. Never expose the decrypted
  code on public pages or in general API responses. Existing hash-only codes
  must be reset before they can be displayed. See
  [ADR-0005](../decisions/ADR-0005-Retrievable-Concert-Access-Codes.md).
- Inactive users cannot log in.
- Browser and API login attempts are rate limited by hashed email/IP and by IP.
- Password-reset link requests and reset submissions have separate rate limits.
- Public booking submissions and download links have endpoint-specific rate
  limits. Limit events log only hashed IP/token identifiers.
- Public concert playback lookup, media streaming and media download routes
  have independent per-asset/IP and broader per-IP minute limits. Defaults are
  configurable through the `CONCERT_*_RATE_LIMIT_PER_MINUTE` settings.
- The booking form uses a honeypot and suppresses identical submissions made
  within `BOOKING_DUPLICATE_WINDOW_MINUTES` (10 minutes by default).
- Download access records are pruned daily after
  `DOWNLOAD_ACCESS_RETENTION_DAYS` (180 days by default). Production must run
  Laravel's scheduler for this and other scheduled maintenance.
- Protected API routes must use `auth:sanctum`.
- Every application response sets MIME-sniffing, same-origin framing,
  referrer and browser-feature restrictions. Production HTTPS responses also
  set a one-year HSTS policy.
- Content Security Policy is enabled in report-only mode by default. Inline
  script elements carry per-request nonces, while current inline styles and
  event-handler attributes remain explicitly allowed. Review browser reports
  and external media/embed requirements before setting `CSP_REPORT_ONLY=false`
  to enforce the policy. `CSP_ENABLED=false` is an emergency rollback only.
- Non-trivial input must use Form Requests.
- Authorization logic should use policies rather than controller conditionals.
- Private S3 buckets and CloudFront/S3 signing should remain server-side only.
- AWS credentials must never be exposed to a client application.
- Chat and notification text uses infrastructure-level encryption at rest under
  [ADR-0008](../decisions/ADR-0008-Communication-Data-Encryption.md). Production
  database storage, snapshots and backups must be encrypted, remote database
  connections must use authenticated TLS, and communication bodies must not be
  written to logs.
- Passwords, tokens, full payment details, identity-document numbers and medical
  information must not be sent through chat or notification content.
- The Flutter desktop application stores its Sanctum token in the macOS
  Keychain and never embeds a long-lived AWS access key.
- Direct-to-S3 uploads use short-lived, object-specific requests generated only
  after staff/media authorisation. Presigned URLs must be treated as secrets and
  redacted from logs.
- Media upload routes require both staff/admin account checks and limited token
  abilities. Customer accounts cannot upload or attach concert media.
- Token abilities do not replace resource scope. External client staff require
  explicit studio/concert assignments before they can use media endpoints; the
  current global staff role is limited to trusted DancePro operators.
- New upload keys are allocated by Laravel beneath an immutable asset prefix;
  clients cannot choose buckets or arbitrary storage keys.
- Private media buckets keep Block Public Access enabled, ACLs disabled,
  encryption at rest enabled and TLS required in transit.
- Relevant S3 write access should be observable through encrypted access logs,
  CloudTrail data events and CloudWatch metrics and alarms.
- Public download links should not expose raw database IDs.
- Filesystem failures must be thrown and reported rather than converted into
  apparent success. File paths are only persisted after storage succeeds.
- Replacing an operational resource, venue map or event programme deletes the
  superseded object from the configured private operational-files disk only
  after the new path has been stored and saved.
- Browser storage writes show a retryable error; API writes return HTTP 503
  using the shared response envelope. Media reads otherwise retain their
  existing behaviour.

## Related Documentation

- [Authentication](Authentication.md)
- [Mobile Security Verification](Mobile-Security-Verification.md)
- [Sensitive Data Retention and Key Recovery](Sensitive-Data-Retention-and-Key-Recovery.md)
- [API Guidelines](API-Guidelines.md)
- [AWS](AWS.md)
- [Competition Downloads Specification](../specifications/Competition-Downloads.md)
- [Flutter Desktop Media Ingest API](../specifications/Flutter-Desktop-Media-Ingest-API.md)
