# ADR-0035: Immutable customer order drafts

## Status

Accepted for the local customer commerce foundation. Checkout and settlement are
not enabled yet.

## Context

Customer competition and concert carts quote overlapping products and bulk
discounts. Media can change between viewing a cart and a payment response. A
routine package may be purchased before its video is ready, while its photos
can be ready immediately. Payment must charge a stable amount and fulfil the
specific media represented at order creation.

## Decision

- Prepare a draft order server-side from a fresh, authorized cart quote. Store
  the AUD subtotal, discount and total, plus item-level allocated amounts.
- Snapshot exact ready asset UUIDs and storage references in each order item.
  The later entitlement activation must match that snapshot. For pending video,
  retain a separate video order item with no storage reference or expiry clock;
  link its source and start its clock only after verified video becomes ready.
- Split a routine package into photo and video items. A $27 upgrade after an
  existing video purchase creates only the new photo item.
- Repeated preparation of an unchanged cart reuses its draft. A changed cart
  creates a new draft and cancels earlier drafts for that source. Paid orders
  and their item snapshots are never rewritten by cart preparation.
- Present orders to customers and staff with a numeric number derived from the
  immutable database sequence, starting at 100001. UUIDs remain the route,
  integration and internal audit identifiers. Numeric order numbers are for
  display, communication, search and exports; gaps are allowed and numbers are
  never reused.
- Do not interpret a draft as a sale or a download right. Before submitting a
  payment, the checkout workflow must verify that the draft is still current,
  the amount is unchanged and the selected media remains orderable. Only a
  server-confirmed successful payment may transition it to paid and activate
  entitlements.

## Consequences

Order-item storage references must be nullable for the pending-video case.
Rolling that migration back requires resolving any pending-source rows first.
The payment and video-readiness workflows must use the frozen order snapshot,
not a mutable cart or the current full media collection. Legacy paid orders
retain their existing collection-based library behavior.
