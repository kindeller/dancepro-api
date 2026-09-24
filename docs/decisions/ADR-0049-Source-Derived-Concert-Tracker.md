# ADR-0049: Source-derived Concert Tracker

## Status

Accepted on 23 September 2026.

## Context

Concert photo preparation and grouped release were previously separate Media
navigation destinations. The integrated media plan defines the Tracker as the
top-level route to operational work and explicitly includes Photo release as a
completion task. It also requires automatic tasks to reflect their source
records instead of being checked off again in the Tracker.

## Decision

Add a top-level Concert Tracker with date and studio views. Its first production
slice derives programme, crew, verified-photo and photo-release statuses from
their existing module records. Every status links to the source screen that can
resolve it.

Move the concert photo release workspace into the Tracker. Release-group creation,
Top Pic cover selection and atomic release continue to use the existing Media
models, service and audit history. The previous release URL redirects to the
Tracker for compatibility, and Concert Photo Releases is removed from the Media
navigation.

The Booking, Preparation and assembly, Editing and approval, and Completion
catalogue is shown in phase tabs. It is consolidated around operational outcomes
rather than reproducing old-system steps. Progressive source tasks show the next
state of one authoritative record, such as Programme from not received through
confirmed and Photo gallery from preparation through release. Tasks without a
source record use a shared manual-task record with status, assignee, deadline
override, follow-up, completion history and notes. Booking-scoped tasks share one
record and merge across related concerts in Studio view.

Editable email templates belong to the applicable manual task. Each queued
delivery freezes its rendered subject and body for history. Successful delivery
completes that task. Template variables are restricted to documented event,
studio and contact fields.

## Consequences

- Staff can see photo-release readiness beside the event's upstream programme,
  crew and uploader state.
- Releasing a gallery updates the Tracker automatically from the release-group
  and album records.
- There is one operational entry point without duplicating release state.
- The compact All tasks view remains readable despite showing every checkpoint,
  using vertical headings and short status cells; phase views retain fuller detail.
- The Tracker can expand across modules while Media continues to own gallery data
  and release rules.
- Studio invoice and payment tasks remain manual until a production studio-billing
  source exists; customer-order payment records are not reused for studio billing.
