# ADR-0026: Private competition photo previews

Date: 2026-09-22
Status: Implemented for local administrator gallery review

## Decision

Competition gallery images use server-rendered JPEG derivatives rather than original
bytes. The existing authenticated, event-scoped image endpoint accepts two sizes:
thumbnail (480-pixel longest edge) and preview (1600-pixel longest edge). These are
initial review settings, JPEG quality 88, with no upscaling. Customer quality/cost
validation remains necessary before production release.

Use the existing square DANCEPRO WATERMARK COMP 2026 copy.png from the owner's
DancePro graphics collection, copied unchanged into resources/images/watermarks.
Centre it, fitting to the narrowest photo side. Apply the image's existing opacity;
bake the watermark into pixels rather than adding a removable browser overlay.
The artwork includes the existing $45 package text. A configurable watermark library
and per-gallery overrides remain future work.

Decode verified JPEGs, resize, apply EXIF orientation (including mirrored variants),
then watermark and encode a fresh JPEG. EXIF/comments are not copied. Original bytes,
file records and purchase identities remain unchanged. Processing checks dimensions,
available memory and source checksum; failure never falls back to serving originals.

Cache derivatives on private local storage, under a file UUID and hash of source
checksum, watermark bytes, renderer version, dimensions and quality. Immutable cache
keys invalidate naturally when rendering inputs change. Per-key filesystem locking
and atomic publication prevent partial JPEGs reaching parallel requests. Temporary
files are cleaned up on failure. Cache cleanup and distributed generation/storage
are deferred until production storage is chosen; no original cleanup is introduced.

## Boundaries

Authorization and event/gallery/plan scoping run before cache access on every request.
Only canonical verified receipts are eligible. Responses remain private/no-store;
this cache is server-side, never a public storage URL. No public original endpoint
is added. Customer entitlements, watermarks by gallery type, publication, section
release gates and video transfer remain separate work. Photos are still private.

## Validation

Tests cover thumbnail/preview dimensions, no upscaling, burned-in watermark pixels,
all eight orientation transforms, metadata removal, original preservation, cache
reuse, damaged-source failure, image access isolation and invalid size rejection.
A synthetic portrait was rendered and visually inspected with the supplied artwork.
No owner photo was modified or uploaded for verification.
