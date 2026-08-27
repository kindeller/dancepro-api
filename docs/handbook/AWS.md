# AWS

## Purpose

Document stable AWS guidance for DancePro V2 integrations.

## Current Status

The `s3_competitions`, `s3_concerts` and `s3_concerts_legacy` Laravel disks are
configured. Competition object browsing is implemented, and the generic
Downloads bounded context can redirect valid tracking links to short-lived S3
or CloudFront URLs.

Concert playback now resolves HLS, a progressive streaming fallback and the
recorded original in that order. The application can issue CloudFront signed
cookies for HLS after the distribution, trusted key group and signing
configuration are supplied. Progressive playback and original downloads retain
their existing Laravel filesystem responses pending the remaining production
delivery work.

## Scope

- AWS credentials must remain server-side and must not be exposed to client
  applications.
- Private S3 buckets and CloudFront/S3 signing should remain behind Laravel
  actions or services.
- Controllers must not contain S3 operations or CloudFront signing logic.
- Public competition download access should use Laravel tracking links before
  redirecting to short-lived signed URLs.
- Public concert original downloads should use the same tracking-link workflow.
- Concert playback should be authorised by Laravel and delivered using a
  short-lived URL that supports byte-range requests.

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

The admin competition object browser derives AWS Console links from
`AWS_COMPETITIONS_DEFAULT_REGION` and `AWS_COMPETITIONS_BUCKET`. The links do
not grant object access and do not replace tracked download links.

If the competition-specific access key, secret, or region are not set, the disk
falls back to the shared `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, and
`AWS_DEFAULT_REGION` values.

Downloads should allow only the disks that are intended to be exposed through
tracking links:

```text
DOWNLOAD_ALLOWED_DISKS=s3_competitions,s3_concerts,s3_concerts_legacy
DOWNLOAD_DEFAULT_DISK=s3_competitions
```

## Concert Media Delivery

New V2 concert media uses the `s3_concerts` filesystem disk backed by the
dedicated `dance-pro-concerts` bucket. Legacy V1 media uses
`s3_concerts_legacy`, backed by `dance-pro-videos`.

```text
AWS_CONCERT_ACCESS_KEY_ID=
AWS_CONCERT_SECRET_ACCESS_KEY=
AWS_CONCERT_DEFAULT_REGION=
AWS_CONCERT_BUCKET=
AWS_CONCERT_URL=
AWS_CONCERT_ENDPOINT=
AWS_CONCERT_USE_PATH_STYLE_ENDPOINT=false

AWS_CONCERT_LEGACY_ACCESS_KEY_ID=
AWS_CONCERT_LEGACY_SECRET_ACCESS_KEY=
AWS_CONCERT_LEGACY_DEFAULT_REGION=
AWS_CONCERT_LEGACY_BUCKET=
AWS_CONCERT_LEGACY_URL=
AWS_CONCERT_LEGACY_ENDPOINT=
AWS_CONCERT_LEGACY_USE_PATH_STYLE_ENDPOINT=false
```

If the concert-specific access key, secret, or region are not set, the disk
falls back to the shared `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, and
`AWS_DEFAULT_REGION` values.

### Playback

Laravel validates concert availability, asset ownership, visibility and the
customer's concert access. It returns an HLS manifest URL with short-lived
CloudFront signed cookies when HLS delivery is configured. The browser uses
native HLS or `hls.js` and falls back to the progressive MP4 route after a fatal
HLS error.

Configure playback using:

```text
CONCERT_PLAYBACK_SIGNED_URL_TTL_MINUTES=15
CLOUDFRONT_CONCERT_DOMAIN=
CLOUDFRONT_CONCERT_KEY_PAIR_ID=
CLOUDFRONT_CONCERT_PRIVATE_KEY=
CLOUDFRONT_CONCERT_PRIVATE_KEY_PATH=app/private/keys/dancepro-concerts-private.pem
CLOUDFRONT_CONCERT_COOKIE_DOMAIN=
CLOUDFRONT_CONCERT_COOKIE_PATH=/
CLOUDFRONT_CONCERT_COOKIE_SECURE=true
CLOUDFRONT_CONCERT_COOKIE_SAME_SITE=lax
```

For file-based signing, leave `CLOUDFRONT_CONCERT_PRIVATE_KEY` empty. Laravel
resolves `CLOUDFRONT_CONCERT_PRIVATE_KEY_PATH` through `storage_path()`, so the
value above points to
`storage/app/private/keys/dancepro-concerts-private.pem`. The directory is
excluded from Git; deploy the key separately and never commit its contents.

Validate at least:

- `Content-Type` matches the playable media.
- Byte-range requests return `206 Partial Content` where applicable.
- `Accept-Ranges` and `Content-Range` are correct.
- Seeking works on supported desktop and mobile browsers.
- The signed URL expires promptly and cannot escape the authorised asset.
- Streaming renditions are preferred where present; originals are not used for
  playback merely because they are available for download.

### Original download target

Concert originals should use a Laravel `/download/{token}` tracking URL backed
by Downloads. The tracking link validates expiry and revocation, logs access,
then redirects to a short-lived signed S3 or CloudFront response configured as
an attachment.

### Configuration work

Before production use, document and verify the concrete configuration for:

- Private concert and competition buckets.
- The CloudFront distribution or distributions serving those buckets.
- Origin access and least-privilege IAM permissions.
- Trusted key groups, public key IDs and private-key storage.
- Cache behavior for signed playback and download requests.
- CORS and response headers required by the player.
- Key rotation and signing-failure recovery.
- Separate behavior for inline streaming and attachment downloads.

If concert and competition media use different CloudFront distributions, the
application configuration must select the correct distribution for the storage
disk rather than assuming one domain serves every asset.

## Links to Related Documentation

- [Concert Streaming AWS Setup Handoff](Concert-Streaming-AWS-Setup-Handoff.md)
- [ADR-0002 - Concert Media Storage and Playback](../decisions/ADR-0002-Concert-Media-Storage-and-Playback.md)
- [DancePro V1 S3 Structure](V1-S3-Structure.md)
- [Competition Downloads Specification](../specifications/Competition-Downloads.md)
- [Concert Epic](../epics/Concert.md)
- [Milestone 03 - Concert Production Readiness](../milestones/Milestone-03-Concert-Production-Readiness.md)
- [Download Links Specification](../specifications/Download-Links.md)
- [Security](Security.md)
- [Architecture](Architecture.md)

## Notes / Future Work

Replace the configuration-work checklist above with the verified production
values and operational procedure as the Concert integration is implemented.
Never record credentials or private-key contents in this repository.
