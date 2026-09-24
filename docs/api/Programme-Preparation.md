# Programme preparation API

Implemented first slice: manual drafts and confirmed snapshots. See
[ADR-0011](../decisions/ADR-0011-Programme-Drafts-and-Confirmed-Snapshots.md).

## Desktop preparation reads

Both endpoints require `auth:sanctum`, the explicit `programmes:read` ability,
an active staff/admin account and a non-cancelled event. Existing wildcard tokens
are not implicitly sufficient. New staff API logins include this ability; older
clients must obtain a newly scoped token through the normal login flow. Customer
and crew accounts do not gain access from the ability alone.

- `GET /api/staff/events/{event_uuid}/programme`: latest confirmed snapshot.
- `GET /api/staff/events/{event_uuid}/programme/source/{revision_uuid?}`:
  protected PDF from that confirmed revision (latest when omitted).

No selected photographer role or crew assignment is needed. This contract does not
yet grant uploader access to ordinary crew accounts; that belongs to the later
module/action permission work.

The snapshot follows `{success, message, data}`. Data fields:

| Field | Meaning |
|---|---|
| `schema_version` | Currently 1 |
| `event_uuid`, `event_code`, `event_name`, `event_type` | Confirmed event metadata; type is competition or concert |
| `programme_uuid`, `version`, `revision_uuid` | Stable programme identity, confirmed draft version and immutable revision UUID |
| `confirmed_at` | Confirmation timestamp |
| `source_uuid` | Retained source identity, nullable for manual preparation |
| `source_url` | Relative authenticated PDF URL pinned to this revision, or null |
| `nodes` | Ordered flat list; reconstruct hierarchy using `parent_uuid` |

Each node has `uuid`, `parent_uuid` (nullable), `kind` and `title`. Optional fields
are `number`, `date`, `session`, `class_name`, `item_kind`, `source_page`. Optional
values may be absent or null. Node order is the saved order; filter by parent and
preserve that relative order when displaying or preparing the hierarchy. Numbers
remain strings so leading zeroes are retained. Gaps are deliberate, not errors.
Do not use array position, display number, event code or filename as identity.

Snapshot event metadata reflects confirmation time. The current scheduling event
remains the source for operational scheduling information. Fetch and retain the
snapshot and its pinned PDF together; do not use the unpinned PDF URL when a
particular revision is required.

HTTP 404 means no programme/confirmed revision/source exists (or the requested
revision belongs to another event). HTTP 403 means insufficient access. HTTP 409
means the event is cancelled. API reads are rate limited to 60 requests/minute
per existing Laravel throttle identity. Responses use private/no-store caching;
authorised clients can deliberately persist the result for offline preparation.
No signed public source link, internal ID, disk path or internal notes are returned.

## Management mutations

All routes below start with
`/admin/scheduling-events/{event_uuid}/programme` and use existing authenticated
admin and two-factor middleware. Cancelled events can be inspected but not edited.

| Method/path | Behaviour |
|---|---|
| `GET /` | Editor; does not create a programme |
| `POST /` | Idempotently start a draft; copy current event PDF when available and seed competition days from shifts |
| `PUT /draft` | JSON `{version, nodes}`; save, increment version and return normalised nodes/version |
| `POST /confirm` | JSON `{version}`; confirm saved draft, idempotent for that version |
| `POST /source` | Multipart `version` and `programme` PDF, up to 20 MB; scan/store, increment draft version, retain old sources |
| `GET /sources/{source_uuid}` | Preview a retained source belonging to this programme |
| `GET /snapshot/{revision_uuid?}` | Inspect a confirmed snapshot, latest by default |

Save/source/confirm reject stale versions with HTTP 409. Draft payload nodes must
be a list of at most 10,000 entries with distinct UUIDs and only the documented
fields. Titles/classes are bounded to 500 characters, session labels to 150,
numbers to 12, and source page references to 1–10,000. Numbers contain digits with
an optional letter suffix. Days/sections/items must have a valid parent within
the same payload. Concert groups and ungrouped items can be roots. A node cannot
change kind or be adopted from another programme.

Drafts can be incomplete. Confirmation requires titles/printed labels on every
row, unique dates for days, competition section numbers unique across the event,
competitor numbers unique within each section, and at least one non-break item.
Leading-zero variants are treated as the same number for conflict detection.
Printed concert numbers may restart across acts. No missing numbers are filled in.

A client saves dirty changes before confirming, uses the returned version for
subsequent mutations, and preserves local edits if saving fails. Replacing a PDF
requires saved draft changes. New source PDFs do not replace ordinary booking or
event attachments. This version provides manual review, not automated extraction.

Existing event image attachments are preserved but are not retained as PDF sources.
Manual preparation remains available; upload a PDF in the editor for a preview.

## Automatic extraction

`POST /admin/scheduling-events/{event_uuid}/programme` accepts an optional
`programme` PDF (same 20 MB validation as replacement upload). With a PDF or an
existing PDF attachment, start queues local extraction. The upload endpoint also
queues extraction for each new retained source. Duplicate start is idempotent;
a PDF submitted after preparation already exists returns 409 rather than being
silently ignored.

`GET /admin/scheduling-events/{event_uuid}/programme/extraction` returns protected
`data.status` and `data.message`, with no source paths or internal IDs. Status is
`queued`, `processing`, `ready` (applied to an unchanged empty draft), `review`
(existing edits preserved), `empty` or `failed`. It uses the existing staff gate.
Queued results are tied to source and draft version. Confirmed data is never changed.

Web drafts autosave; approval uses the existing confirmation endpoint. The worker
runs on the dedicated `programmes` database queue connection. See ADR-0012 for
runtime/resource limits and the extraction milestone for sample results.

### Concert folder sequence

Newly approved concert item nodes include server-derived `folder_number`: a string
with minimum width two (`01`, `02`, … `99`, `100`). It follows the displayed running
order across the whole concert, including legacy group traversal. Break nodes get
`null` and do not consume a number. `number` remains the original printed number;
competition numbering is unchanged. Folder numbers are not accepted as draft input.
Use `uuid` for identity; moving an item changes its folder number, not its identity.

Previously confirmed revisions remain immutable and may lack this field. Explicitly
reapproving a legacy concert creates a new numbered revision; subsequent approvals
of that same numbered version remain idempotent. Actual folder creation remains
uploader work and has not been implemented by this change.

### Concert folder titles

New approvals also include `folder_title` on concert item nodes, separate from
`title`. Use `title` unchanged for display and video title cards. For folders,
`folder_title` replaces `/` with a space, removes punctuation/symbols, collapses
whitespace and trims it. Unicode letters, combining accents and numbers remain.
For example, `Rock / Roll!` has `title: "Rock / Roll!"`, `folder_number: "01"`
and `folder_title: "Rock Roll"`. Break nodes have null folder fields.

The editor shows a read-only Folder title preview under More. Saving/editing does
not remove punctuation from `title`. A non-break item whose cleaned folder title
is empty cannot be approved. Folder fields are generated server-side, never trusted
from draft input. Reapproving an older numbered snapshot lacking `folder_title`
creates a new revision and preserves the older display/title-card text.

## Mac event discovery

`GET /api/staff/programme-events` requires an active administrator and explicit
`programmes:read` token ability, matching the snapshot/source routes. Wildcard
legacy tokens and ordinary crew/customer accounts do not grant this access.

Optional query fields: `search` (event name, maximum 100 characters), `from`
(`YYYY-MM-DD`, inclusive), `page` (positive), `per_page` (1–100, default 50).
Only uncancelled events with at least one confirmed revision are returned.
Order is event date then programme ID. `data` is a list of `uuid`, `name`,
`event_type`, nullable `event_date` and `event_code`, latest `revision_uuid`,
`confirmed_at`, `item_count` excluding breaks, `has_pdf`, `preparation_ready`.
`meta` includes `current_page` and `last_page`. Responses are private/no-store.
No database IDs, internal notes, draft nodes or source storage paths are exposed.

Old concert revisions missing folder numbers/titles report
`preparation_ready: false`; explicitly reapprove them to create a new immutable
revision. The native Mac app downloads the snapshot and its pinned source URL,
then saves local files and stable identity mappings. Discovery is not crew event
assignment or a guarantee that a later download is still authorised.

## Working programme sync

`POST /api/staff/events/{event_uuid}/programme/sync` requires an authenticated
administrator and `concert-media:upload`. Send `revision_uuid` and `nodes` using
the Mac programme node contract, including optional `merged_into_uuid`,
`folder_number` and `folder_title`. Titles retain punctuation. Original identities
and item types must remain; concert additions/aliases are allowed. Competition
nodes must match the approved revision. No client paths or storage destinations
are accepted. Requests are limited to 30/minute and 10,000 nodes.

The success envelope contains `data.uuid` (immutable upload plan UUID),
`event_uuid`, `revision_uuid`, and `digest`. Identical retries return the same
plan. The endpoint saves only a working programme, never media or approval state.
Stale/foreign revisions return 409, invalid structure 422, and unauthorized or
cancelled events 403. Validation also rechecks cancellation inside the transaction
(409 if cancellation happens after authorization). See ADR-0017 for plan scope
and the remaining contribution/transfer integration.

## Published photographers

`GET /api/staff/events/{event_uuid}/programme/photographers` requires the existing
administrator programme policy and `programmes:read`; rate limit 60/minute.
Returns an event UUID, checked-at timestamp, has-shifts flag, photographers grouped
by public crew/role UUID with published assignment/shift UUIDs and dates, plus
unfilled photo-role quantities per shift. No contact, financial or private profile
fields are returned. Null concert periods use `concert`. Cancelled events return
409. Missing access returns 401/403.

Use existing role codes for competition/concert P1/P2 and dress-rehearsal P.
Exclude draft assignments, inactive roles/users and deleted crew profiles.
Shift quantities override event role defaults. Requirements remain visible as
unfilled even when no active published assignment can fulfil them. The response
is informational; it neither grants upload permission nor certifies completion.

## Register queued photo contributions

`POST /api/staff/events/{event_uuid}/programme/photo-contributions` requires
administrator programme-update policy and `concert-media:upload` (30/minute).
Body: `plan_uuid`, `crew_uuid`, `role_uuid`, `item_uuids` (distinct UUID list,
1–10,000 active performance items). The plan must belong to this event and its
current approved revision. Photographer/role must have a current active published
assignment; competition items also require an assignment on their programme day.

The success envelope contains `event_uuid`, `plan_uuid`, `crew_uuid`, `role_uuid`
and `contributions`, each with a public `uuid`, `item_uuid`, and `status` of
`awaiting_files`. Repeated registration reuses IDs. A mixed invalid batch writes
nothing. Retired/foreign/nonperformance items return 422; stale revisions or lost
assignments return 409; a foreign plan returns 404; unauthorized/cancelled policy
checks return 403. As with sync, cancellation is also rechecked inside the action.

Registration creates no assets or file receipts and grants no publication or
completion state. Expected missing P2 contributions are not waived by P1 registering.
See ADR-0018 for historical assignment references and transfer-time revalidation.

## Reserve exact photo files

`POST /api/staff/events/{event_uuid}/programme/photo-files` requires administrator
programme-update policy and `concert-media:upload` (60 requests/minute). Send
`plan_uuid`, `crew_uuid`, `role_uuid`, and `photos` (1–500). Each photo contains
`photo_uuid`, `item_uuid`, `file_name` (JPEG basename), `byte_count` (4–536870912),
`sha256` (64 lowercase hex characters), and `is_top_pic` (boolean). Photo UUIDs must
be unique within a request. Paths and arbitrary storage destinations are rejected.

Returns event/plan/crew/role UUIDs and `files`, each with `uuid`, `photo_uuid`,
`item_uuid`, `contribution_uuid`, `sha256`, `byte_count`, and `status: reserved`.
The endpoint revalidates current contribution eligibility even on retries. Identical
reservations reuse IDs. A conflicting size for an existing identity/hash is 409;
a bad descriptor is 422. The whole batch is transactional. File content is not
accepted here and client hashes are not treated as upload verification.

## Transfer and verify a reserved JPEG

`PUT /api/staff/events/{event_uuid}/programme/photo-files/{file_uuid}/content`
requires administrator update policy, `concert-media:upload` and `Content-Type:
image/jpeg` (120 requests/minute). The body is the original JPEG bytes. Both the
pre-read and post-read checks validate current event/revision/photographer scope.

The first successful file creates a private scheduling-event photo gallery pinned
to its upload plan. Another plan for that event returns 409; existing photos remain
unchanged. The server verifies size/hash, JPEG decoding and stored bytes before
returning `uuid`, `photo_uuid`, `event_uuid`, `plan_uuid`, `gallery_uuid`, `item_uuid`,
`contribution_uuid`, `sha256`, `byte_count`, `status: verified`, `visibility: private`.
No storage paths or public URLs are returned. Retrying the same file is idempotent.
A changed previously uploaded photo version/item returns 409. Invalid JPEG or byte
claims return 422. Disabled transport or storage failure returns 503.

The first verified file moves its contribution to receiving, not complete. File
reservation responses may now return reserved or verified for replay; neither is
a substitute for a validated transfer acknowledgement on the Mac. Customer
publication, contribution completion, waivers and cloud delivery remain separate.
This transport currently writes private local storage and is default-enabled only
for local/testing. See ADR-0020 for deployment constraints and recovery boundaries.

## Explicit photographer completion

`POST /api/staff/events/{event}/programme/photo-completion` requires the existing
staff programme permission and `concert-media:upload` ability. Body: `plan_uuid`,
`crew_uuid`, `role_uuid`. The server derives the matching active items, including
empty items, from the current plan and published photographer shifts. It rejects
stale assignments/revisions, a conflicting active gallery plan, or any unverified
reservation across the selected contributions. It does not accept a client list
of items to omit from completion.

The response includes event/plan/crew/role UUIDs, `status: complete`,
`contribution_count`, `verified_file_count`, `completed_at` and `visibility: private`.
Repeated completion is idempotent. New unverified reservations reopen the affected
contribution; replaying an already verified file preserves completion. No publication,
P2 waiver or HOLD change occurs. Unsent reservations need transfer or a future
explicit administrative resolution. See ADR-0021 for cross-Mac declaration scope.

## Current server progress

`GET /api/staff/events/{event}/programme/photo-progress?plan_uuid={uuid}` requires
staff programme access and `programmes:read`. It is read-only, never registering
items, finishing contributions or changing visibility. Results are scoped to the
requested event/plan and include a server `checked_at`, `plan_current` flag,
`visibility: private`, photographer summaries and unfilled shift roles from Crew
Management. No file paths or storage URLs are returned.

Each published photographer/role shows expected and completed item counts, verified
and pending file counts, and `missing`, `receiving`, `complete`, `no_items` or
`needs_attention`. Completion requires a current plan, matching current assignment
identities, explicit completion and no unverified reserved files. Competition items
are limited to assigned programme dates. Unfilled role quantities remain visible
independently of a photographer's completion. Counts are snapshots across Macs;
refresh after changes. They do not prove that an offline Mac has no unsent files,
and are not a customer release decision or administrator waiver.

## Event-role identity (supersedes crew-bound contribution identity)

`GET /api/staff/events/{event}/programme/upload-roles` returns configured photo/video
roles, shift coverage, `identity_version: 2`, and `default_role_uuid`. A concert
returns a default only when the signed-in actor has one unambiguous published role.
Competitions require explicit station choice and return no automatic default. The
`photographers` collection alias contains the same roles for prepared-cache decoding;
it no longer identifies individual crew members. The older `/photographers` endpoint
remains the staffing view for compatibility.

Contribution registration, reservation and completion require `plan_uuid` and
`role_uuid`; legacy `crew_uuid` is optional and is not used for ownership or access.
Contribution identity is plan/item/role. Staffing changes do not reset completion.
Photo endpoints reject video roles. Current event-role configuration and shift/day
coverage are validated independently from named staffing assignments.

Active crew accounts receive programme-read and photo-upload abilities on login.
Access is limited to events with their published assignment; an authorised event
crew member can upload for another configured role at that event. Programme approval
and management remain administrator-only. Role selection never grants event access.
Existing crew sessions may need a fresh login for the new abilities.

Actual first successful receipt actors are stored on file records; upload and role
completion actions also write programme_upload_audits. Progress includes uploader
submission/duplicate counts. Historical upload actors are unknown unless recorded;
registered-by is never relabelled as uploaded-by. The role migration archives every
original contribution before consolidating its files into canonical role records.
File UUIDs and paths remain intact. Original contribution UUIDs remain on files.

Replacement copies with new local UUIDs are verified by bytes, then reuse existing
storage for the same plan/item/role/SHA-256/size. Their receipt records retain the
new actor and duplicate link; progress counts the stored photo once. Bytes are still
transferred for validation. Different roles/items are not automatically deduplicated.
Migration rollback requires a coordinated backup restore; automatic down cannot
safely invent crew ownership for new role-based work. It never deletes photo files.


## Administrator photo exceptions

Session-authenticated administrator portal routes:

- GET `/admin/scheduling-events/{event}/photo-uploads`: optional `plan_uuid` query;
  defaults to the gallery plan or latest synced plan. Shows progress and audit history.
- POST the same path: `plan_uuid`, `role_uuid`, `action` (`waive` or `reinstate`),
  `reason` (trimmed, 5–2000 characters), optional `scope_uuid` from that plan.
  Omitting scope applies to all active items for this photo role. A day/section/group
  scope resolves its descendant items; item scope applies only to that item.

Both routes require administrator programme-update permission. Stale revisions or
conflicting gallery plans reject changes with 409; foreign plans return 404 and
invalid/no-item scopes return 422. Completed items are never relabelled waived.
Audit actions are contribution_waive, contribution_reinstate and waiver_reopened.

Photo progress includes `waived_item_count`; statuses add `waived` and
`complete_with_exceptions`. Waived files still appear in pending counts. Completion
checks non-waived reservations, leaves waived items unchanged and includes
`waived_item_count` in its acknowledgement. Later new reservations or first receipt
of an older reservation reopen the affected waiver. No files are deleted or published.
See [ADR-0023](../decisions/ADR-0023-Photo-Contribution-Exceptions.md).


## Shared concert programme

GET `/api/staff/events/{event}/programme/shared` requires programmes:read and event
upload access. Returns event_uuid, current revision_uuid, nullable plan (uuid,
event_uuid, revision_uuid, digest) and nullable programme snapshot. Read-only,
private/no-store; no notification or plan creation.

For concerts, POST `programme/sync` establishes the first shared plan. Different
subsequent content requires optional base_plan_uuid equal to the current shared
plan, and is rejected with 409 after any contribution is registered. Identical
content retries reuse the receipt. Accepted changes and crew chat notifications
commit together; stale clients cannot reserve media against superseded plans.
Competition sync behaviour is unchanged in this step.

GET `/crew/chat/events/{event}/programme-updates` is session-authenticated and uses
the existing published-assignment event chat policy. Returns the latest 50 automatic
programme messages, oldest first, with public uuid, author name, body and created_at.
It does not mark unread messages read. The browser polls this endpoint at five-second
intervals while visible. Existing chat/mobile message delivery remains available.


## Competition capture folders and additions

Competition `programme/sync` now accepts added sections and competitors alongside
unchanged approved nodes. The canonical base plan UUID/digest does not change;
additions live in competition_programme_additions and extend the effective node set.
Syncing an older inventory never deletes existing additions. Names on approved nodes
must remain unchanged; observed name differences use the inventory endpoint.

Additional UUIDs use UUIDv5 with programme_uuid as namespace:
`competition/section/{normalized_number}` or
`competition/item/{lowercase_section_uuid}/{normalized_number}`. Normalization removes
leading zeroes and lowercases the suffix, retaining 0 for an all-zero value. New day
identities are not accepted: extra sections must belong to an approved day. Total
base/addition nodes remain capped at 10,000.

- GET `.../programme/competition-folders?revision_uuid=...`: programmes:read plus
  event upload access; read-only comparison across configured P1/P2/V roles.
- POST `.../programme/competition-folders`: concert-media:upload plus event upload
  access. Send revision_uuid, nodes, role_uuid, device_uuid and folders (list of
  node_uuid/title observations). Empty folders is a valid checked-but-empty report.
  Device identity is persistent per Mac; this endpoint transfers no media bytes.

Response: event_uuid, revision_uuid, checked_at and items. Each section/item includes
uuid, kind, date, section_number, number, title and roles. Role states include
role_uuid, role_name, status (matched/name_difference/not_seen/not_checked), observed
names and checked_at. Names are compared case-insensitively after punctuation and
whitespace normalization. Folder presence is independent of photo/video upload
verification. Notifications are issued only for changed/additional observations.
Invalid roles, unknown observations or stale revisions leave no partial writes.

Contribution registration, expected counts, completion and waivers include added
items. They retain their original plan/gallery references and verified photo paths.
See ADR-0025 for local discovery rules and migration rollback constraints.

### Private competition gallery browser

Administrators open **Media → Competition Photos** in the management portal, then
choose an event. `GET /admin/media/competition-photos` lists active competitions
with pagination. Gallery and upload management stay in Media, separate from Event
Details planning.
`GET /admin/scheduling-events/{event}/competition-gallery` accepts an optional
`folder` UUID and positive `page`. One-day events list sections directly; multi-day
events list numbered weekdays first. The stored gallery plan and registered additions
supply stable folder identities; before a gallery exists, the current approved
programme/working plan supplies the structure. A later approved revision does not
silently move stored photos, and the view displays a reconciliation notice.

`GET /admin/scheduling-events/{event}/competition-gallery/photos/{photo}` serves a
verified canonical photo only within that event's gallery and upload plan, behind
the same admin authorization. The current transport is private local storage.
Responses are private/no-store, and the browser never receives storage keys. Both
roles appear in the same paginated album, without replacement-copy receipts.
These are administrator previews of originals, not public/customer delivery routes;
customer previews, watermarks and section release remain pending.

The competition photo image route now returns **watermarked derivatives only**.
Use `?size=thumbnail` for a 480-pixel longest edge or `?size=preview` (default) for
1600 pixels; other values fail validation. EXIF orientation is applied and metadata
is stripped. The original is never returned as a fallback. Original byte storage
is unchanged. First access generates a private cached derivative; later access
still requires admin authorization and matching event/gallery/plan. No customer
publication or original download endpoint is introduced. See ADR-0026.

### Section contribution readiness in Competition Photos

The private gallery now displays per-section readiness, with role-specific expected,
completed, waived, outstanding, verified-file and pending-file counts. The selected
competitor also displays its parent section's readiness. Refresh recomputes it from
the current shared additions and role/date scope; no new API endpoint is introduced.

States: Contributions ready, Ready with exceptions, Waiting for photographers,
Programme needs reconciliation, Photo roles need setup, and No competitors.
Readiness requires explicit timestamped completion plus verified canonical receipts
in the same gallery, or explicit timestamped administrator waivers for every required
competitor/role. Waivers can resolve pre-existing pending reservations, so waived
work is shown separately. New receipts reopen waivers through the existing upload
flow. Duplicate receipts and video roles are excluded. Inactive planned photo roles
require configuration review rather than silently disappearing from readiness.

These are read-only summaries, not persisted release decisions. No customer access
or publication is granted. A later publication action must recheck under its own
transaction; never trust a previously rendered readiness label.

### Competition gallery settings

`GET /admin/scheduling-events/{event}/competition-gallery/settings` displays the
private admin settings editor and latest ten change records. POST to the same path
requires `version` and `action`: `title` with optional `display_title` (255 characters,
blank restores event name), `cover` with a verified own-gallery `photo_uuid`, or
`reset_cover` to restore the event logo. Successful writes redirect to settings;
stale versions return 409 and invalid photo choices return validation errors.

Settings do not mutate planning names, programme identity, original files or release
state. Optional cover images use private watermarked thumbnails. Missing/ineligible
selections fall back to event artwork. See ADR-0027.

### Admin-only Customer Preview

`GET /admin/scheduling-events/{event}/competition-gallery/customer-preview` accepts
optional `folder` and `page`, and renders only sections currently ready according to
the existing contribution rules. Empty/unready days are omitted. Direct access to an
unready section or competitor returns 404. Page responses are private/no-store and
noindex/nofollow. Internal role counts, upload management and exception controls are
not rendered in the customer-style content.

`GET /admin/scheduling-events/{event}/competition-gallery/customer-preview/photos/{photo}`
accepts the same thumbnail/preview sizes as the admin image endpoint and additionally
requires the photo's active competitor section to be ready now. Recheck on every
request, including cached derivatives; reject stale/foreign/pending/retired media.
Ready selected covers use this route; other covers fall back to the event logo.

Both routes remain admin-only. They grant no customer access or publication, and are
not substitutes for future event-code registration/account authorization. Sections
become eligible automatically; there is no manual section-release mutation endpoint.
