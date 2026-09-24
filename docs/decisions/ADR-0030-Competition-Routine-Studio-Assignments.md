# ADR-0030: Explicit studio assignment for competition routines

Date: 2026-09-22
Status: Accepted

## Context

The confirmed competition programme and upload plan preserve section/competitor
identities but do not reliably identify a studio for each routine. Guessing a studio
from the competitor title would produce false customer suggestions. Upload plans are
immutable and must not be edited to add presentation metadata.

## Decision

Store optional administrative event/item/studio assignments separately from programme
snapshots and photo contributions. An administrator assigns or clears one studio on
each current competitor album in Media → Competition Photos. Validate the active
programme item and selected active studio server-side; reject stale saves when another
editor has changed the assignment. Audit before/after studio IDs and the actor.

My Studios’ Routines lists a competitor album only when the customer has verified
access to that competition, has verified the assigned active studio in My Profile, and
the section passes the existing automatic readiness rule. The album and image routes
still enforce their own access and readiness checks. Whole Event remains available.

## Consequences

Assignments do not alter folder names, immutable upload plans, gallery publication,
orders, or studio membership. New/unassigned routines simply do not appear in the
studio suggestion view until mapped. Clearing an assignment removes its suggestion,
not the photos or the customer's event access. The table is keyed by stable event/item
identity; retired items are ignored because suggestions use current programme nodes.

Studio gallery-code verification is now implemented (ADR-0031). It still does not
grant competition event access or purchase rights. Studio assignment does not expose media outside the event
registration/readiness gates.
