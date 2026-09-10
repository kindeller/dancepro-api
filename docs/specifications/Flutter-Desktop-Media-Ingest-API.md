# Flutter Desktop Media Ingest API

## Purpose

This document is the integration handoff for the macOS Flutter application that
converts and uploads DancePro concert media.

The repository is DancePro V2. The Git branch name `v1-migration` means that the
original DancePro V1 application is being ported into the new application; it
does not mean this Laravel codebase is V1.

## Implementation status

This document contains two deliberately separate sections:

- **Implemented baseline** describes API behaviour present in this repository.
- **Required media API** describes the target contract that Laravel must
  implement before Flutter can use the secure upload and assignment workflow.

Do not build the Flutter upload integration on the assumption that the required
media endpoints already exist. Endpoint names and payloads in that section are
the agreed implementation target, not currently deployed routes.

## Responsibility boundary

| Web application | Flutter desktop application | Laravel API |
| --- | --- | --- |
| Create and manage studios and concerts | Select an existing studio and concert | Authenticate and authorise staff |
| Approve and publish concerts | Convert edited source media | Allocate collection and asset UUIDs |
| Set availability and customer access | Upload declared output files directly to S3 | Derive keys and issue short-lived upload requests |
| Manage business-facing release state | Show conversion/upload progress and retry failures | Verify uploads and update database state |
| Review final media readiness | Edit asset display name, order and visibility | Audit upload, import and asset changes |

Flutter should not create or publish studios or concerts in the initial media
workflow. It may create a video collection beneath an existing concert when one
does not exist. Broader management remains in the web application.

## Implemented baseline

### Authentication

The following endpoints exist:

```text
POST /api/auth/login
POST /api/auth/logout
GET  /api/auth/me
```

Login accepts:

```json
{
  "email": "staff@example.com",
  "password": "staff-password",
  "device_name": "DancePro Media Uploader on Studio Mac"
}
```

A successful response uses the normal API envelope and returns a Sanctum bearer
token:

```json
{
  "success": true,
  "message": "Logged in.",
  "data": {
    "token": "returned-once-token",
    "token_type": "Bearer",
    "user": {
      "name": "Staff Name",
      "email": "staff@example.com",
      "type": "staff",
      "is_active": true
    }
  }
}
```

Flutter sends the token on protected requests:

```text
Authorization: Bearer <token>
Accept: application/json
```

Store the token in the macOS Keychain. Never store it in source code, Flutter
assets, ordinary preferences, crash reports or logs. On `401`, discard it and
return to login. On logout, call `/api/auth/logout` before deleting the local
Keychain item.

Current authentication limitations that Laravel must address before enabling
media upload:

- login currently issues the wildcard `*` ability;
- the login controller does not currently reject an active customer account,
  despite its staff/admin intent;
- API login is not currently protected by an explicit route rate limit;
- token expiry depends on deployment configuration and may be unset;
- no device-token listing or remote revocation screen exists.

The current repository also has no staff account creation API, self-registration
flow or per-studio staff assignment. Accounts must be provisioned through an
authorised server-side process. Use named accounts, not a credential embedded in
the application or shared by an entire venue.

### Existing discovery endpoints

The current API has public read-only endpoints:

```text
GET /api/studios
GET /api/studios/{studio_uuid}
GET /api/concerts/{concert_uuid}
```

These return only active studios and publicly available concerts. They are not
suitable for a staff uploader because an unpublished or disabled concert would
be invisible.

Studio and concert creation/editing currently exists only in the authenticated
server-rendered web admin. There are no staff studio or concert management API
routes.

### Existing AWS-related API behaviour

The API currently exposes a server-side, read-only Competition object browser:

```text
GET /api/competitions/objects
```

It does not upload concert media. The only current upload-signing capability is
inside the installed Laravel S3 adapter; no controller or route exposes it.
Download signing and CloudFront playback signing are outbound delivery
capabilities and cannot be reused as upload endpoints without new server-side
actions and policies.

A review of the routes, application code and visible Git history in this
repository found no earlier concert upload, presigned-upload or multipart-upload
API implementation. Any working direct AWS upload code belongs to the old or
Flutter application and is outside this Laravel repository; it must not be
assumed to survive in the V2 API.

There is no currently implemented endpoint for:

- listing private/draft concerts for staff;
- listing or creating concert media collections;
- reserving a media asset UUID;
- creating a presigned upload request;
- coordinating a multipart upload;
- finalising or verifying an upload;
- importing an existing legacy MP4;
- editing media asset metadata;
- assigning an uploaded asset to a concert.

## Required authentication contract

Before the routes below are exposed, Laravel must enforce both account type and
token ability:

| Ability | Purpose |
| --- | --- |
| `concert-media:read` | List staff-visible studios, concerts, collections and assets |
| `concert-media:upload` | Reserve assets, create upload requests, complete and finalise uploads, import legacy media |
| `concert-media:update` | Change display name, playlist order and visibility |

An active `staff` or `admin` account may receive these abilities. A `customer`
account must receive none of them. The server chooses the abilities; Flutter
must not submit an arbitrary abilities array.

The current staff role has global access. Initially, restrict the uploader to
trusted DancePro staff. Before client-studio or venue staff use it, add an
explicit account-to-studio or account-to-concert assignment and enforce that
scope on discovery, reservation, upload, import, finalisation and update. A
token ability describes what an account can do; it does not by itself describe
which concert the account may access.

Every required route uses `auth:sanctum`, a staff/media authorisation policy and
an appropriate rate limit. A valid token without the required ability receives
`403`, not a presigned URL.

## Required media API

All identifiers in routes and responses are public UUIDs. Internal numeric
database IDs, bucket credentials and CloudFront signing material are never
returned.

All JSON responses use the standard envelope:

```json
{
  "success": true,
  "message": "Human-readable result.",
  "data": {}
}
```

### Staff discovery

```text
GET /api/staff/studios?search={text}&cursor={cursor}
GET /api/staff/concerts?studio_uuid={studio_uuid}&search={text}&cursor={cursor}
GET /api/staff/concerts/{concert_uuid}
```

Unlike the public API, these endpoints include authorised draft, disabled and
awaiting-approval concerts. Responses should include status and readiness data
needed for selection, but must omit access-password hashes, notes not intended
for the desktop application and unrelated customer data.

The concert response should include:

```json
{
  "uuid": "concert-uuid",
  "name": "Annual Concert",
  "status": "draft",
  "is_enabled": false,
  "studio": {
    "uuid": "studio-uuid",
    "name": "Example Studio"
  },
  "media_summary": {
    "collections": 1,
    "available_assets": 12,
    "processing_assets": 1
  }
}
```

### Collections

```text
GET  /api/staff/concerts/{concert_uuid}/media-collections
POST /api/staff/concerts/{concert_uuid}/media-collections
GET  /api/staff/media-collections/{collection_uuid}
PATCH /api/staff/media-collections/{collection_uuid}
```

Initial create request:

```json
{
  "name": "Saturday Evening Performances",
  "media_type": "video",
  "sort_order": 10
}
```

Laravel assigns the collection UUID, `s3_concerts` disk and immutable storage
prefix. A new collection starts as `draft` and `private`. Flutter must not be
able to submit a bucket name, AWS region or unrestricted storage prefix.

Collection updates from Flutter are limited to name and sort order. Publishing,
archiving and customer-facing visibility remain web-admin operations in the
initial release.

### Reserve a new asset

```text
GET  /api/staff/media-collections/{collection_uuid}/media-assets
POST /api/staff/media-collections/{collection_uuid}/media-assets
GET  /api/staff/media-assets/{asset_uuid}
```

Flutter reserves the asset before uploading any bytes:

```http
POST /api/staff/media-collections/{collection_uuid}/media-assets
Idempotency-Key: <client-generated-uuid>
```

```json
{
  "media_type": "video",
  "display_name": "Opening Performance",
  "original_filename": "Opening Performance.mov",
  "sort_order": 10,
  "expected_outputs": [
    "original",
    "fallback_mp4",
    "hls_720p",
    "hls_480p",
    "poster"
  ],
  "source": {
    "duration_seconds": 312,
    "width": 3840,
    "height": 2160
  }
}
```

Laravel returns an asset UUID with `status: processing` and
`is_visible: false`. Retrying the same request with the same idempotency key
returns the same asset rather than creating a duplicate.

The default package is:

```text
original/video.mp4       optional when no original download will be offered
stream/fallback.mp4      required for reliable playback
stream/master.m3u8       optional HLS entry point
stream/720p*             maximum streaming rendition
stream/480p*             optional lower-bandwidth rendition
thumbnail/poster.png     optional
```

No high-resolution or source-quality stream is part of the initial contract.
The original is never referenced by `master.m3u8`.

### Request single-object upload URLs

Use single-object uploads for HLS manifests, playlists, segments, initialisation
objects, thumbnails and other objects for which Laravel selects this strategy:

```text
POST /api/staff/media-assets/{asset_uuid}/upload-urls
```

```json
{
  "files": [
    {
      "relative_path": "stream/720p-000001.m4s",
      "content_type": "video/iso.segment",
      "size_bytes": 1048576,
      "checksum_sha256": "base64-encoded-checksum"
    },
    {
      "relative_path": "stream/720p.m3u8",
      "content_type": "application/vnd.apple.mpegurl",
      "size_bytes": 4096,
      "checksum_sha256": "base64-encoded-checksum"
    }
  ]
}
```

Laravel validates every relative path and returns one short-lived presigned
request per accepted object:

```json
{
  "upload_batch_uuid": "upload-batch-uuid",
  "expires_at": "2026-09-10T12:15:00Z",
  "objects": [
    {
      "relative_path": "stream/720p-000001.m4s",
      "method": "PUT",
      "url": "https://temporary-s3-request.example",
      "headers": {
        "Content-Type": "video/iso.segment",
        "x-amz-checksum-sha256": "base64-encoded-checksum"
      }
    }
  ]
}
```

Flutter must send the returned headers exactly. It must not add an ACL header.
It must never log the URL or query string. An expired URL can be replaced by
requesting a new batch for only the unfinished objects.

Laravel must reject:

- absolute paths or URLs;
- `..`, control characters or path normalisation escapes;
- extensions outside the media allowlist;
- duplicate paths with conflicting metadata;
- `master.m3u8` before final package readiness;
- files outside the reserved asset prefix;
- unreasonable file counts, individual sizes or total declared bytes.

### Multipart MP4 uploads

Large original and fallback MP4 files use multipart upload. Laravel chooses the
part size and returns it to Flutter; the client must not assume a fixed value.

```text
POST /api/staff/media-assets/{asset_uuid}/multipart-uploads
POST /api/staff/media-assets/{asset_uuid}/multipart-uploads/{upload_uuid}/parts
POST /api/staff/media-assets/{asset_uuid}/multipart-uploads/{upload_uuid}/complete
POST /api/staff/media-assets/{asset_uuid}/multipart-uploads/{upload_uuid}/abort
```

Initiation request:

```json
{
  "relative_path": "stream/fallback.mp4",
  "content_type": "video/mp4",
  "size_bytes": 4294967296,
  "checksum_algorithm": "CRC64NVME",
  "checksum": "base64-encoded-full-object-checksum"
}
```

The response contains an opaque upload UUID, selected part size and expiry. The
parts endpoint accepts requested part numbers and returns short-lived presigned
`UploadPart` requests. Flutter uploads parts concurrently within a conservative
limit and persists only the opaque upload UUID, part number, ETag and checksum
needed to resume.

The complete request returns the ordered completed-part results to Laravel.
Laravel completes the S3 multipart upload and records the resulting object
metadata. Flutter must not treat uploaded parts as a completed object until this
endpoint succeeds.

Abort only cancels an unfinished multipart upload. It must not delete a
previously completed media object. Server lifecycle configuration should also
abort abandoned incomplete multipart uploads after the approved retention
period.

### Finalise an uploaded asset

```text
POST /api/staff/media-assets/{asset_uuid}/finalize
```

```json
{
  "expected_outputs": [
    "original",
    "fallback_mp4",
    "hls_720p",
    "hls_480p",
    "poster"
  ]
}
```

Before returning success, Laravel performs low-impact metadata and playlist
validation. It confirms declared object existence, size, content type and
checksum where available. It verifies that HLS references are relative, remain
inside the asset prefix and point to uploaded objects.

The HLS master manifest is signed for upload only after all child HLS objects
have passed verification. It is therefore the last HLS object uploaded.

Successful finalisation sets:

```text
status       = available
verified_at  = current server time
is_visible   = false
```

Finalisation is idempotent and returns the current asset when already complete.
It does not publish the collection or concert. Staff review and customer-facing
visibility remain explicit later steps.

### Fallback-only conversion result

If HLS conversion fails, Flutter may complete the asset with:

```text
original/video.mp4     optional but recommended for downloads
stream/fallback.mp4    required
thumbnail/poster.png   optional
```

It omits all HLS outputs from `expected_outputs`. The Laravel player will select
the compressed MP4. No fake or empty HLS manifest should be uploaded.

If even the compressed conversion fails but a valid source MP4 is available,
Flutter may upload that MP4 as `stream/fallback.mp4`. Using the original for
playback is the final compatibility fallback, not the preferred cost profile.

### Import an existing legacy MP4

```text
GET  /api/staff/concerts/{concert_uuid}/legacy-media?cursor={cursor}
POST /api/staff/media-collections/{collection_uuid}/imports
```

The listing endpoint searches only the legacy prefix already associated with
the selected concert or collection. It returns display metadata and an opaque,
short-lived `object_ref`; it does not return credentials or permit an arbitrary
bucket listing.

```json
{
  "object_ref": "opaque-server-reference",
  "display_name": "Opening Performance",
  "sort_order": 10,
  "is_visible": false
}
```

Laravel resolves the reference, verifies the object still exists and is an MP4
inside the permitted legacy prefix, and creates an `available` managed asset
whose recorded disk and key point to that object. Retrying with the same
idempotency key returns the existing asset.

An imported legacy asset may use its recorded MP4 for playback without HLS. The
current player cannot combine an original from `s3_concerts_legacy` with an HLS
package from `s3_concerts` as one asset. Until location-aware playback exists,
move or upload the complete active package to one disk before switching it.

### Update asset presentation

```text
PATCH /api/staff/media-assets/{asset_uuid}
```

Flutter may update only:

```json
{
  "display_name": "Opening Performance",
  "sort_order": 10,
  "is_visible": true
}
```

Visibility can become true only for an `available`, verified asset. Flutter
cannot change the owning concert, collection UUID, disk, storage key, status,
publication, approval or customer access through this endpoint.

Replacement reserves a new immutable asset/package and is reviewed before the
old asset is archived. It must not overwrite an available object's keys.

## Playback and download result

After an asset is available and its collection/concert are published, playback
resolution is:

```text
stream/master.m3u8
    -> stream/fallback.mp4
    -> original/video.mp4
    -> recorded legacy MP4
```

HLS is selected only when CloudFront signing is configured and the master
manifest exists. The browser also switches to the fallback MP4 after a fatal HLS
error.

Production HLS and MP4 bytes should be delivered by CloudFront from private S3,
not proxied through Laravel. MP4 responses must support byte-range requests and
seeking. Originals use a Laravel tracked-download URL that redirects to a
short-lived attachment URL.

## Client retry rules

- Generate and reuse an `Idempotency-Key` for every create, import and finalise
  operation until that operation succeeds.
- Retry network timeouts and `429`/eligible `5xx` responses with exponential
  backoff and jitter.
- Honour `Retry-After` when present.
- Do not retry validation `422`, authentication `401` or authorisation `403`
  responses without resolving the underlying problem.
- Refresh only expired presigned requests, not completed uploads.
- After an uncertain completion response, fetch the asset/upload state before
  repeating the operation.
- Never infer completion from local progress alone.

## Expected errors

| Status | Meaning | Flutter behaviour |
| --- | --- | --- |
| `401` | Token missing, expired or revoked | Clear the Keychain token and log in again |
| `403` | Account or token lacks media permission | Stop and show a permission message |
| `404` | Business resource or permitted object not found | Refresh selection; do not reveal storage details |
| `409` | State or idempotency conflict | Fetch current server state and reconcile |
| `413` | Declared file/package exceeds policy | Stop and show the server limit |
| `422` | Validation or package verification failed | Show field/object failures and allow correction |
| `429` | Rate limited | Honour `Retry-After` and back off |
| `5xx` | Temporary server or AWS integration failure | Retry safely using the same idempotency key |

An S3 presigned request can return an AWS-formatted error rather than the
DancePro envelope. Flutter should retain the HTTP status and AWS request ID for
support, while redacting the complete request URL and signature.

## Security requirements

- Never embed or request a long-lived AWS access key or secret.
- Never shell out to AWS CLI as part of the normal Flutter workflow.
- Never place bearer tokens or presigned query strings in logs or analytics.
- Use HTTPS for Laravel, S3 and CloudFront.
- Keep the S3 bucket private with Block Public Access and disabled ACLs.
- Use only server-derived immutable object keys.
- Sign the required content type and checksum headers.
- Treat presigned URLs as secrets until expiry.
- Require server verification before making an asset available.
- Do not provide delete or arbitrary overwrite capability in the initial client.
- Record the authenticated user, device token, asset, action, result and time in
  server-side audit data without recording credentials.

## Flutter implementation sequence

1. Implement login, Keychain token storage, `/me`, logout and `401` handling.
2. Implement staff studio/concert selection using the required staff discovery
   endpoints, not the public catalogue.
3. Implement collection selection/creation.
4. Reserve an asset and persist its UUID plus idempotency key in the local job.
5. Convert to the agreed fallback MP4 and optional 720p/480p HLS package.
6. Calculate sizes and checksums.
7. Upload large MP4s with multipart endpoints and smaller HLS objects in signed
   URL batches.
8. Upload HLS child objects before requesting the master-manifest upload URL.
9. Finalise and wait for server verification.
10. Update display name, order and visibility after staff review.
11. Confirm the web preview plays, seeks, falls back and downloads correctly.

## Laravel implementation and acceptance checklist

- Add staff/media policies and limited Sanctum token abilities.
- Add and test per-studio/per-concert staff scope before onboarding external
  client staff; retain trusted DancePro staff-only access until then.
- Restrict media login to active staff/admin accounts and rate limit it.
- Add staff discovery resources that include drafts without exposing secrets.
- Add collection and asset actions, Form Requests and API Resources.
- Add persistent upload-session and idempotency records.
- Generate presigned single-object and multipart requests server-side.
- Validate exact prefix, role, extension, content type, size and checksum.
- Parse and constrain HLS playlist references before availability.
- Support fallback-only and legacy-import workflows.
- Audit upload/import/finalise/update operations.
- Keep new objects private and encrypted; never add public ACLs.
- Enable encrypted access logs, CloudTrail S3 data events and CloudWatch metrics
  and alarms for relevant upload failures and access patterns.
- Ensure lifecycle handling aborts abandoned incomplete multipart uploads.
- Deliver progressive MP4 through CloudFront with valid `206 Partial Content`
  responses.
- Route original downloads through the generic Downloads bounded context.
- Feature-test account types, abilities, prefix isolation, expiry, idempotency,
  multipart recovery, checksum failure, HLS validation and fallback-only assets.
- Run a synthetic end-to-end test from the packaged macOS application before
  production access is granted.

## Related documentation

- [ADR-0003 - Desktop Media Ingest and Assignment](../decisions/ADR-0003-Desktop-Media-Ingest-and-Assignment.md)
- [ADR-0002 - Concert Media Storage and Playback](../decisions/ADR-0002-Concert-Media-Storage-and-Playback.md)
- [Concert Streaming AWS Setup Handoff](../handbook/Concert-Streaming-AWS-Setup-Handoff.md)
- [Authentication Specification](Authentication.md)
- [API Guidelines](../handbook/API-Guidelines.md)
- [AWS](../handbook/AWS.md)
- [Amazon S3 presigned uploads](https://docs.aws.amazon.com/AmazonS3/latest/userguide/using-presigned-url.html)
- [Amazon S3 multipart upload](https://docs.aws.amazon.com/AmazonS3/latest/userguide/mpuoverview.html)
- [Amazon S3 upload integrity](https://docs.aws.amazon.com/AmazonS3/latest/userguide/checking-object-integrity-upload.html)
