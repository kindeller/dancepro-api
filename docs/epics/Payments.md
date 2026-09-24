# Payments Epic

> Product direction update: use the [integrated media plan](../specifications/Integrated-Media-Product-Plan.md)
> and [roadmap](../Roadmap.md) for new scope and sequencing. Earlier status and
> priorities below describe the previous delivery slice; they do not establish
> completion of the expanded workflows.

## Purpose

Provide secure customer checkout for competition and concert media and fulfil
only server-confirmed paid orders.

## Current Status

The competition and concert carts quote prices and bulk discounts. Checkout
preparation freezes exact media assets and item prices, including a separate
pending-video item for routine packages (ADR-0035). A local-only fake gateway
now provides reviewable approved and declined checkout paths without accepting
payment details or charging money. Approved orders activate exact-asset
entitlements with independent photo and video clocks (ADR-0034), clear only the
purchased cart/favourite selections, and expose authenticated original-file
downloads through short-lived database-backed tracking links.
Active percentage or fixed-dollar discount codes can be carried from guest
access, applied before bulk eligibility is calculated, usage-limited, frozen
into the order and recorded on approval. Authorised studio principals receive
$1 concert-photo pricing, and those photos do not count towards bulk discounts.
Reusable price lists store an editable GST rate and per-product GST checkbox.
Checkout calculates included GST after discounts and freezes the rate and tax
amount on each item plus the total tax on the order. Checkout and sales views
show that included GST amount.
Administrators can find a customer by email address or numeric account ID and
grant account credit with an audit reason. Customers see their available balance
in My Media and their carts and may apply it after discounts. Credit is recorded
in a signed ledger and consumed only when payment succeeds. It does not alter
bulk-discount eligibility, but it reduces the remaining taxable total before GST
is calculated. Grants identify reward, gift and purchased-credit sources. GST on
purchased credit belongs to its original purchase rather than its redemption.
Sales reporting separates discounts from credit.
Drafts also snapshot the exact cart selections. A confirmed Eloquent paid-order
transition with `paid_at` clears those selections from cart and favourites
(ADR-0036), preserving unrelated or later selections.
Successful payment now queues one deduplicated order confirmation. When pending
purchased media becomes ready, the release workflow queues one media-ready email
for customers who enabled that preference. A daily scheduled check sends an
idempotent reminder ten days before undownloaded media expires when expiry reminders
are enabled. Staff can edit each template independently, send a test email and review
queued, sent or failed delivery records (ADR-0047).

## Next Scope

Replace the fake gateway with Braintree Hosted Fields and a server-side
transaction after sandbox credentials and merchant configuration are ready.
The existing gateway boundary and idempotent payment-attempt record are the
integration seam. Add provider reconciliation, monetary refunds and production operations before enabling live
charges. Add loyalty earning and redemption as a separate future system; neither
refunds nor loyalty should be recorded as manually granted account credit.

## Links to Related Documentation

- [Architecture](../handbook/Architecture.md)
- [Security](../handbook/Security.md)

## Notes / Future Work

The fake gateway is enabled only when `PAYMENT_DRIVER=fake`; production falls
back to disabled. Provider configuration, retry/reconciliation behavior,
privacy and refund handling must be documented and tested before enabling
charges. No payment credential has been configured as part of this work.

Historical sales evidence, the deferred loyalty candidate and the transaction
data required for future rewards are recorded in the
[media sales and loyalty baseline](../specifications/Media-Sales-and-Loyalty-Baseline.md).
That document does not approve loyalty implementation or change the current
product and discount rules.

See [ADR-0043](../decisions/ADR-0043-Media-Discount-Code-Redemption.md) and
[ADR-0044](../decisions/ADR-0044-Studio-Principal-Photo-Pricing.md), plus
[ADR-0045](../decisions/ADR-0045-Reusable-Media-Price-Lists.md) and
[ADR-0046](../decisions/ADR-0046-Customer-Account-Credit-Ledger.md), plus
[ADR-0047](../decisions/ADR-0047-Customer-Media-Notification-Delivery.md).
