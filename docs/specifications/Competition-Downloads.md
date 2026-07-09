# Competition Downloads

## Purpose

Specify the intended secure workflow for competition file downloads.

## Current Status

Competition consumes the generic Downloads bounded context. Download link
records, access logging, expiry, revocation, and signing are not owned by
Competition.

## Scope

Competition downloads must use database-backed tracking links provided by
Downloads.

```text
Client
  ↓
Laravel tracking link
  ↓
Database logging
  ↓
Generate CloudFront signed URL
  ↓
302 redirect
```

The Laravel tracking URL is the public-facing URL. Long-lived CloudFront or S3
URLs must not be returned directly to clients.

Competition controllers should request links from Downloads when competition
workflows are introduced. S3 and CloudFront signing logic belongs in
`app/Features/Downloads/Services/DownloadUrlSigner`.

## Links to Related Documentation

- [Competition Epic](../epics/Competition.md)
- [Downloads Epic](../epics/Downloads.md)
- [Download Links Specification](Download-Links.md)
- [Milestone 02 - Competition](../milestones/Milestone-02-Competition.md)
- [AWS](../handbook/AWS.md)
- [Security](../handbook/Security.md)
- [Architecture](../handbook/Architecture.md)

## Notes / Future Work

Define future Competition-owned file metadata when Competition CRUD is planned.
Do not create `competitions` or `competition_files` solely for the current
storage-key-driven download workflow.
