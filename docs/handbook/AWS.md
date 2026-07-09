# AWS

## Purpose

Document stable AWS guidance for DancePro V2 integrations.

## Current Status

AWS usage is currently documented only at the principle level. Competition
downloads are expected to use private storage and server-side signed redirects.

## Scope

- AWS credentials must remain server-side and must not be exposed to client
  applications.
- Private S3 buckets and CloudFront/S3 signing should remain behind Laravel
  actions or services.
- Controllers must not contain S3 operations or CloudFront signing logic.
- Public competition download access should use Laravel tracking links before
  redirecting to short-lived signed URLs.

## Competition Downloads

Competition download links use the `s3_competitions` filesystem disk. Configure
that disk with the competition-specific environment variables:

```text
AWS_COMPETITIONS_ACCESS_KEY_ID=
AWS_COMPETITIONS_SECRET_ACCESS_KEY=
AWS_COMPETITIONS_DEFAULT_REGION=
AWS_COMPETITIONS_BUCKET=
AWS_COMPETITIONS_URL=
AWS_COMPETITIONS_ENDPOINT=
AWS_COMPETITIONS_USE_PATH_STYLE_ENDPOINT=false
```

If the competition-specific access key, secret, or region are not set, the disk
falls back to the shared `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, and
`AWS_DEFAULT_REGION` values.

Downloads should allow only the disks that are intended to be exposed through
tracking links:

```text
DOWNLOAD_ALLOWED_DISKS=s3_competitions
DOWNLOAD_DEFAULT_DISK=s3_competitions
```

## Links to Related Documentation

- [Competition Downloads Specification](../specifications/Competition-Downloads.md)
- [Security](Security.md)
- [Architecture](Architecture.md)

## Notes / Future Work

Add concrete bucket, CloudFront, IAM, key rotation, and environment variable
guidance when the AWS integration is implemented.
