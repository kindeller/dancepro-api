# ADR-0043: Media Discount Code Redemption

## Status

Accepted

## Context

Gallery Settings could store percentage discount-code drafts, but customers
could not use them and approved orders did not record redemptions. Staff also
need unique dollar-value codes for individual customer service cases.

## Decision

Apply an active code to the cart before testing the remaining eligible spend
against the bulk-discount thresholds. For example, a $120 eligible cart with a
$40 code leaves $80 of eligible spend and receives no bulk discount. A code may
provide either a percentage or fixed dollar discount, capped at the cart total.

Freeze its identifier, label, type, configured value and applied amount into
the immutable order draft. Revalidate the code and its dates and limits while
locking it immediately before payment. Record one redemption only after an
approved outcome.

Codes may have optional global and per-customer-account limits. The latter
counts approved redemptions separately for each authenticated customer account.
A code referenced by a
paid order cannot be deleted; staff can make it inactive. Guest customers may
carry an entered code through account creation or sign-in, but the code is not
validated or consumed until the customer prepares and pays the account order.
A private one-use customer code uses a global limit of one and a per-customer
limit of one, together with the required expiry date.

Gallery Settings presents bulk discounts, code creation and saved codes as
separate expandable sections. Code creation uses one value field that switches
between percentage and dollar validation. New forms default to starting today
and ending 30 days later. Saved-code rows show their value, date range, usage
counts, limits and current active, scheduled, expired, inactive or used-up
status before expanding to the full edit form.

## Consequences

Order totals, sales reporting and customer checkout retain the exact discount
that was approved. A code can reduce a cart below a bulk threshold. Declined
attempts and abandoned drafts do not consume a use. Refund behavior remains
part of the future live-payment policy.
