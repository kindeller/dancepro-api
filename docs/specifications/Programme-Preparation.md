# Programme preparation: sample findings and proposed first slice

## Status and evidence

Sample inspection completed 21 September 2026. The manual review foundation and
initial local automatic extraction are now implemented. Upload proposes editable
rows through a background worker; OCR and layout interpretation have limits and
human review remains required. See the [extraction milestone](../milestones/Programme-Automatic-Extraction.md),
[ADR-0012](../decisions/ADR-0012-Local-Programme-Extraction.md) and
[API contract](../api/Programme-Preparation.md).
Read alongside the [product plan](Integrated-Media-Product-Plan.md) and
[event/programme milestone](../milestones/Integrated-Media-Event-Programme-Foundations.md).

Source folder: `/Users/davidmueller/Documents/DancePro/untitled folder 5`.
Inspected all 16 PDFs for page count/text availability (180 pages total), read
representative running-order content and visually reviewed 28 selected pages
across all 16 files. This was not a line-by-line verification of all 180 pages.
macOS PDFKit supplied local text extraction and page rendering because Poppler
and the usual Python PDF libraries were unavailable. No OCR accuracy benchmark
or external AI processing was performed. Originals were not modified or imported
into the application. Extracted text and renders are temporary local scratch data.

## Sample inventory

Page numbers below mean PDF pages, not printed page labels.

| Source | Pages | Observed structure and preparation implication |
|---|---:|---|
| BUN626.pdf | 35 | Competition; dates and sessions, numbered sections, competitors in one/two columns. Text stream detaches section numbers and interleaves entries. Session numbering repeats by date. |
| EDC626.pdf | 30 | Competition; `Item` means section/category, containing numbered competitor entries. Separate session summary, results boxes and presentations. Page 2 says Session 4 is 6.30pm; page 20 says 6.30am. Flag the conflict. |
| EDC926.pdf | 55 | Competition across September 4–6 and 11–13; timed categories and competitor entries. Sessions restart by date; a single event need not occupy consecutive days. Results boxes and awards are not competitor entries. |
| SNS626.pdf | 8 | Competition table with separate section/competitor columns. Sections continue over page breaks (section 10, pages 1–2). Page 8 awards list has competitor-like rows without section numbers; require classification rather than inventing sections. |
| BMDA 2025.pdf | 8 | Running order on pages 4–5, numbers 1–24 continue across acts. Title/class pairs; slideshow, finale, interval and unnumbered trophy presentations. Other pages include historical results and performer lists. |
| DPA J 2025.pdf | 1 | No extractable text in this inspection. Visually numbered running order restarts after interval. Many labels are classes with weekly lesson days/times, not event session times. Includes slideshows and finale. |
| DPP 2025.pdf | 1 | No extractable text. Visually numbered order restarts after interval; item titles/classes/solos plus a separate credit column, film projects, slideshows and presentations. |
| HYPE 2025.pdf | 4 | Page 3 has two acts, title/class columns and several class lines attached to one named item. Page 4 is performers/sponsors, not a running order. |
| KBBA 2025.pdf | 24 | Narrative ballet: synopsis on page 6; act/scene/role/cast detail on pages 8–9 and 15–16. Photo/video grouping cannot be inferred from every cast heading. Decorative text may extract twice. |
| NLDA E 2025.pdf | 2 | Neither page has extractable text. Visually two acts with unnumbered title/class pairs, interval and finale. Needs OCR or manual entry. |
| S24 2025 a.pdf | 2 | Numbered title/class columns, 17 entries then 16 with numbering restarted. Wrapped titles, combined song titles and class groups. Do not split every slash into a new routine. |
| SDA 2025 sml.pdf | 2 | Two unnumbered parts with 16 title/class pairs each, plus interval/presentations. Extracted text lists titles before classes; geometry is needed to pair them. |
| SRB 2025.pdf | 2 | Narrative/cast/class programme on page 1 and cast roster on page 2. Scene groups coexist with individual character credits; proposed media boundaries need human review. |
| VIT 2025.pdf | 2 | Page 2 has four sections, mostly class/team labels plus choreographer credits. No separate song title for many items. Section is an act-like grouping here, not a competition category. |
| VPC 2025.pdf | 2 | Page 2 has first half, central exam presentations and second half. Numbering restarts; centre-column award names must not become routines. Plain extraction disrupts second-half ordering. |
| XPL 2025.pdf | 2 | Page 2 has two unnumbered acts with choreographer lines between items, slideshows, special performance, finale and awards. Credits are not additional items. |

## Owner decision: title fallback

When no separate routine/song title exists, use the printed label as the initial
album title and allow renaming. Do not block preparation. This is an explicit
exception to the earlier blanket instruction against class names in photo album
titles. Where a title and class are supplied separately, keep them separate and
use the title for the album. Preserve the printed text as source evidence.

Example: a printed `Teen Ballet` item can initially be titled `Teen Ballet`;
a printed song title paired with `Teen Ballet` uses the song title, with the class
stored separately for review and optional title cards.

## Proposed record structure

Reuse the existing scheduling event, dated shifts, studio, contacts and booking
item/concert link. Keep the current event-code calculation unchanged.

A programme has a source attachment revision, editable draft, and confirmed
snapshot. Confirmation records actor/time. The snapshot consumed by the uploader
contains stable identifiers and a revision; it does not depend on PDF filenames,
folder names or extraction reading order.

Competition structure:

- Event -> programme day -> programme section -> competitor/routine entry.
- Day has a confirmed date and display order; nonconsecutive dates are allowed.
- Session label/time is optional grouping metadata within the day. Session 1 on
  different days is not the same session. It need not add another folder level.
- Section retains its authoritative number and category text. Printed `Item` or
  `Section` headings can both represent this concept.
- Entry retains competitor number and original entrant label. A duo/trio/quartet
  is one routine entry, not one item per named dancer. Repeated studio names are
  not duplicates when their competitor numbers differ.
- Store optional routine title, studio reference and participant links separately.
  Do not guess studio membership from a dancer name or invent absent information.
- Preserve gaps and source numbering; page footers, placings, adjudication rows
  and award-only lists do not automatically become deliverable routines.

Concert structure:

- Concert/performance -> ordered items. Owner feedback after trying the editor
  removes act/group controls from concerts; use one compact continuous list.
  Existing draft groups flatten on save/approval while retaining item identities
  and printed numbering. Historical confirmed snapshots remain unchanged.
- Each item has a stable identity, optional printed number, display order, title,
  optional class details and an item kind (dance, slideshow, speech/presentation,
  finale, break or other). Exact database representation is deferred to design.
- The concert editor shows title and class as separate fields on every row. Compact
  printed rows may be split using recognised trailing dance class labels, but the
  proposed values remain editable and require comparison with the source PDF.
- Printed item 1 can occur in both acts without an identity collision. Continuous
  folder numbering is displayed during review and derived again at approval. Use
  two digits minimum: 01, 02, … 99, 100. Moving, adding or removing a performance
  updates that sequence. The printed number remains under More for reference;
  breaks do not consume folder numbers.
- Preserve supplied combined titles/classes as one proposed item unless review
  establishes separate performances. Cast headings are not proof of clip breaks.
- Breaks can remain visible as programme context without generating media folders.
  Other non-dance items can be included for capture; do not silently discard them.
- A replacement PDF creates a new draft. Never replace existing routine IDs merely
  because an extraction produced a different title, order or row count.

## Proposed management workflow

1. Open the existing event and its Programme area. Reuse the protected attachment
   flow; show the selected event and source document revision. The Concert Tracker
   links its Programme cell directly to this workspace. When no source exists, the
   upload control is expanded immediately. Record and display the uploader and time.
2. Show a PDF preview next to an editable structure. Suggest relevant pages and
   allow the admin to include/exclude pages or regions (especially booklet spreads).
3. Propose rows, retaining page/region evidence. Offer manual add/paste/edit when
   extraction is missing or uncertain; do not demand another file format. The
   editor can clear the whole draft in one confirmed action without removing
   historical confirmed versions. Rereading the source may populate an empty
   draft even when confirmed history exists, but never overwrites active rows.
4. Review flags describe actual problems: unmatched title/class, duplicate number
   in the same scope, missing section assignment, conflicting date/time or ambiguous
   scene boundary. Numbering gaps are visible but do not trigger renumbering or
   repetitive confirmation. Avoid unsupported numerical confidence promises.
5. Admin corrects the draft and confirms it. Required identity/assignment conflicts
   must be resolved. Optional missing names/classes do not prevent confirmation.
6. The uploader later downloads the confirmed snapshot with Prepare Event. Extraction
   happens before event day; crew need no personal AI account or internet to use
   the downloaded structure. Role selection is not required for preparation.

Programme session times are source hints. Neither extraction nor confirmation
silently reschedules crew shifts, changes bookings, publishes photos or releases
videos. Proposed schedule discrepancies must go through the existing management
workflow separately.

## Extraction approach

These samples rule out relying only on flattened PDF text or a single numbered-line
regex. Recommend a staged pipeline: use embedded text with positions when useful;
render and OCR pages with absent/unusable text; then propose structured rows with
layout-aware interpretation. AI may assist difficult layouts, but is not the
source of truth and is not selected as a provider here.

First implement the editable structure, source preview and confirmation contract.
Then benchmark extraction against representative reviewed examples: EDC, BUN,
SNS, simple title/class concerts, raster-only concerts and narrative ballets.
Choose the production extraction/OCR tools after checking their Linux/Sail
compatibility, quality, processing time and cost. The macOS inspection method is
not a proposed dependency of the Laravel production server.

Record traceable issues and route ambiguous output to review. A failed extraction
must leave the attachment intact and still allow manual preparation. Do not
fabricate missing numbers, names, class details, dates or ballet clip boundaries.

## Implementation acceptance cases

- EDC: pair two-column entrant numbers/names correctly; results boxes are ignored.
- BUN: detach section numbers from competitor numbering using layout; preserve
  repeated studios with different numbers as separate entries.
- SNS: continue section 10 across a page break; awards-only rows require explicit
  handling rather than invented routine numbers.
- EDC September: preserve all event days across two weekends and daily session resets.
- EDC June: expose the 6.30am/pm source conflict without changing shift times.
- S24/VPC/DPA/DPP: preserve printed numbering resets while output order stays unique.
- SDA/NLDA: pair titles/classes by row even when text extraction groups columns.
- HYPE: several class lines can belong to one title.
- VIT/DPA/XPL: class-only printed labels are valid editable fallback titles;
  choreographer credits or weekly class times are not event items/times.
- KBBA/SRB: review scene/role boundaries without converting every cast name into media.
- Replacement/reorder: existing IDs and media ownership persist; prepared clients
  can detect a new confirmed revision; stale edits cannot overwrite newer work.
- Existing programme attachment access, booking-to-concert copy and concert playback
  continue to pass their tests.

Use synthetic/anonymised fixtures modelled on these layouts in automated tests;
keep full real programmes and performer rosters outside the repository.

## Existing attachment integration to address

`StoreConcertBookingProgramme` updates the booking item and linked scheduling
event, then removes the replaced attachment through the existing storage service.
Confirmed source evidence therefore needs a deliberate retention/version policy
before programme revisions rely on old attachment paths. Do not implement history
by merely referencing a file that the replacement flow removes. Preserve existing
access controls and review this integration in the foundation design.

## Next implementation

Benchmark remaining layouts and improve explicit replacement reconciliation.
Initial extraction, OCR and source-page review are implemented; page/region
selection and general narrative interpretation remain pending. See the active
[extraction milestone](../milestones/Programme-Automatic-Extraction.md) for evidence
and verification limits.

## Owner clarification: folder names and title cards

Punctuation and accent cleanup applies only to concert folder titles. Accented letters become their plain equivalents (for example `BREAKÍN` becomes `BREAKIN`), including decomposed accents from PDFs. Keep the original
punctuation in editable display titles and video title cards. Derive a separate
folder title: `/` becomes a space, other punctuation/symbols are removed, whitespace
is collapsed and trimmed, and letters/numbers remain with accent marks removed. Approved snapshots
provide `folder_title` plus `folder_number`; neither is an item identity.
