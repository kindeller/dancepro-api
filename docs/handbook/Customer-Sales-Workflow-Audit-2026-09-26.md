# Customer, sales and order workflow audit

Date: 26 September 2026. Scope: the current local working tree, including uncommitted work.

## Assessment

The foundations are present: account search, customer history, global selections, orders, complimentary media, credit, download entitlements, support requests and event engagement. The next useful step is consolidation and correctness, before adding more dashboards.

The biggest gaps are inconsistent reporting definitions, several incomplete connections between screens, and operational controls that are present as a first implementation rather than a finished workflow. Live payments remain deliberately deferred; their absence is not a regression.

This was a read-only product audit. No customer details, purchases, refunds, messages, grants, preferences or account statuses were changed. Browser review covered customer overview/privacy, sales orders, an order detail and studio visitor activity. Other findings come from routes, templates, services, models, tests and planning documents. This is not a claim that every route, mobile device or real provider interaction was exercised.

## What already connects well

- Sales orders and account order history open the same dedicated order detail page.
- Orders link to their customer account; visitor reports link to accounts and orders; support requests link to accounts.
- The global cart preserves event groups and independent event discount calculations while preparing one order.
- Checkout snapshots products/files, checks that selections are still current, records payment attempts and activates file entitlements. Pending video can have a separate readiness date and download period.
- Complimentary grants have a recorded order, an administrator audit trail and implied event access. Revocation and optional customer notification exist.
- Credit uses a ledger rather than an editable balance field. Download access is checked server-side and requests are recorded.
- Customer favourites/cart reuse media previews and gallery links. Keep these account-scoped views: they answer a support question that aggregate reports cannot.
- Concert reporting now has a useful studio parent/event child structure. Keep it.

## Findings to fix first

| Priority | Finding and evidence | Impact and recommended correction |
|---|---|---|
| High | **Studio activity filters lead to 404.** Reproduced by clicking Apply on the Aurora studio activity page. The shared view builds Apply and Clear URLs using the concert-event route with a studio UUID. See `resources/views/admin/media/gallery-activity.blade.php`. | Use the descriptor's actual activity URL for both studio and event scopes. Test submitting filters, clearing and exporting at both levels. |
| High | **Admin communication preferences are disconnected from the sender.** `CustomerAccountIntelligence::preferences()` saves `service_email`, `download_email`, `gallery_email`; the customer form/sender use `media_ready_email` and `expiry_reminders_email`. The admin-only keys have no sending consumers in the inspected application. Unchecked optional inputs are also omitted rather than explicitly changed to false. | Share one preference definition across customer/admin screens and sending logic. Explicitly normalise unchecked values, distinguish necessary transactional messages from optional notices, and record actual changes. |
| High | **Privacy export/anonymisation is incomplete.** Export omits several stored categories, including selections, dancers/studios, access, communications and login/download history. Anonymisation soft-deletes `CustomerProfile`, which retains its data, and does not redact `customer_media_notifications.recipient_email` or all other personal-data-bearing records. See `CustomerAccountIntelligence`, `CustomerProfile`. | Inventory each personal-data field and retained record, make export coverage explicit, and implement reviewed retention/redaction rules. Do not present the current action as comprehensive anonymisation. |
| High | **Multi-event orders do not fit all reports.** Checkout uses `kind=account_cart` / `source_uuid=selected-events`; sales type filters match only the order header. Customer Insights counts distinct header sources, and payment engagement records the same header as the purchased event. | Derive event/type reporting from order-item/event allocations. A competition filter should include the competition portion of a mixed order, with clearly labelled whole-order versus selected-event totals. |
| High | **Reporting totals have different meanings.** Overview sums cash after credit; product sales sum original item totals; event sales use item amounts after credit. Customer paid-order count and repeat-customer metrics include complimentary fulfilled orders. Refunds are not deducted. | Establish shared definitions for gross sales, discounts, credit applied, cash collected, refunds, net receipts, paid purchases and complimentary grants. Make the labels and exports reconcile. |
| High | **Event type filtering is incomplete.** `GalleryEngagementReport::events()` calls `orderAllocations($filters)` without passing the selected kind; allocations do not themselves read `filters.kind`. | Apply the same event scope to activity, selections and order allocations. A filtered report must not reintroduce another event type through purchases. |
| Medium | **Download date filters are misleading.** Reports filter an aggregate row's `last_download_at`, then sum its lifetime `download_count` and bytes. “Unique files” counts rows rather than distinct assets. | Either label these as lifetime totals for files last requested in the period, or add individual download-event records for genuine period reporting. Keep “requested” separate from confirmed device download completion. |
| Medium | **Account histories silently stop.** The customer controller caps orders/downloads at 50, messages at 25, activity/login history at 100, without history pagination. Overview counts are based partly on these limited collections. | Paginate and search each history; calculate all-time totals separately. Make recent versus lifetime counts explicit. |
| Medium | **Manual concert access cannot reliably renew an existing support grant.** `CustomerAccountAdministration::addEvent()` uses `firstOrCreate`, leaving an existing expired/revoked support grant unchanged. | Provide an explicit renew/reactivate action with current expiry and an audit reason. |
| Medium | **Concert source lookup is inconsistent.** `AdminOrderDetail::sources()` resolves concert source UUIDs as studios, while global cart snapshots use concert UUIDs. | Resolve modern event snapshots as concerts, retain the legacy studio fallback, and link directly to the correct gallery. |
| Medium | **Media-ready notifications can suppress later releases.** The deduplication key is order ID plus media type, so separate videos becoming ready at different times within one order share a key. | Deduplicate per entitlement or an explicit release batch; preserve a clear event/item label in the notice. |

These are code-confirmed findings except where a browser reproduction is explicitly stated. They should become regression cases, not just visual fixes.

## Order processing: what still needs finishing

The current gateway binding offers a local fake gateway or a disabled gateway. Refund requests produce `pending_provider` records; they do not return money. The screen does disclose this. Preserve that honest distinction.

Before real payments, finish the agreed provider integration with idempotent payment/refund handling, authenticated provider callbacks, retry/reconciliation, failed-payment recovery and dispute visibility. Make provider status separate from order fulfilment and media readiness. Test timeouts and repeated callbacks, not just approved/declined responses.

The practical gaps in daily order administration are:

- **A complete Orders workspace.** The Sales Orders list contains paid/fulfilled customer-cart orders only. It is not a list of every draft, failed attempt, complimentary grant, pending refund or partly fulfilled order. Add status/source/event/customer filters and a search that finds all order types.
- **Actions on the order itself.** Refund preparation currently lives in the customer account's expanded order cards. Order detail should be the canonical location for refunds, complimentary revocation, receipt resend, support context and delivery access management; the customer page should link into the relevant order action.
- **Event-grouped products.** Show event → section/competitor, or concert → routine, with images and group subtotals. Show package photo/video components together while retaining the allocation needed for partial refunds.
- **Separate cash refunds and credit restoration.** Show original tender, available refundable amount, selected products, previous/pending refunds, credit to restore and whether access will be removed. Current refund preparation uses cash allocations and has no completed restoration workflow.
- **Refund management.** Add a queue, readable reference, pending/processing/completed/failed states, retry/cancel rules and links back to the order/customer. Arbitrary amount or partial-quantity refunds need an explicit design if required; current selection is whole order-item rows.
- **Fulfilment visibility.** Show ready, waiting for video, expired, revoked and reactivated per item, independently of payment status. Provide an exception queue for paid orders whose files never become ready.
- **Receipts and exports.** Provide a customer-readable itemised receipt and easy resend/download from the order. Current receipt email is a short order/total summary. Review credit-type and tax allocation rules before live use; this audit does not certify their accounting treatment.

## Recommended menu structure

Use three clear destinations instead of placing every commercial task under Media:

| Destination | Pages | Purpose |
|---|---|---|
| Customers | Accounts, Support inbox, Follow-ups | Find a person and resolve their issue. |
| Sales | Orders, Refunds & payment issues, Account credit, Discount codes | Perform and reconcile commercial actions. |
| Reports | Overview, Event engagement, Customer performance, Product performance, Downloads | Understand results without mixing configuration/actions into the report. |

Keep Gallery Settings for gallery behaviour, pricing configuration and relevant notification templates. Link to discount/credit management rather than maintaining another independent management screen. Remove the inactive Loyalty planning tab from everyday operations until it is useful; preserve its specification.

“Customers” in Reports should be labelled **Customer performance** to distinguish it from the account directory. Customer order history and the global Orders list are useful scoped views of the same records, not duplication to remove.

## Customer account layout

The current twelve-tab strip runs off the visible area at the reviewed 1280-pixel desktop viewport. The overview starts well, but then becomes a long collection of editing, email, credit, access and status forms. Privacy mixes preferences, duplicate merging and anonymisation.

Use six primary pages with secondary tabs where needed:

| Primary page | Contents |
|---|---|
| Overview | Read-only contact summary, account status, verified email, balance, concise purchase/activity totals, dancers/studios, open issues and quick actions. |
| Orders & credit | Order list, complimentary grants, credit ledger. Open an order for its actions. |
| Media & access | Events, Favourites, Cart, Downloads as secondary pages. Keep the familiar thumbnail presentation. |
| Messages | One chronological view of customer messages and outgoing emails/notifications, with channel/status filters and compose/reply actions. |
| Notes & activity | Internal notes, tags, follow-ups and an administrator activity timeline. Customer insights can be a small summary with a report link. |
| Account settings | Contact editing, preferences, security/risk, status, duplicate-account review and privacy tools, with clearly separated sections. |

Opening Edit or another action should reveal only that form. Keep permanent page-level controls in the same position. Do not turn all detail pages into one long accordion; preserve real URLs and sensible browser navigation.

## Connections and overlaps to improve

- Visitor favourites/cart counts should open that customer's corresponding page **filtered to the selected event**. The present values are plain text.
- Support order references should link to the order. Downloads should link to both customer and order. Event headings on orders should open the actual gallery.
- Preserve return context and filters: account → order → Back should return to that account's order list, while report → order → Back should restore the report. The current order page always returns to Sales Orders.
- Keep the relevant sidebar section expanded and selected on order pages. Browser review confirmed it collapses there because `admin.orders.*` is absent from the Media-active route list.
- Use friendly order numbers consistently. Credit-history notes still expose UUIDs, and manual risk entry expects the internal database order ID. Prefer searchable order selection displaying the friendly number, date and amount.
- Distinguish permission to browse an event from ownership of particular media. Show why access exists, who granted it, its expiry and any restriction.
- Keep studio production messages and customer purchase support as separate workflows, but link both when one account has studio and customer responsibilities.

## Support, communications and account-care gaps

- Support has a basic status queue and recorded outcomes. Add search by account/order/event, ownership, priority, due date, thread history, reopen and related-order links. Customer messages should be readable/respondable from the account without losing the queue context.
- Follow-up dates are stored and can be completed, but there is no customer follow-up reminder job in the inspected scheduler. Add a global due/overdue queue before calling these active reminders.
- Chargeback/risk records are manual entries, not a provider-synchronised risk feed. Label them accordingly until gateway integration is complete.
- Duplicate detection exists, but merging needs a preview of both profiles, conflicts, credit, event access and media. `mergeUnique()` retains the target record on collision, which can discard source dancer aliases or a verified access state. Payment-attempt ownership and customer archive ownership are not in its move list. Define intended handling and verify every user-linked record; do not assume all omissions imply lost purchased entitlements, which follow the moved order.
- Email histories are split between direct emails and automatic notices. “Sent” is not proof of delivery. Add provider delivery/bounce information and controlled resend/retry when connected; keep the source and audit trail.
- All these administration routes use the broad admin gate. Before involving support staff, define separate permissions for viewing customer information, granting media, granting credit, refunding, merging and anonymising. This is a separation-of-duties improvement, not evidence of unauthenticated access.

## Reports worth expanding after correctness

1. A reconcilable sales summary: gross sales, discounts, credit, cash, refunds and net receipts; consistently defined by event, product and customer.
2. Event performance: unique visitors, purchasers, conversion, favourites/cart activity, paid value and complimentary value. Keep studio totals and per-concert rows, avoiding double-counted studio visitors.
3. A useful opportunity report: visitors with favourites or cart items and no paid purchase, with event-specific detail. Any later reminder sending must respect the shared preferences and approved messaging policy.
4. Customer value and repeat purchasing based on paid purchase activity, excluding complimentary grants from purchase-frequency claims.
5. Operational exceptions: payment failures, pending refunds, paid media awaiting release, failed emails, overdue support and follow-ups.
6. Download cost indicators: requests, repeated requests, estimated bytes, archive generation/reuse and large-download failures. Estimated bytes are not verified bandwidth billing or confirmed saves to a device.

Define whether each report represents activity **during a period**, current state **now**, or state **as of a date**. Current favourites/cart selections and mutable last-access timestamps cannot reconstruct a historical snapshot. Guest access and account engagement also have different tracking depth; display “not recorded” where appropriate instead of implying zero visits.

## Formatting and efficiency

- Left-align customer/event/product descriptions; right-align currency and numeric totals. The current centre-aligned report cells are harder to scan with long event names.
- Standardise tab appearance, selected states, status colours, toolbar placement, card spacing and compact checkbox rows. The inspected privacy checkboxes are now normal-sized, but the adjacent form has cramped labels and an oversized reason textarea.
- Use short textareas for short reasons, longer ones for message bodies. Avoid a full refund/revocation form repeated underneath every historical order.
- Keep event headings, thumbnails and routine metadata consistent between gallery, favourites/cart, account and order contexts. Use skeleton/loading/error/empty states appropriate to each view.
- Hide unavailable report filters rather than silently ignoring them. Download reporting, for example, does not apply the shared kind filter.
- Load only the selected tab. `AdminCustomerAccountController::show()` currently queries all major histories and computes both selection views each time. `MediaSalesReport::report()` builds all report datasets from full order/item collections, even when only one report is displayed.
- Move aggregate calculations into dedicated query/services, use SQL aggregation and pagination, then measure query count/time with realistic volumes. Large exports should stream/chunk or become queued jobs.
- ZIP creation currently copies the selected originals and builds the archive synchronously. Large batches need queued preparation, progress, retry and resource limits; cached identical competition selections remain useful.
- Reduce duplicated inline CSS and densely compressed template/service code by extracting established shared components, without inventing a second presentation system.

## Recommended sequence

1. **Correctness:** broken studio filters, preferences, mixed-order report allocation, metric definitions, complete history access and source links; tighten privacy/merge behaviour before relying on them.
2. **Consolidation:** Customers / Sales / Reports navigation, smaller account navigation, contextual links, one canonical order action page and shared components.
3. **Operations:** complete order/refund/support queues, follow-ups, communication retry, delivery exceptions and realistic performance checks.
4. **Live-payment readiness:** Braintree sandbox integration and failure/reconciliation/refund scenarios, then the agreed production approval steps. Keep live payments last as planned.

Suggested acceptance scenario: one customer has competition purchases, two concerts, a complimentary photo, account credit and a later video release. One combined checkout must reconcile in every report; staff must move from visitor → event-specific favourites/cart → order → account without losing context; a selected-product refund must preserve unrelated purchases; later video release must notify correctly; histories must remain available past the first page.

## Verification

Browser checks confirmed the studio filter 404, customer tab overflow, normal-sized privacy checkboxes, order/source presentation and missing active sidebar context on order detail. `./vendor/bin/sail artisan test` passed: **723 tests, 6,639 assertions** (249.6 seconds). The passing suite does not cover all the gaps identified above. No live payment, email, refund, merge or anonymisation was performed. Responsive styles were reviewed, but physical phone testing and production-scale load tests remain outside this audit. No application code or routes changed, so route-list and code-formatting checks were not applicable.

Documentation drift also needs a pass: parts of the roadmap/milestone still describe a single-event cart and older gallery navigation, while later sections describe newer behaviour. Reconcile those documents when the recommended changes are scheduled.

## Implementation status — first pass, 26 September 2026

The findings above describe the original audit. This section records subsequent
implementation; it does not imply that every recommendation is complete.

### Implemented

- Fixed studio visitor-filter Apply/Clear URLs and event-specific links into a
  customer's favourites and cart.
- Shared administrator/customer preference storage and consent history, including
  unchecked options; queued media notices recheck current consent and account
  anonymisation/merge state. Separate entitlements get separate ready notices.
- Added shared order financial/event resolution, mixed-order type filtering,
  item-level cash reporting, scoped CSV figures and customer purchased-event counts.
  Complimentary grants are excluded from paid-customer value/repeat counts. Completed
  refunds appear separately from cash collected and net receipts. Previous-period
  date ranges use whole days. Checkout preserves the quoted discount for each event.
- Expanded account export and redaction to related account records, including the
  persisted soft-deleted contact profile. Reactivation is blocked for merged or
  anonymised accounts. Financial/media identities remain retained.
- Strengthened merge collisions for dancer aliases, studio verification, profile
  uniqueness and additional histories, payment attempts and archive ownership.
  Primary preferences win; conflicting contact details are retained as staff activity.
- Replaced the overflowing primary customer tab strip with six page groups and
  contextual secondary navigation. Contact/status, credit, email and access forms
  now live on their appropriate pages. Histories have pagination, and inactive
  account tabs no longer load their datasets.
- Added the all-order workspace, including drafts, grants, refunds and unpaid
  failed/declined payment attempts. Order detail owns refund/revocation controls,
  has readable refund references, entitlement state and validated return links.
- Split Media, Sales & reports, and Customers in the sidebar. Order detail keeps its
  navigation context. Removed the inactive loyalty tab from everyday navigation.
- Added the cross-account follow-up queue and retained the existing completion action.
- Corrected modern concert source lookup and retained legacy studio fallback.
  Support access can renew a revoked/expired concert grant.
- Avoided building inactive report datasets where practical. Download counters and
  current/lifetime credit labels now explain their actual scope.

### Still open / deliberately bounded

- Live Braintree charge/refund execution, reconciliation/webhooks and restoration of
  account-credit tender are not activated. Prepared refunds still move no money.
- Merge conflict preview and a complete retention workflow remain needed. Expanded
  account redaction/export is not erasure from backups, infrastructure logs, external
  providers or original media; financial records and associated product identities
  are retained. No production privacy compliance certification is implied.
- Direct emails, automatic notices and support messages remain distinct histories;
  provider-delivered/bounced states and a unified threaded history remain future work.
- Existing historical item allocations are not rewritten. New checkout allocations
  preserve event quotes; older snapshots need a separately reviewed reconciliation.
- Sales refund totals are an order-cohort measure based on payment date. A settlement
  or period-refund cash-flow report is still needed. Product/event displays show cash
  collected; they do not yet have a full refund breakdown per row.
- Download history has no per-request ledger yet: dates select records by last request,
  while displayed counters are lifetime aggregates. It cannot answer exact transfer
  volume within a period. Media-type selection is hidden on this report.
- The first order workspace has source/status/customer/order filters; event/date
  filters, receipt resend, partial-fulfilment work queues and global credit operations
  remain enhancements.
- Account histories paginate, but some report/source queries still materialise large
  datasets. Database aggregation, bounded exports and production-scale testing remain.
- Role-specific financial/privacy permissions, expiring-media/failed-message queues,
  taxonomy consolidation and background ZIP preparation remain in the audit backlog.

### Verification of the implementation pass

- `./vendor/bin/sail artisan test --compact`: **733 tests passed, 6,695 assertions**
  (243.7 seconds), including ten new audit regression tests.
- `./vendor/bin/sail artisan route:list --path=admin`: succeeded; 250 admin routes,
  including the all-order workspace and customer follow-up queue.
- Scoped `./vendor/bin/sail pint --test` passed for the PHP files changed in this pass.
- `git diff --check` passed. No migrations were added and no commit was created.
- Browser review covered the all-order list, grouped customer overview/settings,
  order source/return navigation and access controls, sales overview, follow-up
  queue, and successful submission of the previously broken studio activity filter.
- Existing local accounts/orders were not merged, anonymised, refunded or granted
  additional media for these checks. No real customer email was sent. Mutation tests
  used the isolated test database and notification fakes.
