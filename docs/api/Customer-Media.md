# Shared customer media home

Competition gallery cart and favourite icons submit to the existing selection endpoint
with a JSON accept header. It returns the saved cart/favourite flags and current cart
and favourite counts so the page can update its icons and tab badges without scrolling or closing the
photo preview. Ordinary form submissions still redirect for non-JavaScript clients.
The favourites page presents saved items in a thumbnail gallery with section,
competitor, product, cart and heart controls. Clicking an individual-photo thumbnail
opens a watermarked preview on the Favourites page; closing it preserves the grid and
scroll position. Other thumbnails open the routine album. Cart rows include the same
location details, with clickable thumbnails and a bin icon. Individual cart photos
preview in place too. The large photo preview has cart and heart controls that save
in place; unfavouriting a photo removes its Favourites card when the preview closes.
When a customer-cart order is confirmed paid with a payment timestamp, its exact
snapshotted selections are removed from cart and favourites. Other saved items remain.
This rule is ready for checkout integration; live payment capture is not enabled yet.

One customer account covers competition and concert media. Customer signup, sign-in
and the public My Media navigation open `/my/media`. Admin home remains unchanged.
Competition event selection/login-code verification stays at `/my/competitions`.
Studio browsing links to the existing public studio/concert catalogue and photo links;
no native concert photo purchase/gallery entitlement is introduced here.

## Routes

- `GET /my/media`: authenticated active customer/admin home, private/no-store.
- `GET /my/media/recommendations/{event}/{item}/{photo}/thumbnail`: authenticated,
  private/no-store watermarked routine thumbnail. Requires a current verified photo,
  an automatically ready section, and a matching profile dancer name or verified
  studio assignment. It never returns an original or opens the event gallery.
- `POST /my/media/concerts`: `concert_code` and integer `year` (1900–2200), CSRF protected, ten attempts/minute.
  Requires active customer/admin. Codes retain existing concert password semantics.

AddCustomerConcertVideo matches the code against released code groups for the
selected year and verified studios. A group can include multiple selected concerts
from the same studio/year. Existing individual concert passwords remain accepted
for migration. Only enabled, published, approved and currently available concerts
are added; cancelled booking items remain excluded. Invalid codes or no eligible
matches produce a generic validation message. Submitted codes are excluded from
session old-input flashing and never stored as plaintext in grants.

## Persistence and gates

Reuse ConcertAccessGrant with source=password, user ownership, active status,
claimed timestamp and the original concert's available_until as expires_at. Metadata
stores a fingerprint of the existing password hash. User-row locking serializes
repeated additions; concert rows are locked and rechecked. Existing expiration and
claim dates are never reset. Revoked, expired or soft-deleted grants cannot be replaced
by another grant from code re-entry. An active grant can be reverified after a code
change without changing its expiry.

Group grants link to the released group and expire at its fixed twelve-month
download boundary. Their streaming check ends after six months; adding a concert
or code later does not restart either boundary. Existing individual-password grants
retain their prior expiry and fingerprint behavior.

CustomerConcertAccess checks active account, live concert availability, grant ownership,
status, revocation, expiry and group membership or legacy code fingerprint. ConcertAccessSession uses that check
when the account has a password grant, rather than falling back to old session unlocks.
Accounts without these saved grants retain the existing public concert unlock flow.
Existing signed public delivery links retain their current behaviour; this slice does
not redesign guest/signed-link transport. My Media shows only usable account grants.

## Remaining scope

Code-group administration and fixed six/twelve-month access windows now work for
newly configured groups. Later assigned concerts can be added to an account by
entering the group code again without extending earlier grants. $18 reactivation,
automatic grant creation for later releases, expiry reminders and verified guest
claims remain pending. No studio-wide access, account entitlement reset, purchase
or production storage change is performed.

Email verification and customer policy onboarding now gate new web accounts; a
shared customer mobile API exists. The first native concert photo browser is
available; concert photo cart/checkout and the iPhone and Android clients remain
pending. See [Customer mobile API](Customer-Mobile.md).


## Add media and My Profile

My Media groups competition, concert photo and concert video controls in a single
full-width box. Missing dancer/studio details show Add now links to `/my/profile`
anchors. Competition access requires a dancer record when adding an event; existing
authorized gallery browsing remains independent of profile filtering.

- `GET /my/profile`: owned dancers/studios and available active studio selector.
- `POST /my/profile`: action=dancer with name, or action=studio with studio_uuid.
  Validation and CSRF apply; user identity always comes from the session. Duplicate
  names/studios for the same account are idempotent.
- `GET /my/profile/studios/{studio}/photos`: requires a verified active studio
  and lists current concerts. Released native photo albums open internally;
  other concerts retain their existing external-gallery links.

Migration 000010 creates customer_dancers and customer_studios with user ownership
and unique user/name or user/studio pairs. Down drops those two tables only. No real
profile data is seeded. Studio registration alone does not grant video access; codes,
selected year and current availability remain required. Studio selection requires the separate gallery code and records verification; it
does not create media grants. Only verified active studio associations participate
in photo links and concert-video studio/year lookup. Dancer names are profile data, not proof of dancer identity.

Native concert photo ordering remains pending. External photo links still lead
to the existing photo service; its own access/order controls continue to apply.

## Recommendations and purchased library

Below Add media, Recommended Media has My Dancers and My Studio tabs. It searches
current competition photo galleries across events. Dancer names and programme
aliases match whole names in routine titles; studio results use explicit routine
assignments and verified profile studios. Only automatically ready routines are
shown. Each card uses a verified, watermarked routine thumbnail when available,
otherwise the event logo or a waiting placeholder. For an event not yet added to
the account, the card preselects that event in Add media and asks for its photo
login code. A recommendation alone never grants gallery or download access.

The separate My Media Photos and Videos tabs group paid/fulfilled order items by
event, using their media collection's concert or competition owner. Draft, pending,
cancelled and refunded orders, and another customer's orders, do not appear.
Collections marked mixed appear in both tabs. A paid order without a linked
collection cannot yet be classified. Competition purchase cards still require
event login before browsing when the account has no registration. Concert video
code grants and competition registrations remain in My Events; they are access,
not purchases. Checkout, purchase claiming, original downloads, and a final
competition collection-to-event identity contract are pending. Account-scoped
order history now lists pending, paid and fulfilled orders.

The web library now renders one compact card per event in each Photos/Videos tab.
Competition cards use an owned asset thumbnail where available, otherwise the
event logo, and show the event title/category and “View media”. Concert cards
show the concert title/date and the same action. `GET /my/media/library/{kind}/{event}`
combines owned assets from every paid/fulfilled order and routine for that event
in a flat album, deduplicated by asset UUID. Private thumbnail requests recheck
paid ownership and competition registration. Original download fulfilment remains
pending. In the local environment only, explicit review-profile examples can
display labelled sample cards and watermarked demo photos without creating orders
or entitlements.

## My Events

My Events follows the purchased-media tabs and replaces the separate competition
gallery and concert-video lists. It combines verified competition registrations,
published/current concert photo galleries belonging to verified profile studios,
currently valid concert video grants, and event-linked paid purchases. Events are
deduplicated and sorted by event date. A concert with both photo and video access
has one card with both actions. Concert photo-only actions use the native gallery
where published albums exist, otherwise the external link; video actions open
the concert page. Competition cards
open the authorized gallery or request the event login code when a purchase is
linked but event browsing has not been added. A verified studio does not grant
concert video access; a purchase alone does not bypass event login.

## Competition selections, concert browsing and orders

`POST /my/competitions/{event}/selections` accepts a ready routine UUID and
`favourite`, `unfavourite`, `add` or `remove`. Individual photos additionally
require a photo UUID belonging to the ready routine. The customer session and
verified event registration are mandatory. `GET /my/competitions/{event}/cart`
shows saved favourites, selected products, deduplicated pricing and bulk
discounts. Only one competition event can occupy the cart. Standalone video
requires a published, visible routine video asset within the event's 12-month
sale window; the package may be selected while video is pending. The cart is a quote;
it creates no order or payment entitlement.

`GET /my/competitions/{event}/favourites` shows saved selections separately
from the existing cart route. Both retain the verified event-access gate. The
event, My Dancers, My Studios’ Routines, Improvisation, Favourites and Cart
pages share one tab navigation; a verified profile studio logo and the event
logo appear as small tab icons when available.

`POST /my/competitions/{event}/selections/photos` accepts `action` (`add` or
`favourite`), a ready `item_uuid`, and one to 100 distinct `photo_uuids[]`.
The request requires the same verified event access as the single-selection
route. The server checks that every photo is verified and belongs to that
routine before saving them in one transaction. The Individual photos product
card starts a page-scoped multi-selection mode. Icons on each thumbnail and
in the large preview add/remove that one photo from Cart or Favourites.

`GET /my/concerts/{concert}/photos` lists published photo collections for a
verified studio customer; an optional folder UUID opens an album. Published
customer/public collections only are visible. `GET /my/concerts/{concert}/photos/assets/{asset}`
returns a private, baked-in watermarked JPEG derivative, never the original.
The first version supports private local JPEG originals. Customer photo counts
are hidden. `POST /my/concerts/{concert}/photos/selections` saves/removes photo
favourites and quote-cart choices. `GET /my/studios/{studio}/photo-cart` combines
released photos from any concert/year in the same verified studio, at $10 each
with the competition bulk tiers. It creates no order. Release-group
administration and concert photo checkout remain future work.

`GET /my/media/orders` shows only the signed-in customer's pending, paid and
fulfilled orders and their item snapshots. For order items with media
entitlements, it also shows whether each media type is pending or ready and its
expiry date. Original download access is not issued by this page.

The competition order-catalogue bridge creates deterministic private photo
collections and assets from verified files in an automatically ready routine.
It rechecks the original files and is idempotent. A paid order item can activate
an entitlement to an exact set of available assets from its source collection.
Photo access starts at the later of payment and photo readiness and expires one
year later. Video access can remain pending with no clock; when its assets are
ready, the separate 60-day clock begins. Repeated activation never changes the
frozen assets or expiry. This implements the clock rule in ADR-0034, but it does
not itself create a payment, expose originals or issue a download link. Checkout
and tracked original delivery are still pending. The download authorization
service rechecks the signed-in order owner, paid status, exact frozen asset,
readiness, expiry and asset availability before a future delivery endpoint can
issue a short-lived original-file response.

`PrepareCustomerOrder` converts a fresh authorized competition or verified
studio concert cart quote into an immutable draft. It stores the exact ready
asset UUIDs, item-level price allocations and the quoted order total. A package
has separate photo and video items, allowing video to wait without a storage
key or expiry clock. Repeating an unchanged cart returns the same draft;
changing the cart cancels that source's older draft and creates another.
The entitlement activator checks its ready assets against an order-time
snapshot when one exists. These drafts are internal preparation records; no
checkout route, payment capture or original download is enabled yet. Payment
must revalidate the draft before charging it (ADR-0035).
