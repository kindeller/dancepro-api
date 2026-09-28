# Account access and schools

One DancePro sign-in can have personal media, crew and studio responsibilities.

- **Customer / dancer:** active customer, staff and crew accounts can use My Media. Event registration, access codes and purchase entitlements still control which media they can see and download. Non-admin accounts must verify their email before entering Media.
- **Staff / crew:** in Hub Management → Crew Management → Crew → Invite crew member, use **Existing DancePro account → Enable My Hub** with the account email. This adds a crew profile without changing their sign-in, customer records, existing studio access or Admin permission. No email is sent by this action. New accounts use the separate invitation form. Incomplete crew profiles must be completed in My Hub.
- **Admin:** edit the crew record and enable **Admin access**. This is additional access; My Hub and My Media remain available. Only administrators can change these settings.
- **Studio:** edit the studio in Contacts → Studios and use **Studio account access** to invite an owner, director or office contact. This is access to that particular studio, rather than an exclusive account type. Verified invited accounts gain access through the existing invitation process.

On the website, Media shows the distinct **My Hub** button when the account has a crew profile, alongside **Studio Hub** where applicable. Crew Hub shows **Back to Media**, with **Back to Admin** retained for admins. Mobile applications remain separate; their existing app-specific token scopes remain in force.

Crew-linked and Admin accounts cannot be merged or anonymised through the customer-only tools because those tools do not reconcile employment records. Review those records separately before closing such an account.

## School classification

When adding or editing a studio, set **Organisation type** to **School**. School records show a School badge in the studio directory. Existing records default to Dance studio, and omitting this field from an older update preserves its saved value.

The classification is stored as `studios.is_school` for future school-specific pricing and features. It does not automatically change any quote, minimum spend or invoice. School discount rates and eligibility rules still need to be agreed before pricing automation is added.

## Crew web navigation

The header has two rows: the logo, Back to Media / Back to Admin and profile controls at the top, with evenly spaced crew menu items underneath. **My Tasks** currently contains assigned concert editing work; the broader label leaves room for additional tracker tasks later. The page title and return links use the same label. On narrow screens the rows wrap to keep every control accessible.
