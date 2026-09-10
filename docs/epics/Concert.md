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

Local demonstration media is intentionally temporary. Production media delivery
still needs to move from Laravel-proxied responses to authorised, short-lived
S3 or CloudFront delivery. Staff media management and the documented Flutter
desktop ingest API are the next work required to make the existing Concert
experience ready for use.

## Scope

- Public studio browsing, limited to studios with an available concert.
- Published-concert browsing with approval and availability controls.
- Password and student-name access with session unlocks and attempt logging.
- Managed video playlist, next-item playback and protected original downloads.
- Optional program and external-gallery links with explicit empty states.
- Read-only public studio and concert API discovery.
- Local demonstration content for workflow testing.
- Staff studio list, creation and editing.
- Staff concert list, creation and editing.
- Staff approval, publication, availability and password controls.

## Production-Readiness Priorities

Work through these areas before beginning another major product capability:

1. Provision and validate the documented S3 and CloudFront infrastructure for
   the implemented HLS signed-cookie playback flow, then move remaining
   progressive video traffic behind the production delivery boundary.
2. Route protected concert originals through the generic Downloads bounded
   context for consistent tracking, expiry, revocation and signing.
3. Add web staff media collection and asset management for review, publication,
   display names, ordering, visibility and safe archive or replacement.
4. Add the limited Flutter desktop media API for staff discovery, direct
   presigned upload, resumable multipart MP4 upload, legacy MP4 import,
   verification and assignment. The client must not contain AWS credentials.
5. Add program and studio/concert cover-image upload and replacement workflows.
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

## Notes / Future Work

- Customer accounts, ordering and payments remain future work and are not
  required to complete the current Concert production-readiness milestone.
- Automatic server-side transcoding, archive restoration and deeper streaming
  optimisation remain future enhancements. Local Flutter conversion and secure
  upload are part of production readiness.
