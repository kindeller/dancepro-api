# Mac uploader: offline photo review

Date: 2026-09-21

## Scope

Stage 4.2 of the agreed product plan. Build the review screen using the
[owner's uploader draft](../specifications/Mac-Uploader-Layout-Reference.md).

## Implemented

- Open Event on each prepared local event. Open Prepared Folder also opens an
  existing preparation from a chosen disk without server access or a login.
- Separate dark review screen with Back to events, Programme, a left folder list
  grouped by competition day/section, a pinned Top Pics collection, local photo
  counts, a large preview, expand, Fit and pinch zoom. Concerts use a flat list.
- Up/down arrows and buttons navigate photos; moving to another photo resets
  zoom. Return/star toggles Top Pic. Delete/bin sends a photo to macOS Trash.
  Undo Trash / Command-Z restores the last supported trash operation, including
  after reopening, provided that Trash file still exists. Never overwrites a
  newer photo at the original location.
- Automatic two-second scans run on a separate actor. Only JPEGs stable across
  observations and with complete JPEG structure enter review. Changing or
  incomplete files are held back; the folder shows Shooting while waiting.
- Orientation-aware, bounded-size previews avoid loading original-size images
  into the view. Originals remain unchanged by previews and Top Pic selection.
- Atomic `photo-review.json` stores local UUIDs, filesystem identity, Top Pics and
  recent Trash locations. Same-volume renames/moves retain selections. Copies or
  cross-volume moves are not an implemented reconciliation workflow.
- A local uploaded flag blocks culling while allowing Top Pics. Actual verified
  upload integration must populate it in Stage 4.4; there are no uploads yet.
- Single app window, asynchronous native folder pickers and atomic executable
  replacement in the development packaging script.

## Verification

- 15 native tests pass, including five new review tests: stable/complete JPEGs,
  natural ordering, preview generation, persistent Top Pics and same-volume move
  identity, stale-selection rejection, real macOS Trash/Undo of generated test
  files, restore collision protection, symlinks and uploaded-photo culling guard.
- Full Sail suite: 477 tests / 3,969 assertions passed; no Laravel routes or
  database changes. Existing required PHP formatting checked without changing
  unrelated files. Native release build and local signing succeeded.
- Native app launch and the new Open Prepared Folder control were verified.
  The remaining visual review interaction was interrupted by changing picker
  windows and computer-use timeouts; do not claim the full review UI has been
  manually verified. No user photos were trashed or modified during UI checking.

## Remaining and next action

User check: open a prepared event, place JPEGs in its numbered folders, open the
review screen and check navigation, star and zoom with real capture output.
Test culling only on disposable copies initially. Very large capture libraries,
external-disk disconnects and camera-writer variations still need field testing.

Next implementation: Stage 4.3, Move Photos thumbnail selection and drag into a
folder, preserving photo IDs and Top Pics. Concert structure changes follow the
same identity rules. Ready/queue controls and actual upload statuses belong to
Stage 4.4; the current bottom bar states that uploading is not implemented.

## Preparation/opening clarification

Owner encountered the missing preparation.json error by choosing a folder before
preparing an event. Inspection confirmed the local list was empty and the demo
still needed programme reapproval. The empty state now explains the prepare
workflow; choosing an ordinary folder gives a product-level next action instead
of a missing-file error. The existing-folder button explicitly says Existing.
A selected legacy event explains where to approve again. Successful preparation
now opens photo review directly instead of opening Finder.

Verification: existing native suite and required Sail suite rerun; release app
rebuilt. Existing programme approval remains an explicit owner action.

## Review interaction refinement

The owner confirmed photo review and deleting work in the running app. Item
selection now gives the entire padded row a rectangular hit area, including
empty space and photo counts. Successful culling shows a five-second notice
naming the file moved to Trash, with an inline Undo button. Undo shows Photo
restored; failed operations show their error instead of a success notice.
Further deletes replace the notice with the latest filename and restart its
timer. The existing Undo Trash button and Command-Z remain available afterward.

Verification: native tests, required Sail regression/formatting checks and the
release build rerun for this refinement. No original media handling changed.

## Pinned Top Pics and competitor shortcut

Top Pics now sits above the sidebar ScrollView so it remains visible while the
competitor list scrolls. Its filled star is yellow. The cull control is a white
trash symbol on a red square, dimmed when unavailable. Command-Up selects the previous item; Command-Down selects the
next programme item (including empty folders, skipping breaks), across sections;
navigation stops at the first/last item. From Top Pics it opens the first item. A visible Next
Competitor / Next Item button exposes the shortcut, and the sidebar scrolls to
the selected row. Plain Up/Down continue to navigate photos.

Verification: native suite, full Sail suite, read-only Pint and release build.


## Safe Top Pics removal and pointer zoom

The preview buttons now share a 32-point square style, and the successful Trash
notice uses a red trash icon. Top Pics no longer displays a Review status or a
trash button/Delete shortcut. Click its star or press Return to unstar a photo;
the original remains in its competitor/item folder. The model also guards any
Top Pics cull call by clearing only the selection, including for uploaded photos.

Native AppKit magnification keeps the image point under the pointer fixed during
pinch zoom. Fit and selecting a new photo reset magnification; routine preview
updates retain it. Regression tests cover pointer anchoring, resets, persistent
Top Pics removal without modifying the JPEG, and competitor Trash/Undo behavior.

Verification: 18 native tests and the full Sail suite (477 tests, 3,969 assertions)
pass. PHP formatting and the packaged release build are checked for this update.


Move Photos is now implemented in the [next milestone](Mac-Uploader-Move-Photos.md).
The roadmap points there for current work and remaining Stage 4.3 scope.
