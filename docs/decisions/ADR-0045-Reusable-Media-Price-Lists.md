# ADR-0045: Reusable Media Price Lists

## Status

Accepted

## Context

A single global set of media prices cannot support special events, studios or
albums without changing the prices for every gallery. Most galleries should
still receive the normal prices automatically.

## Decision

Maintain separate reusable price lists for competition and concert products.
One active list of each type is the automatic default. New competition events
and concerts inherit the matching default without an explicit assignment.

Resolve prices from the most specific active assignment:

1. Competition routine or media-album override, or concert photo-album override.
2. Competition event or concert gallery override.
3. Automatic default for the matching gallery type.

An override may only use an active price list of the same gallery type. A list
already created as competition or concert pricing cannot change type. Changing
a price list affects new quotes and drafts; order drafts and paid orders retain
their captured line prices. The authorised studio-principal $1 price continues
to take precedence over commercial concert price lists.

Prices are GST-inclusive. Each price list stores a GST rate, initially 10%, and
an administrator chooses GST applicability independently for every product in
that list. After promotional and bulk discounts are allocated across the order,
the included GST is calculated only on taxable lines. The resolved rate and
included GST amount are frozen on each order item, and the order stores their
sum for checkout, sales reporting and future receipts.

Favourites and cart selections store the stable media and product identity,
not a copied price. Changing or reassigning a price list therefore preserves
the customer's selections and applies the current resolved price the next time
the cart is quoted. Preparing a new draft supersedes an older stale draft
without clearing those selections.

Bulk-discount tiers and promotional codes remain separate commerce rules rather
than properties of a price list. Their application can change the included GST
amount but does not change which products are taxable.

## Consequences

Routine events require no manual setup, while an administrator can reuse a
special price list across selected galleries or albums. Competition and concert
products can expand independently. Assignment foreign keys preserve referential
integrity and return safely to automatic pricing if a list is removed later.
Mixed taxable and GST-free products can share a cart without reconstructing the
tax treatment from later price-list settings.
