# Customer family accounts

Date: 29 September 2026

## Decision

My Profile → My Family replaces the dancer editor. A family has one account manager and zero or more members. Each account belongs to at most one family; joining another requires leaving the current one. A manager with dependants cannot silently abandon their family by accepting another invitation. Complex shared-custody/multiple-family membership and manager transfer require a later explicit workflow.

A family member and a sign-in account are different records. Dancer-only members have no email, account or invitation. Existing `CustomerDancer` identities and programme aliases are imported lazily on the first family-page visit. Adding account access later attaches the same dancer profile to an accepted account; it never creates a password or signs in on their behalf. Old dancer endpoints remain compatible for managers and synchronise with family profiles.

The manager’s add/remove, view/download and purchase permissions are fixed on the server. Their dancer flag is editable. Only the manager can invite, edit, cancel invitations or remove members. Accepted members may leave. All account members retain their own favourites/cart. Purchase permission is enforced during order preparation and again at payment, including previously prepared drafts.

## Invitations and privacy

Rows show a locked, checked “Access galleries, favourites & cart” permission whenever either account permission is enabled, including the manager. It is hidden for dancer-only profiles and is descriptive rather than a separately stored permission. Existing gallery access and release restrictions still apply. Saving newly enabled account access sends the invitation in the same action; unchanged pending or accepted members are not invited again. Removal uses a trash button with a confirmation dialog.

Account access requires a distinct email within the family and an explicit Save & send invite action. Tokens are random, hashed at rest, expire after 14 days, rotate on resend and clear on acceptance/cancellation. Pending permission or email edits invalidate the old invitation. Acceptance is a CSRF-protected POST by the matching verified account, after normal customer onboarding. An invitation GET never grants access. Recipient email is not disclosed on the public invitation screen. The invitation explains sharing of existing and future eligible purchases before consent.

Membership does not grant Studio Hub, staff/admin roles, credits, receipts or another member’s payment details. Dancer-only profiles cannot sign in. Privacy redaction terminates family membership/sharing; exports omit invitation hashes. Account merges with family membership require deliberate resolution first.

## Invitation onboarding

The invitation card is centred within the public page margins. Opening a valid invitation stores a session-scoped reference for editable sign-in email and signup name/email defaults. No personal details are added to authentication URLs. Prefill references are rechecked against the current invitation hash, pending status and expiry; cancellation, resend and expiry invalidate old defaults. Validation errors keep the submitted edits.

Signup and family forms require first and last names. Signup name corrections are copied onto the existing member at acceptance, retaining the member UUID and dancer link. Thereafter the linked account supplies the displayed name and edit-field values, including later My details changes. Managers edit names for unlinked members; accepted members manage their own account names. Editable email defaults do not retarget invitations: acceptance still requires the verified invited address, and a mistaken invitation address must be corrected and resent by the manager.

## Purchased-media sharing

Eligible purchased media means assets covered by a ready, non-revoked entitlement on a paid/fulfilled order with a payment date. The buyer remains the owner. Accepted members with view permission can access direct purchases from the same family; borrowed media is never re-shared transitively. Existing download expiry, revoked entitlements, unavailable assets and competition-video release checks still apply. The account manager’s verified studio/event registrations and valid concert grants are inherited through the separate gallery-access service; guest-share links remain separate.

Removing a member or view permission is checked on subsequent library/download requests, including outstanding short-lived download handoffs. Previously downloaded files cannot be recalled. Orders, receipts, pricing and account credits remain buyer-specific. Individual download history is keyed by entitlement, asset and viewer so one person’s downloads do not overwrite another’s record. ZIP requests check entitlement validity before preparing an archive.

## Studio and event inheritance

Accepted account members automatically see the manager’s verified studios, registered competitions and valid concert grants, including later additions. This gallery access is available with either account permission; purchased-media sharing remains controlled by view permission. Reads use `FamilyGalleryAccess` instead of duplicating manager registrations. Each member’s independent registrations are preserved, and duplicates are collapsed in event/studio lists. Leaving/removal, manager deactivation and revoked registrations stop inherited access immediately. Pending/dancer-only members receive no account access. Studio Hub, crew and admin roles are never inherited.

Concert release, password fingerprints, grant revocation and streaming/download expiry continue to be checked by the existing concert services. Family studio links appear in My studio with an attribution to the manager and cannot be removed as though personally added. Photo-release recipient selection includes accepted family members; historical inbox matching checks membership acceptance at the send date.

## Migration/rollback

The family tables do not alter users, purchases or existing dancer identifiers. Importing legacy dancers is idempotent. Per-viewer history has a wider unique index, created before dropping the old index to preserve MySQL foreign-key support. A rollback refuses to collapse multiple viewers’ history; archive/merge that history deliberately before reverting the index. No live media is moved.
