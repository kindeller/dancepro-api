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
- Protected API routes must use `auth:sanctum`.
- Non-trivial input must use Form Requests.
- Authorization logic should use policies rather than controller conditionals.
- Private S3 buckets and CloudFront/S3 signing should remain server-side only.
- AWS credentials must never be exposed to a client application.
- Public download links should not expose raw database IDs.

## Related Documentation

- [Authentication](Authentication.md)
- [API Guidelines](API-Guidelines.md)
- [AWS](AWS.md)
- [Competition Downloads Specification](../specifications/Competition-Downloads.md)
