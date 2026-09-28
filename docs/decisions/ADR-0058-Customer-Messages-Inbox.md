# ADR-0058: Customer messages inbox

Date: 28 September 2026
Status: Accepted for the customer web implementation

## Decision

My Messages is the customer correspondence home, with Notifications,
Conversations and New message tabs. It uses the public account layout and shared
sticky tabs. Studio booking/production discussions remain in Studio Hub.

Automated correspondence is projected from existing delivery records into
`customer_inbox_notifications`, with a unique source key, customer ownership,
saved content, action link, delivery status and independent read state. Updating
email delivery updates the same entry; it never creates another unread copy.
Existing notices can be imported idempotently with `customers:sync-inbox`.

Conversations extend `customer_support_requests` with immutable message history
and per-customer read tracking. This keeps the existing admin support queue,
reactivation decisions and navigation badges as the source of staff work.
Personal emails sent from customer administration create an outgoing conversation;
complimentary-media notices remain automated notifications. Customer replies
reopen the thread as Pending. Staff replies appear in the thread and queue email.

## Boundaries

Messages and attachments are accessible only to their customer or authorised
administrators. Message text is escaped. Attachments use private local storage,
a restricted file-type/size list and authenticated download routes. Account
merges and privacy exports/redaction cover the new records.

Historical media delivery records did not save the full email body. Those entries
show a summary and their existing account/order link; new deliveries snapshot the
body and action. Password reset, verification/security challenges and private
crew correspondence are not imported into customer messages.

Ordinary mailbox replies require a separately configured inbound email integration.
The owner has requested this connection and confirmed Vodien/cPanel on
`web203.vodien.com.au` (IMAP SSL/TLS 993, SMTP SSL/TLS 465). The pinned
info/admin/orders/notifications address roles remain authoritative.
[Email mailbox routing](../handbook/Email-Mailbox-Routing.md) records the details.
Live authentication, IMAP ingestion and end-to-end reply verification remain
outstanding. No mailbox credentials, routing or external messages have been changed.
