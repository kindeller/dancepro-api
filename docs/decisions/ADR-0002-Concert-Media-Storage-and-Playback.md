# ADR-0002 - Concert Media Storage and Playback

## Status

Accepted. The initial rendition ladder and desktop ingest boundary are amended
by [ADR-0003](ADR-0003-Desktop-Media-Ingest-and-Assignment.md).

## Context

DancePro V1 stores concert media beneath UUID-only prefixes in the
`dance-pro-videos` bucket. That convention avoids expensive S3 object copies
when mutable studio or concert details change, but V1 does not provide a
defined adaptive-streaming package or an isolated V2 operational boundary.

The earlier V2 proposal nested concert media beneath studio and concert
identifiers. Even when identifiers are immutable, embedding the ownership
hierarchy couples physical storage to a business relationship that may change.
S3 has no directory rename operation; changing a prefix requires copying and
removing objects.

HLS playback also differs from a single MP4 download. A player requests a
master manifest, variant playlists, initialization files and many segments, so
authorising only one signed URL is insufficient.

## Decision

V2 concert media will use a dedicated private `dance-pro-concerts` bucket
through the `s3_concerts` Laravel disk. The V1 `dance-pro-videos` bucket remains
available through the separate `s3_concerts_legacy` disk during migration.

V2 concert video keys use immutable collection and asset UUIDs without studio
or concert names:

```text
{collection_uuid}/media/{asset_uuid}/
```

Each managed video may contain:

```text
original/video.mp4
stream/master.m3u8
stream/720p.m3u8
stream/720p-{segment}.m4s
stream/480p.m3u8
stream/480p-{segment}.m4s
stream/fallback.mp4
thumbnail/poster.png
```

The initial adaptive format is HLS video on demand using fragmented MP4
segments. The maximum streaming rendition is 720p, with an optional 480p
rendition for adaptive bandwidth reduction. A source-quality or 1080p streaming
rendition is intentionally excluded. Rendition files may remain flat inside
`stream/` when their names prevent collisions.

The original is retained for protected download and must not be referenced by
the HLS master manifest. A compressed `stream/fallback.mp4` is the minimum
reliable playback output; HLS is optional.

Playback resolves sources in this order:

```text
HLS → fallback MP4 → canonical or recorded original MP4
```

Laravel authorises the concert and asset. CloudFront signed cookies grant
short-lived access to the selected asset prefix for HLS. Original downloads and
progressive playback retain their existing protected compatibility paths until
production delivery is fully migrated.

## Consequences

- A studio rename, concert rename or studio reassignment does not move media.
- V1 media can remain in place and migrate incrementally.
- V2 and V1 can use different IAM, lifecycle, CORS, CloudFront and cost
  policies.
- Every managed concert video requires a stable media asset UUID.
- HLS availability can be derived from expected object keys because complete
  packages are uploaded before release; no readiness column is required.
- Browser playback requires native HLS or an HLS client such as `hls.js`.
- CloudFront, its trusted key group, cookie domain, CORS and private S3 origin
  must be configured before production HLS playback is available.
- Progressive fallback prevents AWS setup or HLS failures from removing the
  existing playback capability.
- Omitting high-resolution streaming reduces stored derivatives and delivered
  bytes while retaining the source original for protected download.
- The Flutter desktop converter and uploader uses Laravel-authorised presigned
  S3 requests. It does not hold a long-lived AWS credential.

## Related Documentation

- [DancePro V1 S3 Structure](../handbook/V1-S3-Structure.md)
- [Concert Streaming AWS Setup Handoff](../handbook/Concert-Streaming-AWS-Setup-Handoff.md)
- [ADR-0003 - Desktop Media Ingest and Assignment](ADR-0003-Desktop-Media-Ingest-and-Assignment.md)
- [Flutter Desktop Media Ingest API](../specifications/Flutter-Desktop-Media-Ingest-API.md)
- [AWS](../handbook/AWS.md)
- [Concerts and Media Database Migration](../specifications/DancePro-V2-Concerts-Media-Database-Migration-Spec.md)
