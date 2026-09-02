# Security

Phase 0 establishes the security defaults for later DancePro features.

- Secrets must stay in `.env` and must not be committed.
- API authentication uses Laravel Sanctum bearer tokens.
- Sanctum tokens use explicit abilities; wildcard abilities are not issued.
- Sensitive API routes enforce token abilities in addition to current user permissions.
- Passwords are hashed through Laravel's password hashing cast/factory helpers.
- Inactive users cannot log in.
- Browser and API login attempts are rate limited by hashed email/IP and by IP.
- Password-reset link requests and reset submissions have separate rate limits.
- Public booking submissions and download links have endpoint-specific rate
  limits. Limit events log only hashed IP/token identifiers.
- The booking form uses a honeypot and suppresses identical submissions made
  within `BOOKING_DUPLICATE_WINDOW_MINUTES` (10 minutes by default).
- Download access records are pruned daily after
  `DOWNLOAD_ACCESS_RETENTION_DAYS` (180 days by default). Production must run
  Laravel's scheduler for this and other scheduled maintenance.
- Protected API routes must use `auth:sanctum`.
- Non-trivial input must use Form Requests.
- Authorization logic should use policies rather than controller conditionals.
- Private S3 buckets and CloudFront/S3 signing should remain server-side only.
- AWS credentials must never be exposed to a client application.
- Public download links should not expose raw database IDs.
- Filesystem failures must be thrown and reported rather than converted into
  apparent success. File paths are only persisted after storage succeeds.
- Browser storage writes show a retryable error; API writes return HTTP 503
  using the shared response envelope. Media reads otherwise retain their
  existing behaviour.

## Related Documentation

- [Authentication](Authentication.md)
- [Sensitive Data Retention and Key Recovery](Sensitive-Data-Retention-and-Key-Recovery.md)
- [API Guidelines](API-Guidelines.md)
- [AWS](AWS.md)
- [Competition Downloads Specification](../specifications/Competition-Downloads.md)
