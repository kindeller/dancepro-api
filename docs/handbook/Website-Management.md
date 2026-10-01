# Website Management

## What is available

The public website and DancePro Media now share one navigation and account system.
Home (`/`), About (`/about`), Services (`/services`) and Help (`/help`, with `/faq`
redirecting to it) are public. Detailed service information is available at `/book`,
`/competition`, `/portfolio`, `/pose`, `/shootme`, `/promo`, `/studio` and `/shop`;
`/terms` and `/privacy` are linked in the footer. These pages use the same editor. The existing studio catalogue remains at `/studios`;
protected galleries and customer accounts keep their current access checks.
Eligible accounts see Studio Hub, Crew Hub and Admin Hub in a separate second row.

In admin, **Website Management** follows **Media management**:

- **Pages:** edit the blue heading text, title, description, introduction, optional
  public image and section cards. Add/remove/hide cards, move them up/down and change button links.
  Save draft, preview the saved draft, then Approve & publish. Remove from website
  hides a page and retains its draft; even Home can be hidden, so publish it again
  to restore the root page.
- **Events:** uncancelled competition and concert events appear automatically when
  their date range overlaps today through six calendar months ahead (inclusive,
  Perth time). Scheduling date and first/last shift dates determine the full range.
  An in-progress event remains visible through its finish date. Past events drop
  out automatically; future events enter the window without a scheduled job.
  Hide from website sets a persistent override. Restore automatic display clears
  it but never forces a past, cancelled or distant event into the window.
  Competition cards show logo, event name, start/finish and venue. Concerts show
  one logo tile per studio, without titles/dates; the section disappears if there
  are no eligible studios. Missing artwork has a labelled placeholder. Upload
  logos using the linked event/studio records. Website visibility does not release
  media or reveal crew assignments, operational notes or access codes.
- **Crew bios:** review the submitted profile biography, edit a separate public
  name, role and biography, optionally upload a public photo, then publish. Crew
  changes never replace the approved public text automatically. The previous
  approved version remains visible until replaced or removed. Inactive or deleted
  crew are excluded. The review badge appears on Crew bios and its parent menu.
  Profile photographs are not automatically made public; upload the intended
  public image in this editor. Three explicitly labelled sample biography cards
  are included in About until actual crew profiles are approved. Edit, hide or
  remove these in Pages → About; they create no fake crew accounts. The section
  type “Placeholder crew bio” places them under Meet the crew.
- **Help & FAQ:** add/edit, draft, publish and hide articles in the existing help
  library. Published answers appear both publicly and in customer Help. Only
  signed-in customers submit helpfulness feedback; public searching does not log
  anonymous personal data. Contact options route account holders into My Messages;
  guests can sign in or use the published DancePro email address.

Text is plain text and escaped on output. Section links accept local paths or
HTTPS URLs. Uploads accept JPG, PNG and WebP; uploaded files are public assets.
Replaced images are retained, not deleted from storage.

## Current website review (28 September 2026)

Reviewed the public [Home](https://www.dancepro.com.au/),
[About](https://www.dancepro.com.au/about), [Services](https://www.dancepro.com.au/services),
[Help](https://www.dancepro.com.au/faq), [concert booking information](https://www.dancepro.com.au/book),
[competition services](https://www.dancepro.com.au/competition), and
[portfolio services](https://www.dancepro.com.au/portfolio).

| Existing area | Adaptation |
|---|---|
| Separate photo/video/order destinations | Unified galleries and My Media, retaining protected access |
| Manually maintained “Where to next” | Opt-in upcoming scheduling events, automatically excluding past/cancelled dates |
| Staff biographies on About | Profile submissions with an approved public copy and review badges |
| Services and booking links | Editable service cards linking to Studio Hub, galleries or enquiries |
| FAQ and old delivery tutorials | Shared searchable help, with new account, booking, review and download instructions |
| Contact form repeated on pages | Help/contact entry into My Messages for account holders, email for guests |

The live concert booking page still describes older minimum spends and an older
photo-only arrival time. Do not copy those into the new booking rules: the current
booking summary/timeline remains the source for the agreed pricing and timing.
The old FAQ also describes previous manual delivery tools and timings. New help
answers describe the current account and download flow instead of promising old
email/ZIP delivery behaviour. No live website was changed.

Portfolio/headshot enquiries remain possible from Services. The separate Portfolio
gallery workflow is still deferred, as previously agreed. Detailed promotional
shoot booking flows, testimonial approvals, a photo-products shop and a public
portfolio showcase can be built later; this update does not claim they exist.

## Verification / next step

See the Customer Web Gallery milestone for test and browser verification results.
Next: review the starter page wording and public images in Website Management,
choose upcoming events, and approve the first crew biographies before rollout.

## Full content refresh

The September 2026 refresh expands the service pages using the existing website's
full practical topics: director background, portrait setup, photography seating,
video/audio requirements, quote/minimum spend examples, studio preparation,
competition organisation, portfolio/mini-session preparation, promotion packages,
photo days, printing, media terms and privacy. The live `/pose`, `/shootme`, `/promo`,
`/studio` and `/shop` pages were also reviewed. The shop continues to link to the
existing recommendations; no new physical-product checkout was invented.

The content uses current $800/$600 photography minimums, the $1,500-per-concert
or $15-per-family video rule, one-hour photo setup, 1½–2-hour video setup, current
Studio Hub due dates, director release, My Purchases/My Orders/My Messages,
individual concert downloads and bounded family invitations. Old Google forms,
WeTransfer promises, dated shoot offers, perpetual access claims and blanket
no-refund wording are not presented as the new workflow. Current rights wording
was checked against [ACCC consumer guarantees](https://www.accc.gov.au/consumers/buying-products-and-services/consumer-rights-and-guarantees).

Portfolio booking/selection automation remains deferred; service enquiries and
preparation details are available. Old optional promotional benefits and dated
package offers require a current booking quote rather than an assumed entitlement.

`WebsiteContentRefresh` replaces only untouched starter copy and adds missing
service pages/articles. Independent drafts and hidden pages are preserved. Its
rollback restores untouched starter copy and removes only untouched added content;
customised content is kept. Images and media objects are never deleted. The legacy
`website_visible` field is retained for compatibility; explicit hides now use
`website_hidden`. The earlier default-false visibility did not record manual hides
separately, so it is superseded by automatic display and the new override.

## Photographic website layout

Public Home, About, Services, service detail pages and public Help use the
photographic website layout. The Media heading image stays within the account
and gallery interfaces; public website headings use photographs and text. The
shared DancePro wordmark and account navigation remain available.

The curated library contains forty-one images: twenty-five real photographs selected
from the supplied 2025 concert and 2026 competition Top Pics collections, plus
fifteen existing DancePro website portfolio, headshot and studio-promotion images,
and the owner's supplied homepage photograph. Original files are
unchanged. Public derivatives are metadata-free WebP images at 640, 1280 and
2200 pixels, selected by responsive `srcset`. Source provenance, descriptions
and dimensions live in `resources/content/website-photography.json`; page hero
assignments live in `config/website.php`. These are deliberately selected public
website assets, independent of private customer galleries and their permissions.
No generated dancers or stock photographs are used. Existing website imagery
was retrieved from the public Portfolio, Shoot Me, Studio Promo and Studio Portrait
pages; the manifest records each source page and original image URL. Images are
served locally rather than hotlinked to the old website.

Page text, links, order and section visibility remain in the existing editor.
The page image upload now replaces the heading photograph; removing it restores
the curated default. Home uses an account introduction and separate concert/competition photo panels, Services uses photographic stories, About uses
paired images and crew biographies, and long detail pages have section anchors.
The automatic competition/concert event rules are unchanged.

Animations progressively enhance visible HTML: intersection reveals, small hover
transitions and browser-supported page transitions. Reduced-motion preferences
remove movement; keyboard focus, printing and no-JavaScript views keep content
accessible. Images below the heading load lazily; the heading image is prioritised.

Following visual review, each page uses distinct photographs rather than reusing
its heading image in its cards. Dance photos retain their natural proportions;
card hover effects do not zoom/crop the subjects. Hero text and full-pose images
have separate space, with edge fades and a stacked mobile composition. Supporting
About images retain their source framing instead of being forced into portrait
boxes. Service photos are mapped to their destination in `config/website.php`, so reordering services retains the correct photograph. Detail pages have distinct supporting photos instead of sharing one generic image.

The homepage's default heading photograph is the owner-selected white-hat
concert performer (`home-performance` in the photography manifest). Desktop
preserves the source composition with text on the left. Mobile crops only the
empty left stage area, keeping the performer above the headline. An uploaded
heading image still overrides this default through Website Management.


## Account-focused homepage

The homepage prioritises Sign in and Create an account for guests. Signed-in
customers, crew and administrators who can access Media instead see Open My Media
and Add a studio or event; the latter opens My Events at its Add media heading.
Browse event galleries remains a smaller public link. Existing authentication,
verification and gallery access rules still apply.

The editable homepage introduction and three steps explain creating an account,
adding a studio or event, and accessing released photos/videos. Separate concert
and competition photo panels precede the existing six-month upcoming event lists.
A smaller photographic organiser section links to Services and enquiries. The
old six-story homepage, repeated gallery banner and generic promotional outro
have been replaced. The owner's selected hero image remains unchanged.

The homepage content migration updates only fields still matching the previous
provided copy. It preserves customised fields, uploaded images, independent drafts
and hidden pages. Rollback restores unchanged replacement fields and retains later
edits. Portfolio, portrait and promotion images come from the owner's existing
website; concert and competition sections use their respective supplied collections.

## Shared website and account navigation

Public pages, customer account pages and Studio Hub use the same main navigation
partial and header styles. Guests see Home, Media galleries, Services, About, Help
and Sign in. Signed-in users with Media access see Home, My Media, My Messages,
Services, About, Help and My Profile. About uses a book icon to distinguish it
from the profile icon. Existing unread-message badges and the separate hub
shortcuts remain available.

The shared header uses the My Media colours with consistent button dimensions,
type, spacing and responsive wrapping; website-specific styles apply to page
content rather than overriding this header.

Website and Media page containers share a 1,320px maximum width with 40px
side margins, reducing to 20px at widths up to 1,000px and 18px up to 700px.
Headings, tab strips, gallery grids, account content and footers use this same
container. Studio invoices, booking timelines and video reviews also follow it;
individual forms and dialogs may remain compact within the page.

## Customer feature guides

The shared Help library now covers the customer account, gallery registration,
recommendations, purchases/download history, messages, studio invoices and director
review with eight additional editable guides. Topic navigation and direct answer
links make the guides accessible from Home, galleries and the relevant account
screens. See [Customer website feature coverage](Customer-Website-Feature-Coverage.md)
for the full feature map and features deliberately not advertised as released.

Selected Help answers include screenshot guides from the actual customer screens,
using fictional details. Text is still edited through Help & FAQ; curated image
mappings and captions live in `config/help-screenshots.php`. Hiding an article also
hides its screenshots. See the [capture instructions](../../scripts/help-screenshots/README.md)
when customer controls or layouts change.


### Help and Terms & Conditions

Help and the public terms pages share a two-option navigation control. Terms
are divided into Overview, Purchases & refunds, Discounts & credits, Downloads
& access, Family sharing, Media licences, Bookings & events and Privacy.
`/terms?tab=discounts#website-content` opens the discount category directly;
`/privacy` remains a working entry point to the Privacy category.

Each category uses the existing Website Management page editor. The overview
and privacy pages retain their existing stored content; six new `terms-*` pages
provide the other categories. Drafts remain private until published. Hiding a
page removes its category from navigation and makes its public URL unavailable.
The discount tier list is rendered from current commerce settings, not copied
into editable legal text. The cart's Discount Terms & Conditions link uses this
category. Its Selected discounts row is hidden when selected carts have no
bulk discount and updates as carts are included or saved for later.

Content was adapted from the current DancePro FAQ terms/privacy and existing
repository policy content, with delivery, studio-cart grouping, family access
and credits checked against the implemented system. Broad no-refund wording
and statements claiming a general legal ban on all audience photography were
not carried forward; consumer rights and venue rules are described separately.
Old WeTransfer instructions, fixed seven-day delivery promises and outdated
shoot-specific cancellation dates/prices were not applied to the new platform.
The new wording is a draft for business/legal review before production launch;
it does not silently change onboarding consent versions or acceptance records.

Sources reviewed 1 October 2026:
- DancePro: https://www.dancepro.com.au/faq
- Service-specific context: https://www.dancepro.com.au/competition and https://www.dancepro.com.au/portfolio
- Consumer remedies: https://www.accc.gov.au/consumers/problem-with-a-product-or-service-you-bought/repair-replace-refund-cancel
- Privacy access: https://www.oaic.gov.au/privacy/your-privacy-rights/your-personal-information/access-your-personal-information

Owner review items: confirm any service-specific cancellation schedule before
adding it to the general terms; confirm current privacy provider/country and
retention details before launch. No new cancellation fee or guaranteed delivery
time was introduced in these pages.
