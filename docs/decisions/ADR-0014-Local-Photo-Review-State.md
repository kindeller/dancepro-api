# ADR-0014: Local photo review and recoverable culling

Date: 2026-09-21
Status: Accepted for initial implementation

The uploader must review JPEGs offline while capture continues. Keep review disk
work in a `PhotoReviewCore` actor, with a SwiftUI presentation model on the main
actor. Scan the approved preparation's direct media folders, reject symbolic
links, and require unchanged file metadata across a quiet interval plus a
complete JPEG before exposing review actions. Recheck metadata immediately
before preview, starring or culling.

Use a local UUID per observed filesystem identity (volume, inode and creation
time), stored alongside the event in an atomic, versioned `photo-review.json`.
Top Pics are references to original photos, not duplicate images or a public
collection. Same-volume moves retain identity; copying to another volume requires
future explicit reconciliation. This identity is local, not yet the server asset
identity or a verified upload checksum.

Use macOS Trash for culling. Persist its resulting location and the original
relative path for Undo, verify the Trash identity before restore, and refuse to
overwrite a destination. Roll back the file operation when persisting state
fails where possible; otherwise explain Finder recovery. Never empty Trash.
An interruption between the filesystem operation and metadata write can still
require Finder recovery; this is not a transactional filesystem journal.

Use one working window to avoid concurrent review actors editing the same local
state within the app. Multi-process/multi-Mac concurrent editing of a shared
prepared directory is not supported in this first slice. The later upload layer
must populate verified uploaded status before enabling culling restrictions for
server media. Review completion will not become an upload prerequisite.
