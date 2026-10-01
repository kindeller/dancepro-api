# Concert family invitations

Date: 28 September 2026

## Decision

A customer with a current account grant can invite up to three distinct family
recipients per concert. They select currently released videos. This is a separate
invitation, never a grant to the customer account or a disclosure of the studio's
video code. Revoked and expired recipients still count; existing recipients can
be renewed without resetting their usage. The original grant and media visibility
are checked on every request.

Invitations require verification of the nominated email address. The public link
is an opaque UUID. Codes expire after ten minutes, have five attempts and a
one-minute resend cooldown, plus email/IP rate limits. Verification is bound to a
random identifier inside the browser's server-side session. Another verified
browser replaces the previous one; authentication session regeneration alone does
not break the binding. A short renewable playback lease limits an invitation to
one active playback window, with minute-level client activity updates.

The invitation must be accepted within fourteen days. Acceptance permits thirty
days of access, bounded by parent grant expiry, concert availability and the
original six-month streaming/twelve-month download windows. Renewal requires
fresh verification and retains the same recipient and per-video download counts.

The owner's follow-up permits one intended saved copy per video with up to three
download requests to allow for mistakes. Each request is a locked, idempotent
reservation, returning a five-minute download ticket. Retransmission of the same
request and byte-range requests do not consume another allowance. Tickets remain
session-bound and are rechecked for visibility, entitlement, studio settings and
revocation. Media is delivered from the existing recorded disk/key using existing
signed delivery, not copied or packaged into an archive.

Studios and administrators can disable sharing or downloads. Sharing defaults to
enabled, with downloads enabled, as authorised by the owner. Disabling sharing
revokes outstanding invitations; re-enabling does not silently restore them.

## Privacy and limitations

The sender, authorised studio managers and DancePro see recipient acceptance,
playback starts, last position, reported playback ending and download requests.
These are approximate activity signals, not proof of watching or saving. No raw
IP address or device fingerprint is stored in the invitation history. Contact
details are redacted 180 days after expiry/revocation by the existing scheduler;
a recipient hash and usage totals remain to enforce allowances. Account privacy
export/redaction includes family invitations without verification/session secrets.

Short-lived CDN/S3 URLs may continue briefly after revocation. A downloaded or
screen-recorded copy cannot be recalled. Download-request limits govern DancePro's
download action, not every possible copy of a playable video. The feature is not
DRM. Approximate client activity and leases are not billing or forensic metrics.
Existing objects, buckets and storage paths are unchanged.
