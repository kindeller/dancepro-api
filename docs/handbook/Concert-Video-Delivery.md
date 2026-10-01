# Concert video delivery

## Current workflow

1. In the laptop uploader, select the concert and its edited MP4 files. Let every
   file upload and verify, then select **Finished Uploading**. The current limit
   is 5 GiB per file. The uploader retains its queue for retries.
2. In the concert tracker, complete **3.2 Concert editing** and **3.3 Final
   quality check**. In **3.4 Video uploaded**, check the verified file count and
   associate every approved studio video/slideshow with its included export.
   The page lists outstanding requirements before it can be ready for review.
3. Send **3.5 Video approval link** through the existing email task. The studio
   owner reviews the private videos in Studio Hub. Customer access remains
   unavailable until the studio releases them.
4. If the director requests corrections, make and upload the corrections,
   complete the required checks, record **Corrections complete** and
   **Corrections uploaded**, then send the corrections email for another review.
5. The studio owner releases the concert. The studio distributes its video
   access code to the applicable dancers/families. Customers add the concert
   using the code, then watch or download its released videos.

## Customer downloads

Customers can download a selected original or use **Download manager → Start**
to request all video files individually. Their browser may ask for permission to
allow multiple downloads. **Pause/Resume** controls the remaining requests;
**Reset** returns to the first file. Neither cancels files already downloading.
The browser's Downloads view shows actual progress and failures.

Streaming lasts six months and downloads last twelve months from the code-group
release date. The manager remains available after streaming expires. The concert
programme has its own link. Concert video ZIPs are not part of this workflow.

## Existing media and deployment

Existing assets retain their recorded storage disk and full key. Legacy MP4s
use short-lived signed links from that disk; new managed media retains its
configured upload destination and existing streaming delivery. No existing
originals need to be copied, moved or renamed.

The repository documents `dance-pro-videos` and UUID collection folders as the
legacy layout. This has not been verified against the live catalogue. Before
production rollout, verify the configured bucket and existing asset mapping,
then rehearse a representative concert with the existing legacy reference import
and production delivery configuration. Local tests use fake storage and captured
emails; they do not prove live S3/CloudFront or external email operation.

## Share with family

On an added concert, choose **Family sharing**, select videos (or Select all),
enter the family member's name/email, accept the displayed terms and create the
invitation. Copy the private link and send it yourself. Three recipients are
allowed per customer/concert. A family recipient verifies their email without
creating a full account.

Accept within 14 days; access then lasts up to 30 days within the concert's
original expiry. The recipient can stream and, where the studio allows it, save
one intended copy with up to three download requests per video. Retries of an
identical request are not counted twice. Renewing does not reset allowances.
The page explains the limits before acceptance and again alongside the videos.

The sender can renew/revoke invitations and view approximate activity. Studio
owners/directors can open **Family sharing settings** from released video review;
admin can open it from the concert editor. These controls apply to the whole
studio and list the current concert's customer invitations. Disabling sharing
revokes existing invitations. Already saved copies cannot be removed.

Local verification emails use the application's existing mail transport, including
Mailpit during rehearsal. No account creation, marketing subscription or change to
live video storage is involved. Production email delivery and the existing media
delivery configuration must be operational before customer rollout.
