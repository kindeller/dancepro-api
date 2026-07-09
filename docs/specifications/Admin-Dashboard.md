# Admin Dashboard

## Purpose

Provide a small server-rendered admin surface for inspecting platform data while
the API and client applications are still being built.

## Current Scope

The dashboard uses Laravel session authentication and lives under `/admin`.
Authenticated active users can:

- view aggregate download-link counts;
- browse generated download links;
- inspect individual link metadata and access history;
- generate new public tracking URLs for private storage keys;
- revoke existing links.

The dashboard does not introduce a Competition bounded context. Competition
downloads still use the generic Downloads feature with `s3_competitions` storage
keys. Existing public tracking URLs cannot be re-displayed because raw tokens
are never stored.

## Routes

- `GET /login`
- `POST /login`
- `POST /logout`
- `GET /admin`
- `GET /admin/download-links`
- `GET /admin/download-links/create`
- `POST /admin/download-links`
- `GET /admin/download-links/{downloadLink}`
- `PATCH /admin/download-links/{downloadLink}/revoke`

## Notes

This is intentionally functional and conservative. Future dashboard work can
split the UI into richer feature-specific areas once the Competition domain has
database-backed meaning.
