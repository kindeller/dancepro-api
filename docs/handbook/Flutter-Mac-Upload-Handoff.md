# Flutter macOS concert upload handoff

Reviewed 18 October 2026 against `dev-kin-pre-checkout-backup`, HEAD
`95c22e22b8044ccc3363e2832e55f74dbd34c719`. This branch contains the local
`v1-migration` branch history. It is the V2 Laravel application, despite the
migration branch name. No Flutter source is present in this workspace.

The upload API already exists. Adapt the desktop app to this contract; do not
build a second AWS integration in Flutter. This review changed documentation,
not application code, configuration, credentials, production or AWS resources.

Give the Flutter developer the central [API reference](../api/README.md) and
[OpenAPI schema](../api/openapi.json), which include complete request/response
examples for every endpoint. Use this handoff for the integration sequence and
the [feature specification](../specifications/Flutter-Desktop-Media-Ingest-API.md)
for the wider legacy-import
contract. Authentication background is in [Authentication](Authentication.md);
infrastructure background is in [Concert Streaming AWS Setup](Concert-Streaming-AWS-Setup-Handoff.md).

## Architecture and scope

```text
Flutter -- staff login, discovery, reserve/sign/complete/finalize --> Laravel
Flutter -- raw file/part bytes using API-issued PUT URLs ---------> private S3
Laravel -- object/checksum/HLS verification ----------------------> S3
Web app -- authorise playback -----------------------------------> CloudFront
```

All AWS permissions, destination selection and signing belong to Laravel.
Flutter still makes HTTPS requests directly to S3 for the bytes. It needs no AWS
key/secret, signing implementation, AWS CLI or reusable AWS SDK credentials.
Presigned requests grant limited temporary access without giving the recipient
AWS credentials. They remain sensitive and can be reused until expiry; do not
log them. See [AWS presigned URLs](https://docs.aws.amazon.com/AmazonS3/latest/userguide/using-presigned-url.html).

Keep the existing local conversion engine. Replace old concert discovery,
AWS-key configuration, bucket listing/key construction and upload management
with the API workflow below. Web admin continues to create concerts, manage
approval/access and review publication. The desktop selects an existing concert
and creates its video collection/assets.

## Local readiness observed

These are local observations, not production settings or verified AWS access:

| Item | Observed value / implication |
| --- | --- |
| Environment | `local`, configuration cache absent |
| Sail application port | Host `8000` maps to container `80` |
| Configured `APP_URL` | `http://192.168.1.53`, missing the published port |
| Candidate Mac API base | `http://192.168.1.53:8000/api`; confirm this is the current development PC address and reachable from the Mac |
| Upload disk / bucket | `s3_concerts` / `dance-pro-videos-test` |
| Concert region | `ap-southeast-2`; standard AWS endpoint |
| Competition disk / bucket | `s3_competitions` / `dance-pro-competitions`; treat as live |
| Legacy disk | `s3_concerts_legacy`, bucket unset; skip legacy import in the first integration |
| Database | All current migrations ran; upload/session/audit tables present; active staff/admin accounts and concerts exist |
| Existing collections | A mixture of `local` demo collections and `s3_concerts` collections |
| CloudFront | A concert domain is configured; its origin/test-bucket mapping was not verified |

The current config separates concert uploads from competition storage. New
collections created through this API use the configured upload disk. Do not
reuse a demo collection backed by `local`: reservation requires the configured
upload disk. For the first test, create a fresh video collection through the API
under a designated test concert and leave it private.

`APP_URL` does not set Docker's port. Plan to align it with the actual reachable
origin before testing generated links/redirects. No setting was changed here.
On a separate Mac, `localhost` means that Mac, not the WSL development PC.
Follow [Remote Development](Remote-Development.md) if LAN access fails. Only use
local HTTP for an isolated development setup with a development account; use
HTTPS for deployed environments and S3. Do not expose Sail publicly for testing.

macOS debug/profile and release builds need outbound networking permission
(`com.apple.security.network.client` when sandboxed). Confirm local-file access
for the converter too. See [Flutter macOS entitlements](https://docs.flutter.dev/platform-integration/macos/building#entitlements-and-the-app-sandbox).
These settings and any development HTTP transport restriction must be checked
in the Flutter repository; they were not inspected here.

No signed upload URL or AWS object was requested during this review. Successful
URL generation alone would not prove S3 accepts the upload. Server IAM, bucket
policy/encryption requirements and network access need the staged test below.

## API client and authentication

Paths below start at the origin, including `/api`. If the client base already
ends with `/api`, append `/auth/login`, not `/api/auth/login`.

Use two HTTP clients:

- Laravel client: `Accept: application/json`, JSON content type for bodies, and
  bearer authentication after login. Do not follow redirects blindly.
- Upload client: raw bytes to the returned S3 URL, method and headers. Never
  attach the Laravel bearer token, cookies, an AWS Authorization header or ACL
  headers. Do not wrap files in `multipart/form-data`, modify the query string,
  change the host or rewrite signed headers. Transport adds the correct length
  for the bytes being sent. S3 errors are usually XML, not the API envelope.

`POST /api/auth/login` accepts:

```json
{
  "email": "<named-staff-account>",
  "password": "<entered-by-user>",
  "device_name": "DancePro macOS uploader"
}
```

Success is `200`, with `data.token`, `data.token_type`, `data.abilities`,
`data.expires_at` and `data.user` inside `{success, message, data}`. Use
`Authorization: Bearer <token>` on Laravel requests. Store the token in Keychain,
scoped to API environment and account; never share a production token with the
local API or retain the user's password in the upload job.

Only active `staff`/`admin` accounts can log in. Require these abilities before
starting an upload:

```text
concert-media:read
concert-media:upload
concert-media:update
```

New login tokens include those abilities; legacy `*` tokens do not grant concert
media access. `GET /api/auth/me` returns user/abilities/expiry and writes
`last_seen_at`. `POST /api/auth/logout` revokes the current token. Default token
lifetime is 30 days, configurable server-side. No refresh-token endpoint exists;
on `401`, retain nonsecret job progress, clear the token and request login again.

**Staff access is currently global.** There is no account-to-studio/concert
assignment. Trusted DancePro staff can select any concert, including drafts.
Per-studio restrictions require backend work before external studio staff are
onboarded. Login also grants existing competition/download abilities; do not
call those APIs as part of a concert upload.

## Discovery and reservation

All resource identifiers are public UUIDs. Do not send numeric database IDs.

| Method and path | Response data |
| --- | --- |
| `GET /api/staff/studios?search=...&per_page=50` | Studio list: UUID, name, slug, status, concert count |
| `GET /api/staff/concerts?studio_uuid=<uuid>&search=...&per_page=50` | Concert list including drafts, studio and media counts |
| `GET /api/staff/concerts/<uuid>` | One concert with the same discovery fields |
| `GET /api/staff/concerts/<uuid>/media-collections` | Collections with mode/type/status/visibility and asset count |
| `GET /api/staff/media-collections/<uuid>/media-assets` | Assets and ingest/readiness state |

Studio/concert lists return `data: []` and top-level
`meta: {next_cursor, per_page}`. Pass the opaque `next_cursor` as `cursor`, URL
encoded, until null. Collections/assets currently return complete lists. Use
staff discovery rather than `/api/studios`, which hides unpublished data.

For each create below send a separate, client-generated UUID in the
`Idempotency-Key` header. Persist it before sending; reuse it for the identical
request after a timeout. Never reuse one key for a different job or payload.
Successful creates return `201`, including idempotent retries.

Create a collection with
`POST /api/staff/concerts/<concert_uuid>/media-collections`:

```json
{"name":"Desktop upload test","media_type":"video","sort_order":10}
```

The response `data.uuid` is the collection UUID; it starts `managed`, `draft`,
`private`. Flutter supplies no bucket/disk/prefix. Create a new test collection
to avoid the local-demo storage ambiguity described above.

Finish conversion first, then reserve an asset using
`POST /api/staff/media-collections/<collection_uuid>/media-assets`:

```json
{
  "media_type": "video",
  "display_name": "Opening performance",
  "original_filename": "opening.mov",
  "sort_order": 10,
  "expected_outputs": ["original", "fallback_mp4"],
  "source": {"duration_seconds": 120, "width": 1920, "height": 1080}
}
```

Persist `data.uuid` as the asset UUID. Initial state is `processing` and
`is_visible: false`. Add `hls_720p`, `hls_480p` and/or `poster` only when those
outputs have actually been produced. Finalisation must use exactly the reserved
set. The API cannot downgrade an existing reservation after an HLS failure.

| Output | Required path | Content type |
| --- | --- | --- |
| Original, always required | `original/video.mp4` | `video/mp4` |
| Compressed fallback, always required | `stream/fallback.mp4` | `video/mp4` |
| Optional poster | `thumbnail/poster.png` | `image/png` |
| Optional HLS master/children | `stream/master.m3u8`, `stream/720p.m3u8`, etc. | `application/vnd.apple.mpegurl` |
| HLS fragmented MP4 initialisation | `stream/720p-init.mp4`, etc. | `video/mp4` |
| HLS segments | `stream/720p-000001.m4s` or `.ts`, etc. | `video/iso.segment` or `video/mp2t` |

The server derives `<collection_uuid>/media/<asset_uuid>/<relative_path>`.
Send only the relative path from the table. A `.mov` source name is metadata;
upload valid MP4 bytes for the original, not a renamed MOV. The original is the
download-quality output and is separate from the compressed playback output.

## Small-object uploads: first working milestone

`POST /api/staff/media-assets/<asset_uuid>/upload-urls`, with a new UUID
`Idempotency-Key` and this JSON shape (replace checksum/size with actual values):

```json
{
  "files": [
    {
      "relative_path": "original/video.mp4",
      "content_type": "video/mp4",
      "size_bytes": 123456,
      "checksum_sha256": "<base64-of-32-raw-SHA256-bytes>"
    },
    {
      "relative_path": "stream/fallback.mp4",
      "content_type": "video/mp4",
      "size_bytes": 65432,
      "checksum_sha256": "<base64-of-32-raw-SHA256-bytes>"
    }
  ]
}
```

Response `data` contains `upload_batch_uuid`, `expires_at` and
`objects: [{relative_path, method, url, headers}]`. Send the exact file bytes
using each returned PUT request. SHA256 is base64 of the binary digest, not the
hex string or base64 of the file. Preserve the file after hashing.

After **every object in that batch** has uploaded successfully, call
`POST /api/staff/media-assets/<asset_uuid>/upload-batches/<upload_batch_uuid>/complete`
with `{}`. Expect `200`, `data.status: completed`. This is required client
workflow; a successful S3 PUT alone is not a finished ingest operation.

URLs last 15 minutes by current configuration. An identical pending request
with the original key issues fresh URLs for that batch. Re-upload only failed
objects. If the file set changes, use a new key and track both batches; do not
abandon an incomplete child batch and assume HLS master signing will pass.

## Large MP4s: multipart milestone

Use this for original/fallback MP4s above the single-PUT policy limit. Laravel
coordinates initiation/completion; Flutter only uploads the signed parts.

1. Compute a full-file **CRC64NVME** checksum and file size. Start using
   `POST /api/staff/media-assets/<asset_uuid>/multipart-uploads` with a new UUID
   `Idempotency-Key`:

   ```json
   {
     "relative_path": "original/video.mp4",
     "content_type": "video/mp4",
     "size_bytes": 150000000,
     "checksum_algorithm": "CRC64NVME",
     "checksum": "<base64-of-8-raw-full-file-CRC64NVME-bytes>",
     "source_filename": "opening-download.mp4"
   }
   ```

2. Persist response `data.upload_uuid`, `part_size_bytes` and `expires_at`.
   Use the returned part size (currently 64 MiB), with a shorter final part.
   Hash each exact part with CRC64NVME. This is not CRC64-ECMA, MD5 or SHA256.
   Verify the Flutter checksum implementation against a known CRC64NVME vector
   and a real S3 test before processing a large concert.

3. Request URLs using
   `POST /api/staff/media-assets/<asset_uuid>/multipart-uploads/<upload_uuid>/parts`:

   ```json
   {"parts":[{"part_number":1,"checksum_crc64nvme":"<base64-part-checksum>"}]}
   ```

   Response `data.parts` contains `{part_number, method, url, headers}`;
   `data.expires_at` here is the shorter URL expiry. Upload raw parts with the
   upload HTTP client. Retain each successful S3 response's ETag exactly,
   including quotes, plus its part number and checksum. ETag is not a substitute
   for the checksum. Re-request URLs for unfinished parts as needed while the
   upload record remains active. Begin with two concurrent uploads, then tune.

4. Complete using
   `POST /api/staff/media-assets/<asset_uuid>/multipart-uploads/<upload_uuid>/complete`:

   ```json
   {
     "parts": [
       {"part_number":1,"etag":"\"returned-etag\"","checksum_crc64nvme":"<part-checksum>"}
     ],
     "checksum_crc64nvme": "<full-file-checksum-from-initiation>"
   }
   ```

   Include **all** parts in consecutive order starting at 1; the shortened example
   above illustrates one entry, not a complete 150 MB upload. Expect `200` and
   `data.status: completed`. Laravel completes S3 and verifies final size and
   full checksum. Repeat the workflow for `stream/fallback.mp4` with another key.

AWS describes the [multipart process](https://docs.aws.amazon.com/AmazonS3/latest/userguide/mpuoverview.html)
and [full-object checksums](https://docs.aws.amazon.com/AmazonS3/latest/userguide/checking-object-integrity-upload.html).
The application upload UUID is the identifier for Laravel routes. The Flutter
client must not extract the AWS upload ID from the signed URL and manage S3
completion itself.

## HLS milestone

Keep conversion in Flutter. Initially use H.264/AAC, a maximum intended streaming
rendition of 720p, and optionally 480p. Actual codec/quality are converter duties;
the API validates package structure, not decodability or the encoded resolution.

Upload child playlists, init MP4s and segments through small-object batches, then
complete those batches. Request/upload/complete `stream/master.m3u8` **last**, in
its own batch. The server refuses to sign it until completed batches contain a
child `.m3u8` plus `.m4s` or `.ts` segment. Prefer `.m4s` segments for fragmented
MP4, with an `.mp4` init map. Avoid putting everything into one batch.

Master variants require `BANDWIDTH` and `RESOLUTION`, and must include each
reserved 720p/480p rendition. Children need positive `EXTINF`, valid
`EXT-X-TARGETDURATION`, nonempty referenced objects and `EXT-X-ENDLIST`.
Fragmented MP4 needs `EXT-X-MAP` referencing an existing init MP4. Use muxed audio
and relative references entirely inside `stream/`, without `..`, absolute URLs,
query strings, external audio/subtitles, encryption, byte ranges or live media.
Do not upload an FFmpeg playlist with unsupported tags and expect silent acceptance.

The full [API specification](../specifications/Flutter-Desktop-Media-Ingest-API.md)
and `VerifyHlsPackage` define the accepted package. Keep names unique between
variants; never reference the original download in the streaming master.

## Finalise, review and customer delivery

After completing all uploads, send
`POST /api/staff/media-assets/<asset_uuid>/finalize`:

```json
{"expected_outputs":["original","fallback_mp4"]}
```

Use the actual reserved set if it includes HLS/poster. Expect `200`, an asset
with `status: available`, a non-null `verified_at`, and `is_visible: false`.
Repeated finalisation is safe. Never display upload success before the API
accepts finalisation.

`PATCH /api/staff/media-assets/<asset_uuid>` accepts `display_name`, `sort_order`
and `is_visible`; visibility requires verified availability. Leave visibility
and publication to an explicit review step initially. Collection PATCH also
accepts `status: published` (requires a verified visible asset), with optional
`show_all_verified: true` to show every available, verified video and publish
atomically. Omitting this flag preserves existing visibility. Staff must confirm
the warning before using it; unfinished videos remain hidden. Concert
approval/publication/enabled dates remain web-admin concerns. Finalisation alone
does not release the concert.

Staff discovery returns concert identifiers/slugs, not staff-authorised video
preview URLs. The uploader API has **no staff-bearer playback/download endpoint**.
Customer playback uses the web access/session gate and CloudFront signed URLs
or HLS cookies. A desktop viewer would need a separate contract; do not point a
bearer token at the public playback route and assume it grants access.

The original concert download uses an authorised web route and redirects to a
short-lived concert CloudFront URL, or signed S3 for legacy storage/unconfigured
CloudFront. Generic Downloads database tracking remains separate.
Local test-bucket upload verification can pass even if CloudFront playback fails:
the configured distribution may serve another bucket. Validate test delivery
separately without changing a production distribution for this pilot.

## Resume, limits and errors

Persist a local job with API origin/account identifier, collection/asset UUIDs,
output inventory and checksums, per-operation idempotency UUIDs, upload UUIDs,
part size and successful part ETags/checksums. Keep tokens only in Keychain;
fetch signed URLs when needed rather than persisting them in job logs.

`GET /api/staff/media-assets/<asset_uuid>` returns asset state.
`GET /api/staff/media-assets/<asset_uuid>/uploads/<upload_uuid>` returns
`upload_uuid`, `kind` (`single_batch`/`multipart`), `status`, `relative_path`,
`expires_at`, `completed_at`, `aborted_at`. It does not return a list of uploaded
S3 parts. Losing the local part ledger prevents automatic discovery-based resume.
After an uncertain completion, GET first, then retry completion if still pending.

The following are the **current application configuration**, not AWS limits:

| Policy | Value |
| --- | --- |
| Presigned URL TTL | 15 minutes |
| Upload record TTL | 24 hours; expired multipart records cannot issue new part URLs |
| Single object | 100 MiB maximum |
| Single batch | 100 files; 10 GiB total declared bytes |
| Multipart object / selected part size | 100 GiB maximum / 64 MiB |
| Part-URL request | At most 100 part entries |
| HLS verification | At most 10,000 stream objects, 4 variants, 2 MiB per playlist |
| Mutation rate | Shared 120 requests/minute per user for upload routes |
| Login rate | 10 requests/minute using the route throttle |

No limits-discovery endpoint exists. Treat these as environment configuration;
show `422` validation messages when actual server policy differs. Complete
conversion before starting the 24-hour upload window. A record expiry does not
clean up S3 parts by itself. Cancellation/cleanup needs an explicit owner-approved
operation; do not add automatic broad S3 cleanup to the Flutter migration.

API errors use `{success:false,message,errors}` for handled validation/auth
failures. `401`: log in again. `403`: stop and explain permissions. `404`: refresh
selection. `422`: surface field errors; current ingest code also uses it for
state/idempotency conflicts and configured size limits. `429`: honour
`Retry-After`. Retry eligible transport/5xx failures with bounded exponential
backoff and the same operation key. Do not retry a checksum or signature error
indefinitely. Unexpected server failures need not use the normal error envelope.

Keep API and S3 failures distinct. Report method, redacted route, UTC time,
status, safe error code and AWS request ID where available. Never include a
password, bearer token, signed query string, file contents or full HTTP trace.

## Gaps and follow-up for Laravel

No new endpoint is required to start a small private upload with trusted staff.
These findings constrain rollout and deserve follow-up based on pilot results:

| Priority | Finding and scope |
| --- | --- |
| Before first connection | Confirm Mac-to-Sail port 8000; align local `APP_URL` before generated-link testing. No configuration was changed here. |
| Before relying on upload integration | Run actual S3 single/multipart/HLS uploads; current tests mock storage and cannot prove signatures, CRC implementation, IAM, encryption policy or connectivity. |
| Before external staff | Add per-studio/concert assignment; current staff access and issued abilities are broad. |
| Before claiming strict verification | Finalisation HEADs required files and validates HLS structure, but does not require all upload records completed or compare every required file against its recorded checksum/size. Single-batch completion accepts a missing HEAD SHA256. Tighten these checks if verified completion is to be an enforced server invariant; Flutter must complete every batch now. |
| Before unattended multipart recovery | S3 initiation precedes DB record creation; a failed DB insert or concurrent same-key start can leave an orphan multipart upload. Add initiation recovery/concurrency coverage and a separately reviewed retention policy. Do not run cleanup automatically. |
| If jobs exceed 24 hours / local ledger is lost | Add explicit renewal and/or server part-inventory recovery. Existing status GET is not full resume discovery; expiry is not consistently checked across every completion/signing operation. |
| Before claiming storage isolation is enforced | Current destination guard blocks the legacy disk/bucket, but does not reject an upload bucket aliased to the competition bucket or enforce a local test-bucket allowlist. Current config is separate; stronger guards and tests would protect against later misconfiguration. |
| If HLS can fail after reservation | Add an explicit output-contract amendment/cancellation workflow, or continue reserving after conversion. Collection/asset retries return the existing reservation without validating every changed payload field; keep retry payloads immutable. |
| If choosing existing collections in desktop | Consider returning a server-derived `can_upload` field. Current collection resource omits its storage disk, so Flutter cannot identify otherwise similar local/legacy collections from mode alone. |
| If desktop preview/download is needed | Design a staff-authorised read contract and test-bucket delivery. Current customer web access and original-download redirects do not provide it. |

No backend fixes from this table were implemented as part of this review.

## Staged acceptance and evidence

1. From the Mac, check `GET <origin>/up` with JSON accept (200) and unauthenticated
   `GET <origin>/api/staff/studios` (401). Then log in interactively and confirm
   `/me`, staff discovery and cursor pagination. Do not share returned tokens.
2. Confirm the local upload destination is still the test bucket. Select a test
   concert, create a fresh collection, and reserve original+fallback only. Upload
   two short valid MP4s below 100 MiB each via signed PUT; complete the batch and
   finalise. Leave the collection draft/private and the asset hidden.
3. Confirm database/API state after an identical reservation retry and an
   uncertain-completion retry. Check that an unrelated resource/key change is
   not treated as a continuation of the same job.
4. Test multipart with a disposable MP4 above 100 MiB, multiple parts, an
   interrupted part, fresh part URLs and an app restart using the saved ledger.
   Complete both MP4s, then finalise. Verify CRC64NVME interoperability here.
5. Test one real 720p HLS package, then 480p, verifying child completion and
   master-last signing. Test invalid checksum/manifest failure on designated test
   assets. Confirm failures remain hidden/processing until corrected.
6. Have web staff perform an explicit release/visibility review and test playback,
   seeking and original downloads through an approved test delivery environment.
   Check that private media still requires access. This is separate from upload
   acceptance and may need test CloudFront configuration.
7. Test the packaged release macOS build with no AWS credential configuration or
   dependency on the old keys. After migration succeeds, the owner should review
   retirement of the old embedded AWS credentials separately. No credential
   access, rotation or IAM changes were performed in this review.

Record API SHA/environment, Flutter build, test asset/upload UUIDs, output set,
file sizes, stage/status and sanitized errors. Send these back to the Laravel
developer if the real upload disagrees with the documented contract. Do not
include tokens, signatures or personal responses. Keep test artifacts private;
deleting objects or incomplete uploads requires a separate approved scope.

Local verification on the review date:

- `./vendor/bin/sail artisan test`: 123 tests passed, 682 assertions.
- `./vendor/bin/sail artisan route:list --path=api/staff --except-vendor`: 21 routes.
- `./vendor/bin/sail artisan migrate:status`: all current local migrations ran.
- HTTP inside Sail: JSON `/up` 200, unauthenticated staff discovery 401.
- Effective nonsecret bucket/driver/region configuration and aggregate local
  schema/account/collection metadata inspected. Credential values were not read.
- No Mac request, real AWS upload, IAM check, CloudFront origin check, cleanup,
  production operation or infrastructure change was performed.

### Clearing a failed upload in the web admin

Staff can use **Clear unfinished upload** on the upload page or the delete link
in the concert editor. Stop Flutter/browser transfers first, review the exact
files and unfinished multipart upload IDs, acknowledge force deletion, then type
`DELETE`. This discards the reservation without completing verification. Tracked
unfinished uploads are cancelled and confirmed files removed. Cleanup errors
leave hidden, archived records for review and retry. The API returns 422 for
upload operations on an archived video awaiting cleanup and 404 after its soft
deletion; discard that local upload session instead of resuming it. Reserve a new
asset with a new idempotency key if you need to upload the video again.

Already issued single PUT URLs remain usable until expiry; closing the uploader
prevents files arriving after cleanup. Provider-side uploads without a database
record and retained S3 object versions require a separate reviewed operation.
See [Concert staff recovery](../epics/Concert.md#staff-upload-recovery-and-collection-publication)
and the [API contract](../api/README.md#updatecollection).
