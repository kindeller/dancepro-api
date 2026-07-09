# Downloads Epic

## Purpose

Downloads is a reusable bounded context for creating public Laravel tracking
links to private files. It owns link records, expiry, revocation, access
logging, and server-side generation of short-lived signed asset URLs.

Downloads exists outside Competition so the same secure workflow can later be
used by Concerts, Customers, Payments, invoices, documents, reporting exports,
and other private file workflows.

## Current Scope

The current app remains storage-key driven. API clients submit approved S3
storage keys, and Downloads creates one `download_links` row per unique,
normalised key.

The public URL returned to clients is always a Laravel URL:

```text
/download/{token}
```

Opening that URL records an access attempt, validates the link, generates a
short-lived CloudFront or S3 signed URL, and redirects the user to the asset.

## Future Scope

Future bounded contexts should consume Downloads instead of reimplementing file
tracking. For example:

- Competition can request links for submitted media or result packs.
- Concert can request links for rehearsal or performance files.
- Customers can request links for private account documents.
- Payments can request links for invoices or receipts.

Future domain-owned file tables may store richer metadata and pass their
resolved storage keys into Downloads when a public tracking link is needed.

## Links to Related Documentation

- [Download Links Specification](../specifications/Download-Links.md)
- [Competition Epic](Competition.md)
- [ADR-0001 - Generic Downloads Bounded Context](../decisions/ADR-0001-Generic-Downloads-Bounded-Context.md)
- [AWS](../handbook/AWS.md)
- [Security](../handbook/Security.md)
