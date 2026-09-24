# Customer profile

My Profile is individual-first: dancers may own accounts, and customers need not be
parents or members of a family group. The page is shared by competition and concert
customers and is reached through public account navigation and Add media prompts.

## Working profile sections

| Section | Stored information | Current behaviour |
| --- | --- | --- |
| My details | User name; profile preferred_name and phone | Edit own details; login email remains read-only |
| My dancers | Name, relationship (unspecified/self/child/other), programme_names | Add/edit/remove; unique name per account; alternate names stored for future discovery |
| My studios | User/studio associations with verification timestamp | Add active studios using a separate gallery code; list/remove inactive or unverified associations |
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
fields are written; user IDs, email, admin flags and media grants cannot be changed
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
| Studio gallery codes | Implemented as separate hashed studio codes and verified profile associations; verified studio selection alone still grants no competition event access or purchase entitlement |
| Family purchase sharing | Verified invitation/acceptance, owning-account grants, revocation and cart/payment permissions; do not use dancer relationship as authorization |
| Email address changes | Verified new-address workflow and account security checks |
| Email preferences | Future media-ready/expiry/marketing dispatchers must honour stored choices; necessary security/order messaging is separate |
| Orders/invoices | Owned order views and billing details in checkout; no unneeded address collection yet |
| Automatic local saving | Per-device preference/consent in iPhone and Android apps; not a global browser toggle |
| Guest claims | Verified email ownership and claims that preserve original entitlement clocks |

Family invitations and permissions, email verification, notification dispatch, order
views and device download settings are not exposed as working controls. This profile
slice provides the available data and editing flows without implying those systems
are complete.


## Studio verification correction

Admins manage a separate gallery access code on the studio management page. The
customer profile form requires that code to add/verify a studio. Existing associations
created before this rule remain visible with “Gallery code needed” but are excluded
from media links and routine suggestions until verified. Active-status checks are
validated before code comparison. Code attempts are throttled and never flashed back
into the form. Verified associations remain verified when the studio code changes.
No real studio code or customer verification was created during implementation.
See ADR-0031.

The public studio and concert pages retain their existing guest and password rules.
This gallery code verifies profile associations and account navigation; it does not
replace those public delivery rules or the external photo service's own controls.
