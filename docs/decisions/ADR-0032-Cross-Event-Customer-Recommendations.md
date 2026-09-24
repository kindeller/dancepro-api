# ADR-0032: Cross-event customer media recommendations

Status: Accepted (22 September 2026)

## Context

Customers want My Dancers and My Studio suggestions across competitions, including
events they have not yet added to their account. Competition galleries otherwise
require a separate per-event photo login code. My Media must also distinguish
media purchases from mere event browsing and concert code access.

## Decision

The recommendation list may use a customer's stored dancer names/aliases and
verified studio links to discover current, automatically ready routine albums.
Only explicit studio-to-routine assignments qualify for My Studio. A matching
routine may expose one authenticated, watermarked thumbnail of a verified photo.
The thumbnail endpoint rechecks the match, photo ownership and live readiness on
each request; it never serves an original. The rest of the event gallery remains
behind per-event login-code verification. An unopened routine card preselects its
event in Add media.

My Media purchase cards come only from paid or fulfilled order items owned by the
account and linked to event-owned media collections. Registrations and concert
video code grants remain separate browsing entries. No access record is treated
as a purchase. The existing `media_collections.competition_id` is used only when
it resolves to a current competition scheduling event; the final commerce event
identity contract must be settled when checkout is built.

## Consequences

Profile name matching remains a discovery hint, not identity proof. A customer
with a matching name can see a small watermarked preview before event login, but
cannot open the album or retrieve originals from that route. When an order lacks a
linked collection, it cannot yet appear as an event card. Checkout, guest claiming,
family sharing and order-specific downloads remain separate work.
