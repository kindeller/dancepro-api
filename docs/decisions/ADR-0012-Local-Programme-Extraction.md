# ADR-0012: Local programme extraction with review

Status: accepted for the initial automatic extraction slice, 21 September 2026.

## Decision

Run Poppler positioned-text extraction and Tesseract English OCR locally in a
bounded Python process. A dedicated Laravel database queue connection runs the
work outside the upload request. No external AI service or document transmission
is required. The PHP service streams a private retained source to a temporary
local file; the Python process interprets coordinates rather than flattened text.
Scanned pages are rendered and OCR is used when embedded text is absent/minimal.
Flat coloured backgrounds are normalized in disposable renders to recover both
white and dark text. Originals remain unchanged.

The upload creates a pending extraction record with source identity and draft
version. The result fills a still-empty, unchanged draft only; approval remains
explicit. Existing rows, concurrent edits, confirmed snapshots and source changes
prevent automatic application. In that case results are retained as suggestions
for comparison. Automatic replacement reconciliation is deferred: no matching
heuristic may silently replace established routine identities.

## Limits and failure handling

This is a deterministic layout parser, not general visual understanding. Supported
patterns include numbered concert title/class columns, paired unnumbered lists
beneath explicit act/part headings, numbered competition categories, separated
entrant columns and section/competitor tables. Narrative ballet, cast pages and
other layouts may be omitted or require correction. Zero recognised entries,
unread pages, OCR use, missing section numbers and conflicting printed session
times are surfaced. No accuracy percentage or automatic completeness claim is made.
Review every extracted row and the original pages before approval.

Limits: 100 pages, 100 MB retained-source input (new uploads remain 20 MB),
10,000 proposed nodes, 150-second process wall time, 180-second queue timeout,
210-second retry reservation, one attempt. Subprocesses have individual timeouts,
768 MB address-space and 40 MB output-file limits. Commands use argument arrays,
not shell interpolation. Original paths/command stderr are not returned to users.
A process failure retains sources and allows manual editing. Hard worker termination
may leave a private temporary input file; operational cleanup is a separate task.

## Alternatives and consequences

External AI could interpret more varied layouts, but has not been selected or
benchmarked and would add document sharing, cost and credential requirements.
Flattened text alone failed the supplied column layouts. A browser-only parser
would complicate shared review, resource limits and repeatability.

The Sail runtime is now repository-owned under `docker/programmes`, based on the
installed Sail 8.5 runtime, adding Poppler/Tesseract and a Supervisor queue worker.
Keep it aligned with Sail runtime updates. Production must install these tools
and run the dedicated worker; the change does not deploy the application.
