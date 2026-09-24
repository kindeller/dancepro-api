# Programme automatic extraction

## Implemented workflow

- Event programme page begins with PDF upload or reading the existing attachment.
  Manual entry remains an alternative; uploading no longer needs a separate Start.
- A protected progress area polls extraction status. The dedicated queue worker
  reads text with positions, and uses local OCR for scanned pages.
- An unchanged empty draft receives proposed rows automatically. Names, numbers,
  classes and source-page references are editable beside the PDF.
- Draft edits autosave after a short pause. Approve programme saves pending edits
  and creates the existing immutable confirmation; extraction never approves.
- Failed/empty extraction leaves the PDF available and manual editing usable.
- Replacement PDFs and concurrent edits preserve the existing running order;
  extraction results appear as suggestions for comparison. Automatic reconciliation
  and a richer replacement review UI remain pending.

See [ADR-0012](../decisions/ADR-0012-Local-Programme-Extraction.md).

## Sample benchmark

All source files remain outside version control. These are observed parser outputs,
not a complete line-by-line accuracy audit:

- S24: 33 numbered items, two acts; inspected title/class pairing and wrapped titles.
- DPA J: 35 numbered items plus interval after OCR/background normalization.
  Printed weekly class times stay as class details. OCR spelling still needs review.
- SDA: 32 paired unnumbered title/class entries plus interval and presentations.
- EDC June: category/entrant columns extracted; the conflicting 6.30am/pm session
  times are flagged without changing scheduling records.
- EDC September and BUN: category headings recognised using text and indentation;
  day dates use the event year when the PDF omits it, with an explicit review note.
- SNS: sections continue across page boundaries; award categories with no section
  number are excluded and reported, rather than assigned invented section numbers.
- Narrative ballet, multi-panel concert layouts and full coverage of every supplied
  programme are not yet established. Warnings require checking source pages.

## Verification

Final Sail verification: 460 PHP tests passed (3,870 assertions), including the
five Python layout cases invoked by the feature suite. `sail artisan route:list`,
formatting, `sail npm run build` and `git diff --check` passed. Migration up/down/up
succeeded before local sample records were created. The local Supervisor worker
was verified running on the dedicated queue.

Browser verification is pending: automatic approval review denied the browser
connector access to localhost because it exposes authenticated local state. User
permission was requested. The earlier manual-editor browser check does not count
as verification of this new upload/progress/autosave flow.

A clearly labelled local Studio 24 sample event is available at
`/admin/scheduling-events/77dc78a2-9e97-494f-a334-9fbba0020761/programme`.
Its PDF is attached; use Read attached programme to exercise the full workflow.
It has not been automatically approved. Temporary benchmark copies and extracted
performer data were removed from the workspace.
Synthetic fixtures test column ordering, duos, gaps, section continuation, excluded
awards, unnumbered lists, dates and conflicting session times. PHP workflow tests
cover authorization, upload-to-extraction, no automatic approval, concurrent edits,
cancelled events, failure retention and a real synthetic PDF through Poppler.

## Runtime

`docker/programmes` includes the local tools and Supervisor worker. Rebuild with
`sail build laravel.test` and `sail up -d` on a new checkout/container. For a managed
production worker use `sail artisan queue:work programmes --queue=programmes
--timeout=180 --tries=1` in the appropriate service/container configuration.
No deployment was performed. Do not run a second worker against the same queue
without respecting the connection's 210-second reservation.

## Next

Verify the browser flow when localhost access is permitted; benchmark the remaining
layouts, improve omitted/unmatched rows and build explicit replacement reconciliation.
Do not claim full PDF understanding or skip human approval.

## Concert editor simplification

Owner feedback: concerts use one compact running-order list. Item number, title,
move and remove controls share a line; class/type/source-page fields stay under
More. Repeated item/field captions and act/group controls are removed. Legacy
draft groups are flattened in displayed order when saving or approving, retaining
item UUIDs and printed numbering restarts. Earlier confirmed snapshots remain intact.
Competition day/section structure remains available, with compact item rows.

Compact-editor verification: Sail build, view formatting and all 460 PHP tests
(3,870 assertions) passed. Browser visual verification remains unperformed under
the earlier localhost connector restriction. The owner confirmed the preceding
automatic extraction workflow works before requesting this layout refinement.

## Continuous concert folder numbering

Concert rows now display automatic folder numbers with two digits minimum (01 to
99, then 100). Move/add/remove/type changes immediately update the displayed
sequence. Printed numbers remain editable under More. Breaks do not consume a
folder number. Approval calculates the authoritative `folder_number` on the server;
old confirmed snapshots are never rewritten. Reapproval upgrades a legacy concert
by creating a new revision. Competition numbers retain their existing behaviour.

Numbering verification: all 463 Sail tests passed (3,904 assertions), including
reorder/add/remove, skipped breaks, repeated printed numbers, legacy group order,
99-to-100 formatting and immutable historical snapshots. Vite build, changed-file
Pint checks and `git diff --check` passed. No routes or migrations changed.

## Folder punctuation cleanup

Owner corrected the initial preference: clean folder names only. Editor/display
and video title-card titles must keep their punctuation. A separate derived
`folder_title` replaces slashes with spaces, removes other punctuation/symbols,
collapses spaces and preserves accented letters and numbers. More shows a preview.
Approval stores the cleaned title alongside the folder sequence; old snapshots
are unchanged. Blank cleaned titles require correction before approval.

Folder-title verification: all 474 Sail tests passed (3,941 assertions). Coverage
includes punctuation/slashes, whitespace, accents, raw title preservation in drafts
and snapshots, competition compatibility, blank cleaned titles and reapproval of
older numbered snapshots. Build, Pint and `git diff --check` passed. Browser visual
verification remains subject to the previously documented connector restriction.
