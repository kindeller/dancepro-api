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
