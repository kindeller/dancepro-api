# ADR-0004: Communication Data Encryption

## Status

Accepted.

## Context

DancePro stores event-chat messages, direct-chat messages and crew notification
text. These records can contain personal or operational information, but they
are not intended to contain bank details, credentials, identity-document
numbers, medical information or other high-sensitivity profile data.

Laravel application-level encryption is already used for fields whose exposure
in a database copy would create a high risk, including payment, identity,
medical and compliance details. Applying the same encrypted casts to all
communications would prevent ordinary database indexing and future search,
moderation or support tooling. It would also make every retained message depend
on long-term custody of each applicable `APP_KEY`. It would not protect message
content from a compromised running application that can access that key.

## Decision

- Chat bodies, notification titles and notification messages remain plaintext
  application columns.
- Production database volumes, managed database storage, snapshots and all
  database backups must be encrypted at rest by the infrastructure provider.
- Database connections that cross a host or private network boundary must use
  authenticated TLS.
- Database and backup access must use least-privilege production identities and
  be auditable through the selected provider.
- Application and infrastructure logs must not record chat or notification
  bodies.
- Push notifications must use minimal previews and fetch private content only
  after authenticated app access.
- Users must not use chat for passwords, access tokens, full bank details,
  identity-document numbers, medical information or other high-sensitivity
  records. The product should communicate this expectation where appropriate.
- Communication retention is reviewed under the sensitive-data retention
  policy rather than retained indefinitely by default.
- Laravel encrypted casts remain required for the existing high-sensitivity
  model attributes. This decision does not weaken those controls.

## Consequences

An unauthorised raw database or backup copy remains protected by provider-level
encryption, while authorised application features can continue to paginate,
inspect and eventually search or moderate communications without a ciphertext
migration. Database administrators with approved production access can read
communication content, so access control, audit logging, retention and operator
trust remain material controls.

The final hosting provider cannot be verified from the repository. Encrypted
database storage, snapshots, backups and network transport are production
launch checks. If future requirements classify communication content as highly
sensitive, this ADR must be revisited with a searchable-encryption or dedicated
message-encryption design and a tested key-rotation migration.
