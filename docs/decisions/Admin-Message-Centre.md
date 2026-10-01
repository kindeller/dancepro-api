# Admin Message Centre

Date: 2026-09-28
Status: Accepted

## Decision

Provide one admin workspace in `app/Features/Messaging` that delegates to the existing customer, studio and crew conversation services. Preserve their storage, delivery, attachment authorisation and read-state semantics. Redirect old admin inbox/detail URLs into the new workspace and remove their duplicated views/navigation.

Do not merge all messages into a new generic table: studio history contains tracker deliveries/inbound emails and ownership; customer requests include order/download decisions; crew emails must remain distinct from the existing private crew chats. The centre coordinates those existing domain services rather than replacing them.

## Consequences

Existing links and mobile/customer/crew interfaces continue to work with the same messages. The Crew tab contains email conversations explicitly tagged with `context.message_audience=crew`, using the established account-email and reply services. This preserves the audience when a crew member also has customer access. The existing Crew Hub chat is a separate destination; its records are not included in Message Centre tabs or counts. Search and compose remain admin-only and validate the selected recipient server-side. Formatting uses the established safe Markdown formatter; private files are linked through authenticated endpoints. No mailbox, SMTP, queue or live-delivery configuration changes are part of this work.

## Account identifiers

The owner requested a consistent identifier format. Users now receive an immutable, unique public UUID using the shared `HasPublicUuid` convention. New account URLs use this UUID. Route binding continues accepting old numeric account URLs so saved links remain valid. An additive migration backfills all existing users (including archived users); relational primary/foreign keys remain unchanged. Its down migration removes only the new public identifier column/index.

Order display numbers stay numeric and unchanged. Public account IDs shown in the directory/details and message recipient references are UUIDs; numeric customer searches resolve order display numbers rather than also matching an unrelated internal account ID. APIs/form contracts that use database IDs for existing internal mutations retain them for compatibility. No order, booking, entitlement or conversation relationship is re-keyed.

## Follow-up navigation and email scope

The default All tab combines customer, studio and crew email histories. Customers and Crew filter on the saved conversation audience; Studios retains its existing shared history and ownership. Existing untagged account email threads remain customer conversations. No existing chat records are migrated or exposed as email. Crew emails use the existing account inbox and email delivery/reply workflow, including private attachments; the separate My Chat interface is reached through Open Crew Hub chat.

Live search uses the same authorised GET page as the non-JavaScript Search fallback. Requests are debounced and older requests cancelled; only the result list is replaced, leaving drafts and attachments intact. Selecting an All-tab recipient chooses the explicit audience before composition. Message badges count pending customer/crew email conversations plus unread studio mail, excluding crew chat counts.
