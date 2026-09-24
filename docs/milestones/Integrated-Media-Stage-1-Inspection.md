# Integrated media: Stage 1 inspection

## Status and scope

Initial source inspection: 21 September 2026, branch
`management-hub-mobile-api`. Working tree was clean before this documentation
work. This is a source-based implementation map, not a live-system certification.
No application code, database, external service or other repository was changed.
Stage 1 remains open for remaining app compatibility checks. The owner has
clarified that locating the older approval source is no longer required.

Direction: [product plan](../specifications/Integrated-Media-Product-Plan.md).
Sequence: [roadmap](../Roadmap.md).

## Repository and application inventory

| System | Located evidence | Assessment |
|---|---|---|
| Management/API | Current repository; `composer.json`, `resources/views`, `routes/web.php` | Laravel 13, Blade web interfaces, Vite/Tailwind, Sanctum; retain feature-based architecture |
| Desktop uploader and older Flutter portal | `/Users/davidmueller/Documents/dev/uploader_flutter` | Flutter package with desktop uploader, concert portal/admin and separate crew mobile implementation; read its `AGENTS.md` before future work |
| Photo capture | `/Users/davidmueller/Documents/DancePro Photo Capture` | Swift/Xcode project; `EventFolderManager.swift` and folder views show event/section/entry folder selection and local capture destination management |
| Video recorder | `/Users/davidmueller/Downloads/DancePro Video Recorder.app` | App bundle found; source project and file-completion contract not yet located |
| Older live Laravel concert delivery/approval | Not located in bounded local source searches | Owner clarified it is no longer used; no further source search required |
| Customer phone app | No complete customer app established by inspection | Existing Flutter `lib/mobile/` is crew-focused; customer technology/platform decision remains open |

Searches covered source manifests within three levels of Documents, Desktop and
Downloads, plus named DancePro/dev folders. Failure to find a repository there
does not establish that it does not exist elsewhere. Other repositories were
read only and their builds/tests were not run.

## Reuse and gaps

Paths below are relative to this repository unless stated otherwise.

| Area | Implemented evidence / reuse | Missing or different from the new plan |
|---|---|---|
| Events and crew | `app/Features/Scheduling/Models/SchedulingEvent.php`, shift assignments, role requirements; admin event/crew screens | Event code is computed by `eventCode()` from organiser/studio code and date. Proposed: store the event code. Follow-up inspection confirms an event already spans multiple days through dated shifts; reuse that relationship. Crew role requirements are not media contributions. |
| Studio/contact directory | `Studios/Models/Studio.php`, `StudioContact.php`, booking contact reconciliation | Contact role/emails do not constitute authenticated director permissions; need explicit grants and action-level restrictions. |
| Bookings and concert links | `Bookings/Models/ConcertBooking.php`, `ConcertBookingItem.php`; `Concerts/Models/Concert.php`; ADR-0009 | Booking has several items; item links scheduling event and concert. Preserve these. Selected photo release groups and multi-concert access-code groups are separate missing concepts. |
| Programmes | Protected programme attachment fields on booking items/events; concert programme copying via `Concerts/Actions/SaveConcert.php` | No confirmed structured days/sections/routines or extraction/review pipeline found. Attachment upload is not programme preparation. |
| Concert access/playback | `Concerts/Controllers/PublicConcertController.php`, `UnlockConcert`, `ResolveConcertPlaybackSource`, public Blade pages | Session unlock, availability/approval gates, HLS/progressive playback and downloads exist. Director checklist/corrections, payment gates and group-code access clocks are not established. |
| Approval | `Concerts/Actions/SaveConcert.php` records staff approval actor/time; public availability checks approved state | Staff approval flags are not director review, correction rounds, explicit release, or earlier/final-concert payment rules. Preserve current public gate while extending later. |
| Media catalogue | `Media/Models/MediaCollection.php`, `MediaAsset.php`, `MediaAssetLocation.php` have public UUIDs | Collections are flat and current creation/reservation actions force video under a concert. A `competition_id` field alone is not a competition domain. Need gallery hierarchy, routine links and photo support. |
| Upload server | `Media/Actions/ManageMediaUploads.php`, `ManageMediaCatalogue.php`, `RecordMediaIngestEvent.php`; upload records, idempotency, multipart checksums, HLS verification, recovery | Reuse verification mechanisms, not current concert-only release assumptions. Need contributor completion, routine batch gates, unresolved/HOLD state and private unmatched intake. |
| Downloads | `Downloads/Models/DownloadLink.php`, `DownloadAccess.php`, generic tracked redirects; competition object browser | Concert controller still uses its own signed download route. Consolidation and order fulfilment remain work; do not assume whole-order ZIP/expiry/reactivation are implemented. |
| Customers/orders | `Customers/Models/CustomerProfile.php`, `Orders/Models/Order.php`, `OrderItem.php`, `ConcertAccessGrant.php` | Foundational records, not complete checkout. No full family sharing, guest claim, owned-routine products, upgrades or payment workflow established. |
| Permissions/history | Media policies, explicit API abilities, `MediaIngestEvent`, time-entry audits | Media policy relies on `canAccessAdmin()` and concert ownership; not the configurable module/action matrix. Existing audit mechanisms have limited domain scope. |
| Invoices | `Timesheets/Models/CrewInvoice.php`, crew financial screens | Crew invoices are not studio concert invoices. Need booking-level studio billing, final-family numbers, deposits and payment reconciliation. |
| Tracker | Existing crew operational checklists, reminders, exception screens | Not the cross-module scoped Tracker. Need source-derived tasks, booking/group/event scopes, relative deadlines, notes and follow-ups. |

Feature paths abbreviated in the table are under `app/Features/`.

## External app findings and preservation requirements

The Flutter admin concert screen (`lib/admin/concerts/concert_page.dart`) contains
programme editing, an Approved checkbox and generation of a temporary download
page link. Its concert model includes programme, approval and ZIP URL fields.
This establishes useful compatibility requirements, but not the director checklist,
correction submission or programme-in-ZIP implementation. The owner subsequently clarified that the old system is no longer used: the
required baseline is an Approve button on the video page and an emailed checklist.
There is no requirement to recover the older backend before proceeding.

Flutter `lib/just_upload/upload_controller.dart` and `models/upload_job.dart`
provide multipart queue states and part tracking. In the inspected path, jobs
are held in an in-memory list, IDs derive from time and object keys from filenames.
This is not evidence of restart-safe queues or stable media identity. The new
uploader needs a persistent manifest and must use the verified Laravel ingest
contract. Audit all uploader paths before choosing what to retain. Do not alter
the crew mobile shell as a side effect of desktop/customer work.

Keep capture focused: agree folder and completed-file contracts with the Swift
capture and recorder apps, rather than moving the full media workflow into them.

## Existing test evidence

Relevant suites are present:

- `tests/Feature/Bookings/ConcertBookingWorkflowTest.php`
- `tests/Feature/Admin/AdminStudioConcertManagementTest.php`
- `tests/Feature/Admin/AdminAccessBoundaryTest.php`
- `tests/Feature/Concerts/PublicConcertExperienceTest.php`
- `tests/Feature/Concerts/ConcertAccessAndCommerceModelsTest.php`
- `tests/Feature/Media/MediaIngestApiTest.php`
- `tests/Feature/Media/MediaIngestRecoveryTest.php`
- `tests/Feature/Media/HlsFinalizationTest.php`
- `tests/Feature/Media/LocalMediaDevelopmentTest.php`
- `tests/Feature/Downloads/DownloadLinksTest.php`

Recovery tests explicitly cover lost multipart completion responses and rejecting
unverified objects. Public concert tests cover unlock, availability, playlists,
signed downloads and playback fallbacks. Test presence is not a passing result.

Initial sandbox attempt at `./vendor/bin/sail artisan test` reported
`Docker or Podman is not running.` Follow-up confirmed Docker runs in Colima
and the sandbox could not access its socket. Re-running Sail with socket access
succeeded: **435 tests passed, 3,674 assertions**. No routes or PHP formatting
changed in this documentation milestone.

## First manageable implementation milestone

[Event and programme foundations](Integrated-Media-Event-Programme-Foundations.md):
link media preparation to existing scheduling records and introduce stable
confirmed programme identities. The owner subsequently deferred stored-code
changes; preserve the current calculation for this slice. Defer automated extraction
until representative PDFs have been inspected. This allows subsequent uploader
preparation without prematurely implementing commerce or replacing concert access.

## Remaining Stage 1 work

- [x] Read repository instructions and agreed product direction.
- [x] Map Laravel records, routes/screens, permission boundaries and test coverage.
- [x] Locate Flutter uploader/portal and Swift photo-capture source.
- [x] Resolve legacy approval dependency: owner confirmed Approve button plus
      emailed checklist; older backend inspection is no longer required.
- [ ] Locate recorder source and confirm unfinished/completed-file behaviour.
- [ ] Complete desktop ingest compatibility review and choose Mac uploader reuse approach.
- [ ] Confirm customer phone platforms/technology before Stage 5 work.
- [x] Inspect supplied competition/concert programmes: 16 PDFs; see
      [sample findings](../specifications/Programme-Preparation.md). Production
      extraction tool selection and accuracy benchmarking remain later work.
- [x] Run the Laravel baseline suite through Sail/Colima: 435 tests passed.

These gaps do not authorise changing settled pricing or stopping unrelated
foundation planning. The legacy source was not inspected; that dependency was explicitly removed by
the owner, not marked as verified.
