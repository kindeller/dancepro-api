# ADR-0038: Competition video batches and routine HOLD

## Decision

The V station uploads competition recordings through a separate verified batch API. Each file is reserved by event and SHA-256, transferred directly to the configured private media store, then checked against the stored size and checksum. Finder renames therefore do not create another customer asset. A batch becomes complete only when every included file is verified and has a routine assignment. Files whose event, section or competitor identifier cannot be matched remain private for administrator assignment.

HOLD belongs to a routine, rather than a single clip. A complete batch publishes every verified clip for each routine together, unless that routine is held. The administrator can set HOLD before upload or afterward; the change is logged. Unhold exposes the complete clip set and activates paid package video entitlements that were waiting for the recording. An active HOLD also blocks previously created download entitlements from issuing new downloads; it cannot recall copies already downloaded.

## Consequences

The uploader uses a configured source folder remembered for the event and a manual upload action. Recordings modified within two minutes are excluded. The operator confirms the scanned batch, and can set HOLD before uploading a routine with missing or faulty clips. Files above 5 GiB are not yet supported by this direct PUT path and require multipart transfer before larger recordings can be handled. Later additions to a completed routine require a separate reviewed workflow because paid entitlements retain an immutable asset snapshot.

This does not implement customer checkout, payment capture, notifications or final download packaging. Those remain separate delivery work.
