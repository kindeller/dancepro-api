# ADR-0010: Isolate local media from AWS and CloudFront

## Status

Accepted

## Context

The merged media ingest and concert-delivery implementation relies on S3 and
CloudFront in production. Local development must remain usable without AWS
credentials and must not accidentally read or write production data when an
environment file contains copied cloud settings.

## Decision

When `APP_ENV=local`, keep the logical AWS disk names but map them to separate
private local roots. Use signed Laravel PUT URLs for local media ingest and
verify uploaded checksums and multipart receipts before finalisation. Serve
local concert media, covers and programs through access-checked application
routes; use a byte-range-capable file response for video. Never sign CloudFront
URLs in local mode. Refuse to start local mode with remote database, mail,
queue or Redis settings, or a stale configuration that still selects an AWS
driver. Require a local/private `APP_URL` and the expected isolated concert
disk names so signed upload URLs cannot accidentally target a public host.
Do not dispatch mobile push notifications from local development.
Hide external cover, program and gallery URLs in local presentation and public
API responses without changing stored values.

In non-local environments, retain the production S3 multipart and CloudFront
paths. Local PUT endpoints fail closed outside local mode.

## Consequences

Local development needs no S3 emulator and does not consume S3/CloudFront
bandwidth. Local files cannot stand in for production objects, and production
media credentials/settings are still required for deployment validation.
PHP handles video bytes locally, so performance testing must use the production
delivery path. The local client must be able to reach `APP_URL`, and local
multipart uploads require enough private disk space for temporary parts and
the completed object during assembly.
