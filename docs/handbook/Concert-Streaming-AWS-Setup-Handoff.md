# Concert Streaming AWS Setup Handoff

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

## Canonical V2 Object Layout

The initial streaming format is video-on-demand HLS using fragmented MP4
segments. Two renditions are expected: `high` and `standard`.

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
│       │   ├── high.m3u8
│       │   ├── high-init.mp4
│       │   ├── high-000001.m4s
│       │   ├── high-000002.m4s
│       │   ├── standard.m3u8
│       │   ├── standard-init.mp4
│       │   ├── standard-000001.m4s
│       │   ├── standard-000002.m4s
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
```

The private signing key must be supplied through the project's approved secret
management process. Never paste it into source control, documentation, issue
comments, chat messages or command output.

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

Define separate least-privilege access for application delivery and media
upload/processing.

The Laravel runtime should receive only the S3 and signing access required by
its implemented workflows. It must not receive bucket-administration or broad
account permissions.

The upload/FFmpeg process may require permission to write within an explicitly
selected collection or asset prefix. It should not receive deletion permission
unless a separately designed and approved cleanup workflow requires it.

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
- Both high and standard renditions play.
- Automatic quality switching works under throttled bandwidth.
- Manual quality selection works where supported by the frontend player.
- Seeking works and MP4 range requests return partial content.
- The fallback MP4 plays when the HLS manifest is absent.
- The original MP4 plays when both streaming sources are absent.
- Content types and CORS response headers are correct.
- Cache behaviour does not leak authorisation or cache private cookies as
  content variants.
- Logs do not contain cookies, private keys or signed policy values.

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
- [FFmpeg HLS muxer](https://ffmpeg.org/ffmpeg-formats.html#hls-2)

