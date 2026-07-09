# Download Links Specification

## Purpose

Download links provide database-backed public tracking URLs for private storage
objects. They avoid exposing long-lived S3 or CloudFront URLs and keep all
signing decisions server-side.

## Database Tables

`download_links` stores one public link record per private object key. It
contains the UUID, generating user, storage disk/key, original filename,
purpose, token hash, status, expiry timestamps, revocation metadata, notes, and
soft deletion timestamp.

`download_accesses` stores every resolved access attempt for a known link,
including the access time, IP address, user agent, referrer, success flag, and
failure reason.

Raw public tokens are never stored. Only `token_hash` is persisted.

## Endpoint Behaviour

### Create Links

`POST /api/download-links`

Requires Sanctum authentication.

Request:

```json
{
  "keys": ["folder/file.mp4"],
  "disk": "s3_competitions",
  "days": 60,
  "purpose": "Competition download links",
  "notes": "Optional internal note"
}
```

Validation:

- `keys` is required, array, max 200 items.
- `keys.*` is required and string.
- `disk` is nullable string and defaults to `s3_competitions`.
- `days` is nullable integer, minimum 1, maximum 60.
- `purpose` is nullable string, max 150 characters.
- `notes` is nullable string, max 1000 characters.

Behaviour:

- Normalise and deduplicate keys.
- Reject empty keys, absolute paths, traversal such as `..`, and control
  characters.
- Reject disks not listed in `config/downloads.php`.
- Create one `download_links` row per unique key.
- Generate a long random token and store only its SHA-256 hash.
- Return the raw token only inside the Laravel tracking URL.

Response:

```json
{
  "success": true,
  "message": "Download links created.",
  "data": [
    {
      "uuid": "7d90fdb4-8a28-47bb-8c87-e64f6ef64d2a",
      "key": "folder/file.mp4",
      "url": "https://api.example.com/download/{token}",
      "expires_at": "2026-09-07T00:00:00.000000Z",
      "status": "active"
    }
  ]
}
```

### Public Redirect

`GET /download/{token}`

Does not require Sanctum authentication.

Behaviour:

- Hash the incoming token.
- Find `download_links.token_hash`.
- Return a safe 404 response if no link exists.
- Return a safe 410 response and log access if the link is expired or revoked.
- For a valid link, update first/last opened timestamps, increment
  `download_count`, create a successful `download_accesses` row, generate a
  short-lived signed URL, and redirect.

### Management

Authenticated management endpoints:

- `GET /api/download-links`
- `GET /api/download-links/{downloadLink}`
- `PATCH /api/download-links/{downloadLink}/revoke`
- `GET /api/download-links/{downloadLink}/accesses`

`{downloadLink}` uses the public UUID route key, not the internal database ID.

## Expiry, Revocation, and Access Logging

Expired links do not redirect. When an expired link is opened, its status is
recorded as `expired` and a failed access row is created.

Revoked links do not redirect. Revocation stores `revoked_at`,
`revoked_by_user_id`, optional `revoke_reason`, and status `revoked`.

Access rows are recorded for known links whether the attempt succeeds or fails.
Unknown tokens are not logged because they cannot be safely associated with a
link.

## Security Rules

- Do not expose AWS credentials or CloudFront private keys.
- Do not store raw public tokens.
- Do not return long-lived direct asset URLs from the creation API.
- Do not trust client-submitted storage keys.
- Keep token entropy high and token hashes deterministic for lookup.
- Keep allowed storage disks explicit and narrow.
- Keep S3 and CloudFront signing logic outside controllers.
- Public failure responses must not leak internal storage or signing details.
