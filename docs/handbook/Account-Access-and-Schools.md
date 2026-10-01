# Account access and schools

One DancePro sign-in can have personal media, crew and studio responsibilities.

- **Customer / dancer:** active customer, staff and crew accounts can use My Media. Event registration, access codes and purchase entitlements still control which media they can see and download. Non-admin accounts must verify their email before entering Media.
- **Staff / crew:** in Hub Management → Crew Management → Crew → Invite crew member, use **Existing DancePro account → Enable My Hub** with the account email. This adds a crew profile without changing their sign-in, customer records, existing studio access or Admin permission. No email is sent by this action. New accounts use the separate invitation form. Incomplete crew profiles must be completed in My Hub.
- **Admin:** edit the crew record and enable **Admin access**. This is additional access; My Hub and My Media remain available. Only administrators can change these settings.
- **Studio:** edit the studio in Contacts → Studios and use **Studio account access** to invite an owner, director or office contact. This is access to that particular studio, rather than an exclusive account type. Verified invited accounts gain access through the existing invitation process.

On the website, Media shows the distinct **My Hub** button when the account has a crew profile, alongside **Studio Hub** where applicable. The Admin sidebar shows **My Media** directly below **My Hub** for accounts with Media access, opening their own customer media page. Crew Hub shows **Back to Media**, with **Back to Admin** retained for admins. Mobile applications remain separate; their existing app-specific token scopes remain in force.

Crew-linked and Admin accounts cannot be merged or anonymised through the customer-only tools because those tools do not reconcile employment records. Review those records separately before closing such an account.

## School classification

When adding or editing a studio, set **Organisation type** to **School**. School records show a School badge in the studio directory. Existing records default to Dance studio, and omitting this field from an older update preserves its saved value.

The classification is stored as `studios.is_school` for future school-specific pricing and features. It does not automatically change any quote, minimum spend or invoice. School discount rates and eligibility rules still need to be agreed before pricing automation is added.

## Crew web navigation

The header has two rows: the logo, Back to Media / Back to Admin and profile controls at the top, with evenly spaced crew menu items underneath. **My Tasks** currently contains assigned concert editing work; the broader label leaves room for additional tracker tasks later. The page title and return links use the same label. On narrow screens the rows wrap to keep every control accessible.

Admin My Hub and My Media shortcuts display the supplied Crew Hub and Media icons at a matching small size, with the My Hub notification badge retained.

## Shared web sign-in

All accounts sign in at `/login`, using the shared Media heading design and supplied DancePro Media logo image. The page reads “Sign in to access your DancePro account.” Old `/customer/sign-in` links redirect here; older form submissions remain supported. Public navigation, invitations, gallery/QR links, password resets and sign-out use the shared page.

Sign-in resumes the requested page when present. Otherwise the existing account rules open Admin for administrators, My Hub for crew/staff with a crew profile, and My Media for customers (including customers with additional crew access). Authorisation, email verification, onboarding and two-factor requirements still apply. Creating an account does not grant staff or Admin permissions. Mobile app authentication is unchanged.

My Media, My Messages, My Profile, Help, sign-in and sign-up use the same heading: the supplied landscape DancePro Media image, blue account label, page title and description. Studio Hub retains its own studio logo and summary action boxes. The compact Admin sidebar shortcut icons remain unchanged.

The Media header uses the same highlighted button style for Admin Hub, My Hub and Studio Hub, with distinct shield, home and studio icons. My Media, My Messages, My Profile and Help have matching line icons beside their labels. Access rules and unread-message badges remain unchanged.

Signed-out navigation contains Media galleries followed by a highlighted Sign in button on the right; both include icons. Studios & concerts is no longer a separate header link. Its underlying pages remain accessible through gallery/account flows.

## Public account identifiers

Account IDs are UUIDs, matching the public identifier convention for studios and events. Order display numbers remain numeric. Use the account UUID, name or email to find an account; the customer directory and Message Centre also accept order display numbers. An order-number search does not also match another user's internal database number.

Existing numeric account URLs are supported for old bookmarks. The database still uses integer keys for relationships; this compatibility does not change bookings, purchases, messages or access grants. The account UUID is generated automatically and is not an editable profile field.

Customer Accounts → open an account → Overview includes **Manage studio access**
for administrators with studio-management permission. It opens the existing
studio directory: edit the relevant studio and use **Studio account access**
with the customer's email and required role. The shortcut does not grant access
or send an invitation itself.

## Selecting studio staff from customer accounts

Add studio and Edit studio now offer **Find a customer account** in each Studio
staff row. Type at least two characters of the name, email or beginning of the
public account ID. Results appear automatically; select one to fill name, email
and phone. Manual contact entry is still available.

The optional **Studio Hub access** selection grants Owner, Director or Office
contact access when the studio is saved, through the existing invitation process.
Verified accounts activate immediately; unverified accounts remain pending.
The selected account's saved email is used for access, even if additional contact
emails are entered. Re-saving unchanged access does not repeat the invitation.
Contact-only selection preserves any existing access; use the separate Studio
account access section to revoke access. Removing a contact row does not revoke
existing permissions.

Studio contacts retain a nullable user relationship. The migration leaves old
contacts unchanged and nulls the relationship if an account is deleted. Search
only returns active, non-merged customer accounts and existing admin permissions
protect both the lookup and studio-save endpoints.

## Local rehearsal account switcher

All active local accounts are listed dynamically, grouped by customer, studio,
staff and admin. Newly created accounts appear on the next page refresh without
an email-domain restriction or UUID allowlist. Account email, permissions and
verification state are unchanged; inactive/deleted accounts remain excluded.
Switching still requires an authenticated local/testing session and is unavailable
in production. Staff without a crew profile open My Media until their crew access
is configured.

## Account names and Studio Hub access emails

Account signup, My Profile, Crew Hub My Profile, admin customer contact settings,
and admin crew add/edit forms use separate First name and Last name fields. Last
name is required on customer signup and family-member forms; existing crew/admin name rules remain unchanged. Preferred name and legal name
remain separate crew details. These forms edit the same account name, so changes
are reflected across hubs. Customer and crew mobile API responses include the
name parts while retaining the full `name` field for existing clients.

Existing account names are initially split at the first space: first word as
First name, everything after it as Last name. This is a best-effort migration;
people and admins can correct multi-word first names. Explicitly entered name
parts remain intact. Personalised account, support and studio-access greetings
use the first name. The combined name remains available for search and display.
Account privacy exports include both fields; anonymisation replaces them along
with the full name.

When an administrator adds Studio Hub access, the branded email depends on the
account state:

- Verified account: **Studio Hub access added**, with the studio, assigned role,
  available actions and a direct Studio Hub button. It is not an invitation to
  create another account.
- Existing unverified account: **Your Studio Hub invitation**, asking the person
  to sign in with the specified email and verify it before access activates.
- No account: **Your Studio Hub invitation**, explaining that DancePro added them
  as a studio contact, with a Create your DancePro account button that prefills
  their email. They must verify that address before access activates.

All versions explain how to report incorrect access and provide a reply address,
account link and plain-text alternative. Owner/director emails include video
review and release; office-contact emails do not promise that permission. The
email changes do not change role permissions or the activation process.
