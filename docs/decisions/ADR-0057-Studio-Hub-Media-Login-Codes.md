# ADR-0057: Show media login codes to authorised studio managers

Date: 27 September 2026
Status: Accepted

## Context

The owner requested photo-gallery and concert-video login codes in their applicable
Studio Hub progress rows. Studio photo codes and concert code groups previously
retained hashes only. Individual concert passwords already had an encrypted copy.
Hashes cannot be recovered for display.

## Decision

Keep the existing hashes, verification rules and grants. Save a nullable encrypted
copy alongside newly saved studio photo codes and concert group codes using
Laravel's existing encrypted-cast convention. Hide both hashes and encrypted
fields from model serialization. Audit history still contains only actor/version/
action metadata. No cloud secret retrieval or infrastructure change is involved.

Only the existing authorised Studio Hub path renders these codes. Normal profile
studio membership is not management access. The page retains private/no-store
headers. Show the studio photo code on Photo gallery released, and the event's
group video code (or individual concert password if ungrouped) on Video released
to dancers. Confirm that recovered values match the current hash before display.
A group must belong to the same studio. No video row appears for DR portraits.

Configured codes can appear while release is pending, so managers can prepare
communications. Seeing a code does not publish media or bypass availability,
approval, payment or access-period checks. No code is created or rotated on page
view. Missing codes are omitted. Hash-only/unreadable/mismatched copies have a
clear unavailable message; staff can re-enter the known existing code once through
its established save action. No recovery guesses or automatic replacement codes.

## Consequences

This replaces ADR-0031's blanket no-display rule only for authorised Studio Hub
management views. Admin edit forms remain blank and generic model/API serialization
still hides codes. The migration adds nullable fields without backfilling or
changing existing hashes. Rollback removes only display copies, leaving login
verification and existing access intact. Existing encrypted individual passwords
are reused without a migration or reset.

## Staff tracker extension (27 September 2026)

The owner also requested the studio gallery login beside the communications
envelope in the admin Concert Tracker event column. This authorised staff page
reuses the same verified display-copy service and private/no-store responses.
It resolves each studio code once per page and does not substitute internal codes
when a login is missing or cannot be recovered. Generic model serialization and
public/customer pages remain unchanged.

The owner subsequently requested short random three-letter photo-gallery codes
for each studio. These are studio-scoped shared gallery codes, not account
passwords. Admin validation accepts 3–64 characters, preserving compatibility
with existing longer codes. Customer verification, hashing, encryption, and
existing access controls are unchanged.

### Video approval display requirement

At the owner's request, Studio Hub now withholds concert-video codes until the
concert's director/studio-owner approval is recorded (`approved_at`). Merely
turning off `requires_approval` does not reveal a code. Clearing approval hides it
again. This display gate applies to individual and grouped video codes and does
not alter the existing media access or release workflow. Available codes appear
as compact “Access Code:” badges to the right of the progress information.
