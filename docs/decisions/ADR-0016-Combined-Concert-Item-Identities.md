# ADR-0016: Combined concert item identities

Date: 2026-09-21
Status: Accepted for local photo organization

Combine keeps the destination item UUID. Retain the source node in the local
working programme with merged_into_uuid pointing directly to the active target.
Repeated combines flatten earlier aliases. Folder planning rejects missing,
self-referential, cyclic or competition aliases. Inactive source nodes retain
their title/type/identity but have no active folder number and are omitted from
review/navigation/editing. Editors carry them forward when saving active rows.
The downloaded approved snapshot and PDF remain unchanged.

Preflight both items, stable capture, uploaded locks and ready JPEG-only source
contents. Move the complete source photo batch with the existing no-overwrite
transaction, then use the layout journal to rename active directories and archive
the empty source directory as .dancepro-combined-<source UUID>. Do not delete it.
Only Finder's .DS_Store may remain inside it. Refuse unknown/non-photo contents.
Keep folder renumbering recoverable using the existing directory-identity journal.

If layout preflight fails before journaling, roll the photo batch back. If layout
journaling has started, block review and recover on reopen rather than moving
files against a partly changed structure. A process exit between photo movement
and journal creation can leave an empty source item and moved photos; retry the
combine or use Undo Move while the original layout remains. Never report such an
interruption as an atomic all-or-nothing operation.

On scanning a committed merge, redirect old photo records (including trashed
photos) to the active item. This lets Trash Undo restore into the surviving
folder. Remove Move Undo batches referring to inactive source IDs so they cannot
restore into hidden retired directories. Unrelated move history remains. Combine
has no one-click undo in this slice; the UI explains this before applying it.
Split can reorganize photos afterward using a new item ID.

These aliases are local provenance, not authorization to reassign uploaded or
purchased media. Server reconciliation must explicitly validate aliases and
revision lineage before creating upload destinations. No API or cloud changes
are included in this decision.

Owner refinement: Combine now operates on multiple selected rows in Edit Items.
Join titles in displayed programme order with spaces and keep the first selected
position. Apply on Save Changes, allowing Cancel to discard the draft. Retain the
first existing selected identity (or first new row when all are unsaved); retire
other existing selections as aliases and flatten prior aliases. New empty rows
have no filesystem identity to archive. Move all retiring sources in one batch
before applying one layout transition. Existing recovery limits still apply.
