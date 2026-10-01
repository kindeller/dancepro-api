# Concert photo release batches

Date: 30 September 2026
Status: Accepted (owner clarification)

## Decision

A concert photo release group represents an admin publication and notification
batch containing whole events from one studio. It is not a customer gallery level.
Reuse the collection pivot for compatibility: the service expands selected events
to every photo album, validates their current readiness and publishes the batch
within one transaction. The next page reviews one announcement per registered
account, with existing group/email deduplication protecting retries.

Customers navigate through studio and event cards. A concert opens its own routine
albums; a dress rehearsal opens its portraits directly. Explicit historical group
links remain usable. Existing media storage and identifiers do not move.

Native photo access is separate from director video approval. For approved,
uncancelled bookings, explicitly published customer photo collections may open
while their concert record is still draft. Video access continues to require the
existing public-availability/director-release checks. Archived or disabled
published concerts do not gain access through the draft-photo exception.

## Consequences

No group/event schema migration is required. Existing prepared groups remain
visible for review and release; new selections use whole events. All selected
photo roles must be complete, and invalid selections roll back the entire release.
A batch controls which photos appear in one announcement; sending remains a
separate explicit action after review, and does not change video availability.
