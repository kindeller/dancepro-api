# ADR-0020: Private photo transfer and manual Upload

Date: 2026-09-22
Status: Implemented for local development; production storage and customer release pending

Use an authenticated JPEG PUT endpoint for the first photo transfer path. Existing
concert upload services handle video packages and do not bind scheduling programme
items or photographer contributions. Keep the new path inside Programmes until
its customer-gallery bridge is implemented. No AWS operations or credential changes
are required. Local/testing enables this transport by default; other environments
require explicit PROGRAMME_PHOTO_UPLOADS_ENABLED opt-in and persistent-storage,
request-limit and image-verification capacity review. Storage is the private local
disk, not an S3 bucket or customer-visible media collection.

Create one private programme photo gallery per scheduling event on its first
successful verified upload, pinned to the immutable upload plan. Reuse it for
subsequent files and photographers on the same plan. Reject a conflicting plan;
do not silently adopt edits, move existing files or publish media. An administrator
reconciliation interface is still needed to resolve incompatible plans. Pending
reservations do not select the gallery's active plan.

Validate administrator/write scope, event membership, latest approved revision
and live photographer assignments before receipt and again after the network read.
The final transaction holds the event/programme locks through storage verification.
Stream into a temporary file, compare exact size and SHA-256, check JPEG dimensions
and end marker, and decode with GD while rejecting decoder warnings. Enforce a
60-megapixel limit plus available PHP memory before decoding. The web/PHP request
limit may be lower than the 512-MiB reservation cap and must be reviewed for deployment.

Use only server-generated UUID storage paths. Verify the stored stream's checksum
and size before recording status verified and a receipt timestamp. Contributions
move from awaiting_files to receiving; this does not complete a contribution or
release a gallery. Identical retries safely rewrite identical verified bytes at
the same path and return the same receipt. A previously received stable photo UUID
cannot switch item or bytes within its gallery. A transaction/storage failure may
leave a private orphan file; no cleanup or public link is created, and nothing
marks that file verified until a successful retry. Rollback/down removes database
metadata only, never stored originals.

Upload is the single manual entry point in the Mac app. It fingerprints the Ready
queue, syncs the working programme, validates photographer contributions and reserves
files automatically. Remove the separate Sync Programme button. Never start the
first upload on reconnect or app launch. Snapshot each exact file to a private
0700 temporary directory, compare checksum/size again, send from disk, and remove
that generated temporary copy afterward. The original event photos are untouched.
A hard process exit can leave a private OS temporary copy for later OS cleanup.

Validate returned event/plan/gallery/photo/item/contribution/checksum/size and the
verified/private states before atomically recording the local acknowledgement.
If the response is lost or saving acknowledgement fails, leave the original queued;
a later manually started session re-reserves/replays safely. Pause cancels the
current request and preparation task; Resume explicitly restarts it. Transient
network and selected gateway/rate-limit errors retry during an active session with
cancellable bounded backoff. Validation, access and storage-capacity errors pause
with an actionable message. Additional Ready actions join an active session.

Persist received-photo signatures and uploaded item locks with review state. New
photos require Ready and can upload into the locked item, but no photos in a known
uploaded item can be culled or moved in the uploader. Local Top Pics remain editable;
synchronizing later Top Pic changes to server records is still pending. A changed
local file is no longer shown as uploaded merely because its filesystem identity
is unchanged. Never treat all queued files being verified as photographer-event
completion or a multi-photographer release decision.
