# Reporting Epic

## Purpose

Reserve a place for the future DancePro reporting capability design.

## Current Status

The first historical sales baseline is documented. The Media Sales area now
reports live paid and fulfilled customer-cart orders through Overview,
Customers, Products, Events, Discounts & credit, Loyalty and Orders tabs. It
supports date, media-type and customer filters, matching-period comparisons and
a filtered order CSV export.

The Loyalty tab is deliberately informational while loyalty remains disabled.
The historical ShootProof exports have not been imported into live order tables;
their aggregate baseline remains in the linked specification.

## Scope

Reporting must distinguish gross prices, promotional discounts, account credit,
future loyalty credit, refunds, GST and the amount paid. Sales and customer
retention reporting must use frozen transaction records and stable customer
identities rather than current commerce settings.

Current reports distinguish discounts, account credit and GST from the paid
total. Product and event reporting use frozen order-item metadata and order
source snapshots. New and returning customer classification requires a selected
From date and checks for an earlier paid customer-cart order.

## Links to Related Documentation

- [Architecture](../handbook/Architecture.md)
- [Security](../handbook/Security.md)
- [Media sales and loyalty baseline](../specifications/Media-Sales-and-Loyalty-Baseline.md)

## Notes / Future Work

Customer order exports contain personal information and must remain outside the
repository. Future reporting scope includes refunds, gallery-to-checkout
conversion, notification attribution, price-list performance and a controlled
legacy reporting import. Loyalty remains deferred; the baseline records the data
and reports that will be required if it is approved later.
