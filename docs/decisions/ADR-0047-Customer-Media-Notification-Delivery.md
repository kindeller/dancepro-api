# ADR-0047: Customer media notification delivery

## Status

Accepted — 23 September 2026

## Context

Media orders can contain files that are ready immediately and videos that become
ready later. Photo and video access also expire on separate readiness-based
clocks. Customers need reliable order, readiness and expiry email without duplicate
messages when payment or release actions are retried.

## Decision

Store every customer media email in `customer_media_notifications` before it is
queued. A unique semantic key makes order confirmations, media-ready notices and
expiry reminders idempotent. Notification events update the record from queued to
sent or failed.

Successful payment always creates one order confirmation. A newly released pending
media type creates one ready notice when the customer's `media_ready_email`
preference is enabled. A daily task sends one reminder for undownloaded media due
to expire in exactly ten days when `expiry_reminders_email` is enabled. Entitlements
from the same order with the same expiry date share a reminder.

Administrators edit the three templates separately, can send an immediate test
message, and can inspect the latest delivery records. Email delivery remains queued
for customer messages so checkout and media release do not wait on the mail server.

## Consequences

- Retried payment and release operations cannot send the same customer message twice.
- Customer preferences apply to service messages about readiness and expiry; a
  transactional order confirmation is always sent.
- Production requires a working mail transport, queue worker, scheduler and failed
  job monitoring.
- Delivery history records metadata and status without storing rendered message bodies.

