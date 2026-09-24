# ADR-0046: Customer Account Credit Ledger

## Status

Accepted

## Context

DancePro needs to grant a reusable dollar credit to a specific customer account.
The customer must see and use the balance in checkout. This credit is different
from a price discount, a refund of an earlier payment and a future loyalty reward.
A mutable balance alone would not preserve why or when money-equivalent value was
added or consumed.

## Decision

Store account credit as an immutable signed transaction ledger. Positive entries
grant value and negative entries consume it. Every manual grant records the staff
account, whether the source is a reward, gift or previously purchased credit, and a
required reason. Staff locate customer accounts by their unique email
address or numeric user ID; the internal numeric ID is only displayed in the admin
workflow.

Customers see the summed positive balance in My Media and their carts. Applying
credit is optional and happens after promotional and bulk discounts. It does not
change bulk eligibility. It reduces the remaining order value proportionally
across its lines before included GST is calculated. The prepared order freezes
the proposed credit amount and the resulting line-level tax allocation.

Purchased credit is a separate sale. GST is calculated and recorded when that
credit is purchased. Reward and gift credit have no redemption-time sale of their
own. In all three cases, redemption does not add GST to the credited portion; only
the customer's remaining payable amount contributes GST on the media order.

Immediately before payment, lock the customer account and verify that the balance
still covers the frozen amount. Create one order-linked negative ledger entry only
after the gateway approves payment. The unique order link makes redemption
idempotent. A declined payment does not consume credit.

Refunds will record the original payment/provider relationship and refund state.
Loyalty will define earning, expiry, eligibility and redemption rules. Neither is
represented by a manual-credit transaction, even if a later workflow chooses to
issue account credit as an explicit outcome.

## Consequences

The current balance is derived from auditable history, concurrent checkout cannot
silently overspend a balance, and sales reporting can distinguish promotional
discounts from account credit. Future refund and loyalty systems can integrate without
rewriting historical credit grants.
