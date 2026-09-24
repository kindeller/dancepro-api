# ADR-0017: Working programme sync

Date: 2026-09-21
Status: Implemented foundation; media transfer and publication remain pending

Register the Mac's working programme as an immutable upload plan. Do not mutate
approved revisions, source PDFs, editor drafts or customer galleries. Different
working layouts can coexist; registration does not select a globally active
layout or silently replace another laptop's work. Retries with identical content
return the same public plan UUID. Future upload records must bind to the plan and
item together, not resolve a new local item UUID globally.

The server locks the event and programme, requires the current approved revision,
and validates original identities/types, hierarchy, direct combine aliases and
safe continuous concert folder numbering. Competition structure must match the
approved revision. Client-supported values are normalized; server-only source
metadata is retained from the approved revision. New local identities are scoped
to the plan; identities already owned by another programme node are rejected.
Approved grouping can differ from array order, so folder numbers, not array
position, specify concert media order.

The endpoint requires the existing concert-media:upload write ability and the
administrator programme policy. This reuses the current administrator uploader
scope, including competition preparation. It does not expand crew permissions.
Crew assignments, photographer contributions and upload asset mapping still need
an explicit bridge. There are no AWS operations or media transfer side effects.

Sync is manual. A local atomic programme-sync.json receipt records the server,
plan UUID, revision UUID, server digest and a SHA-256 fingerprint of the exact
local programme. Local edits make the receipt stale. Origin and response identity
checks prevent saving a receipt for a different server/event/revision. Receipt
failure after server success is safely retryable. The receipt is historical,
not proof that the server revision is still current or permission to upload:
the future transfer API must reauthorize and revalidate its plan at use time.

Original documents and photo state remain local and untouched by syncing.
Registration does not reconcile simultaneous photographers' incompatible layouts,
change existing uploaded/purchased media, or authorize publication. Resolve those
constraints in the event/item/contribution bridge before enabling Upload.
