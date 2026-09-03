# Crew Scheduling Epic

## Purpose

Build DancePro's crew scheduling and event-operations system inside the existing
Laravel application. The system should reduce administration while preserving a
collaborative, trust-based working culture.

## Current Status

The Crew Hub also includes a tabbed **My Crew** contact directory. Active crew
members can see crew names and phone numbers and open a private My Chat
conversation. Competition entries show the event organiser and phone number.
Reusable studio and competition contacts support an ordered list of staff,
including each person's role, phone number and one or more validated email
addresses. Multiple addresses for a staff member are entered as a
comma-separated list and exposed as individual, deduplicated recipients for
future communication workflows. New competition events take an organiser
snapshot from the first listed competition staff member. The crew directory
shows every saved studio staff contact, with tap-to-call links on supported
devices. Studio and competition overviews also show uploaded logo thumbnails.

The Crew Hub includes a mobile-friendly **My Chat** inbox with event and direct
conversations. Published crew assigned to an event can access its shared event
chat. An empty event chat appears automatically in **Upcoming** from seven days
before its first shift; event chats with messages remain available afterward.
Crew can start private direct conversations with other active crew without
exposing personal contact details. Opening a chat records its messages as read.
The versioned crew API provides the same inbox filters, cursor-paginated message
history, posting and message-bounded read state. It also exposes only the
authenticated crew member's notifications. Event membership and direct-chat
participation are checked for every read and write, and inaccessible chat UUIDs
are returned as not found to avoid disclosing private conversations.

Phase 0 repository inspection and integration design is complete. The first
scheduling foundation now includes crew profiles, clothing sizes, calculated
length of service, configurable role qualifications, versioned contracts and
auditable historical signature recording.

The first administrator workflow is also complete. Staff can create and edit
crew profiles, mark crew active or inactive, maintain qualification status,
create versioned contract records, enter signatures for existing staff and
correct signature dates without removing the earlier audit event.

The administrator sidebar separates operational tools under **Hub Management**
and the existing dashboard and delivery content under **Media**. Administrators who also have a crew profile
receive a **My Hub** link to their own shifts, and their Crew Hub header includes
a **Back to Admin** action.

Hub Management includes a non-payment **Crew Management** workspace with tabs
for Crew, Roles, Contracts, Recognitions & Rewards, and Training. The Contacts
workspace contains only studios and competition contacts. Contractor timesheets,
invoices and rates remain under Crew Payments. Recognition retains a placeholder
administration page. Training supports permanent course completion, draft and
published courses and role targeting. The course builder groups ordered content
blocks into draggable sections; blocks can also be reordered or moved between
sections. Its first content catalogue includes text, images and galleries,
video, audio, files, embeds, callouts, processes, accordions, flashcards,
labelled images, scenarios, confirmations, external links and quiz questions.
A full assessment block supports weighted single-choice, multiple-choice,
true/false, short-answer, numeric and ordering questions. Administrators set a
pass mark, optional attempt limit and whether answer feedback is shown. Every
assessment submission is retained with its score, answers and per-question
result; passing the assessment completes its course block while failed attempts
remain available for review.
Administrators can assign a course to selected crew, set a due date and review
assigned, in-progress, overdue and completed training in one progress table.
Started and completed enrolments are protected from accidental removal.
The central training overview provides a crew-by-course compliance matrix,
status and course filters, individual crew histories, manual reminder records
and a CSV export. Reminder records describe contact that occurred; they do not
claim an email or phone notification was automatically sent.
Courses linked to a crew role may explicitly award that role qualification on
successful completion. This is opt-in per course; ordinary role-targeted
learning does not silently change a crew member's qualifications.
A course may be a single quick equipment update or a larger sequence for new
crew and role training. Updated learning is issued as a linked renewal course
rather than expiring or overwriting the original completion.

Hub Management also contains an **Event Management** workspace with Event
Bookings, Pending Events, Event Availability, and Event Types tabs. Pending
booking items are reviewed separately from the live availability matrix. Event
types have editable names, codes and descriptions, map to the protected concert
or competition workflow, and are made inactive rather than deleted so historical
records remain stable.

The managed type is carried from Add Event or the studio booking form into the
booking item and approved scheduling event. It is also used by availability
filters, crew-role applicability, Resources and Pre-Start Checks. The protected
concert or competition category continues to drive the underlying workflow,
while the managed type provides the precise operational classification.

Reusable crew handbook and role resources are administered from the Resources
tab in Crew Management. Pre-Start Checks templates are administered separately
from the final tab in Event Management; the former combined Resources &
Checklists navigation item is no longer exposed.

The Roles tab supports editing existing role definitions and includes a matrix
of active crew against active roles. New checks create approved qualifications;
existing qualification statuses and dates are preserved; clearing a check removes
that role from the crew profile. This matrix and the individual crew profile edit
screen operate on the same qualification records.

Role event types use the managed Event Types catalogue, plus Any event. This
keeps role eligibility consistent with the precise type stored on event records.

Contract versions can be viewed in full from Crew Management. Administrators can
duplicate an existing version into the contract editor; its name and wording are
prefilled, but the new version and effective date must be reviewed before a new,
separate record is created.

Crew personnel records now also cover address and emergency contacts, encrypted
payment and superannuation data, health and identification details, safety checks and
expiry dates, equipment, travel area, private profile photos, and multiple
vehicles per crew member.

## Crew Profile Visibility

My Profile includes a conditional achievement showcase. Approved role
qualifications appear as Training badges, while completed service and concert-count
thresholds appear as Milestones, and visible awards appear as Recognition badges.
Empty categories, including the future Rewards category, are not rendered at all.

### Recognitions and rewards

Administrators can maintain a reusable bank of recognition badge templates and
personalise the title, message, icon and colour design when making an award. An
award can be given to multiple crew members, optionally linked to an event, and
can be visible or private on the crew profile. Awards store a permanent snapshot
of their presentation and wording, so later template edits do not alter history.

Visible recognitions appear as round badges on My Profile, using the established
DancePro roundel and hover details. Available designs are DancePro blue, gold,
purple, teal, red, green, midnight and rainbow. Rewards remain inactive.

The crew hub includes a self-service My Profile page for identity and contact
details, address, emergency contact, clothing sizes, encrypted payment details,
dietary/medical information, WWCC details and multiple
vehicles. It shows active contract signing status and warns about missing,
expired or soon-expiring WWCC information.
Signed-in crew can change their password after confirming the current password.
The public login screen also provides a non-disclosing forgotten-password flow
using Laravel's time-limited reset tokens. Local reset emails are captured by
Mailpit.

New crew accounts use an invitation-based onboarding flow. An administrator
enters only the crew member's preferred name and email address, then the system
sends a one-time password setup link. The crew member verifies control of that
email address, chooses their own password and is directed to complete their
profile before using the rest of the Crew Hub. Administrators can see whether
onboarding is awaiting setup or complete and can resend an invitation. Random
temporary passwords are never disclosed or sent by email. Existing crew were
marked complete when onboarding tracking was introduced.
Mobile number, a complete primary address (address line 1, suburb, state and
postcode), and Working With Children Check number and expiry are required to
complete onboarding. Address line 2 and all other profile details remain
optional for now.

Active contracts are reviewed and signed inside the Crew Hub. The crew member
must explicitly accept the electronic-signature wording, type their full legal
name and confirm their current password. The audit record retains the signed-in
account, exact contract checksum and version, consent wording, timestamp, IP
address and browser information. Contract versions are immutable after creation;
changed wording must be issued as a new version.

Existing staff migration remains a separate supported path. Administrators can
create a crew record without sending an invitation, enter the date and context
of a previously signed agreement, and invite the person later. These records are
labelled as administrator-recorded historical signatures, and corrections append
to the audit history rather than replacing it.

When a Google Maps browser key is configured, the crew profile provides
Australian-only address autocomplete. Selecting a result fills street, suburb,
state and postcode from structured Google Places address components while the
normal fields remain available as a fallback. The browser key must be restricted
to the application's web domains in Google Cloud.

Authenticator-app two-factor authentication is implemented for web accounts
behind disabled launch switches. Enabling it challenges configured accounts;
enforcement additionally sends unconfigured admin and crew accounts to security
setup before allowing access. TOTP secrets are encrypted, QR codes are rendered
locally and recovery codes are encrypted, one-way hashed and single-use. See
[Two-Factor Authentication](../decisions/Two-Factor-Authentication.md).

The crew-facing profile does not expose administrator-only personnel fields.
The following fields are administrator-only:

- Commencement date and calculated service time.
- Employment status.
- Usual travel area.
- Equipment owned.
- Internal notes.

Profile photos, driver's licence details and first-aid details remain supported
by the data model but are hidden from the interface for now. Working With
Children Check details remain visible because they are operationally important.
Superannuation details also remain encrypted and supported by the data model,
but are hidden from both administrator and crew interfaces until they are needed.
Sensitive fields must never be exposed through a crew-facing resource without
an explicit field visibility review.

## Shift Period Rule

Competition scheduling never uses a full-day shift. Every competition shift is
independently either morning or afternoon. A crew member may work both periods,
but availability, allocation, acknowledgement, swapping and timekeeping remain
separate for each period. Concert shifts do not use morning or afternoon
periods; each exact-time concert or rehearsal shift is displayed as CON.

## Timesheets and Invoices

Timesheets and invoices share one administrator menu. Completed time records
appear automatically in the Pending section of My Timesheets. The workflow is
trust based: there is no crew submission or administrator approval step.
Administrators can monitor all completed records and see internal discrepancy
flags without blocking normal invoicing.

Live clock-in and clock-out are convenient but not mandatory, particularly for
concerts. Every past published competition or concert assignment appears in My
Timesheets even when one or both times were not recorded live. Missing times are
shown as Confirm times and the crew member enters or corrects the actual start and
finish before the record can be selected for invoicing. A competition cannot be
invoiced until every pending shift for that crew member and competition has both
times confirmed.

Live clock controls appear only on the single Next shift card. Clock in changes
to Clock out after the crew member clocks in, or two hours after the posted
arrival time when clock-in was missed. Competition team leaders can also clock
all unfinished crew out from that card. Manual time corrections remain in My
Timesheets before invoicing.

The Next shift card presents event-day actions as three separate steps: Clock
in, Pre-Start Checks and Clock out. Each completed step turns green, displays a
tick and shows the recorded completion time. Pre-start completion is derived
from the timestamp of the last applicable checklist item completed. The checks
button opens the assignment's existing role-specific checklist. A competition
Team Leader's crew clock-out applies the shared finish time to every unfinished
published crew member on that shift, including the Team Leader.

Invoice calculations retain the actual recorded times for audit purposes but
use quarter-hour payable times. Start times round down to the nearest 15 minutes
without going earlier than the shift's posted arrival (or scheduled start when
there is no posted arrival). Finish times round up to the next 15 minutes; for
example, a 9:05 pm finish is payable through 9:15 pm.

Crew explicitly select the pending records they want to invoice. Selecting a
competition selects all pending records for that crew member and competition,
and prevents it being combined with any concert or another competition. Concert
records may be combined for weekend, fortnightly, monthly or seasonal invoicing.
Every app-generated invoice stores an encrypted, immutable snapshot of the
contractor identity, address, ABN and bank details used when it was issued.
Later profile changes therefore do not alter historical payment instructions.
Every app-generated invoice line stores an immutable snapshot of the event,
role, hours, applicable rate and calculated amounts.

Crew who produce an invoice in their own accounting software email that PDF
outside the platform, then mark the selected work as externally
invoiced. The external invoice itself is not stored. Before submitting their
first app-generated invoice, crew set their next unused invoice number. The app
advances that crew-specific sequence thereafter and rejects reused numbers.
App-generated invoices are contractor-issued, unbranded A4 portrait documents
with Classic, Minimal and Modern presentation choices. All styles contain the
same details required by the Crew Member Processes handbook. Crew preview the
invoice and accept it; acceptance places it immediately in Admin > Crew Payments
as Pending payment. There is no email or separate invoice-ready step for an
app-generated invoice. Administrators can export it and mark it paid. A timesheet can
belong to only one app invoice or be marked externally invoiced, preventing
duplicate payment.

## Event Operations and Help

The admin Hub Management area includes a read-only Exceptions overview. It groups
unusual or incomplete records into All, Shifts & Events, Timekeeping, Payments and
Communication tabs. Exceptions draw attention to work that may need checking; they
do not add an approval step to the trust-based timesheet workflow.

Hub Management also opens with an operational dashboard summarising the next 14
days of events, published crew assignments, open cover requests, pending invoices
and the highest-priority exceptions.

Operational information is reusable at the correct level:

- A venue owns its map, parking notes and access instructions, so every event at
  that venue automatically uses the same information.
- An event owns its crew brief, Team Leader-only notes and programme/run sheet.
- Handbook sections and visual cheat sheets can target all crew or a particular
  event type and role.
- Checklist templates can target all crew or a particular event type and role.
  Each published assignment stores its own immediate-save completion state,
  including the completing user and timestamp.

Crew can open a shift detail page containing its venue map, brief, programme,
role-relevant guides and interactive pre-start checklist. Administrators can see
completion totals per assigned crew member from the event page. Help & Handbook
is permanently available in Crew Hub navigation and from every assigned shift.
Section 7 is app-native Help & Support; the supplied handbook retains sections
1–6 and 8 as uploadable PDFs. Resources and checklist wording remain editable in
the administrator Resources & Checklists page so incomplete concert material can
be added later without a code change.

Each scheduling event has one communication thread shared by administrators and
its published crew. Ordinary messages support operational questions, issue
reports and image/document attachments. Important administrator announcements
are visually separated, notify assigned crew and require an explicit per-person
acknowledgement. Unassigned crew cannot view or post in the thread. The thread is
available directly in the administrator event page and each crew shift detail
page, with Help & Handbook kept alongside it.

Event creation begins by selecting competition or concert because their required
details differ. Competition events are entered internally and have day-based,
morning/afternoon shifts with setup/set-down flags.
Concerts normally enter through the public booking workflow. Approval creates
draft scheduling events but never opens crew availability automatically.

Competition roles are selected once for the event and inherited by every shift.
The day builder accepts a date, morning and/or afternoon, and which selected
shift performs setup or set-down. New competitions are always drafts and do not
require exact times. Times are added later from the event overview. Posted
arrival is then calculated as 90 minutes before a setup shift, 45 minutes before
a morning shift, or 30 minutes before an afternoon shift. Set-down adds 20
minutes to the estimated finish. Opening availability is a separate deliberate
action with a response deadline.

Competition events and studios support uploaded JPG, PNG or WebP logos. Logos
share the supplied landscape ratio and are always displayed with containment so
the artwork is never cropped. Competition logos appear in administration and
crew availability; studio logos appear in administration and public studio
presentation.

## Scope

- Crew profiles and role eligibility.
- Venues, scheduling events, shifts and templates.
- Availability, rostering, acknowledgement, cover and swaps.
- Operational briefs, documents, communication and readiness checks.
- Equipment/media custody, timekeeping, payments and compliance.
- Public concert booking and deliberate linking to existing delivery concerts.
- Web administration, responsive crew web access and APIs for an iPhone client.

## Architectural Boundary

Scheduling is a new bounded domain. Existing `Studio`, `Concert` and media
records remain the delivery domain and are linked explicitly where appropriate.
Crew reuse existing Laravel users and authentication. There will be no second
Laravel backend.

## Related Documentation

- [Authoritative Project Specification](../specifications/DancePro-Crew-Scheduling-Project-Specification.md)
- [Phase 0 Integration Plan](../decisions/DancePro-Crew-Scheduling-Phase-0-Integration-Plan.md)
- [Architecture](../handbook/Architecture.md)
- [Authentication](../handbook/Authentication.md)
- [Concert Epic](Concert.md)
- [Competition Epic](Competition.md)
# Bulk concert event workflow

- Each concert or dress rehearsal submitted by a studio appears as its own row in the administrator event list.
- Administrators can select events across multiple studios and approve them, open availability, or close availability in one action.
- Booking approval and crew availability remain separate states. Approval creates a draft scheduling event; opening availability is explicit.
- Availability deadlines use a date chosen by the administrator and are always stored as 5:00 pm Perth time.

Local development includes a repeatable fictional dataset covering crew,
qualifications, vehicles, contracts and signature states, venues, concert
booking responses, competitions, shifts, role requirements and availability
responses. It is seeded through `LocalDevelopmentSeeder` and must never run in
non-local environments.

The administrator availability screen is a spreadsheet-style matrix with one
row per morning or afternoon shift. Pending concert approvals, competition and
concert details, venue, availability status, shift role assignments and every
active crew member's response remain visible together. Availability and draft
roster changes save inline; assignments do not overwrite the crew member's
response.

Roster cells use a compact visual key: 👑 Team Leader, ✏️ private pencil,
💌 published/sent and awaiting acknowledgement, ✅ acknowledged, and 🚩 an
issue needing attention. Each icon provides a plain-language hover explanation.
Material changes that reset acknowledgement continue to use 💌 because the
updated assignment has been sent again. 🚩 is reserved for availability or
scheduling conflicts.

The spreadsheet keeps three stable assignment columns labelled Role 1, Role 2
and Role 3. Each event supplies its own role title above the staff selector in
that slot, allowing competition, concert and rehearsal role sets to differ
without changing the overall grid.

For competition shifts, each assigned crew member has an inline Team Leader
checkbox beneath their technical-role assignment. It is kept out of the crew
availability cells and remains private while the roster is draft.
Publishing the roster displays 👑 for the selected Team Leader. Only a crew
member already pencilled into a technical role can receive this additional
responsibility, and only one Team Leader is selected per shift.

Each shift assignment supports immediate-save Bring and Take toggles for
equipment and media. Concert assignments can use 🔵 Video 1, 🟢 Video 2, 🔴
Video 3, 🟠 Backdrop 1 and 🟡 Backdrop 2; all event types can use 💾 Media.
The grid renders direction compactly: ➡️ before an item means bringing it,
➡️ after means taking it, and arrows on both sides mean both. Hover text states
the responsibility in words. Changing a published responsibility resets that
crew member's acknowledgement.

Each equipment row also has an Other free-text field for arrangements that are
not performed by the assigned crew member, such as “David is bringing it.” A
note-only item remains visible in the assignment with 📝 and its full wording
on hover.

The availability matrix remains the single source of truth for equipment
planning. From its Bring, Take and Other values, it calculates each numbered
kit's previous and next event, identifies same-venue stays, and flags overlapping
use, missing transport, unexplained holder-to-bringer changes and multiple
takers. These checks appear inline and on hover rather than in a separate
equipment administration workflow.

The matrix status column shows one operational status only; the availability
deadline is available on hover. An event automatically displays green READY
only when every required role on every shift has a published assignment and
every assignment has acknowledged the current material details. Any reset or
missing acknowledgement immediately removes READY.

Immediate-save roster changes preserve the administrator's page position,
spreadsheet scroll position and selected event checkboxes across the small data
refresh required to redraw assignment icons and controls.

The event selector is not shown as a checkbox. Clicking an event area toggles
selection and highlights all of that event's shift rows; links and interactive
controls do not change selection. Published rosters awaiting one or more crew
acknowledgements use the compact status label Assigned.

Horizontally scrolling role and crew headings render beneath the fixed left
event headings so they never obscure Date, Shift, Event, Venue or Status.

Competition shift labels are displayed in the Shift column as COMP-M and
COMP-A. Concert shifts have no morning/afternoon period and display only CON.
For studio-booked concerts, the Event column uses the studio name as its main
label and places the concert or rehearsal title underneath.

Roster construction is private. A shift assignment remains a pencil entry while
the event roster is draft, is not visible to crew and creates no notification.
Publishing the roster makes its assignments visible, records an in-app
notification and requires acknowledgement. An assignment is not an offer and
cannot be accepted or declined. Published allocations lock the crew member's
availability response for that shift. Material changes reset acknowledgement
and notify the allocated crew member to review the shift again.

The responsive crew hub is headed My Shifts. It keeps the next published shift
at the top and uses one menu for Availability, Acknowledge, Upcoming and
Completed. Availability and Acknowledge show alert counts when action is
pending. Saved availability has a strong selected state and written confirmation.
Publishing is event-wide: every required role on every shift must be filled
before crew are notified, and publication closes availability for the whole
event. Published allocations are also excluded defensively from Availability.
Private pencil assignments do not hide an open request because crew cannot see
or act on those draft roster choices.

The versioned crew API exposes the same rules through availability list/update,
assignment acknowledgement and assignment checklist endpoints. Mutating mobile
requests require a UUID `Idempotency-Key`; the actions set an explicit target
state so safely retried requests cannot duplicate a response or completion.
Ownership and published-status checks are always enforced on the server,
irrespective of the assignment or shift identifier supplied by a client.

Published crew can organise cover without supplying a reason. They choose one,
multiple or all currently eligible crew and may add one personalised message;
leaving it blank sends the generic request. Eligibility requires an active user,
an approved role qualification, no overlapping published shift and, when the
shift carries Team Leader responsibility, Team Leader qualification. The first
eligible recipient to accept receives the existing assignment atomically. Other
recipient requests close automatically, the replacement must acknowledge the
shift, equipment duties and Team Leader responsibility stay attached, and the
original crew member, other recipients and administrators are notified.
Published work shows role, Team Leader duty,
arrival/start/finish times, venue map access, parking/access notes and assigned
equipment or media responsibilities. Draft roster assignments remain excluded.

Timekeeping is trust-based and correction-friendly. Published crew can clock in
with one action, finish using an adjustable time, and later correct either value
without supplying a reason; a note is optional. Payable start uses the rounded
actual clock-in without going before posted arrival, so an early clock-in records
on-site presence without creating early pay. Every correction retains the old value,
new value, user and timestamp. Competition Team Leaders can apply a shared finish
to all unfinished crew, but existing finishes are never overwritten. Admin time records
show missing finishes, late clock-ins and corrections as supportive Check
recommended flags without rejecting or changing the submitted time.

Payment settings use a compact administrator matrix with crew across the top
and rate types down the side. Rates are crew-specific and effective-dated so a
new rate does not rewrite historical event previews; a general rate provides a
temporary fallback where a crew cell has not yet been completed. Competition crew are calculated
hourly from payable start to actual finish, with a separate Team Leader rate.
Concert and dress rehearsal roles use the configured fixed or hourly category;
Concert Photographer P2 uses the trainee hourly category while that crew-role
qualification is marked training. Competition equipment pickup and drop-off
allowances can be selected per assignment at $15 each; concert equipment movement
is included in the fixed event rate and has no separate allowance. An out-of-metro
travel allowance of $60 is available for either event type. Each event shows the base,
allowances, total preview, superable component and any missing information. A
fixed-rate concert lasting over seven hours retains its fixed base and is flagged
for manual calculation rather than guessing an overtime rule. Payment previews
do not approve, invoice or pay anything. Exact outlier thresholds beyond these
agreed rules remain deferred until real examples are reviewed.
