# Customer profile

My Profile is individual-first: dancers may own accounts, and customers need not be
parents or members of a family group. The page is shared by competition and concert
customers and is reached through public account navigation and Add media prompts.

## Working profile sections

| Section | Stored information | Current behaviour |
| --- | --- | --- |
| My details | First/last names, preferred name, phone and account email | Prefilled editable required fields; email replacement requires current password and confirmation of the new address |
| My Family | First/last names, dancer aliases, membership, invitations and account permissions | Manager edits family members; verified invitations enable eligible purchase sharing; dancer-only profiles need no account |
| My studio | User/studio associations with verification timestamp | Add active studios using their Studio code, including released concert photos; list/remove associations |
| Email preferences | media_ready_email, expiry_reminders_email; marketing_consent_at | Explicitly saved choices; no messages sent by these endpoints |

No date of birth, payment card or identity document is collected. Dancer relationships
are descriptive, not verification of identity or family purchase permissions.

## API adapter

Existing authenticated web routes remain `GET /my/profile` and `POST /my/profile`.
The POST action selects a validated operation:

- details: name required; preferred_name and phone optional.
- preferences: explicit booleans media_ready_email, expiry_reminders_email,
  marketing_email. Existing unrelated JSON preferences are preserved.
- dancer: name required; optional relationship and programme_names.
- dancer_update: owned dancer_uuid, name, optional relationship/programme_names.
- dancer_remove: owned dancer_uuid.
- studio: active studio_uuid plus required studio_gallery_code (not the internal studio code).
- studio_remove: owned associated studio_uuid, including inactive studios.

Programme names accept an array or newline-separated textarea, trim whitespace,
remove blank/duplicate entries, and allow at most ten names of 255 characters each.
An empty programme_names submission clears saved aliases. These names now power read-only My Dancers suggestions in each registered
competition. Public dancer UUIDs prevent exposing database IDs in forms;
all updates/removals additionally scope to the authenticated account.

Profile mutations lock the user row and use one transaction. Only explicitly mapped
fields are written; user IDs, admin flags and media grants cannot be changed
through these forms. Duplicate dancer names are handled as validation errors.
Repeated dancer additions remain idempotent. Re-entering a valid studio code verifies an existing legacy association without duplicating it. Marketing opt-in preserves the
original timestamp while enabled; opt-out clears it. No terms/privacy acceptance
is inferred or changed.

Removal only deletes profile records/associations. Existing event registrations,
concert grants, orders and purchased media remain untouched. Profile changes never
transfer ownership or restrict access to other routines in an authorized event.

Migration 000011 adds and backfills dancer UUIDs plus relationship/programme_names.
Down removes only those extra columns and the UUID index; dancer names and ownership
remain intact. Existing customer_profiles columns are reused for contact/preferences.

## Plan dependencies still to implement

| Plan requirement | Remaining integration |
| --- | --- |
| My Dancers / My Studios’ Routines | My Dancers name and alias suggestions now work for ready routines; admin studio assignments now power My Studios’ Routines; richer discovery and studio-code verification remain pending. Whole Event stays available. |
| Studio gallery codes | The Studio code is shared by membership and concert photos; competition event codes and purchase entitlements remain separate |
| Family purchase sharing | Implemented with verified invitations, explicit permissions and revocation; native family management and multiple-family workflows remain follow-ups |
| Email address changes | Implemented: authenticated signed confirmation, current-password check, 60-minute expiry, old-address notification, preserved user-owned access |
| Email preferences | Future media-ready/expiry/marketing dispatchers must honour stored choices; necessary security/order messaging is separate |
| Orders/invoices | Owned order views and billing details in checkout; no unneeded address collection yet |
| Automatic local saving | Per-device preference/consent in iPhone and Android apps; not a global browser toggle |
| Guest claims | Verified email ownership and claims that preserve original entitlement clocks |

Native family management, shared carts and device download settings remain deferred.
Concert-video guest sharing has its own implemented invitation flow. This profile
slice provides the available data and editing flows without implying those systems
are complete.


## Studio membership and private gallery access

My studio now searches active studios live using `GET /my/profile/studios/search?q=…`.
The response contains public UUID, name and logo only. Two characters are required;
results are capped at 15. No studio/gallery codes or contact records are returned.

`POST /my/profile` supports:
- `action=studio`, `studio_uuid`, `studio_code`: verify the Studio code field and
  attach membership and released concert photo access, even if no gallery exists yet.
- `action=studio_request`, `studio_name`, optional `studio_location`: submit an
  unlisted/no-code studio for review. Duplicate pending requests are reused.
- `action=studio_gallery`, `studio_uuid`, `studio_gallery_code`: legacy compatibility action using the same Studio code; no separate UI control.
- `action=studio_remove`, `studio_uuid`: remove the customer's own association.

Pending requests remain visible on the requesting account. Admin review lives at
`/admin/studio-requests`; create/match approval attaches the studio without making
that customer a studio staff member. Declines require a customer-visible reason.

Membership includes released concert photos and family inheritance. Dancer/studio
recommendations can appear before competition registration, but full competition
photos/videos still need its event code. Concert video codes, purchases, expiry and
release gates remain enforced. See
[Studio membership and review](../decisions/Studio-Membership-and-Review.md).

## Profile refinement: 29 September 2026

My details prefills saved names, preferred name and phone; preferred name falls
back to First name, and phone can use the same account's crew or linked studio
contact record. These displayed detail fields are required on save. Email remains
the current sign-in address until its replacement is confirmed. Changing it
requires the current password, sends a branded link to the proposed address,
and stores pending_email plus its request time. The signed link expires after
60 minutes and requires the same signed-in account. Duplicate addresses are
checked on request and confirmation. Superseded/replayed requests cannot change
the account. Other account-email changes invalidate outstanding requests.

Successful confirmation retains the user ID, purchases and media, updates owned
Studio Hub access and linked studio-contact addresses, revokes API tokens and
other database sessions, and notifies the old address. Separate conflicting studio
invitations stop the change with an explanatory validation message. Pending email
is hidden from generic model serialization, included in privacy exports, and
cleared during account anonymisation. No live email address was changed in testing.

My studio uses a logo/name summary and top-right Not added/Complete status.
Add/change controls default to collapsed once a studio exists, with validation
errors reopening the controls. Existing multiple-studio support is retained.
Eligible studio users see the same Studio Hub component used in navigation.
Account security remains accessible when two-factor setup is disabled, with a
clear availability message instead of a 404. Configuration still controls setup.

## My Family

The web profile uses `tab=family`; `tab=dancers` remains a compatibility alias. `POST /my/profile/family` supports `save`, `manager`, `invite`, `cancel`, `remove`, and `leave`. Member identifiers are public UUIDs scoped to the authenticated manager’s family. New and edited members use first/last name, `is_dancer`, `can_view_media`, `can_purchase`, and optional programme names. Email is required for either account permission and is discarded for dancer-only profiles. Saving a new or changed account-access invitation uses Save & send invite; accepted-member edits do not resend. Pending invitations retain an explicit Resend invite control. A locked “Access galleries, favourites & cart” checkbox is shown for all account-enabled members and hidden for dancer-only profiles. Remove uses a trash button with a confirmation popup.

`GET /family/invitations/{member}/{token}` reviews an invitation; a verified matching account accepts with POST to the same path. Tokens expire in 14 days. Invitations survive web sign-in/verification through a server-held return URL. No GET grants family access. Valid invitation sessions prefill editable login email and signup first/last name and email without adding personal information to URLs. Old submitted values take priority. Acceptance retains the member identity; family names and form values then follow the linked account’s current name. Email edits do not bypass matching verified-recipient checks.

Family purchases are included in the existing purchase catalogue and authorised by the same download service. Each accepted user keeps their own favourites/cart and financial history. View permission shares eligible purchases; purchase permission gates checkout. Dancer routines use the family’s saved dancer identities. Native clients consume the existing catalogue; the web My Family page is the management surface in this change.

See [Customer family accounts](../decisions/Customer-Family-Accounts.md) for security, eligibility and rollback rules.

Accepted family members inherit the manager’s verified studios and events dynamically, including subsequent registrations and valid concert grants. Personal registrations remain independent. Release/expiry checks still apply; no Studio Hub permissions transfer. Both signup and family entry require a last name.

The My studio card shows a red **Studio not added** badge until a studio is linked, then green **Studio added**. My Family shows red **Dancer not added** when no saved family member is marked as a dancer, or green **Dancer added** when at least one is marked, including the account manager. These badges reflect saved details.

My Family and My studio tabs show a red numbered badge to the left of the tab label while their respective dancer/studio details are missing. Each badge shows **1** for the missing detail and disappears once added. Family saves preserve the current scroll position and expanded member details on the return page.

The main My Profile navigation button sums those missing items in the same red numbered badge, before its icon and label: **2** for both missing, **1** for one missing, and no badge when complete. Counts use the same rules on every customer page, including inherited family studios.

### Welcome after verification

First-time signed email verification automatically records and sends a branded account welcome. It includes profile details, studio codes, family setup/permissions, gallery access, favourites/cart, purchases/orders, concert streaming/downloads, private sharing, messages, preferences and Help. The same text is stored as an unread My Messages notification through the existing account-email source; it is not a personal conversation. Setup links are included in the email and the inbox entry links to My Media.

Verification and welcome creation are protected by an account lock. Reopening a successful verification link does not duplicate the email or notification. A delivery failure preserves verification and the inbox welcome; reopening the still-valid verification link can retry the same failed delivery. Existing verified accounts are not automatically backfilled, and changing an account email uses its existing change-confirmation flow. App download links are hidden until HTTPS store URLs are configured under `customer.mobile_apps`; no unpublished app is advertised.

## Compact notification presentation

My Messages → Notifications initially shows each notification's date, time,
Read/Unread status and subject. Native disclosure controls reveal the full message,
email delivery state and actions. Expanding marks the notification as read without
a page reload. A Mark as read button in the collapsed summary also clears it without
opening. Successful saves update the tab and main-menu counts; a failed save keeps
it unread and offers a retry. Normal form submission remains available without JavaScript. Notification and Conversation tab badges precede the
labels. Email preference checkboxes are to the left beside the label text.
