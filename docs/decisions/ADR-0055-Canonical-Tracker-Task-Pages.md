# ADR-0055: Canonical tracker task pages and shared controls

Date: 27 September 2026
Status: Accepted for task 1.1; remaining task destinations will be agreed individually.

## Context

The owner wants tracker cells to open the operational page used elsewhere in administration, with the same sticky status, deadline, assignment and follow-up controls regardless of entry route. A pending booking has no scheduling event yet, while approval can create several events for that booking.

## Decision

- Booking & services (1.1) uses the existing booking review/edit route. The tracker links directly to it; the former task-detail URL redirects there when a booking is linked. Both Pending events → Review and Event bookings → Edit render the shared bar on this canonical page.
- Persist 1.1 against the existing `booking` task scope and booking ID before and after approval. Reading the bar uses an unsaved default if no task exists; a read never creates a task, booking or scheduling event.
- Use one reusable Blade bar partial. Each accessible dialog PATCHes only its own validated fields through a shared task writer, which locks the task, preserves unrelated fields and records before/after history with the actor. Follow-up notes remain append-only in the existing notes table.
- Store an explicit nullable `status_override` separately from operational truth. Automatic uses the booking status, manual overrides are labelled, and clearing the override restores automatic behaviour. The explicit Approved choice invokes the same booking approval action as the Approve booking button and opens confirmation review. Other manual choices remain tracker-only overrides; they cannot cancel a booking, record payment or publish media.
- Store a follow-up status independently of task completion. Resolved follow-ups do not trigger overdue follow-up attention/alerts; notes and the previous date are retained.
- Assignment selects active administrators or users with an active (not deleted) crew profile. This assigns task responsibility and does not change the event roster or grant administration access.
- Back to tracker uses the last validated tracker view/year/phase/attention stored in the staff session, falling back to the booking year and Booking tab. There is no client-supplied external return URL.

## Scope and consequences

The bar is integrated into task 1.1 only for this slice. Other tasks keep their current pages until their destinations and workflows are agreed. The shared component and field writer support that incremental migration. Task keys and existing notes are preserved. The additive migration is reversible by removing the two new columns; rolling it back loses only the new manual-override/follow-up-status values, not existing task history or notes.


## Follow-up: approval and confirmation combined (27 September 2026)

The owner combined the former 1.2 confirmation task into 1.1. Approval now leads directly to a booking-scoped confirmation page with the same 1.1 bar. The separate tracker column is removed and later booking tasks renumber. The internal `confirmation_information_email` record/template key remains as a hidden compatibility alias to preserve delivery history, scheduled jobs and automation rules. Old confirmation detail URLs redirect to the canonical booking confirmation page. Approval itself never sends a message; staff review the template and explicitly send. The bar displays approval and confirmation delivery state separately.

Seats and uploaded programme/slideshow files on approved bookings use the existing event records shared with the Studio Hub and tracker. Replacing a studio file supersedes its prior record without deleting its stored version and requires fresh review. Pending bookings can attach programme PDFs; seats and studio media become available after approval creates the event.

## Seats and families follow-through

Task 1.2 uses the shared tracker bar on its email action page. Manual approval marks the email unnecessary without changing booking approval; successful delivery remains the authoritative Completed transition. Task 1.3 reuses the canonical booking page on `?tab=seats-families`, sharing final-family and seat records with the Studio Hub. Its source status requires confirmed families and seats for every active concert. DR portraits are not required and per-event cells preserve distinct seat summaries even though the completion criterion is booking-wide. Existing task keys and history are retained.

The booking workspace now has two server-rendered tabs, **1.1 Booking & services** and **1.3 Seats & families numbers**. Each tab contains its own shared tracker bar; 1.3 independently stores deadline, assignment and follow-up while deriving its status from confirmed data. The event-management menu strip is removed from this detail page. Back to event bookings and Back to concert tracker are stacked above the tabs. Old `#seats-families` links open the numbers tab for compatibility.
