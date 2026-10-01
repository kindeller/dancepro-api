# Studio membership and customer-submitted studios

Date: 29 September 2026

## Decision

Customers can belong to a studio before DancePro has photographed its concert or
troupe competition. Following the owner's clarification, `studios.code` is the one
code for membership and released concert photo galleries. Adding an active studio
with that code sets both membership and photo verification timestamps. There is no
second photo-gallery unlock step. Existing confirmed memberships are backfilled.

Customers without a listed studio or code can submit a name and optional location.
The request remains owned by that customer. Authorised studio administrators see a
pending queue and badges under Hub Management → Contacts → Studio requests. They
may create a studio with its details, match an existing active studio, or decline
with a reason. Approval atomically links the requester to the chosen studio,
including released concert photos, and records the reviewer/time. Pending requests
do not grant access. Approval never grants Studio Hub staff access.

Studio access follows accepted family membership. Dancer and studio recommendations
remain visible before a competition is added; its event code is still required for
full photo/video access. Concert video codes, director release, expiry and purchase
entitlements remain separate. Membership alone does not grant video or purchased
original download access.

This supersedes the earlier separate membership/photo-gallery decision and
ADR-0031's independent studio gallery code. The existing Studio code takes precedence
over any old gallery code. Hash-only legacy records continue to work until a Studio
code is set. The admin Studio code field is the sole visible editor; the legacy
code endpoint updates that same field. Studio Hub, tracker and QR access use the
same source. Changing it invalidates old QR invitations but preserves membership.

## Implementation and recovery

Live search is authenticated, throttled and returns at most 15 active studios with
public UUID, name and logo only. Neither code nor staff contact details is exposed.
Name/code edits cannot reuse a stale selected studio. Code failures do not flash
submitted codes into the next page. Review submission is deduplicated per account
and normalised name/location; repeat reviews fail rather than create another studio.

Pending requests show Awaiting review and transfer the action badge to admin. A
declined request shows the review reason and can be resubmitted. Account deletion
cascades its requests. Studio/reviewer deletion preserves the request with nullable
references. Removing a customer studio link retains the prior approved review.

Migration 2026_09_29_130000 adds membership timestamps and the request table. Its
rollback drops only those additions, losing request/review history and new
membership evidence; it preserves studios, prior gallery verification and all media.

Migration 2026_09_29_140000 grants photo access to existing confirmed memberships
only; unverified associations and pending requests are excluded. Its down method
intentionally does not revoke access that has already been granted. A policy reversal
would require an explicit, separately scoped access migration.
