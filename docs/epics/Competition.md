# Competition Epic

## Purpose

Capture the product and technical direction for rebuilding DancePro competition
capabilities.

## Current Status

Competition work has not yet been fully specified. The first known technical
focus is consuming the generic Downloads feature for database-backed download
tracking.

## Scope

- Competition domain structure under `app/Features/Competition`.
- Use the generic Downloads bounded context for secure download tracking.
- Server-side generation of signed download redirects through Downloads.
- Future competition workflows as they are planned.

## Links to Related Documentation

- [Downloads Epic](Downloads.md)
- [Download Links Specification](../specifications/Download-Links.md)
- [Competition Downloads Specification](../specifications/Competition-Downloads.md)
- [Milestone 02 - Competition](../milestones/Milestone-02-Competition.md)
- [Architecture](../handbook/Architecture.md)
- [AWS](../handbook/AWS.md)
- [Security](../handbook/Security.md)

## Notes / Future Work

Add product workflows and boundaries as they are confirmed. Avoid copying
legacy Laravel 8 behaviour without reviewing it against the V2 architecture.
Do not implement download links as Competition-owned records or controllers.
