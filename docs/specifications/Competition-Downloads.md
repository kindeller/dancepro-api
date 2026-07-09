# Competition Downloads

## Purpose

Specify the intended secure workflow for competition file downloads.

## Current Status

Competition consumes the generic Downloads bounded context. Download link
records, access logging, expiry, revocation, and signing are not owned by
Competition.

Competition also exposes a shallow object browser for the `s3_competitions`
disk so admin users and API clients can inspect available competition storage
folders and files before creating download links.

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

## Object Browser

Authenticated clients can request immediate folders and files under a storage
prefix:

```text
GET /api/competitions/objects
GET /api/competitions/objects?prefix=competition-a
GET /api/competitions/objects?prefix=competition-a&limit=100&continuation_token=...
```

The admin portal exposes the same listing at:

```text
GET /admin/competitions/objects
GET /admin/competitions/objects/chunk
```

The object browser is read-only, fixed to the `s3_competitions` disk, and does
not return signed file URLs. S3-backed listings use shallow paged requests with
`limit` and `continuation_token` so large folders can be loaded incrementally.
The admin portal automatically loads 25-object chunks up to a 250-object soft
cap before showing a manual continue control.
Download access should still use database-backed tracking links.

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
