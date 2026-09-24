# ADR-0033: Concert video code groups and fixed access clocks

Status: Accepted (22 September 2026)

## Context

The old delivery path puts a password on each concert. The product plan lets one
studio-distributed code unlock selected concerts from the same studio and year,
with six months of streaming and twelve months of downloads. Adding a concert or
entering a code later must not restart the original clocks.

## Decision

An admin creates a hashed code group for one studio/year and assigns concerts.
Releasing the group starts one immutable clock. A concert can belong to at most
one group. Later concerts can be added only from the same studio/year; their
customer grants inherit the original group expiry. Release and additions reject
concerts whose availability would end before the group download period. Codes
can rotate without invalidating already issued grants. The plaintext code is
never displayed or stored. Create, assignment, release and rotation actions have
actor/version audit records that contain no code or hash.

Customer code entry creates per-concert, user-owned grants linked to the group.
The concert page and playback endpoint check the six-month streaming boundary;
the download endpoint checks the twelve-month boundary. Existing individual
concert passwords and grants remain valid under their old rules for migration.
An assigned concert without an individual password is still protected; its group
code is entered in My Media. No customer receives access from studio membership
alone. Expired or revoked grants cannot be restarted by re-entering a code.

## Consequences

The admin release action is consequential and should occur when the first
concert is publicly available. A separately approved reactivation checkout,
guest access and reminder schedule remain later work. Public signed delivery
links retain their short-lived transport behaviour; they do not create grants.
