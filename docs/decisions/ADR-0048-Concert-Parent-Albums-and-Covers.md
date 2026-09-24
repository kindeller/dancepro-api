# ADR-0048: Concert parent albums and covers

## Status

Accepted — 23 September 2026

## Context

A studio may have several concerts whose routine albums should be presented as
one customer gallery. The existing concert photo release group already records
the exact albums released together, while uploader Top Pics identify suitable
promotional images. A second parent-group model would duplicate release state and
risk the customer hierarchy drifting from the albums actually published.

## Decision

Treat each released `ConcertPhotoReleaseGroup` as the customer-visible parent
album. A parent may contain routine albums from several concerts belonging to the
same studio. Opening it shows those routine albums in their saved order, with the
concert name shown when the parent spans more than one concert.

Each parent and routine album stores an optional `cover_media_asset_id`. Sync uses
the first routine photo as the initial routine cover. Parent creation uses the first
available uploader Top Pic. Staff may change either cover after release; parent
candidates remain restricted to Top Pics. Cover changes are recorded in the release
group change history.

Public gallery cards may display the selected parent image through a dedicated
watermarked thumbnail route. Full photos remain behind gallery access. Registered
and guest customers can browse and select photos from every concert in the parent;
the cart records the photo's actual concert identity.

## Consequences

- Release grouping, gallery hierarchy and cover administration share one source of truth.
- Existing media assets and storage keys are unchanged.
- Removing a selected asset clears the foreign key; the gallery falls back to an
  eligible Top Pic or routine photo.
- One watermarked parent thumbnail becomes publicly visible when its gallery is released.

