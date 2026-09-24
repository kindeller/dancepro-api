# ADR-0036: Clear exact customer selections after confirmed payment

## Context

Competition and concert photo carts can also contain favourites. Draft orders freeze
the media and price before payment, but a customer can change a cart while a draft
exists. Clearing a whole event or studio cart after payment would remove later or
unrelated selections. Checkout and payment capture are not yet enabled.

## Decision

Draft orders record the exact cart selection row IDs, plus stable competition keys
or concert asset UUIDs for audit. The snapshot participates in
the draft fingerprint. When an Eloquent order transitions to paid or fulfilled **and**
has a `paid_at` timestamp, an observer deletes only those snapshotted selections
owned by the order's customer and scoped to its event or studio. Deleting the row
removes it from both cart and favourites. Draft and pending orders never clear
selections. Repeated paid updates are harmless.

Future payment handling must update the Eloquent order only after provider-confirmed
success, with `paid_at`, so this cleanup occurs at the paid transition. Payment
handling must still revalidate the current draft before charging. Older drafts without
selection IDs must be regenerated before checkout. This decision does
not introduce checkout, charge a customer, or grant media access.

## Consequences

Selections added or recreated after draft preparation remain unless a new draft
snapshots them. A customer may keep unrelated favourites and cart items. Refunds do
not automatically restore selections; refund behavior belongs to the payment flow.
