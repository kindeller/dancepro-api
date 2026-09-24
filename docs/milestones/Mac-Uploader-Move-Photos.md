# Mac uploader: Move Photos

Date: 2026-09-21

## Scope and implementation

The first part of Stage 4.3: offline photo reassignment within an already prepared
event. Move Photos replaces the preview with a lazy thumbnail grid while keeping
the folder sidebar. Click thumbnails to toggle selection, Select All/Clear, then
drag a selected thumbnail onto a prepared folder. The destination highlights on
hover. Move to provides a menu alternative. Done returns to review.

JPEG thumbnails are orientation-aware and limited to 360 pixels. Top Pics markers
and selection counts remain visible. Uploaded photos are locked against moving;
Top Pics is a collection, not a filesystem destination. Drag payloads contain
session-local photo IDs, and another event/window or an external text drop cannot
supply filesystem paths.

The photo library preflights the entire batch for stale capture files, duplicate
names, uploaded state, valid folders and same-volume movement. It never overwrites
an existing destination. Same-volume moves preserve UUIDs and Top Pics; records
are saved atomically. A move or state-save failure attempts to roll the completed
moves back without overwriting. If recovery is incomplete, the error requests a
refresh; scanning reconciles retained filesystem identities. A process exit during
a batch can leave photos in both folders; reopening discovers their actual paths.
No new persistent schema or architecture is needed.

## Verification

Regression tests cover multiple-photo moves and reopening with the same IDs and
Top Pics, collision preflight with all source files untouched, state-save failure
rollback, invalid destinations, stale files, uploaded locks and foreign drag
payloads. All 22 native tests and 477 Sail tests (3,969 assertions) pass.
Read-only Pint and git diff whitespace checks pass; the release app builds and
is packaged with local signing. Physical dragging in the running app still needs the
owner's interaction check; no user media was moved during implementation.

## Next action

Owner check: open an event, choose Move Photos, select thumbnails, drag onto the
correct folder (or use Move to), then Done. Confirm counts and Top Pics follow.

Next part of Stage 4.3: concert structure changes that preserve item/photo identity.
Ready, queues and actual upload remain Stage 4.4.


Concert rename/add/reorder and the local Ready queue are implemented in the
[next review batch](Mac-Uploader-Concert-Edits-and-Queue.md). Explicit combine/split
and transfer integration remain outstanding there.
