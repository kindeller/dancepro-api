# Customer web gallery

## Completed locally

- Registered competition customers now use a dedicated gallery page rather
  than the administrator Customer Preview template. The existing ready-section
  and event-registration checks still govern folders, thumbnails and previews.
- Day, section and routine cards follow the prepared competition hierarchy;
  breadcrumbs return to each parent. The photo grid keeps natural image ratios
  and opens a watermarked in-page viewer with close, previous, next and keyboard
  controls. Preview links still work when JavaScript is unavailable.
- Competition views now include My Dancers, My Studios’ Routines, Improvisation
  and Whole Event. Improvisation lists ready routines whose item or section is
  labelled improvisation and does not rely on dancer or studio names. Personal
  views do not restrict Whole Event browsing.
- Folder and photo cards are more compact. The selected competitor label stays
  in the breadcrumb without repeating as an album heading. My Media separates
  its three Add Media actions with icons and panels. Competition actions use
  “media” so the wording also covers planned video purchases/downloads.
- Recommended Media cards on My Media now use a smaller grid. Their four text
  rows show event title, section, competitor and the routine action in that
  order. The full stored event title wraps onto additional visual lines when
  needed; no date is appended separately. Unregistered event cards keep the
  event-login action.
- My Events uses the same compact card size and wrapping title. It shows the
  event category without a repeated date, and the card action reads “View
  event →”. Competition events without registration still request the event
  login; photo-only concerts continue to open their gallery, while concerts
  with video open the concert page that also links to photos.
- My Media purchased cards now share the compact size. Competition cards use
  owned-media thumbnails where available; concert cards show their date. All
  paid items from the same event open one flat, deduplicated album across orders
  and routines. The local review account has one watermarked competition sample
  spanning two routines in Photos and one concert sample in Videos, labelled
  Sample; neither creates a paid order or download right.
- Competition galleries now offer persistent photo and routine favourites,
  individual-photo and all-routine-photo cart selections, and a one-event cart.
  Available video collections expose routine video; packages can be quoted while
  video is pending. The cart collapses overlapping selections for pricing,
  applies the agreed bulk tiers,
  suggests the all-photo/package saving and the next nearby discount, and quotes
  the $27 package upgrade when the same customer previously bought that routine's
  video. Purchase actions recheck ready-section and event access.
- Competition gallery navigation now uses one shared row of page-style tabs on
  the event, dancer, studio, improvisation, favourites and cart pages. Tabs use
  person/dancer, heart and cart icons, plus the customer's verified studio logo
  and the event logo when available. Favourites and Cart are separate tabs; the
  saved selections and quote behavior are unchanged.
- Whole Event now leads the tabs and the dancer tab reads “My Dancers’
  Routines.” Ready routine albums show separate photo, package and available
  video product cards with prices and cart/favourite icons. The individual
  photos product card starts a one-page multi-photo mode; the customer can
  choose several thumbnails and add them together to Cart or Favourites.
  Thumbnail icons act on that photo alone. The batch endpoint validates every selected
  verified file before writing any selection.
  Individual photos now have their own $10-each product card, and the $18
  individual routine video has a card even while pending (its cart action is
  disabled until ready). Photo cart/heart controls sit over thumbnails with a
  hover price and matching favourite label. Both icons also overlay the
  bottom-right corner of the large watermarked preview, which closes with Escape.
- Released concert photo collections now open inside the customer web gallery
  for customers with a verified studio profile. Albums use stored sort order;
  photo counts are hidden. Private originals are rendered into separate baked-in
  watermarked previews. Draft and unreleased collections are excluded. Existing
  external gallery links remain for concerts without native released albums.
  The local Moonlit Harbour demo now has a `Sample photos` album using four
  existing test JPEGs; it can be reviewed at
  `/my/concerts/20000000-0000-4000-8000-000000000001/photos`. The original
  invalid demo placeholder was hidden locally. No purchase was created.
- Concert photo galleries now have photo favourites and a quote cart. The cart
  spans concerts and years within one verified studio, prices photos at $10
  each, applies the same bulk thresholds and rejects a second studio until the
  first cart is cleared. Unreleased assets do not contribute to the quote.
- Media → Concert Photos now provides the missing staff setup workflow. It lists
  scheduled concert events, shows verified Mac-uploader folders and previews,
  and syncs them into private customer albums with stable identities. Staff can
  rename and numerically order draft albums; later syncs preserve those edits
  and do not duplicate photos. A scheduled event must first be linked to its
  customer `Concert` record.
- Media → Concert Photo Releases groups unreleased albums from one studio and
  publishes the whole group in one transaction. This supports separate junior
  and senior releases or a combined release across related concerts. Release
  requires at least one visible available photo in every album and records an
  audit entry. Customer galleries still hide every draft album.
- The release workspace now lives in the top-level Concert Tracker. Date and
  studio views show source-derived programme, crew, photo-upload and release
  state, while the existing release records and audit history remain the source
  of truth. The previous release URL redirects to the Tracker for compatibility.
- Gallery Settings now includes a reusable competition and concert
  watermark library. Staff can create a text watermark or upload PNG artwork,
  preview it, choose event-type defaults and archive old entries. Concert
  release groups and individual competition gallery settings can override the
  default. Existing watermark settings remain as safe fallbacks.
- The Watermarks tab now follows the compact Price Lists pattern. Competition
  and concert entries are separated into collapsed rows with inline thumbnails,
  default badges and direct-assignment counts. Expanded rows provide a larger
  preview and guarded default/archive actions. The creation form is collapsed
  until needed and switches between PNG and text fields. Legacy controls and
  the unused proof-watermark option are hidden from the working screen; built-in
  fallbacks remain available when a library default has not been created. PNG
  uploads may be up to 10 MB and 6000 × 6000 pixels; they are decoded once,
  stripped of unnecessary payload and proportionally resized to a maximum
  2000-pixel edge while preserving alpha transparency before storage.
- My Media links to a private order-history page showing each customer's pending,
  paid and fulfilled orders. No order is created by adding an item to a cart.
- Ready competition photo routines can now be bridged into the private order
  catalogue with stable collection and asset identities. The bridge rechecks
  gallery readiness and the original files; repeating it does not duplicate
  assets. It does not publish a checkout or charge a customer.
- Paid order items can record the exact photo or video assets released to that
  purchase. The photo clock starts at the later of payment and photo readiness
  and lasts one year; the video clock starts independently when its video is
  ready and lasts 60 days. Pending video has no expiry clock. Re-running
  activation preserves the original asset set and expiry. Order history shows
  the separate states and dates. Purchased albums use the frozen asset sets
  where present, while older orders retain their existing collection behavior.
  A delivery guard checks customer ownership, paid status, the frozen asset,
  readiness and expiry. Authenticated original requests now create a five-minute
  database-backed handoff through the existing tracked signing endpoint.
- Competition and verified-studio concert carts can now be prepared as fixed
  draft orders with exact ready asset UUIDs, storage references and allocated
  discounted prices. A routine package has separate photo and video items, so
  pending video has no placeholder file reference. Repeated preparation reuses
  an unchanged draft; changing that source cart cancels its older draft. Customers
  can now review the prepared order in the local test checkout.
- Drafts snapshot the exact customer cart selections. A confirmed Eloquent paid-order
  transition clears those selections from both cart and favourites, leaving other
  selections alone. Approved and declined local test outcomes are recorded idempotently.

## Verification and next step

Competition product, thumbnail and large-preview cart/favourite icons now save in the
background and update their state and cart count in place. The large preview remains
open and the page keeps its scroll position. Non-JavaScript form submissions retain
their redirect fallback.
The Favourites tab now shows its own count. Saved favourites appear in the gallery-sized
thumbnail grid with section, competitor, product, cart and heart icons. Clicking a photo
thumbnail opens its large preview without leaving Favourites; other thumbnails open their routine album.
Cart rows show a small clickable thumbnail and section/competitor details with a bin icon.
Individual cart photos preview without leaving the Cart page.
Both in-page photo previews have cart and heart controls that save without closing the preview.

Customer gallery and access tests cover registered/unauthorized visibility,
live readiness, preview security, improvisation and flat albums across orders.
The full Sail suite passes (635 tests, 5,634 assertions), and `git diff --check`
passes. Competition and concert carts now open a local-only test checkout. It
supports approved and declined outcomes, rejects stale carts, records attempts
idempotently, activates exact purchased entitlements and exposes authenticated,
tracked original downloads. No card details are collected and no money is charged.
Next replace the fake gateway with a Braintree sandbox adapter, then complete
production reconciliation and refunds before live charges are enabled.
Guest gallery access and the shared product, favourites and cart experience are
implemented. Live payment remains outstanding.

Administrators can now search customer accounts by name, email address or numeric
account ID and open one account record containing its current favourites, carts,
orders, complimentary grants, media emails and help requests. Admin competition
and concert galleries expose an Add to account control on individual photos and
routine/album folders. Competition routine grants may include the released video.
The same grant action is available beside media in a customer's current favourites
and cart. Complimentary grants create audited zero-value fulfilment records and
normal protected entitlements, do not enter sales reporting, and skip files the
customer already owns.

The account record is now a tabbed customer service workspace containing customer
details, dancers and studios, order and item-level refund records, event access,
emails and help messages, notification history, per-asset download history, credit
history and an administrative activity trail. Staff can send a recorded email, add
credit, grant competition or concert access and pause or cancel sign-in. Refunds
remain `pending_provider`: no money moves and no entitlement is removed until a
payment provider confirms the result. See
[ADR-0052](../decisions/ADR-0052-Customer-Service-Account-Workspace.md).

Customer intelligence tabs add duplicate detection and confirmed account merging,
contact-preference and consent history, login/device review, lifetime and average
order value, repeat-purchase and event conversion reporting, refund/support outcome
visibility, chargeback and payment-risk cases, internal notes/tags/follow-ups, JSON
privacy export, contact correction and confirmed anonymisation. Gallery views,
favourite/cart actions and purchases are recorded from this release forward; the
software does not invent historical funnel activity. Retention anonymisation remains
a deliberate staff action until a production retention policy is approved.

Guest Gallery Access now provides a public gallery index with Competition and
Concert tabs. Released events remain code-gated after email entry. An event is
listed from seven days before it starts with a Coming soon — register now banner;
an event-scoped email signup sends one queued notice when customer-visible media
first becomes available. The signed return link contains no email address, prefills
the email from encrypted registration data and still requires the gallery login
code. Successful access lasts 30 days and exposes only released watermarked media.
Guest favourites and cart selections persist and transfer into a matching new or
existing customer account together with the verified event/studio access. Guests
use the account gallery views, including competition photo multi-select, routine
albums, videos, packages, pricing, discounts, upsells, previews, favourites and
carts. Continuing to checkout hands a guest to account creation or sign-in, after
which the local test checkout and protected original delivery path is available.
# Media commerce settings

Media → Gallery Settings now uses separate page tabs for products and pricing, discounts and promotions, watermarks, notifications, and customer help so each area can expand independently. Competition and concert products have separate reusable price lists. Their compact rows show live gallery and album/routine assignment counts and expand to reveal the complete editing form; each section has a separate expandable creation form. Matching defaults apply automatically, with optional competition-event, concert-gallery, routine and media-album overrides. Each list also has a GST rate and a GST checkbox on every product. Customer quotes resolve the most specific active list while order drafts retain their captured prices and product tax treatment. Included GST is calculated after discounts, shown at checkout and retained for sales reporting. Bulk tiers, code creation and saved discount codes are also collapsed into expandable rows. The single code-value input switches between percentage and dollars; new codes default to today through 30 days from today, and saved rows show dates, redemption counts, per-account limits and status. The remaining settings control upsell notices, customer preview watermarks, help copy, and draft notification templates. Media → Sales shows paid customer-cart orders only. Percentage and fixed-dollar discount codes can be activated with dates, global-use and per-customer-account limits; checkout applies a code before testing bulk eligibility and records approved redemptions. Authorised studio principals receive $1 concert-photo pricing, which is excluded from bulk discounts. Media-ready and expiry emails remain pending live payment work. See [ADR-0037](../decisions/ADR-0037-Media-Commerce-Settings.md), [ADR-0043](../decisions/ADR-0043-Media-Discount-Code-Redemption.md), [ADR-0044](../decisions/ADR-0044-Studio-Principal-Photo-Pricing.md) and [ADR-0045](../decisions/ADR-0045-Reusable-Media-Price-Lists.md).

Customer account credit now has its own immutable transaction ledger. Staff can
find a customer by email or numeric account ID in Discounts & Promotions, view
the current balance and add a positive credit with a required reason. Customers
see the balance in My Media and carts and may apply it to a prepared order. It is
applied after all discounts without changing bulk eligibility; GST is calculated
on the remaining total. Credit is consumed idempotently only after an approved payment. Sales reports show it
separately from discounts. Refunds and loyalty remain separately planned features.
See [ADR-0046](../decisions/ADR-0046-Customer-Account-Credit-Ledger.md).

Customer media email delivery now records an idempotent delivery before queueing.
Approved checkout sends one order confirmation; newly released pending media sends
a ready notice when enabled; and a daily job sends expiry reminders ten days before
undownloaded media expires. The Gallery Settings notification tab has separate
collapsed editors, email previews, test delivery and recent queued/sent/failed
history. Next replace the fake payment gateway with Braintree sandbox, then add
provider reconciliation and refunds. See
[ADR-0047](../decisions/ADR-0047-Customer-Media-Notification-Delivery.md).

Customer Help now accepts general media questions and entitlement-specific download
reactivation requests. Expired items link directly to a preselected request form.
Management Hub has a status-filtered Customer Support queue where staff can resolve,
decline or approve a timed extension. Approval updates only the selected entitlement,
records its old and new expiry with the acting administrator and reason, and queues
an outcome email. Customers can review request status and the restored-through date
on their Help page. Opening an expired purchased-download link returns the customer
to that form with the correct order and entitlement already selected instead of
showing a generic expiry error. See
[ADR-0050](../decisions/ADR-0050-Customer-Download-Reactivation.md).

The customer Help Centre now groups searchable placeholder articles by category,
records zero-result searches and accepts one helpful/not-helpful response per account
and article. Gallery Settings shows unanswered searches and article feedback so the
final content can be based on real gaps. The introductory copy remains editable.
Article wording is explicitly provisional: complete the final article, screenshot,
policy and contextual troubleshooting review after customer and production workflows
are stable, rather than hard-coding unfinished processes now.

Released concert photo groups now act as customer-visible parent albums and may
contain routine albums from several concerts for the same studio. Routine albums
receive an automatic first-photo cover during sync; parent albums receive an
automatic uploader Top Pic. Staff can change both after release, and parent cover
choices remain restricted to Top Pics. Customer and guest galleries show the
parent hierarchy, album covers and cross-concert labels, while cart selections
retain the photo's actual concert. Public gallery cards use a dedicated watermarked
parent-cover thumbnail. Next review the complete web journey before beginning the
shared mobile clients; live payments remain last. See
[ADR-0048](../decisions/ADR-0048-Concert-Parent-Albums-and-Covers.md).

## Admin order detail

The media sales order list and customer workspace link to one dedicated order detail page. It preserves the information needed to reconcile a sale: customer and event source, snapshotted filenames and product names, quantities, discounts, account credit, included GST, amount collected, provider transaction attempts, refunds, download activity and order-email delivery. Digital delivery is shown explicitly; print-lab shipping fields are not presented unless physical products are introduced.

## Customer and sales workflow audit — 26 September 2026

Completed a read-only review of customer accounts, sales/reporting, order processing,
support, navigation and layout. Findings and proposed grouping are recorded in the
[workflow audit](../handbook/Customer-Sales-Workflow-Audit-2026-09-26.md).
No application fixes were made during this review. The existing Sail suite passed:
723 tests and 6,639 assertions. Browser review reproduced a studio visitor-filter
404 and identified navigation/layout gaps; code review found disconnected admin
email preferences, mixed-order reporting inconsistencies and incomplete privacy
handling. Recommended next step: fix those correctness gaps, add regression coverage,
then consolidate the customer, sales and reports navigation. Live payment integration
remains deferred as previously agreed.

## Audit implementation pass — 26 September 2026

The first implementation pass follows the audit's correctness-first sequence:

- Studio visitor filters use the studio route, while visitor favourites/cart links
  retain an event filter for individual events.
- Shared communication preferences govern customer and administrator changes;
  unchecked options revoke consent and ready notices deduplicate per entitlement.
- Mixed orders report their item-level event allocations, preserving event-specific
  discount totals. Sales overview and CSV distinguish cash, completed refunds and
  net receipts; complimentary grants do not inflate customer purchase metrics.
- Customer accounts use six primary page groups and contextual secondary tabs.
  Editing and account actions moved off Overview. Histories paginate instead of
  silently stopping at fixed limits, and only the selected account page loads its data.
- Orders & refunds has a canonical all-order list, source/status/search filters,
  refund history and unpaid payment-issue views. Refund and complimentary-access
  actions now live on the individual order page, with contextual return links.
- Sales & reports and Customers are separate navigation groups from Media. Dated
  customer notes have a Due now / Upcoming / Completed follow-up queue.
- Support can renew expired or revoked concert grants. Account merge handling
  retains dancer aliases and verification, moves additional account histories, and
  handles profile collisions. Privacy export covers more account records and
  anonymisation redacts the stored soft-deleted profile and related contact data.

Live payments and provider refund execution remain deferred. The audit is not
fully closed: merge preview, unified email delivery history, fine-grained staff
permissions, cash/credit refund completion, per-request download analytics,
large-data report aggregation, background ZIP preparation and retention workflow
still need separate implementation and validation. See the audit's implementation
status for the exact boundaries of this pass.

Verification: the final Sail suite passed **733 tests / 6,695 assertions**. Admin
route registration, scoped Pint and whitespace checks passed. Browser checks covered
the reorganised account/order/report pages and the corrected studio activity filter.
No migration or commit was created by this pass.

## Admin Top Pics workspace (26 September 2026)

- Media now has a private Top Pics gallery with a Competition/Concert segmented
  switch and one album per event that has verified uploader Top Pics.
- Both backend photo galleries link to an event's Top Pics through the same
  All photos / Top Pics navigation. Customer galleries and menus are unchanged.
- Albums show section/competitor or concert/item context, in-place large previews,
  previous/next controls, original download links and the shared translucent
  Add to account control. Concert grants become available after verified photos
  are synced into customer album records; unsynced photos remain previewable and
  downloadable in this admin workspace.
- Pages and original downloads require active admin permission. Selections use
  existing ProgrammePhotoFile flags, current gallery plans and storage keys;
  unverified and duplicate records are excluded. No extra originals are copied.
  Downloads record the actor and source photo in the upload audit.
- This workspace reads Top Pic flags already received by the backend. It does
  not add post-upload uploader metadata synchronisation or kiosk export tooling.

Verification: five focused feature tests passed (46 assertions), including
customer/crew denial, event filters, original bytes, download auditing, obsolete
plan exclusion and concert grants after sync. Browser review covered competition
and concert album lists plus competition large previews and navigation. The full suite ran 738 tests: 737 passed and the existing
`LocalDevelopmentSeederTest::test_seeder_creates_repeatable_linked_local_data`
failed because a repeat seed added 8 collections and 40 assets. An isolated
rerun of that test file passed (2 tests / 57 assertions), so the full-suite
seeder failure remains intermittent and is not claimed as fixed. Scoped Pint,
route registration and whitespace checks passed. No migrations or commits were
created.

### Duplicate demo Top Pics tidy-up (26 September 2026)

The PDC realistic demo contained 46 selected file records pointing to 35 storage
files. Eleven pairs had the same contribution, storage path, checksum and size.
The local-only `TidyDemoTopPics` action retains the earliest photo identity as the
selected Top Pic and clears the redundant selection flags. It keeps all photo
records and files so favourites, carts, entitlements and existing downloads retain
their targets; it does not hide reused sample images belonging to different items.
The realistic demo seeder updates the earliest matching record and runs this tidy
step to prevent repeated selections returning on reseed.

Applied locally: 11 flags cleared; PDC now has 35 Top Pics and zero repeated
selections within a routine. Prior selected identities are recorded privately in
`maintenance/top-pics-before-2026-09-26.json` on the local storage disk. Seven
focused tests passed (55 assertions), including idempotence, preservation of
records and separate routines, and refusal outside the local environment.

Final cleanup verification: full Sail suite passed **740 tests / 6,753 assertions**;
scoped Pint and whitespace checks passed. Browser confirmed 35 Top Pics for PDC.

### Publicity navigation (26 September 2026)

Renamed the admin Media menu entry to Publicity and added shared Top Pics and
TV Slideshows tabs on separate pages. Existing Top Pics links and its
Competition/Concert switch remain available. The new TV page clearly states
that playback and screen pairing are not connected yet.

TV will reuse the Top Pics event/photo catalogue rather than create separate
selections. Inspection confirmed that changing a star after upload currently
saves only to the uploader's local library; verified uploads do not receive a
metadata update. That sync remains required for both the backend gallery and
future TV updates. No uploader sync or TV playback was implemented in this change.

Browser review confirmed both pages and tab navigation. Route registration,
scoped Pint and whitespace checks passed. Feature coverage includes tab links,
event-type preservation and denial for guests, customers and non-admin crew.
Full Sail suite passed **741 tests / 6,764 assertions**.

### Post-upload Top Pics sync connected (26 September 2026)

The missing uploader metadata path is now implemented: automatic durable star and
unstar syncing, offline retries, restart recovery and pending/synced status. The
shared Publicity catalogue reads updated flags on page load; no photo bytes are
copied. See the Mac uploader milestone for scope and verification. All 744 Laravel
and 89 Swift tests passed. TV playback and pairing remain the next separate task.

### Orders page navigation layout (26 September 2026)

All orders, Refunds and Payment issues now use the same page-tab presentation as
Media sales, sharing one stylesheet partial. Sales reports is a separate button
above the tabs. Existing page URLs, filtering and payment/refund behaviour remain
unchanged. Browser review covered all three pages and the Media sales comparison;
scoped Pint and whitespace checks passed.

Orders navigation verification: full Sail suite passed **744 tests / 6,834 assertions**.

### Interface wording audit — 26 September 2026

- Audited navigation, headings, buttons and descriptions across admin, customer, studio and crew web templates. Canonical naming, changes and remaining content-review boundaries are recorded in `docs/handbook/Interface-Wording-Audit-2026-09-26.md`.
- Aligned sales/orders/customer headings with navigation; standardised administrative labels, filter actions, download links, programme spelling and access-code terminology.
- Corrected stale library/profile/crew-help copy and clarified that download history measures requests rather than confirmed saves.
- Added a guarded update for three original default Help articles covering media access and global carts, preserving customised content and existing slugs; rollback follows the same guard.
- Verification: the final complete Sail regression suite passed **746 tests / 6,843 assertions**. Scoped Pint and `git diff --check` passed. The Help migration ran successfully locally; dedicated tests verified guarded rollback and preservation of custom articles. Browser spot checks covered Orders & refunds (including Refunds), Sales reports and Customer performance, Customer accounts, Create download links, My Handbook and customer Help. No routes, permissions or financial calculations changed.
- Next: use the audit glossary for future UI work; review staff-customised Help and email content before launch, preserving the existing notices for unfinished integrations.

### Consistent Add to account placement — 26 September 2026

- Audited all six grant-button call sites across competition routines/photos,
  concert albums/photos, Top Pics and the shared customer favourites/cart view.
- Standardised one shared translucent circular control: 39 px, bottom-right,
  10 px inset. Removed corner and small-size variants. Anchors now surround only
  the thumbnail; competition folder navigation remains separate from granting.
- Recorded the component usage rule in the interface audit handbook.
- Browser checks confirmed competition folders/photos, concert albums/photos,
  Top Pics and customer favourites/cart placement. Grant dialogs opened and
  cancelled correctly; no grants were submitted. Scoped Sail Pint and
  `git diff --check` passed.
- Full `sail artisan test --compact`: 745 of 746 tests passed (6,843 assertions).
  The demo-seeder repeatability test reported 8 additional collections and 40
  assets on a second seed. Isolated rerun with
  `sail artisan test --compact --filter=LocalDevelopmentSeederTest` passed
  both tests (57 assertions), indicating an intermittent fixture issue.
  No seeder or grant business logic was changed for this placement task.

### Publicity TV pilot — 26 September 2026

TV Slideshows now has event settings, shared/event slides, scoped screen pairing,
a live browser preview and an initial native Fire OS player. This work is tracked
in [Publicity TV slideshows](Publicity-TV-Slideshows.md). The owner confirmed Fire
OS; hardware testing awaits the delivered stick. Customer galleries and media
access remain separate from TV pairing. Verification and remaining rollout work
are recorded in that milestone.

TV pilot verification: all **751 Laravel tests / 6,913 assertions** and four
offline browser tests passed. Fire OS debug APK compiled and linted; physical
stick installation and endurance testing remain outstanding.

### Studio Hub media login codes — 27 September 2026

- Photo gallery released shows the studio's photo gallery login code when available.
  Video released to dancers shows the applicable grouped or individual concert video
  login code after studio-owner video approval is recorded. Available codes appear
  as compact right-hand “Access Code:” badges beside the progress status; code text
  can be selected for copying. Long codes wrap within the badge on narrow screens.
- Existing Studio Hub management authorisation and private/no-store caching remain.
  Ordinary verified studio customers and revoked managers cannot view these codes.
  DR portrait events have no video-code row. Codes do not alter release status.
- Photo and grouped video codes now retain hidden encrypted display copies on save.
  Current hashes remain authoritative. Legacy hash-only codes have no badge and
  can be re-entered once by staff. This presentation change does not reset codes.
- Migration applied locally. Down removes only new encrypted display columns;
  hashes, grants and verification history remain. See ADR-0057.
- Next action: re-enter any known legacy code that needs to appear in Studio Hub;
  no code changes were made during implementation or visual verification.
- Verification: all 863 Laravel tests passed (8,353 assertions), including current
  code selection, group rotation/encryption, missing/legacy/stale-code handling,
  ordinary-customer denial and revoked-manager denial. Scoped Pint and whitespace
  checks passed. Desktop (1440px) and mobile (390px) browser checks passed using
  temporary demonstration codes; all preview database changes were rolled back.

Badge/approval follow-up verification: full Laravel suite passed (865 tests,
8,377 assertions); after the approval requirement was added during that run,
`BookingDeliveryConnectionsTest` was rerun against the final code and passed.
The checks cover hidden unapproved individual/group codes, approval revealing
only the applicable concert code, and withdrawal hiding it again. Scoped Pint,
whitespace checks, and desktop/mobile browser layout checks passed. Preview-only
demonstration codes and approvals were rolled back; no live values were changed.
