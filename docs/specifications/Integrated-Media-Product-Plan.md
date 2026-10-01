# DancePro integrated media system — build handover

## Subsequent clarification — 21 September 2026

The owner clarified that the older concert approval system is no longer in use
and may not be present in this version. The required baseline is an Approve
button on the video page, with a checklist emailed by David. Locating the old
source is no longer a prerequisite. The richer Director Hub/checklist/correction
features below remain future scope, not a claim about legacy functionality.

Local Docker runs in Colima. Use Laravel Sail against that Docker context.

Event-code follow-up: retain the current calculated codes for the next slice.
The owner considers event date changes unlikely and normally within the same
month. Proposed stored-code changes are deferred, not an implementation
prerequisite. Stable internal media/routine identities remain required.

Programme sample clarification: when no separate routine/song title is supplied,
use the printed label as the initial album title and allow renaming. This is an
exception to the earlier no-class-names rule. Keep separate title and class fields
where both are supplied. See [programme preparation](Programme-Preparation.md)
for the inspected examples and proposed workflow.



The original handover below is retained as the planning record; this clarification
supersedes its requirement to locate the older approval implementation.

## 1. Objective and working approach

Build DancePro’s integrated media management, uploading, customer delivery and studio director experience.

This extends existing DancePro projects:

- Crew and event management.
- Competition photo capture.
- Competition video recorder.
- Concert video delivery and approval.
- The partially started media management system.

The goal is one connected system, while keeping capture, uploading and customer delivery sufficiently separate for reliability.

**Until this handover, the work has been planning and mockups only. Do not assume the proposed features already exist.**

Start by inspecting the existing projects and confirming what is implemented. Preserve useful existing functionality, especially the older concert video delivery and approval workflow, which is not fully represented in the new portal.

Work in manageable stages. Resolve remaining questions when they affect the stage being built rather than restarting discovery or asking every question upfront.

### Existing management project

`/Users/davidmueller/Downloads/dancepro-api-1-migration`

This is a Laravel rebuild and is not yet live. Read its project instructions before making changes.

Previous read-only inspection found foundations for:

- Scheduling events, studios, contacts and crew assignments.
- Concert bookings and programme attachments.
- Concert media collections and assets.
- Concert playback and access controls.
- Tracked download links.
- Early customer/order models.

These foundations do **not** establish that the new purchasing, uploader, permissions or release workflows are complete.

The older live concert delivery system must be inspected separately before replacing its functionality.

### Important implementation principles

- Reuse existing event, studio, contact and crew information.
- Avoid duplicate data entry.
- Use stable internal identifiers. Display names, filenames and folder numbering can change without changing ownership or purchase entitlements.
- Keep originals private.
- Separate uploading, public visibility, purchasing eligibility and delivery eligibility.
- Record important administrative actions and who performed them.
- Keep busy-event crew workflows simple.
- Customer-facing screens should avoid internal technical details.
- Build the customer website and phone app together; they influence each other.
- Existing ShootProof galleries remain on ShootProof. **No historical migration is planned.**

---

## 2. Overall products and responsibilities

### Crew and event management

The source of:

- Studios and competition organisers.
- Events, dates, venues and event codes.
- Bookings and services.
- Crew assignments and roles.
- Programmes.
- Studio contacts.
- Eventually crew costs.

### Capture apps

Keep capture focused and reliable.

- Competition photo capture writes into prepared folders.
- Competition video recording creates files using event, section and competitor identifiers.
- Do not overload capture apps with the full review, upload and commerce workflow.

### Mac uploader

Owner clarification (2026-09-21): no uploader exists to reuse. Build a new
standalone Mac app; preparation and programme downloads happen inside it, with
a remembered API sign-in rather than a separate browser login per computer.

A separate **Mac-only** app for:

- Preparing events and folders.
- Offline photo review and culling.
- Top Pics.
- Concert photo sorting.
- Uploading photos or videos.
- Video HOLD management.
- Upload reconciliation.
- Exporting photos for the event kiosk slideshow.

Photo and video uploading normally happen on separate crew laptops. The selected role determines the uploader’s screen mode.

### Management portal

The existing portal window gains Media Management and a separate Tracker.

Proposed Media Management areas:

- Overview.
- Competition Galleries.
- Competition Videos.
- Studio Galleries.
- Concert Videos.
- Orders and Customers.
- Reports.
- Settings.

Competition videos use the existing **Competition Media** area and its established
`year / event code / filename` storage structure. Event-level video matching and
HOLD controls share programme and routine records with Competition Galleries; they
do not require a duplicate top-level menu or a second video store.

### Customer website and phone app

Customers can:

- Access events and studios.
- Browse and purchase media.
- Access orders, invoices and purchased media.
- Save media locally.
- Manage individual or family access.

### Director Hub

Extra features within the same customer website and app for authorised studio directors.

### Tracker

A separate top-level overview across modules, showing what is complete, outstanding, waiting or overdue, with direct links to the relevant work.

---

## 3. Core structure and relationships

Design the system to distinguish:

- Studio.
- Studio contact/director.
- Booking.
- Individual event or concert performance.
- Selected concert group.
- Gallery and nested albums.
- Programme section and routine.
- Photo asset.
- Video asset, including multiple clips for one routine.
- Crew role/contribution.
- Order and purchased entitlement.
- Concert video access-code group.
- Manual and automatically tracked tasks.

These groupings are not interchangeable.

Examples:

- One studio can have junior and senior concerts months apart.
- One booking can contain several events.
- A photo release can cover selected events.
- A video access code can cover several concerts.
- One final invoice can cover the whole booking.
- Earlier concerts may be approved before the final concert.
- A competition routine can have multiple video files, all included in the purchase.

Do not force every workflow to use one universal grouping.

---

## 4. Programme preparation

Programmes are usually PDFs and are available before the event.

### Intended workflow

1. Admin uploads the programme into Crew Management.
2. The system extracts and proposes the event structure.
3. Admin checks and confirms the structure.
4. The confirmed structure becomes the shared source for capture, uploader, galleries and video titles.
5. Before travelling, crew select the event in the uploader and choose **Prepare Event**.
6. The uploader downloads the required event information and creates the local folders.
7. The event is ready for offline use.

Programme interpretation may use automated extraction or AI where useful, but:

- Event-day preparation must not depend on internet access.
- Crew should not need personal AI accounts.
- Programme output must be reviewable before it controls folders or media routing.
- The final extraction approach should be chosen after inspecting the supplied programme examples.

### Competition numbering

- Existing section and competitor numbers remain authoritative.
- Withdrawals leave gaps; other competitors are not renumbered.
- An added competitor can use their competitor number with a generic “Competitor” label.
- Customers must still be able to browse the whole event when personal matching misses something.
- Competition structure is managed before the event, not freely rearranged in the uploader.

### Concert structure

Concert programmes do not always match the stage performance.

Before uploading, crew can:

- Rename items.
- Add unlisted items, speeches or presentations.
- Reorder by dragging.
- Combine items performed together. Owner clarification: use Edit Items → Combine Item, select multiple items, join titles in programme order with a space between them, then renumber. Review the draft before Save Changes.
- Split items where required.

Concert folders are then renumbered into the correct numeric order. Owner
clarification: show this continuous sequence during programme review, with two
digits minimum (01, 02, … 99, 100), and include it in the approved programme.
Keep printed programme numbers separately; breaks do not create media folders.

These operations must preserve stable internal identities and must not accidentally reassign already uploaded or purchased media.

### Customer programmes

- Customers can view competition programmes to find routines.
- Concert programmes remain separately downloadable. Owner clarification (28 September 2026): concert videos use individual downloads and the existing multiple-file download manager, not a concert ZIP.
- Customer programme files do not contain internal notes.

---

## 5. Mac uploader: event selection and offline operation

### Event selection

Crew select the specific event once.

That selection determines:

- Programme and local folders.
- Destination gallery.
- Upload routing.
- Relevant roles and expected contributions.

The uploader must support multiple events happening at the same time without mixing them.

### Role selection

Roles come from Crew Management, for example:

- Photographer 1.
- Photographer 2.
- Videographer.

Role identification is needed for contribution tracking and the appropriate screen mode.
Owner update, 29 September 2026: concerts require explicit role selection before
Prepare. P1/P2 prepare programme/photo folders; V prepares programme/video titles.
This supersedes the earlier optional-role preparation rule for concerts.

Remember the selected role where appropriate.

### Offline operation

After preparation at home, the uploader must work completely offline for:

- Viewing programme/folder information.
- Reviewing photos.
- Culling.
- Top Pics.
- Moving photos.
- Marking sections ready.
- Queuing uploads.
- Video HOLD changes.
- Kiosk USB export.

Local state must survive quitting the app and restarting the laptop.

Offline changes must synchronise safely when connectivity returns.

### Uploader identity and event roles — agreed 22 September 2026

The event role identifies the media; the signed-in account identifies who performed
the upload. Keep those identities separate from the roster's assigned crew member.

- **Concerts (owner update, 29 September 2026):** require an explicit role before
  Prepare and confirmation each time an existing concert is opened. The choice
  made for preparation also confirms that immediate opening. P1/P2 create photo
  folders; V downloads ready video titles into a separate video preparation folder.
  Both save programme data and the source PDF when supplied. Never require choosing
  a colleague's name or signing in with their account. Existing photos are retained.
- **Competitions:** set the prepared laptop's event role once, as P1, P2 or V. It is
  an equipment station that stays at the event, not a laptop assigned to a crew member.
  Persist the role across app restarts and offline work. Crew handovers do not require
  account or role changes and do not reset upload progress or completion.
- Record the media's event role, the actual uploading account, and the roster
  assignment separately. Neither the login nor the person uploading later proves
  who captured every file. Completion actions also retain the acting account.
- Track progress and **Upload Complete** by event + role, with item/section detail
  beneath that role. Crew identity is not part of contribution identity. Replacing
  roster personnel does not by itself reopen a completed role; new media still does.
- Role changes are explicit, including equipment swaps and emergency replacements.
  Previously uploaded media keeps its original role. A replacement laptop selecting
  the same event role continues that role's existing work. Cross-laptop duplicate
  detection must prevent copied originals from being delivered twice.
- Choosing a role does not grant additional access. Keep event access checks;
  authorisation must support the agreed colleague-upload and event-station workflows.

This direction is now implemented in the local uploader foundation. Existing
crew-bound contributions have a preserving migration to event-role identity. V
station selection is available; video transport is still pending. See ADR-0022.

### Bottom upload bar

- Clicking **Ready** on a section queues it, including offline.
- When offline, show the queued work and an unavailable Upload action with a clear offline status.
- Once online, the user can press **Upload**. Owner clarification: Upload automatically syncs the programme, validates the photographer and prepares the queue. This is now connected in the local development build, and the temporary Sync Programme button has been removed.
- Do not start the first upload merely because the laptop reconnects.
- During uploading, show Pause.
- After a manual pause, show Resume.
- An active upload session can recover from a temporary connection loss.
- Additional sections marked ready during an active session join the queue.

Do not add unnecessary disk-space or drive-availability checklists. Show actual actionable errors when they occur.

---

## 6. Photo review

### Layout

- Folder tree on the left.
- Day, section and competitor organisation for competitions.
- Status beside folders.
- **Ready button beside each section title.**
- **Top Pics** appears as a pinned folder above Day 1.
- Large photo preview uses most of the remaining screen.
- Clearly show the local folder’s image count to crew/admin.

### Controls

- Up/down arrows move through photos.
- Delete sends the selected photo to macOS Trash in competitor/item folders.
- Top Pics has no bin or Delete shortcut. Unstar to remove a selection; the original stays in its competitor/item folder.
- Undo should restore supported recent actions.
- Return toggles Top Pic.
- Bin and star icons remain available for clicking in competitor/item folders; Top Pics shows only the star.
- Pinch to zoom around the mouse pointer.
- Moving to another image resets zoom to fit.

No RAW support, cropping or rotation is required. Photos are JPEG.

### Review is optional

Photos can be uploaded without being reviewed.

Do not require a “review complete” step before uploading.

### Working during capture

A photographer may review competitor 1 while competitor 2 in the same section is still being captured.

The uploader must avoid reading incomplete files. This should be automatic and unobtrusive.

### Statuses

Use simple states:

- Shooting.
- Review.
- Ready.
- Queued.
- Uploading.
- Complete.

Avoid unnecessary extra indicators unless they solve a real problem.

### Moving photos

A **Move Photos** button opens a thumbnail grid for the current folder.

Crew select photos and drag them into the correct folder in the left sidebar.

Maintain Top Pic selections and file identity when moving files.

### After uploading

**Uploaded albums cannot be culled through the uploader.**

- Administrative corrections remain possible in the management portal.
- Top Pics can still be selected after upload.
- New photos arriving after a section was uploaded must be clearly identified.
- Do not silently treat later files as reviewed or deliberately approved.

---

## 7. Two-photographer completion and release

Expected photo roles and staffing requirements come from Crew Management.
Completion belongs to the event role, independently of changes to its assigned crew.

Normally both photographers cover all competition sections. Exceptions can occur because of equipment failure, illness or emergencies.

The system must distinguish:

- Files received from P1.
- Files received from P2.
- A contribution explicitly marked complete.
- An expected contribution missing or waived by admin.

**Do not release a section’s photos merely because one photographer has finished uploading.**

The normal release condition is that all required photo contributions for that section are complete and verified.

An admin must be able to resolve legitimate exceptions.

Empty folders should not create repetitive crew confirmation steps.

A role-level **Upload Complete** action confirms completion once its uploads are verified. It must not release concert galleries or override video HOLDs.

If additional files are later added, the system must make the renewed work visible.

---

## 8. Concert photo import and sorting

Concert photographers do not use the competition capture app.

They can:

- Prepare folders using the programme.
- Copy photographs from the SD card into folders.
- Use the uploader to review and upload.

Owner update (30 September 2026): concert photo Upload automatically records the
selected role as complete after verification, without a separate finish prompt.
Later uploads reopen that role and complete again after verification. Tracker 3.1
still requires every configured photo role. Competitions retain explicit finish;
gallery release remains separate.

### Black-frame sorting

Use **ordinary local image analysis**, not cloud AI, to detect black separator photos.

Requirements:

- Works offline.
- No AI subscription/account for crew.
- Proposes photo groups and destination folders.
- Shows first and last photo for each proposed group.
- Allows crew to approve or change the grouping.
- Allows a group to be split where a separator was missed.
- Allows groups to be combined or moved.
- Keeps source-card files safe during import.
- Separators are not uploaded as customer photos.

Camera filenames are already configured to differ. Do not introduce an elaborate duplicate-management workflow.

However, never overwrite a different file because its name collides. Ask for a rename where necessary.

---

## 9. Top Pics and kiosk export

Top Pics are private backend selections used for:

- Marketing.
- Album covers.
- Kiosk slideshow preparation.

They do not automatically become a public marketing collection.

Requirements:

- Select Top Pics during review or after uploading.
- Upload additional concert Top Pics for backend access.
- Choose parent concert album covers from Top Pics.
- Routine album covers can default to an image from that album.
- Allow manual cover changes.

### Kiosk USB export

Export full-resolution JPEGs.

- Provide a default export setup and customisation.
- Support copying new selections without unnecessarily recopying everything.
- Optional DancePro roundel in the bottom-right corner.
- Optional text across the bottom.
- Preview the result.
- Do not alter the original photo.

Retrieving another photographer’s Top Pics should be possible, but must not take priority over uploading media for sale.

---

## 10. Competition video uploading

### File organisation

There are no required section folders for video.

The recorder uses filenames such as:

- `EDC926_01_01.mp4`
- `EDC926_01_02.mp4`
- `EDC926_01_02a.mp4`

The filename contains event, section and competitor identifiers.

Multiple suffixes such as `a`, `b`, `c`, `d` are legitimate. Customers receive all valid included videos for that routine.

### Uploader view

Show a file list grouped under programme section headings:

**01 Jazz Solos**

- `EDC926_01_01.mp4`
- `EDC926_01_02.mp4`

**02 Lyrical Solos**

- `EDC926_02_01.mp4`
- `EDC926_02_02.mp4`

The source location is configured in settings rather than selected for every upload.

### Upload trigger

Use a manual **Upload** button.

When pressed:

1. Scan the configured source.
2. Exclude unfinished recordings.
3. Identify files already uploaded and verified.
4. Queue the remaining eligible files.
5. Upload and verify them.

This allows the videographer to rename, move or HOLD files before starting.

Do not identify uploaded files solely by filename; Finder renaming must not cause accidental duplicate delivery or incorrect reassignment.

### HOLD

- A video/routine can be marked **HOLD**.
- No reason is required.
- Keep an action log.
- Allow **Unhold**.
- HOLD means the affected video delivery is blocked.
- Files can be uploaded privately while held.
- Purchases may still be accepted where applicable.
- Admin can apply HOLD after upload.
- Previously downloaded copies cannot be recalled.

A failed live recording may require backup-card conversion and editing several days later.

Do not require the repaired video to exist before recording the problem.

Finalise the exact file-versus-routine HOLD interaction during implementation so one valid clip does not accidentally release an incomplete set.

### Delivery

There is no separate normal manual release step for competition video once the applicable upload batch is complete and verified, unless it is held or unresolved.

Avoid releasing the first clip while additional clips for the same uploaded routine are still being processed.

There are no customer competition video previews.

### Unmatched files

Allow an unrecognised file to be uploaded into a private **Needs assignment** area, with a clear status.

It must not be lost or publicly attached to a guessed routine.

---

## 11. Upload reliability and reconciliation

Provide:

- Persistent upload queue.
- Resume after interruption or restart.
- Verification that files reached storage correctly.
- Retry failed files without repeating successful uploads.
- Clear local/server status.
- Safe cancellation.
- No deletion of local originals as an automatic upload side effect.

A **Check / Reconcile** action must compare the expected local media with the server.

It should identify:

- Missing server files.
- Failed or incomplete transfers.
- Unassigned files.
- Additional local files.
- Differences needing admin attention.

Allow missing files to upload directly into the correct existing destination.

Exclude rejected photos, separator frames and intentionally excluded media from “missing file” results.

---

## 12. Competition galleries and purchasing

### Structure

Competition event → day → section → competitor/routine album.

One competitor performing one routine is the purchasing unit.

Improvisation is treated like other routines but may lack useful competitor or studio names.

Customers need:

- My Dancers.
- My Studios’ Routines.
- Improvisation.
- Whole Event access.

Personal filtering must never prevent access to the rest of an event they are authorised to view.

### Access

Customers add a competition using its event code.

Usually this follows a format such as `EDC926`, but exceptions exist. Use the stored event profile rather than assuming the code can always be calculated.

Studio-based competition filtering requires both:

- Membership of that studio.
- Registration for that competition event.

### Prices, including GST

Competition and concert products use separate reusable price lists. One active
price list of each type is the automatic default. A specific competition event,
concert gallery, competition routine or media album may override that default.
The most specific active assignment wins: album or routine, then gallery or
event, then the matching automatic default. Paid orders keep their captured
line prices when a price list later changes.

Prices shown to customers include GST. Every price list has an editable GST
rate, defaulting to 10%, and a GST checkbox for each product. Checkout calculates
the included GST on checked products after discount allocation. Draft and paid
orders retain the resolved item rate, item GST amount and order GST total even
if the price list changes later.

| Product | Price |
|---|---:|
| Individual photo | $10 |
| All photos from one routine | $40 |
| All video clips from one routine | $18 |
| Routine package: all photos and videos | $45 |

### Bulk discounts

Calculated on the eligible cart total for that competition:

| Total | Discount |
|---|---:|
| $100 or more | 5% |
| $150 or more | 10% |
| $200 or more | 15% |
| $250 or more | 20% |

Exactly reaching a threshold qualifies.

Apply a discount code before calculating bulk eligibility. The code's applied
amount reduces the eligible cart total used for the threshold and bulk-discount
calculation. For example, a $120 eligible cart with a $40 code has an $80 bulk-
eligible total and receives no bulk discount.

One competition event per cart.

### Purchasing behaviour

- Favourites for individual photos and routine albums/packages.
- Suggest all-photos or package savings when several photos from one routine are selected.
- Suggest additional purchases when close to a bulk-discount threshold.
- Prevent duplicate/overlapping purchases within the same order.
- Show relevant already-owned media.
- A previous video purchase can be upgraded to the package for **$27**, as a normal eligible cart item.
- Do not retrospectively recalculate discounts on previous orders.
- No general photo-to-package upgrade credit has been agreed.

Standalone video sales close 12 months after the event.

Photos and packages can remain available indefinitely.

### Visibility and delivery

Photos become visible after the required section photo uploads are complete.

A package can be purchased while video is still pending.

App customers may receive available media earlier, while the combined ready email/download experience should avoid confusing partial delivery. The precise interaction with whole-order downloads must be resolved before implementing fulfilment.

Do not start a customer’s download entitlement while their required media is still unavailable.

---

## 13. Concert photo galleries

Owner update (30 September 2026): dress rehearsal portraits use
**Studio → Dress Rehearsal Portraits → images** with no extra concert/group/item
navigation. Approval automatically creates a single portrait upload preparation;
no programme is required. Existing verified-transfer, per-role completion and
explicit gallery release rules apply. Older grouping examples below continue to
apply to concert performance albums.


Studio gallery structure is flexible.

Examples:

**Studio → 2026 Concert**

- 00 DR Portraits.
- 01 Dance.
- 02 Dance.

**Studio → 2026 Senior Concerts**

- 00 DR Portraits.
- 01 Senior Concert Friday.
- 02 Senior Concert Saturday.

Requirements:

- Numeric ordering.
- No class names in photo album titles.
- Dress rehearsal photos may sit inside the relevant concert grouping.
- Selected galleries can be released together.
- Junior and senior releases can be separate even within one studio/year.
- Release commonly occurs after the relevant final event, but admin chooses the actual group.

### Purchases

- $10 per photo, GST-inclusive.
- Same bulk-discount thresholds as competitions.
- No all-album photo product.
- No customer concert video purchase through the photo cart.
- One studio cart can contain photos from multiple concerts and years.

Customer-facing photo counts should be hidden. Admin counts must remain available for consistency checking.

Album-specific portfolio/photo-shoot pricing is a later feature.

---

## 14. Watermarks and previews

Provide a watermark library:

- Create/configure a watermark.
- Upload existing artwork.
- Preview it on photos.
- Assign defaults by event/gallery type.
- Override the watermark in gallery settings.

Types include:

- Competition.
- Concert.
- Proof.

The existing square competition watermark fits to the **narrowest side** of portrait or landscape photos.

Customer previews must remain excellent quality.

Purchased originals are unwatermarked.

Protect previews without claiming screenshots can be made impossible. Do not rely solely on a removable visual overlay as protection.

Choose preview dimensions, processing and caching after quality and cost testing.

---

## 15. Concert video access and approval

Preserve and extend the existing concert delivery workflow.

### Studio approval

The studio director can:

- Watch the approval version.
- Use the checklist.
- Identify missing routines or corrections.
- Approve the concert.
- Release it.

The studio distributes the customer code and link because DancePro does not know which families should receive access.

DancePro must not automatically distribute concert access to every studio member.

### Customer access

A global **Add Concert Video** action accepts the concert code.

The code grants access to all events/videos assigned to it.

Codes may cover multiple concerts from the same studio/year.

Codes are usually less guessable than competition event codes.

### Storage and downloads (owner clarification, 28 September 2026)

Retain each existing video's recorded storage disk and complete object key. Do not
move or rename live originals. Legacy originals use short-lived signed delivery
from their configured disk; new uploads keep their existing configured destination.
The documented legacy bucket is `dance-pro-videos`, but the live catalogue and
configuration must be verified before connecting production data.

Keep individual video downloads and the browser's multiple-file download manager
with Start, Pause/Resume and Reset. The manager remains available during the
12-month download period after streaming ends. It reports requests sent to the
browser, not completed transfers; the browser may require multiple-download
permission. Pause and Reset do not cancel files already transferring. Do not add
a concert ZIP workflow. This does not change programme/title packages or purchased
photo archive workflows.

### Access periods

- Streaming: six months.
- Downloads: 12 months.
- Downloaded/offline copies remain usable indefinitely.
- Customers are encouraged to download and keep the videos.

Adding a code later must not automatically restart the original access periods.

### Reactivation

- $18, GST-inclusive.
- Download-only access for 60 days.
- Applies only to the paying customer.
- Covers the relevant concerts attached to that code.
- Separate checkout from normal media purchases.
- No normal bulk discount.
- Can apply to an already-added expired concert or when entering an expired code.

If restoration from cheaper storage is required, explain the expected wait and start usable access when the media is available.

### Expiry reminders

Send a reminder ten days before download expiry only where the customer has not started downloading.

Tracking a genuine download start is sufficient; exhaustive confirmation of completion is not required.

No separate streaming-expiry reminder is required.

---

## 16. Concert title cards

Generate landscape **3840 × 2160 4K UHD JPEGs** for insertion into edited videos. The high-resolution source can be used directly in 4K projects and downscales cleanly for 1080p delivery.

The default template uses Atlanta on a black, customisable background. A full-width customisable band contains the centred studio logo at the largest size that fits, using the full band height where the logo's proportions allow it, with gradient separator lines above and below. Beneath the band, the concert title is italic, the programme item title is large and bold, and the class name appears when supplied. The approved programme supplies this text and its punctuation remains unchanged.

The concert title comes from the individual concert booking item. The studio name remains separate and is not repeated in the title text because it is already represented by the studio logo. Older manually created events fall back to their event name when no linked booking item exists.

Administrators can preview programme items, choose the event or studio logo, adjust the background, band, text and separator colours, save the event template, and download the selected card. Automated batch rendering requires the licensed Atlanta font file on the production renderer.

Provide defaults and customisation, with selectable elements such as:

- Concert-specific studio logo.
- Routine title.
- Class name where supplied.
- Other agreed title elements.

The event logo may differ from the studio’s usual logo.

If class names are missing, omit them.

Dress rehearsals have no videos and need no title cards.

Repeated concerts can reuse matching information, but their programme items may differ; do not assume an identical running order.

---

## 17. Studio concert video pricing and invoicing

These are charges to the studio, separate from competition customer video purchases.

### Standard studios

All prices include GST.

Total video fee is the greater of:

- **$1,500 × number of concerts filmed**, or
- **$15 × total unique families across those concerts**.

Families performing in multiple concerts are counted once.

The advertised $15–$25 is an effective per-family guide, not pricing tiers.

### High schools

- One concert.
- No family-number collection.
- $1,500 flat rate less $300 discount.
- **$1,200 total**, including GST.
- No per-student calculation.

### Estimated and final numbers

The booking form collects an estimated family total.

Public booking form refinements (27 September 2026): photography and videography
are both selected initially. Adding a DR Portrait event activates portrait
requirements and the portrait service; there is no separate portrait-interest
question. The managed event-type selector is hidden on the public form and
retained in the backend. Event title is optional. Postal address is in Studio
details for every service selection (required for video bookings). Optional
feedback on previous media/processes and requests for this year belongs in
Booking requirements.

The post-submission page shows a personalised timeline and a read-only original
submission. New bookings store a snapshot of submitted details, requirements,
quote and initial timeline so later staff edits do not rewrite the submission.
The public guest receipt is restricted to the submitting browser session. Studio
Hub has an **Event timeline** button and matching page heading, listing saved
submissions with timeline and original-form links. Studio Hub bookings store a
server-authorised studio association and remain accessible to current authorised
studio managers across sessions, including while awaiting approval. Legacy saved
submissions use real event/studio links; names/emails alone never grant access.
All receipt responses are private/no-store. The guest receipt and original-form
view retain the initial snapshot. The authenticated Event timeline recalculates
current estimates and adds shared tracker production status; actual finance and
release follow-up milestones are still pending implementation.

Owner-confirmed calculations (27 September 2026), all AUD inclusive of GST:

- Video estimate: greater of $1,500 per booked concert or $15 per estimated
  performing family across the booking, continuing the same rule for 3+ concerts.
- Count each family once, including performing siblings and parents across one
  or more concerts. DR Portrait rows do not count as video concerts.
- Crew setup/arrival: one hour before photo-only concerts and DR Portraits;
  1.5–2 hours before concerts with videography.
- Estimated photo release: 48 hours after the final booked event ends.
- Estimated video ready for approval: two weeks after the final event ends.
- Programme, supplied studio-media and video-invoice dates reuse the tracker
  catalogue offsets. Seats/technical arrangements and final-number deadlines
  are shown as to be confirmed because no authoritative receipt deadlines are
  currently configured. Submission does not create an invoice or charge.

In the Tracker:

- Show only the number.
- Grey means estimate.
- Normal text means final.
- Hover can explain its source/status.
- Retain the estimate in history.
- An estimate does not complete the Final Numbers task.
- High schools show not applicable.

### Deposits

Optional **Deposit required?** checkbox, off by default.

When enabled, create the relevant:

- Amount.
- Due date.
- Invoice/payment steps.
- Tracker tasks.

### Final invoice and payment

- One final invoice for the whole booking.
- Do not automatically split invoices for concerts far apart.
- Prepare/send as soon as the necessary final information is available.
- Prepare/send seven days before the final event, once final family numbers are confirmed. Payment is due fourteen days after the first successful invoice email (owner update, 27 September 2026).
- Earlier concerts may be sent for approval before payment.
- The final concert’s approval access is blocked until payment.

Keep editing, uploading, invoicing, payment and approval as separate statuses.

The accounting integration and payment-reconciliation mechanism still need to be selected.

### Photography minimum spend

Owner-confirmed policy (27 September 2026), replacing earlier legacy figures:

- $800 AUD including GST for the first photography event in the booking.
- $600 AUD including GST for each subsequent photography event.
- Count both DR Portrait sessions and concerts booked for photography.
- Examples: one concert $800; two concerts $1,400; one DR Portrait and one
  concert $1,400; one DR Portrait and two concerts $2,000.
- If sales do not meet the minimum 30 days after the actual gallery release,
  contact the studio and explore ways to resolve the shortfall.
- Only after all options are exhausted, invoice the difference between actual
  spend and the minimum spend.

The submission summary calculates the minimum and an indicative review date;
it does not automatically invoice a shortfall. The actual release date controls
that later review, not the estimated release shown at booking.

---

## 18. Customer accounts, family access and guests

### Profiles

Use **My Profile**, not an assumption that every customer has a family account.

Anyone can have an account, including dancers.

Customers can:

- Add multiple studios using studio gallery codes.
- Add competition events.
- Add dancers to improve routine discovery.
- Maintain contact and notification preferences.
- Access orders, invoices and media.

### Family sharing

Concert-video family invitations are implemented locally (28 September 2026):
three distinct recipients per account/concert, selected released videos, verified
recipient email without a full account, fourteen days to accept and thirty days
from acceptance, bounded by the original concert entitlement. Each recipient may
save one intended copy per video, with at most three download requests for retries.
Renewals retain counts. Customer renew/revoke and approximate activity, studio/admin
sharing/download controls, and contact-data retention are included. Existing video
storage is unchanged. See [the decision](../decisions/Concert-Family-Sharing.md).
This does not implement the broader family cart/payment permissions below.


- Shared access to family purchases.
- Dancers can add items to a cart for a parent to pay.
- Parents can allow payment permissions.
- Purchases remain linked to the owning account and cannot be transferred.
- Removing family access does not erase copies already downloaded.

### Guest access

Guest purchasing is supported for competition and concert photos to avoid unnecessary checkout friction.

Concert video access should also support a guest route.

Use secure verified email access and a way to claim purchases into an account later.

Do not let guest access reset expired entitlements or bypass concert codes.

Provide one public media landing page with **Competitions** and **Concerts** tabs.
Released event cards are publicly discoverable, but opening protected media still
requires an email address and the correct event or studio gallery login code.
Guest favourites and cart contents must survive account creation or sign-in at
checkout. If the email already belongs to an account, authenticate and merge the
guest activity instead of creating a duplicate account.

An uncancelled event may appear in the public gallery from seven days before its
scheduled start, even when its media has not been released. Show a **Coming soon —
register now** banner and collect an email address for that event's release notice.
Store one active notification request per normalised email and event, record the
customer's consent and provide an unsubscribe path. Registration must not grant
gallery access or reveal protected previews.

Send one release email when the event first has customer-visible media. For a
competition with rolling section availability, this means the first section that
becomes available; later sections do not send repeated event-release emails. For a
concert, it means the relevant administrator release group has published an album.
The email link returns to that event's guest gallery access page and safely prefills
the registered email address; the customer must still enter the gallery login code.
Use a signed opaque link rather than placing the raw email address in the URL.

### Payments

Braintree is the selected payment provider.

Support Apple Pay and the Google equivalent where available.

Do not force a repeated account-password entry solely because the customer is paying with a wallet.

Exact mobile payment implementation and applicable platform requirements must be verified before committing to the native checkout architecture.

---

## 19. Orders, downloads and support

Customers must be able to see media **by order**, especially when they place several orders at one competition.

Provide:

- Orders/invoices area.
- Order-specific purchased items.
- What is ready and what is pending.
- Download access and expiry.
- Links into the media library.

The library is an additional browsing experience, not a replacement for order clarity.

### Downloads

Prefer complete-order downloads so customers do not accidentally leave media behind.

Use temporary download packages where appropriate rather than storing permanent duplicate ZIPs unnecessarily.

The app should provide optional automatic local saving, controlled in the profile/device settings because phone storage is limited.

A customer may already have downloaded on a laptop and not want automatic phone copies.

### Purchased-media periods

Planning established:

- Photo-order downloads: 12 months.
- Competition video downloads: 60 days.
- Later clarification set normal competition photo/video order reactivation to **$10 for 60 days**, replacing earlier $18 discussion.
- Concert photo order reactivation is also planned at $10.
- Concert video-code reactivation remains $18.

**Owner decision (22 September 2026):** A mixed photo/video order uses separate
download clocks by media type and readiness. Photo access lasts 12 months and
video access lasts 60 days; each item’s period starts when that item is ready
for that customer. A pending video must not shorten or delay a ready photo’s
window. The order view should show item-specific readiness and expiry while
still offering a convenient whole-order download once everything is ready.

### Support and adjustments

- Guided help before contacting support.
- Admin can investigate an order and its exact media.
- Refund or account-credit options.
- Whole order or specified amount.
- Account credit expires after 12 months.
- Wrong routine involving a different child can be swapped or credited.
- Correct child but wrong routine is not normally eligible for a discretionary refund.

Implement customer policies consistently with applicable consumer obligations.

---

## 20. Customer communications

Studio account holders can enter reserved row and seat details separately for every staffed concert role. Photography roles accept one or two seats and videography roles accept five or six seats. Dress rehearsal portrait events do not request auditorium seating. The same submission endpoint supports the responsive web portal and customer mobile app.

Concert confirmation event details show the submitted reserved row and seat numbers beside venue and access information, grouped by photography or videography role. Concert events without submitted seats show **To be confirmed**. Dress rehearsal portraits and competitions omit the seating line.

### Studio event file submissions

- Files are submitted against a specific booking event. Junior and senior concerts ordinarily have separate programmes and separate supplied-media lists.
- Each event has one current programme. A replacement keeps the earlier submission in its audit history rather than silently deleting it.
- The upload screen asks the studio for a PDF programme, but accepts other document, image and archive formats. A non-PDF programme is marked **Needs conversion** for DancePro and is not sent to automatic programme extraction until a PDF replacement is available.
- Each concert event can receive multiple studio videos, slideshows, opening sequences or presentation files. New files append to the event submission; they do not replace another video implicitly.
- Every submission records its original filename, size, type, uploader and upload time. DancePro can download, relabel, approve, replace or remove an individual supplied file without affecting the other event files.
- Large videos use protected resumable multipart uploads directly to object storage from web and mobile. Upload progress and retry state survive ordinary network interruptions; the application server does not proxy the video body.
- Tracker state is event-specific and distinguishes **Not received**, **Uploading**, **Received**, **Needs conversion/checking** and **Approved**. Several received videos are shown as a count and list rather than a single completed checkbox.

Booking confirmation emails present each booked event as its own readable block. The bold event title is followed by labelled date, venue access/setup time, curtain-up or portrait-session start, expected finish, venue and event-specific services. Dress-rehearsal portraits and each junior or senior concert remain separate, and service names use concise customer wording such as Photography, Videography and Portrait photography.

DancePro branding, with event/studio details where relevant.

Templates should support:

- Preview.
- Test send to admin.
- Relevant event/order information.
- Text-based order confirmations; photo thumbnails are unnecessary.

Messages include appropriate:

- Order confirmation and tax invoice.
- Media-ready notification.
- Access/download links.
- Expiry reminder where required.
- Reactivation confirmation.
- Support replies.

Customer accounts use **My Messages**, between My Media and My Profile:

- **Notifications:** automated media/order updates and gallery announcements.
- **Conversations:** personal account emails and customer-support threads.
- **New message:** Subject and Message, with optional topic, owned event/order
  context, expired media and attachment in a collapsed Advanced options section.

My Media, My Messages, My Profile, Help and Studio Hub share the blue eyebrow,
heading and description format. The customer header uses the supplied full
DancePro logo; Studio Hub retains its studio logo and notification boxes.
Email preferences are preselected for customers without a saved choice; saved
opt-outs are preserved, and news/offers still require a saved consent before sending.

Tabs share the account heading, layout and scroll retention. Unread counts are
shown per tab and combined in the main menu. Replies reopen the existing admin
support request. Customer correspondence stays separate from Studio Hub production
correspondence. See [My Messages](../handbook/Customer-Messages.md).

The agreed email roles remain info (general), admin (studio), orders (orders),
and notifications (automated From). Vodien/cPanel hosting was confirmed on
28 September 2026; live mailbox ingestion is not enabled yet.
See [Email mailbox routing](../handbook/Email-Mailbox-Routing.md).

Avoid redundant “complete order” and “additional media” messages.

No promotional customer email programme is planned.

Concert access distribution remains the studio’s responsibility.

---

## 21. Director Hub

This is part of the customer website and app, not a separate application.

An authorised director keeps ordinary customer functionality and gains a Director Hub.

Director access is based on existing studio contact records or admin authorisation.

A normal studio gallery code must not grant director privileges.

### Features

- Home showing **Action needed**.
- New booking requests.
- Booking status.
- Change requests.
- Rebook using previous details.
- Save forms and return later.
- Confirm that existing venue/technical details are unchanged.
- Estimated/final family numbers where applicable.
- Programme, event logo and slideshow/video uploads.
- Simple media progress.
- Expected delivery dates when available.
- Concert-specific questions/messages.
- Video review, corrections, approval and release.
- Invoices, payments and receipts.
- Released concert links and codes.
- Prepared messages to copy and send to families.
- Access expiry information.
- Studio details and authorised contacts.
- Previous years’ bookings and records.

Changes to confirmed dates, venues or services remain pending until DancePro accepts them. Do not silently alter crew commitments.

### Limited progress

Show understandable stages such as:

- Received.
- Editing.
- Final checks.
- Ready for approval.
- Released.

Do not expose internal crew notes or every operational problem.

### Principal photo pricing

Existing authorised directors automatically receive **$1 per photo** for their studio’s eligible concert and dress rehearsal photos.

These $1 principal photos do not contribute to bulk-discount thresholds or
bulk-discount amounts. Principal eligibility is granted when the customer's
email matches the studio's primary contact or an owner, principal or director
contact; it is not granted by studio membership alone.

Allow restricted additional contacts, for example:

- Office manager can provide information or pay.
- Video approval permission is separately controlled.
- Principal photo entitlement is not automatically given to every invited person.

Director actions update source records and the Tracker.

### Future feature

Director loyalty discounts on competition media purchases.

Not part of the initial pricing implementation. Leave room for eligibility, products, rates and discount-stacking rules.

Customer account credit is a separate stored balance that an administrator may
grant to a specific account by email address or account ID. It is visible in the
customer account and cart and is applied after discounts. It does not change bulk
eligibility, but GST is calculated on the remaining total after credit. Monetary refunds and loyalty earning/redemption
remain separate features with their own history and rules; do not represent them
as discount codes or manual account-credit grants.

---

## 22. Tracker

A standalone top-level area across all modules.

Its main purpose is an overview and direct route to work requiring attention.

### Source of truth

Most task status comes from the module where the work happens.

Examples:

- Programme uploaded.
- Crew assigned.
- Media verified.
- Invoice paid.
- Approval link sent.

Do not require the same task to be manually marked complete again in the Tracker.

Support additional manual tasks with:

- Assignee.
- Due date.
- Status.
- Completion actor/time.
- Notes.
- Follow-up date.

### Views

**Date view**

All events in chronological order.

**Studio view**

- Studio name as a heading.
- Event name is the prominent title in each row.
- Date and event code beneath it.
- Events are chronological within the studio.
- Shared task cells merge vertically across the events they cover.

Merge based on the actual booking/task scope, not merely because events belong to the same studio.

### Task scopes

- Studio/season.
- Booking or selected concert group.
- Individual event.

Examples:

- Invoice/payment can span the whole booking.
- Family numbers can span the relevant concerts.
- Editing and approval remain per concert.
- Crew assignments remain event-specific.

### Status and interaction

- Complete: green.
- Near due: amber.
- Overdue: red.
- Waiting.
- Not started.
- Not required.

Hover shows who completed it and when, or assignee/due details.

Click opens the relevant source page or task.

**Add note** is available even on automatically tracked tasks.

Waiting does not automatically remove overdue status.

The task sheet centres its text, highlights past open-task deadlines in red, and
supports full-screen expansion with fixed column headings and a fixed event
column. Service-specific checkpoints show Not required when the linked booking
does not include that service for the event type. Current supplied studio videos
show approved/total progress with a file-review action; sending a request email
never counts as receiving those files.

### Due dates

Support offsets from:

- Individual event.
- First concert in a selected group.
- Last event in a selected group.
- Another milestone.
- A fixed date.

Date changes recalculate relative deadlines while preserving explicit overrides and history.

### Original Concert Tracker tasks to preserve or deliberately replace

**Booking**

- Booking information email.
- Videography booked/confirmed.
- Confirmation information email.
- Final Numbers and Technical Information email.
- Final Numbers received.
- Optional concert video deposit.
- Concert Week requirements email.

“Compile Booking” can be removed because it was spreadsheet administration. A booking-received acknowledgement may remain separately.

**Editing**

- Concert edited.
- Double check/final quality check.

**Assembly**

- Post-concert information email.
- Studio video/slideshow received.
- Programme received.
- Group chat created, if still required in the new workflow.

**Completion**

- Photos uploaded.
- Video uploaded.
- Invoice finalised/sent.
- Payment received.
- Video approval link sent.

Also account for:

- Crew availability/assignment readiness.
- Programme preparation.
- Required photographer contributions.
- Photo release.
- Video holds/missing media.
- Director corrections and approval.
- Final release.
- Manual exceptions and follow-ups.

#### Tracker consolidation decision

The implemented Tracker preserves these outcomes in 21 checkpoints rather than
one column per old-system action:

- Booking receipt, approval, selected services and videography are one **Booking
  & services** source task.
- Programme receipt and confirmation are progressive states of **Programme**.
- Photographer contributions and verified files are progressive states of
  **Photo uploads**.
- Album preparation, parent grouping and release are progressive states of
  **Photo release**.
- Invoice preparation and sending are one **Invoice** task.
- Group chat creation is not tracked because the unified event chat is automatic.
- The optional video deposit is hidden until a production deposit record exists;
  it should return as a source task if deposits become part of billing.

This keeps the All tasks view focused on work that can change an outcome. Email
tasks remain separate where sending the message is itself a scheduled action.

### Deadline decisions

- Programme: one week before the relevant event.
- Numbers/tech email example: one calendar month before.
- Concert Week email: one week before.
- Post-concert email example: one day after.
- Supplied slideshow/video request in existing materials: within two days after.
- Final invoice due: seven days after last concert.

**Approval link sent** represents completion of DancePro’s initial production work.

Studio approval, corrections and release remain separately tracked.

A 14-day production target appeared in the example tracker. Confirm whether it is the universal default before enforcing it.

---

## 23. Permissions, administration and history

Initially David is the primary administrator, with options to delegate.

Permissions should be configurable by module and sensitive action.

Examples:

- Upload.
- Edit gallery structure.
- Release photos.
- HOLD/unhold videos.
- Resolve missing contributions.
- Change prices.
- Issue refunds or credit.
- Manage invoices.
- Approve/release concert videos.
- Manage director access.

Crew can upload to another event if necessary; suggest the assigned event without making assignment an inflexible restriction.

Record significant changes, including:

- Previous and new assignment.
- Holds.
- Release actions.
- Pricing overrides.
- Refunds/credits.
- Approval activity.
- Invoice/payment changes.

---

## 24. Storage, costs and reporting

ShootProof currently holds approximately:

- 33,724.85 GB of photos.
- 6.2 million photos.
- Eight years of history.

This is background context, **not a migration requirement**.

Future media should be retained indefinitely where financially practical.

### Storage planning

Compare:

- Original storage.
- Preview storage.
- Video streaming copies.
- Long-term archive storage.
- Retrieval/restoration charges.
- Download bandwidth.
- Temporary ZIP creation.
- Processing and watermark costs.

Choose cost-efficient downloads even if restoration involves a clearly explained wait.

Keep frequently viewed previews readily available while considering cheaper storage for older originals.

Define a practical recovery approach for customer/order records and new media. Do not assume either “duplicate everything forever” or “no recovery needed” without a costed decision.

### Reports

On-screen reporting with CSV exports.

Include:

- Sales.
- Discounts.
- GST.
- Refunds, account credits and loyalty, reported separately.
- Studio invoices.
- Outstanding payments.
- Media/upload progress.
- Estimated storage and delivery costs.
- Event profitability estimates.

Later include crew costs from Crew Management.

Clearly distinguish estimated costs from actual billed costs.

---

## 25. Later features

Leave room for, but do not let these delay the first usable system:

- Facial recognition to suggest a dancer’s troupe/improvisation routines.
- Director competition loyalty discounts.
- Customer loyalty balances, earning rules and redemption history.
- Payment refunds with provider references, amounts, reasons and audit history.
- Portfolio/photo-shoot album pricing.
- More advanced automation.
- Tracker tasks surfaced in crew members’ own Hub.
- Additional editing/integration features not yet agreed.

Facial recognition is explicitly in the **maybe pile**, not approved first-version scope.

---

## 26. Suggested implementation order

### Stage 1 — inspect and map existing systems

- Read project instructions.
- Inspect current models, screens, permissions and tests.
- Locate the older concert delivery/approval implementation.
- Map existing features to this plan.
- Identify reusable components and missing relationships.
- Confirm repositories and app technology choices.

**Output:** a concrete implementation map, with no unnecessary replacement of working functionality.

### Stage 2 — shared records and permissions

Implement:

- Stable event/routine/media identities.
- Booking and concert group relationships.
- Role contributions.
- Programme structures.
- Gallery hierarchy.
- Access-code groups.
- Permissions and audit history.

**Output:** reliable shared foundations.

### Stage 3 — management event screens and programme preparation

Implement:

- Competition event media view.
- Concert/studio media view.
- Programme upload, extraction and confirmation.
- Expected media/contributions.
- Gallery settings and defaults.
- Watermarks and covers.
- Clear next actions.

**Output:** admin can prepare a real event for the uploader.

### Stage 4 — Mac uploader

Build in this order:

1. Event preparation and persistent offline state.
2. Photo review, Trash/undo and Top Pics.
3. Moving photos and concert structure changes.
4. Reliable queued uploads and verification.
5. Required photographer completion.
6. Video upload and HOLD/unhold.
7. Concert black-frame import.
8. Reconciliation and kiosk export.

**Output:** crew can prepare at home, work offline and upload later.

### Stage 5 — customer web and app foundations

Develop together:

- Accounts and guests.
- Studio/event access.
- Gallery browsing.
- Programme viewing.
- Favourites.
- Family sharing.
- Orders and media library.
- Local saving behaviour.

**Output:** customers can access the correct media consistently on web and phone.

### Stage 6 — commerce and fulfilment

Implement:

- Product prices.
- Bulk discounts.
- Ownership/duplicate rules.
- Video-to-package upgrades.
- Braintree and wallets.
- GST invoices.
- Fulfilment gates.
- Download periods and reactivation.
- Refunds and credits.
- Transactional email.

Resolve mixed package/whole-order download clocks before this stage is completed.

**Output:** a tested purchase-to-download workflow.

### Stage 7 — concert production and Director Hub

Implement:

- Studio booking/forms.
- Final numbers.
- Optional deposits.
- Studio invoicing/payment state.
- Limited production progress.
- Approval checklist and corrections.
- Earlier-concert exceptions.
- Final-concert payment gate.
- Release and code distribution tools.
- Principal photo pricing.

**Output:** directors can manage their bookings and media in the customer app.

### Stage 8 — Tracker and reporting

Connect actual module states into:

- Date and studio views.
- Shared merged cells.
- Relative deadlines.
- Notes and follow-ups.
- Manual tasks.
- Click-through actions.
- Cost/sales reports.

The Tracker interface can be built earlier, but automatic completion must reflect real source records.

### Stage 9 — pilot and rollout

Test representative real scenarios:

- Competition with one photographer.
- Competition with two photographers finishing at different times.
- Entirely offline event.
- Multiple clips and a held competition video.
- Additional media arriving after an initial upload.
- Concert with combined/reordered items and a missed black separator.
- Studio with concerts months apart.
- Earlier concert approved before payment.
- Final concert blocked until payment.
- High school flat-rate booking.
- Guest purchase later claimed by an account.
- Interrupted upload/download and reactivation.

Use future events in the new system while leaving historical ShootProof galleries intact.

---

## 27. Questions to resolve during the build

Do not treat these as reasons to halt the entire project:

1. Location and behaviour of the older concert delivery system.
2. Customer phone-app technology. Supported platforms settled: both iPhone and Android for the first version (22 September 2026).
3. Mobile payment requirements and Braintree wallet setup.
4. Accounting/invoice integration and payment reconciliation.
5. Mixed photo/video download periods are settled as separate readiness-based
   clocks (22 September 2026); exact ready-notification timing remains open.
6. Storage provider, archive tiers and restoration costs.
7. Programme extraction approach and confidence/review handling.
8. File-versus-routine HOLD details.
9. Exact default deadlines and escalation rules.
10. Current photography minimum-spend enforcement.
11. Cancellation and rescheduling processes.
12. Final permission matrix.
13. Handling later additions to previously fulfilled all-photo/package purchases.
14. Which existing email wording and commercial terms need updating.

Resolve each at the relevant implementation stage and record the decision.

---

## Starting instruction for the new build chat

**Use this handover as the agreed product direction. Start with Stage 1: inspect the existing management project and locate the current/older concert delivery and approval functionality. Report what can be reused, what is missing and the first manageable implementation milestone. Preserve working features and avoid reopening decisions already settled here. Build incrementally, resolving remaining gaps when they become relevant.**


## Owner clarification: shared programmes and capture folders (22 September 2026)

Concerts use the first synced working programme across photographers. Keep the
Programme button and show Confirmed once that Mac matches the shared version;
otherwise show Update available and load it from online explicitly. Synced programme
changes notify the event's Crew Hub chat in one batch, with actor and summary.
Further changes must be explicit shared updates, never a second hidden programme.

Competitions allow additional competitor folders and last-minute sections from the
capture app. Compare P1/P2/V and flag Folder differences to check; do not automatically
rename another laptop's folders or block ordinary uploads. Programme differences
and additions should notify crew in event chat. Competition folder discovery and
comparison remain the next implementation milestone.


### Competition capture hierarchy clarified (22 September 2026)

The capture app uses event code / numbered weekday / numbered section and title /
numbered competitor and name, e.g. EDC926 / 01 Friday / 07 Jazz 12 & Under /
03 Jessica Smith. Competition preparation/discovery now follows this hierarchy.
External folder names are retained, with approved-day and printed-number identity
matching. Shared additions extend the expected role contributions without replacing
the approved programme or moving uploaded files. The Programme panel compares P1/P2/V
and posts synced change batches to event chat. Video byte transfer and administrator
correction of already uploaded identities remain later scope.


### Optional competition day level and video filenames (owner-confirmed)

Use the same hierarchy for the online competition gallery and capture preparation:

- One-day events: event code → section → competitor.
- Multi-day events: event code → numbered weekday → section → competitor.

The gallery example is EDC926 → 07 Jazz 12 & Under → 03 Jessica Smith for a one-day
event, with 01 Friday inserted only for multi-day events. Photos from both roles and
the competitor video belong to that competitor identity; source roles are retained
in backend tracking, not presented as duplicate competitor branches. Additions follow
the same hierarchy and existing gallery release requirements.

Video basenames always use event code_section number_competitor number, with no day:
EDC926_07_03 is Jessica's video. The earlier _06 example was a confirmed typo, not a
separate video numbering scheme. Section numbers are unique across the event, so
filenames remain unambiguous across days. Day records remain in the programme for
scheduling and contribution scope even when the visible day directory is omitted.

Mac preparation, discovery and local filename checks implement these rules. A private
administrator gallery browser now follows the same hierarchy and combines verified
photos across roles. Customer-facing previews/access, section release and video byte
transfer are still pending.


### Management navigation clarification (22 September 2026)

Event Details belongs to upcoming-event planning, crew and programme preparation.
Media owns the subsequent upload, gallery and order workflows, using the same event
records rather than duplicating events. The competition photo area is labelled
**Media → Competition Photos**. Its event list opens the gallery and photo-upload
management. Competition gallery/upload buttons do not belong in Event Details.
Apply this separation to subsequent concert media and order screens as they are built;
the existing concert upload entry is retained until its Media entry is connected.


### Competition preview implementation (22 September 2026)

The private admin gallery now uses burned-in competition-watermark JPEG derivatives,
with initial 480-pixel thumbnails and 1600-pixel previews at quality 88. Original
files remain unchanged; orientation is applied before metadata removal. The existing
2026 square artwork is centred and fitted to the narrowest side. Owner image-quality
review is still required before customer launch. The configurable watermark library,
gallery overrides, production processing and customer release remain pending.


### Section readiness implementation (22 September 2026)

The private competition gallery now shows each section's required photo roles and
complete, waived, outstanding and verified/pending counts. All applicable roles must
be complete with verified receipts or explicitly waived; first-photographer
completion alone is insufficient. Added competitors reopen readiness, and stale
programmes or missing role configuration prevent a ready label. This remains a
read-only management display. Publication controls/customer access are not yet built.


### Gallery presentation settings (22 September 2026)

Competition Photos now supports an optional display title and selecting a verified
photo as the cover, with an explicit restore-event-logo action. Settings default to
the event name/logo and leave planning records untouched. Changes record the actor
and previous/new values; stale saves are rejected. These are private administrative
presentation controls, not publication or customer-access settings. Watermark library
and per-gallery watermark overrides remain pending.


### Automatic visibility preview (22 September 2026)

The admin gallery now offers Customer Preview, showing only ready sections and
watermarked images through admin-only routes. Image and cover requests recheck section
readiness. This implements a management review of the automatic rule already agreed
in sections 7/12, without inventing a manual release approval step. Event-code access,
accounts, personal filtering, web/phone customer clients and purchasing remain pending.
No customer publication or delivery entitlement is created by opening the preview.


### Customer event-access foundation (22 September 2026)

Owner decision: the first customer phone app supports **both iPhone and Android**.
Mobile technology and authentication remain to be implemented. The first web slice
uses existing active customer accounts and adds My Competitions at `/my/competitions`.
An exact event-code lookup registers the stable event ID to the account; repeat entry
is idempotent and later code changes do not remove existing registrations. Ambiguous
codes require correction instead of choosing an event silently.

Registered customers see only sections meeting the existing automatic readiness rule,
including when directly requesting a watermarked image. Administrators may register
an event to review this flow with their own account. This does not complete Stage 5:
signup, guest access, mobile API/authentication and clients, personal filters,
favourites, family sharing, orders and downloads remain outstanding.


### Correction: competition media codes and customer login codes

Owner clarification (22 September 2026): the competition code used for media is
separate from the customer login code. A competition can run four or five events
per year under the same competition code. A media code must not grant gallery access.

Customer flow: select the specific event from a dropdown, then enter that event's
separate login code to add it to the account or access its gallery. Event selection
must use stable event identity, with event name/date making repeated events clear.
The login code belongs to that event, not the competition organisation. The existing
ready-section visibility rules still apply after access is granted.

The first web implementation incorrectly treated media codes as login codes; this
has now been corrected with event selection and hashed event login-code verification.
Administration: a separate event login-code setting in Media → Competition
Photos → Gallery settings. Mobile and web should share the same verification rule.


Event selector clarification: list all noncancelled competition events, even before
their login codes are configured. Login-code configuration controls access, not
whether the event is visible in the dropdown.


### Customer web account slice (22 September 2026)

Customer signup and a customer-facing sign-in page are implemented using the existing
user/profile and session infrastructure. New users have customer permissions only;
event login verification remains independent. Customer sign-out returns to customer
sign-in. Email verification, customer policy onboarding, guest purchase claims and
mobile API/authentication remain pending. No consent or verified email is inferred
from creating an account. Both iPhone and Android remain required.


### Shared DancePro account and My Media (22 September 2026)

Owner correction: signup is for all DancePro media, including concerts. My Media is
now the customer landing page, with competition galleries, Add Concert Video and the
existing studio/concert finder. All flows use one account; access rights are separate.

The first concert account adapter accepts existing concert passwords and saves all
currently available matching concerts using existing access-grant records. Access is
persistent across signed-in sessions and preserves original availability/expiry.
Revoked/expired grants are not recreated on code entry. This is an intermediate
adapter: section 15's explicit code groups, later-release inclusion, separate six/twelve
month windows, reactivation and reminders are still pending. Existing public delivery
remains in place. See `docs/api/Customer-Media.md`.


### Add media and profile prerequisites (owner direction, 22 September 2026)

My Media has one full-width Add media box with three options:

- Add competition photos: event selector and photo login code. With no dancer in
  My Profile, show the requirement and Add now linking to the dancer section.
- View/order my concert photos: links for studios registered in My Profile. With no
  studio, show the requirement and Add now linking to the studio section.
- Download concert video: select the concert year and enter the video login code.
  With no studio, show the same studio requirement and Add now link.

Implemented My Profile dancer names and studio registration, scoped to the account.
Competition registration requires at least one dancer, but dancer details do not
restrict the rest of an already-authorized event. Concert-code matching now uses the
selected year and registered active studios. Profile registration alone grants no
video access. Existing saved competition/concert access remains visible independently.
Studio photo pages link to existing published concert photo-gallery URLs; native photo
ordering and studio gallery-code verification remain later work.


### Profile implementation and dependency map (22 September 2026)

Reviewed sections 12, 18 and 19 for the expanded customer profile. Working sections
now cover contact/preferred-name editing, owned dancer editing/removal with alternative
programme names, multiple studios/removal and saved notification/marketing choices.
No account email change, email verification or legal consent is inferred from saving.
Profile removal preserves existing media access. Dancer relationship is descriptive
and does not grant family/payment permissions. Alternate names are stored for future
routine discovery; authorized Whole Event access remains available.

Family verified invitations and purchase sharing, carts/payment permissions, studio
code verification, notification dispatch, order/invoice screens and per-device local
saving remain dependent work, not simulated profile controls. See
`docs/api/Customer-Profile.md` for the complete requirement-to-implementation map.


### My Dancers routine suggestions (22 September 2026)

Saved dancer names and alternative programme names now suggest matching competitor
albums in a registered competition. Suggestions use only automatically ready sections.
Whole Event browsing remains available and is never narrowed by a saved name or
missing match. These matches are discovery hints, not proof of dancer identity or
purchase entitlement. Current programme nodes do not carry reliable structured studio
identity, so My Studios’ Routines remains pending until that association exists.


### My Studios’ Routines (22 September 2026)

Competition Photos management now lets an administrator assign one active studio to a
current competitor album. These assignments sit outside the immutable programme and
upload plan. My Studios’ Routines uses the assignment only when the customer has
registered the competition, saved that studio in My Profile, and the section is ready.
Unmapped/retired items do not appear as suggestions. Whole Event remains available.
Assignments are audited and protected against stale saves. See ADR-0030.

Saving a studio in the profile is currently self-declared and does not prove
membership, authorize an event, or grant purchasing rights. Studio gallery-code
verification remains an independent planned step.


### Studio gallery-code verification (22 September 2026)

Owner plan requirement implemented: adding a studio to My Profile now requires a
studio gallery code, separate from the studio's internal code, concert video code,
and competition event photo login code. Admins set/replace the hash in Studio management.
Existing pre-code profile associations require verification before studio links,
concert-video year lookup or My Studios’ Routines will use them. Verified associations
survive later code changes. Profile studio verification does not grant a competition,
video purchase or family entitlement. See ADR-0031.


### Cross-event recommendations and purchased media layout (22 September 2026)

My Media now places Recommended Media under Add media, with My Dancers and My Studio
pill tabs. Recommendations search ready competition routines across events; verified
profile studios use explicit album assignments. Matching routines show watermarked
photo thumbnails where a verified photo exists. An unregistered event card leads
to the event login form with that event preselected. The event gallery remains
locked until registration.

The Photos and Videos tabs below it are reserved for media from paid/fulfilled
orders, grouped into event cards. Existing competition registrations and concert
video grants remain in My Events because access is not proof of purchase. There
is now a local-only test checkout and order-delivery screen for the new gallery.
Live Braintree capture, refunds and reconciliation remain part of the commerce stage.

Purchased items from different competition routines or separate orders are
combined into one event card and one flat event album, without routine/order
subfolders. The compact card uses a purchased-media thumbnail when available.
Concert cards show concert title and date. Local-only, labelled review examples
may demonstrate the layout without creating financial records or entitlements.


### My Events on My Media (22 September 2026)

My Events follows the purchased Photos/Videos box. It shows each added or
accessible event once: verified competition registrations, current concert photo
galleries for verified studios, valid concert video grants, and events represented
by linked paid orders. Compact concert cards open the concert page when video
is available (the page also links to photos), or the photo gallery when it is
photo-only. A purchase does not silently unlock an otherwise
unregistered competition; that card asks for its event login code.


### Customer code groups, onboarding and mobile API (22 September 2026)

Admin concert video code groups now bind selected concerts from one studio/year
to a hashed code. Explicit release starts a fixed six-month streaming and
twelve-month download period. Later added concerts share those dates; rotating
the code preserves issued grants. Existing individual concert passwords remain
available during transition. Customer web signup now sends signed email
verification and records explicit acceptance of the current public Terms and
Privacy Policy. Existing web customers without acceptance see onboarding before
My Media. The shared `/api/v1/customer` contract supports customer-only mobile
authentication, onboarding state, profile, event codes, galleries and media.
The iPhone and Android clients, guest access, reactivation and commerce remain
later work. Policy content/version require owner review before production.

### Customer web gallery first (22 September 2026)

The owner chose to complete the web gallery experience before native customer
clients. The registered-customer competition gallery now has its own view,
natural-ratio photo thumbnails, an in-page watermarked viewer and an
Improvisation view based on ready section/routine labels. My Dancers, My
Studios’ Routines and Whole Event remain available. Persistent favourites and
the competition quote cart now work for available media, with overlap and bulk
pricing. A first native concert photo browser serves published albums to
verified studio customers with private baked-in watermarked previews. Order
history is account-scoped. A concert photo quote cart now spans concerts in
one verified studio. Guest purchasing now hands off through account conversion
to a local test checkout with protected original downloads. Live Braintree still
needs to be built before translating the flow into phone screens.

### Concert photo setup and grouped release (23 September 2026)

Media → Concert Photos now lists scheduled concert events and imports verified
Mac-uploader programme folders into private customer photo albums. The import
uses stable programme/photo identities, remains idempotent, and preserves staff
album renaming and numeric ordering. It cannot publish media. The event must be
linked to its existing customer concert record first.

Media → Concert Photo Releases allows unreleased albums from one studio to be
grouped and released together. Every album must contain a visible available
photo. Release publishes the group atomically and records the actor and album
set. Reusable competition, concert and proof watermarks now have a library,
previews, event-type defaults and gallery/release overrides. Existing settings
remain as fallbacks. Parent album grouping, covers and live payment remain pending.

The release workspace now lives in the Concert Tracker Photo release task rather than a
separate Media navigation item. Date and studio views derive programme, crew,
verified-photo and photo-release status from their source records. Creating a
release group, selecting its Top Pic cover and releasing its albums therefore
updates the Tracker without a duplicate completion checkbox. The old release URL
redirects to the Tracker for compatibility. Manual tasks, relative deadlines,
notes and the remaining original Tracker columns are later Tracker stages.

The next Tracker slice now implements the full planned task catalogue across
Booking, Preparation and assembly, Editing and approval, and Completion tabs.
Booking-scoped tasks share one record across related concerts and merge in Studio
view. Manual tasks support status, assignee, due-date override, follow-up,
completion history and notes. Calculated deadlines implement the agreed programme,
final-number request, Concert Week, post-concert, supplied-media and final-invoice
offsets; the unresolved 14-day production target is not enforced.

Booking information, confirmation, final-number, Concert Week, post-concert,
studio-media request, approval-link and invoice tasks have editable templates,
event-specific previews, queued delivery and frozen delivery history. A successful
delivery completes its manual task. Local-only fictional review data demonstrates
shared booking tasks, different stages, media, follow-ups and delivery history.

### Public guest gallery and pre-release registrations (23 September 2026)

The public media entry point will use Competition and Concert tabs. Released event
cards lead to email-and-gallery-code guest access without requiring account creation.
Events may appear seven days before their scheduled start with a Coming soon —
register now banner. Email registration records interest only and does not unlock
media. When customer-visible media first becomes available, the customer receives
one email containing a signed return link that prefills their email; they still enter
the applicable gallery login code. Guest favourites and carts transfer when the
customer creates or authenticates an account during purchase.

The first web implementation now provides this index, email/code unlock, encrypted
deduplicated registrations, one-time queued release notifications and 30-day guest
access to released watermarked competition and concert photos. The guest experience
now reuses the signed-in gallery, product cards, routine packages and videos,
individual-photo multi-select, previews, favourites, carts, prices, discounts and
upsell notices. Guests create an account or sign in only when they continue from
the cart to checkout; their saved selections then transfer. The local test
checkout and order delivery now cover the post-account flow; live payment remains pending.

The email also acts as a lightweight guest identity within each gallery. A later
unlock using the same normalised email and the correct current gallery code restores
that gallery's favourites and cart on another browser. A browser session expiring
does not discard the saved guest activity. Purchased media still requires a full
account.

### Customer media notifications (23 September 2026)

The Notifications tab edits order confirmation, media-ready and download-expiry
templates separately, provides a test-email action and lists recent queued, sent
and failed deliveries. Order confirmations are always sent after successful
payment. Ready and ten-day expiry messages respect the customer's profile
preferences and use deduplication keys so retries do not resend them. Production
still requires a configured mail transport, queue worker and scheduler.

### Concert parent albums and covers (23 September 2026)

Updated 30 September 2026: release groups are an admin publication/announcement
batch for whole events from one studio, rather than another customer folder.
Tracker 4.1 presents event cards with photo counts and readiness, selects every
album in each selected event, releases them atomically and opens one editable
announcement listing the included events. Retries skip previously queued/sent
recipients. Customers see Studio → event → routine albums → photos; DR portraits
open straight to images. Existing group links and cover records stay compatible.
Photo publication does not approve or release concert video. Media files and
storage identities are unchanged.

### Admin Top Pics browsing implementation (26 September 2026)

Media > Publicity > Top Pics provides a private competition/concert switch and event albums.
The same event album is linked from each backend photo gallery. It reads verified
uploader selection flags without creating new originals, shows routine context,
and supports preview, audited original download and complimentary account grants
(using synced customer assets for concerts). It is not a customer-facing gallery.
Post-upload tag synchronisation is implemented below; kiosk exports remain separate work.

Publicity now has separate Top Pics and TV Slideshows pages with shared tabs.
The TV page is an explicit placeholder; playback and screen pairing are not
implemented. TV should reuse the existing Top Pics event/photo catalogue, with
advertising and event information added to its playlist.

### Automatic post-upload Top Pics sync (26 September 2026)

The Mac uploader now persists star edits and acknowledgements in its local review
sidecar. A background worker syncs verified-photo changes in batches of up to 100,
including when the event list is shown. Offline failures retain changes and retry
with backoff; restarting the app resumes the queue. Only matching acknowledgements
clear pending revisions, so edits during upload or sync are not lost. Older local
reviews receive a one-time reconciliation of their saved stars.

The upload-authorised event endpoint changes existing photo flags and records the
actor. Cross-event/unverified files are rejected atomically, and replacement-laptop
receipts resolve to the canonical photo. Bytes, gallery visibility, order references
and upload-completion state are unchanged. Publicity reads the same flags on the
next page load. TV playback remains unimplemented and will consume this catalogue.

### Publicity TV local pilot (26 September 2026)

The TV Slideshows placeholder is replaced with event settings, global advertising,
event information and admin-approved screen pairing. Both a browser player and
native Fire OS app consume a feed derived from the existing Top Pics catalogue.
Only display derivatives are cached; originals and star identities are unchanged.
Playback refreshes every minute, with a 500 MB cache and a 24-hour offline window.
Admin pairs each crew screen once; the native homepage lists TV-enabled events
newest first. Crew select the event locally. Events without Top Pics still play
general and event adverts. TV photos use separate unwatermarked derivatives,
while customer galleries remain watermarked. Physical Fire OS testing and
production storage/hosting remain pending. See the
[TV milestone](../milestones/Publicity-TV-Slideshows.md) and
[ADR-0053](../decisions/ADR-0053-Publicity-TV-Playback.md) for the precise boundary.

TV controls now separate Back/Escape (event menu) from Menu/M (playback controls).
Photo/general/event defaults are 2/5/8 seconds. A generated event advert includes
current event branding and a scan-to-add QR when gallery access is configured;
customer sign-in and onboarding still apply. Concert QR admission follows existing
studio photo access, without paid video or media entitlements. Both corner logos
are cached for offline playback. Custom uploaded adverts remain supported; a
multi-template design editor is not yet included.


### Gallery sharing independent of TV (26 September 2026)

Publicity → QR codes & links lists competitions and studios separately, with search,
copyable signed access links and downloadable PNG QR codes. Competition links are
identical to those in the TV advert. Studio links add the studio’s photo-gallery
access without requiring a slideshow or a specific concert. Signup, verification,
onboarding and existing-account sign-in preserve the pending selection. Access is
idempotent and audited; purchased media and concert videos are not granted. A
configured gallery code is required; changing it invalidates old links. TV enablement
is independent of sharing. The crew TV catalogue now lists competitions only.


### Standard advertising categories (26 September 2026)

The TV Slideshows page’s Standard advertising panel has separate Competition and
Concert categories. Each has independent slides, ordering, visibility and duration.
Existing standard adverts belong to Competitions. Playlist construction and image
requests use the event’s type to select only matching standard adverts, alongside
that event’s own slides. Concert adverts may be prepared without enabling concert
TV playback. The default remains 5 seconds per standard advert.


### Competition advertising templates (26 September 2026)

Six supplied competition designs are included automatically in each eligible TV
playlist. Each uses the event’s current shareable admission QR, plays for 8 seconds,
and leaves logos to the existing corner overlays. Left/right QR placements are
recorded per template. Ads play even without Top Pics; a generic event message is
used until gallery access is configured. Admin event slideshow pages show the six
rendered previews. Cached PNGs refresh on artwork or access-link changes, using the
existing player update/offline flow without an APK rebuild.


### TV image fill (26 September 2026)

Photos and advertising fill the screen proportionally with a centred crop where
aspect ratios differ. This replaces the initial contain/letterbox behaviour.
Image advert titles alone are management labels, not playback captions; explicit
advert body text overlays the image. Corner logos remain separate overlays.


### TV video advertising (26 September 2026)

Standard and event adverts accept validated H.264/AAC MP4 video, up to 1080p,
60 fps, 120 seconds and 100 MB. Video adverts are spaced evenly across the Top Pics, independently of the
five-photo image/QR advert interval, and play to completion. Silent is the default, with optional
audio per advert. Native 0.4.0 and the browser preview cache complete verified clips
before playback. Offline access remains 24 hours; media cache capacity is 500 MB.
Playback has no visible transport controls unless deliberately opened by crew.
Routine/customer video playback is outside this advertising-only change.


### TV general advert retention — 26 September 2026

General photo/video adverts are shared on each paired device across event changes
and restarts. Content versions, rather than titles/timing/audio settings, control
redownloads. Disabled playback retains the current general library; removed or
replaced adverts are reconciled at the next successful update. Pairing reset or
revocation clears all media. The 500 MB limit and 24-hour offline access lease
still apply. Native version 0.4.1 and browser shell v7 implement this behaviour.

### Booking delivery progress — 27 September 2026

The authenticated Studio Hub Event timeline now uses current booking items for estimates and the Concert Tracker for production status; the original form continues to use the immutable submission snapshot. Cancelled items are excluded from current estimates. Approval preserves the saved studio and prepares a private disabled customer-media record for each concert or DR portrait session. This does not publish galleries or grant family access.

Local tracker-email rehearsal uses Mailpit and simulated delivery labels. See [rehearsal handbook](../handbook/Booking-Delivery-Rehearsal.md). Live finance/review/release milestones and the complete delivery rehearsal remain outstanding.

### Booking workflow update — 27 September 2026

Reviewed booking amendments now synchronise linked scheduling/media details and retain revision history without changing the original submission. Pending tracker emails are invalidated after material changes, and replacement reminders require review. Authenticated studios and authorised staff can confirm final unique-family counts; quotes and the final-number tracker task use that record. Booking-level invoicing/payment reconciliation and director review/release remain subsequent work. Operational details are in `docs/handbook/Booking-Delivery-Rehearsal.md`.

### Studio invoices and Hub payments — 27 September 2026

Approved studio bookings support a single active invoice with multiple GST-inclusive service lines, sequential invoice numbers, private drafts, issuance, partial received payments, immutable issued details and audit history. Studio Hub invoices include event details, payment status and downloadable PDFs based on the supplied DancePro invoice example, including bank details and an authenticated invoice link/QR. Photography minimum-spend shortfalls remain a reviewed manual decision.

The Concert Tracker and current Event timeline use the invoice ledger for financial state. A separate Braintree Hosted Fields/3DS2 integration reserves durable payment attempts and reconciles settlement; local simulation is available for rehearsal invoices. Live card activation still needs merchant configuration and sandbox verification. Director review and its final-concert payment gate remain subsequent work. See [Studio invoices](../handbook/Studio-Invoices.md).

### Tracker pending-booking visibility — 27 September 2026

Date view includes unconfirmed booking items with red studio headings and explicit Pending booking labels. Review opens the booking; approval replaces that preview with the scheduled event and its production tasks. Cancelled and declined requests are excluded. Tracker phases and tasks have stable hierarchical display numbers (1. Booking, 1.1 Booking & services), independent of filtering; internal task identifiers remain unchanged.

Event Availability also previews pending concert/DR portrait booking items alongside scheduled shifts in chronological order, with one shared paginator and studio/search/type/status filters. Pending rows show requested times, venue and red Pending approval, link to booking review and expose no crew response or bulk scheduling controls. No scheduling records are created until approval; the resulting draft shift replaces the preview and still requires a separate availability request. Tracker 2.1 uses the same studio-filtered view.

### Shared tracker controls on canonical pages — 27 September 2026

Task 1.1 Booking & services now opens the existing booking review/edit page. It always includes a shared sticky tracker bar, including direct entry from Event management. Booking-scoped task state persists from pending through approval. As updated on 29 September 2026, 1.1 changes the actual booking status: Pending approval (red), Received (yellow), Approved or Partial Approval (green), and Cancelled (grey/hidden from active lists). Partial Approval selects which events proceed; Received leaves them unscheduled and appears without a description in Studio Hub. Cancellation requires a reason. Editable received/partial/cancelled notification drafts use branded mail and Studio messages; approval retains the confirmation workflow. Deadlines and assignments save independently, and follow-up dialogs retain dated notes. Other task destinations will be migrated individually as agreed with the owner; see ADR-0055.


### Booking approval and confirmation combined — 27 September 2026

Task 1.1 now includes booking review, approval and the immediate confirmation-email step; the former separate confirmation column is removed. Both approval controls use the same action and then open the personalised template for explicit sending. Historical confirmation records and automation remain compatible. The booking information page also exposes per-event reserved seats, programmes and slideshow files shared with the Studio Hub, including staff uploads and retained replacement history. Final family numbers remain unconfirmed until explicitly submitted, regardless of due date.

### Concert booking task tabs (27 September 2026)

The admin booking detail is the canonical workspace for tracker 1.1 Booking & services and 1.3 Seats & families numbers, with separate page tabs and task bars. The latter records one final unique-family count and reserved seats for each concert; it completes only after all required details are confirmed and remains not required for DR portraits. Tracker 1.2 Seats & families email shows its template below its shared task bar, supports manual Approved without sending, and becomes Completed on successful delivery. Existing task keys and history remain intact.

Tracker 1.3 now presents families and seats independently: an explicit final-family confirmation or correction updates the green family count immediately, while outstanding seats retain the current pending/warning colour. Successfully sending 1.2 sets a seven-calendar-day response deadline for 1.3. Queueing or manually approving 1.2 does not start the deadline; the global three-day yellow warning and past-date red rules apply.

Tracker email tasks 1.2 and 1.4 share the same tracker bar and email workflow. Successful sends show recipient, sender and time; pending deliveries block duplicates and subsequent copies require explicit resend confirmation. Test captures are labelled separately from live sends.

Tasks 2.1 and 2.2 reuse operational workspaces: 2.1 displays the studio-filtered live availability sheet, with shared readiness labels and assigned-crew summaries; 2.2 is a booking tab containing the existing per-event programme editor. Studio-supplied programmes and admin PDF uploads use the same preparation and revision workflow. Existing preparation URLs resolve to the canonical booking tab for administrators.

Event Availability and the 2.1 snapshot share clickable empty role cells and event-status controls. Active applicable roles can be added to an event's three role slots without opening availability or notifying crew. Concert columns use V / P1 / P2; DR portraits retain their own role order. A newly required role participates in readiness and uploader requirements. Status actions use the existing Draft, Request availability (with response deadline), Close availability and Publish roster workflows; Ready remains automatic. Published rosters cannot be reset to Draft through this control. Choosing a bulk action reveals a checkbox column on the left; selecting any shift selects the whole event, while pending bookings remain excluded until approval.

Admin assignment dropdowns prioritise active crew who have marked the specific shift Available and have Approved training for its role, with a tick before their name. Other active crew remain selectable below, alphabetically; recommendations ignore overlapping assignments while existing clash indicators remain visible. Assigning other crew does not grant a qualification. Saving or clearing Crew Hub availability retains the Availability tab and selected layout even when no unanswered requests remain.

Competition and concert assignments share numbered video/backdrop kit controls and separate Photos and Videos bring/take controls. Photo/video selections are independent; the roster combines them into one 💾 marker with direction-specific descriptions such as “Bring photos · Take photos & videos”. Crew Hub uses the same combined description. On My Shifts, numbered equipment uses plain instructions (Bring, Take, or Bring & Take) and the full Video Kit/Backdrop Kit name alongside the existing coloured kit emoji and directional arrows; note-only assignments show the kit name and notes. Legacy Media allocations migrate to both types with their flags and notes preserved; rollback merges them back into Media (losing the distinction between types). The equipment popup saves each change while remaining open, serialises rapid changes and shows save feedback; × or an outside click closes it. Sheet markers and journey warnings refresh after closing and completion of pending saves. Required Setup/Set down shift indicators turn green when an assigned crew member on that same shift is bringing/taking any numbered video or backdrop kit respectively. They remain yellow without that responsibility, including when only Media or notes are recorded, and return to yellow if the responsibility or assignment is removed. Green means assigned, not physically completed. Clicking the Shift cell opens an autosaving Setup/Set down popup; it stays open until closed with × or an outside click. Concert approval continues to default both requirements on; competitions retain their event-form settings. Admin can override each shift independently for consecutive events at one venue. Saved times are preserved. Changes to published shifts reset acknowledgement and notify only that shift’s assigned published crew.

Concert tracker follow-up (27 September 2026): Photo uploads is 3.1, ahead of the existing editing tasks. Post-concert email is 2.3 and uses the shared email workflow with deliberate resend protection. Studio video/slideshow is 2.4 and opens an event-scoped tab in the canonical booking workspace, reusing studio submissions and admin upload/review controls. Task keys and historical records are retained when display numbers change.

Tracker 2.4 now includes dated uploader/reviewer attribution. Programme reading exposes
page-level progress and recoverable failures, with lightweight polling and improved
scanned concert title/class alignment. Dedicated worker availability is required;
extraction remains an editable proposal that staff must review and approve.

Video Titles follow-up (27 September 2026): Preparation inserts **2.3 Video Titles**;
Post-concert email and Studio video/slideshow are now **2.4** and **2.5**. The new
booking tab embeds the existing title editor and its tracker bar. **Video titles
ready** publishes the browser-rendered, approved-programme JPEG ZIP privately and
records task completion with staff/date. The Mac uploader downloads this exact
package after checking access, revision and integrity. Programme/template/title/logo
changes withdraw stale packages until reviewed and published again. No title
package is exposed through customer routes. See ADR-0056.

Studio Hub progress follow-up (27 September 2026): Authorised studio managers see
available photo-gallery and concert-video login codes in compact right-hand
“Access Code:” badges in their respective release rows. Video codes and the concert link remain hidden
until the studio has approved and released the current concert video; removing
approval or reopening the upload hides them again. Review & release concert
video appears under Your actions, greyed out until review is ready. The separate
Concert video released milestone remains under DancePro progress. Missing codes produce no extra text row. Newly saved studio/group codes retain encrypted display copies alongside
existing hashes; legacy hash-only codes require re-entry once. Existing individual
concert encrypted passwords are reused. This does not change publication, account
verification, media entitlement or code-group access periods. See ADR-0057.

Concert Tracker event-column follow-up (27 September 2026): studio heading, event
subtitle, separate event date, then a communications envelope and the studio's
actual gallery login on one wrapping row. This applies to date/studio views,
including red pending studio headings. Existing navigation and task status rules
are retained; unavailable codes are labelled rather than replaced by internal IDs.

Studio photo-gallery logins support three-letter codes at the owner's request;
tracker and Studio Hub continue reading the same stored code. Concert-video codes
remain separate.

Concert Tracker 3.1 uses the canonical Photo uploads workspace with the shared
tracker bar and registered-file progress. Cells show each photo role as Waiting
(blue, red only when overdue), Uploading (blue), or Complete (green), with no yellow
state. All required roles must explicitly finish against the current programme;
verified percentage alone and waived contributions do not complete the task.

Concert Tracker 3.2 follow-up (27 September 2026): concert editing is an
event-scoped assignment and manual sign-off. The task opens one shared editing
view with the tracker bar above it. Admin assigns an editor and manages deadlines;
the assigned crew editor can access it through Crew Hub > My editing, update
progress/follow-up notes and mark the edit complete. Completion records actor and
time and turns the tracker cell green; reopening clears the current completion
stamp while retaining change history. Completion does not advance quality checks,
studio approval or release. Photo-only/DR events remain Not required.


Concert Tracker follow-up (27 September 2026): 3.2 exposes authorized source-file
and title-package downloads. 3.3 saves the owner's 20 quality checks individually
as they change, with reviewer/time records and conflict protection. Only actual
admin accounts have Complete all; staff cannot bulk-complete checks. 3.4 consumes
the laptop's complete concert-video manifest and verified-file counts, requires
an explicit Finished Uploading signal, and records which final exports include
studio videos/slideshows. Readiness also requires editing and quality completion.
This is a private review workflow; sending and publication remain separate. See
Concert-Tracker.md for the current MP4 upload size limits and verification.

Studio Hub navigation follow-up (28 September 2026): Invoices shares the Hub
header and tabs and displays current timeline quote/minimum-spend cards per
booking above issued invoices. Overdue action dates are red. Studio Hub is a
distinct navigation button before My Media and My Profile. Profile uses the Hub
tab style and owns Sign out; its extra My Media return link is removed.

Customer navigation follow-up (28 September 2026): My Media has three page tabs:
My Events (galleries, recommendations, Add media), My Purchases (Photos/Videos),
and My Orders (history and receipts). The tabs share Studio Hub/Profile styling
and preserve scroll position. Favourites and Cart remain utility actions.

### Shared account access and school classification (28 September 2026)

On the web, crew members can open personal Media from **Back to Media**, and Media shows a distinct **My Hub** button alongside Studio Hub when applicable. One account can hold customer, crew, studio and Admin access together; adding crew access does not replace their customer records. Crew and customer mobile apps remain separate with app-specific token scopes. Admins can enable My Hub for an existing account from Crew Management, grant Admin access through the crew editor, and manage studio-specific access from the studio editor.

Studio records now support **Organisation type: Dance studio / School**. This identifies schools for future pricing and features, without recalculating existing quotes or invoices. School discount rates remain to be supplied. See [Account access and schools](../handbook/Account-Access-and-Schools.md).

Crew web navigation uses a top row for the logo, return links and profile, with crew menu items spread across the lower row. My editing is renamed **My Tasks** in navigation, headings and return links; its current scope remains assigned concert editing.

The Admin sidebar includes **My Media** directly below **My Hub** for accounts with customer media access, linking to the same signed-in account’s personal media page.

Admin My Hub and My Media shortcuts display the supplied Crew Hub and Media icons at a matching small size, with the My Hub notification badge retained.

### Shared web sign-in (28 September 2026)

Use one `/login` page for customers, crew and administrators, based on the shared customer page heading design with the supplied landscape Media logo image left of the heading and “Sign in to access your DancePro account.” Keep old customer sign-in URLs as compatibility redirects and preserve pending gallery joins, intended destinations, account access checks and two-factor authentication. All new web sign-in links use the shared page; the separate mobile apps retain their existing authentication contracts.

The shared blue account label, heading and description apply to sign-in and sign-up as well as My Media, My Messages, My Profile and Help. Each uses `DPM copy.png` as the proportional heading image. Studio Hub keeps its studio-specific image and notification boxes.

### Gallery headings and navigation consistency (28 September 2026)

- Gallery and studio pages use the shared Media hero: logo image, blue context label, title and description. Directory pages use DancePro Media branding; competition gallery pages use the event logo (competition logo fallback), and concert pages use the studio logo. Missing logos fall back to the Media image.
- Released event cards and studio cards are clickable across their full area using an accessible native link. Unreleased-event registration controls retain their own interaction.
- Switching between Competitions and Concerts preserves the current vertical scroll position, consistent with customer page tabs. Shorter pages naturally clamp to their available height.

### Media header and gallery registration (28 September 2026)

- Admin Hub uses the same highlighted navigation style as My Hub, with a shield icon. All signed-in customer header items use coordinated line icons. The signed-out header contains Media galleries and a highlighted Sign in button last on the right, each with an icon; the duplicate Studios & concerts menu item is removed.
- Competition and concert catalogue cards share the same grid sizing and layout. Upcoming events show Coming soon and a Register now button; the email/notification-consent fields live in an accessible dialog with the event/studio logo, event name and date. The existing one-time gallery-release notification registration rules are unchanged. Dialogs close with Cancel, Close, Escape or a backdrop click, and reopen with entered details after validation errors.

### Studio-first concert browsing (28 September 2026)

- Media galleries → Concerts shows one card per studio in the existing public catalogue. Opening the card shows that studio’s event galleries and a studio-level Register now action.
- Studio registration is ongoing: notify the subscriber once as each new studio event photo gallery becomes available, including events created after registration. Previously released galleries are excluded; unsubscribe stops future studio notifications. Existing event-only registrations remain event-only.
- Competition cards show the event title and date without the repeated organiser/subtitle. Registration pop-ups put the title and date beside the event/studio logo.

### Portfolio gallery type — started, deferred (28 September 2026)

Portfolio is the reserved third type under Media galleries, after Competitions and Concerts. The catalogue recognises its key and label but currently returns no portfolio cards, so the tab is hidden. A direct `?type=portfolio` request falls back to Competitions while unavailable.

This is a placeholder only. Do not treat Portfolio as implemented or expose the tab simply to show an empty page. Resume later to agree the portfolio content, management/publishing workflow, card/detail rendering and access requirements, then connect published content and reveal the tab only when non-empty. No Portfolio database schema, uploader, admin pages or registration/delivery process has been built.

### Admin Message Centre and quick access (28 September 2026)

Admin has five coordinated navy shortcuts: Event availability, Concert tracker, My Hub, My Media and Message Centre. The ordinary top-bar account name is removed; local test-account identification remains. The sidebar places My Hub under Hub management and My Media/Concert tracker under Media management. Studio messages and Customer support no longer appear as separate navigation entries.

The Message Centre at `/admin/messages` combines customer requests/conversations, studio account and email history, and crew email conversations. All/Customers/Studios/Crew page tabs, history filters, recipient search, formatting preview, private attachments and replies reuse the existing delivery systems. Search accepts names, emails and existing account/studio/crew IDs; customer lookup also accepts order display numbers/UUIDs. Customer support decisions/download reactivation and studio conversation ownership remain available. Attachments are downloaded through authenticated conversation links. Raw HTML and unsafe links are stripped from rendered message formatting.

Legacy admin customer-support and studio-message entry routes redirect into this one workspace. Task-specific tracker email pages and customer/crew inboxes remain their own role-appropriate interfaces to the same records. Crew Hub chat remains separate, reached through an Open Crew Hub chat button. Crew email conversations carry an explicit audience tag so accounts with both customer and crew roles keep separate email contexts. Existing email routing, inbound mailbox ingestion and local rehearsal settings are unchanged.

Badges: Availability counts actionable scheduling exceptions; Tracker retains its task attention count; My Hub counts shift/time-sheet/invoice actions; My Media counts unread account notices/replies; Message Centre combines pending customer requests, unread studio messages and pending crew email conversations. Opening studio threads clears their read state; customer and crew email conversations remain actionable until answered or decided. Parent sidebar badges roll up the queues within their new groups.

Account identifiers now follow the same public UUID convention as studios and conversations. Existing database relationship IDs stay intact, and old account URLs remain valid. Account IDs displayed in the admin directory/details are UUIDs; order numbers remain their existing numeric format. Numeric customer search no longer interprets an order number as a second account's internal ID.

### Navigation consistency and live Message Centre search (28 September 2026)

My Media and My Hub use matching compact landscape app-switch buttons (36px minimum height, 24px icons), with the supplied app icon on the left and consistent Admin Hub/My Hub/My Media labels. Admin shortcuts remain square. The Admin sidebar uses the supplied Admin icon with the original dark menu background (#101820), restored following owner feedback; menu badges appear to the left of their icon/title. Message Centre opens on All, with live partial-entry search in inbox and new-message mode. Results update without replacing a message draft. Event Availability filters, keys and bulk selections are collapsed under Advanced options.

### Crew page branding and biography (28 September 2026)

Crew Hub retains the landscape DancePro menu logo. Page headings use the supplied outline Crew Hub artwork. My Profile includes a Biography section after contact details, with collapsed guidance and eight prompts, no external examples link, and a saved text field of up to 5,000 characters.

The biography deadline is calculated as the admin-managed commencement date plus four weeks (28 days), updating automatically when that date changes. An empty biography is overdue from the day after the deadline and shown in red; a saved biography is complete and green. If the start date is missing, show that the deadline awaits a start date rather than using account creation as a substitute. Biography does not block initial onboarding during the four-week writing period.

### Website content editing and publishing (28 September 2026)

Website Management follows Media management in admin, with Pages, Events, Crew
bios and Help & FAQ. The editor uses the established DancePro layout: headings,
text, optional images, section cards with links, visibility and ordering. Drafts
can be saved and previewed; publishing explicitly replaces the public snapshot.
This is a structured editor, not a free-form page designer.

Home, About, Services and public Help share the Media website. Eligible accounts
have Studio Hub, Crew Hub and Admin Hub in a second navigation row. Upcoming
competition and concert scheduling events appear automatically within the next
six calendar months; past and cancelled events are excluded. Admin can manually
hide an event until it is restored. Competition cards show logo, date range and
venue; concerts show one logo per studio and the section is hidden when empty. Crew biographies stay private until admin
approval, and later crew changes require review without replacing published text.
Help articles use the existing customer library with draft/publish/hide controls.
No media storage, gallery access or live website records were changed. See
`docs/handbook/Website-Management.md` and `docs/decisions/Website-Publishing.md`.

The September content refresh expands the live site's service, booking, About
and FAQ details into editable pages, replacing legacy forms, transfer emails,
expired shoot dates and old rates with the current account workflow. Detailed
service, media terms and privacy pages share the same editor. Three labelled
placeholder crew bios live in About's editable sections without creating crew
accounts. See the Website Management handbook for sources and migration rules.

Public website presentation uses the supplied Top Pics collections rather than
Media app icons in headings: large photographs with readable text overlays,
varied editorial sections, photographic service cards and restrained transitions.
Original photographs remain intact; optimised responsive derivatives are public
website assets. Public Help shares this design while signed-in Media Help keeps
the account heading. Editor image uploads override each curated hero. Motion is
optional and respects reduced-motion preferences.

### Public homepage account entry

The primary homepage journey is DancePro account access. Guests see Sign in and
Create an account; authenticated users with Media access see Open My Media and an
Add a studio or event shortcut into My Events. Public gallery browsing remains
available. Account setup guidance precedes separate concert/competition imagery,
upcoming events and secondary organiser enquiries. Photography must match the
service and avoid repeated default images across public pages.

### Family account implementation (29 September 2026)

My Family is the profile management surface for manager/member permissions, email invitations and dancer-only profiles. Accepted members automatically share eligible direct purchases; each keeps a personal favourites/cart and private order/payment history. A parent can upgrade a dancer-only profile by adding email and account permissions. See `docs/decisions/Customer-Family-Accounts.md` for the authoritative eligibility, permission, expiry and one-family-per-account rules. Native management UI and multiple-family/manager-transfer workflows remain follow-up work.


### Seats & families email and Studio Hub entry

Task 1.2 explains the exact Concert events workflow: open a concert card, confirm the unique-family total once across the booking under Your actions, and save row/seat allocations separately for every concert role. Its branded Open Concert events button links to the studio's events tab with actionable event cards expanded. Final family confirmation is available in those cards and returns there after saving. Family/seating due dates use tracker 1.3, which is set to ten days after successful 1.2 delivery. Other uploads retain their separate deadlines. Sent message bodies remain historical snapshots.


### Studio event confirmations and applicable action totals

Every Studio Hub event includes an explicit confirmation of arrival/setup times, start/finish times, date and venue. Incorrect details should be raised promptly through Request changes. Confirmation is stored per event with the user/date and can be unchecked independently of portrait requirements. It shares the existing seats/families deadline (ten days after task 1.2 is sent), without adding a tracker task. The 1.2 email describes this step.

Studio progress counts only applicable studio actions, excluding automatic booking approval and not-required placeholders. Dress rehearsal portraits have two actions: event details and portrait requirements. Concerts with video normally have five: event details, family total, seats, programme and supplied videos/slideshows. Photo-only concerts normally have four. Video review/release joins the count when actionable and remains counted after completion. Family confirmation is shared across the booking; other confirmations are per event.

### Save-position convention

Saving a form should preserve page position and expanded sections by default, including newly added forms. The Studio Hub event workspace automatically applies the shared scroll helper to its forms and restores stable panel IDs, including after successful uploads. Do not require users to ask for this separately for each new form.


### Simple seats and visible save feedback

Reserved seats accept row shorthand such as J16-20 (five individual seats), J16, 18 (two separate seats), lowercase row letters, ranges with typographic dashes and one row per line. The previous Row J: 16, 17 format remains supported. Ranges are bounded; duplicate seats cannot inflate completion counts. Existing per-role limits still apply.

Studio Hub event saves keep the section open and show success feedback beside the save button after the server confirms success. Validation errors appear there in red. Editing clears the success message. Successful uploads use the same feedback after reloading. Page position and expanded sections remain preserved.


### Compact seats/families tracker cell

Task 1.3 keeps the family and role-seat badges, using consecutive seat ranges (for example V: J16-20). Gaps and different rows remain explicit. Beneath the badges, show only the latest relevant editor's name and date across the booking's confirmed family total and this event's seating records, without repeating separate family/role activity logs or times.


Concert-week email (task 1.4) uses the same compact seat formatter as the tracker. Event-detail email blocks list each role on its own line, for example Photographer P1: J15 and Videographer V: J16-20. The shared renderer also keeps other event-detail emails consistent; previously frozen/sent message bodies are retained.
