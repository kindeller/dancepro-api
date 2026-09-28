# ADR-0031: Verified studio gallery codes

Date: 2026-09-22
Status: Accepted

## Context

The product plan calls for customers to add studios using gallery codes. My Profile
initially let customers select any studio, so its associations could not serve as
verified membership or safely drive studio media links. The existing Studio.code is
an internal identifier; concert video codes are separate.

## Decision

Store an optional hashed gallery access code on each studio with a version counter.
Administrators set or replace it on the studio management page. Studio code changes
use a studio row lock, stale-version rejection and an audit record containing only the
version, studio, actor and time. Never show the saved code or hash on the page or in
model serialization. Local Laravel hashing is authorized by the owner; no AWS secret
is read or stored.

A customer must choose an active studio and submit its gallery code. Normalize the
submitted code to uppercase for case-insensitive comparison with the configured hash.
On success, mark the user/studio association verified. Previously created associations
remain but do not count as verified until the current code is entered. Verified
associations survive subsequent code changes, as competition registrations do.

Only verified active associations appear in My Media studio links, concert photo
routes, concert-video studio/year selection and My Studios’ Routines suggestions.
Removing a studio association does not revoke existing order or video grants.
Competition event registration, section readiness and purchase entitlement remain
independent security gates.

## Consequences

A studio with no configured gallery code cannot be added as verified. The studio
selector may list it, but entry fails safely. Studio.code and event/photo/video codes
retain their existing meanings. A profile studio association now demonstrates code
knowledge, not legal identity or family purchase rights. Admins must supply codes
through the separate form before customers can add those studios.

Migration 000013 adds hash/version fields, verification timestamps and code-change
audit records. Rollback drops only these fields/history and does not delete studios,
profiles, media or grants. Rollback also loses verification evidence, so it must not
be used to create a legacy membership bypass.

## Studio Hub display amendment (27 September 2026)

ADR-0057 supersedes the no-display rule for authorised Studio Hub management views
at the owner's request. Newly saved codes retain an encrypted display copy alongside
the hash. Admin input fields, serialization and audit history still do not disclose
codes; verification and existing access rules are unchanged. Hash-only legacy codes
must be re-entered once before they can be displayed.
