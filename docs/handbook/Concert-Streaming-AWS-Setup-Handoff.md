# Concert Streaming AWS Setup Handoff

For a paste-ready interactive setup prompt, see
[Concert Streaming AWS ChatGPT Prompt](Concert-Streaming-AWS-ChatGPT-Prompt.md).

## Purpose

This document is a handoff for configuring the AWS infrastructure required by
DancePro V2 concert media. It records the agreed application and storage
boundaries so the AWS setup can be completed without redesigning them.

This handoff does not authorise infrastructure changes by an AI agent. Review
the proposed resources, permissions and costs before applying them.

## Agreed Direction

- New V2 concert media will use a dedicated private S3 bucket named
  `dance-pro-concerts`.
- The existing `dance-pro-videos` bucket remains the legacy V1 media source.
- New V2 object keys use immutable collection and media asset UUIDs.
- Studio names, concert names, slugs and other mutable business data must not
  appear in authoritative storage prefixes.
- Original MP4 downloads continue through Laravel's tracked-download workflow.
- HLS playback uses CloudFront signed cookies because a stream consists of a
  manifest and multiple protected files.
- Laravel performs playback authorisation and generates the signed cookies.
- CloudFront delivers manifests, segments and MP4 fallback content.
- S3 remains private and must not be exposed as the public delivery endpoint.
- The macOS Flutter application converts media and uploads bytes directly to S3
  only through short-lived, Laravel-authorised presigned requests.
- No long-lived AWS credential or CloudFront signing key is stored in Flutter.
- Studio and concert management remains in the web admin. Flutter selects an
  existing concert and manages the lower-level media ingest workflow.

## Canonical V2 Object Layout

The initial streaming format is video-on-demand HLS using fragmented MP4
segments. The maximum streaming rendition is 720p. A 480p rendition may be
included for adaptive bandwidth reduction. High-resolution and source-quality
streaming renditions are intentionally excluded; the original remains available
only through the protected download workflow.

Variant files may remain directly inside `stream/`; separate rendition folders
are not required. FFmpeg output names must prevent collisions between the two
renditions.

```text
{collection_uuid}/
├── media/
│   └── {asset_uuid}/
│       ├── original/
│       │   └── video.mp4
│       ├── stream/
│       │   ├── master.m3u8
│       │   ├── 720p.m3u8
│       │   ├── 720p-init.mp4
│       │   ├── 720p-000001.m4s
│       │   ├── 720p-000002.m4s
│       │   ├── 480p.m3u8
│       │   ├── 480p-init.mp4
│       │   ├── 480p-000001.m4s
│       │   ├── 480p-000002.m4s
│       │   └── fallback.mp4
│       └── thumbnail/
│           └── poster.png
└── documents/
    └── program.pdf
```

The numbered segment names above are illustrative. The FFmpeg process may
produce a different zero-padding width, but all referenced paths must remain
relative to the asset's `stream/` prefix.

## Playback Resolution Order

The application will resolve playback sources in this order:

```text
stream/master.m3u8
    ↓ unavailable
stream/fallback.mp4
    ↓ unavailable
original/video.mp4
```

Streaming readiness does not need a separate database flag. Concert media is
uploaded completely before release, and the backend may resolve the available
source from the expected object keys.

The original MP4 fallback remains allowed. It is existing supported behaviour
and does not require a feature flag.

`stream/fallback.mp4` is the minimum required playback output for a newly
converted asset. HLS is optional. If the converter cannot produce a valid HLS
package, it must omit `master.m3u8` and finalise the asset as fallback-only.

The initial encoding targets are a compressed H.264/AAC 720p fallback and HLS
rendition near a 2 Mbps maximum, plus an optional 480p rendition near a 1 Mbps
maximum. These are starting points based on current AWS MediaConvert QVBR
guidance. Representative high-motion dance footage must be reviewed before the
profile is accepted.

## Desktop Ingest Workflow

The target Flutter integration is documented in
[Flutter Desktop Media Ingest API](../specifications/Flutter-Desktop-Media-Ingest-API.md).
The corresponding Laravel endpoints are not yet implemented.

The intended flow is:

1. Flutter authenticates to Laravel using an active staff/admin account and a
   limited Sanctum device token stored in the macOS Keychain.
2. Flutter selects an existing staff-visible studio and concert.
3. Laravel creates or selects a collection, reserves the asset UUID and derives
   its immutable object prefix.
4. Flutter converts the source locally and submits a file inventory containing
   relative paths, sizes, content types and checksums.
5. Laravel returns short-lived, object-specific presigned upload requests.
   Small HLS objects use `PutObject`; large MP4s use server-coordinated
   multipart upload.
6. Flutter transfers bytes directly to S3 and reports completion to Laravel.
7. HLS child objects are uploaded and verified before `master.m3u8` is signed
   and uploaded last.
8. Laravel verifies object metadata and playlist references, then changes the
   asset from `processing` to `available`.
9. Web staff review and publish the collection/concert. Upload finalisation does
   not publish customer content automatically.

Flutter must not receive a reusable AWS access key, choose a bucket, submit an
authoritative full object key or obtain delete permission. AWS CLI access is for
authorised operators and diagnostics only, not normal application operation.

An existing legacy MP4 may be attached without conversion. Laravel must list
only the server-known legacy collection prefix, return an opaque object
reference, verify the selected object, and create the media asset on the legacy
disk. The client must not receive unrestricted legacy-bucket browsing.

The current playback resolver reads original, HLS and fallback objects from one
`storage_disk`. Until it becomes location-aware, keep every active package on
one disk. Do not assume an original in `s3_concerts_legacy` can be combined with
HLS in `s3_concerts` merely through location metadata.

## Required AWS Components

### S3 bucket

Create or prepare the private bucket:

```text
dance-pro-concerts
```

Confirm and record:

- AWS account and region.
- Block Public Access is enabled.
- Object ownership settings do not depend on public ACLs.
- Default server-side encryption is enabled.
- S3 Versioning decision and lifecycle treatment for non-current versions.
- Lifecycle and archival rules appropriate for original videos, streaming
  renditions and incomplete multipart uploads.
- CORS permits only the required DancePro frontend origins and methods.
- Object metadata uses appropriate media content types.

Expected content types include:

```text
.m3u8  application/vnd.apple.mpegurl
.m4s   video/iso.segment
.mp4   video/mp4
.png   image/png
.pdf   application/pdf
```

### CloudFront distribution

Create or prepare a CloudFront distribution with:

- `dance-pro-concerts` as a private S3 origin.
- Origin Access Control rather than public bucket access.
- An S3 bucket policy restricted to the intended CloudFront distribution.
- HTTPS-only viewer access.
- A custom media hostname where available.
- Trusted key-group enforcement for private concert media.
- Range-request support for MP4 playback and downloads.
- Caching suitable for immutable UUID-based objects.
- Correct response headers and CORS behaviour for HLS playback.
- Access logging and monitoring appropriate for production operations.

Do not grant public S3 access as a workaround for CloudFront configuration.

### CloudFront signing

Create a CloudFront public key and trusted key group for application-generated
signed cookies. Store the corresponding private key server-side only.

The application will need non-secret configuration equivalent to:

```text
CLOUDFRONT_CONCERT_DOMAIN=
CLOUDFRONT_CONCERT_KEY_PAIR_ID=
CLOUDFRONT_CONCERT_COOKIE_DOMAIN=
```

The private signing key must be supplied through the project's approved secret
management process. Never paste it into source control, documentation, issue
comments, chat messages or command output.

The standard file-based runtime location is
`storage/app/private/keys/dancepro-concerts-private.pem`, configured as
`CLOUDFRONT_CONCERT_PRIVATE_KEY_PATH=app/private/keys/dancepro-concerts-private.pem`.
Laravel resolves the environment value through `storage_path()`. The key file
must be deployed separately and must remain outside source control.

Signed-cookie policies should:

- Restrict access to the selected asset's media prefix.
- Use the shortest practical playback expiry.
- Use secure cookies.
- Use `HttpOnly` where compatible with the delivery flow.
- Scope cookie domain and path as narrowly as practical.
- Avoid exposing signing material to frontend JavaScript.

CloudFront signed cookies use three values:

```text
CloudFront-Policy
CloudFront-Signature
CloudFront-Key-Pair-Id
```

### Domain and browser delivery

Prefer a first-party media hostname, for example:

```text
media.{dancepro-domain}
```

Choose the API, frontend and media hostnames together so signed cookies work
reliably across supported browsers. If playback is cross-origin, explicitly
verify credentialed requests and CORS behaviour for the master playlist,
variant playlists, initialization files and media segments.

## IAM Boundary

Define least-privilege access for application delivery and upload signing.

The Laravel runtime should receive only the S3 and signing access required by
its implemented workflows. It must not receive bucket-administration or broad
account permissions.

When Laravel runs on EC2, prefer a least-privilege instance profile over
long-lived access keys in deployment environment variables. The application
still generates presigned requests from that role; the role credential is never
returned to Flutter.

The Flutter/FFmpeg process does not receive an IAM principal. Laravel uses its
server-side role to create presigned requests restricted to the exact selected
asset prefix and required operation. The runtime should receive only the S3
actions needed to create those requests and coordinate multipart uploads. Do
not grant deletion permission unless a separately designed and approved cleanup
workflow requires it.

CloudFront signing uses the private key locally; it does not require broad
CloudFront administration permissions at application runtime.

## Application Configuration Boundary

The existing Laravel disk name remains:

```text
s3_concerts
```

Configure it to use `dance-pro-concerts` for V2. If V1 media must remain
available during migration, configure a separate legacy disk rather than
making one disk ambiguously address both buckets.

Suggested conceptual mapping:

```text
s3_concerts         → dance-pro-concerts
s3_concerts_legacy  → dance-pro-videos
s3_competitions     → existing competition bucket
```

Final environment-variable names must follow the Laravel configuration in this
repository. Do not duplicate credentials when an approved shared or
role-based mechanism is available.

## Validation Checklist

Complete these checks with synthetic, non-sensitive test media before the
infrastructure is considered ready:

- Direct public S3 object access is denied.
- CloudFront rejects an unsigned manifest request.
- A valid signed-cookie session can load `master.m3u8` and every referenced
  child object.
- Expired cookies are rejected.
- The cookie policy cannot access another asset UUID prefix.
- The 720p rendition plays and the optional 480p rendition is selected under
  constrained bandwidth.
- Automatic quality switching works under throttled bandwidth.
- Manual quality selection works where supported by the frontend player.
- Seeking works and MP4 range requests return partial content.
- The fallback MP4 plays when the HLS manifest is absent.
- The original MP4 plays when both streaming sources are absent.
- Content types and CORS response headers are correct.
- Cache behaviour does not leak authorisation or cache private cookies as
  content variants.
- Logs do not contain cookies, private keys or signed policy values.
- Flutter contains no embedded AWS credential and stores its Sanctum token in
  the macOS Keychain.
- Customer and inactive accounts cannot obtain upload requests.
- Presigned requests cannot write outside the reserved asset prefix or after
  expiry.
- Interrupted multipart MP4 upload can resume, complete and pass checksum
  validation.
- Upload finalisation is idempotent and does not publish the concert.
- A fallback-only asset finalises and plays without `master.m3u8`.
- An imported legacy MP4 is constrained to its server-known collection prefix.

## Information to Return to the Application Team

Return only non-secret deployment information:

- Bucket region.
- CloudFront distribution ID.
- CloudFront domain and custom media hostname.
- CloudFront public-key/key-pair ID.
- Trusted key-group ID.
- Required CORS origins.
- Confirmed cookie domain and path strategy.
- Required non-secret environment configuration.
- Secret-manager reference or deployment mechanism for the private signing key,
  without returning the key itself.
- Lifecycle, logging and monitoring decisions.
- Validation results and any known browser limitations.

## Official References

- [CloudFront private content](https://docs.aws.amazon.com/AmazonCloudFront/latest/DeveloperGuide/PrivateContent.html)
- [Choosing signed URLs or signed cookies](https://docs.aws.amazon.com/AmazonCloudFront/latest/DeveloperGuide/private-content-choosing-signed-urls-cookies.html)
- [Using CloudFront signed cookies](https://docs.aws.amazon.com/AmazonCloudFront/latest/DeveloperGuide/private-content-signed-cookies.html)
- [Restricting S3 origin access with Origin Access Control](https://docs.aws.amazon.com/AmazonCloudFront/latest/DeveloperGuide/private-content-restricting-access-to-s3.html)
- [Amazon S3 presigned uploads](https://docs.aws.amazon.com/AmazonS3/latest/userguide/using-presigned-url.html)
- [Amazon S3 multipart upload](https://docs.aws.amazon.com/AmazonS3/latest/userguide/mpuoverview.html)
- [Amazon S3 upload integrity](https://docs.aws.amazon.com/AmazonS3/latest/userguide/checking-object-integrity-upload.html)
- [MediaConvert QVBR guidance](https://docs.aws.amazon.com/mediaconvert/latest/ug/qvbr-guidelines.html)
- [FFmpeg HLS muxer](https://ffmpeg.org/ffmpeg-formats.html#hls-2)
