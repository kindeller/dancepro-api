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

The target media-ingest design uses a macOS Flutter converter/uploader. Flutter
authenticates to Laravel and uploads directly to S3 using short-lived presigned
requests for exact server-allocated object keys. The required Laravel media and
upload endpoints are not yet implemented. The installed Laravel S3 adapter can
generate a presigned `PutObject` request, and the installed AWS SDK can
coordinate multipart uploads once application actions and policies are added.

## Scope

- AWS credentials must remain server-side and must not be exposed to client
  applications.
- Desktop applications must not embed access keys or depend on AWS CLI login for
  their normal operation.
- Private S3 buckets and CloudFront/S3 signing should remain behind Laravel
  actions or services.
- Controllers must not contain S3 operations or CloudFront signing logic.
- Public competition download access should use Laravel tracking links before
  redirecting to short-lived signed URLs.
- Public concert original downloads should use the same tracking-link workflow.
- Concert playback should be authorised by Laravel and delivered using a
  short-lived URL that supports byte-range requests.
- New concert uploads should send bytes directly from Flutter to private S3
  using short-lived requests scoped to one server-allocated asset prefix.

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

On EC2, prefer an instance profile with a least-privilege IAM role and leave
long-lived access-key environment values unset where the deployed SDK credential
chain supports that configuration. A server-side static key is still sensitive
and should not be copied into Flutter or shared with client staff.

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

### Desktop media ingest

The web admin is the primary interface for studio/concert management,
publication, availability and customer access. Flutter is limited to selecting
an existing concert, converting media, uploading it, importing a permitted
legacy MP4 and updating operational asset metadata.

The target upload flow is:

1. An active staff/admin account authenticates to Laravel with a limited Sanctum
   token stored in the macOS Keychain.
2. Laravel reserves the collection/asset UUID and derives
   `{collection_uuid}/media/{asset_uuid}/`.
3. Flutter reports the intended relative paths, sizes, content types and
   checksums.
4. Laravel returns short-lived presigned requests for only those exact objects.
5. Flutter uploads bytes directly to S3. Large MP4s use multipart upload;
   manifests, playlists, segments and thumbnails use single-object uploads.
6. Laravel verifies the completed package and marks the asset available.
7. Web staff review and publish it separately.

The client must not choose a bucket, submit an unrestricted authoritative key,
receive reusable AWS credentials or obtain deletion permission. Presigned URLs
are temporary bearer capabilities and must be excluded from application,
proxy, analytics and crash logs.

The minimum new playback package contains a compressed 720p
`stream/fallback.mp4`. HLS is optional and is limited to a 720p rendition plus
an optional 480p rendition. Do not generate a high-resolution streaming
rendition. Keep `original/video.mp4` for protected downloads and out of the HLS
master manifest.

For HLS, upload and verify every child object before issuing the upload request
for `stream/master.m3u8`. Use object checksums, verify them during finalisation,
and make finalisation idempotent. Configure lifecycle handling for abandoned
incomplete multipart uploads.

The existing resolver uses one `storage_disk` for all renditions of an asset.
An old MP4 can play directly from `s3_concerts_legacy`, but an original in that
bucket cannot currently be combined with HLS in `s3_concerts` as one asset.

Enable encrypted access logging, CloudTrail S3 data events for relevant write
paths, and CloudWatch metrics and alarms for upload and delivery failures. Keep
Block Public Access enabled, ACLs disabled, encryption at rest enabled and all
transport over TLS. Scope the Laravel IAM role to required actions and prefixes,
with source-account and source-resource conditions where applicable.

See the complete target contract in
[Flutter Desktop Media Ingest API](../specifications/Flutter-Desktop-Media-Ingest-API.md).

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
- [ADR-0003 - Desktop Media Ingest and Assignment](../decisions/ADR-0003-Desktop-Media-Ingest-and-Assignment.md)
- [Flutter Desktop Media Ingest API](../specifications/Flutter-Desktop-Media-Ingest-API.md)
- [DancePro V1 S3 Structure](V1-S3-Structure.md)
- [Competition Downloads Specification](../specifications/Competition-Downloads.md)
- [Concert Epic](../epics/Concert.md)
- [Milestone 03 - Concert Production Readiness](../milestones/Milestone-03-Concert-Production-Readiness.md)
- [Download Links Specification](../specifications/Download-Links.md)
- [Security](Security.md)
- [Architecture](Architecture.md)
- [Amazon S3 presigned uploads](https://docs.aws.amazon.com/AmazonS3/latest/userguide/using-presigned-url.html)
- [Amazon S3 multipart upload](https://docs.aws.amazon.com/AmazonS3/latest/userguide/mpuoverview.html)
- [Amazon S3 upload integrity](https://docs.aws.amazon.com/AmazonS3/latest/userguide/checking-object-integrity-upload.html)

## Notes / Future Work

Replace the configuration-work checklist above with the verified production
values and operational procedure as the Concert integration is implemented.
Never record credentials or private-key contents in this repository.
