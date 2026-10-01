# Integrated media roadmap

## Working reference

The [agreed product plan](specifications/Integrated-Media-Product-Plan.md) is the
user-supplied handover, preserved in full. It defines product direction, not
implemented functionality. It takes precedence over older product proposals
where they differ. Existing security rules and working behaviour remain in
force; identify compatibility changes explicitly before implementing them.

Read this file, the product plan sections relevant to the task, and the active
milestone before implementation. Do not reopen settled product decisions.
Resolve open questions when their stage needs them. Record new decisions in
the relevant specification and add an ADR only for an architectural decision.

After each implementation session, update the active milestone with completed
steps, verification, remaining work and the exact next step. Do not mark code
complete based only on a model, mockup, proposed endpoint or test filename.

## Delivery sequence

| Stage | Scope | Status / prerequisite |
|---|---|---|
| 1 | Inspect and map current systems | Initial source inspection recorded; legacy approval clarified by owner; recorder/desktop compatibility checks remain |
| 2 | Shared records and permissions | In progress; manual event/programme foundation implemented, wider records/permissions pending |
| 3 | Management media screens and programme preparation | In progress; initial local automatic extraction and review implemented |
| 4 | Mac uploader | In progress; new native app prepares events; no existing uploader to reuse; follow the eight substeps in the product plan |
| 5 | Customer website and phone app foundations | Planned together; existing crew mobile app is not the customer app |
| 6 | Commerce and fulfilment | In progress; local test checkout, account-credit ledger and authenticated tracked original delivery implemented; live Braintree, refunds, loyalty and reconciliation pending |
| 7 | Concert production and Director Hub | Planned; build from Approve button and emailed checklist baseline |
| 8 | Tracker and reporting | In progress; full concert task catalogue, manual workflow, deadlines, templates and photo release implemented; cost/sales reporting pending |
| 9 | Pilot and rollout | Planned; run all representative scenarios from product plan section 26 |

Existing foundation code supports several stages but does not make any full
stage complete. Production storage readiness remains a deployment requirement;
it does not prevent isolated local foundation work.

## Current work and next step

- Inspection: [Stage 1 implementation map](milestones/Integrated-Media-Stage-1-Inspection.md).
- Publicity TV pilot: [Fire OS slideshows and screen pairing](milestones/Publicity-TV-Slideshows.md).
- Active customer web milestone: [Customer web gallery](milestones/Customer-Web-Gallery.md).
- Portfolio galleries: **started, deferred**. Third gallery type is reserved and hidden while empty; see the Portfolio entry in the customer web milestone. Full workflow remains for a later request.
- Customer access and shared API foundation: [Customer access and mobile API](milestones/Customer-Access-and-Mobile-API.md).
- Uploader milestone: [Mac uploader concert edits and local queue](milestones/Mac-Uploader-Concert-Edits-and-Queue.md).
- Move Photos: [local reassignment](milestones/Mac-Uploader-Move-Photos.md).
- Photo review: [completed local review refinements](milestones/Mac-Uploader-Photo-Review.md).
- Preparation foundation: [Mac uploader preparation](milestones/Mac-Uploader-Preparation.md).
- Uploader visual reference: [owner-supplied four-screen draft](specifications/Mac-Uploader-Layout-Reference.md).
- Programme extraction and review remain documented in [Programme automatic extraction](milestones/Programme-Automatic-Extraction.md).
- Event and programme [foundations](milestones/Integrated-Media-Event-Programme-Foundations.md)
  remain implemented. Initial local extraction now proposes rows on PDF upload,
  with review and explicit approval. Next: remaining layout benchmarks and
  replacement reconciliation, plus browser verification when permitted.
- Keep existing event codes, scheduling records and crew/studio/booking sources.
- Owner clarification: older approval source is no longer needed. The baseline
  is an Approve button on the video page and a checklist emailed by David; richer
  Director Hub workflows remain later scope.

- Owner clarification: no Mac uploader exists. A new SwiftUI app now provides
  administrator sign-in, local preparation, offline photo review and Move Photos.
  Concert rename/add/reorder, splitting/combining items and local Ready queues are
  implemented. Manual working-programme sync now registers immutable server plans.
  Published photographer selection now comes from Crew Management and is cached
  for offline use. Programme sync now registers queued-item contributions for the
  selected photographer and reserves exact queued JPEG versions using SHA-256.
  Private local-server galleries and verified JPEG transfer are now connected.
  Upload automatically prepares the queue; Sync Programme has been removed.
  Pause/Resume, retries and persisted upload/culling locks are implemented.
  Explicit Finished Uploading Event is connected, with verified-file checks and
  reopening on later uploads. Current server progress and missing photographer
  roles are visible inside the queue window. Owner decision: media/progress belong
  to event roles, separately from the uploading account and roster personnel.
  Event-role contributions, actor auditing, automatic concert role defaults and
  explicit colleague-upload overrides are now implemented. Competition station roles
  persist across handovers; replacement copies reuse verified storage. V selection is
  available, with video transport still pending. Audited administrator photo exceptions
  now support role/day/section/item waivers and reinstatement, with automatic reopening
  on later uploads. Shared concert confirmation, explicit loading and automatic event-chat notifications
  are implemented. Competition capture-folder additions, P1/P2/V comparisons and chat
  notifications are implemented using the capture hierarchy confirmed by the owner.
  One-day competition folders now omit the day level; multi-day events retain it.
  Local video filename checks use event_section_competitor with no day. The online
  competition gallery now has an administrator browser with the same hierarchy and
  combined verified photo albums under Media → Competition Photos. Event Details
  remains for planning; Media owns uploads, galleries and orders. Private watermarked
  thumbnails/previews and read-only section contribution readiness are implemented
  for review. Separate gallery title/cover settings and change history are implemented.
  Admin-only Customer Preview now exercises automatic section visibility.
  Follow product-plan sections 7/12: no extra manual section-release approval.
  Customer event-code registration and My Competitions now support existing web
  accounts with automatic ready-section visibility. First phone version will support
  both iPhone and Android. Owner correction implemented: an event dropdown and a
  separate hashed event login code now replace media-code access. Gallery settings
  manage codes; old unverified registrations require verification. Customer web signup,
  sign-in and sign-out are implemented using existing account/session infrastructure.
  My Media is the shared account home for competitions and concerts. Add Concert
  Video now saves matching released concerts using existing codes and availability,
  with persistent account access and expiry/revocation checks. My Media now has a
  shared Add media box, with working My Profile dancer/studio registration and
  missing-detail prompts. Concert video lookup uses the selected year and registered
  studios; concert photo links open those studios’ galleries. My Profile now supports
  contact details, editable/removable dancers with programme-name aliases, studio
  removal and saved email preferences. My Family now supports verified invitations,
  eligible purchase sharing and account permissions; device-specific saving remains pending. My Dancers now suggests ready competition
  routines from saved names and aliases while Whole Event remains available.
  My Studios’ Routines now uses explicit admin studio assignments on competitor
  albums and only suggests ready sections to registered customers who saved that
  studio. A separate hashed gallery code now verifies profile studio associations;
  previous associations need verification before studio links and suggestions appear.
  My Media now has cross-event recommendations, purchased-media tabs and My Events.
  Concert code-group administration and separate streaming/download windows are
  implemented alongside email verification, customer policy onboarding and a
  shared customer mobile API. Customer web gallery work now leads the phone
  clients: a dedicated competition gallery, in-page watermarked photo viewer and
  ready improvisation view are implemented. Persistent favourites and the
  competition quote cart now cover photos, routine albums, available videos and
  packages. Verified studio customers can browse released native concert photo
  albums with private watermarked previews, and My Media links to private order
  history. Concert photo favourites and a same-studio, cross-concert quote cart
  now work as well. Ready competition photos now enter a private order
  catalogue with stable asset identities, and paid order items can freeze their
  exact photo/video assets with independent readiness-based expiry clocks.
  Immutable draft orders now snapshot exact selected assets and prices from both
  competition and concert carts, including pending package video. A local-only
  approved/declined checkout activates entitlements idempotently, and purchased
  originals use authenticated short-lived tracked links. Active, dated and
  usage-limited percentage and fixed-dollar discount codes now apply before
  bulk eligibility is calculated. Authorised studio principals receive $1
  concert-photo pricing that is excluded from bulk discounts. Reusable price
  lists now configure a GST rate and GST applicability for every product;
  discounted orders freeze and display their included GST. Order confirmations,
  pending-media ready notices and ten-day download-expiry reminders are now queued,
  deduplicated and visible in administrator delivery history; customer preferences
  govern ready and expiry messages. Next: replace the fake gateway with Braintree
  sandbox and add production payment operations before iPhone/Android screens. Administrators can now grant audited account credit by customer email or numeric account ID; customers can see and apply it after discounts, and approved payment consumes it once. Monetary refunds and customer loyalty remain separate future commerce features. Owner review
  of policy content/version and local email delivery
  remains required before launch. Guest Gallery Access is now specified with a
  Competition/Concert public index, seven-day Coming soon registrations, one-time
  release notices and cart/favourite transfer at account conversion. Guest galleries
  now share the account product, preview, favourites and cart experience; account
  conversion now leads into the local test checkout and delivery path. Existing Competition Media videos can now be linked in
  place from their year/event-code folders, while new V uploads use the same protected
  competition store and refuse to replace existing keys. Production deployment and
  end-to-end transfer validation remain pending.
  Concert photo release groups now provide the customer parent-album hierarchy.
  They support multi-concert studio galleries, Top Pic parent covers, automatic or
  manually selected routine covers, public watermarked cover thumbnails and
  cross-concert photo selection. Concert photo releases now live in the top-level
  Concert Tracker, whose date and studio views derive programme, crew, photo-upload
  and release state from source records. The full planned concert task catalogue now
  adds shared booking tasks, manual assignments, deadlines, notes, follow-ups and
  editable queued email templates. Customer help/support and audited entitlement-level
  download reactivation are now implemented. Next review the completed web gallery as
  one customer journey, then begin the shared iPhone and Android client work; live
  payments remain last.
  A searchable, category-based Help Centre foundation now includes provisional
  articles, customer feedback and unanswered-search reporting. Final article wording,
  screenshots and workflow-specific guidance are deliberately scheduled after the
  product structure and operating processes are stable.
  See [ADR-0022](decisions/ADR-0022-Event-Role-Uploader-Identity.md).

## Customer operations audit implementation — September 2026

The first audit implementation pass adds shared preference/financial definitions,
six grouped customer pages, complete paginated histories, a canonical all-order
workspace and a customer follow-up queue. Media, Sales & reports, and Customers
have distinct navigation groups. Remaining payment, permission, retention and
large-data work is tracked in the
[customer/sales audit](handbook/Customer-Sales-Workflow-Audit-2026-09-26.md#implementation-status--first-pass-26-september-2026).
Live payments remain the last stage.

## Decisions already settled

The complete rules remain in the product plan. In particular: Mac-only uploader;
manual initial Upload; offline preparation/review; JPEG photos; stable identity
independent of paths; all required photographer contributions verified before
competition photo release; private video HOLD uploads; Braintree; no historical
ShootProof migration; no first-version facial recognition. Prices and access
periods are recorded in the product plan and must not be inferred from old docs.

Concert Tracker 3.2 now uses a dedicated concert-editing workspace with the shared
tracker bar, editor assignment and audited completion. Assigned crew reach the
same workspace from My editing; their access is restricted to their assignments.
See [Concert Tracker](milestones/Concert-Tracker.md) for verification and next steps.

Concert Tracker 3.2 now provides editing-source downloads; 3.3 supports automatic
saving of 20 quality checks with admin-only Complete all; 3.4 tracks a laptop
concert-video queue, verification, explicit upload completion and studio-media
inclusions. The Mac uploader supports private MP4 exports up to 5 GiB each;
large-file multipart UI remains a follow-up. Director notification/publication
remain separate actions.

## Open decisions

Use product plan section 27 as the question register. Record answers beside the
implementing milestone rather than asking all questions upfront. Early needs:
recorder source; programme samples have now been inspected. Later
needs include customer mobile technology, mixed-media expiry, accounting,
archive costs, HOLD interaction, deadlines and the permission matrix.

Admin messaging now uses a single Message Centre for customers, studios and crew email, backed by the existing conversation records and delivery services. Crew Hub chat remains separate, with a link from the Message Centre. Five navy quick-access icons expose Availability, Tracker, My Hub, My Media and Messages. Sidebar shortcuts are grouped under Hub management and Media management. See the Customer Web Gallery milestone for verification.


Concert delivery follow-up (28 September 2026): local uploader-to-director-release
and customer-download integration is verified in the customer web gallery
milestone. Existing legacy object references are preserved. Concerts retain the
multiple-file download manager rather than ZIPs. Live catalogue mapping and
production storage/delivery verification remain rollout prerequisites.

Concert family invitations are implemented locally (28 September 2026), including
email verification, limited-time viewing, three download requests per shared video,
revocation, studio controls and approximate activity. The customer-web milestone
records verification and the remaining live media/email rollout checks. This does
not complete the separate family cart/payment-permissions scope.

Website Management is implemented locally (28 September 2026): structured page
editing with draft previews and publishing, scheduling-event visibility, approved
crew biography snapshots, and shared public/customer Help articles. Home, About
and Services share the Media navigation; role-specific hubs occupy a second row.
See the website handbook and Customer Web Gallery milestone. Public content and
images need owner review before rollout; the current live website is unchanged.

Website content refresh (28 September 2026): fuller existing-site details are now
adapted to current booking, quotes, approvals and account delivery. Homepage
events use an automatic six-month window with manual hide overrides; competitions
show dates/venues and concerts show deduplicated studio logos. About includes
editable placeholder biographies. Missing event/studio artwork remains a content
review item; Portfolio gallery automation remains deferred.

Public website visual design (28 September 2026): curated real concert/competition
photography, responsive WebP assets, photographic headings, editorial service
layouts and accessible motion. Media branding images remain in Media interfaces.
The existing page editor, published text and automatic event rules are retained.

- Homepage account access: guest sign-in/registration actions, signed-in Media
  shortcuts, three setup steps, concert/competition imagery and a smaller organiser
  section. Public website photo assignments now avoid reuse and match each service.

### Customer families (29 September 2026)

Implemented the web My Family profile, dancer-to-account upgrades, explicit verified invitations, per-member media/purchase permissions and automatic eligible-purchase sharing. Individual carts, orders and payment details remain private. Native family management screens, multiple-family membership and manager transfer are future follow-ups. See [Customer family accounts](decisions/Customer-Family-Accounts.md).
