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

## Links to Related Documentation

- [Competition Downloads Specification](../specifications/Competition-Downloads.md)
- [Security](Security.md)
- [Architecture](Architecture.md)

## Notes / Future Work

Add concrete bucket, CloudFront, IAM, key rotation, and environment variable
guidance when the AWS integration is implemented.
