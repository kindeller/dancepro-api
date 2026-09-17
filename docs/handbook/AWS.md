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
configuration are supplied. Progressive playback now uses an exact-object,
short-lived CloudFront signed URL for assets on `s3_concerts`. Playback from the
legacy disk or without signing configuration fails closed. Original downloads
still use Laravel filesystem responses.

The media-ingest API reserves exact object keys, issues short-lived presigned
requests and verifies multipart uploads. A staff-only web uploader under each
concert's **Upload media** page can send an original MP4 and a fallback MP4
directly from the browser to S3. It does not convert videos or create HLS.
The macOS Flutter converter/uploader can use the same API when ready.

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
- New concert uploads should send bytes directly from the browser or Flutter to private S3
  using short-lived requests scoped to one server-allocated asset prefix.

### Staff web uploader

From the admin concert edit page, select **Upload media**. The first upload
automatically creates a collection named after the concert; staff can add a
separate collection for another show. Choose or drop both MP4 files and upload.
The original filename supplies the initial video title, which staff can edit.
The uploaded filenames are retained in the catalogue and S3 object metadata;
the object keys remain the fixed `original/video.mp4` and
`stream/fallback.mp4` paths required by the ingest and playback contract.
For a batch, drop all MP4s into **Upload several videos**: `Ballet.mp4` pairs
with `Ballet-stream.mp4`. Review each match and edit its title before upload;
unmatched or duplicate files must be resolved first. The batch uses the same
direct-to-S3 multipart flow and creates one asset per pair. Concert downloads
use the recorded original filename, or the video title if that filename is a
generic storage name. Duplicate download names within a concert get a short
asset identifier so that downloading all videos does not overwrite them.
Finalisation verifies the required `original/video.mp4` and
`stream/fallback.mp4` objects. A verified
asset remains hidden until staff explicitly makes it visible; a collection
cannot be published without a verified visible asset. Keep the concert itself
in draft until playback has been checked on the production distribution.

The public playlist groups videos by published collection name while retaining
the collection and asset sort order. Each MP4 slot shows its own upload
progress, and the page shows combined byte progress for the current upload or
entire batch.

The concert edit page lists collections and their videos for renaming,
visibility and publishing. Managed collections also offer deletion. Before a
delete, staff must review the exact S3 object keys and count on a separate
confirmation page and type `DELETE`; the server checks that the list has not
changed before removing current objects and soft-deleting the database rows.
Legacy storage is excluded from this deletion flow. S3 Versioning and
retention, when enabled, may keep older versions. The managed concert bucket
credentials need scoped object listing and deletion permissions for this flow;
the legacy bucket must remain read-only.

The browser uses the existing multipart API with CRC-64/NVME checksums. It
uploads parts directly to S3 and sends only metadata to Laravel. The S3 bucket
must permit CORS `PUT` from the exact admin site origin, allow the signed
request headers (including `x-amz-checksum-crc64nvme`), and expose `ETag` to
browser JavaScript. Restrict CORS origins to the actual admin origin. The
browser keeps incomplete upload identifiers locally for retry with the same
files, resending parts when needed; close or clear failed multipart uploads through the existing API or a
bucket lifecycle rule after review. S3 multipart requests, incomplete parts,
storage and transfer incur charges. The browser must support BigInt and Web
Crypto, and checksum calculation can take time for large files. File extension
and checksum validation do not prove the fallback codec is browser-playable.

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
HLS error. The MP4 route redirects to an exact-object CloudFront signed URL;
progressive-only playback receives the signed URL directly. The CloudFront
cookie domain is required for HLS but is not required for signed MP4 URLs. A
local app on `localhost` can therefore test fallback MP4 playback through a
signed CloudFront URL without issuing cookies for an unrelated media domain.
The CloudFront behavior must require trusted key-group signatures, and its S3 origin must be
private and accessible only through an origin access control. Otherwise an
unsigned CloudFront URL or direct S3 URL may still expose the object despite
the application's signed URLs. Check this on the deployed distribution and
bucket before publishing video. Signed URL requests add CloudFront request and
transfer costs; they avoid proxying MP4 bytes through the EC2 application.

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
