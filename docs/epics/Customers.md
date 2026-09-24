# Customers Epic

## Current scope

Follow the [integrated media plan](../specifications/Integrated-Media-Product-Plan.md)
and [roadmap](../Roadmap.md). The first phone version must support both iPhone and
Android; framework selection remains open.

Existing active customer accounts can now select an event and enter its separate login code under
My Competitions. Registrations attach to stable event IDs and are shared foundations
for future phone clients. Registered customers can browse ready sections and private
watermarked derivatives. Admins can explicitly register events for review.

## Still to build

Guest access, phone clients,
My Dancers/My Studios filters, favourites, family sharing, commerce, purchased-media
library and local saving. Non-admin crew accounts do not yet have dual customer access.
Event registration conveys browsing access only, never an original-download entitlement.

See [customer competition access](../api/Customer-Competition-Access.md).


Media codes never grant customer access. Gallery settings manage per-event hashed
login codes. The initial media-code shortcut has been replaced and prior unverified
registrations require the proper event login code. Verified registrations remain
valid when that code is replaced.


Customer web signup is now available at `/customer/register`, with customer sign-in
at `/customer/sign-in`. Creation atomically makes a customer-only user and profile;
existing emails cannot be replaced. Sign-in reuses current session/2FA behaviour,
password reset remains the existing flow, and customer sign-out returns to customer
sign-in. No email verification, consent acceptance or media entitlement is assumed.


## Shared My Media and concert video access

Owner correction: signup is for all DancePro media, not just competitions. Signup and
customer sign-in now land at `/my/media`. Competition registration remains a separate
flow under My Competitions. My Media also offers Add Concert Video and a studio finder
for the existing concert pages/photo gallery links.

The Add Concert Video action accepts an existing studio-supplied concert code and
saves each currently released, available matching concert to the account. Saved grants
reuse `concert_access_grants`; a fresh signed-in session can access those concerts.
No public listing or studio membership automatically grants video rights. Existing
availability is preserved, and expired/revoked grants cannot be replaced by re-entry.
See [customer media access](../api/Customer-Media.md) for this first adapter's limits.


My Media now has the owner-requested Add media box across the top. My Profile supports
adding dancer names and active studios. Missing prerequisites show Add now links;
competition registration requires a dancer, and concert video entry requires a
registered studio plus selected year and video code. Concert photos link to registered
studios’ existing galleries. Native concert photo ordering is still pending.


## Expanded profile

My Profile now supports owned contact detail editing, preferred name/phone, dancer
rename/remove and programme-name aliases, descriptive dancer relationship, studio
removal and email preference storage. Email is read-only until verified changes are
implemented. Removing profile associations never removes media ownership or grants.
The [profile implementation and dependency map](../api/Customer-Profile.md) maps each
remaining family, studio-code, notification, order and device requirement to its
unfinished integration. These capabilities are not implied by saving profile data.


My Studios’ Routines now suggests ready competition albums explicitly assigned to a
studio by an administrator. It requires both event registration and the customer's
saved studio preference, but the studio association is now verified with its gallery code, not a proof of legal identity. Whole Event
remains available. Studio gallery-code verification now gates profile studio links; event and purchase access remain separate.

My Media now recommends ready routines across competition events in My Dancers and
My Studio tabs. A matching routine can show a watermarked thumbnail before its
event is registered; opening the album still requires that event's photo login
code. The purchased Photos and Videos tabs read only paid/fulfilled account order
items linked to event collections. Registered galleries and concert video grants
remain available in My Events for browsing. The checkout and purchase-delivery
flow is not yet connected to these cards.

My Events groups competitions added to the account, available photo concerts from
verified studios, valid concert video grants, and paid event purchases. Each
event appears once, with actions its current access permits. Studio and video
access remain separate; the event list does not itself grant either.

Concert video code groups now define explicit concert membership and one release
date with six months of streaming and twelve months of downloads. New customers
verify email and acknowledge DancePro's published policies; older web customers
with missing acknowledgement are prompted. A customer-only v1 API provides the
same account, profile and media flows to future iPhone and Android clients. See
[Customer mobile API](../api/Customer-Mobile.md). Guest access, reactivation and
the native phone clients remain to build.
