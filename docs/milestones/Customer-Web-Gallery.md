# Customer web gallery

## Completed locally

- Booking drafts/acknowledgements verification (29 September 2026): full Sail suite passed 1,043 tests (10,876 assertions); desktop/mobile browser checks covered pending cards, collapsed estimates, matching buttons, receipt branding and saving incomplete drafts. Migrations and protected route checks passed. Next: rehearse a real studio draft → submission → approval using the local test inbox.

- Studio Hub booking drafts now save incomplete forms without creating bookings or sending email. Continue booking resumes the shared studio draft, with version checks against stale saves. Submission retains the original receipt and clears only the submitted draft version.
- Submitted bookings receive a branded pending-approval email and a Studio messages notification; unapproved items appear as pending event cards. Repeated submissions do not duplicate successful acknowledgement emails. My Messages links authorised studio accounts directly to Studio messages. Quote estimates & minimum spends starts collapsed and shares Studio Hub booking buttons.

- Studio booking forms now search venues dynamically by name/address, with Other venue beside the field and stronger separators between events. Studio Hub has My booking (latest saved submission, with previous-form history) and My timeline links. Both use the studio logo heading and return to My Events; the timeline no longer duplicates final-family entry or the invoice shortcut.

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

## My Media page tabs — 28 September 2026

- Added the agreed My Events, My Purchases and My Orders page tabs using the same
  shared sticky tab style and scroll retention as Studio Hub and My Profile.
- My Events keeps event galleries, recommendations and Add media. My Purchases
  isolates purchased media and retains the Photos/Videos filter. My Orders uses
  the existing private, paginated history and links paid orders to their receipt.
- Shared Favourites/Cart actions and counts remain available on all three tabs.
  Purchased galleries return to My Purchases. Fixed the existing receipt back
  route to the named order-history endpoint. No entitlement/payment rules changed.
- Removed the redundant My Media return link from Help. The shared scroll script
  uses the page CSP nonce so it also works when script restrictions are enforced.
- Focused customer media/profile/help verification passed 20 tests (218 assertions).
  Browser checks passed for desktop/phone tabs, keyboard Photos/Videos switching,
  correct content isolation, scroll retention and removal of the Help link.
  Final full-suite verification follows below.

Final verification: full `sail artisan test` passed 900 tests (9,071 assertions).
`sail pint --dirty --test` and `git diff --check` passed. All three tabs and scroll
retention passed rendered Chrome checks at 1440px and 390px, without JavaScript
errors or horizontal overflow. Final screenshots were reviewed; order-description
contrast was corrected. No routes or migrations changed. Next: user review of the
three-tab navigation, then continue the existing customer delivery rehearsal.

## My Messages — 28 September 2026

- Added Notifications, Conversations and New message, using the shared account
  navigation, heading, tabs and scroll retention. My Messages sits between My
  Media and My Profile; unread badges combine notices and conversations.
- Existing automated delivery records project to one inbox entry per source.
  Delivery updates preserve read state. Personal account emails become support
  conversations; new replies reopen the existing admin Pending queue. Studio
  booking/production correspondence remains in Studio Hub.
- Added immutable thread messages, customer/admin replies, optional owned
  event/order context and private authenticated attachment downloads. Existing
  reactivation decisions remain audited and now appear in conversation history.
- Help keeps its configurable guidance and FAQ. Contact us opens New message;
  old download-reactivation links retain their context. Customer support and
  customer account history link directly to the same conversation page.
- Migration applied locally; rollback SQL reviewed without removing data.
  `customers:sync-inbox` backfilled existing correspondence without sending mail.
  Repeated backfills are idempotent and do not restore anonymised messages.
  Merges, privacy exports and redaction cover inbox messages and attachments.
- Focused verification passed 26 tests / 322 assertions, covering ownership,
  attachments, source deduplication, same-second replies, unread state, merges,
  privacy, navigation and the Help settings regression.
- Vodien/cPanel provider and server settings are confirmed and recorded in
  [Email mailbox routing](../handbook/Email-Mailbox-Routing.md), preserving the
  existing info/admin/orders/notifications role plan. **External email-app reply
  ingestion remains unimplemented and unconnected.** Secure credential handling,
  the IMAP adapter and an authorised live round-trip test are remaining work.
  The repository-required credential skill was unavailable; passwords were not
  accessed and no mailboxes, DNS or transports were changed.
- See [ADR-0058](../decisions/ADR-0058-Customer-Messages-Inbox.md) and
  [My Messages handbook](../handbook/Customer-Messages.md).

Final verification: full `sail artisan test --compact` passed **910 tests /
9,251 assertions**. Scoped `sail pint --test`, full `sail artisan route:list`
and `git diff --check` passed. Rendered Chrome checks at 1440px and 390px passed
all three tabs and the conversation page, scroll retention, absence of horizontal
page overflow and JavaScript errors. Screenshots reviewed; temporary layout
records were rolled back. Existing unrelated working-tree changes were preserved;
no commit or external email send was performed.

## Customer account presentation refinements — 28 September 2026

- New message shows Subject and Message, with Topic, Event, Order, Expired media
  and Attachment in a collapsed Advanced options section above them. Optional
  field validation errors automatically open the section; ordinary enquiries
  submit with the existing general topic without opening it.
- Preference checkboxes now use the delivery service's defaults and honour saved
  opt-outs, including legacy download-email choices. News/offers is preselected
  only without a saved choice; viewing the form never creates marketing consent.
  Saving records each first choice (including opting out) in consent history.
  Updated the obsolete description to explain unticking and saving preferences.
- Copied the exact supplied inverse landscape logo into a dedicated customer
  header asset. The logo retains its transparency and proportions, replacing
  the roundel plus separately typeset DancePro text.
- My Media, My Messages, My Profile, Help and Studio Hub now share one heading
  partial for the blue eyebrow, heading and description. Studio Hub uses the
  same typography and spacing, retains its studio image, and places its three
  notification boxes below the identity to leave room for long studio names.
- No routes, database migrations, email transports or mailbox connections changed.
  Existing unrelated work remains untouched. Next: user review of these account
  screens; Vodien integration remains the separate open item described above.

Verification: full `sail artisan test --compact` passed **911 tests / 9,262
assertions**. Scoped Pint and `git diff --check` passed. Chrome checks across all
five pages at 1440px, 900px and 390px confirmed matching heading styles, correctly
loaded/proportioned logos, retained Studio Hub boxes, no horizontal page overflow,
keyboard expansion/collapse, hidden optional fields, valid simple-message forms
and checked initial preferences. Reviewed desktop/mobile screenshots. Logo asset
SHA-256 matches the supplied original. All temporary preview data was rolled back.

## Shared web accounts and school classification — 28 September 2026

- Media and Crew Hub now have reciprocal My Hub / Back to Media links; Studio Hub and Admin links remain available according to each account's permissions.
- Active crew/staff accounts can use personal Media with existing content access checks. Non-admin Media access requires verified email. Existing customer accounts can be enabled for My Hub by an admin without changing credentials, purchases, customer type or studio permissions, and without sending mail.
- Crew onboarding applies to linked customer accounts too. Customer-only merge/anonymisation tools prevent disconnecting crew or Admin records.
- Studio create/edit supports a persisted School classification and directory badge. Existing studios default to Dance studio. Pricing is unchanged until school discount rules are agreed.
- Verification: `./vendor/bin/sail artisan test --compact` passed all 919 tests / 9,332 assertions. Scoped Sail Pint and `git diff --check` passed. School migration applied locally; rollback removes only the new classification column (reviewed). Chrome checks passed for four pages at 1440, 900 and 390 pixels, including navigation visibility, wrapping and form interaction, with no JavaScript errors. Mobile app navigation is unchanged.
- Next: agree school discount rates and their applicability to minimum spends/video quotes before adding automatic pricing.

## Admin shortcut to personal media — 28 September 2026

- Added My Media directly beneath My Hub in the Admin sidebar, using the existing customer-access check and personal media route. Accounts without a crew profile can still open My Media.
- Desktop and mobile browser checks confirm the link position and destination; formatting and whitespace checks pass. No routes or permissions changed.
- Full verification: `./vendor/bin/sail artisan test --compact` passed all 919 tests / 9,332 assertions. No remaining work for this navigation change.

## Admin shortcut icons — 28 September 2026

- Added the supplied Crew Hub and Media icons beside My Hub and My Media, with matching 26px proportions and decorative image markup. My Hub retains its notification badge on the right.
- Chrome checks at 1440px and 390px confirm both assets load, labels align, and existing link destinations remain intact. Reviewed both screenshots; asset hashes match the supplied files. Sail Pint and whitespace checks passed.
- Full `sail artisan test --compact` ran 919 tests: 918 passed; one navigation assertion expected the old text-only My Hub markup. Updated that assertion for both icon links and reran the entire AdminNavigationTest file: all 8 tests / 131 assertions passed. No application changes were needed after the full run. No remaining work for the icon change.

## Shared web sign-in — 28 September 2026

- Replaced the separate Admin sign-in design with the customer-led shared page at `/login`, including account-wide wording. The subsequent heading update below replaces the initial small sign-in icon. Removed duplicate sign-in templates.
- Public navigation now has one Sign in link. Updated gallery joins, invitations, verification, registration, logout and password-reset return links. Old customer sign-in links and form submissions remain compatible.
- Preserved existing role destinations, intended pages, access checks, two-factor authentication and mobile API contracts.
- Browser checks passed at 1440px and 390px: legacy redirect, icon loading/position, account wording, form error feedback, no horizontal overflow or JavaScript errors. Reviewed desktop/mobile screenshots. All 6 focused shared-sign-in tests passed; Sail Pint and route registration (656 routes) passed. Full regression suite passed: 925 tests / 9,383 assertions.

## Shared Media heading image — 28 September 2026

- Added the supplied `DPM copy.png` unchanged as the default image in the shared heading, with its original proportions. My Media, My Messages, My Profile and Help use it throughout their tabs.
- Sign-in and sign-up now use the same full-width hero, blue account label, heading and description. Studio Hub explicitly retains its own studio logo and three summary boxes. Admin shortcut icons are unchanged.
- Browser checks passed for all seven pages at 1440, 900, 390 and 320 pixels: matching heading styles, correct image proportions, preserved Studio Hub elements, no horizontal page overflow or JavaScript errors. Desktop/mobile screenshots reviewed. Final sign-in/signup regression checks passed: 12 tests / 120 assertions. Sail Pint and whitespace checks passed; the image hash matches the supplied original. No remaining work for this heading update.

## Gallery headings and browsing — 28 September 2026

- Applied the shared Media heading layout to gallery browsing, studio directories/details, competition gallery tabs and carts, concert galleries/carts, and gallery access/video pages. Browse pages use the supplied DancePro Media image; competition pages prefer the event logo, and concert pages use the studio logo, with Media branding as fallback.
- Released gallery and studio/concert cards now open from their image or empty space as well as their existing link. Upcoming-event notification forms remain independently usable.
- Extended the existing tab scroll preservation to the Competitions/Concerts gallery switcher, retaining ordinary link navigation and keyboard/modifier behavior.
- Full Sail test suite passed: 925 tests / 9,383 assertions. Browser checks passed for 15 rendered pages at 1440px and 390px: shared headings, loaded heading logos, no horizontal overflow, both scroll-switch directions, and whole-card destinations. Reviewed desktop/mobile screenshots. Sail Pint and whitespace checks passed. No remaining work for this update.

## Media navigation icons — 28 September 2026

- Matched Admin Hub to the existing highlighted My Hub button, using a distinct shield/check icon. Added coordinated media, envelope, profile and help icons to the remaining customer navigation links using shared SVG styling.
- Browser checks passed at 1440, 1100, 900 and 390 pixels: matching hub button styles, distinct icons, correct Admin destination and no page overflow. Reviewed the desktop navigation screenshot. Existing access conditions, labels and unread-message badges are preserved.
- Signed-out navigation now has icons, Media galleries first and highlighted Sign in last. Removed the duplicate Studios & concerts header link. Browser checks passed for both account states at all four viewport widths.

## Catalogue cards and registration dialogs — 28 September 2026

- Unified competition and concert card grid sizing. Moved upcoming-event email and notification-consent fields out of the cards into Register now dialogs with event/studio branding and a Media-image fallback.
- Retained existing registration endpoints and consent rules. Dialogs support keyboard focus containment, Escape, close/cancel, backdrop dismissal and reopening with prior input after validation errors.
- Verification: all 925 Sail tests passed (9,384 assertions); Sail Pint and whitespace checks passed. Browser checks passed at 1440px and 390px for both event types: matching grids, hidden inline fields, logo display, email/consent validation, correct POST destination and payload, Escape/cancel/close with focus restoration, and validation-error recovery with saved input. Reviewed the mobile dialog screenshot. No remaining work for these navigation and registration changes.

## Studio-first galleries and ongoing registration — 28 September 2026

- Concert browse cards now group by studio; opening a studio shows its public event cards and a single ongoing registration dialog. Competition cards omit the repeated subtitle.
- Registration pop-ups place the title/date to the right of the logo on desktop and mobile. Extracted the shared dialog markup for event and studio registration.
- Added encrypted studio subscriptions, explicit ongoing consent, per-event notification deduplication, signed studio-wide unsubscribe, queue-time unsubscribe checks, and account export/redaction/merge support. Existing one-event registrations retain their original scope. The existing every-minute notification job handles future releases, including events created after subscription.
- Migration applied locally; rollback order reviewed. Browser checks passed at 1440px, 390px and 320px for unique studio cards, nested event pages, simplified competition titles, side-by-side modal branding and no overflow. Reviewed desktop catalogue/mobile dialog screenshots. All six new subscription tests passed in the focused run; one older test expected event titles at the top level and was updated to follow the studio page instead.
- Final verification: full Sail suite passed all 931 tests / 9,443 assertions; final subscription checks passed all 6 tests / 58 assertions after ensuring unsubscribed queued notices stay cancelled on resubscription. All 659 routes registered, including the three studio routes. Sail Pint and whitespace checks passed. No remaining work for this feature; automatic delivery uses the existing scheduler/queue and local test inbox settings.

## Portfolio gallery type — started, deferred (28 September 2026)

- Reserved Portfolio as the third catalogue type and made the gallery switcher use the available catalogue types. Portfolio currently returns no cards and is omitted from navigation; direct selection falls back to Competitions.
- Deliberately stopped at the placeholder per owner instruction. Remaining: define content/access and management workflow, implement Portfolio card/detail and publishing support, and reveal the tab once there is published content. No schema or full process added. Next action: resume Portfolio only when requested; this feature is **started, not complete**.
- Placeholder verification: live desktop/mobile checks confirm Portfolio is hidden, direct Portfolio selection falls back safely, and both existing types work. Full Sail suite passed: 931 tests / 9,444 assertions. Sail Pint and whitespace checks passed. Placeholder work is complete; the Portfolio feature remains deferred.

## Admin Message Centre (28 September 2026)

- Consolidated admin customer support/conversations and studio communication pages into `/admin/messages`, with customer/studio/crew selectors, search, private attachments, formatted messages, replies and history. Existing old links redirect; support decisions and studio ownership remain in the centre. Existing customer and crew views share the same records.
- Added five navy header shortcuts in the agreed order; removed the redundant staff name. Sidebar now has My Hub in Hub management and My Media/Concert tracker in Media management. Legacy standalone message navigation entries removed.
- Role/participant access, notification counts, recipient validation, formatting sanitisation, private attachments and compatibility redirects are covered by feature checks. Verification results recorded below after completion.
- Follow-up: standardised public account identifiers on UUIDs, matching studio/conversation identifiers, with an additive backfill and compatible old numeric account links. Order display numbers remain numeric and unchanged. Directory and recipient searches disambiguate order numbers from account UUIDs; regression coverage includes an internal account number colliding with an order display number.
- Verification: focused final checks passed 46 tests / 716 assertions. Desktop (1440px) and mobile (390px) browser checks passed across customer inbox/thread, studio history, crew inbox and new-message pages, including all five shortcuts, no horizontal overflow and working formatting/preview. Sail registered 662 routes. Pint and whitespace checks passed. Local UUID migration ran successfully; rollback only removes the added identifier column/index, preserving all relationship IDs. Temporary UI fixtures were rolled back and email tests used fakes; no live email was sent.
- Final regression verification: all 937 Sail tests passed (9,532 assertions). Updated three older assertions to check the new UUID display and distinguish page-specific navigation/pagination from the global shortcuts; their 19-test focused run also passed. Final Sail Pint and whitespace checks passed. Implementation is complete; changes remain uncommitted.

## Navigation consistency and Message Centre email tabs — 28 September 2026

- Shared landscape app-switch component uses supplied Media, Crew and new Admin icons with consistent My Media/My Hub/Admin Hub labels. Square Admin shortcuts retained; Admin sidebar branding/background updated and notification badges moved left.
- Message Centre now defaults to All, with Customers/Studios/Crew tabs. Debounced live search accepts partial entries in inbox and recipient search, cancels stale responses and preserves composer drafts. Search remains available without JavaScript.
- Crew now contains tagged email conversations using existing account email delivery, history, private attachments and replies. Crew Hub chat is linked separately and its records/counts are excluded from Message Centre. Existing customer/studio correspondence remains accessible.
- Event Availability filters, legends and bulk actions are grouped in a collapsed Advanced options disclosure; the sheet and Full screen remain accessible.
- Focused verification: 36 tests / 769 assertions passed. Desktop/mobile browser checks passed for matching app-switch styling, loaded icons, All-first tabs, live partial-entry results with draft retention, collapsed/expandable availability controls and no horizontal overflow. Full regression passed all 937 tests / 9,548 assertions; final Sail Pint and whitespace checks passed. Sampled the Admin icon background and matched the menu to #021b44. Final desktop/mobile checks passed again after the colour and search-field adjustments. No live email sent. This follow-up is complete; changes remain uncommitted.

## Compact navigation and Crew biographies — 28 September 2026

- Reduced shared landscape app-switch buttons to a 36px minimum height with 24px icons. Restored the original Admin menu colour (#101820), retaining the supplied Admin icon.
- Kept the landscape DancePro logo in the Crew menu and added the supplied outline Crew Hub artwork to page headings, including nested task/detail pages. Achievement badge artwork is unchanged.
- Added a persisted Biography section after contact details in My Profile, with closed-by-default guidance containing all eight questions and no external examples link. Biography saves with the profile, accepts up to 5,000 characters and remains outside initial onboarding requirements.
- Added a deadline of four weeks after the existing commencement date. Saved biographies show complete in green; unfinished biographies turn red the day after the deadline. Missing start dates show an explanatory prompt. Changes to the start date automatically recalculate the deadline. Local inspection found all nine crew profiles already have a commencement date.
- Applied the additive biography migration locally. Its rollback removes only the new biography column. Requests and the profile action explicitly allow the field; omitted values from existing clients preserve saved biography text.
- Inspected the current repository's concert delivery: HLS/progressive playback, protected downloads, access-code/account grants, director corrections and release, and six-month streaming/twelve-month download periods from code-group release. Focused checks passed 37 tests / 345 assertions across Crew profile and concert delivery/review/code-group suites. This verifies the local implementation, not live AWS playback or the old Laravel backend.
- Desktop/mobile checks passed at 1440px and 390px for button sizing, unchanged menu logo, outline heading icon, collapsed guidance, all eight prompts, restored Admin menu colour and no overflow. Sail Pint and whitespace checks passed.
- Final verification: all 938 Sail tests passed (9,571 assertions). Focused deadline/training checks passed 14 tests / 166 assertions, including the due day, next-day overdue boundary, saved completion, changed commencement date and missing date. The earlier full run overlapped the new deadline edit and used a previously loaded model; the clean final run supersedes that result. Migration, documentation and browser review are complete. Changes remain uncommitted.


### Concert video delivery and multiple downloads — 28 September 2026

- Connected legacy concert playback/downloads to each asset's recorded disk and
  full key using private signed URLs. Existing files are not moved, renamed or
  copied, and legacy objects are not routed through the new bucket's CloudFront
  origin. Progressive playback does not send CloudFront cookies to S3.
- Kept the existing Start, Pause/Resume and Reset multiple-file manager instead of
  adding a concert ZIP. It now works without a streaming player during months
  seven to twelve and prevents duplicate Start timers. Its status describes
  browser requests rather than falsely claiming completed file transfers.
- Group codes now work on the direct concert unlock page. Guest/signed-link access
  observes the group's six-month streaming and twelve-month download windows.
- The uploader's Finished Uploading action publishes hidden assets in natural
  filename order. Existing readiness still requires verified files, editing,
  quality checks and approved studio video/slideshow inclusions before review.
- Local end-to-end coverage transfers and verifies an upload, finishes the batch,
  checks readiness, captures the approval email, previews as studio owner,
  releases, adds the customer code, streams and downloads the original. Separate
  checks cover exact legacy keys, signed delivery, visibility and concert/access
  boundaries. No live email or S3 operation was used.
- Remaining deployment work: verify live bucket/disk configuration and map/import
  the live catalogue through the existing legacy reference workflow. Local data
  has no legacy collections/assets to establish that mapping. Current uploader
  limit remains 5 GiB per MP4. Existing live media has not been altered.
- Next action: rehearse one representative real concert after verifying that
  mapping and the production delivery configuration.
- Workflow guide: [Concert video delivery](../handbook/Concert-Video-Delivery.md).
- Final verification: full Sail suite passed 942 tests / 9,617 assertions;
  focused concert suites passed 36 tests / 371 assertions; uploader Swift suite
  passed 93 tests. Isolated Chrome download-manager regression passed, frontend
  build passed, all 648 application routes registered, Pint and whitespace checks
  passed. No migrations were needed for this delivery change.
- Follow-up inspection: concert access-code attempts and account grants exist,
  but per-video watch progress and concert download-request history are not yet
  connected. Do not describe browser requests as confirmed saved copies. Optional
  app local saving is in the product plan; a native Photos/Gallery export decision
  was not found in the recorded specification.

## Concert family invitations (28 September 2026)

Implemented customer **Family sharing** from the concert page, a guest email-code
verification and video page, customer invitation/activity management and
studio/admin policy controls. Three distinct recipients per customer/concert;
fourteen days to accept, thirty days after acceptance, bounded by the original
concert access. One intended saved copy with three download requests per video;
locked/idempotent counters survive renewal. Existing recorded storage keys are
used without moving live media. The complete rules are visible before acceptance
and beside the videos. Contact-data cleanup and account privacy export/redaction
are included. See the family-sharing ADR and concert delivery handbook.

Local migration applied; rollback reviewed (only these new records/settings).
Focused feature checks passed: 12 tests, 153 assertions. Desktop/mobile browser
retry checks passed, and invitation creation, verification and video views were
visually checked at 1440px and 390px without horizontal overflow. Select-all works.
Production build and 676-route listing passed. The complete Sail suite passed:
954 tests and 9,770 assertions. Pint and git diff --check passed.

Remaining rollout work: confirm the existing live media mapping/delivery and
outbound verification-email transport, deploy the migration/build and ensure the
scheduler runs. No real family invitations or external verification emails were
sent during testing. Broader family cart/payment permissions and native-app
sharing screens remain separate work. Next: rehearse one released concert with a
customer and a family recipient using the local test inbox.

### Website Management and public pages (28 September 2026)

Implemented a structured website editor under Website Management, after Media
management. Home, About and Services support draft/published snapshots, optional
public images, editable section cards, visibility, links and ordering. Saved-draft
previews require admin access and use private/no-store responses. Public and
customer Help share the existing article library with draft/publish/hide controls.

Upcoming scheduling events require explicit website selection and exclude past
or cancelled events. Crew biographies use separate approved public snapshots;
changed submissions raise review badges and stale forms cannot approve a newer
unseen submission. Private profile photos and operational event details are not
published. Studio, Crew and Admin Hub links occupy a second navigation row.

Reviewed the existing live website's structure, services, booking information and
FAQ; starter content uses the current account flow rather than copying outdated
rates or delivery instructions. The live site and existing media storage were not
changed. See the Website Management handbook and publishing ADR.

Local migration applied and down/rollback reviewed. Eight focused feature tests
passed (92 assertions). Fourteen desktop/mobile rendered-page checks passed,
including editor add/remove/reorder controls; screenshots reviewed with no
horizontal overflow. Production build, 695-route listing and Pint passed.
The full Sail suite ran 962 tests: 960 passed and two legacy expectations failed
(the old studio-list homepage and the former Help search result count). Those
expectations were updated for the requested changes; all seven tests in their two
affected test classes then passed (44 assertions). No application changes were
needed after that full run. Git diff --check passed.

Remaining: owner content/image review, selecting public events and approving the
first crew biographies before production rollout. Portfolio galleries, testimonial
management and detailed promotional booking flows remain separate follow-ups.

### Full website content and automatic upcoming events (28 September 2026)

Expanded the live Home, About, Services, booking, competition, portrait, portfolio,
promo, studio-shoot, shop and FAQ information into editable local pages. Current
minimum spends, family pricing, preparation, studio approval and account delivery
replace obsolete rates, forms and transfer-email instructions. Expired mini-shoot
dates are omitted. Media terms preserve consumer rights; privacy covers current
account/support/access activity. No live website changes or media moves occurred.

Events now automatically cover today through six calendar months ahead, including
ongoing multi-day competitions. Past/cancelled events are excluded, explicit manual
hides persist, competition cards show logo/date range/venue, and concert studios
appear once each as logo tiles. Empty concert sections disappear. This supersedes
the opt-in event selection described above. About has three editable, labelled
placeholder biographies; no fake crew accounts were created.

Both local migrations applied successfully. Refresh/rollback preserve customised
page drafts and hidden content; the event rollback removes only the new override.
Five focused refresh tests passed (64 assertions), including date boundaries,
manual hiding, studio deduplication, content and rollback behaviour. Thirty-four
desktop/mobile rendering checks passed, including page-editor controls. Some local
demo scheduling records lack logos; the public cards identify missing artwork
instead of substituting another event's logo. Owner content and artwork review
remain before production rollout.

Final verification: the complete Sail suite passed all 967 tests (9,943 assertions).
The production build, 696-route listing, Pint and git diff --check passed.

### Public website photographic design (28 September 2026)

Reviewed 244 candidate photographs from the supplied Top Pics folders. Selected
thirteen concert/competition images and six existing website portfolio, headshot
and studio-promotion images. Nineteen images have responsive 640/1280/2200px WebP
derivatives with metadata removed; originals remain untouched. Source provenance
and accessible descriptions are recorded in the website photography manifest.

Home, About, Services, detail pages and public Help have photographic headings
without the Media app image. Added staggered photographic stories, text overlays,
a gallery feature, paired About imagery, a cyan closing section, section anchors
on long pages and responsive event/logo grids. Existing published text and editor
controls remain intact; heading image uploads override curated defaults. Account
and gallery headings retain the Media image. Existing navigation icons are kept.

Scroll reveals and optional page/hover transitions respect reduced motion and
keyboard focus. HTML remains visible without JavaScript. No live publishing,
customer-gallery storage changes or database migrations were needed.

Owner feedback prompted a second photo pass: replaced the homepage leap, expanded
the library to 31 images, removed within-page reuse and changed fixed-cover crops
to natural proportions. Dance poses stay clear of text; mobile uses separate
image and copy space. About image pairs and service cards retain complete source
framing. The full Sail suite passed 967 tests (9,943 assertions); affected website
tests are being rechecked after these visual refinements.

The affected website checks passed again after the photo corrections: 20 tests,
200 assertions. The production build, Pint and git diff --check passed. Public
pages were checked for missing images, layout overflow, Media-heading leakage,
and repeated photos within a page, with desktop/mobile screenshots reviewed.

The owner selected a concert-led direction for the homepage. Its final heading
uses the blue-lit concert ensemble from the 2025 Top Pics collection; that image
is removed from the homepage's supporting stories to avoid repetition.

Final visual verification passed all 28 public-page/viewport checks at 1440px and
390px, including loaded images, no horizontal overflow, no repeated photographs
within each page, and no Media heading image on public pages. Reduced-motion and
no-JavaScript checks passed. Reviewed the concert homepage, About and Services
screenshots after the final framing changes. The live site remains unchanged.

The owner subsequently supplied a preferred homepage photograph
(`696312_8bb0645dc1134ac280f7e31555d5c33a~mv2.avif`). This now replaces the
ensemble hero. Three responsive WebP derivatives preserve the original file;
the desktop headline uses the photograph's empty left side, while mobile frames
the full performer above the copy. Checked the actual homepage at 320, 390, 768,
1440 and 1920px with no horizontal overflow or missing hero image. The production
build and targeted Pint check passed.
The chosen-image update passed the full Sail suite: 967 tests and 9,943 assertions.
The homepage also remains visible without JavaScript; git diff --check passed.

### Account-focused homepage and relevant photography

The homepage now leads with Sign in / Create an account for guests and Open My
Media / Add a studio or event for signed-in Media users. Gallery browsing stays
available as a smaller link. Three editable steps replace the six large stories;
separate concert and competition photographic panels, upcoming events and a
smaller organiser section keep the homepage visually rich. The chosen hero is
retained. The additive content migration preserves custom fields, images and hidden
pages and supports rollback without deleting media.

Added nine distinct photographs from the existing DancePro shoot pages. Service
images follow the service destination rather than section position; detail pages
use individual image assignments. Concert content uses concert photographs and
competition content uses competition photographs, with no default photography
repeated across the public website.

Browser verification passed all 28 public-page/viewport checks: no repeated
photographs across the 14 public pages (29 distinct photographs displayed), no
horizontal overflow or missing images, and correct source collections on concert
and competition detail pages. The homepage retains four large photographs plus
event/studio logos and measures 4,783px on mobile versus the previous 7,180px.
Customer/admin previews passed at 1440, 390 and 320px, including no-JavaScript
rendering and the guest Create an account link. The three focused feature tests
passed with 33 assertions, including migration rollback and preservation checks.
Production build, targeted Pint and git diff --check passed.
Final regression verification: all 970 Sail tests passed (9,976 assertions). The
local migration completed successfully; no routes changed and no live site was
published. Existing unrelated worktree changes were preserved.

### About cleanup and legacy Photo/Video content review

Removed “A connected DancePro account” from the local About draft and published
content and the starter content resource. Other sections, photos, drafts and page
visibility remain intact. A local content backup was retained before the edit.
Reviewed the existing public /photo and /video pages for a proposed content mapping:
service benefits belong under Services, purchasing details alongside galleries,
and account access/download/playback guidance under Help. Legacy delivery promises,
pricing and links need reconciliation with the new account system. The review has
not added new public pages or changed the live website.
The About cleanup passed desktop/mobile browser checks and the complete Sail test
suite (970 tests, 9,976 assertions). git diff --check passed; no routes or styles
changed in this content-only update.

### Shared website and Media page widths — 29 September 2026

Applied the website's 1,320px maximum content width to the shared public/Media
layout. Headings, navigation, tab strips, galleries and footers now align with
40px desktop side margins, 20px below 1,001px and 18px below 701px. Removed duplicate
website container rules and narrower outer wrappers on studio invoices, booking
forms/timelines and director video review. Individual form fields and dialogs
retain their appropriate compact layout within the shared page.

Browser checks passed 128 page/viewport combinations across eight widths from
320px to 1,920px, using live public pages and real account views with fictional
data. Container edges match and no horizontal overflow was found. Production
build, targeted Pint and git diff checks passed. The full Sail regression suite
passed: 977 tests and 10,161 assertions. No routes, records or production media changed.
Next: owner visual review; broader customer rollout requirements remain open.

### Studio Hub tab wording — 29 September 2026

Renamed the shared Studio Hub tabs to Concert events, Studio messages and Event
invoices. The corresponding section headings and browser titles match those
labels; the studio identity remains in the shared hero. All three sections reuse
the same navigation partial; destinations and selected-tab behaviour are unchanged.
Next: owner wording review.
Concert event cards also show the booking's start/finish times between the date
and venue. A missing time is explicitly shown as to be confirmed; a known finish
without a start is labelled as a finish time. The existing hub test checks that
the date, 12-hour times and venue appear in the intended order.

DancePro progress now explains each milestone rather than showing a generic
DancePro label. Booking and gallery descriptions reflect confirmation/release;
crew descriptions explain assignment and retain published crew names. Video
release wording distinguishes not ready, ready for director review, corrections
being prepared and released. Readiness is taken from the existing review service,
independent of whether the current studio contact has director permissions.

Verification: all three tab states render the new labels; targeted Pint and
`git diff --check` passed. The full Sail suite passed after the label/time changes
(977 tests, 10,162 assertions). Following the progress-description update, all 63
tracker, director-workflow and invoice tests passed (960 assertions), covering
not-ready, review-ready, corrections and released descriptions. No routes changed.

### Visible Studio Hub actions — 29 September 2026

Collapsed Concert events cards now show red overdue counts, the overdue task
names, other outstanding action counts and a completed state. Cards with overdue
actions have a red border. The shared summary highlights its overdue total; its
existing action link opens incomplete event cards. Counts come only from the
studio checklist, excluding unreleased video review and DancePro production work,
and clear as studio requirements are completed. Events now sort by date ascending
with a stable secondary ID order, so the list is earliest first.

Next: owner review of Aurora's event summaries; regression results follow below.
Verification: Aurora preview passed 1,440px, 390px and 320px browser checks for
collapsed badges, chronological ordering, opening cards and no horizontal overflow.
Preview access was rolled back and messages were not marked read. The full Sail
suite ran 978 tests; two test assumptions needed updates for the extra CSS class
and seating every booked role. After those corrections, all 64 tracker, director
workflow and invoice tests passed (979 assertions), including count reduction,
completed badges and opening incomplete cards. Pint and git diff checks passed.

### Branded account verification email — 29 September 2026

Email verification now reuses the concert email's DancePro branding, logo,
blue action button, signature and footer. A focused customer service supplies
escaped greeting/content and a plain-text alternative through Laravel's existing
VerifyEmail notification. Signed URL creation and expiry are unchanged; the email
states its expiry and includes a copyable fallback link. No marketing content or
open tracking is added to this account-security message.

The default sender and environment example are now DancePro
<notifications@dancepro.com.au>; local sender configuration matches while SMTP
continues to use Mailpit. Existing received emails remain unchanged. Use Resend
verification to receive the updated version. No real email was sent during this
change.

Focused rendering/delivery checks passed (2 tests, 20 assertions), including
signed URL expiry, escaped user input, HTML and plain-text delivery, configured
sender and leaving the account unverified on resend. Browser previews passed at
1,000px, 390px and 320px with a loaded logo, visible action and no horizontal
overflow. Full regression results follow below.
Full Sail regression suite passed: 980 tests, 10,205 assertions. Scoped Pint and
git diff checks passed. Next: owner can resend verification and continue account
onboarding in the local rehearsal.

### Verification page delivery reminder — 29 September 2026

The verification page now shows the signed-in account's email address in bold
and reminds customers to check junk/spam folders before requesting another link.
The address uses escaped Blade output and wraps on narrow screens. Existing
verification and resend behaviour is unchanged. A local render check confirmed
both the address and reminder without creating or changing an account. Pint and
git diff checks passed; full regression results follow below.
Full Sail regression suite passed: 980 tests, 10,205 assertions. Next: owner
continues signup/verification rehearsal with the delivery reminder visible.

### Customer account studio-access shortcut — 29 September 2026

Added Manage studio access beside Manage event access on the customer account
Overview. It links to the existing studio directory to select/edit a studio and
use Studio account access. Visibility uses the existing manageStudios gate;
access grants and invitation behaviour remain unchanged. Shortcut columns now
fit the available width while preserving existing mobile breakpoints. Local
render verified the link without granting access or sending messages. Scoped
Pint and git diff checks passed; browser/full regression results follow below.
Shortcut verification: desktop/mobile browser checks passed at 1,440px and 390px,
including the correct destination and non-overlapping shortcut cards. The full
Sail suite passed all 980 tests (10,205 assertions).

### Studio staff customer picker and access roles — 29 September 2026

Each studio staff row now has debounced live customer search by partial name,
email or account-ID prefix, with keyboard selection, empty/error feedback and
cancellation of stale searches. Selecting an account fills name/email/phone and
stores its relationship to the studio contact. The optional access-role selector
uses existing Studio Hub invitations, with immediate verified-account access or
pending unverified-account access. Unchanged access does not resend invitations.
Contact-only saves preserve existing grants, and grants always target the selected
account's server-resolved email. Shared add/edit and booking-created studio forms
use the same picker and explicit actor-aware save action.

Migration applied successfully locally. Its down method removes only the added
nullable user foreign key/column; existing contacts and access records remain.
Twenty focused tests passed (159 assertions). Browser checks at 1,440px and 390px
passed live selection, populated contacts, keyboard selection, independent rows,
no-result/error/retry states and non-overflowing staff rows. No invitations were
sent to real accounts during checks. Scoped Pint and git diff checks passed.
Full regression result follows. Next: owner selects the new test account while
creating its studio and chooses its Studio Hub role.
Full Sail regression suite passed: 984 tests, 10,248 assertions. Final git diff
check passed. The live customer picker and role selection are ready for the
owner's Add studio rehearsal.

### Include the newly created studio owner in local rehearsal — 29 September 2026

Added a local/testing-only explicit UUID allowlist to the existing account
switcher, retaining automatic `.test` accounts and the active-account filter.
Jesstudio Ownerberg was added to the local environment allowlist and verified to
appear in the Studio group with the Studio Hub destination. The user's email,
account permissions and verification state were not changed, and no browser
session was switched. Production ignores the allowlist. Regression results follow.
Local switcher verification completed: 986 tests, 10,258 assertions passed.

### Structured account names and Studio Hub access emails — 29 September 2026

Added first/last names across signup, customer self-service, crew self-service,
admin customer contact settings and admin crew forms. Legacy full-name inputs
remain supported, structured names stay synchronised with the searchable display
name, and preferred-name edits no longer overwrite the crew member's account
name. Mobile account/profile payloads expose the name parts. Existing names were
backfilled using first word/rest, with correction available in profile forms.
Personalised account/support/studio greetings now use first name. Privacy exports
include the new fields and model-based anonymisation replaces them.

Migration applied locally. Its down method removes only the new columns; the
combined display name is retained. No account permissions, verification states,
email addresses or order/media relationships were changed by the migration.

Studio access notifications now use the shared branded HTML layout and plain-text
alternative. Verified accounts receive access-added wording and a direct studio
link. New accounts receive a create-and-verify invitation, while unverified
existing accounts receive sign-in-and-verify instructions. Each version explains
the studio, role, capabilities and how to report a mistake. Existing activation
and role authorisation are preserved. Email layout checks passed at 1,000px,
390px and 320px for all three versions, including loaded logo, visible action
and no horizontal overflow. Full regression and resend verification follow.
Signup and customer/crew profile browser checks passed at 1,440px and 390px:
both name fields were visible and editable without horizontal overflow. The
corrected Energy Dance Studio access-added email was resent once to
studio@dancepro.com.au through local Mailpit, and its received sender
notifications@dancepro.com.au, first-name greeting, branding, direct Studio Hub
link and correction instructions were verified. Access records were unchanged.
Verification: the complete Sail suite ran 993 tests; 992 passed and one old mobile
profile assertion still expected a nickname to overwrite the account name. That
test now submits explicit first/last names and checks the returned fields and
combined name. Its full six-test class then passed (60 assertions). No production
code changed after the full run. Scoped Pint, final diff checks and all browser
checks passed. The migrated Jesstudio account was verified as First name
Jesstudio / Last name Ownerberg. Changes remain uncommitted.


### Editable details, verified email changes and compact My studio — 29 September 2026

Profile detail fields are prefilled, editable and required. Email changes now
use a current-password check plus a signed 60-minute confirmation sent to the
new address; the original remains active until confirmation. User-owned studio
access/contact links survive the change, existing media stays with the same
account, old-address notification and session/token revocation are included.
Pending addresses cannot grant access, confirmation checks current uniqueness,
and mismatched/expired/replayed requests are rejected. New migration applied
locally; rollback removes only pending-change columns and retains active emails.

Renamed the tab/heading My studio; added logo-left/name-right rows, completion
badge, collapsed add/change controls and the shared navigation Studio Hub button.
The profile description explains studio media access without promising purchases
or event access automatically. Account security now renders when two-factor
configuration is disabled; it does not enable two-factor authentication globally.

Family plan discussed: My Dancers remains discovery data; a future My Family tab
will support verified account invitations, shared viewing, then shared carts and
explicit parent payment. Existing concert-video guest invitations are distinct.
No broader family-account functionality was claimed or introduced. Verification
results follow below.
Verification completed: full Sail suite passed all 997 tests (10,366 assertions).
Focused email/profile checks, scoped Pint, full route listing and git diff checks
passed. Browser checks at 1,440px and 390px verified required prefilled fields,
conditional email/password controls, empty/complete studio badges, collapsed
management controls, owner Hub button and reachable account security page, with
no horizontal overflow. Both branded email-change variants and plain text rendered.
No real account email was changed and no live message was sent during testing.
Changes remain uncommitted. Next family step is a separately authorised verified
family-invitation/shared-viewing implementation, followed by shared-cart permissions.

### My Family — 29 September 2026

- Replaced My Dancers profile tab with My Family, preserving dancer IDs and competition aliases.
- Added locked manager permissions, member editing/removal, optional dancer flags, combined Save & send invite with branded emails, expiry/resend/cancel and verified acceptance.
- A dancer-only profile can later gain an email/account invitation without being recreated.
- Eligible direct family purchases appear in My Purchases; orders/payment details and individual carts remain private. Purchase permission is checked at checkout and payment.
- Added membership-based download authorisation, ZIP entitlement checks and per-viewer history. Leaving/removal revokes shared access and preserves each buyer’s own purchases.
- Initial scope is one family per account. Multiple families/shared-custody and manager-transfer workflows are deferred; concert guest links remain separate.
- My Family uses the full page width with compact name/permission rows, a + Add family member button, accessible hover/focus permission hints, and competition search names shown only while Dancer is checked. Mobile rows stack without horizontal overflow.

- Family rows now include the locked “Access galleries, favourites & cart” permission for account-enabled members, excluding dancer-only profiles. Newly enabled account permissions change the action to Save & send invite; accepted and unchanged pending members do not receive duplicate invitations. The right-hand trash button opens a removal confirmation.

- Family invitation onboarding: centred review card; editable session-based login/signup prefills; signup and subsequent account-name edits stay attached to the same family member. Expired/cancelled/replaced invitations cannot supply stale defaults.

- Accepted members now inherit the manager’s current and future verified studios/events, with no duplicate registration copies. Leave/removal ends inherited access; independent registrations survive. Concert release/expiry/revocation rules and Studio Hub role boundaries remain enforced.

- My Family save now preserves the page and profile-tab scroll positions and keeps the edited member’s expanded details open. Restoration is limited to the expected return page, expires after 30 seconds and does not prevent saving when browser storage is unavailable. Profile tabs show small accessible red dots for a missing dancer or studio, matching the saved-data badges. Desktop/mobile browser previews verified save navigation, expanded details, both missing-data indicators and no horizontal overflow. No account changes or emails were retained during browser checks.

- Profile tab indicators now match My Messages: the shared red badge with a white **1**, placed before the My Family/My studio label. Missing-data rules are unchanged.

- My Profile now carries the combined missing-dancer/studio count in the shared navigation. One completion service supplies both tab and main-menu counts; checking/unchecking the manager as a dancer and inherited studios use the same rules.

### Verified-account welcome — 29 September 2026

- Added the branded welcome after first-time verification, with personalised first-name greeting, setup instructions and current customer features. Reuses account-email records and their inbox projection, so the full welcome appears unread in My Messages → Notifications and contributes to its badge.
- Verification and welcome creation use a locked account transaction; delivery is attempted after commit and recorded as sent/failed. Repeat verification does not duplicate successful welcomes. Failed mail does not undo verification or hide the inbox copy; the valid verification link can retry that same delivery.
- Future iOS/Android store links have a configuration slot and are omitted until published HTTPS URLs are supplied. Existing verified accounts and account-email changes are not bulk-emailed.
- Focused welcome/profile checks passed (9 tests, 113 assertions). Desktop/mobile profile previews verified left-side numbered badges, aggregate My Profile counts, missing-data states and preserved save scroll. The full suite exercised 1,027 tests: 1,025 passed; two navigation-label assertions needed to exclude the newly added badge numbers. After updating those assertions, all 19 welcome/profile/navigation rechecks passed (262 assertions). Branded email previews passed at desktop and mobile widths; Pint and diff checks passed. Next: continue the user’s signup-to-delivery rehearsal with a newly verified account and its welcome notification.

### Studio membership and unlisted-studio review — 29 September 2026

- Replaced the customer studio dropdown with authenticated live name search, keyboard selection and stale-selection clearing. Adding a studio uses its Studio code field; no photo gallery is needed.
- Added an unlisted/no-code submission with optional location. Pending requests remain on the customer's profile and show Awaiting review. Contacts and Hub Management badge the admin queue.
- Admin can create the studio with its details, match an existing active record, or decline with a visible reason. Approval keeps the original customer linked; duplicate requests/reviews are guarded.
- Approved affiliation follows accepted family membership and supports studio filtering in authorised competition events. Private gallery verification and all existing video/purchase/release gates are preserved. Updated welcome instructions.
- Migration applied locally; rollback reviewed: it removes only request/review history and the new membership timestamp, preserving studios and existing gallery access.
- Focused studio/profile/gallery tests passed (18 tests, 233 assertions). The full suite exercised 1,034 tests: 1,033 passed; one assertion still expected the old Add or verify studio label. After updating it and verifying the Contacts tab badge, all 37 affected profile/family/gallery/welcome checks passed (469 assertions). Desktop/mobile browser checks passed for search, keyboard selection, unknown studios and overflow; the admin create/match/decline forms were also checked visually. Route listing, targeted Pint and git diff checks passed. No preview account or request data was retained.
- Open decision: whether ordinary studio membership should satisfy the first studio-eligibility check when separately entering a concert video code. Current private-media gate remains unchanged.

- Next: rehearse adding a studio with its Studio code, then submit an unlisted studio and review it under Hub Management → Contacts → Studio requests. No commit was created.

### Full-width My Profile — 29 September 2026

- My details, My Family, My studio and Email preferences now share the website container width. Removed the separate family-only width override.
- Name fields sit in three columns and contact fields in two on larger screens, with labelled separators. Studio search/code and new-studio name/location use two columns; all stack on mobile.
- Email preferences use full-width separated rows, text on the left and labelled checkboxes on the right. Existing save, validation, membership and email-change behaviour is preserved.
- Browser checks passed for all four tabs at 1440, 768 and 390 pixels: matching container widths, no horizontal overflow, working preference labels and conditional email confirmation. Formatting and diff checks passed. The full Sail regression suite passed: 1,034 tests, 10,744 assertions. Changes remain uncommitted.

### Shared studio access and compact notifications — 29 September 2026

- The Studio code now covers membership and released concert photo galleries. Removed the separate gallery-code editor/unlock UI; old compatibility endpoints update/use the same code. Studio Hub, tracker, guest access and QR invitations agree on that source.
- Backfilled existing confirmed memberships; pending requests and unverified associations remain gated. Admin-approved studio requests and accepted family inheritance include released concert photos. Competition event codes, concert-video release/codes and purchase permissions remain separate.
- Dancer and studio matches remain recommendations before the competition event is added; full galleries still require event registration.
- Email preference tickboxes sit on the left beside their text. Notifications collapse to date, time, Read/Unread status and heading; expanding reveals the full message, email delivery status and actions. Notification/Conversation tab badges sit before their labels.
- Validation: migration applied; 74 focused tests passed (993 assertions); the full Sail suite passed with 1,035 tests and 10,758 assertions. Targeted Pint and diff checks passed. Browser previews verified 1440/768/390px layouts, keyboard disclosure controls, left-aligned badges/checkboxes and no horizontal overflow; fixture data was rolled back.
- Notification disclosures now use the Studio Hub down/up chevron treatment, replacing the plus symbol. Desktop/mobile checks cover arrow direction, mouse/keyboard expansion and overflow.
- Expanding a notification now saves its read status asynchronously and refreshes notification/menu counts. Mark as read is also available in the collapsed title row. Failures preserve unread status and allow retry; normal form submission works without JavaScript.
- Add media uses the requested View & order Competition media, View & order Concert photos and Stream & download Concert videos headings. Concert photos use the shared studio gallery card (logo/name/View events), centred within the box.
- Removed the Studio and crew access section from newly generated welcome emails and their Notifications copy; existing sent emails are unchanged.
- Verification: full Sail suite passed (1,036 tests, 10,777 assertions), followed by the welcome recheck after its final copy change (4 tests, 49 assertions). Desktop/mobile browser checks passed for disclosure arrows, automatic read and collapsed read actions, failed-save retry, live badges, the new headings and centred studio card. Pint and diff checks passed; preview fixture data was rolled back.

### Studio messages inbox — 29 September 2026

- Studio messages now reuses My Messages disclosure styling and read controls: date/time, Read/Unread/Sent status, heading, down/up arrows and Mark as read in the collapsed row. Full text, attachments and Reply appear when expanded.
- Opening a tab no longer marks the whole studio conversation read. Individual reads persist for authorised studio users; tracker emails have a separate Studio Hub read timestamp, independent of email-open tracking. The summary box and left-hand tab badge count unread incoming messages only, update without a page reload and exclude studio replies and unsent emails.
- Send a message is above the list and starts collapsed. Reply opens it with subject/event prefilled; validation errors preserve the draft and reopen it. Optional event and attachment fields are inside Advanced options.
- Verification: migration applied and rollback reviewed; 14 focused tests passed (197 assertions). Desktop/mobile browser checks passed for collapsed composer placement, individual reads, failed-save retry, badge updates and Reply, plus My Messages regression checks. Route listing, targeted Pint and diff checks passed. Preview fixture records were rolled back.
- Full Sail regression suite passed: 1,045 tests, 10,909 assertions. Changes remain uncommitted.

### Pending booking availability preview — 29 September 2026

- Submitted pending concert and DR portrait events now appear in admin Event Availability, chronologically mixed with scheduled shifts and sharing one paginator. Rows show requested date/time, studio/title, venue and red Pending approval, with a review link.
- Pending rows support studio/search/type/status filtering and tracker 2.1's studio snapshot. Cancelled, declined and already scheduled requests are excluded. No scheduling records, crew responses or notifications are created by viewing them; approval replaces the row with a draft shift, and Request availability remains a separate action.
- Verification: all 1,049 Sail tests passed (10,952 assertions), including pending-row lifecycle, crew isolation, filters and pagination. Normal/full-screen browser checks verified chronological placement, review links, closed crew cells and the Pending approval filter; preview fixtures were rolled back. Targeted Pint and diff checks passed. No migration is needed; changes remain uncommitted.

### Booking and tracker panel consistency — 29 September 2026

- Removed outer width limits from admin booking forms, seats/families, studio uploads, draft invoices, task emails, editing and correction panels. Photo release panels now stack at full width. Programme/title preview columns and responsive field layouts remain inside their full-width workspaces; the booking save bar aligns with its form.
- Shared the existing four category accent colours between the tracker spreadsheet and task bar: blue Booking, yellow Preparation, purple Editing & approval and green Completion. Pending-booking tabs use their task's category too; status colours are unchanged.
- Browser checks passed for 31 rendered page variants at 1600, 1100 and 390 pixels (93 checks), covering all six pending/approved booking tabs, all tracker tasks and booking confirmation. Main-panel alignment, category colours, horizontal overflow and page errors passed. Preview fixtures were rolled back and mail was faked. Targeted Pint and diff checks passed.
- Full Sail regression suite passed: 1,049 tests, 10,952 assertions. No routes or database changes were required; changes remain uncommitted.

### Remove duplicate booking materials panel — 29 September 2026

- Removed the Programmes & slideshows box from 1.1 Booking & services and deleted its unused view partial. The existing 2.2 Prepare programme and 2.5 Studio videos & slideshows tabs retain their workspaces and uploads.
- Rendered pending and approved booking previews confirm the duplicate is absent and the dedicated workspaces remain. Preview data was rolled back; mail was faked. Targeted Pint and diff checks passed. The full regression suite exercised 1,049 tests: 1,048 passed and one still expected an uploaded video on 1.1. Updated that assertion to verify it on the studio-media tab, then all 13 booking-materials tests passed (311 assertions). No application changes were needed after the full run. Changes remain uncommitted; continue the booking rehearsal through the dedicated preparation tabs.

### Booking status workflow — 29 September 2026

- 1.1 now offers real Pending approval (red), Received (yellow), Approved and Partial Approval (green), and Cancelled (grey) states. Removed tracker-only status overrides from the booking picker. Partial selection schedules only selected pending events; full approval and existing bulk/event approval keep the booking state consistent.
- Received remains unscheduled and appears without a description in Studio Hub. Cancellation requires a reason and uses existing event cancellation/crew closure behavior. Existing partly approved bookings are aligned with Partial Approval.
- Added editable branded notification drafts for received/partial/cancelled states, with account links, recorded cancellation reasons, send history and Studio messages copies. Duplicate sends and stale drafts are blocked; failed delivery can be retried and changed details can generate a current draft. Email sending remains an explicit action.
- Fixed Partial Approval checkboxes in the shared tracker dialog: 18px boxes on the left, aligned with their event text on both booking and email pages.
- Both migrations applied locally; rollback behavior reviewed. Desktop/mobile browser checks passed for status and email pages, checkbox positions and conditional fields. Preview data was rolled back and mail faked. The full regression suite exercised 1,056 tests: 1,052 passed, three exposed shared status validation affecting other tracker tasks, and one new retry test needed its mail fake restored. Separated generic task validation from the five booking states and corrected the test fixture. All 73 affected booking, email, programme, editing and tracker tests then passed (1,271 assertions). Route listing, targeted Pint and diff checks passed. Changes remain uncommitted.


### Event Availability role and status controls — 29 September 2026

- Empty role cells open an active, applicable role picker, including P2. Additions are event-wide, duplicate-safe, limited to the sheet's three slots, and blocked for cancelled events. Concerts use V / P1 / P2 columns; DR portrait ordering is preserved.
- Clicking an event status opens the existing workflow actions. Request availability requires a future/current deadline; publishing checks required allocations and notifies crew. Ready remains source-derived; adding a role invalidates readiness until it is staffed and acknowledged. Draft cannot undo a published roster.
- Selecting a bulk action reveals a dedicated left checkbox column, synchronises selection across event shifts, and shows the deadline for availability requests. Empty selection is blocked. Pending rows remain informational until booking approval.
- Both controls are shared by Event Availability and tracker 2.1. Saves retain sheet position and filters; full-screen saves stay in the expanded sheet. Narrow-screen columns scroll to keep controls reachable.
- Verification: full Sail suite passed (1,061 tests, 11,100 assertions); the final role-filter adjustment and pending rows passed a further nine focused tests (97 assertions). Route listing, targeted Pint and diff checks passed. Desktop/mobile/full-screen browser checks verified role/status dialogs, inline validation, deadline fields and synchronised multi-shift bulk checkboxes. No migration or live notifications required; preview fixtures were rolled back. Changes remain uncommitted. Next: rehearse adding P2 and requesting availability on the test booking.


### Crew availability navigation and assignment choices — 29 September 2026

- Fixed the detailed Crew Hub availability form returning to an implicit My Shifts tab after saving the final unanswered request. Save/clear now explicitly return to Availability, preserving Detailed/Grid layout; grid AJAX behaviour stays in place. A regression scenario with outstanding acknowledgement work verifies the tab remains Availability.
- Event Availability and tracker 2.1 role pickers put active crew with both an Available response for that shift and Approved training for that role first, prefixed with a tick. Remaining active crew are listed alphabetically once underneath. Clashes do not affect membership or ordering; existing assignment clash indicators remain.
- Administrators can select the other crew group as requested without changing training qualifications. Inactive accounts cannot be assigned. Crew self-service cover qualification rules are unchanged.
- Verification: all 1,063 Sail tests passed (11,132 assertions), including the final-response tab regression, recommendations despite clashes, other-crew assignment, and inactive-account rejection. Desktop/mobile browser checks passed for ticked priority groups, alphabetical ordering, unique entries and per-shift availability. Targeted Pint and diff checks passed. No migrations or route changes. Changes remain uncommitted. Next: continue the crew allocation rehearsal using the grouped dropdown.


### Competition equipment and persistent popup — 29 September 2026

- Competition assignments now support the same Video 1/2/3, Backdrop 1/2 and Media bring/take options and notes as concerts. Existing equipment journey warnings and published-assignment acknowledgement behaviour apply to both.
- Equipment popups remain open during each automatic save. Requests are serialised per popup so rapid Bring/Take selections preserve the latest state; saved-state feedback and failed-save rollback/retry are included. Closing with × or outside click refreshes the sheet once pending saves finish, retaining scroll/filter/full-screen context. Narrow-screen popups fit the viewport.
- Verification: all 1,064 Sail tests passed (11,148 assertions). Desktop/mobile browser checks passed for all six options, rapid selections, persistent popup, failed-save rollback/retry, and closing with × or outside click. Popup positioning was visually checked at both sizes. Targeted Pint and diff checks passed. No migration or route change. Preview fixtures are rolled back; changes remain uncommitted.


### Equipment coverage indicators — 29 September 2026

- Required Setup and Set down labels in Event Availability (including tracker 2.1) now turn green when a crew assignment for that shift brings or takes a video/backdrop kit respectively. Uncovered requirements remain yellow; Media and notes alone do not count. Green indicates responsibility assigned, not completion of the physical work.
- Responsibility/assignment removal restores yellow. The existing popup close refresh updates the labels while preserving the sheet position. Hover text explains the status.
- Verification: all 1,065 Sail tests passed (11,184 assertions), including shift isolation, Media exclusion, removal and both event types. Desktop/mobile browser checks confirmed green/yellow colours. Targeted Pint and diff checks passed. No routes or migrations changed. Changes remain uncommitted.


### Editable shift setup and set down — 29 September 2026

- Kept concert approval defaults (both requirements on) and competition event-form defaults. Clicking a scheduled Shift cell now opens two checkboxes for per-shift overrides, including in tracker 2.1. Each saves automatically; the popup stays open for multiple changes and closes with × or outside click. Save failures restore the last saved state and support retry.
- No automatic grouping across junior/senior concerts; admin can remove intermediate setup/set down requirements. Existing arrival/finish times are preserved. Only published assignments on the changed shift are notified and have acknowledgement reset; unchanged saves do not notify again.
- Verification: all 1,066 Sail tests passed (11,204 assertions); six focused equipment tests also passed (77 assertions); targeted Pint, route registration and diff checks passed. Desktop/mobile browser checks passed for rapid autosaving toggles, persistent popup, failure rollback/retry and closing. No migration. Changes remain uncommitted. Next: rehearse turning off intermediate requirements for junior/senior concerts at one venue.


### Separate photo and video media responsibilities — 29 September 2026

- Replaced the Media row with independent Photos and Videos bring/take/notes controls. Kept one shared 💾 marker in the assignment cell, combining directions into descriptions such as “Bring photos · Take photos & videos”. Crew Hub shift cards share this description and catalogue. Numbered Video kits remain separate from video media.
- Forward migration preserves legacy Media selections and notes as both Photos and Videos. Applied locally after tests and read-only preview confirmed the forward direction; the initial automatic review rejection misread rollback as forward logic and was resolved by those checks. Rollback preserves flags/notes but combines the two media types.
- Verification: all 1,068 Sail tests passed (11,220 assertions). The 23 focused tests also passed (285 assertions), including independent selections, combined marker and migration/rollback preservation. Targeted Pint and diff checks passed. Desktop/mobile browser checks passed for seven options, independent saving and the combined media marker. Next: review migrated photo/video selections during crew allocation. No routes changed. Changes remain uncommitted.


### Plain crew equipment instructions — 29 September 2026

- My Shifts equipment entries now read “Bring & Take Backdrop Kit 1”, “Bring Video Kit 1”, or “Take Video Kit 2” according to the saved responsibilities. The existing coloured kit emoji and directional arrows remain alongside the wording. Notes remain visible; note-only entries show the kit name without inventing a transport instruction. Photo/video media wording remains unchanged.
- Verification: all 1,068 Sail tests passed (11,220 assertions). The final Crew Hub render passed checks for all direction combinations, notes-only labels, coloured emojis and arrows. Targeted Pint and diff checks passed. No routes or migrations changed. Changes remain uncommitted.


### Clear seats & families request — 29 September 2026

- Rewrote task 1.2 with the exact Studio Hub → Concert events → Your actions process, unique-family counting, explicit confirmation, per-concert row/seat entry, matching button names and the ten-day deadline. Programme/supplied-media uploads are described as separate actions with their own due dates. The branded Open Concert events button targets the correct studio, events tab and expanded action cards; the admin preview includes it.
- Found the existing family confirmation endpoint had no visible customer form after removal from the timeline. Restored the shared form inside concert event actions and returned successful confirmations to Concert events. Family/seating badges now use the tracker 1.3 deadline; family confirmation is included in action/overdue counts.
- Applied the content-only template migration locally. Previously sent deliveries remain unchanged; no emails sent. Down deliberately does not restore obsolete instructions. Added UTF-8 metadata to the shared HTML layout.
- Verification: all 1,069 Sail tests passed (11,236 assertions), including the email link, shared family confirmation and overdue action counts. Email browser checks passed on desktop/mobile. Targeted Pint and diff checks passed. No route changes. Changes remain uncommitted.

### Studio action icon colours — 29 September 2026

- The left-hand action circles now show yellow exclamation marks for outstanding tasks, red for overdue tasks and green ticks for completed tasks. Completion takes priority over a past deadline. This covers portrait requirements, family numbers, reserved seats, programmes and supplied videos/slideshows. Available video review uses yellow; unavailable review stays grey and completed review stays green.
- Verification: desktop/mobile browser checks passed for all five action types in all three states using the actual Blade markup and styles. Targeted Pint and diff checks passed. All 1,069 Sail tests passed (11,236 assertions). No routes, data or workflow rules changed; changes remain uncommitted.


### Preserve dress rehearsal save position — 29 September 2026

- Save requirements now uses the existing customer scroll-preservation mechanism. The event page restores the expanded/closed sections as well as vertical position, keeping the dress rehearsal and its requirement checkboxes visible after saving. Existing redirect, permissions, validation and success notices remain unchanged.
- Desktop/mobile browser checks passed through an actual POST/redirect with saved checkbox state, unchanged scroll position and expanded/closed sections restored. Targeted Pint and diff checks passed; all 21 focused existing save/profile regression tests passed (405 assertions). Changes remain uncommitted.


### Confirm event details and count applicable actions — 29 September 2026

- Added per-event Confirm event details under Your actions for concerts and dress rehearsals. Studio users confirm arrival/setup times, start/finish times, date and venue, with guidance to Request changes promptly. Reuses scoped acknowledgement storage and the seats/families deadline; no tracker task or route added. Portrait and event-detail saves cannot clear one another.
- Removed automatic booking approval and not-required tasks from action totals. DR portraits now have two actions; video concerts normally five and photo-only concerts four, with review/release added when actionable. The local Energy studio preview showed DR 1/2, Junior 2/5, Senior 1/5.
- Applied the content-only 1.2 email migration with the new instructions. No emails sent. Rollback intentionally retains current wording.
- Made position/expanded-section preservation automatic for all Studio Hub event forms, including successful uploads. Stable panel IDs preserve expansion even when submitted-file sections are added. Recorded this as the default convention for future forms.
- Verification: all 38 ConcertTracker tests passed (663 assertions), including per-event isolation, DR counts, shared deadlines, applicable concert totals, mixed-group validation and unauthorized access. Desktop/mobile actual-view checks and explicit/default/upload redirect scroll checks passed. Targeted Pint and diff checks passed. All 1,070 Sail regression tests passed (11,261 assertions). Updated 1.2 email browser checks passed on desktop/mobile. Changes remain uncommitted.


### Simple seat entry and clear save feedback — 29 September 2026

- Studios/admin can enter J16-20, j16-20, J16–20 or J16, 18. The parser expands ranges into the existing individual-seat storage, preserves the old row/colon format, rejects reversed/excessive/mixed-row ranges and removes duplicates before checking role limits. Updated field labels, examples and the stored 1.2 email wording; no emails sent.
- Studio Hub event forms now show a green saved notice by the submit button on successful return and a red error on validation failure, with upload success handled after reload. New edits clear stale success feedback. Position and panel expansion remain preserved. Forms without explicit IDs use the workspace form index.
- Verification: seat endpoint regression passed (37 assertions), covering shorthand/legacy input, ranges, separate seats and invalid entries without replacing saved data. Desktop/mobile seat form checks passed. Targeted Pint and diff checks passed. Desktop/mobile save-feedback checks passed for explicit/default forms, uploads, validation errors, clearing stale success and preserving position/sections. All 1,070 Sail regression tests passed (11,283 assertions). Changes remain uncommitted.


### Compact seats/families tracker cell — 29 September 2026

- Replaced repeated family/P1/V/P2 activity lines with one latest relevant name and date. Seat activity uses the current event; the shared family confirmation remains relevant to each event. Stored seat records and completion rules are unchanged.
- Consecutive numeric seats now display as ranges such as V: J16-20. Single seats, gaps, other rows and named seats remain explicit; sorting and duplicate handling prevent misleading ranges.
- Verification: two focused tests passed (30 assertions), covering latest actor/date selection and compact runs, gaps, row boundaries and alphanumeric seats. The real Energy tracker cell preview passed browser checks and visual review. Targeted Pint and diff checks passed. All 1,071 Sail tests passed (11,288 assertions). No migrations or route changes. Changes remain uncommitted.


### Compact reserved seats in task 1.4 email — 29 September 2026

- Reused the tracker seat-range formatter in the shared event-details email rendering. Reserved seats now have a separate bullet for each role, rather than repeating Row/Seat for every position in one long line. This updates the 1.4 preview and other future event-detail emails; no emails sent or historical delivery bodies rewritten.
- Desktop/mobile checks of the real Energy concert-week email passed: three separate role lines and compact J16-20. All 38 ConcertTracker email/workflow tests passed (689 assertions). Targeted Pint passed. Changes remain uncommitted.


### Concert-week email aligned with Studio Hub — 29 September 2026

- Task 1.4 now explains uploading programmes and supplied videos/slideshows under each event’s Your actions, replacing a changed programme, checking Submitted files, following the displayed deadlines and contacting Studio messages for help. Video-specific instructions are omitted for photo-only bookings.
- Each non-cancelled booking event includes its own assigned crew names in “You’ll have Alex and Casey capturing your event” wording, without crew roles, alongside its arrival/setup time and compact reserved seats. Cancelled assignments are excluded. The email and admin preview button open the studio’s Concert events actions.
- Applied the content-only migration locally; existing sent deliveries are unchanged and no emails were sent. Rollback retains the accurate instructions. Desktop/mobile rendered email checks and visual review passed, as did targeted Pint and diff checks. The full Sail run passed 1,071 of 1,072 tests; the sole failure was the pre-change crew-role assertion loaded before the requested wording change. All 39 tracker tests then passed with the final wording (712 assertions). No routes changed. Changes remain uncommitted.


### Twenty-day concert email groups and programme deadlines — 29 September 2026

- Added fixed 20-day groups within a booking. 1.4 now shares one task, delivery log, duplicate-send protection and successful completion across all included events. The email preview names the group, and content is limited to those events. Older sent history is retained and reconciled to the shared task.
- Email due/scheduling basis is ten days before the first event, including DR. Each programme is due seven days before the first concert in the group, excluding DR; Studio Hub and timeline follow the tracker date. Existing automation mode and explicit date overrides remain intact. Updated email subject, deadline wording and task instructions.
- 2.2 now shows grey Not required for dress rehearsals, with no deadline or programme editor. The booking programme tab defaults to a concert when available.
- Applied the migration locally and confirmed all three Energy events share the existing sent email. Standard dates are 20 December for the email and 24 December for programmes; DR is Not required. No emails sent. Desktop/mobile email checks, targeted Pint and diff checks passed.
- Full Sail run covered 1,074 tests: six affected failures were three old individual-event/subject expectations and three view failures caused by the DR view change arriving during that run. After updating the expectations and completing the DR workspace change, all 63 affected booking/tracker tests passed (1,177 assertions); the other 1,011 tests passed in the full run. No routes changed. Changes remain uncommitted.

### Compact video title controls — 29 September 2026

- Removed the standalone description box in 2.3 Video titles and placed Video titles ready beside Save template. Existing readiness rules and missing-programme messages remain intact; a failed publish still permits retry.
- Desktop and mobile browser checks passed for layout and failed-publish recovery, without saving or publishing real titles. Targeted Pint and diff checks passed. All 1,075 Sail tests passed (11,396 assertions). No routes changed. Changes remain uncommitted.

### Preserve video title save position — 29 September 2026

- Task 2.3 remembers scroll position and selected preview item when saving the template and before the successful ready/publish reload. Restoration is limited to the same page and a recent save, and stops if the user scrolls or interacts. Normal new visits remain unaffected.
- Moved saved/validation feedback beside the template controls so it is visible without returning to the page top. Editing again clears the previous saved notice.
- Desktop/mobile browser checks passed through isolated POST/redirect responses for success and validation errors: scroll and preview selection were retained, feedback was visible, and fresh visits started normally. No application data was saved by these checks. Targeted Pint and diff checks passed; all 1,075 Sail tests passed (11,396 assertions). No routes changed. Changes remain uncommitted.

### Return to the same tracker position — 29 September 2026

- Back to tracker now uses the current browser tab’s most recent tracker URL and restores page scroll plus both table scroll directions. It preserves date/studio view, year and filters, including when another browser tab has changed the server-side return context. Newly rendered task-bar buttons use the same behaviour.
- Returning from an expanded tracker restores the existing expanded-page mode, with its working Exit full screen control. Ordinary fresh tracker visits remain unchanged. Saved state is local to the tab, expires after eight hours and is validated against the same-origin tracker route; normal navigation still works when storage is unavailable.
- Eight isolated browser scenarios passed across desktop/mobile, date/studio view and normal/expanded mode, including return URL, both scroll directions, exit behaviour and unaffected fresh visits. No application data changed. Targeted Pint and diff checks passed; all 1,075 Sail tests passed (11,396 assertions). No routes changed. Changes remain uncommitted.

### Grouped post-concert email and Studio Hub instructions — 29 September 2026

- Task 2.4 now shares one task/delivery across the same fixed 20-day booking group as 1.4, due the day after its final event. Successful delivery completes every included cell. Separate groups stay separate; cancelled events are excluded. Sending before the group has finished is blocked, with a clear scheduling notice and earliest date.
- Updated the branded template with a concise event list, current programme/studio-file uploads, photo access, owner video review/corrections/release, and customer streaming plus individual/multiple downloads. Removed legacy file-sending and principal-offer wording. The CTA opens Studio Hub Concert events.
- Applied the local data migration: retained sent history, consolidated tasks, and invalidated stale unsent copies for review. Energy's three events now share a task due 1 January 2027. Review-first mode and 09:00 send time remain unchanged. No emails sent.
- All 44 focused tracker tests passed (876 assertions), including grouping boundaries, cancellation, timing, early-send prevention, shared completion, duplicate protection, service-specific copy and migration history. Desktop/mobile rendered email and scheduling-page checks passed with visual review. Targeted Pint and diff checks passed. The full run passed 1,077 of 1,078 tests; its one failure expected the old independent post-concert status. Updated that expectation and ran the send test after the final event. All 58 affected tracker/booking tests then passed (1,193 assertions). No routes changed. Changes remain uncommitted.

### Post-concert timing warning instead of a restriction — 29 September 2026

- Replaced the 2.4 early-send restriction with a yellow warning showing the recommended date. Send email stays available and Send later is optional, including dates before the group's last event. Removed the corresponding queue and worker rejection; manually configured automation offsets are honoured too.
- The standard deadline/default automation offset remains the day after the final event. Grouped completion, duplicate-send protection, explicit resend confirmation and booking-change invalidation remain in place. Existing cancelled deliveries were not replayed and no emails were sent.
- Added/updated regression coverage for immediate early delivery, early scheduled delivery, grouped completion, warning expiry and configurable automation timing. All 1,079 Sail tests passed (11,493 assertions). Desktop/mobile browser checks confirmed the warning, enabled Send email button and optional send date with no event-date minimum. Targeted Pint and diff checks passed. No routes or migrations changed; work remains uncommitted.

### One-off tracker email customisation — 29 September 2026

- All tracker send pages now support editing the formatted subject/message for that send only, including booking-status, director-review/corrections, photo-release and invoice emails. Reset restores generated wording; future templates and automatic emails remain separate.
- The visual editor preserves the existing safe Markdown delivery/history format and keeps branding/signature outside the editable body. Photo announcements share edits across the current batch without re-emailing sent or pending recipients. Recorded delivery messages are now expandable for review.
- All 1,082 Sail tests passed (11,539 assertions); targeted Pint and asset build passed. Desktop/mobile browser checks confirmed direct formatted edits, reset, safe links, blank-message validation and unchanged templates. No emails sent. Next: review a one-off message in the local test workflow before choosing Send.

### Tracker completion ownership and dates — 29 September 2026

- Unassigned completed tracker records now take their recorded completer as assignee; prior assignments stay intact. Applied the historical backfill only to completed records with a known completer and blank assignee.
- All shared tracker task bars show Completed on with date/time in place of Due date once complete. Source workflows supply their own completion timestamps, missing history is labelled Not recorded, and reopened tasks recover their normal due-date control. Added photo completion timestamps and refined aggregate crew/video readiness dates.
- All 1,086 Sail tests passed (11,567 assertions). Desktop/mobile completed-task browser checks, targeted Pint and diff checks passed; migration applied and rollback behaviour reviewed. Next: check completed email tasks in the local tracker; no email was sent by this change.

### Studio-level guest photo access — 30 September 2026

- Guests enter their email and studio code at the studio gallery entrance, then
  move between that studio's released events without re-entering the code.
  Event cards open their galleries directly after access; gallery Back navigation
  returns to the studio, including the flat dress rehearsal portrait gallery.
- Existing event access links and unexpired guest access remain compatible. Cart
  and favourites keep event-specific records while sharing studio authorisation;
  browsing does not extend the original 30-day expiry. Other studios, competitions,
  concert video and unpublished photos retain their separate access checks.
- Studio payment receipts appear in Studio messages only. The corrective migration
  removes old personal inbox projections and preserves emails, studio messages and
  payment history; the personal inbox importer cannot recreate those projections.
- Read-only desktop/mobile studio entrance review passed with no script errors or
  page overflow. Jesstudio's local personal projection was removed and its sent
  email audit and Studio messages receipt were confirmed intact.
- Verification: full Sail run passed 1,105 of 1,106 tests; the remaining test still
  expected public studio entry before code verification. Updated it to unlock at
  studio level, then all 48 affected gallery, release and invoice tests passed
  (665 assertions), including the final DR return-link check. Scoped Pint,
  `git diff --check` and route listing (715 routes) passed. Correction migration
  applied locally; rollback intentionally retains the notification correction.

## Gallery card interaction standard — 30 September 2026

Customer and admin galleries now share `public/css/gallery-cards.css`. Use the
existing event-card effect for studio, event, album and media cards: DancePro blue
border and 2px outline with a 2px offset, no lift and no link underline. Keyboard
focus uses the same visible treatment, including cards with child controls.
Existing gallery card variants are covered centrally; new variants should use
`gallery-hover-card` rather than adding separate hover rules. Layout, image ratios
and individual action-button styles remain separate from this shared interaction.

Verification: full Sail suite passed (1,107 tests, 11,847 assertions). Scoped Pint
and whitespace checks passed. Browser checks confirmed matching hover and keyboard
focus on admin Concert Photos/Top Pics studio and event cards, customer studio and
event cards, and the shared class for future card variants.

## Item-album card layout — 30 September 2026

The item-album design is now the shared navigation-card layout. Use
`x-gallery-navigation-card` for studio, event and routine/folder links, with
`gallery-navigation-grid` for the responsive grid. The component uses a 3:2 image,
4px corners, a 17px title, a 13px muted subtitle and a separate bottom action strip.
Photos fill the image area; logos fit without cropping. Long titles wrap naturally,
and action strips align along the bottom of each row without a large blank title
area. This replaces the earlier 3508:2480 studio/event image ratio.

The shared component supports whole-card links and an action slot for registration
buttons. My Media uses the same layout classes while preserving its access-code
buttons, purchase links and recommendation controls. Administrative album controls
and photo thumbnails keep their specialised actions. Hover/focus continues to use
the shared gallery-card interaction standard.

Admin concert item albums now open from their cover, title or **View album** link.
A bottom-right settings cog expands the existing title, order and cover controls.
The album view paginates available photos, opens full previews and returns to the
original album card. Album queries are scoped to the selected concert, and the
existing admin-only preview/access rules remain enforced.


## Admin concert gallery headings — 30 September 2026

Concert Photos uses the same page heading and description throughout the studio,
event, album and photo views. The shared `x-admin.gallery-heading` component puts
back navigation at the top left, followed by a context card with studio artwork,
a heading, event name/date where relevant and a concise description. Event views
use matching Albums/Top Pics tabs and a Photo release button. Top Pics concert
views reuse the same context layout while retaining their Publicity page heading.
Gallery repair is collapsed below the albums and capture folders.

Verification for the card, album navigation and heading changes: full Sail suite
passed (1,108 tests, 11,856 assertions); the final focused gallery run passed
24 tests (301 assertions). Desktop (1440px) and mobile (390px) browser checks
confirmed shared headings, back links, active tabs and no horizontal overflow.
Album settings, loaded photo previews and the return-to-album anchor also passed.

## Concert photo release announcements — 30 September 2026

Task 4.1 retains two explicit steps: publish the selected studio events, then review
and send the announcements. The review page shows three audience counts and three
independently editable messages with one **Send all release announcements** button:

- Active, verified customer accounts with studio photo access (including eligible
  family access) receive the existing account announcement and personal inbox entry.
- Guest studio/event subscribers and guests who previously entered a valid studio
  code receive a distinct guest email explaining code-based browsing, account
  creation, ordering and transferring guest favourites/cart in the same browser.
  Guest unsubscribe choices are respected, including immediately before delivery.
- Active, verified Studio Hub accounts receive a separate branded email with a
  forwardable family message and signed studio-joining link. Existing customers
  sign in; new customers create and verify their account. The existing joining
  flow then attaches the studio without asking for its code. This grants released
  concert photo access only, not Studio Hub or video entitlements. Code changes
  invalidate the link and cancel queued studio emails that still contain it.

Gallery announcements are deduplicated by address, prioritising linked customers
over guests. Studio Hub instructions are separate: contacts who also have customer
studio access receive both the customer announcement in My Messages and the studio
instructions in Studio Messages. Emails are sent individually. Existing sent/queued
messages are skipped and failed/cancelled messages can be retried. Guest emails include individual signed studio
unsubscribe links; creating that delivery preserves any existing opt-out.

One Studio Messages entry is created after the first successful studio delivery,
linked uniquely to the release group. Customer/guest copies are excluded from
studio histories and unread counts. The guest scheduler (and already queued legacy
guest notifications) skips events in released groups, preventing extra per-event
emails before or after admin sends the grouped announcement. Ungrouped legacy
releases and competition notifications retain their existing behaviour.

No release or email is sent simply by opening the review page. The public gallery
is available after release even if admin has not yet sent the announcements.
The migration adds a nullable unique release reference to Studio Messages; its
rollback removes only that reference and retains the message content.

Verification: full Sail suite passed (1,111 tests, 11,904 assertions), followed by
35 focused tests (508 assertions) covering the final copy, Studio Messages button
and changed-code cancellation/retry. Tests cover grouped events, all three
messages, overlapping recipients, inactive/unverified exclusions, unsubscribe,
legacy scheduler suppression, inbox separation, retries and existing/new-account
joining. Desktop/mobile browser checks at 1440px and 390px confirmed three separate
visual editors, independent edits/resets, recipient counts, one send button and no
horizontal overflow. Scoped Pint and `git diff --check` passed. Migration applied
locally; no media was released and no real email was sent during verification.

Customer release copy now states that recipients already have studio photo access
and directs them to My Media → My Events → their studio. It no longer asks these
recipients to add the studio or obtain its code. The targeted data migration
replaces only the old stock paragraph, preserves other template edits and leaves
historical deliveries unchanged. Rollback retains the corrected wording.

Customer and Studio Hub release greetings use `{{ first_name }}`. The review page
retains the placeholder and explains it; each delivery resolves it using the
recipient account's first name (with the existing legacy-name fallback). Names
are escaped as text in the message, and the shared template is not overwritten
with any recipient's details. Guest emails keep their general greeting. One-off
message edits can retain the placeholder for personalised wording.


## Event covers and release delivery follow-up — 30 September 2026

My Media event cards and purchase event cards use the admin date format (`j M Y`),
with “Date to be confirmed” for missing dates. Event covers are scoped to that
concert’s published, visible photo albums, respecting its selected Top Pic from
admin and using the admin automatic Top Pic default otherwise. A shared release
group no longer makes every event inherit the same cover. Hidden or unreleased
assets cannot become customer card covers.

Delivery uniqueness now separates gallery announcements from Studio Hub messages,
allowing both for one eligible account without duplicating either on retries.
The migration retains existing delivery history; rollback refuses to discard dual
delivery records when the previous unique key cannot represent them.

The studio page reached from My Media now uses the standard logo/eyebrow/heading
and the shared event cards. Customer and guest concert album navigation uses the
same gallery-navigation-card component as event and competition navigation:
3:2 covers, 4px corners, 17px titles, 13px subtitles and a separate action footer.
Album covers respect the administrator’s chosen album photo, and subtitles show
visible photo counts. The studio photo page keeps My Media active in navigation.
Guest studio event cards use the same event-specific Top Pics and date format.


Gallery breadcrumbs use one shared component and wrap on mobile. Customer, guest
and admin concert views link Studio → Event → Album; competition galleries link
Event → Day → Section → Routine using their existing folder paths. Current pages
are marked with `aria-current` and earlier levels remain clickable. Studio event
lists and admin Top Pics use the same treatment. Dress rehearsal portraits keep
the direct Studio → Event → photos flow without introducing another folder.

Competition dancer, studio and improvisation suggestions now reuse the shared
routine card layout and existing folder-cover selection; registered competition
cards also use the shared component. All these views retain their existing access
and section-readiness checks.

Verification: 58 focused gallery/profile tests passed (914 assertions),
and the separate release audience checks passed. Browser checks at 1440px and
390px confirmed loaded, distinct Energy event covers matching admin, consistent
card dimensions, working album links and both breadcrumb ancestor links. The
three release editors remain independent and fit both screen sizes. No release,
email send or media mutation was performed during browser verification.

Final full Sail run exercised 1,114 tests: 1,113 passed and one assertion still
expected the former fixed dress-rehearsal card title. Updated that assertion to
the event name, matching admin and My Media, while retaining the portrait album
name and direct-to-images checks. The final dress-rehearsal/release-email rerun
passed all 7 tests (113 assertions). Scoped Pint and `git diff --check` passed.
Migrations applied locally; no routes changed. Work remains uncommitted.


### Centred logo on photographic gallery covers (30 September 2026)

Photographic event, album and routine covers now request a separate `cover`
derivative in customer, guest and admin galleries, including Top Pics, publicity,
release previews, My Media recommendations and purchase cards. Competition
cover settings and preview headers use the same derivative. Studio and event
logo artwork continues to use its existing logo URL without a watermark.

The supplied transparent DancePro landscape artwork is stored at
`resources/images/watermarks/gallery-cover.png`. The image processor crops a
3:2 cover before centring the logo at 82% of the cover width, with a 600px maximum
edge. The logo is baked into a small JPEG from the original, rather than layered
over the existing concert/competition watermark or supplied as removable HTML.
Original uploads, downloads, individual gallery thumbnails/previews and private
publicity originals keep their existing behaviour.

Existing preview endpoints accept the validated `cover` size and retain their
normal authorisation, album visibility and release checks. Managed public cover
endpoints and purchased-media covers also generate a cached branded JPEG from
the stored original instead of redirecting to an unbranded original. They accept
only server-resolved storage keys, not an arbitrary image URL. Legacy external
cover URLs without managed source storage are not fetched or rewritten.

Cover generation is lazy and cached locally with source version and artwork
fingerprint; normal visits reuse the derivative. Different preview pipelines
may retain their own cached copy, and previous cached sizes are not deleted.
Local Energy samples measured approximately 27–48 KB each. No original storage
location, S3 object, gallery release or email notification was changed.

Verification includes centred-logo output, unchanged original bytes, matching
concert/competition branding, cache revisions, and denied access to private or
unreleased covers. Desktop (1440px) and mobile (390px) browser checks confirmed
loaded cover images, distinct event covers, standard card crops and working
album/breadcrumb navigation.


### Photo preview keyboard navigation

Concert photo previews now support Left/Right Arrow for previous/next photos,
with first/last boundaries and focus returned to the photo when closed. Customer
and guest concert galleries share this viewer. Competition and Top Pic viewers
already support those keys. Admin concert and competition album photos now use
a shared accessible dialog instead of requiring separate image tabs, with
previous/next controls, a position counter, arrow keys, Escape and focus return.
Modified clicks can still open the image separately. The same dialog is used for
photo previews in the sample purchased-media library. Navigation covers photos
on the current gallery page, without changing gallery pagination or access.

Browser verification used an eight-photo album in both admin and customer
galleries: forward, backward, both ends of the sequence and Escape all passed
without JavaScript errors.

Verification result: the initial focused cover suite passed 52 tests (842
assertions). The full Sail suite ran 1,116 tests: 1,114 passed; the two failures
were an old thumbnail-URL expectation and a plain-text fake cover image. Both
fixtures were updated. The final affected-suite rerun passed 55 of 56 tests; its
remaining error was the temporary fake image being destroyed before it was read.
After retaining that fixture, all 5 local media tests passed (30 assertions).
All previously failing tests therefore pass on recheck. Scoped Pint and
`git diff --check` passed. Browser tests passed for customer/admin arrow-key
navigation and desktop/mobile cover displays. The local database has no unmanaged
external studio/concert cover URLs. No routes or migrations changed.


Studio-priced concert galleries hide the bulk discount card using the same
`principalPrice` eligibility flag as the displayed $1 price and checkout.
Eligible regular customers and guests retain the discount card. Removed the
studio-principal exclusion sentence from the shared gallery card entirely.
Browser check: Jesstudio Ownerberg displayed $1.00 with no bulk-discount
card; Gustomer Accountford displayed $10.00 with the card. Neither page contained
the removed exclusion wording. Existing pricing tests also check visibility for
both customer types.
Verification: full Sail suite passed 1,116 tests (11,991 assertions). Scoped Pint and diff checks passed. No routes or pricing rules changed.


Individual photo thumbnails in the account-wide cart now open the shared large
photo dialog in place for both concert and competition selections, rather than
navigating to the gallery. It supports previous/next buttons, arrow keys, Escape
and focus return without scrolling. Routine/package links keep their album
navigation. Guest concert and competition carts already used modal previews;
added arrow-key navigation and focus return to those viewers, and to the admin
customer cart preview. Cart selection, pricing, checkout and preview access
checks remain unchanged. The shared viewer uses the page CSP nonce.
Browser checks passed for the account cart at 1440px and 390px and the admin
customer cart: the requested photo loaded in a dialog, the URL and tab count
stayed unchanged, and Escape restored the same scroll position. Existing guest
cart previews continue to use their protected image endpoints. No cart contents
were changed for these browser checks.
Unavailable individual photo previews remain in the cart dialog and show a
clear message. A browser-only simulated 404 confirmed this behaviour without
changing any stored image or cart selection.
Verification: full Sail suite passed all 1,116 tests (11,997 assertions).
Scoped Pint and `git diff --check` passed. No routes, migrations or checkout
rules changed.


Individual photo cart items show their source filename beneath the item details
in account, guest and admin customer carts, for both concert and competition
photos. A shared filename component escapes the text and wraps long filenames.
Concert selections use the original filename (falling back to the display name);
competition selections reuse the existing authorised photo lookup without adding
queries. Album and video package items do not show an individual filename.
Desktop (1440px) and mobile (390px) browser checks confirmed the filename displays
without horizontal overflow and the existing in-place photo preview still works.
Admin customer cart browser checks also confirmed the correct filename and
working in-place preview. Full Sail suite passed all 1,116 tests (11,997
assertions); scoped Pint and `git diff --check` passed. No routes, migrations,
pricing or checkout behaviour changed.


### Studio-grouped concert carts (1 October 2026)

The account cart now groups all concert photos from the same studio into one
purchase group, with event subsections, each event's own date, and one studio
subtotal, bulk discount and total. The studio heading contains only its name;
there is no misleading shared event date or redundant concert subtitle. Each
studio has one Include in purchase control. Competition event cards retain
their existing format and independent pricing. Favourites retain event groups.

Cart display and account checkout now reuse the same studio-wide eligibility
that the existing concert cart already provided. Concerts across dates/years
combine for that studio's bulk threshold; other studios and competitions do not.
Principal $1 pricing remains excluded and its grouping explanation omits bulk
discounts. Discount-code allocation precedes bulk eligibility as before.

New account-order snapshots identify concert groups as studio-scoped. Individual
order lines keep their actual concert identity for reporting and fulfilment,
with an explicit purchase-group key for allocating the correct studio discount.
Payment validation and paid-cart clearing support both new studio snapshots and
existing event-scoped snapshots; only purchased selections are cleared. Admin
customer cart browsing retains its event filters and gallery links.

Targeted tests passed (10 tests, 161 assertions), including combined concert
thresholds, separate competition/other-studio totals, discount codes, line/tax
reconciliation and paid-selection clearing. Desktop/mobile browser checks cover
the two-event Energy studio cart, one purchase checkbox, save-for-later totals,
filenames, in-place photo previews and no horizontal overflow.
Full Sail suite passed all 1,117 tests (12,021 assertions). Scoped Pint and
`git diff --check` passed. Final desktop/mobile review verified the simplified
studio heading, readable filenames and per-event dates. No routes or migrations
changed.
Next: owner visual review of the grouped cart.


### Help terms categories and conditional cart discount (1 October 2026)

Implemented Help / Terms & Conditions navigation for public and signed-in Help,
with eight linked category pages. The original overview/privacy content is
preserved; new purchases, discounts, downloads, family, licence and booking
content uses the existing Website Management publishing workflow. The cart
links directly to Discount Terms & Conditions, whose tier list follows current
commerce settings. Zero Selected discounts rows are hidden on initial render
and when purchase-group selection changes.

The content migration only inserts missing pages; rollback removes untouched
seeded pages and preserves owner edits/drafts/hidden pages. It ran locally.
Feature checks passed for all categories, dynamic tiers, invalid tab handling,
private admin previews, publishing/hiding and safe migration apply/rollback.
Existing Help screenshot tests also passed. Desktop/mobile browser checks passed
for category navigation, zero/applicable/deselected discount visibility and the
cart link. Browser-only discount values were used to exercise the conditional
UI; no stored prices, selections or orders were modified.

Source references and business/legal review items are recorded in the Website
Management handbook. Existing account consent versions were not changed.
Full Sail regression suite passed all 1,121 tests (12,088 assertions). Scoped
Pint and `git diff --check` passed. No route definitions changed.
Next: owner review of the new wording before production launch.
