# ADR-0002 - Concert Media Storage and Playback

## Status

Accepted.

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
stream/high.m3u8
stream/high-{segment}.m4s
stream/standard.m3u8
stream/standard-{segment}.m4s
stream/fallback.mp4
thumbnail/poster.png
```

The initial adaptive format is HLS video on demand using fragmented MP4
segments with high and standard renditions. Rendition files may remain flat
inside `stream/` when their names prevent collisions.

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

## Related Documentation

- [DancePro V1 S3 Structure](../handbook/V1-S3-Structure.md)
- [Concert Streaming AWS Setup Handoff](../handbook/Concert-Streaming-AWS-Setup-Handoff.md)
- [AWS](../handbook/AWS.md)
- [Concerts and Media Database Migration](../specifications/DancePro-V2-Concerts-Media-Database-Migration-Spec.md)
