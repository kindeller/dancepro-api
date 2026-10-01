# Customer website feature coverage

Reviewed 28 September 2026 against the implemented web routes, views and services.
There are no new standalone Photo or Video landing pages. Services describe what
DancePro provides; Media galleries leads into access and shopping; the shared
public/account Help library explains the customer workflow. About remains about
the business and crew, without the removed account feature section.

## Coverage map

| Customer feature | Where customers use it | Website explanation |
|---|---|---|
| Shared sign-in, account creation, email verification, password recovery, account security | Sign in / Create an account; Account security | Help: Create, verify and manage your DancePro account |
| Account navigation and role-dependent hubs | Main navigation and second hub row | Help: Website, My Media and your hubs; account setup guide |
| Profile details and email preferences | My Profile | Account setup; Notifications, Conversations and New message |
| Dancers, aliases, studio links, cross-event recommendations and improvisation | My Profile; My Events; competition gallery views | Find routines with My dancers and My studios; existing profile article |
| Register for one event or ongoing studio gallery updates; unsubscribe | Media galleries → Register now | Register for gallery updates or unlock a gallery?; collapsed gallery introduction |
| Gallery/studio codes versus released concert-video codes | Gallery access; My Events → Add media | Gallery updates guide; existing access-code articles |
| Preview media, favourites, cart and routine/photo/video packages | Gallery; cart; checkout | Existing Shopping articles; My Events/My Purchases/My Orders guide; Photo products service page |
| Current prices, eligible discounts and account credit | Product/cart/checkout totals | Existing Pricing article and account guide; prices are not duplicated in the new guides |
| Purchased media, pending fulfilment, receipts and expired-access requests | My Purchases; My Orders | Account guide and existing Orders/Downloads articles; contextual links on both tabs |
| Individual downloads, photo-folder ZIP, request history, repeat-download prompt, expiry and backups | Purchased library | Save purchases and understand download history; existing phone/technical Help |
| Released concert playlist, streaming, individual videos, multiple-file download manager and programme | Concert player | Existing Concert videos Help; linked guides beside player utilities |
| Private family invitations, three recipients, email verification, acceptance/access deadlines, retry allowance and revocation | Concert → Family sharing | Existing family-sharing Help and Terms; full limitations and activity notice on invitation and management pages; Privacy explains tracking |
| Automatic notifications, unread state, personal conversations, replies, optional message context and attachments | My Messages | Notifications, Conversations and New message; contextual link above inbox |
| Studio booking, quotes/minimum spends, preparation, final family/seat numbers, programme and supplied video uploads | Studio Hub → Make booking / Events / Event timeline | Existing Studio Hub preparation and pricing Help; concert service page |
| Studio invoice PDF, online payment when enabled, transfer instructions, pending verification and payment details | Studio Hub → Invoices | View, download and pay studio invoices; contextual invoice link |
| Director review, correction requests, updated review, release and code distribution | Studio Hub → Events → Your actions | Review, request corrections and release your concert video; contextual review link |
| Support, refunds/corrections, personal image use and privacy requests | My Messages; Help/contact | Existing Orders, Photos and Account & privacy Help, Terms and Privacy |

## Discoverability and editing

- Home retains its three account steps and photo layout, with a single link to
  the account guide beneath the steps.
- Help has category links and search. Article links use
  `#article-{slug}` and automatically expand the linked answer. Native details
  controls still work without JavaScript.
- Eight additional guides are stored in
  `resources/content/website-customer-guides.json`, installed by
  `CustomerFeatureGuides` and the additive migration. They remain editable,
  draftable and hideable through Website Management → Help & FAQ.
- Reapplying does not overwrite existing articles, including hidden ones or
  independent drafts. Rollback removes only unchanged, published new guides;
  edited/hidden/draft articles survive. Only an exact unchanged old Help intro
  is replaced; custom commerce settings are retained.
- No customer-facing route, media permissions, payment flow, storage location or
  delivery behaviour changes in this content update.

## Deliberately not advertised as available

- Native-app encrypted offline video storage/camera-roll export: client delivery
  remains separate from the working browser download and device share options.
- Automatic import of replies sent from external email apps: mailbox integration
  is not ready. Help directs users to reply inside My Messages.
- Portfolio gallery workflow: deferred and hidden when empty. The existing
  portfolio photography service page is an enquiry page, not a delivery claim.
- Guaranteed immediate delivery, permanent gallery access, universal casting/TV
  compatibility or automatic download reactivation.
- Live payment or email activation: public instructions follow the controls
  available in the account; local rehearsal mode does not charge/send live mail.

## Visual instructions

Thirteen screenshots illustrate ten relevant answers: account creation, media
navigation and Add media, studio linking, dancers, purchase filters, preferences,
messages and optional context, family invitations, the concert download manager,
and director corrections/release. Captures use the actual templates with fictional
sample details. Each has a caption, alternative text and an enlarge/zoom control.
No JavaScript is required to open the full-size image in a new tab. The screenshot
mapping follows article visibility and Help search results.

See [screenshot maintenance](../../scripts/help-screenshots/README.md) to refresh
these assets after interface changes. They are illustrations, not live account
screens; available choices can vary with account access and event state.
