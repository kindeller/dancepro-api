# ADR-0019: Exact-byte photo file reservations

Date: 2026-09-21
Status: Registration implemented; transfer and receipt verification pending

Add programme_photo_files under a programme photo contribution. Uniquely identify
a reservation by contribution, stable local photo UUID and SHA-256. Record expected
byte count, original filename and Top Pic metadata. Retries reuse the public file
UUID; contradictory sizes for the same identity/checksum fail atomically. A changed
checksum creates a separate version rather than overwriting an earlier reservation.
Changed Top Pic metadata on a reserved file does not create another file version.
Reservations remain scoped to their immutable programme plan and photographer.

The server reuses contribution registration's live event/revision/assignment/item
validation and programme lock, with an outer transaction so an invalid batch leaves
no partial file or contribution writes. Accept JPEG basenames, expected size from
4 bytes to 512 MiB, lowercase SHA-256 and up to 500 unique photo IDs per request.
No client storage paths, file URLs, upload states or received/verified flags are
accepted. Every record starts reserved. Client checksums are claims about expected
bytes, not proof that the server has received or validated a JPEG.

The Mac actor scans its explicit Ready queue, streams each stable JPEG through
SHA-256 in 1 MiB chunks, refuses final-component symlinks, checks open descriptor
identity and file size/timestamps, and rechecks the current reviewed signature.
Cancellation is checked during reads. No full-image memory buffer is needed for
hashing. Readiness changes still use the existing queue invalidation rules.

After explicit programme/contribution sync, send descriptors in batches of 500.
Validate returned event/plan/photographer/role, photo/item/contribution identity,
size, hash, uniqueness and reserved status. Save results in the existing local sync
receipt. A failed later batch can leave earlier reservations, but replay safely
reuses them. Nothing marks local photos uploaded or culling-locked at this stage.
No file content is transferred and no storage location or gallery is published.

Before transfer, revalidate the live plan/assignment and exact local bytes, provide
server-owned destinations, and verify actual received size/hash/JPEG content.
Resolve gallery mapping and conflicting plans/file versions before accepting
uploads; historical reservations alone must never reassign uploaded or purchased
media or satisfy photographer completion. This is not global cross-plan deduplication.

Owner direction: Sync Programme is temporary. Keep it for now, then remove it
when Upload automatically performs sync, photographer/contribution validation and
reservation before a manually initiated transfer. Do not add another setup button.
