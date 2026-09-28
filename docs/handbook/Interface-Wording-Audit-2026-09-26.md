# Interface wording audit — 26 September 2026

## Scope

Reviewed navigation, page titles, headings, action labels and explanatory copy across the admin, customer, studio and crew web interfaces. The source inventory covers 174 Blade templates, including shared layouts and components. Reviewed related controller messages, guest-gallery notifications and seeded Help articles where they describe the same actions.

This is a wording and navigation-consistency review, not certification of every operational workflow or external provider. Staff-authored event descriptions, handbook documents and email-template content have not been rewritten wholesale.

## Naming conventions

Use sentence case for administrative headings, tabs and buttons. Keep established customer/crew area names such as **My Media**, **My Profile**, **Studio Hub**, **My Shifts** and **My Handbook**, and publicity names **Top Pics** and **TV Slideshows**. Uppercase sidebar styling is a presentation choice; the underlying labels use the same words as the destination.

| Area | Preferred label and meaning |
| --- | --- |
| Operational dashboard | Hub dashboard |
| Download-link dashboard | Media dashboard |
| Concert video administration | Concert media |
| Concert photo administration | Concert photos |
| Competition photo administration | Competition photos |
| Stored competition files | Competition media |
| Order processing | Orders & refunds; All orders, Refunds and Payment issues pages |
| Reporting | Sales reports |
| Reporting customers tab | Customer performance; distinct from Customer accounts |
| Reporting products tab | Product performance |
| Reporting events tab | Event engagement; View activity opens Gallery visitors |
| Customer administration | Customer accounts; Customer follow-ups; Customer support |
| Download-link creation | Create download links |
| Customer assistance | Help |
| Account security | Account security in both admin and crew navigation |

Parent menu groups can be broader than the active child page: Event management contains Event bookings, Pending events and Event availability; Crew management contains Crew, Crew roles, Crew contracts, Crew training and Crew resources. Entity detail pages retain the entity's name rather than repeating the entire parent menu label.

## Changes made

- Aligned main menu labels, page titles and headings for sales, orders, customer accounts, concert media and both dashboards.
- Aligned crew/event tab labels with their destination headings and standardised administrative capitalisation.
- Distinguished customer performance reports from customer account management. Updated the shortcut from the account directory too.
- Standardised download-link creation terminology. Removed references to Laravel tracking URLs and raw tokens from the explanation, retaining the actionable instruction to copy links before leaving.
- Used **Apply filters / Clear filters** on the affected list filters. Kept destructive resets and media-player Reset controls distinct.
- Standardised **Sign out**, **Customer preview**, and the spelling **programme** for event running orders.
- Clarified that Add to account can search by customer name, email or account ID.
- Used **Event media access code**, **Gallery access code**, **Video access code** and **Concert access code** for their respective scopes, including relevant access-error messages. These remain distinct from the account password. Internal field names and API contracts are unchanged.
- Replaced customer library text claiming fulfilment still needed connecting with a current empty-state explanation.
- Reworded download history as requests, not confirmed saves; removed references to an unimplemented DancePro offline library. Clarified the same limitation and requested-byte totals in the admin customer Downloads page.
- Corrected My orders to include complimentary media, and a Help link labelled Open my order that actually opens the order list.
- Replaced the profile's future email-change promise with instructions to contact Help, reflecting the existing staff-assisted contact editing workflow.
- Replaced the crew handbook's outdated messaging roadmap text with a link to the existing My Chat page.
- Updated the Help-settings description to describe current content management.
- Refreshed three original Help articles covering finding media, access codes and global favourites/carts. The migration changes only exact original default content, preserves custom edits and stable article URLs, and has a matching guarded rollback.
- Allowed report tab groups to wrap, so the clearer labels do not hide the Downloads group at ordinary desktop widths.

## Descriptions deliberately retained

- TV playback and screen pairing are not connected yet; Publicity continues to say so.
- Test checkout explicitly says that it takes no real payment.
- Prepare selected refund records a pending provider refund; it must not be labelled Refund now or imply money has already moved.
- Production-mailbox integration and title-card font/renderer limitations remain described where relevant.
- Upload/readiness and local sample-media states remain explicit. Their wording should change only when the corresponding capability is verified.

## Follow-through

For future pages, reuse the destination label in links and headings. Button labels should describe their immediate effect: **Save**, **Send**, **Request**, **Prepare**, **Download** or **View** are not interchangeable. Preserve scope words when different access codes or payment states are involved.

Custom Help articles and email templates remain staff-managed content. The default-content migration intentionally does not overwrite edited records; those should receive editorial review before launch. This audit does not claim to validate the legal or tax advice in every article.

## Verification

The final full regression suite passed **746 tests / 6,843 assertions**. Scoped Sail Pint and `git diff --check` passed. The Help migration applied successfully to the local database. Browser spot checks cover Orders & refunds (including Refunds), Sales reports and Customer performance, Customer accounts, Create download links, My Handbook and customer Help. The initial regression run identified old literal-label expectations; those were updated without weakening their route or behaviour checks. Dedicated Help-content checks cover current defaults, stable URLs, rollback and preservation of custom edits.

## Add to account control placement

Use `x-admin.media-grant-button` for every thumbnail grant action. It has one
appearance: a 39 × 39 px translucent circular plus button, inset 10 px from the
bottom-right of the image area. This applies to competition routines/photos,
concert albums/photos, Top Pics, and customer-account favourites/cart cards.
There are no per-page position or size variants.

Wrap only the thumbnail in `.media-grant-target`. Keep the grant button beside
its image link or preview button, never nested inside either and never anchored
to the full card including its text or forms. This keeps the action in the same
place even when captions wrap or album controls expand. Ordinary Add actions
that create records are not media-grant controls.
