# Milestone 03 - Concert Production Readiness

## Purpose

Finish and harden the existing Concert experience so staff can prepare a
concert and customers can securely access, play and download its media using
production storage and delivery infrastructure.

This milestone improves functionality already present in the repository. It
does not introduce Customer accounts, ordering, payments or a broader
Competition domain.

## Current Status

Planned and ready to begin.

The public studio and concert journey, password access, access logging, ordered
video playlist, next-item playback, individual downloads, bulk-download manager
and basic studio/concert administration are implemented. Next-video playback
has been manually validated on desktop and mobile across multiple browsers.

The remaining gap is operational: staff cannot yet manage concert media through
the application, and concert video/download responses are still proxied by
Laravel instead of using the established S3 and CloudFront delivery boundary.

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
- Browse storage-derived objects only beneath the configured collection prefix.
- Create and edit collections.
- Upload or import a video as a managed asset.
- Replace media without losing its stable business identity.
- Edit display names, playlist order, visibility and publication state.
- Archive or delete managed assets safely.
- Validate the collection prefix and object existence for every submitted
  storage key.
- Do not bulk-create managed asset records for every storage-derived photo.

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
- Automatic transcoding and archive restoration.

## Completion Criteria

- Staff can configure and populate a concert without seed or direct database
  manipulation.
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
