# ADR-0029: Shared customer media home and persistent concert access

Date: 2026-09-22
Status: Accepted, intermediate legacy concert adapter

## Context

The owner confirmed signup is for all DancePro media. Competition registration and
concert access differ, but customers must not maintain separate identities. Existing
concerts already have passwords, publication/availability gates and access-grant tables.

## Decision

Use My Media as the common customer landing page. Keep competition registration separate.
Add Concert Video matches the supplied code against currently available existing
concerts and reuses password-source ConcertAccessGrant records per customer/concert.
Use user-row locking for idempotent additions. Persist a password-hash fingerprint
instead of a raw code. Live concert and grant checks apply on account-based access;
old session unlocks cannot override an unusable saved grant for that signed-in account.

Preserve first claim and expiry dates on repeat entry. Do not replace revoked, expired
or soft-deleted grants. An active grant may be reverified against a rotated code without
extending its original expiry. Account creation alone grants no media access.

## Consequences and limits

Customer access survives sessions without duplicating concert storage or account data.
Changing the existing password requires re-entry for saved access. Multiple concerts
with the same code can be added together. This initial adapter scans existing password
hashes; explicit indexed code groups remain future work. No migration is required.

Existing availability windows remain authoritative for this adapter. The planned
six-month streaming/twelve-month download split, code-group administration, automatic
later-release inclusion, reactivation and verified guest access remain unfinished.
Existing public/signed delivery remains separate; this does not redesign guest access.
