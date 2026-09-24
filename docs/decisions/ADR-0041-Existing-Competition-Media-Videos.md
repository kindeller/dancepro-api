# ADR-0041: Existing competition media videos remain in place

## Decision

The existing `s3_competitions` hierarchy is the source of truth for competition
videos. Its established layout is `year / event code / video filename`, for example
`2026/EDC926/EDC926_07_03.mp4`. The Competition Media object browser remains the
single top-level admin entry point. Video matching and HOLD controls remain
available from an individual competition, without a duplicate Competition Videos
menu item.

An administrator may link an event folder to the customer gallery. Linking reads
the prepared programme and matches filenames to section and competitor numbers. It
creates deterministic `media_collections` and `media_assets` references to the
existing object keys. It never copies, moves, renames, replaces or deletes an S3
object. Repeating the operation is idempotent, and unmatched files remain unchanged
for manual review.

New competition video uploads use the `s3_competitions` disk and the same
`year / event code / filename` layout. An upload is rejected if its target key
already exists, preventing an existing live video from being overwritten.

## Consequences

Existing production videos keep their current URLs and storage keys. Customer
catalogue and order records refer to those keys in place. Database records can be
rebuilt by repeating the non-destructive link operation if necessary. Concert media
continues to use its separate configured upload disk.
