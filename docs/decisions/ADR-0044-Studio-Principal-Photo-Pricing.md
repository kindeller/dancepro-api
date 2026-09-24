# ADR-0044: Studio Principal Photo Pricing

## Status

Accepted

## Context

Existing authorised studio directors receive $1 pricing for their studio's
eligible concert and dress rehearsal photos. This price must not accidentally
extend to every studio-linked customer or contribute to retail bulk discounts.

## Decision

Apply the $1 price automatically when the signed-in customer's email matches
the studio's primary contact email, or an email on a studio contact whose role
contains owner, principal or director. Ordinary studio membership and other
contact roles do not grant the price.

Principal-priced photos contribute zero dollars to bulk-discount eligibility.
They remain part of the cart subtotal and can still be reduced by a valid
discount code. Freeze the $1 line prices and principal-price marker into the
order draft so later studio-contact changes cannot alter a reviewed order.

## Consequences

Authorised principals receive the agreed price without selecting a special
product or requesting a manual adjustment. Office and customer access remains
separate from the pricing entitlement. Future media eligibility rules can be
added at the album or asset level without changing the contact test.
