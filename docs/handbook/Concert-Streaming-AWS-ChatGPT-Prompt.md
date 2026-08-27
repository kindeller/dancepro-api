# Concert Streaming AWS ChatGPT Prompt

Copy and paste the prompt below into the ChatGPT session that will help prepare
the AWS infrastructure.

```text
I need you to walk me through preparing the AWS infrastructure for DancePro V2
concert video streaming. Work interactively and explain each step before I
apply it. Do not assume that you are authorised to create or modify resources
without my explicit approval.

Important safety requirements:

- Begin with read-only discovery.
- Do not delete, replace, move or overwrite any S3 object or AWS resource.
- Do not alter the existing dance-pro-videos bucket.
- Do not weaken Block Public Access, bucket policies, IAM boundaries or origin
  protections.
- Never retrieve, display or ask me to paste a private key, access key, secret,
  token or credential into chat.
- Use a secure secret-management or deployment mechanism for private signing
  material.
- Prefer infrastructure as code when practical, but first explain the proposed
  resources and configuration.
- Ask for explicit approval before making any AWS change.

Existing context:

- AWS region is expected to be ap-southeast-2.
- The existing V1 concert bucket is dance-pro-videos.
- dance-pro-videos must remain a read-only legacy source and must not be
  migrated or reorganised as part of this task.
- The existing competition storage and delivery setup should be inspected
  read-only and used as a reference where appropriate.
- The new private V2 concert bucket should be dance-pro-concerts, subject to
  confirming that the name is available in this AWS account.
- Laravel uses the disk name s3_concerts for the new bucket.
- Laravel uses s3_concerts_legacy for dance-pro-videos.
- The application already contains HLS playback resolution and CloudFront
  signed-cookie generation. I am asking you to prepare the AWS side and provide
  the configuration values back to me.

Canonical V2 media structure:

{collection_uuid}/
├── media/
│   └── {asset_uuid}/
│       ├── original/video.mp4
│       ├── stream/master.m3u8
│       ├── stream/high.m3u8
│       ├── stream/high-init.mp4
│       ├── stream/high-{segment}.m4s
│       ├── stream/standard.m3u8
│       ├── stream/standard-init.mp4
│       ├── stream/standard-{segment}.m4s
│       ├── stream/fallback.mp4
│       └── thumbnail/poster.png
└── documents/program.pdf

Playback order:

1. stream/master.m3u8
2. stream/fallback.mp4
3. original/video.mp4

HLS details:

- Video-on-demand HLS using fragmented MP4 segments.
- Two renditions: high and standard.
- CloudFront signed cookies must authorise all files beneath only the selected
  {collection_uuid}/media/{asset_uuid}/ prefix.
- The S3 bucket must remain private.
- CloudFront should access S3 through Origin Access Control.
- The frontend will use native HLS where supported and hls.js elsewhere.
- The player sends credentials when requesting manifests and segments.
- Original downloads retain the existing protected application workflow.

Please guide me through these phases:

Phase 1 - Read-only discovery

1. Confirm the active AWS account and region without exposing credentials.
2. Inspect the existing competition bucket, CloudFront distribution, Origin
   Access Control, bucket policy, CORS, cache behaviours, logging and relevant
   IAM structure as a read-only reference.
3. Inspect whether dance-pro-concerts already exists in this account.
4. Summarise what can safely be mirrored and what should differ for HLS.

Phase 2 - Proposed design

Present the proposed resources before creating anything:

1. Private S3 bucket dance-pro-concerts in ap-southeast-2.
2. Block Public Access enabled.
3. Bucket owner enforced/object ownership without public ACLs.
4. Default encryption.
5. A considered S3 Versioning recommendation and lifecycle policy, including
   incomplete multipart-upload cleanup and non-current-version cost control.
6. CORS limited to the actual DancePro frontend origin or origins.
7. CloudFront distribution using the bucket as a private S3 origin.
8. Origin Access Control and a bucket policy restricted to that distribution.
9. HTTPS-only viewer policy.
10. A custom first-party media hostname if available.
11. A CloudFront public key and trusted key group for signed cookies.
12. Cache and response-header policies suitable for immutable HLS manifests,
    playlists, initialization files, segments, MP4 files, PNG files and PDFs.
13. Logging, monitoring and cost implications.
14. Least-privilege IAM boundaries for Laravel and the separate upload/FFmpeg
    process.

Do not create separate high and standard directories. The FFmpeg output uses
quality-prefixed filenames directly inside stream/.

Expected content types:

.m3u8  application/vnd.apple.mpegurl
.m4s   video/iso.segment
.mp4   video/mp4
.png   image/png
.pdf   application/pdf

Phase 3 - Cookie, domain and CORS design

Help me select and validate:

1. The CloudFront/custom media domain.
2. The narrowest workable signed-cookie domain.
3. Cookie path, Secure, HttpOnly and SameSite settings.
4. Exact allowed CORS origins.
5. Credentialed GET and HEAD requests for every HLS object type.
6. Any DNS certificate requirements in ACM. Remember that CloudFront ACM
   certificates must be provisioned in the AWS region required by CloudFront.

Explain the browser implications before finalising these settings.

Phase 4 - Signing setup

Guide me through creating or selecting:

1. A CloudFront signing public/private key pair.
2. The CloudFront public key.
3. The trusted key group.
4. The distribution behaviour that requires the trusted key group.
5. A secure deployment mechanism for the private key used by Laravel.
6. A key-rotation and recovery procedure.

Do not display the private key. Do not place it in source control. Return only
the key-pair/public-key ID and trusted key-group ID.

Phase 5 - Validation

Use synthetic test media only and verify:

1. Direct public S3 access is denied.
2. Unsigned CloudFront requests are denied.
3. Signed cookies load master.m3u8 and every referenced child object.
4. A cookie scoped to one asset cannot read another asset prefix.
5. Expired cookies fail.
6. High and standard variants play.
7. Automatic quality switching works under throttled bandwidth.
8. MP4 byte-range requests and seeking work.
9. CORS and content types are correct.
10. Logs do not contain signed cookie values or private signing material.

At the end, return a redacted implementation summary and these non-secret
Laravel configuration values:

AWS_CONCERT_DEFAULT_REGION=ap-southeast-2
AWS_CONCERT_BUCKET=dance-pro-concerts
AWS_CONCERT_URL=
AWS_CONCERT_ENDPOINT=
AWS_CONCERT_USE_PATH_STYLE_ENDPOINT=false

AWS_CONCERT_LEGACY_DEFAULT_REGION=ap-southeast-2
AWS_CONCERT_LEGACY_BUCKET=dance-pro-videos
AWS_CONCERT_LEGACY_URL=
AWS_CONCERT_LEGACY_ENDPOINT=
AWS_CONCERT_LEGACY_USE_PATH_STYLE_ENDPOINT=false

CLOUDFRONT_CONCERT_DOMAIN=
CLOUDFRONT_CONCERT_KEY_PAIR_ID=
CLOUDFRONT_CONCERT_PRIVATE_KEY_PATH=app/private/keys/dancepro-concerts-private.pem
CLOUDFRONT_CONCERT_COOKIE_DOMAIN=
CLOUDFRONT_CONCERT_COOKIE_PATH=/
CLOUDFRONT_CONCERT_COOKIE_SECURE=true
CLOUDFRONT_CONCERT_COOKIE_SAME_SITE=lax
CONCERT_PLAYBACK_SIGNED_URL_TTL_MINUTES=15

Also return:

- AWS account ID, region and resource ARNs/IDs, excluding secrets.
- CloudFront distribution ID.
- CloudFront domain and custom media hostname.
- Origin Access Control ID.
- CloudFront public-key ID.
- Trusted key-group ID.
- Required frontend CORS origins.
- Confirmed cookie-domain decision.
- S3 Versioning and lifecycle decisions.
- IAM policy/resource boundaries without credential values.
- The secure reference or deployment path for the signing private key, without
  its contents.
- Validation results and remaining manual actions.

Do not invent configuration values. Clearly mark anything that is still
unknown or awaiting a decision.
```

## Repository Reference

The detailed application-side handoff remains in
[Concert Streaming AWS Setup Handoff](Concert-Streaming-AWS-Setup-Handoff.md).
