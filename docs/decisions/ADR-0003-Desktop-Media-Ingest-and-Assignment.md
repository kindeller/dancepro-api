# ADR-0003 - Desktop Media Ingest and Assignment

## Status

Accepted as the target integration design. The Laravel API endpoints described
by this decision are not yet implemented.

## Context

DancePro has a macOS Flutter desktop application that converts edited concert
video into playback media and uploads the result. An earlier implementation
called AWS directly using a long-lived access key stored in the desktop
application. A distributed client cannot protect an embedded AWS secret, so
that design must not be restored.

DancePro V2 now provides the primary web administration experience for studios,
concerts, publication, availability and customer access. The desktop
application therefore no longer needs to duplicate broad business management.
Its main responsibility is lower-level media preparation:

- select an existing studio and concert;
- create or select a concert media collection;
- convert a source video;
- upload an original, a compressed MP4 fallback, an optional HLS package and a
  thumbnail;
- register and verify the resulting managed media asset;
- update operational asset metadata such as display name, order and visibility;
- attach an existing legacy MP4 when conversion is unavailable.

The current Laravel API implements Sanctum login, logout and current-user
endpoints, public read-only studio and concert discovery, Competition object
listing and download-link management. It does not currently implement concert
media discovery, upload signing, multipart upload coordination, legacy import,
asset assignment or staff studio/concert management endpoints.

Laravel's installed S3 filesystem adapter can generate a presigned `PutObject`
request. The installed AWS SDK can support multipart upload coordination, but
neither capability is currently exposed through an application endpoint.

## Decision

### Responsibility boundary

The web application remains the source of truth for studio and concert
management, approval, publication, availability windows and customer access.

The Flutter desktop application is a trusted staff tool for media ingest, but it
is not trusted with AWS credentials or arbitrary bucket access. Laravel owns:

- staff authentication and authorisation;
- allocation of collection and asset UUIDs;
- calculation and validation of S3 object keys;
- generation of short-lived upload requests;
- multipart upload creation, completion and abort coordination;
- verification of uploaded object metadata and HLS completeness;
- database creation and state transitions for collections, assets and upload
  sessions;
- constrained import of existing legacy objects;
- audit records for upload, import, finalisation and metadata changes.

Flutter owns:

- local media selection and conversion;
- calculation of file sizes and checksums;
- upload progress, retry and resume behaviour;
- direct transfer of object bytes to S3 using server-authorised requests;
- submission of final upload results to Laravel;
- presentation of server validation failures to staff.

### Authentication

Flutter authenticates to Laravel using an active staff or admin account and a
Laravel Sanctum bearer token. The token is stored in the macOS Keychain, never
in source code, application assets, logs or ordinary preferences.

Desktop tokens must receive server-selected, limited abilities. The initial
ability set is:

```text
concert-media:read
concert-media:upload
concert-media:update
```

The client must not be allowed to choose or expand its own abilities. Customer
accounts cannot receive media abilities. Login and upload-signing endpoints
must be rate limited. Tokens must expire according to an explicit production
policy, and logout revokes the current device token.

Use named staff accounts rather than a shared embedded or site-wide password so
upload and assignment actions have an accountable actor. The current staff role
is global. Until per-studio or per-concert staff assignments are implemented,
only trusted DancePro staff may use the uploader. Client-studio staff must not
receive production access merely by being labelled `staff`; their account must
be restricted to explicitly assigned studios or concerts by the media policy.

The existing wildcard `*` token issuance is a baseline implementation gap and
must be replaced or narrowed before media upload endpoints are exposed.

### Upload authorisation

No long-lived AWS access key, secret key, CloudFront signing key or bucket-wide
credential is distributed to Flutter. The AWS CLI is an operator and diagnostic
tool, not a runtime dependency or desktop authentication mechanism.

Laravel creates short-lived, object-specific presigned S3 requests after it has
authorised the user, concert, collection, asset and complete destination key.
The client receives only the URL, required request headers, expiry and an opaque
upload identifier.

Small objects such as manifests, playlists, segments and thumbnails use
presigned single-object uploads. Large original and fallback MP4 objects use
server-coordinated multipart upload so an interrupted transfer can resume
without restarting the entire file.

Every upload is restricted to an immutable prefix allocated by Laravel:

```text
{collection_uuid}/media/{asset_uuid}/
```

For a new asset, Flutter supplies relative file roles and metadata, not a
bucket, disk or authoritative full storage key. Laravel derives the final keys
and refuses paths outside the assigned prefix.

Presigned requests are bearer capabilities until they expire. They must be
short-lived, transmitted only over HTTPS, excluded from logs, and generated
from an IAM role limited to the required concert bucket and actions.

### Media package

The production streaming package intentionally excludes a high-resolution
streaming rendition. It may contain:

```text
{collection_uuid}/media/{asset_uuid}/
├── original/video.mp4
├── stream/master.m3u8
├── stream/720p.m3u8
├── stream/720p-init.mp4
├── stream/720p-{segment}.m4s
├── stream/480p.m3u8
├── stream/480p-init.mp4
├── stream/480p-{segment}.m4s
├── stream/fallback.mp4
└── thumbnail/poster.png
```

The original is retained for protected download and is not referenced by the
HLS master manifest. `stream/fallback.mp4` is the minimum reliable playback
output. HLS is optional and may contain one 720p rendition or a 720p and 480p
adaptive ladder. No 1080p or source-quality stream should be generated for the
initial production workflow.

The final bitrate profile must be validated against representative dance
footage. The initial MediaConvert-equivalent targets are a 720p H.264/AAC output
near a 2 Mbps maximum and an optional 480p output near a 1 Mbps maximum. These
are starting points rather than acceptance criteria; visible motion artefacts
must be checked before adopting them.

The existing playback resolution order remains:

```text
HLS -> compressed fallback MP4 -> canonical original -> recorded legacy MP4
```

### Upload state and publication

A new asset begins in `processing` and is invisible to customers. Flutter may
request upload URLs in batches and may renew expired URLs without changing the
asset UUID or destination keys.

The HLS master manifest is uploaded last. Laravel finalisation must verify:

- every declared object exists at the exact assigned key;
- reported size, content type and checksum match where available;
- `stream/fallback.mp4` exists and is a playable MP4;
- `original/video.mp4` exists when an original download was declared;
- every local URI referenced by the HLS master and variant playlists remains
  inside the asset prefix and resolves to an uploaded object;
- no unexpected absolute URL or path traversal appears in a playlist;
- the asset still belongs to the authorised collection and concert.

Only successful finalisation changes the asset to `available` and records
`verified_at`. Finalisation is idempotent. It does not publish the concert or
change customer-access settings. Publication remains a deliberate web-admin
operation.

### Existing and legacy MP4 import

Flutter can attach an existing MP4 without uploading it again. Laravel lists
objects only under the server-known legacy collection prefix and returns an
opaque object reference. The import endpoint accepts that reference rather than
an arbitrary bucket or unrestricted storage key.

Laravel resolves the reference, performs a low-impact object metadata check,
validates the file type and prefix, then creates the managed asset and records
the exact legacy disk and key. The imported asset can play through its recorded
MP4 key without HLS.

The current playback resolver reads all renditions for an asset from one
`storage_disk`. Until location-aware playback is implemented, a package must be
complete on one active disk. An original in the legacy bucket and HLS in the new
bucket cannot be combined as one playable asset merely by adding
`media_asset_locations` rows.

### Delivery

Laravel authorises playback and downloads, but it must not proxy production
video bytes through EC2. HLS and progressive MP4 playback are delivered through
CloudFront from private S3. MP4 delivery must support byte-range requests.
Original downloads use the generic Downloads tracking link before redirecting
to a short-lived CloudFront or S3 attachment URL.

### Storage protection and observability

The concert bucket remains private with S3 Block Public Access, bucket-owner
enforced object ownership, encryption at rest and TLS in transit. CloudFront
uses Origin Access Control. IAM policies are limited to the required bucket,
prefixes and actions, with source-account and source-resource conditions where
applicable.

Enable encrypted access logging, CloudTrail S3 data events for the relevant
write paths, CloudWatch metrics and alarms, and a lifecycle rule that aborts
incomplete multipart uploads. Logging must redact bearer tokens, presigned URL
query strings, signed cookies and private signing material.

The cost model includes original and derived-object storage, S3 PUT/GET and
multipart requests, CloudFront requests and viewer data transfer. Omitting the
high-resolution streaming rendition reduces stored derivatives and delivered
bytes. HLS adds request volume because it contains many objects; segment length
and rendition count should therefore remain proportionate to expected viewing.

## Consequences

- Flutter can upload directly to S3 without possessing a reusable AWS secret.
- Laravel remains the only authority that can assign uploaded objects to
  business entities.
- Upload traffic bypasses EC2, while API calls remain small and auditable.
- Large uploads can resume at part boundaries.
- A converter failure does not prevent release when a compressed or legacy MP4
  is available.
- Web administration and desktop media preparation have distinct, smaller
  responsibility surfaces.
- New Laravel endpoints, policies, token abilities, upload-session persistence
  and tests are required before the documented Flutter workflow is usable.
- Per-studio or per-concert account scoping is required before access is given
  to staff outside the trusted DancePro operational team.
- Multi-disk rendition resolution remains future work; current assets use one
  active disk.

## Alternatives considered

### Embedded AWS credentials

Rejected. A desktop binary cannot keep a long-lived access key secret, and the
credential would be reusable outside the intended workflow.

### Requiring AWS CLI login from Flutter

Rejected as the application workflow. CLI authentication remains useful for
authorised operators, but creates installation, session and account-coupling
problems for client staff and bypasses Laravel's business authorisation.

### Uploading video through Laravel

Rejected for production media. It would route large video bytes through EC2,
consume application workers and network capacity, and make retries less
efficient.

### Temporary STS credentials in Flutter

Deferred. Carefully scoped temporary credentials may reduce signing calls for
very large HLS packages, but they expose a broader temporary AWS capability and
require an additional credential-vending design. Object-specific presigned
requests provide the narrower initial boundary.

## Related documentation

- [Flutter Desktop Media Ingest API](../specifications/Flutter-Desktop-Media-Ingest-API.md)
- [ADR-0002 - Concert Media Storage and Playback](ADR-0002-Concert-Media-Storage-and-Playback.md)
- [Concert Streaming AWS Setup Handoff](../handbook/Concert-Streaming-AWS-Setup-Handoff.md)
- [AWS](../handbook/AWS.md)
- [Authentication](../handbook/Authentication.md)
- [Concert Production Readiness](../milestones/Milestone-03-Concert-Production-Readiness.md)
- [Amazon S3 presigned uploads](https://docs.aws.amazon.com/AmazonS3/latest/userguide/using-presigned-url.html)
- [Amazon S3 multipart upload](https://docs.aws.amazon.com/AmazonS3/latest/userguide/mpuoverview.html)
- [Amazon S3 upload integrity](https://docs.aws.amazon.com/AmazonS3/latest/userguide/checking-object-integrity-upload.html)
