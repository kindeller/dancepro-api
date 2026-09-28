# Customer media download experience

## Decision

DancePro records purchased-media download requests per entitlement and asset. The first download remains prominent. After the first request, the customer sees a downloaded tick, the request count and the last request date. A repeat request remains available behind a confirmation reminding the customer to check their Downloads folder or DancePro offline media.

The customer mobile client should download into encrypted application storage by default. Offline playback must use that local copy. Export to the native Photos library is a separate, explicit customer action and requires operating-system permission. The API advertises these capabilities; device-local state remains authoritative for whether a file is currently stored on that device.

The server stores request count, first and last request times, estimated bytes transferred, channel, a limited user-agent value and a one-way hash of the requesting IP address. It does not store a raw IP address. Admin download reporting displays repeat activity and estimated transfer volume.

ZIP generation for full albums should be implemented as an asynchronous, cached derivative when production object storage and delivery are connected. Generating a new archive synchronously for every click would increase processing and storage churn.

## Consequences

- Customers retain access throughout their entitlement period.
- Repeat downloads require one additional deliberate action.
- Download reporting represents issued download handoffs. It cannot prove that a browser completed writing the file.
- The mobile client can avoid repeat network transfer by retaining an encrypted offline copy.
