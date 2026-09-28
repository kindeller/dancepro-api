# ADR-0052: Customer service account workspace

## Status

Accepted for the local build. Payment-provider execution remains deferred.

## Context

Customer support needs one account view for identity, orders, event access, communications, downloads, favourites, carts, credits and complimentary media. Staff also need auditable actions for manual access, account status and refunds. These records must not be scattered across unrelated screens or hidden in provider dashboards.

## Decision

- Use the customer account as the support workspace, with separate tabs for orders, access, communications, downloads, current selections and staff activity.
- Preserve paused and cancelled accounts and their commercial records. Pausing or cancelling blocks authentication and revokes active API tokens; it does not erase orders or operational history.
- Record every sensitive staff action with the acting user, customer, time, summary and supporting metadata.
- Treat complimentary media as a zero-dollar fulfilled order so it uses the same entitlement and download path as purchased media without appearing as revenue.
- Allow staff to revoke only complimentary entitlements, with a required reason and optional customer email. Revocation is retained on the entitlement and cannot enter the expired-download reactivation workflow.
- Treat manual event access as a named access grant or registration with an audit reason.
- Store item-level refund requests and their frozen paid and tax allocation. A refund remains `pending_provider` until a payment integration confirms it. Entitlements are not revoked while a refund is merely pending.
- Keep account credits in the existing immutable credit transaction ledger and expose that ledger in the workspace.
- Allow staff to correct a customer's name, preferred name, email and phone with a required audit reason. An email change clears verification and active API tokens. Staff may send the standard expiring password-reset link but cannot view or set a customer's password.
- Preserve every customer and staff change to email contact preferences as a consent-history event.
- Record customer web sign-ins with success, IP address, user agent and simple review flags for repeated failures and first-seen IP addresses. Never store submitted passwords.
- Record event views, favourite/cart changes and completed purchases so the account can show an event conversion history.
- Calculate lifetime paid value, average paid order value, order frequency and purchased-event count from paid and fulfilled orders rather than maintaining mutable counters.
- Store internal notes, tags and follow-up dates separately from customer-visible messages.
- Store chargebacks, disputes, payment failures and manual risk reviews as explicit cases linked to the customer and optional order.
- Offer a JSON privacy export and a separately confirmed anonymisation operation. Anonymisation removes profile data and disables access while retaining pseudonymised order and audit records.
- Detect likely duplicate accounts using normalised names and phone numbers. A merge requires the chosen primary account email and reason, moves supported commercial and media records, preserves an audit record and disables the source identity.

## Consequences

The workspace can be used before payment integration and gives later providers a clear boundary: execute the recorded refund, save the provider reference and result, and revoke only the selected entitlements after success. Account cancellation is operational rather than destructive; privacy access, correction and retention workflows can be added without losing financial audit records.

Retention remains a reviewed workflow rather than an automatic destructive job. Staff must confirm the current account email before anonymisation; financial and operational records remain attached to a pseudonymised account. Production policy must define the applicable retention period before scheduled candidate reporting is enabled.

For Braintree, the provider adapter must use the original successful payment attempt's transaction ID. It should void transactions that have not begun settlement and refund transactions that are settling or settled. Partial refunds pass the selected paid amount, serialize attempts for one original transaction, retain unsuccessful processor details and store the successful Braintree refund transaction ID.

## Consolidation update — 26 September 2026

The account workspace groups its pages under Overview, Orders & credit,
Media & access, Messages, Notes & activity, and Account settings. Histories use
independent pagination and load only on their relevant page. The dedicated order
page owns refund/revocation controls; the account order list links there with a
validated local return path. `/admin/orders` lists all order states and sources.
Dated notes have a cross-account follow-up queue.

Shared communication preferences use `media_ready_email`,
`expiry_reminders_email`, and the existing marketing-consent timestamp. Absent
checkbox values are false on a preference save; only changed consent values create
history rows. A queued media email rechecks consent and anonymised/merged state
before sending. Media-ready notification identity is per entitlement, so later
videos in an existing order can generate their own notice.

Sales reporting derives type/event from individual items, including mixed-event
orders. Report projections are read-only and do not rewrite historical orders.
Cash collected, credit, completed refunds and net receipts have separate labels.
Refund totals on a payment-date report refer to those selected orders' completed
refunds, not refunds settled within that period. Download counters remain lifetime
customer/file aggregates filtered by last request, explicitly labelled as such.
