# Mac uploader: concert editing, offline queue and app icon

## Post-upload Top Pics sync (26 September 2026)

Implemented a durable metadata queue for uploaded photo stars and removals, with
automatic background sync while signed in, including on the event list. Changes
survive offline operation and app restart. The two-second worker sends only pending
metadata; failed requests back off from five seconds to at most one minute. The
review footer shows pending/syncing/synced status, errors and optional Retry sync.

Upload acknowledgement always queues a reconciliation, covering stars changed
during transfer and reused verified reservations. Revision-matched acknowledgements
cannot discard newer local edits. Older review sidecars reconcile once on opening.
One cached library actor per event coordinates review and background writes.

The protected, event-scoped endpoint accepts at most 100 verified-file changes,
updates canonical records for deduplicated receipts and audits actual changes.
It does not copy images, change customer visibility or reopen completed uploads.
Publicity uses these flags on its next page load; TV playback remains separate work.

Next: connect TV pairing/playlists and cached playback to this shared catalogue.

Verification: all **744 Laravel tests / 6,834 assertions** and **89 Swift tests**
passed. Coverage includes true offline retry, restart/legacy-sidecar recovery,
stale acknowledgements, edits during upload, removal, response validation,
cross-event and account permissions, batch rollback, duplicate receipts and
unchanged original bytes/completion. Scoped Pint, route registration and whitespace
checks passed. The local Mac app was rebuilt; running copies must be reopened.
No migrations, production deployment, TV playback or commits were included.

Date: 2026-09-21

## Implemented for one owner review

- Package the supplied Uploader.png as a complete macOS ICNS icon and declare it
  in Info.plist; show the application icon in the event-selection header.
- Edit Items for concerts: change titles, add items, drag rows or use up/down
  controls to reorder, and save/cancel the draft. Existing items cannot be removed.
  Folder numbers remain 01, 02, …; title punctuation is preserved and removed only
  from folder names. Competition structure has no editor.
- Persist a separate working concert programme and recoverable folder-change
  journal. Move whole item directories without overwriting; preserve item IDs,
  photo IDs, Top Pics and Trash Undo. Block edits for uploaded media or known
  incomplete capture files. Original approved snapshot/PDF remain unchanged.
- Ready beside competition sections and concert items queues the current stable
  photos. Show Queued/New photos/Shooting statuses and a persistent queue count.
  The queue sheet lists filenames/folders and supports Remove from Queue/Clear.
  New/changed/reassigned photos require another Ready action. Queue works offline.
- Upload is visibly unavailable; no fabricated upload progress or automatic
  reconnect behavior. This is only the local portion of Stage 4.4.

## Verification

28 native tests cover existing behavior plus edit identity/Top Pics preservation,
Trash Undo after renumbering, collision rejection, capture and uploaded locks,
interrupted-rename recovery and wrong-folder rejection, immutable approved data,
queue restart/quiet-interval persistence, later arrivals and invalidation on change
or movement. All 28 native tests and 477 Sail tests (3,969 assertions) pass.
Read-only Pint and git diff checks pass. Release 0.2.0 builds successfully; its
ICNS resource is declared in Info.plist, includes the 1024-pixel image, and the
app passes strict code-signature verification.

The actual drag gestures and editor layout are for owner review in the packaged
app; do not claim they were manually exercised on the owner's event. Only
synthetic fixture media was modified in tests.

## Review steps

1. Quit/reopen DancePro Uploader and check the new icon.
2. Open a prepared concert, pause capture/copying, and choose Edit Items. Rename,
   add and reorder, then Save Changes. Check folders and Top Pics.
3. Use Move Photos to populate a new item if needed. Done returns to review.
4. Click Ready, inspect the bottom queue count, and reopen to check persistence.
   Adding another JPEG must leave it needing Ready rather than silently queued.

## Remaining and next action

Stage 4.3 now includes selected-photo splitting (see the latest section below).
Local Combine Items is now implemented (see below); server reconciliation remains.
Combining preserves retired source identities as aliases of the surviving item.

Next: define the event/item/contribution bridge to existing server media upload
services, then implement manual-start, pause/resume, retries and checksum/server
verification. Crew role/contribution completion and actual release gates must be
connected before claiming uploads or event completion work. No upload API or
production configuration was changed in this batch.


## Drag and icon clarification

Move Photos keeps the Move to menu pinned above the grid. Dragging a selected
thumbnail takes the entire selection; dragging an unselected thumbnail takes
that one photo. The payload is captured when the drag starts and the drag preview
shows its photo count. Existing sidebar destination highlighting and same-event
validation remain in place.

The app now explicitly applies the bundled ICNS to NSApplication on launch,
in addition to the Finder bundle icon declaration. Packaging bumps to 0.2.1 and
updates the bundle modification date so macOS can refresh its cached icon.
Quit/reopen the packaged app to use the new Dock icon and drag preview.

Verification for 0.2.1: 28 native tests and 477 API tests pass; Pint, whitespace
checks, release packaging and strict bundle signature verification pass.


## Matching move notification and Undo

Move and delete use the same floating material notice, spacing, type and Undo
button. Move uses a cyan arrow icon; delete keeps its red trash icon. The grid
uses the shared notice instead of a plain text footer. Move Undo restores the
whole batch to its original item folders, retaining Top Pics and identities.
History persists in the review sidecar and Undo Move remains in the bottom bar
after the five-second notice disappears. Existing Undo Trash stays separate.

Undo preflights all destinations and rejects changed files, uploaded photos or
filename collisions without overwriting or partially restoring the batch. It
resolves original folders by item ID so concert renumbering is supported. Move
and Undo clear Ready for the affected photos; neither silently queues them.
Batch recovery retains the existing move rollback behavior and limitations.

Regression coverage includes reopening with a multi-source move, conflict
preflight that preserves all moved files and history, and notice Undo routing.

Verification: 31 native tests and 477 API tests passed. Read-only Pint, whitespace
checks and the packaged release build passed.


## Native drag correction and Top Pics exclusion

Owner reported the SwiftUI thumbnail drag did not work. Replace the thumbnail
Button/onDrag and String dropDestination combination with native AppKit mouse
tracking and NSDraggingSource/destination views, using one private pasteboard type
at both ends. A four-point movement starts a move session; mouse-up without a drag
selects the thumbnail. A valid destination highlights and reports the move cursor.
Reject same-folder, foreign-session, uploaded and Top Pics drops before accepting.
Keep the Move to menu at the top.

Hide Top Pics in the moving sidebar, hide the Move Photos entry point while
viewing Top Pics, and omit Top Pics star badges from move thumbnails. Existing
photo selections remain unchanged by these presentation changes.

Verified in the rebuilt native app through computer use with two disposable JPEGs:
Select All, drag a thumbnail to the second folder, observe source 2→0 and target
0→2 plus the move notice, then click Undo and observe both photos restored. The
Top Pics row was absent. Returned the app to the owner's prepared event afterward;
no owner photos were moved. All 31 native tests and 477 API tests pass, along with
read-only Pint and the release build.


The Move Photos toolbar button now uses arrow.right.square.fill, matching the
move notification icon. No movement or selection behavior changed.


Move thumbnail selection indicators now use one fixed 18-point circle in both
states, with a 10-point checkmark inside the selected circle. This replaces the
empty padded container that grew when the selected symbol appeared. Uploaded
locks use the same fixed badge dimensions.


The Move Photos Done button now uses the prominent blue style to make returning
to review easier to find.


## Concert photo splitting

Move Photos now offers Split into New Item for concerts. The title sheet names
the selection/source, asks capture/copying to be paused, and provides Create and
Move. Insert the new UUID-backed item immediately after the source, preserve its
parent grouping, normalize folder numbering, and move only the selected photos.
The view opens the new item and uses the existing move notice and batch Undo.
Undo returns the photos; it deliberately retains the newly created empty item.

Preflight rejects empty/duplicate/mixed-item selections, uploaded media, invalid
titles and unfinished capture before changing structure. Reuse the recoverable
layout operation and atomic batch move with their existing protections. Preserve
stable scan observations across directory renames, then recheck signatures so
production quiet intervals do not prevent moving unchanged photos immediately.
A failure after the layout commits reports that the new item exists, updates the
UI's working manifest and instructs retrying Move Photos into that item. A process
exit between the two operations can likewise leave a new empty item; it never
requires inventing a new identity for an existing photo.

Tests cover the normal two-second quiet interval, partial selection, following-item
insertion and punctuation, continuous numbering, persistent UUID/Top Pics, Undo,
invalid preflight with no new folders, and move-save failure with no lost photos.

Next: explicit concert Combine Items and server reconciliation of local edits,
followed by the event/item/contribution bridge for real uploads. Split is local
photo organization; it does not split video files or change purchased media.

Verification for splitting: all 35 native tests and 477 API tests passed; Pint,
whitespace checks and the release app build passed. The new split sheet has not
yet had the owner’s live interaction review.


## Combine Items

In a concert's Move Photos screen, Combine Item opens a destination/title sheet.
It explicitly combines the whole current item, including unselected photos. The
chosen destination survives with a combined title; the source disappears from
active lists and numbering closes the gap. Photos retain UUIDs and Top Pics.
The source programme ID remains as a direct alias, and the empty directory is
archived locally. Original programme/PDF remain unchanged.

Reject duplicate filenames, changing capture, uploaded media and non-JPEG source
contents. Roll back photo movement if layout preflight fails; if folder journaling
has begun, recovery completes on reopen. Old Trash actions resolve through the
alias into the surviving item. Move Undo entries involving the retired source
are removed; the sheet clearly states Combine has no one-click undo. Split can
reorganize photos afterward. The existing batch/layout crash limits remain as
documented in ADR-0016.

Tests cover identity/Top Pics and original bytes across reopen, Trash recovery,
filename conflicts, unsupported source content, layout-conflict rollback,
repeated combinations, invalid aliases and interrupted folder-journal recovery.
Only synthetic test photos were changed. The new combine sheet is for owner
interaction review in the packaged app.

Next: server reconciliation of local programme changes and the event/item/crew
contribution bridge to real uploads. Upload remains unavailable until those
records, manual-start transfer, retries and verification are connected.

Verification for Combine Items: all 41 native tests and 477 API tests passed;
Pint, whitespace checks, release packaging and strict signature verification
passed. Trash recovery was also tested before the first scan after reopening.

## Working programme sync

Implemented a manual Sync Programme action in photo review with a persisted
receipt and local-edit invalidation. Registers immutable server upload plans,
including split identities and direct combine aliases, against the latest
approved revision. Repeated identical submissions reuse a plan. Approved
snapshots/PDFs/drafts remain untouched. Wrong-server sessions are rejected before
networking; stale revisions, invalid hierarchies/aliases/folders, identity removal
and unauthorized callers are rejected server-side. Competition nodes must match
the approved structure. Administrator-only scope remains unchanged.

This is registration, not conflict reconciliation across photographers or actual
photo upload. Next: bind plan/item records to event galleries and assigned crew
contributions, then manual-start JPEG transfer, retries and verification. No AWS
storage or credentials were changed. ADR-0017 records the scope and safeguards.

Verification: all 45 native tests and 483 API tests (3,996 assertions) passed.
Pint, complete route registration, whitespace checks, release packaging and strict
app signature verification passed. The local Sail migration completed, including
previously pending repository migrations. The new migration's down method only
drops its own upload-plan table; existing programme revisions are preserved.
The new button is packaged for owner review; a live signed-in desktop-to-server
sync has not been exercised with the owner's event or credentials. API tests use
isolated records, and native receipt tests use temporary files.

## Combine moved into Edit Items

Owner refinement supersedes the earlier Move Photos combine flow. Edit Items now
has Combine Item, opening a multi-selection list with whole-row selection and a
joined-title preview. Titles join with one space in current programme order,
regardless of click order, preserving punctuation. Keep the earliest selected
position and renumber active rows. Combine changes the editor draft; Cancel
leaves disk untouched, and Save Changes applies all folder/photo changes.

Multiple retirements move as one preflighted batch, so duplicate filenames across
any source reject the whole batch before movement. Reuse layout journaling,
rollback, stable photo identities and Top Pics handling. New unsaved rows can be
combined too: retain an existing selected folder identity when available, placed
at the earliest selected position; unsaved empty rows need no retired identity.
Saved combinations still have no one-click undo. Review follows the surviving
item if the currently selected item was retired.

Next remains event/item/contribution mapping and actual verified photo transfer.

Verification: all 48 Mac tests and 483 API tests passed, including multi-source
combining, selection-order-independent titles, mixed saved/new rows, numbering,
Top Pics/identity preservation and rejection of cross-source filename collisions.
Release packaging, strict signature verification and whitespace checks passed.
The new editor/sheet is packaged for owner interaction review.

## Photographer selection from Crew Management

Photo review now offers Choose photographer beneath the event name. Fetch the
published event roster on opening its sheet, with explicit Refresh and a last
checked time. Group the same crew member/role across shifts, show dates and
unfilled required photo roles, and persist an explicit selection plus cached roster
in upload-photographer.json for offline use. Read-only roster fetching does not
block photo edits. Preparation remains independent of choosing a role.

Use existing competition/concert P1/P2 and dress-rehearsal P codes. Exclude draft
assignments, inactive users/roles and deleted crew profiles. Shift-specific role
quantities override event defaults. No-shift/no-assignment states explain what to
update in Crew Management. Names and public identity/date fields are exposed, not
private crew profile fields. API access remains administrator-only with existing
programmes:read, event-scoped checks and no-store responses. No crew assignment,
notification, credential or AWS changes are made.

Cache selection by server/event and crew/role public identity. A successful roster
refresh clears a removed/reassigned selection; a network failure retains the last
checked snapshot. This is remembered source identification, not upload authority,
contribution completion or gallery release. Revalidate assignments server-side
when implementing real uploads. No automatic initial transfer is introduced.

Next: persist server event/item/contribution mappings, reconcile conflicting
concert layouts across laptops, then connect manual JPEG transfer and verification.
The photographer list itself is not a completed contribution tracking system.

Verification: all 51 Mac tests and 488 API tests (4,018 assertions) passed.
Coverage includes published assignment grouping, missing quantities, event
isolation, access control, nullable concert shifts, persisted offline selection,
reassignment clearing, cross-server rejection and symlink protection. Pint,
route registration and whitespace checks passed. The new sheet is for owner
interaction review; no live crew records or assignments were changed in testing.
Release app packaging and strict signature verification also passed.

## Server photo contribution records

Explicit Sync Programme now scans the queue and registers each distinct queued
item for the selected photographer after programme sync. The server stores a
public contribution identity tied to the immutable upload plan, active item,
crew profile and role, with original qualifying assignment references. Repeated
requests reuse identities; P1/P2 are separate. Validate the whole batch before
writing and reject stale/foreign plans, invalid/retired items and lost assignments.
Competition assignment dates must match the item's programme day.

Native receipts retain validated contribution links. Registration failure leaves
the successful programme receipt intact and reports a retryable error. Old receipts
still load. No photographer selection is required just to prepare or sync a
programme. This is per-item source registration; individual files are not yet
reserved, received, verified or marked complete. No gallery is published.

Next: per-file reservations, transfer and content verification, with event/gallery
mapping and conflict resolution across different local plans before enabling
Upload. Expected contribution coverage and release remain separate work; do not
infer completion from the presence of registration records. ADR-0018 records the
boundary. No AWS operations or credential changes were performed.

Verification: all 54 Mac tests and 495 API tests (4,047 assertions) passed.
Checks cover idempotency, separate P1/P2 identities, whole-batch rejection,
foreign/stale plans, lost assignments, competition dates, retired items, access
control, receipt round trips and response identity checks. The targeted local
migration, full route registration, Pint, whitespace checks, release packaging
and strict app signature verification passed. Migration down drops only the new
contribution table; existing plans and programme revisions are preserved.
No live owner queue was registered during testing; native integration is packaged
for review and API tests use isolated database fixtures.

## Per-photo fingerprints and reservations

Sync Programme now streams explicitly queued, stable JPEGs through SHA-256 before
registering their expected byte count and checksum with the server. Fingerprinting
runs on the photo-library actor in bounded chunks, checks cancellation, descriptor
identity and before/after metadata, and leaves upload/culling state untouched.
Reservation batches reuse the contribution authorization checks and transaction
lock. Stable photo ID plus checksum plus contribution identify a version; retries
reuse the reservation. Conflicting size claims roll back the complete batch, and
Top Pic metadata can change without creating a duplicate file. Paths/non-JPEG names,
invalid hashes, oversized files and duplicate photo IDs are rejected.

Native response validation binds every reservation to the exact requested photo,
item, contribution and byte identity. Persist links in the existing receipt, with
backward-compatible optional fields. No files are transferred, verified, published
or marked uploaded. No owner photos were altered for tests. ADR-0019 records the
expected-byte versus verified-receipt boundary and remaining cross-plan decisions.

Owner direction: retain Sync Programme for now. Remove the separate button when
Upload automatically performs sync and contribution/file preparation. No additional
setup button was introduced. Next: resolve event/gallery and active-plan mapping,
then private transfer, actual byte/JPEG verification and manual-start Upload.

Verification: all 56 Mac tests and 498 API tests (4,071 assertions) passed.
Coverage includes exact SHA-256/size calculation, queued-only processing, changed
file invalidation, preserved Top Pics/review state, response identity checks,
idempotent reservations, changed-byte versions, full-batch rollback, unsafe names,
invalid hashes, duplicate IDs and live assignment rejection. The targeted local
migration, complete route check, Pint, whitespace checks, release packaging and
strict app signature verification passed. Migration down only drops the new file
reservation table and preserves contribution/programme records. No live owner
files were reserved during testing; transfer remains intentionally unavailable.

## Private gallery, verified transfer and the Upload workflow

Implemented the next connected batch:

- A private scheduling-event gallery binds to the first successfully uploaded
  working programme. Matching P1/P2 plans share it. Conflicting layouts and changed
  previously uploaded photo identities are blocked without overwriting originals.
- Authenticated raw JPEG PUT validates live event/revision/roster scope before and
  after receipt, verifies size/SHA-256/JPEG decoding, saves private local storage,
  verifies stored bytes, and returns a scoped verified receipt. Contributions become
  receiving, never automatically complete. No customer gallery is published.
- Upload now performs programme sync, photographer validation, fingerprinting and
  reservation automatically. The separate Sync Programme button is removed as
  requested. Pause/Resume, active-session transient retries, additional Ready work,
  private disk snapshots and persisted per-photo acknowledgements are connected.
- Uploaded folders show Uploaded and reject culling/movement. New arrivals remain
  unreviewed until Ready. Reopening never starts transfer automatically and preserves
  verified state. Top Pics remain editable locally.

This batch uses private local server storage and is default-enabled only for
local/testing. It does not deploy cloud storage or production transfer. The active
plan conflict is safely detected/rejected; an administrator reconciliation UI is
still required for incompatible layouts. Later Top Pic edits also still need server
synchronization. ADR-0020 records these boundaries and failure recovery.

Next: owner review of the local upload flow, then role-level Finished Uploading Event,
expected-contribution tracking and admin exceptions, plus production storage and
customer gallery integration. Never publish competition sections until all required
photographer contributions are explicitly complete and verified.

Verification: all 62 Mac tests and 505 API tests (4,118 assertions) passed.
Coverage includes automatic preparation, Pause/Resume, transient retry, additional
Ready work during transfer, rejected receipts, persisted acknowledgements and
uploaded-item locks, exact JPEG verification, idempotent receipt replay, conflicting
plans, assignment changes during receipt and storage failure rollback. The targeted
local migration, complete route listing, scoped Pint check, whitespace check,
release packaging and strict app signature verification passed. Migration down was
reviewed: it removes metadata but does not delete stored photo files. Tests used
synthetic JPEGs and mocked native HTTP responses; the combined live desktop/server
flow remains for owner review. No owner photos were uploaded or changed for testing.

## Explicit Finished Uploading Event

Added a single confirmation for the selected photographer and role. The Mac scans
for remaining local work, automatically syncs, validates the response and saves the
completion receipt. Reopening displays the recorded declaration; new local photos
require Ready/Upload/Finish again. Server completion derives all eligible items,
including empty items, checks live assignments and all reserved files, and records
completion time and declaring administrator. New reservations reopen the affected
contribution. Verified retries preserve completion. Other photographers and private
gallery status are untouched. ADR-0021 records the cross-Mac and rollback boundaries.

Next: current server contribution progress, expected/missing photographers and
administrator exception handling. Production storage, customer galleries and plan
reconciliation remain pending. Local completion receipts are not release evidence.

Owner clarification: Finish must also catch Ready folders whose photos never
entered the queue or were removed from it. The preflight scans all local photos
and rejects every unuploaded photo, regardless of queue membership, as well as
unstable/incomplete JPEGs. An empty queue alone is never sufficient.

Verification: 66 Mac tests and 509 API tests (4,155 assertions) passed. Coverage
includes completion persistence/reopening, Ready-but-unqueued photos, incomplete
JPEGs, mismatched completion receipts, verified/empty items, idempotent completion,
later upload reopening, independent P1/P2 state, stale roster/revision and ability
checks. Scoped Pint, full route listing, local migration, whitespace check, release
packaging and strict signature verification passed. No owner photo or completion
records were changed for testing. The new completion flow awaits owner UI review.

## Review and upload control grouping

Owner direction: move Undo Move and Undo Trash beside Move Photos, and move Finish
Uploading into the bottom uploader bar. Implemented a separate photo-action row
below the folder title/navigation, keeping both undo controls in stable positions
and disabled when unavailable. Shared undo controls also remain accessible inside
Move Photos. The bottom bar groups queue, Upload/Pause/Resume and Finish, with
upload status beneath the local-save label. Existing actions and shortcuts remain.

Verification: 66 Mac tests and 509 API tests (4,155 assertions) passed; release
packaging, strict signature and whitespace checks passed. This layout awaits owner
visual review. No API routes, database schema or upload behaviour changed.

Owner label refinement: the bottom completion button now reads **Upload Complete**
both before and after recording completion. Its validation and confirmation are
unchanged. This replaces the previous Finish Uploading / Finish recorded labels.
Verification: release build, strict signature, whitespace check and all 509 API
tests (4,155 assertions) passed. No behaviour or schema changed.

## Photo preview navigation refinement

Replaced the duplicate photo total beside the folder heading with the current
position (e.g. 1 of 9) and filename. Moved labelled Previous Photo / Next Photo
buttons above matching Previous Item / Next Item controls; existing arrow and
Command-arrow shortcuts remain. Fit now uses a viewfinder icon over the image,
beside Expand, with a tooltip and accessibility label. Removed the old footer and
Pinch to zoom hint; the zoom gesture itself remains available. Filename truncation
preserves a tooltip for its full value. Owner visual review remains pending.
Verification: release packaging, strict signature, whitespace checks and all 509
API tests (4,155 assertions) passed. This is a view-only change; no photo files or
upload behaviour were changed.

## Current server upload progress

Added a read-only server progress endpoint and a Server / All photographers tab
inside the existing queue window. This Mac retains queue inspection/removal;
Server shows published photographer roles, verified/pending files, expected versus
explicitly completed items, and unfilled shift roles. Refresh is manual after the
initial tab load, with a checked timestamp. Offline/request errors discard the old
server display rather than presenting old completion as current. Opening progress
does not sync or upload; a matching existing programme receipt is required.

Completion scope is shared between finishing and progress. A new roster assignment
identity invalidates old completion; reconfirmation updates the recorded assignment
identities. New programme revisions or conflicting gallery plans show attention,
and one photographer's completion does not complete P2 or conceal unfilled roles.
Server counts aggregate file metadata without loading photo bytes. No database
migration or publication was added. Next: audited administrator exceptions and
missing-contribution management; production storage/customer galleries remain pending.

Verification: 68 Mac tests and 513 API tests (4,198 assertions) passed, including
read-only status, independent P1/P2 completion, later reservations, missing roles,
changed assignments/programmes, date scope, access controls and invalid response
rejection. Scoped Pint, complete route listing, whitespace checks, release packaging
and strict signature verification passed. A read-only check of EK's actual local
gallery returned 84 verified files, zero pending files and 32/32 items complete;
no records or owner files were changed. Native UI awaits owner visual review.

## Locked owner decision: event roles, accounts and roster personnel

Owner approved ADR-0022. Concerts automatically select the signed-in person's event
role when clear, with explicit role selection to upload for another crew member.
Competition laptops are persistent P1/P2/V equipment stations independent of crew
handovers. Separate the media role, actual upload/completion actor and roster staff.
Progress/completion follows event + role; personnel changes alone do not reopen it.
Explicit role changes preserve prior media attribution, and replacement laptops
continue the role with duplicate protection.

This session records the decision only. No code, database, prepared events, owner
photos or accounts changed. Next: implement event-role identity and migration of the
existing crew-bound contributions before more exception/release work. Product plan,
roadmap and ADR-0021 supersession note updated; documentation whitespace check passed.

## Event-role implementation

Implemented the agreed identity change: canonical plan/item/role contributions,
independent roster staffing and actual upload/completion actors. Concerts obtain a
single matching default from the signed-in account; explicit alternate role choice
supports uploading for a colleague. Competition station selection persists in the
prepared event across handovers and reopening. Existing saved person selections map
to the same role on refresh. Queued photos block role changes until uploaded or
cleared; previous uploaded media is never reassigned. V is selectable but photo
transfer/completion reject it; video transport remains future work.

Crew login now grants the two uploader abilities with event-scoped authorisation;
crew can act for another configured role at their event, but cannot approve the
programme or access unrelated events. Roster identity changes alone preserve role
completion. New files still reopen work. Server progress groups roles and shows
verified upload actors; historical unknown actors are labelled explicitly.

Migration archives original contribution metadata, consolidates crew-split records,
and preserves every file UUID/path and original contribution reference. Replacement
laptop copies verify byte identity then reuse storage for matching item/role photos;
receipt actors remain distinct and progress does not double count those copies.
A coordinated database restore is required to undo this identity migration safely.

Next: administrator plan reconciliation, then production
storage/customer galleries and video transport. Do not infer publication readiness
from one role or the local completion indicator.

Verification: 71 Mac tests and 518 API tests (4,254 assertions) passed. Coverage
includes crew event access and denied unrelated/admin actions, P1 uploading P2,
automatic concert defaults, persistent competition roles, queue-safe role switching,
V photo rejection, handovers preserving completion, actor attribution, exact-content
replacement deduplication and historical duplicate receipt retries. An isolated
populated legacy-schema migration test preserves colliding client identities, file
paths, completion and original contribution history. Scoped Pint, route listing,
whitespace checks, release packaging and strict signature verification passed.

Applied the local migration after saving a private metadata snapshot at
storage/app/private/event-role-metadata-backup-20260922-125609.json. Re-read and
checksum-verified all 84 existing files: every original path and file UUID remains,
and all 32 completed role items remain complete. No stored photos were deleted.
No production service or AWS resource was changed. Native UI awaits owner review;
existing crew login sessions may need renewed sign-in to obtain uploader abilities.


## Administrator photo exceptions — 22 September 2026

Event → Photo uploads now shows server counts, item contributions and exception
history. Administrators can waive or reinstate a photo role's whole event,
day/section/group or individual item, with a required reason. Completed work and
other roles are preserved. Crew cannot grant exceptions. New reservations or the
first arrival of an earlier reservation reopen the affected waiver with an audit.
Verified receipt retries leave waivers unchanged. Mac server progress separates
waived counts from completed counts and labels fully waived/mixed roles explicitly.
Upload Complete skips waived requirements without converting them into completion.

Verification: 523 API tests (4,308 assertions) and 72 Mac tests passed. Coverage
includes authorization, scope isolation, repeat requests, stale/foreign plans,
reinstatement, receipt retries, automatic reopening and native status validation.
Scoped Pint, full route listing, whitespace checks, release build and strict app
signature verification passed. Migration 000004 was applied locally; its rollback
was reviewed to ensure waived work returns to receiving, never complete. No stored
photo was deleted or published. Portal and native visual review remain with owner.

See ADR-0023. Next: programme plan reconciliation, then production storage/customer
release and video transport, subject to the existing product decisions.


## Shared concert programme and notifications — 22 September 2026

The existing Programme button now has a concert-only Confirmed / Update available
tag. The first sync establishes the shared version. Programme offers explicit
confirmation before Upload, online checks and safe loading into local folders.
Further pre-upload changes require the currently shared plan as their base;
concurrent stale submissions fail rather than create a divergent upload programme.
Once contributions exist, later structural changes still require administrator
reconciliation. The app polls every 30 seconds and does not report a fresh Confirmed
state after a failed check.

Loading shared renames and combinations preserves photo identities and Top Pics,
using the existing journalled folder operations. Additional conflicting local items
are retained and flagged; automatic mapping of independent additions is not yet
available. No owner photo folders were used for automated tests.

Each accepted concert batch posts one automatic event-chat message with its actor
and change summary and creates existing crew notifications. Identical retries do not
repeat messages. Visible web event chats fetch new programme messages every five
seconds without replacing the composer. Mobile chat uses its existing message feed;
this change does not add push delivery or competition folder comparison.

Verification: 528 API tests / 4,343 assertions and 75 Mac tests passed. Coverage
includes stale competing edits, first confirmation, shared reads and event permissions,
notification deduplication and recipients, inaccessible chat, old-plan registration,
photo-preserving rename/combine application, UUID case compatibility and refusing to
discard additional local items. Scoped Pint, route listing, whitespace checks, release
packaging and strict codesign verification passed. Migration 000005 was applied locally;
rollback drops only the nullable pointer and retains programmes, plans and photos.
Visual owner review is still pending. No actual crew message was sent by verification.

Next: competition capture-folder discovery and additions, P1/P2/V differences and
notifications. See ADR-0024 and the owner clarification in the product plan.


## Competition capture folders and P1/P2/V checks — 22 September 2026

Owner confirmed EDC926 / 01 Friday / 07 Jazz 12 & Under / 03 Jessica Smith.
New competition preparation follows that hierarchy, retaining safe capture-title
punctuation. Existing date/Section preparations remain readable. Native discovery
finds additional numbered sections and competitors automatically, retains external
paths, matches approved identities by number/day and reports observed names separately.
No capture folder is renamed, moved or deleted by discovery.

Added identities extend an append-only registry attached to the original immutable
upload plan. Existing gallery paths, file UUIDs, completion and waivers remain intact.
Expected role contributions include additions; an old station's sync cannot remove
them. Effective nodes are used for registration, progress, completion and admin
exception scopes. Added photos were tested through real private-storage receipt in
isolated fixtures, including preservation of earlier verified bytes and completion.

Programme shows Folder differences to check across P1/P2/V, distinguishing unchecked
stations, unseen folders and name differences. Device/role reports run online every
30 seconds while idle; Check folders runs immediately. Folder presence does not mean
media uploaded; V transport is still pending. Name/role differences do not block
ordinary uploads. Unmatched/ambiguous or unsafe local folders prevent Upload Complete
until reviewed, while other recognised folders remain uploadable. Uploaded photos
moved externally to another competitor are flagged rather than reassigned silently.

Changed inventory batches notify the existing Crew Hub event chat once. Repeated
reports do not duplicate notifications. Invalid roles/identities and rejected batches
leave no partial additions, inventories or messages. Native photo transfer remains
explicitly started by the operator.

Verification: 531 API tests / 4,385 assertions and 82 Mac tests passed. Coverage
includes stable shared IDs, scoped inventories, P1/P2/V differences, metadata retries,
older stations preserving additions, actual additional photo receipt, completion
scope, capture-format/legacy folder discovery, symlinks, ambiguous folders, retained
photo identities/Top Pics and externally moved uploaded photos. Scoped Pint, route
listing, whitespace checks, release build and strict codesign verification passed.
Migration 000006 was applied locally. Rollback refuses to discard populated addition
identities, preventing orphaned contribution/media records. No owner photos or actual
crew messages were used for testing. Visual owner review remains pending.

Next: administrator reconciliation for already uploaded identities and canonical
title corrections, then production storage/customer delivery and video transfer.
See ADR-0025 for supported hierarchy, identity rules and report freshness semantics.


## One-day layout and video filename matching — 22 September 2026

New one-day preparations now use event / section / competitor; multi-day preparations
retain numbered weekday directories. Programme day identities/dates are unchanged,
so role coverage and shared additions still use the same records. Discovery supports
flat one-day folders and old numbered-day/date wrappers without moving existing
media. Re-preparing a one-day numbered-day preparation keeps its original layout.
The one-day review sidebar omits the unnecessary day heading.

Programme now displays local video filename matches for event_section_competitor,
including EDC926_07_03 for section 07 / competitor 03. It scans supported video
filenames at recognised event/day/section/competitor levels, checks unique identities
and flags wrong events, missing competitors and containing-folder conflicts. The day
is never parsed from the filename. Files are not read or transferred; this is not a
verified video upload or publication feature.

Verification: 87 Mac tests and all 531 API tests (4,385 assertions) passed. Added
coverage includes one-/multi-day layouts, legacy re-preparation preservation, weekday
section-name ambiguity, video mapping across days, wrong-event/competitor filenames,
folder conflicts and unchanged placeholder bytes. No PHP/routes/schema changed.
Release packaging, strict signature and whitespace verification completed. Visual
owner review remains pending; tests used temporary fixtures, not owner media.

The online-gallery hierarchy and corrected _03 filename rule are recorded in the
product plan. Next: implement the online competition gallery using those identities;
production delivery, video byte transfer and administrator reconciliation remain work.

## Private online competition gallery — 22 September 2026

Added Event → Competition gallery in the local management portal. The browser uses
stable programme UUIDs and the same visible hierarchy as capture: event code /
numbered weekday (multi-day only) / numbered section / numbered competitor. Confirmed
programmes can be browsed before uploads. Registered competition additions appear
automatically in numeric order. Both photo roles share one competitor album.

Only verified, stored canonical photo receipts appear; replacement-laptop duplicate
receipts and pending reservations are excluded. Albums paginate at 48 photos with
lazy image loading. An authenticated, event-scoped admin image route serves existing
private local originals, with no public storage URLs or shared caching. This is an
admin preview, not customer delivery: thumbnail/watermark generation, customer
permissions, release gates and video transfer remain pending. The stored gallery's
plan remains authoritative when a later programme exists, with a reconciliation
notice instead of silently reassigning existing photos. No schema or native changes.

Tests cover one-/multi-day browsing, breadcrumbs, additions, numeric order, empty
albums, both roles, duplicates/pending/missing bytes, pagination, escaped titles,
admin-only access, cross-event isolation, pre-upload programmes and stale plans.
The live browser required sign-in, so visual review remains with the owner.
No owner photos, completion declarations or crew messages were changed.

Next: customer-safe preview generation and per-section release readiness, before
customer access. Video transfer, production storage and administrator reconciliation
remain separate outstanding work. Verification: all 537 API tests (4,440 assertions),
scoped Pint, full route listing and whitespace checks passed. Six gallery feature
tests were added. No native rebuild or migration was needed.


## Competition Photos navigation correction

Owner clarified that planning belongs in Event Details; uploads, galleries and orders
belong in Media. Added Media → Competition Photos with a paginated competition list,
opening the existing gallery and its Photo uploads console. Removed the competition
gallery/upload links from Event Details. Gallery and upload back links stay within
Media, with the active menu retained. Existing legacy Competition Media remains
available. Concert navigation will follow this separation when its media integration
is built. No event data, photo data or release behaviour changed.

Verification: 538 API tests (4,450 assertions), scoped Pint, full route listing and
whitespace checks passed. Added coverage for the Media event list, competition-only
filtering, cancellation exclusion, gallery links and denied crew access. Browser
visual review remains pending sign-in. Next work remains previews/release readiness.


## Competition card logos and gallery back button

Competition Photos cards now reuse the event artwork component and the same logo
source as Event Details, including the existing missing-artwork fallback. Logos are
shown in full without cropping. The gallery toolbar now explicitly says
“← Back to Competition Photos” and returns to the event cards from any gallery level.
This is a view-only change; no routes, storage or native app behaviour changed.

Verification: all 538 API tests (4,450 assertions) and whitespace checks passed.
Owner visual review remains pending; no new behavioural tests or migration needed.

## Private competition previews

The Competition Photos grid now requests 480-pixel thumbnails; opening a photo
requests a 1600-pixel preview. Both use the existing competition 2026 watermark,
centred and fitted to the narrowest side, baked into the JPEG. These initial sizes
and quality remain subject to owner photo-quality review before customer release.
The original artwork is bundled unchanged. No owner original/photo record changes.

Generation applies all EXIF orientation variants, drops camera metadata, checks
source integrity/memory limits and caches private derivatives by source/artwork/
render settings. Generation failure does not expose an original. Atomic writes and
per-key locks prevent partial previews. Existing admin/event scoping is retained;
the existing image endpoint no longer serves originals. No routes/schema/native
changes. See ADR-0026 for cache and production boundaries.

Synthetic portrait output was visually checked. Targeted tests passed for resize,
watermark pixels, cache reuse, orientation, metadata removal, private access and
corrupt-source rejection. All 541 API tests (4,495 assertions), scoped Pint and
whitespace checks passed. No route listing, migration or native rebuild was required.

Next: per-section release readiness showing missing/complete/waived photo roles.
Customer access, watermark library/settings, production generation/storage and
video transfer remain pending. Previews alone do not publish a gallery.

## Competition section contribution readiness

Section cards now show a live-on-page-load readiness summary. Opening a section or
competitor shows the section's required photo roles, completed/waived/outstanding
competitor counts and verified/pending photo counts, with a link to the existing
Photo uploads exception console. This is read-only and never publishes media.

Readiness derives from the existing effective programme, role/date scopes and
contribution declarations. All applicable roles must explicitly complete every
active competitor with canonical receipts verified in the correct gallery, or have
an administrator waiver. Waivers remain labelled Ready with exceptions; a waived
contribution may retain old unfulfilled reservations by design. Later receipt or
reservation uses the existing waiver/completion reopening behaviour. Replacement
receipt duplicates are not counted twice. Video roles do not block photo readiness.

Added competitors extend the requirements immediately. Stale programmes show
reconciliation needed; no roles/date coverage or inactive configured photo roles show
setup needed, never ready. Empty sections show No competitors. Empty competitor
folders still count toward the uploader's single role-level completion declaration.
Staffing names/handovers do not replace event-role identity. No schema/routes/native
changes and no real completion, waiver, photo or notification mutations for testing.

Targeted tests cover P1/P2 independence, incomplete verification, waivers/reopening,
new competitors, stale plans, date scopes, inactive required roles and gallery UI.
Verification: all 546 API tests (4,519 assertions), scoped Pint and whitespace
checks passed. No migration, route listing or native build was needed. Owner visual
review remains pending.

Next: gallery settings and customer release/access workflow, including explicit
publication controls that recheck current requirements. This display is not an
authorization or publication gate. Production delivery, watermark management,
administrator reconciliation and video transfer remain pending.

## Owner-requested sample gallery

Populated local private event “AAA826 — Sample photo gallery”
(UUID 7bc0787a-73d5-41e5-802f-47de9b1e8ed3) with two placeholder sections,
two competitor albums per section and six photos per album (24 total), copied
from the owner's supplied AAA826 lyrical-solo sample directory. Album titles carry
“(sample)” and event notes identify it as local review data. Both roles contribute
sample photos; section 1 is marked complete for both and section 2 leaves P2
receiving to demonstrate the readiness display. These declarations are illustrative,
not completion records for the real AAA826 event. No crew assignments/messages or
public release were created. The event date is a placeholder in August 2026.

Verified every copied JPEG and its stored checksum, generated all 48 thumbnail/
preview derivatives and visually inspected a watermarked photo. Rechecked all 24
source checksums unchanged. Removed temporary staging photo copies and the one-off
population script; private import manifest/result remain for provenance. Live Chrome
requires sign-in; the direct sample gallery link is provided to the owner. All 546
API tests (4,519 assertions) and whitespace checks passed. No feature code changed.

### Sample placement corrected

Owner wanted an existing gallery populated and source-folder groupings ignored.
After owner authorised any existing gallery, moved only the newly created sample
programme/gallery records into Express Dance Challenge - DEMO Spring weekend
(event 322dc574-a9ed-4801-95b1-c90d1b6504f5). Confirmed this existing event had no
programme or photo gallery before the transaction. Its planning/crew records were
preserved. The placeholder structure follows its two existing dates, 11/12 September,
with one placeholder section per day and Competitor 1–4 (two per section).

The 24 copied photos were redistributed round-robin independently of source folders,
six per album. Storage bytes and cached derivatives remain unchanged; old physical
keys are deliberately retained while gallery/plan ownership points to the existing
event. The accidental AAA826 sample event and only its empty planning records were
removed after checking it had no crew assignments. No source photos were touched.
The initial request was stopped by automatic approval review; execution occurred
only after the owner's follow-up authorisation. Sample completion remains illustrative.

Verified through the gallery service: two day folders, four six-photo albums,
private visibility, unchanged photo hashes, available thumbnails/previews and absence
of the extra sample event. This supersedes the earlier sample gallery link.
Verification after correction: all 546 API tests (4,519 assertions) and whitespace
checks passed. Temporary inspection/move/verification scripts were removed. No
feature code, routes, migrations or native app changes were made.

## Competition gallery presentation settings

Added Gallery settings inside Competition Photos. Administrators can set an optional
gallery display title, defaulting to the event name, without editing planning records.
Photo cards offer Use as cover; settings show the current cover and Use event logo
to restore the default. Only canonical verified photos belonging to this gallery and
plan can be selected. Cards use the existing private watermarked thumbnail endpoint.
No demo title or cover was automatically changed during implementation.

Presentation settings live separately from event/programme/upload records and work
before preparation. Saves are version-checked under an event lock and atomically
record the actor and before/after values. The settings page displays the latest ten
changes. Ineligible covers fall back to the event logo without exposing originals.
A shared eligible-photo query now serves gallery browsing and cover selection.

Migration 000007 ran locally. Down removes only presentation settings/change history,
leaving event, programme and media records. ADR-0027 records the boundary. Tests cover
independent titles, reset/default behavior, audit/idempotency, stale writes, photo
eligibility/event isolation, private thumbnails, reset-cover, input limits and admin
permissions. Verification: all 549 API tests (4,559 assertions), scoped Pint,
full route listing, local migration and whitespace checks passed. No native rebuild
was needed.

Next: explicit customer release/access controls; watermark library/overrides,
production storage and video transfer remain pending. Gallery settings never publish
photos or modify contribution readiness. Owner visual review remains pending.

## Plan check and automatic-visibility customer preview

Owner reminded us to follow the plan. Sections 7/12 specify automatic photo visibility
once required section photo contributions are complete/verified or explicitly waived.
Do not introduce an extra manual section-release approval step. Earlier roadmap notes
about explicit release controls are superseded by this clarification of the existing
plan, not a new owner product decision.

Added Customer Preview within the management gallery. It uses the public-facing
layout while remaining behind administrator authentication, with an explicit preview
banner and Back to management. Visible sections derive from readiness on each page
and image request. Hide unready sections and days containing no ready sections;
retain whole-event/day/section/competitor navigation. Ready images use the existing
private watermarked derivatives. Direct unready image links are denied, including
cached previews. A selected cover from an unready section falls back to event artwork.
The admin management browser still shows the full private gallery.

No publication state, customer credentials, purchasing controls or automatic emails
were added. The preview does not implement event-code registration, customer account
access, My Dancers/My Studios filtering, favourites, purchases or original delivery.
The plan requires web/phone foundations to be designed together; the existing crew
app must not be assumed to be the customer app. This is an admin review tool within
the current management milestone, not completion of Stage 5.

Read-only check of the existing EDC demo shows only 01 Friday in Customer Preview;
the unfinished Saturday section remains visible in management. Tests cover all-role
completion, direct-image denial, new pending uploads, stale programmes, days/covers,
admin-only access and event isolation. No demo photo/completion records were changed.
Verification: all 552 API tests (4,601 assertions), scoped Pint, full route listing
and whitespace checks passed. No migration or native rebuild was needed. Browser
visual review remains with the owner.

Next: event-code customer-access foundation consistent across web and phone, while
keeping watermark library/overrides, commerce, production delivery and video transfer
explicitly outstanding. No manual section-release button is planned.


## Image-proportioned gallery frames

Owner screenshot showed side bars around the preview header logo and top/bottom
bars around landscape thumbnails. The Customer Preview logo now uses its natural
aspect ratio within the existing 180×120 maximum size. Thumbnail images in Customer
Preview and management now use automatic height, and grid cards align at the top
rather than stretching to the tallest photo. Images remain uncropped and containers
follow each image's proportions. No image bytes, routes or readiness logic changed.

Verification: all 552 API tests (4,601 assertions) and whitespace checks passed.
No route, schema or native changes; visual review of the resized frames remains
with the owner after refresh.


## Customer event registration and first web access

Owner selected **both iPhone and Android** for the first customer phone version.
Implemented the shared user/event registration model and existing-account web flow
at `/my/competitions`. Exact current event codes are case-normalized; invalid and
ambiguous codes fail validation. Repeated registration is idempotent. Stable event
identity preserves registration through later code changes.

The shared preview renderer now supports customer routes. Membership and current
section readiness protect folder, cover and image requests, including cached images.
Customer pages omit administrative controls; index cards use the event logo. Active
admins can explicitly register events for review, without bypassing membership.
No real accounts were registered as part of implementation and no upload state changed.

Migration 2026_09_22_000008 applied locally; down reviewed: it drops only registrations.
See ADR-0028 and `docs/api/Customer-Competition-Access.md` for routes and access rules.

Next: customer onboarding and the shared mobile API/authentication design. Both phone
platforms are required; framework remains open. Signup, guest access, mobile clients,
personal filters, favourites, family sharing, commerce and original delivery remain
outstanding. Existing web sign-in is reused; its customer branding remains to be done.
Visual review requires the owner's signed-in browser.

Verification: all 557 Laravel tests passed (4,654 assertions), scoped Pint passed,
365 routes listed successfully, and `git diff --check` passed. The five added tests
cover exact/ambiguous codes, idempotency, stable registrations, account/event isolation,
inactive/cancelled access and ready-section image gating. No native build was needed.


## Pending correction: event selection and separate customer login code

Owner clarified that competition media codes may be shared across several events
and are not the codes customers use to access galleries. Replace the media-code
lookup with an event dropdown plus that event's separate login code. Keep stable
user/event registrations and automatic ready-section gating. Add login-code management
to the event's Gallery settings. No media naming changes are requested.

Plan and ADR corrected; runtime implementation is still unchanged. AGENTS.md requires
aws-secrets-manager for credential work, but no installed skill or callable retrieval
tool was found. Asked for a narrow exception to use Laravel local hashing without AWS;
awaiting the owner's answer before implementing credential storage/verification.


### Correction implemented

Owner approved Laravel local hashing without AWS. Event dropdown + separate login
code now replaces the media-code lookup. Gallery settings let administrators set
or replace the selected event's code using existing stale-save protection. Codes
are normalized and hashed; hashes are hidden, audit contains only the change marker,
and submitted codes are excluded from validation flashing. No real event code was
chosen or displayed during development.

Migration 000009 retains registrations but requires `access_verified_at` before
browsing; earlier media-code registrations must verify the correct login code. The
old `registered_code` column is removed. Verified membership survives code replacement.
The event selector lists noncancelled competitions with configured codes by name/date.
Readiness and original-file privacy continue unchanged. Media naming is untouched.

Rollback reviewed: removes code hashes/verification timestamps and recreates an empty
nullable legacy code column; no media, events or accounts are removed. Guest access
and signup remain pending. Next: owner sets an event login code in Gallery settings
and reviews My Competitions, then customer onboarding/mobile API work continues.

Verification: all 558 Laravel tests passed (4,686 assertions); scoped Pint and
`git diff --check` passed. Migration 000009 applied locally. Tests cover two events
sharing a media code but requiring independent login codes, legacy registration
reverification, settings authorization/version conflicts, hash secrecy and current
readiness gating. Routes were unchanged. Owner browser review remains outstanding.


### Event dropdown visibility correction

The owner found the event dropdown empty because events without a configured login
code were filtered out. It now lists all noncancelled competition events by name/date,
including events with no gallery settings record. Verification remains required and
unconfigured events cannot grant access. Updated the existing access test to check
this distinction and corrected the settings helper text.

Verification: all 558 Laravel tests passed (4,687 assertions), scoped Pint and
whitespace checks passed. No route or schema changes were needed.


## Customer web account creation

Checked Stage 5 and the roadmap; customer signup was the next missing web foundation.
Added customer sign-in and registration forms under `/customer`, guest redirects for
My Competitions, and public navigation/sign-out. Registration creates a fixed-role
customer user and profile transactionally, normalizes email, rejects duplicate or
soft-deleted account emails, validates password confirmation/length and hashes via
the existing User cast. Unique insert races become validation errors. Sign-in reuses
the existing login controller, limits and 2FA; sign-out returns customers to their
own sign-in page. Existing password recovery is linked, not reimplemented.

No real accounts or messages were created. No event registration, email verification,
terms/privacy acceptance, marketing consent or purchase entitlement is inferred.
Uses the previously authorized local Laravel hashing approach without AWS. No schema
change or native app build is needed. Email verification/customer policy onboarding,
guest access and the shared mobile API remain next work, with both phone platforms
required. Owner visual review remains outstanding.

Verification: all 563 Laravel tests passed (4,738 assertions), scoped Pint passed,
369 routes listed successfully and whitespace checks passed. Added tests cover guest
routing, fixed customer privileges, duplicate/deleted emails, password validation,
no fabricated verification/consent, inactive sign-in and customer sign-out.


## Shared My Media plus first persistent concert-video access

Owner requested one signup for all media and the next step afterward. Account wording
and landing now cover competition photos, concert photos and concert videos. My Media
shows owned competition galleries, saved concert videos and entry points for separate
competition and studio/concert workflows. Admin home remains unchanged.

Followed product-plan section 15 for the next concert slice: Add Concert Video accepts
a studio-supplied code, can add multiple currently released matching concerts, and
persists access on the shared account. Reused existing ConcertAccessGrant records and
concert password/availability logic. No real codes or grants were read/created during
implementation. No automatic studio membership access or publication was added.

Original claim/expiry dates stay fixed. User and concert locks protect repeat additions;
revoked, expired or deleted grants cannot be recreated. A saved account grant is checked
against live availability and the current code fingerprint before browsing/playback.
Old session unlocks cannot override a denied saved grant for that signed-in account.

No schema or native build needed. ADR-0029 and Customer-Media.md explain boundaries:
code-group management/later-release inclusion, separate six/twelve-month windows,
reactivation, reminders, native concert photo galleries and verified guest access
remain future work. Next: those concert access foundations plus customer verification
and mobile API planning. Browser review remains with the owner.

Verification: all 568 Laravel tests passed (4,789 assertions), scoped Pint passed,
371 routes listed successfully and whitespace checks passed. New tests cover one
code/multiple concerts, account isolation, repeat entry without expiry extension,
code rotation, revocation/expiry, unavailable releases and shared account wording.


## Add media box, profile dancers/studios and concert year selection

Owner requested one top Add media box with competition event + photo code, registered
studio photo links, and concert year + video code. Implemented all three in a responsive
box. Missing dancer or studio details show Add now links to the relevant My Profile
section. My Profile now has working account-owned dancer and studio addition forms;
duplicates are idempotent and posted user IDs cannot redirect ownership.

Competition registration checks the dancer prerequisite. Concert video lookup checks
registered active studios and selected year, rechecking the concert identity/year
under lock before granting access. Already registered media remains visible; profile
names never narrow an authorized competition gallery. Studio photo pages link to
existing released photo galleries and show waiting states when none exist.

Migration 000010 applied locally. Down reviewed: drops only the new profile association
tables, retaining accounts, events, media and access grants. No real customer profile
data was added. Native photo orders, studio-code verification and richer profile editing
remain pending. Existing provider photo access/order controls remain in place.

Verification: all 571 Laravel tests passed (4,842 assertions), scoped Pint passed,
374 routes listed successfully and whitespace checks passed. New coverage includes
missing-profile prompts, owned/duplicate dancer and studio records, photo links,
unauthorized profiles, and video grants scoped to selected year and registered studio.
Browser visual review remains with the owner.


## Expanded customer profile

Owner requested the profile now, using the complete customer plan. Reviewed profile,
family, routine-discovery and download requirements. Added editable account display
name, preferred name and phone; email remains read-only until verification exists.
Added dancer UUIDs, edit/remove, descriptive relationship and normalized programme-name
aliases. Added studio removal with visibility of inactive registered studios. Added
explicit saved media-ready/expiry email preferences and marketing opt-in/out, preserving
unrelated settings and the original opt-in timestamp while enabled.

All mutations are owner-scoped and transactional. Posted IDs/privileges/emails cannot
change another account. Removing a dancer or studio does not remove media access.
No real customer data was added or modified, and no messages were sent. No birth dates,
payment details or false consent records were collected. Existing prerequisite flows
continue to use the account’s dancer/studio records.

Migration 000011 applied locally; existing dancers receive UUIDs. Down reviewed: only
new dancer metadata/UUID columns are removed, retaining dancer names and ownership.
No route changes. Customer-Profile.md maps the outstanding plan: verified family access,
email changes, studio codes, notification dispatch, order/invoice views, routine filters
and device-local saving. These remain future work rather than nonfunctional controls.

Verification: all 575 Laravel tests passed (4,886 assertions), scoped Pint and
whitespace checks passed. Tests cover protected account fields, immediate detail
refresh, preference merge/opt-in timing, dancer alias normalization/clearing, duplicate
names, cross-account edit/removal rejection and preservation of media registrations.
No routes changed; owner browser review remains outstanding.


## My Dancers routine discovery

Connected My Profile dancer names and programme aliases to a new My Dancers view in
each verified customer competition. Matches use full name boundaries and current
programme item titles; only items in ready sections appear. The suggestion links to
the normal guarded album. Whole Event remains available independently, so unknown
or unmatched names do not hide purchased/authorized media. Customer Preview stays
unchanged and admin-only. No account or gallery data was mutated by this read-only
feature. See Customer-Competition-Access.md for route and matching scope.

Structured studio data is not present on current programme nodes. My Studios’ Routines
will need that data; text guesses from competitor titles would be unreliable.

Verification: all 576 Laravel tests passed (4,907 assertions), scoped Pint passed,
375 routes listed successfully and whitespace checks passed. Tests cover aliases,
name boundaries, readiness changes, account isolation and Whole Event access.


## My Studios’ Routines through explicit studio assignment

The next profile-to-gallery step is implemented. Admins can set or clear one studio
on a current competitor album in Media → Competition Photos. Assignment is stored
separately from immutable programme/upload plans, with actor change history and
stale-save protection. Customer My Studios’ Routines suggests only current assigned
items whose section is ready, whose studio is saved in the customer's profile, and
whose event was already registered. Whole Event and all existing image gates remain.
No real routine studios were assigned during implementation.

Migration 000012 applied locally. Down reviewed: drops only assignment and history
tables; no programme, photos, accounts, studio records or registrations are deleted.
ADR-0030 records this presentation association. Profile studio selection is still a
self-declared preference; studio gallery-code verification is a separate next step.

Verification: all 578 Laravel tests passed (4,944 assertions), scoped Pint passed,
377 routes listed successfully and whitespace checks passed. Tests cover assignment,
clear/no-op/audit/stale-save, current item validation, profile-studio matching, live
readiness, event/account isolation and continued Whole Event access.


## Studio gallery-code verification

The plan's studio-code requirement is now connected. Admin Studio management has a
separate customer gallery code form, hashed and versioned with action-only audit history.
The internal studio code, concert video codes and competition event codes are unchanged.
Customer My Profile requires that gallery code before a studio is verified. Previous
studio associations remain visible but need re-verification. My Media photo links,
concert-video studio/year lookup and My Studios’ Routines use only verified active
associations. Existing video grants/orders and Whole Event access are not deleted.

Migration 000013 applied locally. Down reviewed: removes hash/version/verification
fields and code-change records only, leaving account/studio/media records intact.
The owner previously authorized Laravel local hashing without AWS; no real codes were
created or read, no messages were sent. See ADR-0031 and Customer-Profile.md.

Verification: all 581 Laravel tests passed (4,986 assertions), scoped Pint passed,
378 routes listed successfully and whitespace checks passed. Tests cover separate
hashes, stale admin saves, no plaintext/history leak, invalid/internal codes,
legacy association re-verification, account isolation and unchanged verified access
after code rotation. Existing public concert pages retain their separate rules.

## Published video titles (27 September 2026)

The uploader now offers **Download Video Titles…** alongside selected and prepared
concerts, plus **Video Titles…** in concert review. An online request checks that
2.3 Video Titles is ready; the saved ZIP contains the exact 4K JPEGs published in
the browser. The user chooses where to save it and Finder reveals the archive.
It can then be opened for editing offline. This does not change programme folders
or upload any titles as photos.

The API requires existing programme access and `programmes:read`. The client
checks event/revision identity, a pinned same-origin package URL, size and SHA-256
before writing a file. A changed programme requires refreshing the selected event
(or updating the prepared programme); an unpublished/stale package produces a
clear message. A running older uploader must be reopened using the rebuilt app.

Verification for video-title downloads: 90 Swift tests passed, including stale
revision, wrong event/URL, oversize manifest and damaged download rejection.
The release app was rebuilt successfully. Laravel's full suite passed 861 tests
with 8,318 assertions; browser-generated ZIPs passed the server archive validator.


### Concert customer delivery verification — 28 September 2026

The existing concert upload protocol was exercised through signed local transfer,
checksum verification, Finished Uploading, tracker readiness, studio review and
release, and customer code access/playback/individual download. Completion now
orders exported items naturally by filename. Customer bulk downloading retains
the existing multiple-file manager, not a concert ZIP. No uploader storage
destination or live object path changed. Production catalogue/disk verification
remains necessary; the current single-file upload limit is 5 GiB. See the customer
web gallery milestone for delivery checks and remaining rollout work.

### Approved-event search and concert separator import — 29 September 2026

- Approved event cards display studio name followed by the booking event title.
  The API adds optional studio/title fields without changing the legacy name;
  the app remains compatible with older responses. Partial searches match studio,
  scheduling-event name or booking title and retain existing access/approval gates.
- Search refreshes after a 300 ms typing pause, cancels superseded work and rejects
  stale results. It waits for an active preparation operation rather than dropping
  the latest search.
- Added offline JPEG import to prepared concerts. Local downsampled luminance
  detection proposes black separators and groups in natural folder/filename order.
  Crew review first/last images, choose destinations, split/combine groups, rename
  conflicting files, exclude frames or restore false positives before confirming.
- Import is serialized through the photo library, preserves source files, excludes
  separators, rejects changed files/unsafe paths/locked uploaded items and refuses
  destination collisions. Hidden staging avoids discovering partially copied JPEGs;
  completed copies are removed from the dialog's retry plan after a partial failure.
  Upload remains separate. No cloud AI, live-storage change or source-file deletion.
- Verification: all 100 Swift tests passed, including new detector/group editing,
  safe-copy, collision and changed-source checks. Release app rebuilt and locally
  signed successfully. Scoped Pint and diff checks passed. All 1,087 Laravel tests
  passed (11,576 assertions). Next: pilot with real concert camera JPEGs to assess
  separator sensitivity and folder/filename ordering. No real photos were imported.


### Folder accents and clear import completion — 29 September 2026

- Newly generated concert folder titles remove composed/decomposed accents while
  display titles and video-title text retain their original spelling. Existing
  approved snapshots, event root names and saved folder paths stay readable.
  Uploader Edit Items → Save Changes uses the existing journalled rename flow to
  clean old unuploaded folders without changing item identity or losing photos.
  Server sync accepts old and new cleaned folder spellings; competition comparison
  behaviour is unchanged.
- Successful photo import now displays a green Import complete panel with the
  imported count and next step, and replaces Import 0 photos with a bottom Done
  button. Partial failures retain the remaining-photo controls. The completion
  copy distinguishes local import from Upload.
- Checked the current local runtime: photo uploads enabled, local filesystem,
  website URL http://localhost. Upload receipts feed tracker 3.1; all expected photo
  roles must explicitly complete their uploads. Admin Concert Photos provides a
  private preview; customer galleries retain their release/access gates.
- Accent/import verification: 101 Swift tests passed and release app rebuilt.
  Full Laravel suite passed 1,090 tests (11,579 assertions); subsequent focused
  programme sync/title checks passed 21 tests (65 assertions).


### Concert role confirmation, upload all and automatic private galleries

- Every concert open requires an explicit role confirmation, including cached and
  reopened prepared folders. Stored role choice remains a suggestion. Competitions
  retain their saved station role and per-section Ready workflow.
- Concert Upload queues all current stable, unuploaded JPEGs. No per-item Ready
  controls. Later photos need another click; already verified files are not resent.
  Unreadable/copying files explain why uploading cannot start. Pending queues must
  be explicitly cleared or uploaded before a role change; clearing keeps photos.
- Verified linked-concert photos are attached incrementally to private customer
  albums in the receipt transaction, with a watermarked thumbnail and existing
  storage key. Retries/reservations are idempotent; duplicate receipts resolve to
  the canonical file. Top Pic changes update customer asset metadata as well.
- No queue worker or manual gallery sync is needed. Gallery repair remains under
  a collapsed admin control for legacy uploads / later event linking. Standalone
  scheduling events without a customer concert continue private capture uploads.
- Existing album edits, cover choice, visibility and release status are retained.
  New photos in an already published album remain hidden for admin review; normal
  unreleased albums stay draft/private until explicit 4.1 release. Upload Complete
  remains the photographer's explicit finish declaration for tracker 3.1.
- Verification: 104 Swift tests passed and the signed release app rebuilt. The full
  Laravel run passed 1,092 of 1,093 tests; its sole failure was an assertion for the
  replaced manual-sync help text. Updated that expectation and reran all affected
  upload/gallery/Top Pic suites: 60 tests passed (641 assertions), including
  archive/deletion preservation. Scoped Pint and diff checks passed. No real media
  uploaded or released; restart the rebuilt uploader to use the new flow.


### Role selection before concert preparation — 29 September 2026

- Concert selection loads current configured media roles; Prepare stays disabled
  until an explicit choice. Roles and the programme revision are rechecked before
  writing. P1/P2 save programme/photo folders; V downloads the verified ready title
  ZIP plus programme in a separate Video directory with no photo folders.
- Optional manifest preparation mode preserves old prepared folders. New video
  preparations use a dedicated programme/title view and retain the edited concert
  video uploader. Existing photos, local review state and role queues are preserved.
  A role chosen for preparation confirms the immediate opening; subsequent opens
  still request confirmation. Pending queues retain the role-change safeguard.
- Missing/stale titles fail with the existing actionable 2.3 message before file
  creation. ZIP title JPEGs are opened/extracted locally. Competition preparation
  keeps its existing workflow. Updated the product plan to record the owner's
  newer explicit concert-role requirement over the earlier optional-role rule.
- Verification: 105 Swift tests passed, including separate video preparation,
  no photo-folder creation, preservation of existing photos and mismatched title
  rejection. Release app rebuilt and signed. Full Sail suite passed 1,093 tests
  (11,613 assertions); diff check passed. No user preparation folders or photos
  changed. Next: reopen the rebuilt app and choose a concert role before Prepare.


### Recovering an existing photo preparation — 29 September 2026

- Diagnosed an empty nested Energy preparation created by selecting the existing
  event folder as the destination for Prepare. The outer preparation still has
  all 146 JPEGs and its review records; no missing expected folders. No original
  files or the nested copy were moved/deleted.
- Prepare now detects a preparation manifest in the selected directory, validates
  event/server/revision/media mode and reopens a match without rebuilding anything.
  Mismatches explain how to resume or select a parent folder for a new preparation.
- Open Existing Event Folder now remembers the selected location. Prepared cards
  display their paths to distinguish similarly named roots. Regression coverage
  checks no nested directory, unchanged photos/review/manifest, and mismatches.
- Verification: 106 Swift tests passed; release app rebuilt and signed. Full Sail
  suite passed 1,093 tests (11,613 assertions); diff check passed. Observed the
  user's uploader showing the original photos and actively uploading its 146-photo
  batch after opening the outer folder. No re-import was needed. Restart the rebuilt
  app after the current upload finishes to pick up the nested-folder prevention.

- Confirmed the existing watcher also supports external sorting: JPEGs copied or
  exported directly into prepared numbered folders are detected automatically
  after the stability checks. Import Photos is optional; keep folder names and
  preparation files intact. Documented the Finder/editing-software workflow.


### Automatic concert photo completion — 30 September 2026

- Owner replaced the proposed end-of-upload prompt with unattended completion:
  concert Upload now records role completion after the local queue drains and
  the server verifies every reservation. Other required photo roles still gate
  tracker 3.1. No gallery is released by this action.
- Concert footer replaces Upload Complete with an automatic-completion indicator.
  A failed completion request offers Retry completion without retransferring
  acknowledged photos, including after restarting. Transient failures retry in
  the upload loop. Competitions retain explicit completion.
- Paused/failed uploads, copying files and newly discovered unuploaded photos
  cannot auto-complete. Later uploads reopen the role through existing reservation
  logic. Empty local preparations are not automatically marked complete.
- Completion recovery is tied to the role in the upload receipt: switching roles
  cannot complete another photographer using the existing uploaded batch.
- Updated tracker guidance and release blockers to match. Verification: 109 Swift
  tests passed and the signed release app rebuilt. Full Sail run passed 1,091 of
  1,093 tests; two assertions loaded the previous completion wording while it was
  being updated. Both affected suites then passed all 56 tests (628 assertions).
  Scoped Pint and diff checks passed. No live uploads or releases performed.
- Next: reopen the rebuilt uploader after any running transfer ends. Concert
  uploads now finish unattended; older verified batches offer Retry completion.


### Dress rehearsal portrait uploads — 30 September 2026

- Approved dress rehearsal bookings with portrait photography automatically get
  one stable portrait preparation, without PDF upload or task 2.2 approval. An
  idempotent data migration prepares existing approved sessions while preserving
  existing programme drafts/revisions. Rollback intentionally retains identities
  because photo uploads can reference them.
- The API advertises a dress_rehearsal capture mode. The uploader creates one plain
  Dress Rehearsal Portraits folder, requests the photographer role and hides
  programme editing, title/video controls and black-frame sorting for this mode.
  Copy JPEGs into the folder and Upload; existing automatic completion drives 3.1.
  The portrait assistant is not a separate photo-upload role.
- Customer albums use Dress Rehearsal Portraits without a numeric prefix. Studio
  gallery cards lead directly to the released images, bypassing release-group and
  album navigation. Release, studio access, shopping and private storage checks
  remain in force; albums are still private until 4.1 release.
- Explicit photo release also publishes the draft DR media record created by
  booking approval; preparing or uploading portraits does not open customer access.
- The local migration prepared five existing approved DR sessions, including Energy
  Dance Studio on 30 December 2026. The signed uploader release was rebuilt; all
  110 Swift tests and the two focused upload/release tests (38 assertions) passed.
  The full Sail suite passed all 1,095 tests (11,651 assertions). Scoped Pint and
  diff checks passed. No real photos were uploaded and no customer galleries
  were released.
- Next: reopen the rebuilt uploader, refresh approved events, choose the DR event
  and photographer role, prepare its folder, copy JPEGs into Dress Rehearsal
  Portraits, then click Upload.

### Newest prepared projects first — 30 September 2026

- Local prepared projects sort by their saved preparation date, newest first,
  whenever the project list loads. Opening an older project does not move it ahead
  of a more recently prepared project.
- All 111 Swift tests passed and the signed release app rebuilt. Reopen the rebuilt
  uploader to load the updated ordering.
