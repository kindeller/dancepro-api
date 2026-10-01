# ADR-0040: Guest gallery access and release notifications

## Decision

DancePro has one public media index with Competition and Concert tabs. Released event cards are publicly discoverable, while protected folders and watermarked previews require an email address and the existing competition event code or studio gallery code. A successful code check creates an expiring guest access record and stores only its public identifier in the encrypted Laravel session. It does not create a user account.

Uncancelled events can appear from seven days before their scheduled start. Before release, the card accepts one notification registration per normalised email and event. The email address is encrypted at rest and separately hashed for deduplication. A scheduled release check sends one queued notification when the first competition section or linked concert album becomes customer-visible. The signed email link contains the registration UUID, rather than the email address, and prefills the email on the access page. The gallery code remains required.

Guest favourites and cart selections belong to a persistent lightweight guest identity scoped to the normalised email and gallery. Entering the same email with the correct current gallery code on another browser reopens that identity and its saved activity; the code is always rechecked before a new session is attached. Guest and account holders use the same gallery, product, viewer, favourites and cart views. Competition guests can select individual photos, routine photo albums, routine videos and packages, including photo multi-select, standard pricing, bulk discounts and upsell notices. When the same email creates or authenticates a customer account, the access grant and selections merge into the established customer records. Existing true selection states are preserved. Activity submitted under a different email is not transferred.

## Consequences

Coming-soon registration cannot expose media or grant gallery access. Code attempts and notification registrations are independently rate limited. Guest gallery responses are private and excluded from search indexing once unlocked. Competition readiness and concert publication continue to be evaluated by the existing gallery services, so the guest path cannot bypass normal release rules.

An unlocked browser session currently lasts 30 days. The lightweight identity and its selections remain available for a later successful email-and-code unlock. Starting checkout asks the guest to create an account or sign in; the saved cart transfers before checkout continues. Payment and original-file delivery remain to be connected. The existing customer cart constraints continue to apply after transfer.


## Studio-level concert photo access (30 September 2026)

Guests enter their email and studio code once at the studio gallery entrance.
The resulting guest session can open every released photo gallery from that studio,
including galleries released during the remaining access period. Old event access
links lead to the studio entrance; existing unexpired concert grants also work for
their studio. Event gallery navigation returns to the same studio's event cards.
Competition access remains specific to each competition; concert video access is
unchanged.

Authorisation derives the studio from the concert associated with an existing
unexpired guest access record. Event-specific guest records remain for favourites,
cart and account transfer compatibility. Opening another event attaches that event's
record with the original grant expiry, so browsing cannot renew the 30-day period.
No media storage or existing access identities are moved. Every gallery and image
request still checks release state and asset ownership; a studio grant cannot open
another studio's gallery or an unreleased event.
