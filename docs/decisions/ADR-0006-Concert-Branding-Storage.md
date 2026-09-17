# ADR-0006: Managed studio and concert branding files

## Status

Accepted

## Context

The application has URL fields for studio covers, concert covers and a single
concert program, but staff need to upload and replace those files without
managing public S3 URLs. The earlier media specification allows a program
under a collection, while the current data model has one program URL per
concert.

## Decision

Store these files in the configured concert upload disk under stable,
owner-scoped keys: `studios/{uuid}/cover`, `concerts/{uuid}/cover` and
`concerts/{uuid}/documents/program.pdf`. Record the managed key and a revision
alongside the existing URL field. Serve the private objects through Laravel;
require concert access for programs. A replacement overwrites one exact key,
and clearing requires a separate confirmation before deleting that key.

## Consequences

The public URLs remain stable application routes, with a revision query string
for replacement cache busting. Existing external URLs continue to work until
replaced or cleared. A future per-collection program feature would need its
own field and storage policy. The PHP and web-server upload limits must permit
the configured 10 MB application limit. Changing the physical bucket behind
`s3_concerts` does not migrate existing objects automatically.
