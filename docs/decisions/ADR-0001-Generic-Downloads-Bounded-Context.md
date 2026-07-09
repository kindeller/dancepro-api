# ADR-0001: Generic Downloads Bounded Context

## Decision

DancePro V2 will implement download tracking as a generic Downloads bounded
context under `app/Features/Downloads`, not as a Competition-owned feature.

Downloads owns public Laravel tracking links, database-backed link records,
expiry and revocation checks, access logging, and short-lived CloudFront or S3
signed URL generation.

## Context

The legacy Laravel 8 application generated direct S3 or CloudFront signed URLs
from client-submitted keys. V2 should preserve useful key-driven behaviour while
improving security, auditability, and reuse.

Competition is currently the first consumer, but private file downloads will
also be needed by Concerts, Customers, Payments, invoices, documents, and future
exports. Making downloads Competition-specific would duplicate security logic
or force unrelated domains to depend on Competition.

## Alternatives Considered

- Keep download links inside Competition. This matches the first use case, but
  couples a general file access workflow to one product area.
- Return direct signed S3 or CloudFront URLs from API endpoints. This is
  simpler, but loses Laravel-side tracking and makes revocation and access
  logging weaker.
- Wait for full domain file tables before implementing downloads. This delays
  useful secure behaviour and is unnecessary because the current app still
  works from storage keys.

## Consequences

Downloads can be reused by multiple bounded contexts and can evolve as richer
domain file tables are introduced.

The creation API currently accepts storage keys directly. Those keys must be
normalised, validated, and restricted to approved disks.

The public URL is a Laravel tracking URL. The final CloudFront or S3 URL is
short-lived, generated server-side, and only returned as a redirect.

Domains that need downloads should call into Downloads instead of duplicating
token generation, access logging, or signing logic.
