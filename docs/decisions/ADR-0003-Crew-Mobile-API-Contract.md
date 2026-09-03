# ADR-0003: Crew Mobile API Contract

## Status

Accepted for implementation.

## Context

Crew Hub functionality currently uses authenticated server-rendered web routes.
The native app needs the same business rules without coupling itself to Blade,
HTML structure or internal database identifiers. Existing unversioned APIs are
already used by media and download workflows and must not be disrupted.

## Decision

- New Crew mobile endpoints use the `/api/v1` prefix.
- The normative draft contract is `docs/api/openapi-v1.yaml`.
- Existing unversioned media and download endpoints remain unchanged.
- Mobile resources expose UUIDs, never internal numeric identifiers.
- Responses retain the shared `success`, `message`, `data` and optional `meta`
  envelope.
- Collection endpoints use cursor pagination with a maximum page size of 100.
- State-changing operations that can be retried by a mobile client require an
  `Idempotency-Key` UUID.
- A resource outside the current user's scope is returned as not found so the
  API does not disclose its existence.
- Dates and timestamps use ISO 8601; timestamps include an offset and are stored
  in UTC. The app presents them in the user's local timezone.
- Financial list/detail responses expose redacted bank identifiers only. Full
  bank details are accepted only through a separately protected update flow to
  be finalised with token and onboarding behavior.
- Offline documents use short-lived authorised downloads plus checksums. The
  app must delete cached private files on sign-out or access revocation.
- Each operation remains marked `planned` until its route, authorization,
  resources and feature tests exist.

## Consequences

The web and mobile interfaces can share Actions, Services, policies and models
while maintaining separate controllers and resources. Mobile releases can
continue using v1 while later server changes are introduced behind another API
version. The contract is intentionally broader than the currently implemented
API and must not be advertised as available until implementation status changes.
