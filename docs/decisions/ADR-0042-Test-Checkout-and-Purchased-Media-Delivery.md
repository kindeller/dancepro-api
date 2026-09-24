# ADR-0042: Test Checkout and Purchased Media Delivery

## Status

Accepted

## Context

The customer galleries had immutable order drafts and media entitlements but
no end-to-end checkout or original-file delivery path. Real merchant setup is
not ready, while the customer experience still needs to be reviewed safely.

## Decision

Use a payment gateway contract with a fake implementation restricted to local
and test environments and a disabled fallback. The fake checkout offers
explicit approved and declined outcomes,
collects no card data, and records each result in `payment_attempts` under a
unique idempotency key. Only an approved attempt changes the order to paid and
activates its frozen media assets.

Purchased originals require an authenticated, unexpired entitlement. Each
request creates a five-minute database-backed download link and redirects
through the existing tracked signing endpoint. Pending package videos remain
without a ready date or expiry until verified video media is linked.

## Consequences

The whole customer flow can be reviewed locally without charges or payment
credentials. A future Braintree adapter can replace the fake implementation
without changing order fulfilment. Live payments remain disabled until their
provider configuration and operational behavior are complete.
