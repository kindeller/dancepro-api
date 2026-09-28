# ADR-0056: Publish concert video titles as private versioned packages

Date: 27 September 2026
Status: Accepted

## Context

The existing title editor renders 3840 × 2160 JPEGs in the administrator's browser,
including its selected colours, logo, programme text and locally installed Atlanta
font (Arial fallback). Marking a tracker task complete did not make these generated
files available to the uploader. Reimplementing rendering on the server would risk
different fonts and layouts.

## Decision

“Video titles ready” submits the browser-generated JPEG ZIP, approved revision,
content fingerprint and template settings to one publication action. The action
locks the programme, rejects stale revisions/content, validates archive entry
names/count/dimensions/size, scans and stores the archive privately, saves template
settings, and records a versioned package plus tracker completion in a database
transaction. Only successful publication completes the task. A republish refreshes
staff/time attribution and preserves previous artifacts and audit notes.

Existing programme policies and explicit `programmes:read` token ability protect
metadata/download endpoints. A package is downloadable only while the task is
complete and its revision, template and content fingerprint remain current. The
uploader pins the URL to the package and event, requires its local approved
revision, and verifies size/SHA-256 before saving. Customer routes do not expose
packages. Reopening the task withdraws downloads.

## Consequences

Browser preview and uploader files share one rendering implementation. Admins must
wait for logos/fonts to load and keep the page open while publishing. Drafts can
be previewed but cannot be published. The uploader saves a ZIP chosen by the user;
extracting it does not alter prepared programme folders.

Content invalidation tracks approved snapshot, title and logo paths plus saved
settings. Replacing image bytes at the same path or installing a different font
requires deliberate republishing. Private files are retained, including any file
left by a storage success followed by database failure; no automatic cloud delete
is introduced. A future retention policy needs a separately authorised design.
Migration down removes package metadata without deleting private files.
