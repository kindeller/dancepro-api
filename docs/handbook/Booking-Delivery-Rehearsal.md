# Booking-to-delivery rehearsal

## Local test setup

Live email is not needed. This workspace uses local SMTP at `mailpit:1025`; inspect captured messages at <http://localhost:8025>. No external SMTP service is configured by this change. The application displays a local email rehearsal banner. Tracker delivery records carry an `is_simulated` flag and display **Test · sent** in history. This means captured by the local transport, not delivered to a customer's inbox.

New immediate tracker messages run synchronously after their transaction commits when the local Mailpit transport is active. Scheduled messages use the separate `concert-rehearsal` queue. Existing jobs remain untouched. Do not start a general queue worker or the whole scheduler for this rehearsal.

A fake/log transport is also labelled simulated. A simulated delivery cannot subsequently be processed through a live transport. Already sent deliveries are not sent again; failed deliveries can retry. Pending tracker mail for a cancelled event is cancelled at send time. Unsent tracker messages remain staff-only until delivery succeeds.

The new label applies to tracker email history. Other existing notification and conversation systems still have their own delivery logging; they use the configured local transport but do not all expose a Test label. Live deliverability, provider bounces and external reply ingestion still need separate verification.

## Test studio

Run from the repository root:

```sh
./vendor/bin/sail artisan db:seed --class=BookingDeliveryRehearsalSeeder
```

This seeder is allowed only in local/testing. It requires the existing `studio-director@example.test` account and never changes its credentials. It creates a separate **Booking Rehearsal Studio - TEST**, one fictional venue and three bookings:

- Mixed services: one portrait session and two photo/video concerts; 80 families; $2,000 photo minimum and $3,000 indicative video quote.
- Photo only: one concert; $800 photo minimum.
- Video only: one concert; 80 families; $1,500 indicative video quote.

Dates are set 21–23 days ahead on the first run. Stable UUIDs make reruns preserve dates, approvals and subsequent work. This is a fresh-workflow fixture, not an automatic reset or completion of the delivery process.

[Open Studio Hub](http://localhost/my/studios/91000000-0000-4000-8000-000000000001/communications) using the existing local studio-director account. **My timeline** opens the latest booking plan. **My booking** appears after the first saved submission and opens the latest submitted form. Use **See previous booking forms** on that page to view earlier submissions and their timelines. Use [admin bookings](http://localhost/admin/concert-bookings) for review and approval.

On 27 September 2026, the photo-only booking was approved and one concert-week message was captured for validation. The mixed-service and video-only bookings were left pending. No existing demo bookings were changed.

## One scheduled message and a repeatable clock

For a simulated delivery UUID from its staff email-history page:

```sh
./vendor/bin/sail artisan tracker:rehearse-email DELIVERY_UUID
./vendor/bin/sail artisan tracker:rehearse-email DELIVERY_UUID --at='2026-10-25 10:00:00'
```

The optional time applies only inside this command. It does not change the server clock, stored event dates, scheduler or other jobs. The delivery is sent only if due at that time; cancelled/sent deliveries remain unchanged. Recorded send/completion timestamps use the simulated time, while Mailpit's receipt time is the actual capture time. The command refuses non-local transports and records not marked simulated.

Automated regression tests use isolated fixtures and temporary clocks. Avoid advancing the global application clock or draining a queue to inspect one reminder.

## First implementation batch

- Individual and bulk approval preserve the explicitly linked studio; conflicting explicit studio selection is rejected. Studio name changes no longer break the saved association.
- Approval prepares a private, disabled draft media record for each linked concert or DR portrait session. This allows later photo sync without manual linking. Legacy bookings without a studio still require their association to be resolved; existing approved events are not automatically backfilled.
- Studio Hub photo/video/crew progress and authenticated timeline progress use the tracker calculation. Portrait photo releases are included. Published but disabled, unapproved or unavailable videos do not appear released.
- The authenticated Event timeline recalculates estimates from current booking items, excluding cancelled items. It displays tracker progress, completion dates, overdue dates and customer links. The original submitted form and its snapshot remain unchanged.
- The timeline's chronological plan remains an estimate. Actual minimum-spend review dates, finance records, director review/correction screens, fuller release-readiness checks and integrated deadline overrides in every planning entry remain later audit work. Studio submission checklists still distinguish their own submission actions from staff production progress; this batch does not replace those with automatic acceptance.

## Verification

Focused tests cover preserved studio links, conflict rejection, private draft media, original-form immutability, recalculated quotes, portrait release state, disabled-video state, customer authorisation, unsent-history visibility, retry, cancelled sends, live-transport refusal, fixture repeatability and an isolated scheduled-email clock.

The local smoke check captured the actual rendered concert-week email in Mailpit and rendered the approved photo-only timeline. These checks do not constitute the full booking-to-delivery acceptance rehearsal in the audit report.

Full suite: `./vendor/bin/sail artisan test --compact` passed **810 tests / 7,501 assertions**. Scoped Sail Pint, `sail artisan route:list --except-vendor` and `git diff --check` passed. The simulated-delivery migration ran successfully; its down method removes only the new label column. Desktop and mobile rendered-page checks found no horizontal overflow. The captured email contained the correct Studio Hub link and no unresolved template placeholders.

## Booking changes and final family numbers

Staff now open **Review and apply changes** from a scheduling event or booking. The booking editor shows the studio's request and requires an agreed resolution note. Saving the actual edits completes the request in the same transaction and records the actor, before/after details and resolution. Status-only completion is rejected; a no-change outcome can be declined. Requests cannot be applied to a different booking. The original submission remains unchanged.

Booking edits synchronise scheduling dates, venue, event type, linked media title/date/venue and managed crew requirements. Staff-adjusted setup allowances and additional shift offsets are preserved. Default setup changes between 60 minutes for photo-only and 90 minutes for video bookings; staff can retain the agreed two-hour allowance. Overnight finishes are supported. Existing crew assignments and custom role requirements remain for review; material changes reset crew acknowledgements. A venue-access override changes the first arrival only. Services remain booking-wide, as in the existing booking model.

Pending tracker deliveries for an edited or cancelled booking are cancelled with a recorded explanation. New messages carry a booking-context fingerprint which is checked again before sending. Invalidated messages cannot be resent with old content. The communication schedule holds them for review even when its rule is automatic; staff can schedule a replacement using the current recipient, dates and template. Cancelled items are excluded from new email event summaries and first-event scheduling. Legacy messages without a fingerprint cannot detect edits made outside this booking workflow; editing/cancelling through this workflow still invalidates their pending deliveries.

**Final family numbers** is available on the authenticated Event timeline and the admin booking. It records the unique performing-family count across all concerts, confirmation time and actor. Corrections create revision history, repeat submissions of the same count do not create duplicate revisions, and the original approximate count is retained. The current video quote uses the confirmed count and the existing GST-inclusive pricing rule. The tracker task is now completed from that record, not a manual tick. Cancelled/photo-only bookings cannot submit a count. Cancelling a concert or changing its service/type clears the current confirmation so the studio can reconfirm the revised lineup.

This batch does not create a studio invoice, collect money, reconcile payments or open director approval. Those remain the next audit steps; retail media orders and crew invoices are not used as substitutes for booking finances. It also does not send booking-change notifications automatically. Rehearsal messages remain captured locally.

Second-batch verification: full Sail suite passed **818 tests / 7,576 assertions**. After the final cancellation guard, all **8 booking-change tests / 76 assertions** passed again. Both migrations applied locally; their rollback methods were reviewed and only remove the added fields/history table. Scoped Pint, route listing and `git diff --check` passed. Rendered desktop (1440px) and mobile (390px) forms have no horizontal page overflow and 18px checkboxes. The temporary visual-review request was rolled back; no live customer email was sent and no old jobs were drained. These rendered-page checks and automated tests do not replace the later complete delivery rehearsal.

## Studio invoice batch

Studio booking invoices now have drafts, issuance, private Studio Hub access, downloadable branded PDFs, partial manual payments and audit history. Local simulated payments exercise the complete Hub flow without money movement. Online card integration includes Hosted Fields/3DS2, durable attempts and settlement reconciliation, but needs merchant configuration and sandbox verification before live use. Business/bank settings were initialised locally from the supplied invoice example; no customer invoice was issued and no live email/payment was sent by the implementation work. See [Studio invoices](Studio-Invoices.md).

The tracker and current Event timeline read invoice/payment source state. The separate director approval page and final-concert payment gate remain outstanding.

Invoice-batch verification: **829 tests / 7,691 assertions passed** in the full Sail suite. Scoped Pint, JavaScript syntax, route listing and whitespace checks passed. The single-/multi-page PDF output and 390px/1440px invoice pages were inspected, and draft item controls were exercised in the browser. No real payment or customer email was sent.

## Studio booking navigation and venue entry

The public booking form searches the loaded venue list by partial name or address as the studio types. Select a result with the pointer or arrow keys and Enter, or use **Other venue** beside the field to enter a name and address. Editing a selected venue clears its saved selection until another result is chosen. Each added event has its own venue field and a stronger separator.

Studio Hub links **My booking** and **My timeline** to the newest saved submission, including pending requests. Earlier forms retain their original submitted values and remain accessible through booking history; existing Studio Hub access checks protect every form and timeline. These pages use the studio logo header and **Back to My Events** returns to Concert events. My timeline no longer repeats the invoice shortcut or final-family-number entry form; those actions remain in their existing Studio Hub tasks.

## Booking drafts and submission receipts

Studio Hub bookings support **Save draft**, including incomplete contact/event details. A studio has one shared draft; authorised Studio Hub users can resume it with **Continue booking**. Saving a draft creates no booking events and sends no email. Stale browser tabs cannot overwrite a newer draft. Final submission runs the full booking validation and clears only the submitted draft version; My booking continues to refer to the latest submitted form, separately from any new draft.

Submitting a booking automatically sends a branded pending-approval acknowledgement to the signed-in account (or the form contact for a guest). Linked studios receive the same update in Studio messages and can see their requested events as pending in Concert events. Approval remains the existing review/confirmation process. The receipt state is stored on the booking; repeated submissions do not resend a successful receipt. A failed send does not remove the booking or studio notification and can be retried by resubmitting the same request within the duplicate-protection window. Mail is still captured in Mailpit locally. Transport acceptance is not proof of inbox delivery.

Opening Studio Hub tabs does not mark Studio messages read. Studio messages uses the same collapsed date/time, Read/Unread status, heading and down/up arrows as My Messages. Expanding an incoming message marks only that message read; the collapsed row also offers Mark as read. Read status is shared across authorised studio users, and the top message box and left-hand tab badge count only unread incoming messages (including sent tracker emails), not studio replies or unsent mail. Email tracking-pixel opens are separate from Studio Hub read status. Send a message sits above the message list and starts collapsed; Reply opens it and fills the subject/event. Validation errors reopen the composer, and optional event/attachment fields sit inside Advanced options. My Messages provides a **Studio messages** shortcut for accounts with Studio Hub access. Invoice estimates are headed **Quote estimates & minimum spends**, start collapsed, and use the same My booking / My timeline / Make booking (or Continue booking) controls as Concert events.

### Pending bookings in Event Availability

Submitted concert and DR portrait booking items appear in the admin availability spreadsheet before approval, interleaved with scheduled shifts by date/time and included in the same pagination. Pending rows show studio, event title, requested times, venue and a red **Pending approval** badge. Click the studio name to review the booking. Use **Advanced options → Status → Pending approval** to isolate them; search, studio and event-type filters also apply. Cancelled, declined and already scheduled requests are excluded.

These rows are previews of booking items, not scheduling records. They cannot be selected for bulk availability requests, assigned crew or answered by crew. No crew notification or availability round is created by viewing them. Approval replaces the preview with the scheduled shift in **Draft**; **Request availability** remains a separate deliberate step. The same pending rows appear in tracker 2.1's studio-filtered availability snapshot. Requested times help staff compare dates/venues manually; existing crew-assignment conflict flags still describe actual assignments.

### Booking and tracker task layout

The admin booking tabs and tracker task pages use the full content width for their main panels, including forms, task emails, editing checks, invoice preparation and photo release. Field columns and programme/title preview workspaces remain responsive inside those panels. The booking save bar aligns with the form.

The duplicate **Programmes & slideshows** panel is removed from **1.1 Booking & services**. Programme preparation remains in **2.2 Prepare programme**, and supplied studio media remains in **2.5 Studio videos & slideshows**.

Tracker bars use the same category accents as the tracker spreadsheet: Booking blue, Preparation yellow, Editing & approval purple and Completion green. This applies to pending booking tabs as well as scheduled events. The strip identifies the category; status boxes retain their separate completion and deadline colours.

### 1.1 booking status workflow

The 1.1 status box changes the booking itself, rather than applying a tracker-only override:

- **Pending approval** (red): initial submitted booking, awaiting review.
- **Received** (yellow): acknowledged before scheduling starts. Events remain unscheduled and unavailable to crew. Studio Hub shows Received without explanatory text. Saving opens an optional, editable acknowledgement email; staff can return without sending it.
- **Approved** (green): approves all remaining events and opens the existing booking-confirmation email.
- **Partial Approval** (green): select pending events to approve. Unselected events stay pending; only selected events create scheduling records. The notification lists each event's approval state. Select Approved when confirming all remaining events.
- **Cancelled** (grey): requires a reason, cancels the booking/events through the existing cancellation workflow and hides them from active lists. The recorded reason pre-fills an editable studio email. Closed bookings cannot be reopened through the status box.

Approved events cannot be moved back to Received or Pending approval using the status picker. Existing per-event and bulk approvals also maintain Partial Approval until no pending events remain. Venue validation remains required for events being approved.

Received, partial-approval and cancellation notifications use DancePro branding and a link to the account. Status changes do not themselves send emails. Sending records staff/date and an unread copy in Studio messages for linked studios. Booking notifications in 1.1 reopen draft/sent history; sent or in-flight messages cannot be sent again. Changed booking details invalidate old drafts, with a Prepare updated notification action where applicable. Received bookings are not automatically red attention alerts, but overdue manual deadlines/follow-ups still count.

The notification-history migration adds a table only. Rolling it back removes those notification records, not bookings or studio message copies. The separate status alignment migration converts existing mixed approved/pending bookings to Partial Approval; its rollback restores Received/Partial Approval to the legacy Pending status without removing event approvals.


## Grouped concert-week communication and programme deadlines

Events in the same booking are grouped in date/time order into fixed 20-day windows, inclusive of day 20. The next event outside that window starts another group; the window does not roll forward after each event. Cancelled events are excluded. Different bookings are never combined.

- **1.4 Concert week email:** due 10 days before the first event in the group, including dress rehearsal. One successful delivery completes all group members' 1.4 cells with the same sender/date. Queued/scheduled deliveries and resend protection are also shared. The preview identifies the events covered and the email includes only that group.
- **2.2 is Not required for dress rehearsals:** grey status, no programme deadline or editor. The booking tab defaults to a concert when one exists.
- **Programmes:** each concert still has its own programme, but all programmes in a group are due seven days before that group's first concert. Dress rehearsals do not set this date. Studio Hub, My timeline and tracker use the same deadline; existing explicit tracker date overrides remain available.
- Example: dress rehearsal 18 October, concerts 20 and 21 October → email due 8 October; both programmes due 13 October. Another event on 7 November is within the 20-day window; 8 November starts a new group.
- Email scheduling uses the same first-event date and a default offset of -10 days. Existing automation modes are retained; review-mode messages still need scheduling approval. Yellow starts three days before the due date; red starts after it.
- Existing sent email history is consolidated without rewriting or resending messages. Queued/scheduled/failed messages prepared under the old individual-event rules are cancelled for review to prevent obsolete or duplicate sends. The content/history migration intentionally retains consolidated history on rollback.
