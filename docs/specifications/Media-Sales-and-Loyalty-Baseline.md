# Media Sales and Loyalty Baseline

## Purpose

This document preserves the commercial analysis performed from the DancePro
order exports for 2023 through 23 September 2026. It is the baseline for future
sales reporting and any later loyalty or rewards discovery.

The current products, prices, packages and discounts remain unchanged. Loyalty
is deferred. The figures and candidate rules below are evidence and planning
inputs, not active commerce requirements.

## Source data

The analysis used these ShootProof order exports held outside the repository:

- `orders-from-2023-01-01-to-2023-12-31.csv`
- `orders-from-2024-01-01-to-2024-12-31 (1).csv`
- `orders-from-2025-01-01-to-2025-12-31 (1).csv`
- `orders-from-2026-01-01-to-2026-12-31 (1).csv`
- `items-report-from-2025-08-25-to-2025-09-24.csv`, used for a limited
  product-mix check

These files contain customer personal information and must not be committed to
the repository. No customer names, email addresses or postal addresses are
reproduced here.

## Method

- Only rows with a positive `Total Sales` value and a non-empty customer email
  were included.
- Email addresses were trimmed and converted to lowercase, then used as the
  customer key.
- Matching was exact. Different email addresses belonging to the same person
  were not merged, and shared email addresses were not split.
- An annual repeat customer placed more than one included order in that year.
- A multi-year customer appeared in at least two annual exports.
- The 2026 export ends on 23 September 2026 and is not a complete year.
- The exports do not reliably expose product-level revenue, discounts before
  the exported total, fulfilment cost, payment cost or contribution margin.
- Product names changed over time and include duplicate or misspelled legacy
  labels. Product-quantity comparisons are directional only.

The results should therefore be treated as a reliable order-value and customer
return baseline, but not as a final profitability model.

## Annual sales baseline

| Period | Included orders | Sales | Exact-email customers | Average order | Median order | Annual repeat customers | Annual repeat rate | Galleries |
| --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: |
| 2023 | 4,494 | $264,507.50 | 2,834 | $58.86 | $45.00 | 887 | 31.3% | 91 |
| 2024 | 4,069 | $252,790.65 | 2,725 | $62.13 | $45.00 | 779 | 28.6% | 100 |
| 2025 | 4,181 | $260,122.70 | 2,915 | $62.22 | $45.00 | 749 | 25.7% | 106 |
| 2026 to 23 September | 2,185 | $126,072.10 | 1,548 | $57.70 | $45.00 | 401 | 25.9% | 81 |
| **Total** | **14,929** | **$903,492.95** | **6,736 distinct across all years** |  |  |  |  |  |

For a comparable January-to-23-September view:

| Year | Sales | Orders | Customers | Average order |
| --- | ---: | ---: | ---: | ---: |
| 2024 | $142,913.65 | 2,386 | 1,659 | $59.90 |
| 2025 | $146,760.05 | 2,373 | 1,706 | $61.85 |
| 2026 | $126,072.10 | 2,185 | 1,548 | $57.70 |

## Customer value and retention

- 6,736 distinct exact-email customers purchased during the four-year period.
- 2,201 purchased in at least two different years.
- 868 purchased in at least three different years.
- 217 purchased in all four years.
- Multi-year customers generated $602,487.95, or 66.7% of included sales.
- Returning customers generated 49.6% of 2025 sales.
- Customers seen in any earlier export generated 63.1% of 2026 sales to the
  export date.

Annual cohort return:

| Customer cohort | Returned in following year | Full-year rate | Returned by 23 September | Comparable rate |
| --- | ---: | ---: | ---: | ---: |
| 2023 to 2024 | 1,077 | 38.0% | 764 | 27.0% |
| 2024 to 2025 | 1,091 | 40.0% | 739 | 27.1% |
| 2025 to 2026 | 672 | 23.1% to date | 672 | 23.1% |

Some customers return after missing an entire calendar year:

- 241 purchased in 2023 and 2025 but not 2024. Their median observed gap was
  724 days.
- 147 purchased in 2024 and 2026 but not 2025. Their median observed gap was
  662 days.

This supports a future reward lifetime closer to 24 months than 18 months.

## Purchase frequency and customer spend

| Included orders per customer across the period | Customers |
| --- | ---: |
| 1 | 3,834 |
| 2 | 1,260 |
| 3 to 4 | 969 |
| 5 or more | 673 |

| Four-year customer spend | Customers at or above threshold |
| --- | ---: |
| $100 | 2,544 |
| $150 | 1,716 |
| $200 | 1,222 |
| $300 | 730 |
| $500 | 293 |
| $1,000 | 58 |

## Order-value distribution

The $45 median is stable across every year. Common exact order values across
the four exports include $45 (2,106 orders), $18 (1,707), $90 (729), $30 (742)
and $36 (678).

| Order value | Share of orders at or above value |
| --- | ---: |
| $80 | 25.5% |
| $90 | 22.5% |
| $100 | 15.0% |
| $120 | 11.3% |
| $150 | 6.8% |
| $200 | 3.0% |
| $250 | 1.3% |

The concentrations at $45 and $90 are useful baselines when evaluating future
price or package changes. They do not by themselves prove that a different
price would increase profit.

## Limited product-mix evidence

Legacy item descriptions show sustained demand for three broad competition
products: individual photographs, individual videos and all-photo packages.
Names vary between exports, including several versions of `Full Resolution`,
`Individual Image`, `Competition Video` and the misspelled `Competiton Package`.

The supplied one-month 2025 item report showed:

| Product represented in the report | Quantity | Revenue | Revenue share |
| --- | ---: | ---: | ---: |
| $10 individual images | 954 | $9,540 | 39.4% |
| $18 individual videos | 303 | $5,454 | 22.5% |
| $45 packages | 205 | $9,225 | 38.1% |

This period is too short to establish an annual product mix, but it confirms
that all three product types materially contribute to sales.

## Current commerce decisions that remain in force

The sales analysis does not replace the accepted commerce decisions:

- Competition and concert price lists are separate and reusable.
- Specific galleries and albums can override the matching default price list.
- Cart and favourite selections retain stable media and product identities when
  prices change.
- Paid orders retain immutable prices, discounts and GST treatment.
- GST applicability is configured per product and included GST is calculated
  after discounts and account credit allocation.
- Discount codes may be fixed-dollar or percentage based, date-limited and
  usage-limited.
- Under ADR-0043, a discount code reduces eligible spend before the existing
  bulk threshold is tested.
- Studio-principal $1 images do not qualify for bulk discounts.
- Account credit is an auditable ledger separate from discounts, refunds and
  future loyalty rewards.
- Account credit reduces the amount paid before included GST is calculated.

## Deferred loyalty candidate

No loyalty program is currently approved for implementation. If DancePro
reopens this work, the evidence-supported starting candidate is:

- Earn 5% DancePro Credit on eligible amounts actually paid.
- Make earned credit available 30 days after the qualifying order.
- Require at least $5 available before redemption.
- Allow credit to fund no more than 50% of a new order.
- Expire credit after 24 months of customer inactivity, subject to legal and
  accounting review.
- Notify the customer before expiry.
- Do not earn credit on GST, shipping, free products, $1 studio-principal
  images, purchased credit, gift credit or amounts paid using credit.
- Reverse or adjust earned credit when its source order is refunded.
- Keep loyalty entries distinguishable from manual reward, gift, purchased
  credit and refund entries.
- Attach loyalty to the permanent customer account ID. Use verified email only
  for account discovery or historical migration.

The candidate deliberately rewards a later purchase rather than discounting
the source order immediately.

## Historical loyalty scenario

A directional simulation was run over the four exports using exact email as
identity, a 30-day earning delay, a 50% redemption cap and expiry 730 days after
each earning. It assumed that a customer always used the maximum available
credit on every eligible later order and earned new credit only on the amount
then paid.

| Candidate earning rate | Credit issued | Credit redeemed within exports | Credit expired in model | Ending model balance | Customers redeeming | Redeemed cost as share of sales |
| --- | ---: | ---: | ---: | ---: | ---: | ---: |
| 3% | $26,721.64 | $12,771.63 | $385.05 | $13,564.96 | 2,375 | 1.41% |
| 5% | $44,124.75 | $20,997.89 | $653.89 | $22,472.97 | 2,375 | 2.32% |

This is not a revenue forecast. It does not estimate incremental purchases,
customer behaviour changes, future redemption, refunds, migration matching,
breakage accounting or margin. Because it forces redemption whenever possible,
it is best used as a redemption-exposure scenario.

## Data required before loyalty implementation

### Stable identity

- Permanent customer account ID
- Verified and historical email addresses
- Account merge history with staff audit
- Guest-to-account ownership transfer
- Rules for shared family accounts

### Qualifying transaction data

- Permanent order and order-item IDs
- Product ID, media type, event, gallery, album and dancer/routine relationship
- Gross line price
- Product/package saving
- Bulk discount allocation
- Discount-code allocation
- Account-credit allocation
- GST allocation
- Cash paid
- Refunds and chargebacks by line
- Payment and fulfilment status

### Loyalty ledger data

Every entry should record:

- Customer account ID
- Signed value
- Entry type: pending earn, available earn, redemption, expiry, reversal,
  adjustment or migration
- Source order and source order item where applicable
- Redemption order where applicable
- Earned, available and expiry timestamps
- Eligibility rule and earning rate snapshot
- Campaign ID when applicable
- Staff actor and reason for manual adjustments
- Idempotency key
- Created timestamp

Available balance must be derived from immutable ledger entries. Pending,
available and expired values must remain separately reportable.

### Rule snapshots

An order that earns or spends loyalty must preserve the applied program version,
earning rate, excluded value, qualifying value, amount earned, amount redeemed
and expiry basis. Later program edits must not rewrite historical transactions.

## Loyalty decisions still required

Before implementation, confirm:

1. Whether loyalty is available to all accounts or requires an opt-in.
2. Whether competition and concert products share one balance.
3. The exact definition of customer inactivity.
4. Whether account activity, eligible purchase activity or either one extends
   expiry.
5. Treatment of partially refunded and fully refunded source orders.
6. Treatment of refunded orders paid partly with loyalty credit.
7. Whether credit is restored after a refund and what expiry it receives.
8. Which products and price lists can earn or redeem credit.
9. Whether promotional bonus credit has a different expiry.
10. Whether loyalty and discount codes may combine.
11. Accounting treatment of issued credit, redeemed credit and breakage.
12. Legal wording, customer terms and expiry notifications.
13. Historical balance migration, if any.

## Required reporting foundation

### Executive sales reporting

- Gross sales, discounts, credit, refunds, GST and net paid amount
- Orders, customers, average order and median order
- New and returning customer sales
- Sales by event, gallery, studio, competition, product and price list
- Sales by day after gallery release
- Year-over-year and matching-period comparisons

### Commerce conversion reporting

- Gallery visitors and identified customers
- Product views
- Favourites added and removed
- Cart additions and removals
- Checkout starts, approvals, declines and abandonments
- Conversion from individual photo to album or package
- Promotion shown, accepted and ignored
- Notification delivery, open, click and attributed purchase

### Discount and credit reporting

- Bulk discount value and affected orders
- Discount-code usage, revenue and average order
- Manual account-credit grants and redemptions by source and reason
- Outstanding account-credit liability
- Refunds and restored credit
- Excluded $1 studio-principal transactions

### Future loyalty reporting

- Pending, issued, available, redeemed, expired, reversed and outstanding value
- Redemption rate by earning cohort
- Time from earning to redemption
- Customers with available credit and no subsequent purchase
- Incremental return rate and revenue against an untreated comparison group
- Average order with and without redemption
- Reward cost as a share of paid sales and gross margin
- Liability by expected expiry period
- Reactivation and bonus-campaign performance

## Measurement principles

- Report monetary values from frozen order and ledger records rather than
  reconstructing history from current settings.
- Separate a price reduction, promotional discount, account credit, loyalty
  redemption, refund and payment. They have different business and accounting
  meanings.
- Use matching date ranges for year-over-year comparisons.
- Retain both account-level and order-level views. A high order count can come
  from a small number of repeat customers.
- Record exposure to an upsell or campaign so its conversion can be measured.
- Where practical, preserve a control group before claiming that a reward
  generated incremental sales.
- Do not report outstanding credit as revenue.

## Data-quality improvements

Before relying on new reporting for commercial decisions:

- Consolidate legacy product labels into permanent product IDs without changing
  historical display names.
- Preserve source-system order IDs during migration.
- Import refunds, voids and discount allocations explicitly.
- Capture reliable payment processing and fulfilment costs if profitability is
  required.
- Keep customer merge decisions auditable.
- Document the timezone used for event release, order and expiry reporting.

## Related decisions

- [ADR-0037: Media commerce settings before checkout](../decisions/ADR-0037-Media-Commerce-Settings.md)
- [ADR-0043: Media discount code redemption](../decisions/ADR-0043-Media-Discount-Code-Redemption.md)
- [ADR-0044: Studio-principal photo pricing](../decisions/ADR-0044-Studio-Principal-Photo-Pricing.md)
- [ADR-0045: Reusable media price lists](../decisions/ADR-0045-Reusable-Media-Price-Lists.md)
- [ADR-0046: Customer account credit ledger](../decisions/ADR-0046-Customer-Account-Credit-Ledger.md)

