# ADR-0009: Booking concert link and programme copy

## Status

Accepted

## Context

An operational concert booking item may later become a customer-facing concert.
Booking programmes are private operational documents; customer-facing concert
media belongs in the dedicated S3 media store and is delivered by CloudFront.
The two records have different lifecycles and must not be conflated.

## Decision

A concert may link to one non-cancelled concert booking item. The link is
optional and unique. On saving a linked concert, copy a newer PDF programme
from that booking item's protected operations folder to the concert's managed
S3 programme slot. Record the source path copied so repeated saves do not
transfer unchanged files. Authorised concert visitors receive a short-lived
CloudFront URL for the copied PDF; they never receive an operations-file URL.

## Consequences

Linking does not automatically publish a concert, and the booking and concert
remain separately editable. Replacing a booking programme requires saving the
linked concert again to refresh its public copy. Future automation can trigger
that same copy action when booking programmes change. Production CloudFront
and private S3 configuration must be verified before publishing. A concert
linked to a cancelled booking event is not publicly available.
