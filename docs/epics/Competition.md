# Competition Epic

> Product direction update: use the [integrated media plan](../specifications/Integrated-Media-Product-Plan.md)
> and [roadmap](../Roadmap.md) for new scope and sequencing. Earlier status and
> priorities below describe the previous delivery slice; they do not establish
> completion of the expanded workflows.

## Purpose

Capture the product and technical direction for rebuilding DancePro competition
capabilities.

## Current Status

The event Programme editor now supports manual drafts, bulk entry, protected PDF
preview and confirmed preparation snapshots. See the
[programme milestone](../milestones/Integrated-Media-Event-Programme-Foundations.md).
Local automatic extraction and the native offline uploader are now implemented;
see the [current roadmap](../Roadmap.md) for their delivery boundaries.

Media → Competition Photos now provides an admin-only browser of the approved
programme and verified uploader photos. It uses numbered days only for multi-day
events, then sections and competitors, with both photo roles in one album. This
private local-storage preview is separate from the older S3 object browser below.
Private watermarked thumbnails/previews now replace originals in the admin gallery.
Section contribution readiness now shows outstanding photo roles and administrator
exceptions without publishing. Gallery titles and photo covers can now be configured
separately from planning, with default event artwork and an audit history.
An admin-only Customer Preview now filters by automatic section readiness and gates
photo/cover requests. No manual section-release approval is required.

Competition video batch transfer is now implemented in the Mac V-station mode and
the staff API. Competition Media remains the source of truth and uses the existing
`year / event code / filename` structure. Its event folders can be linked to the
customer catalogue without moving or changing stored objects. Event-level video
matching shows batches, unmatched files and routine HOLD controls. Verified, assigned files in a completed batch
become customer-available together unless held. Unholding activates video
entitlements for previously paid pending packages. Direct PUT currently supports
files up to 5 GiB; larger files and later clips for a completed routine still
need a reviewed transfer path. Customer payment capture and final delivery are
not yet implemented. See [ADR-0038](../decisions/ADR-0038-Competition-Video-Batches-and-Holds.md)
and [ADR-0041](../decisions/ADR-0041-Existing-Competition-Media-Videos.md).

The initial technical Competition slice is implemented. Authenticated API and
admin users can browse the `s3_competitions` object hierarchy, select files and
create database-backed tracking links through the generic Downloads feature.
Expiry, revocation, access logging and server-side S3 or CloudFront signing are
implemented by Downloads.

The wider Competition business domain has not yet been fully specified. The
current object browser and download workflow must not be mistaken for complete
Competition CRUD or product workflows.

## Scope

- Competition object-browser structure under `app/Features/Competition`.
- Use the generic Downloads bounded context for secure download tracking.
- Server-side generation of signed download redirects through Downloads.
- Future competition workflows as they are planned.

## Links to Related Documentation

- [Downloads Epic](Downloads.md)
- [Download Links Specification](../specifications/Download-Links.md)
- [Competition Downloads Specification](../specifications/Competition-Downloads.md)
- [Milestone 02 - Competition](../milestones/Milestone-02-Competition.md)
- [Architecture](../handbook/Architecture.md)
- [AWS](../handbook/AWS.md)
- [Security](../handbook/Security.md)

## Notes / Future Work

Add product workflows and boundaries as they are confirmed. Avoid copying
legacy Laravel 8 behaviour without reviewing it against the V2 architecture.
Do not implement download links as Competition-owned records or controllers.


Customer event selection and separate login-code verification now provide existing-account web browsing via
My Competitions, using the same automatic section readiness checks as admin preview.
Direct watermarked image requests require registration and ready sections. See
[customer access](../api/Customer-Competition-Access.md); commerce and original delivery
remain pending. Both iPhone and Android are required for the future customer app.
