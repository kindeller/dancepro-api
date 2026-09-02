# DancePro Crew Scheduling and Event Operations

## Project specification and implementation handover

- **Document status:** Authoritative requirements baseline
- **Last updated:** 29 August 2026
- **Primary audience:** Codex and developers working inside the existing DancePro PHP/Laravel project
- **Existing system:** DancePro competition and concert video delivery/streaming platform
- **New surfaces:** Laravel web administration, public concert booking form, responsive crew web interface, and an internal iPhone app
- **Initial crew size:** Approximately 15–20 subcontractors; all are believed to use iPhones

This document consolidates the decisions made for the DancePro crew scheduling project. Statements using **must**, **must not**, **is**, or **will** are agreed requirements. Items labelled **TBD** or **future consideration** must not be guessed or silently implemented as settled business rules.

This is a living implementation guide. A later practical decision confirmed by
the owner overrides an earlier statement in this document. When that happens,
the document must be updated so the superseded rule is not implemented again.

---

## 1. Project purpose

Build a DancePro-specific internal system for:

- collecting crew availability for individual shifts;
- creating and publishing competition and concert rosters;
- helping crew organise their own replacements and swaps;
- delivering shift details, venue information, programmes and job briefs;
- communicating with everyone assigned to an event;
- completing role-specific ready-to-start checks;
- tracking equipment and media custody;
- recording actual working times;
- calculating staff payment information, allowances and superannuation-related records;
- maintaining crew profiles and compliance information; and
- accepting concert booking information from studios through a public web form.

The new system will be part of the existing PHP/Laravel project. The existing project currently handles competition and concert video delivery/streaming. It does not currently contain scheduling modules or data that should be assumed to overlap with the new system. The new scheduling domain must be isolated cleanly while allowing selected, deliberate integration with the existing delivery domain.

The iPhone app must use the Laravel application as its backend. Do not create a second independent backend.

---

## 2. Product and cultural principles

### 2.1 Primary design principle

Every module must be helpful and efficient. The system must not feel inhibiting, authoritarian, accusatory, punitive, or as if DancePro does not trust its crew.

DancePro's work is collaborative and trust-based. The application exists to reduce administration, clarify responsibilities, help the crew work together, and bring unusual situations to attention without assuming wrongdoing.

### 2.2 Required behavioural principles

- Trust by default.
- Record actions clearly.
- Flag exceptions without blocking normal work.
- Prefer self-service and collaboration over unnecessary manager approval.
- Let crew correct honest mistakes and provide context.
- Avoid surveillance-oriented functionality.
- Do not implement continuous GPS tracking or geofencing as a default requirement.
- Do not create trust scores, performance rankings, public availability percentages, or punitive leaderboards.
- Do not describe a flag as proof that someone has acted incorrectly.
- Do not automatically remove pay because a time looks unusual.
- Restrict private, financial and compliance information to people who genuinely need it.
- Use red only for genuinely urgent or blocking matters.

### 2.3 Required language style

Prefer supportive language such as:

- **Check recommended** rather than **Violation** or **Discrepancy**.
- **Finish time looks different from the team** rather than **Abnormal time**.
- **Have you had a chance to view your updated shift?** rather than **Acknowledgement overdue**.
- **Organise cover** rather than **Abandon shift**.
- **Ready-to-start checklist** rather than **Compliance check**.

Flags should support outcomes such as:

- Expected — no action
- Confirmed with crew member
- Corrected
- Follow-up required

---

## 3. Users and permissions

### 3.1 Owner/administrator

The owner/administrator can:

- create and edit events, shifts, templates, venues and briefs;
- open and close availability rounds;
- see availability responses and non-responses;
- allocate staff;
- publish rosters;
- see acknowledgements;
- see self-managed replacement and swap activity;
- receive notification of completed swaps without needing to approve them;
- manage role approvals, rates, allowances, compliance details and staff profiles;
- review time flags and manual corrections;
- view all ready-to-start checks;
- approve studio concert bookings;
- decide when approved concert events become available to staff;
- create or confirm delivery-system event shells; and
- see the audit history.

### 3.2 Crew member

A crew member can:

- view and update their profile;
- respond Available or Unavailable for each offered shift;
- leave a special-circumstances note with an availability response;
- view their own calendar and shift list;
- acknowledge allocated shifts;
- view briefs, programmes, venue details, maps and parking/access information;
- communicate in event channels;
- complete relevant ready-to-start checklists;
- clock in and record an actual finish time;
- organise their own replacement or shift swap;
- see eligible available crew when organising cover;
- see how their time and payment were calculated;
- submit or correct time details with an explanation; and
- access annual payment/tax-time reporting when that module is implemented.

### 3.3 Competition Team Leader

Team Leader is an additional competition responsibility, not a standalone event type and not a concert role.

A competition Team Leader can:

- perform their normal assigned technical role;
- receive the Team Leader pay rate for the shift;
- lead on-site communication;
- see completion status for every crew member's competition ready-to-start check;
- complete the Team Leader checklist in addition to their technical-role checklist;
- use **Finish shift for team**; and
- assist with handovers and operational coordination.

There are no Team Leaders for concerts.

### 3.4 Studio contact

A studio contact uses the public concert booking form. They do not need access to internal crew scheduling, availability, pay, chat or compliance data.

---

## 4. Core domain concepts

The implementation should model these as separate concepts even if the final table names differ after repository inspection:

- **Studio:** the dance studio/company making a concert booking.
- **Booking:** the studio's submitted booking request and captured details.
- **Scheduling event:** the internal operational event used for staffing and event delivery.
- **Delivery event:** the existing system's hidden or published event used to hold programmes, videos and streaming/delivery content.
- **Shift:** one staffable period within a scheduling event.
- **Role slot:** a required role on a shift.
- **Assignment:** a crew member allocated to a role slot.
- **Additional responsibility:** a duty held on top of a technical role, such as Team Leader, equipment custody or media delivery.
- **Availability response:** Available or Unavailable for one shift, optionally with a note.
- **Acknowledgement:** confirmation that an allocated crew member has seen the current material shift details.
- **Venue:** reusable location, access and parking information.
- **Brief:** operational instructions assembled from the event, venue, studio and admin notes.
- **Ready-to-start checklist:** role/event-type checklist completed before work.
- **Time entry:** recorded clock-in, payable start and actual finish information.
- **Payment calculation:** hourly or fixed-fee calculation plus applicable allowances and superannuation-related data.
- **Event channel:** event-specific announcements, group discussion and issue reporting.
- **Equipment custody record:** who holds an equipment kit and what must happen next.
- **Workflow:** future production task tracking associated with an event.

Do not use one generic status field to represent unrelated lifecycle concerns. Booking approval, staff release, availability, roster publication, delivery publication and workflow completion must remain independently representable.

---

## 5. Event hierarchy and templates

### 5.1 General structure

A scheduling event contains one or more shifts. Each shift contains required role slots and optional additional responsibilities.

Templates should generate defaults while remaining editable per event. Templates must not prevent the administrator from adding or removing roles, duties or timing exceptions.

### 5.2 Competition events

Competition organiser details are maintained as reusable records in the
administrator **Contacts** page alongside Studios and Crew. Each record contains
the competition name and organiser name, email and phone number. When creating
a competition event, staff can select an active saved competition to prefill
those fields. The event retains both the reusable-record link and a contact
snapshot so historical event details remain accurate if the master contact is
changed later.

Competition availability may initially be offered using:

- Morning shift — times TBC
- Afternoon shift — times TBC

Exact start and arrival times can be added later. The placeholder is a shift placeholder, not whole-event availability.

#### Competition technical roles

- Videographer
- Photographer P1
- Photographer P2, optional
- Trainee Videographer, optional and linked to the videographer training them

Photographer P2 usually works from a corner close to the stage to capture different angles and close-ups. P2 is often used for photographers in training. Role eligibility must distinguish P1, P2/training and other approval levels so an inappropriate self-service replacement cannot occur.

Photographer P is a separate dress-rehearsal portrait role. It must not inherit the stage-photography guides or checklists used by concert Photographer P1 (or competition P1/P2), and Photographer P1 must not receive the portrait setup checklist.

Competition Photographer P1 and Competition Photographer P2 are also separate roles from Concert Photographer P1. Their qualifications and allocations must be stored independently even though the roster may show the shorter P1/P2 labels in a competition context.

Competition Videographer V and Concert Videographer V are separate roles. Qualifications, allocations, checklists and resources must be event-specific even though both rosters may display the shorter V label.

Concert DR Portrait Assistant A is a separate dress-rehearsal portrait role and is required alongside Dress Rehearsal Photographer P. It has its own qualification and allocation rather than inheriting a photography role.

Concert Photographer P2 is a separate, optional concert stage-photography role. It must not share qualifications or allocations with Concert Photographer P1 or Competition Photographer P2, and it is added only when a concert needs a second photographer.

#### Competition additional responsibilities

- Team Leader
- Collect equipment from the owner
- Bring/take equipment to the event
- Keep equipment for another event
- Transfer equipment to another crew member
- Return/drop equipment to the owner
- Take media for upload
- Drop media off to the owner

Additional responsibilities can sit on top of a technical assignment. For example, one person may be Photographer P1, Team Leader and responsible for taking media to upload.

Equipment collection, custody and return are separate actions. If a crew member keeps equipment for another event, the system must not create a return/drop-off expectation for the earlier event.

### 5.3 Concert events

Concert events must have exact times before staff availability is opened. Concerts must not use Morning/Afternoon or time-TBC placeholders.

#### Concert templates and default roles

| Concert event subtype | Default technical roles |
| --- | --- |
| Concert — video only | Videographer |
| Concert — photography only | Photographer P1; optional Photographer P2 |
| Concert — photography and video | Videographer, Photographer P1; optional Photographer P2 |
| Dress rehearsal portrait shoot | Photographer P and Assistant A |
| Dress rehearsal stage photography | Photographer P1; optional Photographer P2 |

Smaller photography concerts may use P1 only. Larger concerts may use P1 and P2. Concerts do not have Team Leaders.

---

## 6. Timing rules

The system must distinguish:

- event/performance start time;
- posted staff arrival time;
- estimated or posted end time;
- actual clock-in time;
- payable start time; and
- actual selected finish time.

Do not collapse these into one start/end pair.

### 6.1 Competition arrival rules

| Competition shift situation | Default posted arrival |
| --- | --- |
| Full setup required | 1 hour 30 minutes before the shift/event start |
| Partial re-setup required | 1 hour before the shift/event start |
| First shift of the day with no setup required | 45 minutes before the shift start |
| Afternoon or second shift of the day | 30 minutes before the shift start |

The 30-minute arrival for a second/afternoon shift provides time for traffic or parking delays, handover of important details, checking media, putting out flyers and waiting for a suitable changeover moment.

Full setup normally occurs during the first shift of the event/day. Some venues book other activities during a competition, requiring a partial pack-up followed by a partial setup. This must be representable as an operational interruption/reset in the event timeline and brief. Partial setup requires one hour.

### 6.2 Concert timing rules

#### Video concert

- The studio/theatre should be asked to provide access from two hours before the concert.
- Standard internal staff arrival is 1 hour 30 minutes before the concert.
- The administrator can set an earlier two-hour arrival for new staff or staff unfamiliar with the venue.
- The additional access buffer protects the setup when studio/theatre staff are late providing access.

#### Photography-only concert

- Standard internal staff arrival is one hour before the concert.
- Studio-facing wording can explain that the photographer normally requires auditorium access approximately 30 minutes before the concert.
- The public wording should not cause studios to panic if the photographer is not yet visibly set up earlier than needed.

#### Dress rehearsal portrait shoot

- Standard arrival/access is one hour before the shoot.

#### Pack-up

- The expected internal finish may be calculated as the supplied event finish time plus approximately 20 minutes for pack-up.
- The actual selected finish time, not the expected finish, controls actual time-based payment where applicable.

---

## 7. Public concert booking form

### 7.1 Purpose

Replace the current Google Form with a DancePro-branded public Laravel web page. Submitted data must enter the same Laravel application and become an internal booking record.

This form is specifically for studio concert-related bookings. Competition events may continue to be entered internally unless a separate future requirement is approved.

### 7.2 Form behaviour

The form should be conditional and dynamic rather than limited to a fixed number of events.

Studios should be able to:

- enter studio/company and contact details;
- select photography, video, both, dress rehearsal portraits and/or dress rehearsal stage photography as applicable;
- add multiple dress rehearsals;
- add multiple concert performances;
- optionally supply a concert title, and supply an event type, date, start time,
  finish time and venue for every event;
- select a reusable managed venue from a prefilled list;
- select **Other venue** and submit a proposed venue name and address when the
  location is not listed;
- explain whether multiple concerts are the same or different where operationally relevant;
- identify presentations/awards, tap, singing/musical theatre, slideshows or video presentations;
- provide approximate family numbers where required for video services;
- provide relevant technical and seating/access information;
- confirm the applicable photography, portrait and videography requirements; and
- provide notes about previous concert videos, preferences or priorities.

Selecting **Yes** for dress rehearsal portrait photography automatically changes
the first event type to **DR Portrait** so the required portrait event is not
overlooked. All applicable fields are required except the concert title. Video
delivery fields are required only when concert videography is selected.

During implementation, review the complete current Google Form and preserve required agreement text and operational questions. Do not invent replacement contract wording. Update timing wording to reflect the distinction between requested venue access and internal staff arrival.

Submitting an **Other venue** value must not create a reusable venue record.
The submitted name and address are retained on the booking item as an audit
snapshot. Before approval, an administrator must either match the proposal to
an existing venue or explicitly create a new managed venue from it. Booking
approval is blocked while any pending booking item has an unresolved venue.

### 7.3 Submission emails

On submission:

- send the studio a clear email summary of all details they entered;
- send the owner/administrator the same summary;
- assign a booking reference;
- state clearly that the booking has been received but is not yet approved/confirmed; and
- retain the generated email or a rendered copy against the booking where practical.

### 7.4 Booking review

The owner can:

- review the booking;
- edit or correct internal data as appropriate;
- approve it;
- decline it; or
- request corrected/missing information if that capability is implemented.

The studio receives the approval outcome by email.

### 7.5 Explicit version-one exclusions

The studio booking workflow in this version does **not** include:

- quote generation;
- online quote acceptance;
- a studio acceptance step;
- final-details collection closer to the event; or
- studio invoicing.

Final details and studio invoices are handled later/outside this version.

### 7.6 Booking and staff-release separation

Studio approval must not automatically open an event to staff.

Some studios book approximately one year in advance. The owner wants to approve those bookings while holding them internally. Concert availability should generally be opened for all relevant concerts together so crew can respond in one sitting and earlier-booked studios do not receive staffing priority merely because they booked first.

Required lifecycle:

1. Submitted
2. Under review
3. Approved or declined
4. Approved — held from staff
5. Approved for inclusion in a staff availability round
6. Availability open
7. Availability closed/rostering
8. Roster published

Booking approval and staff-release approval are separate decisions and must have separate state.

---

## 8. Delivery-system event shell integration

### 8.1 Intent

An approved concert booking may create a hidden/draft event shell in the existing video delivery/streaming domain. This avoids entering the studio, concert title, dates and programme information again before videos are added.

### 8.2 Separation of concerns

Keep two linked records:

#### Scheduling event

Used for venue, shifts, availability, assignments, briefs, checks, time, pay, equipment and crew communication.

#### Delivery event

Used for the studio association, concert title(s), shoot date(s), event code, programme, videos, streaming/delivery and publication status.

These records must be linked but must not be forced into one model because their lifecycles, visibility and permissions differ.

### 8.3 Shell creation rules

- Do not create a delivery shell when an unreviewed public form is submitted.
- Create it after the owner approves the booking, or expose an explicit owner action if repository inspection shows that is safer.
- Match to an existing studio record where possible and avoid duplicate studios.
- Create the delivery shell as hidden/unpublished.
- Populate studio, concert title(s), date(s), booked photography/video services and a programme placeholder.
- Link the booking, scheduling event and delivery event through stable identifiers.
- Permit videos and other delivery content to be added later.
- Make programme information accessible to both the crew brief and the delivery workflow without uncontrolled duplication.

One booking may map to one parent delivery event with multiple performances or to multiple delivery events. This is **TBD** pending inspection of the existing Laravel models and current video-upload/delivery workflow. Do not choose a structure before that inspection.

---

## 9. Availability

### 9.1 Response choices

Availability is shift-specific and has only:

- Available
- Unavailable

There is no whole-event availability response. There is no Maybe or Partially Available status in the current requirements.

Crew may leave a note for special circumstances.

### 9.2 Deadlines and reminders

- Every availability round has a response deadline.
- Non-response must remain visibly distinct from Unavailable.
- Send friendly reminders to crew who have not responded.
- The owner must be able to see response completion at a glance.

### 9.3 Editing and locking

- A crew member may edit their response while the availability round remains editable and they have not been assigned to that shift.
- Once that crew member is allocated to the shift, their availability for that shift is locked.
- They must not change an allocated shift back to Unavailable.
- If they can no longer work, they use the self-service replacement/swap process.

### 9.4 Concert availability round

Concerts have exact start and arrival information when released. The owner can select all approved concerts for an availability round and publish them together. The staff interface should make it efficient to answer Available/Unavailable for every offered concert shift in one session.

---

## 10. Rostering and acknowledgement

### 10.1 Roster construction

The admin web interface should support viewing by event, shift and crew member. It should show:

- required role slots;
- staff availability;
- role eligibility/training status;
- existing allocations and time conflicts;
- additional responsibilities;
- staffing gaps; and
- whether exact times are known.

Reusable event and shift templates are required.

### 10.2 Allocation is not an offer

An allocated shift does not require the crew member to accept it. Do not use **Accept shift** or language implying that an assigned crew member can simply refuse after allocation.

The system must instead request acknowledgement that they have seen the allocation.

Suggested wording:

> **Acknowledge shift**  
> I have seen this shift and understand my arrival time, role and responsibilities.

### 10.3 Acknowledgement states

- Not yet acknowledged
- Acknowledged
- Material details changed — acknowledgement required again

Friendly reminders continue until acknowledgement. The owner can see who has and has not acknowledged.

Material changes such as date, venue, posted arrival, shift time or role reset acknowledgement. Minor file or note changes do not automatically reset acknowledgement unless the owner marks the update as important.

Competition placeholder allocations can be acknowledged before exact times exist. Adding exact times later is a material change and requires a fresh acknowledgement.

---

## 11. Self-managed replacements and swaps

### 11.1 Principles

Crew are expected to organise their own replacement when they can no longer work an allocated shift. The process should normally complete without owner approval.

### 11.2 Replacement workflow

1. Assigned crew member opens the shift.
2. They choose **Organise cover** or **Find a replacement**.
3. The system lists eligible crew who previously marked that shift Available.
4. Filter out people who are not approved for the role, are already assigned, or have a time conflict.
5. The requester sends a replacement request with an optional message.
6. Recipient accepts or declines.
7. The first valid acceptance completes the transfer.
8. The roster updates atomically.
9. Both crew members are notified.
10. The owner receives a notification but does not need to approve.
11. The change appears in the audit history.

The original crew member remains responsible until a replacement has accepted and the transfer completes.

### 11.3 Shift swap

Two assigned crew members may propose an exchange. Both must agree. Eligibility and conflict checks apply to both sides. The two assignment changes must complete together or not at all.

### 11.4 Additional responsibilities during a replacement

The system must make clear whether additional responsibilities transfer with the base role. Do not silently transfer equipment custody, Team Leader responsibility, media delivery or other duties when only a technical-role replacement was requested.

If Team Leader responsibility is included, the replacement must be eligible and the pay rule must update. Equipment transfers must follow the equipment custody workflow.

### 11.5 Consequences of a completed change

- Remove event access from the outgoing crew member if they no longer have another assignment.
- Add event access for the incoming crew member.
- Update event-channel membership.
- Assign the correct ready-to-start checklist.
- Require the incoming crew member to acknowledge the shift.
- Retain a complete audit trail.

---

## 12. Calendar and shift views

Later practical decisions override earlier planning assumptions. In particular,
morning and afternoon periods apply only to competition shifts. Concert and
rehearsal shifts use their exact start and finish times without an M/A period.

Crew need:

- a clear next-shift home view;
- a chronological shift list;
- a calendar view;
- filters for availability requests, allocated shifts, completed shifts and shifts needing acknowledgement;
- venue and map links from each shift; and
- optional personal calendar integration/export.

The most important actions should appear prominently:

- Availability needed
- Shift acknowledgement needed
- Next shift
- Important event update
- Ready-to-start checklist
- Organise cover

---

## 13. Venues, briefs and programmes

### 13.1 Reusable venue records

A venue may store:

- venue name;
- street address and map pin;
- parking instructions;
- staff entrance;
- loading area;
- access instructions;
- venue contact;
- arrival instructions;
- accessibility details;
- photographs of parking/entry/setup areas;
- internet, power, sound or auditorium notes; and
- other operational information.

Sensitive access information should only be available to authorised/allocated crew and only when operationally appropriate.

### 13.2 Notes and visibility

Support at least:

- persistent studio notes;
- event-specific notes;
- venue notes; and
- admin-authored brief notes.

Each note should support a visibility level such as:

- Admin only
- Team Leader where applicable
- All allocated crew
- Include in generated job brief

Studio-specific information and event-specific information must be able to appear automatically in a draft brief.

### 13.3 Programme and documents

- Store the current event programme and other event files.
- Record version/update information where practical.
- Notify allocated crew when an important file changes.
- Allow important documents and briefs to be available offline in the iPhone app because venue connectivity can be unreliable.

---

## 14. Event communications

Each staffed event should support an event channel containing only authorised admins and allocated crew.

Required uses:

- send the programme;
- send event updates;
- ask operational questions;
- report issues or problems;
- attach documents/images where appropriate; and
- communicate with everyone working the event.

Separate important announcements from ordinary group discussion so critical information is not lost. Important announcements may require a read acknowledgement.

The Crew Hub provides a My Chat inbox for event channels and private direct
messages between active crew members. Event chats appear automatically for
allocated crew seven days before an upcoming event, even when no message has
yet been posted. The inbox supports All, Unread, Upcoming, Events and Direct
views. Direct chat does not expose private phone numbers or email addresses.
Self-service replacement requests may link into the relevant conversation.

Completed events can be archived while retaining the record.

---

## 15. Ready-to-start checklists

The existing DancePro website contains role pre-start checks of approximately 10–12 tick boxes. The content must be reviewed/imported during implementation; do not invent the checklist items.

Required checklist templates:

1. Competition Team Leader
2. Competition P1
3. Competition P2
4. Competition Videographer
5. Concert P1
6. Concert P2
7. Concert Videographer
8. Dress Rehearsal Shoot

Team Leader is completed on top of the person's technical-role checklist. Trainee staff use the appropriate technical checklist unless the owner later defines a separate trainee checklist.

For competitions, the Team Leader can see completion status for all crew checklists. For concerts, there is no Team Leader; each crew member is responsible for their own checklist and the owner can view completion remotely.

Store:

- assigned checklist template/version;
- each checked item;
- completed by;
- completion time;
- optional note; and
- whether the checklist was completed after any relevant deadline.

Checklist language and notifications must be supportive. The checklist is a practical readiness aid, not an employee test.

**TBD:** Confirm whether the Dress Rehearsal Shoot checklist is individually completed by each assigned person or is one shared checklist led by the primary photographer.

---

## 16. Equipment and media custody

Equipment movement must be treated as a chain of custody rather than unrelated event check boxes.

Track, at an appropriate kit or asset-group level:

- current holder;
- current location/status where needed;
- date/time collected;
- event for which it is needed;
- next event for which it is needed;
- whether it must be retained;
- planned handover recipient;
- handover confirmation by both parties where appropriate;
- planned return/drop-off; and
- actual return/drop-off.

Possible operational states/actions:

- With owner
- Assigned for collection
- Collected
- In transit to event
- At event
- Retained for next event
- Transfer pending
- Transferred to another crew member
- Return pending
- Returned

Media custody may use a similar but separate record where required:

- who has the media;
- whether it is being taken for upload;
- whether it is being dropped off; and
- completion confirmation.

Do not assign a return/drop-off action when the crew member is intentionally keeping equipment for another event.

---

## 17. Timekeeping

### 17.1 General principles

- Time entry is trust-based and correction-friendly.
- Staff should not be paid for simply opening the clock early.
- Staff must be paid for genuine event overtime beyond the posted end.
- Paid breaks do not need to be entered or deducted.
- Unusual records are flagged for review, not automatically rejected or changed.

### 17.2 Competition payable start

For competition hourly work:

```text
payable_start = max(posted_arrival_time, actual_clock_in_time)
```

Examples:

- Posted arrival 08:00, clock-in 07:45 → payable from 08:00.
- Posted arrival 08:00, clock-in 08:00 → payable from 08:00.
- Posted arrival 08:00, clock-in 08:07 → payable from 08:07.

Early clock-in may record that the person is on site, but must not create early pay.

### 17.3 Forgotten or corrected start

If a crew member forgot to clock in, allow a manual/corrected actual start entry with a reason. Flag it for the owner as a check recommended. Do not silently underpay or silently treat the correction as suspicious.

### 17.4 Finish time

- A crew member must select an actual finish time.
- **Finish shift** defaults to the current time.
- The crew member may adjust the time before submitting.
- If they forget to finish at the venue, they can return later and select the actual event finish time.
- A posted end time is an estimate and must not cap payment.
- If an event runs late, staff are paid until the actual selected finish time.
- A late-entered finish should record when it was entered and may be flagged for review.

### 17.5 Team Leader — Finish shift for team

At the end of a competition shift, a Team Leader can:

1. choose **Finish shift for team**;
2. select a shared finish time, defaulting to now;
3. see everyone assigned to that shift;
4. exclude anyone who left earlier or is continuing pack-up, equipment or media duties; and
5. confirm the finish for selected people.

Rules:

- Do not overwrite a finish time someone already submitted unless an explicit correction workflow is used.
- Record that the Team Leader supplied the time.
- Notify each affected crew member, for example: **Shift finished by Team Leader at 5:42 pm.**
- Allow a crew member to submit a different finish with a reason if genuinely required.
- The shared finish does not change each person's individual payable start or late-arrival calculation.

### 17.6 Time flags

Potential **Check recommended** conditions include:

- finish time substantially earlier than the rest of the same team/shift;
- finish time substantially later than the rest of the same team/shift;
- missing finish time;
- start or finish entered manually well after the event;
- late clock-in;
- corrected start/finish;
- unusually long shift; or
- a finish time inconsistent with known continued equipment/media responsibilities.

When comparing team members, show their roles and additional responsibilities because legitimate duties may explain a difference.

Do not block payment automatically. The owner can record a resolution/outcome.

**TBD:** Define the threshold for a substantially different time and any rounding rules after reviewing real event examples.

---

## 18. Pay, allowances, staff invoices and superannuation

### 18.1 Competition pay

- Nearly all competition crew use the same default hourly rate.
- Team Leaders receive a slightly higher hourly rate.
- Team Leader is a shift-level rate override/additional responsibility.
- Pay begins at the calculated payable start.
- Pay ends at the actual selected finish.
- Paid breaks are included.

### 18.2 Concert pay

- Most concert roles receive a fixed rate for the event.
- Some P2 trainees remain on the normal hourly rate.
- Dress rehearsal shoot assistants remain on the normal hourly rate.
- Actual arrival and finish information should still be stored for fixed-fee concert assignments.
- Concerts longer than seven hours attract an hourly-rate rule.

The exact greater-than-seven-hour calculation is **TBD** pending real examples. Do not decide whether the fixed fee covers the first seven hours plus hourly excess, or whether the calculation changes entirely, without owner confirmation.

### 18.3 Allowances and additional fees

- Travel allowance for events outside the metropolitan area.
- Additional fee for collecting equipment from the owner's house.
- Additional fee for returning/dropping equipment at the owner's house.
- Collection and return may be performed by different people.
- A person keeping equipment for another event should not automatically receive or be assigned a return fee/task for the earlier event.

Rates and allowance values must be configurable and effective-dated. Do not hard-code business rates in application logic.

### 18.4 Subcontractor and superannuation data

Crew are subcontractors. The system must support superannuation-related tracking because DancePro pays applicable superannuation.

Payment categories should be configurable as superable/non-superable rather than assuming every fee or allowance has identical treatment. Keep the contractor invoice/payment amount and the associated super obligation/payment record linked but conceptually separate.

The application is an operational tool, not a substitute for accounting/legal advice. Super rates and qualifying components must be effective-dated/configurable and verified before production use.

### 18.5 Staff-generated invoice/reporting capability

Desired capability:

- crew profile stores the details required for their invoice;
- approved/completed event work generates invoice lines;
- crew can preview a PDF;
- crew can submit the invoice to the owner;
- invoice status can be Received, Needs correction, Approved and Paid; and
- super is shown/tracked appropriately without incorrectly adding employer super to the contractor payment total.

The exact first-release boundary for staff-generated invoices is **TBD**. The time and payment data model must not make this future capability difficult.

### 18.6 Tax-time report

Desired annual PDF/CSV report for each crew member showing, as applicable:

- financial year;
- event dates and names;
- roles;
- hourly/fixed payments;
- allowances;
- GST information;
- invoice numbers/status;
- payment dates; and
- super contributions/records.

---

## 19. Crew profiles and compliance

Profiles may contain:

- legal name;
- preferred name;
- contact details;
- business name;
- ABN;
- GST registration status;
- payment details;
- super fund details;
- emergency contact;
- DancePro start date;
- birthday/date of birth;
- approved roles and training status;
- milestones;
- WWCC number, status and expiry;
- relevant documents/acknowledgements; and
- notification preferences.

Profiles must also support:

- shirt size;
- jacket size; and
- calculated DancePro length of service derived from the commencement date.

Length of service must be calculated when needed rather than stored as a
separate value that becomes stale. Clothing sizes should remain flexible text
until an authoritative size chart is supplied.

Crew contracts must be versioned. For each crew member and contract version,
record whether it has been signed and the stated signing date. Existing staff
contracts may be entered manually with their historical signing dates. An
administrator may correct that date, but the system must retain the previous
value, who made the change, when it was recorded and an optional explanation.
The record must distinguish a system-captured digital signature from an
administrator-entered existing contract or later manual correction.

Required compliance capability:

- WWCC expiry reminders;
- role/training eligibility used during rostering and replacement matching;
- start-date-based tenure and milestone reporting; and
- birthday/milestone awareness where appropriate.

Birthday and sensitive financial/compliance information must not be exposed broadly. Any team-facing birthday feature should require a deliberate visibility decision.

---

## 20. Notifications

Notifications may be delivered through the iPhone app, web interface and email where appropriate.

Required notification categories include:

- new availability round;
- availability deadline reminder;
- new shift allocation;
- roster published;
- acknowledgement reminder;
- material shift change;
- exact competition times added;
- upcoming shift reminder;
- important event announcement;
- programme or brief updated;
- direct/event message;
- replacement request;
- replacement accepted/declined;
- completed self-service roster change notification to owner;
- ready-to-start check reminder;
- incomplete check visibility to Team Leader;
- equipment collection/transfer/return reminder;
- missing time entry;
- time check recommended;
- invoice/payment update where implemented; and
- WWCC expiry reminder.

Avoid duplicate notification noise. Use configurable timing and group related changes where practical.

---

## 21. Future production workflow tracker

### 21.1 Status

This is a future consideration and may not be implemented in the first version. The architecture must leave a clean extension point for it.

The owner currently uses a spreadsheet in which staff enter initials, dates, statuses, values or completion indicators. Column headers have hover instructions and may link to Google Docs containing email templates.

### 21.2 Current workflow phases and legacy steps

#### 1. Booking

- 1.1 Email — Booking Info
- 1.2 Compile Booking
- 1.3 Videography Booked?
- 1.3 Email — Confirmation Info (legacy spreadsheet currently repeats the 1.3 number)
- 1.4 Email — Numbers & Tech
- 1.5 Final Numbers
- 1.6 Concert Video Deposit
- 1.7 Email — Concert Week List

#### 2. Editing

- 2.1 Concert Edited (M4V)
- 2.2 Double Check

#### 3. Assembly

- 3.1 Email — Post Concert Info
- 3.2 Video/Slideshow Received
- 3.3 Programme Received
- 3.4 Group Chat Created

#### 4. Completion and release

- 4.1 Photos Uploaded
- 4.2 Video Uploaded
- 4.3 Invoice Finalised
- 4.4 Email — Video Approval Link
- Overall deadline

### 21.3 Future workflow capabilities

Each workflow template step should be able to store:

- phase and sequence;
- name;
- hover/help instructions;
- optional external resource or Google Doc template link;
- conditional applicability based on booked services;
- responsible crew/admin;
- due-date rule relative to event/shoot date;
- current status;
- initials/completed by;
- completion timestamp;
- text/number/date/link value where required;
- note or blocker;
- evidence/attachment where useful; and
- optional automatic completion trigger.

Potential statuses:

- Not started
- In progress
- Waiting
- Complete
- Not applicable
- Check required

The future interface may provide both:

- a spreadsheet-style matrix across events for rapid oversight; and
- a vertical task list within each event.

### 21.4 Required extension strategy

Do not add each workflow step as a hard-coded column on the core event table. Future workflow data should use configurable templates and event-specific step instances, conceptually similar to:

- workflow templates;
- workflow template steps;
- event workflows;
- event workflow steps; and
- step resources/attachments.

Version one should already provide stable event IDs, booked-service flags, document/link attachments, deadlines, responsible-person relationships and an activity/audit log so the future module can attach cleanly.

---

## 22. Web and iPhone application surfaces

### 22.1 Laravel web application

The existing Laravel project should contain:

- public concert booking form;
- owner/admin scheduling portal;
- crew responsive web portal;
- shared event, venue, brief, communication, time and profile data;
- API endpoints for the iPhone app; and
- email, scheduled reminder and queued/background work.

### 22.2 iPhone app

The iPhone app is primarily crew-facing and should prioritise:

- availability actions;
- next shift;
- acknowledgement;
- calendar and shift list;
- maps/parking/access;
- offline brief/programme;
- event communications;
- ready-to-start checks;
- clock-in/finish;
- Team Leader finish-for-team;
- organising cover/swaps;
- equipment responsibilities; and
- notifications.

Admin-heavy roster construction should be designed for the web first. Mobile admin functionality can be added selectively.

The iPhone app must authenticate against the existing Laravel application through an appropriate API authentication method after repository inspection. Do not assume the existing Laravel version or authentication package.

### 22.3 Distribution

The owner has an Apple Developer account. The app is for internal crew use. Final private/unlisted distribution method is **TBD** based on the organisation's Apple Business setup and whether crew install on personal devices.

Android is not a current native-app requirement. The responsive web portal provides a browser fallback.

---

## 23. Conceptual data model

This is a planning model, not a mandate for exact table names. Inspect the existing application before producing migrations.

Potential new entities:

- studios or a link to the existing studio entity;
- studio contacts;
- concert bookings;
- booking event items/performances;
- scheduling events;
- event services/subtypes;
- shifts;
- shift role slots;
- availability rounds;
- availability requests/responses;
- assignments;
- assignment acknowledgements;
- replacement/swap requests;
- additional responsibilities;
- crew profiles;
- role qualifications/training links;
- venues;
- venue access/parking resources;
- studio/event/venue notes with visibility;
- briefs and brief versions;
- event documents/programmes;
- event announcements;
- event messages/channels;
- checklist templates and versions;
- checklist runs and item results;
- equipment kits/assets;
- equipment custody/handover records;
- media custody records;
- time entries and corrections;
- time flags and resolutions;
- rate rules and effective dates;
- allowance rules;
- assignment payment calculations;
- staff invoices and invoice lines, if included;
- super obligations/payments;
- compliance records;
- notification records/preferences;
- activity/audit events; and
- links between scheduling events and existing delivery events.

All date/time data should be stored consistently and rendered in the correct local timezone. The existing project timezone/configuration must be inspected before implementation.

---

## 24. Required state separation

At minimum, represent these independently:

### Booking lifecycle

```text
submitted -> under_review -> approved | declined
```

### Staff release lifecycle

```text
held -> approved_for_release -> availability_open -> availability_closed
```

### Roster lifecycle

```text
draft -> published -> changed -> completed
```

### Assignment acknowledgement

```text
not_acknowledged -> acknowledged -> reset_by_material_change
```

### Delivery event lifecycle

Use the existing project's delivery lifecycle after inspection. A shell created from a booking must initially be hidden/draft.

### Time entry lifecycle

```text
not_started -> clocked_in -> finish_recorded -> calculated -> reviewed_if_flagged
```

Do not derive all of these from one event status.

---

## 25. Suggested implementation process

### Phase 0 — Repository inspection and integration design

Before implementing features, Codex must inspect and report:

- Laravel and PHP versions from the project itself;
- `composer.json` and major installed packages;
- frontend stack, such as Blade, Livewire, Inertia, Vue or another approach;
- authentication/user/role implementation;
- existing studio, concert, event, programme, video and delivery models;
- migrations and naming conventions;
- queue, scheduler, mail and file-storage configuration;
- test framework and existing test coverage;
- API conventions;
- hosting/deployment constraints; and
- any project-specific instruction files.

Do not modify existing delivery behaviour during this inspection. Do not create duplicate studio/event models until the existing domain has been mapped.

Produce an integration/data-model proposal after inspection and before major migrations.

### Phase 1 — Scheduling foundation

- crew profile and role eligibility foundation;
- venues;
- scheduling events and shifts;
- event templates;
- additional responsibilities;
- admin event/shift management;
- responsive crew shift list/calendar foundation;
- audit activity foundation.

### Phase 2 — Booking intake and event shells

- public dynamic concert booking form;
- submission summary emails;
- booking review/approval;
- separate held/release state;
- studio matching;
- hidden delivery-shell creation/linking after approval;
- programme/document placeholder.

### Phase 3 — Availability and rostering

- availability rounds;
- Available/Unavailable plus note;
- deadlines and reminders;
- batch concert release;
- roster builder;
- role/conflict validation;
- acknowledgement and reset rules;
- self-managed replacement and swaps.

### Phase 4 — Event operations

- venue maps/parking/access;
- studio/event notes and briefs;
- programme/file access;
- event announcements and channel;
- ready-to-start checklists;
- equipment/media custody;
- offline iPhone access for operational documents.

### Phase 5 — Time, payments and compliance

- clock-in and payable-start calculation;
- actual finish and late entry;
- Team Leader finish-for-team;
- supportive time flags;
- hourly/fixed rate rules;
- allowances;
- super-related records;
- WWCC/start-date/milestone reminders;
- staff invoice and annual report capability if confirmed for the first release.

### Future phase — Production workflow tracker

- configurable workflow templates;
- event step instances;
- hover instructions/resource links;
- deadlines, assignments and status;
- spreadsheet-style overview;
- event checklist view;
- automatic completion from system events where appropriate.

Implementation may be split into smaller pull requests. Each phase should include migrations, authorisation, validation and automated tests.

---

## 26. High-value automated tests

At minimum, test:

- Available/Unavailable is per shift and no whole-event response exists.
- An allocated crew member cannot edit availability for that shift.
- A competition placeholder can be allocated and acknowledged.
- Adding exact competition times resets acknowledgement.
- Concert shifts cannot be released without exact times.
- Studio approval does not open staff availability.
- Batch concert release includes only selected approved/ready events.
- A replacement list includes only available, eligible and conflict-free crew.
- A valid replacement acceptance updates the assignment without owner approval.
- The owner is notified and the change is audited.
- Additional responsibilities are not silently transferred.
- Full/partial/no-setup arrival calculations are correct.
- Competition payable start uses the later of posted arrival and actual clock-in.
- Early clock-in does not increase pay.
- Late clock-in reduces payable duration appropriately.
- Actual finish may exceed the posted finish.
- Finish-for-team does not overwrite existing submitted finishes.
- Finish-for-team preserves individual payable starts.
- Time flags do not automatically change pay.
- Team Leader rate overrides the normal competition rate.
- Fixed and hourly concert assignments can coexist.
- Paid breaks are not deducted.
- Equipment retained for another event does not generate an automatic return task.
- Programme/brief access follows event assignment and visibility rules.
- Outgoing replacement loses access only when no other event assignment remains.
- WWCC and sensitive profile information obey authorisation rules.
- Public booking creates no delivery shell before approval.
- Approved shell is hidden/draft and correctly linked.
- Existing video delivery behaviour remains unaffected.

---

## 27. Explicit non-goals and prohibited assumptions

Do not implement or assume:

- whole-event availability;
- Maybe or Partially Available responses;
- crew acceptance/refusal of an allocated shift;
- owner approval for every valid crew-organised replacement;
- concert placeholder times;
- concert Team Leaders;
- unpaid breaks;
- automatic pay truncation at the posted finish;
- pay for clocking in before the posted arrival;
- GPS surveillance, trust scores or punitive abnormality labels;
- quote generation or quote acceptance;
- studio final-details workflow in this version;
- studio invoicing in this version;
- Android native app in the current scope;
- one shared status for all lifecycles;
- hard-coded pay/super/allowance rates;
- a hard-coded spreadsheet column for every future production task;
- automatic delivery-shell creation from unapproved public submissions; or
- a new standalone Laravel backend.

---

## 28. Confirmed open decisions

These items require later evidence or owner examples:

1. Exact existing Laravel/PHP version and frontend/authentication architecture.
2. Whether a multi-concert booking maps to one parent delivery event with child performances or separate delivery events.
3. Exact time-difference threshold for a **Check recommended** flag.
4. Any time rounding rules.
5. Exact concert payment formula when the event exceeds seven hours.
6. Exact rates, Team Leader premium, fixed concert fees, travel allowances and equipment collection/return fees.
7. Exact current super/payment accounting rules and export requirements.
8. Whether staff-generated invoices are included in the first release or a later phase.
9. Exact PDF invoice format and status workflow.
10. Complete content and source URLs for the eight ready-to-start checklist templates.
11. Whether the Dress Rehearsal Shoot checklist is shared or individually completed.
12. Final iPhone distribution method.
13. Final notification timing and escalation intervals.
14. Exact public form contract/requirements wording after reviewing the current form.
15. Which corrections require an owner review versus a visible flag only; default philosophy is self-correction plus visibility.

Codex must surface these as decisions rather than inventing values.

---

## 29. Final implementation directive

Build a crew-first operations system that reduces duplicated entry and administration. Preserve the collaborative culture. Automate calculations and routine communication, allow trusted self-service, and make important information easy to find. Use flags to ask for attention, not to accuse. Integrate cleanly into the existing Laravel delivery project, create only deliberate links between scheduling and delivery, and preserve future room for the production workflow tracker.
