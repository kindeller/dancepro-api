# ADR-0022: Event-role uploader identity

Status: accepted by owner, 2026-09-22. Implemented in the local foundation; production rollout pending.

## Decision

Separate the event media role, the authenticated actor and the roster's staffing
assignment. Media contribution identity is event + role, with item/section detail
and existing programme reconciliation beneath it, rather than event + role + crew.
Record who actually uploads and confirms completion without claiming that person
captured every file. Crew Management remains the staffing record.

Concerts default to the signed-in person's assigned event role when unambiguous.
Allow explicit selection of another event role: P1 may upload P2's photos as a favour
or for logistics, using P1's own account. Do not require selecting a roster name or
using a colleague's credentials. Ambiguous/no matching defaults require role choice.

Competition laptops are event equipment stations, configured as P1, P2 or V and
persisted for that prepared event. They stay with the equipment through crew changes;
no sign-out/sign-in or role change is required merely because someone else takes over.
The login provides access and an actor audit trail, not ownership of the station.

Progress and Upload Complete belong to event roles. A personnel change must not reset
completion; later media does reopen renewed work. One role's completion does not
complete another role, waive missing work, publish concert galleries or override HOLD.

Role changes are explicit. Uploaded files retain their original role. A replacement
laptop selecting the same role continues existing work, with cross-laptop duplicate
protection for copied originals. Preserve access controls while supporting delegated
concert uploads and competition stations; role choice alone is not access permission.

## Implementation implications

The existing local database and API bind contributions to crew_profile_id and role,
and validate named published assignments. Completion currently changes when assignment
UUIDs change. These are known implementation gaps, not the newly agreed behaviour.

Next implementation must migrate that identity without losing verified files,
completion history or attribution; record the actual per-file upload actor and
completion actor; adapt role selection, API checks and progress to event roles; and
provide safe replacement-laptop deduplication. Preserve existing local receipts and
prepared events through an explicit compatibility path. V station selection does
not imply video transfer has already been implemented.

This supersedes crew-bound identity assumptions in the contribution/completion
foundation, particularly ADR-0018 and ADR-0021. Existing byte verification, manual
initial upload, Ready checks and private storage/release separation still apply.

## Implementation record

Canonical contributions use plan/item/role. Original contribution rows are archived
as JSON before crew-split rows consolidate; each file retains its original contribution
UUID, file UUID and storage path. Legacy unknown upload actors are not guessed.
Actual receipts and completion declarations record the authenticated actor. Automatic
down is intentionally refused because new role work cannot be assigned back to a
crew identity without inventing history; use a coordinated backup restore.

Replacement-file deduplication is scoped to the same plan/item/role and exact SHA-256
and size. Incoming bytes are verified, then identical stored bytes are reused; a
distinct receipt links to the original and retains the second actor. No stored photo
is deleted. Cross-role/item content is not silently reassigned or collapsed.

The named-person roster endpoint remains available, while the new upload-role endpoint
provides an optional concert default and persisted role choices. Existing local person
selections map to their role on refresh. Queued photos block explicit role changes;
operators must upload or clear them first, then Ready the new role's work.

Ordinary crew accounts have event-scoped uploader access through published event
assignments, independent of which event role they upload for. Event access is still
revoked if all their published event assignments are removed; a normal shift handover
does not remove historical published assignments. Programme approval stays admin-only.
