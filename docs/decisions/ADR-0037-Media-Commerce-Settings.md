# ADR-0037: Media commerce settings before checkout

## Decision

Keep shared gallery commerce rules in one persisted Media settings record. Reusable competition and concert price lists now hold product prices, with automatic defaults and specific gallery or album overrides defined by ADR-0045. New cart quotes read the resolved prices, bulk discount tiers and upsell preferences; order drafts store their resulting amounts so later setting edits do not rewrite paid orders. Competition previews use a private PNG overlay (built-in by default), and concert previews use configurable text. Preview cache keys include the watermark revision.

Discount codes are stored as inactive drafts until checkout can validate and redeem them. Media-ready and expiry message templates are editable but no customer messages are dispatched yet. The sales page reports only paid or fulfilled customer-cart orders and excludes drafts.

## Consequences

An administrator can prepare the customer-facing gallery experience without making incomplete checkout or delivery features appear live. Checkout must later define code applicability, stacking, usage limits and redemption records; delivery jobs must respect profile notification preferences and the per-media readiness clocks before templates are sent.
