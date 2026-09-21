# Concert Epic

## Purpose

Provide a secure customer experience for discovering studios, accessing released
concerts, playing concert media and downloading protected originals.

## Current Status

The customer discovery, access and playback foundation is implemented. Staff
studio and concert creation, editing, approval, release, availability and
access-password management are available in the web admin. The ordered playlist
automatically advances to the next video and has been manually validated on
desktop and mobile across multiple browsers without closing the active player.

Concert video playback, original downloads, covers and programmes now use
authorised, short-lived CloudFront delivery from the dedicated S3 media store.
Staff media management and the Flutter desktop ingest API are implemented.
Production readiness still depends on provisioning and verifying the private
S3 origin, CloudFront signing, origin access control and deployment settings.

## Scope

- Public studio browsing, limited to studios with an available concert.
- Published-concert browsing with approval and availability controls.
- Password and student-name access with session unlocks and attempt logging.
- Managed video playlist, next-item playback and protected original downloads.
- Optional program and external-gallery links with explicit empty states.
- Read-only public studio and concert API discovery.
- Local demonstration content for workflow testing.
- Staff studio list, creation and editing.
- Staff review of booking contact details against the studio directory, with
  field-level discrepancy warnings and explicit update-or-add reconciliation.
- Creation of an unmatched studio directly from booking review, with submitted
  studio and event-contact details prefilled into the full editable studio form.
- Staff concert list, creation and editing.
- Optional link from a concert to one concert booking item. Saving the concert
  copies the booking item's current protected PDF programme to the concert's
  managed S3 slot. Save again after a booking-programme replacement to refresh
  that copy; an unlinked concert can still receive a programme directly.
- Staff approval, publication, availability and password controls.

## Production-Readiness Priorities

Work through these areas before beginning another major product capability:

1. Provision and validate the documented S3 and CloudFront infrastructure for
   signed-cookie HLS and signed-URL delivery. Forward the
   `response-content-disposition` query parameter to the S3 origin for original
   download filenames, and verify that the private origin rejects unsigned
   access.
2. Route protected concert originals through the generic Downloads bounded
   context for consistent tracking, expiry, revocation and signing.
3. Verify the implemented web staff media collection and asset-management
   workflows against production storage and permissions.
4. Verify the implemented Flutter desktop media API and upload flows against
   production storage. The client must not contain AWS credentials.
5. Verify programme and studio/concert cover-image upload and replacement.
6. Polish playback thumbnails, loading, unavailable-media and end-of-playlist
   states while retaining the validated next-video and fullscreen behavior.
7. Add the staff authorization and customer-access administration needed to
   operate the implemented Concert workflow safely.
8. Complete production configuration, security review, automated coverage and
   deployment smoke tests for the full Concert journey.

## Links to Related Documentation

- [Architecture](../handbook/Architecture.md)
- [Security](../handbook/Security.md)
- [Functional specification](../specifications/DancePro-V2-Functional-Specification.md)
- [Concert/media database specification](../specifications/DancePro-V2-Concerts-Media-Database-Migration-Spec.md)
- [Milestone 03 - Concert Production Readiness](../milestones/Milestone-03-Concert-Production-Readiness.md)
- [AWS](../handbook/AWS.md)
- [Flutter Desktop Media Ingest API](../specifications/Flutter-Desktop-Media-Ingest-API.md)
- [ADR-0003 - Desktop Media Ingest and Assignment](../decisions/ADR-0003-Desktop-Media-Ingest-and-Assignment.md)
- [ADR-0009 - Booking Concert Link and Programme Copy](../decisions/ADR-0009-Booking-Concert-Link-and-Programme-Copy.md)

## Notes / Future Work

- Concert bookings retain their submitted contact snapshot after directory
  reconciliation so the original submission remains auditable. Matching uses
  the normalised studio name, then contact email and contact name; no directory
  field is changed automatically.
- Desktop ingest now preserves existing competition-token access while requiring
  explicit media abilities, reconciles multipart completion, validates bounded
  HLS VOD packages, restricts upload destinations and isolates legacy prefixes.
  See [ADR-0004](../decisions/ADR-0004-Media-Ingest-Compatibility-and-Verification.md).
- Customer accounts, ordering and payments remain future work and are not
  required to complete the current Concert production-readiness milestone.
- Automatic server-side transcoding, archive restoration and deeper streaming
  optimisation remain future enhancements. Local Flutter conversion and secure
  upload are part of production readiness.
