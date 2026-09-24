# Concert Tracker

## Outcome

The top-level Concert Tracker now covers the plan's Booking, Preparation and
assembly, Editing and approval, and Completion task groups. Date and studio views
show source-derived and manual work together without duplicating completion state.

## Implemented

- Twenty-one operational checkpoints grouped into four phase tabs, plus a compact
  all-task view with vertical column labels.
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
- `{{ services }}`
- `{{ family_count }}`
- `{{ venue }}`
- `{{ start_time }}`
- `{{ finish_time }}`
- `{{ arrival_time }}`
- `{{ crew_names }}`
- `{{ gallery_access }}`

Templates may conditionally include a block with
`{{#if condition}}...{{/if}}`. Supported conditions are `video`, `photo`,
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
