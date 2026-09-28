# Booking to concert delivery audit

Audit date: 27 September 2026. Scope: concert and DR Portrait bookings, Studio Hub, scheduling, tracker, communication, media preparation, approval, release and financial follow-up.

## Assessment

The individual building blocks are substantial, but the journey is not yet a complete self-service production workflow. The main risks are inconsistent state between pages, missing links between scheduling and customer media, and tasks which look complete without the corresponding business action having happened. Fix these connections before adding more screens.

This is a source, route, configuration and automated-test audit. The browser session available for this audit stopped at sign-in; it is not a claim that a fresh booking was manually walked through every authenticated screen. No real booking, media release, payment or outbound email was created. Findings marked below are confirmed implementation gaps; recommended behaviour is proposed work, not an implemented fix.

## Journey map

| Stage | Present | Gap or handoff |
| --- | --- | --- |
| Studio makes booking | Profile prefill, repeatable events, service requirements, duplicate protection, quote and original-form snapshot | No immediate booking-received email/staff notification in the submission action; standalone guest receipts depend on the original session |
| Admin reviews | Contact reconciliation, proposed venue resolution, individual/bulk approval, cancellation | Approval does not consistently use the booking's saved studio association |
| Scheduling | Draft events/shifts, availability, roles, roster publication and crew acknowledgements | Service edits do not rebuild role requirements; changes require more than closing a request |
| Studio preparation | Seats, portrait acknowledgements, programme and studio-file uploads, messages | Final family numbers lack a structured submission; programme/file completion differs between Hub and tracker |
| Production | Programme preparation, verified uploader files, manual edit/quality/hold tasks | Customer concert record must be created/linked separately before photo sync and delivery |
| Photos | Private albums, covers, release groups, customer galleries and tracked purchases/downloads | Release does not enforce photographer completion; minimum-spend follow-up is only an estimate in the booking plan |
| Video review | Uploads, staff approval field, approval email text | No dedicated director review/checklist/corrections/Approve journey |
| Video delivery | Publication/availability checks, code groups, customer registration, streaming/download windows | Tracker's release status is narrower than actual customer availability; payment/review gates are not integrated |
| Financial close | Indicative booking quote, manual Invoice/Payment tasks | No booking-level final invoice/payment ledger, deposit workflow or minimum-spend reconciliation |

## Priority findings

### 1. Carry studio identity through approval — high, confirmed

`BulkUpdateConcertBookingItems` calls `approveItem` without a studio. `ApproveConcertBooking` creates the event with `$studio?->id` and does not fall back to `booking.studio_id`. Bulk approval therefore creates an unassigned scheduling event even for a booking submitted from a known Studio Hub. Individual review still defaults to a studio-name match through `ReviewBookingStudioContact`, rather than the saved association.

Impact: the saved timeline can remain visible while the confirmed event is absent from the same studio's Hub; gallery setup also loses a dependable studio link. Prefer the authorised stored studio, require explicit reconciliation for conflicts, and apply the same rule to individual and bulk approval.

Evidence: [bulk approval](../../app/Features/Bookings/Actions/BulkUpdateConcertBookingItems.php), [approval](../../app/Features/Bookings/Actions/ApproveConcertBooking.php), [studio matching](../../app/Features/Bookings/Services/ReviewBookingStudioContact.php).

### 2. Use one progress calculation for admin and studios — high, confirmed

The tracker derives photo release, video upload, director approval and final release from media/concert records. Studio Hub instead looks up stored `ConcertTrackerTask` rows for photo release, director approval and final release. Those source-driven states need not have matching completed task rows. Hub also calls the video milestone “released for approval” while reading the approval-completed task. Portrait photo delivery is omitted from its photo-release milestone, which is restricted to concert items.

Impact: staff can see Released while the studio still sees Waiting or has no gallery link. Share a progress service, keeping separate states for review available, director approved, and customer released. Include DR Portrait delivery.

Evidence: [tracker source state](../../app/Features/Tracking/Services/ConcertTracker.php), [Hub progress](../../app/Features/Customers/Services/StudioCommunicationHistory.php).

### 3. Build the actual director review journey — high, confirmed missing feature

The approval email instructs directors to submit corrections and press Approve on the video page. No corresponding director approval route was found. `SaveConcert` records the staff user as approver, and the admin form labels this “Require staff approval before release”. The public concert page first requires `isPubliclyAvailable()`, including approval when required, so it cannot provide the missing private pre-release review journey.

Add an authorised review page, identifiable media revision, checklist, correction requests, resubmission and recorded director sign-off. A changed video must have an explicit review/approval policy. Keep director approval separate from admin publication. Generate the actual review link in the email; the renderer currently has no approval-link substitution.

Evidence: [concert save](../../app/Features/Concerts/Actions/SaveConcert.php), [public concert](../../app/Features/Concerts/Controllers/PublicConcertController.php), [email template](../../database/migrations/2026_09_24_000003_import_concert_email_templates.php), [renderer](../../app/Features/Tracking/Services/ConcertTracker.php).

### 4. Make Event timeline current while retaining the original submission — high, confirmed

The saved Event timeline reads `submission_snapshot`. This correctly preserves the original request, but it does not reflect later date/service changes, cancellation, completed tasks, revised deadlines or actual release dates. Its introduction still says DancePro will review the request, even after approval.

Keep “View submitted form” immutable. Make Event timeline the live plan with current booking status, last update, task completion, due dates and direct action links. Clearly distinguish the original quote from a revised/current quote.

Evidence: [saved-plan service](../../app/Features/Bookings/Services/StudioBookingPlans.php), [timeline view](../../resources/views/public/concert-bookings/thanks.blade.php), [snapshot builder](../../app/Features/Bookings/Services/ConcertBookingSummary.php).

### 5. Finish final-number, invoice and payment handoffs — high, confirmed missing feature

Bookings store `approximate_family_count`; the tracker offers manual “Final numbers received”, “Invoice” and “Payment received” tasks. The Hub has no structured final-family-number action, and no booking-level studio invoice/payment model was found. Customer media orders and crew invoices are different workflows and should not be reused as if they were studio booking invoices.

Add estimated versus confirmed family numbers, who confirmed them and when, an auditable final quote, one invoice per booking, due date, payment references, partial payments and optional deposits. Start with manual recorded payments if accounting integration remains undecided. Implement the agreed rule: earlier concerts may be reviewed before payment; final-concert approval access is blocked until payment. Current timeline wording implies payment is required for all approval access and needs correcting.

Evidence: [booking model](../../app/Features/Bookings/Models/ConcertBooking.php), [task catalogue](../../app/Features/Tracking/Support/ConcertTrackerTaskCatalog.php), [agreed rules](../specifications/Integrated-Media-Product-Plan.md).

### 6. Make customer-media setup an explicit guided handoff — high, confirmed

Booking approval creates scheduling events and shifts, not customer Concert records. `ConcertPhotoGallerySetup::sync` refuses to continue without a linked customer concert. Tracker buttons fall back to the general concert list when one is missing.

Add a contextual “Set up concert media” action that pre-fills studio, event title/date and booking-item association, or safely creates the draft record during approval. Do not publish it automatically. Staff should not have to reconstruct these links from several menus.

Evidence: [approval](../../app/Features/Bookings/Actions/ApproveConcertBooking.php), [photo setup](../../app/Features/Media/Services/ConcertPhotoGallerySetup.php), [tracker links](../../app/Features/Tracking/Services/ConcertTracker.php).

### 7. Strengthen release readiness and its wording — high, confirmed

Photo release checks that each selected album contains an available visible photo, but does not check whether required photographer contributions are complete. That completion exists separately in the tracker. Decide whether an incomplete release is prohibited or is an explicit audited partial-release override.

The final-video task turns complete from `status=published` and `published_at`; actual public availability also depends on enabled state, approval and availability dates, with code-group readiness relevant to account delivery. Show “Published, access blocked” with the reason instead of implying customers can use it. Link to a customer-access check before final handover.

Evidence: [photo release](../../app/Features/Media/Services/ConcertPhotoReleases.php), [tracker](../../app/Features/Tracking/Services/ConcertTracker.php), [concert availability](../../app/Features/Concerts/Models/Concert.php), [code groups](../../app/Features/Concerts/Actions/ManageConcertCodeGroup.php).

### 8. Apply changes, then resolve requests — high, confirmed

Resolving a studio booking change only updates request status and clears alerts; it does not apply the requested edits. Admin booking edits update dates/venues/shifts, but do not reconcile role requirements when photo/video services change, or update the linked customer concert. They also reset every event shift to the default arrival time, potentially replacing a staff-adjusted setup plan.

Provide a reviewed before/after change action with consequences for crew, media, quote and scheduled communications. Keep decline separate from apply. Preserve intentional manual overrides, and make “Completed” mean the approved changes were applied.

Evidence: [resolve request](../../app/Features/Studios/Actions/ReviewStudioBookingChange.php), [booking update](../../app/Features/Bookings/Actions/UpdateConcertBooking.php).

### 9. Unify due dates and service relevance — medium, confirmed

- Booking estimate: video ready two weeks after the final event's finish. Tracker editing/approval targets: each event's date plus configurable days. Define separate production target, review-ready target and director-response deadline.
- Studio Hub sets seating due seven days before the event; the booking plan says its deadline is to be confirmed. They should use the same rule and any override.
- Tracker's Invoice task is due at last event +7 days and its description implies sending then; the agreed rule is to send when ready and make payment due +7 days. Split sent date from payment due date.
- Photo release lacks a tracker due-date rule despite the 48-hour customer estimate.
- Tracker requires Programme for DR Portrait events, while Hub says it is not required. Final-number/invoice tasks also need explicit photo-only and portrait-only relevance.
- Cancelled booking items remain included in some booking-first/last calculations and email event lists. Recalculate future operational deadlines using active events while preserving historical promises.

Evidence: [timeline builder](../../app/Features/Bookings/Services/ConcertBookingSummary.php), [task catalogue](../../app/Features/Tracking/Support/ConcertTrackerTaskCatalog.php), [tracker deadlines/templates](../../app/Features/Tracking/Services/ConcertTracker.php), [Hub checklist](../../app/Features/Customers/Services/StudioCommunicationHistory.php).

### 10. Make file receipt versus approval clear — medium, confirmed

Hub marks programme supplied when any current programme file exists and studio media supplied after at least one file. Staff still have conversion, programme confirmation and file approval work. These are legitimate distinct stages, but “Complete” needs a clear owner/context. Offer “No studio video/slideshow required” so studios without supplied media are not permanently shown as overdue.

Studio uploads are ordinary multipart requests, up to 2 GB per file/20 files, with no resumable upload path here. Local PHP permits about 2.1 GB for the whole request, so several individually valid large videos exceed the request limit. State the total limit and provide resumable/per-file transfer before relying on this for large studio uploads. PDF processing occurs after the initial file transaction, so failures also need a clear retry/recovery path.

Evidence: [Hub checklist](../../app/Features/Customers/Services/StudioCommunicationHistory.php), [upload request](../../app/Features/Studios/Requests/UploadStudioEventFilesRequest.php), [storage/processing](../../app/Features/Studios/Actions/StoreStudioEventFiles.php), [PHP limits](../../docker/programmes/php.ini).

### 11. Give email a reliable lifecycle — high before live automation

- Queued/scheduled outgoing emails are currently visible in the studio's communication history before they are sent. Keep upcoming messages in admin; show sent communication in the studio's received history.
- A successful log/catcher transport sets the tracker delivery to `sent` and completes the associated email task. That is useful for rehearsal, but must be labelled simulated in test records/UI; it is not external delivery confirmation.
- Scheduled content is frozen when queued. Cancellation/date changes do not invalidate that delivery, and the send job does not recheck event cancellation. Revalidate or replace scheduled messages after material changes.
- On failure the job records `failed`; subsequent execution accepts only queued/scheduled records, so a normal queue retry will exit without resending. Explicit Resend exists; make retry behaviour and failure recovery consistent.
- Staff conversation replies create a `sent_at` record before the synchronous mail call. A transport failure can leave a message appearing sent. Use the same queued delivery lifecycle as tracker messages.
- Inbound email models/history exist, but no receiving webhook/import implementation was found. Studio Hub replies work; ordinary email replies must not be advertised as automatically captured yet.

Evidence: [send job](../../app/Features/Tracking/Jobs/SendConcertTrackerEmail.php), [queue/resend](../../app/Features/Tracking/Services/ConcertTrackerEmails.php), [history and replies](../../app/Features/Customers/Services/StudioCommunicationHistory.php), [cancellation](../../app/Features/Bookings/Actions/CancelConcertBookingItem.php).

### 12. Add the photography financial close-out — medium, missing feature

The booking summary calculates the correct minimum and indicative review date, but does not reconcile qualifying sales against that booking or create an operational follow-up 30 days after actual release. Add actual release basis, attributed sales/refunds, shortfall, contact attempts and manual resolution. Invoice only after the agreed resolution process; no automatic shortfall charge. Multi-release bookings need a clearly chosen review basis.

Evidence: [quote](../../app/Features/Bookings/Services/ConcertBookingQuote.php), [timeline](../../app/Features/Bookings/Services/ConcertBookingSummary.php), [task catalogue](../../app/Features/Tracking/Support/ConcertTrackerTaskCatalog.php).

## Navigation and layout recommendations

Keep Studio Hub as the studio's front door and Concert Tracker as the staff work queue. Avoid adding another independent copy of status.

| Surface | Recommended organisation |
| --- | --- |
| Studio Hub | Events and Messages remain. Pending requests visible alongside confirmed events; Event timeline is the live booking overview. Make booking stays the primary creation action. |
| Event timeline | Current status/quote, next required action, chronological milestones, then original submission link. Completed items show dates; blocked items show why and where to resolve them. |
| Studio event | Your actions: final numbers, seats, programme, supplied media. DancePro progress: production, review and releases. Hide irrelevant tasks; separate Submitted from Accepted. |
| Admin booking | Review/contact/venue details plus direct links to its events, tracker, customer media and studio account. Remove obsolete portrait-interest wording from admin editing; keep backend event types. |
| Tracker task | One primary action appropriate to the task, then owner, due date, notes and communication history. Source-driven tasks should take the user directly to the matching event/album/review record. |
| Financial area | Booking-level invoice/payment workspace, reachable from both booking and tracker. Keep retail media orders and crew invoices distinctly named. |
| Communication | One delivery-status vocabulary and shared failure/retry handling; master templates in settings, previews/history beside the task. Never put unsent messages into received history. |

These layout recommendations are based on template and route inspection, not a new visual acceptance test of every authenticated page.

## Can we send pretend emails?

Yes. Live email is not required for the rehearsal.

Verified local runtime settings:

- Environment: `local`.
- Active mailer: `log` (messages are written to the configured application log).
- SMTP destination configured for Mailpit: `mailpit:1025`, but inactive while the default mailer is `log`.
- Mailpit container is healthy; its inbox port is exposed at `http://localhost:8025`.
- Queue connection: `database`.
- No `queue:work`/`queue:listen` or `schedule:work`/`schedule:run` process was observed in the application container at inspection time. This does not establish whether an external cron runner exists.

Recommended rehearsal setup: route local SMTP exclusively to Mailpit, use clearly named test studios/accounts, and run the queue worker and scheduler deliberately. We can inspect rendered messages and follow their localhost links without delivering mail to a customer's inbox. Retain recipient, content, timestamp and outcome in application history, with a Test/Simulated indicator. Mailpit currently has no configured persistent data volume in Compose, so retain the application's audit history and configure catcher persistence if inbox preservation across container recreation is needed.

Do not simply drain all old jobs after enabling a worker. Inspect pending jobs and automation candidates first, or use an isolated rehearsal dataset/queue so old reminders and unrelated jobs are not unintentionally processed. This audit did not change mail settings or start workers.

Automated tests can use `Mail::fake()` for dispatch assertions; rendered-message/catcher checks are also needed for content, links and attachments. Real deliverability, spam placement, bounce handling, external reply ingestion and provider delivery receipts require a later controlled live-email test. No email has been sent as part of this audit.

## Recommended implementation sequence

1. Establish an isolated rehearsal dataset, captured email, visible test mode, deliberate workers and a repeatable clock/date strategy.
2. Fix studio association for both approval paths; add contextual customer-media setup.
3. Share operational status/deadlines between tracker, Hub and live Event timeline; keep original forms immutable.
4. Connect reviewed booking changes, service/crew changes and scheduled-message invalidation.
5. Add final family numbers and booking-level invoices/payment recording; apply the agreed final-concert payment rule.
6. Build director review/corrections/re-approval and actual review links.
7. Add complete release-readiness checks, customer-access verification and minimum-spend follow-up.
8. Run the full rehearsal below, then refine layout from observed friction. Connect live email/accounting only when these flows are reliable.

## Acceptance rehearsal

Use one fictional studio with a DR Portrait session plus two concerts, and separate photo-only/video-only variants. Include one new studio booking and one existing studio.

1. Submit from Studio Hub; verify quote, snapshot, pending visibility and captured receipt.
2. Resolve an unknown venue and contact difference. Approve both individually and in bulk; verify studio links, shifts and service roles.
3. Confirm crew, enter seats and final family numbers; upload/replace programme and supplied media. Exercise the “none required” path.
4. Change one event date/service and cancel another; verify the current timeline, preserved submission, roster acknowledgement and reminder recalculation.
5. Prepare programme and upload files; try release with an incomplete photographer contribution, missing file and no linked concert.
6. Release photos; verify Studio Hub state, real customer access, notification content and download behaviour.
7. Record final invoice and partial/full payment; verify earlier versus final-concert approval access.
8. Send a captured approval message, review privately, request corrections, upload a revision, approve and release.
9. Register a separate customer with the correct concert code; verify all included concerts, availability/expiry and tracked downloads.
10. Advance the rehearsal clock for late tasks and the actual-release +30-day minimum-spend review. Simulate an email failure/resend and an account whose studio access was revoked.

## Verification

`./vendor/bin/sail artisan test --compact` passed: **801 tests, 7,445 assertions**, in 264.4 seconds. `git diff --check` passed. No application behaviour was modified in this audit. Passing existing tests validates covered behaviour, not the missing end-to-end features listed above.

## Implementation update — first batch, 27 September 2026

The initial rehearsal and connection fixes are implemented. See [Booking Delivery Rehearsal](Booking-Delivery-Rehearsal.md) for setup, test studio and exact scope.

Sequence steps 1–3 now have a local captured-mail path, isolated repeatable booking fixtures, retained studio associations, automatic private draft media setup, shared production progress and a current authenticated timeline with preserved original submission. The earlier runtime description above records the audit baseline: the local default mailer is now Mailpit SMTP rather than log. One synthetic message was captured; old queued jobs were not processed.

Steps 1–3 are foundations, not a completed end-to-end rehearsal: all-source delivery labels, finance/actual-release milestones, complete deadline integration and a separate new-studio acceptance pass remain. Next follow sequence steps 4–7, then perform step 8. The full original gap list remains relevant except for the specific fixes documented in the rehearsal handbook.

## Implementation update — booking changes and family numbers, 27 September 2026

Sequence step 4 now has an explicit reviewed-application path, booking revision history, linked schedule/media updates, preserved crew timing adjustments, acknowledgement reset and stale-reminder cancellation/review. Structured requests retain machine-readable requested fields. Direct status-only completion and completing an unchanged booking are blocked. New deliveries recheck a context fingerprint at send time; the automation review queue holds invalidated messages until staff schedule a current replacement.

Step 5 has begun with authenticated final-family confirmation, staff entry on behalf of a studio, audited corrections, source-driven tracker completion and recalculated current video quotes. Original submissions and estimates remain intact. Invoices, partial/full payments, accounting reconciliation, invoice-dependent review access, director corrections and release gating are still open. See the updated [rehearsal handbook](Booking-Delivery-Rehearsal.md) for scope and limitations.
