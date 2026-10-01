# Concert Tracker

## Outcome

The top-level Concert Tracker now covers the plan's Booking, Preparation and
assembly, Editing and approval, and Completion task groups. Date and studio views
show source-derived and manual work together without duplicating completion state.

## Implemented

- Eighteen operational checkpoints grouped into four compact phase filters, plus
  an all-task view with wrapping vertical column labels.
- Date and studio ordering, with booking-scoped cells merged across related
  concert rows in Studio view.
- Source-derived states for the unified booking and selected services, roster
  readiness, progressive programme preparation, photographer contributions and
  verified uploads, uploaded video media, photo-gallery preparation and release,
  director approval and final video publication.
- Old-system duplicates are deliberately folded into their authoritative record:
  booking information and videography into Booking and services; programme
  receipt into Programme; photographer contributions into Photo uploads; album
  preparation into Photo gallery; and invoice finalisation into Invoice.
- Deposit tracking and chat creation are omitted until they represent real work:
  chat is created automatically and no production deposit source currently exists.
- Manual task records with status, assignee, calculated or overridden due date,
  follow-up date, completion actor/time, notes and audited changes.
- Plan deadlines for programmes, final-number requests, Concert Week messages,
  post-concert messages, supplied studio media and final invoices.
- Overdue, due-soon, open and completed summaries. Waiting work remains overdue
  when its deadline passes.
- Shared booking tasks use one underlying record across all concerts in that
  booking.
- Editable studio email templates with placeholder preview, frozen delivery
  history and queued delivery. A successful email completes its manual Tracker
  task.
- A central email-template library keeps master wording together while each
  concert has its own Communications history. Messages can be sent immediately
  or scheduled, and scheduled deliveries can be cancelled. Resending creates a
  new auditable delivery while retaining the original record.
- Delivery states flow back into Tracker cells as Scheduled, Queued, Failed,
  Cancelled or Complete. Every new delivery is tied to the event even when its
  underlying task is shared by a multi-concert booking.
- Concert Communications summarises sent, opened, received and failed messages.
  Outbound HTML includes a per-delivery open pixel and records first/last open
  time plus count. Open data is explicitly presented as approximate because mail
  privacy systems may block or preload images.
- A provider-neutral inbound-email record and combined Received timeline are
  ready for the production mailbox webhook. Provider credentials and webhook
  validation are intentionally deferred until that provider is selected.
- The Needs attention filter focuses the operational table on overdue tasks,
  failed deliveries and due follow-ups.
- The Communication schedule provides a review queue for booking confirmation,
  final-number requests, Concert Week requirements and post-concert messages.
  It calculates dates from the approved booking and event schedule, shows the
  resolved recipient, flags missing email addresses and prevents duplicate
  scheduling for shared booking tasks. Nothing is scheduled until a staff member
  approves the row; past-due rows receive a five-minute cancellation window.
- Tracker alerts are generated every five minutes for overdue work, due
  follow-ups, failed email and missing recipient addresses. Alerts go to the
  assigned staff member, or to active administrators when a task is unassigned.
  Stable deduplication keys prevent repeated alerts, resolved conditions close
  automatically, and reopened conditions become unread again. The admin
  navigation displays the current user's unread count and each alert links to
  the task or schedule that can resolve it.
- Automation settings make the four standard communication rules configurable.
  Each rule can be Disabled, Review first or Automatic, with an editable offset
  from booking approval, first concert or event date and a local send time.
  Automatic mode uses the same duplicate-safe scheduler and delivery history as
  reviewed messages. Editing and director-approval targets, the owner for
  unassigned alerts and the escalation delay are also configurable. Assigned
  alerts escalate to the configured administrator after the selected interval.
- Concert photo grouping, cover choice and release remain in the Tracker's
  Completion phase and continue using the Media release records and audit log.
- Local-only fictional review data for a two-concert booking, source media,
  shared task states, follow-ups and example sent-message history.

## Email template fields

- `{{ studio_name }}`
- `{{ event_name }}`
- `{{ event_date }}`
- `{{ event_code }}`
- `{{ contact_name }}`
- `{{ event_details }}`
- `{{ event_summary }}` — concise names/dates for the current 20-day email group
- `{{ services }}`
- `{{ family_count }}`
- `{{ venue }}`
- `{{ start_time }}`
- `{{ finish_time }}`
- `{{ arrival_time }}`
- `{{ crew_names }}`
- `{{ gallery_access }}`

Templates may conditionally include a block with
`{{#if condition}}...{{/if}}`. Supported conditions are `video`, `photo`, `photos` (concert photos or portraits),
`photo_and_video`, `photo_only`, `portraits`, `second_photographer`,
`programme_missing` and `slideshow_missing`. These are resolved from booking,
programme, staffing and Tracker records before previewing or sending an email.

The legacy booking, confirmation, technical information, Concert Week,
post-concert and video approval documents were imported as editable starting
templates. Booking-open and booking-request copy is kept inactive as reference;
the operational Tracker templates are active and data-driven.

Gallery codes are stored as one-way hashes and cannot be recovered for an email.
`{{ gallery_access }}` therefore renders safe access instructions and status.
A future gallery-access link or separately encrypted delivery secret is required
if an email must contain a reusable credential. Venue-specific technical copy
also needs structured venue type, platform and power fields before it can be
selected reliably.

Templates are provided for booking confirmation, final numbers and technical
information, Concert Week, post-concert information, studio video/slideshow
requests, approval links and final invoices.

Every Tracker email includes a link to the DancePro account. A verified studio
contact can open a private Studio emails page containing the stored subject,
body, event, recipient, status and date for outbound Tracker messages, together
with inbound replies associated with the studio's concerts. Access requires both
a verified customer-to-studio link and an account email matching a contact email
recorded for that studio. Gallery-code access alone does not expose studio
correspondence.

Studio contacts can reply from that account page and optionally attach the
message to a concert. Staff see unread replies in the Management Hub's Studio
Messages inbox and can answer from the shared studio thread. Staff replies are
stored in the thread and emailed to the selected recorded studio contact. Web
messages use the same timeline as Tracker deliveries and provider-ingested
email replies, so a future inbound-mail webhook can add ordinary email responses
without creating a second conversation system.

Management Hub also manages explicit studio-account access. Staff can invite an
owner, director or office contact by email, link an existing verified customer
account immediately, see pending/active/revoked state and revoke studio access
without removing customer media or purchases. New contacts receive the normal
account-registration link with their invited email prefilled. No reusable
invitation secret is created: the pending grant activates only after a DancePro
account with that exact verified email signs in. Multiple active contacts can
share the studio thread.

The Studio Hub opens on an event list, with every event collapsed. Opening an
event shows the confirmed booking, assigned crew, outstanding studio actions
and the limited production milestones that are useful to the studio. Seats,
programme uploads and video/slideshow uploads are separate collapsible action
rows. Seats are entered in grouped form such as `Row A: 12, 13, 14`, with one
line per row.

Booking details remain read-only until the studio selects **Request changes**.
The existing date, venue, times and services then become editable in place,
with an optional note. Only changed values are recorded; they do not alter the
confirmed booking until DancePro reviews them. Open requests remain visibly
tagged on the event and create a Management Hub Tracker alert.

Studio threads support one optional private attachment per web message (PDF,
image, Office document, CSV or text, up to 10 MB). Files use private storage and
can only be downloaded through authenticated staff or authorised studio-contact
routes. Management Hub can assign each studio conversation to an active admin;
the owner appears in the inbox alongside the existing unread reply count.

Tracker templates and studio replies use a shared Markdown composer with buttons
for headings, bold, italics, lists, links and callouts plus an inline preview.
Raw HTML is stripped and unsafe links are rejected before rendering. Outgoing
messages use a responsive 640-pixel DancePro email card with a clear content
hierarchy, mobile padding, a single account action and a compact footer. Stored
Markdown remains readable in audit history and can be rendered consistently in
Management Hub and studio account conversations.

Email branding is configurable in Tracker automation settings: business name,
logo URL, phone, address, website, Reply-To address and legal footer. The app
logo is the fallback when no custom URL is provided. The supplied official
DancePro landscape artwork is used in the dark email header, while the official
square mark is used in compact application branding. Each administrator has a
separate optional signature containing display name, position, direct phone and
sign-off. Manual studio replies use the sender's signature, staff-queued Tracker
emails use the queuing staff member and automatic emails use the DancePro Team
fallback. The resolved pre-send task preview includes the same logo, business
identity and staff signature used by the delivered email. Administrators can
also send a realistic branded preview to their own account from automation
settings; this uses the saved branding and their resolved signature without
creating a studio communication record.

## Verification

- Feature coverage includes phase navigation, shared booking cells, task updates,
  notes, assignments, due-date overrides, template preview, queued delivery and
  successful-delivery completion.
- Full-suite verification is required after the local migration is completed.

## Remaining work

- Confirm whether the example 14-day production target is a universal deadline
  before applying it to editing and approval tasks.
- Connect studio invoicing and payment when their production source records are
  built; they remain manual Tracker tasks in this slice.
- Add the Stage 8 cost and sales reporting views.

## Pinned production email decision

Keep the existing public-facing addresses and isolate automated application
traffic from ordinary mail:

- `info@dancepro.com.au` remains the public enquiries and person-to-person
  address. DancePro automation must not send through this mailbox.
- `admin@dancepro.com.au` remains the studio-facing address and is the default
  Reply-To for Concert Tracker and studio-operation messages.
- `orders@dancepro.com.au` remains the ShootProof notification address during
  migration and becomes the order-facing Reply-To when DancePro takes over order
  delivery.
- `notifications@dancepro.com.au` is the preferred visible From address for new
  DancePro automated messages.
- A transactional provider may use a hidden technical subdomain such as
  `mail.dancepro.com.au` for DKIM, return-path and bounce handling. Customers
  should continue to see the clean `@dancepro.com.au` From address.

Separate provider streams should be used for studio operations, customer media,
account security and orders so a reputation or volume problem in one stream is
less likely to stop the others. Production setup must include SPF, DKIM, DMARC,
TLS, bounce and complaint suppression, gradual sending-volume growth and delivery
monitoring. Tracker replies should be ingested from `admin@dancepro.com.au` and
linked to their concert communication history.

Hosting confirmed 28 September 2026: Vodien/cPanel, `web203.vodien.com.au`,
secure IMAP 993 / SMTP 465. This confirms the provider, not a live connection.
See [Email mailbox routing](../handbook/Email-Mailbox-Routing.md) for the
confirmed settings and remaining activation work.
# Studio dress rehearsal portrait requirements

Dress rehearsal portrait events label their booking start as **Shoot time**. The Studio Hub asks the studio to confirm two source requirements before the event: a minimum 8 × 10 metre portrait area with accessible power, a desk and chair; and that teachers or minders will bring every student after rehearsal to be photographed. Each confirmation records the account and completion time and contributes to the event action progress.

## Tracker usability and task audit — 27 September 2026

- Centred tracker text, table cells, task metadata and release controls. Past
  deadlines on open tasks now have explicit red date text, including task details.
- Added Full screen / Exit full screen to the task sheet, matching Event
  availability. The sheet keeps its title and column headings visible while its
  rows scroll; the event column stays visible when scrolling horizontally.
  Browsers without fullscreen support use an expanded viewport with Escape to exit.
- Shared booking cells are merged after filtering so Needs attention cannot hide
  the first cell or leave a rowspan covering events that are no longer displayed.
- Photo/video tasks derive applicability from booked services and event type.
  Portrait rehearsals do not generate video work. Director approval, corrections
  and approval-email tasks show Not required when approval is disabled. Events
  without a linked booking retain their tasks rather than assuming no service.
- Each task detail now explains its completion criteria and uses a named source
  action (for example Manage event roster or Review studio files).
- Current studio-video uploads show approved/total counts and a direct link to
  review the files. They complete only when all current files are approved;
  external files can still be recorded manually when no portal uploads exist.
- Fixed the studio-file request template/task-key mismatch. Sending a request no
  longer completes the receipt task, and its confirmation text reflects that.
  No customer/studio emails were sent during this audit.

### Completion paths reviewed

| Checkpoint | Completion / action |
| --- | --- |
| Booking & services | Automatic from approved booking; Review booking & services |
| Confirmation information email | Task email action; automatic after successful delivery |
| 1.2 Seats & families email | Shared tracker bar above email template; completed after sending, or manually Approved without sending |
| 1.3 Seats & families numbers | Booking page, Seats & families numbers tab; automatic after final families and all concert seats are recorded; DR portraits not required |
| Concert Week email | Task email action; automatic after successful delivery |
| Crew ready | Automatic from published and acknowledged roster; Manage event roster |
| Programme | Direct programme preparation page; attachment/confirmation update automatically |
| Photo uploads | Automatic from verified uploads and completed/waived contributions; Open photo uploads |
| Post-concert email | Task email action; automatic after successful delivery |
| Studio video/slideshow | Review studio files; automatic approved/total progress, manual external receipt |
| Concert edited | Click task and save completion after editing |
| Final quality check | Click task and save completion after review |
| Video uploaded | Automatic from available visible concert media; Manage concert video |
| Video holds/missing media | Click task, record/resolve issues, or mark Not required |
| Approval link sent | Task email action; automatic after successful delivery |
| Director corrections | Click task, record applied changes, or mark Not required |
| Director approval | Automatic from recorded approval; Review director approval |
| Photo gallery | Automatic from album/group release; Manage photo release |
| Invoice | Task email action; automatic after successful delivery, or manual external send |
| Payment received | Click task and confirm payment manually |
| Final video release | Automatic from published concert record; Manage concert video |

Invoice/payment tracking concerns the studio's production invoice, not customer
media orders. It remains manual until a production billing source is integrated.
Editing, quality checking, missing-media resolution and corrections require human
judgement; they are intentionally not inferred from a file merely existing.

The photo release workspace now lives inside the **Photo release** task (renamed
from Photo gallery). The tracker grid has no release forms underneath. Opening
that task shows eligible albums and existing release groups for its studio and
event year, retaining cross-concert grouping. The backend gallery's Prepare photo
release link and tracker source links now open this task. Other task pages do not
load the release queries. Regression coverage checks studio isolation, related
concert albums and the existing release/cover workflow.

### Studio Hub booking entry

The Studio Hub now has a **Make booking** button on both Events and Messages tabs.
It opens the existing concert booking form with the studio name and contact name,
email and phone prefilled. A contact matching the signed-in email takes priority,
then studio contact fields, then the signed-in account name/email/profile phone.
All fields remain editable and validation redisplays the user's edits. Studio
prefill requires existing Studio Hub access and is returned private/no-store.
Event dates, venue, services and consent are still confirmed for each new booking;
there is no stored profile postal address to prefill. The public form remains
available to guests and creating a booking still uses the existing review flow.

Verification for this session: `./vendor/bin/sail artisan test --compact` passed
775 tests / 7,309 assertions. Targeted Pint passed. Chrome checks passed for
fullscreen entry/exit, fallback Escape, fixed headings during two-axis scrolling,
centred cells and red deadline text. Rendered release and booking forms passed
responsive layout, editable prefill, blank consent and add-event checks. No routes
or database migrations changed. The working tree contains earlier unrelated work;
no commit, branch operation or push was performed.


## Booking form refinements (27 September 2026)

- Both concert photography and videography are selected on a fresh form; a
  validation redisplay preserves the customer's selections.
- Removed the separate portrait-interest question. DR Portrait rows now drive
  portrait service selection and the portrait agreements, both in the browser
  and in server validation. Portrait-only requests remain supported. Existing
  stored interest fields and staff editing remain compatible.
- Removed the public event-type selector while retaining the hidden managed
  event-type value, default concert mapping and backend type management.
- Renamed the event title label to **Event title**, keeping it optional.
- Moved postal address into Studio details, available for all bookings. Existing
  mandatory-address validation for videography is retained.
- Moved **Feedback and requests (optional)** into Booking requirements. It covers
  previous photography, video and booking processes and this year's requests.
  The existing storage field is reused; staff booking/event labels also match.
- Booking grids now stack on narrow screens, including dynamically added rows.

Verification: all 28 booking feature tests passed (328 assertions); targeted
Pint passed. Chrome checks passed for default services, removed selectors,
second-row DR requirements, switching services, address/feedback visibility,
mobile stacking and no JavaScript errors. Full-suite result follows below.

### Personalised submission summary and timeline

Implemented with the owner's confirmed rates and timing rules:

- Photography minimum: $800 first photo event, $600 each additional photo event,
  including DR Portrait sessions. Video-only concerts are excluded.
- Video estimate: maximum of $1,500 × concert count or $15 × estimated performing
  family count, including 3+ concerts. DR Portrait sessions are excluded.
- All amounts are AUD inclusive of GST; a family counts once across concerts and
  includes performing parents/siblings. The final video invoice uses final counts.
- Setup: one hour before DR/photo-only events; 1.5–2 hours before video concerts.
- Estimated photos: 48 hours after the final event ends. Estimated video ready
  for approval: two weeks after the final event ends. Overnight events use their
  next-day finish when calculating those estimates.
- Timeline includes submission date/time and original-form link, reserved seats,
  family numbers, programme deadlines, venue/event-day requirements, setup,
  supplied studio-media deadlines, video invoice, release/approval estimates and
  photography minimum-spend review. Applicable dated milestones sort in order.
- Programme, studio-media and invoice offsets are read from the tracker catalogue.
  Seats and final-number receipt deadlines remain explicitly **To be confirmed**;
  the email request's scheduled send date is not treated as a response deadline.
- The minimum-spend review is 30 days after actual gallery release. Booking shows
  an indicative date using estimated release, and explains that contact and
  attempted resolution precede any shortfall invoice. No invoice, charge or
  notification is created by submission.

New bookings save the submitted details, accepted requirement wording, quote and
initial timeline in an additive nullable JSON snapshot. Staff edits do not change
that historical copy. Receipt pages are restricted to the submitting browser
session and returned private/no-store; another browser/session cannot guess a
booking UUID to read it. Existing bookings are not backfilled with a fabricated
original submission. The migration was applied successfully; rollback removes
only the new snapshot column (and its saved snapshots).

This is the initial submission plan, not live production tracking. Durable receipt
access from the Studio Hub, live status/date revisions, and final-number/seat due
date rules remain follow-up work. No new customer delivery promises are inferred
from the internal editing tracker target.

Verification: 46 targeted tests passed (403 assertions), covering rate boundaries,
photography combinations, service-specific setup, release estimates, immutable
submitted details and access isolation. Chrome checks passed for the revised
form, timeline, read-only form link and desktop/mobile layouts. Booking route list
and targeted Pint passed. The first full suite reported one demo-seeder
repeatability failure unrelated to booking assertions; the final full run of
`./vendor/bin/sail artisan test --compact` passed **796 tests / 7,400 assertions**
in 257.6 seconds. No seeder changes were made. `git diff --check` passed and the
working tree was reviewed; earlier unrelated work remains intact. No commit,
branch change or push was performed.


### Booking navigation refinement

Replaced the inline **← Studio Hub** link on the prefilled booking form with the
shared header **Back** button. Its destination is the selected studio's parent
Hub page, rather than browser history. The standalone public booking form has no
parent button when no studio is selected. Rendered HTML checks verified both
variants, the destination and the Back label.

Verification: `./vendor/bin/sail artisan test --compact` passed 796 tests /
7,400 assertions. `git diff --check` passed. No routes or PHP classes changed.


### Studio Hub Event timeline access (27 September 2026)

- Added **Event timeline** beside Make booking on both Studio Hub tabs. The
  destination uses the same heading and lists saved submissions newest first,
  with event titles/dates, current booking status, timeline and original-form
  buttons, and pagination.
- New bookings made from Studio Hub preserve its studio UUID in the form. The
  server rechecks current Studio Hub management access on submission and stores
  an explicit studio foreign key; customers cannot attach bookings to another
  studio. Duplicate detection also includes that studio context.
- Studio submissions open the persistent account-protected timeline after save.
  Timeline → submitted form → Back returns to the same timeline; Back from the
  timeline returns to the list, then Studio Hub. Both views reuse the existing
  public receipt templates and saved snapshot rather than a separate copy.
- Access is checked on every list, timeline and original-form request using
  existing Studio Hub access, including revocation. Responses are private/no-store.
  It works after the original browser session ends and for other authorised
  managers of the same studio.
- Existing saved submissions can be located through their real event/studio
  links when no explicit booking-studio link exists. Conflicting multi-studio
  event links are excluded; matching names or emails alone never grant access.
  Older pending requests without a trusted studio link need their events linked
  by staff first. Bookings with no original snapshot are not backfilled.
- Additive studio foreign-key migration applied successfully. Rollback drops
  only the new association column/constraint and preserves bookings/snapshots.

Verification: 36 booking tests passed (408 assertions) before the final naming
refinement. Browser checks passed for Event timeline button/heading consistency,
list → timeline → submitted-form links, parent Back navigation and mobile layout.
Targeted Pint, Studio Hub route listing and whitespace checks passed. The final
full Sail test suite passed: 801 tests, 7,445 assertions.

### Booking-to-delivery audit (27 September 2026)

Completed a source, route, runtime mail configuration and automated-test audit
of booking, Studio Hub, scheduling, tracker, communication, photo release,
video approval/delivery and booking financial follow-up. Findings and the
recommended implementation/rehearsal sequence are recorded in the
[full audit](../handbook/Booking-to-Concert-Delivery-Audit-2026-09-27.md).

Key gaps: bulk approval loses studio association; Hub and tracker derive
progress differently; Event timeline remains the original snapshot; customer
media setup is a separate manual handoff; director review/approval, final family
numbers and booking-level invoices/payments are incomplete. Scheduled-email
lifecycle, release readiness and actual-release minimum-spend follow-up need
integration. These findings are not fixes or completed features.

Runtime mailer is local `log`; Mailpit is healthy at localhost:8025 and the queue
uses the database. No worker or scheduler process was observed in the app
container. Mail configuration was not changed and no jobs or real emails were
sent. Next: isolated rehearsal data/captured email, then approval studio links
and shared progress/deadline calculations before the director-review workflow.

Verification: full Sail suite passed 801 tests / 7,445 assertions in 264.4 seconds;
whitespace check passed. Authenticated end-to-end browser rehearsal is still
required; available browser session stopped at login. No application changes.

### Booking-to-delivery connections and local email rehearsal — 27 September 2026

- Preserve a booking's saved studio through individual/bulk approval and reject conflicting explicit associations.
- Create private disabled draft media records on approval for concerts and DR portrait events; existing approved data is not bulk changed.
- Share source-driven tracker progress with Studio Hub and current Event timeline. Retain immutable original submission views and exclude cancelled events from current estimates.
- Use Mailpit for local email capture, flag simulated tracker deliveries, hide unsent mail from studio history, retry failed sends and suppress pending mail for cancelled events.
- Add local repeatable mixed/photo-only/video-only fixtures and a selected-message rehearsal command with a temporary clock; do not drain unrelated jobs.
- [Rehearsal setup and limitations](../handbook/Booking-Delivery-Rehearsal.md). Finance, director review, release-readiness and full end-to-end acceptance remain the next audit phases.

### 27 September 2026 — reviewed booking changes and family numbers

- Studio change requests are completed by saving reviewed booking edits; status-only completion is rejected. Staff can inspect before/after revisions and the agreed resolution.
- Booking changes/cancellation invalidate pending tracker messages. New messages also verify booking context before sending. Invalidated automatic reminders require manual scheduling from current details.
- Final numbers are confirmed on the Event timeline or admin booking. The current video quote uses the confirmed count; tracker 1.3 additionally requires all concert seat allocations; retail orders and crew invoices remain separate from the planned studio invoice/payment workflow.
- See `docs/handbook/Booking-Delivery-Rehearsal.md` for rehearsal instructions and remaining delivery work.

## Studio invoice handoff (27 September 2026)

Invoice and Payment received are now source-driven from the studio billing ledger. Issuing makes an invoice available in the Studio Hub; recorded transfers or settled card payments reduce its balance. Pending payments remain waiting. The payment task uses the invoice's actual due date. Email delivery cannot complete either financial source task. See [Studio invoices](../handbook/Studio-Invoices.md) for operation and remaining gateway activation work.

### Tracker invoice navigation correction (27 September 2026)

The task action now distinguishes creating an invoice from the booking, opening a draft, and opening an issued invoice. Unapproved or unlinked bookings explicitly require review; events without a booking link to the invoice list. The existing Not required action still opens event/services review. GET navigation never creates or issues an invoice. Regression coverage verifies both Invoice and Payment received destinations through creation, draft and issued states, plus approval/studio prerequisites. No routes or financial behaviour changed. Gateway activation and director review remain the next delivery work.

Verification: full Sail suite passed 831 tests / 7,724 assertions. Scoped Pint and whitespace checks passed.

### Pending date-view bookings and task numbering (27 September 2026)

- Date view includes requested events from pending booking items, sorted alongside scheduled events. Unconfirmed studio headings are red with a Pending booking label and a link to booking review. This includes portrait sessions and partially approved bookings, but excludes cancelled/declined items and bookings or other years.
- Pending rows are read-only projections of booking items: they do not create scheduling records, media, tasks or mail. Production task totals and attention filters continue to cover scheduled tasks. On approval the scheduled row replaces the pending row without duplication. Studio view keeps its scheduled-event scope.
- Phases use 1–4, with stable task numbers such as 1.1 Booking & services and 2.1 Crew ready in the table, tooltips and task page headings. Keys, automation and stored tasks are unchanged.
- Next: continue the remaining director-review/payment-gate work and gateway activation described in the studio invoice handoff.
- Verification: full Sail suite passed 834 tests / 7,756 assertions; scoped Pint and whitespace checks passed. Browser-rendered normal and full-screen layouts were inspected, including red heading colour and accessible booking review placement. The preview used an in-memory pending row; no live booking was created or approved.

### Canonical booking review/edit page with tracker bar — 27 September 2026

- Task 1.1 now links directly to the existing booking page for both pending and approved bookings. Legacy task 1.1 detail links redirect there; Event management Review/Edit always includes the same bar.
- Sticky four-box controls provide manual status override/reset to automatic, due-date editing/clearing, active staff or crew assignment, and follow-up status/date/notes. Keyboard Escape/Cancel close dialogs; invalid inputs reopen the relevant dialog with errors.
- Each dialog saves its own fields without clearing other boxes. Changes are audited; notes and assignment remain attached to the same booking task on approval. Manual status does not approve a booking or change financial/media source records.
- Pending tracker rows have a clickable red Not approved cell for 1.1 and show its due date. Both the cell and studio heading remain red until actual approval, even when a separate manual tracker override is set. The back link preserves the last tracker filters. Follow-up resolution retains notes and suppresses follow-up attention.
- Architecture: [canonical tracker task pages](../decisions/ADR-0055-Canonical-Tracker-Task-Pages.md). Booking confirmation is now included in 1.1; the seats and families workflow is described below.

- Bar saves use a JSON partial refresh, preserving unsaved booking form edits. Validation errors remain in the open dialog, and refreshed controls retain keyboard behaviour.
- Verification: full Sail suite passed 839 tests / 7,846 assertions; final focused tracker-bar checks passed 5 tests / 91 assertions, including resolved follow-up alerts. Migration and route listing succeeded; scoped Pint and whitespace checks passed. Browser checks at 1440px and 390px verified sticky placement, all four dialogs, validation, and preservation of unsaved booking edits. The pending red cell was inspected using an in-memory preview; no live booking was approved.


### Combined booking approval and confirmation, and event materials — 27 September 2026

- Final family numbers align to the 900px booking form. The estimate pre-fills the input but does not confirm it; the checkbox and explicit submission are required, independently of the due date.
- Booking details now include per-event seats, programme upload/replacement and studio video/slideshow upload, replacement and review. Approved-event uploads reuse Studio Hub storage and tracker sources. Old studio-file versions remain in history; replacements reset review. Pending programmes remain available before approval; seats and studio files become available once an event exists.
- Approve booking and the tracker’s Approved choice share validation and the same approval action, then open the confirmation template. Neither sends automatically. Unsaved booking edits block approval until saved.
- Former 1.2 is folded into 1.1; later booking tasks renumber. The confirmation page shares the 1.1 bar and exposes recipient, template, send/schedule and delivery history. Legacy email records, templates and job keys are retained, and legacy confirmation links redirect to this page. Confirmation delivery state is visible from the booking bar.
- Next: agree the next task page in the tracker walkthrough. Remaining operational task destinations are unchanged.
- Verification: full Sail suite passed 843 tests / 7,921 assertions. Final focused booking/tracker checks passed 66 tests / 875 assertions, including the merged send/history flow through a fake mail transport. Route listing, scoped Pint and whitespace checks passed. Rendered browser checks at 1440px and 390px confirmed matching form widths, unchecked family confirmation, event material controls, unsaved-edit protection and approval navigation. No live booking was approved and no real email was sent during verification.

### Seats and families tasks (27 September 2026)

- 1.2 retains the `final_numbers_email` key and delivery history. Its task page uses the shared status/deadline/assignment/follow-up bar above the existing email template. Manual Approved records completion without sending or approving a booking; successful delivery sets Completed and records the sender/time.
- 1.3 retains `final_numbers_received`, links directly to the canonical booking page on `?tab=seats-families`, and displays the quoted family estimate until the final unique-family count and every active concert’s required seats are confirmed. Photo-only concerts can also confirm families. Cancelled items and DR portraits do not count toward completion.
- The combined booking section has one total family count and a collapsible seats form per concert. It reuses Studio Hub seat records and actor/time metadata. Programmes and slideshows remain in their own section.
- Completed cells display confirmed families and each event’s compact seat labels (for example, `P1: N23`) in green with attribution/time. These cells are kept per event in studio view so DR portraits remain grey and different concert seats remain visible. Legacy task links redirect to the booking numbers tab.

The booking workspace now has two server-rendered tabs, **1.1 Booking & services** and **1.3 Seats & families numbers**. Each tab contains its own shared tracker bar; 1.3 independently stores deadline, assignment and follow-up while deriving its status from confirmed data. The event-management menu strip is removed from this detail page. Back to event bookings and Back to concert tracker are stacked above the tabs. Old `#seats-families` links open the numbers tab for compatibility.

### Independent family/seat indicators and email deadline (27 September 2026)

- Task 1.3 shows the quoted family estimate and a separate Seats not confirmed badge in blue before a deadline applies. Confirming or updating final families immediately shows the current count in green, independently of outstanding seats. Confirmed event seats appear in green with compact seat labels and actor/time details. DR portraits remain grey Not required.
- Successful delivery of 1.2 sets the booking-wide 1.3 deadline to seven calendar days after the actual send date and records an audit entry. Queueing, scheduling, failures and manual approval do not start this deadline. A fresh resend sets a new seven-day deadline; duplicate execution of an already-sent job does not. Assignment and follow-up fields are preserved.
- The global warning window is now three days: due within three days is yellow; dates before today are red/overdue. Confirmed family/seat badges stay green while remaining requirements show the warning colour. Overall completion still requires confirmed families and all required concert seats.
- The numbers-page tracker bar reflects the same blue/yellow/red pending state. Staff can still adjust the deadline manually. Next: continue the task-by-task tracker walkthrough.

- Verification: full Sail suite passed 851 tests / 8,056 assertions; scoped Pint and whitespace checks passed. Focused tests cover actual versus scheduled send dates, failed delivery, manual approval, duplicate jobs, resends, preserved assignment/follow-up fields, warning boundaries, and initial/corrected family counts. Browser checks verified blue pending badges, independently green families, yellow due-soon seats and red overdue seats using in-memory examples only.

### Consistent email task pages and warning window (27 September 2026)

- Yellow starts three calendar days before the due date, including the due date; red starts the next day. The seven-day response deadline after sending 1.2 is unchanged. The summary and 1.3 task bar use the same warning window.
- 1.4 Concert Week email uses the same page, tracker bar and manual approval workflow as 1.2. It remains event-specific; 1.2 remains shared across a booking.
- Both pages prominently show successful delivery recipient, sender and time, with test captures explicitly distinguished from live sends. Pending delivery blocks another send. Resending requires opening the resend section and explicitly acknowledging the latest sent message; server-side locking prevents duplicate submissions and stale resend confirmations. Delivery-history shortcuts cannot bypass this review.
- Verification: 41 focused tests / 637 assertions passed; scoped Sail Pint, route listing and whitespace checks passed. Browser checks at 1440px and 390px verified both layouts, normal-sized confirmation checkboxes, collapsed resend controls and blocked pending sends. The full Sail suite passed 851 of 852 tests (8,131 assertions); the demo-seeder repeatability test intermittently produced eight extra collections and 40 assets. Its isolated rerun passed both tests / 57 assertions. The existing realistic-media seeder uses an unordered first matching concert; no seeder code was changed here. No live emails were sent. Next: continue the task-by-task tracker walkthrough; separately stabilise the demo-seeder target.

### Crew availability and programme workspaces (27 September 2026)

- Event availability includes a studio filter, retained across pagination. Task 2.1 embeds the same editable availability sheet, scoped to the selected event's studio; events without a studio are scoped to that event only. Back to concert tracker and Full event availability links are provided above the isolated scrolling sheet.
- Availability and 2.1 share roster status calculation: Draft, Availability open, Rostering, Assigned or READY. READY requires every required role on every shift to be published and acknowledged. Green tracker cells list assigned roles and crew (for example, `V: Alex`).
- Task 2.2 opens the canonical booking workspace's Prepare Programme tab, with an event selector for multi-event bookings. The existing editor is reused as a partial, and admin preparation links redirect to that same tab. Standalone events retain the editor and receive a tracker bar; existing crew viewing permissions are preserved.
- Both source tasks have the shared tracker bar for due date, assignment and follow-up. Status derives from the underlying roster or programme. Programme preparation can use the current studio/booking attachment, accept an admin upload, or explicitly import a newer attached PDF into an existing draft while retaining prior sources.
- Verification: full Sail suite passed 854 tests / 8,178 assertions; scoped Sail Pint, route listing and whitespace checks passed. Browser checks at 1440px and 390px verified the studio filter, isolated sheet, programme tab and tracker dialogs. Availability navigation now wraps on narrow screens. Preview rendering rolled back all temporary changes; no live emails were sent. Next: continue the task-by-task tracker walkthrough.

### Photo task order, post-concert email and studio media tab (27 September 2026)

- Photo uploads moves from 2.3 to 3.1. Post-concert email and Studio video/slideshow become 2.3 and 2.4; existing editing tasks become 3.2–3.8. Stable task keys retain saved statuses, notes, deadlines and delivery history.
- Post-concert email now uses the same tracker bar and email page as 1.2/1.4, including manual approval, successful-delivery details, blocked pending sends and explicit resend confirmation. Delivery-history shortcuts use the same resend review; successful sending clears manual approval and records completion.
- Studio video/slideshow cells and their previous task URLs open the canonical booking workspace's 2.4 tab for the selected event. The tab includes the shared tracker bar, event selector, back links, current file reviews, admin uploads/replacements and an optional request-email section. It reuses the booking's existing studio-media file controls and records, including files received through the Studio Hub. Approved files complete the task; replacements reopen review. With no portal files, staff can record external receipt or mark the task not required.
- Verification: full Sail suite passed 855 tests / 8,248 assertions. Scoped Sail Pint, route listing and whitespace checks passed. Browser checks at 1440px and 390px verified the post-concert email, studio-media tab and tracker dialogs. Tests cover renumbering, deliberate email resends, event isolation, upload/replacement review, manual receipt and permissions. No live emails were sent; preview data changes were rolled back. Next: continue the task-by-task tracker walkthrough.

### Studio media audit details and programme reader recovery (27 September 2026)

- Cell 2.4 shows the latest current-file upload date and uploader, plus the latest
  review date and reviewer. Existing manual completion details remain available.
  Replaced file history stays on the shared booking tab.
- Programme preparation now reports page progress, recovers transient polling
  failures and recognises scanned paired columns with mixed font sizes or decorative
  act headings. The local worker/tools were restored and two stalled drafts recovered
  without approval. See Programme-Automatic-Extraction.md for measured results and limits.

Verification: the final full Sail suite passed 856 tests / 8,262 assertions, including
nine synthetic Python layout cases and real PDF extraction with progress reporting.
Scoped Sail Pint, Vite build and whitespace checks passed. Browser checks verified
progress recovery after a failed request and the tracker audit display. No routes or
migrations changed; no live emails were sent and no programme was approved.

## Video title publication (27 September 2026)

- Preparation now reads **2.1 Crew ready, 2.2 Programme,
  2.3 Video Titles, 2.4 Post-concert email, 2.5 Studio video/slideshow**.
  Stable task keys retain existing statuses, notes, deadlines and send history.
- 2.3 opens the canonical booking workspace's Video Titles tab, with its own
  tracker bar and concert selector. The existing title-card URL redirects there
  for booked concerts; standalone concerts reuse the same editor and tracker bar.
- **Video titles ready** renders the approved programme's title cards as 4K JPEGs,
  publishes one private ZIP and completes the tracker task. Template saves and
  local downloads alone do not complete it. Publication requires an approved
  programme, a valid archive and successful private storage.
- Each publication records the staff member and time. The uploader offers
  **Download Video Titles…** for selected/prepared concerts and **Video Titles…**
  inside concert review. Downloads require programme access and the
  `programmes:read` token ability, and verify event, revision, size and SHA-256.
- A changed approved revision, saved template, event title or logo path makes the
  old package unavailable and reopens a completed task as “Titles need updating”.
  Manually reopening the task also withdraws the download. Re-publish after review.
- Package records and files are versioned; replacing titles does not delete old
  files. Migration rollback removes package metadata only, not stored artifacts.
  See ADR-0056 for the publication boundary and font/layout considerations.

Verification: full Sail suite passed 861 tests / 8,318 assertions. Scoped Pint,
629 registered routes and whitespace checks passed. Browser checks passed at
1440px/390px with working tracker dialogs and publication error recovery; the
captured browser ZIP passed server validation with two 3840 × 2160 JPEGs. The Mac
uploader passed 90 tests and its release app was rebuilt. The new migration ran
locally; rollback removes metadata only. No live titles were published during
browser verification and no messages were sent.

Studio Hub progress rows now show the available studio photo gallery code and the
applicable concert video code (grouped or individual). This is a management-only
presentation change; tracker completion/release status remains source-driven.
Encrypted display-copy handling and legacy limitations are recorded in ADR-0057
and the Customer Web Gallery milestone.

## Event column hierarchy (27 September 2026)

Both date and studio views now show studio name as the heading, booking event title
as the subtitle, and event date on its own line. An accessible envelope link and
studio photo-gallery login sit together beneath them, wrapping only if needed.
Confirmed rows keep their event communications destination. Pending rows keep red
studio headings and open studio communications where a studio is linked; their
heading still opens booking review.

The actual saved gallery login is used, not the internal studio/event code. Missing
and legacy hash-only codes are labelled accurately. Gallery codes are resolved once
per studio per tracker page through the shared code-display service. Staff-only
tracker access and private/no-store responses remain. No codes were changed.

Follow-up: the owner requested unique random three-letter studio gallery logins,
replacing existing codes in the local development dataset. The admin gallery-code
form now accepts 3–64 characters; longer existing codes remain supported. The
one-off assignment uses the existing hashed/encrypted code-save action and audit
history. It does not change internal studio codes, concert-video codes, customer
studio memberships, or send notifications.

The local assignment completed for 109 non-deleted studios. All 109 stored codes
were checked for three uppercase letters, uniqueness, and matching login hashes
before committing the database transaction. No codes were printed or exported.
Desktop/mobile browser checks passed in date/studio views, including pending red
headings and the envelope/code row.

Verification: full `./vendor/bin/sail artisan test --compact` passed (865 tests).
Scoped Laravel Pint and `git diff --check` passed. No routes changed.


Studio Hub badge refinement (27 September 2026): the release progress items now
place compact “Access Code:” badges to the right of the existing information,
beside the status. There is no extra explanatory code row. A video code is only
included in Hub view data after the concert has recorded director approval
(`approved_at`); waiting, unapproved, or approval-not-required states alone do not
reveal it. Removing recorded approval hides the badge again. Photo codes keep
their existing availability rule. No stored codes or access grants change.

Verification: the full suite passed (865 tests), then the focused booking-delivery
suite passed against the final approval gate. Desktop/mobile visual checks,
scoped Pint and `git diff --check` passed. No routes or migrations changed.

## 3.1 Photo uploads workspace and role states (27 September 2026)

- The tracker cell and legacy task link open the existing Photo uploads page.
  Concert routes into this page include the same tracker bar, with due date,
  assignment and follow-up editing. Upload completion remains source-driven.
- The overview shows published photographer count, configured photo-role count,
  registered/verified/remaining photos and verified percentage overall and by role.
  Crew assignments and actual uploader names are distinguished. Refresh progress
  fetches current server data without modifying uploads. Competition upload pages
  retain their existing workflow and do not receive a concert tracker bar.
- The Mac uploader already registers the current queued list in batches of 500
  before transferring bytes. The server can count those registered files after
  the requests arrive; unsent/offline laptop queues, transfer speed and pause state
  are not server telemetry. Duplicate copies are excluded. Counts cover the
  selected working programme, and 100% verified does not mean Finished upload.
- Each tracker cell shows P1/P2 (or the configured photo role) Waiting, Uploading,
  or Complete. Waiting is blue until overdue, then red; Uploading stays blue;
  Complete is green. This task has no three-day yellow state. Uploading means
  registered work is still open, not proof the laptop is currently connected.
- Every configured photo role must confirm Finished upload for all expected items
  on the current programme. One completed role cannot finish a two-role task;
  waivers are reported separately and do not masquerade as a photographer's finish.
  Later registered files reopen the affected role. Release permissions do not change.

Verification: the full 868-test Laravel run passed 867 tests; the remaining new
mixed web/API test failed because its browser request changed the authentication
guard before a subsequent uploader request. Moving the browser assertion after
the uploader actions corrected only the test harness. All 45 photo-contribution
tests then passed (447 assertions), including that regression. No application
changes followed the full run. The initial tracker-cell Blade parsing issue was
fixed and covered by the full rerun. Scoped Pint, `git diff --check`, tracker route
listing (28 routes), desktop/mobile upload-page rendering and coloured role-cell
browser checks passed. No database migration, real upload or completion was
performed during verification.

### Task 3.2: editor assignment and completion (27 September 2026)

- The existing concert-edited task URL now opens a focused editing page, with the
  shared sticky tracker bar at the top. Admin can change status, due date, editor
  and follow-up details there. Programme, video-title and studio-media links use
  their existing workspaces.
- Start editing, Mark editing complete and Reopen editing update only status;
  assignment, due date and follow-up details are preserved. Completion uses the
  existing audit action to record actor/time, preserve the first completion stamp
  on repeat submissions, and turn the tracker cell green. Reopening clears the
  active completion stamp; historical changes remain logged.
- Crew Hub > My Tasks lists assigned concert edits. The same editing view and
  tracker task are used for crew, with assignment/deadline controls read-only.
  Crew may update their own task status and follow-ups, but cannot access another
  editor's task or admin workspaces. Reassignment removes the previous editor's
  access. Customer accounts have no access. Cancelled events are excluded.
- Photo-only and DR tasks remain Not required and reject completion. Completing
  editing does not complete quality checking, approve or release media.
- No database migration, email, live assignment or live completion is needed.

Verification: focused ConcertEditingTest passes (3 tests, 60 assertions). Scoped
Pint, route listing, diff whitespace checks, and admin/crew desktop/mobile browser
layout and tracker-dialog checks pass. The initial 3.2 implementation passed the full suite (871 tests, 8,499 assertions). Further verification for downloads, 3.3 and 3.4 is recorded below.


### 3.2 downloads, 3.3 quality checks and 3.4 video uploads (27 September 2026)

- 3.2 includes current programme, published/current video-title ZIP, and studio
  video/slideshow downloads. Assigned editors see approved studio files only.
  Downloads use server-selected paths, reject cross-event/superseded files, and
  recheck editor assignment. Reopened or changed title packages are unavailable.
- 3.3 presents the owner's 20 checks in Files, Titles, Video, and Audio & Music
  groups. Each change autosaves through a serial queue, with saving/saved/error
  feedback, retry, and an unsaved-navigation warning. An optimistic fingerprint
  prevents overwriting another reviewer's changes. Each tick records staff and
  time; all 20 complete the task, and unchecking reopens it.
- Complete all is available only to active `type=admin` users with scheduling
  access. Staff accounts cannot invoke it or bypass it with a bulk checkbox
  payload. This is separate from the shared `is_admin` back-office access flag.
- 3.4 has the common tracker bar, complete queued/verified counts, file-based
  percentage, uploader finish actor/time, blockers, and studio file inclusion
  selectors. A current approved studio file must be mapped to a verified final
  export; multiple studio files may be edited into one export. Replacement files
  require fresh inclusion confirmation. These mappings are explicit review
  records, not an automated assertion that the video content was inspected.
- The Mac uploader has Upload Edited Concert Videos, private MP4 transfer,
  checksum verification, persistent retryable queues, and a separate Finished
  Uploading confirmation. All files register before transfer. Starting a changed
  full queue makes the previous finish signal stale; verified identical bytes
  can be reused without another transfer. The editor's assignment also grants
  programme read access and event discovery; it does not grant photo-upload or
  programme-edit permissions.
- This uploader path currently accepts up to 500 MP4 exports, each up to 5 GiB,
  using the existing signed single-object upload mechanism. Larger individual
  exports need multipart support added to the laptop UI. No HLS transcoding is
  performed by this path; the existing concert player supports progressive MP4.
- 3.4 is ready only after the current queue is fully verified and explicitly
  finished, 3.2 editing and all 3.3 checks are complete, all current studio media
  are approved/accounted for, and the linked video assets are available. One
  legacy attached asset no longer incorrectly completes the task. Completion
  creates private draft media and does not email the director or release media.
- Additive migrations store quality checks, upload batches and file manifests;
  rollback drops only the newly added fields/tables, in dependency order.
- Regression testing exposed an unordered concert lookup in the local realistic
  media seeder. It now uses the stable Aurora Junior concert UUID, so repeated
  setup cannot attach another copy of the eight sample albums to a different
  concert. Existing media is not deleted or reseeded by this change.

Verification: `sail artisan test` passes all 878 tests (8,649 assertions).
Scoped Pint and `sail artisan route:list` pass (639 routes), and both additive
migrations ran successfully locally. All 93 Mac uploader tests pass; the release
app builds and its signature verifies. Desktop/mobile browser checks cover
layout, individual autosave, serial rapid changes, save failure/retry, admin-only
Complete all, and tracker controls. `git diff --check` passes. No emails were
sent and no concert media was released during verification.

### Director review, corrections and releases (27 September 2026)

- Removed the Video holds/missing media column (formerly 3.5). Video approval
  link is now 3.5 and Director corrections is 3.6. The standalone Director
  approval column is folded into 4.4 Final video release; its old task URL
  redirects to the release page. Historical task/delivery records are retained.
- 3.5 uses the standard email task page, tracker bar, editable template, clear
  delivery history and explicit resend confirmation. The current video queue,
  editing and quality checks must be ready before its message can be queued.
- Verified studio owners/directors can review current concert videos privately
  from their Studio Hub. Office contacts and other studio customers cannot use
  this review/release route. Filename/time-based correction notes are recorded
  with the submitting account, time and review revision.
- 3.6 displays that feedback below the tracker bar. Confirming Corrections
  complete and Corrections uploaded opens the follow-up email page. Sending the
  email completes the task; a new feedback revision resets both confirmations
  and reopens the task. The current upload batch and review revision travel with
  queued review emails; outdated messages are cancelled before sending.
- Director release approves and publishes only the current verified video batch
  in one transaction. Previous video collections remain private. The release
  actor/time and batch are recorded; an outdated page cannot release a new batch.
  Existing video-code groups are reused, and a group is created when a concert
  has no code protection. Release does not grant access to studio-photo accounts
  or email the video code to families. The director shares it after release.
- 4.1 defaults to 48 hours after the final active booking event's finish time.
  It shows Pending until 3.1 is complete, then Ready to release in red, and
  Released in green after publication. Release requires finished uploads for
  every linked event in the selected photo group.
- Photo release opens a separate announcement preview and recipient list for
  active, verified accounts with verified registration to that studio. Each
  group/address has one delivery record: repeated sends skip sent/pending
  recipients and retry failed/cancelled records. Eligibility is checked again
  when the queued message runs. Each recipient receives a separate message.
- The updated photo email links to the account's studio gallery and explains
  current access, checkout offers and My Media downloads. Historic discount,
  file-size and delivery-time claims were removed. Video code distribution
  remains the studio's responsibility.
- The additive review migration stores feedback, correction confirmations,
  release audit and email workflow context. Its rollback removes those new
  structures only. Template rollback retains edited text and delivery history.

Verification: all 881 application tests pass (8,707 assertions). Scoped Pint,
route listing (646 routes), both migrations and `git diff --check` pass.
Browser checks cover six desktop/mobile layouts, compact checkboxes, the shared
tracker bars and automatic navigation after both correction confirmations.
No live messages were sent or existing galleries released during verification.

### Invoice & payment and seats/families deadline (27 September 2026)

- 1.3 is due ten days after actual successful delivery of 1.2. The existing
  three-day yellow warning and overdue red rules still apply. Previously
  automatic seven-day deadlines are extended where they have not been manually
  changed; task assignment/follow-up data is retained.
- 4.2 is now **Invoice & payment**. The separate Payment received column is
  removed, and its old task URL redirects to the combined task. Final video
  release is consequently 4.3. Historical payment task records are retained.
- Before final families are confirmed, invoicing is Pending with no deadline.
  Once confirmed, it is Not invoiced, due seven days before the final active
  booking event. The normal three-day yellow warning and overdue red apply.
- Sending the invoice from its editor saves line items, a GST-inclusive discount
  and notes, makes the invoice available in the Studio Hub, then opens the shared
  email page. The email links directly to that invoice in the studio account.
  The editor, email and payment details share the automatic invoice tracker bar.
- The first successful invoice email records sent date/staff and sets payment
  due fourteen days later. Queueing alone does not mark an invoice sent. Test
  capture follows the same workflow and remains labelled as a test. Repeated
  sends require confirmation and never extend the original payment deadline.
  Queued emails for voided, replaced or fully paid invoices are cancelled.
- Sent invoices show Not paid in yellow until overdue, then red. Full settled
  payment shows Paid in green with the payment date instead of sent date/staff.
  Partial/pending payments remain outstanding. Explicit tracker due-date edits
  also update the invoice date shown to the studio, with an audit entry.
- The task reopens the existing invoice page, with PDF download, payment details
  and history. Issued amounts stay fixed; corrections use the existing void and
  replacement process. Payment Received is also removed from the live studio
  booking timeline, avoiding duplicate progress entries.
- Additive migration stores invoice sent audit and links email deliveries to the
  specific invoice. Rollback removes these fields, preserving edited templates
  and audited task deadlines. No live invoice or email is sent by migration.

Verification: all 883 application tests pass (8,735 assertions). Scoped Pint,
route listing (647 routes), the migration and `git diff --check` pass. Browser
checks cover invoice preparation, draft editing, the email page and paid invoice
at desktop/mobile sizes, including saving an unsaved discount with Send invoice.
The generated one-page PDF was rendered and checked for discount, GST, payment
history and balance. Preview records were rolled back; no live invoices,
messages or payments were sent during verification.

### Review readiness, checklist permissions and upload colours (27 September 2026)

- 3.5 displays missing upload/review requirements above its email preview, with
  links to 3.4 and 3.3. Sending is unavailable until ready; a stale form returns
  normal field validation instead of an exception page. Queue delivery still
  rechecks readiness, so later changes cannot send an obsolete approval link.
- 3.3 has a prominent Complete all checks checkbox above the checklist. Its
  browser, request and service checks use the same active admin permission as
  the account switcher, including admin-enabled staff/crew accounts. Ordinary
  staff cannot use the bulk action. Existing checks retain their audit; newly
  checked items are saved and logged automatically. Individual unticks reopen
  the task. No local account roles were changed.
- 3.4 is due ten days after its own event date. Open uploads start blue, turn
  yellow within three days of the deadline and red when overdue; readiness
  remains green and removes the due date. Manual due-date overrides remain.
- 3.6 is blue Pending before feedback, red Corrections while work/email remains,
  green Completed after corrected uploads and successful email, and grey No
  corrections only after the studio releases a video without requesting changes.
- Local test-data repair restored 18 missing studio links on already-approved
  `.test` bookings using their existing, unambiguous event/studio associations.
  Each repair has a booking revision. Pending bookings and family confirmations
  were not changed. The local-only repair seeder is idempotent and skips missing
  or conflicting event links; the tracker demo seeder now retains its studio ID.
- Verified Centre Stage invoice draft creation with its already-confirmed 150
  families ($2,250 including GST) inside a rolled-back transaction. No test
  invoice, send, upload completion or quality completion was persisted for QA.

Verification: all 886 application tests pass (8,759 assertions), scoped Pint
and `git diff --check` pass. Browser checks cover 3.3, 3.5 and invoice preparation
at desktop/mobile widths, including bulk autosave and reopening individual
checks. No routes or migrations changed. Next: complete a real laptop upload
queue and remaining review checks before sending the test event's approval link.


### Task-page consistency audit (27 September 2026)

- The shared tracker bar now places **Back to tracker** and **Back to event**
  together at the top left, including AJAX replacements. Tracker view context is
  preserved. Event tasks return to their scheduling event; booking-wide tasks
  return to the first active event, or the pending booking event details before
  scheduling. Crew-only editing keeps its permitted My Tasks destination.
- Booking tabs retain their tracker bar inside the active tab. Pending programme,
  title and studio-media tabs now show the correct task heading and a read-only
  pending bar instead of the booking bar or no bar. Duplicate navigation on photo
  uploads, programmes and crew availability has been removed.
- Every active task has a shared **Task instructions** disclosure: overview,
  ordered steps and current progress. See [the complete task guide](../handbook/Concert-Tracker-Task-Guide.md).
  Labels now consistently use Prepare programme, Video titles, Studio videos &
  slideshows, Concert editing and Video uploads. Deadline help explains that
  clearing an override restores the normal rule rather than deleting it.
- Photo release readiness uses the same checks for its visible notice and its
  server action. Incomplete uploads, empty albums, studio mismatch and cancelled
  events no longer appear ready to release. Stale release submissions return
  normal validation messages instead of an exception screen.
- Email pages explain inactive templates and cancelled events before sending.
  Existing review-readiness and duplicate-send checks remain. Photo announcements
  explain missing recipients or an inactive template. Corrections explain upload
  blockers and disable confirmation until the current upload is ready. Director
  release shows outstanding review/correction requirements.
- Invoice drafts display the same issue blockers enforced by the service:
  booking approval, changed source, business details and positive amount. The
  editor remains available to resolve them. Missing booking links now keep users
  on an explanatory task page rather than silently sending them to an invoice list.
- Title publishing explains missing programme/items, and manual status no longer
  offers Complete before publication. Empty video queues now have an explicit
  readiness reason. No readiness requirements or admin permissions were bypassed.

Verification: `sail artisan test` passes all 892 tests (8,923 assertions).
Scoped Pint and `git diff --check` pass. All 18 task pages were rendered against
local data and checked in Chromium at 1440px and 390px: both navigation links,
task instructions, tracker dialogs and horizontal overflow. Representative
screenshots were visually inspected. Render-only database work was rolled back;
no live emails, invoice sends, payments or releases were performed. No routes or
migrations changed. Next: walk a representative booking through these pages
using the existing local email rehearsal and real uploader completion signals.

## Booking invoice tab — 28 September 2026

- Moved 4.2 Invoice & payment into a booking tab with its own task bar and shared
  tracker/event return controls. Removed invoice preparation from 1.1.
- Draft creation, quote refresh, editing, discounts, notes, invoice PDF, payment
  records and history now use that workspace. Send invoice still opens the
  reviewed email workflow. No email, issuance or payment happens on page load.
- Tracker and source links open the tab; old invoice URLs redirect there,
  preserving access to a specific historical invoice. Invoice selection is
  scoped to the booking and retains existing admin permissions.
- Added coverage for canonical navigation, saved drafts, tracker filter context,
  voided invoice history, cross-booking isolation and customer denial.

Verification: `sail artisan test` passes all 894 tests (8,953 assertions),
including 18 studio billing tests. `sail pint --dirty --test`,
`git diff --check` and `sail artisan route:list` pass (647 routes). Prepare,
draft and paid states were rendered and checked in isolated Chrome at 1440px
and 390px, including task navigation, one tracker bar within the tab, no
horizontal overflow, and adding/removing invoice lines. Desktop and mobile
screenshots were visually inspected. Render fixtures were rolled back; no
invoice was sent and no live payment or email was triggered.

## Studio Hub video actions — 28 September 2026

- Moved Review & release concert video into Your actions for video bookings.
  It stays grey with an explanation until the current uploads, quality checks
  and any correction workflow are ready. Only the owner/director gets the link.
- Review becomes an outstanding action when available and completes on release.
  Photo-only concerts and DR portraits do not show a video-review action.
- DancePro progress now has Concert video released with the family-facing
  concert link and right-hand Access Code badge after approval and release.
  Reopened uploads withdraw stale release links/codes. Existing photo badges
  and customer access checks are unchanged.
- Added regression coverage for waiting, ready, released, reopened, corrections,
  office-contact permissions and non-video bookings.

Verification: full `sail artisan test` passes 896 tests (8,998 assertions),
including 20 focused booking/director workflow tests. `sail pint --dirty --test`
and `git diff --check` pass. Waiting, ready and released presentation states
were checked in isolated Chrome at 1440px and 390px with no horizontal overflow;
desktop and mobile screenshots were inspected. Visual fixtures used rolled-back
transactions. No real emails, approvals, releases or payments were performed.
No routes or migrations changed. Next: review the placement in the Studio Hub
and continue the existing full booking-to-delivery rehearsal.

## Studio Hub, invoice and profile layout — 28 September 2026

- Corrected overdue action date styling: the existing overdue labels now turn red
  on portrait requirements, seating, programme and studio-video rows. Completed
  actions retain their completion label; invoice balances use overdue red too.
- Studio invoice list/details share the Hub header and Events/Messages/Invoices
  tabs. The list starts with current booking estimates, grouped by booking and
  event dates, using the exact timeline quote cards and calculation path. Cancelled
  events/bookings are excluded; confirmed family numbers update the estimates.
  Issued invoice amounts remain their stored, separate invoice values.
- Studio Hub is a distinct icon/button before adjacent My Media/My Profile links.
  Sign out lives on My Profile, its redundant My Media return link is removed,
  and profile tabs use the shared sticky Hub tab style.

Verification: full `sail artisan test` passes 899 tests (9,039 assertions).
The focused booking, invoice and profile suite passes 38 tests (403 assertions).
`sail pint --dirty --test` and `git diff --check` pass. Hub tabs, invoice list
and detail, profile tabs and overdue states were checked at 1440px and 390px
in Chrome, with no horizontal overflow. Screenshots were inspected and overdue
dates verified as red. Visual fixture changes were rolled back. No routes or
migrations changed; no real messages, payments or releases were performed.

## Hub submenu position and summary links — 28 September 2026

- Studio Hub and My Profile submenu navigation retains vertical scroll and the
  horizontal tab-strip position. This applies to the shared section-tabs pattern;
  ordinary navigation, anchors and modified/new-tab clicks keep normal behaviour.
- Restored all three Hub summary boxes to invoice list and detail pages. Shared
  links open Events, unfinished event actions (expanded), or Messages. Viewing
  invoices does not mark studio messages read.
- Added invoice summary/navigation and message-read regression coverage. Desktop
  and phone browser checks exercised mouse and keyboard tab switching, normal
  navigation, invoice layouts and red overdue dates; screenshots were inspected.
- Initial overlapping test runs contended over the shared testing database;
  verification was rerun as a single full suite. No production data was changed.

Verification: the clean full `sail artisan test` run passed all 900 tests
(9,056 assertions). `sail pint --dirty --test` and `git diff --check` passed.
Browser checks passed at 1440px and 390px. No routes or migrations changed.
Next: review these navigation refinements in the Studio Hub; the proposed
My Media top-level tabs remain a recommendation, not an implemented change.

## Tracker counts and compact controls — 28 September 2026

- Fixed summary aggregation dropping earlier events when associative task keys
  were flattened. All events now contribute; shared booking tasks count once,
  while event-specific seats/tasks retain their separate counts.
- Pending events contribute to Open and Needs attention and remain visible in
  both date/studio attention views. Red Ready to release and Corrections states
  are actionable, alongside overdue work, failures and due follow-ups.
- Sidebar now uses the same live task-count calculation across all phases for
  the selected tracker year, rather than the number of unread alert records.
  Tracker visits refresh immediately; other admin pages cache the count for up
  to 30 seconds. Alerts history remains separate. Summary cards follow the
  selected phase and retain totals when filtering rows to Needs attention.
- Moved Date/Studio tabs into the tracker toolbar, fullscreen to its right, and
  the phase filters into compact buttons with colour squares inside the sheet.
  Removed both explanatory paragraphs and the old top phase pills. Left-aligned
  the page heading and wrapped long vertical task headings to two shorter lines.
- Regression coverage checks multiple events, shared tasks, pending events in
  both views, completion/cancellation, year boundaries and sidebar agreement.
  Desktop/mobile Chrome checks cover layout, wrapping, phase navigation and
  fullscreen; screenshots were inspected. No booking or event data was changed.
- The full-suite check also exposed an incomplete programme snapshot without an
  event type. The shared programme lookup now treats it as unavailable instead
  of throwing while the sidebar calculates task counts.

Verification: final full `sail artisan test` passes 901 tests (9,086 assertions).
The focused tracker/bar checks pass 42 tests (712 assertions), and editing checks
pass 5 tests (94 assertions). `sail pint --dirty --test` and `git diff --check`
pass. Browser checks passed at 1440px and 390px, including fullscreen. No routes
or migrations changed. Next: review the compact tracker in normal use.

## Fullscreen filters and release access codes — 28 September 2026

- Expanded phase filters and Date/Studio view links fetch the same server-rendered
  tracker page and replace its contents without replacing the fullscreen element.
  Native and fallback fullscreen survive. URLs, summary boxes, keyboard focus,
  tooltips and browser history follow the new selection. Failed loads retain the
  current view and explain that the user can retry; normal/new-tab links retain
  ordinary navigation.
- Added Access Code badges to applicable photo-release cells using the studio's
  configured gallery code. Final video-release cells use the current concert or
  code-group value only after actual release, independent of manual completion.
  Missing/unrecoverable codes and reopened/unreleased videos show no code.
- Reused the Studio Hub code validation path and existing admin authorisation.
  Added regression assertions for both tracker views, video release and renewed
  upload/review, and unauthorised access.

Verification: full `sail artisan test` passed 901 tests (9,098 assertions);
focused tracker/director checks passed 44 tests (743 assertions). Formatting and
whitespace checks passed. Chrome verified native and fallback fullscreen through
all five phase selections, both views, keyboard activation, Back/Forward,
tooltips, failed-request retry and exit. Code-cell layouts passed at 1440px and
390px. No routes or migrations changed, and no live releases were performed.

## Admin navigation notification roll-ups — 28 September 2026

- Added badges to pending support and due customer follow-ups, their queue tabs,
  and the Customers parent. Pending events now badges its tab, Event management
  and Hub management; Hub management also includes action exceptions once.
- Retained the Studio messages unread-message badge and added My Hub's existing
  red shift-action count to the admin menu. Zero-count badges disappear, and
  parent counts remain visible while their submenus are collapsed.
- Shared pending-event eligibility between the approval queue, navigation and
  tracker, excluding cancelled, declined and already scheduled items. Navigation
  counts all years; the tracker retains its year filter. Counts reuse one result
  per request and refresh on the next page load.
- Added [count definitions and follow-up candidates](../handbook/Admin-Navigation-Notifications.md).

Verification: full `sail artisan test` passed 903 tests (9,149 assertions).
New feature coverage checks queue/tab agreement, roll-ups, excluded records,
resolution/read/completion and access restrictions. `sail pint --dirty --test`
and `git diff --check` passed. Chrome checks passed at 1440px and 390px for
pending-event, support and follow-up pages, including collapsed-parent badges.
Temporary layout fixtures were rolled back. No routes or migrations changed.

## Crew header and My Tasks naming — 28 September 2026

- Crew web pages now place the logo, Back to Media / Back to Admin and profile controls in the top header row, with evenly spaced crew navigation below. The menu wraps on narrow screens.
- My editing is renamed My Tasks in the menu, page title, heading and task return links. It continues to list assigned concert edits; other tracker tasks have not yet been added.
- Chrome checks passed at 1440, 900 and 390 pixels, including eight menu items with Account security enabled, equal desktop spacing, active My Tasks state and no horizontal overflow or JavaScript errors. Scoped Sail Pint passed. No routes or permissions changed.
- Full verification: `./vendor/bin/sail artisan test --compact` passed all 919 tests / 9,332 assertions; `git diff --check` passed. Next: select any additional tracker tasks to expose through My Tasks.


### Grouped post-concert email — 29 September 2026

- Task 2.4 uses the same fixed, inclusive 20-day booking windows as 1.4. The window starts with the first non-cancelled event; it does not chain successive 20-day gaps. Separate bookings and later groups remain separate.
- All events in a group share one task, due the day after its final event, plus duplicate protection, sent history and completion. Scheduling is not completion. Automation uses the final event with a default one-day offset, preserving its configured mode and send time. Admin can choose another offset.
- Early sending shows a yellow timing warning with the recommended date, but does not block sending or scheduling. The worker honours the chosen send time and retains existing booking-change invalidation. Duplicate-send and resend confirmation rules remain unchanged.
- Replaced legacy post-concert copy with Studio Hub upload, photo access, owner review/corrections/release, and customer streaming/individual or multiple download instructions. Photo/video sections follow booked services. The button opens Concert events; the email never claims media is already released.
- Migration consolidates historical tasks and retains sent bodies. Old pending/failed messages are cancelled for review because their individual-event dates and content are stale. Rollback deliberately does not split histories or restore outdated copy.

- Verification: full Sail run passed 1,077/1,078 tests; the sole outdated individual-task assertion was corrected. All 58 affected tracker/booking tests then passed (1,193 assertions). Desktop/mobile email and scheduling preview checks, targeted Pint and diff checks passed. Local migration applied; no emails sent.

- Timing-warning follow-up: removed early-send enforcement from the form, queue and worker; optional early scheduling and configured automation offsets are accepted. All 1,079 Sail tests passed (11,493 assertions), with desktop/mobile form checks and targeted Pint/diff checks passing. No emails sent.

### One-off tracker email editing — 29 September 2026

- Audited every tracker email entry point: 1.1 booking confirmation and Received/Partial Approval/Cancelled notices, 1.2 seats & families, 1.4 concert week, 2.4 post-concert, studio-file requests, 3.5 review link, 3.6 corrections, 4.1 photo release and 4.2 invoice.
- All use the same one-off subject/message editor. The formatted message itself is editable with paragraph/heading, bold/italic, numbered/bulleted list, callout and link controls. There is no separate source/preview step. Branding/signature stay outside the editable body, and Reset restores the original generated message. A plain-text fallback remains when JavaScript is unavailable.
- Visual edits convert to the existing Markdown message format using Turndown, then pass through the existing server-side safe formatter. Pasted text does not import active HTML. Blank/oversized bodies and subject header injection are rejected. The standard template remains separate and collapsed; automation continues to render standard templates when no one-off fields are supplied.
- The exact subject/body are frozen on each delivery (including scheduled sends) and can be expanded in delivery history. Booking-status notices retain their existing draft/history record. Photo release edits apply to the current recipient batch; previously sent/queued recipients remain skipped. Existing readiness, stale-context and duplicate-send checks remain in force.
- Verification: all 1,082 Sail tests passed (11,539 assertions). Direct-editing browser checks passed at 1500px and 390px, including formatted text, safe links, reset, blank-message validation and unchanged templates. Targeted Pint and production asset build passed. No emails sent; no routes or migrations changed.

### Completion ownership and task-bar date — 29 September 2026

- Completing a tracker record with a recorded user now fills its blank assignee from that completer. Existing assignments are preserved, including when a status-only update omits the assignee field. Automated completions without a recorded person remain unassigned.
- Applied a data migration to fill blank assignees on already-completed records with known completers. Rollback deliberately retains these assignments because later manual changes cannot safely be distinguished.
- The shared tracker bar replaces Due date with Completed on (date and time) only while the task is complete. It uses the source completion time for automatic tasks and the stored task completion time for manual/email tasks; missing historical dates show Not recorded. Completed tasks no longer open a due-date dialog. Reopening restores the normal deadline control.
- Photo upload status now includes the last required photo-role completion time. Crew readiness uses the latest publication/acknowledgement, photo release can use album publication dates for older ungrouped releases, and video readiness uses the last recorded upload/edit/check/inclusion prerequisite time.
- Verification: all 1,086 Sail tests passed (11,567 assertions). Desktop/mobile browser checks passed at 1500px and 390px, confirming the assignee, completion date/time, hidden deadline control and responsive layout. Targeted Pint and diff checks passed; migration applied and rollback behaviour reviewed. No emails sent.

### Studio media file sizes — 29 September 2026

- Task 2.5 shows the stored file size beside each current studio video/slideshow filename, using readable KB/MB/GB units and an exact-byte tooltip. Missing historical sizes show Size unavailable. No storage requests or upload/review workflow changes are needed.
- In-memory Blade rendering verified KB, MB, GB and missing-size labels. Scoped Sail Pint and diff checks passed. All 1,086 Sail tests passed (11,567 assertions). No routes or database changes. Next: review uploaded file sizes in task 2.5.


### 4.2 concert video invoice quantities - 30 September 2026

- Reviewed all four supplied Square invoice PDFs. Video invoice drafts now show
  concert quantities at $1,500 or unique family quantities at $15, selecting the
  greater existing quote. Equal totals use flat rate. Family-rate descriptions
  include the number of concerts, with correct singular/plural wording.
- Shared rate constants keep booking quotes and invoice lines aligned. Draft
  creation and quote refresh multiply quantity by unit price for totals/GST.
  The invoice preparation page explains the rule. Existing issued amounts and
  manually edited drafts are preserved; the existing refresh action can rebuild
  a draft's lines explicitly.
- Verification: 24 invoice feature tests passed (276 assertions), including
  one/two concerts, equal-rate totals, 151/584 families, excluded portraits and
  cancelled concerts, and switching a refreshed draft from flat to family rate.
  Both actual invoice PDF layouts were rendered and visually checked using
  unsaved synthetic samples. Scoped Pint passed. The full Sail suite passed
  1,100 tests / 11,712 assertions.
- No invoice records were created for PDF review, no existing invoices changed,
  no invoice emails sent and no payments initiated. Next: review a new video
  draft in booking tab 4.2, or refresh an existing draft to use the itemised lines.


### Whole-event photo release and DR import - 30 September 2026

- Redesigned 4.1 around selectable event cards for the current studio/year, with
  covers, dates, photo/album counts and readiness explanations. Select all ready
  selects whole events. Release name and watermark are collapsed advanced options;
  release history and the one-announcement flow remain on the same task page.
- Event selections are checked server-side and expanded to all photo albums.
  Mixed studios, cancelled/unapproved events, unfinished roles and previously
  grouped/released albums are rejected. Publication is atomic; then the admin
  reviews one announcement listing every included event. Retries skip recipients
  already queued/sent for this release.
- Customers browse individual event cards under the studio, without a release-group
  folder. DR portraits still open directly to images. Old explicit group URLs remain
  supported. Published native photos can be accessed while a booked concert video
  remains draft; photo release never grants video streaming/download access.
- DR Import Photos is restored as an optional copy/review tool with a single
  destination and no black-frame splitting. Manual Finder copy/paste remains valid.
- Removed “per unique family” from newly generated family-rate invoice descriptions.
  Quantity, pricing, GST and the greater-of-two-rates rule remain unchanged.
- Verification: focused 45 Laravel tests / 614 assertions passed; 111 Swift tests
  passed and the signed uploader rebuilt. Desktop/mobile selection checks passed.
  Full Sail regression passed: 1,102 tests / 11,740 assertions. Scoped Pint and
  diff checks passed. No real galleries released or email announcements sent.

### Studio invoice acknowledgements and DR upload review — 30 September 2026

- Studio Hub invoice tabs now count unpaid issued invoices. Paid cards and invoice
  details show a green Invoice paid confirmation; partial payments keep the badge.
- Confirmed payments generate branded email, Studio messages and an authorised
  billing account notification, with a balance summary and invoice link. Receipt
  snapshots prevent repeat confirmations; delivery failure leaves payment intact.
- Energy's DR upload was checked through the tracker service: 34 verified photos,
  no pending photos, role P Complete and task 3.1 Complete. An already-open tracker
  needs refreshing to see completion; no upload record or override was changed.
- Verification: focused billing/welcome/studio-message suite passed 32 tests and
  388 assertions. Desktop/mobile invoice pages and branded receipt were reviewed.
- Full Sail suite passed all 1,104 tests (11,772 assertions). Scoped Pint and
  `git diff --check` passed; the receipt migration applied locally. No real
  payments, customer email sends or gallery releases were performed.
