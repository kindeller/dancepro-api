# Milestone 02 - Competition

## Purpose

Track delivery of the first Competition capability work after the platform
foundation is ready.

## Current Status

Initial technical scope implemented.

The Competition object browser, generic database-backed download tracking,
server-side signed redirect flow, expiry, revocation and access logging are
implemented and covered by feature tests. This milestone does not represent a
complete Competition business domain.

## Scope

- Initial Competition feature structure.
- Read-only shallow object browsing for the `s3_competitions` disk.
- Incremental object listing for the admin portal.
- Admin file selection that feeds the tracking-link creation workflow.
- Competition download tracking workflow.
- Server-side signed download redirect flow.
- Feature tests for object access, download access, expiry, revocation and
  unauthorised access.

## Links to Related Documentation

- [Competition Epic](../epics/Competition.md)
- [Competition Downloads Specification](../specifications/Competition-Downloads.md)
- [AWS](../handbook/AWS.md)
- [Security](../handbook/Security.md)
- [Testing](../handbook/Testing.md)

## Notes / Future Work

The wider Competition route shape, data model and product authorization rules
remain to be defined when Competition workflows are planned. Do not create a
Competition business model solely to replace the current storage-key-driven
workflow.
