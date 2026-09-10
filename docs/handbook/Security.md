# Security

Phase 0 establishes the security defaults for later DancePro features.

- Secrets must stay in `.env` and must not be committed.
- API authentication uses Laravel Sanctum bearer tokens.
- Passwords are hashed through Laravel's password hashing cast/factory helpers.
- Inactive users cannot log in.
- Protected API routes must use `auth:sanctum`.
- Non-trivial input must use Form Requests.
- Authorization logic should use policies rather than controller conditionals.
- Private S3 buckets and CloudFront/S3 signing should remain server-side only.
- AWS credentials must never be exposed to a client application.
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

## Related Documentation

- [Authentication](Authentication.md)
- [API Guidelines](API-Guidelines.md)
- [AWS](AWS.md)
- [Competition Downloads Specification](../specifications/Competition-Downloads.md)
- [Flutter Desktop Media Ingest API](../specifications/Flutter-Desktop-Media-Ingest-API.md)
