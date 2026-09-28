# ADR-0051: Account-wide media basket

## Status

Accepted.

## Context

Customers may return to DancePro after several competitions or concerts and select media from more than one event. Restricting an account to one active competition or studio cart hid the earlier event, interrupted browsing, and encouraged customers to discard saved selections.

Prices, bulk discounts, products, fulfilment rules, and download periods can differ by event. Combining selections for presentation must not combine those event-specific calculations.

## Decision

Signed-in customer favourites and cart selections persist across competitions, concerts, and studios. My Media provides account-wide Favourites and Cart pages.

Signed-in galleries link to those account-wide pages instead of rendering event-specific favourites or cart pages. The links identify the gallery being viewed so its event group appears first. Entry from My Media uses reverse event-date order. Guest gallery sessions keep their gallery-scoped pages because those selections do not belong to an account yet.

The account-wide pages group selections by event. Each event group calculates its own subtotal and bulk discount using that event's price list. The account-wide cart may display the sum of those event totals, but crossing an event boundary never helps an event reach a bulk discount threshold.

Removing an item affects only that selection. Adding media from a new event does not clear or replace existing selections.

Checkout creates one account-cart order for the event groups selected by the
customer. The order retains a snapshot for each event, including its selected
items, subtotal, allocated code discount, bulk discount and total. A discount
code is applied once across the transaction and allocated proportionally to the
selected event groups before each event's bulk discount is recalculated. Account
credit is then applied once to the combined total. GST remains calculated from
the amount actually paid and the tax settings of the purchased products.

One payment settles the grouped order. Successful payment clears only the
selection snapshots included in that order; carts left unselected remain saved.
The event and product metadata remains attached to order items for fulfilment,
download clocks, reporting and future refund handling.

Guest gallery sessions remain gallery-scoped until they are attached to an account. Account-wide persistence begins once selections belong to the signed-in customer.

## Consequences

- Customers can collect media across several events before checkout.
- Favourites remain useful as a long-term shortlist.
- Pricing and bulk discount rules remain isolated per event.
- Production payment integration can charge the grouped order as one transaction while retaining its event snapshots.
