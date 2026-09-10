# Milestone 03 - Concert Production Readiness

## Purpose

Finish and harden the existing Concert experience so staff can prepare a
concert and customers can securely access, play and download its media using
production storage and delivery infrastructure.

This milestone improves functionality already present in the repository. It
does not introduce Customer accounts, ordering, payments or a broader
Competition domain.

## Current Status

In progress.

The public studio and concert journey, password access, access logging, ordered
video playlist, next-item playback, individual downloads, bulk-download manager
and basic studio/concert administration are implemented. Next-video playback
has been manually validated on desktop and mobile across multiple browsers.

The application-side streaming foundation is implemented. Playback resolves an
HLS manifest, progressive stream fallback and original in order; generates
asset-prefix-scoped CloudFront signed cookies; and uses native HLS or `hls.js`
with automatic and manual quality selection. New concert prefixes no longer
contain studio identity.

AWS provisioning and production validation remain outstanding. Progressive
video/download responses are still proxied by Laravel, and staff cannot yet
manage concert media through the web application or Flutter API. The target
desktop ingest, presigned-upload, legacy-import and assignment contract is now
documented but not implemented.

## In Scope

### Concert media delivery

- Authorise playback through Laravel.
- Redirect authorised playback to a short-lived signed S3 or CloudFront URL.
- Ensure the storage or CDN response handles byte-range requests and seeking.
- Keep storage credentials, private keys, object keys and internal identifiers
  out of public business-entity APIs.
- Prefer an available streaming rendition for playback while retaining the
  original for protected download.
- Define and document the required S3, CloudFront, cache, CORS and response
  header configuration.

### Concert downloads

- Replace direct controller downloads with the generic Downloads bounded
  context.
- Retain the public Laravel tracking URL before redirecting to a short-lived
  signed asset URL.
- Associate download records with their concert, media collection and media
  asset where available.
- Apply the same tracking, expiry, revocation and signing behavior to individual
  and bulk concert downloads.
- Add concert-level download visibility for staff.

### Staff media management

- List media collections belonging to a concert.
- Create and edit collections.
- Replace media without losing its stable business identity.
- Edit display names, playlist order, visibility and publication state.
- Archive or delete managed assets safely.
- Do not bulk-create managed asset records for every storage-derived photo.

The web admin remains responsible for studio/concert management, publication,
availability and customer access. The macOS Flutter application supplies the
lower-level media ingest workflow:

- Authenticate with an active staff/admin account and a limited Sanctum device
  token stored in the macOS Keychain.
- Limit initial use to trusted DancePro staff; add explicit studio/concert
  account scope before client-studio or venue staff receive access.
- Select staff-visible studios and concerts, including drafts.
- Create or select a video collection under an existing concert.
- Reserve a managed asset UUID before upload.
- Convert locally to a compressed fallback MP4 and optional 720p/480p HLS.
- Upload directly to private S3 through short-lived, Laravel-authorised
  presigned requests without receiving an AWS credential.
- Use resumable multipart upload for large MP4s and checksummed single-object
  uploads for smaller HLS objects.
- Finalise only after Laravel verifies object metadata and playlist references.
- Import an existing MP4 only through a server-constrained legacy prefix and
  opaque object reference.
- Update display name, playlist order and visibility, without publishing a
  concert or changing customer access.

### Programs and cover media

- Upload and replace concert programs.
- Upload and replace studio and concert cover images.
- Preserve the existing unavailable states when optional content is absent.
- Keep protected content behind authorised server-side delivery where required.

### Playback polish

- Preserve the working ordered playlist and automatic next-video playback.
- Retain fullscreen playback across item changes where supported by the browser.
- Add real thumbnails when available.
- Add clear loading, unavailable-media, signing-failure and final-item states.
- Verify desktop and mobile playback against production-like S3 or CloudFront
  responses, including seeking.

### Administration and hardening

- Add the staff permissions and customer-access administration required to
  operate the current Concert workflow.
- Review login, session, rate-limit, access-log privacy and retention settings.
- Cover the new delivery and administration behavior with feature tests.
- Complete a production configuration review and deployment smoke test.
- Update the Concert, AWS, testing and deployment documentation as behavior is
  implemented.

## Deferred

The following existing documented areas remain future work and are not required
for this milestone:

- Customer account workflows.
- Saved concerts, favourites and permanent customer libraries.
- Ordering, purchasing and payment processing.
- The wider Competition business domain.
- Automatic server-side transcoding and archive restoration. Local conversion
  in the Flutter ingest application is part of this milestone.

## Completion Criteria

- Staff can configure and populate a concert without seed or direct database
  manipulation.
- Flutter can upload or import and assign a concert MP4 without embedded AWS
  credentials, direct database manipulation or unrestricted bucket access.
- A fallback-only compressed MP4 remains playable when HLS conversion fails.
- The production stream ladder is limited to 720p plus optional 480p; originals
  remain protected downloads and are not advertised as streaming variants.
- Published concert playback uses short-lived production media delivery and
  supports byte-range seeking.
- Original downloads use database-backed tracking links and short-lived signed
  redirects.
- Programs and cover media can be managed through staff workflows.
- Existing password, availability, approval, playlist, next-item and bulk
  download behavior continues to work.
- Applicable tests, route inspection and formatting checks pass through Sail.
- AWS and deployment configuration is documented and smoke-tested.

## Links to Related Documentation

- [Concert Epic](../epics/Concert.md)
- [DancePro V2 Functional Specification](../specifications/DancePro-V2-Functional-Specification.md)
- [Concerts and Media Database Migration](../specifications/DancePro-V2-Concerts-Media-Database-Migration-Spec.md)
- [Downloads Epic](../epics/Downloads.md)
- [Download Links Specification](../specifications/Download-Links.md)
- [AWS](../handbook/AWS.md)
- [Security](../handbook/Security.md)
- [Testing](../handbook/Testing.md)
- [Deployment](../handbook/Deployment.md)
- [ADR-0003 - Desktop Media Ingest and Assignment](../decisions/ADR-0003-Desktop-Media-Ingest-and-Assignment.md)
- [Flutter Desktop Media Ingest API](../specifications/Flutter-Desktop-Media-Ingest-API.md)
