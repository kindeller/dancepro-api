# ADR-0050: Customer download reactivation

## Status

Accepted on 24 September 2026.

## Context

Photo and video download periods begin independently when each purchased media
entitlement becomes ready. Customers need a support path when one of those periods
has ended without changing the original order, purchased asset set or the expiry of
other media in the order.

## Decision

Customers may create general media-help requests or request reactivation of one
expired, ready entitlement that belongs to their account. The request stores the
linked order and entitlement plus a frozen operational context containing the order
number, media type, former expiry and download count.

An administrator may approve a reactivation for a chosen number of days from the
approval time, decline it, or resolve a general request. Approval changes only the
linked entitlement. Every extension stores the previous and new expiry, duration,
administrator, request and reason in a separate audit record. The customer receives
an email after the decision and can see the outcome in their Help page.

## Consequences

- Purchased asset identities and order snapshots remain immutable.
- Photo and video clocks remain separate.
- Repeated pending requests for the same entitlement are rejected.
- Support decisions remain reviewable after the entitlement expiry changes.
- Production email delivery still depends on the selected transactional provider.
