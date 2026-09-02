# DancePro Crew Scheduling - Phase 0 Integration Plan

## Status

Proposed integration baseline, 29 August 2026.

This plan records the repository inspection required by the
[Crew Scheduling Project Specification](../specifications/DancePro-Crew-Scheduling-Project-Specification.md).
It defines boundaries for the first implementation phase without settling the
business decisions explicitly marked TBD in that specification.

## Repository Findings

### Runtime and framework

- Laravel `^13.8` on PHP `^8.3` is declared in `composer.json`.
- Laravel Sail uses its PHP 8.5 runtime image locally.
- MySQL 8.4, Redis and Mailpit are defined in `compose.yaml`.
- The production deployment script targets `/var/www/dancepro-api` from the
  `master` branch. Development currently occurs on `v1-migration`.

### Web and API surfaces

- The web UI uses server-rendered Blade templates, Tailwind CSS 4 and Vite 8.
- There is no Livewire, Inertia, Vue or React dependency.
- The API uses Laravel Sanctum bearer tokens.
- Browser authentication uses Laravel sessions.
- API responses use the shared `success`, `message`, `data` envelope from
  `App\Shared\Responses\ApiResponse`.

### Users and authorization

- `App\Models\User` is the shared authenticatable identity.
- Users have a broad `type` string and `is_active` flag, but the repository does
  not yet contain a complete role/permission system for owner, administrator,
  crew or Team Leader access.
- Existing policies cover downloads only. Crew scheduling authorization must be
  added deliberately and tested before private profile, pay or compliance data
  is exposed.

### Existing delivery domain

- `App\Features\Studios\Models\Studio` is the canonical studio record and must
  be reused rather than duplicated.
- `App\Features\Concerts\Models\Concert` is a delivery event. It owns delivery
  visibility, access, media and publication concerns.
- Media collections, media assets, storage locations, concert access grants and
  tracked downloads already exist.
- The current Competition feature is an authenticated S3 object browser, not a
  scheduling-event model.
- Concert `available_from` and `available_until` control customer delivery
  availability. They must not be reused for crew availability rounds.

### Infrastructure and operations

- Database-backed queues are the default and the jobs tables already exist.
- Mail defaults to the log driver and supports SMTP and SES configuration.
- Local, public and separate concert/competition S3 disks are configured.
- Application time is currently stored and processed in UTC. Scheduling should
  continue storing instants in UTC and introduce an explicit business timezone
  for local display and local date/time input before time-based features ship.
- No scheduling commands or recurring task definitions currently exist.

### Tests

- PHPUnit 12.5 is used through Laravel's test runner.
- Existing feature tests cover authentication, users, admin screens, concert
  delivery, competition object browsing and downloads.
- New scheduling work should use feature tests for workflows and unit tests for
  timing and payment rules.

## Integration Decision

Scheduling is a new bounded domain inside this Laravel application. It will use
the existing identity, studio and delivery capabilities through explicit links;
it will not create a second backend and will not add scheduling state to the
existing delivery `concerts` table.

The initial feature boundaries should be:

- `app/Features/Crew` for crew profiles and role eligibility;
- `app/Features/Scheduling` for scheduling events, shifts, role slots,
  assignments, responsibilities, availability and roster lifecycles;
- `app/Features/Venues` for reusable operational venue information; and
- later focused features such as `Bookings`, `EventOperations` and `Payments`
  when their implementation phases begin.

These are Laravel feature folders within the existing repository, not separate
applications. Shared code belongs in `app/Shared` only when multiple bounded
features genuinely consume it.

## Identity and Domain Links

```text
User 1--0..1 CrewProfile
Studio 1--* ConcertBooking
ConcertBooking 1--* SchedulingEvent
SchedulingEvent 0..1--1 Concert (delivery event)
SchedulingEvent 1--* Shift
Shift 1--* RoleSlot
RoleSlot 0..1--1 Assignment
Assignment *--1 User
```

- Crew authenticate as existing `User` records.
- A `CrewProfile` extends a user with scheduling-specific and sensitive data.
- The existing `Studio` remains the studio source of truth.
- A `SchedulingEvent` represents staffing and operations independently of the
  existing delivery `Concert`.
- A nullable, explicit delivery link allows competition scheduling events to
  exist without a `Concert` and approved concert bookings to create hidden
  delivery shells later.
- Multi-performance booking cardinality remains unresolved and must be decided
  with real examples before Phase 2 migrations.

## Phase 1 Data Proposal

The first migration set should be deliberately narrow:

1. `crew_profiles`
   - one-to-one `user_id`;
   - preferred/legal identity, basic operational contact fields, shirt size,
     jacket size and commencement date;
   - length of service calculated from commencement date rather than stored;
   - sensitive financial, super and compliance details deferred until Phase 5.
2. `crew_roles`
   - stable role code, display name, event applicability and active state.
3. `crew_role_qualifications`
   - crew profile, role, qualification/training status and effective dates.
4. `venues`
   - reusable name, address, timezone and operational access fields.
5. `scheduling_events`
   - public UUID, event type, title, studio, venue and optional delivery concert;
   - separate booking, staff-release and roster states where applicable;
   - no media-delivery publication state.
6. `shifts`
   - scheduling event, label, local-date context, posted arrival, event start,
     estimated finish and whether exact times are known;
   - competition placeholders allowed; concert placeholders prohibited by
     domain validation.
7. `shift_role_slots`
   - required role, optional trainee relationship and staffing status.
8. `assignments`
   - role slot, user, acknowledgement state and acknowledgement timestamps.
9. `additional_responsibility_types` and `assignment_responsibilities`
   - duties remain separate from technical roles.
10. `activity_events`
    - actor, subject, event name, safe metadata and timestamp for scheduling
      actions that require an audit history.

The confirmed contract extension adds versioned crew contract definitions,
one current signature record per crew and contract version, and append-only
signature events. Existing paper contracts can be recorded with their true
historical signing date. Corrections update the current value while retaining
the previous value, recorder, recording time, method and explanation.

Exact columns and indexes should be finalized alongside request validation,
policies, models, factories and tests in small vertical slices. Avoid building
all later-phase tables in advance.

## State Boundaries

The implementation must keep these independent:

- public booking review;
- release to crew and availability-round state;
- roster publication;
- assignment acknowledgement;
- delivery concert publication;
- time-entry calculation and review.

Typed enums may represent each lifecycle, but one generic event status must not
represent them all.

## Delivery Sequence

### Slice 1 - crew and authorization foundation

- Add owner/admin/crew authorization rules using the existing user identity.
- Add crew profiles and role eligibility.
- Expose minimal admin management and authenticated API resources.
- Prove sensitive profile fields are not returned to unauthorized users.

### Slice 2 - venues and scheduling events

- Add venues, scheduling events, shifts and editable templates.
- Implement exact-time rules for concerts and placeholder rules for competitions.
- Add admin web management using the existing Blade/Tailwind approach.

### Slice 3 - role slots, assignments and audit

- Add role slots, assignments and additional responsibilities.
- Add acknowledgement state and material-change reset behavior.
- Add crew shift-list API and a responsive web foundation.
- Record assignment and state changes in the audit history.

Availability, public booking intake and event-shell creation follow in the
later phases defined by the authoritative project specification.

## Decisions Still Required

Do not infer values for the confirmed open decisions in the project
specification. The earliest blockers are:

- owner/admin/crew permission semantics beyond the current `User.type` string;
- the authoritative list of crew and each person's role qualifications;
- the default business timezone for scheduling display and input;
- multi-concert booking to delivery-event cardinality before Phase 2;
- source content for public booking agreements and readiness checklists; and
- all pay, allowance, super, rounding and time-flag threshold values.

## Guardrails

- Preserve all existing customer delivery behavior while scheduling is added.
- Use Form Requests, policies, small controllers and action/service classes.
- Use public UUIDs in externally visible routes and API payloads.
- Do not expose financial, compliance or private venue access information by
  default.
- Queue notifications and emails where appropriate, with tests that do not send
  real messages.
- Add migrations, factories and automated tests together for each slice.
- Run Sail tests and route inspection before requesting commit approval.
