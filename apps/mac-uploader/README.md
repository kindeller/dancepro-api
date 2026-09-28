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

**Ready** queues current stable photos in a concert item or competition section.
New or changed files need another Ready action. Click the queue count to inspect
or remove entries; the queue survives reopening and offline work.

The role beneath the event name identifies this Mac's media. Concerts automatically
select your published event role when unambiguous; choose another configured role to
upload on a colleague's behalf using your own account. Competition laptops are
persistent P1/P2/V equipment stations. Crew handovers do not change the station role.
Select a different role explicitly for equipment swaps; upload or clear the queue
first, then mark the new role's photos Ready. Previously uploaded photos retain
their original role. V station selection is saved, but video transfer is not yet connected.

**Upload** automatically syncs the programme, checks the event role,
prepares file checksums and sends the Ready queue. There is no separate Sync
Programme button. **Pause** cancels the active request; **Resume** restarts it safely.
An active session retries temporary connection failures and includes additional
items marked Ready. Reopening the app never starts an upload automatically.

Only a matching verified server receipt marks a photo Uploaded and removes it from
the queue. Uploaded folders cannot be culled or rearranged; later new photos still
need Ready. Top Pics can still be changed locally. Gallery publication remains a separate future step; role completion is
an explicit action described below.

This build transfers to **private storage on the local development server**. It
verifies exact byte count, SHA-256 and JPEG decoding and never publishes a gallery.
Conflicting working programmes on different Macs stop with an error; existing
photos are preserved. Administrative reconciliation, production/cloud storage,
customer release and later Top Pic synchronization remain pending. Production
transport is disabled by default. Server JPEG pixel/memory and HTTP request limits
apply; see ADR-0020.

To combine concert items, open **Edit Items → Combine Item…**, select two or more
rows, then Combine. Titles join in programme order with spaces. Review the
renumbered draft, then Save Changes; Cancel discards edits.

The supplied icon is stored in Assets/Uploader.png. build-app.sh includes standard
ICNS sizes in the signed app bundle.

After this role's uploads are verified, choose **Finish Uploading…** and
confirm **Finished Uploading Event**. This covers the selected event role's
work across Macs, including empty items, in one confirmation. Remaining local
photos or unverified server reservations block completion. The saved **Finish
recorded** indicator is the last declaration; new photos need Ready, Upload and
Finish again. Repeat Finish to recheck server state. This never publishes a gallery
or completes another photographer's work.

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
