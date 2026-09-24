# ADR-0015: Local concert layouts and Ready snapshots

Date: 2026-09-21
Status: Accepted for local implementation

## Concert layout

Keep the downloaded programme.json, preparation.json and PDF unchanged. Store the
working concert programme in local-programme.json, bound to the original event,
programme and revision UUIDs. Loading review resolves this overlay before scanning.
Existing item IDs and types cannot be removed or replaced in this initial editor;
new items receive UUIDs. Display titles retain punctuation; folder names use the
existing sanitizer and continuous two-digit-minimum numbering.

Rename/reorder entire item directories, preserving all contents and filesystem
identities. Preflight destination collisions, stage directories under a unique
hidden directory, then assign their final names. Persist each operation's progress
in layout-change.json, with directory identities and the previous/new programme.
On reopening, validate the move sequence against both folder plans and resume.
If an operation completed before its progress write, accept its destination only
when the directory identity matches. Never overwrite a destination. Do not remove
staging recursively: remove it only after it is empty and the overlay is committed.
A blocked recovery stops review instead of guessing the folder mapping.

All changes are serialized through PhotoLibrary. The UI asks operators to pause
capture/copying, and known unfinished JPEGs block layout changes. This cannot lock
external camera/copy processes; concurrent writing or multiple app instances into
one prepared event remains unsupported. Uploaded records block concert editing.
Trash Undo resolves the item's current directory by ID after renumbering.

## Ready queue

Extend photo-review.json with an optional queued dictionary so previous review
files continue loading. Ready snapshots only currently stable, unuploaded photos
in the requested item/section, recording photo ID, signature, item ID and path.
Scans retain a queued entry only while its file and assignment match. New files,
changed files, moved files and renamed paths need a fresh Ready action; temporary
quiet-interval waiting on reopen does not erase an unchanged queue entry.

This is a local reviewable queue foundation, not a transfer engine. It makes no
network requests, starts nothing on reconnect, and never reports uploaded or
verified progress. Upload remains explicitly unavailable. Reuse the existing
server media-upload services only after event/item and crew-contribution mappings,
local programme reconciliation, resumable transfer and verification are defined.
No API, token, IAM, S3 or production configuration changes accompany this slice.
