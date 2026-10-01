# DancePro Uploader for Mac

Native SwiftUI app, macOS 14 or later. This first slice prepares approved events:
sign in, select an event, download the programme and original PDF, create media
folders, and open saved programmes/folders offline. Offline JPEG photo review, Top Pics and Trash/Undo are also implemented.
Moving photos and uploading are subsequent stages.

## Run locally

1. Start the API with Colima and `./vendor/bin/sail up -d` from the repository root.
2. Build with `apps/mac-uploader/build-app.sh` (Xcode/Swift 6 required).
3. Double-click `apps/mac-uploader/dist/DancePro Uploader.app`.
4. Use `http://localhost` for this local API, or the HTTPS origin of an updated
   DancePro server. Sign in with an existing administrator account. Expand
   Two-factor authentication for an authenticator or recovery code.
5. Select an approved event, click **Prepare Event**, and choose a destination.
   Photo review opens automatically. Later use **Prepared on this Mac** to view
   saved titles, open the PDF or open folders without internet.

The build script creates an ad-hoc signed app for the build machine. This is not
a notarized installer or a distribution build for crew laptops. Developer ID,
notarization and target architectures remain release work.

## Behaviour and boundaries

- Top Pic stars and removals sync automatically after upload while the app is open
  and signed in. The app checks its durable metadata queue every two seconds,
  including on the event list; failed requests retry with a delay up to one minute.
  Offline changes survive quitting and resume when the app reopens. The footer
  shows pending/syncing/synced status and an optional **Retry sync** action.
  Unuploaded photos send their stars with the normal upload. Only flags are sent
  for verified photos; no second copy of the image is uploaded. Opening older local
  reviews reconciles previously saved post-upload stars once. A server with the
  new `/api/staff/events/{event}/programme/top-pics` endpoint is required.

- Login uses the existing staff API; only administrators with `programmes:read`
  can download. Ordinary crew accounts are not yet enabled for this workflow.
- The bearer token is stored only in this app's macOS Keychain item. Passwords,
  authenticator codes and recovery codes are never saved. No AWS credentials are
  accessed. The owner explicitly permitted API/Keychain implementation without
  the unavailable AWS secrets skill for this task.
- Login survives app restarts while the server token remains valid. The existing
  API expires tokens after 7 days by default; this app does not extend that policy.
  An expired token prompts sign-in again. Offline prepared files remain usable.
- Logout revokes the current token before removing it locally. A network failure
  leaves sign-in available for retry; no media is removed.
- HTTPS required except exact loopback development origins. Cross-origin source
  URLs and all HTTP redirects are rejected, so tokens are not forwarded elsewhere.
- Event selection uses the latest approved snapshot. Prepare checks it is still
  current and pins the PDF to that revision. If approval changes, select again.
- Older concert approvals without folder fields require explicit reapproval in
  the portal. The app does not alter or approve programme contents itself.
- Concert folders use the server's continuous `01`, `02`, … `99`, `100` sequence.
  Breaks create no folder. Punctuation stays in programme/title-card titles;
  folder titles are cleaned by the server.
- Competition folders use `<event code>/<07 section title>/<03 competitor name>` for one-day events. Multi-day events include `<01 weekday>` between event and section. Existing day/date layouts remain readable.
  Withdrawn competitor gaps stay intact.
- Root folder: `<event code or name> - v<version> - <revision UUID prefix>`.
  Full server/event/revision/item identities are stored in `preparation.json`.
  Existing folders must match the full identities; prefix collisions cannot
  silently overwrite a different event. New revisions get separate folders.
- Saves `programme.json`, optional `programme.pdf`, and `preparation.json` with
  item-to-folder mapping. No token is written into these files.
- Preparation writes a temporary staging directory and renames it only on
  success. Repeating the same revision preserves existing media. Changed/missing
  programme files or symlinked folders produce an actionable error instead of
  being overwritten. Later reconciliation will handle local media moves.
- Security-scoped bookmarks remember prepared roots across restarts. If an
  external disk is unavailable, reconnect it and reopen the app.

## Verification

```sh
swift test --package-path apps/mac-uploader
apps/mac-uploader/build-app.sh
./vendor/bin/sail artisan test --compact
./vendor/bin/sail artisan route:list
```

Native tests cover numbering past 99, breaks, title preservation, competition
number gaps, invalid paths, symlinks, missing PDF rollback, immutable repeat
preparation, revision separation, server origins and API JSON decoding.

## Photo review

Click **Open Event** beside a prepared event. Alternatively, **Open Existing Event
Folder…** opens an existing event root containing `preparation.json`; this
one-time folder selection does not add it to the remembered preparation list.
All review works offline.

Place JPEGs directly in the prepared item folders. After they finish writing,
they appear automatically. The sidebar shows counts and Top Pics; competitions
are grouped by day and section. Up/down browses photos, Return toggles Top Pic,
Delete moves to macOS Trash in competitor/item folders, and Command-Z / Undo
Trash restores the last supported cull. Top Pics has no trash button or Delete
shortcut: click the star or press Return to remove a selection, keeping the
original in its competitor/item folder. Pinch zooms around the pointer and Fit
resets zoom; selecting another photo fits it.
Programme opens the locally saved titles or PDF. No original is modified by
previewing or starring it.

**Move Photos** opens a thumbnail grid with the event folder sidebar. Top Pics
is excluded from this screen. Click photos
to select them, then drag a selected thumbnail onto the correct folder, or use
**Move to…**. Select All/Clear changes the selection; Done returns to review.
Moves preserve Top Pics and photo identity. The move notice has an Undo button;
Undo Move in the bottom bar remains available afterward and after reopening.
Undo restores the entire batch without overwriting an occupied original filename. Filename conflicts stop the batch
without overwriting, and uploaded photos cannot be moved through the uploader.

Review selections and Trash history are saved in `photo-review.json` next to the
programme. Undo survives reopening, but needs its original Trash file and refuses
to overwrite a new file with the same name. If Trash has been emptied, the app
cannot restore that photo. A crash between moving a file and saving metadata may
require Finder recovery. Do not review one shared directory concurrently from
multiple app processes or computers.

Only stable, structurally complete JPEGs appear. Incomplete/unreadable JPEGs stay
out of review; the folder shows Shooting while files are pending. Scans and
preview decoding run off the UI actor. Previews are capped at 2400 pixels and
respect image orientation. Originals retain full resolution.

The review layout follows the owner-supplied draft. Move Photos, section Ready,
queued uploads and upload verification are subsequent stages. The current
footer identifies this limitation instead of showing simulated upload progress.


## Concert edits and offline queue

In a concert, **Edit Items** lets you rename, add and reorder rows. Pause capture
and copying first, then Save Changes. Folders renumber continuously and preserve
their media and Top Pics. The downloaded programme/PDF stays unchanged; the app
shows your local working titles. Reopening resumes an interrupted folder change.
Uploaded media locks editing. In **Move Photos**, select photos and choose
**Split into New Item…** to create a titled item directly after the current one.
The selected photos move there and folders renumber. Undo Move returns the photos
while retaining the new item. **Combine Item…** in Edit Items combines selected
rows in programme order, joining titles with spaces. Save Changes applies the
combined draft and renumbers folders; Top Pics and photo identities persist.
Saved combinations have no one-click undo; Cancel discards the editor draft.
Duplicate filenames and non-JPEG source files block combining without overwrite.

For **concerts**, choose and confirm your role every time you open the event,
including previously prepared events. A remembered role is not confirmation.
Use P1/P2 for photos and V for concert video. Previously uploaded photos keep
their original role; a pending queue must be uploaded or explicitly cleared before
changing roles. Clearing the queue does not remove the photos.

Concert folders have no **Ready** buttons. **Upload** queues all current stable,
unuploaded JPEGs in the prepared concert. Files still copying or unreadable block
that click with an explanation. Later additions require another Upload click.
Competitions retain their saved equipment role and section **Ready** buttons.

**Upload** automatically syncs the programme, checks the event role, prepares
checksums and transfers the photos. **Pause** cancels the active request;
**Resume** retries safely. Reopening the app never starts uploading automatically.
Only a matching verified server receipt marks a photo Uploaded. Uploaded folders
cannot be culled or rearranged; Top Pics can still be changed and sync automatically.

Photo transfer currently uses private local-server storage, verifying byte count,
SHA-256 and JPEG decoding. For linked concerts, each verified photo automatically
creates/updates its private customer album and watermarked thumbnail using the
existing storage paths. No manual gallery sync is required. Album titles, order,
covers and release settings are preserved. Tracker **4.1 Photo Release** remains
the explicit customer release action. Gallery repair is available for older
uploads or standalone events linked to a customer concert later. Photos added to
an already released album stay hidden pending administrator review.

Conflicting working programmes stop with an error; existing photos are preserved.
Production/cloud photo transport remains disabled by default; server pixel/memory
and HTTP request limits apply (ADR-0020).

To combine concert items, open **Edit Items → Combine Item…**, select two or more
rows, then Combine. Titles join in programme order with spaces. Review the
renumbered draft, then Save Changes; Cancel discards edits.

The supplied icon is stored in Assets/Uploader.png. build-app.sh includes standard
ICNS sizes in the signed app bundle.

Concert photo uploads automatically record completion after all local photos and server reservations are verified. No separate finish button or return visit is needed. A green Complete indicator confirms the tracker update. Later uploads reopen the role and complete again after verification. If only the tracker update fails, Retry completion sends no photos again. Paused, failed, copying or newly discovered unuploaded photos prevent automatic completion. Empty local preparations are not automatically completed.

Competitions retain **Upload Complete** and its confirmation, including empty items. This never publishes a gallery or completes another photographer's work.

Click the bottom **queued** count, then **Server · Event roles**, to see current
server progress across Macs. It shows verified files, uploads still awaited, items
explicitly completed and unfilled photographer roles. **Refresh** checks again;
the timestamp shows when the snapshot was taken. This needs a previously connected
working programme and an online session. It does not start uploads or publish photos.

Server progress also identifies the accounts that uploaded files for each role.
Copied files from replacement laptops are verified and reuse the same stored photo
within that item/role; earlier uploads with no actor record remain labelled unknown.
Crew who signed in before this update may need to sign in again for uploader access.


Administrators can open an event in the web portal and choose **Photo uploads**
to waive missing work or reinstate its requirement, with a reason and exact scope.
Server progress shows **Waived by admin** or **Complete with exceptions**, alongside
separate waived counts. Later uploads reopen the affected waived item. The local
completion message includes the waiver count from its latest acknowledgement;
refresh server progress for current state. Nothing is published by these actions.


For concerts, **Programme** now shows a **Confirmed** or **Update available** tag.
Open Programme to check online, confirm the first shared version, or load the version
another photographer confirmed. Local edits stay local until Confirm Programme,
Share Changes or the first Upload succeeds. A successful shared change posts an
automatic Crew Hub event-chat message. The app checks every 30 seconds; failed checks
show Check needed. Existing photo folders and Top Pics survive supported renames and
combinations. Conflicting local additions are kept and flagged for review. Structural
changes after contributions begin remain blocked pending administrator reconciliation.
Competition folder additions/comparison use their own folder-check status, without concert confirmation tags.


Competition preparations now match the capture hierarchy, for example
**EDC926 / 01 Friday / 07 Jazz 12 & Under / 03 Jessica Smith**. Existing date/Section
layouts still open. Extra numbered sections and competitors appear automatically
as their folders are created. The app keeps actual folder names and matches by day,
section and competitor number. Safe punctuation is retained in competition capture
titles; concert folder cleanup is unchanged.

Open **Programme** to see **Folder differences to check** across P1/P2/V. The app
checks/reports metadata every 30 seconds while idle and signed in; Check folders
runs immediately. Photo Upload remains manual. Name differences and folders not yet
seen on another role do not block normal uploads. Unrecognised/ambiguous local
folders are listed for review and prevent Upload Complete until resolved. No folders
are automatically renamed or created on another station. V reports its folders but
does not yet transfer videos. Comparison timestamps refer to stations' last reports.

Changed report batches notify Crew Hub event chat once; retrying the same batch
does not post another message. Existing photo identities and Top Pics survive folder
name changes. Moving an uploaded photo to a different competitor number is flagged
for correction rather than silently changing its server ownership.


One-day competition preparation omits the day directory entirely, e.g.
**EDC926 / 07 Jazz 12 & Under / 03 Jessica Smith**. Multi-day events retain
**01 Friday**. Existing one-day preparations with that wrapper open without moving
folders; preparing them again retains the existing layout.

The Programme panel also lists local video filename matches. **EDC926_07_03.mp4**
means event EDC926, section 07, competitor 03, independent of day. MP4, MOV, M4V and
MXF names are checked at event, day, section and competitor levels of recognised
folders. Wrong events, unknown/ambiguous numbers and conflicts with the containing
competitor/section are flagged. This checks names only: videos are not read, renamed,
transferred or marked uploaded.
# Competition video station

Select the competition V role from the event role picker to open the video upload
screen. Choose the recording source folder once per event; the app remembers its
macOS security-scoped bookmark. Scan to review filename matches, then use Upload
Videos after confirming all clips are present and setting HOLD for incomplete
routines in the web Competition Videos page. Files changed in the last two minutes
are skipped. The app verifies each upload and resumes an interrupted open batch.
Unmatched filenames upload privately and must be assigned in the web view before
the batch can complete. Direct uploads are currently limited to 5 GiB per file.


## Edited concert video uploads

Open a prepared concert and choose **Upload Edited Concert Videos…**. Admin/staff
or the assigned concert editor can select the folder of final MP4 exports. Include
opening videos and studio slideshows, either as separate exports or within the
concert edit. Files must have stopped changing for two minutes; filenames must
be unique within the queue. This path accepts up to 500 files, each up to 5 GiB.
Larger individual exports need the multipart upload UI (not implemented here).

**Upload / Resume** hashes files, registers the entire queue, transfers missing
files to private storage and verifies size/checksum. Queues persist across app
restarts. After all files verify, **Finished Uploading** explicitly records the
uploader and time. New/changed exports require uploading the current queue first.
The tracker shows verified-file percentage, not partial-byte transfer percentage.

Tracker 3.4 separately checks editing, all quality checks and approved studio file
inclusions before showing Ready for studio. Uploading never sends an email or
publishes media. Playback uses the existing progressive MP4 fallback; this upload
path does not generate streaming renditions.

## Concert photos: black-frame import

Open a prepared concert and choose **Import Photos…** above the photo folders.
Select a source folder or SD card containing JPEGs from one camera. The app scans
locally in natural folder/filename order and suggests a new group after black
separator frames. It does not use cloud AI or send photos during scanning.

Check each group's first/last photo and programme destination. **Review photos**
lets you split before a photo, exclude a separator, or rename a destination file.
Groups can be combined with the previous group; the destination picker lets you
move a group to another item. Expand excluded photos to restore a dark stage photo
that was incorrectly detected. Extra groups need an explicit destination.

Confirm the review, then **Import photos** copies into the prepared event. Source
files stay untouched; excluded frames are not copied. Existing files are never
overwritten. Unreadable files are listed, and changed source files are rejected.
If copying stops part-way, the dialog retains only the remaining photos for retry.
Imported photos enter the normal local review workflow; upload remains a separate
step. Uploaded items are locked against this import operation. RAW files are not
supported. Detection should be checked against real camera samples before large
imports; dark stage images can also be nearly black.

Approved event cards now show the studio and concert title separately. Search
updates after a short pause in typing and matches partial studio/event names.


After a successful import, a green **Import complete** panel shows the copied count;
**Done** returns to photo review. Local import does not upload or release photos.
Confirm the correct photo role and click Upload. Completion is recorded automatically after verification.

Newly generated concert folder titles use unaccented letters. To clean a previously
prepared, unuploaded concert, reopen it in the updated app and use **Edit Items →
Save Changes**. This safely renames its item folders while preserving original
programme/video-title spelling and photo identities. Older saved paths remain
readable until that explicit save; do not rename prepared folders manually.


## Choose the concert role before Prepare

Selecting a concert loads its configured roles. **Prepare Event…** is disabled
until a role is chosen. P1/P2 save the programme and create photo folders; V saves
the programme and the verified ready **Video Titles.zip**, with no photo folders.
Video preparation uses a separate ` - Video` directory to preserve existing photo
preparations. The source PDF is included when attached. Video titles must first
be marked ready in tracker 2.3; missing/stale titles explain what needs fixing and
no preparation is written. Open the ZIP locally to extract the title JPEGs.

The chosen role is retained for the immediate prepared-event opening. Reopening
an existing concert still requires role confirmation. Video preparations open a
programme/title view with the edited-video uploader button, rather than photo
review. Existing photo preparations remain compatible and are not recreated.
Competition preparation is unchanged.


If you choose an already prepared event folder in **Prepare Event…**, the app now
reopens that matching event instead of making another event folder inside it.
A different event/revision/media type is rejected with instructions to use a parent
folder. Existing photos and saved review files are preserved. **Open Existing
Event Folder…** also remembers the location for later launches. Prepared event
cards show their saved path so similarly named locations can be distinguished.


## Sorting concert photos outside the uploader

After preparing as P1/P2, photographers can use Finder or editing software to
copy/export JPEGs directly into each numbered item folder. Keep the prepared
folder names and programme/manifest files unchanged. Reopen the existing event
(or leave photo review open); the watcher discovers JPEGs after copying settles.
No Import Photos or individual Ready step is required. Import Photos is an
optional black-frame grouping/copy tool, not a prerequisite for externally sorted
photos. Click Upload after sorting; the tracker updates automatically after transfer succeeds.


## Dress rehearsal portraits

Approved DR bookings with portrait photography appear without a programme PDF.
Select the session and your photographer role, then Prepare Event. Use
**Import Photos…** to choose a source folder, review the photos and copy them into
the single **Dress Rehearsal Portraits** folder. DR import includes dark photos
and does not split by black frames. Originals stay untouched. Alternatively,
use Open Folders and copy/paste JPEGs into that folder yourself. Click Upload. The role completes automatically after verification. No individual Ready,
programme confirmation, title download or separate finish button is needed.

The customer gallery is **Studio → Dress Rehearsal Portraits → images**, with no
extra parent group or item folder to open. Photos stay private until release.
