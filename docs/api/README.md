# DancePro API reference

This is the central consumer-facing API reference. Give consuming app developers
this file and [openapi.json](openapi.json) together. The OpenAPI 3.1.1 contract
contains request/response schemas, required fields, nullability, parameters,
auth requirements, errors and a full success example for every operation in
`routes/api.php`. This Markdown file expands those examples for easy reading.

Reviewed against the current implementation on 18 October 2026. Examples are
fictional; tokens, signed URLs and object references are deliberately unusable.
Example checksums illustrate encoding and do not describe actual media files.
This is documentation, not an instruction to execute mutating operations.

## How to consume this contract

- Use an API origin such as `http://<development-PC>:8000`. Paths below already
  include `/api`. Use HTTPS for deployed environments. Localhost on a Mac means
  that Mac; replace it when Laravel runs on another machine.
- Send `Accept: application/json`, and JSON content type on body requests.
  Protected operations require `Authorization: Bearer <opaque Sanctum token>`.
  Login uses no existing bearer token or CSRF cookie. Parse `data.token` and
  `data.user`; do not expect `access_token` or decode the token as JWT.
- UUIDs are strings; auth user/access-record IDs are integers. Datetimes are
  ISO-8601 strings; date-only values use `YYYY-MM-DD`. Nullable fields can be
  null. A conditional resource field can be absent: consult schema `required`
  arrays, not just a non-null example. Unknown response fields should be ignored.
- Success is `{success, message, data}`, optionally top-level `meta`. Lists may
  be empty. Handled auth/validation errors use `{success:false,message,errors}`;
  `errors` is an object of arrays of messages. Do not parse a success model when
  the HTTP request failed. Throttle and unexpected/infrastructure responses may
  use another shape or non-JSON content.
- Staff discovery pagination uses top-level `meta.next_cursor`; legacy listing
  uses `data.next_cursor`; competition listing uses `data.pagination.next_token`;
  download lists use numbered `page` and top-level `meta`. Do not interchange them.
- Current staff access is global, not restricted to assigned studios/concerts.
  Explicit media abilities and active staff/admin policies both apply. Older
  wildcard tokens work only for the legacy competition/download abilities.
- Configurable size/count/TTL limits in descriptions are current defaults, not
  immutable schema guarantees. Inspect 422 field errors when an environment
  differs. Upload mutations share a default 120 requests/minute per user.

## Concert upload sequence

1. Log in, inspect `/auth/me`, and use staff studio/concert discovery.
2. Select/create a managed video collection. Finish conversion before reserving
   an asset with the exact expected outputs.
3. Upload valid `original/video.mp4` plus `stream/fallback.mp4`. Both are required;
   a MOV source name is metadata, not permission to upload MOV bytes as MP4.
4. Use signed single PUT for small files, or the multipart endpoints for large
   original/fallback MP4s. Returned S3 URLs receive raw bytes and returned headers,
   never the Laravel bearer token, form-data wrapping or AWS keys from Flutter.
5. Complete each batch/multipart upload. For optional HLS, complete child
   playlists/init files/segments first, then sign/upload/complete the master last.
6. Finalise with the same output set. Success is available/verified but hidden.
   Staff review, collection publication and concert availability are separate.
   Publication can optionally show all verified videos at once after a warning;
   see [updateCollection](#updatecollection).

Generate a separate UUID `Idempotency-Key` for collection creation, asset
reservation, upload URL batches, multipart initiation and legacy imports. Reuse
it for an unchanged retry. Completion/finalisation need no new header. Persist
upload IDs, checksums and part ETags for resume; status GET does not return the
S3 part inventory. There is no token refresh, output-contract amendment, desktop
asset deletion or staff-bearer playback endpoint. Failed uploads can be cleared
through the web-admin force-deletion confirmation after stopping active
transfers. Archived videos awaiting deletion cleanup reject upload mutations
with 422, and deleted videos return 404. Generic download-link creation
is not idempotent and is separate from concert-original web downloads.

See the [Flutter handoff](../handbook/Flutter-Mac-Upload-Handoff.md) for local
bucket readiness, HLS restrictions, rollout gaps and the staged upload test.

## Scope and maintenance

This reference covers all operations registered in `routes/api.php`. Automatic
HEAD routes share GET semantics and return no body. Web/admin routes, `/up`,
customer-session playback/download routes and arbitrary S3/CloudFront requests
are outside this JSON API contract. Server responses can fail before Laravel;
no contract guarantees a JSON envelope for every proxy/service error.

For each API change, update the OpenAPI schema **and** examples here in the same
change, including field types, optional/null fields, headers, permissions,
pagination, statuses and error behaviour. Examples must match the actual
controller/resource, not an intended future design. Do not insert production
responses or real credentials. No documentation hosting service is required.

`tests/Feature/ApiDocumentationTest.php` checks route coverage, example presence
and local references. It does not establish that every live response matches the
schemas or that S3 integration works. Schema/example validation and endpoint
tests supplement it. The OpenAPI file can be read directly by a coding agent or
loaded into a compatible local viewer; no request needs to be made to view it.
Format reference: [OpenAPI 3.1.1](https://spec.openapis.org/oas/v3.1.1.html).

## Endpoint index

| Method | Path | Operation |
| --- | --- | --- |
| GET | `/api/studios` | [List publicly available studios](#listpublicstudios) |
| GET | `/api/studios/{studio}` | [Get studio and public concerts](#getpublicstudio) |
| GET | `/api/concerts/{concert}` | [Get public concert metadata](#getpublicconcert) |
| POST | `/api/auth/login` | [Log in an active staff/admin account](#login) |
| GET | `/api/auth/me` | [Get current user and token metadata](#getme) |
| POST | `/api/auth/logout` | [Revoke the current bearer token](#logout) |
| GET | `/api/staff/studios` | [List staff-visible studios](#liststaffstudios) |
| GET | `/api/staff/concerts` | [List staff-visible concerts including drafts](#liststaffconcerts) |
| GET | `/api/staff/concerts/{concert}` | [Get a staff-visible concert](#getstaffconcert) |
| GET | `/api/staff/concerts/{concert}/media-collections` | [List concert collections](#listcollections) |
| POST | `/api/staff/concerts/{concert}/media-collections` | [Create a managed video collection](#createcollection) |
| GET | `/api/staff/media-collections/{collection}` | [Get collection metadata](#getcollection) |
| PATCH | `/api/staff/media-collections/{collection}` | [Update collection presentation or publication](#updatecollection) |
| GET | `/api/staff/media-collections/{collection}/media-assets` | [List collection assets](#listassets) |
| POST | `/api/staff/media-collections/{collection}/media-assets` | [Reserve an immutable asset package](#reserveasset) |
| GET | `/api/staff/media-assets/{asset}` | [Get asset ingest state](#getasset) |
| PATCH | `/api/staff/media-assets/{asset}` | [Update asset presentation or visibility](#updateasset) |
| GET | `/api/staff/media-assets/{asset}/uploads/{upload}` | [Get upload record status](#getupload) |
| POST | `/api/staff/media-assets/{asset}/upload-urls` | [Sign small-object PUT requests](#createuploadurls) |
| POST | `/api/staff/media-assets/{asset}/upload-batches/{upload}/complete` | [Verify and complete a single-object batch](#completeuploadbatch) |
| POST | `/api/staff/media-assets/{asset}/multipart-uploads` | [Start a multipart original/fallback MP4 upload](#startmultipart) |
| POST | `/api/staff/media-assets/{asset}/multipart-uploads/{upload}/parts` | [Sign selected multipart parts](#signmultipartparts) |
| POST | `/api/staff/media-assets/{asset}/multipart-uploads/{upload}/complete` | [Complete and verify multipart object](#completemultipart) |
| POST | `/api/staff/media-assets/{asset}/multipart-uploads/{upload}/abort` | [Abort an unfinished multipart upload](#abortmultipart) |
| POST | `/api/staff/media-assets/{asset}/finalize` | [Verify package and finalise the asset](#finalizeasset) |
| GET | `/api/staff/concerts/{concert}/legacy-media` | [List concert-scoped legacy MP4 candidates](#listlegacymedia) |
| POST | `/api/staff/media-collections/{collection}/imports` | [Import a permitted legacy MP4 reference](#importlegacymedia) |
| GET | `/api/competitions/objects` | [Browse permitted competition storage](#listcompetitionobjects) |
| GET | `/api/download-links` | [List download link metadata](#listdownloadlinks) |
| POST | `/api/download-links` | [Create tracked download links](#createdownloadlinks) |
| GET | `/api/download-links/{downloadLink}` | [Get download link metadata](#getdownloadlink) |
| PATCH | `/api/download-links/{downloadLink}/revoke` | [Revoke a download link](#revokedownloadlink) |
| GET | `/api/download-links/{downloadLink}/accesses` | [List recorded download accesses](#listdownloadaccesses) |

## listPublicStudios

`GET /api/studios`

No body or query parameters. Only active studios with an available concert. Lists can be empty.

Authentication: none.

Request body: none.

Response example — **HTTP 200**:

```json
{
  "success": true,
  "message": "Studios returned.",
  "data": [
    {
      "uuid": "00000001-0000-4000-8000-000000000001",
      "name": "Example Studio",
      "description": null,
      "cover_image_url": null,
      "brand_color": null
    }
  ]
}
```

Error responses: Other server/infrastructure failures may not use the API envelope or JSON; inspect HTTP status and content type. (`default`)

## getPublicStudio

`GET /api/studios/{studio}`

Get studio and public concerts.

Authentication: none.

| Parameter | Location | Required | Example / meaning |
| --- | --- | --- | --- |
| `studio` | path | yes | `00000001-0000-4000-8000-000000000001`. Public resource UUID. |

Request body: none.

Response example — **HTTP 200**:

```json
{
  "success": true,
  "message": "Studio concerts returned.",
  "data": {
    "studio": {
      "uuid": "00000001-0000-4000-8000-000000000001",
      "name": "Example Studio",
      "description": null,
      "cover_image_url": null,
      "brand_color": null
    },
    "concerts": [
      {
        "uuid": "00000002-0000-4000-8000-000000000001",
        "name": "Example Concert",
        "description": null,
        "event_date": "2026-10-18",
        "event_end_date": null,
        "venue_name": null,
        "cover_image_url": null,
        "brand_color": null,
        "requires_password": true,
        "program_available": false,
        "gallery_available": false
      }
    ]
  }
}
```

Error responses: Resource absent or unavailable. (`404`), Other server/infrastructure failures may not use the API envelope or JSON; inspect HTTP status and content type. (`default`)

## getPublicConcert

`GET /api/concerts/{concert}`

Only currently available concerts; this is metadata, not a staff media preview or an upload endpoint.

Authentication: none.

| Parameter | Location | Required | Example / meaning |
| --- | --- | --- | --- |
| `concert` | path | yes | `00000002-0000-4000-8000-000000000001`. Public resource UUID. |

Request body: none.

Response example — **HTTP 200**:

```json
{
  "success": true,
  "message": "Concert returned.",
  "data": {
    "uuid": "00000002-0000-4000-8000-000000000001",
    "name": "Example Concert",
    "description": null,
    "event_date": "2026-10-18",
    "event_end_date": null,
    "venue_name": null,
    "cover_image_url": null,
    "brand_color": null,
    "requires_password": true,
    "program_available": false,
    "gallery_available": false,
    "studio": {
      "uuid": "00000001-0000-4000-8000-000000000001",
      "name": "Example Studio"
    }
  }
}
```

Error responses: Resource absent or unavailable. (`404`), Other server/infrastructure failures may not use the API envelope or JSON; inspect HTTP status and content type. (`default`)

## login

`POST /api/auth/login`

No existing token or CSRF cookie required. Returns an opaque Sanctum token. Default individual expiry 30 days. Rate limit 10/minute. Login writes account activity timestamps.

Authentication: none.

Request example:

```json
{
  "email": "staff@example.com",
  "password": "EXAMPLE-ONLY-NOT-A-REAL-PASSWORD",
  "device_name": "DancePro macOS uploader"
}
```

Response example — **HTTP 200**:

```json
{
  "success": true,
  "message": "Logged in.",
  "data": {
    "token": "REDACTED-NOT-A-REAL-TOKEN",
    "token_type": "Bearer",
    "abilities": [
      "concert-media:read",
      "concert-media:upload",
      "concert-media:update",
      "competition-objects:read",
      "download-links:manage"
    ],
    "expires_at": "2026-11-17T08:00:00.000000Z",
    "user": {
      "id": 123,
      "name": "Example Staff",
      "email": "staff@example.com",
      "type": "staff",
      "is_active": true,
      "email_verified_at": null,
      "last_login_at": "2026-10-18T08:00:00.000000Z",
      "last_seen_at": "2026-10-18T08:00:00.000000Z",
      "created_at": "2026-10-18T08:00:00.000000Z",
      "updated_at": "2026-10-18T08:00:00.000000Z"
    }
  }
}
```

Error responses: Validation, state, checksum, size-policy or idempotency conflict; inspect errors. (`422`), Other server/infrastructure failures may not use the API envelope or JSON; inspect HTTP status and content type. (`default`), Login rejected; 403 also covers customer accounts (This account cannot use staff API access.). (`401`), Login rejected; 403 also covers customer accounts (This account cannot use staff API access.). (`403`), Rate limited. Honour Retry-After. (`429`)

## getMe

`GET /api/auth/me`

No body/query. Updates last_seen_at. Does not return the bearer token again.

Authentication: Sanctum bearer.

Request body: none.

Response example — **HTTP 200**:

```json
{
  "success": true,
  "message": "Authenticated user returned.",
  "data": {
    "user": {
      "id": 123,
      "name": "Example Staff",
      "email": "staff@example.com",
      "type": "staff",
      "is_active": true,
      "email_verified_at": null,
      "last_login_at": "2026-10-18T08:00:00.000000Z",
      "last_seen_at": "2026-10-18T08:00:00.000000Z",
      "created_at": "2026-10-18T08:00:00.000000Z",
      "updated_at": "2026-10-18T08:00:00.000000Z"
    },
    "abilities": [
      "concert-media:read",
      "concert-media:upload",
      "concert-media:update",
      "competition-objects:read",
      "download-links:manage"
    ],
    "expires_at": "2026-11-17T08:00:00.000000Z"
  }
}
```

Error responses: Missing, invalid, expired or revoked bearer token. (`401`), Account/policy/ability denied. (`403`), Other server/infrastructure failures may not use the API envelope or JSON; inspect HTTP status and content type. (`default`)

## logout

`POST /api/auth/logout`

Revoke the current bearer token.

Authentication: Sanctum bearer.

Request example (body optional):

```json
{}
```

Response example — **HTTP 200**:

```json
{
  "success": true,
  "message": "Logged out.",
  "data": {}
}
```

Error responses: Missing, invalid, expired or revoked bearer token. (`401`), Account/policy/ability denied. (`403`), Other server/infrastructure failures may not use the API envelope or JSON; inspect HTTP status and content type. (`default`)

## listStaffStudios

`GET /api/staff/studios`

List staff-visible studios. Requires an active staff/admin account and explicit token ability concert-media:read.

Authentication: Sanctum bearer. Ability: `concert-media:read`.

| Parameter | Location | Required | Example / meaning |
| --- | --- | --- | --- |
| `search` | query | no | `Example`.  |
| `per_page` | query | no | `50`.  |
| `cursor` | query | no | `opaque-cursor`. Opaque cursor returned in meta.next_cursor; omit on the first page. |

Request body: none.

Response example — **HTTP 200**:

```json
{
  "success": true,
  "message": "Staff studios returned.",
  "data": [
    {
      "uuid": "00000001-0000-4000-8000-000000000001",
      "name": "Example Studio",
      "slug": "example-studio",
      "status": "active",
      "concert_count": 1
    }
  ],
  "meta": {
    "next_cursor": null,
    "per_page": 50
  }
}
```

Error responses: Missing, invalid, expired or revoked bearer token. (`401`), Account/policy/ability denied. (`403`), Validation, state, checksum, size-policy or idempotency conflict; inspect errors. (`422`), Other server/infrastructure failures may not use the API envelope or JSON; inspect HTTP status and content type. (`default`)

## listStaffConcerts

`GET /api/staff/concerts`

List staff-visible concerts including drafts. Requires an active staff/admin account and explicit token ability concert-media:read.

Authentication: Sanctum bearer. Ability: `concert-media:read`.

| Parameter | Location | Required | Example / meaning |
| --- | --- | --- | --- |
| `studio_uuid` | query | no | `00000001-0000-4000-8000-000000000001`.  |
| `search` | query | no | `Example`.  |
| `per_page` | query | no | `50`.  |
| `cursor` | query | no | `opaque-cursor`. Opaque cursor returned in meta.next_cursor; omit on the first page. |

Request body: none.

Response example — **HTTP 200**:

```json
{
  "success": true,
  "message": "Staff concerts returned.",
  "data": [
    {
      "uuid": "00000002-0000-4000-8000-000000000001",
      "name": "Example Concert",
      "slug": "example-concert",
      "status": "draft",
      "event_date": "2026-10-18T00:00:00.000000Z",
      "is_enabled": false,
      "studio": {
        "uuid": "00000001-0000-4000-8000-000000000001",
        "name": "Example Studio"
      },
      "media_summary": {
        "collections": 1,
        "available_assets": 0,
        "processing_assets": 1
      }
    }
  ],
  "meta": {
    "next_cursor": null,
    "per_page": 50
  }
}
```

Error responses: Missing, invalid, expired or revoked bearer token. (`401`), Account/policy/ability denied. (`403`), Validation, state, checksum, size-policy or idempotency conflict; inspect errors. (`422`), Other server/infrastructure failures may not use the API envelope or JSON; inspect HTTP status and content type. (`default`)

## getStaffConcert

`GET /api/staff/concerts/{concert}`

Get a staff-visible concert. Requires an active staff/admin account and explicit token ability concert-media:read.

Authentication: Sanctum bearer. Ability: `concert-media:read`.

| Parameter | Location | Required | Example / meaning |
| --- | --- | --- | --- |
| `concert` | path | yes | `00000002-0000-4000-8000-000000000001`. Public resource UUID. |

Request body: none.

Response example — **HTTP 200**:

```json
{
  "success": true,
  "message": "Staff concert returned.",
  "data": {
    "uuid": "00000002-0000-4000-8000-000000000001",
    "name": "Example Concert",
    "slug": "example-concert",
    "status": "draft",
    "event_date": "2026-10-18T00:00:00.000000Z",
    "is_enabled": false,
    "studio": {
      "uuid": "00000001-0000-4000-8000-000000000001",
      "name": "Example Studio"
    },
    "media_summary": {
      "collections": 1,
      "available_assets": 0,
      "processing_assets": 1
    }
  }
}
```

Error responses: Missing, invalid, expired or revoked bearer token. (`401`), Account/policy/ability denied. (`403`), Resource absent or unavailable. (`404`), Other server/infrastructure failures may not use the API envelope or JSON; inspect HTTP status and content type. (`default`)

## listCollections

`GET /api/staff/concerts/{concert}/media-collections`

Unpaginated, sorted by sort_order then internal ID. concert_uuid is omitted; asset_count is present. Empty list is valid. Requires an active staff/admin account and explicit token ability concert-media:read.

Authentication: Sanctum bearer. Ability: `concert-media:read`.

| Parameter | Location | Required | Example / meaning |
| --- | --- | --- | --- |
| `concert` | path | yes | `00000002-0000-4000-8000-000000000001`. Public resource UUID. |

Request body: none.

Response example — **HTTP 200**:

```json
{
  "success": true,
  "message": "Media collections returned.",
  "data": [
    {
      "uuid": "00000003-0000-4000-8000-000000000001",
      "name": "Evening performances",
      "media_type": "video",
      "catalogue_mode": "managed",
      "status": "draft",
      "visibility": "private",
      "sort_order": 10,
      "asset_count": 0,
      "created_at": "2026-10-18T08:00:00.000000Z",
      "updated_at": "2026-10-18T08:00:00.000000Z"
    }
  ]
}
```

Error responses: Missing, invalid, expired or revoked bearer token. (`401`), Account/policy/ability denied. (`403`), Resource absent or unavailable. (`404`), Other server/infrastructure failures may not use the API envelope or JSON; inspect HTTP status and content type. (`default`)

## createCollection

`POST /api/staff/concerts/{concert}/media-collections`

Server selects upload disk and UUID prefix. Starts draft/private. Identical retry returns 201 and the same collection. Requires an active staff/admin account and explicit token ability concert-media:upload.

Authentication: Sanctum bearer. Ability: `concert-media:upload`.

| Parameter | Location | Required | Example / meaning |
| --- | --- | --- | --- |
| `concert` | path | yes | `00000002-0000-4000-8000-000000000001`. Public resource UUID. |
| `Idempotency-Key` | header | yes | `70000000-0000-4000-8000-000000000001`. Generate a new UUID per operation. Reuse it only for an unchanged retry by the same user. |

Request example:

```json
{
  "name": "Evening performances",
  "media_type": "video",
  "sort_order": 10
}
```

Response example — **HTTP 201**:

```json
{
  "success": true,
  "message": "Media collection ready.",
  "data": {
    "uuid": "00000003-0000-4000-8000-000000000001",
    "concert_uuid": "00000002-0000-4000-8000-000000000001",
    "name": "Evening performances",
    "media_type": "video",
    "catalogue_mode": "managed",
    "status": "draft",
    "visibility": "private",
    "sort_order": 10,
    "asset_count": 0,
    "created_at": "2026-10-18T08:00:00.000000Z",
    "updated_at": "2026-10-18T08:00:00.000000Z"
  }
}
```

Error responses: Missing, invalid, expired or revoked bearer token. (`401`), Account/policy/ability denied. (`403`), Resource absent or unavailable. (`404`), Validation, state, checksum, size-policy or idempotency conflict; inspect errors. (`422`), Other server/infrastructure failures may not use the API envelope or JSON; inspect HTTP status and content type. (`default`), Rate limited. Honour Retry-After. (`429`)

## getCollection

`GET /api/staff/media-collections/{collection}`

Get collection metadata. Requires an active staff/admin account and explicit token ability concert-media:read.

Authentication: Sanctum bearer. Ability: `concert-media:read`.

| Parameter | Location | Required | Example / meaning |
| --- | --- | --- | --- |
| `collection` | path | yes | `00000003-0000-4000-8000-000000000001`. Public resource UUID. |

Request body: none.

Response example — **HTTP 200**:

```json
{
  "success": true,
  "message": "Media collection returned.",
  "data": {
    "uuid": "00000003-0000-4000-8000-000000000001",
    "concert_uuid": "00000002-0000-4000-8000-000000000001",
    "name": "Evening performances",
    "media_type": "video",
    "catalogue_mode": "managed",
    "status": "draft",
    "visibility": "private",
    "sort_order": 10,
    "asset_count": 0,
    "created_at": "2026-10-18T08:00:00.000000Z",
    "updated_at": "2026-10-18T08:00:00.000000Z"
  }
}
```

Error responses: Missing, invalid, expired or revoked bearer token. (`401`), Account/policy/ability denied. (`403`), Resource absent or unavailable. (`404`), Other server/infrastructure failures may not use the API envelope or JSON; inspect HTTP status and content type. (`default`)

## updateCollection

`PATCH /api/staff/media-collections/{collection}`

Fields optional. Published requires a managed video collection with at least one visible, available, verified asset; sets visibility public. Optional show_all_verified (boolean, non-null, default false) is accepted only with status published: true makes every available, verified, non-deleted asset in this collection visible and publishes atomically. Processing, unverified, missing and archived assets are excluded. Omitting the flag preserves existing visibility. Draft sets private. Archived collections awaiting deletion cleanup cannot be edited or receive new reservations (422). Does not approve or publish the concert. asset_count omitted. Requires an active staff/admin account and explicit token ability concert-media:update.

Authentication: Sanctum bearer. Ability: `concert-media:update`.

| Parameter | Location | Required | Example / meaning |
| --- | --- | --- | --- |
| `collection` | path | yes | `00000003-0000-4000-8000-000000000001`. Public resource UUID. |

Request fields (all optional and non-null): `name` (string, 1–255 characters),
`sort_order` (integer, minimum 0), `status` (`draft` or `published`), and
`show_all_verified` (boolean, default false; allowed only alongside
`status: published`). Show a warning before sending `show_all_verified: true`,
as it also shows videos deliberately hidden during review. Staff can hide
individual videos again after publishing. With no ready videos, publication
returns 422 and the collection stays unchanged.

Response fields retain the existing collection schema: UUIDs, name, media type,
catalogue mode, status, visibility and timestamps are strings; sort_order is an
integer. No additional response fields are introduced.

Request example:

```json
{
  "status": "published",
  "show_all_verified": true
}
```

Response example — **HTTP 200**:

```json
{
  "success": true,
  "message": "Media collection updated.",
  "data": {
    "uuid": "00000003-0000-4000-8000-000000000001",
    "concert_uuid": "00000002-0000-4000-8000-000000000001",
    "name": "Evening performances",
    "media_type": "video",
    "catalogue_mode": "managed",
    "status": "published",
    "visibility": "public",
    "sort_order": 10,
    "created_at": "2026-10-18T08:00:00.000000Z",
    "updated_at": "2026-10-18T08:00:00.000000Z"
  }
}
```

Error responses: Missing, invalid, expired or revoked bearer token. (`401`), Account/policy/ability denied. (`403`), Resource absent or unavailable. (`404`), Validation, state, checksum, size-policy or idempotency conflict; inspect errors. (`422`), Other server/infrastructure failures may not use the API envelope or JSON; inspect HTTP status and content type. (`default`)

## listAssets

`GET /api/staff/media-collections/{collection}/media-assets`

Unpaginated; sorted by sort_order then internal ID. Empty list is valid. Requires an active staff/admin account and explicit token ability concert-media:read.

Authentication: Sanctum bearer. Ability: `concert-media:read`.

| Parameter | Location | Required | Example / meaning |
| --- | --- | --- | --- |
| `collection` | path | yes | `00000003-0000-4000-8000-000000000001`. Public resource UUID. |

Request body: none.

Response example — **HTTP 200**:

```json
{
  "success": true,
  "message": "Media assets returned.",
  "data": [
    {
      "uuid": "00000004-0000-4000-8000-000000000001",
      "collection_uuid": "00000003-0000-4000-8000-000000000001",
      "media_type": "video",
      "display_name": "Opening performance",
      "original_filename": "opening.mp4",
      "status": "processing",
      "is_visible": false,
      "sort_order": 10,
      "size_bytes": null,
      "duration_seconds": null,
      "mime_type": null,
      "extension": "mp4",
      "expected_outputs": [
        "original",
        "fallback_mp4"
      ],
      "verified_at": null,
      "created_at": "2026-10-18T08:00:00.000000Z",
      "updated_at": "2026-10-18T08:00:00.000000Z"
    }
  ]
}
```

Error responses: Missing, invalid, expired or revoked bearer token. (`401`), Account/policy/ability denied. (`403`), Resource absent or unavailable. (`404`), Other server/infrastructure failures may not use the API envelope or JSON; inspect HTTP status and content type. (`default`)

## reserveAsset

`POST /api/staff/media-collections/{collection}/media-assets`

Requires a managed video collection on the configured upload disk. Both original and fallback_mp4 required. Convert first and reserve the actual outputs. Starts processing/hidden. Same-key retry returns existing reservation; never change its payload. Requires an active staff/admin account and explicit token ability concert-media:upload. Collections archived for deletion cleanup reject new reservations with 422.

Authentication: Sanctum bearer. Ability: `concert-media:upload`.

| Parameter | Location | Required | Example / meaning |
| --- | --- | --- | --- |
| `collection` | path | yes | `00000003-0000-4000-8000-000000000001`. Public resource UUID. |
| `Idempotency-Key` | header | yes | `70000000-0000-4000-8000-000000000001`. Generate a new UUID per operation. Reuse it only for an unchanged retry by the same user. |

Request example:

```json
{
  "media_type": "video",
  "display_name": "Opening performance",
  "original_filename": "opening.mp4",
  "sort_order": 10,
  "expected_outputs": [
    "original",
    "fallback_mp4"
  ]
}
```

Response example — **HTTP 201**:

```json
{
  "success": true,
  "message": "Media asset reserved.",
  "data": {
    "uuid": "00000004-0000-4000-8000-000000000001",
    "collection_uuid": "00000003-0000-4000-8000-000000000001",
    "media_type": "video",
    "display_name": "Opening performance",
    "original_filename": "opening.mp4",
    "status": "processing",
    "is_visible": false,
    "sort_order": 10,
    "size_bytes": null,
    "duration_seconds": null,
    "mime_type": null,
    "extension": "mp4",
    "expected_outputs": [
      "original",
      "fallback_mp4"
    ],
    "verified_at": null,
    "created_at": "2026-10-18T08:00:00.000000Z",
    "updated_at": "2026-10-18T08:00:00.000000Z"
  }
}
```

Error responses: Missing, invalid, expired or revoked bearer token. (`401`), Account/policy/ability denied. (`403`), Resource absent or unavailable. (`404`), Validation, state, checksum, size-policy or idempotency conflict; inspect errors. (`422`), Other server/infrastructure failures may not use the API envelope or JSON; inspect HTTP status and content type. (`default`), Rate limited. Honour Retry-After. (`429`)

## getAsset

`GET /api/staff/media-assets/{asset}`

Get asset ingest state. Requires an active staff/admin account and explicit token ability concert-media:read.

Authentication: Sanctum bearer. Ability: `concert-media:read`.

| Parameter | Location | Required | Example / meaning |
| --- | --- | --- | --- |
| `asset` | path | yes | `00000004-0000-4000-8000-000000000001`. Public resource UUID. |

Request body: none.

Response example — **HTTP 200**:

```json
{
  "success": true,
  "message": "Media asset returned.",
  "data": {
    "uuid": "00000004-0000-4000-8000-000000000001",
    "collection_uuid": "00000003-0000-4000-8000-000000000001",
    "media_type": "video",
    "display_name": "Opening performance",
    "original_filename": "opening.mp4",
    "status": "processing",
    "is_visible": false,
    "sort_order": 10,
    "size_bytes": null,
    "duration_seconds": null,
    "mime_type": null,
    "extension": "mp4",
    "expected_outputs": [
      "original",
      "fallback_mp4"
    ],
    "verified_at": null,
    "created_at": "2026-10-18T08:00:00.000000Z",
    "updated_at": "2026-10-18T08:00:00.000000Z"
  }
}
```

Error responses: Missing, invalid, expired or revoked bearer token. (`401`), Account/policy/ability denied. (`403`), Resource absent or unavailable. (`404`), Other server/infrastructure failures may not use the API envelope or JSON; inspect HTTP status and content type. (`default`)

## updateAsset

`PATCH /api/staff/media-assets/{asset}`

Making visible requires status available and verified_at non-null. Does not publish the collection or concert. Requires an active staff/admin account and explicit token ability concert-media:update.

Authentication: Sanctum bearer. Ability: `concert-media:update`.

| Parameter | Location | Required | Example / meaning |
| --- | --- | --- | --- |
| `asset` | path | yes | `00000004-0000-4000-8000-000000000001`. Public resource UUID. |

Request example:

```json
{
  "display_name": "Opening performance",
  "sort_order": 10,
  "is_visible": true
}
```

Response example — **HTTP 200**:

```json
{
  "success": true,
  "message": "Media asset updated.",
  "data": {
    "uuid": "00000004-0000-4000-8000-000000000001",
    "collection_uuid": "00000003-0000-4000-8000-000000000001",
    "media_type": "video",
    "display_name": "Opening performance",
    "original_filename": "opening.mp4",
    "status": "available",
    "is_visible": true,
    "sort_order": 10,
    "size_bytes": 123456,
    "duration_seconds": null,
    "mime_type": "video/mp4",
    "extension": "mp4",
    "expected_outputs": [
      "original",
      "fallback_mp4"
    ],
    "verified_at": "2026-10-18T08:00:00.000000Z",
    "created_at": "2026-10-18T08:00:00.000000Z",
    "updated_at": "2026-10-18T08:00:00.000000Z"
  }
}
```

Error responses: Missing, invalid, expired or revoked bearer token. (`401`), Account/policy/ability denied. (`403`), Resource absent or unavailable. (`404`), Validation, state, checksum, size-policy or idempotency conflict; inspect errors. (`422`), Other server/infrastructure failures may not use the API envelope or JSON; inspect HTTP status and content type. (`default`)

## getUpload

`GET /api/staff/media-assets/{asset}/uploads/{upload}`

No signed URLs, file inventory, S3 part list or ETags returned. Single-batch relative_path is null. Resume requires a locally persisted part ledger. Requires an active staff/admin account and explicit token ability concert-media:read.

Authentication: Sanctum bearer. Ability: `concert-media:read`.

| Parameter | Location | Required | Example / meaning |
| --- | --- | --- | --- |
| `asset` | path | yes | `00000004-0000-4000-8000-000000000001`. Public resource UUID. |
| `upload` | path | yes | `00000005-0000-4000-8000-000000000001`. Public resource UUID. |

Request body: none.

Response example — **HTTP 200**:

```json
{
  "success": true,
  "message": "Media upload returned.",
  "data": {
    "upload_uuid": "00000005-0000-4000-8000-000000000001",
    "kind": "single_batch",
    "status": "pending",
    "relative_path": null,
    "expires_at": "2026-10-19T08:00:00.000000Z",
    "completed_at": null,
    "aborted_at": null
  }
}
```

Error responses: Missing, invalid, expired or revoked bearer token. (`401`), Account/policy/ability denied. (`403`), Resource absent or unavailable. (`404`), Other server/infrastructure failures may not use the API envelope or JSON; inspect HTTP status and content type. (`default`)

## createUploadUrls

`POST /api/staff/media-assets/{asset}/upload-urls`

Only unfinished processing assets. Files must use allowlisted relative paths and exact MIME types. Upload SHA256-signed raw bytes directly to returned URLs using every returned header and no Laravel bearer token. Return header names may vary. Default TTL 15 minutes. Reissue identical pending batch/key for fresh URLs. HLS master signing requires completed child-playlist and segment batches. Requires an active staff/admin account and explicit token ability concert-media:upload.

Authentication: Sanctum bearer. Ability: `concert-media:upload`.

| Parameter | Location | Required | Example / meaning |
| --- | --- | --- | --- |
| `asset` | path | yes | `00000004-0000-4000-8000-000000000001`. Public resource UUID. |
| `Idempotency-Key` | header | yes | `70000000-0000-4000-8000-000000000001`. Generate a new UUID per operation. Reuse it only for an unchanged retry by the same user. |

Request example:

```json
{
  "files": [
    {
      "relative_path": "original/video.mp4",
      "content_type": "video/mp4",
      "size_bytes": 123456,
      "checksum_sha256": "YWFhYWFhYWFhYWFhYWFhYWFhYWFhYWFhYWFhYWFhYWE="
    },
    {
      "relative_path": "stream/fallback.mp4",
      "content_type": "video/mp4",
      "size_bytes": 65432,
      "checksum_sha256": "YWFhYWFhYWFhYWFhYWFhYWFhYWFhYWFhYWFhYWFhYWE="
    }
  ]
}
```

Response example — **HTTP 201**:

```json
{
  "success": true,
  "message": "Upload requests created.",
  "data": {
    "upload_batch_uuid": "00000005-0000-4000-8000-000000000001",
    "expires_at": "2026-10-18T08:15:00.000000Z",
    "objects": [
      {
        "relative_path": "original/video.mp4",
        "method": "PUT",
        "url": "https://upload.example.invalid/original?REDACTED",
        "headers": {
          "Content-Type": "video/mp4",
          "x-amz-checksum-sha256": "YWFhYWFhYWFhYWFhYWFhYWFhYWFhYWFhYWFhYWFhYWE="
        }
      },
      {
        "relative_path": "stream/fallback.mp4",
        "method": "PUT",
        "url": "https://upload.example.invalid/fallback?REDACTED",
        "headers": {
          "Content-Type": "video/mp4",
          "x-amz-checksum-sha256": "YWFhYWFhYWFhYWFhYWFhYWFhYWFhYWFhYWFhYWFhYWE="
        }
      }
    ]
  }
}
```

Error responses: Missing, invalid, expired or revoked bearer token. (`401`), Account/policy/ability denied. (`403`), Resource absent or unavailable. (`404`), Validation, state, checksum, size-policy or idempotency conflict; inspect errors. (`422`), Other server/infrastructure failures may not use the API envelope or JSON; inspect HTTP status and content type. (`default`), Rate limited. Honour Retry-After. (`429`)

## completeUploadBatch

`POST /api/staff/media-assets/{asset}/upload-batches/{upload}/complete`

After all batch objects upload. Checks object size and SHA256 when returned by HEAD. Repeat completion returns completed state. No Idempotency-Key required. Validation/state failures return 422. Requires an active staff/admin account and explicit token ability concert-media:upload. Videos archived for deletion cleanup reject upload completion with 422.

Authentication: Sanctum bearer. Ability: `concert-media:upload`.

| Parameter | Location | Required | Example / meaning |
| --- | --- | --- | --- |
| `asset` | path | yes | `00000004-0000-4000-8000-000000000001`. Public resource UUID. |
| `upload` | path | yes | `00000005-0000-4000-8000-000000000001`. Public resource UUID. |

Request example (body optional):

```json
{}
```

Response example — **HTTP 200**:

```json
{
  "success": true,
  "message": "Upload batch completed.",
  "data": {
    "upload_batch_uuid": "00000005-0000-4000-8000-000000000001",
    "status": "completed",
    "completed_at": "2026-10-18T08:00:00.000000Z"
  }
}
```

Error responses: Missing, invalid, expired or revoked bearer token. (`401`), Account/policy/ability denied. (`403`), Resource absent or unavailable. (`404`), Other server/infrastructure failures may not use the API envelope or JSON; inspect HTTP status and content type. (`default`), Upload state or verification failure. (`422`), Rate limited. Honour Retry-After. (`429`)

## startMultipart

`POST /api/staff/media-assets/{asset}/multipart-uploads`

Only original/video.mp4 or stream/fallback.mp4; no path separators/control characters in source_filename. Use server-selected part size. Record expiry defaults 24 hours. Small single-part example is deliberate; actual large files use multiple parts. Requires an active staff/admin account and explicit token ability concert-media:upload.

Authentication: Sanctum bearer. Ability: `concert-media:upload`.

| Parameter | Location | Required | Example / meaning |
| --- | --- | --- | --- |
| `asset` | path | yes | `00000004-0000-4000-8000-000000000001`. Public resource UUID. |
| `Idempotency-Key` | header | yes | `70000000-0000-4000-8000-000000000001`. Generate a new UUID per operation. Reuse it only for an unchanged retry by the same user. |

Request example:

```json
{
  "relative_path": "original/video.mp4",
  "content_type": "video/mp4",
  "size_bytes": 123456,
  "checksum_algorithm": "CRC64NVME",
  "checksum": "Y2NjY2NjY2M=",
  "source_filename": "opening.mp4"
}
```

Response example — **HTTP 201**:

```json
{
  "success": true,
  "message": "Multipart upload started.",
  "data": {
    "upload_uuid": "00000005-0000-4000-8000-000000000001",
    "relative_path": "original/video.mp4",
    "part_size_bytes": 67108864,
    "expires_at": "2026-10-19T08:00:00.000000Z"
  }
}
```

Error responses: Missing, invalid, expired or revoked bearer token. (`401`), Account/policy/ability denied. (`403`), Resource absent or unavailable. (`404`), Validation, state, checksum, size-policy or idempotency conflict; inspect errors. (`422`), Other server/infrastructure failures may not use the API envelope or JSON; inspect HTTP status and content type. (`default`), Rate limited. Honour Retry-After. (`429`)

## signMultipartParts

`POST /api/staff/media-assets/{asset}/multipart-uploads/{upload}/parts`

Pending, unexpired upload record required. CRC64NVME of each exact part. Response expiry refers to URLs, not upload record. Persist successful ETags and part checksums locally. Requires an active staff/admin account and explicit token ability concert-media:upload. Videos archived for deletion cleanup reject part signing with 422.

Authentication: Sanctum bearer. Ability: `concert-media:upload`.

| Parameter | Location | Required | Example / meaning |
| --- | --- | --- | --- |
| `asset` | path | yes | `00000004-0000-4000-8000-000000000001`. Public resource UUID. |
| `upload` | path | yes | `00000005-0000-4000-8000-000000000001`. Public resource UUID. |

Request example:

```json
{
  "parts": [
    {
      "part_number": 1,
      "checksum_crc64nvme": "Y2NjY2NjY2M="
    }
  ]
}
```

Response example — **HTTP 200**:

```json
{
  "success": true,
  "message": "Multipart part requests created.",
  "data": {
    "upload_uuid": "00000005-0000-4000-8000-000000000001",
    "expires_at": "2026-10-18T08:15:00.000000Z",
    "parts": [
      {
        "part_number": 1,
        "method": "PUT",
        "url": "https://upload.example.invalid/part?REDACTED",
        "headers": {
          "x-amz-checksum-crc64nvme": "Y2NjY2NjY2M="
        }
      }
    ]
  }
}
```

Error responses: Missing, invalid, expired or revoked bearer token. (`401`), Account/policy/ability denied. (`403`), Resource absent or unavailable. (`404`), Validation, state, checksum, size-policy or idempotency conflict; inspect errors. (`422`), Other server/infrastructure failures may not use the API envelope or JSON; inspect HTTP status and content type. (`default`), Rate limited. Honour Retry-After. (`429`)

## completeMultipart

`POST /api/staff/media-assets/{asset}/multipart-uploads/{upload}/complete`

Include every consecutive part starting at 1 and the full-object checksum from initiation. Laravel completes S3, verifies full checksum/size and reconciles matching objects after uncertain completion. No Idempotency-Key required. Requires an active staff/admin account and explicit token ability concert-media:upload. Videos archived for deletion cleanup reject multipart completion with 422.

Authentication: Sanctum bearer. Ability: `concert-media:upload`.

| Parameter | Location | Required | Example / meaning |
| --- | --- | --- | --- |
| `asset` | path | yes | `00000004-0000-4000-8000-000000000001`. Public resource UUID. |
| `upload` | path | yes | `00000005-0000-4000-8000-000000000001`. Public resource UUID. |

Request example:

```json
{
  "parts": [
    {
      "part_number": 1,
      "checksum_crc64nvme": "Y2NjY2NjY2M=",
      "etag": "\"example-etag\""
    }
  ],
  "checksum_crc64nvme": "Y2NjY2NjY2M="
}
```

Response example — **HTTP 200**:

```json
{
  "success": true,
  "message": "Multipart upload completed.",
  "data": {
    "upload_uuid": "00000005-0000-4000-8000-000000000001",
    "relative_path": "original/video.mp4",
    "status": "completed",
    "completed_at": "2026-10-18T08:00:00.000000Z"
  }
}
```

Error responses: Missing, invalid, expired or revoked bearer token. (`401`), Account/policy/ability denied. (`403`), Resource absent or unavailable. (`404`), Validation, state, checksum, size-policy or idempotency conflict; inspect errors. (`422`), Other server/infrastructure failures may not use the API envelope or JSON; inspect HTTP status and content type. (`default`), Rate limited. Honour Retry-After. (`429`)

## abortMultipart

`POST /api/staff/media-assets/{asset}/multipart-uploads/{upload}/abort`

Cancels only an unfinished multipart upload; cannot delete completed media. Stop active part transfers before user-requested cancellation. No Idempotency-Key required. Requires an active staff/admin account and explicit token ability concert-media:upload. Videos archived for deletion cleanup reject client abort requests with 422; retry the web-admin deletion flow to finish cleanup.

Authentication: Sanctum bearer. Ability: `concert-media:upload`.

| Parameter | Location | Required | Example / meaning |
| --- | --- | --- | --- |
| `asset` | path | yes | `00000004-0000-4000-8000-000000000001`. Public resource UUID. |
| `upload` | path | yes | `00000005-0000-4000-8000-000000000001`. Public resource UUID. |

Request example (body optional):

```json
{}
```

Response example — **HTTP 200**:

```json
{
  "success": true,
  "message": "Multipart upload aborted.",
  "data": {
    "upload_uuid": "00000005-0000-4000-8000-000000000001",
    "status": "aborted",
    "aborted_at": "2026-10-18T08:00:00.000000Z"
  }
}
```

Error responses: Missing, invalid, expired or revoked bearer token. (`401`), Account/policy/ability denied. (`403`), Resource absent or unavailable. (`404`), Other server/infrastructure failures may not use the API envelope or JSON; inspect HTTP status and content type. (`default`), Upload state or verification failure. (`422`), Rate limited. Honour Retry-After. (`429`)

## finalizeAsset

`POST /api/staff/media-assets/{asset}/finalize`

Output set must equal reservation. HEADs required objects and structurally validates HLS when declared; not codec verification or an enforcement of all upload records completed. Complete every upload first. Success available/verified but hidden. Existing verified available asset returns current state. No Idempotency-Key required. Requires an active staff/admin account and explicit token ability concert-media:upload.

Authentication: Sanctum bearer. Ability: `concert-media:upload`.

| Parameter | Location | Required | Example / meaning |
| --- | --- | --- | --- |
| `asset` | path | yes | `00000004-0000-4000-8000-000000000001`. Public resource UUID. |

Request example:

```json
{
  "expected_outputs": [
    "original",
    "fallback_mp4"
  ]
}
```

Response example — **HTTP 200**:

```json
{
  "success": true,
  "message": "Media asset finalised.",
  "data": {
    "uuid": "00000004-0000-4000-8000-000000000001",
    "collection_uuid": "00000003-0000-4000-8000-000000000001",
    "media_type": "video",
    "display_name": "Opening performance",
    "original_filename": "opening.mp4",
    "status": "available",
    "is_visible": false,
    "sort_order": 10,
    "size_bytes": 123456,
    "duration_seconds": null,
    "mime_type": "video/mp4",
    "extension": "mp4",
    "expected_outputs": [
      "original",
      "fallback_mp4"
    ],
    "verified_at": "2026-10-18T08:00:00.000000Z",
    "created_at": "2026-10-18T08:00:00.000000Z",
    "updated_at": "2026-10-18T08:00:00.000000Z"
  }
}
```

Error responses: Missing, invalid, expired or revoked bearer token. (`401`), Account/policy/ability denied. (`403`), Resource absent or unavailable. (`404`), Validation, state, checksum, size-policy or idempotency conflict; inspect errors. (`422`), Other server/infrastructure failures may not use the API envelope or JSON; inspect HTTP status and content type. (`default`), Rate limited. Honour Retry-After. (`429`)

## listLegacyMedia

`GET /api/staff/concerts/{concert}/legacy-media`

Only configured legacy prefixes for this concert. Empty objects and zero configured_prefixes are valid. Never arbitrary S3 browsing. Requires an active staff/admin account and explicit token ability concert-media:read.

Authentication: Sanctum bearer. Ability: `concert-media:read`.

| Parameter | Location | Required | Example / meaning |
| --- | --- | --- | --- |
| `concert` | path | yes | `00000002-0000-4000-8000-000000000001`. Public resource UUID. |
| `cursor` | query | no | `opaque-cursor`. Omit initially; use data.next_cursor, including after an empty page. Encrypted, expiring cursor scoped to concert. |

Request body: none.

Response example — **HTTP 200**:

```json
{
  "success": true,
  "message": "Legacy media returned.",
  "data": {
    "objects": [
      {
        "object_ref": "REDACTED-OPAQUE-REFERENCE",
        "filename": "opening.mp4",
        "size_bytes": 123456,
        "last_modified": "2026-10-18T08:00:00.000000Z"
      }
    ],
    "next_cursor": null,
    "configured_prefixes": 1
  }
}
```

Error responses: Missing, invalid, expired or revoked bearer token. (`401`), Account/policy/ability denied. (`403`), Resource absent or unavailable. (`404`), Validation, state, checksum, size-policy or idempotency conflict; inspect errors. (`422`), Other server/infrastructure failures may not use the API envelope or JSON; inspect HTTP status and content type. (`default`)

## importLegacyMedia

`POST /api/staff/media-collections/{collection}/imports`

Use the short-lived opaque object_ref from listing, not a full key. Object remains in legacy storage; this does not upload/copy it. Imported asset starts available/verified and hidden. is_visible true is rejected. Requires an active staff/admin account and explicit token ability concert-media:upload.

Authentication: Sanctum bearer. Ability: `concert-media:upload`.

| Parameter | Location | Required | Example / meaning |
| --- | --- | --- | --- |
| `collection` | path | yes | `00000003-0000-4000-8000-000000000001`. Public resource UUID. |
| `Idempotency-Key` | header | yes | `70000000-0000-4000-8000-000000000001`. Generate a new UUID per operation. Reuse it only for an unchanged retry by the same user. |

Request example:

```json
{
  "object_ref": "REDACTED-OPAQUE-REFERENCE",
  "display_name": "Opening performance",
  "sort_order": 10,
  "is_visible": false
}
```

Response example — **HTTP 201**:

```json
{
  "success": true,
  "message": "Legacy media asset imported.",
  "data": {
    "uuid": "00000004-0000-4000-8000-000000000001",
    "collection_uuid": "00000003-0000-4000-8000-000000000001",
    "media_type": "video",
    "display_name": "Opening performance",
    "original_filename": "opening.mp4",
    "status": "available",
    "is_visible": false,
    "sort_order": 10,
    "size_bytes": 123456,
    "duration_seconds": null,
    "mime_type": "video/mp4",
    "extension": "mp4",
    "expected_outputs": [],
    "verified_at": "2026-10-18T08:00:00.000000Z",
    "created_at": "2026-10-18T08:00:00.000000Z",
    "updated_at": "2026-10-18T08:00:00.000000Z"
  }
}
```

Error responses: Missing, invalid, expired or revoked bearer token. (`401`), Account/policy/ability denied. (`403`), Resource absent or unavailable. (`404`), Validation, state, checksum, size-policy or idempotency conflict; inspect errors. (`422`), Other server/infrastructure failures may not use the API envelope or JSON; inspect HTTP status and content type. (`default`), Rate limited. Honour Retry-After. (`429`)

## listCompetitionObjects

`GET /api/competitions/objects`

Uses competition disk, not concert upload disk. May target live competition storage even in local environment. Does not issue concert upload requests. Requires an active staff/admin account and explicit token ability competition-objects:read.

Authentication: Sanctum bearer. Ability: `competition-objects:read`.

| Parameter | Location | Required | Example / meaning |
| --- | --- | --- | --- |
| `prefix` | query | no | ``. Relative path, no traversal or control characters. |
| `limit` | query | no | `100`.  |
| `continuation_token` | query | no | `opaque-token`. Use data.pagination.next_token, not a staff-discovery cursor. |

Request body: none.

Response example — **HTTP 200**:

```json
{
  "success": true,
  "message": "Competition objects returned.",
  "data": {
    "disk": "s3_competitions",
    "prefix": "",
    "breadcrumbs": [],
    "directories": [
      {
        "type": "directory",
        "name": "Example event",
        "prefix": "Example event"
      }
    ],
    "files": [],
    "pagination": {
      "limit": 100,
      "next_token": null,
      "has_more": false
    }
  }
}
```

Error responses: Missing, invalid, expired or revoked bearer token. (`401`), Account/policy/ability denied. (`403`), Validation, state, checksum, size-policy or idempotency conflict; inspect errors. (`422`), Other server/infrastructure failures may not use the API envelope or JSON; inspect HTTP status and content type. (`default`)

## listDownloadLinks

`GET /api/download-links`

List download link metadata. Requires an active staff/admin account and explicit token ability download-links:manage.

Authentication: Sanctum bearer. Ability: `download-links:manage`.

| Parameter | Location | Required | Example / meaning |
| --- | --- | --- | --- |
| `page` | query | no | `1`.  |

Request body: none.

Response example — **HTTP 200**:

```json
{
  "success": true,
  "message": "Download links returned.",
  "data": [
    {
      "uuid": "00000006-0000-4000-8000-000000000001",
      "key": "Example event/routine.mp4",
      "disk": "s3_competitions",
      "original_filename": "routine.mp4",
      "purpose": "Example delivery",
      "status": "active",
      "expires_at": "2026-11-17T08:00:00.000000Z",
      "first_opened_at": null,
      "last_opened_at": null,
      "download_count": 0,
      "revoked_at": null,
      "revoke_reason": null,
      "notes": null,
      "created_at": "2026-10-18T08:00:00.000000Z",
      "updated_at": "2026-10-18T08:00:00.000000Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 50,
    "total": 1
  }
}
```

Error responses: Missing, invalid, expired or revoked bearer token. (`401`), Account/policy/ability denied. (`403`), Validation, state, checksum, size-policy or idempotency conflict; inspect errors. (`422`), Other server/infrastructure failures may not use the API envelope or JSON; inspect HTTP status and content type. (`default`)

## createDownloadLinks

`POST /api/download-links`

Not idempotent: an uncertain retry may create new links. Allowed disks server-configured, default s3_competitions; default days 60. Original public URL is returned only at creation. This generic download flow is not the concert-original web download endpoint. Requires an active staff/admin account and explicit token ability download-links:manage.

Authentication: Sanctum bearer. Ability: `download-links:manage`.

Request example:

```json
{
  "keys": [
    "Example event/routine.mp4"
  ],
  "disk": "s3_competitions",
  "days": 30,
  "purpose": "Example delivery",
  "notes": null
}
```

Response example — **HTTP 201**:

```json
{
  "success": true,
  "message": "Download links created.",
  "data": [
    {
      "uuid": "00000006-0000-4000-8000-000000000001",
      "key": "Example event/routine.mp4",
      "url": "https://app.example.invalid/download/REDACTED",
      "expires_at": "2026-11-17T08:00:00.000000Z",
      "status": "active"
    }
  ]
}
```

Error responses: Missing, invalid, expired or revoked bearer token. (`401`), Account/policy/ability denied. (`403`), Validation, state, checksum, size-policy or idempotency conflict; inspect errors. (`422`), Other server/infrastructure failures may not use the API envelope or JSON; inspect HTTP status and content type. (`default`)

## getDownloadLink

`GET /api/download-links/{downloadLink}`

Get download link metadata. Requires an active staff/admin account and explicit token ability download-links:manage.

Authentication: Sanctum bearer. Ability: `download-links:manage`.

| Parameter | Location | Required | Example / meaning |
| --- | --- | --- | --- |
| `downloadLink` | path | yes | `00000006-0000-4000-8000-000000000001`. Public resource UUID. |

Request body: none.

Response example — **HTTP 200**:

```json
{
  "success": true,
  "message": "Download link returned.",
  "data": {
    "uuid": "00000006-0000-4000-8000-000000000001",
    "key": "Example event/routine.mp4",
    "disk": "s3_competitions",
    "original_filename": "routine.mp4",
    "purpose": "Example delivery",
    "status": "active",
    "expires_at": "2026-11-17T08:00:00.000000Z",
    "first_opened_at": null,
    "last_opened_at": null,
    "download_count": 0,
    "revoked_at": null,
    "revoke_reason": null,
    "notes": null,
    "created_at": "2026-10-18T08:00:00.000000Z",
    "updated_at": "2026-10-18T08:00:00.000000Z"
  }
}
```

Error responses: Missing, invalid, expired or revoked bearer token. (`401`), Account/policy/ability denied. (`403`), Resource absent or unavailable. (`404`), Other server/infrastructure failures may not use the API envelope or JSON; inspect HTTP status and content type. (`default`)

## revokeDownloadLink

`PATCH /api/download-links/{downloadLink}/revoke`

Revoke a download link. Requires an active staff/admin account and explicit token ability download-links:manage.

Authentication: Sanctum bearer. Ability: `download-links:manage`.

| Parameter | Location | Required | Example / meaning |
| --- | --- | --- | --- |
| `downloadLink` | path | yes | `00000006-0000-4000-8000-000000000001`. Public resource UUID. |

Request example:

```json
{
  "reason": "Example owner-approved revocation"
}
```

Response example — **HTTP 200**:

```json
{
  "success": true,
  "message": "Download link revoked.",
  "data": {
    "uuid": "00000006-0000-4000-8000-000000000001",
    "key": "Example event/routine.mp4",
    "disk": "s3_competitions",
    "original_filename": "routine.mp4",
    "purpose": "Example delivery",
    "status": "revoked",
    "expires_at": "2026-11-17T08:00:00.000000Z",
    "first_opened_at": null,
    "last_opened_at": null,
    "download_count": 0,
    "revoked_at": "2026-10-18T08:00:00.000000Z",
    "revoke_reason": "Example owner-approved revocation",
    "notes": null,
    "created_at": "2026-10-18T08:00:00.000000Z",
    "updated_at": "2026-10-18T08:00:00.000000Z"
  }
}
```

Error responses: Missing, invalid, expired or revoked bearer token. (`401`), Account/policy/ability denied. (`403`), Resource absent or unavailable. (`404`), Validation, state, checksum, size-policy or idempotency conflict; inspect errors. (`422`), Other server/infrastructure failures may not use the API envelope or JSON; inspect HTTP status and content type. (`default`)

## listDownloadAccesses

`GET /api/download-links/{downloadLink}/accesses`

Contains personal activity metadata; use named authorised staff access and redact before sharing diagnostics. Requires an active staff/admin account and explicit token ability download-links:manage.

Authentication: Sanctum bearer. Ability: `download-links:manage`.

| Parameter | Location | Required | Example / meaning |
| --- | --- | --- | --- |
| `downloadLink` | path | yes | `00000006-0000-4000-8000-000000000001`. Public resource UUID. |
| `page` | query | no | `1`.  |

Request body: none.

Response example — **HTTP 200**:

```json
{
  "success": true,
  "message": "Download link accesses returned.",
  "data": [
    {
      "id": 1,
      "accessed_at": "2026-10-18T08:00:00.000000Z",
      "ip_address": "192.0.2.1",
      "user_agent": "Example client",
      "referrer": null,
      "was_successful": true,
      "failure_reason": null,
      "created_at": "2026-10-18T08:00:00.000000Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 50,
    "total": 1
  }
}
```

Error responses: Missing, invalid, expired or revoked bearer token. (`401`), Account/policy/ability denied. (`403`), Resource absent or unavailable. (`404`), Validation, state, checksum, size-policy or idempotency conflict; inspect errors. (`422`), Other server/infrastructure failures may not use the API envelope or JSON; inspect HTTP status and content type. (`default`)

## Shared error examples

**401, protected operation:**

```json
{"success":false,"message":"Unauthenticated.","errors":{}}
```

**401, invalid login:**

```json
{"success":false,"message":"Invalid credentials.","errors":{}}
```

**403, policy/ability denied:**

```json
{"success":false,"message":"Unauthorised.","errors":{}}
```

**403, login rejected:** inactive accounts use `This account is inactive.`;
customer accounts use `This account cannot use staff API access.` with the same
error envelope. **404** uses the envelope with a not-found message (message text
can vary by missing resource).

**422, example validation failure:**

```json
{"success":false,"message":"The given data was invalid.","errors":{"expected_outputs":["The original output is required."]}}
```

Validation keys/messages depend on the request. Current ingest state/idempotency
conflicts also return 422, not 409. **429** generally uses
`{"message":"Too Many Attempts."}` and `Retry-After`; honour the delay.
Unexpected 5xx failures may use `{"message":"Server Error"}` or another shape.
S3 upload errors are not DancePro API responses; retain only sanitized error
codes/status/request IDs, never bearer tokens or presigned query strings.

## Concert customer web delivery

`GET /concerts/{concert}/media/{asset}/download` uses UUID path parameters.
Authentication is the unlocked concert session, a valid Laravel signed URL
(`expires`, `signature`, `access=1`), or a concert without a password. No bearer
ability grants customer access. Success is **302**, with an empty body and a
non-null string `Location` header pointing to a short-lived S3 presigned URL
on the asset's disk, even when CloudFront playback is configured. The attachment
filename is signed with the URL;
`Cache-Control: private, no-store` prevents caching the redirect.

Example: `Location: https://concert-bucket.example.invalid/collection/media/asset/original/video.mp4?response-content-disposition=attachment%3B%20filename%3D%22performance.mp4%22&X-Amz-Algorithm=AWS4-HMAC-SHA256&X-Amz-Credential=REDACTED&X-Amz-Date=20261024T060000Z&X-Amz-Expires=900&X-Amz-SignedHeaders=host&X-Amz-Signature=REDACTED`.
Errors: 403 for missing access; 404 for unavailable concerts, unrelated,
unpublished, hidden or unavailable assets; 503 for unsupported storage disks.
Storage/signing infrastructure errors may return 500. The original recorded
storage key is downloaded. Legacy disks always use their own S3 origin.
The original-download button and download manager use this same attachment route.
Browsers may still require permission for multiple automatic downloads.
These web operations remain outside the JSON API `paths` contract; their delivery
contract is recorded in the OpenAPI `x-concert-web-delivery` extension.

Playback retains its existing CloudFront HLS/progressive contract. Progressive
MP4 URLs authenticate with their signature; the player only requests cross-origin
cookies for HLS. Legacy playback still fails closed with 503 because the concert
CloudFront distribution is only mapped to `s3_concerts`.
