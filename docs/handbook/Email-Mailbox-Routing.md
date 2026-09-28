# DancePro email addresses and mailbox connection

Confirmed by the owner on 28 September 2026. This preserves the
[pinned production email decision](../milestones/Concert-Tracker.md#pinned-production-email-decision).

| Address | Purpose | Reply destination in DancePro |
| --- | --- | --- |
| `info@dancepro.com.au` | Public enquiries and personal correspondence | Customer Conversations where a customer/thread is verified; other enquiries remain staff-only |
| `admin@dancepro.com.au` | Studio bookings, concert operations and tracker replies | Authorised studio/concert communication history |
| `orders@dancepro.com.au` | Order enquiries; existing ShootProof use during migration | Customer order/support conversation after matching the account/thread |
| `notifications@dancepro.com.au` | Preferred visible From address for automated messages | Reply-To uses the relevant address above |

Automated sending must not use the `info@` mailbox as its transport. Choosing a
Vodien inbound connection does not replace the planned transactional sending
provider or its separate studio, order, customer-media and security streams.

## Confirmed provider settings

The owner's cPanel Mail Client Manual Settings screenshot for
`notifications@dancepro.com.au` shows:

- Provider: Vodien, standard cPanel hosting.
- Incoming server: `web203.vodien.com.au`.
- Incoming protocol: IMAP over SSL/TLS, port **993**.
- Outgoing server: `web203.vodien.com.au`.
- Outgoing protocol: SMTP over SSL/TLS, port **465**.
- Username: the full mailbox email address.
- Authentication required. No password was supplied or recorded.

Verify the same host in the other mailboxes' settings before activating them.
The screenshot's web control-panel session address is not an IMAP endpoint and
must not be copied into configuration.

## Current status and remaining work

The application inbox, in-app replies, private attachments, unread tracking and
email-source history are implemented. **Live Vodien mailbox ingestion is not
implemented or connected.** Replies from external email apps do not yet appear
automatically in My Messages. No remote mailbox, forwarding rule, DNS record or
outbound transport has been changed.

Activation sequence:

1. Use the approved secure configuration mechanism for mailbox authentication.
   Do not paste passwords into chat, commit them or print them in diagnostic logs.
2. Add a verified-TLS IMAP adapter with bounded polling, timeouts, retry tracking
   and an explicit import start point. Read without marking messages read,
   deleting them or moving them out of the existing mailbox.
3. Match replies using trusted conversation references and the expected sender.
   A subject or an email address alone must not expose an existing account's
   history. Unmatched/spoofed/ambiguous mail stays staff-only for review.
4. Deduplicate retries using the mailbox identity and server message identity.
   Imported attachments need the same private storage and validation as uploads.
5. Route studio mail to Studio Hub, customer/order mail to Conversations, and
   keep technical delivery/bounce traffic separate from personal messages.
6. Test one expressly authorised round trip for each route, including duplicates,
   attachments, account ownership and connection failure, before enabling polling.

The repository requires the `aws-secrets-manager` skill before secret/credential
work. It was not available in this session's skill catalog or local skill search;
no mailbox credentials have been accessed. Resolve that required secure workflow
before entering or using credentials. The IMAP adapter and live verification are
still open work, not implied by the confirmed server settings.
