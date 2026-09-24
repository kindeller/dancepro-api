# ADR-0039: Concert photo album sync and release groups

## Decision

Verified concert photographs uploaded from the Mac app remain the source files for the customer gallery. An administrator explicitly syncs those files from the event's confirmed programme into private `MediaCollection` albums linked to the corresponding `Concert`. Album and asset UUIDs are derived from the stable programme item and uploaded-file identities, so repeating the sync does not duplicate customer media. A later sync preserves album titles and ordering edited by an administrator.

Sync never publishes a gallery. Albums begin as private drafts. An administrator groups one or more unreleased photo albums belonging to the same studio and releases the group in one transaction. This supports separate junior and senior releases as well as one release containing several related concerts. Release records the actor and selected albums, applies the chosen concert watermark, and changes every album in the group to customer-visible and published together.

## Consequences

The uploader completion action cannot expose customer media. Missing files or identity conflicts stop sync instead of silently dropping or overwriting a photo. Customer gallery queries continue to require a published, customer-visible collection and a verified studio relationship.

This first administration workflow uses confirmed programme folders as its album structure. Staff can rename and reorder draft albums before release. Parent grouping beyond the linked concert, automatic class-name removal, cover selection, guest purchasing, payment and original delivery remain separate work.
