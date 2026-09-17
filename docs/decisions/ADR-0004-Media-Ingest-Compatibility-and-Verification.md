# ADR-0004: Media ingest compatibility and verification

Status: Accepted

## Context

Competition clients already use V2 while concert ingest is introduced. Existing
wildcard tokens must not gain media privileges or be unexpectedly invalidated.
Multipart completion spans S3 and the database, and long HLS packages make
per-segment synchronous verification too expensive for a request.

## Decision

- Accept wildcard abilities only for existing competition-object and download-link
  operations. Retain active staff/admin policies. New tokens carry explicit
  abilities and individual expiry; preserve the optional global Sanctum policy.
- Reconcile multipart objects using recorded size and full-object CRC64NVME
  before reissuing completion. Persist completion and its audit event in one
  transaction. An unverified object is never treated as recovered.
- Validate a bounded VOD HLS package using a paginated inventory of the exact
  stream prefix, then read master and child playlists. Require declared
  resolutions, segment durations, target duration, ENDLIST and referenced objects.
  Support TS and initialized fragmented MP4 with muxed audio. Reject unsupported
  tags instead of silently accepting external dependencies or encryption.
- Restrict upload mutations to managed video collections on the dedicated upload
  disk. A legacy disk or legacy bucket alias must be rejected in application code.
- Normalize legacy listing scopes to slash-terminated prefixes and filter every
  returned key against that boundary. Imports continue to reference files in place.

## Consequences

Competition clients can continue operating; media clients need newly scoped
tokens. Existing token expiry and revocation remain effective. Rotation of old
wildcard tokens can be scheduled independently of this launch.

HLS request work is bounded by object/variant/playlist-size limits. S3 listing
requests replace thousands of individual HEAD requests; listing permission for
the managed stream prefix is required. Transient AWS latency can still fail a
request, which can be retried. This is structural verification, not transcoding
or decoding proof. Revisit background verification if packages exceed these limits.

No AWS resources, objects, policies or lifecycle rules are changed by this decision.

## References

- [S3 CompleteMultipartUpload](https://docs.aws.amazon.com/AmazonS3/latest/API/API_CompleteMultipartUpload.html)
- [HLS specification, RFC 8216](https://www.rfc-editor.org/rfc/rfc8216)
- [Desktop ingest contract](../specifications/Flutter-Desktop-Media-Ingest-API.md)
