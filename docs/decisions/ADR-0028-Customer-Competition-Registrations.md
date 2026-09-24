# ADR-0028: Stable customer competition registrations

Date: 2026-09-22
Status: Stable event registrations retained; media-code verification superseded by owner clarification

## Context

Customers add competitions by event code and need consistent access on web, iPhone
and Android. Existing event codes are calculated and can change with event dates.
The owner has deferred stored-code redesign.

## Decision

Store one registration per user and scheduling event, including the code used to
register. Resolve exact current profile codes, ignoring surrounding whitespace and
case, without parsing their date components. Reject ambiguous codes. Subsequent
access uses the event identity, not the historical code. Registration and membership
rules live in Customers actions/services, with a web adapter first. Both mobile
platforms are required but their clients/authentication are separate pending work.

Registration permits browsing automatically ready sections and watermarked previews,
not originals or purchased media. Reuse the admin preview readiness service and
check membership at every customer route. Administrators register explicitly for
review. Inactive users and cancelled/noncompetition events are denied.

## Consequences

Date/code edits do not strand registered users. Code collisions fail closed. Lookup
currently scans active competition profiles to retain existing code semantics;
a future stored-code decision can replace lookup without migrating memberships.
The migration down removes registrations only. Account onboarding, guest access,
mobile authentication, dual crew/customer roles and commerce remain separate work.


## Owner correction (22 September 2026)

Media codes are not customer login codes. Customers select an event from a dropdown
and verify its separate login code. The original exact-media-code lookup is an
implementation error requiring replacement. Stable user/event registrations and
readiness gating remain applicable; login codes must not be retained in registration
records. The owner approved local Laravel hashing without the unavailable AWS skill. The
correction is now implemented: gallery settings store a hidden hash, registrations
store only an access-verification timestamp, and legacy media-code registrations
require verification. Existing verified registrations survive code replacement.
No code or hash is written to the settings audit.
