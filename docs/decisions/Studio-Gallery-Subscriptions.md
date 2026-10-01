# Studio gallery release subscriptions

Date: 28 September 2026

## Decision

Concert browsing groups the existing public event catalogue by studio. A studio page contains its released and upcoming event cards. Registering there creates a persistent studio subscription; competition registrations remain event-specific.

Store studio subscriptions separately from `guest_gallery_registrations`. Reuse the existing per-event registration/delivery records when a subscribed studio releases a new photo gallery. The existing event/email uniqueness constraint prevents repeat scheduler runs and overlapping event/studio opt-ins from generating duplicate notices.

## Behaviour

- Existing one-event consent is not expanded into ongoing studio consent. Studio registration explicitly describes future event updates and requires consent.
- A subscription stores an encrypted email, normalized email hash, consent timestamp, optional account association and unsubscribe timestamp.
- The existing every-minute gallery notification job prepares eligible released concerts and then processes pending deliveries in chunks. Eligibility uses the first published customer/public photo collection time and existing concert availability checks. Previously released galleries before the subscription date are excluded.
- Notifications continue to use signed gallery-entry links and existing access-code checks. Subscribing grants no gallery or account access.
- Studio notification emails link to a signed studio-wide unsubscribe route. Queue-time checks respect unsubscribe changes before delivery. Resubscribing starts a new consent period; repeated registration while active keeps the original start date.
- Account-associated subscribers also receive inbox notifications. Account export, redaction and merge include the studio subscription records.
- The scheduler and queue worker remain necessary for automatic delivery. Local email rehearsal behaviour is unchanged.

## Migration and compatibility

The new subscription table has a studio/email unique key. Event registration rows have an optional subscription reference. Existing event registrations and email links remain valid. Rollback removes the foreign key/column before dropping the subscription table; it removes ongoing subscription preferences but preserves event registration records.
