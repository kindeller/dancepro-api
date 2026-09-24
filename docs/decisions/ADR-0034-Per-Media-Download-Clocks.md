# ADR-0034: Separate download clocks for photos and video

Date: 22 September 2026

Status: Accepted by owner

## Context

One competition order can include photos that are ready immediately and video
that becomes ready later. A single order-level expiry would either shorten one
media type’s agreed access or delay access to already-ready media.

## Decision

Photo download access lasts 12 months and video download access lasts 60 days.
For each purchased item, its clock begins when that item becomes ready for the
customer. An item that is still pending has no running download clock. Order
history will display item-level readiness and expiry. Whole-order packages may
be offered after all required items are ready without changing those clocks.

## Consequences

Fulfilment must persist each item’s customer-ready timestamp and avoid deriving
it from the order date alone. Repeated requests or later purchases must not
restart existing entitlements. Mixed packages need item-level photo and video
entitlements even when sold as one priced line. This decision does not itself
enable a payment or download route.
