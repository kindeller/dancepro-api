# ADR-0025: Competition capture folders and role comparisons

Status: implemented, 22 September 2026.

Owner confirmed the capture hierarchy: EDC926 / 01 Friday /
07 Jazz 12 & Under / 03 Jessica Smith. New Mac preparations use the event code as
the root, programme days in date order with two-digit day indices and weekday names,
then two-digit section and competitor numbers plus titles. Safe punctuation in
competition capture titles is preserved, including ampersands; path separators,
colons and control characters are removed/replaced. Concert title cleaning stays
unchanged. Existing date / Section N preparations remain readable.

The Mac scans this event root without creating, moving, renaming or deleting capture
folders. Approved days are matched by their dated weekday/index (or legacy date).
Sections use their event-wide printed number; competitors use their section and
printed number. Leading zeroes and letter suffix case do not create another identity.
The supplied hierarchy is supported; arbitrary capture layouts are not guessed.
Unknown days/labels, symlinks and duplicate competitor folders are flagged. Ambiguous
competitor paths are excluded from transfer; ordinary recognised folders continue.
Upload Complete waits while these local discovery issues remain. Server name or
missing-role warnings alone do not block uploads or completion declarations.

Approved identities and titles remain immutable. Additional sections and competitors
use deterministic UUIDv5 identities namespaced by programme UUID and normalized
section/competitor numbers. Existing approved IDs always win. Native and server
implementations share a fixed test vector. The server validates the incoming
structure and identities rather than trusting the client's UUIDs.

An append-only competition_programme_additions registry extends the effective
nodes of the existing immutable upload plan. This preserves gallery references,
file paths, contribution identities, completion and waivers. An older station's
sync cannot remove additions. New items enter every applicable role's expected
scope; previous completion stays attached only to its original items. Registration,
progress, completion and administrator exceptions use the effective node set.

Per-device/per-role folder inventories hold observed titles, actor and last-report
time. P1/P2/V compare against the common identities and titles, distinguishing
matched, name_difference, not_seen and not_checked. Role coverage follows configured
shift dates. A role aggregates its reporting devices, so one replacement laptop
cannot erase another laptop's report. Reports represent last-seen state, not proof
of file transfer, and are not automatically expired. Video inventories work without
JPEGs; video transfer remains a separate unfinished feature.

The uploader scans locally every two seconds and reports/checks online every thirty
seconds while the event is open, signed in, idle and a role is selected. Without a
role it only reads comparisons. Metadata sync may run automatically; photo upload
still requires explicit Upload. The Programme panel shows folder differences and
local discovery issues. Offline errors retain local review/culling and show that
checks are needed. No folders are downloaded or renamed on other stations.

Accepted addition/name/removal report batches create one automatic event-chat
message using the existing crew notification service. Identical retries do not
repeat the message. Rejected batches roll back additions, reports and messages.
Browser chat uses the existing five-second programme update polling. Read-only
comparison calls never create an upload plan or send a message.

If a capture app moves an already uploaded photo to a different competitor number,
the uploader flags it, retains the original recorded ownership and leaves other
competitors uploadable. Administrator reconciliation is still needed for changing
uploaded identities or canonical titles; this feature does not silently reassign
server media.

Migration rollback is permitted only while the additions registry is empty.
Once identities exist, rollback refuses to orphan uploaded media or contribution
records; use a coordinated data-preserving restore instead. Migration itself adds
tables only and does not rewrite owner event/media data.


## Owner correction: one-day events and video matching

One-day events omit the visible day folder; multi-day events retain it. This changes
presentation only: logical programme days, dates, section/item IDs and server role
scope stay unchanged. Existing one-day day-wrapper preparations remain readable
and are not automatically flattened. A manifest distinguishes an old day wrapper
from a flat section whose title happens to be a weekday. Multi-day flat sections
are flagged rather than assigned to an assumed day.

The online gallery must follow the same optional-day hierarchy when implemented.
Video basenames use event code_section_competitor, e.g. EDC926_07_03, never the day.
Native local checks map supported video filenames to unique section/item identities,
including local added folders; unknown or ambiguous identities and containing-folder
conflicts are flagged. Checks do not read video bytes or imply verified transfer.
Video-only unknown additions require reconciliation; filename parsing does not invent
competitor titles or automatically modify the programme.
